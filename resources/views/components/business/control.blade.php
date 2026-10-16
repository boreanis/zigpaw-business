@props(['as' => 'input'])
@aware(['name' => null, 'hint' => null, 'error' => null])

@php
    $fieldName = $name ?: $attributes->wire('model')->value();
    $fieldId = $fieldName ? 'business-field-'.str($fieldName)->replace(['.', '[', ']'], '-') : null;
    $errorMessage = $error ?: ($fieldName && isset($errors) ? $errors->first($fieldName) : null);
    $describedBy = collect([$hint && $fieldId ? $fieldId.'-hint' : null, $errorMessage && $fieldId ? $fieldId.'-error' : null])->filter()->join(' ');
    $controlAttributes = $attributes->merge(array_filter([
        'id' => $fieldId,
        'name' => $fieldName,
        'aria-invalid' => $errorMessage ? 'true' : 'false',
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
    ], static fn ($value) => $value !== null));
@endphp

@if ($as === 'select')
    <select {{ $controlAttributes }}>{{ $slot }}</select>
@elseif ($as === 'textarea')
    <textarea {{ $controlAttributes }}>{{ $slot }}</textarea>
@else
    <input {{ $controlAttributes }}>
@endif
