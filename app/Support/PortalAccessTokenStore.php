<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PortalAccessTokenStore
{
    private const CACHE_KEY_PREFIX = 'platform.oauth.tokens';

    private const BUSINESS_SESSION_HANDLE_KEY = 'platform.oauth.token_handle';

    private const CLINICAL_SESSION_HANDLE_KEY = 'platform.oauth.clinical_token_handle';

    public function __construct(
        private readonly Session $session,
        private readonly string $context = PlatformConfiguration::BUSINESS,
    ) {}

    public function accessToken(): ?string
    {
        $tokens = $this->tokens();

        if (! $tokens) {
            return null;
        }

        if ($tokens['expires_at'] > now()->getTimestamp()) {
            return $tokens['access_token'];
        }

        if (! PlatformConfiguration::isSafe($this->context)) {
            Log::error('Portal OAuth refresh was blocked by unsafe platform configuration.', [
                'context' => $this->context,
            ]);

            return null;
        }

        $cacheKey = $this->cacheKey(create: false);
        if ($cacheKey === null) {
            return null;
        }

        try {
            return Cache::lock($cacheKey.':refresh', 15)->block(5, function () use ($cacheKey): ?string {
                $tokens = $this->tokens();

                if (! $tokens) {
                    return null;
                }

                if ($tokens['expires_at'] > now()->getTimestamp()) {
                    return $tokens['access_token'];
                }

                $tokens = Cache::lock($cacheKey.':mutate', 5)->block(5, function () use ($cacheKey): ?array {
                    $current = $this->tokens(discardMalformed: false);
                    if ($current === null) {
                        return null;
                    }
                    if (! isset($current['refresh_operation'])) {
                        $current['refresh_operation'] = (string) Str::uuid();
                        $this->persist($cacheKey, $current);
                    }

                    return $current;
                });
                if ($tokens === null) {
                    return null;
                }

                $response = Http::asForm()
                    ->acceptJson()
                    ->withHeader('X-Request-ID', RequestCorrelation::id())
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->post(
                        rtrim((string) config($this->configKey('auth_url')), '/').'/oauth/token',
                        [
                            'grant_type' => 'refresh_token',
                            'client_id' => config($this->configKey('oauth_client_id')),
                            'client_secret' => config($this->configKey('oauth_client_secret')),
                            'refresh_token' => $tokens['refresh_token'],
                            'refresh_operation' => $tokens['refresh_operation'],
                            'session_binding' => hash('sha256', $cacheKey),
                            'scope' => implode(' ', config($this->configKey('oauth_scopes'))),
                        ],
                    );

                if (! $response->successful()) {
                    if ($this->refreshFailureInvalidatesSession($response)) {
                        $this->forget();
                    } else {
                        Log::warning('Portal OAuth token refresh was rejected without invalidating the session.', [
                            'context' => $this->context,
                            'status' => $response->status(),
                        ]);
                    }

                    return null;
                }

                try {
                    $payload = $response->json();

                    if (! is_array($payload)) {
                        throw new \InvalidArgumentException('The authorization server returned an invalid token response.');
                    }

                    $replacement = $this->envelope($payload);
                    $saved = Cache::lock($cacheKey.':mutate', 5)->block(5, function () use ($cacheKey, $tokens, $replacement): bool {
                        $current = $this->tokens(discardMalformed: false);
                        if ($current === null || $current['refresh_token'] !== $tokens['refresh_token']
                            || ($current['refresh_operation'] ?? null) !== $tokens['refresh_operation']) {
                            return false;
                        }
                        $this->persist($cacheKey, $replacement);

                        return true;
                    });
                    if (! $saved) {
                        return null;
                    }
                } catch (\InvalidArgumentException|\JsonException $exception) {
                    Log::error('Portal OAuth token refresh returned an invalid response.', [
                        'context' => $this->context,
                        'exception' => $exception::class,
                    ]);

                    return null;
                }

                return $this->tokens()['access_token'] ?? null;
            });
        } catch (ConnectionException $exception) {
            Log::warning('Portal OAuth token refresh was unavailable.', [
                'context' => $this->context,
                'exception' => $exception::class,
            ]);

            return null;
        } catch (LockTimeoutException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(array $payload): void
    {
        $tokens = $this->envelope($payload);
        $cacheKey = $this->cacheKey();
        if ($cacheKey === null) {
            throw new \LogicException('The portal token handle could not be created.');
        }

        Cache::lock($cacheKey.':mutate', 5)->block(5, fn () => $this->persist($cacheKey, $tokens));
    }

    /** @param array<string, mixed> $payload
     * @return array{access_token: string, refresh_token: string, expires_at: int}
     */
    private function envelope(array $payload): array
    {
        $accessToken = $payload['access_token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        $refreshToken = $payload['refresh_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($refreshToken) || $refreshToken === '' || ! is_int($expiresIn)) {
            throw new \InvalidArgumentException('The authorization server returned an invalid token response.');
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds(max(0, $expiresIn - 30))->getTimestamp(),
        ];
    }

    /** @param array{access_token: string, refresh_token: string, expires_at: int, refresh_operation?: string} $tokens */
    private function persist(string $cacheKey, array $tokens): void
    {
        if (! Cache::put($cacheKey, Crypt::encryptString(json_encode($tokens, JSON_THROW_ON_ERROR)), now()->addDays(31))) {
            throw new \RuntimeException('The secure session service is unavailable.');
        }
    }

    public function forget(): void
    {
        $cacheKey = $this->cacheKey(create: false);

        try {
            if ($cacheKey !== null) {
                Cache::lock($cacheKey.':mutate', 5)->block(5, fn () => Cache::forget($cacheKey));
            }
        } finally {
            $this->session->forget($this->sessionHandleKey());
        }
    }

    public function apiUrl(): ?string
    {
        return PlatformConfiguration::isSafe($this->context)
            ? rtrim((string) config($this->configKey('api_url')), '/')
            : null;
    }

    public function revoke(): string
    {
        try {
            PlatformConfiguration::ensureSafe($this->context);

            $accessToken = $this->accessToken();

            if (! $accessToken) {
                throw new \RuntimeException('The portal session is no longer available.');
            }

            $apiUrl = $this->apiUrl();
            if ($apiUrl === null) {
                throw new \RuntimeException('The canonical API route is not available.');
            }

            $response = Http::baseUrl($apiUrl)
                ->acceptJson()
                ->withToken($accessToken)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->withHeader('X-Request-ID', RequestCorrelation::id())
                ->connectTimeout(3)
                ->timeout(8)
                ->delete((string) config($this->configKey('session_endpoint')));

            try {
                $logoutUrl = $response->successful() ? $response->json('data.logout_url') : null;
            } catch (\JsonException $exception) {
                throw new \RuntimeException('The identity service returned an invalid logout response.', previous: $exception);
            }

            if (! is_string($logoutUrl) || ! $this->isTrustedIdentityLogoutUrl($logoutUrl)) {
                throw new \RuntimeException('The identity service returned an invalid logout response.');
            }

            $this->forget();

            return $logoutUrl;
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('The identity service is unavailable.', previous: $exception);
        }
    }

    /** @return array{access_token: string, refresh_token: string, expires_at: int, refresh_operation?: string}|null */
    private function tokens(bool $discardMalformed = true): ?array
    {
        $cacheKey = $this->cacheKey(create: false);
        $encrypted = $cacheKey === null ? null : Cache::get($cacheKey);
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $tokens = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            if ($discardMalformed) {
                $this->forget();
            }

            return null;
        }

        if (
            ! is_array($tokens)
            || ! is_string($tokens['access_token'] ?? null)
            || $tokens['access_token'] === ''
            || ! is_string($tokens['refresh_token'] ?? null)
            || $tokens['refresh_token'] === ''
            || ! is_int($tokens['expires_at'] ?? null)
        ) {
            if ($discardMalformed) {
                $this->forget();
            }

            return null;
        }

        $result = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => $tokens['expires_at'],
        ];
        if (is_string($tokens['refresh_operation'] ?? null) && Str::isUuid($tokens['refresh_operation'])) {
            $result['refresh_operation'] = $tokens['refresh_operation'];
        }

        return $result;
    }

    private function cacheKey(bool $create = true): ?string
    {
        $handle = $this->session->get($this->sessionHandleKey());

        if (! is_string($handle) || $handle === '') {
            if (! $create) {
                return null;
            }

            $handle = Str::random(64);
            $this->session->put($this->sessionHandleKey(), $handle);
        }

        return self::CACHE_KEY_PREFIX.':'.hash_hmac('sha256', $handle, (string) config('app.key'));
    }

    private function isTrustedIdentityLogoutUrl(string $logoutUrl): bool
    {
        $logout = parse_url($logoutUrl);
        $identity = parse_url((string) config($this->configKey('auth_url')));

        if (! is_array($logout)
            || ! is_array($identity)
            || strtolower((string) ($logout['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($logout['scheme'] ?? '')) !== strtolower((string) ($identity['scheme'] ?? ''))
            || strtolower((string) ($logout['host'] ?? '')) !== strtolower((string) ($identity['host'] ?? ''))
            || $this->normalizedPort($logout) !== $this->normalizedPort($identity)
            || isset($logout['user'])
            || isset($logout['pass'])
            || isset($logout['fragment'])
            || preg_match('#^/session/end/[0-9a-f-]{36}$#i', (string) ($logout['path'] ?? '')) !== 1) {
            return false;
        }

        parse_str((string) ($logout['query'] ?? ''), $query);

        return is_string($query['nonce'] ?? null)
            && is_string($query['expires'] ?? null)
            && is_string($query['signature'] ?? null);
    }

    private function refreshFailureInvalidatesSession(Response $response): bool
    {
        if ($response->status() === 401) {
            return true;
        }

        if ($response->status() !== 400) {
            return false;
        }

        try {
            return $response->json('error') === 'invalid_grant';
        } catch (\JsonException) {
            return false;
        }
    }

    /** @param array<string, mixed> $parts */
    private function normalizedPort(array $parts): int
    {
        return isset($parts['port']) ? (int) $parts['port'] : 443;
    }

    private function sessionHandleKey(): string
    {
        return $this->context === PlatformConfiguration::CLINICAL
            ? self::CLINICAL_SESSION_HANDLE_KEY
            : self::BUSINESS_SESSION_HANDLE_KEY;
    }

    private function configKey(string $key): string
    {
        return $this->context === PlatformConfiguration::CLINICAL ? "platform_clinical.{$key}" : "platform.{$key}";
    }
}
