@props([
    'tone' => 'success',
    'timeout' => 6000,
    'dismissAction' => null,
])

<article
    {{ $attributes->class(['toast', 'toast-error' => $tone === 'error']) }}
    role="{{ $tone === 'error' ? 'alert' : 'status' }}"
    data-toast
    data-toast-timeout="{{ $timeout }}"
>
    <span class="toast-check" aria-hidden="true">{{ $tone === 'error' ? '!' : '✓' }}</span>
    <span class="toast-message">{{ $slot }}</span>
    <button
        type="button"
        class="toast-close"
        data-toast-close
        @if ($dismissAction) wire:click="{{ $dismissAction }}" @endif
        aria-label="Dismiss notification"
    >
        <span aria-hidden="true">×</span>
    </button>
</article>
