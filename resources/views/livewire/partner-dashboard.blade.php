<main class="portal-shell">
    <header class="portal-header">
        <img src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="Zigpaw">
        <p>Partner portal</p>
    </header>

    <section class="portal-surface">
        @if ($state === 'ready')
            <p class="eyebrow">{{ $partner['business_type'] ?? 'Partner' }}</p>
            <h1>{{ $partner['business_name'] ?? 'Your Zigpaw partner space' }}</h1>
            <p>This portal reads and writes only the capabilities granted to your organisation through the Zigpaw API.</p>
            <form method="POST" action="{{ route('oauth.logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        @elseif ($state === 'forbidden')
            <p class="eyebrow">Organisation access</p>
            <h1>Choose an active partner organisation to continue.</h1>
            <p>{{ $message }}</p>
            <a class="button" href="{{ route('oauth.redirect') }}">Use another account</a>
        @elseif ($state === 'unavailable')
            <p class="eyebrow">Connection check</p>
            <h1>Zigpaw partners is temporarily unavailable.</h1>
            <p>{{ $message }}</p>
        @else
            <p class="eyebrow">Zigpaw partners</p>
            <h1>Give every pet a prepared start.</h1>
            <p>Sign in through Zigpaw identity to prepare profiles, organise groups, and hand over care details through scoped access.</p>
            <a class="button" href="{{ route('oauth.redirect') }}">Sign in</a>
        @endif
    </section>
</main>
