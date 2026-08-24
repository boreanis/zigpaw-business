@props([
    'variant' => 'secondary',
    'type' => 'button',
    'href' => null,
])

@php($classes = match ($variant) {
    'primary' => 'primary-button',
    'text' => 'text-button',
    'danger' => 'remove-link',
    'organization' => 'organization-option',
    'theme' => 'theme-control',
    default => 'secondary-button',
})

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @unless ($attributes->has('wire:loading.attr')) wire:loading.attr="disabled" @endunless {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
