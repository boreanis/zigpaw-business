@props(['label', 'name', 'hint' => null, 'required' => false, 'rows' => 4])

<label class="form-field form-field-wide">
    <span class="field-label">{{ $label }} @if ($required)<span aria-hidden="true">*</span>@endif</span>
    <textarea rows="{{ $rows }}" {{ $attributes->merge(['class' => 'control']) }} @if ($required) required @endif></textarea>
    @if ($hint)<span class="field-hint">{{ $hint }}</span>@endif
    @error($name)<span class="field-error">{{ $message }}</span>@enderror
</label>
