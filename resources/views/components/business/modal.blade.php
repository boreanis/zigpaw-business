@props([
    'id' => null,
    'open' => false,
    'title' => null,
    'eyebrow' => null,
    'closeAction' => null,
    'closeOnBackdrop' => true,
])

@php
    $overlayId = $id ?: 'modal-'.str($title ?: 'dialog')->slug();
    $headingId = $overlayId.'-heading';
    $hasFooter = isset($footer) || isset($actions);
@endphp

<div
    id="{{ $overlayId }}"
    {{ $attributes->class(['overlay'])->except('id') }}
    data-overlay
    data-overlay-kind="modal"
    data-overlay-open-state="{{ $open ? 'true' : 'false' }}"
    data-overlay-backdrop-close="{{ $closeOnBackdrop ? 'true' : 'false' }}"
    aria-hidden="{{ $open ? 'false' : 'true' }}"
    @unless ($open) hidden inert @endunless
>
    <section
        @class(['business-modal', 'has-overlay-footer' => $hasFooter])
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
            <x-business.overlay-close :action="$closeAction" />
        </header>

        <div class="overlay-body" data-overlay-scroll-body>
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
