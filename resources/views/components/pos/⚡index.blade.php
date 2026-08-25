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

<div class="flex min-h-screen bg-slate-950 text-white" x-data>
    <div class="flex w-56 flex-col border-r border-slate-800 bg-slate-900/50 p-4">
        <a href="{{ route('dashboard') }}" wire:navigate class="mb-4 text-sm text-slate-400 hover:text-white">&larr; Salir del POS</a>
        <div class="mb-4 text-xs text-slate-500">Terminal: {{ $terminal?->name ?? '—' }}</div>

        <button wire:click="selectCategory(null)" class="mb-1 rounded-lg px-3 py-2 text-left text-sm {{ $activeCategoryId === null ? 'bg-indigo-600' : 'hover:bg-slate-800' }}">
            Todas
        </button>
        @foreach ($categories as $category)
            <button wire:click="selectCategory({{ $category->id }})" class="mb-1 rounded-lg px-3 py-2 text-left text-sm {{ $activeCategoryId === $category->id ? 'bg-indigo-600' : 'hover:bg-slate-800' }}">
                {{ $category->name }}
            </button>
        @endforeach
    </div>

    <div class="flex-1 overflow-y-auto p-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar producto…" class="mb-4 w-full max-w-sm rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <button wire:click="addProduct({{ $product->id }})" class="flex h-28 flex-col justify-between rounded-xl border border-slate-800 bg-slate-900 p-3 text-left hover:border-indigo-500">
                    <span class="text-sm font-medium">{{ $product->name }}</span>
                    <span class="text-indigo-400">${{ number_format((float) $product->price, 2) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex w-96 flex-col border-l border-slate-800 bg-slate-900/50 p-4">
        <h2 class="mb-3 text-lg font-semibold">Ticket</h2>

        <div class="flex-1 space-y-2 overflow-y-auto">
            @forelse ($cart as $line)
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-3 text-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-medium">{{ $line['name'] }}</div>
                            @foreach ($line['modifiers'] as $modifier)
                                <div class="text-xs text-slate-500">+ {{ $modifier['name'] }}</div>
                            @endforeach
                            @if ($line['notes'])
                                <div class="text-xs italic text-amber-400">{{ $line['notes'] }}</div>
                            @endif
                        </div>
                        <button wire:click="removeLine({{ $line['id'] }})" class="text-red-400 hover:text-red-300">&times;</button>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button wire:click="decrementQty({{ $line['id'] }})" class="h-6 w-6 rounded bg-slate-800 hover:bg-slate-700">-</button>
                            <span>{{ $line['quantity'] }}</span>
                            <button wire:click="incrementQty({{ $line['id'] }})" class="h-6 w-6 rounded bg-slate-800 hover:bg-slate-700">+</button>
                        </div>
                        <span class="font-medium">
                            ${{ number_format(($line['unit_price'] + array_sum(array_column($line['modifiers'], 'price_delta'))) * $line['quantity'], 2) }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-slate-500">Agrega productos al ticket.</p>
            @endforelse
        </div>

        <div class="mt-4 space-y-1 border-t border-slate-800 pt-3 text-sm">
            <div class="flex justify-between text-slate-400"><span>Subtotal</span><span>${{ number_format($this->subtotal(), 2) }}</span></div>
            @if ($this->discountAmount() > 0)
                <div class="flex justify-between text-slate-400"><span>Descuento</span><span>-${{ number_format($this->discountAmount(), 2) }}</span></div>
            @endif
            <div class="flex justify-between text-slate-400"><span>IVA</span><span>${{ number_format($this->taxAmount(), 2) }}</span></div>
            @if ((float) ($tipAmount ?: 0) > 0)
                <div class="flex justify-between text-slate-400"><span>Propina</span><span>${{ number_format((float) $tipAmount, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-base font-semibold text-white"><span>Total</span><span>${{ number_format($this->total(), 2) }}</span></div>
        </div>

        @can('ventas.aplicar_descuento')
            <div class="mt-3 grid grid-cols-2 gap-2">
                <select wire:model="discountType" class="rounded-lg border border-slate-700 bg-slate-800 px-2 py-1.5 text-xs text-white">
                    <option value="">Sin descuento</option>
                    <option value="percentage">%</option>
                    <option value="amount">$</option>
                </select>
                <input type="number" step="0.01" wire:model="discountValue" placeholder="Valor" class="rounded-lg border border-slate-700 bg-slate-800 px-2 py-1.5 text-xs text-white">
            </div>
        @endcan

        <button
            wire:click="openCheckout"
            @if (empty($cart)) disabled @endif
            class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40"
        >
            Cobrar
        </button>
    </div>

    {{-- Modal de modificadores --}}
    @if ($modifierProduct)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 p-4">
            <div class="w-full max-w-md rounded-xl border border-slate-800 bg-slate-900 p-6">
                <h3 class="mb-4 text-lg font-semibold">{{ $modifierProduct->name }}</h3>

                @if ($modifierError)
                    <p class="mb-3 text-sm text-red-400">{{ $modifierError }}</p>
                @endif

                @foreach ($modifierProduct->modifierGroups as $group)
                    <div class="mb-4">
                        <div class="mb-2 text-sm font-medium">
                            {{ $group->name }}
                            @if ($group->is_required) <span class="text-red-400">*</span> @endif
                        </div>
                        @foreach ($group->options as $option)
                            <label class="mb-1 flex items-center justify-between rounded-lg border border-slate-800 px-3 py-2 text-sm hover:bg-slate-800">
                                <span class="flex items-center gap-2">
                                    @if ($group->max_selections > 1)
                                        <input type="checkbox" wire:model="modifierSelections.{{ $group->id }}" value="{{ $option->id }}">
                                    @else
                                        <input type="radio" wire:model="modifierSelections.{{ $group->id }}" value="{{ $option->id }}">
                                    @endif
                                    {{ $option->name }}
                                </span>
                                <span class="text-slate-500">
                                    @if ((float) $option->price_delta > 0) +${{ number_format((float) $option->price_delta, 2) }} @else gratis @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endforeach

                <input type="text" wire:model="modifierNotes" placeholder="Notas (p. ej. sin cebolla)" class="mb-4 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">

                <div class="flex gap-2">
                    <button wire:click="confirmAddWithModifiers" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">Agregar</button>
                    <button wire:click="cancelModifiers" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de cobro --}}
    @if ($showCheckout)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 p-4">
            <div class="w-full max-w-md rounded-xl border border-slate-800 bg-slate-900 p-6">
                <h3 class="mb-4 text-lg font-semibold">Cobrar ${{ number_format($this->total(), 2) }}</h3>

                @if ($checkoutError)
                    <p class="mb-3 text-sm text-red-400">{{ $checkoutError }}</p>
                @endif

                <div class="mb-3">
                    <label class="mb-1 block text-sm text-slate-300">Cliente (opcional)</label>
                    <select wire:model="customerId" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                        <option value="">Sin cliente</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 space-y-2">
                    @foreach ($paymentRows as $index => $row)
                        <div class="flex gap-2">
                            <select wire:model="paymentRows.{{ $index }}.method" class="w-1/3 rounded-lg border border-slate-700 bg-slate-800 px-2 py-2 text-xs text-white">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" wire:model="paymentRows.{{ $index }}.amount" placeholder="Monto" class="flex-1 rounded-lg border border-slate-700 bg-slate-800 px-2 py-2 text-xs text-white">
                            @if ($row['method'] === 'efectivo')
                                <input type="number" step="0.01" wire:model="paymentRows.{{ $index }}.received_amount" placeholder="Recibido" class="flex-1 rounded-lg border border-slate-700 bg-slate-800 px-2 py-2 text-xs text-white">
                            @endif
                            @if (count($paymentRows) > 1)
                                <button wire:click="removePaymentRow({{ $index }})" class="text-red-400">&times;</button>
                            @endif
                        </div>
                    @endforeach

                    <button wire:click="addPaymentRow" class="text-xs text-indigo-400 hover:text-indigo-300">+ Agregar método de pago</button>
                </div>

                <div class="mb-4 flex justify-between text-sm text-slate-400">
                    <span>Pagado</span>
                    <span>${{ number_format($this->paymentsTotal(), 2) }}</span>
                </div>

                <div class="flex gap-2">
                    <button wire:click="checkout" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold hover:bg-emerald-500">Confirmar cobro</button>
                    <button wire:click="closeCheckout" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Venta completada --}}
    @if ($completedFolio)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
            <div class="w-full max-w-sm rounded-xl border border-emerald-800 bg-slate-900 p-6 text-center">
                <h3 class="mb-2 text-xl font-semibold text-emerald-400">Venta completada</h3>
                <p class="mb-4 text-slate-300">Folio {{ $completedFolio }}</p>
                <div class="flex flex-col gap-2">
                    <a href="{{ route('ventas.ticket', $completedOrderId) }}" target="_blank" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                        Ver / imprimir ticket
                    </a>
                    <button wire:click="startNewSale" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">
                        Nueva venta
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
