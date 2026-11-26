@props([
    'eyebrow' => null,
    'title',
])

<div {{ $attributes->class(['section-heading-inline']) }}>
    <div>
        @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h2>{{ $title }}</h2>
    </div>
    @if (isset($meta))
        {{ $meta }}
    @elseif (isset($actions))
        {{ $actions }}
    @endif
</div>
