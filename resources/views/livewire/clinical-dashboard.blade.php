<div>
    @if ($state === 'ready')
        <div class="page-stack">
            <x-clinical.page-heading title="Clinical overview">
                @if (data_get($pagination, 'organizations.total', 0) > 1)
                    <x-slot:actions>
                        <x-clinical.button wire:click="changeOrganization" wire:loading.attr="disabled" wire:target="changeOrganization">Change organisation</x-clinical.button>
                    </x-slot:actions>
                @endif
            </x-clinical.page-heading>

            <section class="metric-strip" aria-label="Partners clinical workspace totals">
                <a href="{{ route('clinical.patients.index') }}" wire:navigate><span>Active patients</span><strong>{{ data_get($overview, 'grants.active', 0) }}</strong></a>
                <div><span>Expiring soon</span><strong>{{ data_get($overview, 'grants.expiring_soon', 0) }}</strong></div>
                <a href="{{ route('clinical.submissions.index', ['status' => 'pending']) }}" wire:navigate><span>Pending review</span><strong>{{ data_get($overview, 'submissions.pending', 0) }}</strong></a>
                <a href="{{ route('clinical.submissions.index') }}" wire:navigate><span>Total submissions</span><strong>{{ data_get($overview, 'submissions.total', 0) }}</strong></a>
            </section>

            <section class="plain-section">
                <x-clinical.section-heading title="Family review">
                    <x-slot:actions><x-clinical.button variant="text" :href="route('clinical.submissions.index')" wire:navigate>Submission history</x-clinical.button></x-slot:actions>
                </x-clinical.section-heading>
                <p>Families review submitted records before adding them to their pet’s care history.</p>
                <div class="status-key">
                    <span><i class="dot dot-green"></i>{{ data_get($overview, 'submissions.approved', 0) }} approved</span>
                    <span><i class="dot dot-blue"></i>{{ data_get($overview, 'submissions.partially_approved', 0) }} partly approved</span>
                </div>
            </section>

            @if (count(data_get($overview, 'locations', [])) > 0)
                <section class="plain-section">
                    <x-clinical.section-heading title="Clinical locations">
                        <x-slot:meta><span>{{ count(data_get($overview, 'locations', [])) }} listed</span></x-slot:meta>
                    </x-clinical.section-heading>
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
            <section class="access-card">
                @if ($state === 'choose_organization')
                    <h1>Choose your workspace</h1>
                    <p>Choose which organisation’s patients and submissions to open.</p>
                    <x-business.workspace-selector label="Organisations" resource="organizations" :items="$organizations" search-property="organizationSearch" search-action="searchOrganizations" select-action="selectOrganization" :pagination="$pagination" :list="true" />
                    @if ($message)<p role="alert">{{ $message }}</p>@endif
                @elseif ($state === 'forbidden')
                    <h1>Clinical access is not available</h1>
                    <p>{{ $message ?: 'This account does not belong to an active, verified veterinary organisation.' }}</p>
                    <form method="POST" action="{{ route('clinical.auth.logout') }}">
                        @csrf
                        <x-clinical.button variant="primary" type="submit">Use another account</x-clinical.button>
                    </form>
                @elseif ($state === 'unavailable')
                    <h1>Clinical workspace unavailable</h1>
                    <p>{{ $message ?: 'Try again in a moment.' }}</p>
                    <x-clinical.button :href="route('clinical.dashboard')">Try again</x-clinical.button>
                @else
                    <h1>Sign in to the clinical workspace</h1>
                    <p>Open patient records that families have shared with your clinical organisation.</p>
                    <div class="access-actions">
                        <x-clinical.button variant="primary" :href="route('clinical.auth.login')">Sign in securely</x-clinical.button>
                        <x-clinical.button variant="text" :href="route('dashboard')">Partners workspace</x-clinical.button>
                    </div>
                @endif
            </section>
        </div>
    @endif
</div>
