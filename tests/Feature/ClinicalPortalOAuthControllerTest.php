<?php

namespace Tests\Feature;

use App\Support\PortalAccessTokenStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClinicalPortalOAuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.url' => 'https://business.zigpaw.test',
            'platform_clinical.api_url' => 'https://api.zigpaw.test',
            'platform_clinical.auth_url' => 'https://login.zigpaw.test',
            'platform_clinical.session_endpoint' => '/v1/business/clinical/session',
            'platform_clinical.oauth_client_id' => 'clinical-portal-client',
            'platform_clinical.oauth_client_secret' => str_repeat('c', 40),
            'platform_clinical.oauth_scopes' => ['clinical:read', 'clinical:submit'],
            'platform_clinical.oauth_redirect_uri' => 'https://business.zigpaw.test/clinical/auth/callback',
        ]);
    }

    public function test_it_starts_clinical_pkce_sign_in_at_the_central_auth_host(): void
    {
        $response = $this->get('https://business.zigpaw.test/clinical/auth/login')
            ->assertRedirectContains('https://login.zigpaw.test/oauth/authorize?')
            ->assertSessionHas('platform.oauth.clinical.state')
            ->assertSessionHas('platform.oauth.clinical.verifier')
            ->assertSessionMissing('platform.oauth.state')
            ->assertSessionMissing('platform.oauth.verifier');

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('clinical-portal-client', $query['client_id'] ?? null);
        $this->assertSame('https://business.zigpaw.test/clinical/auth/callback', $query['redirect_uri'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame('clinical:read clinical:submit', $query['scope'] ?? null);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertNotSame('', $query['state'] ?? '');
        $this->assertNotSame('', $query['code_challenge'] ?? '');
    }

    public function test_it_redirects_an_alternate_host_before_starting_a_clinical_oauth_session(): void
    {
        $this->get('https://vets.zigpaw.test/clinical/auth/login')
            ->assertStatus(308)
            ->assertRedirect('https://business.zigpaw.test/clinical/auth/login')
            ->assertSessionMissing('platform.oauth.clinical.state')
            ->assertSessionMissing('platform.oauth.clinical.verifier');
    }

    public function test_it_rejects_a_clinical_oauth_callback_with_the_wrong_state(): void
    {
        $this->withSession([
            'platform.oauth.clinical.state' => 'expected',
            'platform.oauth.clinical.verifier' => 'verifier',
        ])->get(route('clinical.auth.callback', ['state' => 'unexpected', 'code' => 'code']))
            ->assertRedirect(route('clinical.dashboard'))
            ->assertSessionHas('error', 'That clinical sign-in link is no longer valid. Please try again.');
    }

    public function test_it_rejects_unsafe_clinical_identity_and_callback_configuration_before_sending_credentials(): void
    {
        Http::fake([
            'https://login.zigpaw.test.attacker.example/*' => Http::response([
                'access_token' => 'stolen',
                'refresh_token' => 'stolen',
                'expires_in' => 900,
            ]),
        ]);

        config()->set('platform_clinical.auth_url', 'https://login.zigpaw.test.attacker.example');
        $this->get('/clinical/auth/login')
            ->assertServiceUnavailable()
            ->assertSessionMissing('platform.oauth.clinical.state');

        config()->set([
            'platform_clinical.auth_url' => 'https://login.zigpaw.test',
            'platform_clinical.oauth_redirect_uri' => 'https://business.zigpaw.test.attacker.example/clinical/auth/callback',
        ]);
        $this->withSession([
            'platform.oauth.clinical.state' => 'expected',
            'platform.oauth.clinical.verifier' => 'verifier',
        ])->get(route('clinical.auth.callback', ['state' => 'expected', 'code' => 'code']))
            ->assertServiceUnavailable();

        Http::assertNothingSent();
    }

    public function test_local_clinical_sign_out_uses_visible_feedback_without_ending_the_business_session(): void
    {
        $businessTokens = app(PortalAccessTokenStore::class);
        $businessTokens->put([
            'access_token' => 'business-access-token',
            'refresh_token' => 'business-refresh-token',
            'expires_in' => 900,
        ]);

        $this->post(route('clinical.auth.logout'))
            ->assertRedirect(route('clinical.dashboard'))
            ->assertSessionHas('status', 'You are signed out of the clinical workspace on this device.');

        $this->assertSame('business-access-token', $businessTokens->accessToken());
    }
}
