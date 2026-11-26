@props(['title', 'description' => null])

<li {{ $attributes->class(['data-row']) }}>
    <div>
        <strong>{{ $title }}</strong>
        @if ($description)<small>{{ $description }}</small>@endif
    </div>
    @isset($actions)<div class="row-actions">{{ $actions }}</div>@endisset
    {{ $slot }}
</li>
