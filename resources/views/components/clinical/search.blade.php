@props([
    'label',
    'disabled' => false,
])

<label class="filter-select">
    <span>{{ $label }}</span>
    <input type="search" @disabled($disabled) {{ $attributes }}>
</label>
