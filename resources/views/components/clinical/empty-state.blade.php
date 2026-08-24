@props([
    'title',
    'description' => null,
    'tone' => 'neutral',
    'headingLevel' => 2,
])

@php($headingTag = in_array((int) $headingLevel, [1, 2, 3], true) ? 'h'.(int) $headingLevel : 'h2')

<section {{ $attributes->class(['empty-state', 'empty-state-error' => $tone === 'error'])->merge([
    'role' => $tone === 'error' ? 'alert' : null,
]) }}>
    @if (isset($icon))<div class="empty-state-icon" aria-hidden="true">{{ $icon }}</div>@endif
    <{{ $headingTag }}>{{ $title }}</{{ $headingTag }}>
    @if ($description)<p>{{ $description }}</p>@endif
    @if (isset($actions))<div class="button-row">{{ $actions }}</div>@endif
</section>
