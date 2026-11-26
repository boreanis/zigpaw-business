<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'Zigpaw Partners clinical workspace' }}</title>
    <x-business.theme-init />
    @vite(['resources/css/business.css', 'resources/js/business.js'])
    @livewireStyles
</head>
<body>
    @php
        $hasClinicalWorkspace = session()->has('platform.oauth.clinical_token_handle')
            && session()->has('clinical_portal.organization_id');
    @endphp
    <a class="skip-link" href="#main-content">Skip to clinical workspace</a>
    <div class="clinical-app-frame">
        <header class="clinical-app-header">
            <x-business.brand :href="route('clinical.dashboard')" label="Partners · Clinical" home-label="Zigpaw Partners clinical workspace home" wire:navigate />

            @if ($hasClinicalWorkspace)
                <nav class="clinical-nav" aria-label="Clinical workspace">
                    @foreach (['clinical.dashboard' => ['Overview', 'clinical.dashboard'], 'clinical.patients.index' => ['Patients', 'clinical.patients.*'], 'clinical.submissions.index' => ['Submissions', 'clinical.submissions.*']] as $destination => [$label, $section])
                        <a href="{{ route($destination) }}" @class(['active' => request()->routeIs($section)]) @if (request()->routeIs($section)) aria-current="{{ request()->routeIs($destination) ? 'page' : 'location' }}" @endif wire:navigate>{{ $label }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="clinical-header-actions">
                <x-business.appearance />
                @if ($hasClinicalWorkspace)
                    <span class="organization-chip">{{ session('clinical_portal.organization_name', 'Clinical workspace') }}</span>
                @endif
                @if (session()->has('platform.oauth.clinical_token_handle'))
                    <form method="POST" action="{{ route('clinical.auth.logout') }}">
                        @csrf
                        <x-clinical.button variant="text" type="submit">Sign out</x-clinical.button>
                    </form>
                @endif
            </div>
        </header>

        <x-business.toast-region>
            @if (session('success'))
                <x-business.toast>{{ session('success') }}</x-business.toast>
            @endif
            @if (session('error'))
                <x-business.toast tone="error">{{ session('error') }}</x-business.toast>
            @endif
        </x-business.toast-region>

        <main id="main-content" class="clinical-workspace" tabindex="-1">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
