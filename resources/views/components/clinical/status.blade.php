@props(['tone' => 'neutral'])

<span {{ $attributes->class([
    'status-badge',
    'status-green' => $tone === 'good',
    'status-amber' => $tone === 'warn',
    'status-red' => $tone === 'danger',
    'status-blue' => $tone === 'blue',
]) }}>{{ $slot }}</span>
