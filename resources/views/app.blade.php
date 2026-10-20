<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'ReviewLens') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%234f46e5'/%3E%3Ccircle cx='15' cy='15' r='6' fill='none' stroke='white' stroke-width='3'/%3E%3Cpath d='M20 20l6 6' stroke='white' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    @inertia
</body>
</html>
