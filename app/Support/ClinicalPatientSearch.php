<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\PlatformApiException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

final class ClinicalPatientSearch
{
    private const UNAVAILABLE = 'Patient search is temporarily unavailable. Try again.';

    public function __construct(
        private readonly Session $session,
        private readonly ClinicalPortalAccessTokenStore $tokens,
    ) {}

    public function apply(string $organizationId, #[\SensitiveParameter] string $name): string
    {
        $handle = Str::random(40);
        $key = $this->key($organizationId, $handle);
        if ($key === null) {
            throw new PlatformApiException(401, 'Your clinical workspace session has ended. Please sign in again.');
        }

        try {
            $saved = Cache::put($key, Crypt::encryptString($name), now()->addMinutes(10));
        } catch (Throwable) {
            $saved = false;
        }
        if (! $saved) {
            throw new PlatformApiException(503, self::UNAVAILABLE);
        }

        return $handle;
    }

    public function resolve(string $organizationId, string $handle): ?string
    {
        $key = $this->key($organizationId, $handle);
        if ($key === null) {
            return null;
        }

        try {
            $value = Cache::get($key);
        } catch (Throwable) {
            throw new PlatformApiException(503, self::UNAVAILABLE);
        }

        try {
            return is_string($value) ? Crypt::decryptString($value) : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public function clear(string $organizationId, string $handle): void
    {
        $key = $this->key($organizationId, $handle);
        if ($key !== null) {
            try {
                Cache::forget($key);
            } catch (Throwable) {
                throw new PlatformApiException(503, self::UNAVAILABLE);
            }
        }
    }

    private function key(string $organizationId, string $handle): ?string
    {
        $fingerprint = $this->tokens->contextFingerprint();

        return $fingerprint === null ? null : 'clinical.patient-search:'.hash_hmac(
            'sha256',
            $this->session->getId().':'.$fingerprint.':'.$organizationId.':'.$handle,
            (string) config('app.key'),
        );
    }
}
