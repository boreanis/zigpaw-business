<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'Zigpaw Partners' }}</title>
    <x-business.theme-init />
    @vite(['resources/css/business.css', 'resources/js/business.js'])
    @livewireStyles
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to Partners workspace</a>
    {{ $slot }}
    @livewireScripts
</body>
</html>
