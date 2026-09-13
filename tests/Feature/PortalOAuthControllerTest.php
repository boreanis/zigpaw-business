<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalOAuthControllerTest extends TestCase
{
    public function test_it_starts_pkce_sign_in_at_the_central_auth_host(): void
    {
        config([
            'app.url' => 'https://business.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
        ]);

        $response = $this->get('https://business.zigpaw.test/auth/login')
            ->assertRedirectContains('https://auth.zigpaw.test/oauth/authorize?')
            ->assertSessionHas('platform.oauth.state')
            ->assertSessionHas('platform.oauth.verifier');

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('https://business.zigpaw.test/auth/callback', $query['redirect_uri'] ?? null);
    }

    public function test_it_redirects_an_alternate_host_before_starting_an_oauth_session(): void
    {
        config([
            'app.url' => 'https://business.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
        ]);

        $this->get('https://zigpaw-partners.test/auth/login')
            ->assertStatus(308)
            ->assertRedirect('https://business.zigpaw.test/auth/login')
            ->assertSessionMissing('platform.oauth.state')
            ->assertSessionMissing('platform.oauth.verifier');
    }

    public function test_it_rejects_a_noncanonical_identity_origin_before_starting_oauth(): void
    {
        config([
            'app.url' => 'https://business.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
            'platform.auth_url' => 'https://auth.zigpaw.test.attacker.example',
        ]);

        $this->get('https://business.zigpaw.test/auth/login')
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
            'app.url' => 'https://business.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
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
            'app.url' => 'https://business.zigpaw.test',
            'platform.oauth_client_id' => 'partner-portal-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
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
            && $request['code_verifier'] === 'verifier');
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
}
