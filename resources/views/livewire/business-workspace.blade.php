<div class="business-workspace-root">
@if ($state === 'ready')
    <x-business.shell :section="$section" :navigation-sections="$navigationSections" :organizations="$organizations" :organization-id="$organizationId" :identity="$identity" :message="$message">
        @if ($section === 'overview')
                <section class="workspace-section">
                    <x-business.section-heading eyebrow="Today" title="Your business at a glance" />
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
                                <strong>{{ collect($bookings)->whereIn('status', ['requested', 'proposed', 'accepted'])->count() }}</strong>
                                <small>{{ collect($bookings)->where('status', 'requested')->count() }} waiting for a response</small>
                            </article>
                        @endif
                        @if ($this->hasCapability('programs.view'))
                            <article class="metric-card">
                                <span>Programs</span>
                                <strong>{{ collect($programs)->where('status', 'approved')->count() }}</strong>
                                <small>{{ collect($programs)->where('status', 'pending_review')->count() }} under review</small>
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
                        <div class="section-heading section-heading-spaced">
                            <div>
                                <p class="eyebrow">Needs attention</p>
                                <h2>Keep work moving</h2>
                            </div>
                        </div>
                        <div class="action-list">
                            @forelse (collect($bookings)->where('status', 'requested')->take(5) as $booking)
                                <x-business.button variant="action" wire:click="showSection('bookings')">
                                    <span>
                                        <strong>{{ $booking['request_number'] }}</strong>
                                        <small>{{ data_get($booking, 'pet.name', 'Pet') }} needs a response for {{ data_get($booking, 'provider.name', 'your service') }}.</small>
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
                <section class="workspace-section narrow-section">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Business profile</p>
                            <h2>Details your team relies on</h2>
                            <p>Keep the legal identity and operational contact current. Sensitive fields are only returned to people who can manage the organization.</p>
                        </div>
                        <x-business.status :tone="data_get($identity, 'business_profile.verified_at') ? 'good' : 'warn'">{{ data_get($identity, 'business_profile.verified_at') ? 'Verified' : 'Verification pending' }}</x-business.status>
                    </div>

                    <form class="form-panel" wire:submit="saveBusinessProfile">
                        <div class="form-grid form-grid-two">
                            <x-business.field class="form-span-two" label="Business name"><input type="text" wire:model="businessName" autocomplete="organization"></x-business.field>
                            <x-business.field label="Legal name"><input type="text" wire:model="businessLegalName"></x-business.field>
                            <x-business.field label="Registered country"><input type="text" wire:model="businessRegisteredCountryCode" maxlength="2" autocapitalize="characters" placeholder="AU"></x-business.field>
                            <x-business.field label="Registration number"><input type="text" wire:model="businessRegistrationNumber"></x-business.field>
                            <x-business.field label="Registration number type"><input type="text" wire:model="businessRegistrationNumberType" placeholder="ABN, company number"></x-business.field>
                            <x-business.field label="Tax identifier"><input type="text" wire:model="businessTaxIdentifier"></x-business.field>
                            <x-business.field label="Tax identifier type"><input type="text" wire:model="businessTaxIdentifierType" placeholder="GST, VAT"></x-business.field>
                        </div>

                        <div class="form-divider"></div>
                        <p class="eyebrow">Primary contact</p>
                        <div class="form-grid form-grid-two">
                            <x-business.field label="Name"><input type="text" wire:model="businessPrimaryContactName" autocomplete="name"></x-business.field>
                            <x-business.field label="Email"><input type="email" wire:model="businessPrimaryContactEmail" autocomplete="email"></x-business.field>
                            <x-business.field label="Phone"><input type="tel" wire:model="businessPrimaryContactPhone" autocomplete="tel"></x-business.field>
                            <x-business.field label="Website"><input type="url" wire:model="businessWebsite" placeholder="https://"></x-business.field>
                        </div>
                        @error('businessName') <p class="field-error">{{ $message }}</p> @enderror
                        @error('businessPrimaryContactEmail') <p class="field-error">{{ $message }}</p> @enderror
                        @error('businessWebsite') <p class="field-error">{{ $message }}</p> @enderror
                        <div class="form-actions"><x-business.button variant="primary" type="submit">Save profile</x-business.button></div>
                    </form>
                </section>
            @elseif ($section === 'listings')
                <section class="workspace-section">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Managed locations · {{ data_get($pagination, 'providers.total', count($providers)) }}</p>
                            <h2>Business listings</h2>
                            <p>Verified authority lets your team correct public details without changing organic ranking.</p>
                        </div>
                    </div>
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
                                    <x-business.button variant="text" wire:click="startProviderEdit('{{ $link['id'] }}')">Edit details</x-business.button>
                                </div>
                            </article>
                        @empty
                            <x-business.empty-state title="No managed locations yet" description="Claim a public provider listing to manage its details, services and booking requests." />
                        @endforelse
                    </div>

                    @php($editingProvider = collect($providers)->firstWhere('id', $editingProviderLinkId))
                    <x-business.drawer
                        id="provider-edit-drawer"
                        :open="$editingProviderLinkId !== ''"
                        :title="data_get($editingProvider, 'place.name') ?: data_get($editingProvider, 'provider.name', 'Edit location')"
                        eyebrow="Location details"
                        close-action="cancelProviderEdit"
                    >
                        <x-slot:body>
                        <form id="provider-edit-form" wire:submit="saveProvider">
                            <div class="form-grid form-grid-two">
                                <x-business.field label="Provider name"><input type="text" wire:model="providerName" data-overlay-initial-focus></x-business.field>
                                <x-business.field label="Location name"><input type="text" wire:model="placeName" @disabled(! data_get($editingProvider, 'place.id'))></x-business.field>
                                <x-business.field class="form-span-two" label="Description"><textarea wire:model="providerDescription" rows="4"></textarea></x-business.field>
                                <x-business.field label="Provider phone"><input type="tel" wire:model="providerPhone"></x-business.field>
                                <x-business.field label="Provider email"><input type="email" wire:model="providerEmail"></x-business.field>
                                <x-business.field label="Provider website"><input type="url" wire:model="providerWebsite" placeholder="https://"></x-business.field>
                                <x-business.field label="Location phone"><input type="tel" wire:model="placePhone" @disabled(! data_get($editingProvider, 'place.id'))></x-business.field>
                            </div>
                            @if (data_get($editingProvider, 'place.id'))
                                <div class="form-divider"></div>
                                <p class="eyebrow">Location details</p>
                                <div class="form-grid form-grid-two">
                                    <x-business.field label="Location email"><input type="email" wire:model="placeEmail"></x-business.field>
                                    <x-business.field label="Location website"><input type="url" wire:model="placeWebsite" placeholder="https://"></x-business.field>
                                    <x-business.field label="Street address"><input type="text" wire:model="placeAddressLine1"></x-business.field>
                                    <x-business.field label="Address line 2"><input type="text" wire:model="placeAddressLine2"></x-business.field>
                                    <x-business.field label="City or suburb"><input type="text" wire:model="placeCity"></x-business.field>
                                    <x-business.field label="State or region"><input type="text" wire:model="placeState"></x-business.field>
                                    <x-business.field label="Postcode"><input type="text" wire:model="placePostalCode"></x-business.field>
                                    <x-business.field label="Timezone"><input type="text" wire:model="placeTimezone" placeholder="Australia/Brisbane"></x-business.field>
                                </div>
                            @endif
                            @error('providerName') <p class="field-error">{{ $message }}</p> @enderror
                            @error('providerEmail') <p class="field-error">{{ $message }}</p> @enderror
                            @error('providerWebsite') <p class="field-error">{{ $message }}</p> @enderror
                            @error('placeTimezone') <p class="field-error">{{ $message }}</p> @enderror
                        </form>
                        </x-slot:body>
                        <x-slot:actions>
                            <x-business.button wire:click="cancelProviderEdit">Cancel</x-business.button>
                            <x-business.button variant="primary" type="submit" form="provider-edit-form">Save details</x-business.button>
                        </x-slot:actions>
                    </x-business.drawer>
                    <x-business-pagination resource="providers" label="locations" :pagination="$pagination" />

                    @if ($this->featureIsAvailable('provider_claims'))
                        <div class="section-heading section-heading-spaced">
                            <div><p class="eyebrow">Add a location</p><h2>Find your public listing</h2><p>Search Zigpaw’s local directory. Results are limited to the country registered on this workspace.</p></div>
                        </div>
                        <form class="inline-form claim-search-form" wire:submit="searchClaimableProviders">
                            <x-business.field class="form-grow" label="Business or location name"><input type="search" wire:model="claimSearch" placeholder="Laidley Veterinary Surgery" autocomplete="off"></x-business.field>
                            <x-business.button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="searchClaimableProviders">Search directory</x-business.button>
                        </form>
                        @error('claimSearch') <p class="field-error">{{ $message }}</p> @enderror

                        @if ($claimableProviders !== [])
                            <div class="claim-result-list" aria-label="Claimable provider listings">
                                @foreach ($claimableProviders as $candidate)
                                    @php($isSelectedClaim = $claimServiceProviderId === (string) $candidate['service_provider_id'] && $claimPlaceId === (string) $candidate['place_id'])
                                    <x-business.button variant="claim" class="{{ $isSelectedClaim ? 'claim-result-selected' : '' }}" wire:click="selectClaimableProvider('{{ $candidate['service_provider_id'] }}', '{{ $candidate['place_id'] }}')" aria-pressed="{{ $isSelectedClaim ? 'true' : 'false' }}">
                                        <span><strong>{{ $candidate['branch_name'] ?: $candidate['provider_name'] }}</strong><small>{{ $candidate['provider_name'] !== $candidate['branch_name'] ? $candidate['provider_name'].' · ' : '' }}{{ $candidate['address'] }}</small></span>
                                        <span class="claim-result-action">{{ $isSelectedClaim ? 'Selected' : 'Choose' }}</span>
                                    </x-business.button>
                                @endforeach
                            </div>
                            <x-business-pagination resource="claimableProviders" label="directory results" :pagination="$pagination" />
                        @elseif ($claimSearch !== '' && isset($pagination['claimableProviders']))
                            <p class="section-note">No claimable listings matched that search. Try the business name, suburb or postcode.</p>
                        @endif

                        <x-business.modal
                            id="listing-claim-modal"
                            :open="$claimServiceProviderId !== ''"
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
                                    <x-business.field label="Your authority"><select wire:model="claimAuthorityType" data-overlay-initial-focus><option value="">Choose your role</option><option value="owner">Business owner</option><option value="manager">Business manager</option><option value="location_manager">Location manager</option></select></x-business.field>
                                    <x-business.field label="How should we verify you?"><select wire:model.live="claimVerificationMethod"><option value="">Choose a method</option><option value="domain_email">Business email</option><option value="phone">Business phone</option></select></x-business.field>
                                    @if ($claimVerificationMethod === 'domain_email')
                                        <x-business.field class="form-span-two" label="Business email"><input type="email" wire:model="claimantEmail" autocomplete="email" placeholder="you@business.example"></x-business.field>
                                    @elseif ($claimVerificationMethod === 'phone')
                                        <x-business.field class="form-span-two" label="Business phone"><input type="tel" wire:model="claimantPhone" autocomplete="tel"></x-business.field>
                                    @endif
                                </div>
                                @error('claimAuthorityType') <p class="field-error">{{ $message }}</p> @enderror
                                @error('claimVerificationMethod') <p class="field-error">{{ $message }}</p> @enderror
                                @error('claimantEmail') <p class="field-error">{{ $message }}</p> @enderror
                                @error('claimantPhone') <p class="field-error">{{ $message }}</p> @enderror
                            </form>
                            </x-slot:body>
                            <x-slot:footer>Submitting does not change the public listing until Zigpaw verifies your authority.</x-slot:footer>
                            <x-slot:actions>
                                <x-business.button wire:click="cancelProviderClaim">Cancel</x-business.button>
                                <x-business.button variant="primary" type="submit" form="listing-claim-form">Submit claim</x-business.button>
                            </x-slot:actions>
                        </x-business.modal>
                    @endif

                    @if ($this->featureIsAvailable('provider_claims') && $providerClaims !== [])
                        <div class="section-heading section-heading-spaced">
                            <div><p class="eyebrow">Claims</p><h2>Verification progress</h2></div>
                        </div>
                        <x-business.data-table label="Listing claim status" class="data-list">
                            @foreach ($providerClaims as $claim)
                                <div class="data-row">
                                    <div><strong>{{ data_get($claim, 'place.name') ?: data_get($claim, 'provider.name') }}</strong><small>{{ data_get($claim, 'place.address') }}</small></div>
                                    <x-business.status>{{ str($claim['status'])->headline() }}</x-business.status>
                                </div>
                            @endforeach
                        </x-business.data-table>
                        <x-business-pagination resource="providerClaims" label="claims" :pagination="$pagination" />
                    @elseif (! $this->featureIsAvailable('provider_claims'))
                        <p class="section-note">Listing claims are not available for this workspace yet. Existing approved locations remain available above.</p>
                    @endif
                </section>
            @elseif ($section === 'bookings')
                <section class="workspace-section">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Booking requests · {{ data_get($pagination, 'bookings.total', count($bookings)) }}</p>
                            <h2>Requests from pet families</h2>
                            <p>Zigpaw carries the request and conversation. Payment remains between the business and customer.</p>
                        </div>
                    </div>
                    <div class="booking-list">
                        @forelse ($bookings as $booking)
                            @php($customerWindows = collect($booking['requested_windows'] ?? [])->where('source', 'customer')->where('status', 'requested'))
                            <article class="booking-row">
                                <div class="booking-row-heading">
                                    <div>
                                        <x-business.status :tone="$booking['status'] === 'requested' ? 'warn' : ($booking['status'] === 'accepted' ? 'good' : 'neutral')">{{ str($booking['status'])->headline() }}</x-business.status>
                                        <h3>{{ data_get($booking, 'pet.name', 'Pet') }} · {{ data_get($booking, 'provider.offering_name', data_get($booking, 'provider.name', 'Service request')) }}</h3>
                                        <p>{{ $booking['request_number'] }} · Submitted {{ $this->displayRelative($booking['submitted_at'] ?? null) }}</p>
                                    </div>
                                </div>
                                @if ($booking['customer_message'] ?? null)
                                    <p class="booking-note">{{ $booking['customer_message'] }}</p>
                                @endif
                                @if (data_get($booking, 'pet_access.status') === 'active')
                                    <div class="booking-pet-access">
                                        <div>
                                            <strong>Pet profile available</strong>
                                            <small>View-only access is active until {{ data_get($booking, 'pet_access.expires_at') ? $this->displayDateTime($booking['pet_access']['expires_at']) : 'the appointment access is revoked' }}.</small>
                                        </div>
                                        <x-business.button variant="text" wire:click="viewBookingPetContext('{{ $booking['id'] }}')">View pet profile</x-business.button>
                                    </div>
                                @endif
                                @if (isset($bookingPetContexts[$booking['id']]))
                                    <div class="booking-pet-context" aria-label="Pet profile context">
                                        <strong>{{ data_get($bookingPetContexts[$booking['id']], 'pet.name', 'Pet') }}</strong>
                                        <span>{{ data_get($bookingPetContexts[$booking['id']], 'pet.species', 'Pet') }}{{ data_get($bookingPetContexts[$booking['id']], 'pet.breed') ? ' · '.data_get($bookingPetContexts[$booking['id']], 'pet.breed') : '' }}</span>
                                        <small>Only profile fields permitted by the confirmed appointment are shown.</small>
                                    </div>
                                @endif
                                @if ($customerWindows->isNotEmpty())
                                    <div class="window-list">
                                        @foreach ($customerWindows as $window)
                                            <x-business.button variant="window" wire:click="acceptBooking('{{ $booking['id'] }}', '{{ $window['id'] }}')" wire:confirm="Accept this appointment time? The customer will be notified." :disabled="! in_array('accept', $booking['available_actions'] ?? [], true)">
                                                <span>{{ $this->displayDateTime($window['starts_at'] ?? null) }}</span>
                                                <small>Accept this time</small>
                                            </x-business.button>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="booking-actions">
                                    @if (in_array('complete', $booking['available_actions'] ?? [], true))
                                        <x-business.button variant="primary" wire:click="completeBooking('{{ $booking['id'] }}')" wire:confirm="Mark this appointment complete? This will end appointment-based pet access.">Mark complete</x-business.button>
                                    @endif
                                    @if (in_array('decline', $booking['available_actions'] ?? [], true))
                                        <input type="text" wire:model="declineReasons.{{ $booking['id'] }}" placeholder="Reason for declining" aria-label="Reason for declining {{ $booking['request_number'] }}">
                                        <x-business.button variant="danger" wire:click="declineBooking('{{ $booking['id'] }}')" wire:confirm="Decline this booking request? The customer will be notified.">Decline</x-business.button>
                                        @error('declineReasons.'.$booking['id']) <p class="field-error booking-field-error">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            </article>
                        @empty
                            <x-business.empty-state title="No booking requests yet" description="New customer requests will appear here." />
                        @endforelse
                    </div>
                    <x-business-pagination resource="bookings" label="booking requests" :pagination="$pagination" />
                </section>
            @elseif ($section === 'booking-setup')
                <section class="workspace-section narrow-section">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Booking setup</p>
                            <h2>Choose how requests reach your team</h2>
                            <p>Configure one managed location at a time. The portal itself is a delivery path, so a duplicate email or phone is optional.</p>
                        </div>
                    </div>

                    @if ($providers !== [])
                        <form class="form-panel" wire:submit="saveBookingConfiguration">
                            <div class="form-grid form-grid-two">
                                <x-business.field class="form-span-two" label="Managed location"><select wire:model="bookingProviderLinkId" wire:change="selectBookingProvider($event.target.value)">@foreach ($providers as $link)<option value="{{ $link['id'] }}">{{ data_get($link, 'place.name') ?: data_get($link, 'provider.name') }}</option>@endforeach</select></x-business.field>
                                <x-business.field label="Booking requests"><select wire:model="bookingStatus"><option value="">Choose a status</option><option value="disabled">Off</option><option value="enabled">Accepting requests</option><option value="paused">Paused</option></select></x-business.field>
                                <x-business.field label="Timezone"><input type="text" wire:model="bookingTimezone" placeholder="Australia/Brisbane"></x-business.field>
                                <x-business.field label="Notification email (optional)"><input type="email" wire:model="bookingNotificationEmail"></x-business.field>
                                <x-business.field label="Notification phone (optional)"><input type="tel" wire:model="bookingNotificationPhone"></x-business.field>
                                <x-business.field label="Minimum notice (hours)"><input type="number" min="0" max="8760" wire:model="bookingMinimumNoticeHours"></x-business.field>
                                <x-business.field label="Book up to (days)"><input type="number" min="1" max="730" wire:model="bookingMaximumAdvanceDays"></x-business.field>
                                <x-business.field label="Response window (hours)"><input type="number" min="1" max="720" wire:model="bookingResponseWindowHours"></x-business.field>
                                <x-business.field class="form-span-two" label="Customer instructions"><textarea wire:model="bookingCustomerInstructions" rows="4" placeholder="What should a customer know before sending a request?"></textarea></x-business.field>
                            </div>

                            <section class="schedule-editor" aria-labelledby="weekly-availability-heading">
                                <div class="schedule-editor-heading">
                                    <div>
                                        <p class="eyebrow">Weekly availability</p>
                                        <h3 id="weekly-availability-heading">When customers can request a booking</h3>
                                        <p>Add only the windows this location normally accepts requests.</p>
                                    </div>
                                    <x-business.button wire:click="addBookingAvailabilityRule">Add window</x-business.button>
                                </div>

                                <div class="schedule-list">
                                    @forelse ($bookingAvailabilityRules as $index => $rule)
                                        <fieldset class="schedule-item" wire:key="booking-rule-{{ $index }}">
                                            <legend>Window {{ $index + 1 }}</legend>
                                            <div class="schedule-rule-grid">
                                                <x-business.field label="Day" :error="$errors->first('bookingAvailabilityRules.'.$index.'.day_of_week')">
                                                    <select wire:model="bookingAvailabilityRules.{{ $index }}.day_of_week">
                                                        <option value="0">Sunday</option>
                                                        <option value="1">Monday</option>
                                                        <option value="2">Tuesday</option>
                                                        <option value="3">Wednesday</option>
                                                        <option value="4">Thursday</option>
                                                        <option value="5">Friday</option>
                                                        <option value="6">Saturday</option>
                                                    </select>
                                                </x-business.field>
                                                <x-business.field label="Opens" :error="$errors->first('bookingAvailabilityRules.'.$index.'.starts_at')"><input type="time" wire:model="bookingAvailabilityRules.{{ $index }}.starts_at"></x-business.field>
                                                <x-business.field label="Closes" :error="$errors->first('bookingAvailabilityRules.'.$index.'.ends_at')"><input type="time" wire:model="bookingAvailabilityRules.{{ $index }}.ends_at"></x-business.field>
                                                <label class="schedule-toggle">
                                                    <input type="checkbox" wire:model="bookingAvailabilityRules.{{ $index }}.is_active">
                                                    <span>Active</span>
                                                </label>
                                                <x-business.button class="schedule-remove" variant="danger" wire:click="removeBookingAvailabilityRule({{ $index }})" aria-label="Remove availability window {{ $index + 1 }}">Remove</x-business.button>
                                            </div>
                                            <details class="schedule-effective-dates">
                                                <summary>Limit to a date range</summary>
                                                <div class="schedule-date-grid">
                                                    <x-business.field label="Starts on" :error="$errors->first('bookingAvailabilityRules.'.$index.'.effective_from')"><input type="date" wire:model="bookingAvailabilityRules.{{ $index }}.effective_from"></x-business.field>
                                                    <x-business.field label="Ends on" :error="$errors->first('bookingAvailabilityRules.'.$index.'.effective_until')"><input type="date" wire:model="bookingAvailabilityRules.{{ $index }}.effective_until"></x-business.field>
                                                </div>
                                            </details>
                                        </fieldset>
                                    @empty
                                        <p class="schedule-empty">No weekly windows have been added. Customers can still contact the business, but they cannot choose from normal availability.</p>
                                    @endforelse
                                </div>
                            </section>

                            <section class="schedule-editor" aria-labelledby="availability-exceptions-heading">
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
                                                <x-business.field label="Date" :error="$errors->first('bookingAvailabilityExceptions.'.$index.'.date')"><input type="date" wire:model="bookingAvailabilityExceptions.{{ $index }}.date"></x-business.field>
                                                <x-business.field label="Availability" :error="$errors->first('bookingAvailabilityExceptions.'.$index.'.availability')">
                                                    <select wire:model="bookingAvailabilityExceptions.{{ $index }}.availability"><option value="unavailable">Closed</option><option value="available">Open</option></select>
                                                </x-business.field>
                                                <x-business.field label="Opens (optional)" :error="$errors->first('bookingAvailabilityExceptions.'.$index.'.starts_at')"><input type="time" wire:model="bookingAvailabilityExceptions.{{ $index }}.starts_at"></x-business.field>
                                                <x-business.field label="Closes (optional)" :error="$errors->first('bookingAvailabilityExceptions.'.$index.'.ends_at')"><input type="time" wire:model="bookingAvailabilityExceptions.{{ $index }}.ends_at"></x-business.field>
                                                <x-business.field class="schedule-reason" label="Reason (optional)" :error="$errors->first('bookingAvailabilityExceptions.'.$index.'.reason')"><input type="text" wire:model="bookingAvailabilityExceptions.{{ $index }}.reason" placeholder="Public holiday"></x-business.field>
                                                <x-business.button class="schedule-remove" variant="danger" wire:click="removeBookingAvailabilityException({{ $index }})" aria-label="Remove availability exception {{ $index + 1 }}">Remove</x-business.button>
                                            </div>
                                        </fieldset>
                                    @empty
                                        <p class="schedule-empty">No date exceptions have been added.</p>
                                    @endforelse
                                </div>
                            </section>

                            @error('bookingStatus') <p class="field-error">{{ $message }}</p> @enderror
                            @error('bookingTimezone') <p class="field-error">{{ $message }}</p> @enderror
                            @error('bookingNotificationEmail') <p class="field-error">{{ $message }}</p> @enderror
                            <div class="form-actions"><x-business.button variant="primary" type="submit">Save booking setup</x-business.button></div>
                        </form>
                        <x-business-pagination resource="bookingProfiles" label="booking configurations" :pagination="$pagination" />
                    @else
                        <x-business.empty-state title="No managed locations yet" description="A verified location is required before booking requests can be configured." />
                    @endif
                </section>
            @elseif ($section === 'services')
                <section class="workspace-section">
                    <div class="section-heading">
                        <div><p class="eyebrow">Services · {{ data_get($pagination, 'offerings.total', count($offerings)) }}</p><h2>What customers can request</h2><p>Offerings describe the service. Availability controls when requests can be made.</p></div>
                    </div>
                    @if ($providers !== [])
                        <form class="inline-form" wire:submit="createOffering">
                            <x-business.field label="Managed location"><select wire:model="offeringProviderLinkId">@foreach ($providers as $link)<option value="{{ $link['id'] }}">{{ data_get($link, 'place.name') ?: data_get($link, 'provider.name') }}</option>@endforeach</select></x-business.field>
                            <x-business.field class="form-grow" label="Service name"><input type="text" wire:model="offeringName" placeholder="Wellness consultation"></x-business.field>
                            <x-business.field label="Minutes"><input type="number" min="5" max="1440" step="5" wire:model="offeringDurationMinutes" placeholder="30"></x-business.field>
                            <x-business.button variant="primary" type="submit">Add service</x-business.button>
                        </form>
                        @error('offeringName') <p class="field-error">{{ $message }}</p> @enderror
                    @endif
                    <x-business.data-table label="Published services" class="data-list">
                        @forelse ($offerings as $offering)
                            <div class="data-row">
                                <div><strong>{{ $offering['name'] }}</strong><small>{{ data_get($offering, 'place.name', 'All locations') }}{{ $offering['default_duration_minutes'] ? ' · '.$offering['default_duration_minutes'].' minutes' : '' }}</small></div>
                                <div class="row-actions"><x-business.status :tone="$offering['status'] === 'active' ? 'good' : 'neutral'">{{ str($offering['status'])->headline() }}</x-business.status><x-business.button variant="text" wire:click="startOfferingEdit('{{ $offering['id'] }}')">Edit</x-business.button></div>
                            </div>
                        @empty
                            <div class="empty-row">No services have been published.</div>
                        @endforelse
                    </x-business.data-table>

                    <x-business.drawer
                        id="offering-edit-drawer"
                        :open="$editingOfferingId !== ''"
                        :title="$editingOfferingName ?: 'Edit service'"
                        eyebrow="Service"
                        close-action="cancelOfferingEdit"
                    >
                        <x-slot:body>
                        <form id="offering-edit-form" wire:submit="saveOffering">
                            <div class="form-grid form-grid-two">
                                <x-business.field label="Service name"><input type="text" wire:model="editingOfferingName" data-overlay-initial-focus></x-business.field>
                                <x-business.field label="Duration (minutes)"><input type="number" min="5" max="1440" step="5" wire:model="editingOfferingDurationMinutes"></x-business.field>
                                <x-business.field label="Status"><select wire:model="editingOfferingStatus"><option value="draft">Draft</option><option value="active">Available</option><option value="paused">Paused</option></select></x-business.field>
                                <x-business.field label="How customers respond"><select wire:model="editingOfferingRequestMode"><option value="request">Request in Zigpaw</option><option value="contact">Contact the business</option><option value="external">Use an external page</option></select></x-business.field>
                                <x-business.field class="form-span-two" label="Description"><textarea wire:model="editingOfferingDescription" rows="4"></textarea></x-business.field>
                            </div>
                            @error('editingOfferingName') <p class="field-error">{{ $message }}</p> @enderror
                        </form>
                        </x-slot:body>
                        <x-slot:footer>
                            <x-business.confirmation action="deleteOffering('{{ $editingOfferingId }}')" message="Remove this service?">Remove service</x-business.confirmation>
                        </x-slot:footer>
                        <x-slot:actions>
                            <x-business.button wire:click="cancelOfferingEdit">Cancel</x-business.button>
                            <x-business.button variant="primary" type="submit" form="offering-edit-form">Save service</x-business.button>
                        </x-slot:actions>
                    </x-business.drawer>
                    <x-business-pagination resource="offerings" label="services" :pagination="$pagination" />
                </section>
            @elseif ($section === 'programs')
                <section class="workspace-section narrow-section">
                    <div class="section-heading"><div><p class="eyebrow">Optional commercial programs</p><h2>Grow with Zigpaw</h2><p>Business verification and directory ranking never depend on joining a commercial program.</p></div></div>
                    <div class="program-card">
                        <div><x-business.status tone="blue">Referral program</x-business.status><h3>Recommend Zigpaw when it genuinely helps</h3><p>Approved businesses receive an attributable referral code. Commercial participation never changes organic directory placement.</p></div>
                        @php($referral = collect($programs)->firstWhere('program_key', 'referral'))
                        @if ($referral)
                            <x-business.status :tone="$referral['status'] === 'approved' ? 'good' : 'neutral'">{{ str($referral['status'])->headline() }}</x-business.status>
                        @elseif ($this->hasCapability('programs.manage'))
                            <x-business.button variant="primary" wire:click="applyForReferralProgram" wire:confirm="Apply to the referral program? Zigpaw will review the business before approval.">Apply</x-business.button>
                        @endif
                    </div>
                    <x-business-pagination resource="programs" label="programs" :pagination="$pagination" />
                </section>
            @elseif ($section === 'revenue')
                <section class="workspace-section">
                    <div class="section-heading"><div><p class="eyebrow">Revenue</p><h2>Commission activity and agreements</h2><p>Track approved referral activity and the commercial terms Zigpaw has recorded with your business. Organic directory ranking remains independent.</p></div></div>
                    <div class="metric-grid">
                        @forelse (data_get($financials, 'currencies', []) as $currency)
                            <article class="metric-card"><span>{{ $currency['currency'] }}</span><strong>{{ number_format(collect($currency['commissions'])->sum('amount_cents') / 100, 2) }}</strong><small>{{ collect($currency['commissions'])->sum('entry_count') }} commission entries</small></article>
                        @empty
                            <article class="metric-card"><span>Commission activity</span><strong>—</strong><small>No approved commission activity yet.</small></article>
                        @endforelse
                    </div>
                    <div class="section-heading section-heading-spaced"><div><p class="eyebrow">Recent commissions</p></div></div>
                    <x-business.data-table label="Recent commissions" class="data-list">
                        @forelse ($commissions as $commission)
                            <div class="data-row"><div><strong>{{ str($commission['event_type'])->headline() }}</strong><small>{{ $this->displayDate($commission['created_at'] ?? null) }}</small></div><div class="amount"><strong>{{ $commission['currency'] }} {{ number_format($commission['commission_amount_cents'] / 100, 2) }}</strong><small>{{ $commission['status_label'] ?? str($commission['status'])->headline() }}</small></div></div>
                        @empty
                            <div class="empty-row">No commissions have been recorded.</div>
                        @endforelse
                    </x-business.data-table>
                    <x-business-pagination resource="commissions" label="commission entries" :pagination="$pagination" />

                    <div class="section-heading section-heading-spaced"><div><p class="eyebrow">Commercial agreements</p></div></div>
                    <x-business.data-table label="Commercial agreements" class="data-list">
                        @forelse ($agreements as $agreement)
                            <div class="data-row">
                                <div>
                                    <strong>{{ str($agreement['agreement_type'])->headline() }}</strong>
                                    <small>{{ data_get($agreement, 'provider.name') ?: data_get($agreement, 'place.name') ?: $agreement['reference'] }}</small>
                                </div>
                                <div class="amount">
                                    <strong>{{ str($agreement['status'])->headline() }}</strong>
                                    <small>{{ $agreement['organic_rank_independent'] ? 'Organic ranking remains independent' : 'Review terms with Zigpaw' }}</small>
                                </div>
                            </div>
                        @empty
                            <div class="empty-row">No commercial agreements are recorded for this business.</div>
                        @endforelse
                    </x-business.data-table>
                    <x-business-pagination resource="agreements" label="agreements" :pagination="$pagination" />
                </section>
            @elseif ($section === 'team')
                <section class="workspace-section">
                    <div class="section-heading"><div><p class="eyebrow">Team · {{ data_get($pagination, 'team.total', count($team)) }}</p><h2>People with business access</h2><p>Give each person the smallest role needed for their work.</p></div></div>
                    <form class="inline-form" wire:submit="inviteTeamMember">
                        <x-business.field class="form-grow" label="Email address"><input type="email" wire:model="teamEmail" placeholder="colleague@example.com"></x-business.field>
                        <x-business.field label="Role"><select wire:model="teamRole"><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="manager">Manager</option><option value="finance">Finance</option></select></x-business.field>
                        <x-business.button variant="primary" type="submit">Send invitation</x-business.button>
                    </form>
                    @error('teamEmail') <p class="field-error">{{ $message }}</p> @enderror
                    <x-business.data-table label="People with business access" class="data-list">
                        @forelse ($team as $member)
                            <div class="data-row">
                                <div><strong>{{ $member['name'] ?: data_get($member, 'email', 'Invited teammate') }}</strong><small>{{ data_get($member, 'email') }}</small></div>
                                <div class="row-actions">
                                    <x-business.status :tone="$member['status'] === 'active' ? 'good' : ($member['status'] === 'invited' ? 'blue' : 'neutral')">{{ str($member['status'])->headline() }}</x-business.status>
                                    <strong class="role-label">{{ str($member['role'])->headline() }}</strong>
                                    @unless ($member['is_current_user'] ?? false)
                                        <x-business.button variant="text" wire:click="startTeamMemberEdit('{{ $member['id'] }}')">Edit</x-business.button>
                                        @if ($member['status'] === 'invited')
                                            <x-business.button variant="text" wire:click="resendTeamInvitation('{{ $member['id'] }}')">Resend</x-business.button>
                                        @endif
                                        <x-business.confirmation action="revokeTeamMember('{{ $member['id'] }}')" message="Remove this person’s business access?">Revoke</x-business.confirmation>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <x-business.empty-state title="No team members yet" description="Invite a colleague when they need access to this workspace." />
                        @endforelse
                    </x-business.data-table>

                    @php($editingMember = collect($team)->firstWhere('id', $editingTeamMemberId))
                    <x-business.drawer
                        id="team-access-drawer"
                        :open="$editingTeamMemberId !== ''"
                        :title="data_get($editingMember, 'name') ?: data_get($editingMember, 'email', 'Update access')"
                        eyebrow="Team access"
                        close-action="cancelTeamMemberEdit"
                    >
                        <x-slot:body>
                        <form id="team-access-form" wire:submit="saveTeamMemberRole">
                            <x-business.field label="Role"><select wire:model="editingTeamRole" data-overlay-initial-focus><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="manager">Manager</option><option value="finance">Finance</option></select></x-business.field>
                            @error('editingTeamRole') <p class="field-error">{{ $message }}</p> @enderror
                        </form>
                        </x-slot:body>
                        <x-slot:actions>
                            <x-business.button wire:click="cancelTeamMemberEdit">Cancel</x-business.button>
                            <x-business.button variant="primary" type="submit" form="team-access-form">Save role</x-business.button>
                        </x-slot:actions>
                    </x-business.drawer>
                    <x-business-pagination resource="team" label="team members" :pagination="$pagination" />
                </section>
            @endif
    </x-business.shell>
    @else
        <main id="main-content" class="access-shell">
            <header class="access-header">
                <div class="access-brand"><span class="brand-art"><img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="Zigpaw"><img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw"></span><span>Business</span></div>
                <x-business.button variant="theme" data-theme-toggle aria-label="Change appearance"><span aria-hidden="true" data-theme-icon>◐</span><span data-theme-label>System</span></x-business.button>
            </header>
            <section class="access-card">
                @if ($state === 'choose_organization')
                    <p class="eyebrow">Business workspace</p><h1>Choose where you’re working.</h1><p>Each workspace keeps locations, bookings, staff and financial information isolated.</p>
                    <div class="organization-list">@foreach ($organizations as $organization)<x-business.button variant="organization" wire:click="selectOrganization('{{ $organization['id'] }}')"><span>{{ $organization['name'] }}</span><small>{{ str($organization['role'])->headline() }}</small></x-business.button>@endforeach</div>
                @elseif ($state === 'forbidden')
                    <p class="eyebrow">Business access</p><h1>No active business workspace.</h1><p>{{ $message ?: 'Ask the business owner to invite you, or sign in with another account.' }}</p><x-business.button variant="primary" :href="route('auth.login')">Use another account</x-business.button>
                @elseif ($state === 'unavailable')
                    <p class="eyebrow">Connection check</p><h1>The business workspace is taking a pause.</h1><p>{{ $message ?: 'Your information is safe. Try again shortly.' }}</p><x-business.button variant="primary" :href="route('dashboard')">Try again</x-business.button>
                @else
                    <p class="eyebrow">Zigpaw business</p><h1>Manage the work around every pet.</h1><p>{{ $message ?: 'Keep verified locations, customer booking requests, services, team access and optional partner programs together.' }}</p><x-business.button variant="primary" :href="route('auth.login')">Sign in</x-business.button>
                @endif
            </section>
        </main>
    @endif

    <x-business.toast-region>
        @if ($notice)
            <x-business.toast dismiss-action="dismissNotice">{{ $notice }}</x-business.toast>
        @endif
    </x-business.toast-region>
</div>
