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
            'description' => 'For your security, this form can no longer be submitted. Start again from the Partners sign-in page.',
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
            'description' => 'Try again, or return to your workspace to continue.',
            'primary' => 'Try again',
            'retry' => true,
        ],
        '502' => [
            'title' => 'The workspace is briefly out of reach',
            'description' => 'Workspace services could not reach a required service. Try again in a moment.',
            'primary' => 'Try again',
            'retry' => true,
        ],
        '503' => [
            'title' => 'The workspace is taking a short break',
            'description' => 'Workspace services are temporarily unavailable. Please try again shortly.',
            'primary' => 'Try again',
            'retry' => true,
        ],
        '504' => [
            'title' => 'The workspace took too long to respond',
            'description' => 'Workspace services are temporarily slow. Try again in a moment.',
            'primary' => 'Try again',
            'retry' => true,
        ],
    ];
    $state = $states[$status] ?? $states['500'];
    $clinical = request()->is('clinical', 'clinical/*');
    $home = route($clinical ? 'clinical.dashboard' : 'dashboard');
    $homeLabel = $clinical ? 'Clinical workspace home' : 'Workspace home';
    $signIn = route($clinical ? 'clinical.auth.login' : 'auth.login');
    $canRetryGet = request()->isMethod('get');
    $primaryHref = $state['retry'] && $canRetryGet ? url()->current() : ($status === '419' ? $signIn : $home);
    $primaryLabel = $state['retry'] && ! $canRetryGet
        ? $homeLabel
        : ($state['primary'] === 'Workspace home' ? $homeLabel : $state['primary']);
@endphp

<x-business.access-layout :home="$home" :home-label="$homeLabel" :label="$clinical ? 'Partners · Clinical' : 'Partners'" aria-labelledby="business-error-title">
        <h1 id="business-error-title">{{ $state['title'] }}</h1>
        <p>{{ $state['description'] }}</p>
        <div class="access-actions">
            <x-business.button variant="primary" :href="$primaryHref">{{ $primaryLabel }}</x-business.button>
            @if ($primaryHref !== $home)
                <x-business.button variant="text" :href="$home">{{ $homeLabel }}</x-business.button>
            @endif
        </div>
</x-business.access-layout>
