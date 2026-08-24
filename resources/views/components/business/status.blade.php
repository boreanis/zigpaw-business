@props([
    'tone' => 'neutral',
])

<span {{ $attributes->class(['status', 'status-good' => $tone === 'good', 'status-warn' => $tone === 'warn', 'status-blue' => $tone === 'blue', 'status-danger' => in_array($tone, ['danger', 'error'], true)]) }}>
    {{ $slot }}
</span>
