<?php

use App\Enums\KitchenItemStatus;
use App\Models\KitchenStation;
use App\Models\OrderItem;
use App\Services\KitchenService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $activeStationId = null;

    public function mount(): void
    {
        $businessId = Auth::user()->businessId();

        $stations = KitchenStation::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get();

        $roleStation = $stations->first(fn ($station) => Auth::user()->hasRole($station->code));

        $this->activeStationId = $roleStation?->id ?? $stations->first()?->id;
    }

    public function selectStation(int $stationId): void
    {
        $this->activeStationId = $stationId;
    }

    public function advance(int $orderItemId, string $to, KitchenService $kitchen): void
    {
        $item = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('business_id', Auth::user()->businessId()))
            ->findOrFail($orderItemId);

        $kitchen->advance($item, KitchenItemStatus::from($to));
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        $items = collect();

        if ($this->activeStationId) {
            $items = OrderItem::query()
                ->where('kitchen_station_id', $this->activeStationId)
                ->whereHas('order', fn ($q) => $q->where('business_id', $businessId)->where('status', '!=', 'cancelled'))
                ->where(function ($q) {
                    $q->whereIn('kitchen_status', ['nuevo', 'preparando', 'listo'])
                        ->orWhere(function ($q2) {
                            $q2->where('kitchen_status', 'entregado')->whereDate('delivered_at', today());
                        });
                })
                ->with(['order.table', 'order.user', 'modifiers'])
                ->orderBy('created_at')
                ->get();
        }

        return [
            'stations' => KitchenStation::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
            'nuevos' => $items->where('kitchen_status', KitchenItemStatus::Nuevo),
            'preparando' => $items->where('kitchen_status', KitchenItemStatus::Preparando),
            'listos' => $items->where('kitchen_status', KitchenItemStatus::Listo),
            'entregados' => $items->where('kitchen_status', KitchenItemStatus::Entregado),
        ];
    }
};
?>

<div wire:poll.3s >
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Cocina</h1>
        </div>
        <div class="flex gap-2">
            @foreach ($stations as $station)
                <button
                    wire:click="selectStation({{ $station->id }})"
                    class="rounded-lg border px-3 py-1.5 text-sm {{ $activeStationId === $station->id ? 'border-violet-500 bg-violet-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}"
                    style="{{ $activeStationId === $station->id ? '' : 'border-left: 3px solid '.$station->color }}"
                >
                    {{ $station->name }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($stations->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-gray-400">
            Sin estaciones configuradas. Ve a Configuración &rarr; Estaciones para crear una.
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            @php
                $columns = [
                    ['title' => 'Nuevas', 'items' => $nuevos, 'action' => 'preparando', 'actionLabel' => 'Iniciar', 'actionColor' => 'bg-amber-600 hover:bg-amber-500'],
                    ['title' => 'En preparación', 'items' => $preparando, 'action' => 'listo', 'actionLabel' => 'Marcar listo', 'actionColor' => 'bg-emerald-600 hover:bg-emerald-500'],
                    ['title' => 'Listas', 'items' => $listos, 'action' => 'entregado', 'actionLabel' => 'Entregar', 'actionColor' => 'bg-violet-600 hover:bg-violet-700'],
                    ['title' => 'Entregadas', 'items' => $entregados, 'action' => null, 'actionLabel' => null, 'actionColor' => null],
                ];
            @endphp

            @foreach ($columns as $column)
                <div>
                    <h2 class="mb-3 flex items-center justify-between text-sm font-semibold text-gray-500">
                        {{ $column['title'] }}
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs">{{ $column['items']->count() }}</span>
                    </h2>

                    <div class="space-y-3">
                        @foreach ($column['items'] as $item)
                            @php
                                $minutes = (int) floor($item->created_at->diffInSeconds(now()) / 60);
                                $priority = $minutes >= 20 ? 'border-red-600 bg-red-50' : ($minutes >= 10 ? 'border-amber-600 bg-amber-50' : 'border-gray-200 bg-white');
                            @endphp
                            <div class="rounded-xl border {{ $priority }} p-3 text-sm">
                                <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ $item->order->table?->name ?? $item->order->order_type->label() }}</span>
                                    <span>{{ $item->created_at->format('H:i') }} &middot; {{ $minutes }} min</span>
                                </div>
                                <div class="font-medium">{{ $item->quantity }} &times; {{ $item->name }}</div>
                                @foreach ($item->modifiers as $modifier)
                                    <div class="text-xs text-gray-500">+ {{ $modifier->name }}</div>
                                @endforeach
                                @if ($item->notes)
                                    <div class="text-xs italic text-amber-600">{{ $item->notes }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-400">Mesero: {{ $item->order->user->name }}</div>

                                @can('cocina.gestionar')
                                    @if ($column['action'])
                                        <button
                                            wire:click="advance({{ $item->id }}, '{{ $column['action'] }}')"
                                            class="mt-2 w-full rounded-lg {{ $column['actionColor'] }} py-1.5 text-xs font-semibold text-white"
                                        >
                                            {{ $column['actionLabel'] }}
                                        </button>
                                    @endif
                                @endcan
                            </div>
                        @endforeach

                        @if ($column['items']->isEmpty())
                            <p class="text-center text-xs text-gray-400">Sin pendientes.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
