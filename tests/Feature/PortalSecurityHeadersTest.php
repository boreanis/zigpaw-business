<?php

namespace Tests\Feature;

use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalSecurityHeadersTest extends TestCase
{
    #[DataProvider('approvedOrigins')]
    public function test_form_navigation_allows_only_the_validated_identity_origin(string $environment, string $suffix, bool $qa): void
    {
        $this->configureOrigins($environment, $suffix, $qa);
        config()->set('platform.auth_url', strtoupper((string) config('platform.auth_url')).':443/');

        $response = $this->get('/health')->assertOk();
        $policy = $response->headers->get('Content-Security-Policy');

        self::assertIsString($policy);
        self::assertContains("form-action 'self' https://".($qa ? 'qa-auth' : 'auth').'.'.$suffix, explode('; ', $policy));
        self::assertContains("default-src 'self'", explode('; ', $policy));
        self::assertContains("base-uri 'self'", explode('; ', $policy));
        self::assertContains("frame-ancestors 'none'", explode('; ', $policy));
        self::assertContains("object-src 'none'", explode('; ', $policy));
        self::assertStringNotContainsString('https://untrusted.example', $policy);
        Http::assertNothingSent();
    }

    public function test_invitation_token_capture_preserves_no_referrer_while_ordinary_response_policy_is_unchanged(): void
    {
        $this->get('/health')->assertOk()->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->get('/team/invitations/019f5a00-0000-7000-8000-000000000031?token='.str_repeat('Q', 64))
            ->assertStatus(303)->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get('/team/invitations/019f5a00-0000-7000-8000-000000000031')
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public static function approvedOrigins(): array
    {
        return [
            'ordinary local' => ['local', 'zigpaw.test', false],
            'staged local QA' => ['local', 'zigpaw.test', true],
            'staging' => ['staging', 'staging.zigpaw.app', false],
            'production' => ['production', 'zigpaw.app', false],
        ];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_configuration_keeps_form_navigation_self_only(array $changes, string $environment): void
    {
        $this->configureOrigins('local', 'zigpaw.test', false);
        $this->app->instance('env', $environment);
        config()->set($changes);

        $policy = $this->get('/health')->assertOk()->headers->get('Content-Security-Policy');

        self::assertIsString($policy);
        $directives = explode('; ', $policy);
        self::assertContains("form-action 'self'", $directives);
        self::assertCount(1, array_filter($directives, fn (string $directive): bool => str_starts_with($directive, 'form-action ')));
        self::assertStringNotContainsString('untrusted', $policy);
        self::assertStringNotContainsString('qa-auth', $policy);
        Http::assertNothingSent();
    }

    public static function unsafeConfigurations(): array
    {
        $cases = [];
        foreach ([
            'https://untrusted.example',
            'https://auth.zigpaw.test.untrusted.example',
            'https://user:pass@auth.zigpaw.test',
            'https://auth.zigpaw.test:444',
            'https://auth.zigpaw.test/arbitrary',
            'https://auth.zigpaw.test?next=untrusted',
            'https://auth.zigpaw.test#untrusted',
            'http://auth.zigpaw.test',
            'https://auth.zigpaw.test; form-action https://untrusted.example',
            "https://auth.zigpaw.test\r\nX-Untrusted: yes",
            'https://auth.zigpaw.test:invalid',
        ] as $url) {
            $cases[] = [['platform.auth_url' => $url], 'local'];
        }
        $cases['unapproved API'] = [['platform.api_url' => 'https://untrusted.example'], 'local'];
        $cases['unapproved callback'] = [['platform.oauth_redirect_uri' => 'https://business.zigpaw.test/arbitrary'], 'local'];
        $cases['unapproved endpoint'] = [['platform.session_endpoint' => '/v1/untrusted'], 'local'];
        $cases['request host cannot configure issuer'] = [['app.url' => 'https://untrusted.example'], 'local'];
        $qa = [
            'app.url' => 'https://qa-business.zigpaw.test',
            'platform.api_url' => 'https://qa-api.zigpaw.test',
            'platform.auth_url' => 'https://qa-auth.zigpaw.test',
            'platform.oauth_redirect_uri' => 'https://qa-business.zigpaw.test/auth/callback',
        ];
        $cases['QA flag absent'] = [$qa, 'local'];
        $cases['QA wrong app host'] = [[...$qa, 'app.url' => 'https://business.zigpaw.test', 'platform.staged_browser_qa' => true], 'local'];
        $cases['QA forbidden in production'] = [[...$qa, 'platform.staged_browser_qa' => true], 'production'];

        return $cases;
    }

    #[DataProvider('logoutContexts')]
    public function test_logout_redirect_and_form_policy_preserve_context_bound_token_custody(bool $clinical, bool $trusted): void
    {
        $this->configureOrigins('local', 'zigpaw.test', true);
        $business = app(PortalAccessTokenStore::class);
        $clinicalTokens = app(ClinicalPortalAccessTokenStore::class);
        foreach (['management' => $business, 'clinical' => $clinicalTokens] as $context => $tokens) {
            $tokens->put(['access_token' => $context.'-token', 'refresh_token' => $context.'-refresh', 'expires_in' => 900]);
        }
        $endpoint = $clinical ? '/v1/business/clinical/session' : '/v1/business/session';
        $logoutUrl = ($trusted ? 'https://qa-auth.zigpaw.test' : 'https://untrusted.example')
            .'/session/end/019f5a00-0000-7000-8000-000000000099?nonce=synthetic&expires=1786400000&signature=synthetic';
        Http::fake(['https://qa-api.zigpaw.test'.$endpoint => Http::response(['data' => ['logout_url' => $logoutUrl]])]);

        session()->regenerateToken();
        $response = $this->post($clinical ? '/clinical/auth/logout' : '/auth/logout', ['_token' => session()->token()]);

        $response->assertRedirect($trusted ? $logoutUrl : route($clinical ? 'clinical.dashboard' : 'dashboard'));
        self::assertContains("form-action 'self' https://qa-auth.zigpaw.test", explode('; ', $response->headers->get('Content-Security-Policy')));
        self::assertNull($clinicalTokens->accessToken());
        self::assertSame($clinical ? 'management-token' : null, $business->accessToken());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://qa-api.zigpaw.test'.$endpoint
            && $request->hasHeader('Authorization', 'Bearer '.($clinical ? 'clinical' : 'management').'-token'));
    }

    public static function logoutContexts(): array
    {
        return [[false, true], [true, true], [false, false], [true, false]];
    }

    public function test_allowing_the_identity_origin_does_not_bypass_logout_csrf_or_trust_the_request_host(): void
    {
        $this->configureOrigins('local', 'zigpaw.test', true);
        $tokens = app(ClinicalPortalAccessTokenStore::class);
        $tokens->put(['access_token' => 'clinical-token', 'refresh_token' => 'clinical-refresh', 'expires_in' => 900]);

        $this->post('/clinical/auth/logout')->assertStatus(419);

        self::assertSame('clinical-token', $tokens->accessToken());
        $policy = $this->get('https://untrusted.example/health')->assertOk()->headers->get('Content-Security-Policy');
        self::assertContains("form-action 'self' https://qa-auth.zigpaw.test", explode('; ', $policy));
        self::assertStringNotContainsString('untrusted.example', $policy);
        Http::assertNothingSent();
    }

    private function configureOrigins(string $environment, string $suffix, bool $qa): void
    {
        $this->app->instance('env', $environment);
        $portal = 'https://'.($qa ? 'qa-business' : 'business').'.'.$suffix;
        config()->set([
            'app.url' => $portal,
            'platform.staged_browser_qa' => $qa,
            'platform.api_url' => 'https://'.($qa ? 'qa-api' : 'api').'.'.$suffix,
            'platform.auth_url' => 'https://'.($qa ? 'qa-auth' : 'auth').'.'.$suffix,
            'platform.oauth_redirect_uri' => $portal.'/auth/callback',
            'platform.session_endpoint' => '/v1/business/session',
            'platform_clinical.api_url' => 'https://'.($qa ? 'qa-api' : 'api').'.'.$suffix,
            'platform_clinical.auth_url' => 'https://'.($qa ? 'qa-auth' : 'auth').'.'.$suffix,
            'platform_clinical.oauth_redirect_uri' => $portal.'/clinical/auth/callback',
            'platform_clinical.session_endpoint' => '/v1/business/clinical/session',
        ]);
    }
}
