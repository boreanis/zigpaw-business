@props([
    'action' => null,
    'label' => 'Close dialog',
])

<button
    type="button"
    class="icon-button"
    data-overlay-close
    @if ($action) wire:click="{{ $action }}" @endif
    aria-label="{{ $label }}"
    {{ $attributes }}
>
    <span aria-hidden="true">×</span>
</button>
