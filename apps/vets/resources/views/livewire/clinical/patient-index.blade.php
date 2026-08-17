<div class="page-stack">
    <header class="page-heading">
        <div>
            <p class="eyebrow">Patient access</p>
            <h1>Patients shared with your team</h1>
            <p>Each profile and clinical action is limited by its current family-approved grant.</p>
        </div>
    </header>

    <form class="filter-bar" wire:submit="applyFilters">
        <label class="search-field">
            <span class="sr-only">Search active patients by name</span>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
            <input wire:model="search" type="search" maxlength="80" placeholder="Search active patients" @disabled($status !== 'active')>
        </label>
        <label class="filter-select">
            <span class="sr-only">Grant status</span>
            <select wire:model="status">
                <option value="active">Active grants</option>
                <option value="expired">Expired grants</option>
                <option value="revoked">Revoked grants</option>
                <option value="all">All grants</option>
            </select>
        </label>
        <button class="secondary-button" type="submit">Apply</button>
    </form>

    @error('search')<p class="inline-error">{{ $message }}</p>@enderror
    @if ($status !== 'active')<p class="filter-note">Name search is available for active grants.</p>@endif

    @if ($loadingFailed)
        <section class="empty-state" role="alert">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            <h2>Patients could not be loaded</h2>
            <p>{{ $message }}</p>
            <button class="secondary-button" type="button" wire:click="retry">Try again</button>
        </section>
    @elseif ($grants === [])
        <section class="empty-state">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M8.5 11.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm7 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM5.5 17c.8-2.1 2.5-3.2 5-3.2h3c2.5 0 4.2 1.1 5 3.2"/><path d="M4 5.5A2.5 2.5 0 1 0 4 10m16-4.5a2.5 2.5 0 1 1 0 4.5"/></svg>
            <h2>{{ $search !== '' ? 'No matching active patient' : 'No grants in this view' }}</h2>
            <p>{{ $search !== '' ? 'Try a different patient name or clear the search.' : 'A patient appears only after their profile manager shares access with this clinical organisation.' }}</p>
            @if ($search !== '')<button class="text-button" type="button" wire:click="clearSearch">Clear search</button>@endif
        </section>
    @else
        <section class="patient-grid" aria-label="Patient access grants">
            @foreach ($grants as $grant)
                @php
                    $grantStatus = (string) ($grant['status'] ?? 'unknown');
                    $statusClass = match ($grantStatus) { 'active' => 'status-green', 'expired' => 'status-amber', 'revoked' => 'status-red', default => 'status-neutral' };
                    $petName = data_get($grant, 'pet.name');
                    $title = is_string($petName) && $petName !== '' ? $petName : str((string) ($grant['purpose'] ?? 'Restricted patient grant'))->headline();
                    $details = collect([data_get($grant, 'pet.species'), data_get($grant, 'pet.breed')])->filter()->join(' · ');
                @endphp
                <a class="patient-card" href="{{ route('patients.show', ['grantId' => $grant['id']]) }}" wire:navigate>
                    <div class="patient-avatar" aria-hidden="true">{{ str($title)->substr(0, 1)->upper() }}</div>
                    <div class="patient-card-body">
                        <div class="patient-card-title"><h2>{{ $title }}</h2><span class="status-badge {{ $statusClass }}">{{ str($grantStatus)->headline() }}</span></div>
                        <p>{{ $details !== '' ? $details : 'Profile details are not included in this grant.' }}</p>
                        <div class="patient-meta">
                            @if (data_get($grant, 'location.name'))<span>{{ data_get($grant, 'location.name') }}</span>@endif
                            @if ($grant['expires_at'] ?? null)<span>Ends {{ str((string) $grant['expires_at'])->before('T') }}</span>@else<span>No set end date</span>@endif
                        </div>
                    </div>
                    <svg class="row-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7"/></svg>
                </a>
            @endforeach
        </section>

        @if ((int) ($meta['last_page'] ?? 1) > 1)
            <nav class="pagination" aria-label="Patient pages">
                <button class="secondary-button" type="button" wire:click="previousPage" @disabled((int) ($meta['current_page'] ?? 1) <= 1)>Previous</button>
                <span>Page {{ $meta['current_page'] ?? 1 }} of {{ $meta['last_page'] ?? 1 }}</span>
                <button class="secondary-button" type="button" wire:click="nextPage" @disabled((int) ($meta['current_page'] ?? 1) >= (int) ($meta['last_page'] ?? 1))>Next</button>
            </nav>
        @endif
    @endif

    <div class="loading-line" wire:loading role="status">Updating patient access…</div>
</div>
