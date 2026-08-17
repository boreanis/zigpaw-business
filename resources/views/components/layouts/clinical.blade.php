<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'Zigpaw Business clinical workspace' }}</title>
    @vite(['resources/css/business.css', 'resources/js/business.js'])
    @livewireStyles
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to clinical workspace</a>
    <div class="clinical-app-frame">
        <header class="clinical-app-header">
            <a class="clinical-brand" href="{{ route('clinical.dashboard') }}" wire:navigate aria-label="Zigpaw Business clinical workspace home">
                <img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="Zigpaw">
                <img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw">
                <span>Business · Clinical</span>
            </a>

            @if (session()->has('portal.organization_id'))
                <nav class="clinical-nav" aria-label="Clinical workspace">
                    <a @class(['active' => request()->routeIs('clinical.dashboard')]) href="{{ route('clinical.dashboard') }}" wire:navigate>Overview</a>
                    <a @class(['active' => request()->routeIs('clinical.patients.*')]) href="{{ route('clinical.patients.index') }}" wire:navigate>Patients</a>
                    <a @class(['active' => request()->routeIs('clinical.submissions.*')]) href="{{ route('clinical.submissions.index') }}" wire:navigate>Submissions</a>
                </nav>
            @endif

            <div class="clinical-header-actions">
                @if (session()->has('portal.organization_id'))
                    <span class="organization-chip">{{ session('portal.organization_name', 'Clinical workspace') }}</span>
                @endif
                @if (session()->has('platform.oauth.clinical_token_handle'))
                    <form method="POST" action="{{ route('clinical.auth.logout') }}">
                        @csrf
                        <button class="text-button" type="submit">Sign out</button>
                    </form>
                @endif
            </div>
        </header>

        @if (session('success'))
            <div class="toast toast-success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="toast toast-error" role="alert">{{ session('error') }}</div>
        @endif

        <main id="main-content" class="clinical-workspace">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
