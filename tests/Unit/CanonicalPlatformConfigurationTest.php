<?php

namespace Tests\Unit;

use App\Support\PlatformConfiguration;
use Tests\TestCase;

class CanonicalPlatformConfigurationTest extends TestCase
{
    public function test_canonical_configuration_requires_no_routing_outcome_or_stored_placement_context(): void
    {
        $this->assertTrue(PlatformConfiguration::isSafe());
        $this->assertArrayNotHasKey('regional_oauth_routes', config('platform'));
        $this->assertArrayNotHasKey('oauth_routing_signing_key', config('platform'));
        $this->assertArrayNotHasKey('runtime_cell_code', config('platform'));
    }

    public function test_oauth_and_api_routes_require_exact_canonical_https_origins(): void
    {
        foreach (['auth_url', 'api_url'] as $field) {
            $original = config('platform.'.$field);

            foreach ([
                'https://user:password@api.zigpaw.test',
                'https://api.zigpaw.test?redirect=https://attacker.example',
                'https://api.zigpaw.test#fragment',
                'https://api.zigpaw.test:444',
                "https://api.zigpaw.test\nattacker",
                ' https://api.zigpaw.test',
                'https://api.zigpaw.test\\oauth',
                'https://api.zigpaw.test.attacker.example',
            ] as $url) {
                config()->set('platform.'.$field, $url);
                $this->assertFalse(PlatformConfiguration::isSafe(), $field.': '.$url);
            }

            config()->set('platform.'.$field, $original);
        }
    }

    public function test_api_endpoint_is_one_configured_authority_for_every_market(): void
    {
        $this->assertSame('https://api.zigpaw.test', config('platform.api_url'));
        $this->assertSame('https://api.zigpaw.test', config('platform_clinical.api_url'));
    }

    public function test_cache_and_cookie_namespaces_are_stable_without_placement_suffixes(): void
    {
        putenv('CACHE_PREFIX=custom-cache');
        putenv('SESSION_COOKIE=__Host-custom-session');

        try {
            $cache = require config_path('cache.php');
            $session = require config_path('session.php');

            $this->assertSame('custom-cache-', $cache['prefix']);
            $this->assertSame('__Host-custom-session', $session['cookie']);
        } finally {
            putenv('CACHE_PREFIX');
            putenv('SESSION_COOKIE');
        }
    }

    public function test_request_context_cannot_select_a_second_api_authority(): void
    {
        config()->set('platform.api_url', 'https://api-au.zigpaw.test');

        $this->assertFalse(PlatformConfiguration::isSafe());
    }

    public function test_production_configuration_does_not_require_a_runtime_placement_selector(): void
    {
        $this->withProductionConfiguration(function (): void {
            $this->assertTrue(PlatformConfiguration::isSafe());
            $this->assertArrayNotHasKey('runtime_cell_code', config('platform'));
        });
    }

    public function test_production_allows_only_the_configured_canonical_api_and_auth_origins(): void
    {
        $this->withProductionConfiguration(function (): void {
            config()->set('platform.auth_url', 'https://regional-id.zigpaw.app');
            $this->assertFalse(PlatformConfiguration::isSafe());

            config()->set('platform.auth_url', 'https://auth.zigpaw.app');
            config()->set('platform.api_url', 'https://api-au.zigpaw.app');
            $this->assertFalse(PlatformConfiguration::isSafe());
        });
    }

    public function test_retired_routing_configuration_cannot_override_canonical_origins(): void
    {
        config()->set('platform.regional_oauth_routes', [
            'attacker' => ['oauth_url' => 'https://attacker.example', 'api_url' => 'https://attacker.example'],
        ]);

        $this->assertTrue(PlatformConfiguration::isSafe());
        $this->assertSame('https://auth.zigpaw.test', config('platform.auth_url'));
        $this->assertSame('https://api.zigpaw.test', config('platform.api_url'));
    }

    public function test_management_and_clinical_audiences_keep_distinct_callbacks_and_sessions_on_one_origin(): void
    {
        $this->assertTrue(PlatformConfiguration::isSafe(PlatformConfiguration::BUSINESS));
        $this->assertTrue(PlatformConfiguration::isSafe(PlatformConfiguration::CLINICAL));
        $this->assertSame('https://api.zigpaw.test', config('platform.api_url'));
        $this->assertSame('https://api.zigpaw.test', config('platform_clinical.api_url'));
        $this->assertNotSame(config('platform.oauth_redirect_uri'), config('platform_clinical.oauth_redirect_uri'));
        $this->assertNotSame(config('platform.session_endpoint'), config('platform_clinical.session_endpoint'));
    }

    public function test_local_staged_browser_mode_accepts_only_the_exact_qa_origins(): void
    {
        $originalEnvironment = app()->environment();

        try {
            config()->set([
                'app.url' => 'https://qa-business.zigpaw.test',
                'platform.staged_browser_qa' => true,
                'platform.api_url' => 'https://qa-api.zigpaw.test',
                'platform.auth_url' => 'https://qa-auth.zigpaw.test',
                'platform.oauth_redirect_uri' => 'https://qa-business.zigpaw.test/auth/callback',
                'platform_clinical.api_url' => 'https://qa-api.zigpaw.test',
                'platform_clinical.auth_url' => 'https://qa-auth.zigpaw.test',
                'platform_clinical.oauth_redirect_uri' => 'https://qa-business.zigpaw.test/clinical/auth/callback',
            ]);

            $this->assertTrue(PlatformConfiguration::isSafe(PlatformConfiguration::BUSINESS));
            $this->assertTrue(PlatformConfiguration::isSafe(PlatformConfiguration::CLINICAL));

            config()->set('platform.api_url', 'https://api.zigpaw.test');
            $this->assertFalse(PlatformConfiguration::isSafe(PlatformConfiguration::BUSINESS));

            app()->detectEnvironment(fn (): string => 'production');
            config()->set('platform.api_url', 'https://qa-api.zigpaw.test');
            $this->assertFalse(PlatformConfiguration::isSafe(PlatformConfiguration::BUSINESS));
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    private function withProductionConfiguration(callable $assertions): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            config()->set([
                'app.url' => 'https://business.zigpaw.app',
                'platform.api_url' => 'https://api.zigpaw.app',
                'platform.auth_url' => 'https://auth.zigpaw.app',
                'platform.oauth_redirect_uri' => 'https://business.zigpaw.app/auth/callback',
            ]);
            $assertions();
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }
}
