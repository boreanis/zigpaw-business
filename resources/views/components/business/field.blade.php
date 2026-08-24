@props([
    'label',
    'hint' => null,
    'error' => null,
])

<label {{ $attributes->class(['field']) }}>
    <span>{{ $label }}</span>
    {{ $slot }}
    @if ($hint)<small class="field-hint">{{ $hint }}</small>@endif
    @if ($error)<small class="inline-error">{{ $error }}</small>@endif
</label>
