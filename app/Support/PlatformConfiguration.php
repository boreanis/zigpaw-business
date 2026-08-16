<?php

namespace App\Support;

use RuntimeException;

class PlatformConfiguration
{
    public static function isSafe(): bool
    {
        $usesLocalDomains = app()->environment(['local', 'testing']);
        $domain = $usesLocalDomains ? 'zigpaw.test' : 'zigpaw.app';
        $portalOrigin = self::canonicalOrigin((string) config('app.url'));

        if ($portalOrigin !== 'https://vets.'.$domain
            || self::canonicalOrigin((string) config('platform.api_url')) !== 'https://api.'.$domain
            || self::canonicalOrigin((string) config('platform.auth_url')) !== 'https://login.'.$domain) {
            return false;
        }

        return self::canonicalCallback((string) config('platform.oauth_redirect_uri'))
            === $portalOrigin.'/auth/callback';
    }

    public static function ensureSafe(): void
    {
        if (! self::isSafe()) {
            throw new RuntimeException('Unsafe Zigpaw platform configuration.');
        }
    }

    private static function canonicalOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        return 'https://'.strtolower((string) $parts['host']);
    }

    private static function canonicalCallback(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ($parts['path'] ?? '') !== '/auth/callback'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return null;
        }

        $origin = self::canonicalOrigin(sprintf(
            '%s://%s%s',
            (string) ($parts['scheme'] ?? ''),
            (string) ($parts['host'] ?? ''),
            isset($parts['port']) ? ':'.(int) $parts['port'] : '',
        ));

        return $origin === null ? null : $origin.'/auth/callback';
    }
}
