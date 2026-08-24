@props([
    'options',
    'selected',
    'model' => 'status',
    'action' => 'filter',
    'label' => 'Filter results',
])

<form {{ $attributes->class(['segmented-filter']) }} wire:submit="{{ $action }}" aria-label="{{ $label }}">
    @foreach ($options as $value => $optionLabel)
        <button
            type="submit"
            wire:click="$set('{{ $model }}', '{{ $value }}')"
            @class(['active' => $selected === $value])
            aria-pressed="{{ $selected === $value ? 'true' : 'false' }}"
        >{{ $optionLabel }}</button>
    @endforeach
</form>
