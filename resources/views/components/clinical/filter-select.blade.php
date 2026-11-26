@props(['label'])

<label class="filter-select">
    <span>{{ $label }}</span>
    <select {{ $attributes }}>{{ $slot }}</select>
</label>
