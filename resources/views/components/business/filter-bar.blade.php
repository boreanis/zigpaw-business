@props([
    'label' => 'Filters',
])

<div {{ $attributes->class(['filter-bar']) }} role="search" aria-label="{{ $label }}">
    {{ $slot }}
</div>
