<div class="page-stack">
    <x-clinical.page-heading :title="$status === 'expiring_soon' ? 'Access expiring soon' : 'Patients shared with your team'"
        :description="$status === 'expiring_soon' ? 'Active access ending in the next 30 days, soonest first.' : 'Open a patient to see what their family has shared.'" />

    <form class="filter-bar" method="post" wire:submit="applyFilters" x-data="{ draftStatus: $wire.entangle('status') }">
        <x-clinical.search label="Search active patients by name" wire:model="search" maxlength="80" autocomplete="off" placeholder="Search active patients" x-bind:disabled="draftStatus !== 'active'" />
        <x-clinical.filter-select label="Grant status" wire:model="status" x-model="draftStatus">
                <option value="active">Active grants</option>
                <option value="expiring_soon">Expiring soon</option>
                <option value="expired">Expired grants</option>
                <option value="revoked">Revoked grants</option>
                <option value="all">All grants</option>
        </x-clinical.filter-select>
        <x-clinical.button type="submit" wire:loading.attr="disabled" wire:target="applyFilters">Apply filters</x-clinical.button>
    </form>

    @error('search')<p class="inline-error" role="alert">{{ $message }}</p>@enderror
    @if ($status !== 'active')<p class="filter-note">Name search is available for active grants.</p>@endif

    @if ($appliedSearch !== null && ! $searchExpired)
        <p class="filter-note">
            Name filter applied.
            <x-clinical.button variant="text" wire:click="clearSearch" wire:loading.attr="disabled" wire:target="clearSearch">Clear search</x-clinical.button>
        </p>
    @endif

    @if ($searchNeedsInput)
        <x-clinical.empty-state title="Patient search could not be saved" description="Enter the patient’s name and apply the filters again." tone="error" />
    @elseif ($searchExpired)
        <x-clinical.empty-state title="This search has expired" description="Enter the patient’s name to search again.">
            <x-slot:actions><x-clinical.button wire:click="clearSearch">Show active patients</x-clinical.button></x-slot:actions>
        </x-clinical.empty-state>
    @elseif ($loadingFailed)
        <x-clinical.empty-state title="Patients could not be loaded" :description="$message" tone="error">
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></x-slot:icon>
            <x-slot:actions><x-clinical.button wire:click="retry" wire:loading.attr="disabled" wire:target="retry">Try again</x-clinical.button></x-slot:actions>
        </x-clinical.empty-state>
    @elseif ($grants === [])
        <x-clinical.empty-state
            :title="$appliedSearch !== null ? 'No matching active patient' : 'No grants in this view'"
            :description="$appliedSearch !== null ? 'Try a different patient name or clear the search.' : ($status === 'expiring_soon' ? 'No active access expires in the next 30 days.' : 'A patient appears only after their profile manager shares access with this clinical organisation.')"
        >
            <x-slot:icon><svg viewBox="0 0 24 24" fill="none"><path d="M8.5 11.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm7 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM5.5 17c.8-2.1 2.5-3.2 5-3.2h3c2.5 0 4.2 1.1 5 3.2"/><path d="M4 5.5A2.5 2.5 0 1 0 4 10m16-4.5a2.5 2.5 0 1 1 0 4.5"/></svg></x-slot:icon>
        </x-clinical.empty-state>
    @else
        <section class="patient-grid" aria-label="Patient access grants">
            @foreach ($grants as $grant)
                @php
                    $grantStatus = (string) ($grant['status'] ?? 'unknown');
                    $statusTone = match ($grantStatus) { 'active' => 'good', 'expired' => 'warn', 'revoked' => 'danger', default => 'neutral' };
                    $petName = data_get($grant, 'pet.name');
                    $title = is_string($petName) && $petName !== '' ? $petName : str((string) ($grant['purpose'] ?? 'Restricted patient grant'))->headline();
                    $details = collect([data_get($grant, 'pet.species'), data_get($grant, 'pet.breed')])->filter()->join(' · ');
                @endphp
                <a class="patient-card" href="{{ route('clinical.patients.show', ['grantId' => $grant['id']]) }}" wire:navigate>
                    <div class="patient-avatar" aria-hidden="true">{{ str($title)->substr(0, 1)->upper() }}</div>
                    <div class="patient-card-body">
                        <div class="patient-card-title"><h2>{{ $title }}</h2><x-clinical.status :tone="$statusTone">{{ str($grantStatus)->headline() }}</x-clinical.status></div>
                        <p>{{ $details !== '' ? $details : 'No species or breed details available.' }}</p>
                        <div class="patient-meta">
                            @if (data_get($grant, 'location.name'))<span>{{ data_get($grant, 'location.name') }}</span>@endif
                            <span><x-clinical.grant-timing :grant="$grant" /></span>
                        </div>
                    </div>
                    <svg class="row-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7"/></svg>
                </a>
            @endforeach
        </section>

        <x-clinical.pagination :current="$meta['current_page'] ?? 1" :last="$meta['last_page'] ?? 1" label="Patient pages" />
    @endif

    <div class="loading-line" wire:loading role="status">Updating patient access…</div>
</div>
