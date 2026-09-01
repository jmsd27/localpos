<?php

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Terminal;
use App\Services\SaleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $activeCategoryId = null;

    public string $search = '';

    public array $cart = [];

    public int $nextCartLineId = 1;

    public ?int $modifierProductId = null;

    public array $modifierSelections = [];

    public string $modifierNotes = '';

    public ?string $modifierError = null;

    public bool $showCheckout = false;

    public string $orderType = 'mostrador';

    public ?int $customerId = null;

    public string $discountType = '';

    public string $discountValue = '';

    public string $tipAmount = '0';

    public array $paymentRows = [];

    public ?string $checkoutError = null;

    public ?string $completedFolio = null;

    public ?int $completedOrderId = null;

    public function mount(): void
    {
        if (! session('terminal_id')) {
            $this->redirectRoute('pos.terminal', navigate: true);

            return;
        }

        if (! session('cash_register_session_id')) {
            $this->redirectRoute('caja.apertura', navigate: true);
        }
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->activeCategoryId = $categoryId;
    }

    public function addProduct(int $productId): void
    {
        $product = Product::query()
            ->where('business_id', Auth::user()->businessId())
            ->with('modifierGroups.options')
            ->findOrFail($productId);

        if ($product->modifierGroups->isEmpty()) {
            $this->pushCartLine($product, [], null);

            return;
        }

        $this->modifierProductId = $productId;
        $this->modifierSelections = [];
        $this->modifierNotes = '';
        $this->modifierError = null;

        foreach ($product->modifierGroups as $group) {
            $this->modifierSelections[$group->id] = $group->max_selections > 1 ? [] : null;
        }
    }

    public function confirmAddWithModifiers(): void
    {
        $product = Product::query()->with('modifierGroups.options')->findOrFail($this->modifierProductId);

        $modifiers = [];

        foreach ($product->modifierGroups as $group) {
            $selected = $this->modifierSelections[$group->id] ?? null;
            $selectedIds = is_array($selected) ? $selected : array_filter([$selected]);

            if (count($selectedIds) < $group->min_selections) {
                $this->modifierError = "Selecciona al menos {$group->min_selections} opción(es) de \"{$group->name}\".";

                return;
            }

            foreach ($group->options as $option) {
                if (in_array((string) $option->id, array_map('strval', $selectedIds), true)) {
                    $modifiers[] = [
                        'modifier_option_id' => $option->id,
                        'name' => $option->name,
                        'price_delta' => (float) $option->price_delta,
                    ];
                }
            }
        }

        $this->pushCartLine($product, $modifiers, $this->modifierNotes !== '' ? $this->modifierNotes : null);

        $this->modifierProductId = null;
        $this->modifierSelections = [];
        $this->modifierNotes = '';
        $this->modifierError = null;
    }

    public function cancelModifiers(): void
    {
        $this->modifierProductId = null;
        $this->modifierSelections = [];
        $this->modifierNotes = '';
        $this->modifierError = null;
    }

    private function pushCartLine(Product $product, array $modifiers, ?string $notes): void
    {
        if (empty($modifiers)) {
            foreach ($this->cart as $index => $line) {
                if ($line['product_id'] === $product->id && empty($line['modifiers']) && $line['notes'] === $notes) {
                    $this->cart[$index]['quantity']++;

                    return;
                }
            }
        }

        $this->cart[] = [
            'id' => $this->nextCartLineId++,
            'product_id' => $product->id,
            'kitchen_station_id' => $product->kitchen_station_id,
            'name' => $product->name,
            'unit_price' => (float) $product->price,
            'tax_rate' => (float) $product->tax_rate,
            'quantity' => 1,
            'notes' => $notes,
            'modifiers' => $modifiers,
        ];
    }

    public function incrementQty(int $lineId): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['id'] === $lineId) {
                $this->cart[$index]['quantity']++;
            }
        }
    }

    public function decrementQty(int $lineId): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['id'] === $lineId) {
                if ($line['quantity'] <= 1) {
                    unset($this->cart[$index]);
                    $this->cart = array_values($this->cart);
                } else {
                    $this->cart[$index]['quantity']--;
                }
            }
        }
    }

    public function removeLine(int $lineId): void
    {
        $this->cart = array_values(array_filter($this->cart, fn ($line) => $line['id'] !== $lineId));
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discountType = '';
        $this->discountValue = '';
        $this->tipAmount = '0';
    }

    private function lineTotal(array $line): float
    {
        $modifiersTotal = array_sum(array_column($line['modifiers'], 'price_delta'));

        return round(($line['unit_price'] + $modifiersTotal) * $line['quantity'], 2);
    }

    public function subtotal(): float
    {
        return round(array_sum(array_map(fn ($line) => $this->lineTotal($line), $this->cart)), 2);
    }

    public function taxAmount(): float
    {
        $tax = 0.0;

        foreach ($this->cart as $line) {
            $tax += round($this->lineTotal($line) * ($line['tax_rate'] / 100), 2);
        }

        return round($tax, 2);
    }

    public function discountAmount(): float
    {
        if ($this->discountType === '' || $this->discountValue === '' || ! is_numeric($this->discountValue)) {
            return 0.0;
        }

        $subtotal = $this->subtotal();
        $value = (float) $this->discountValue;

        return round($this->discountType === 'percentage' ? $subtotal * ($value / 100) : min($value, $subtotal), 2);
    }

    public function total(): float
    {
        return round($this->subtotal() - $this->discountAmount() + $this->taxAmount() + (float) ($this->tipAmount ?: 0), 2);
    }

    public function openCheckout(): void
    {
        if (empty($this->cart)) {
            return;
        }

        $this->checkoutError = null;
        $this->paymentRows = [
            ['method' => 'efectivo', 'amount' => number_format($this->total(), 2, '.', ''), 'received_amount' => ''],
        ];
        $this->showCheckout = true;
    }

    public function closeCheckout(): void
    {
        $this->showCheckout = false;
    }

    public function addPaymentRow(): void
    {
        $this->paymentRows[] = ['method' => 'tarjeta', 'amount' => '', 'received_amount' => ''];
    }

    public function removePaymentRow(int $index): void
    {
        unset($this->paymentRows[$index]);
        $this->paymentRows = array_values($this->paymentRows);
    }

    public function paymentsTotal(): float
    {
        return round(array_sum(array_map(fn ($row) => (float) ($row['amount'] ?: 0), $this->paymentRows)), 2);
    }

    public function checkout(SaleService $sales): void
    {
        $this->checkoutError = null;

        if (empty($this->cart)) {
            $this->checkoutError = 'El carrito está vacío.';

            return;
        }

        if ($this->paymentsTotal() + 0.005 < $this->total()) {
            $this->checkoutError = 'El monto pagado no cubre el total de la venta.';

            return;
        }

        $user = Auth::user();

        $items = array_map(fn ($line) => [
            'product_id' => $line['product_id'],
            'kitchen_station_id' => $line['kitchen_station_id'],
            'name' => $line['name'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'tax_rate' => $line['tax_rate'],
            'notes' => $line['notes'],
            'modifiers' => $line['modifiers'],
        ], $this->cart);

        $payments = array_map(fn ($row) => [
            'method' => $row['method'],
            'amount' => (float) $row['amount'],
            'received_amount' => $row['received_amount'] !== '' ? (float) $row['received_amount'] : null,
        ], $this->paymentRows);

        try {
            $order = $sales->complete([
                'business_id' => $user->businessId(),
                'branch_id' => $user->branch_id,
                'terminal_id' => session('terminal_id'),
                'cash_register_session_id' => session('cash_register_session_id'),
                'user_id' => $user->id,
                'customer_id' => $this->customerId,
                'order_type' => $this->orderType,
                'items' => $items,
                'discount_type' => $this->discountType !== '' ? $this->discountType : null,
                'discount_value' => $this->discountValue !== '' ? (float) $this->discountValue : null,
                'tip_amount' => (float) ($this->tipAmount ?: 0),
                'payments' => $payments,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->checkoutError = $e->getMessage();

            return;
        }

        $this->completedFolio = $order->folio;
        $this->completedOrderId = $order->id;
        $this->showCheckout = false;
        $this->clearCart();
    }

    public function startNewSale(): void
    {
        $this->completedFolio = null;
        $this->completedOrderId = null;
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        $products = Product::query()
            ->where('business_id', $businessId)
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->when($this->activeCategoryId, fn ($q) => $q->where('product_category_id', $this->activeCategoryId))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();

        return [
            'categories' => ProductCategory::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'products' => $products,
            'customers' => Customer::query()->where('business_id', $businessId)->orderBy('name')->get(),
            'terminal' => Terminal::find(session('terminal_id')),
            'modifierProduct' => $this->modifierProductId
                ? Product::with('modifierGroups.options')->find($this->modifierProductId)
                : null,
            'paymentMethods' => PaymentMethod::cases(),
        ];
    }
};
?>

<div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-900 lg:h-[80vh] lg:flex-row" x-data>
    <div class="flex flex-col gap-3 border-b border-gray-200 bg-white/50 p-4 lg:w-56 lg:shrink-0 lg:overflow-y-auto lg:border-b-0 lg:border-r">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Salir del POS</a>
            <div class="mt-2 text-xs text-gray-400">Terminal: {{ $terminal?->name ?? '—' }}</div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('mesas.mapa') }}" wire:navigate class="rounded-lg border border-gray-200 px-2 py-1 text-gray-600 hover:bg-white">Mesas</a>
            @can('caja.ver_movimientos')
                <a href="{{ route('caja.movimientos') }}" wire:navigate class="rounded-lg border border-gray-200 px-2 py-1 text-gray-600 hover:bg-white">Movimientos</a>
            @endcan
            @can('caja.cerrar')
                <a href="{{ route('caja.cierre') }}" wire:navigate class="rounded-lg border border-gray-200 px-2 py-1 text-gray-600 hover:bg-white">Cerrar caja</a>
            @endcan
        </div>

        <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:gap-0 lg:overflow-visible lg:px-0 lg:pb-0">
            <button wire:click="selectCategory(null)" class="shrink-0 rounded-lg px-3 py-2 text-left text-sm lg:mb-1 lg:shrink {{ $activeCategoryId === null ? 'bg-violet-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                Todas
            </button>
            @foreach ($categories as $category)
                <button wire:click="selectCategory({{ $category->id }})" class="shrink-0 rounded-lg px-3 py-2 text-left text-sm lg:mb-1 lg:shrink {{ $activeCategoryId === $category->id ? 'bg-violet-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                    {{ $category->name }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar producto…" class="mb-4 w-full max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-violet-500 focus:outline-none">

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <button wire:click="addProduct({{ $product->id }})" class="flex h-28 flex-col justify-between rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-violet-500">
                    <span class="text-sm font-medium">{{ $product->name }}</span>
                    <span class="text-violet-600">${{ number_format((float) $product->price, 2) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex max-h-[70vh] flex-col border-t border-gray-200 bg-white/50 p-4 lg:h-auto lg:max-h-none lg:w-96 lg:shrink-0 lg:border-l lg:border-t-0">
        <h2 class="mb-3 text-lg font-semibold">Ticket</h2>

        <div class="flex-1 space-y-2 overflow-y-auto">
            @forelse ($cart as $line)
                <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-medium">{{ $line['name'] }}</div>
                            @foreach ($line['modifiers'] as $modifier)
                                <div class="text-xs text-gray-400">+ {{ $modifier['name'] }}</div>
                            @endforeach
                            @if ($line['notes'])
                                <div class="text-xs italic text-amber-600">{{ $line['notes'] }}</div>
                            @endif
                        </div>
                        <button wire:click="removeLine({{ $line['id'] }})" class="text-red-600 hover:text-red-700">&times;</button>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button wire:click="decrementQty({{ $line['id'] }})" class="h-6 w-6 rounded bg-white hover:bg-gray-100">-</button>
                            <span>{{ $line['quantity'] }}</span>
                            <button wire:click="incrementQty({{ $line['id'] }})" class="h-6 w-6 rounded bg-white hover:bg-gray-100">+</button>
                        </div>
                        <span class="font-medium">
                            ${{ number_format(($line['unit_price'] + array_sum(array_column($line['modifiers'], 'price_delta'))) * $line['quantity'], 2) }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-gray-400">Agrega productos al ticket.</p>
            @endforelse
        </div>

        <div class="mt-4 space-y-1 border-t border-gray-200 pt-3 text-sm">
            <div class="flex justify-between text-gray-500"><span>Subtotal</span><span>${{ number_format($this->subtotal(), 2) }}</span></div>
            @if ($this->discountAmount() > 0)
                <div class="flex justify-between text-gray-500"><span>Descuento</span><span>-${{ number_format($this->discountAmount(), 2) }}</span></div>
            @endif
            <div class="flex justify-between text-gray-500"><span>IVA</span><span>${{ number_format($this->taxAmount(), 2) }}</span></div>
            @if ((float) ($tipAmount ?: 0) > 0)
                <div class="flex justify-between text-gray-500"><span>Propina</span><span>${{ number_format((float) $tipAmount, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-base font-semibold text-gray-900"><span>Total</span><span>${{ number_format($this->total(), 2) }}</span></div>
        </div>

        @can('ventas.aplicar_descuento')
            <div class="mt-3 grid grid-cols-2 gap-2">
                <select wire:model="discountType" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-900">
                    <option value="">Sin descuento</option>
                    <option value="percentage">%</option>
                    <option value="amount">$</option>
                </select>
                <input type="number" step="0.01" wire:model="discountValue" placeholder="Valor" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-900">
            </div>
        @endcan

        <button
            wire:click="openCheckout"
            @if (empty($cart)) disabled @endif
            class="mt-4 w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40 text-white"
        >
            Cobrar
        </button>
    </div>

    {{-- Modal de modificadores --}}
    @if ($modifierProduct)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 p-4">
            <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-4 text-lg font-semibold">{{ $modifierProduct->name }}</h3>

                @if ($modifierError)
                    <p class="mb-3 text-sm text-red-600">{{ $modifierError }}</p>
                @endif

                @foreach ($modifierProduct->modifierGroups as $group)
                    <div class="mb-4">
                        <div class="mb-2 text-sm font-medium">
                            {{ $group->name }}
                            @if ($group->is_required) <span class="text-red-600">*</span> @endif
                        </div>
                        @foreach ($group->options as $option)
                            <label class="mb-1 flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-white">
                                <span class="flex items-center gap-2">
                                    @if ($group->max_selections > 1)
                                        <input type="checkbox" wire:model="modifierSelections.{{ $group->id }}" value="{{ $option->id }}">
                                    @else
                                        <input type="radio" wire:model="modifierSelections.{{ $group->id }}" value="{{ $option->id }}">
                                    @endif
                                    {{ $option->name }}
                                </span>
                                <span class="text-gray-400">
                                    @if ((float) $option->price_delta > 0) +${{ number_format((float) $option->price_delta, 2) }} @else gratis @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endforeach

                <input type="text" wire:model="modifierNotes" placeholder="Notas (p. ej. sin cebolla)" class="mb-4 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">

                <div class="flex gap-2">
                    <button wire:click="confirmAddWithModifiers" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Agregar</button>
                    <button wire:click="cancelModifiers" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Cancelar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de cobro --}}
    @if ($showCheckout)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 p-4">
            <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6">
                <h3 class="mb-4 text-lg font-semibold">Cobrar ${{ number_format($this->total(), 2) }}</h3>

                @if ($checkoutError)
                    <p class="mb-3 text-sm text-red-600">{{ $checkoutError }}</p>
                @endif

                <div class="mb-3">
                    <label class="mb-1 block text-sm text-gray-600">Cliente (opcional)</label>
                    <select wire:model="customerId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                        <option value="">Sin cliente</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 space-y-2">
                    @foreach ($paymentRows as $index => $row)
                        <div class="flex gap-2">
                            <select wire:model="paymentRows.{{ $index }}.method" class="w-1/3 rounded-lg border border-gray-300 bg-white px-2 py-2 text-xs text-gray-900">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" wire:model="paymentRows.{{ $index }}.amount" placeholder="Monto" class="flex-1 rounded-lg border border-gray-300 bg-white px-2 py-2 text-xs text-gray-900">
                            @if ($row['method'] === 'efectivo')
                                <input type="number" step="0.01" wire:model="paymentRows.{{ $index }}.received_amount" placeholder="Recibido" class="flex-1 rounded-lg border border-gray-300 bg-white px-2 py-2 text-xs text-gray-900">
                            @endif
                            @if (count($paymentRows) > 1)
                                <button wire:click="removePaymentRow({{ $index }})" class="text-red-600">&times;</button>
                            @endif
                        </div>
                    @endforeach

                    <button wire:click="addPaymentRow" class="text-xs text-violet-600 hover:text-violet-600">+ Agregar método de pago</button>
                </div>

                <div class="mb-4 flex justify-between text-sm text-gray-500">
                    <span>Pagado</span>
                    <span>${{ number_format($this->paymentsTotal(), 2) }}</span>
                </div>

                <div class="flex gap-2">
                    <button wire:click="checkout" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold hover:bg-emerald-500 text-white">Confirmar cobro</button>
                    <button wire:click="closeCheckout" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Cancelar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Venta completada --}}
    @if ($completedFolio)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
            <div class="w-full max-w-sm rounded-xl border border-emerald-200 bg-white p-6 text-center">
                <h3 class="mb-2 text-xl font-semibold text-emerald-600">Venta completada</h3>
                <p class="mb-4 text-gray-600">Folio {{ $completedFolio }}</p>
                <div class="flex flex-col gap-2">
                    <a href="{{ route('ventas.ticket', $completedOrderId) }}" target="_blank" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                        Ver / imprimir ticket
                    </a>
                    <button wire:click="startNewSale" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">
                        Nueva venta
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
