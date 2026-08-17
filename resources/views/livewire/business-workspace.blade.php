<main class="business-shell">
    @if ($state === 'ready')
        <aside class="business-sidebar" aria-label="Business workspace">
            <a href="{{ route('dashboard') }}" class="brand-link" wire:navigate>
                <span class="brand-art"><img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="Zigpaw"><img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw"></span>
                <span>Business</span>
            </a>

            <nav class="business-nav">
                @foreach ($navigationSections as $key => $navigation)
                    <button type="button" class="business-nav-item {{ $section === $key ? 'is-active' : '' }}" wire:click="showSection('{{ $key }}')">
                        <x-business-icon :name="$navigation['icon']" />
                        <span>{{ $navigation['label'] }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="sidebar-account">
                <p>{{ data_get($identity, 'organization.name', 'Business workspace') }}</p>
                <span>{{ str(data_get($identity, 'membership.role', 'member'))->headline() }}</span>
                <form method="POST" action="{{ route('auth.logout') }}">
                    @csrf
                    <button type="submit" class="text-action">Sign out</button>
                </form>
            </div>
        </aside>

        <section class="workspace-main">
            <header class="workspace-header">
                <div>
                    <p class="eyebrow">{{ str($section)->headline() }}</p>
                    <h1>{{ data_get($identity, 'organization.name', 'Your business') }}</h1>
                </div>
                <label class="organization-switcher">
                    <span>Working in</span>
                    <select wire:change="selectOrganization($event.target.value)" aria-label="Working organization">
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization['id'] }}" @selected($organization['id'] === $organizationId)>{{ $organization['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            </header>

            <div class="mobile-sections" role="navigation" aria-label="Business sections">
                <select wire:change="showSection($event.target.value)" aria-label="Current section">
                    @foreach ($navigationSections as $key => $navigation)
                        <option value="{{ $key }}" @selected($section === $key)>{{ $navigation['label'] }}</option>
                    @endforeach
                </select>
            </div>

            @if ($message)
                <div class="inline-alert" role="alert">{{ $message }}</div>
            @endif

            @if ($section === 'overview')
                <section class="workspace-section">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Today</p>
                            <h2>Your business at a glance</h2>
                        </div>
                    </div>
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
                                <button type="button" class="action-row" wire:click="showSection('bookings')">
                                    <span>
                                        <strong>{{ $booking['request_number'] }}</strong>
                                        <small>{{ data_get($booking, 'pet.name', 'Pet') }} needs a response for {{ data_get($booking, 'provider.name', 'your service') }}.</small>
                                    </span>
                                    <span class="status status-warn">Respond</span>
                                </button>
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
                        <span class="status {{ data_get($identity, 'business_profile.verified_at') ? 'status-good' : 'status-warn' }}">{{ data_get($identity, 'business_profile.verified_at') ? 'Verified' : 'Verification pending' }}</span>
                    </div>

                    <form class="form-panel" wire:submit="saveBusinessProfile">
                        <div class="form-grid form-grid-two">
                            <label class="field form-span-two"><span>Business name</span><input type="text" wire:model="businessName" autocomplete="organization"></label>
                            <label class="field"><span>Legal name</span><input type="text" wire:model="businessLegalName"></label>
                            <label class="field"><span>Registered country</span><input type="text" wire:model="businessRegisteredCountryCode" maxlength="2" autocapitalize="characters" placeholder="AU"></label>
                            <label class="field"><span>Registration number</span><input type="text" wire:model="businessRegistrationNumber"></label>
                            <label class="field"><span>Registration number type</span><input type="text" wire:model="businessRegistrationNumberType" placeholder="ABN, company number"></label>
                            <label class="field"><span>Tax identifier</span><input type="text" wire:model="businessTaxIdentifier"></label>
                            <label class="field"><span>Tax identifier type</span><input type="text" wire:model="businessTaxIdentifierType" placeholder="GST, VAT"></label>
                        </div>

                        <div class="form-divider"></div>
                        <p class="eyebrow">Primary contact</p>
                        <div class="form-grid form-grid-two">
                            <label class="field"><span>Name</span><input type="text" wire:model="businessPrimaryContactName" autocomplete="name"></label>
                            <label class="field"><span>Email</span><input type="email" wire:model="businessPrimaryContactEmail" autocomplete="email"></label>
                            <label class="field"><span>Phone</span><input type="tel" wire:model="businessPrimaryContactPhone" autocomplete="tel"></label>
                            <label class="field"><span>Website</span><input type="url" wire:model="businessWebsite" placeholder="https://"></label>
                        </div>
                        @error('businessName') <p class="field-error">{{ $message }}</p> @enderror
                        @error('businessPrimaryContactEmail') <p class="field-error">{{ $message }}</p> @enderror
                        @error('businessWebsite') <p class="field-error">{{ $message }}</p> @enderror
                        <div class="form-actions"><button type="submit" class="button button-primary">Save profile</button></div>
                    </form>
                </section>
            @elseif ($section === 'listings')
                <section class="workspace-section">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Managed locations · {{ count($providers) }}</p>
                            <h2>Business listings</h2>
                            <p>Verified authority lets your team correct public details without changing organic ranking.</p>
                        </div>
                    </div>
                    <div class="listing-grid">
                        @forelse ($providers as $link)
                            <article class="listing-card">
                                <div class="listing-card-top">
                                    <span class="status {{ $link['status'] === 'active' ? 'status-good' : '' }}">{{ str($link['status'])->headline() }}</span>
                                    <span>{{ str($link['authority_type'])->headline() }}</span>
                                </div>
                                <h3>{{ data_get($link, 'place.name') ?: data_get($link, 'provider.name') }}</h3>
                                <p>{{ data_get($link, 'place.address', 'All verified locations') }}</p>
                                <div class="listing-card-footer">
                                    <small>{{ data_get($link, 'provider.template.label') ?: collect(data_get($link, 'provider.categories', []))->implode(' · ') }}</small>
                                    <button type="button" class="text-action" wire:click="startProviderEdit('{{ $link['id'] }}')">Edit details</button>
                                </div>
                            </article>
                        @empty
                            <div class="empty-state">
                                <h3>No managed locations yet</h3>
                                <p>Claim a public provider listing to manage its details, services and booking requests.</p>
                            </div>
                        @endforelse
                    </div>

                    @if ($editingProviderLinkId !== '')
                        @php($editingProvider = collect($providers)->firstWhere('id', $editingProviderLinkId))
                        <form class="form-panel" wire:submit="saveProvider">
                            <div class="section-heading compact-heading"><div><p class="eyebrow">Edit location</p><h3>{{ data_get($editingProvider, 'place.name') ?: data_get($editingProvider, 'provider.name') }}</h3></div><button type="button" class="text-action" wire:click="cancelProviderEdit">Close</button></div>
                            <div class="form-grid form-grid-two">
                                <label class="field"><span>Provider name</span><input type="text" wire:model="providerName"></label>
                                <label class="field"><span>Location name</span><input type="text" wire:model="placeName" @disabled(! data_get($editingProvider, 'place.id'))></label>
                                <label class="field form-span-two"><span>Description</span><textarea wire:model="providerDescription" rows="4"></textarea></label>
                                <label class="field"><span>Provider phone</span><input type="tel" wire:model="providerPhone"></label>
                                <label class="field"><span>Provider email</span><input type="email" wire:model="providerEmail"></label>
                                <label class="field"><span>Provider website</span><input type="url" wire:model="providerWebsite" placeholder="https://"></label>
                                <label class="field"><span>Location phone</span><input type="tel" wire:model="placePhone" @disabled(! data_get($editingProvider, 'place.id'))></label>
                            </div>
                            @if (data_get($editingProvider, 'place.id'))
                                <div class="form-divider"></div>
                                <p class="eyebrow">Location details</p>
                                <div class="form-grid form-grid-two">
                                    <label class="field"><span>Location email</span><input type="email" wire:model="placeEmail"></label>
                                    <label class="field"><span>Location website</span><input type="url" wire:model="placeWebsite" placeholder="https://"></label>
                                    <label class="field"><span>Street address</span><input type="text" wire:model="placeAddressLine1"></label>
                                    <label class="field"><span>Address line 2</span><input type="text" wire:model="placeAddressLine2"></label>
                                    <label class="field"><span>City or suburb</span><input type="text" wire:model="placeCity"></label>
                                    <label class="field"><span>State or region</span><input type="text" wire:model="placeState"></label>
                                    <label class="field"><span>Postcode</span><input type="text" wire:model="placePostalCode"></label>
                                    <label class="field"><span>Timezone</span><input type="text" wire:model="placeTimezone" placeholder="Australia/Brisbane"></label>
                                </div>
                            @endif
                            @error('providerName') <p class="field-error">{{ $message }}</p> @enderror
                            @error('providerEmail') <p class="field-error">{{ $message }}</p> @enderror
                            @error('providerWebsite') <p class="field-error">{{ $message }}</p> @enderror
                            @error('placeTimezone') <p class="field-error">{{ $message }}</p> @enderror
                            <div class="form-actions"><button type="button" class="button button-secondary" wire:click="cancelProviderEdit">Cancel</button><button type="submit" class="button button-primary">Save details</button></div>
                        </form>
                    @endif
                    <x-business-pagination resource="providers" label="locations" :pagination="$pagination" />

                    @if ($this->featureIsAvailable('provider_claims'))
                        <div class="section-heading section-heading-spaced">
                            <div><p class="eyebrow">Add a location</p><h2>Find your public listing</h2><p>Search Zigpaw’s local directory. Results are limited to the country registered on this workspace.</p></div>
                        </div>
                        <form class="inline-form claim-search-form" wire:submit="searchClaimableProviders">
                            <label class="form-grow"><span>Business or location name</span><input type="search" wire:model="claimSearch" placeholder="Laidley Veterinary Surgery" autocomplete="off"></label>
                            <button class="button button-primary" type="submit" wire:loading.attr="disabled" wire:target="searchClaimableProviders">Search directory</button>
                        </form>
                        @error('claimSearch') <p class="field-error">{{ $message }}</p> @enderror

                        @if ($claimableProviders !== [])
                            <div class="claim-result-list" aria-label="Claimable provider listings">
                                @foreach ($claimableProviders as $candidate)
                                    @php($isSelectedClaim = $claimServiceProviderId === (string) $candidate['service_provider_id'] && $claimPlaceId === (string) $candidate['place_id'])
                                    <button type="button" class="claim-result {{ $isSelectedClaim ? 'claim-result-selected' : '' }}" wire:click="selectClaimableProvider('{{ $candidate['service_provider_id'] }}', '{{ $candidate['place_id'] }}')" aria-pressed="{{ $isSelectedClaim ? 'true' : 'false' }}">
                                        <span><strong>{{ $candidate['branch_name'] ?: $candidate['provider_name'] }}</strong><small>{{ $candidate['provider_name'] !== $candidate['branch_name'] ? $candidate['provider_name'].' · ' : '' }}{{ $candidate['address'] }}</small></span>
                                        <span class="claim-result-action">{{ $isSelectedClaim ? 'Selected' : 'Choose' }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <x-business-pagination resource="claimableProviders" label="directory results" :pagination="$pagination" />
                        @elseif ($claimSearch !== '' && isset($pagination['claimableProviders']))
                            <p class="section-note">No claimable listings matched that search. Try the business name, suburb or postcode.</p>
                        @endif

                        @if ($claimServiceProviderId !== '')
                            <form class="form-panel claim-form" wire:submit="submitProviderClaim">
                                <div class="section-heading compact-heading"><div><p class="eyebrow">Selected listing</p><h3>{{ $claimBranchName ?: $claimProviderName }}</h3><p>{{ $claimAddress }}</p></div><button type="button" class="text-action" wire:click="cancelProviderClaim">Choose another</button></div>
                                <div class="form-grid form-grid-two">
                                    <label class="field"><span>Your authority</span><select wire:model="claimAuthorityType"><option value="">Choose your role</option><option value="owner">Business owner</option><option value="manager">Business manager</option><option value="location_manager">Location manager</option></select></label>
                                    <label class="field"><span>How should we verify you?</span><select wire:model.live="claimVerificationMethod"><option value="">Choose a method</option><option value="domain_email">Business email</option><option value="phone">Business phone</option></select></label>
                                    @if ($claimVerificationMethod === 'domain_email')
                                        <label class="field form-span-two"><span>Business email</span><input type="email" wire:model="claimantEmail" autocomplete="email" placeholder="you@business.example"></label>
                                    @elseif ($claimVerificationMethod === 'phone')
                                        <label class="field form-span-two"><span>Business phone</span><input type="tel" wire:model="claimantPhone" autocomplete="tel"></label>
                                    @endif
                                </div>
                                @error('claimAuthorityType') <p class="field-error">{{ $message }}</p> @enderror
                                @error('claimVerificationMethod') <p class="field-error">{{ $message }}</p> @enderror
                                @error('claimantEmail') <p class="field-error">{{ $message }}</p> @enderror
                                @error('claimantPhone') <p class="field-error">{{ $message }}</p> @enderror
                                <p class="section-note">Submitting a claim does not change the public listing until Zigpaw verifies your authority.</p>
                                <div class="form-actions"><button type="button" class="button button-secondary" wire:click="cancelProviderClaim">Cancel</button><button type="submit" class="button button-primary">Submit claim</button></div>
                            </form>
                        @endif
                    @endif

                    @if ($this->featureIsAvailable('provider_claims') && $providerClaims !== [])
                        <div class="section-heading section-heading-spaced">
                            <div><p class="eyebrow">Claims</p><h2>Verification progress</h2></div>
                        </div>
                        <div class="data-list">
                            @foreach ($providerClaims as $claim)
                                <div class="data-row">
                                    <div><strong>{{ data_get($claim, 'place.name') ?: data_get($claim, 'provider.name') }}</strong><small>{{ data_get($claim, 'place.address') }}</small></div>
                                    <span class="status">{{ str($claim['status'])->headline() }}</span>
                                </div>
                            @endforeach
                        </div>
                        <x-business-pagination resource="providerClaims" label="claims" :pagination="$pagination" />
                    @elseif (! $this->featureIsAvailable('provider_claims'))
                        <p class="section-note">Listing claims are not available for this workspace yet. Existing approved locations remain available above.</p>
                    @endif
                </section>
            @elseif ($section === 'bookings')
                <section class="workspace-section">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Booking requests · {{ count($bookings) }}</p>
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
                                        <span class="status {{ $booking['status'] === 'requested' ? 'status-warn' : ($booking['status'] === 'accepted' ? 'status-good' : '') }}">{{ str($booking['status'])->headline() }}</span>
                                        <h3>{{ data_get($booking, 'pet.name', 'Pet') }} · {{ data_get($booking, 'provider.offering_name', data_get($booking, 'provider.name', 'Service request')) }}</h3>
                                        <p>{{ $booking['request_number'] }} · Submitted {{ \Illuminate\Support\Carbon::parse($booking['submitted_at'])->diffForHumans() }}</p>
                                    </div>
                                </div>
                                @if ($booking['customer_message'] ?? null)
                                    <p class="booking-note">{{ $booking['customer_message'] }}</p>
                                @endif
                                @if (data_get($booking, 'pet_access.status') === 'active')
                                    <div class="booking-pet-access">
                                        <div>
                                            <strong>Pet profile available</strong>
                                            <small>View-only access is active until {{ data_get($booking, 'pet_access.expires_at') ? \Illuminate\Support\Carbon::parse($booking['pet_access']['expires_at'])->format('j M Y, g:i A') : 'the appointment access is revoked' }}.</small>
                                        </div>
                                        <button type="button" class="text-action" wire:click="viewBookingPetContext('{{ $booking['id'] }}')">View pet profile</button>
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
                                            <button type="button" wire:click="acceptBooking('{{ $booking['id'] }}', '{{ $window['id'] }}')" @disabled(! in_array('accept', $booking['available_actions'] ?? [], true))>
                                                <span>{{ \Illuminate\Support\Carbon::parse($window['starts_at'])->format('D j M · g:i A') }}</span>
                                                <small>Accept this time</small>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="booking-actions">
                                    @if (in_array('complete', $booking['available_actions'] ?? [], true))
                                        <button class="button button-primary" wire:click="completeBooking('{{ $booking['id'] }}')">Mark complete</button>
                                    @endif
                                    @if (in_array('decline', $booking['available_actions'] ?? [], true))
                                        <input type="text" wire:model="declineReasons.{{ $booking['id'] }}" placeholder="Reason for declining" aria-label="Reason for declining {{ $booking['request_number'] }}">
                                        <button class="button button-danger-soft" wire:click="declineBooking('{{ $booking['id'] }}')">Decline</button>
                                        @error('declineReasons.'.$booking['id']) <p class="field-error booking-field-error">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="empty-state"><h3>No booking requests yet</h3><p>New customer requests will appear here.</p></div>
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
                                <label class="field form-span-two"><span>Managed location</span><select wire:model="bookingProviderLinkId" wire:change="selectBookingProvider($event.target.value)">@foreach ($providers as $link)<option value="{{ $link['id'] }}">{{ data_get($link, 'place.name') ?: data_get($link, 'provider.name') }}</option>@endforeach</select></label>
                                <label class="field"><span>Booking requests</span><select wire:model="bookingStatus"><option value="">Choose a status</option><option value="disabled">Off</option><option value="enabled">Accepting requests</option><option value="paused">Paused</option></select></label>
                                <label class="field"><span>Timezone</span><input type="text" wire:model="bookingTimezone" placeholder="Australia/Brisbane"></label>
                                <label class="field"><span>Notification email (optional)</span><input type="email" wire:model="bookingNotificationEmail"></label>
                                <label class="field"><span>Notification phone (optional)</span><input type="tel" wire:model="bookingNotificationPhone"></label>
                                <label class="field"><span>Minimum notice (hours)</span><input type="number" min="0" max="8760" wire:model="bookingMinimumNoticeHours"></label>
                                <label class="field"><span>Book up to (days)</span><input type="number" min="1" max="730" wire:model="bookingMaximumAdvanceDays"></label>
                                <label class="field"><span>Response window (hours)</span><input type="number" min="1" max="720" wire:model="bookingResponseWindowHours"></label>
                                <label class="field form-span-two"><span>Customer instructions</span><textarea wire:model="bookingCustomerInstructions" rows="4" placeholder="What should a customer know before sending a request?"></textarea></label>
                            </div>
                            @error('bookingStatus') <p class="field-error">{{ $message }}</p> @enderror
                            @error('bookingTimezone') <p class="field-error">{{ $message }}</p> @enderror
                            @error('bookingNotificationEmail') <p class="field-error">{{ $message }}</p> @enderror
                            <p class="section-note">Existing availability windows and exceptions are preserved. Detailed schedule editing will appear here when that API-backed editor is ready.</p>
                            <div class="form-actions"><button type="submit" class="button button-primary">Save booking setup</button></div>
                        </form>
                        <x-business-pagination resource="bookingProfiles" label="booking configurations" :pagination="$pagination" />
                    @else
                        <div class="empty-state"><h3>No managed locations yet</h3><p>A verified location is required before booking requests can be configured.</p></div>
                    @endif
                </section>
            @elseif ($section === 'services')
                <section class="workspace-section">
                    <div class="section-heading">
                        <div><p class="eyebrow">Services · {{ count($offerings) }}</p><h2>What customers can request</h2><p>Offerings describe the service. Availability controls when requests can be made.</p></div>
                    </div>
                    @if ($providers !== [])
                        <form class="inline-form" wire:submit="createOffering">
                            <label><span>Managed location</span><select wire:model="offeringProviderLinkId">@foreach ($providers as $link)<option value="{{ $link['id'] }}">{{ data_get($link, 'place.name') ?: data_get($link, 'provider.name') }}</option>@endforeach</select></label>
                            <label class="form-grow"><span>Service name</span><input type="text" wire:model="offeringName" placeholder="Wellness consultation"></label>
                            <label><span>Minutes</span><input type="number" min="5" max="1440" step="5" wire:model="offeringDurationMinutes" placeholder="30"></label>
                            <button class="button button-primary" type="submit">Add service</button>
                        </form>
                        @error('offeringName') <p class="field-error">{{ $message }}</p> @enderror
                    @endif
                    <div class="data-list">
                        @forelse ($offerings as $offering)
                            <div class="data-row">
                                <div><strong>{{ $offering['name'] }}</strong><small>{{ data_get($offering, 'place.name', 'All locations') }}{{ $offering['default_duration_minutes'] ? ' · '.$offering['default_duration_minutes'].' minutes' : '' }}</small></div>
                                <div class="row-actions"><span class="status {{ $offering['status'] === 'active' ? 'status-good' : '' }}">{{ str($offering['status'])->headline() }}</span><button type="button" class="text-action" wire:click="startOfferingEdit('{{ $offering['id'] }}')">Edit</button></div>
                            </div>
                        @empty
                            <div class="empty-row">No services have been published.</div>
                        @endforelse
                    </div>

                    @if ($editingOfferingId !== '')
                        <form class="form-panel" wire:submit="saveOffering">
                            <div class="section-heading compact-heading"><div><p class="eyebrow">Edit service</p><h3>{{ $editingOfferingName }}</h3></div><button type="button" class="text-action" wire:click="cancelOfferingEdit">Close</button></div>
                            <div class="form-grid form-grid-two">
                                <label class="field"><span>Service name</span><input type="text" wire:model="editingOfferingName"></label>
                                <label class="field"><span>Duration (minutes)</span><input type="number" min="5" max="1440" step="5" wire:model="editingOfferingDurationMinutes"></label>
                                <label class="field"><span>Status</span><select wire:model="editingOfferingStatus"><option value="draft">Draft</option><option value="active">Available</option><option value="paused">Paused</option></select></label>
                                <label class="field"><span>How customers respond</span><select wire:model="editingOfferingRequestMode"><option value="request">Request in Zigpaw</option><option value="contact">Contact the business</option><option value="external">Use an external page</option></select></label>
                                <label class="field form-span-two"><span>Description</span><textarea wire:model="editingOfferingDescription" rows="4"></textarea></label>
                            </div>
                            @error('editingOfferingName') <p class="field-error">{{ $message }}</p> @enderror
                            <div class="form-actions form-actions-split"><button type="button" class="button button-danger-soft" wire:click="deleteOffering('{{ $editingOfferingId }}')" wire:confirm="Remove this service?">Remove service</button><div><button type="button" class="button button-secondary" wire:click="cancelOfferingEdit">Cancel</button><button type="submit" class="button button-primary">Save service</button></div></div>
                        </form>
                    @endif
                    <x-business-pagination resource="offerings" label="services" :pagination="$pagination" />
                </section>
            @elseif ($section === 'programs')
                <section class="workspace-section narrow-section">
                    <div class="section-heading"><div><p class="eyebrow">Optional commercial programs</p><h2>Grow with Zigpaw</h2><p>Business verification and directory ranking never depend on joining a commercial program.</p></div></div>
                    <div class="program-card">
                        <div><span class="status status-blue">Referral program</span><h3>Recommend Zigpaw when it genuinely helps</h3><p>Approved businesses receive an attributable referral code. Commercial participation never changes organic directory placement.</p></div>
                        @php($referral = collect($programs)->firstWhere('program_key', 'referral'))
                        @if ($referral)
                            <span class="status {{ $referral['status'] === 'approved' ? 'status-good' : '' }}">{{ str($referral['status'])->headline() }}</span>
                        @elseif ($this->hasCapability('programs.manage'))
                            <button class="button button-primary" wire:click="applyForReferralProgram">Apply</button>
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
                    <div class="data-list">
                        @forelse ($commissions as $commission)
                            <div class="data-row"><div><strong>{{ str($commission['event_type'])->headline() }}</strong><small>{{ \Illuminate\Support\Carbon::parse($commission['created_at'])->format('j M Y') }}</small></div><div class="amount"><strong>{{ $commission['currency'] }} {{ number_format($commission['commission_amount_cents'] / 100, 2) }}</strong><small>{{ $commission['status_label'] ?? str($commission['status'])->headline() }}</small></div></div>
                        @empty
                            <div class="empty-row">No commissions have been recorded.</div>
                        @endforelse
                    </div>
                    <x-business-pagination resource="commissions" label="commission entries" :pagination="$pagination" />

                    <div class="section-heading section-heading-spaced"><div><p class="eyebrow">Commercial agreements</p></div></div>
                    <div class="data-list">
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
                    </div>
                    <x-business-pagination resource="agreements" label="agreements" :pagination="$pagination" />
                </section>
            @elseif ($section === 'team')
                <section class="workspace-section">
                    <div class="section-heading"><div><p class="eyebrow">Team · {{ count($team) }}</p><h2>People with business access</h2><p>Give each person the smallest role needed for their work.</p></div></div>
                    <form class="inline-form" wire:submit="inviteTeamMember">
                        <label class="form-grow"><span>Email address</span><input type="email" wire:model="teamEmail" placeholder="colleague@example.com"></label>
                        <label><span>Role</span><select wire:model="teamRole"><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="manager">Manager</option><option value="finance">Finance</option></select></label>
                        <button class="button button-primary" type="submit">Send invitation</button>
                    </form>
                    @error('teamEmail') <p class="field-error">{{ $message }}</p> @enderror
                    <div class="data-list">
                        @foreach ($team as $member)
                            <div class="data-row">
                                <div><strong>{{ $member['name'] ?: data_get($member, 'email', 'Invited teammate') }}</strong><small>{{ data_get($member, 'email') }}</small></div>
                                <div class="row-actions">
                                    <span class="status {{ $member['status'] === 'active' ? 'status-good' : ($member['status'] === 'invited' ? 'status-blue' : '') }}">{{ str($member['status'])->headline() }}</span>
                                    <strong class="role-label">{{ str($member['role'])->headline() }}</strong>
                                    @unless ($member['is_current_user'] ?? false)
                                        <button type="button" class="text-action" wire:click="startTeamMemberEdit('{{ $member['id'] }}')">Edit</button>
                                        @if ($member['status'] === 'invited')
                                            <button type="button" class="text-action" wire:click="resendTeamInvitation('{{ $member['id'] }}')">Resend</button>
                                        @endif
                                        <button type="button" class="text-action text-danger" wire:click="revokeTeamMember('{{ $member['id'] }}')" wire:confirm="Remove this person’s business access?">Revoke</button>
                                    @endunless
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($editingTeamMemberId !== '')
                        @php($editingMember = collect($team)->firstWhere('id', $editingTeamMemberId))
                        <form class="form-panel compact-form" wire:submit="saveTeamMemberRole">
                            <div><p class="eyebrow">Update access</p><h3>{{ data_get($editingMember, 'name') ?: data_get($editingMember, 'email') }}</h3></div>
                            <label class="field"><span>Role</span><select wire:model="editingTeamRole"><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="manager">Manager</option><option value="finance">Finance</option></select></label>
                            @error('editingTeamRole') <p class="field-error">{{ $message }}</p> @enderror
                            <div class="form-actions"><button type="button" class="button button-secondary" wire:click="cancelTeamMemberEdit">Cancel</button><button type="submit" class="button button-primary">Save role</button></div>
                        </form>
                    @endif
                    <x-business-pagination resource="team" label="team members" :pagination="$pagination" />
                </section>
            @endif
        </section>
    @else
        <div class="access-shell">
            <header class="access-brand"><span class="brand-art"><img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="Zigpaw"><img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw"></span><span>Business</span></header>
            <section class="access-card">
                @if ($state === 'choose_organization')
                    <p class="eyebrow">Business workspace</p><h1>Choose where you’re working.</h1><p>Each workspace keeps locations, bookings, staff and financial information isolated.</p>
                    <div class="organization-list">@foreach ($organizations as $organization)<button type="button" wire:click="selectOrganization('{{ $organization['id'] }}')"><span>{{ $organization['name'] }}</span><small>{{ str($organization['role'])->headline() }}</small></button>@endforeach</div>
                @elseif ($state === 'forbidden')
                    <p class="eyebrow">Business access</p><h1>No active business workspace.</h1><p>{{ $message ?: 'Ask the business owner to invite you, or sign in with another account.' }}</p><a class="button button-primary" href="{{ route('auth.login') }}">Use another account</a>
                @elseif ($state === 'unavailable')
                    <p class="eyebrow">Connection check</p><h1>The business workspace is taking a pause.</h1><p>{{ $message ?: 'Your information is safe. Try again shortly.' }}</p><a class="button button-primary" href="{{ route('dashboard') }}">Try again</a>
                @else
                    <p class="eyebrow">Zigpaw business</p><h1>Manage the work around every pet.</h1><p>{{ $message ?: 'Keep verified locations, customer booking requests, services, team access and optional partner programs together.' }}</p><a class="button button-primary" href="{{ route('auth.login') }}">Sign in</a>
                @endif
            </section>
        </div>
    @endif

    @if ($notice)
        <button type="button" class="toast" wire:click="dismissNotice" aria-label="Dismiss notification">
            <span class="toast-check">✓</span><span>{{ $notice }}</span>
        </button>
    @endif
</main>
