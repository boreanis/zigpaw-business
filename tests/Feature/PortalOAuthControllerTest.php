<?php

namespace Tests\Feature;

use App\Support\TeamInvitationContinuation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalOAuthControllerTest extends TestCase
{
    public function test_it_starts_pkce_sign_in_at_the_central_auth_host(): void
    {
        config([
            'app.url' => 'https://partners.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_redirect_uri' => 'https://partners.zigpaw.test/auth/callback',
        ]);

        $response = $this->get('https://partners.zigpaw.test/auth/login')
            ->assertRedirectContains('https://auth.zigpaw.test/oauth/authorize?')
            ->assertSessionHas('platform.oauth.state')
            ->assertSessionHas('platform.oauth.verifier');

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('https://partners.zigpaw.test/auth/callback', $query['redirect_uri'] ?? null);
    }

    public function test_partner_application_resumes_at_programs_only_for_the_sign_in_that_started_it(): void
    {
        config(['platform.oauth_client_id' => 'synthetic-client']);
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'example-access', 'refresh_token' => 'example-refresh', 'expires_in' => 900,
        ])]);

        $this->get(route('auth.login', ['intent' => 'join']))->assertRedirectContains('intent=join');
        $this->get(route('auth.callback', ['state' => session('platform.oauth.state'), 'code' => 'code']))
            ->assertRedirect(route('dashboard', ['section' => 'programs']));

        $this->get(route('auth.login', ['intent' => 'join']));
        $this->get(route('auth.login'));
        $this->get(route('auth.callback', ['state' => session('platform.oauth.state'), 'code' => 'code', 'intent' => 'join']))
            ->assertRedirect(route('dashboard'));
    }

    public function test_it_redirects_an_alternate_host_before_starting_an_oauth_session(): void
    {
        config([
            'app.url' => 'https://partners.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_redirect_uri' => 'https://partners.zigpaw.test/auth/callback',
        ]);

        $this->get('https://zigpaw-partners.test/auth/login')
            ->assertStatus(308)
            ->assertRedirect('https://partners.zigpaw.test/auth/login')
            ->assertSessionMissing('platform.oauth.state')
            ->assertSessionMissing('platform.oauth.verifier');
    }

    public function test_it_rejects_a_noncanonical_identity_origin_before_starting_oauth(): void
    {
        config([
            'app.url' => 'https://partners.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_redirect_uri' => 'https://partners.zigpaw.test/auth/callback',
            'platform.auth_url' => 'https://auth.zigpaw.test.attacker.example',
        ]);

        $this->get('https://partners.zigpaw.test/auth/login')
            ->assertServiceUnavailable()
            ->assertSessionMissing('platform.oauth.state')
            ->assertSessionMissing('platform.oauth.verifier');
    }

    public function test_it_rejects_an_oauth_callback_with_the_wrong_state(): void
    {
        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('auth.callback', ['state' => 'unexpected', 'code' => 'code']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'That sign-in link is no longer valid. Please try again.');
    }

    public function test_it_handles_a_malformed_token_response_without_a_server_error(): void
    {
        config([
            'app.url' => 'https://partners.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_redirect_uri' => 'https://partners.zigpaw.test/auth/callback',
        ]);
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response(['data' => 'not-a-token-envelope'])]);

        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('auth.callback', [
            'state' => 'expected',
            'code' => 'code',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Zigpaw could not complete sign-in. Please try again.');
    }

    public function test_it_exchanges_a_callback_only_with_the_canonical_identity_origin(): void
    {
        config([
            'app.url' => 'https://partners.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_redirect_uri' => 'https://partners.zigpaw.test/auth/callback',
        ]);
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'business-access-token',
            'refresh_token' => 'business-refresh-token',
            'expires_in' => 900,
        ])]);

        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('auth.callback', [
            'state' => 'expected',
            'code' => 'code',
        ]))->assertRedirect(route('dashboard'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://auth.zigpaw.test/oauth/token'
            && $request['code'] === 'code'
            && $request['code_verifier'] === 'verifier'
            && $request['redirect_uri'] === 'https://partners.zigpaw.test/auth/callback');
        $this->assertNotNull(session('platform.oauth.token_handle'));
    }

    public function test_callback_query_cannot_select_the_token_exchange_origin(): void
    {
        Http::fake([
            'https://auth.zigpaw.test/oauth/token' => Http::response([
                'access_token' => 'business-access-token',
                'refresh_token' => 'business-refresh-token',
                'expires_in' => 900,
            ]),
            'https://attacker.example/*' => Http::response(['access_token' => 'stolen']),
        ]);

        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('auth.callback', [
            'state' => 'expected',
            'code' => 'code',
            'issuer_hint' => 'https://attacker.example/oauth/token',
        ]))->assertRedirect(route('dashboard'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://auth.zigpaw.test/oauth/token');
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://attacker.example/'));
    }

    public function test_it_rejects_a_token_response_missing_required_refresh_credentials(): void
    {
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'incomplete-access-token',
            'expires_in' => 900,
        ])]);

        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('auth.callback', [
            'state' => 'expected',
            'code' => 'code',
        ]))->assertRedirect(route('dashboard'));

        $this->assertNull(session('platform.oauth.token_handle'));
    }

    public function test_only_a_protected_invitation_continuation_can_resume_after_normal_state_and_pkce_exchange(): void
    {
        $membership = '019f5a00-0000-7000-8000-000000000031';
        $token = str_repeat('Q', 64);
        config(['platform.oauth_client_id' => 'synthetic-client']);
        app(TeamInvitationContinuation::class)->capture($membership, $token);
        $this->get(route('auth.login', ['invitation' => $membership, 'return_to' => 'https://untrusted.example']))->assertRedirectContains('https://auth.zigpaw.test/oauth/authorize?');
        $state = session('platform.oauth.state');
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'synthetic-access-token', 'refresh_token' => 'synthetic-refresh-token', 'expires_in' => 900,
        ])]);
        $this->get(route('auth.callback', ['state' => $state, 'code' => 'synthetic-code', 'return_to' => 'https://untrusted.example']))
            ->assertRedirect(route('team.invitations.show', ['membership' => $membership]))->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee($token);
        $this->assertNotNull(app(TeamInvitationContinuation::class)->entry($membership));
    }

    public function test_booking_continuation_is_exact_session_bound_and_single_use(): void
    {
        $booking = '019f5a00-0000-7000-8000-000000000041';
        config(['platform.oauth_client_id' => 'synthetic-client']);
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'synthetic-access-token', 'refresh_token' => 'synthetic-refresh-token', 'expires_in' => 900,
        ])]);

        $response = $this->get(route('auth.login', ['booking' => $booking, 'booking_status' => 'requested']))
            ->assertRedirectContains('https://auth.zigpaw.test/oauth/authorize?');
        self::assertStringNotContainsString($booking, (string) $response->headers->get('Location'));
        $state = session('platform.oauth.state');
        $callback = route('auth.callback', [
            'state' => $state,
            'code' => 'synthetic-booking-code',
            'booking' => '019f5a00-0000-7000-8000-000000000099',
            'return_to' => 'https://untrusted.example',
        ]);
        $this->get($callback)
            ->assertRedirect(route('bookings.show', ['booking' => $booking, 'status' => 'requested']))
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSessionMissing('platform.oauth.booking_return');
        $this->get($callback)->assertRedirect(route('dashboard'));
        Http::assertSentCount(1);
    }

    public function test_expired_or_replaced_booking_continuation_cannot_direct_sign_in(): void
    {
        $booking = '019f5a00-0000-7000-8000-000000000041';
        config(['platform.oauth_client_id' => 'synthetic-client']);
        Http::fake(['https://auth.zigpaw.test/oauth/token' => Http::response([
            'access_token' => 'synthetic-access-token', 'refresh_token' => 'synthetic-refresh-token', 'expires_in' => 900,
        ])]);

        $this->freezeSecond();
        $this->get(route('auth.login', ['booking' => $booking]))->assertRedirect();
        $this->travel(10)->minutes();
        $this->get(route('auth.callback', ['state' => session('platform.oauth.state'), 'code' => 'synthetic-booking-code']))
            ->assertRedirect(route('dashboard'))->assertSessionMissing('platform.oauth.booking_return');

        $this->get(route('auth.login', ['booking' => $booking]))->assertRedirect();
        $this->get(route('auth.login', ['booking' => 'https://untrusted.example']))->assertRedirect();
        $this->get(route('auth.callback', ['state' => session('platform.oauth.state'), 'code' => 'another-synthetic-code']))
            ->assertRedirect(route('dashboard'))->assertSessionMissing('platform.oauth.booking_return');
        Http::assertSentCount(2);
    }

    public function test_unknown_browser_selected_invitation_cannot_become_an_oauth_return_destination(): void
    {
        config(['platform.oauth_client_id' => 'synthetic-client']);
        $this->get(route('auth.login', ['invitation' => '019f5a00-0000-7000-8000-000000000099']))->assertRedirect();
        $this->assertNull(session('business.team_invitation_oauth_return'));
    }
}
