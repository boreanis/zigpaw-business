<?php

namespace Tests\Feature;

use App\Support\PlatformConfiguration;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalOperationalBoundaryTest extends TestCase
{
    public function test_browser_responses_enforce_the_portal_security_boundary(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('Content-Security-Policy')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only')
            ->assertHeaderMissing('X-Powered-By');

        $this->assertStringContainsString("header_remove('X-Powered-By')", (string) file_get_contents(public_path('index.php')));
    }

    public function test_liveness_is_uncached_and_propagates_a_valid_request_id(): void
    {
        $response = $this->withHeader('X-Request-ID', 'portal-test-request-1234')
            ->get('/health')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'portal-test-request-1234')
            ->assertExactJson(['status' => 'ok']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_readiness_checks_the_canonical_api_without_exposing_dependency_details(): void
    {
        config()->set('platform.oauth_client_id', 'portal-client');
        config()->set('platform.oauth_client_secret', str_repeat('s', 40));
        config()->set('platform.oauth_scopes', ['portal:read']);

        Http::fake([
            'https://api.zigpaw.test/health/ready' => Http::response(['status' => 'ready']),
        ]);

        $response = $this->get('/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/D', $requestId);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.zigpaw.test/health/ready'
            && $request->hasHeader('X-Request-ID', $requestId));
    }

    public function test_readiness_fails_closed_with_a_coarse_response(): void
    {
        config()->set('platform.oauth_client_id', null);
        Http::preventStrayRequests();

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);

        Http::assertNothingSent();
    }

    public function test_the_development_oauth_secret_fallback_is_limited_to_local_and_testing(): void
    {
        $configuration = (string) file_get_contents(config_path('platform.php'));

        $this->assertStringContainsString(
            "in_array(\$applicationEnvironment, ['local', 'testing'], true)",
            $configuration,
        );
        $this->assertStringNotContainsString("env('APP_ENV') === 'production'", $configuration);
    }

    public function test_hostile_platform_origins_and_callback_urls_fail_readiness_without_a_request(): void
    {
        config()->set('platform.api_url', 'https://api.zigpaw.test.attacker.example');
        config()->set('platform.oauth_redirect_uri', 'https://attacker.example/auth/callback');
        Cache::forget('health:readiness');
        Http::preventStrayRequests();

        $this->assertFalse(PlatformConfiguration::isSafe());
        config()->set('platform.api_url', 'https://operator@api.zigpaw.test');
        $this->assertFalse(PlatformConfiguration::isSafe());
        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);
        Http::assertNothingSent();
    }

    public function test_readiness_returns_a_coarse_failure_when_the_application_cache_is_unavailable(): void
    {
        app(RateLimiter::class);
        Cache::shouldReceive('remember')->once()->andThrow(new \RuntimeException('cache unavailable'));

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);
    }
}
