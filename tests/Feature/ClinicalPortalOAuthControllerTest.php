<?php

namespace Tests\Feature;

use App\Support\ClinicalReturnDestination;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ClinicalPortalOAuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.url' => 'https://partners.zigpaw.test',
            'platform_clinical.api_url' => 'https://api.zigpaw.test',
            'platform_clinical.auth_url' => 'https://auth.zigpaw.test',
            'platform_clinical.session_endpoint' => '/v1/business/clinical/session',
            'platform_clinical.oauth_client_id' => 'clinical-portal-client',
            'platform_clinical.oauth_client_secret' => str_repeat('c', 40),
            'platform_clinical.oauth_scopes' => ['clinical:read', 'clinical:submit'],
            'platform_clinical.oauth_redirect_uri' => 'https://partners.zigpaw.test/clinical/auth/callback',
        ]);
    }

    public function test_it_starts_clinical_pkce_sign_in_at_the_central_auth_host(): void
    {
        $response = $this->get('https://partners.zigpaw.test/clinical/auth/login')
            ->assertRedirectContains('https://auth.zigpaw.test/oauth/authorize?')
            ->assertSessionHas(ClinicalReturnDestination::BROWSER_CONTEXT)
            ->assertSessionMissing('platform.oauth.state')
            ->assertSessionMissing('platform.oauth.verifier');

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('clinical-portal-client', $query['client_id'] ?? null);
        $this->assertSame('https://partners.zigpaw.test/clinical/auth/callback', $query['redirect_uri'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame('clinical:read clinical:submit', $query['scope'] ?? null);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertNotSame('', $query['state'] ?? '');
        $this->assertNotSame('', $query['code_challenge'] ?? '');
    }

    public function test_two_clinical_tabs_restore_their_own_exact_task_in_reverse_callback_order(): void
    {
        $grant = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'synthetic-clinical-access', 'refresh_token' => 'synthetic-clinical-refresh', 'expires_in' => 900,
        ])]);
        $patient = $this->withSession(['clinical_portal.organization_id' => 'clinic-1'])
            ->get('/clinical/patients/'.$grant.'?search=Private+synthetic')->assertRedirect();
        $list = $this->get('/clinical/patients?status=expiring_soon')->assertRedirect();
        $patientLaunch = $this->get(route('clinical.auth.login', ['resume' => $this->redirectParameter($patient, 'resume')]))->assertRedirect();
        $listLaunch = $this->get(route('clinical.auth.login', ['resume' => $this->redirectParameter($list, 'resume')]))->assertRedirect();
        $this->assertStringNotContainsString($grant, $patientLaunch->headers->get('Location'));
        $this->assertStringNotContainsString('expiring_soon', $listLaunch->headers->get('Location'));
        $patientState = $this->redirectParameter($patientLaunch, 'state');
        $listState = $this->redirectParameter($listLaunch, 'state');
        $this->assertNotSame($patientState, $listState);
        $this->assertNotSame($this->redirectParameter($patientLaunch, 'code_challenge'), $this->redirectParameter($listLaunch, 'code_challenge'));

        $listCallback = $this->get(route('clinical.auth.callback', ['state' => $listState, 'code' => 'synthetic-list']))->assertRedirect();
        $patientCallback = $this->get(route('clinical.auth.callback', ['state' => $patientState, 'code' => 'synthetic-patient']))->assertRedirect();
        $continuation = app(ClinicalReturnDestination::class);
        $listResume = $this->redirectParameter($listCallback, 'resume');
        $patientResume = $this->redirectParameter($patientCallback, 'resume');
        $this->assertSame(['clinical.patients.show', ['grantId' => $grant]], $continuation->consume($patientResume, 'clinic-1'));
        $this->assertSame(['clinical.patients.index', ['status' => 'expiring_soon']], $continuation->consume($listResume, 'clinic-1'));
        $this->assertNull($continuation->consume($patientResume, 'clinic-1'));
        $this->get(route('clinical.auth.callback', ['state' => $listState, 'code' => 'already-used']))
            ->assertSessionHas('error', 'That clinical sign-in link is no longer valid. Please try again.');
        Http::assertSentCount(2);

        $wrongWorkspace = $continuation->remember('clinical.patients.index', 'expiring_soon', authenticated: true);
        $this->assertNull($continuation->consume($wrongWorkspace, 'clinic-2'));
        $this->assertNull($continuation->consume($wrongWorkspace, 'clinic-1'));
    }

    private function redirectParameter(TestResponse $response, string $name): string
    {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertIsString($query[$name] ?? null);

        return $query[$name];
    }

    public function test_it_redirects_an_alternate_host_before_starting_a_clinical_oauth_session(): void
    {
        $this->get('https://vets.zigpaw.test/clinical/auth/login')
            ->assertStatus(308)
            ->assertRedirect('https://partners.zigpaw.test/clinical/auth/login')
            ->assertSessionMissing(ClinicalReturnDestination::BROWSER_CONTEXT);
    }

    public function test_it_rejects_a_clinical_oauth_callback_with_the_wrong_state(): void
    {
        $state = str_repeat('E', 64);
        app(ClinicalReturnDestination::class)->begin(null, $state, 'verifier');
        $this->get(route('clinical.auth.callback', ['state' => 'unexpected', 'code' => 'code']))
            ->assertRedirect(route('clinical.dashboard'))
            ->assertSessionHas('error', 'That clinical sign-in link is no longer valid. Please try again.');
    }

    public function test_it_handles_a_malformed_clinical_token_response_without_a_server_error(): void
    {
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response(['data' => 'not-a-token-envelope'])]);

        $state = str_repeat('E', 64);
        app(ClinicalReturnDestination::class)->begin(null, $state, 'verifier');
        $this->get(route('clinical.auth.callback', [
            'state' => $state,
            'code' => 'code',
        ]))
            ->assertRedirect(route('clinical.dashboard'))
            ->assertSessionHas('error', 'Zigpaw could not complete clinical sign-in. Please try again.');
    }

    public function test_it_exchanges_a_callback_only_with_the_canonical_identity_origin(): void
    {
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'clinical-access-token',
            'refresh_token' => 'clinical-refresh-token',
            'expires_in' => 900,
        ])]);

        $state = str_repeat('E', 64);
        app(ClinicalReturnDestination::class)->begin(null, $state, 'verifier');
        $this->get(route('clinical.auth.callback', [
            'state' => $state,
            'code' => 'code',
        ]))->assertRedirect(route('clinical.dashboard'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://auth.zigpaw.test/oauth/token'
            && $request['code'] === 'code'
            && $request['code_verifier'] === 'verifier'
            && $request['redirect_uri'] === 'https://partners.zigpaw.test/clinical/auth/callback');
        $this->assertNotNull(session('platform.oauth.clinical_token_handle'));
    }

    public function test_clinical_callback_query_cannot_select_the_token_exchange_origin(): void
    {
        Http::fake([
            'https://auth.zigpaw.test/oauth/token' => Http::response([
                'access_token' => 'clinical-access-token',
                'refresh_token' => 'clinical-refresh-token',
                'expires_in' => 900,
            ]),
            'https://attacker.example/*' => Http::response(['access_token' => 'stolen']),
        ]);

        $state = str_repeat('E', 64);
        app(ClinicalReturnDestination::class)->begin(null, $state, 'verifier');
        $this->get(route('clinical.auth.callback', [
            'state' => $state,
            'code' => 'code',
            'issuer_hint' => 'https://attacker.example/oauth/token',
        ]))->assertRedirect(route('clinical.dashboard'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://auth.zigpaw.test/oauth/token');
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://attacker.example/'));
    }

    public function test_it_rejects_unsafe_clinical_identity_and_callback_configuration_before_sending_credentials(): void
    {
        Http::fake([
            'https://auth.zigpaw.test.attacker.example/*' => Http::response([
                'access_token' => 'stolen',
                'refresh_token' => 'stolen',
                'expires_in' => 900,
            ]),
        ]);

        config()->set('platform_clinical.auth_url', 'https://auth.zigpaw.test.attacker.example');
        $this->get('/clinical/auth/login')
            ->assertServiceUnavailable()
            ->assertSessionMissing(ClinicalReturnDestination::BROWSER_CONTEXT);

        config()->set([
            'platform_clinical.auth_url' => 'https://auth.zigpaw.test',
            'platform_clinical.oauth_redirect_uri' => 'https://partners.zigpaw.test.attacker.example/clinical/auth/callback',
        ]);
        $state = str_repeat('E', 64);
        app(ClinicalReturnDestination::class)->begin(null, $state, 'verifier');
        $this->get(route('clinical.auth.callback', ['state' => $state, 'code' => 'code']))
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
