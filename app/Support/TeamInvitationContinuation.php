<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use RuntimeException;

/** Short-lived interaction state for one explicitly reviewed team invitation. */
final class TeamInvitationContinuation
{
    public const SESSION_KEY = 'business.team_invitations';

    private const OAUTH_RETURN = 'business.team_invitation_oauth_return';

    private const AUTH_CONTEXT = 'business.team_invitation_auth_context';

    public function __construct(private readonly Session $session) {}

    public function capture(string $membership, #[\SensitiveParameter] string $token): void
    {
        if (! Str::isUuid($membership) || preg_match('/^[A-Za-z0-9]{64}$/', $token) !== 1) {
            throw new RuntimeException('This invitation link is not valid. Reopen the latest invitation email.');
        }
        $entries = $this->entries();
        $entry = $entries[$membership] ?? null;
        if (is_array($entry) && hash_equals($entry['token'], $token)) {
            return;
        }
        if (is_array($entry) && ($entry['attempted'] ?? false)) {
            throw new RuntimeException('An earlier acceptance is unconfirmed. Review your workspace access before opening another invitation.');
        }
        if (! isset($entries[$membership]) && count($entries) >= 3) {
            throw new RuntimeException('Finish an existing invitation before opening another one.');
        }
        $entries[$membership] = ['token' => $token, 'expires_at' => now()->timestamp + 1800, 'attempted' => false];
        $this->session->put(self::SESSION_KEY, $entries);
    }

    /** @return array<string, mixed>|null */
    public function entry(string $membership): ?array
    {
        return $this->entries()[$membership] ?? null;
    }

    public function renewAuthenticationContext(): void
    {
        $this->session->put(self::AUTH_CONTEXT, Str::random(64));
    }

    public function beginOAuth(?string $membership, string $state): void
    {
        $this->session->forget(self::OAUTH_RETURN);
        if ($membership !== null && $this->entry($membership) !== null) {
            $this->session->put(self::OAUTH_RETURN, ['membership' => $membership, 'state_hash' => hash('sha256', $state)]);
        }
    }

    public function consumeOAuthReturn(?string $state): ?string
    {
        $return = $this->session->pull(self::OAUTH_RETURN);
        if (! is_array($return) || ! is_string($state) || ! is_string($return['state_hash'] ?? null)
            || ! hash_equals($return['state_hash'], hash('sha256', $state)) || ! is_string($return['membership'] ?? null)) {
            return null;
        }

        return $this->entry($return['membership']) !== null ? $return['membership'] : null;
    }

    /** @param array<string, mixed> $preview @return array<string, mixed> */
    public function prepare(string $membership, string $actor, array $preview): array
    {
        $entry = $this->requiredEntry($membership);
        $actorHash = $this->actorHash($actor);
        if ($entry['attempted'] ?? false) {
            $this->assertActor($entry, $actorHash);

            return $entry;
        }
        $digest = hash_hmac('sha256', json_encode([$membership, $entry['token'], $actorHash, $preview['role']], JSON_THROW_ON_ERROR), (string) config('app.key'));
        $entry = [...$entry, 'actor' => $actorHash, 'expected_role' => $preview['role'],
            'organization_name' => $preview['organization_name'], 'invitation_expires_at' => $preview['expires_at'], 'preview_hash' => $this->previewHash($preview),
            'key' => substr($digest, 0, 8).'-'.substr($digest, 8, 4).'-4'.substr($digest, 13, 3).'-8'.substr($digest, 17, 3).'-'.substr($digest, 20, 12)];
        $this->save($membership, $entry);

        return $entry;
    }

    /** @return array<string, mixed> */
    public function operation(string $membership, string $actor, string $key): array
    {
        $entry = $this->requiredEntry($membership);
        $this->assertActor($entry, $this->actorHash($actor));
        if (! is_string($entry['key'] ?? null) || ! hash_equals($entry['key'], $key)) {
            throw new RuntimeException('This invitation form changed. Review the invitation again before accepting.');
        }

        return $entry;
    }

    /** @param array<string, mixed> $preview */
    public function previewMatches(array $entry, array $preview): bool
    {
        return is_string($entry['preview_hash'] ?? null) && hash_equals($entry['preview_hash'], $this->previewHash($preview));
    }

    public function markAttempted(string $membership): void
    {
        $entry = $this->requiredEntry($membership);
        $entry['attempted'] = true;
        $this->save($membership, $entry);
        $this->session->save();
    }

    public function resetRejected(string $membership): void
    {
        $entry = $this->requiredEntry($membership);
        $this->save($membership, ['token' => $entry['token'], 'expires_at' => $entry['expires_at'], 'attempted' => false]);
    }

    public function forget(string $membership): void
    {
        $entries = $this->entries();
        unset($entries[$membership]);
        $this->session->put(self::SESSION_KEY, $entries);
    }

    /** @return array<string, array<string, mixed>> */
    private function entries(): array
    {
        $entries = $this->session->get(self::SESSION_KEY, []);
        $entries = is_array($entries) ? $entries : [];

        $active = array_filter($entries, static fn ($entry): bool => is_array($entry)
            && is_string($entry['token'] ?? null) && preg_match('/^[A-Za-z0-9]{64}$/', $entry['token']) === 1
            && is_int($entry['expires_at'] ?? null) && $entry['expires_at'] > now()->timestamp);
        $this->session->put(self::SESSION_KEY, $active);

        return $active;
    }

    /** @return array<string, mixed> */
    private function requiredEntry(string $membership): array
    {
        return $this->entry($membership) ?? throw new RuntimeException('This invitation session expired. Reopen the latest invitation email.');
    }

    private function actorHash(string $actor): string
    {
        if ($actor === '') {
            throw new RuntimeException('Sign in again before reviewing this invitation.');
        }

        $context = $this->session->get(self::AUTH_CONTEXT);
        if (! is_string($context) || strlen($context) !== 64) {
            $this->renewAuthenticationContext();
            $context = $this->session->get(self::AUTH_CONTEXT);
        }

        return hash_hmac('sha256', $actor.':'.$context, (string) config('app.key'));
    }

    /** @param array<string, mixed> $entry */
    private function assertActor(array $entry, string $actorHash): void
    {
        if (! is_string($entry['actor'] ?? null) || ! hash_equals($entry['actor'], $actorHash)) {
            throw new RuntimeException('Your sign-in changed. Review workspace access before starting another acceptance request.');
        }
    }

    /** @param array<string, mixed> $preview */
    private function previewHash(array $preview): string
    {
        return hash_hmac('sha256', json_encode($preview, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /** @param array<string, mixed> $entry */
    private function save(string $membership, array $entry): void
    {
        $entries = $this->entries();
        $entries[$membership] = $entry;
        $this->session->put(self::SESSION_KEY, $entries);
    }
}
