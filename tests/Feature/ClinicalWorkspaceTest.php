<?php

namespace Tests\Feature;

use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClinicalWorkspaceTest extends TestCase
{
    public function test_clinical_workspace_has_its_own_signed_out_entry_point(): void
    {
        $this->get('/clinical')
            ->assertOk()
            ->assertSee('Zigpaw Business clinical workspace')
            ->assertSee('Sign in securely');

        $this->get('/clinical/patients')
            ->assertRedirect(route('clinical.dashboard'))
            ->assertSessionHas('error', 'Your secure clinical session has ended. Please sign in again.');
    }

    public function test_clinical_api_calls_use_the_canonical_audience_and_separate_credentials(): void
    {
        app(ClinicalPortalAccessTokenStore::class)->put([
            'access_token' => 'clinical-access-token',
            'refresh_token' => 'clinical-refresh-token',
            'expires_in' => 900,
        ]);

        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/organizations' => Http::response([
                'data' => [['id' => 'organization-1', 'name' => 'Riverbank Clinic']],
            ]),
        ]);

        $organizations = app(PlatformApiClient::class)->clinicalOrganizations(
            app(ClinicalPortalAccessTokenStore::class),
        );

        $this->assertSame('organization-1', $organizations[0]['id']);
        $this->assertNull(app(PortalAccessTokenStore::class)->accessToken());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.zigpaw.test/v1/business/clinical/organizations'
            && $request->hasHeader('Authorization', 'Bearer clinical-access-token')
            && ! $request->hasHeader('X-Zigpaw-Organization-ID'));
    }
}
