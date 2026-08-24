@props([
    'variant' => 'secondary',
    'type' => 'button',
    'href' => null,
])

@php($classes = match ($variant) {
    'primary' => 'button button-primary',
    'danger' => 'button button-danger-soft',
    'text' => 'text-action',
    'action' => 'action-row',
    'claim' => 'claim-result',
    'window' => 'booking-window',
    'organization' => 'organization-option',
    'theme' => 'theme-control',
    default => 'button button-secondary',
})

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @unless ($attributes->has('wire:loading.attr')) wire:loading.attr="disabled" @endunless {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
