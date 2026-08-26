@props(['status' => 500])

@php
    $status = (string) $status;
    $states = [
        '403' => [
            'title' => 'You do not have access to this page',
            'description' => 'Your current workspace session cannot open this area. Return to the workspace or sign in with an approved account.',
            'primary' => 'Workspace home',
            'retry' => false,
        ],
        '404' => [
            'title' => 'That page is not here',
            'description' => 'The page may have moved, or the address may be incomplete. Return to a safe workspace starting point.',
            'primary' => 'Workspace home',
            'retry' => false,
        ],
        '419' => [
            'title' => 'That form has expired',
            'description' => 'For your security, this form can no longer be submitted. Start again from the Business sign-in page.',
            'primary' => 'Start again',
            'retry' => false,
        ],
        '429' => [
            'title' => 'Please take a short pause',
            'description' => 'There have been a lot of requests in a short time. Wait a moment, then try again.',
            'primary' => 'Try again',
            'retry' => true,
        ],
        '500' => [
            'title' => 'We could not complete that request',
            'description' => 'Your workspace is still safe. Try again, or return home and continue from there.',
            'primary' => 'Try again',
            'retry' => true,
        ],
        '502' => [
            'title' => 'The workspace is briefly out of reach',
            'description' => 'Business services could not reach a required service. Try again in a moment.',
            'primary' => 'Try again',
            'retry' => true,
        ],
        '503' => [
            'title' => 'The workspace is taking a short break',
            'description' => 'Business services are temporarily unavailable. Please try again shortly.',
            'primary' => 'Try again',
            'retry' => true,
        ],
        '504' => [
            'title' => 'The workspace took too long to respond',
            'description' => 'Business services are temporarily slow. Try again in a moment.',
            'primary' => 'Try again',
            'retry' => true,
        ],
    ];
    $state = $states[$status] ?? $states['500'];
    $signedIn = session()->has('platform.oauth.token_handle');
    $home = $signedIn ? route('dashboard') : route('auth.login');
    $homeLabel = $signedIn ? 'Workspace home' : 'Business sign in';
    $canRetryGet = request()->isMethod('get');
    $primaryHref = $state['retry'] && $canRetryGet ? url()->current() : ($status === '419' ? route('auth.login') : $home);
    $primaryLabel = $state['retry'] && ! $canRetryGet
        ? $homeLabel
        : ($state['primary'] === 'Workspace home' ? $homeLabel : $state['primary']);
@endphp

<main id="main-content" class="business-error-shell" tabindex="-1">
    <section class="business-error-page" aria-labelledby="business-error-title">
        <a class="business-error-brand" href="{{ $home }}" aria-label="Zigpaw Business home">
            <span class="brand-art">
                <img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="Zigpaw">
                <img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="">
            </span>
            <span>Business</span>
        </a>
        <div class="business-error-mark" aria-hidden="true">{{ $status }}</div>
        <p class="business-error-eyebrow">Zigpaw Business</p>
        <h1 id="business-error-title">{{ $state['title'] }}</h1>
        <p class="business-error-description">{{ $state['description'] }}</p>
        <div class="business-error-actions">
            <x-business.button variant="primary" :href="$primaryHref">{{ $primaryLabel }}</x-business.button>
            @if (($state['retry'] && $canRetryGet) || $state['primary'] !== 'Workspace home')
                <x-business.button variant="secondary" :href="$home">{{ $homeLabel }}</x-business.button>
            @endif
        </div>
    </section>
</main>
