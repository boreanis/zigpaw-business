<?php

namespace Tests\Feature\Livewire;

use App\Livewire\VetDashboard;
use App\Support\PortalAccessTokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class VetDashboardTest extends TestCase
{
    public function test_it_prompts_for_platform_sign_in_when_no_portal_token_exists(): void
    {
        Livewire::test(VetDashboard::class)
            ->assertSee('Care records, with families in control')
            ->assertSee('patient access stays limited to family-approved grants')
            ->assertDontSee('access tokens')
            ->assertDontSee('browser JavaScript');
    }

    public function test_the_shell_uses_the_contrasting_brand_for_each_appearance(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('class="brand-light" src="'.asset('brand/zigpaw-wordmark-light.svg').'"', false)
            ->assertSee('class="brand-dark" src="'.asset('brand/zigpaw-wordmark-dark.svg').'"', false);

        $this->assertStringNotContainsString(
            '.header-actions form { display: none;',
            (string) file_get_contents(resource_path('css/vets.css')),
        );
    }

    public function test_a_stale_organization_is_removed_when_the_secure_token_is_missing(): void
    {
        session()->put([
            'portal.organization_id' => 'stale-organization',
            'portal.organization_name' => 'Stale Clinic',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSessionMissing('portal.organization_id')
            ->assertSessionMissing('portal.organization_name')
            ->assertDontSee('Stale Clinic');
    }

    public function test_a_signed_in_account_without_an_eligible_organization_can_end_sso_and_switch_accounts(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/vets/organizations' => Http::response(['data' => []]),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Clinical access is not available')
            ->assertSee('Use another account')
            ->assertSee('Sign out')
            ->assertSee('action="'.route('auth.logout').'"', false);
    }
}
