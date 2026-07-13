<main class="portal-shell">
    <header class="portal-header">
        <img src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw">
        <p>Clinical portal</p>
    </header>

    <section class="portal-surface">
        @if ($state === 'ready')
            <p class="eyebrow">{{ $identity['organization']['name'] ?? 'Veterinary team' }}</p>
            <h1>Welcome, {{ $identity['name'] ?: $identity['email'] }}.</h1>
            <p>{{ $identity['clinical_access'] }}</p>
            <form method="POST" action="{{ route('oauth.logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        @elseif ($state === 'forbidden')
            <p class="eyebrow">Organisation access</p>
            <h1>This account does not have clinical portal access.</h1>
            <p>{{ $message }}</p>
            <a class="button" href="{{ route('oauth.redirect') }}">Use another account</a>
        @elseif ($state === 'unavailable')
            <p class="eyebrow">Connection check</p>
            <h1>Zigpaw clinical is temporarily unavailable.</h1>
            <p>{{ $message }}</p>
        @else
            <p class="eyebrow">Zigpaw clinical</p>
            <h1>Submit care context with the family in control.</h1>
            <p>Sign in through Zigpaw identity. Record access is always limited to a pet family's explicit, owner-approved grant.</p>
            <a class="button" href="{{ route('oauth.redirect') }}">Sign in</a>
        @endif
    </section>
</main>
