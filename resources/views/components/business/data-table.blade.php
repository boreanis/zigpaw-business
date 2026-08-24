@props([
    'label' => 'Results',
])

<div {{ $attributes->class(['data-table']) }} role="region" aria-label="{{ $label }}">
    {{ $slot }}
</div>
