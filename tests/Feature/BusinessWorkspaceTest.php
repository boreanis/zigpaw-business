<?php

namespace Tests\Feature;

use App\Livewire\BusinessWorkspace;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessWorkspaceTest extends TestCase
{
    public function test_livewire_sessions_are_blocked_before_loading_persisted_state(): void
    {
        $this->assertTrue((bool) config('session.block'));
        $this->assertSame(60, config('session.block_lock_seconds'));
        $this->assertSame(30, config('session.block_wait_seconds'));
    }

    public function test_uncertain_management_mutation_reuses_the_session_key_after_component_reload(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);
        $keys = [];
        Http::fake(function (Request $request) use (&$keys) {
            if ($request->url() === 'https://api.zigpaw.test/v1/business/organizations') {
                return Http::response(['data' => [['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery']]]);
            }
            if ($request->url() === 'https://api.zigpaw.test/v1/business/me') {
                return Http::response(['data' => [
                    'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                    'membership' => ['id' => 'membership-1', 'role' => 'manager', 'capabilities' => ['portal.view', 'providers.manage']],
                    'features' => ['provider_claims' => false, 'booking_requests' => false],
                ]]);
            }
            if (str_contains($request->url(), '/providers')) {
                return Http::response(self::page([['id' => 'link-1', 'provider' => ['name' => 'Laidley Veterinary Surgery']]]));
            }
            if ($request->method() === 'POST' && $request->url() === 'https://api.zigpaw.test/v1/business/offerings') {
                $keys[] = $request->header('Idempotency-Key')[0] ?? null;
                if (count($keys) === 1) {
                    throw new ConnectionException('synthetic lost response');
                }
                if (count($keys) === 2) {
                    return Http::response(['message' => 'A request with this idempotency key is still processing.'], 409);
                }

                return Http::response(['data' => ['id' => 'offering-1']], 201);
            }
            if (str_contains($request->url(), '/offerings')) {
                return Http::response(self::page([]));
            }

            return Http::response(['data' => []]);
        });

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'link-1')
            ->set('offeringName', 'Wellness consultation')
            ->call('createOffering')
            ->assertSee('Zigpaw is unavailable right now. Please try again shortly.');

        $this->assertCount(1, (array) session('portal.pending_mutations'));
        $this->assertStringNotContainsString('Wellness consultation', json_encode(session('portal.pending_mutations')));

        $pending = session('portal.pending_mutations');
        $fingerprint = (string) array_key_first($pending);
        $pending[$fingerprint]['created_at'] = now()->getTimestamp() - 841;
        session()->put('portal.pending_mutations', $pending);
        session()->save();

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'link-1')
            ->set('offeringName', 'Wellness consultation')
            ->call('createOffering')
            ->assertSee('This update is older than the safe retry window. Please start a deliberate new update after confirming its result.');
        $this->assertCount(1, $keys);

        $pending[$fingerprint]['created_at'] = now()->getTimestamp();
        session()->put('portal.pending_mutations', $pending);
        session()->save();

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'link-1')
            ->set('offeringName', 'Different service')
            ->call('createOffering')
            ->assertSee('A request with this idempotency key is still processing.');

        $this->assertCount(2, (array) session('portal.pending_mutations'));

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'link-1')
            ->set('offeringName', 'Wellness consultation')
            ->call('createOffering')
            ->assertSee('Service saved as a draft. Review it before making it available to customers.');

        $this->assertCount(3, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertSame($keys[0], $keys[2]);

        Livewire::test(BusinessWorkspace::class)
            ->set('offeringProviderLinkId', 'link-1')
            ->set('offeringName', 'Different service')
            ->call('createOffering')
            ->assertSee('Service saved as a draft. Review it before making it available to customers.');

        $this->assertCount(4, $keys);
        $this->assertSame($keys[1], $keys[3]);
        $this->assertNull(session('portal.pending_mutations'));
    }

    public function test_malformed_upstream_dates_render_as_a_placeholder_instead_of_breaking_the_workspace(): void
    {
        $component = Livewire::test(BusinessWorkspace::class);

        $this->assertSame('—', $component->instance()->displayDate('not-a-date'));
        $this->assertSame('—', $component->instance()->displayDateTime('not-a-date'));
        $this->assertSame('—', $component->instance()->displayRelative('not-a-date'));
    }

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
            ->assertSee('Change appearance')
            ->assertSee('data-theme-toggle', false)
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
            'https://api.zigpaw.test/v1/business/offerings/11111111-1111-4111-8111-111111111111' => Http::response(['data' => [
                'id' => '11111111-1111-4111-8111-111111111111',
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
                'id' => '11111111-1111-4111-8111-111111111111',
                'name' => 'Wellness consultation',
                'description' => null,
                'default_duration_minutes' => 30,
                'status' => 'draft',
                'request_mode' => 'request',
                'place' => ['name' => 'Laidley Veterinary Surgery'],
            ]])),
        ]);

        $component = Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'services')
            ->call('startOfferingEdit', '11111111-1111-4111-8111-111111111111')
            ->assertSee('role="dialog"', false)
            ->assertSee('id="offering-edit-drawer"', false)
            ->assertSee('data-overlay-open-state="true"', false)
            ->assertSee('data-overlay-close', false)
            ->assertSee('form="offering-edit-form"', false)
            ->set('editingOfferingStatus', 'active')
            ->call('saveOffering')
            ->assertSee('Service updated.');

        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && $request->url() === 'https://api.zigpaw.test/v1/business/offerings/11111111-1111-4111-8111-111111111111'
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
            ->assertSee('role="dialog"', false)
            ->assertSee('id="listing-claim-modal"', false)
            ->assertSee('data-overlay-open-state="true"', false)
            ->assertSee('data-overlay-initial-focus', false)
            ->assertSee('Confirm listing claim')
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

    public function test_booking_manager_can_edit_weekly_availability_and_date_exceptions(): void
    {
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'manager-access-token',
            'refresh_token' => 'manager-refresh-token',
            'expires_in' => 900,
        ]);

        $provider = [[
            'id' => '11111111-1111-4111-8111-111111111111',
            'status' => 'active',
            'provider' => ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Laidley Veterinary Surgery'],
            'place' => ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Laidley Veterinary Surgery', 'timezone' => 'Australia/Brisbane'],
        ]];
        $profile = [[
            'id' => '44444444-4444-4444-8444-444444444444',
            'service_provider_id' => '22222222-2222-4222-8222-222222222222',
            'place_id' => '33333333-3333-4333-8333-333333333333',
            'status' => 'enabled',
            'timezone' => 'Australia/Brisbane',
            'availability_rules' => [[
                'id' => 'rule-existing',
                'day_of_week' => 1,
                'starts_at' => '08:00',
                'ends_at' => '12:00',
                'effective_from' => null,
                'effective_until' => null,
                'is_active' => true,
            ]],
            'availability_exceptions' => [],
        ]];

        Http::fake(function (Request $request) use ($provider, $profile) {
            if ($request->url() === 'https://api.zigpaw.test/v1/business/organizations') {
                return Http::response(['data' => [[
                    'id' => 'organization-1',
                    'name' => 'Laidley Veterinary Surgery',
                    'role' => 'manager',
                ]]]);
            }

            if ($request->url() === 'https://api.zigpaw.test/v1/business/me') {
                return Http::response(['data' => [
                    'organization' => ['id' => 'organization-1', 'name' => 'Laidley Veterinary Surgery'],
                    'membership' => ['role' => 'manager', 'capabilities' => ['portal.view', 'providers.manage', 'bookings.manage']],
                    'features' => ['provider_claims' => false, 'booking_requests' => true],
                ]]);
            }

            if (str_contains($request->url(), '/booking-profiles')) {
                return $request->method() === 'PUT'
                    ? Http::response(['data' => $profile[0]])
                    : Http::response(self::page($profile));
            }

            if (str_contains($request->url(), '/providers')) {
                return Http::response(self::page($provider));
            }

            if (str_contains($request->url(), '/bookings') || str_contains($request->url(), '/programs')) {
                return Http::response(self::page([]));
            }

            return Http::response(['data' => []]);
        });

        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'booking-setup')
            ->assertSee('Weekly availability')
            ->assertSee('Date exceptions')
            ->call('addBookingAvailabilityRule')
            ->set('bookingAvailabilityRules.1.day_of_week', 2)
            ->set('bookingAvailabilityRules.1.starts_at', '13:00')
            ->set('bookingAvailabilityRules.1.ends_at', '17:00')
            ->call('addBookingAvailabilityException')
            ->set('bookingAvailabilityExceptions.0.date', '2026-12-25')
            ->set('bookingAvailabilityExceptions.0.availability', 'unavailable')
            ->set('bookingAvailabilityExceptions.0.reason', 'Public holiday')
            ->call('saveBookingConfiguration')
            ->assertHasNoErrors()
            ->assertSee('Booking requests are enabled for this location.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.zigpaw.test/v1/business/booking-profiles'
            && count((array) $request['availability_rules']) === 2
            && $request['availability_rules'][0]['day_of_week'] === 1
            && ! array_key_exists('id', $request['availability_rules'][0])
            && $request['availability_rules'][1]['starts_at'] === '13:00'
            && $request['availability_exceptions'][0]['date'] === '2026-12-25'
            && $request['availability_exceptions'][0]['reason'] === 'Public holiday');
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
