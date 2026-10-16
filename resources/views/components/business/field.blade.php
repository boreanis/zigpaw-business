@props([
    'label',
    'name' => null,
    'hint' => null,
    'error' => null,
])

@php
    $fieldId = $name ? 'business-field-'.str($name)->replace(['.', '[', ']'], '-') : null;
    $errorMessage = $error ?: ($name && isset($errors) ? $errors->first($name) : null);
@endphp

<label {{ $attributes->class(['field']) }} @if ($fieldId) for="{{ $fieldId }}" @endif>
    <span>{{ $label }}</span>
    {{ $slot }}
    @if ($hint)<small class="field-hint" @if ($fieldId) id="{{ $fieldId }}-hint" @endif>{{ $hint }}</small>@endif
    @if ($errorMessage)<small class="inline-error" @if ($fieldId) id="{{ $fieldId }}-error" @endif role="alert">{{ $errorMessage }}</small>@endif
</label>
