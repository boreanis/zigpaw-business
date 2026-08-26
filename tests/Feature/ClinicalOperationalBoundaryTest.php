<?php

namespace Tests\Feature;

use App\Http\Controllers\HealthController;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class ClinicalOperationalBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureReadyClients();
    }

    public function test_clinical_browser_responses_enforce_the_portal_security_boundary(): void
    {
        $response = $this->get('/clinical')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('Content-Security-Policy')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only')
            ->assertHeaderMissing('X-Powered-By');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_readiness_requires_both_distinct_business_and_clinical_clients(): void
    {
        Http::fake([
            'https://api.zigpaw.test/health' => Http::response(['status' => 'ok']),
        ]);

        $response = $this->get('/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/D', $requestId);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.zigpaw.test/health'
            && $request->hasHeader('X-Request-ID', $requestId));
    }

    public function test_readiness_fails_closed_when_the_clinical_client_is_missing(): void
    {
        config()->set('platform_clinical.oauth_client_id', null);
        Http::preventStrayRequests();

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);

        Http::assertNothingSent();
    }

    public function test_readiness_rejects_cross_audience_scopes_and_reused_client_credentials(): void
    {
        config()->set('platform_clinical.oauth_scopes', ['business:read']);
        Http::preventStrayRequests();

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);
        Http::assertNothingSent();

        Cache::forget('health:readiness');
        $this->configureReadyClients();
        config()->set('platform_clinical.oauth_client_id', config('platform.oauth_client_id'));

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);
        Http::assertNothingSent();
    }

    public function test_hostile_clinical_origins_and_callbacks_fail_readiness_without_a_request(): void
    {
        config()->set('platform_clinical.auth_url', 'https://login.zigpaw.test.attacker.example');
        Http::preventStrayRequests();

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);
        Http::assertNothingSent();

        Cache::forget('health:readiness');
        $this->configureReadyClients();
        config()->set(
            'platform_clinical.oauth_redirect_uri',
            'https://business.zigpaw.test.attacker.example/clinical/auth/callback',
        );

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);
        Http::assertNothingSent();
    }

    public function test_staging_rejects_the_well_known_local_clinical_oauth_secret(): void
    {
        $originalEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn (): string => 'staging');
            config()->set([
                'app.debug' => false,
                'app.url' => 'https://business.staging.zigpaw.app',
                'platform.api_url' => 'https://api.staging.zigpaw.app',
                'platform.auth_url' => 'https://login.staging.zigpaw.app',
                'platform.oauth_redirect_uri' => 'https://business.staging.zigpaw.app/auth/callback',
                'platform.oauth_client_id' => 'staging-business-client',
                'platform.oauth_client_secret' => str_repeat('b', 40),
                'platform.oauth_scopes' => ['business:read'],
                'platform_clinical.api_url' => 'https://api.staging.zigpaw.app',
                'platform_clinical.auth_url' => 'https://login.staging.zigpaw.app',
                'platform_clinical.oauth_redirect_uri' => 'https://business.staging.zigpaw.app/clinical/auth/callback',
                'platform_clinical.oauth_client_id' => 'staging-clinical-client',
                'platform_clinical.oauth_client_secret' => hash('sha256', 'zigpaw-local-business-clinical-bff-secret'),
                'platform_clinical.oauth_scopes' => ['clinical:read', 'clinical:submit'],
                'cache.default' => 'redis',
                'session.driver' => 'redis',
                'session.encrypt' => true,
                'session.secure' => true,
                'session.domain' => null,
                'session.cookie' => '__Host-zigpaw-business-session',
            ]);

            $method = new ReflectionMethod(HealthController::class, 'configurationIsReady');

            $this->assertFalse($method->invoke(app(HealthController::class)));
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    public function test_readiness_returns_a_coarse_failure_when_the_application_cache_is_unavailable(): void
    {
        app(RateLimiter::class);
        Cache::shouldReceive('remember')
            ->once()
            ->andThrow(new RuntimeException('cache unavailable'));

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);
    }

    private function configureReadyClients(): void
    {
        config()->set([
            'app.url' => 'https://business.zigpaw.test',
            'platform.api_url' => 'https://api.zigpaw.test',
            'platform.auth_url' => 'https://login.zigpaw.test',
            'platform.session_endpoint' => '/v1/business/session',
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
            'platform.oauth_client_id' => 'business-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_scopes' => ['business:read', 'business:team:read'],
            'platform_clinical.api_url' => 'https://api.zigpaw.test',
            'platform_clinical.auth_url' => 'https://login.zigpaw.test',
            'platform_clinical.session_endpoint' => '/v1/business/clinical/session',
            'platform_clinical.oauth_redirect_uri' => 'https://business.zigpaw.test/clinical/auth/callback',
            'platform_clinical.oauth_client_id' => 'clinical-client',
            'platform_clinical.oauth_client_secret' => str_repeat('c', 40),
            'platform_clinical.oauth_scopes' => ['clinical:read', 'clinical:submit'],
        ]);
    }
}
