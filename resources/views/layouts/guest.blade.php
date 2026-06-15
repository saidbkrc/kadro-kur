<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kadro Kur') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|barlow-condensed:500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-pitch-ink antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-pitch-bg bg-[radial-gradient(1200px_500px_at_50%_-10%,rgba(40,120,70,.25),transparent_60%)]">
            <a href="/" wire:navigate class="flex items-center gap-3">
                <span class="text-4xl">⚽</span>
                <span class="font-display uppercase tracking-wider text-3xl font-bold">Kadro Kur</span>
            </a>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-pitch-surface border border-pitch-line shadow-md overflow-hidden sm:rounded-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
