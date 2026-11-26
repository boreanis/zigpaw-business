@props(['value' => null, 'noDeadline' => 'No set expiry', 'unavailable' => 'Expiry unavailable'])

@php($deadline = is_string($value) && preg_match('/T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value) ? \App\Support\PortalDateTime::parse($value) : null)

@if ($deadline)
    <time datetime="{{ $value }}">{{ $deadline->translatedFormat('j M Y · H:i T') }}</time>
@elseif ($value === null)
    {{ $noDeadline }}
@else
    {{ $unavailable }}
@endif
