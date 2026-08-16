<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'Zigpaw clinical' }}</title>
    @vite(['resources/css/vets.css', 'resources/js/vets.js'])
    @livewireStyles
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to clinical workspace</a>
    <div class="app-frame">
        <header class="app-header">
            <a class="brand" href="{{ route('dashboard') }}" wire:navigate aria-label="Zigpaw clinical home">
                <img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="Zigpaw">
                <img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw">
                <span>Clinical</span>
            </a>

            @if (session()->has('portal.organization_id'))
                <nav class="desktop-nav" aria-label="Clinical workspace">
                    <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}" wire:navigate>Overview</a>
                    <a @class(['active' => request()->routeIs('patients.*')]) href="{{ route('patients.index') }}" wire:navigate>Patients</a>
                    <a @class(['active' => request()->routeIs('submissions.*')]) href="{{ route('submissions.index') }}" wire:navigate>Submissions</a>
                </nav>

            @endif

            <div class="header-actions">
                @if (session()->has('portal.organization_id'))
                    <span class="organization-chip" title="Active clinical organisation">{{ session('portal.organization_name', 'Clinical workspace') }}</span>
                @endif
                <button class="icon-button" id="theme-toggle" type="button" aria-label="Change colour appearance" title="Change colour appearance">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.64 5.64l1.42 1.42m9.88 9.88 1.42 1.42m0-12.72-1.42 1.42M7.06 16.94l-1.42 1.42M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                </button>
                @if (session()->has('platform.oauth.token_handle'))
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button class="text-button" type="submit">Sign out</button>
                    </form>
                @endif
            </div>
        </header>

        @if (session()->has('portal.organization_id'))
            <nav class="mobile-nav" aria-label="Clinical workspace">
                <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}" wire:navigate>Overview</a>
                <a @class(['active' => request()->routeIs('patients.*')]) href="{{ route('patients.index') }}" wire:navigate>Patients</a>
                <a @class(['active' => request()->routeIs('submissions.*')]) href="{{ route('submissions.index') }}" wire:navigate>Submissions</a>
            </nav>
        @endif

        @if (session('success'))
            <div class="toast toast-success" role="status">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m7 12 3 3 7-7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="toast toast-error" role="alert">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <main id="main-content" class="workspace">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
