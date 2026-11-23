@props([
    'id' => null,
    'kind' => 'modal',
    'open' => false,
    'title' => null,
    'eyebrow' => null,
    'closeAction' => null,
    'closeOnBackdrop' => true,
    'message' => null,
])

@php
    $drawer = $kind === 'drawer';
    $overlayId = $id ?: $kind.'-'.str($title ?: 'dialog')->slug();
    $headingId = $overlayId.'-heading';
    $hasFooter = isset($footer) || isset($actions);
@endphp

<div
    id="{{ $overlayId }}"
    {{ $attributes->class(['overlay'])->except('id') }}
    data-overlay
    data-overlay-kind="{{ $kind }}"
    data-overlay-open-state="{{ $open ? 'true' : 'false' }}"
    data-overlay-backdrop-close="{{ $closeOnBackdrop ? 'true' : 'false' }}"
    aria-hidden="{{ $open ? 'false' : 'true' }}"
    @unless ($open) hidden inert @endunless
>
    <section
        @class(['business-drawer' => $drawer, 'business-modal' => ! $drawer])
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $headingId }}"
        tabindex="-1"
        data-overlay-panel
    >
        <header class="overlay-header">
            <div id="{{ $headingId }}" class="overlay-heading">
                @if (isset($heading))
                    {{ $heading }}
                @else
                    @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
                    <h2>{{ $title }}</h2>
                @endif
            </div>
            <x-business.overlay-close :action="$closeAction" :label="$drawer ? 'Close drawer' : 'Close dialog'" />
        </header>

        <div class="overlay-body" data-overlay-scroll-body>
            @if ($open)
                @if ($message)<p class="inline-alert" role="alert">{{ $message }}</p>@endif
                <x-business.validation-summary />
            @endif
            {{ $body ?? $slot }}
        </div>

        <button type="button" class="overlay-scroll-cue" data-overlay-scroll-cue hidden aria-label="Show more content">
            <span>More below</span>
            <span aria-hidden="true">↓</span>
        </button>

        @if ($hasFooter)
            <footer class="overlay-footer">
                @if (isset($footer))
                    <div class="overlay-footer-copy">{{ $footer }}</div>
                @endif
                @if (isset($actions))
                    <div class="overlay-actions">{{ $actions }}</div>
                @endif
            </footer>
        @endif
    </section>
</div>
