<?php

namespace App\Support;

use RuntimeException;

class PlatformConfiguration
{
    public const BUSINESS = 'business';

    public const CLINICAL = 'clinical';

    public static function isSafe(string $context = self::BUSINESS): bool
    {
        $configPrefix = self::configPrefix($context);
        $origins = self::expectedOrigins();
        $portalOrigin = self::canonicalOrigin((string) config('app.url'));

        if ($origins === null
            || $portalOrigin !== $origins['portal']
            || self::canonicalOrigin((string) config("{$configPrefix}.api_url")) !== $origins['api']
            || self::canonicalOrigin((string) config("{$configPrefix}.auth_url")) !== $origins['auth']) {
            return false;
        }

        return self::canonicalCallback((string) config("{$configPrefix}.oauth_redirect_uri"), self::callbackPath($context))
                === $portalOrigin.self::callbackPath($context)
            && self::canonicalEndpointPath((string) config("{$configPrefix}.session_endpoint"))
                === self::sessionEndpoint($context);
    }

    public static function ensureSafe(string $context = self::BUSINESS): void
    {
        if (! self::isSafe($context)) {
            throw new RuntimeException('Unsafe Zigpaw platform configuration.');
        }
    }

    private static function configPrefix(string $context): string
    {
        return match ($context) {
            self::BUSINESS => 'platform',
            self::CLINICAL => 'platform_clinical',
            default => throw new \InvalidArgumentException('Unknown Zigpaw platform context.'),
        };
    }

    /** @return array{portal: string, api: string, auth: string}|null */
    private static function expectedOrigins(): ?array
    {
        $suffix = match (app()->environment()) {
            'local', 'testing' => 'zigpaw.test',
            'staging' => 'staging.zigpaw.app',
            'production' => 'zigpaw.app',
            default => null,
        };

        if ($suffix === null) {
            return null;
        }

        return [
            'portal' => "https://business.{$suffix}",
            'api' => "https://api.{$suffix}",
            'auth' => "https://login.{$suffix}",
        ];
    }

    private static function callbackPath(string $context): string
    {
        return match ($context) {
            self::BUSINESS => '/auth/callback',
            self::CLINICAL => '/clinical/auth/callback',
            default => throw new \InvalidArgumentException('Unknown Zigpaw platform context.'),
        };
    }

    private static function sessionEndpoint(string $context): string
    {
        return match ($context) {
            self::BUSINESS => '/v1/business/session',
            self::CLINICAL => '/v1/business/clinical/session',
            default => throw new \InvalidArgumentException('Unknown Zigpaw platform context.'),
        };
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

    private static function canonicalCallback(string $url, string $path): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ($parts['path'] ?? '') !== $path
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

        return $origin === null ? null : $origin.$path;
    }

    private static function canonicalEndpointPath(string $endpoint): ?string
    {
        if ($endpoint === '' || ! str_starts_with($endpoint, '/') || str_contains($endpoint, '?') || str_contains($endpoint, '#')) {
            return null;
        }

        $parts = parse_url($endpoint);

        if (! is_array($parts)
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return null;
        }

        return $parts['path'] ?? null;
    }
}
