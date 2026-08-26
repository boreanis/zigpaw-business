<?php

namespace Tests\Feature;

use App\Http\Controllers\HealthController;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalOperationalBoundaryTest extends TestCase
{
    public function test_liveness_is_uncached_and_propagates_a_valid_request_id(): void
    {
        $response = $this->withHeader('X-Request-ID', 'portal-test-request-1234')
            ->get('http://business.zigpaw.test/health')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'portal-test-request-1234')
            ->assertExactJson(['status' => 'ok']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $policy);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $policy);
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_secure_requests_keep_the_subresource_upgrade_policy(): void
    {
        $policy = (string) $this->get('https://business.zigpaw.test/health')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('upgrade-insecure-requests', $policy);
    }

    public function test_portal_scripts_follow_the_csp_nonce_contract_without_inline_theme_bootstrap(): void
    {
        $response = $this->get('/')->assertOk();
        $html = (string) $response->getContent();
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
        $this->assertMatchesRegularExpression('/<script\\b[^>]*type="module"[^>]*nonce="[^"]+"/i', $html);
        $this->assertStringNotContainsString('localStorage.getItem(\'zigpaw-business-theme\')', $html);
    }

    public function test_readiness_checks_the_canonical_api_without_exposing_dependency_details(): void
    {
        config()->set('platform.oauth_client_id', 'portal-client');
        config()->set('platform.oauth_client_secret', str_repeat('s', 40));
        config()->set('platform.oauth_scopes', ['business:read']);
        config()->set('platform_clinical.oauth_client_id', 'clinical-client');
        config()->set('platform_clinical.oauth_client_secret', str_repeat('c', 40));
        config()->set('platform_clinical.oauth_scopes', ['clinical:read', 'clinical:submit']);

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

    public function test_readiness_fails_closed_with_a_coarse_response(): void
    {
        config()->set('platform.oauth_client_id', null);
        Http::preventStrayRequests();

        $this->get('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson(['status' => 'not_ready']);

        Http::assertNothingSent();
    }

    public function test_staging_rejects_the_well_known_local_oauth_secret(): void
    {
        $originalEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn (): string => 'staging');
            config()->set([
                'platform.oauth_client_id' => 'staging-client',
                'platform.oauth_client_secret' => hash('sha256', 'zigpaw-local-business-bff-secret'),
                'platform.oauth_scopes' => ['business:read'],
                'app.debug' => false,
                'app.url' => 'https://business.staging.zigpaw.app',
                'platform.api_url' => 'https://api.staging.zigpaw.app',
                'platform.auth_url' => 'https://login.staging.zigpaw.app',
                'cache.default' => 'redis',
                'session.driver' => 'redis',
                'session.encrypt' => true,
                'session.secure' => true,
                'session.domain' => null,
                'session.cookie' => '__Host-zigpaw-business-session',
            ]);

            $method = new \ReflectionMethod(HealthController::class, 'configurationIsReady');

            $this->assertFalse($method->invoke(app(HealthController::class)));
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }
}
