<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'Zigpaw business' }}</title>
    @vite(['resources/css/business.css', 'resources/js/business.js'])
    @livewireStyles
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to business workspace</a>
    {{ $slot }}
    @livewireScripts
</body>
</html>
