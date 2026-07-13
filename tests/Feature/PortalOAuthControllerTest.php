<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalOAuthControllerTest extends TestCase
{
    public function test_it_starts_pkce_sign_in_at_the_central_auth_host(): void
    {
        config(['platform.oauth_client_id' => 'partner-portal-client']);

        $this->get(route('oauth.redirect'))
            ->assertRedirectContains('https://auth.zigpaw.test/oauth/authorize?')
            ->assertSessionHas('platform.oauth.state')
            ->assertSessionHas('platform.oauth.verifier');
    }

    public function test_it_rejects_an_oauth_callback_with_the_wrong_state(): void
    {
        $this->withSession([
            'platform.oauth.state' => 'expected',
            'platform.oauth.verifier' => 'verifier',
        ])->get(route('oauth.callback', ['state' => 'unexpected', 'code' => 'code']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'That sign-in link is no longer valid. Please try again.');
    }
}
