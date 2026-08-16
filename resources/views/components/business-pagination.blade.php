@props(['pagination' => [], 'resource', 'label' => 'results'])

@php($meta = data_get($pagination, $resource))

@if (is_array($meta) && ($meta['last_page'] ?? 1) > 1)
    <nav class="pagination-controls" aria-label="{{ str($label)->headline() }} pages">
        <button
            type="button"
            wire:click="changePage('{{ $resource }}', {{ max(1, (int) $meta['current_page'] - 1) }})"
            @disabled((int) $meta['current_page'] <= 1)
        >
            Previous
        </button>
        <span>Page {{ $meta['current_page'] }} of {{ $meta['last_page'] }} · {{ $meta['total'] }} {{ $label }}</span>
        <button
            type="button"
            wire:click="changePage('{{ $resource }}', {{ min((int) $meta['last_page'], (int) $meta['current_page'] + 1) }})"
            @disabled((int) $meta['current_page'] >= (int) $meta['last_page'])
        >
            Next
        </button>
    </nav>
@endif
