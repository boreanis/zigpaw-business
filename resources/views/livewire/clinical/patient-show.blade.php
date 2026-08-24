<div class="page-stack">
    <a class="back-link" href="{{ route('clinical.patients.index') }}" wire:navigate><span aria-hidden="true">←</span> Patients</a>

    @if ($loadingFailed)
        <x-clinical.empty-state title="Patient access is unavailable" :description="$message" tone="error" :heading-level="1">
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></x-slot:icon>
            <x-slot:actions>
                <x-clinical.button :href="route('clinical.patients.index')" wire:navigate>Back to patients</x-clinical.button>
                <x-clinical.button variant="text" wire:click="retry" wire:loading.attr="disabled" wire:target="retry">Try again</x-clinical.button>
            </x-slot:actions>
        </x-clinical.empty-state>
    @else
        @php
            $petName = data_get($grant, 'pet.name');
            $displayName = is_string($petName) && $petName !== '' ? $petName : 'Restricted patient';
            $capabilities = is_array($grant['capabilities'] ?? null) ? $grant['capabilities'] : [];
            $status = (string) ($grant['status'] ?? 'unknown');
            $statusTone = match ($status) { 'active' => 'good', 'expired' => 'warn', 'revoked' => 'danger', default => 'neutral' };
        @endphp

        <header class="patient-hero">
            <div class="patient-avatar patient-avatar-large" aria-hidden="true">{{ str($displayName)->substr(0, 1)->upper() }}</div>
            <div class="patient-hero-copy">
                <div class="heading-with-status"><p class="eyebrow">Family-approved access</p><x-clinical.status :tone="$statusTone">{{ str($status)->headline() }}</x-clinical.status></div>
                <h1>{{ $displayName }}</h1>
                <p>{{ collect([data_get($grant, 'pet.species'), data_get($grant, 'pet.breed')])->filter()->join(' · ') ?: 'Profile details are not included in this grant.' }}</p>
            </div>
            @if (($capabilities['submit_care'] ?? false) === true)
                <x-clinical.button variant="primary" :href="route('clinical.patients.submissions.create', ['grantId' => $grantId])" wire:navigate>Submit care records</x-clinical.button>
            @endif
        </header>

        <section class="access-summary" aria-label="Grant capabilities">
            <div><span>Purpose</span><strong>{{ str((string) ($grant['purpose'] ?? 'Clinical care'))->headline() }}</strong></div>
            <div><span>Location</span><strong>{{ data_get($grant, 'location.name', 'Organisation-wide') }}</strong></div>
            <div><span>Access ends</span><strong>{{ ($grant['expires_at'] ?? null) ? str((string) $grant['expires_at'])->before('T') : 'No set end date' }}</strong></div>
            <div class="capability-list"><span>Allowed</span><strong>
                @foreach (['read_profile' => 'Profile', 'read_care' => 'Care history', 'submit_care' => 'Submit records', 'read_media' => 'Shared files'] as $capability => $label)
                    @if (($capabilities[$capability] ?? false) === true)<i>{{ $label }}</i>@endif
                @endforeach
            </strong></div>
        </section>

        @if (($capabilities['read_care'] ?? false) === true)
            <section class="plain-section">
                <div class="section-heading-inline">
                    <div><p class="eyebrow">Current care context</p><h2>Information approved for sharing</h2></div>
                    <span>Read only</span>
                </div>

                <div class="clinical-summary-grid">
                    <div>
                        <span class="data-label">Latest weight</span>
                        @if (count($care['weights'] ?? []) > 0)
                            <strong>{{ data_get($care, 'weights.0.weight') }} {{ data_get($care, 'weights.0.unit') }}</strong>
                            <small>{{ data_get($care, 'weights.0.recorded_date') }}</small>
                        @else<strong>Not shared</strong>@endif
                    </div>
                    <div>
                        <span class="data-label">Active medication records</span>
                        <strong>{{ count($care['medications'] ?? []) }}</strong>
                    </div>
                    <div>
                        <span class="data-label">Condition and allergy records</span>
                        <strong>{{ count($care['conditions'] ?? []) }}</strong>
                    </div>
                    <div>
                        <span class="data-label">Vaccination records</span>
                        <strong>{{ count($care['vaccinations'] ?? []) }}</strong>
                    </div>
                </div>
            </section>

            <div class="clinical-columns">
                <section class="plain-section">
                    <div class="section-heading-inline"><div><p class="eyebrow">Conditions & allergies</p><h2>Relevant history</h2></div><span>{{ count($care['conditions'] ?? []) }}</span></div>
                    @forelse ($care['conditions'] ?? [] as $condition)
                        <article class="clinical-row">
                            <div><strong>{{ $condition['name'] }}</strong><span>{{ collect([str((string) ($condition['type'] ?? 'condition'))->headline(), $condition['severity'] ?? null, $condition['status'] ?? null])->filter()->join(' · ') }}</span></div>
                            @if ($condition['treatment_plan'] ?? null)<p>{{ $condition['treatment_plan'] }}</p>@endif
                        </article>
                    @empty
                        <p class="quiet-empty">No condition or allergy records were included.</p>
                    @endforelse
                </section>

                <section class="plain-section">
                    <div class="section-heading-inline"><div><p class="eyebrow">Medications</p><h2>Shared medication records</h2></div><span>{{ count($care['medications'] ?? []) }}</span></div>
                    @forelse ($care['medications'] ?? [] as $medication)
                        <article class="clinical-row">
                            <div><strong>{{ $medication['name'] }}</strong><span>{{ collect([$medication['dosage'] ?? null, $medication['frequency'] ?? null, $medication['route'] ?? null])->filter()->join(' · ') }}</span></div>
                            @if ($medication['instructions'] ?? null)<p>{{ $medication['instructions'] }}</p>@endif
                        </article>
                    @empty
                        <p class="quiet-empty">No medication records were included.</p>
                    @endforelse
                </section>
            </div>

            <section class="plain-section">
                <div class="section-heading-inline"><div><p class="eyebrow">Clinical history</p><h2>Visits shared by the family</h2></div><span>{{ count($care['visits'] ?? []) }}</span></div>
                <div class="timeline">
                    @forelse ($care['visits'] ?? [] as $visit)
                        <article>
                            <time datetime="{{ $visit['visit_date'] ?? '' }}">{{ $visit['visit_date'] ?? 'Date not supplied' }}</time>
                            <div><strong>{{ str((string) ($visit['visit_type'] ?? 'Visit'))->headline() }}</strong><span>{{ collect([$visit['clinic_name'] ?? null, $visit['vet_name'] ?? null])->filter()->join(' · ') }}</span>
                                @if ($visit['diagnosis'] ?? null)<p><b>Assessment:</b> {{ $visit['diagnosis'] }}</p>@endif
                                @if ($visit['treatment'] ?? null)<p><b>Treatment:</b> {{ $visit['treatment'] }}</p>@endif
                                @if ($visit['home_care_instructions'] ?? null)<p><b>Home care:</b> {{ $visit['home_care_instructions'] }}</p>@endif
                            </div>
                        </article>
                    @empty
                        <p class="quiet-empty">No visit records were included.</p>
                    @endforelse
                </div>
            </section>

            <section class="plain-section">
                <div class="section-heading-inline"><div><p class="eyebrow">Vaccinations</p><h2>Shared vaccination history</h2></div><span>{{ count($care['vaccinations'] ?? []) }}</span></div>
                <div class="record-list">
                    @forelse ($care['vaccinations'] ?? [] as $vaccination)
                        <article><div><strong>{{ $vaccination['name'] }}</strong><span>{{ collect([$vaccination['manufacturer'] ?? null, $vaccination['batch_number'] ?? null, $vaccination['clinic_name'] ?? null])->filter()->join(' · ') }}</span></div><div><span>Given {{ $vaccination['administered_date'] ?? '—' }}</span><small>{{ ($vaccination['next_due_date'] ?? null) ? 'Next due '.$vaccination['next_due_date'] : 'No next due date shared' }}</small></div></article>
                    @empty
                        <p class="quiet-empty">No vaccination records were included.</p>
                    @endforelse
                </div>
            </section>
        @else
            <x-clinical.alert title="Care history is not included" description="The family can change the grant from their Zigpaw account. This clinical team cannot expand access." />
        @endif

        <section class="plain-section">
            <div class="section-heading-inline"><div><p class="eyebrow">Shared media</p><h2>Photos and clinical documents</h2></div><span>{{ count($media) }}</span></div>
            @if (($capabilities['read_media'] ?? false) !== true)
                <p class="quiet-empty">Media access is not included in this grant.</p>
            @elseif ($media === [])
                <p class="quiet-empty">The family has not shared any available files.</p>
            @else
                <div class="media-grid">
                    @foreach ($media as $item)
                        @php $mediaUrl = route('clinical.patients.media.show', ['grantId' => $grantId, 'mediaId' => $item['id']]); @endphp
                        @if (in_array($item['kind'] ?? null, ['profile_photo', 'pet_photo'], true))
                            <a href="{{ $mediaUrl }}" target="_blank" rel="noopener"><img src="{{ $mediaUrl }}" alt="{{ $item['label'] ?? 'Shared patient photo' }}"><span>{{ $item['label'] ?? 'Patient photo' }}</span></a>
                        @else
                            <a class="document-tile" href="{{ $mediaUrl }}" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg><span>{{ $item['label'] ?? 'Shared document' }}</span><small>{{ str((string) ($item['mime_type'] ?? 'document'))->after('/') }}</small></a>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <div class="loading-line" wire:loading role="status">Refreshing approved records…</div>
</div>
