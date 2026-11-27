<?php

namespace App\Livewire;

use App\Exceptions\PlatformApiException;
use App\Livewire\Concerns\HandlesWorkspaceValidation;
use App\Services\PlatformApiClient;
use App\Support\PartnerBookingStatus;
use App\Support\PartnerOfferingScope;
use App\Support\PortalAccessTokenStore;
use App\Support\PortalDateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class BusinessWorkspace extends Component
{
    use HandlesWorkspaceValidation;

    #[Url]
    public string $section = 'overview';

    #[Url(as: 'status', except: 'all')]
    public string $bookingListStatus = 'all';

    #[Url(as: 'booking_page', except: 1)]
    public int $bookingPage = 1;

    #[Locked]
    public ?string $bookingId = null;

    #[Locked]
    public bool $bookingWasVerified = false;

    #[Locked]
    public bool $bookingPetProfileOpen = false;

    #[Locked]
    public int $bookingMessagePage = 1;

    public string $state = 'signed_out';

    public ?string $message = null;

    public ?string $notice = null;

    #[Locked]
    public ?string $organizationId = null;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $organizations = [];

    public string $organizationSearch = '';

    public string $providerSearch = '';

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $providerOptions = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $selectedOfferingProvider = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $selectedBookingProvider = null;

    #[Locked]
    public ?string $loadedBookingProviderLinkId = null;

    #[Locked]
    public string $bookingConfigurationState = 'unavailable';

    #[Locked]
    public ?string $bookingConfigurationRevision = null;

    #[Locked]
    public ?string $bookingDraftBaseline = null;

    #[Locked]
    public ?string $pendingBookingProvider = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $identity = [];

    public string $displayTimezone = 'UTC';

    public string $displayDateFormat = 'd/m/Y';

    public string $displayTimeFormat = 'H:i';

    public string $displayLocale = 'en';

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
    #[Locked]
    public array $offerings = [];

    /** @var list<array<string, mixed>> */
    public array $bookings = [];

    /** @var array<string, mixed> */
    public array $financials = [];

    /** @var list<array<string, mixed>> */
    public array $commissions = [];

    /** @var list<array<string, mixed>> */
    public array $agreements = [];

    /** @var list<array<string, mixed>> */
    public array $programs = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $team = [];

    /** @var array<string, array{current_page: int, last_page: int, per_page: int, total: int}> */
    public array $pagination = [];

    public string $offeringProviderLinkId = '';

    public string $offeringName = '';

    public ?int $offeringDurationMinutes = null;

    #[Locked]
    public string $editingOfferingId = '';

    #[Locked]
    public string $editingOfferingScope = '';

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

    public string $bookingDeclineReason = '';

    public string $bookingReply = '';

    public string $bookingProposedTime = '';

    /** @var array<string, array{label: string, icon: string, capability: string, feature: ?string}> */
    private const SECTION_DEFINITIONS = [
        'overview' => ['label' => 'Overview', 'icon' => 'home', 'capability' => 'portal.view', 'feature' => null],
        'profile' => ['label' => 'Business profile', 'icon' => 'building', 'capability' => 'organization.manage', 'feature' => null],
        'listings' => ['label' => 'Locations', 'icon' => 'pin', 'capability' => 'providers.manage', 'feature' => null],
        'bookings' => ['label' => 'Bookings', 'icon' => 'calendar', 'capability' => 'bookings.manage', 'feature' => 'booking_requests'],
        'booking-setup' => ['label' => 'Booking setup', 'icon' => 'settings', 'capability' => 'bookings.manage', 'feature' => 'booking_requests'],
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
        'programs' => 'programs',
        'commissions' => 'revenue',
        'agreements' => 'revenue',
        'team' => 'team',
    ];

    private const PENDING_MUTATIONS_SESSION_KEY = 'portal.pending_mutations';

    private const MUTATION_RETRY_CONTEXT_SESSION_KEY = 'portal.mutation_retry_context';

    private const MAX_PENDING_MUTATIONS = 32;

    // Leave a safety margin below Platform's 15-minute processing lease.
    private const PENDING_MUTATION_SAFE_RETRY_SECONDS = 14 * 60;

    public function mount(PlatformApiClient $api, PortalAccessTokenStore $tokens, ?string $booking = null): void
    {
        abort_unless($booking === null || Str::isUuid($booking), 404);
        $this->bookingPage = max(1, min(100_000, $this->bookingPage));
        $this->bookingId = $booking === null ? null : strtolower($booking);
        $this->message = session()->pull('error');
        $this->notice = session()->pull('status');

        $accessToken = $tokens->accessToken();
        if (! $accessToken) {
            return;
        }

        try {
            $this->assignPage('organizations', $api->organizations($accessToken));
            $organizationId = session('portal.organization_id');

            if (is_string($organizationId) && $organizationId !== '' && ! collect($this->organizations)->contains('id', $organizationId)) {
                try {
                    $selected = $api->organization($accessToken, $organizationId);
                    if (($selected['id'] ?? null) !== $organizationId) {
                        throw new PlatformApiException(502, 'The selected organisation could not be loaded. Please choose it again.');
                    }
                } catch (PlatformApiException $exception) {
                    if (! in_array($exception->status, [403, 404], true)) {
                        throw $exception;
                    }
                    session()->forget('portal.organization_id');
                    $organizationId = null;
                }
            }

            if (($this->pagination['organizations']['total'] ?? 0) === 1) {
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
            if ($this->bookingId !== null) {
                $this->section = 'bookings';
                $this->authorizeSection($this->section);
            } else {
                $this->section = array_key_exists($this->section, $this->navigationSections) ? $this->section : 'overview';
                $this->loadSection($api, $accessToken);
            }
            $this->state = 'ready';
        } catch (PlatformApiException $exception) {
            $this->handlePlatformFailure($exception, $tokens);
        }
    }

    public function selectOrganization(string $organizationId): void
    {
        abort_unless($organizationId === $this->organizationId || collect($this->organizations)->contains('id', $organizationId), 403);
        session()->put('portal.organization_id', $organizationId);
        if ($this->bookingWasVerified && $this->organizationId !== $organizationId) {
            $this->redirectRoute('dashboard', ['section' => 'bookings'], navigate: true);

            return;
        }
        if ($this->bookingId !== null) {
            $this->redirectRoute('bookings.show', ['booking' => $this->bookingId, 'status' => $this->bookingFilter(), 'booking_page' => $this->bookingPage > 1 ? $this->bookingPage : null], navigate: true);

            return;
        }
        if ($this->state === 'choose_organization' && $this->section === 'bookings') {
            $this->redirectRoute('dashboard', ['section' => 'bookings', 'status' => $this->bookingFilter(), 'booking_page' => $this->bookingPage > 1 ? $this->bookingPage : null], navigate: true);

            return;
        }
        $section = $this->state === 'choose_organization' && $this->section === 'programs' ? 'programs' : 'overview';
        $this->redirectRoute('dashboard', ['section' => $section], navigate: true);
    }

    public function searchOrganizations(): void
    {
        $this->validate(['organizationSearch' => ['nullable', 'string', 'max:80']]);
        $this->pagination['organizations']['current_page'] = 1;
        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $this->assignPage('organizations', $api->organizations($accessToken, 1, $this->organizationSearch));
        });
    }

    public function searchProviders(): void
    {
        abort_unless(in_array($this->section, ['services', 'booking-setup'], true), 404);
        $this->authorizeCapability('providers.manage');
        $this->validate(['providerSearch' => ['nullable', 'string', 'max:80']]);
        $this->pagination['providerOptions']['current_page'] = 1;
        $this->withApi(fn (PlatformApiClient $api, string $accessToken) => $this->loadProviderOptions($api, $accessToken));
    }

    public function selectOfferingProvider(string $providerLinkId): void
    {
        $this->authorizeCapability('providers.manage');
        if ($providerLinkId === '') {
            $this->offeringProviderLinkId = '';
            $this->selectedOfferingProvider = null;

            return;
        }
        $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($providerLinkId): void {
            $this->selectedOfferingProvider = $this->selectedProvider($api, $accessToken, $providerLinkId);
            $this->offeringProviderLinkId = $providerLinkId;
        });
    }

    public function showSection(string $section): void
    {
        $this->authorizeSection($section);
        if ($this->bookingId !== null) {
            $this->redirectRoute('dashboard', ['section' => $section, 'status' => $section === 'bookings' ? $this->bookingFilter() : null], navigate: true);

            return;
        }
        $this->section = $section;
        $this->message = null;

        $this->withApi(function (PlatformApiClient $api, string $accessToken): void {
            $this->loadSection($api, $accessToken);
        });
    }

    public function displayDate(?string $value): string
    {
        return $this->displayCarbon($value)?->translatedFormat($this->displayDateFormat) ?? '—';
    }

    public function displayDateTime(?string $value, bool $includeZone = false, ?string $timezone = null): string
    {
        $date = $this->displayCarbon($value, $timezone);

        return $date
            ? $date->translatedFormat($this->displayDateFormat).' · '.$date->translatedFormat($this->displayTimeFormat.($includeZone ? ' T' : ''))
            : '—';
    }

    public function displayRelative(?string $value): string
    {
        return $this->displayCarbon($value)?->diffForHumans() ?? '—';
    }

    public function changePage(string $resource, int $page): void
    {
        if ($resource === 'organizations') {
            $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($page): void {
                $this->assignPage('organizations', $api->organizations($accessToken, max(1, $page), $this->organizationSearch));
            });

            return;
        }
        if ($resource === 'providerOptions') {
            abort_unless(in_array($this->section, ['services', 'booking-setup'], true), 404);
            $this->authorizeCapability('providers.manage');
            $this->pagination['providerOptions']['current_page'] = max(1, $page);
            $this->withApi(fn (PlatformApiClient $api, string $accessToken) => $this->loadProviderOptions($api, $accessToken));

            return;
        }
        abort_unless((self::PAGINATION_SECTIONS[$resource] ?? null) === $this->section, 404);
        $meta = $this->pagination[$resource] ?? null;
        abort_unless(is_array($meta), 404);

        $page = min(
            max(1, $page),
            max(1, $meta['last_page']),
        );

        if (in_array($resource, ['commissions', 'agreements'], true)) {
            $this->authorizeSection('revenue');
            $this->withApi(fn (PlatformApiClient $api, string $token) => $this->loadRevenuePage($api, $token, $this->requiredOrganizationId(), $resource, $page));

            return;
        }
        $this->pagination[$resource]['current_page'] = $page;
        if ($resource === 'bookings') {
            $this->bookingPage = (int) $this->pagination[$resource]['current_page'];
        }

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

        $payload = array_filter([
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
        ], static fn (mixed $value): bool => $value !== null);

        $this->withMutationApi('provider_claims.create', 'provider-claims', $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $api->submitProviderClaim($accessToken, $this->requiredOrganizationId(), $payload, $idempotencyKey);
            $markMutationSucceeded();
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

        $payload = array_filter([
            'provider_link_id' => $this->offeringProviderLinkId,
            'name' => Str::squish($this->offeringName),
            'default_duration_minutes' => $this->offeringDurationMinutes,
        ], static fn (mixed $value): bool => $value !== null);

        $this->withMutationApi('offerings.create', 'offerings', $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $created = $api->createOffering($accessToken, $this->requiredOrganizationId(), $payload, $idempotencyKey);
            $markMutationSucceeded();
            $this->assignPage('offerings', $api->offerings(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->currentPage('offerings'),
            ));
            if (is_string($created['id'] ?? null) && is_string($created['name'] ?? null) && ($created['status'] ?? null) === 'draft') {
                $this->openOfferingEditor($created);
            }
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

        $payload = [
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
        ];

        $current = $this->identity['business_profile'] ?? [];
        $current['name'] = Str::squish((string) data_get($this->identity, 'organization.name'));
        $current['primary_contact_email'] = mb_strtolower(trim((string) ($current['primary_contact_email'] ?? '')));
        $current['registered_country_code'] = Str::upper(trim((string) ($current['registered_country_code'] ?? '')));
        $payload = array_filter($payload, fn ($value, $key): bool => $this->nullableField((string) ($current[$key] ?? '')) !== $value, ARRAY_FILTER_USE_BOTH);
        if ($payload === []) {
            $this->notice = 'No changes to save.';

            return;
        }

        $this->withMutationApi('business_profile.update', 'business-profile', $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $this->identity = $api->updateBusinessProfile($accessToken, $this->requiredOrganizationId(), $payload, $idempotencyKey);
            $markMutationSucceeded();
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

        $this->withMutationApi('providers.update', "providers/{$this->editingProviderLinkId}", $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $api->updateManagedProvider(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->editingProviderLinkId,
                $payload,
                $idempotencyKey,
            );
            $markMutationSucceeded();
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

        $this->openOfferingEditor($offering);
    }

    /** @param array<string, mixed> $offering */
    private function openOfferingEditor(array $offering): void
    {
        $this->editingOfferingId = (string) $offering['id'];
        $this->editingOfferingScope = PartnerOfferingScope::label($offering);
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

        $payload = [
            'name' => Str::squish($this->editingOfferingName),
            'description' => $this->nullableField($this->editingOfferingDescription),
            'default_duration_minutes' => $this->editingOfferingDurationMinutes,
            'status' => $this->editingOfferingStatus,
            'request_mode' => $this->editingOfferingRequestMode,
        ];

        $this->withMutationApi('offerings.update', "offerings/{$this->editingOfferingId}", $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $api->updateOffering(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->editingOfferingId,
                $payload,
                $idempotencyKey,
            );
            $markMutationSucceeded();
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
        abort_unless($offeringId !== '' && ($this->editingOfferingId === $offeringId || collect($this->offerings)->contains('id', $offeringId)), 404);

        $this->withMutationApi('offerings.delete', "offerings/{$offeringId}", [], function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($offeringId): void {
            $api->deleteOffering($accessToken, $this->requiredOrganizationId(), $offeringId, $idempotencyKey);
            $markMutationSucceeded();
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
        $this->authorizeBookingConfiguration();
        if ($providerLinkId !== $this->bookingProviderLinkId && $this->bookingDraftBaseline !== null
            && ! hash_equals($this->bookingDraftBaseline, $this->bookingDraftSignature())) {
            $this->pendingBookingProvider = $providerLinkId;

            return;
        }
        $this->loadBookingLocation($providerLinkId);
    }

    public function keepBookingDraft(): void
    {
        $this->pendingBookingProvider = null;
    }

    public function discardBookingDraftAndSwitch(): void
    {
        $this->authorizeBookingConfiguration();
        if ($this->pendingBookingProvider !== null) {
            $this->loadBookingLocation($this->pendingBookingProvider);
        }
    }

    private function loadBookingLocation(string $providerLinkId): void
    {
        $this->pendingBookingProvider = null;
        $this->bookingDraftBaseline = null;
        $this->loadedBookingProviderLinkId = null;
        $this->bookingConfigurationState = 'unavailable';
        $this->bookingConfigurationRevision = null;
        $this->bookingProviderLinkId = $providerLinkId;
        $this->selectedBookingProvider = null;
        if ($providerLinkId === '') {
            return;
        }
        $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($providerLinkId): void {
            $link = $this->selectedProvider($api, $accessToken, $providerLinkId);
            $this->selectedBookingProvider = $link;
            $profile = $api->bookingProfile($accessToken, $this->requiredOrganizationId(), $providerLinkId);
            $this->hydrateBookingConfiguration($link, $profile);
        });
    }

    public function addBookingAvailabilityRule(): void
    {
        $this->authorizeBookingConfiguration();

        $this->bookingAvailabilityRules[] = [
            'day_of_week' => 1,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'effective_from' => '',
            'effective_until' => '',
            'is_active' => true,
        ];
    }

    public function removeBookingAvailabilityRule(int $index): void
    {
        $this->authorizeBookingConfiguration();
        abort_unless(array_key_exists($index, $this->bookingAvailabilityRules), 404);

        unset($this->bookingAvailabilityRules[$index]);
        $this->bookingAvailabilityRules = array_values($this->bookingAvailabilityRules);
        $this->resetValidation('bookingAvailabilityRules');
    }

    public function addBookingAvailabilityException(): void
    {
        $this->authorizeBookingConfiguration();

        $this->bookingAvailabilityExceptions[] = [
            'date' => '',
            'starts_at' => '',
            'ends_at' => '',
            'availability' => 'unavailable',
            'reason' => '',
        ];
    }

    public function removeBookingAvailabilityException(int $index): void
    {
        $this->authorizeBookingConfiguration();
        abort_unless(array_key_exists($index, $this->bookingAvailabilityExceptions), 404);

        unset($this->bookingAvailabilityExceptions[$index]);
        $this->bookingAvailabilityExceptions = array_values($this->bookingAvailabilityExceptions);
        $this->resetValidation('bookingAvailabilityExceptions');
    }

    public function saveBookingConfiguration(): void
    {
        $this->authorizeBookingConfiguration();
        if ($this->bookingConfigurationState === 'conflict') {
            $this->message = 'Review the latest booking settings before saving again.';

            return;
        }
        if ($this->selectedBookingProvider === null || $this->loadedBookingProviderLinkId === null || $this->loadedBookingProviderLinkId !== $this->bookingProviderLinkId) {
            $this->addError('bookingProviderLinkId', 'Choose a location and load its current booking settings before saving.');

            return;
        }
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
            'bookingAvailabilityRules' => ['array', 'max:35'],
            'bookingAvailabilityRules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'bookingAvailabilityRules.*.starts_at' => ['required', 'date_format:H:i'],
            'bookingAvailabilityRules.*.ends_at' => ['required', 'date_format:H:i', 'after:bookingAvailabilityRules.*.starts_at'],
            'bookingAvailabilityRules.*.effective_from' => ['nullable', 'date_format:Y-m-d'],
            'bookingAvailabilityRules.*.effective_until' => ['nullable', 'date_format:Y-m-d'],
            'bookingAvailabilityRules.*.is_active' => ['boolean'],
            'bookingAvailabilityExceptions' => ['array', 'max:180'],
            'bookingAvailabilityExceptions.*.date' => ['required', 'date_format:Y-m-d'],
            'bookingAvailabilityExceptions.*.starts_at' => ['nullable', 'date_format:H:i', 'required_with:bookingAvailabilityExceptions.*.ends_at'],
            'bookingAvailabilityExceptions.*.ends_at' => ['nullable', 'date_format:H:i', 'required_with:bookingAvailabilityExceptions.*.starts_at'],
            'bookingAvailabilityExceptions.*.availability' => ['required', 'in:available,unavailable'],
            'bookingAvailabilityExceptions.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'provider_link_id' => $this->bookingProviderLinkId,
            'expected_revision' => $this->bookingConfigurationRevision,
            'status' => $this->bookingStatus,
            'timezone' => $this->bookingTimezone,
            'notification_email' => $this->nullableField(mb_strtolower(trim($this->bookingNotificationEmail))),
            'notification_phone' => $this->nullableField($this->bookingNotificationPhone),
            ...array_filter([
                'minimum_notice_hours' => $this->bookingMinimumNoticeHours,
                'maximum_advance_days' => $this->bookingMaximumAdvanceDays,
                'response_window_hours' => $this->bookingResponseWindowHours,
            ], static fn (mixed $value): bool => $value !== null),
            'customer_instructions' => $this->nullableField($this->bookingCustomerInstructions),
            'availability_rules' => $this->normalizedBookingAvailabilityRules(),
            'availability_exceptions' => $this->normalizedBookingAvailabilityExceptions(),
        ];

        $this->withMutationApi('booking_profiles.update', "booking-profiles/{$this->bookingProviderLinkId}", $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $profile = $api->updateBookingProfile($accessToken, $this->requiredOrganizationId(), $payload, $idempotencyKey);
            $markMutationSucceeded();
            $this->hydrateBookingConfiguration($this->selectedBookingProvider, $profile);
            $this->notice = $this->bookingStatus === 'enabled'
                ? 'Booking requests are enabled for this location.'
                : 'Booking settings saved.';
        });
    }

    public function acceptBooking(string $bookingId, string $windowId): void
    {
        $this->respondToBooking($bookingId, ['action' => 'accept', 'window_id' => $windowId]);
    }

    public function proposeBooking(string $bookingId): void
    {
        $this->authorizeExactBooking($bookingId);
        $this->validate(['bookingProposedTime' => ['required', 'date_format:Y-m-d\TH:i']], attributes: ['bookingProposedTime' => 'appointment time']);
        $this->bookingPetProfileOpen = false;
        $this->notice = null;

        $startsAt = null;
        $this->withApi(function (PlatformApiClient $api, string $token) use ($bookingId, &$startsAt): void {
            $booking = $api->booking($token, $this->requiredOrganizationId(), $bookingId);
            if (($booking['id'] ?? null) !== $bookingId
                || ! in_array('propose', (array) ($booking['available_actions'] ?? []), true)) {
                throw new PlatformApiException(409, 'This booking can no longer receive a proposed time.');
            }

            $timezone = new \DateTimeZone((string) ($booking['appointment_timezone'] ?? 'UTC'));
            $format = 'Y-m-d\TH:i';
            $start = \DateTimeImmutable::createFromFormat('!'.$format, $this->bookingProposedTime, $timezone);
            if ($start === false || $start->format($format) !== $this->bookingProposedTime) {
                $this->addError('bookingProposedTime', 'Enter a valid time in the appointment location’s timezone.');

                return;
            }

            $startsAt = $start->format(DATE_ATOM);
        });
        if ($startsAt !== null) {
            $this->respondToBooking($bookingId, ['action' => 'propose', 'proposed_windows' => [['starts_at' => $startsAt]]]);
        }
    }

    public function completeBooking(string $bookingId): void
    {
        $this->respondToBooking($bookingId, ['action' => 'complete']);
    }

    public function showBookingMessagePage(int $page): void
    {
        $this->authorizeExactBooking((string) $this->bookingId);
        $this->bookingMessagePage = max(1, min($page, 100_000));
    }

    public function toggleBookingPetProfile(string $bookingId): void
    {
        $this->authorizeExactBooking($bookingId);
        $this->bookingPetProfileOpen = ! $this->bookingPetProfileOpen;
    }

    public function declineBooking(string $bookingId): void
    {
        $this->authorizeExactBooking($bookingId);
        $this->validate([
            'bookingDeclineReason' => ['required', 'string', 'max:1000'],
        ], [
            'bookingDeclineReason.required' => 'Add a short reason before declining this request.',
        ]);
        $this->respondToBooking($bookingId, [
            'action' => 'decline',
            'reason' => Str::squish($this->bookingDeclineReason),
        ]);
    }

    public function applyForReferralProgram(): void
    {
        $this->authorizeCapability('programs.manage');
        $payload = [
            'program_key' => 'referral',
            'application_notes' => 'Submitted from the Zigpaw Partners workspace.',
        ];

        $this->withMutationApi('programs.apply', 'programs', $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $api->applyForProgram($accessToken, $this->requiredOrganizationId(), $payload, $idempotencyKey);
            $markMutationSucceeded();
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
            'teamRole' => ['required', Rule::in(data_get($this->identity, 'membership.assignable_team_roles', []))],
        ]);

        $payload = [
            'email' => mb_strtolower(trim($this->teamEmail)),
            'role' => $this->teamRole,
        ];

        $this->withMutationApi('team.invitations.create', 'team/invitations', $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $api->inviteTeamMember($accessToken, $this->requiredOrganizationId(), $payload, $idempotencyKey);
            $markMutationSucceeded();
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
        abort_unless(data_get($member, 'actions.edit_role') === true, 404);

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
            'editingTeamRole' => ['required', Rule::in(data_get($this->identity, 'membership.assignable_team_roles', []))],
        ]);

        $member = collect($this->team)->firstWhere('id', $this->editingTeamMemberId);
        abort_unless(data_get($member, 'actions.edit_role') === true, 404);
        $payload = ['role' => $this->editingTeamRole];

        $this->withMutationApi('team.memberships.update', "team/memberships/{$this->editingTeamMemberId}", $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($payload): void {
            $api->updateTeamMember(
                $accessToken,
                $this->requiredOrganizationId(),
                $this->editingTeamMemberId,
                $payload,
                $idempotencyKey,
            );
            $markMutationSucceeded();
            $this->reloadTeam($api, $accessToken);
            $this->cancelTeamMemberEdit();
            $this->notice = 'Team role updated.';
        });
    }

    public function resendTeamInvitation(string $membershipId): void
    {
        $this->authorizeCapability('team.manage');
        $member = collect($this->team)->firstWhere('id', $membershipId);
        abort_unless(data_get($member, 'actions.resend') === true, 404);

        $this->withMutationApi('team.invitations.resend', "team/invitations/{$membershipId}/resend", [], function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($membershipId): void {
            $api->resendTeamInvitation($accessToken, $this->requiredOrganizationId(), $membershipId, $idempotencyKey);
            $markMutationSucceeded();
            $this->reloadTeam($api, $accessToken);
            $this->notice = 'Invitation sent again.';
        });
    }

    public function revokeTeamMember(string $membershipId): void
    {
        $this->authorizeCapability('team.manage');
        $member = collect($this->team)->firstWhere('id', $membershipId);
        abort_unless(data_get($member, 'actions.revoke') === true, 404);

        $this->withMutationApi('team.memberships.revoke', "team/memberships/{$membershipId}", [], function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($membershipId): void {
            $api->revokeTeamMember($accessToken, $this->requiredOrganizationId(), $membershipId, $idempotencyKey);
            $markMutationSucceeded();
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
        $booking = $bookingPetProfile = $bookingError = $bookingPetError = $bookingMessages = $bookingMessagesError = null;
        if ($this->state === 'ready' && $this->bookingId !== null) {
            $tokens = app(PortalAccessTokenStore::class);
            try {
                $token = $tokens->accessToken();
                if ($token === null) {
                    throw new PlatformApiException(401, 'Sign in to open the booking.');
                }
                $this->authorizeExactBooking($this->bookingId);
                $api = app(PlatformApiClient::class);
                $booking = $api->booking($token, $this->requiredOrganizationId(), $this->bookingId);
                if (($booking['id'] ?? null) !== $this->bookingId || ! is_string($booking['request_number'] ?? null)
                    || ! is_string($booking['status'] ?? null) || ! is_array($booking['available_actions'] ?? null)) {
                    throw new PlatformApiException(502, 'Zigpaw could not verify the booking details. Try again.');
                }
                $this->bookingWasVerified = true;
                try {
                    $bookingMessages = $api->bookingMessages($token, $this->requiredOrganizationId(), $this->bookingId, $this->bookingMessagePage);
                    if ($this->bookingMessagePage > $bookingMessages['meta']['last_page']) {
                        $this->bookingMessagePage = $bookingMessages['meta']['last_page'];
                        $bookingMessages = $api->bookingMessages($token, $this->requiredOrganizationId(), $this->bookingId, $this->bookingMessagePage);
                    }
                } catch (PlatformApiException $exception) {
                    if (in_array($exception->status, [401, 403, 404], true)) {
                        throw $exception;
                    }
                    $bookingMessagesError = 'Booking messages could not be loaded. Retry to see the latest instructions.';
                }
                if ($this->bookingPetProfileOpen) {
                    [$bookingPetProfile, $bookingPetError] = $this->readBookingPetProfile($api, $token, $booking);
                }
            } catch (PlatformApiException $exception) {
                $booking = null;
                $this->bookingPetProfileOpen = false;
                if ($exception->status === 401) {
                    $this->handlePlatformFailure($exception, $tokens);
                } else {
                    $bookingError = $exception->getMessage();
                }
            }
        }

        return view('livewire.business-workspace', compact('booking', 'bookingPetProfile', 'bookingError', 'bookingPetError', 'bookingMessages', 'bookingMessagesError'))
            ->layout('components.layouts.portal', ['title' => 'Zigpaw Partners']);
    }

    /** @param array<string, mixed> $booking */
    public function canShowBookingPetProfile(array $booking): bool
    {
        $expiresAt = data_get($booking, 'pet_access.expires_at');

        return data_get($booking, 'pet_access.status') === 'active'
            && is_string(data_get($booking, 'pet_access.id'))
            && is_string(data_get($booking, 'pet.id'))
            && ($expiresAt === null || (is_string($expiresAt) && $this->displayCarbon($expiresAt)?->isFuture() === true));
    }

    /** @param array<string, mixed> $booking @return array{0: array<string, mixed>|null, 1: string|null} */
    private function readBookingPetProfile(PlatformApiClient $api, string $token, array $booking): array
    {
        if ($this->canShowBookingPetProfile($booking)) {
            try {
                $profile = $api->bookingPetContext($token, $this->requiredOrganizationId(), (string) $this->bookingId);
                $expiresAt = data_get($profile, 'access.expires_at');
                if (data_get($profile, 'access.id') === data_get($booking, 'pet_access.id')
                    && data_get($profile, 'access.status') === 'active'
                    && ($expiresAt === null || (is_string($expiresAt) && $this->displayCarbon($expiresAt)?->isFuture() === true))
                    && data_get($profile, 'pet.id') === data_get($booking, 'pet.id')
                    && is_array($profile['pet'] ?? null)) {
                    return [$profile['pet'], null];
                }
            } catch (PlatformApiException $exception) {
                if ($exception->status === 401) {
                    throw $exception;
                }
                if (! in_array($exception->status, [403, 404], true)) {
                    $this->bookingPetProfileOpen = false;

                    return [null, 'The pet profile could not be loaded. Try opening it again.'];
                }
            }
        }

        $this->bookingPetProfileOpen = false;

        return [null, 'This appointment no longer grants access to the pet profile.'];
    }

    private function authorizeExactBooking(string $bookingId): void
    {
        abort_unless($this->bookingId === $bookingId && $this->organizationId !== null
            && $this->organizationId === session('portal.organization_id'), 404);
        $this->authorizeCapability('bookings.manage');
        $this->authorizeFeature('booking_requests');
    }

    public function bookingFilter(): ?string
    {
        return PartnerBookingStatus::filter($this->bookingListStatus);
    }

    /** @param array<string, mixed> $payload */
    private function respondToBooking(string $bookingId, array $payload): void
    {
        $this->authorizeExactBooking($bookingId);
        $this->bookingPetProfileOpen = false;
        $this->notice = null;

        $this->validate(['bookingReply' => ['nullable', 'string', 'max:4000']]);
        if (filled($this->bookingReply)) {
            $payload['message'] = trim($this->bookingReply);
        }

        $this->withMutationApi('bookings.respond', "bookings/{$bookingId}/response", $payload, function (PlatformApiClient $api, string $accessToken, string $idempotencyKey, callable $markMutationSucceeded) use ($bookingId, $payload): void {
            $status = match ($payload['action']) {
                'accept' => 'accepted',
                'propose' => 'proposed',
                'decline' => 'declined',
                'complete' => 'completed',
                default => throw new \LogicException('Unknown booking action.'),
            };
            try {
                $receipt = $api->respondToBooking($accessToken, $this->requiredOrganizationId(), $bookingId, $payload, $idempotencyKey);
            } catch (PlatformApiException $exception) {
                if ($payload['action'] === 'accept' && $exception->status === 422 && isset($exception->errors['window_id'])) {
                    throw new PlatformApiException(422, 'That time is now unavailable. Review the updated appointment options.');
                }

                throw $exception;
            }
            if (($receipt['id'] ?? null) !== $bookingId || ($receipt['status'] ?? null) !== $status
                || ($status === 'proposed' && ! $this->confirmsBookingProposal($receipt, $payload['proposed_windows'][0]['starts_at']))) {
                throw new PlatformApiException(502, 'Zigpaw could not confirm the update. Review the current booking before retrying.');
            }
            $markMutationSucceeded();
            $this->bookingReply = '';
            $this->bookingDeclineReason = '';
            $this->bookingProposedTime = '';
            $this->notice = match ($status) {
                'accepted' => 'Booking accepted. The customer can now see the confirmed time.',
                'proposed' => 'New time proposed. Waiting for customer confirmation.',
                'declined' => 'Booking declined.',
                'completed' => 'Booking marked complete.',
            };
        });
    }

    /** @param array<string, mixed> $booking */
    private function confirmsBookingProposal(array $booking, string $startsAt): bool
    {
        $expected = $this->displayCarbon($startsAt);

        return $expected !== null && collect($booking['requested_windows'] ?? [])->contains(
            fn ($window): bool => data_get($window, 'source') === 'provider'
                && data_get($window, 'status') === 'proposed'
                && is_string(data_get($window, 'starts_at'))
                && $this->displayCarbon($window['starts_at'])?->equalTo($expected) === true,
        );
    }

    private function loadSection(PlatformApiClient $api, string $accessToken): void
    {
        $this->authorizeSection($this->section);
        $organizationId = $this->requiredOrganizationId();

        match ($this->section) {
            'overview' => $this->loadOverview($api, $accessToken, $organizationId),
            'profile' => $this->hydrateBusinessProfile(),
            'listings' => $this->loadListings($api, $accessToken, $organizationId),
            'bookings' => $this->loadBookingQueue($api, $accessToken, $organizationId),
            'booking-setup' => $this->loadBookingSetup($api, $accessToken, $organizationId),
            'services' => $this->loadServices($api, $accessToken, $organizationId),
            'programs' => $this->assignPage('programs', $api->programs($accessToken, $organizationId, $this->currentPage('programs'))),
            'revenue' => $this->loadRevenue($api, $accessToken, $organizationId),
            'team' => $this->assignPage('team', $api->team($accessToken, $organizationId, $this->currentPage('team'))),
            default => abort(404),
        };
    }

    private function loadBookingQueue(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        $fetch = fn (int $page): array => $api->bookings($accessToken, $organizationId, $page, status: $this->bookingFilter());
        $page = $fetch($this->currentPage('bookings'));
        if ($page['data'] === [] && $this->bookingPage > $page['meta']['last_page']) {
            $this->bookingPage = max(1, $page['meta']['last_page']);
            $page = $fetch($this->bookingPage);
        }
        $this->assignPage('bookings', $page);
    }

    private function loadOverview(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        if ($this->hasCapability('providers.manage')) {
            $this->assignPage('providers', $api->providers($accessToken, $organizationId, 1, 6));
        }

        if ($this->hasCapability('bookings.manage') && $this->featureIsAvailable('booking_requests')) {
            // The identity response owns the complete booking metric. This request is
            // only the bounded requested-items feed used by the attention list.
            $this->assignPage('bookings', $api->bookings($accessToken, $organizationId, 1, 5, 'requested'));
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
        $this->loadProviderOptions($api, $accessToken);
        $this->assignPage('offerings', $api->offerings($accessToken, $organizationId, $this->currentPage('offerings')));
        $providerLinkId = $this->offeringProviderLinkId ?: (string) data_get($this->providerOptions, '0.id', '');
        if ($providerLinkId !== '') {
            $this->selectedOfferingProvider = $this->selectedProvider($api, $accessToken, $providerLinkId);
            $this->offeringProviderLinkId = $providerLinkId;
        }
    }

    private function loadBookingSetup(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        $this->loadedBookingProviderLinkId = null;
        $this->bookingConfigurationState = 'unavailable';
        $this->loadProviderOptions($api, $accessToken);
        $providerLinkId = $this->bookingProviderLinkId ?: (string) data_get($this->providerOptions, '0.id', '');
        if ($providerLinkId !== '') {
            $this->hydrateBookingConfiguration(
                $this->selectedProvider($api, $accessToken, $providerLinkId),
                $api->bookingProfile($accessToken, $organizationId, $providerLinkId),
            );
        }
    }

    private function loadProviderOptions(PlatformApiClient $api, string $accessToken): void
    {
        $this->assignPage('providerOptions', $api->providers($accessToken, $this->requiredOrganizationId(), $this->currentPage('providerOptions'), 25, $this->providerSearch, true));
    }

    /** @return array<string, mixed> */
    private function selectedProvider(PlatformApiClient $api, string $accessToken, string $providerLinkId): array
    {
        $link = collect($this->providerOptions)->firstWhere('id', $providerLinkId)
            ?? $api->provider($accessToken, $this->requiredOrganizationId(), $providerLinkId);
        if (($link['id'] ?? null) !== $providerLinkId) {
            throw new PlatformApiException(502, 'The selected location could not be loaded. Please choose it again.');
        }

        return $link;
    }

    private function loadRevenue(PlatformApiClient $api, string $accessToken, string $organizationId): void
    {
        $this->financials = $api->financials($accessToken, $organizationId);
        foreach (['commissions', 'agreements'] as $resource) {
            $this->loadRevenuePage($api, $accessToken, $organizationId, $resource, $this->currentPage($resource));
        }
    }

    private function loadRevenuePage(PlatformApiClient $api, string $token, string $organizationId, string $resource, int $page): void
    {
        $fetch = fn (int $number): array => $resource === 'commissions'
            ? $api->commissions($token, $organizationId, $number)
            : $api->agreements($token, $organizationId, $number);
        $result = $fetch($page);
        if ($page > $result['meta']['last_page']) {
            $result = $fetch($result['meta']['last_page']);
        }
        $this->assignPage($resource, $result);
    }

    /** @param array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}, links: array<string, ?string>} $page */
    private function assignPage(string $resource, array $page): void
    {
        $this->{$resource} = $page['data'];
        $this->pagination[$resource] = $page['meta'];
    }

    private function currentPage(string $resource): int
    {
        if ($resource === 'bookings') {
            return max(1, min(100_000, $this->bookingPage));
        }

        return max(1, (int) data_get($this->pagination, "{$resource}.current_page", 1));
    }

    private function hydrateBusinessProfile(): void
    {
        $this->displayTimezone = (string) data_get($this->identity, 'display_preferences.timezone', 'UTC');
        $this->displayDateFormat = (string) data_get($this->identity, 'display_preferences.date_format', 'd/m/Y');
        $this->displayTimeFormat = (string) data_get($this->identity, 'display_preferences.time_format', 'H:i');
        $this->displayLocale = (string) data_get($this->identity, 'display_preferences.locale', 'en');
        $this->businessName = (string) data_get($this->identity, 'organization.name', '');
        $this->businessLegalName = (string) data_get($this->identity, 'business_profile.legal_name', '');
        $this->businessRegistrationNumber = (string) data_get($this->identity, 'business_profile.registration_number', '');
        $this->businessRegistrationNumberType = (string) data_get($this->identity, 'business_profile.registration_number_type', '');
        $this->businessTaxIdentifier = (string) data_get($this->identity, 'business_profile.tax_identifier', '');
        $this->businessTaxIdentifierType = (string) data_get($this->identity, 'business_profile.tax_identifier_type', '');
        $this->businessRegisteredCountryCode = (string) data_get($this->identity, 'business_profile.registered_country_code', '');
        $this->businessPrimaryContactName = (string) data_get($this->identity, 'business_profile.primary_contact_name', '');
        $this->businessPrimaryContactEmail = (string) data_get($this->identity, 'business_profile.primary_contact_email', '');
        $this->businessPrimaryContactPhone = (string) data_get($this->identity, 'business_profile.primary_contact_phone', '');
        $this->businessWebsite = (string) data_get($this->identity, 'business_profile.website', '');
    }

    private function hydrateBookingConfiguration(array $link, ?array $profile): void
    {
        if ($profile !== null && (! is_string($profile['revision'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $profile['revision']))) {
            throw new PlatformApiException(502, 'The booking settings revision could not be verified. Reload this location before editing.');
        }
        $serviceProviderId = (string) data_get($link, 'provider.id', '');
        $placeId = data_get($link, 'place.id');
        if ($profile !== null && ((string) ($profile['service_provider_id'] ?? '') !== $serviceProviderId
            || (string) ($profile['place_id'] ?? '') !== (string) ($placeId ?? ''))) {
            throw new PlatformApiException(502, 'The selected location’s booking settings could not be verified. Please try again.');
        }

        $this->selectedBookingProvider = $link;
        $this->bookingProviderLinkId = (string) $link['id'];
        $this->loadedBookingProviderLinkId = $this->bookingProviderLinkId;
        $this->bookingConfigurationState = $profile === null ? 'unconfigured' : 'configured';
        $this->bookingConfigurationRevision = $profile === null ? 'unconfigured' : $profile['revision'];
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
        $this->pendingBookingProvider = null;
        $this->bookingDraftBaseline = $this->bookingDraftSignature();
    }

    private function bookingDraftSignature(): string
    {
        $fields = $this->only([
            'bookingStatus', 'bookingTimezone', 'bookingNotificationEmail', 'bookingNotificationPhone',
            'bookingMinimumNoticeHours', 'bookingMaximumAdvanceDays', 'bookingResponseWindowHours',
            'bookingCustomerInstructions', 'bookingAvailabilityRules', 'bookingAvailabilityExceptions',
        ]);

        return hash_hmac('sha256', serialize($fields), (string) config('app.key'));
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
            'editingOfferingScope',
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

    private function displayCarbon(?string $value, ?string $timezone = null): ?Carbon
    {
        return PortalDateTime::parse($value, $timezone ?? $this->displayTimezone, $this->displayLocale);
    }

    /** @return list<array<string, mixed>> */
    private function normalizedBookingAvailabilityRules(): array
    {
        return collect($this->bookingAvailabilityRules)
            ->map(fn (array $rule): array => [
                'day_of_week' => (int) $rule['day_of_week'],
                'starts_at' => (string) $rule['starts_at'],
                'ends_at' => (string) $rule['ends_at'],
                'effective_from' => $this->nullableField((string) ($rule['effective_from'] ?? '')),
                'effective_until' => $this->nullableField((string) ($rule['effective_until'] ?? '')),
                'is_active' => (bool) ($rule['is_active'] ?? true),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function normalizedBookingAvailabilityExceptions(): array
    {
        return collect($this->bookingAvailabilityExceptions)
            ->map(fn (array $exception): array => [
                'date' => (string) $exception['date'],
                'starts_at' => $this->nullableField((string) ($exception['starts_at'] ?? '')),
                'ends_at' => $this->nullableField((string) ($exception['ends_at'] ?? '')),
                'availability' => (string) $exception['availability'],
                'reason' => $this->nullableField((string) ($exception['reason'] ?? '')),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, array{label: string, icon: string}> */
    private function availableNavigationSections(): array
    {
        $sections = [];

        foreach (self::SECTION_DEFINITIONS as $key => $definition) {
            if (! $this->hasCapability($definition['capability'])) {
                continue;
            }

            if ($key === 'booking-setup' && ! $this->hasCapability('providers.manage')) {
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

    private function authorizeBookingConfiguration(): void
    {
        $this->authorizeCapability('providers.manage');
        $this->authorizeCapability('bookings.manage');
        $this->authorizeFeature('booking_requests');
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

    /**
     * Execute a management mutation with a session-bound retry key.
     *
     * Only the keyed fingerprint and opaque key are retained in the encrypted
     * BFF session. The browser never receives the payload, token or session ID.
     * Uncertain upstream outcomes keep their key so a later intent can replay
     * the same Platform operation within the Platform retention window. Once
     * the mutation itself succeeds, the key is also retained if a follow-up
     * hydration read fails; the UI must not turn a committed write into a new
     * intent merely because its refresh response was lost.
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(PlatformApiClient, string, string, callable): void  $callback
     */
    private function withMutationApi(string $operation, string $resource, array $payload, callable $callback): void
    {
        $organizationId = $this->requiredOrganizationId();
        $this->withApi(function (PlatformApiClient $api, string $accessToken) use ($operation, $resource, $payload, $organizationId, $callback): void {
            $fingerprint = $this->mutationFingerprint($operation, $resource, $organizationId, $payload);
            $idempotencyKey = $this->pendingMutationKey($fingerprint, $operation, $resource);
            $mutationSucceeded = false;
            $markMutationSucceeded = static function () use (&$mutationSucceeded): void {
                $mutationSucceeded = true;
            };

            try {
                $this->resetErrorBag();
                $callback($api, $accessToken, $idempotencyKey, $markMutationSucceeded);
                $this->forgetPendingMutation($fingerprint);
            } catch (PlatformApiException $exception) {
                if ($operation === 'booking_profiles.update' && $exception->errorCode === 'BOOKING_CONFIGURATION_CHANGED') {
                    $this->bookingConfigurationState = 'conflict';
                }
                if ($exception->status === 422) {
                    $this->mapWorkspaceValidationErrors($exception->errors, $this->mutationValidationFields($operation, $resource));
                }
                if (! $mutationSucceeded && ! $this->isUncertainMutationFailure($exception)) {
                    $this->forgetPendingMutation($fingerprint);
                }

                throw $exception;
            }
        });
    }

    /** @param array<string, mixed> $payload */
    private function mutationFingerprint(
        string $operation,
        string $resource,
        string $organizationId,
        array $payload,
    ): string {
        $membershipId = data_get($this->identity, 'membership.id');
        $accountBinding = is_string($membershipId) && $membershipId !== ''
            ? $membershipId
            : session()->get('platform.oauth.token_handle');
        if (! is_string($accountBinding) || $accountBinding === '') {
            throw new PlatformApiException(
                409,
                'This update cannot be safely retried until the workspace session is re-established.',
            );
        }
        $sessionBinding = hash_hmac('sha256', $this->mutationRetryContext(), (string) config('app.key'));

        return hash_hmac('sha256', json_encode([
            'version' => 1,
            'operation' => $operation,
            'resource' => $resource,
            'organization' => $organizationId,
            'account' => $accountBinding,
            'session' => $sessionBinding,
            'payload' => $this->canonicalMutationValue($payload),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function mutationRetryContext(): string
    {
        // This opaque nonce is migrated by Session::regenerate() with the
        // authenticated session data and removed by Session::invalidate().
        $context = session()->get(self::MUTATION_RETRY_CONTEXT_SESSION_KEY);
        if (is_string($context) && strlen($context) >= 32) {
            return $context;
        }

        $context = Str::random(64);
        session()->put(self::MUTATION_RETRY_CONTEXT_SESSION_KEY, $context);
        session()->save();

        return $context;
    }

    private function canonicalMutationValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalMutationValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->canonicalMutationValue($item);
        }
        ksort($normalized);

        return $normalized;
    }

    private function pendingMutationKey(string $fingerprint, string $operation, string $resource): string
    {
        $pending = session()->get(self::PENDING_MUTATIONS_SESSION_KEY, []);
        $pending = is_array($pending) ? $pending : [];
        $entry = $pending[$fingerprint] ?? null;
        if (is_array($entry) && is_string($entry['key'] ?? null) && $entry['key'] !== '') {
            $createdAt = (int) ($entry['created_at'] ?? 0);
            if ($createdAt <= 0 || $createdAt < now()->getTimestamp() - self::PENDING_MUTATION_SAFE_RETRY_SECONDS) {
                throw new PlatformApiException(
                    409,
                    'This update is older than the safe retry window. Please start a deliberate new update after confirming its result.',
                );
            }

            return $entry['key'];
        }

        if (count($pending) >= self::MAX_PENDING_MUTATIONS) {
            throw new PlatformApiException(
                409,
                'An earlier update is awaiting confirmation. Please retry it before starting another update.',
            );
        }

        $key = (string) Str::uuid();
        $pending[$fingerprint] = [
            'key' => $key,
            'operation' => $operation,
            'resource' => $resource,
            'created_at' => now()->getTimestamp(),
        ];
        session()->put(self::PENDING_MUTATIONS_SESSION_KEY, $pending);
        session()->save();

        return $key;
    }

    private function forgetPendingMutation(string $fingerprint): void
    {
        $pending = session()->get(self::PENDING_MUTATIONS_SESSION_KEY, []);
        if (! is_array($pending) || ! array_key_exists($fingerprint, $pending)) {
            return;
        }

        unset($pending[$fingerprint]);
        if ($pending === []) {
            session()->forget(self::PENDING_MUTATIONS_SESSION_KEY);
            session()->save();

            return;
        }

        session()->put(self::PENDING_MUTATIONS_SESSION_KEY, $pending);
        session()->save();
    }

    private function isUncertainMutationFailure(PlatformApiException $exception): bool
    {
        // Preserve every conflict key. Platform uses typed 409 conflicts for
        // in-flight idempotency and a message is not a stable contract.
        return $exception->status >= 500 || ($exception->status === 409 && $exception->errorCode !== 'BOOKING_CONFIGURATION_CHANGED');
    }

    /** @return array<string, string> */
    private function mutationValidationFields(string $operation, string $resource): array
    {
        return match ($operation) {
            'business_profile.update' => [
                'name' => 'businessName', 'legal_name' => 'businessLegalName',
                'registration_number' => 'businessRegistrationNumber', 'registration_number_type' => 'businessRegistrationNumberType',
                'tax_identifier' => 'businessTaxIdentifier', 'tax_identifier_type' => 'businessTaxIdentifierType',
                'registered_country_code' => 'businessRegisteredCountryCode', 'primary_contact_name' => 'businessPrimaryContactName',
                'primary_contact_email' => 'businessPrimaryContactEmail', 'primary_contact_phone' => 'businessPrimaryContactPhone',
                'website' => 'businessWebsite',
            ],
            'providers.update' => [
                'provider.name' => 'providerName', 'provider.description' => 'providerDescription',
                'provider.phone' => 'providerPhone', 'provider.email' => 'providerEmail', 'provider.website' => 'providerWebsite',
                'place.name' => 'placeName', 'place.phone' => 'placePhone', 'place.email' => 'placeEmail', 'place.website' => 'placeWebsite',
                'place.address_line_1' => 'placeAddressLine1', 'place.address_line_2' => 'placeAddressLine2',
                'place.city' => 'placeCity', 'place.state' => 'placeState', 'place.postal_code' => 'placePostalCode', 'place.timezone' => 'placeTimezone',
            ],
            'provider_claims.create' => [
                'service_provider_id' => 'claimServiceProviderId', 'place_id' => 'claimPlaceId',
                'requested_authority_type' => 'claimAuthorityType', 'verification_method' => 'claimVerificationMethod',
                'claimant_email' => 'claimantEmail', 'claimant_phone' => 'claimantPhone',
            ],
            'offerings.create' => ['provider_link_id' => 'offeringProviderLinkId', 'name' => 'offeringName', 'default_duration_minutes' => 'offeringDurationMinutes'],
            'offerings.update' => [
                'name' => 'editingOfferingName', 'description' => 'editingOfferingDescription',
                'default_duration_minutes' => 'editingOfferingDurationMinutes', 'status' => 'editingOfferingStatus', 'request_mode' => 'editingOfferingRequestMode',
            ],
            'booking_profiles.update' => [
                'provider_link_id' => 'bookingProviderLinkId', 'status' => 'bookingStatus', 'timezone' => 'bookingTimezone',
                'notification_email' => 'bookingNotificationEmail', 'notification_phone' => 'bookingNotificationPhone',
                'minimum_notice_hours' => 'bookingMinimumNoticeHours', 'maximum_advance_days' => 'bookingMaximumAdvanceDays',
                'response_window_hours' => 'bookingResponseWindowHours',
                'customer_instructions' => 'bookingCustomerInstructions',
                'availability_rules' => 'bookingAvailabilityRules', 'availability_exceptions' => 'bookingAvailabilityExceptions',
            ],
            'team.invitations.create' => ['email' => 'teamEmail', 'role' => 'teamRole'],
            'team.memberships.update' => ['role' => 'editingTeamRole'],
            'bookings.respond' => ['reason' => 'bookingDeclineReason', 'proposed_windows.0.starts_at' => 'bookingProposedTime'],
            default => [],
        };
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'organizationSearch' => 'organisation search', 'providerSearch' => 'location search',
            'businessName' => 'business name', 'businessLegalName' => 'legal name',
            'businessRegistrationNumber' => 'registration number', 'businessRegistrationNumberType' => 'registration number type',
            'businessTaxIdentifier' => 'tax identifier', 'businessTaxIdentifierType' => 'tax identifier type',
            'businessRegisteredCountryCode' => 'registered country', 'businessPrimaryContactName' => 'contact name',
            'businessPrimaryContactEmail' => 'contact email', 'businessPrimaryContactPhone' => 'contact phone', 'businessWebsite' => 'website',
            'providerName' => 'provider name', 'providerDescription' => 'description', 'providerPhone' => 'provider phone',
            'providerEmail' => 'provider email', 'providerWebsite' => 'provider website',
            'placeName' => 'location name', 'placePhone' => 'location phone', 'placeEmail' => 'location email', 'placeWebsite' => 'location website',
            'placeAddressLine1' => 'street address', 'placeAddressLine2' => 'address line 2', 'placeCity' => 'city', 'placeState' => 'state or region',
            'placePostalCode' => 'postcode', 'placeTimezone' => 'timezone',
            'claimSearch' => 'directory search', 'claimAuthorityType' => 'authority', 'claimVerificationMethod' => 'verification method',
            'claimantEmail' => 'verification email', 'claimantPhone' => 'verification phone',
            'offeringName' => 'service name', 'offeringDurationMinutes' => 'service duration', 'offeringProviderLinkId' => 'managed location',
            'editingOfferingName' => 'service name', 'editingOfferingDescription' => 'description',
            'editingOfferingDurationMinutes' => 'service duration', 'editingOfferingStatus' => 'service status', 'editingOfferingRequestMode' => 'request mode',
            'bookingProviderLinkId' => 'managed location', 'bookingStatus' => 'booking status', 'bookingTimezone' => 'timezone',
            'bookingNotificationEmail' => 'notification email', 'bookingNotificationPhone' => 'notification phone',
            'bookingMinimumNoticeHours' => 'minimum notice', 'bookingMaximumAdvanceDays' => 'advance booking window',
            'bookingResponseWindowHours' => 'response window', 'bookingCustomerInstructions' => 'customer instructions',
            'bookingAvailabilityRules' => 'weekly availability', 'bookingAvailabilityRules.*.day_of_week' => 'day',
            'bookingAvailabilityRules.*.starts_at' => 'opening time', 'bookingAvailabilityRules.*.ends_at' => 'closing time',
            'bookingAvailabilityRules.*.effective_from' => 'start date', 'bookingAvailabilityRules.*.effective_until' => 'end date',
            'bookingAvailabilityExceptions' => 'date exceptions', 'bookingAvailabilityExceptions.*.date' => 'exception date',
            'bookingAvailabilityExceptions.*.starts_at' => 'opening time', 'bookingAvailabilityExceptions.*.ends_at' => 'closing time',
            'bookingAvailabilityExceptions.*.availability' => 'availability', 'bookingAvailabilityExceptions.*.reason' => 'exception reason',
            'teamEmail' => 'email address', 'teamRole' => 'role', 'editingTeamRole' => 'role', 'bookingDeclineReason' => 'decline reason',
        ];
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
            $this->state = $exception->status === 403
                || ($exception->status === 404 && $exception->errorCode === 'feature_unavailable')
                ? 'forbidden'
                : 'unavailable';
        }
        $this->message = $exception->getMessage();
    }

    private function requiredOrganizationId(): string
    {
        abort_unless(is_string($this->organizationId) && $this->organizationId !== '', 403);

        return $this->organizationId;
    }
}
