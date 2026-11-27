<div class="business-workspace-root">
@if ($state === 'ready')
    @php
        $overlayOpen = match ($section) {
            'listings' => $editingProviderLinkId !== '' || $claimServiceProviderId !== '',
            'services' => $editingOfferingId !== '',
            'team' => $editingTeamMemberId !== '',
            default => false,
        };
    @endphp
    <x-business.shell :section="$section" :navigation-sections="$navigationSections" :organizations="$organizations" :organization-id="$organizationId" :organization-search="$organizationSearch" :identity="$identity" :message="$message" :pagination="$pagination" :overlay-open="$overlayOpen">
        @if ($section === 'overview')
                <section class="workspace-section">
                    <x-business.section-heading title="Activity summary" />
                    <div class="metric-grid {{ count($navigationSections) <= 2 ? 'metric-grid-compact' : '' }}">
                        @if ($this->hasCapability('providers.manage'))
                            <article class="metric-card">
                                <span>Managed locations</span>
                                <strong>{{ data_get($pagination, 'providers.total', count($providers)) }}</strong>
                                <small>{{ data_get($identity, 'managed_provider_count', 0) }} approved for management</small>
                            </article>
                        @endif
                        @if ($this->hasCapability('bookings.manage') && $this->featureIsAvailable('booking_requests'))
                            <article class="metric-card">
                                <span>Open bookings</span>
                                <strong>{{ data_get($identity, 'booking_summary.open', 0) }}</strong>
                                <small>{{ data_get($identity, 'booking_summary.requested', 0) }} waiting for a response</small>
                            </article>
                        @endif
                        @if ($this->hasCapability('programs.view'))
                            <article class="metric-card">
                                <span>Programs</span>
                                <strong>{{ data_get($identity, 'program_summary.approved', 0) }}</strong>
                                <small>{{ data_get($identity, 'program_summary.pending_review', 0) + data_get($identity, 'program_summary.under_review', 0) }} under review</small>
                            </article>
                        @endif
                        @if (! $this->hasCapability('providers.manage') && ! $this->hasCapability('bookings.manage') && ! $this->hasCapability('programs.view'))
                            <article class="metric-card metric-card-welcome">
                                <span>Your access</span>
                                <strong>{{ str(data_get($identity, 'membership.role', 'viewer'))->headline() }}</strong>
                                <small>Your workspace only shows the information assigned to this role.</small>
                            </article>
                        @endif
                    </div>

                    @if ($this->hasCapability('bookings.manage') && $this->featureIsAvailable('booking_requests'))
                        <x-business.section-heading class="section-heading-spaced" title="Bookings to review">
                            <x-slot:actions><x-business.button variant="text" :href="route('dashboard', ['section' => 'bookings', 'status' => 'requested'])" wire:navigate>View requests</x-business.button></x-slot:actions>
                        </x-business.section-heading>
                        <div class="action-list">
                            @forelse (collect($bookings)->where('status', 'requested')->take(5) as $row)
                                <x-business.button variant="action" :href="route('bookings.show', ['booking' => $row['id'], 'status' => 'requested'])" wire:navigate>
                                    <span>
                                        <strong>{{ $row['request_number'] }}</strong>
                                        <small>{{ data_get($row, 'pet.name', 'Pet') }}
                                            @if (is_string($row['response_due_at'] ?? null))
                                                · Respond by <time datetime="{{ $row['response_due_at'] }}">{{ $this->displayDateTime($row['response_due_at'], true) }}</time>
                                            @endif
                                        </small>
                                    </span>
                                    <x-business.status tone="warn">Respond</x-business.status>
                                </x-business.button>
                            @empty
                                <div class="empty-row">No booking requests need a response.</div>
                            @endforelse
                        </div>
                    @endif
                </section>
            @elseif ($section === 'profile')
                <section class="workspace-section">
                    <x-business.section-heading
                        title="Registration details"
                    >
                        <x-slot:actions><x-business.status :tone="data_get($identity, 'business_profile.verified_at') ? 'good' : 'warn'">{{ data_get($identity, 'business_profile.verified_at') ? 'Verified' : 'Verification pending' }}</x-business.status></x-slot:actions>
                    </x-business.section-heading>

                    <form class="form-panel" wire:submit="saveBusinessProfile">
                        <div class="form-grid form-grid-two">
                            <x-business.field class="form-span-two" label="Business name" name="businessName"><x-business.control type="text" wire:model="businessName" autocomplete="organization" /></x-business.field>
                            <x-business.field label="Legal name" name="businessLegalName"><x-business.control type="text" wire:model="businessLegalName" /></x-business.field>
                            <x-business.field label="Registered country" name="businessRegisteredCountryCode">
                                <x-business.control as="select" wire:model="businessRegisteredCountryCode" autocomplete="country">
                                    <option value="">Select country</option>
                                    @foreach (data_get($identity, 'profile_options.countries', []) as $country)
                                        <option value="{{ $country['code'] }}">{{ $country['name'] }}</option>
                                    @endforeach
                                </x-business.control>
                            </x-business.field>
                            <x-business.field label="Registration number" name="businessRegistrationNumber"><x-business.control type="text" wire:model="businessRegistrationNumber" /></x-business.field>
                            <x-business.field label="Registration number type" name="businessRegistrationNumberType"><x-business.control type="text" wire:model="businessRegistrationNumberType" placeholder="ABN, company number" /></x-business.field>
                            <x-business.field label="Tax identifier" name="businessTaxIdentifier"><x-business.control type="text" wire:model="businessTaxIdentifier" /></x-business.field>
                            <x-business.field label="Tax identifier type" name="businessTaxIdentifierType"><x-business.control type="text" wire:model="businessTaxIdentifierType" placeholder="GST, VAT" /></x-business.field>
                        </div>

                        <div class="form-divider"></div>
                        <p class="eyebrow">Primary contact</p>
                        <div class="form-grid form-grid-two">
                            <x-business.field label="Name" name="businessPrimaryContactName"><x-business.control type="text" wire:model="businessPrimaryContactName" autocomplete="name" /></x-business.field>
                            <x-business.field label="Email" name="businessPrimaryContactEmail"><x-business.control type="email" wire:model="businessPrimaryContactEmail" autocomplete="email" /></x-business.field>
                            <x-business.field label="Phone" name="businessPrimaryContactPhone"><x-business.control type="tel" wire:model="businessPrimaryContactPhone" autocomplete="tel" /></x-business.field>
                            <x-business.field label="Website" name="businessWebsite"><x-business.control type="url" wire:model="businessWebsite" placeholder="https://" /></x-business.field>
                        </div>
                        <div class="form-actions"><x-business.button variant="primary" type="submit">Save profile</x-business.button></div>
                    </form>
                </section>
            @elseif ($section === 'listings')
                <section class="workspace-section">
                    <x-business.section-heading
                        title="Managed locations"
                    />
                    <div class="listing-grid">
                        @forelse ($providers as $link)
                            <article class="listing-card">
                                <div class="listing-card-top">
                                    <x-business.status :tone="$link['status'] === 'active' ? 'good' : 'neutral'">{{ str($link['status'])->headline() }}</x-business.status>
                                    <span>{{ str($link['authority_type'])->headline() }}</span>
                                </div>
                                <h3>{{ data_get($link, 'place.name') ?: data_get($link, 'provider.name') }}</h3>
                                <p>{{ data_get($link, 'place.address', 'All verified locations') }}</p>
                                <div class="listing-card-footer">
                                    <small>{{ data_get($link, 'provider.template.label') ?: collect(data_get($link, 'provider.categories', []))->implode(' · ') }}</small>
                                    <x-business.button variant="text" aria-label="Edit details for {{ data_get($link, 'place.name') ?: data_get($link, 'provider.name') }}" wire:click="startProviderEdit({{ Illuminate\Support\Js::from($link['id']) }})">Edit details</x-business.button>
                                </div>
                            </article>
                        @empty
                            <x-business.empty-state title="No managed locations yet" description="Claim a public provider listing to manage its details, services and booking requests." />
                        @endforelse
                    </div>

                    @php($editingProvider = collect($providers)->firstWhere('id', $editingProviderLinkId))
                    <x-business.overlay kind="drawer"
                        id="provider-edit-drawer"
                        :open="$editingProviderLinkId !== ''"
                        :message="$message"
                        :title="data_get($editingProvider, 'place.name') ?: data_get($editingProvider, 'provider.name', 'Edit location')"
                        eyebrow="Location details"
                        close-action="cancelProviderEdit"
                    >
                        <x-slot:body>
                        <form id="provider-edit-form" wire:submit="saveProvider">
                            <div class="form-grid form-grid-two">
                                <x-business.field label="Provider name" name="providerName"><x-business.control type="text" wire:model="providerName" data-overlay-initial-focus /></x-business.field>
                                <x-business.field label="Location name" name="placeName"><x-business.control type="text" wire:model="placeName" @disabled(! data_get($editingProvider, 'place.id')) /></x-business.field>
                                <x-business.field class="form-span-two" label="Description" name="providerDescription"><x-business.control as="textarea" wire:model="providerDescription" rows="4"></x-business.control></x-business.field>
                                <x-business.field label="Provider phone" name="providerPhone"><x-business.control type="tel" wire:model="providerPhone" /></x-business.field>
                                <x-business.field label="Provider email" name="providerEmail"><x-business.control type="email" wire:model="providerEmail" /></x-business.field>
                                <x-business.field label="Provider website" name="providerWebsite"><x-business.control type="url" wire:model="providerWebsite" placeholder="https://" /></x-business.field>
                                <x-business.field label="Location phone" name="placePhone"><x-business.control type="tel" wire:model="placePhone" @disabled(! data_get($editingProvider, 'place.id')) /></x-business.field>
                            </div>
                            @if (data_get($editingProvider, 'place.id'))
                                <div class="form-divider"></div>
                                <p class="eyebrow">Location details</p>
                                <div class="form-grid form-grid-two">
                                    <x-business.field label="Location email" name="placeEmail"><x-business.control type="email" wire:model="placeEmail" /></x-business.field>
                                    <x-business.field label="Location website" name="placeWebsite"><x-business.control type="url" wire:model="placeWebsite" placeholder="https://" /></x-business.field>
                                    <x-business.field label="Street address" name="placeAddressLine1"><x-business.control type="text" wire:model="placeAddressLine1" /></x-business.field>
                                    <x-business.field label="Address line 2" name="placeAddressLine2"><x-business.control type="text" wire:model="placeAddressLine2" /></x-business.field>
                                    <x-business.field label="City or suburb" name="placeCity"><x-business.control type="text" wire:model="placeCity" /></x-business.field>
                                    <x-business.field label="State or region" name="placeState"><x-business.control type="text" wire:model="placeState" /></x-business.field>
                                    <x-business.field label="Postcode" name="placePostalCode"><x-business.control type="text" wire:model="placePostalCode" /></x-business.field>
                                    <x-business.field label="Timezone" name="placeTimezone"><x-business.control type="text" wire:model="placeTimezone" placeholder="Australia/Brisbane" /></x-business.field>
                                </div>
                            @endif
                        </form>
                        </x-slot:body>
                        <x-slot:actions>
                            <x-business.button wire:click="cancelProviderEdit">Cancel</x-business.button>
                            <x-business.button variant="primary" type="submit" form="provider-edit-form">Save details</x-business.button>
                        </x-slot:actions>
                    </x-business.overlay>
                    <x-business-pagination resource="providers" label="locations" :pagination="$pagination" />

                    @if ($this->featureIsAvailable('provider_claims'))
                        <x-business.section-heading
                            class="section-heading-spaced"
                            eyebrow="Add a location"
                            title="Find your public listing"
                            description="Search Zigpaw’s local directory. Results are limited to the country registered on this workspace."
                        />
                        <form class="inline-form claim-search-form" wire:submit="searchClaimableProviders">
                            <x-business.field class="form-grow" label="Business or location name" name="claimSearch"><x-business.control type="search" wire:model="claimSearch" placeholder="Laidley Veterinary Surgery" autocomplete="off" /></x-business.field>
                            <x-business.button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="searchClaimableProviders">Search directory</x-business.button>
                        </form>

                        @if ($claimableProviders !== [])
                            <div class="claim-result-list" aria-label="Claimable provider listings">
                                @foreach ($claimableProviders as $candidate)
                                    @php($isSelectedClaim = $claimServiceProviderId === (string) $candidate['service_provider_id'] && $claimPlaceId === (string) $candidate['place_id'])
                                    <x-business.button variant="claim" class="{{ $isSelectedClaim ? 'claim-result-selected' : '' }}" wire:click="selectClaimableProvider({{ Illuminate\Support\Js::from($candidate['service_provider_id']) }}, {{ Illuminate\Support\Js::from($candidate['place_id']) }})" aria-pressed="{{ $isSelectedClaim ? 'true' : 'false' }}">
                                        <span><strong>{{ $candidate['branch_name'] ?: $candidate['provider_name'] }}</strong><small>{{ $candidate['provider_name'] !== $candidate['branch_name'] ? $candidate['provider_name'].' · ' : '' }}{{ $candidate['address'] }}</small></span>
                                        <span class="claim-result-action">{{ $isSelectedClaim ? 'Selected' : 'Choose' }}</span>
                                    </x-business.button>
                                @endforeach
                            </div>
                            <x-business-pagination resource="claimableProviders" label="directory results" :pagination="$pagination" />
                        @elseif ($claimSearch !== '' && isset($pagination['claimableProviders']))
                            <p class="section-note">No claimable listings matched that search. Try the business name, suburb or postcode.</p>
                        @endif

                        <x-business.overlay
                            id="listing-claim-modal"
                            :open="$claimServiceProviderId !== ''"
                            :message="$message"
                            title="Confirm listing claim"
                            eyebrow="Managed locations"
                            close-action="cancelProviderClaim"
                        >
                            <x-slot:body>
                            <form id="listing-claim-form" class="claim-form" wire:submit="submitProviderClaim">
                                <div class="overlay-context">
                                    <div><span class="data-label">Selected listing</span><strong>{{ $claimBranchName ?: $claimProviderName }}</strong><small>{{ $claimAddress }}</small></div>
                                    <x-business.button variant="text" wire:click="cancelProviderClaim">Choose another</x-business.button>
                                </div>
                                <div class="form-grid form-grid-two">
                                    <x-business.field label="Your authority" name="claimAuthorityType"><x-business.control as="select" wire:model="claimAuthorityType" data-overlay-initial-focus><option value="">Choose your role</option><option value="owner">Business owner</option><option value="manager">Business manager</option><option value="location_manager">Location manager</option></x-business.control></x-business.field>
                                    <x-business.field label="How should we verify you?" name="claimVerificationMethod"><x-business.control as="select" wire:model.live="claimVerificationMethod"><option value="">Choose a method</option><option value="domain_email">Business email</option><option value="phone">Business phone</option></x-business.control></x-business.field>
                                    @if ($claimVerificationMethod === 'domain_email')
                                        <x-business.field class="form-span-two" label="Business email" name="claimantEmail"><x-business.control type="email" wire:model="claimantEmail" autocomplete="email" placeholder="you@business.example" /></x-business.field>
                                    @elseif ($claimVerificationMethod === 'phone')
                                        <x-business.field class="form-span-two" label="Business phone" name="claimantPhone"><x-business.control type="tel" wire:model="claimantPhone" autocomplete="tel" /></x-business.field>
                                    @endif
                                </div>
                            </form>
                            </x-slot:body>
                            <x-slot:footer>Submitting does not change the public listing until Zigpaw verifies your authority.</x-slot:footer>
                            <x-slot:actions>
                                <x-business.button wire:click="cancelProviderClaim">Cancel</x-business.button>
                                <x-business.button variant="primary" type="submit" form="listing-claim-form">Submit claim</x-business.button>
                            </x-slot:actions>
                        </x-business.overlay>
                    @endif

                    @if ($this->featureIsAvailable('provider_claims') && $providerClaims !== [])
                        <x-business.section-heading class="section-heading-spaced" eyebrow="Claims" title="Verification progress" />
                        <x-business.data-list label="Listing claim status">
                            @foreach ($providerClaims as $claim)
                                <x-business.data-row :title="data_get($claim, 'place.name') ?: data_get($claim, 'provider.name')" :description="data_get($claim, 'place.address')">
                                    <x-business.status>{{ str($claim['status'])->headline() }}</x-business.status>
                                </x-business.data-row>
                            @endforeach
                        </x-business.data-list>
                        <x-business-pagination resource="providerClaims" label="claims" :pagination="$pagination" />
                    @elseif (! $this->featureIsAvailable('provider_claims'))
                        <p class="section-note">Listing claims are not available for this workspace yet. Existing approved locations remain available above.</p>
                    @endif
                </section>
            @elseif ($section === 'bookings')
                <section class="workspace-section" @if ($bookingId !== null) data-verified-booking-detail @endif>
                    @if ($bookingId !== null)
                        <x-business.button variant="text" :href="route('dashboard', ['section' => 'bookings', 'status' => $this->bookingFilter(), 'booking_page' => $bookingPage > 1 ? $bookingPage : null]).'#booking-'.$bookingId" wire:navigate>Back to booking requests</x-business.button>
                        @if ($bookingError)
                            <x-business.error-state title="Booking details unavailable" :message="$bookingError">
                                <x-slot:actions><x-business.button wire:click="$refresh">Try again</x-business.button></x-slot:actions>
                            </x-business.error-state>
                        @elseif ($booking)
                            @php($actions = $booking['available_actions'])
                            @php($appointmentTimezone = is_string($booking['appointment_timezone'] ?? null) ? $booking['appointment_timezone'] : 'UTC')
                            @php($appointmentLocation = collect([data_get($booking, 'provider.location_name'), data_get($booking, 'provider.address'), data_get($booking, 'provider.city'), data_get($booking, 'provider.state'), data_get($booking, 'provider.postal_code')])->filter(fn ($value) => is_string($value) && filled($value))->unique()->join(', '))
                            @php($responseWindowClosed = in_array($booking['status'], ['requested', 'proposed'], true) && $actions === [])
                            @php($windows = collect($booking['requested_windows'] ?? [])->where('source', 'customer')->where('status', 'requested'))
                            <x-business.section-heading class="section-heading-spaced" :title="$booking['request_number']" :description="data_get($booking, 'pet.name', 'Pet').' · '.data_get($booking, 'provider.service_name', data_get($booking, 'provider.name', 'Service request'))">
                                <x-slot:actions><x-business.status :tone="$booking['status'] === 'requested' && ! $responseWindowClosed ? 'warn' : ($booking['status'] === 'accepted' ? 'good' : 'neutral')">{{ str($booking['status'])->headline() }}</x-business.status></x-slot:actions>
                            </x-business.section-heading>
                            @if ($responseWindowClosed)
                                <p class="section-note">This request’s response window has closed.</p>
                            @elseif (is_string($booking['response_due_at'] ?? null) && $booking['status'] === 'requested')
                                <p class="section-note">Respond by <time datetime="{{ $booking['response_due_at'] }}">{{ $this->displayDateTime($booking['response_due_at'], true) }}</time></p>
                            @endif
                            @if ($appointmentLocation !== '')
                                <p class="section-note">Appointment location · {{ $appointmentLocation }}</p>
                            @endif
                            @if (is_string($booking['confirmed_starts_at'] ?? null))
                                <p class="section-note">Confirmed for <time datetime="{{ $booking['confirmed_starts_at'] }}">{{ $this->displayDateTime($booking['confirmed_starts_at'], true, $appointmentTimezone) }}</time></p>
                            @endif
                            @if ($booking['customer_message'] ?? null)
                                <p class="booking-note">{{ $booking['customer_message'] }}</p>
                            @endif
                            <section aria-label="Booking conversation" data-booking-conversation>
                                @if ($bookingMessagesError)
                                    <x-business.error-state title="Booking conversation unavailable" :message="$bookingMessagesError">
                                        <x-slot:actions><x-business.button wire:click="$refresh">Retry conversation</x-business.button></x-slot:actions>
                                    </x-business.error-state>
                                @elseif ($bookingMessages['data'] ?? [])
                                    <h3>Booking conversation</h3>
                                    <ol class="schedule-list">
                                        @foreach ($bookingMessages['data'] as $entry)
                                            <li wire:key="booking-message-{{ $entry['id'] }}">
                                                <strong>{{ match ($entry['sender_type']) { 'customer' => 'Customer', 'organization', 'external_provider', 'provider' => 'Provider', default => 'Zigpaw' } }}</strong>
                                                <time datetime="{{ $entry['created_at'] }}">{{ $this->displayDateTime($entry['created_at'], true, $appointmentTimezone) }}</time>
                                                <p class="booking-note">{{ $entry['message'] }}</p>
                                            </li>
                                        @endforeach
                                    </ol>
                                    @if ($bookingMessages['meta']['last_page'] > 1)
                                        <nav aria-label="Conversation pages">
                                            @if ($bookingMessagePage > 1)
                                                <x-business.button wire:click="showBookingMessagePage({{ $bookingMessagePage - 1 }})">Newer messages</x-business.button>
                                            @endif
                                            <span>Page {{ $bookingMessagePage }} of {{ $bookingMessages['meta']['last_page'] }}</span>
                                            @if ($bookingMessagePage < $bookingMessages['meta']['last_page'])
                                                <x-business.button wire:click="showBookingMessagePage({{ $bookingMessagePage + 1 }})">Older messages</x-business.button>
                                            @endif
                                        </nav>
                                    @endif
                                @endif
                            </section>
                            @if ($this->canShowBookingPetProfile($booking))
                                <div class="booking-pet-access">
                                    <div>
                                        <strong>Pet profile available</strong>
                                        <small>
                                            @if (data_get($booking, 'pet_access.expires_at'))
                                                Access ends <time datetime="{{ $booking['pet_access']['expires_at'] }}">{{ $this->displayDateTime($booking['pet_access']['expires_at'], true) }}</time>.
                                            @else
                                                View-only access can end if the appointment or sharing changes.
                                            @endif
                                        </small>
                                    </div>
                                    <x-business.button variant="text" wire:click="toggleBookingPetProfile({{ Illuminate\Support\Js::from($bookingId) }})" :aria-expanded="$bookingPetProfile ? 'true' : 'false'" aria-controls="booking-pet-profile">{{ $bookingPetProfile ? 'Hide pet profile' : 'View pet profile' }}</x-business.button>
                                </div>
                            @endif
                            <div id="booking-pet-profile">
                                @if ($bookingPetProfile)
                                    <div class="booking-pet-context" aria-label="Pet profile context" data-booking-private-profile wire:poll.visible.15s>
                                        <strong>{{ $bookingPetProfile['name'] ?? 'Pet' }}</strong>
                                        <span>{{ collect([$bookingPetProfile['species'] ?? null, $bookingPetProfile['breed'] ?? null])->filter()->join(' · ') }}</span>
                                    </div>
                                @endif
                            </div>
                            @if ($bookingPetError)
                                <x-business.error-state title="Pet profile unavailable" :message="$bookingPetError" />
                            @endif
                            @if ($actions !== [])
                                <x-business.field label="Message to customer (optional)" name="bookingReply">
                                    <x-business.control as="textarea" wire:model="bookingReply" rows="2" maxlength="4000" aria-describedby="booking-reply-help" />
                                </x-business.field>
                                <small id="booking-reply-help" class="section-note">Sent with your booking decision and saved in the conversation.</small>
                            @endif
                            @if (in_array('accept', $actions, true) && $windows->isNotEmpty())
                                <x-business.section-heading class="section-heading-spaced" title="Requested appointment times" />
                                <div class="window-list">
                                    @foreach ($windows as $window)
                                        <x-business.button variant="window" wire:click="acceptBooking({{ Illuminate\Support\Js::from($bookingId) }}, {{ Illuminate\Support\Js::from($window['id']) }})" wire:confirm="Accept this appointment time? The customer will see the confirmed time in Zigpaw.">
                                            <time datetime="{{ $window['starts_at'] ?? '' }}">{{ $this->displayDateTime($window['starts_at'] ?? null, true, $appointmentTimezone) }}</time>
                                            <small>Accept this time</small>
                                        </x-business.button>
                                    @endforeach
                                </div>
                            @endif
                            @php($proposals = collect($booking['requested_windows'] ?? [])->where('source', 'provider')->where('status', 'proposed'))
                            @if ($proposals->isNotEmpty())
                                <x-business.section-heading class="section-heading-spaced" title="Awaiting customer confirmation" />
                                @foreach ($proposals as $proposal)
                                    <p class="section-note"><time datetime="{{ $proposal['starts_at'] }}">{{ $this->displayDateTime($proposal['starts_at'], true, $appointmentTimezone) }}</time></p>
                                @endforeach
                            @endif
                            @if (in_array('propose', $actions, true))
                                <details class="booking-note" @if ($errors->has('bookingProposedTime')) open @endif>
                                    <summary>Propose another time</summary>
                                    <form wire:submit="proposeBooking({{ Illuminate\Support\Js::from($bookingId) }})" class="booking-actions">
                                        <x-business.field label="Appointment time" name="bookingProposedTime" required>
                                            <x-business.control type="datetime-local" wire:model="bookingProposedTime" aria-describedby="booking-proposed-timezone" required />
                                        </x-business.field>
                                        <small id="booking-proposed-timezone" class="section-note">Appointment timezone · {{ $appointmentTimezone }}</small>
                                        <x-business.button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="proposeBooking">Send proposed time</x-business.button>
                                    </form>
                                </details>
                            @endif
                            <div class="booking-actions">
                                @if (in_array('complete', $actions, true))
                                    <x-business.button variant="primary" wire:click="completeBooking({{ Illuminate\Support\Js::from($bookingId) }})" wire:confirm="Mark this appointment complete? The customer will see the completed status.">Mark complete</x-business.button>
                                @endif
                                @if (in_array('decline', $actions, true))
                                    <x-business.field label="Reason for declining" name="bookingDeclineReason" required><x-business.control type="text" wire:model="bookingDeclineReason" maxlength="1000" required /></x-business.field>
                                    <x-business.button variant="danger" wire:click="declineBooking({{ Illuminate\Support\Js::from($bookingId) }})" wire:confirm="Decline this booking request? The customer will see the declined status.">Decline</x-business.button>
                                @endif
                            </div>
                        @endif
                    @else
                        <x-business.section-heading title="Booking requests" description="Customers pay your business directly." />
                        <nav class="window-list" aria-label="Booking status" data-booking-filters>
                            <x-business.booking-status-links :statuses="\App\Support\PartnerBookingStatus::QUICK_FILTERS" :current="$this->bookingFilter()" />
                            <details @if (isset(\App\Support\PartnerBookingStatus::OTHER_FILTERS[$this->bookingFilter()])) open @endif>
                                <summary>More statuses</summary>
                                <div class="window-list">
                                    <x-business.booking-status-links :statuses="\App\Support\PartnerBookingStatus::OTHER_FILTERS" :current="$this->bookingFilter()" />
                                </div>
                            </details>
                        </nav>
                        <div class="booking-list">
                            @forelse ($bookings as $row)
                                <x-business.button variant="action" id="booking-{{ $row['id'] }}" :href="route('bookings.show', ['booking' => $row['id'], 'status' => $this->bookingFilter(), 'booking_page' => $bookingPage > 1 ? $bookingPage : null])" wire:navigate>
                                    <span>
                                        <strong>{{ $row['request_number'] }}</strong>
                                        <small>{{ data_get($row, 'pet.name', 'Pet') }} · {{ data_get($row, 'provider.service_name', data_get($row, 'provider.name', 'Service request')) }}
                                            @if (is_string($row['response_due_at'] ?? null) && ($row['status'] ?? null) === 'requested')
                                                · Respond by <time datetime="{{ $row['response_due_at'] }}">{{ $this->displayDateTime($row['response_due_at'], true) }}</time>
                                            @endif
                                        </small>
                                    </span>
                                    <x-business.status :tone="$row['status'] === 'requested' ? 'warn' : ($row['status'] === 'accepted' ? 'good' : 'neutral')">{{ str($row['status'])->headline() }}</x-business.status>
                                </x-business.button>
                            @empty
                                <x-business.empty-state :title="$this->bookingFilter() === 'requested' ? 'No requests need a response' : ($this->bookingFilter() ? 'No '.$this->bookingFilter().' bookings' : 'No booking requests yet')" :description="$this->bookingFilter() === null ? 'New customer requests will appear here.' : null" />
                            @endforelse
                        </div>
                        <x-business-pagination resource="bookings" label="booking requests" :pagination="$pagination" />
                    @endif
                </section>
            @elseif ($section === 'booking-setup')
                <section class="workspace-section">
                    <x-business.section-heading
                        title="Location settings"
                        description="Choose where to accept requests. Your team can see them here even without email or phone notifications."
                    />

                    <div wire:key="booking-location-picker-{{ $pendingBookingProvider === null ? 'current' : 'confirm' }}">
                        <x-business.workspace-selector label="Locations" resource="providerOptions" :items="$providerOptions" :selected-id="$bookingProviderLinkId" :selected-label="data_get($selectedBookingProvider, 'place.name') ?: data_get($selectedBookingProvider, 'provider.name')" search-property="providerSearch" search-action="searchProviders" select-action="selectBookingProvider" field-name="bookingProviderLinkId" :pagination="$pagination" />
                    </div>
                    @if ($pendingBookingProvider !== null)
                        <x-business.overlay id="booking-location-change" data-overlay-return-id="business-field-bookingProviderLinkId" title="Discard booking changes?" :open="true" close-action="keepBookingDraft">
                            <x-slot:body><p>Switching locations will discard the changes to this location’s booking setup.</p></x-slot:body>
                            <x-slot:actions>
                                <x-business.button wire:click="keepBookingDraft" data-overlay-initial-focus>Keep editing</x-business.button>
                                <x-business.button variant="danger" wire:click="discardBookingDraftAndSwitch">Discard and switch</x-business.button>
                            </x-slot:actions>
                        </x-business.overlay>
                    @endif
                    @if ($providerOptions !== [] || $bookingProviderLinkId !== '')
                        @if ($loadedBookingProviderLinkId === null)
                            <x-business.error-state title="Booking settings could not be loaded" message="Choose a location or retry before changing its settings.">
                                <x-slot:actions><x-business.button wire:click="selectBookingProvider({{ Illuminate\Support\Js::from($bookingProviderLinkId) }})">Retry selected location</x-business.button></x-slot:actions>
                            </x-business.error-state>
                        @else
                        <form class="form-panel" wire:submit="saveBookingConfiguration">
                            @if ($bookingConfigurationState === 'unconfigured')<p role="status">This location has no booking setup yet. Choose its settings before saving.</p>@endif
                            <div class="form-grid form-grid-two">
                                <x-business.field label="Booking requests" name="bookingStatus"><x-business.control as="select" wire:model="bookingStatus"><option value="">Choose a status</option><option value="disabled">Off</option><option value="enabled">Accepting requests</option><option value="paused">Paused</option></x-business.control></x-business.field>
                                <x-business.field label="Timezone" name="bookingTimezone"><x-business.control type="text" wire:model="bookingTimezone" placeholder="Australia/Brisbane" /></x-business.field>
                                <x-business.field label="Notification email (optional)" name="bookingNotificationEmail"><x-business.control type="email" wire:model="bookingNotificationEmail" /></x-business.field>
                                <x-business.field label="Notification phone (optional)" name="bookingNotificationPhone"><x-business.control type="tel" wire:model="bookingNotificationPhone" /></x-business.field>
                                <x-business.field label="Minimum notice (hours)" name="bookingMinimumNoticeHours"><x-business.control type="number" min="0" max="8760" wire:model="bookingMinimumNoticeHours" /></x-business.field>
                                <x-business.field label="Book up to (days)" name="bookingMaximumAdvanceDays"><x-business.control type="number" min="1" max="730" wire:model="bookingMaximumAdvanceDays" /></x-business.field>
                                <x-business.field label="Response window (hours)" name="bookingResponseWindowHours"><x-business.control type="number" min="1" max="720" wire:model="bookingResponseWindowHours" /></x-business.field>
                                <x-business.field class="form-span-two" label="Customer instructions" name="bookingCustomerInstructions"><x-business.control as="textarea" wire:model="bookingCustomerInstructions" rows="4" placeholder="What should a customer know before sending a request?"></x-business.control></x-business.field>
                            </div>

                            <section id="business-field-bookingAvailabilityRules" class="schedule-editor" aria-labelledby="weekly-availability-heading" tabindex="-1">
                                <div class="schedule-editor-heading">
                                    <div>
                                        <p class="eyebrow">Weekly availability</p>
                                        <h3 id="weekly-availability-heading">When customers can request a booking</h3>
                                        <p>Add the hours customers may request. Disabling every saved window limits requests to dates you explicitly open below.</p>
                                    </div>
                                    <x-business.button wire:click="addBookingAvailabilityRule">Add window</x-business.button>
                                </div>

                                <div class="schedule-list">
                                    @forelse ($bookingAvailabilityRules as $index => $rule)
                                        <fieldset class="schedule-item" wire:key="booking-rule-{{ $index }}">
                                            <legend>Window {{ $index + 1 }}</legend>
                                            <div class="schedule-rule-grid">
                                                <x-business.field label="Day" name="bookingAvailabilityRules.{{ $index }}.day_of_week">
                                                    <x-business.control as="select" wire:model="bookingAvailabilityRules.{{ $index }}.day_of_week">
                                                        <option value="0">Sunday</option>
                                                        <option value="1">Monday</option>
                                                        <option value="2">Tuesday</option>
                                                        <option value="3">Wednesday</option>
                                                        <option value="4">Thursday</option>
                                                        <option value="5">Friday</option>
                                                        <option value="6">Saturday</option>
                                                    </x-business.control>
                                                </x-business.field>
                                                <x-business.field label="Opens" name="bookingAvailabilityRules.{{ $index }}.starts_at"><x-business.control type="time" wire:model="bookingAvailabilityRules.{{ $index }}.starts_at" /></x-business.field>
                                                <x-business.field label="Closes" name="bookingAvailabilityRules.{{ $index }}.ends_at"><x-business.control type="time" wire:model="bookingAvailabilityRules.{{ $index }}.ends_at" /></x-business.field>
                                                <label class="schedule-toggle">
                                                    <input type="checkbox" wire:model="bookingAvailabilityRules.{{ $index }}.is_active">
                                                    <span>Active</span>
                                                </label>
                                                <x-business.button class="schedule-remove" variant="danger" wire:click="removeBookingAvailabilityRule({{ $index }})" aria-label="Remove availability window {{ $index + 1 }}">Remove</x-business.button>
                                            </div>
                                            <details class="schedule-effective-dates">
                                                <summary>Limit to a date range</summary>
                                                <div class="schedule-date-grid">
                                                    <x-business.field label="Starts on" name="bookingAvailabilityRules.{{ $index }}.effective_from"><x-business.control type="date" wire:model="bookingAvailabilityRules.{{ $index }}.effective_from" /></x-business.field>
                                                    <x-business.field label="Ends on" name="bookingAvailabilityRules.{{ $index }}.effective_until"><x-business.control type="date" wire:model="bookingAvailabilityRules.{{ $index }}.effective_until" /></x-business.field>
                                                </div>
                                            </details>
                                        </fieldset>
                                    @empty
                                        <p class="schedule-empty">No weekly hours are set. With booking requests enabled, customers may suggest any time within your notice limits, except dates you close. Pause requests to stop accepting them.</p>
                                    @endforelse
                                </div>
                            </section>

                            <section id="business-field-bookingAvailabilityExceptions" class="schedule-editor" aria-labelledby="availability-exceptions-heading" tabindex="-1">
                                <div class="schedule-editor-heading">
                                    <div>
                                        <p class="eyebrow">Date exceptions</p>
                                        <h3 id="availability-exceptions-heading">Closures and one-off availability</h3>
                                        <p>Override the weekly schedule for holidays, closures or special opening times.</p>
                                    </div>
                                    <x-business.button wire:click="addBookingAvailabilityException">Add exception</x-business.button>
                                </div>

                                <div class="schedule-list">
                                    @forelse ($bookingAvailabilityExceptions as $index => $exception)
                                        <fieldset class="schedule-item" wire:key="booking-exception-{{ $index }}">
                                            <legend>Exception {{ $index + 1 }}</legend>
                                            <div class="schedule-exception-grid">
                                                <x-business.field label="Date" name="bookingAvailabilityExceptions.{{ $index }}.date"><x-business.control type="date" wire:model="bookingAvailabilityExceptions.{{ $index }}.date" /></x-business.field>
                                                <x-business.field label="Availability" name="bookingAvailabilityExceptions.{{ $index }}.availability">
                                                    <x-business.control as="select" wire:model="bookingAvailabilityExceptions.{{ $index }}.availability"><option value="unavailable">Closed</option><option value="available">Open</option></x-business.control>
                                                </x-business.field>
                                                <x-business.field label="Opens (optional)" name="bookingAvailabilityExceptions.{{ $index }}.starts_at"><x-business.control type="time" wire:model="bookingAvailabilityExceptions.{{ $index }}.starts_at" /></x-business.field>
                                                <x-business.field label="Closes (optional)" name="bookingAvailabilityExceptions.{{ $index }}.ends_at"><x-business.control type="time" wire:model="bookingAvailabilityExceptions.{{ $index }}.ends_at" /></x-business.field>
                                                <x-business.field class="schedule-reason" label="Reason (optional)" name="bookingAvailabilityExceptions.{{ $index }}.reason"><x-business.control type="text" wire:model="bookingAvailabilityExceptions.{{ $index }}.reason" placeholder="Public holiday" /></x-business.field>
                                                <x-business.button class="schedule-remove" variant="danger" wire:click="removeBookingAvailabilityException({{ $index }})" aria-label="Remove availability exception {{ $index + 1 }}">Remove</x-business.button>
                                            </div>
                                        </fieldset>
                                    @empty
                                        <p class="schedule-empty">No date exceptions have been added.</p>
                                    @endforelse
                                </div>
                            </section>
                            <div class="form-actions">
                                @if ($bookingConfigurationState === 'conflict')
                                    <p role="status">Another update changed these settings. Your draft is still shown.</p>
                                    <x-business.button wire:click="selectBookingProvider({{ Illuminate\Support\Js::from($bookingProviderLinkId) }})" wire:confirm="Discard this draft and load the latest booking settings?">Reload latest settings</x-business.button>
                                @else
                                    <x-business.button variant="primary" type="submit">Save booking setup</x-business.button>
                                @endif
                            </div>
                        </form>
                        @endif
                    @else
                        <x-business.empty-state title="No managed locations yet" description="A verified location is required before booking requests can be configured." />
                    @endif
                </section>
            @elseif ($section === 'services')
                <section class="workspace-section">
                    <x-business.section-heading
                        title="Manage services"
                        description="Add services customers can request from your listings."
                    />
                    <x-business.workspace-selector label="Locations" resource="providerOptions" :items="$providerOptions" :selected-id="$offeringProviderLinkId" :selected-label="data_get($selectedOfferingProvider, 'place.name') ?: data_get($selectedOfferingProvider, 'provider.name')" search-property="providerSearch" search-action="searchProviders" select-action="selectOfferingProvider" field-name="offeringProviderLinkId" :pagination="$pagination" />
                    @if ($providerOptions !== [] || $selectedOfferingProvider !== null)
                        <form class="inline-form" wire:submit="createOffering">
                            <x-business.field class="form-grow" label="Service name" name="offeringName"><x-business.control type="text" wire:model="offeringName" placeholder="Wellness consultation" /></x-business.field>
                            <x-business.field label="Duration (minutes)" name="offeringDurationMinutes"><x-business.control type="number" min="5" max="1440" step="1" wire:model="offeringDurationMinutes" placeholder="30" /></x-business.field>
                            <x-business.button variant="primary" type="submit">Add service</x-business.button>
                        </form>
                    @endif
                    <x-business.data-list label="Services">
                        @forelse ($offerings as $offering)
                            <x-business.data-row
                                :title="$offering['name']"
                                :description="\App\Support\PartnerOfferingScope::label($offering).($offering['default_duration_minutes'] ? ' · '.$offering['default_duration_minutes'].' minutes' : '')"
                            >
                                <x-slot:actions>
                                    <x-business.status :tone="$offering['status'] === 'active' ? 'good' : 'neutral'">{{ $offering['status'] === 'active' ? 'Available' : str($offering['status'])->headline() }}</x-business.status>
                                    <x-business.button aria-label="Edit {{ $offering['name'] }}" variant="text" wire:click="startOfferingEdit({{ Illuminate\Support\Js::from($offering['id']) }})">Edit</x-business.button>
                                </x-slot:actions>
                            </x-business.data-row>
                        @empty
                            <li class="empty-row">No services have been added.</li>
                        @endforelse
                    </x-business.data-list>

                    <x-business.overlay kind="drawer"
                        id="offering-edit-drawer"
                        :open="$editingOfferingId !== ''"
                        :message="$message"
                        :title="$editingOfferingName ?: 'Edit service'"
                        :eyebrow="$editingOfferingScope"
                        close-action="cancelOfferingEdit"
                    >
                        <x-slot:body>
                        <form id="offering-edit-form" wire:submit="saveOffering">
                            <div class="form-grid form-grid-two">
                                <x-business.field label="Service name" name="editingOfferingName"><x-business.control type="text" wire:model="editingOfferingName" data-overlay-initial-focus /></x-business.field>
                                <x-business.field label="Duration (minutes)" name="editingOfferingDurationMinutes"><x-business.control type="number" min="5" max="1440" step="1" wire:model="editingOfferingDurationMinutes" /></x-business.field>
                                <x-business.field label="Status" name="editingOfferingStatus"><x-business.control as="select" wire:model="editingOfferingStatus"><option value="draft">Draft</option><option value="active">Available</option><option value="paused">Paused</option></x-business.control></x-business.field>
                                <x-business.field label="How customers respond" name="editingOfferingRequestMode"><x-business.control as="select" wire:model="editingOfferingRequestMode"><option value="request">Request in Zigpaw</option><option value="contact">Contact the business</option><option value="external">Use an external page</option></x-business.control></x-business.field>
                                <x-business.field class="form-span-two" label="Description" name="editingOfferingDescription"><x-business.control as="textarea" wire:model="editingOfferingDescription" rows="4"></x-business.control></x-business.field>
                            </div>
                        </form>
                        </x-slot:body>
                        <x-slot:footer>
                            <x-business.confirmation action="deleteOffering('{{ $editingOfferingId }}')" message="Remove this service?">Remove service</x-business.confirmation>
                        </x-slot:footer>
                        <x-slot:actions>
                            <x-business.button wire:click="cancelOfferingEdit">Cancel</x-business.button>
                            <x-business.button variant="primary" type="submit" form="offering-edit-form">Save service</x-business.button>
                        </x-slot:actions>
                    </x-business.overlay>
                    <x-business-pagination resource="offerings" label="services" :pagination="$pagination" />
                </section>
            @elseif ($section === 'programs')
                <section class="workspace-section">
                    <x-business.section-heading
                        title="Available programs"
                        description="Joining a program has no effect on business verification or directory ranking."
                    />
                    <div class="program-card">
                        <div><h3>Referral program</h3><p>Approved businesses receive a code to track referrals to Zigpaw.</p></div>
                        @php($referral = collect($programs)->firstWhere('program_key', 'referral'))
                        @if ($referral)
                            <x-business.status :tone="$referral['status'] === 'approved' ? 'good' : 'neutral'">{{ str($referral['status'])->headline() }}</x-business.status>
                            @if ($referral['status'] === 'approved' && filled($referral['code'] ?? null))
                                <div data-referral-code>
                                    <span>Referral code</span> <code>{{ $referral['code'] }}</code>
                                    <x-business.button variant="text" data-copy-referral>Copy code</x-business.button>
                                    <span role="status"></span>
                                </div>
                            @endif
                        @elseif ($this->hasCapability('programs.manage'))
                            <x-business.button variant="primary" wire:click="applyForReferralProgram" wire:confirm="Apply to the referral program? Zigpaw will review the business before approval.">Apply</x-business.button>
                        @endif
                    </div>
                    <x-business-pagination resource="programs" label="programs" :pagination="$pagination" />
                </section>
            @elseif ($section === 'revenue')
                <section class="workspace-section">
                    <x-business.section-heading
                        title="Commission activity and agreements"
                    />
                    <div class="metric-grid">
                        @forelse (data_get($financials, 'currencies', []) as $currency)
                            @foreach ($currency['commissions'] as $group)
                                <article class="metric-card">
                                    <span>{{ $currency['currency'] }} · {{ $group['status_label'] ?? str($group['status'])->headline() }}{{ ($group['payable'] ?? false) ? ' (payable)' : '' }}</span>
                                    <strong>{{ $group['amount_display'] ?? 'Unavailable' }}</strong>
                                    <small>{{ $group['entry_count'] }} {{ str('commission entry')->plural($group['entry_count']) }}</small>
                                </article>
                            @endforeach
                        @empty
                            <article class="metric-card"><span>Commission activity</span><strong>—</strong><small>No commissions have been recorded.</small></article>
                        @endforelse
                    </div>
                    <x-business.section-heading class="section-heading-spaced" title="Recent commissions" />
                    <x-business.data-list label="Recent commissions">
                        @forelse ($commissions as $commission)
                            <x-business.data-row :title="str($commission['event_type'])->headline()" :description="$this->displayDate($commission['created_at'] ?? null)">
                                <div class="amount"><strong>{{ $commission['currency'] }} {{ $commission['amount_display'] ?? 'Unavailable' }}</strong><small>{{ $commission['status_label'] ?? str($commission['status'])->headline() }}</small></div>
                            </x-business.data-row>
                        @empty
                            <li class="empty-row">No commissions have been recorded.</li>
                        @endforelse
                    </x-business.data-list>
                    <x-business-pagination resource="commissions" label="commission entries" :pagination="$pagination" />

                    <x-business.section-heading class="section-heading-spaced" title="Commercial agreements" />
                    <x-business.data-list label="Commercial agreements">
                        @forelse ($agreements as $agreement)
                            <x-business.data-row :title="str($agreement['agreement_type'])->headline()" :description="data_get($agreement, 'provider.name') ?: data_get($agreement, 'place.name') ?: $agreement['reference']">
                                <div class="amount">
                                    <strong>{{ str($agreement['status'])->headline() }}</strong>
                                    <small>{{ $agreement['organic_rank_independent'] ? 'Organic ranking remains independent' : 'Review terms with Zigpaw' }}</small>
                                </div>
                            </x-business.data-row>
                        @empty
                            <li class="empty-row">No commercial agreements are recorded for this business.</li>
                        @endforelse
                    </x-business.data-list>
                    <x-business-pagination resource="agreements" label="agreements" :pagination="$pagination" />
                </section>
            @elseif ($section === 'team')
                <section class="workspace-section">
                    <x-business.section-heading
                        title="People with business access"
                    />
                    @php($assignableRoles = data_get($identity, 'membership.assignable_team_roles', []))
                    @if ($assignableRoles !== [])
                        <form class="inline-form" wire:submit="inviteTeamMember">
                            <x-business.field class="form-grow" label="Email address" name="teamEmail"><x-business.control type="email" wire:model="teamEmail" placeholder="colleague@example.com" /></x-business.field>
                            <x-business.field label="Role" name="teamRole">
                                <x-business.control as="select" wire:model="teamRole">
                                    @foreach ($assignableRoles as $role)
                                        <option value="{{ $role }}">{{ str($role)->headline() }}</option>
                                    @endforeach
                                </x-business.control>
                            </x-business.field>
                            <x-business.button variant="primary" type="submit">Send invitation</x-business.button>
                        </form>
                    @endif
                    <x-business.data-list label="People with business access">
                        @forelse ($team as $member)
                            <x-business.data-row :title="$member['name'] ?: data_get($member, 'email', 'Invited teammate')" :description="$member['name'] ? data_get($member, 'email') : null">
                                <x-slot:actions>
                                    <x-business.status :tone="$member['status'] === 'active' ? 'good' : ($member['status'] === 'invited' ? 'blue' : 'neutral')">{{ str($member['status'])->headline() }}</x-business.status>
                                    <strong class="role-label">{{ str($member['role'])->headline() }}</strong>
                                    @if (data_get($member, 'actions.edit_role') === true)
                                        <x-business.button variant="text" aria-label="Edit role for {{ $member['name'] ?: data_get($member, 'email', 'invited teammate') }}" wire:click="startTeamMemberEdit({{ Illuminate\Support\Js::from($member['id']) }})">Edit</x-business.button>
                                    @endif
                                    @if (data_get($member, 'actions.resend') === true)
                                        <x-business.button variant="text" wire:click="resendTeamInvitation({{ Illuminate\Support\Js::from($member['id']) }})">Resend</x-business.button>
                                    @endif
                                    @if (data_get($member, 'actions.revoke') === true)
                                        <x-business.confirmation action="revokeTeamMember('{{ $member['id'] }}')" message="Remove this person’s business access?">Revoke</x-business.confirmation>
                                    @endif
                                </x-slot:actions>
                            </x-business.data-row>
                        @empty
                            <li><x-business.empty-state title="No team members yet" description="Invite a colleague when they need access to this workspace." /></li>
                        @endforelse
                    </x-business.data-list>

                    @php($editingMember = collect($team)->firstWhere('id', $editingTeamMemberId))
                    <x-business.overlay kind="drawer"
                        id="team-access-drawer"
                        :open="$editingTeamMemberId !== ''"
                        :message="$message"
                        :title="data_get($editingMember, 'name') ?: data_get($editingMember, 'email', 'Update access')"
                        eyebrow="Team access"
                        close-action="cancelTeamMemberEdit"
                    >
                        <x-slot:body>
                            <form id="team-access-form" wire:submit="saveTeamMemberRole">
                                <x-business.field label="Role" name="editingTeamRole">
                                    <x-business.control as="select" wire:model="editingTeamRole" data-overlay-initial-focus>
                                        @foreach ($assignableRoles as $role)
                                            <option value="{{ $role }}">{{ str($role)->headline() }}</option>
                                        @endforeach
                                    </x-business.control>
                                </x-business.field>
                            </form>
                        </x-slot:body>
                        <x-slot:actions>
                            <x-business.button wire:click="cancelTeamMemberEdit">Cancel</x-business.button>
                            <x-business.button variant="primary" type="submit" form="team-access-form">Save role</x-business.button>
                        </x-slot:actions>
                    </x-business.overlay>
                    <x-business-pagination resource="team" label="team members" :pagination="$pagination" />
                </section>
            @endif
    </x-business.shell>
    @else
        <x-business.access-layout :home="route('dashboard')">
                @if ($state === 'choose_organization')
                    <h1>Choose your organisation</h1>
                    <p>Choose where to manage listings, bookings, services and team access.</p>
                    <x-business.workspace-selector label="Organisations" resource="organizations" :items="$organizations" search-property="organizationSearch" search-action="searchOrganizations" select-action="selectOrganization" :pagination="$pagination" :list="true" />
                    @if ($message)<p role="alert">{{ $message }}</p>@endif
                @elseif ($state === 'forbidden')
                    <h1>No active Partners workspace.</h1><p>{{ $message ?: 'Ask the organization owner to invite you, or sign in with another account.' }}</p>
                    <x-business.button :href="rtrim(config('platform.auth_url'), '/').'/partner-workspace-access'" variant="secondary">Request Partner workspace access</x-business.button>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <x-business.button variant="primary" type="submit">Use another account</x-business.button>
                    </form>
                @elseif ($state === 'unavailable')
                    <h1>The Partners workspace is taking a pause.</h1><p>{{ $message ?: 'Try again in a moment.' }}</p><x-business.button variant="primary" :href="$bookingId === null ? route('dashboard') : route('bookings.show', ['booking' => $bookingId, 'status' => $this->bookingFilter()])">Try again</x-business.button>
                @else
                    <h1>Sign in to Partners</h1>
                    <p>{{ $message ?: 'Manage your listings, bookings, services and team in one place.' }}</p>
                    <div class="access-actions">
                        <x-business.button variant="primary" :href="route('auth.login', $bookingId !== null || $section === 'bookings' ? ['booking' => $bookingId, 'booking_queue' => $bookingId === null ? 1 : null, 'booking_status' => $this->bookingFilter(), 'booking_page' => $bookingPage > 1 ? $bookingPage : null] : [])">Sign in</x-business.button>
                        <x-business.button variant="text" :href="route('clinical.dashboard')">Clinical workspace</x-business.button>
                    </div>
                @endif
        </x-business.access-layout>
    @endif

    <x-business.toast-region>
        @if ($notice)
            <x-business.toast dismiss-action="dismissNotice">{{ $notice }}</x-business.toast>
        @endif
    </x-business.toast-region>
</div>
