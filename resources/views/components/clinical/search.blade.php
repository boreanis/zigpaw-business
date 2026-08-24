@props([
    'label',
    'disabled' => false,
])

<label class="search-field">
    <span class="sr-only">{{ $label }}</span>
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
    <input type="search" @disabled($disabled) {{ $attributes }}>
</label>
