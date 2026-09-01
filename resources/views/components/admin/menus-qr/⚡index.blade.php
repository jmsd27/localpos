<?php

use App\Models\Business;
use App\Models\ProductCategory;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    /** @var list<int> */
    public array $selected = [];

    public function mount(): void
    {
        $this->selected = $this->categoriesQuery()->pluck('id')->all();
    }

    public function selectAll(): void
    {
        $this->selected = $this->categoriesQuery()->pluck('id')->all();
    }

    public function clearAll(): void
    {
        $this->selected = [];
    }

    public function selectOnly(int $categoryId): void
    {
        $this->selected = [$categoryId];
    }

    private function categoriesQuery()
    {
        return ProductCategory::query()
            ->where('business_id', Auth::user()->businessId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    private function menuUrl(): string
    {
        $allIds = $this->categoriesQuery()->pluck('id')->all();
        $isEverything = empty($this->selected) || empty(array_diff($allIds, $this->selected));

        return $isEverything
            ? route('menu.show')
            : route('menu.show', ['c' => implode(',', $this->selected)]);
    }

    private function qrDataUri(): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $this->menuUrl(),
            size: 320,
            margin: 12,
        ))->build();

        return $result->getDataUri();
    }

    public function with(): array
    {
        return [
            'categories' => $this->categoriesQuery()
                ->withCount(['products' => fn ($query) => $query->where('is_active', true)->where('is_sellable', true)])
                ->get(),
            'business' => Business::find(Auth::user()->businessId()),
            'menuUrl' => $this->menuUrl(),
            'qrDataUri' => $this->qrDataUri(),
        ];
    }
};
?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Menús QR</h1>
            <p class="mt-1 text-sm text-gray-500">Genera un código QR y una imagen para compartir el menú de comida, bebidas u otra categoría.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-600">Categorías incluidas</h2>
                <div class="flex gap-2 text-xs">
                    <button type="button" wire:click="selectAll" class="rounded-lg border border-gray-300 px-2.5 py-1 text-gray-600 hover:bg-gray-50">Seleccionar todo</button>
                    <button type="button" wire:click="clearAll" class="rounded-lg border border-gray-300 px-2.5 py-1 text-gray-600 hover:bg-gray-50">Quitar todo</button>
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($categories as $category)
                    <div class="flex items-center justify-between gap-3 py-2.5">
                        <label class="flex min-w-0 flex-1 items-center gap-3 text-sm">
                            <input type="checkbox" wire:model.live="selected" value="{{ $category->id }}" class="rounded border-gray-300 bg-white">
                            <span class="truncate">{{ $category->name }}</span>
                            <span class="shrink-0 text-xs text-gray-400">{{ $category->products_count }} productos</span>
                        </label>
                        <button type="button" wire:click="selectOnly({{ $category->id }})" class="shrink-0 rounded-lg border border-gray-200 px-2.5 py-1 text-xs text-violet-700 hover:bg-violet-50">
                            Solo esta
                        </button>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-gray-400">Crea categorías en Catálogo &rarr; Categorías para poder generar menús.</p>
                @endforelse
            </div>

            @if (empty($selected))
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    Sin categorías seleccionadas, el QR mostrará el menú completo.
                </p>
            @endif
        </div>

        <div class="flex flex-col items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
            <img src="{{ $qrDataUri }}" alt="Código QR del menú" class="h-48 w-48 rounded-lg border border-gray-100 sm:h-56 sm:w-56">

            <div class="w-full" x-data="{ copied: false }">
                <label class="mb-1 block text-xs text-gray-500">Enlace del menú</label>
                <div class="flex gap-2">
                    <input x-ref="url" type="text" readonly value="{{ $menuUrl }}" class="w-full truncate rounded-lg border border-gray-300 bg-gray-50 px-2 py-1.5 text-xs text-gray-600">
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText($refs.url.value); copied = true; setTimeout(() => copied = false, 1500)"
                        class="shrink-0 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
                    >
                        <span x-show="!copied">Copiar</span>
                        <span x-show="copied" x-cloak>Copiado</span>
                    </button>
                </div>
            </div>

            <a href="{{ $menuUrl }}" target="_blank" rel="noopener" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-center text-sm text-gray-600 hover:bg-gray-50">
                Ver menú
            </a>

            <button
                type="button"
                id="descargar-imagen-venta"
                data-qr="{{ $qrDataUri }}"
                data-negocio="{{ $business?->name }}"
                data-url="{{ $menuUrl }}"
                class="w-full rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"
            >
                Descargar imagen de venta
            </button>
        </div>
    </div>

    @script
    <script>
        $el.querySelector('#descargar-imagen-venta')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const { qr, negocio, url } = button.dataset;

            const width = 1080;
            const height = 1350;
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');

            const gradient = ctx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, '#7c3aed');
            gradient.addColorStop(1, '#4c1d95');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, width, height);

            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.font = '600 56px sans-serif';
            ctx.fillText(negocio || 'LOCALPOS', width / 2, 160);

            ctx.font = '500 34px sans-serif';
            ctx.fillText('Escanea para ver el menú', width / 2, 230);

            const qrImage = new Image();
            qrImage.src = qr;
            await new Promise((resolve) => { qrImage.onload = resolve; });

            const qrSize = 640;
            const qrX = (width - qrSize) / 2;
            const qrY = 340;
            const padding = 32;
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.roundRect(qrX - padding, qrY - padding, qrSize + padding * 2, qrSize + padding * 2, 24);
            ctx.fill();
            ctx.drawImage(qrImage, qrX, qrY, qrSize, qrSize);

            ctx.fillStyle = '#ede9fe';
            ctx.font = '400 24px sans-serif';
            ctx.fillText(url.replace(/^https?:\/\//, ''), width / 2, qrY + qrSize + padding + 60);

            const link = document.createElement('a');
            link.download = 'menu-qr.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    </script>
    @endscript
</div>
