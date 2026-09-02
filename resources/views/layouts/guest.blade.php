<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" href="/favicon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#7c3aed">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="relative min-h-screen overflow-x-hidden bg-gray-50 text-gray-900 antialiased">
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-32 left-1/4 h-96 w-96 rounded-full bg-violet-400/25 blur-3xl"></div>
            <div class="absolute top-1/3 -right-24 h-80 w-80 rounded-full bg-fuchsia-400/20 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-indigo-300/20 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgb(156_163_175)_1px,transparent_0)] bg-[length:28px_28px] opacity-[0.15]"></div>
        </div>

        <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div class="mb-8 flex items-center gap-2.5">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-fuchsia-600 text-white shadow-lg shadow-violet-600/30">
                    <x-icon name="building-storefront" class="h-6 w-6" />
                </span>
                <span class="text-xl font-semibold tracking-tight text-gray-900">{{ config('app.name', 'puntoYA') }}</span>
            </div>

            <div class="w-full max-w-md rounded-2xl border border-white bg-white/80 p-8 shadow-2xl shadow-gray-900/10 ring-1 ring-gray-900/5 backdrop-blur-sm">
                {{ $slot }}
            </div>

            <p class="relative mt-8 text-xs text-gray-400">&copy; {{ now()->year }} {{ config('app.name', 'puntoYA') }}</p>
        </div>

        @livewireScripts
    </body>
</html>
