@props(['title', 'description' => null])

<header {{ $attributes->class(['page-heading']) }}>
    <div>
        @isset($context){{ $context }}@endisset
        <h1>{{ $title }}</h1>
        @if ($description)<p>{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="heading-actions">{{ $actions }}</div>@endisset
</header>
