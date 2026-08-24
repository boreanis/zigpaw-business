@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->class(['section-heading']) }}>
    <div>
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h2>{{ $title }}</h2>
        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="section-heading-actions">{{ $actions }}</div>
    @endif
</div>
