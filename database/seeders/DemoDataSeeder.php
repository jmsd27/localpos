<?php

namespace Database\Seeders;

use App\Enums\CashRegisterSessionStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RecipeItem;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\TableArea;
use App\Models\Terminal;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

/**
 * Datos demo para probar LOCALPOS de punta a punta: categorías, productos,
 * clientes, proveedores, insumos con receta, mesas, un cajero/mesero extra,
 * una caja abierta y algunas ventas ya cobradas.
 *
 * Idempotente: usa firstOrCreate/updateOrCreate en todo, así que correrlo
 * varias veces no duplica catálogo. Las ventas de ejemplo solo se generan
 * si el negocio todavía no tiene ninguna orden.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::query()->firstOrFail();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();

        $stations = $this->seedKitchenStations($business, $branch);
        $categories = $this->seedCategories($business);
        $products = $this->seedProducts($business, $categories, $stations);
        $this->seedModifiers($products);
        $this->seedCustomers($business);
        $this->seedSuppliers($business);
        $this->seedInventory($business, $branch, $products);
        $this->seedFloor($business, $branch);
        $this->seedDemoUsers($branch);
        $terminal = $this->seedTerminalAndRegister($business, $branch);
        $this->seedCashSessionAndSales($business, $branch, $terminal, $products);

        $this->command?->info('Datos demo generados.');
    }

    /** @return array<string, KitchenStation> */
    private function seedKitchenStations(Business $business, Branch $branch): array
    {
        $stations = [
            'cocina' => 'Cocina',
            'barra' => 'Barra',
        ];

        return collect($stations)->mapWithKeys(fn ($name, $code) => [
            $code => KitchenStation::query()->firstOrCreate(
                ['business_id' => $business->id, 'code' => $code],
                ['branch_id' => $branch->id, 'name' => $name, 'is_active' => true],
            ),
        ])->all();
    }

    /** @return array<string, ProductCategory> */
    private function seedCategories(Business $business): array
    {
        $categories = [
            'Entradas' => ['color' => '#f59e0b', 'sort_order' => 1],
            'Platos fuertes' => ['color' => '#8b5cf6', 'sort_order' => 2],
            'Postres' => ['color' => '#ec4899', 'sort_order' => 3],
            'Bebidas' => ['color' => '#0ea5e9', 'sort_order' => 4],
            'Cervezas y cocteles' => ['color' => '#f43f5e', 'sort_order' => 5],
        ];

        return collect($categories)->mapWithKeys(fn ($attrs, $name) => [
            $name => ProductCategory::query()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                [...$attrs, 'is_active' => true],
            ),
        ])->all();
    }

    /**
     * @param  array<string, ProductCategory>  $categories
     * @param  array<string, KitchenStation>  $stations
     * @return array<string, Product>
     */
    private function seedProducts(Business $business, array $categories, array $stations): array
    {
        $definitions = [
            ['sku' => 'ENT-001', 'name' => 'Guacamole con totopos', 'price' => 95, 'cost' => 40, 'category' => 'Entradas', 'station' => 'cocina'],
            ['sku' => 'ENT-002', 'name' => 'Queso fundido', 'price' => 85, 'cost' => 35, 'category' => 'Entradas', 'station' => 'cocina'],
            ['sku' => 'ENT-003', 'name' => 'Alitas BBQ (12pz)', 'price' => 135, 'cost' => 60, 'category' => 'Entradas', 'station' => 'cocina'],
            ['sku' => 'ENT-004', 'name' => 'Nachos con queso', 'price' => 75, 'cost' => 30, 'category' => 'Entradas', 'station' => 'cocina'],

            ['sku' => 'PF-001', 'name' => 'Tacos al pastor (orden)', 'price' => 89, 'cost' => 40, 'category' => 'Platos fuertes', 'station' => 'cocina'],
            ['sku' => 'PF-002', 'name' => 'Arrachera a la parrilla', 'price' => 195, 'cost' => 95, 'category' => 'Platos fuertes', 'station' => 'cocina', 'inventoried' => true],
            ['sku' => 'PF-003', 'name' => 'Enchiladas verdes', 'price' => 110, 'cost' => 45, 'category' => 'Platos fuertes', 'station' => 'cocina'],
            ['sku' => 'PF-004', 'name' => 'Milanesa de pollo', 'price' => 125, 'cost' => 55, 'category' => 'Platos fuertes', 'station' => 'cocina'],

            ['sku' => 'PST-001', 'name' => 'Flan napolitano', 'price' => 55, 'cost' => 20, 'category' => 'Postres', 'station' => 'cocina'],
            ['sku' => 'PST-002', 'name' => 'Pastel de tres leches', 'price' => 65, 'cost' => 25, 'category' => 'Postres', 'station' => 'cocina'],
            ['sku' => 'PST-003', 'name' => 'Helado de vainilla', 'price' => 45, 'cost' => 18, 'category' => 'Postres', 'station' => 'cocina'],

            ['sku' => 'BEB-001', 'name' => 'Agua fresca de horchata', 'price' => 35, 'cost' => 12, 'category' => 'Bebidas', 'station' => 'barra'],
            ['sku' => 'BEB-002', 'name' => 'Refresco', 'price' => 30, 'cost' => 10, 'category' => 'Bebidas', 'station' => 'barra'],
            ['sku' => 'BEB-003', 'name' => 'Limonada', 'price' => 32, 'cost' => 11, 'category' => 'Bebidas', 'station' => 'barra'],
            ['sku' => 'BEB-004', 'name' => 'Café americano', 'price' => 28, 'cost' => 8, 'category' => 'Bebidas', 'station' => 'barra'],

            ['sku' => 'ALC-001', 'name' => 'Cerveza Corona', 'price' => 45, 'cost' => 20, 'category' => 'Cervezas y cocteles', 'station' => 'barra', 'inventoried' => true],
            ['sku' => 'ALC-002', 'name' => 'Cerveza Victoria', 'price' => 45, 'cost' => 20, 'category' => 'Cervezas y cocteles', 'station' => 'barra'],
            ['sku' => 'ALC-003', 'name' => 'Margarita', 'price' => 95, 'cost' => 35, 'category' => 'Cervezas y cocteles', 'station' => 'barra'],
            ['sku' => 'ALC-004', 'name' => 'Mojito', 'price' => 105, 'cost' => 38, 'category' => 'Cervezas y cocteles', 'station' => 'barra'],
            ['sku' => 'ALC-005', 'name' => 'Michelada', 'price' => 65, 'cost' => 25, 'category' => 'Cervezas y cocteles', 'station' => 'barra'],
        ];

        $products = [];

        foreach ($definitions as $definition) {
            $products[$definition['sku']] = Product::query()->firstOrCreate(
                ['business_id' => $business->id, 'sku' => $definition['sku']],
                [
                    'product_category_id' => $categories[$definition['category']]->id,
                    'kitchen_station_id' => $stations[$definition['station']]->id,
                    'name' => $definition['name'],
                    'price' => $definition['price'],
                    'cost_price' => $definition['cost'],
                    'tax_rate' => 16,
                    'unit' => 'pieza',
                    'is_inventoried' => $definition['inventoried'] ?? false,
                    'is_sellable' => true,
                    'is_active' => true,
                ],
            );
        }

        return $products;
    }

    /** @param array<string, Product> $products */
    private function seedModifiers(array $products): void
    {
        $termino = ModifierGroup::query()->firstOrCreate(
            ['business_id' => $products['PF-002']->business_id, 'name' => 'Término'],
            ['is_required' => true, 'min_selections' => 1, 'max_selections' => 1],
        );

        if ($termino->wasRecentlyCreated) {
            foreach (['Término medio', 'Bien cocido', 'Término rojo'] as $i => $name) {
                $termino->options()->create(['name' => $name, 'price_delta' => 0, 'is_active' => true, 'sort_order' => $i]);
            }
        }

        $tamano = ModifierGroup::query()->firstOrCreate(
            ['business_id' => $products['BEB-001']->business_id, 'name' => 'Tamaño'],
            ['is_required' => true, 'min_selections' => 1, 'max_selections' => 1],
        );

        if ($tamano->wasRecentlyCreated) {
            $tamano->options()->create(['name' => 'Chico', 'price_delta' => 0, 'is_active' => true, 'sort_order' => 0]);
            $tamano->options()->create(['name' => 'Grande', 'price_delta' => 10, 'is_active' => true, 'sort_order' => 1]);
        }

        $products['PF-002']->modifierGroups()->syncWithoutDetaching([$termino->id]);

        foreach (['BEB-001', 'BEB-002', 'BEB-003'] as $sku) {
            $products[$sku]->modifierGroups()->syncWithoutDetaching([$tamano->id]);
        }
    }

    private function seedCustomers(Business $business): void
    {
        $customers = [
            ['name' => 'Juan Pérez', 'phone' => '555-101-2020', 'email' => 'juan.perez@example.com'],
            ['name' => 'María González', 'phone' => '555-202-3030', 'email' => 'maria.gonzalez@example.com'],
            ['name' => 'Carlos Ramírez', 'phone' => '555-303-4040', 'email' => 'carlos.ramirez@example.com'],
        ];

        foreach ($customers as $customer) {
            Customer::query()->firstOrCreate(
                ['business_id' => $business->id, 'email' => $customer['email']],
                ['name' => $customer['name'], 'phone' => $customer['phone']],
            );
        }
    }

    private function seedSuppliers(Business $business): void
    {
        $suppliers = [
            ['name' => 'Distribuidora La Central', 'contact_name' => 'Luis Herrera', 'phone' => '555-404-5050'],
            ['name' => 'Cervecería Regional', 'contact_name' => 'Ana Torres', 'phone' => '555-505-6060'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $supplier['name']],
                ['contact_name' => $supplier['contact_name'], 'phone' => $supplier['phone'], 'is_active' => true],
            );
        }
    }

    /** @param array<string, Product> $products */
    private function seedInventory(Business $business, Branch $branch, array $products): void
    {
        $ingredients = [
            'Carne de arrachera' => ['unit' => 'kg', 'stock' => 20, 'min_stock' => 5, 'cost' => 180, 'recipe' => ['PF-002', 0.35]],
            'Cerveza Corona 355ml' => ['unit' => 'botella', 'stock' => 100, 'min_stock' => 24, 'cost' => 14, 'recipe' => ['ALC-001', 1]],
            'Papa' => ['unit' => 'kg', 'stock' => 15, 'min_stock' => 5, 'cost' => 12, 'recipe' => ['ENT-004', 0.2]],
        ];

        foreach ($ingredients as $name => $attrs) {
            $ingredient = Ingredient::query()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                [
                    'branch_id' => $branch->id,
                    'unit' => $attrs['unit'],
                    'stock' => $attrs['stock'],
                    'min_stock' => $attrs['min_stock'],
                    'cost_per_unit' => $attrs['cost'],
                    'is_active' => true,
                ],
            );

            [$sku, $quantity] = $attrs['recipe'];

            RecipeItem::query()->firstOrCreate([
                'product_id' => $products[$sku]->id,
                'ingredient_id' => $ingredient->id,
            ], ['quantity' => $quantity]);
        }
    }

    private function seedFloor(Business $business, Branch $branch): void
    {
        $areas = [
            'Salón principal' => ['capacity' => 4, 'count' => 6],
            'Terraza' => ['capacity' => 2, 'count' => 3],
        ];

        $tableNumber = 1;

        foreach ($areas as $areaName => $config) {
            $area = TableArea::query()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $areaName],
                ['branch_id' => $branch->id, 'sort_order' => $tableNumber],
            );

            for ($i = 0; $i < $config['count']; $i++) {
                Table::query()->firstOrCreate(
                    ['business_id' => $business->id, 'table_area_id' => $area->id, 'name' => 'Mesa '.$tableNumber],
                    ['branch_id' => $branch->id, 'capacity' => $config['capacity'], 'status' => 'available'],
                );

                $tableNumber++;
            }
        }
    }

    private function seedDemoUsers(Branch $branch): void
    {
        $users = [
            ['email' => 'cajero@localpos.local', 'name' => 'Ana Cajero', 'role' => RoleName::Cajero],
            ['email' => 'mesero@localpos.local', 'name' => 'Luis Mesero', 'role' => RoleName::Mesero],
        ];

        foreach ($users as $definition) {
            $user = User::query()->firstOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => 'localpos-admin',
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ],
            );

            if (! $user->hasRole($definition['role']->value)) {
                $user->assignRole($definition['role']->value);
            }
        }
    }

    private function seedTerminalAndRegister(Business $business, Branch $branch): Terminal
    {
        $register = CashRegister::query()->firstOrCreate(
            ['business_id' => $business->id, 'code' => 'principal'],
            ['branch_id' => $branch->id, 'name' => 'Caja Principal', 'is_active' => true],
        );

        $terminal = Terminal::query()->where('business_id', $business->id)->first();

        if (! $terminal) {
            $terminal = Terminal::create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'cash_register_id' => $register->id,
                'name' => 'CAJA1',
                'code' => 'caja-1',
                'is_active' => true,
            ]);
        } elseif (! $terminal->cash_register_id) {
            $terminal->update(['cash_register_id' => $register->id]);
        }

        return $terminal;
    }

    /** @param array<string, Product> $products */
    private function seedCashSessionAndSales(Business $business, Branch $branch, Terminal $terminal, array $products): void
    {
        if (Order::query()->where('business_id', $business->id)->exists()) {
            return;
        }

        $admin = User::where('email', 'admin@localpos.local')->first();

        if (! $admin || ! $terminal->cash_register_id) {
            return;
        }

        $session = CashRegisterSession::query()
            ->where('cash_register_id', $terminal->cash_register_id)
            ->where('status', CashRegisterSessionStatus::Open)
            ->first();

        if (! $session) {
            $session = app(CashRegisterService::class)->open(
                $terminal->cash_register_id,
                $terminal->id,
                $admin->id,
                1000,
            );
            $session->update(['opened_at' => now()->subHours(3)]);
        }

        $sales = app(SaleService::class);
        $customer = Customer::query()->where('business_id', $business->id)->inRandomOrder()->first();

        $tickets = [
            ['skus' => ['PF-001', 'BEB-002'], 'method' => 'efectivo', 'tip' => 10],
            ['skus' => ['ENT-001', 'ALC-001', 'ALC-001'], 'method' => 'tarjeta', 'tip' => 20],
            ['skus' => ['PF-002', 'ALC-003'], 'method' => 'tarjeta', 'tip' => 30, 'customer' => true],
            ['skus' => ['PST-001', 'BEB-004'], 'method' => 'efectivo', 'tip' => 0],
            ['skus' => ['ENT-003', 'ALC-002', 'ALC-002', 'ALC-005'], 'method' => 'efectivo', 'tip' => 15],
            ['skus' => ['PF-004', 'BEB-001'], 'method' => 'efectivo', 'tip' => 5],
        ];

        foreach ($tickets as $ticket) {
            $items = collect($ticket['skus'])->map(fn ($sku) => [
                'product_id' => $products[$sku]->id,
                'name' => $products[$sku]->name,
                'quantity' => 1,
                'unit_price' => (float) $products[$sku]->price,
                'tax_rate' => (float) $products[$sku]->tax_rate,
                'notes' => null,
                'modifiers' => [],
            ])->all();

            $order = $sales->createDraftOrder([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'terminal_id' => $terminal->id,
                'cash_register_session_id' => $session->id,
                'user_id' => $admin->id,
                'customer_id' => ($ticket['customer'] ?? false) ? $customer?->id : null,
                'order_type' => 'mostrador',
            ]);

            $order = $sales->addItemsToOrder($order, $items);
            $finalTotal = round((float) $order->total + $ticket['tip'], 2);

            $sales->payOrder($order, [
                'tip_amount' => $ticket['tip'],
                'user_id' => $admin->id,
                'payments' => [[
                    'method' => $ticket['method'],
                    'amount' => $finalTotal,
                    'received_amount' => null,
                ]],
            ]);
        }
    }
}
