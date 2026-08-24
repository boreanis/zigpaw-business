@props([
    'title',
    'description' => null,
    'tone' => 'info',
    'role' => null,
])

<section {{ $attributes->class([
    'permission-note',
    'clinical-alert-error' => $tone === 'error',
    'clinical-alert-success' => $tone === 'success',
])->merge(['role' => $role ?? ($tone === 'error' ? 'alert' : 'status')]) }}>
    @if (isset($icon))<div class="clinical-alert-icon" aria-hidden="true">{{ $icon }}</div>@endif
    <div><strong>{{ $title }}</strong>@if ($description)<span>{{ $description }}</span>@endif{{ $slot }}</div>
</section>
