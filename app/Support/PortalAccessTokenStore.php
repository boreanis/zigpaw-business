<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;

class PortalAccessTokenStore
{
    private const SESSION_KEY = 'platform.oauth.tokens';

    public function __construct(private readonly Session $session) {}

    public function accessToken(): ?string
    {
        $tokens = $this->tokens();

        if (! $tokens || ($tokens['expires_at'] ?? 0) <= now()->getTimestamp()) {
            $this->forget();

            return null;
        }

        return $tokens['access_token'] ?? null;
    }

    /** @param array<string, mixed> $payload */
    public function put(array $payload): void
    {
        $accessToken = $payload['access_token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        if (! is_string($accessToken) || $accessToken === '' || ! is_int($expiresIn)) {
            throw new \InvalidArgumentException('The authorization server returned an invalid token response.');
        }

        $this->session->put(self::SESSION_KEY, Crypt::encryptString(json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $payload['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds(max(0, $expiresIn - 30))->getTimestamp(),
        ], JSON_THROW_ON_ERROR)));
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_at: int}|null */
    private function tokens(): ?array
    {
        $encrypted = $this->session->get(self::SESSION_KEY);
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $tokens = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->forget();

            return null;
        }

        return is_array($tokens) ? $tokens : null;
    }
}
