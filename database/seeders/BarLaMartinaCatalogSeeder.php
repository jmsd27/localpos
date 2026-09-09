<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Coupon;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RecipeItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\TableArea;
use App\Models\Terminal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Carga el catálogo real de Bar La Martina (menú, insumos, recetas, mesas,
 * caja) para la puesta en marcha en producción. Pensado para correr UNA vez
 * sobre una base de datos vacía o casi vacía (p. ej. el espejo de Vercel al
 * pasar de SYNC_ROLE=mirror a source) — es idempotente por nombre: si un
 * registro con ese nombre ya existe para el negocio, no lo duplica.
 *
 * No incluye usuarios: esos se crean aparte (ver
 * App\Console\Commands\ImportarDatosRealesCommand), para no versionar
 * contraseñas ni hashes en el repositorio.
 */
class BarLaMartinaCatalogSeeder extends Seeder
{
    private array $categoryRefs = [];

    private array $stationRefs = [];

    private array $productRefs = [];

    private array $ingredientRefs = [];

    public function run(): void
    {
        $business = Business::first() ?? Business::create(['name' => 'Bar La Martina']);
        $branch = Branch::where('business_id', $business->id)->first()
            ?? Branch::create(['business_id' => $business->id, 'name' => 'Sucursal Principal', 'code' => 'principal', 'is_main' => true]);

        $this->seedCategories($business->id);
        $this->seedStations($business->id, $branch->id);
        $this->seedProducts($business->id);
        $this->seedIngredients($business->id, $branch->id);
        $this->seedRecipeItems();
        $this->seedModifierGroups($business->id);
        $this->seedTableAreasAndTables($business->id, $branch->id);
        $this->seedCashRegisterAndTerminal($business->id, $branch->id);
        $this->seedSuppliers($business->id);
        $this->seedCoupons($business->id);
        $this->seedTicketSettings($business->id);
    }

