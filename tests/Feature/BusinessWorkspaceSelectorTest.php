<?php

namespace Tests\Feature;

use App\Livewire\BusinessWorkspace;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessWorkspaceSelectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'synthetic-management-token',
            'refresh_token' => 'synthetic-management-refresh',
            'expires_in' => 900,
        ]);
    }

    public function test_selected_organization_recovers_independently_of_its_list_page(): void
    {
        session()->put('portal.organization_id', 'org-27');
        $this->fakeWorkspace(organizationTotal: 27);

        Livewire::test(BusinessWorkspace::class)
            ->assertSet('organizationId', 'org-27')
            ->assertSet('state', 'ready')
            ->assertSee('Synthetic organisation 27');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/organizations/org-27'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/me')
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'org-27'));
    }

    public function test_revoked_selected_organization_returns_to_the_paginated_chooser(): void
    {
        session()->put('portal.organization_id', 'org-27');
        $this->fakeWorkspace(
            override: fn (Request $request) => str_ends_with($request->url(), '/organizations/org-27')
                ? Http::response(['message' => 'This organisation is unavailable.'], 404)
                : null,
            organizationTotal: 27,
        );

        Livewire::test(BusinessWorkspace::class)
            ->assertSet('state', 'choose_organization')
            ->assertSet('organizationId', null);
        $this->assertNull(session('portal.organization_id'));
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/me'));
    }

    public function test_organization_search_and_later_pages_remain_selectable(): void
    {
        $this->fakeWorkspace(organizationTotal: 27);
        Livewire::test(BusinessWorkspace::class)
            ->assertSet('state', 'choose_organization')
            ->call('changePage', 'organizations', 2)
            ->assertSee('Synthetic organisation 27')
            ->call('selectOrganization', 'org-27')
            ->assertRedirect(route('dashboard', ['section' => 'overview']));
        $this->assertSame('org-27', session('portal.organization_id'));

        Livewire::test(BusinessWorkspace::class)
            ->set('organizationSearch', 'no match')
            ->call('searchOrganizations')
            ->assertSet('organizationId', 'org-27')
            ->assertSee('No matching organisations.')
            ->assertSee('Synthetic organisation 27');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'search=no+match'));
    }

    public function test_location_selection_is_retained_outside_the_current_page_and_search(): void
    {
        $this->fakeWorkspace();
        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'services')
            ->call('selectOfferingProvider', 'link-101')
            ->assertSet('offeringProviderLinkId', 'link-101')
            ->call('changePage', 'providerOptions', 2)
            ->assertSet('offeringProviderLinkId', 'link-101')
            ->assertSee('Synthetic location 101')
            ->set('providerSearch', 'no match')
            ->call('searchProviders')
            ->assertSet('selectedOfferingProvider.id', 'link-101')
            ->assertSee('Synthetic location 101')
            ->assertSee('No matching locations.');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/providers/link-101'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/providers?')
            && (int) $request['page'] === 2 && (int) $request['per_page'] === 25 && (int) $request['active_only'] === 1);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/providers?') && (int) $request['per_page'] === 100);
    }

    public function test_booking_edit_uses_exact_configuration_and_provider_pagination_preserves_unsaved_fields(): void
    {
        $this->fakeWorkspace();
        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'booking-setup')
            ->call('selectBookingProvider', 'link-101')
            ->assertSet('loadedBookingProviderLinkId', 'link-101')
            ->assertSet('bookingConfigurationState', 'configured')
            ->assertSet('bookingAvailabilityRules.0.starts_at', '09:00')
            ->set('bookingCustomerInstructions', 'Use the side entrance.')
            ->call('changePage', 'providerOptions', 2)
            ->assertSet('bookingCustomerInstructions', 'Use the side entrance.')
            ->assertSet('bookingAvailabilityRules.0.starts_at', '09:00')
            ->call('saveBookingConfiguration')
            ->assertHasNoErrors();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request['provider_link_id'] === 'link-101'
            && $request['availability_rules'][0]['starts_at'] === '09:00'
            && count($request['availability_exceptions']) === 1);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/booking-profiles'));
    }

    public function test_failed_lookup_blocks_saving_and_explicit_null_is_an_unconfigured_location(): void
    {
        $unavailable = true;
        $this->fakeWorkspace(override: function (Request $request) use (&$unavailable) {
            if (str_ends_with($request->url(), '/booking-profile')) {
                return $unavailable
                    ? Http::response(['message' => 'Booking settings are temporarily unavailable.'], 503)
                    : Http::response(['data' => null]);
            }

            return null;
        });

        $workspace = Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'booking-setup')
            ->assertSet('loadedBookingProviderLinkId', null)
            ->assertSee('Booking settings could not be loaded')
            ->call('saveBookingConfiguration')
            ->assertHasErrors('bookingProviderLinkId');
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');

        $unavailable = false;
        $workspace->call('selectBookingProvider', 'link-1')
            ->assertSet('bookingConfigurationState', 'unconfigured')
            ->assertSet('loadedBookingProviderLinkId', 'link-1')
            ->assertSee('This location has no booking setup yet.')
            ->set('bookingStatus', 'enabled')
            ->call('saveBookingConfiguration')
            ->assertHasNoErrors();
    }

    public function test_changed_browser_location_cannot_save_settings_loaded_for_another_location(): void
    {
        $this->fakeWorkspace();
        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'booking-setup')
            ->set('bookingProviderLinkId', 'link-101')
            ->call('saveBookingConfiguration')
            ->assertHasErrors('bookingProviderLinkId');
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
    }

    public function test_loaded_booking_identity_is_locked_against_browser_updates(): void
    {
        $this->fakeWorkspace();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'booking-setup')
            ->set('loadedBookingProviderLinkId', 'link-101');
    }

    public function test_platform_nested_and_contact_validation_errors_are_associated_and_preserve_input(): void
    {
        $this->fakeWorkspace(override: fn (Request $request) => $request->method() === 'PUT'
            ? Http::response(['message' => 'Check the booking settings.', 'errors' => [
                'availability_rules.1.starts_at' => ['These weekly windows overlap.'],
                'notification_phone' => ['Use an available notification phone.'],
                'unrecognized_constraint' => ['This update needs another review.'],
            ]], 422)
            : null);

        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'booking-setup')
            ->call('addBookingAvailabilityRule')
            ->set('bookingAvailabilityRules.1.starts_at', '10:00')
            ->set('bookingAvailabilityRules.1.ends_at', '16:00')
            ->set('bookingNotificationPhone', '+61412345678')
            ->call('saveBookingConfiguration')
            ->assertHasErrors(['bookingAvailabilityRules.1.starts_at', 'bookingNotificationPhone', 'workspace'])
            ->assertSet('bookingAvailabilityRules.1.starts_at', '10:00')
            ->assertSet('bookingNotificationPhone', '+61412345678')
            ->assertSee('These weekly windows overlap.')
            ->assertSee('aria-describedby="business-field-bookingAvailabilityRules-1-starts_at-error"', false)
            ->assertSee('href="#business-field-bookingNotificationPhone"', false);
    }

    public function test_platform_role_ceiling_validation_remains_authoritative_and_visible(): void
    {
        $this->fakeWorkspace(override: fn (Request $request) => str_ends_with($request->url(), '/team/invitations')
            ? Http::response(['message' => 'Check this invitation.', 'errors' => ['role' => ['Your role cannot grant manager access.']]], 422)
            : null);
        Livewire::test(BusinessWorkspace::class)
            ->call('showSection', 'team')
            ->set('teamEmail', 'colleague@example.test')
            ->set('teamRole', 'manager')
            ->call('inviteTeamMember')
            ->assertHasErrors('teamRole')
            ->assertSet('teamRole', 'manager')
            ->assertSet('teamEmail', 'colleague@example.test')
            ->assertSee('Your role cannot grant manager access.')
            ->assertSee('aria-describedby="business-field-teamRole-error"', false);
    }

    private function fakeWorkspace(?callable $override = null, int $organizationTotal = 1): void
    {
        Http::fake(function (Request $request) use ($override, $organizationTotal) {
            if ($override !== null && ($response = $override($request)) !== null) {
                return $response;
            }
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/business/organizations') {
                $id = (int) ($request['page'] ?? 1) === 2 ? 'org-27' : 'org-1';
                $items = ($request['search'] ?? '') === 'no match' ? [] : [$this->organization($id)];

                return Http::response($this->page($items, (int) ($request['page'] ?? 1), $items === [] ? 0 : $organizationTotal));
            }
            if (preg_match('#/organizations/(org-\d+)$#', $path, $matches)) {
                return Http::response(['data' => $this->organization($matches[1])]);
            }
            if ($path === '/v1/business/me') {
                $id = $request->header('X-Zigpaw-Organization-ID')[0] ?? 'org-1';

                return Http::response(['data' => [
                    'organization' => $this->organization($id),
                    'membership' => ['id' => 'membership-'.$id, 'role' => 'owner', 'capabilities' => ['portal.view', 'providers.manage', 'bookings.manage', 'team.manage']],
                    'features' => ['provider_claims' => false, 'booking_requests' => true],
                ]]);
            }
            if ($path === '/v1/business/providers') {
                $items = ($request['search'] ?? '') === 'no match' ? [] : [$this->provider((int) ($request['page'] ?? 1) === 2 ? 'link-26' : 'link-1')];

                return Http::response($this->page($items, (int) ($request['page'] ?? 1), $items === [] ? 0 : 101));
            }
            if (preg_match('#/providers/(link-\d+)/booking-profile$#', $path, $matches)) {
                return Http::response(['data' => $this->profile($matches[1])]);
            }
            if (preg_match('#/providers/(link-\d+)$#', $path, $matches)) {
                return Http::response(['data' => $this->provider($matches[1])]);
            }
            if ($path === '/v1/business/booking-profiles' && $request->method() === 'PUT') {
                return Http::response(['data' => array_replace($this->profile($request['provider_link_id']), $request->data())]);
            }

            return Http::response($this->page([], 1, 0));
        });
    }

    /** @return array<string, mixed> */
    private function organization(string $id): array
    {
        return ['id' => $id, 'name' => 'Synthetic organisation '.substr($id, 4), 'role' => 'owner'];
    }

    /** @return array<string, mixed> */
    private function provider(string $id): array
    {
        $suffix = substr($id, 5);

        return ['id' => $id, 'status' => 'active', 'provider' => ['id' => 'provider-'.$suffix, 'name' => 'Synthetic provider '.$suffix], 'place' => ['id' => 'place-'.$suffix, 'name' => 'Synthetic location '.$suffix, 'timezone' => 'Australia/Brisbane']];
    }

    /** @return array<string, mixed> */
    private function profile(string $linkId): array
    {
        $suffix = substr($linkId, 5);

        return [
            'id' => 'profile-'.$suffix, 'service_provider_id' => 'provider-'.$suffix, 'place_id' => 'place-'.$suffix,
            'status' => 'enabled', 'timezone' => 'Australia/Brisbane', 'minimum_notice_hours' => 0,
            'availability_rules' => [['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_active' => true]],
            'availability_exceptions' => [['date' => '2026-12-25', 'availability' => 'unavailable']],
        ];
    }

    /** @param list<array<string, mixed>> $items @return array<string, mixed> */
    private function page(array $items, int $page, int $total): array
    {
        return [
            'data' => $items,
            'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($total / 25)), 'per_page' => 25, 'total' => $total],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        ];
    }
}
