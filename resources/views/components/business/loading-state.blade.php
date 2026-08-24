@props([
    'label' => 'Loading workspace data',
])

<div {{ $attributes->class(['loading-state']) }} role="status" aria-live="polite">
    <span class="loading-spinner" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</div>
