@props(['label', 'name', 'hint' => null, 'required' => false, 'rows' => 4])

@php
    $fieldId = $attributes->get('id', 'clinical-'.str($name)->replace(['.', '[', ']'], '-')->slug());
    $hintId = $fieldId.'-hint';
    $errorId = $fieldId.'-error';
    $errorMessage = isset($errors) ? $errors->first($name) : null;
    $hasError = is_string($errorMessage) && $errorMessage !== '';
    $describedBy = collect([$hint ? $hintId : null, $hasError ? $errorId : null])->filter()->join(' ');
@endphp

<div class="form-field form-field-wide">
    <label class="field-label" for="{{ $fieldId }}">{{ $label }} @if ($required)<span aria-hidden="true">*</span><span class="sr-only"> required</span>@endif</label>
    <textarea
        id="{{ $fieldId }}"
        rows="{{ $rows }}"
        {{ $attributes->except('id')->merge(['class' => 'control']) }}
        @if ($required) required aria-required="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        aria-invalid="{{ $hasError ? 'true' : 'false' }}"
    ></textarea>
    @if ($hint)<span id="{{ $hintId }}" class="field-hint">{{ $hint }}</span>@endif
    @if ($hasError)<span id="{{ $errorId }}" class="field-error" role="alert">{{ $errorMessage }}</span>@endif
</div>
