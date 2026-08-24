@props([
    'current' => 1,
    'last' => 1,
    'previousAction' => 'previousPage',
    'nextAction' => 'nextPage',
    'label' => 'Result pages',
])

@if ((int) $last > 1)
    <nav {{ $attributes->class(['pagination']) }} aria-label="{{ $label }}">
        <x-clinical.button
            wire:click="{{ $previousAction }}"
            wire:loading.attr="disabled"
            type="button"
            :disabled="(int) $current <= 1"
            aria-label="Previous page"
        >Previous</x-clinical.button>
        <span aria-live="polite">Page {{ (int) $current }} of {{ (int) $last }}</span>
        <x-clinical.button
            wire:click="{{ $nextAction }}"
            wire:loading.attr="disabled"
            type="button"
            :disabled="(int) $current >= (int) $last"
            aria-label="Next page"
        >Next</x-clinical.button>
    </nav>
@endif
