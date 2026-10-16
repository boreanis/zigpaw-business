<?php

namespace App\Support;

use App\Exceptions\PlatformApiException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

final class ClinicalSubmissionRetry
{
    public const SESSION_KEY = 'portal.clinical_pending_submissions';

    private const MAX_PENDING_SUBMISSIONS = 32;

    public function __construct(private readonly Session $session) {}

    /** @param array<string, mixed> $identity */
    public function context(string $organizationId, string $grantId, array $identity): string
    {
        $handle = $this->session->get('platform.oauth.clinical_token_handle');
        $principalId = $identity['id'] ?? null;
        $membershipId = data_get($identity, 'membership.id');
        if (! is_string($handle) || $handle === ''
            || ! is_string($principalId) || $principalId === ''
            || ! is_string($membershipId) || $membershipId === ''
            || data_get($identity, 'organization.id') !== $organizationId) {
            throw new PlatformApiException(502, 'Your clinical workspace could not be verified. Reopen this patient before submitting records.');
        }

        return $this->fingerprint([$handle, $principalId, $membershipId, $organizationId, $grantId]);
    }

    public function hasPending(string $context): bool
    {
        return $this->session->has(self::SESSION_KEY.'.'.$context);
    }

    /** @param array<string, mixed> $payload */
    public function key(string $context, array $payload): string
    {
        $pending = $this->session->get(self::SESSION_KEY, []);
        $pending = is_array($pending) ? $pending : [];
        $fingerprint = $this->fingerprint($payload);
        $entry = $pending[$context] ?? null;
        if (is_array($entry)) {
            if (! is_string($entry['key'] ?? null) || ! Str::isUuid($entry['key'])
                || ($entry['fingerprint'] ?? null) !== $fingerprint) {
                throw new PlatformApiException(409, 'A previous submission for this patient is unconfirmed. Retry its unchanged records, or check submission history before starting another submission.');
            }

            return $entry['key'];
        }
        if (count($pending) >= self::MAX_PENDING_SUBMISSIONS) {
            throw new PlatformApiException(409, 'Earlier submissions are awaiting confirmation. Check submission history before starting another submission.');
        }

        $key = (string) Str::uuid();
        $pending[$context] = ['key' => $key, 'fingerprint' => $fingerprint];
        $this->session->put(self::SESSION_KEY, $pending);
        $this->session->save();

        return $key;
    }

    public function forget(string $context, ?string $key = null): void
    {
        if ($key !== null && $this->session->get(self::SESSION_KEY.'.'.$context.'.key') !== $key) {
            return;
        }

        $this->session->forget(self::SESSION_KEY.'.'.$context);
        if ($this->session->get(self::SESSION_KEY) === []) {
            $this->session->forget(self::SESSION_KEY);
        }
        $this->session->save();
    }

    /** @param array<mixed> $value */
    private function fingerprint(array $value): string
    {
        return hash_hmac('sha256', json_encode($this->canonicalValue($value), JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalValue($item), $value);
    }
}
