<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

final class ClinicalReturnDestination
{
    public const PATIENT_STATUSES = ['active', 'expiring_soon', 'expired', 'revoked', 'all'];

    public const BROWSER_CONTEXT = 'clinical_portal.return_context';

    private const LIFETIME = 900;

    public function rememberRequest(Request $request): ?string
    {
        if ($request->routeIs('clinical.patients.index')) {
            return $this->remember('clinical.patients.index', $request->query('status'));
        }
        if ($request->routeIs('clinical.patients.show')) {
            return $this->remember('clinical.patients.show', $request->route('grantId'));
        }

        return null;
    }

    public function remember(string $route, mixed $value, bool $authenticated = false): ?string
    {
        $value = $this->validatedValue($route, $value);
        if ($value === null) {
            return null;
        }

        $organization = session('clinical_portal.organization_id');
        $handle = Str::random(48);
        $this->save($authenticated ? 'ready' : 'queued', $handle, [
            'route' => $route,
            'value' => $value,
            'organization' => is_string($organization) && $organization !== '' ? $organization : null,
            'expires_at' => now()->timestamp + self::LIFETIME,
        ]);

        return $handle;
    }

    public static function validHandle(mixed $handle): ?string
    {
        return is_string($handle) && preg_match('/^[A-Za-z0-9]{48}$/D', $handle) === 1 ? $handle : null;
    }

    public function begin(?string $resume, string $state, #[\SensitiveParameter] string $verifier): void
    {
        $target = $resume === null ? null : $this->take('queued', $resume);
        $this->save('authorization', $state, ['verifier' => $verifier, 'target' => $target]);
    }

    /** @return array{verifier: string, target: array<string, mixed>|null}|null */
    public function authorization(string $state): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{64}$/D', $state) !== 1) {
            return null;
        }
        $entry = $this->take('authorization', $state);
        if (! is_string($entry['verifier'] ?? null)) {
            return null;
        }

        return ['verifier' => $entry['verifier'], 'target' => is_array($entry['target'] ?? null) ? $entry['target'] : null];
    }

    /** @param array<string, mixed>|null $target */
    public function complete(?array $target): ?string
    {
        if ($target === null || ($target['expires_at'] ?? 0) <= now()->timestamp) {
            return null;
        }
        $handle = Str::random(48);
        $this->save('ready', $handle, $target);

        return $handle;
    }

    /** @return array{0: string, 1: array<string, string>}|null */
    public function consume(?string $handle, string $authorizedOrganization): ?array
    {
        $intent = $handle === null ? null : ($this->take('ready', $handle) ?? $this->take('queued', $handle));
        if ($intent === null || ($intent['expires_at'] ?? 0) <= now()->timestamp
            || (($intent['organization'] ?? null) !== null && $intent['organization'] !== $authorizedOrganization)) {
            return null;
        }

        $route = $intent['route'];
        $value = $this->validatedValue($route, $intent['value']);
        if ($value === null) {
            return null;
        }

        return [$route, $route === 'clinical.patients.show' ? ['grantId' => $value] : ['status' => $value]];
    }

    /** @param array<string, mixed> $value */
    private function save(string $stage, string $handle, array $value): void
    {
        $context = session(self::BROWSER_CONTEXT);
        if (! is_string($context)) {
            $context = Str::random(64);
            session()->put(self::BROWSER_CONTEXT, $context);
        }
        $key = $this->cacheKey($stage, $handle, $context);
        abort_unless(Cache::put($key, Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)), self::LIFETIME), 503);
    }

    /** @return array<string, mixed>|null */
    private function take(string $stage, string $handle): ?array
    {
        $context = session(self::BROWSER_CONTEXT);
        if (! is_string($context)) {
            return null;
        }

        $key = $this->cacheKey($stage, $handle, $context);
        $value = Cache::lock($key.':claim', 5)->get(fn (): mixed => Cache::pull($key));
        if (! is_string($value)) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($value), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (DecryptException|JsonException) {
            return null;
        }
    }

    private function cacheKey(string $stage, string $handle, string $context): string
    {
        return 'clinical-return:'.hash_hmac('sha256', $stage.':'.$context.':'.$handle, (string) config('app.key'));
    }

    private function validatedValue(string $route, mixed $value): ?string
    {
        return match ($route) {
            'clinical.patients.index' => in_array($value, self::PATIENT_STATUSES, true) ? $value : 'active',
            'clinical.patients.show' => is_string($value) && Str::isUuid($value) ? $value : null,
            default => null,
        };
    }
}
