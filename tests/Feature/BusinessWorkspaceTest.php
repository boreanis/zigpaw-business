<?php

namespace Tests\Feature;

use App\Livewire\BusinessWorkspace;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessWorkspaceTest extends TestCase
{
    public function test_sign_in_feedback_is_shown_on_the_signed_out_workspace(): void
    {
        $this->withSession(['error' => 'That sign-in link is no longer valid. Please try again.']);

        Livewire::test(BusinessWorkspace::class)
            ->assertSet('state', 'signed_out')
            ->assertSee('That sign-in link is no longer valid. Please try again.');
    }

    public function test_authenticated_business_sees_an_isolated_operational_overview(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'business-access-token',
            'refresh_token' => 'business-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/business/organizations' => Http::response(['data' => [[
                'id' => 'organization-1',
                'name' => 'Laidley Veterinary Surgery',
                'role' => 'owner',
            ]]]),
            'https://api.zigpaw.test/v1/business/me' => Http::response(['data' => [
                'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery', 'primary_country_code' => 'AU'],
                'membership' => ['role' => 'owner', 'capabilities' => [
                    'portal.view', 'organization.manage', 'providers.manage', 'bookings.manage', 'financials.view', 'team.manage', 'programs.view', 'programs.manage',
                ]],
                'features' => ['provider_claims' => true, 'booking_requests' => true],
                'managed_provider_count' => 1,
            ]]),
            'https://api.zigpaw.test/v1/business/providers*' => Http::response(self::page([[
                'id' => 'link-1',
                'status' => 'active',
                'authority_type' => 'location_manager',
                'provider' => ['name' => 'Laidley Veterinary Surgery'],
                'place' => ['name' => 'Laidley Veterinary Surgery'],
            ]])),
            'https://api.zigpaw.test/v1/business/bookings*' => Http::response(self::page([[
                'id' => 'booking-1',
                'request_number' => 'ZPBR20260815ABC123',
                'status' => 'requested',
                'pet' => ['name' => 'Diesel'],
                'provider' => ['name' => 'Laidley Veterinary Surgery'],
            ]])),
            'https://api.zigpaw.test/v1/business/programs*' => Http::response(self::page([])),
        ]);

        Livewire::test(BusinessWorkspace::class)
            ->assertSet('state', 'ready')
            ->assertSet('organizationId', 'organization-1')
            ->assertSee('Laidley Veterinary Surgery')
            ->assertSee('Diesel needs a response');
    }

    public function test_finance_role_loads_read_only_programs_and_financial_information(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'finance-access-token',
            'refresh_token' => 'finance-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/business/organizations' => Http::response(['data' => [[
                'id' => 'organization-1',
                'name' => 'Laidley Veterinary Surgery',
                'role' => 'finance',
            ]]]),
            'https://api.zigpaw.test/v1/business/me' => Http::response(['data' => [
                'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                'membership' => ['role' => 'finance', 'capabilities' => ['portal.view', 'financials.view', 'programs.view']],
                'features' => ['provider_claims' => false, 'booking_requests' => false],
            ]]),
            'https://api.zigpaw.test/v1/business/programs*' => Http::response(self::page([])),
            'https://api.zigpaw.test/v1/business/financials' => Http::response(['data' => ['currencies' => []]]),
            'https://api.zigpaw.test/v1/business/financials/commissions*' => Http::response(self::page([])),
            'https://api.zigpaw.test/v1/business/financials/agreements*' => Http::response(self::page([])),
        ]);

        $component = Livewire::test(BusinessWorkspace::class)
            ->assertSet('state', 'ready')
            ->assertSee('Revenue')
            ->assertSee('Partner programs')
            ->assertDontSee('Locations')
            ->assertDontSee('Bookings');

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/providers')
            || str_contains($request->url(), '/bookings'));

        $component->call('showSection', 'revenue')
            ->assertSet('section', 'revenue')
            ->assertSee('Commission activity and agreements');

        $component->call('showSection', 'programs')
            ->assertSet('section', 'programs')
            ->assertDontSee('Apply')
            ->call('applyForReferralProgram')
            ->assertForbidden();
    }

    public function test_disabled_booking_rollout_removes_booking_navigation_and_blocks_tampering(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'operator-access-token',
            'refresh_token' => 'operator-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/business/organizations' => Http::response(['data' => [[
                'id' => 'organization-1',
                'name' => 'Laidley Veterinary Surgery',
                'role' => 'operator',
            ]]]),
            'https://api.zigpaw.test/v1/business/me' => Http::response(['data' => [
                'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                'membership' => ['role' => 'operator', 'capabilities' => ['portal.view', 'bookings.manage']],
                'features' => ['provider_claims' => false, 'booking_requests' => false],
            ]]),
        ]);

        Livewire::test(BusinessWorkspace::class)
            ->assertSet('state', 'ready')
            ->assertDontSee('Bookings')
            ->call('showSection', 'bookings')
            ->assertForbidden();

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/bookings'));
    }

    public function test_organization_manager_can_update_the_business_profile_without_exposing_it_to_viewers(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/business/organizations' => Http::response(['data' => [[
                'id' => 'organization-1',
                'name' => 'Laidley Veterinary Surgery',
                'role' => 'manager',
            ]]]),
            'https://api.zigpaw.test/v1/business/me' => Http::sequence()
                ->push(['data' => [
                    'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery', 'primary_country_code' => 'AU'],
                    'membership' => ['role' => 'manager', 'capabilities' => ['portal.view', 'organization.manage']],
                    'features' => ['provider_claims' => false, 'booking_requests' => false],
                    'business_profile' => ['legal_name' => 'Laidley Veterinary Surgery Pty Ltd', 'registered_country_code' => 'AU'],
                ]])
                ->push(['data' => [
                    'organization' => ['id' => 'organization-1', 'name' => 'Laidley Animal Care', 'primary_country_code' => 'AU'],
                    'membership' => ['role' => 'manager', 'capabilities' => ['portal.view', 'organization.manage']],
                    'features' => ['provider_claims' => false, 'booking_requests' => false],
                    'business_profile' => ['legal_name' => 'Laidley Veterinary Surgery Pty Ltd', 'registered_country_code' => 'AU'],
                ]]),
        ]);

        Livewire::test(BusinessWorkspace::class)
            ->assertSee('Business profile')
            ->call('showSection', 'profile')
            ->set('businessName', 'Laidley Animal Care')
            ->call('saveBusinessProfile')
            ->assertSet('businessName', 'Laidley Animal Care')
            ->assertSee('Business profile saved.');

        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && $request->url() === 'https://api.zigpaw.test/v1/business/me'
            && $request['name'] === 'Laidley Animal Care');
    }

    public function test_role_and_feature_boundaries_hide_workspace_configuration_from_an_operator(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'operator-access-token',
            'refresh_token' => 'operator-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/business/organizations' => Http::response(['data' => [[
                'id' => 'organization-1',
                'name' => 'Laidley Veterinary Surgery',
                'role' => 'operator',
            ]]]),
            'https://api.zigpaw.test/v1/business/me' => Http::response(['data' => [
                'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                'membership' => ['role' => 'operator', 'capabilities' => ['portal.view', 'bookings.manage']],
                'features' => ['provider_claims' => true, 'booking_requests' => true],
            ]]),
            'https://api.zigpaw.test/v1/business/bookings*' => Http::response(self::page([])),
        ]);

        $component = Livewire::test(BusinessWorkspace::class)
            ->assertSee('Bookings')
            ->assertDontSee('Business profile')
            ->assertDontSee('Booking setup');

        $component->call('showSection', 'booking-setup')->assertForbidden();

        Livewire::test(BusinessWorkspace::class)
            ->call('saveBusinessProfile')
            ->assertForbidden();

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/providers')
            || str_contains($request->url(), '/booking-profiles'));
    }

    public function test_provider_manager_can_edit_an_existing_service(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake([
            'https://api.zigpaw.test/v1/business/organizations' => Http::response(['data' => [[
                'id' => 'organization-1',
                'name' => 'Laidley Veterinary Surgery',
                'role' => 'manager',
            ]]]),
            'https://api.zigpaw.test/v1/business/me' => Http::response(['data' => [
                'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                'membership' => ['role' => 'manager', 'capabilities' => ['portal.view', 'providers.manage']],
                'features' => ['provider_claims' => false, 'booking_requests' => false],
            ]]),
            'https://api.zigpaw.test/v1/business/offerings/offering-1' => Http::response(['data' => [
                'id' => 'offering-1',
                'name' => 'Wellness consultation',
                'status' => 'active',
            ]]),
            'https://api.zigpaw.test/v1/business/providers*' => Http::response(self::page([[
                'id' => 'link-1',
                'status' => 'active',
                'authority_type' => 'manager',
                'provider' => ['id' => 'provider-1', 'name' => 'Laidley Veterinary Surgery'],
                'place' => ['id' => 'place-1', 'name' => 'Laidley Veterinary Surgery'],
            ]])),
            'https://api.zigpaw.test/v1/business/offerings*' => Http::response(self::page([[
                'id' => 'offering-1',
                'name' => 'Wellness consultation',
                'description' => null,
                'default_duration_minutes' => 30,
                'status' => 'draft',
                'request_mode' => 'request',
                'place' => ['name' => 'Laidley Veterinary Surgery'],
            ]])),
        ]);

        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'services')
            ->call('startOfferingEdit', 'offering-1')
            ->set('editingOfferingStatus', 'active')
            ->call('saveOffering')
            ->assertSee('Service updated.');

        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && $request->url() === 'https://api.zigpaw.test/v1/business/offerings/offering-1'
            && $request['status'] === 'active');
    }

    public function test_provider_manager_searches_selects_and_claims_a_directory_listing(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://api.zigpaw.test/v1/business/organizations') {
                return Http::response(['data' => [[
                    'id' => 'organization-1',
                    'name' => 'Laidley Animal Care',
                    'role' => 'manager',
                ]]]);
            }

            if ($request->url() === 'https://api.zigpaw.test/v1/business/me') {
                return Http::response(['data' => [
                    'organization' => ['id' => 'organization-1', 'name' => 'Laidley Animal Care', 'primary_country_code' => 'AU'],
                    'membership' => ['role' => 'manager', 'capabilities' => ['portal.view', 'providers.manage']],
                    'features' => ['provider_claims' => true, 'booking_requests' => false],
                ]]);
            }

            if (str_contains($request->url(), '/provider-claims/discovery')) {
                return Http::response(self::page([[
                    'service_provider_id' => 'provider-1',
                    'place_id' => 'place-1',
                    'provider_name' => 'Laidley Veterinary Group',
                    'branch_name' => 'Laidley Veterinary Surgery',
                    'address' => '1 Patrick Street, Laidley, QLD, 4341, AU',
                    'country_code' => 'AU',
                    'verification_status' => 'unverified',
                    'categories' => ['Veterinary clinic'],
                ]]));
            }

            if ($request->url() === 'https://api.zigpaw.test/v1/business/provider-claims' && $request->method() === 'POST') {
                return Http::response(['data' => ['id' => 'claim-1', 'status' => 'pending']], 201);
            }

            if (str_contains($request->url(), '/provider-claims')) {
                return Http::response(self::page([]));
            }

            if (str_contains($request->url(), '/providers')) {
                return Http::response(self::page([]));
            }

            return Http::response(['data' => []]);
        });

        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'listings')
            ->assertSee('Find your public listing')
            ->assertDontSee('service_provider_id')
            ->set('claimSearch', 'Laidley Veterinary')
            ->call('searchClaimableProviders')
            ->assertSee('Laidley Veterinary Surgery')
            ->assertSee('1 Patrick Street, Laidley, QLD, 4341, AU')
            ->call('selectClaimableProvider', 'provider-1', 'place-1')
            ->set('claimAuthorityType', 'manager')
            ->set('claimVerificationMethod', 'domain_email')
            ->set('claimantEmail', 'manager@example.test')
            ->call('submitProviderClaim')
            ->assertSee('Claim submitted. We will show verification progress here.');

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_contains($request->url(), '/provider-claims/discovery')
                && ($query['q'] ?? null) === 'Laidley Veterinary';
        });
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.zigpaw.test/v1/business/provider-claims'
            && $request['service_provider_id'] === 'provider-1'
            && $request['place_id'] === 'place-1'
            && $request['requested_authority_type'] === 'manager'
            && $request['verification_method'] === 'domain_email');
    }

    /** @param list<array<string, mixed>> $data @return array<string, mixed> */
    private static function page(array $data): array
    {
        return [
            'data' => $data,
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => count($data)],
        ];
    }
}
