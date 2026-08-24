@props([
    'action',
    'message' => 'This action cannot be undone.',
])

<x-business.button variant="text" class="text-danger" wire:click="{{ $action }}" wire:confirm="{{ $message }}" {{ $attributes }}>
    {{ $slot }}
</x-business.button>
