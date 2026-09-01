<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Menú &middot; {{ $business->name }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
        <div class="mx-auto max-w-2xl px-4 py-8 sm:py-10">
            <div class="mb-8 flex flex-col items-center text-center">
                @if ($business->logo_path)
                    <img src="{{ Illuminate\Support\Facades\Storage::url($business->logo_path) }}" alt="{{ $business->name }}" class="mb-3 h-16 w-16 rounded-xl object-cover">
                @else
                    <span class="mb-3 flex h-14 w-14 items-center justify-center rounded-xl bg-violet-600 text-white">
                        <x-icon name="building-storefront" class="h-8 w-8" />
                    </span>
                @endif
                <h1 class="text-2xl font-semibold">{{ $business->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Menú</p>
            </div>

            @forelse ($categories as $category)
                <section class="mb-8">
                    <h2 class="mb-3 border-b border-gray-200 pb-2 text-lg font-semibold text-violet-700">{{ $category->name }}</h2>
                    <div class="space-y-3">
                        @foreach ($category->products as $product)
                            <div class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $product->name }}</p>
                                    @if ($product->description)
                                        <p class="mt-0.5 text-sm text-gray-500">{{ $product->description }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 font-semibold text-violet-700">${{ number_format((float) $product->price, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="text-center text-sm text-gray-400">Este menú no tiene productos disponibles todavía.</p>
            @endforelse

            @if ($uncategorized->isNotEmpty())
                <section class="mb-8">
                    <h2 class="mb-3 border-b border-gray-200 pb-2 text-lg font-semibold text-violet-700">Otros</h2>
                    <div class="space-y-3">
                        @foreach ($uncategorized as $product)
                            <div class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $product->name }}</p>
                                    @if ($product->description)
                                        <p class="mt-0.5 text-sm text-gray-500">{{ $product->description }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 font-semibold text-violet-700">${{ number_format((float) $product->price, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <p class="mt-10 text-center text-xs text-gray-400">&copy; {{ now()->year }} {{ $business->name }}</p>
        </div>
    </body>
</html>
