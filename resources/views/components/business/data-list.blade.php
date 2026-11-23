@props(['label'])

<ul aria-label="{{ $label }}" {{ $attributes->class(['data-list']) }}>
    {{ $slot }}
</ul>
