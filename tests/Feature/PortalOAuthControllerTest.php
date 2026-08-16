<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalOAuthControllerTest extends TestCase
{
    public function test_it_starts_pkce_sign_in_at_the_central_auth_host(): void
    {
        config([
            'app.url' => 'https://vets.zigpaw.test',
            'platform.oauth_client_id' => 'vet-portal-client',
            'platform.oauth_redirect_uri' => 'https://vets.zigpaw.test/auth/callback',
        ]);

        $response = $this->get('https://vets.zigpaw.test/auth/login')
            ->assertRedirectContains('https://login.zigpaw.test/oauth/authorize?')
            ->assertSessionHas('platform.oauth.state')
            ->assertSessionHas('platform.oauth.verifier');

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('https://vets.zigpaw.test/auth/callback', $query['redirect_uri'] ?? null);
    }

    public function test_it_redirects_an_alternate_host_before_starting_an_oauth_session(): void
    {
        config([
            'app.url' => 'https://vets.zigpaw.test',
            'platform.oauth_client_id' => 'vet-portal-client',
            'platform.oauth_redirect_uri' => 'https://vets.zigpaw.test/auth/callback',
        ]);

        $this->get('https://zigpaw-vets.test/auth/login')
            ->assertStatus(308)
            ->assertRedirect('https://vets.zigpaw.test/auth/login')
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

    public function test_it_rejects_unsafe_identity_and_callback_configuration_before_sending_credentials(): void
    {
        config([
            'platform.auth_url' => 'https://login.zigpaw.test.attacker.example',
            'platform.oauth_redirect_uri' => 'https://attacker.example/auth/callback',
        ]);
        Http::preventStrayRequests();

        $this->get('/auth/login')->assertServiceUnavailable();
        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('auth.callback', ['state' => 'expected', 'code' => 'code']))
            ->assertServiceUnavailable();
        Http::assertNothingSent();
    }

    public function test_local_sign_out_confirmation_uses_the_visible_success_feedback(): void
    {
        $this->post('/auth/logout')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'You are signed out on this device.');
    }
}
