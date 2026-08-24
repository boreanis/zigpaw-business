@props(['label'])

<label class="filter-select">
    <span class="sr-only">{{ $label }}</span>
    <select {{ $attributes }}>{{ $slot }}</select>
</label>
