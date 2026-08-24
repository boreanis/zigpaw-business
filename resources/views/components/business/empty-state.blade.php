@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class(['empty-state']) }} role="status">
    <h3>{{ $title }}</h3>
    @if ($description)
        <p>{{ $description }}</p>
    @endif
    @if (isset($actions))
        <div class="button-row">{{ $actions }}</div>
    @endif
</div>
