<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'Zigpaw business' }}</title>
    <script>
        (() => { const value = localStorage.getItem('zigpaw-business-theme'); if (value === 'light' || value === 'dark') document.documentElement.dataset.theme = value; })();
    </script>
    @vite(['resources/css/business.css', 'resources/js/business.js'])
    @livewireStyles
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to business workspace</a>
    {{ $slot }}
    @livewireScripts
</body>
</html>
