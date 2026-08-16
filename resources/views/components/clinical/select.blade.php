@props(['label', 'name', 'hint' => null, 'required' => false])

<label class="form-field">
    <span class="field-label">{{ $label }} @if ($required)<span aria-hidden="true">*</span>@endif</span>
    <select {{ $attributes->merge(['class' => 'control']) }} @if ($required) required @endif>{{ $slot }}</select>
    @if ($hint)<span class="field-hint">{{ $hint }}</span>@endif
    @error($name)<span class="field-error">{{ $message }}</span>@enderror
</label>
