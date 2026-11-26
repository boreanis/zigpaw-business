@props([
    'section',
    'navigationSections' => [],
    'organizations' => [],
    'organizationId' => null,
    'organizationSearch' => '',
    'identity' => [],
    'message' => null,
    'overlayOpen' => false,
    'pagination' => [],
])

<div class="business-shell">
    <aside id="business-sidebar" class="business-sidebar" aria-label="Partners workspace" aria-hidden="false" data-sidebar wire:ignore.self>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close navigation">
            <span aria-hidden="true">×</span>
        </button>
        <x-business.brand :href="route('dashboard')" wire:navigate />
        <nav class="business-nav" aria-label="Partners sections">
            @foreach ($navigationSections as $key => $navigation)
                <button type="button" class="business-nav-item {{ $section === $key ? 'is-active' : '' }}" wire:click="showSection('{{ $key }}')" @if ($section === $key) aria-current="page" @endif>
                    <x-business-icon :name="$navigation['icon']" />
                    <span>{{ $navigation['label'] }}</span>
                </button>
            @endforeach
        </nav>
        <x-business.button class="sidebar-clinical-link" variant="text" :href="route('clinical.dashboard')" wire:navigate>Clinical workspace</x-business.button>
        <x-business.appearance class="sidebar-theme-control" />
        <div class="sidebar-account">
            <p>{{ data_get($identity, 'organization.name', 'Partners workspace') }}</p>
            <span>{{ str(data_get($identity, 'membership.role', 'member'))->headline() }}</span>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <x-business.button type="submit" variant="text">Sign out</x-business.button>
            </form>
        </div>
    </aside>
    <button type="button" class="sidebar-backdrop" data-sidebar-backdrop data-sidebar-close aria-hidden="true" tabindex="-1" hidden wire:ignore.self></button>
    <main id="main-content" class="workspace-main" tabindex="-1" wire:ignore.self>
        <header class="workspace-header">
            <button type="button" class="sidebar-menu-toggle" data-sidebar-toggle aria-controls="business-sidebar" aria-expanded="false">
                <span class="sidebar-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                <span>Menu</span>
            </button>
            <div>
                <p class="workspace-organization">{{ data_get($identity, 'organization.name', 'Your business') }}</p>
                <h1>{{ data_get($navigationSections, $section.'.label', 'Overview') }}</h1>
            </div>
            @if (data_get($pagination, 'organizations.total', count($organizations)) > 1 || $organizationSearch !== '')
                <details class="organization-switcher" wire:ignore.self>
                    <summary>Switch organisation</summary>
                    <x-business.workspace-selector label="Organisations" resource="organizations" :items="$organizations" :selected-id="$organizationId" :selected-label="data_get($identity, 'organization.name')" search-property="organizationSearch" search-action="searchOrganizations" select-action="selectOrganization" :pagination="$pagination" />
                </details>
            @endif
        </header>
        @if ($message && ! $overlayOpen)
            <x-business.error-state :message="$message" title="This workspace needs a retry">
                <x-slot:actions>
                    <x-business.button variant="secondary" wire:click="showSection('{{ $section }}')" wire:loading.attr="disabled" wire:target="showSection">Try again</x-business.button>
                </x-slot:actions>
            </x-business.error-state>
        @endif
        @unless ($overlayOpen)<x-business.validation-summary />@endunless
        <div class="workspace-loading" wire:loading.delay wire:target="showSection,selectOrganization,changePage,searchClaimableProviders,saveBusinessProfile,saveProvider,saveOffering,saveBookingConfiguration,acceptBooking,proposeBooking,declineBooking,completeBooking,submitProviderClaim,inviteTeamMember,saveTeamMemberRole,revokeTeamMember">
            <x-business.loading-state label="Updating workspace" />
        </div>
        {{ $slot }}
    </main>
</div>
