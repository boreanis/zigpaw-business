@if ($state === 'ready')
    <div class="page-stack">
        <header class="page-heading">
            <div>
                <p class="eyebrow">{{ $identity['organization']['name'] ?? 'Business clinical workspace' }}</p>
                <h1>Clinical overview</h1>
                <p>Only family-approved patient grants and records appear here.</p>
            </div>
            @if (count($organizations) > 1)
                <x-clinical.button wire:click="changeOrganization" wire:loading.attr="disabled" wire:target="changeOrganization">Change organisation</x-clinical.button>
            @endif
        </header>

        <section class="metric-strip" aria-label="Business clinical workspace totals">
            <a href="{{ route('clinical.patients.index') }}" wire:navigate><span>Active patients</span><strong>{{ data_get($overview, 'grants.active', 0) }}</strong></a>
            <div><span>Expiring soon</span><strong>{{ data_get($overview, 'grants.expiring_soon', 0) }}</strong></div>
            <a href="{{ route('clinical.submissions.index', ['status' => 'pending']) }}" wire:navigate><span>Pending review</span><strong>{{ data_get($overview, 'submissions.pending', 0) }}</strong></a>
            <a href="{{ route('clinical.submissions.index') }}" wire:navigate><span>Total submissions</span><strong>{{ data_get($overview, 'submissions.total', 0) }}</strong></a>
        </section>

        <div class="overview-grid">
            <section class="primary-panel">
                <p class="eyebrow">Start here</p>
                <h2>Open an approved patient grant</h2>
                <p>Review the care context a family chose to share, then submit visit records only when the grant permits it.</p>
                <x-clinical.button variant="primary" :href="route('clinical.patients.index')" wire:navigate>View patients</x-clinical.button>
            </section>

            <section class="plain-section">
                <div class="section-heading-inline">
                    <div><p class="eyebrow">Submission queue</p><h2>Family review remains in control</h2></div>
                    <a href="{{ route('clinical.submissions.index') }}" wire:navigate>View history</a>
                </div>
                <p>Clinical records are held as pending until the profile manager reviews them. Existing approved history is never changed directly from this workspace.</p>
                <div class="status-key">
                    <span><i class="dot dot-amber"></i>{{ data_get($overview, 'submissions.pending', 0) }} pending</span>
                    <span><i class="dot dot-green"></i>{{ data_get($overview, 'submissions.approved', 0) }} approved</span>
                    <span><i class="dot dot-blue"></i>{{ data_get($overview, 'submissions.partially_approved', 0) }} partly approved</span>
                </div>
            </section>
        </div>

        @if (count(data_get($overview, 'locations', [])) > 0)
            <section class="plain-section">
                <div class="section-heading-inline">
                    <div><p class="eyebrow">Organisation locations</p><h2>Available clinical locations</h2></div>
                    <span>{{ count(data_get($overview, 'locations', [])) }} listed</span>
                </div>
                <div class="compact-list">
                    @foreach (data_get($overview, 'locations', []) as $location)
                        <div><strong>{{ $location['name'] }}</strong><span>{{ collect([$location['city'] ?? null, $location['state'] ?? null, $location['country_code'] ?? null])->filter()->join(' · ') }}</span></div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@else
    <div class="auth-shell">
        <section class="auth-card">
            @if ($state === 'choose_organization')
                <p class="eyebrow">Clinical organisation</p>
                <h1>Choose your workspace</h1>
                <p>Each patient grant and submission stays within the clinical organisation selected here.</p>
                <div class="organization-list">
                    @foreach ($organizations as $organization)
                        <x-clinical.button variant="organization" wire:click="selectOrganization('{{ $organization['id'] }}')" wire:loading.attr="disabled" wire:target="selectOrganization"><span>{{ $organization['name'] }}</span><small>{{ str($organization['role'])->headline() }}</small></x-clinical.button>
                    @endforeach
                </div>
            @elseif ($state === 'forbidden')
                <p class="eyebrow">Organisation access</p><h1>Clinical access is not available</h1>
                <p>{{ $message ?: 'This account does not belong to an active, verified veterinary organisation.' }}</p>
                <form method="POST" action="{{ route('clinical.auth.logout') }}">
                    @csrf
                    <x-clinical.button variant="primary" type="submit">Use another account</x-clinical.button>
                </form>
            @elseif ($state === 'unavailable')
                <p class="eyebrow">Connection check</p><h1>Zigpaw Business clinical is temporarily unavailable</h1>
                <p>{{ $message ?: 'Your account is safe. Please try again shortly.' }}</p>
                <x-clinical.button :href="route('clinical.dashboard')">Try again</x-clinical.button>
            @else
                <p class="eyebrow">Zigpaw Business clinical</p><h1>Care records, with families in control</h1>
                <p>Sign in through Zigpaw identity to open only the patient information a family has explicitly shared with your clinical organisation.</p>
                <x-clinical.button variant="primary" :href="route('clinical.auth.login')">Sign in securely</x-clinical.button>
                <div class="trust-note"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.7 2.8 8 7 10 4.2-2 7-5.3 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg><span>Your clinical session is protected, and patient access stays limited to family-approved grants.</span></div>
            @endif
        </section>
    </div>
@endif
