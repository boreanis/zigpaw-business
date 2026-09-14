@props([
    'eyebrow',
    'title',
])

<div {{ $attributes->class(['section-heading-inline']) }}>
    <div>
        <p class="eyebrow">{{ $eyebrow }}</p>
        <h2>{{ $title }}</h2>
    </div>
    @if (isset($meta))
        {{ $meta }}
    @elseif (isset($actions))
        {{ $actions }}
    @endif
</div>
