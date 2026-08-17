<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PortalAccessTokenStore
{
    private const CACHE_KEY_PREFIX = 'platform.oauth.tokens';

    private const BUSINESS_SESSION_HANDLE_KEY = 'platform.oauth.token_handle';

    private const CLINICAL_SESSION_HANDLE_KEY = 'platform.oauth.clinical_token_handle';

    public function __construct(
        private readonly Session $session,
        private readonly string $context = 'business',
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

        $cacheKey = $this->cacheKey(create: false);
        if ($cacheKey === null) {
            return null;
        }

        try {
            return Cache::lock($cacheKey.':refresh', 10)->block(5, function (): ?string {
                $tokens = $this->tokens();

                if (! $tokens) {
                    return null;
                }

                if ($tokens['expires_at'] > now()->getTimestamp()) {
                    return $tokens['access_token'];
                }

                $response = Http::asForm()
                    ->acceptJson()
                    ->withHeader('X-Request-ID', RequestCorrelation::id())
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->post(
                        config($this->configKey('auth_url')).'/oauth/token',
                        [
                            'grant_type' => 'refresh_token',
                            'client_id' => config($this->configKey('oauth_client_id')),
                            'client_secret' => config($this->configKey('oauth_client_secret')),
                            'refresh_token' => $tokens['refresh_token'],
                            'scope' => implode(' ', config($this->configKey('oauth_scopes'))),
                        ],
                    );

                if (! $response->successful()) {
                    if (in_array($response->status(), [400, 401], true)) {
                        $this->forget();
                    }

                    return null;
                }

                $this->put($response->json());

                return $this->tokens()['access_token'] ?? null;
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload */
    public function put(array $payload): void
    {
        $accessToken = $payload['access_token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        $refreshToken = $payload['refresh_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($refreshToken) || $refreshToken === '' || ! is_int($expiresIn)) {
            throw new \InvalidArgumentException('The authorization server returned an invalid token response.');
        }

        $cacheKey = $this->cacheKey();
        if ($cacheKey === null) {
            throw new \LogicException('The portal token handle could not be created.');
        }

        Cache::put($cacheKey, Crypt::encryptString(json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds(max(0, $expiresIn - 30))->getTimestamp(),
        ], JSON_THROW_ON_ERROR)), now()->addDays(31));
    }

    public function forget(): void
    {
        $cacheKey = $this->cacheKey(create: false);

        if ($cacheKey !== null) {
            Cache::forget($cacheKey);
        }

        $this->session->forget($this->sessionHandleKey());
    }

    public function revoke(): string
    {
        try {
            $accessToken = $this->accessToken();

            if (! $accessToken) {
                throw new \RuntimeException('The portal session is no longer available.');
            }

            $response = Http::baseUrl((string) config($this->configKey('api_url')))
                ->acceptJson()
                ->withToken($accessToken)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->withHeader('X-Request-ID', RequestCorrelation::id())
                ->connectTimeout(3)
                ->timeout(8)
                ->delete((string) config($this->configKey('session_endpoint')));

            $logoutUrl = $response->successful() ? $response->json('data.logout_url') : null;

            if (! is_string($logoutUrl) || ! $this->isTrustedIdentityLogoutUrl($logoutUrl)) {
                throw new \RuntimeException('The identity service returned an invalid logout response.');
            }

            $this->forget();

            return $logoutUrl;
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('The identity service is unavailable.', previous: $exception);
        }
    }

    /** @return array{access_token: string, refresh_token: string, expires_at: int}|null */
    private function tokens(): ?array
    {
        $cacheKey = $this->cacheKey(create: false);
        $encrypted = $cacheKey === null ? null : Cache::get($cacheKey);
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $tokens = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->forget();

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
            $this->forget();

            return null;
        }

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => $tokens['expires_at'],
        ];
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

    /** @param array<string, mixed> $parts */
    private function normalizedPort(array $parts): int
    {
        return isset($parts['port']) ? (int) $parts['port'] : 443;
    }

    private function sessionHandleKey(): string
    {
        return $this->context === 'clinical'
            ? self::CLINICAL_SESSION_HANDLE_KEY
            : self::BUSINESS_SESSION_HANDLE_KEY;
    }

    private function configKey(string $key): string
    {
        return $this->context === 'clinical' ? "platform_clinical.{$key}" : "platform.{$key}";
    }
}
