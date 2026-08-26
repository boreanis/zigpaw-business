<?php

namespace Tests\Feature;

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
            ->assertRedirectContains('https://login.zigpaw.test/oauth/authorize?')
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
        Http::fake(['https://login.zigpaw.test/oauth/token' => Http::response(['data' => 'not-a-token-envelope'])]);

        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('auth.callback', ['state' => 'expected', 'code' => 'code']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Zigpaw could not complete sign-in. Please try again.');
    }
}
