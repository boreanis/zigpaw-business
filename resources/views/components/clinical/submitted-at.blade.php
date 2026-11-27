@props(['value', 'prefix' => ''])

@php($time = \App\Support\PortalDateTime::parse($value))
@if ($time)
    {{ $prefix }}<time datetime="{{ $time->toIso8601String() }}">{{ $time->format('j M Y, H:i T') }}</time>
@endif
