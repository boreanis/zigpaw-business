<?php

namespace App\Livewire;

use App\Exceptions\PlatformApiException;
use App\Services\PlatformApiClient;
use App\Support\PortalAccessTokenStore;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class BusinessWorkspace extends Component
{
    #[Url]
    public string $section = 'overview';

    public string $state = 'signed_out';

    public ?string $message = null;

    public ?string $notice = null;

    public ?string $organizationId = null;

    /** @var list<array<string, mixed>> */
    public array $organizations = [];

    /** @var array<string, mixed> */
    public array $identity = [];

    /** @var array<string, array{label: string, icon: string}> */
    public array $navigationSections = [];

    /** @var list<array<string, mixed>> */
    public array $providers = [];

    /** @var list<array<string, mixed>> */
    public array $providerClaims = [];

    /** @var list<array<string, mixed>> */
    public array $claimableProviders = [];

    public string $claimSearch = '';

    public string $claimServiceProviderId = '';

    public string $claimPlaceId = '';

    public string $claimProviderName = '';

    public string $claimBranchName = '';

    public string $claimAddress = '';

    public string $claimAuthorityType = '';

    public string $claimVerificationMethod = '';

    public string $claimantEmail = '';

    public string $claimantPhone = '';

    /** @var list<array<string, mixed>> */
    public array $offerings = [];

    /** @var list<array<string, mixed>> */
    public array $bookings = [];

    /** @var list<array<string, mixed>> */
    public array $bookingProfiles = [];

    /** @var array<string, mixed> */
    public array $financials = [];

    /** @var list<array<string, mixed>> */
    public array $commissions = [];

    /** @var list<array<string, mixed>> */
    public array $agreements = [];

    /** @var list<array<string, mixed>> */
    public array $programs = [];

    /** @var list<array<string, mixed>> */
    public array $team = [];

    /** @var array<string, array{current_page: int, last_page: int, per_page: int, total: int}> */
    public array $pagination = [];

    public string $offeringProviderLinkId = '';

    public string $offeringName = '';

    public ?int $offeringDurationMinutes = null;

    public string $editingOfferingId = '';

    public string $editingOfferingName = '';

    public string $editingOfferingDescription = '';

    public ?int $editingOfferingDurationMinutes = null;

    public string $editingOfferingStatus = '';

    public string $editingOfferingRequestMode = '';

    public string $editingProviderLinkId = '';

    public string $providerName = '';

    public string $providerDescription = '';

    public string $providerPhone = '';

    public string $providerEmail = '';

    public string $providerWebsite = '';

    public string $placeName = '';

    public string $placePhone = '';

    public string $placeEmail = '';

    public string $placeWebsite = '';

    public string $placeAddressLine1 = '';

    public string $placeAddressLine2 = '';

    public string $placeCity = '';

    public string $placeState = '';

    public string $placePostalCode = '';

    public string $placeTimezone = '';

    public string $bookingProviderLinkId = '';

    public string $bookingStatus = '';

    public string $bookingTimezone = '';

    public string $bookingNotificationEmail = '';

    public string $bookingNotificationPhone = '';

    public ?int $bookingMinimumNoticeHours = null;

    public ?int $bookingMaximumAdvanceDays = null;

    public ?int $bookingResponseWindowHours = null;

    public string $bookingCustomerInstructions = '';

    /** @var list<array<string, mixed>> */
    public array $bookingAvailabilityRules = [];

    /** @var list<array<string, mixed>> */
    public array $bookingAvailabilityExceptions = [];

    public string $businessName = '';

    public string $businessLegalName = '';

    public string $businessRegistrationNumber = '';

    public string $businessRegistrationNumberType = '';

    public string $businessTaxIdentifier = '';

    public string $businessTaxIdentifierType = '';

    public string $businessRegisteredCountryCode = '';

    public string $businessPrimaryContactName = '';

    public string $businessPrimaryContactEmail = '';

    public string $businessPrimaryContactPhone = '';

    public string $businessWebsite = '';

    public string $teamEmail = '';

    public string $teamRole = 'viewer';

    public string $editingTeamMemberId = '';

    public string $editingTeamRole = '';

    /** @var array<string, string> */
    public array $declineReasons = [];

    /** @var array<string, array{label: string, icon: string, capability: string, feature: ?string}> */
    private const SECTION_DEFINITIONS = [
        'overview' => ['label' => 'Overview', 'icon' => 'home', 'capability' => 'portal.view', 'feature' => null],
        'profile' => ['label' => 'Business profile', 'icon' => 'building', 'capability' => 'organization.manage', 'feature' => null],
        'listings' => ['label' => 'Locations', 'icon' => 'pin', 'capability' => 'providers.manage', 'feature' => null],
        'bookings' => ['label' => 'Bookings', 'icon' => 'calendar', 'capability' => 'bookings.manage', 'feature' => 'booking_requests'],
        'booking-setup' => ['label' => 'Booking setup', 'icon' => 'settings', 'capability' => 'providers.manage', 'feature' => 'booking_requests'],
        'services' => ['label' => 'Services', 'icon' => 'briefcase', 'capability' => 'providers.manage', 'feature' => null],
        'programs' => ['label' => 'Partner programs', 'icon' => 'gift', 'capability' => 'programs.view', 'feature' => null],
        'revenue' => ['label' => 'Revenue', 'icon' => 'wallet', 'capability' => 'financials.view', 'feature' => null],
        'team' => ['label' => 'Team', 'icon' => 'people', 'capability' => 'team.manage', 'feature' => null],
    ];

    /** @var array<string, string> */
    private const PAGINATION_SECTIONS = [
        'providers' => 'listings',
        'providerClaims' => 'listings',
        'claimableProviders' => 'listings',
        'offerings' => 'services',
        'bookings' => 'bookings',
        'bookingProfiles' => 'booking-setup',
        'programs' => 'programs',
        'commissions' => 'revenue',
        'agreements' => 'revenue',
        'team' => 'team',
    ];

    public function mount(PlatformApiClient $api, PortalAccessTokenStore $tokens): void
    {
        $this->message = session()->pull('error');
        $this->notice = session()->pull('status');

        $accessToken = $tokens->accessToken();
        if (! $accessToken) {
            return;
        }

        try {
            $this->organizations = $api->organizations($accessToken);
            $organizationId = session('portal.organization_id');

            if (! collect($this->organizations)->contains('id', $organizationId)) {
                session()->forget('portal.organization_id');
                $organizationId = null;
            }

            if (count($this->organizations) === 1) {
                $organizationId = (string) $this->organizations[0]['id'];
                session()->put('portal.organization_id', $organizationId);
            }

            if (! is_string($organizationId) || $organizationId === '') {
                $this->state = $this->organizations === [] ? 'forbidden' : 'choose_organization';

                return;
            }

            $this->organizationId = $organizationId;
            $this->identity = $api->identity($accessToken, $organizationId);
            $this->hydrateBusinessProfile();
            $this->navigationSections = $this->availableNavigationSections();
            $this->section = array_key_exists($this->section, $this->navigationSections) ? $this->section : 'overview';
            $this->state = 'ready';
            $this->loadSection($api, $accessToken);
        } catch (PlatformApiException $exception) {
            $this->handlePlatformFailure($exception, $tokens);
        }
    }

    public function selectOrganization(string $organizationId): void
    {
        abort_unless(collect($this->organizations)->contains('id', $organizationId), 403);
        session()->put('portal.organization_id', $organizationId);
        $this->redirectRoute('dashboard', ['section' => 'overview'], navigate: true);
    }

    public function showSection(string $section): void
    {
        abort_unless(array_key_exists($section, $this->navigationSections), 403);
        $this->section = $section;
        $this->message = null;

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $this->loadSection($api, $accessToken);
        });
    }

    public function changePage(string $resource, int $page): void
    {
        abort_unless((self::PAGINATION_SECTIONS[$resource] ?? null) === $this->section, 404);
        $meta = $this->pagination[$resource] ?? null;
        abort_unless(is_array($meta), 404);

        $this->pagination[$resource]['current_page'] = min(
            max(1, $page),
            max(1, $meta['last_page']),
        );

        if ($resource === 'claimableProviders') {
            $this->loadClaimableProviders();

            return;
        }

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $this->loadSection($api, $accessToken);
        });
    }

    public function searchClaimableProviders(): void
    {
        $this->authorizeCapability('providers.manage');
        $this->authorizeFeature('provider_claims');
        $this->validate([
            'claimSearch' => ['required', 'string', 'min:3', 'max:120'],
        ]);

        $this->pagination['claimableProviders']['current_page'] = 1;
        $this->resetClaimSelection();
        $this->loadClaimableProviders();
    }

    public function selectClaimableProvider(string $serviceProviderId, string $placeId): void
    {
        $this->authorizeCapability('providers.manage');
        $this->authorizeFeature('provider_claims');
        $candidate = collect($this->claimableProviders)->first(
            fn (array $result): bool => (string) ($result['service_provider_id'] ?? '') === $serviceProviderId
                && (string) ($result['place_id'] ?? '') === $placeId,
        );
        abort_unless(is_array($candidate), 404);

        $this->claimServiceProviderId = $serviceProviderId;
        $this->claimPlaceId = $placeId;
        $this->claimProviderName = (string) ($candidate['provider_name'] ?? '');
        $this->claimBranchName = (string) ($candidate['branch_name'] ?? '');
        $this->claimAddress = (string) ($candidate['address'] ?? '');
        $this->claimAuthorityType = '';
        $this->claimVerificationMethod = '';
        $this->claimantEmail = '';
        $this->claimantPhone = '';
        $this->resetErrorBag();
    }

    public function cancelProviderClaim(): void
    {
        $this->resetClaimSelection();
        $this->resetErrorBag();
    }

    public function submitProviderClaim(): void
    {
        $this->authorizeCapability('providers.manage');
        $this->authorizeFeature('provider_claims');
        $this->validate([
            'claimServiceProviderId' => ['required', 'string'],
            'claimPlaceId' => ['required', 'string'],
            'claimAuthorityType' => ['required', 'in:owner,manager,location_manager'],
            'claimVerificationMethod' => ['required', 'in:domain_email,phone'],
            'claimantEmail' => ['required_if:claimVerificationMethod,domain_email', 'nullable', 'email:rfc', 'max:255'],
            'claimantPhone' => ['required_if:claimVerificationMethod,phone', 'nullable', 'string', 'max:80'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $api->submitProviderClaim($accessToken, $this->requiredOrganizationId(), array_filter([
                'service_provider_id' => $this->claimServiceProviderId,
                'place_id' => $this->claimPlaceId,
                'requested_authority_type' => $this->claimAuthorityType,
                'verification_method' => $this->claimVerificationMethod,
                'claimant_email' => $this->claimVerificationMethod === 'domain_email'
                    ? mb_strtolower(trim($this->claimantEmail))
                    : null,
                'claimant_phone' => $this->claimVerificationMethod === 'phone'
                    ? trim($this->claimantPhone)
                    : null,
            ], static fn (mixed $value): bool => $value !== null));
            $this->assignPage('providerClaims', $api->providerClaims(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('providerClaims'),
            ));
            $this->resetClaimSelection();
            $this->claimableProviders = [];
            unset($this->pagination['claimableProviders']);
            $this->claimSearch = '';
            $this->notice = 'Claim submitted. We will show verification progress here.';
        });
    }

    public function createOffering(): void
    {
        $this->authorizeCapability('providers.manage');
        $this->validate([
            'offeringProviderLinkId' => ['required', 'string'],
            'offeringName' => ['required', 'string', 'max:160'],
            'offeringDurationMinutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $api->createOffering($accessToken, $this->requiredOrganizationId(), array_filter([
                'provider_link_id' => $this->offeringProviderLinkId,
                'name' => Str::squish($this->offeringName),
                'default_duration_minutes' => $this->offeringDurationMinutes,
            ], static fn (mixed $value): bool => $value !== null));
            $this->assignPage('offerings', $api->offerings(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('offerings'),
            ));
            $this->offeringName = '';
            $this->offeringDurationMinutes = null;
            $this->notice = 'Service saved as a draft. Review it before making it available to customers.';
        });
    }

    public function saveBusinessProfile(): void
    {
        $this->authorizeCapability('organization.manage');
        $this->validate([
            'businessName' => ['required', 'string', 'max:255'],
            'businessLegalName' => ['nullable', 'string', 'max:255'],
            'businessRegistrationNumber' => ['nullable', 'string', 'max:120'],
            'businessRegistrationNumberType' => ['nullable', 'string', 'max:40'],
            'businessTaxIdentifier' => ['nullable', 'string', 'max:120'],
            'businessTaxIdentifierType' => ['nullable', 'string', 'max:40'],
            'businessRegisteredCountryCode' => ['nullable', 'string', 'size:2'],
            'businessPrimaryContactName' => ['nullable', 'string', 'max:255'],
            'businessPrimaryContactEmail' => ['nullable', 'email:rfc', 'max:255'],
            'businessPrimaryContactPhone' => ['nullable', 'string', 'max:80'],
            'businessWebsite' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $this->identity = $api->updateBusinessProfile($accessToken, $this->requiredOrganizationId(), [
                'name' => Str::squish($this->businessName),
                'legal_name' => $this->nullableField($this->businessLegalName),
                'registration_number' => $this->nullableField($this->businessRegistrationNumber),
                'registration_number_type' => $this->nullableField($this->businessRegistrationNumberType),
                'tax_identifier' => $this->nullableField($this->businessTaxIdentifier),
                'tax_identifier_type' => $this->nullableField($this->businessTaxIdentifierType),
                'registered_country_code' => $this->businessRegisteredCountryCode === '' ? null : Str::upper(trim($this->businessRegisteredCountryCode)),
                'primary_contact_name' => $this->nullableField($this->businessPrimaryContactName),
                'primary_contact_email' => $this->nullableField(mb_strtolower(trim($this->businessPrimaryContactEmail))),
                'primary_contact_phone' => $this->nullableField($this->businessPrimaryContactPhone),
                'website' => $this->nullableField($this->businessWebsite),
            ]);
            $this->hydrateBusinessProfile();
            $this->notice = 'Business profile saved.';
        });
    }

    public function startProviderEdit(string $providerLinkId): void
    {
        $this->authorizeCapability('providers.manage');
        $link = collect($this->providers)->firstWhere('id', $providerLinkId);
        abort_unless(is_array($link), 404);

        $this->editingProviderLinkId = $providerLinkId;
        $this->providerName = (string) data_get($link, 'provider.name', '');
        $this->providerDescription = (string) data_get($link, 'provider.description', '');
        $this->providerPhone = (string) data_get($link, 'provider.phone', '');
        $this->providerEmail = (string) data_get($link, 'provider.email', '');
        $this->providerWebsite = (string) data_get($link, 'provider.website', '');
        $this->placeName = (string) data_get($link, 'place.name', '');
        $this->placePhone = (string) data_get($link, 'place.phone', '');
        $this->placeEmail = (string) data_get($link, 'place.email', '');
        $this->placeWebsite = (string) data_get($link, 'place.website', '');
        $this->placeAddressLine1 = (string) data_get($link, 'place.address_line_1', '');
        $this->placeAddressLine2 = (string) data_get($link, 'place.address_line_2', '');
        $this->placeCity = (string) data_get($link, 'place.city', '');
        $this->placeState = (string) data_get($link, 'place.state', '');
        $this->placePostalCode = (string) data_get($link, 'place.postal_code', '');
        $this->placeTimezone = (string) data_get($link, 'place.timezone', '');
        $this->resetErrorBag();
    }

    public function cancelProviderEdit(): void
    {
        $this->resetProviderEditor();
        $this->resetErrorBag();
    }

    public function saveProvider(): void
    {
        $this->authorizeCapability('providers.manage');
        $this->validate([
            'editingProviderLinkId' => ['required', 'string'],
            'providerName' => ['required', 'string', 'max:255'],
            'providerDescription' => ['nullable', 'string', 'max:5000'],
            'providerPhone' => ['nullable', 'string', 'max:80'],
            'providerEmail' => ['nullable', 'email:rfc', 'max:255'],
            'providerWebsite' => ['nullable', 'url:http,https', 'max:2048'],
            'placeName' => ['nullable', 'string', 'max:255'],
            'placePhone' => ['nullable', 'string', 'max:80'],
            'placeEmail' => ['nullable', 'email:rfc', 'max:255'],
            'placeWebsite' => ['nullable', 'url:http,https', 'max:2048'],
            'placeAddressLine1' => ['nullable', 'string', 'max:255'],
            'placeAddressLine2' => ['nullable', 'string', 'max:255'],
            'placeCity' => ['nullable', 'string', 'max:160'],
            'placeState' => ['nullable', 'string', 'max:160'],
            'placePostalCode' => ['nullable', 'string', 'max:32'],
            'placeTimezone' => ['nullable', 'timezone'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $currentLink = collect($this->providers)->firstWhere('id', $this->editingProviderLinkId);
            abort_unless(is_array($currentLink), 404);
            $payload = [
                'provider' => [
                    'name' => Str::squish($this->providerName),
                    'description' => $this->nullableField($this->providerDescription),
                    'phone' => $this->nullableField($this->providerPhone),
                    'email' => $this->nullableField(mb_strtolower(trim($this->providerEmail))),
                    'website' => $this->nullableField($this->providerWebsite),
                ],
            ];
            if (data_get($currentLink, 'place.id')) {
                $payload['place'] = [
                    'name' => Str::squish($this->placeName),
                    'phone' => $this->nullableField($this->placePhone),
                    'email' => $this->nullableField(mb_strtolower(trim($this->placeEmail))),
                    'website' => $this->nullableField($this->placeWebsite),
                    'address_line_1' => $this->nullableField($this->placeAddressLine1),
                    'address_line_2' => $this->nullableField($this->placeAddressLine2),
                    'city' => $this->nullableField($this->placeCity),
                    'state' => $this->nullableField($this->placeState),
                    'postal_code' => $this->nullableField($this->placePostalCode),
                    'timezone' => $this->nullableField($this->placeTimezone),
                ];
            }

            $api->updateManagedProvider(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->editingProviderLinkId,
                $payload,
            );
            $this->assignPage('providers', $api->providers(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('providers'),
            ));
            $this->resetProviderEditor();
            $this->notice = 'Location details saved.';
        });
    }

    public function startOfferingEdit(string $offeringId): void
    {
        $this->authorizeCapability('providers.manage');
        $offering = collect($this->offerings)->firstWhere('id', $offeringId);
        abort_unless(is_array($offering), 404);

        $this->editingOfferingId = $offeringId;
        $this->editingOfferingName = (string) ($offering['name'] ?? '');
        $this->editingOfferingDescription = (string) ($offering['description'] ?? '');
        $this->editingOfferingDurationMinutes = isset($offering['default_duration_minutes'])
            ? (int) $offering['default_duration_minutes']
            : null;
        $this->editingOfferingStatus = (string) ($offering['status'] ?? '');
        $this->editingOfferingRequestMode = (string) ($offering['request_mode'] ?? '');
        $this->resetErrorBag();
    }

    public function cancelOfferingEdit(): void
    {
        $this->resetOfferingEditor();
        $this->resetErrorBag();
    }

    public function saveOffering(): void
    {
        $this->authorizeCapability('providers.manage');
        $this->validate([
            'editingOfferingId' => ['required', 'string'],
            'editingOfferingName' => ['required', 'string', 'max:160'],
            'editingOfferingDescription' => ['nullable', 'string', 'max:3000'],
            'editingOfferingDurationMinutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'editingOfferingStatus' => ['required', 'in:draft,active,paused'],
            'editingOfferingRequestMode' => ['required', 'in:request,contact,external'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $api->updateOffering(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->editingOfferingId,
                [
                    'name' => Str::squish($this->editingOfferingName),
                    'description' => $this->nullableField($this->editingOfferingDescription),
                    'default_duration_minutes' => $this->editingOfferingDurationMinutes,
                    'status' => $this->editingOfferingStatus,
                    'request_mode' => $this->editingOfferingRequestMode,
                ],
            );
            $this->assignPage('offerings', $api->offerings(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('offerings'),
            ));
            $this->resetOfferingEditor();
            $this->notice = 'Service updated.';
        });
    }

    public function deleteOffering(string $offeringId): void
    {
        $this->authorizeCapability('providers.manage');
        abort_unless(collect($this->offerings)->contains('id', $offeringId), 404);

        $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($offeringId): void {
            $api->deleteOffering($accessToken, $this->requiredOrganizationId(), $offeringId);
            $this->assignPage('offerings', $api->offerings(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('offerings'),
            ));
            if ($this->editingOfferingId === $offeringId) {
                $this->resetOfferingEditor();
            }
            $this->notice = 'Service removed.';
        });
    }

    public function selectBookingProvider(string $providerLinkId): void
    {
        $this->authorizeCapability('bookings.manage');
        $this->authorizeFeature('booking_requests');
        $this->hydrateBookingConfiguration($providerLinkId);
    }

    public function saveBookingConfiguration(): void
    {
        $this->authorizeCapability('bookings.manage');
        $this->authorizeFeature('booking_requests');
        $this->validate([
            'bookingProviderLinkId' => ['required', 'string'],
            'bookingStatus' => ['required', 'in:disabled,enabled,paused'],
            'bookingTimezone' => ['required', 'timezone'],
            'bookingNotificationEmail' => ['nullable', 'email:rfc', 'max:255'],
            'bookingNotificationPhone' => ['nullable', 'string', 'max:80'],
            'bookingMinimumNoticeHours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'bookingMaximumAdvanceDays' => ['nullable', 'integer', 'min:1', 'max:730'],
            'bookingResponseWindowHours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'bookingCustomerInstructions' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $payload = array_filter([
                'provider_link_id' => $this->bookingProviderLinkId,
                'status' => $this->bookingStatus,
                'timezone' => $this->bookingTimezone,
                'notification_email' => $this->nullableField(mb_strtolower(trim($this->bookingNotificationEmail))),
                'notification_phone' => $this->nullableField($this->bookingNotificationPhone),
                'minimum_notice_hours' => $this->bookingMinimumNoticeHours,
                'maximum_advance_days' => $this->bookingMaximumAdvanceDays,
                'response_window_hours' => $this->bookingResponseWindowHours,
                'customer_instructions' => $this->nullableField($this->bookingCustomerInstructions),
            ], static fn (mixed $value): bool => $value !== null);

            $api->updateBookingProfile($accessToken, $this->requiredOrganizationId(), $payload);
            $this->assignPage('bookingProfiles', $api->bookingProfiles(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('bookingProfiles'),
            ));
            $this->hydrateBookingConfiguration($this->bookingProviderLinkId);
            $this->notice = $this->bookingStatus === 'enabled'
                ? 'Booking requests are enabled for this location.'
                : 'Booking settings saved.';
        });
    }

    public function acceptBooking(string $bookingId, string $windowId): void
    {
        $this->respondToBooking($bookingId, ['action' => 'accept', 'window_id' => $windowId]);
    }

    public function completeBooking(string $bookingId): void
    {
        $this->respondToBooking($bookingId, ['action' => 'complete']);
    }

    public function declineBooking(string $bookingId): void
    {
        $this->authorizeCapability('bookings.manage');
        $this->authorizeFeature('booking_requests');
        $this->validate([
            "declineReasons.{$bookingId}" => ['required', 'string', 'max:1000'],
        ], [
            "declineReasons.{$bookingId}.required" => 'Add a short reason before declining this request.',
        ]);
        $this->respondToBooking($bookingId, [
            'action' => 'decline',
            'reason' => Str::squish($this->declineReasons[$bookingId]),
        ]);
        unset($this->declineReasons[$bookingId]);
    }

    public function applyForReferralProgram(): void
    {
        $this->authorizeCapability('programs.manage');
        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $api->applyForProgram($accessToken, $this->requiredOrganizationId(), [
                'program_key' => 'referral',
                'application_notes' => 'Submitted from the Zigpaw business workspace.',
            ]);
            $this->assignPage('programs', $api->programs(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('programs'),
            ));
            $this->notice = 'Application sent. Zigpaw will review the business and program fit.';
        });
    }

    public function inviteTeamMember(): void
    {
        $this->authorizeCapability('team.manage');
        $this->validate([
            'teamEmail' => ['required', 'email:rfc', 'max:254'],
            'teamRole' => ['required', 'in:manager,operator,finance,viewer'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $api->inviteTeamMember($accessToken, $this->requiredOrganizationId(), [
                'email' => mb_strtolower(trim($this->teamEmail)),
                'role' => $this->teamRole,
            ]);
            $this->assignPage('team', $api->team(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('team'),
            ));
            $this->teamEmail = '';
            $this->teamRole = 'viewer';
            $this->notice = 'Invitation sent. Access stays limited to the selected role.';
        });
    }

    public function startTeamMemberEdit(string $membershipId): void
    {
        $this->authorizeCapability('team.manage');
        $member = collect($this->team)->firstWhere('id', $membershipId);
        abort_unless(is_array($member) && ! ($member['is_current_user'] ?? false), 404);

        $this->editingTeamMemberId = $membershipId;
        $this->editingTeamRole = (string) ($member['role'] ?? 'viewer');
        $this->resetErrorBag();
    }

    public function cancelTeamMemberEdit(): void
    {
        $this->reset(['editingTeamMemberId', 'editingTeamRole']);
        $this->resetErrorBag();
    }

    public function saveTeamMemberRole(): void
    {
        $this->authorizeCapability('team.manage');
        $this->validate([
            'editingTeamMemberId' => ['required', 'string'],
            'editingTeamRole' => ['required', 'in:manager,operator,finance,viewer'],
        ]);

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $api->updateTeamMember(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->editingTeamMemberId,
                ['role' => $this->editingTeamRole],
            );
            $this->reloadTeam($api, $accessToken);
            $this->cancelTeamMemberEdit();
            $this->notice = 'Team role updated.';
        });
    }

    public function resendTeamInvitation(string $membershipId): void
    {
        $this->authorizeCapability('team.manage');
        $member = collect($this->team)->firstWhere('id', $membershipId);
        abort_unless(is_array($member) && ($member['status'] ?? null) === 'invited', 404);

        $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($membershipId): void {
            $api->resendTeamInvitation($accessToken, $this->requiredOrganizationId(), $membershipId);
            $this->reloadTeam($api, $accessToken);
            $this->notice = 'Invitation sent again.';
        });
    }

    public function revokeTeamMember(string $membershipId): void
    {
        $this->authorizeCapability('team.manage');
        $member = collect($this->team)->firstWhere('id', $membershipId);
        abort_unless(is_array($member) && ! ($member['is_current_user'] ?? false), 404);

        $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($membershipId): void {
            $api->revokeTeamMember($accessToken, $this->requiredOrganizationId(), $membershipId);
            $this->reloadTeam($api, $accessToken);
            if ($this->editingTeamMemberId === $membershipId) {
                $this->cancelTeamMemberEdit();
            }
            $this->notice = 'Team access revoked.';
        });
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, (array) data_get($this->identity, 'membership.capabilities', []), true);
    }

    public function featureIsAvailable(string $feature): bool
    {
        return data_get($this->identity, "features.{$feature}") === true;
    }

    public function render(): View
    {
        return view('livewire.business-workspace')->layout('components.layouts.portal', [
            'title' => 'Zigpaw business',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function respondToBooking(string $bookingId, array $payload): void
    {
        $this->authorizeCapability('bookings.manage');
        $this->authorizeFeature('booking_requests');

        $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($bookingId, $payload): void {
            $api->respondToBooking($accessToken, $this->requiredOrganizationId(), $bookingId, $payload);
            $this->assignPage('bookings', $api->bookings(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('bookings'),
            ));
            $this->notice = match ($payload['action']) {
                'accept' => 'Booking accepted. The customer can now see the confirmed time.',
                'decline' => 'Booking declined. The customer has been told why.',
                'complete' => 'Booking marked complete.',
                default => 'Booking updated.',
            };
        });
    }

    private function loadSection(PlatformApiClient $api, string $accessToken): void
    {
        $this->authorizeSection($this->section);
        $organizationId = $this->requiredOrganizationId();

        match ($this->section) {
            'overview' => $this->loadOverview($api, $accessToken, $organizationId),
            'profile' => $this->hydrateBusinessProfile(),
            'listings' => $this->loadListings($api, $accessToken, $organizationId),
            'bookings' => $this->assignPage('bookings', $api->bookings($accessToken, $organizationId, $this->currentPage('bookings'))),
            'booking-setup' => $this->loadBookingSetup($api, $accessToken, $organizationId),
            'services' => $this->loadServices($api, $accessToken, $organizationId),
            'programs' => $this->assignPage('programs', $api->programs($accessToken, $organizationId, $this->currentPage('programs'))),
            'revenue' => $this->loadRevenue($api, $accessToken, $organizationId),
            'team' => $this->assignPage('team', $api->team($accessToken, $organizationId, $this->currentPage('team'))),
            default => abort(404),
        };
    }

    private function loadOverview(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        if ($this->hasCapability('providers.manage')) {
            $this->assignPage('providers', $api->providers($accessToken, $organizationId, 1, 6));
        }

        if ($this->hasCapability('bookings.manage') && $this->featureIsAvailable('booking_requests')) {
            $this->assignPage('bookings', $api->bookings($accessToken, $organizationId, 1, 6));
        }

        if ($this->hasCapability('programs.view')) {
            $this->assignPage('programs', $api->programs($accessToken, $organizationId, 1, 6));
        }
    }

    private function loadListings(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        $this->assignPage('providers', $api->providers($accessToken, $organizationId, $this->currentPage('providers')));

        if ($this->featureIsAvailable('provider_claims')) {
            $this->assignPage('providerClaims', $api->providerClaims(
                $accessToken,
                $organizationId,
                $this->currentPage('providerClaims'),
            ));
        } else {
            $this->providerClaims = [];
            unset($this->pagination['providerClaims']);
        }
    }

    private function loadClaimableProviders(): void
    {
        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $this->assignPage('claimableProviders', $api->claimableProviders(
                $accessToken,
                $this->requiredOrganizationId(),
                Str::squish($this->claimSearch),
                $this->currentPage('claimableProviders'),
                10,
            ));
        });
    }

    private function loadServices(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        $this->assignPage('providers', $api->providers($accessToken, $organizationId, 1, 100));
        $this->assignPage('offerings', $api->offerings($accessToken, $organizationId, $this->currentPage('offerings')));
        $this->offeringProviderLinkId = (string) ($this->offeringProviderLinkId ?: data_get($this->providers, '0.id', ''));
    }

    private function loadBookingSetup(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        $this->assignPage('providers', $api->providers($accessToken, $organizationId, 1, 100));
        $this->assignPage('bookingProfiles', $api->bookingProfiles(
            $accessToken,
            $organizationId,
            $this->currentPage('bookingProfiles'),
        ));

        $providerLinkId = $this->bookingProviderLinkId ?: (string) data_get($this->providers, '0.id', '');
        if ($providerLinkId !== '') {
            $this->hydrateBookingConfiguration($providerLinkId);
        }
    }

    private function loadRevenue(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        $this->financials = $api->financials($accessToken, $organizationId);
        $this->assignPage('commissions', $api->commissions(
            $accessToken,
            $organizationId,
            $this->currentPage('commissions'),
        ));
        $this->assignPage('agreements', $api->agreements(
            $accessToken,
            $organizationId,
            $this->currentPage('agreements'),
        ));
    }

    /** @param array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}, links: array<string, ?string>} $page */
    private function assignPage(string $resource, array $page): void
    {
        $this->{$resource} = $page['data'];
        $this->pagination[$resource] = $page['meta'];
    }

    private function currentPage(string $resource): int
    {
        return max(1, (int) data_get($this->pagination, "{$resource}.current_page", 1));
    }

    private function hydrateBusinessProfile(): void
    {
        $this->businessName = (string) data_get($this->identity, 'organization.name', '');
        $this->businessLegalName = (string) data_get($this->identity, 'business_profile.legal_name', '');
        $this->businessRegistrationNumber = (string) data_get($this->identity, 'business_profile.registration_number', '');
        $this->businessRegistrationNumberType = (string) data_get($this->identity, 'business_profile.registration_number_type', '');
        $this->businessTaxIdentifier = (string) data_get($this->identity, 'business_profile.tax_identifier', '');
        $this->businessTaxIdentifierType = (string) data_get($this->identity, 'business_profile.tax_identifier_type', '');
        $this->businessRegisteredCountryCode = (string) data_get(
            $this->identity,
            'business_profile.registered_country_code',
            data_get($this->identity, 'organization.primary_country_code', ''),
        );
        $this->businessPrimaryContactName = (string) data_get($this->identity, 'business_profile.primary_contact_name', '');
        $this->businessPrimaryContactEmail = (string) data_get($this->identity, 'business_profile.primary_contact_email', '');
        $this->businessPrimaryContactPhone = (string) data_get($this->identity, 'business_profile.primary_contact_phone', '');
        $this->businessWebsite = (string) data_get($this->identity, 'business_profile.website', '');
    }

    private function hydrateBookingConfiguration(string $providerLinkId): void
    {
        $link = collect($this->providers)->firstWhere('id', $providerLinkId);
        abort_unless(is_array($link), 404);

        $serviceProviderId = (string) data_get($link, 'provider.id', '');
        $placeId = data_get($link, 'place.id');
        $profile = collect($this->bookingProfiles)->first(function (array $profile) use ($serviceProviderId, $placeId): bool {
            return (string) ($profile['service_provider_id'] ?? '') === $serviceProviderId
                && (string) ($profile['place_id'] ?? '') === (string) ($placeId ?? '');
        });

        $this->bookingProviderLinkId = $providerLinkId;
        $this->bookingStatus = is_array($profile) ? (string) ($profile['status'] ?? '') : '';
        $this->bookingTimezone = is_array($profile)
            ? (string) ($profile['timezone'] ?? '')
            : (string) data_get($link, 'place.timezone', '');
        $this->bookingNotificationEmail = is_array($profile) ? (string) ($profile['notification_email'] ?? '') : '';
        $this->bookingNotificationPhone = is_array($profile) ? (string) ($profile['notification_phone'] ?? '') : '';
        $this->bookingMinimumNoticeHours = is_array($profile) && isset($profile['minimum_notice_hours']) ? (int) $profile['minimum_notice_hours'] : null;
        $this->bookingMaximumAdvanceDays = is_array($profile) && isset($profile['maximum_advance_days']) ? (int) $profile['maximum_advance_days'] : null;
        $this->bookingResponseWindowHours = is_array($profile) && isset($profile['response_window_hours']) ? (int) $profile['response_window_hours'] : null;
        $this->bookingCustomerInstructions = is_array($profile) ? (string) ($profile['customer_instructions'] ?? '') : '';
        $this->bookingAvailabilityRules = is_array($profile) ? array_values((array) ($profile['availability_rules'] ?? [])) : [];
        $this->bookingAvailabilityExceptions = is_array($profile) ? array_values((array) ($profile['availability_exceptions'] ?? [])) : [];
        $this->resetErrorBag();
    }

    private function resetProviderEditor(): void
    {
        $this->reset([
            'editingProviderLinkId',
            'providerName',
            'providerDescription',
            'providerPhone',
            'providerEmail',
            'providerWebsite',
            'placeName',
            'placePhone',
            'placeEmail',
            'placeWebsite',
            'placeAddressLine1',
            'placeAddressLine2',
            'placeCity',
            'placeState',
            'placePostalCode',
            'placeTimezone',
        ]);
    }

    private function resetOfferingEditor(): void
    {
        $this->reset([
            'editingOfferingId',
            'editingOfferingName',
            'editingOfferingDescription',
            'editingOfferingDurationMinutes',
            'editingOfferingStatus',
            'editingOfferingRequestMode',
        ]);
    }

    private function resetClaimSelection(): void
    {
        $this->reset([
            'claimServiceProviderId',
            'claimPlaceId',
            'claimProviderName',
            'claimBranchName',
            'claimAddress',
            'claimAuthorityType',
            'claimVerificationMethod',
            'claimantEmail',
            'claimantPhone',
        ]);
    }

    private function reloadTeam(PlatformApiClient $api, string $accessToken): void
    {
        $this->assignPage('team', $api->team(
            $accessToken,
            $this->requiredOrganizationId(),
            $this->currentPage('team'),
        ));
    }

    private function nullableField(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, array{label: string, icon: string}> */
    private function availableNavigationSections(): array
    {
        $sections = [];

        foreach (self::SECTION_DEFINITIONS as $key => $definition) {
            if (! $this->hasCapability($definition['capability'])) {
                continue;
            }

            if (is_string($definition['feature']) && ! $this->featureIsAvailable($definition['feature'])) {
                continue;
            }

            $sections[$key] = [
                'label' => $definition['label'],
                'icon' => $definition['icon'],
            ];
        }

        return $sections;
    }

    private function authorizeSection(string $section): void
    {
        abort_unless(array_key_exists($section, $this->navigationSections), 403);
    }

    private function authorizeCapability(string $capability): void
    {
        abort_unless($this->hasCapability($capability), 403);
    }

    private function authorizeFeature(string $feature): void
    {
        abort_unless($this->featureIsAvailable($feature), 404);
    }

    private function withApi(callable $callback): void
    {
        $tokens = app(PortalAccessTokenStore::class);
        $accessToken = $tokens->accessToken();
        if (! $accessToken) {
            $this->state = 'signed_out';

            return;
        }

        try {
            $callback(app(PlatformApiClient::class), $accessToken);
            $this->message = null;
        } catch (PlatformApiException $exception) {
            $this->handlePlatformFailure($exception, $tokens, keepWorkspace: true);
        }
    }

    private function handlePlatformFailure(
        PlatformApiException $exception,
        PortalAccessTokenStore $tokens,
        bool $keepWorkspace = false,
    ): void {
        if ($exception->status === 401) {
            $tokens->forget();
            $this->state = 'signed_out';

            return;
        }

        if (! $keepWorkspace) {
            $this->state = $exception->status === 403 ? 'forbidden' : 'unavailable';
        }
        $this->message = $exception->getMessage();
    }

    private function requiredOrganizationId(): string
    {
        abort_unless(is_string($this->organizationId) && $this->organizationId !== '', 403);

        return $this->organizationId;
    }
}