    private function seedTicketSettings(int $businessId): void
    {
        $defaults = [
            'ticket_ancho' => '48',
            'ticket_feed' => '3',
            'ticket_pie' => '¡Gracias por su visita! — Bar La Martina',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['business_id' => $businessId, 'key' => $key],
                ['value' => $value, 'group' => 'ticket'],
            );
        }
    }

    private function seedCoupons(int $businessId): void
    {
        $coupons = [
            ['code' => 'CASA', 'name' => 'Cortesía de la casa', 'discount_type' => 'percentage', 'discount_value' => 100, 'max_uses' => null],
            ['code' => 'AMIGO15', 'name' => 'Descuento amigo 15%', 'discount_type' => 'percentage', 'discount_value' => 15, 'max_uses' => null],
            ['code' => 'CUMPLE', 'name' => 'Cumpleañero (postre gratis $80)', 'discount_type' => 'amount', 'discount_value' => 80, 'max_uses' => null],
        ];

        foreach ($coupons as $data) {
            Coupon::firstOrCreate(
                ['business_id' => $businessId, 'code' => $data['code']],
                $data + ['is_active' => true],
            );
        }
    }

    private function findOrCreateByName(string $model, array $data, int $businessId): object
    {
        $existing = $model::where('business_id', $businessId)->where('name', $data['name'])->first();

        return $existing ?: $model::create($data + ['business_id' => $businessId]);
    }

    private function seedCategories(int $businessId): void
    {
        $categories = [
            ['name' => 'Entradas', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Platos fuertes', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Postres', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Bebidas', 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Cervezas y cocteles', 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Comida', 'sort_order' => 6, 'is_active' => true],
            ['name' => 'Cerveza', 'sort_order' => 7, 'is_active' => true],
            ['name' => 'Tragos', 'sort_order' => 8, 'is_active' => true],
            ['name' => 'Whiskey', 'sort_order' => 10, 'is_active' => true],
            ['name' => 'Tequila', 'sort_order' => 11, 'is_active' => true],
            ['name' => 'Licores', 'sort_order' => 12, 'is_active' => true],
            ['name' => 'Vodka', 'sort_order' => 13, 'is_active' => true],
            ['name' => 'Brandy', 'sort_order' => 14, 'is_active' => true],
            ['name' => 'Ron', 'sort_order' => 15, 'is_active' => true],
        ];

        foreach ($categories as $index => $data) {
            $this->categoryRefs[$index] = $this->findOrCreateByName(ProductCategory::class, $data, $businessId)->id;
        }
    }

    private function seedStations(int $businessId, int $branchId): void
    {
        $stations = [
            ['name' => 'Cocina', 'code' => 'cocina', 'color' => '#6366f1', 'is_active' => true],
            ['name' => 'Barra', 'code' => 'barra', 'color' => '#6366f1', 'is_active' => true],
        ];

        foreach ($stations as $index => $data) {
            $existing = KitchenStation::where('business_id', $businessId)->where('name', $data['name'])->first();
            $this->stationRefs[$index] = $existing
                ? $existing->id
                : KitchenStation::create($data + ['business_id' => $businessId, 'branch_id' => $branchId])->id;
        }
    }

    private function seedProducts(int $businessId): void
    {
        // [ref viejo => [nombre, precio, iva, indice categoria|null, indice estacion|null]]
        $products = [
            1 => ['Guacamole con totopos', 95, 16, 0, 0], 2 => ['Queso fundido', 85, 16, 0, 0],
            3 => ['Alitas BBQ (12pz)', 135, 16, 0, 0], 4 => ['Nachos con queso', 75, 16, 0, 0],
            5 => ['Tacos al pastor (orden)', 89, 16, 1, 0], 6 => ['Arrachera a la parrilla', 195, 16, 1, 0],
            7 => ['Enchiladas verdes', 110, 16, 1, 0], 8 => ['Milanesa de pollo', 125, 16, 1, 0],
            9 => ['Flan napolitano', 55, 16, 2, 0], 10 => ['Pastel de tres leches', 65, 16, 2, 0],
            11 => ['Helado de vainilla', 45, 16, 2, 0], 12 => ['Agua fresca de horchata', 35, 16, 3, 1],
            13 => ['Refresco', 30, 16, 3, 1], 14 => ['Limonada', 32, 16, 3, 1],
            15 => ['Café americano', 28, 16, 3, 1], 16 => ['Cerveza Corona', 45, 16, 4, 1],
            17 => ['Cerveza Victoria', 45, 16, 4, 1], 18 => ['Margarita', 95, 16, 4, 1],
            19 => ['Mojito', 105, 16, 4, 1], 20 => ['Michelada', 65, 16, 4, 1],
            21 => ['Hamburguesa Sencilla', 120, 16, 5, 0], 22 => ['Hamburguesa Doble', 150, 16, 5, 0],
            23 => ['Hamburguesa Hawaiana', 130, 16, 5, 0], 24 => ['Boneless', 190, 16, 5, 0],
            25 => ['Papas Francesas', 90, 16, 5, 0], 26 => ['Papas La Martina', 280, 16, 5, 0],
            27 => ['Papas Preparadas', 110, 16, 5, 0], 28 => ['Chicharrones', 80, 16, 5, 0],
            29 => ['Charola Martina', 0, 16, 5, 0], 30 => ['Nachos Especiales con Bistec', 140, 16, 5, 0],
            31 => ['Nachos Sencillos', 110, 16, 5, 0], 32 => ['Papa Nachos Especiales con Bistec', 160, 16, 5, 0],
            33 => ['Papa Nachos Sencillos', 120, 16, 5, 0], 34 => ['Camarón Seco', 100, 16, 5, 0],
            35 => ['Carne Seca', 120, 16, 5, 0], 36 => ['Tacos de Bistec', 140, 16, 5, 0],
            37 => ['Tacos de Pastor', 100, 16, 5, 0], 38 => ['Stella', 50, 16, 6, 1],
            39 => ['Bud Light', 35, 16, 6, 1], 40 => ['Modelo Especial', 40, 16, 6, 1],
            41 => ['Modelo Negra', 45, 16, 6, 1], 42 => ['Modelo 0', 40, 16, 6, 1],
            43 => ['Modelo Malta', 50, 16, 6, 1], 44 => ['Michelob Ultra', 40, 16, 6, 1],
            45 => ['Pacífico Suave', 40, 16, 6, 1], 46 => ['Pacifico Clara', 40, 16, 6, 1],
            47 => ['Corona Extra', 40, 16, 6, 1], 48 => ['Victoria', 40, 16, 6, 1],
            49 => ['Caribe', 55, 16, 6, 1], 50 => ['Barrilito', 35, 16, 6, 1],
            51 => ['Skyy', 55, 16, 6, 1], 52 => ['Pacífico Clara (Caguama)', 90, 16, 6, 1],
            53 => ['Bud Light (Caguama)', 85, 16, 6, 1], 54 => ['Modelo Especial (Caguama)', 95, 16, 6, 1],
            55 => ['Michelob (Caguama)', 90, 16, 6, 1], 56 => ['Victoria (Caguama)', 85, 16, 6, 1],
            57 => ['Victoria Mega (Caguama)', 95, 16, 6, 1], 58 => ['Corona (Caguama)', 85, 16, 6, 1],
            59 => ['Corona Mega (Caguama)', 95, 16, 6, 1], 60 => ['Cantarito Chico', 65, 16, 7, 1],
            61 => ['Cantarito Litro', 150, 16, 7, 1], 62 => ['Azulito', 130, 16, 7, 1],
            63 => ['Piña Colada', 150, 16, 7, 1], 64 => ['Tequila Sonrise', 130, 16, 7, 1],
            65 => ['Cosmopolita', 160, 16, 7, 1], 66 => ['Gin Tonic Frutos Rojos', 120, 16, 7, 1],
            67 => ['Amarre de Amor', 150, 16, 7, 1], 68 => ['Colombia', 130, 16, 7, 1],
            69 => ['Criollo', 130, 16, 7, 1], 70 => ['Fuego', 130, 16, 7, 1],
            71 => ['Perla Negra', 130, 16, 7, 1], 72 => ['Margarita', 100, 16, 7, 1],
            73 => ['Shots de Mango', 15, 16, 7, 1], 74 => ['Agua', 40, 16, 3, 1],
            75 => ['Limonada Grande', 70, 16, 3, 1], 76 => ['Clamato Preparado', 60, 16, 3, 1],
            77 => ['Clamato Preparado Litro', 120, 16, 3, 1], 78 => ['Michelado', 35, 16, 3, 1],
            79 => ['Passport (1 onza)', 55, 16, 8, 1], 80 => ['Passport (1 litro)', 110, 16, 8, 1],
            81 => ['Passport (botella)', 750, 16, 8, 1], 82 => ['Red Label (1 onza)', 65, 16, 8, 1],
            83 => ['Red Label (1 litro)', 140, 16, 8, 1], 84 => ['Red Label (botella)', 1100, 16, 8, 1],
            85 => ["Jack Daniel's (1 onza)", 65, 16, 8, 1], 86 => ["Jack Daniel's (1 litro)", 150, 16, 8, 1],
            87 => ["Jack Daniel's (botella)", 1200, 16, 8, 1], 88 => ['Black Label (1 onza)', 90, 16, 8, 1],
            89 => ['Black Label (1 litro)', 180, 16, 8, 1], 90 => ['Black Label (botella)', 1800, 16, 8, 1],
            91 => ["Buchanan's (1 onza)", 90, 16, 8, 1], 92 => ["Buchanan's (1 litro)", 180, 16, 8, 1],
            93 => ["Buchanan's (botella)", 1800, 16, 8, 1], 94 => ['Bajio (1 onza)', 40, 16, 9, 1],
            95 => ['Bajio (1 litro)', 70, 16, 9, 1], 96 => ['Centenario (1 onza)', 50, 16, 9, 1],
            97 => ['Centenario (1 litro)', 120, 16, 9, 1], 98 => ['Centenario (botella)', 1100, 16, 9, 1],
            99 => ['Tradicional Rep (1 onza)', 60, 16, 9, 1], 100 => ['Tradicional Rep (1 litro)', 125, 16, 9, 1],
            101 => ['Tradicional Rep (botella)', 1250, 16, 9, 1], 102 => ['Tradicional Plata (1 onza)', 70, 16, 9, 1],
            103 => ['Tradicional Plata (1 litro)', 135, 16, 9, 1], 104 => ['Tradicional Plata (botella)', 1350, 16, 9, 1],
            105 => ['Hornitos Reposado (1 onza)', 65, 16, 9, 1], 106 => ['Hornitos Reposado (1 litro)', 130, 16, 9, 1],
            107 => ['Hornitos Reposado (botella)', 1300, 16, 9, 1], 108 => ['1800 Cristalino (1 onza)', 110, 16, 9, 1],
            109 => ['1800 Cristalino (1 litro)', 200, 16, 9, 1], 110 => ['1800 Cristalino (botella)', 2100, 16, 9, 1],
            111 => ['Don Julio (1 onza)', 100, 16, 9, 1], 112 => ['Don Julio (1 litro)', 190, 16, 9, 1],
            113 => ['Don Julio (botella)', 1950, 16, 9, 1], 114 => ['Maestro Dobel (1 onza)', 90, 16, 9, 1],
            115 => ['Maestro Dobel (1 litro)', 180, 16, 9, 1], 116 => ['Maestro Dobel (botella)', 1800, 16, 9, 1],
            117 => ['Jagger (1 onza)', 70, 16, 10, 1], 118 => ['Jagger (1 litro)', 140, 16, 10, 1],
            119 => ['Licor 43 (1 onza)', 55, 16, 10, 1], 120 => ['Licor 43 (1 litro)', 140, 16, 10, 1],
            121 => ['Zaverich (1 onza)', 30, 16, 11, 1], 122 => ['Zaverich (1 litro)', 80, 16, 11, 1],
            123 => ['Zaverich (botella)', 450, 16, 11, 1], 124 => ['Oso Negro (1 onza)', 40, 16, 11, 1],
            125 => ['Oso Negro (1 litro)', 90, 16, 11, 1], 126 => ['Oso Negro (botella)', 550, 16, 11, 1],
            127 => ['Absolut (1 onza)', 55, 16, 11, 1], 128 => ['Absolut (1 litro)', 110, 16, 11, 1],
            129 => ['Absolut (botella)', 1050, 16, 11, 1], 130 => ['Absolut Rasberry (1 onza)', 55, 16, 11, 1],
            131 => ['Absolut Rasberry (1 litro)', 110, 16, 11, 1], 132 => ['Absolut Rasberry (botella)', 1050, 16, 11, 1],
            133 => ['Smirnoff (1 onza)', 55, 16, 11, 1], 134 => ['Smirnoff (1 litro)', 130, 16, 11, 1],
            135 => ['Smirnoff (botella)', 1150, 16, 11, 1], 136 => ['Azteca de Oro (1 onza)', 40, 16, 12, 1],
            137 => ['Azteca de Oro (1 litro)', 90, 16, 12, 1], 138 => ['Azteca de Oro (botella)', 600, 16, 12, 1],
            139 => ['Torres 5 (1 onza)', 45, 16, 12, 1], 140 => ['Torres 5 (1 litro)', 100, 16, 12, 1],
            141 => ['Torres 5 (botella)', 750, 16, 12, 1], 142 => ['Torres 10 (1 onza)', 50, 16, 12, 1],
            143 => ['Torres 10 (1 litro)', 110, 16, 12, 1], 144 => ['Torres 10 (botella)', 900, 16, 12, 1],
            145 => ['Presidente (1 onza)', 40, 16, 12, 1], 146 => ['Presidente (1 litro)', 90, 16, 12, 1],
            147 => ['Presidente (botella)', 750, 16, 12, 1], 148 => ['Ron de la Casa (1 onza)', 40, 16, 13, 1],
            149 => ['Ron de la Casa (1 litro)', 90, 16, 13, 1], 150 => ['Bacardi (1 onza)', 45, 16, 13, 1],
            151 => ['Bacardi (1 litro)', 100, 16, 13, 1], 152 => ['Bacardi (botella)', 900, 16, 13, 1],
            153 => ['Capitán Morgan (1 onza)', 50, 16, 13, 1], 154 => ['Capitán Morgan (1 litro)', 110, 16, 13, 1],
            155 => ['Capitán Morgan (botella)', 900, 16, 13, 1],
        ];

        foreach ($products as $ref => [$name, $price, $tax, $categoryIndex, $stationIndex]) {
            $categoryId = $categoryIndex !== null ? $this->categoryRefs[$categoryIndex] : null;

            // Match por nombre + categoría, no solo nombre: el menú fuente
            // repite "Margarita" en dos categorías distintas (coctel de
            // barra vs. trago con tequila) a propósito, con precios propios.
            $product = Product::where('business_id', $businessId)
                ->where('name', $name)
                ->where('product_category_id', $categoryId)
                ->first();

            if (! $product) {
                $product = Product::create([
                    'business_id' => $businessId,
                    'name' => $name,
                    'price' => $price,
                    'tax_rate' => $tax,
                    'product_category_id' => $categoryId,
                    'kitchen_station_id' => $stationIndex !== null ? $this->stationRefs[$stationIndex] : null,
                    'is_sellable' => true,
                    'is_active' => true,
                ]);
            }

            $this->productRefs[$ref] = $product->id;
        }
    }

    private function seedIngredients(int $businessId, int $branchId): void
    {
        // [ref viejo => [nombre, unidad]]
        $ingredients = [
            1 => ['Carne de arrachera', 'kg'], 2 => ['Cerveza Corona 355ml', 'botella'], 3 => ['Papa', 'kg'],
            4 => ['Queso para nachos', 'caja'], 5 => ['Chile Curtido', 'kg'], 6 => ['Chile Jalapeño', 'caja'],
            7 => ['Tomate', 'kg'], 8 => ['Cebolla', 'kg'], 9 => ['Limon', 'kg'], 10 => ['Pepino', 'kg'],
            11 => ['Lechuga', 'pieza'], 12 => ['Papa Casera', 'pieza'], 13 => ['Bisteck', 'kg'],
            14 => ['Carne Molina', 'kg'], 15 => ['Carne Pastor', 'kg'], 16 => ['Camaron Seco', 'kg'],
            17 => ['Carne Seca', 'kg'], 18 => ['Cacahates', 'kg'], 19 => ['Rielitos', 'kg'],
            20 => ['Mayonesa', 'botella'], 21 => ['Capsup', 'botella'], 22 => ['Mostaza', 'botella'],
            23 => ['Queso Laurel', 'kg'], 24 => ['Cilantro', 'pieza'], 25 => ['Aceitunas', 'botella'],
            26 => ['Cerezas', 'botella'], 27 => ['Fresas Congeladas', 'caja'], 28 => ['Crema chantigi', 'botella'],
            29 => ['Huevo', 'caja'], 30 => ['Leche clavel', 'litro'], 31 => ['Leche coco', 'litro'],
            32 => ['Jugo naranja', 'litro'], 33 => ['Jugo Piña', 'litro'], 34 => ['Jugo de Arandono', 'litro'],
            35 => ['Jugo de Mango', 'litro'], 36 => ['Aguas', 'caja'], 37 => ['Refresco toronga', 'caja'],
            38 => ['Refresco coca-cola', 'caja'], 39 => ['Refresco agua mineral', 'caja'],
            40 => ['Aceito Porror de 10 L', 'caja'], 41 => ['Endulzaste Granadina', 'litro'],
            42 => ['Comida empleado', 'pieza'], 43 => ['Papel de baño', 'caja'], 44 => ['Sanitas paraa baño', 'caja'],
            45 => ['Papeel canela', 'caja'], 46 => ['Porron de 20 L de fabuloso', 'caja'],
            47 => ['Cloralex de 20 L desengrasante lavalosa', 'caja'], 48 => ['Trapeador', 'pieza'],
            49 => ['Escoba', 'pieza'], 50 => ['Recojedores', 'pieza'], 51 => ['Trapos', 'pieza'],
            52 => ['Cerveza Victoria', 'botella'], 53 => ['Stella', 'botella'], 54 => ['Bud Light', 'botella'],
            55 => ['Modelo Especial', 'botella'], 56 => ['Modelo Negra', 'botella'], 57 => ['Modelo 0', 'botella'],
            58 => ['Modelo Malta', 'botella'], 59 => ['Michelob Ultra', 'botella'], 60 => ['Pacífico Suave', 'botella'],
            61 => ['Pacifico Clara', 'botella'], 62 => ['Corona Extra', 'botella'], 63 => ['Victoria', 'botella'],
            64 => ['Caribe', 'botella'], 65 => ['Barrilito', 'botella'], 66 => ['Skyy', 'botella'],
            67 => ['Pacífico Clara (Caguama)', 'botella'], 68 => ['Michelob', 'botella'],
            69 => ['Victoria Mega', 'botella'], 70 => ['Corona', 'botella'], 71 => ['Corona Mega', 'botella'],
            72 => ['Passport', 'onza'], 73 => ['Red Label', 'onza'], 74 => ["Jack Daniel's", 'onza'],
            75 => ['Black Label', 'onza'], 76 => ["Buchanan's", 'onza'], 77 => ['Centenario', 'onza'],
            78 => ['Tradicional Rep', 'onza'], 79 => ['Tradicional Plata', 'onza'],
            80 => ['Hornitos Reposado', 'onza'], 81 => ['1800 Cristalino', 'onza'], 82 => ['Don Julio', 'onza'],
            83 => ['Maestro Dobel', 'onza'], 84 => ['Zaverich', 'onza'], 85 => ['Oso Negro', 'onza'],
            86 => ['Absolut', 'onza'], 87 => ['Absolut Rasberry', 'onza'], 88 => ['Smirnoff', 'onza'],
            89 => ['Azteca de Oro', 'onza'], 90 => ['Torres 5', 'onza'], 91 => ['Torres 10', 'onza'],
            92 => ['Presidente', 'onza'], 93 => ['Bacardi', 'onza'], 94 => ['Capitán Morgan', 'onza'],
            95 => ['Bajio', 'onza'], 96 => ['Jagger', 'onza'], 97 => ['Licor 43', 'onza'],
            98 => ['Ron de la Casa', 'onza'],
            // Del conteo físico por fotos del 2026-09-08 (licores/cervezas que
            // no tenían insumo todavía). Todos en onzas como en la hoja de
            // conteo, salvo "VOH": su "28" quedó igual sin convertir (ver nota
            // en database/data-real/conteo-2026-09-08.md — es dudoso si esa
            // cifra era en onzas o ya en botellas/"medias").
            99 => ['Don Julio 70', 'onza'], 100 => ['Jose Cuervo Margarita', 'onza'],
            101 => ['Etiqueta Negra', 'onza'], 102 => ['Ginebra', 'onza'],
            103 => ['Bacardi Sabores', 'onza'], 104 => ['Baileys', 'onza'],
            105 => ['Don Pedro', 'onza'], 106 => ['Hpnotiq', 'onza'],
            107 => ['Chivas 12', 'onza'], 108 => ['Gran Malo Horchata', 'onza'],
            109 => ['Gran Malo Tamarindo', 'onza'], 110 => ['Gran Malo Jamaica', 'onza'],
            111 => ['Don Julio Cristalino', 'onza'], 112 => ['7 Leguas', 'onza'],
            113 => ['Jose Cuervo Cristalino', 'onza'], 114 => ['Centenario Reposado', 'onza'],
            115 => ['Hornitos Cristalino', 'onza'], 116 => ["Jack Daniel's Piña", 'onza'],
            117 => ['Black & White', 'onza'], 118 => ['1800 Añejo', 'onza'],
            119 => ['Conti', 'onza'], 120 => ['100 Conejos', 'onza'],
            121 => ['Campari', 'onza'], 122 => ['Ampevol', 'onza'],
            123 => ['Cinzano', 'onza'], 124 => ['Kahlúa', 'onza'],
            125 => ['Absolut Azul', 'onza'], 126 => ['Cointreau', 'onza'],
            127 => ['Martell', 'onza'], 128 => ["Buchanan's Piña", 'onza'],
            129 => ['Appleton', 'onza'], 130 => ['Anís', 'onza'],
            131 => ['Chichón', 'onza'], 132 => ['Jefe', 'onza'],
            133 => ['Flamingo', 'onza'], 134 => ['VOH', 'botella'],
        ];

        // Existencia real del conteo físico por fotos del 2026-09-08: licores
        // en onzas (tal cual la hoja de conteo, sin convertir a botella —
        // el personal cuenta el sobrante en onzas, no en fracciones de
        // botella), cervezas/caguamas por unidad. Los insumos que no
        // aparecen acá (comida, limpieza, etc.) quedan en 0 — se cargan con
        // Conteo físico cuando el negocio los cuente. Ver
        // database/data-real/conteo-2026-09-08.md para el detalle. "VOH" es
        // la excepción: quedó en botellas (ver nota junto a su unidad más
        // arriba).
        $stockCounts = [
            'Don Julio 70' => 39, 'Jose Cuervo Margarita' => 61, "Jack Daniel's" => 96,
            'Red Label' => 149, 'Tradicional Plata' => 159, 'Tradicional Rep' => 46,
            'Capitán Morgan' => 72, 'Etiqueta Negra' => 21, 'Ginebra' => 70,
            'Hornitos Reposado' => 33, 'Smirnoff' => 57, 'Bacardi Sabores' => 4,
            'Presidente' => 31, 'Baileys' => 34, 'Don Pedro' => 25, 'Hpnotiq' => 34,
            "Buchanan's" => 95, 'Chivas 12' => 78, 'Gran Malo Horchata' => 36,
            'Gran Malo Tamarindo' => 46, 'Gran Malo Jamaica' => 25, 'Don Julio Cristalino' => 38,
            '7 Leguas' => 44, 'Jose Cuervo Cristalino' => 25, 'Maestro Dobel' => 40,
            'Centenario Reposado' => 17, 'Hornitos Cristalino' => 4, 'Jagger' => 27,
            "Jack Daniel's Piña" => 20, 'Azteca de Oro' => 44, 'Passport' => 15,
            '1800 Cristalino' => 2, 'Black & White' => 69, '1800 Añejo' => 85,
            'Conti' => 44, '100 Conejos' => 92, 'Campari' => 75, 'Ampevol' => 69,
            'Cinzano' => 25, 'Kahlúa' => 19, 'Zaverich' => 30, 'Absolut Rasberry' => 24,
            'Absolut Azul' => 52, 'Cointreau' => 30, 'Martell' => 21, "Buchanan's Piña" => 17,
            'Appleton' => 18, 'Anís' => 25, 'Torres 10' => 38, 'Bacardi' => 10,
            'Torres 5' => 20, 'Chichón' => 23, 'Jefe' => 297, 'Flamingo' => 204,
            'VOH' => 28, 'Corona' => 39, 'Victoria' => 94, 'Pacífico Suave' => 154,
            'Modelo Especial' => 102, 'Victoria Mega' => 123, 'Corona Mega' => 69,
            'Michelob Ultra' => 220, 'Bud Light' => 190, 'Modelo 0' => 26, 'Corona Extra' => 83,
            'Pacifico Clara' => 41, 'Barrilito' => 100, 'Modelo Negra' => 40, 'Skyy' => 22,
            'Caribe' => 20,
        ];

        // Nota: "Pacífico Clara" (ref 67) y "Pacifico Clara" (ref 61) son dos
        // insumos distintos por una tilde en el menú fuente — se respeta tal
        // cual porque unificarlos es una decisión del negocio, no del código.
        foreach ($ingredients as $ref => [$name, $unit]) {
            $existing = Ingredient::where('business_id', $businessId)->where('name', $name)->first();

            $ingredient = $existing ?: Ingredient::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'name' => $name,
                'unit' => $unit,
                'stock' => $stockCounts[$name] ?? 0,
                'is_active' => true,
            ]);

            $this->ingredientRefs[$ref] = $ingredient->id;
        }
    }

    private function seedRecipeItems(): void
    {
        // [product_ref, ingredient_ref, cantidad]
        $recipeItems = [
            [6, 1, 0.35], [16, 2, 1], [4, 3, 0.2],
            [17, 52, 1], [38, 53, 1], [39, 54, 1], [40, 55, 1], [41, 56, 1], [42, 57, 1], [43, 58, 1],
            [44, 59, 1], [45, 60, 1], [46, 61, 1], [47, 62, 1], [48, 63, 1], [49, 64, 1], [50, 65, 1],
            [51, 66, 1], [52, 67, 1], [53, 54, 1], [54, 55, 1], [55, 68, 1], [56, 63, 1], [57, 69, 1],
            [58, 70, 1], [59, 71, 1],
            // Cantidades en onzas: el insumo ahora se mide en onzas (no en
            // botella de 750 ml), así que "vender la botella completa"
            // consume 750/29.5 = 25.424 onzas del insumo.
            [81, 72, 25.424], [84, 73, 25.424], [87, 74, 25.424], [90, 75, 25.424], [93, 76, 25.424], [98, 77, 25.424], [101, 78, 25.424],
            [104, 79, 25.424], [107, 80, 25.424], [110, 81, 25.424], [113, 82, 25.424], [116, 83, 25.424], [123, 84, 25.424], [126, 85, 25.424],
            [129, 86, 25.424], [132, 87, 25.424], [135, 88, 25.424], [138, 89, 25.424], [141, 90, 25.424], [144, 91, 25.424], [147, 92, 25.424],
            [152, 93, 25.424], [155, 94, 25.424],
            // "1 onza" consume 1 onza; "1 litro" consume 1000/29.5 = 33.898 onzas.
            [79, 72, 1], [80, 72, 33.898], [82, 73, 1], [83, 73, 33.898], [85, 74, 1], [86, 74, 33.898],
            [88, 75, 1], [89, 75, 33.898], [91, 76, 1], [92, 76, 33.898], [94, 95, 1], [95, 95, 33.898],
            [96, 77, 1], [97, 77, 33.898], [99, 78, 1], [100, 78, 33.898], [102, 79, 1], [103, 79, 33.898],
            [105, 80, 1], [106, 80, 33.898], [108, 81, 1], [109, 81, 33.898], [111, 82, 1], [112, 82, 33.898],
            [114, 83, 1], [115, 83, 33.898], [117, 96, 1], [118, 96, 33.898], [119, 97, 1], [120, 97, 33.898],
            [121, 84, 1], [122, 84, 33.898], [124, 85, 1], [125, 85, 33.898], [127, 86, 1], [128, 86, 33.898],
            [130, 87, 1], [131, 87, 33.898], [133, 88, 1], [134, 88, 33.898], [136, 89, 1], [137, 89, 33.898],
            [139, 90, 1], [140, 90, 33.898], [142, 91, 1], [143, 91, 33.898], [145, 92, 1], [146, 92, 33.898],
            [148, 98, 1], [149, 98, 33.898], [150, 93, 1], [151, 93, 33.898], [153, 94, 1], [154, 94, 33.898],
        ];

        foreach ($recipeItems as [$productRef, $ingredientRef, $quantity]) {
            $productId = $this->productRefs[$productRef];
            $ingredientId = $this->ingredientRefs[$ingredientRef];

            if (! RecipeItem::where('product_id', $productId)->where('ingredient_id', $ingredientId)->exists()) {
                RecipeItem::create(['product_id' => $productId, 'ingredient_id' => $ingredientId, 'quantity' => $quantity]);
            }

            Product::where('id', $productId)->update(['is_inventoried' => true]);
        }
    }

    private function seedModifierGroups(int $businessId): void
    {
        $groups = [
            [
                'name' => 'Término', 'is_required' => true, 'min_selections' => 1, 'max_selections' => 1,
                'options' => ['Término medio' => 0, 'Bien cocido' => 0, 'Término rojo' => 0],
                'products' => [6],
            ],
            [
                'name' => 'Tamaño', 'is_required' => true, 'min_selections' => 1, 'max_selections' => 1,
                'options' => ['Chico' => 0, 'Grande' => 10],
                'products' => [12, 13, 14],
            ],
        ];

        foreach ($groups as $data) {
            $group = ModifierGroup::where('business_id', $businessId)->where('name', $data['name'])->first()
                ?: ModifierGroup::create([
                    'business_id' => $businessId,
                    'name' => $data['name'],
                    'is_required' => $data['is_required'],
                    'min_selections' => $data['min_selections'],
                    'max_selections' => $data['max_selections'],
                ]);

            foreach ($data['options'] as $name => $priceDelta) {
                if (! ModifierOption::where('modifier_group_id', $group->id)->where('name', $name)->exists()) {
                    ModifierOption::create(['modifier_group_id' => $group->id, 'name' => $name, 'price_delta' => $priceDelta]);
                }
            }

            foreach ($data['products'] as $productRef) {
                $productId = $this->productRefs[$productRef];

                if (! $group->products()->where('products.id', $productId)->exists()) {
                    $group->products()->attach($productId);
                }
            }
        }
    }

    private function seedTableAreasAndTables(int $businessId, int $branchId): void
    {
        $areas = [
            ['name' => 'Salón principal', 'sort_order' => 1, 'tables' => [1, 2, 3, 4, 5, 6, 10, 11, 12], 'capacity' => 4],
            ['name' => 'Terraza', 'sort_order' => 7, 'tables' => [7, 8, 9], 'capacity' => 2],
        ];

        foreach ($areas as $data) {
            $area = TableArea::where('business_id', $businessId)->where('name', $data['name'])->first()
                ?: TableArea::create(['business_id' => $businessId, 'branch_id' => $branchId, 'name' => $data['name'], 'sort_order' => $data['sort_order']]);

            foreach ($data['tables'] as $tableNumber) {
                $name = "Mesa {$tableNumber}";

                if (! Table::where('business_id', $businessId)->where('name', $name)->exists()) {
                    Table::create([
                        'business_id' => $businessId,
                        'branch_id' => $branchId,
                        'table_area_id' => $area->id,
                        'name' => $name,
                        'capacity' => $data['capacity'],
                    ]);
                }
            }
        }
    }

    private function seedCashRegisterAndTerminal(int $businessId, int $branchId): void
    {
        $cashRegister = CashRegister::where('business_id', $businessId)->where('name', 'Caja Principal')->first()
            ?: CashRegister::create(['business_id' => $businessId, 'branch_id' => $branchId, 'name' => 'Caja Principal', 'code' => 'caja-1']);

        // Dos terminales físicas: una en la barra y otra en la cocina, cada
        // una con su impresora térmica ESC/POS por red (RAW :9100). Las IPs
        // son las de la LAN del local — si la red cambia, se editan desde
        // Administración → Terminales. El agente local las lee del sistema.
        $terminals = [
            'barra' => ['name' => 'BARRA', 'code' => 'BARRA', 'ip_address' => '192.168.1.75'],
            'cocina' => ['name' => 'COCINA', 'code' => 'COC-1', 'ip_address' => '192.168.1.74'],
        ];

        $terminalIds = [];

        foreach ($terminals as $key => $data) {
            $terminal = Terminal::where('business_id', $businessId)->where('name', $data['name'])->first()
                ?: Terminal::create($data + [
                    'business_id' => $businessId,
                    'branch_id' => $branchId,
                    'cash_register_id' => $cashRegister->id,
                    'connection_type' => 'red',
                    'printer_port' => 9100,
                    'paper_width_chars' => 48,
                    'api_token' => Str::random(48),
                ]);

            $terminalIds[$key] = $terminal->id;
        }

        // Cada estación imprime sus comandas en su propia terminal.
        KitchenStation::where('business_id', $businessId)->where('name', 'Cocina')
            ->update(['printer_terminal_id' => $terminalIds['cocina']]);
        KitchenStation::where('business_id', $businessId)->where('name', 'Barra')
            ->update(['printer_terminal_id' => $terminalIds['barra']]);
    }

    private function seedSuppliers(int $businessId): void
    {
        $suppliers = [
            ['name' => 'Distribuidora La Central', 'phone' => '555-404-5050'],
            ['name' => 'Cervecería Regional', 'phone' => '555-505-6060'],
        ];

        foreach ($suppliers as $data) {
            $this->findOrCreateByName(Supplier::class, $data, $businessId);
        }
    }
}
