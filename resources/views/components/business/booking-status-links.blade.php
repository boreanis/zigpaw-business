@props(['statuses', 'current'])

@foreach ($statuses as $status => $label)
    @php($active = ($current ?? 'all') === $status)
    <x-business.button :variant="$active ? 'primary' : 'secondary'" :href="route('dashboard', ['section' => 'bookings', 'status' => $status === 'all' ? null : $status])" :aria-current="$active ? 'page' : null" wire:navigate>{{ $label }}</x-business.button>
@endforeach
