@if ($state === 'ready')
    <div class="page-stack">
        <header class="page-heading">
            <div>
                <p class="eyebrow">{{ $identity['organization']['name'] ?? 'Clinical workspace' }}</p>
                <h1>Clinical overview</h1>
                <p>Only family-approved patient grants and records appear here.</p>
            </div>
            @if (count($organizations) > 1)
                <button class="secondary-button" type="button" wire:click="changeOrganization">Change organisation</button>
            @endif
        </header>

        <section class="metric-strip" aria-label="Clinical workspace totals">
            <a href="{{ route('patients.index') }}" wire:navigate><span>Active patients</span><strong>{{ data_get($overview, 'grants.active', 0) }}</strong></a>
            <div><span>Expiring soon</span><strong>{{ data_get($overview, 'grants.expiring_soon', 0) }}</strong></div>
            <a href="{{ route('submissions.index', ['status' => 'pending']) }}" wire:navigate><span>Pending review</span><strong>{{ data_get($overview, 'submissions.pending', 0) }}</strong></a>
            <a href="{{ route('submissions.index') }}" wire:navigate><span>Total submissions</span><strong>{{ data_get($overview, 'submissions.total', 0) }}</strong></a>
        </section>

        <div class="overview-grid">
            <section class="primary-panel">
                <p class="eyebrow">Start here</p>
                <h2>Open an approved patient grant</h2>
                <p>Review the care context a family chose to share, then submit visit records only when the grant permits it.</p>
                <a class="primary-button" href="{{ route('patients.index') }}" wire:navigate>View patients</a>
            </section>

            <section class="plain-section">
                <div class="section-heading-inline">
                    <div><p class="eyebrow">Submission queue</p><h2>Family review remains in control</h2></div>
                    <a href="{{ route('submissions.index') }}" wire:navigate>View history</a>
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
                        <button type="button" wire:click="selectOrganization('{{ $organization['id'] }}')"><span>{{ $organization['name'] }}</span><small>{{ str($organization['role'])->headline() }}</small></button>
                    @endforeach
                </div>
            @elseif ($state === 'forbidden')
                <p class="eyebrow">Organisation access</p><h1>Clinical access is not available</h1>
                <p>{{ $message ?: 'This account does not belong to an active, verified veterinary organisation.' }}</p>
                <form method="POST" action="{{ route('auth.logout') }}">
                    @csrf
                    <button class="primary-button" type="submit">Use another account</button>
                </form>
            @elseif ($state === 'unavailable')
                <p class="eyebrow">Connection check</p><h1>Zigpaw clinical is temporarily unavailable</h1>
                <p>{{ $message ?: 'Your account is safe. Please try again shortly.' }}</p>
                <a class="secondary-button" href="{{ route('dashboard') }}">Try again</a>
            @else
                <p class="eyebrow">Zigpaw clinical</p><h1>Care records, with families in control</h1>
                <p>Sign in through Zigpaw identity to open only the patient information a family has explicitly shared with your clinical organisation.</p>
                <a class="primary-button" href="{{ route('auth.login') }}">Sign in securely</a>
                <div class="trust-note"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.7 2.8 8 7 10 4.2-2 7-5.3 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg><span>Your clinical session is protected, and patient access stays limited to family-approved grants.</span></div>
            @endif
        </section>
    </div>
@endif
