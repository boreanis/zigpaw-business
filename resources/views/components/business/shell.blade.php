@props([
    'section',
    'navigationSections' => [],
    'organizations' => [],
    'organizationId' => null,
    'identity' => [],
    'message' => null,
])

<div class="business-shell">
    <aside id="business-sidebar" class="business-sidebar" aria-label="Business workspace" aria-hidden="false" data-sidebar>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close navigation">
            <span aria-hidden="true">×</span>
        </button>
        <a href="{{ route('dashboard') }}" class="brand-link" wire:navigate>
            <span class="brand-art"><img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="Zigpaw"><img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw"></span>
            <span>Business</span>
        </a>
        <nav class="business-nav" aria-label="Business sections">
            @foreach ($navigationSections as $key => $navigation)
                <button type="button" class="business-nav-item {{ $section === $key ? 'is-active' : '' }}" wire:click="showSection('{{ $key }}')" @if ($section === $key) aria-current="page" @endif>
                    <x-business-icon :name="$navigation['icon']" />
                    <span>{{ $navigation['label'] }}</span>
                </button>
            @endforeach
        </nav>
        <button class="theme-control sidebar-theme-control" type="button" data-theme-toggle aria-label="Change appearance">
            <span aria-hidden="true" data-theme-icon>◐</span><span data-theme-label>System</span>
        </button>
        <div class="sidebar-account">
            <p>{{ data_get($identity, 'organization.name', 'Business workspace') }}</p>
            <span>{{ str(data_get($identity, 'membership.role', 'member'))->headline() }}</span>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="text-action">Sign out</button>
            </form>
        </div>
    </aside>
    <main id="main-content" class="workspace-main">
        <header class="workspace-header">
            <button type="button" class="sidebar-menu-toggle" data-sidebar-toggle aria-controls="business-sidebar" aria-expanded="false">
                <span class="sidebar-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                <span>Menu</span>
            </button>
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
        <button type="button" class="sidebar-backdrop" data-sidebar-close aria-label="Close navigation" hidden></button>
        <div class="mobile-sections" role="navigation" aria-label="Business sections">
            <select wire:change="showSection($event.target.value)" aria-label="Current section">
                @foreach ($navigationSections as $key => $navigation)
                    <option value="{{ $key }}" @selected($section === $key)>{{ $navigation['label'] }}</option>
                @endforeach
            </select>
        </div>
        @if ($message)
            <x-business.error-state :message="$message" title="This workspace needs a retry">
                <x-slot:actions>
                    <x-business.button variant="secondary" wire:click="showSection('{{ $section }}')" wire:loading.attr="disabled" wire:target="showSection">Try again</x-business.button>
                </x-slot:actions>
            </x-business.error-state>
        @endif
        <div class="workspace-loading" wire:loading.delay wire:target="showSection,selectOrganization,changePage,searchClaimableProviders,saveBusinessProfile,saveProvider,saveOffering,saveBookingConfiguration,acceptBooking,declineBooking,completeBooking,submitProviderClaim,inviteTeamMember,saveTeamMemberRole,revokeTeamMember">
            <x-business.loading-state label="Updating workspace" />
        </div>
        {{ $slot }}
    </main>
</div>
