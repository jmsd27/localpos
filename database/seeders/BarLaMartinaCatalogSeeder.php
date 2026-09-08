<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RecipeItem;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\TableArea;
use App\Models\Terminal;
use Illuminate\Database\Seeder;

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
            21 => ['Hamburguesa Sencilla', 120, 16, 5, null], 22 => ['Hamburguesa Doble', 150, 16, 5, null],
            23 => ['Hamburguesa Hawaiana', 130, 16, 5, null], 24 => ['Boneless', 190, 16, 5, null],
            25 => ['Papas Francesas', 90, 16, 5, null], 26 => ['Papas La Martina', 280, 16, 5, null],
            27 => ['Papas Preparadas', 110, 16, 5, null], 28 => ['Chicharrones', 80, 16, 5, null],
            29 => ['Charola Martina', 0, 16, 5, null], 30 => ['Nachos Especiales con Bistec', 140, 16, 5, null],
            31 => ['Nachos Sencillos', 110, 16, 5, null], 32 => ['Papa Nachos Especiales con Bistec', 160, 16, 5, null],
            33 => ['Papa Nachos Sencillos', 120, 16, 5, null], 34 => ['Camarón Seco', 100, 16, 5, null],
            35 => ['Carne Seca', 120, 16, 5, null], 36 => ['Tacos de Bistec', 140, 16, 5, null],
            37 => ['Tacos de Pastor', 100, 16, 5, null], 38 => ['Stella', 50, 16, 6, null],
            39 => ['Bud Light', 35, 16, 6, null], 40 => ['Modelo Especial', 40, 16, 6, null],
            41 => ['Modelo Negra', 45, 16, 6, null], 42 => ['Modelo 0', 40, 16, 6, null],
            43 => ['Modelo Malta', 50, 16, 6, null], 44 => ['Michelob Ultra', 40, 16, 6, null],
            45 => ['Pacífico Suave', 40, 16, 6, null], 46 => ['Pacifico Clara', 40, 16, 6, null],
            47 => ['Corona Extra', 40, 16, 6, null], 48 => ['Victoria', 40, 16, 6, null],
            49 => ['Caribe', 55, 16, 6, null], 50 => ['Barrilito', 35, 16, 6, null],
            51 => ['Skyy', 55, 16, 6, null], 52 => ['Pacífico Clara (Caguama)', 90, 16, 6, null],
            53 => ['Bud Light (Caguama)', 85, 16, 6, null], 54 => ['Modelo Especial (Caguama)', 95, 16, 6, null],
            55 => ['Michelob (Caguama)', 90, 16, 6, null], 56 => ['Victoria (Caguama)', 85, 16, 6, null],
            57 => ['Victoria Mega (Caguama)', 95, 16, 6, null], 58 => ['Corona (Caguama)', 85, 16, 6, null],
            59 => ['Corona Mega (Caguama)', 95, 16, 6, null], 60 => ['Cantarito Chico', 65, 16, 7, null],
            61 => ['Cantarito Litro', 150, 16, 7, null], 62 => ['Azulito', 130, 16, 7, null],
            63 => ['Piña Colada', 150, 16, 7, null], 64 => ['Tequila Sonrise', 130, 16, 7, null],
            65 => ['Cosmopolita', 160, 16, 7, null], 66 => ['Gin Tonic Frutos Rojos', 120, 16, 7, null],
            67 => ['Amarre de Amor', 150, 16, 7, null], 68 => ['Colombia', 130, 16, 7, null],
            69 => ['Criollo', 130, 16, 7, null], 70 => ['Fuego', 130, 16, 7, null],
            71 => ['Perla Negra', 130, 16, 7, null], 72 => ['Margarita', 100, 16, 7, null],
            73 => ['Shots de Mango', 15, 16, 7, null], 74 => ['Agua', 40, 16, 3, null],
            75 => ['Limonada Grande', 70, 16, 3, null], 76 => ['Clamato Preparado', 60, 16, 3, null],
            77 => ['Clamato Preparado Litro', 120, 16, 3, null], 78 => ['Michelado', 35, 16, 3, null],
            79 => ['Passport (1 onza)', 55, 16, 8, null], 80 => ['Passport (1 litro)', 110, 16, 8, null],
            81 => ['Passport (botella)', 750, 16, 8, null], 82 => ['Red Label (1 onza)', 65, 16, 8, null],
            83 => ['Red Label (1 litro)', 140, 16, 8, null], 84 => ['Red Label (botella)', 1100, 16, 8, null],
            85 => ["Jack Daniel's (1 onza)", 65, 16, 8, null], 86 => ["Jack Daniel's (1 litro)", 150, 16, 8, null],
            87 => ["Jack Daniel's (botella)", 1200, 16, 8, null], 88 => ['Black Label (1 onza)', 90, 16, 8, null],
            89 => ['Black Label (1 litro)', 180, 16, 8, null], 90 => ['Black Label (botella)', 1800, 16, 8, null],
            91 => ["Buchanan's (1 onza)", 90, 16, 8, null], 92 => ["Buchanan's (1 litro)", 180, 16, 8, null],
            93 => ["Buchanan's (botella)", 1800, 16, 8, null], 94 => ['Bajio (1 onza)', 40, 16, 9, null],
            95 => ['Bajio (1 litro)', 70, 16, 9, null], 96 => ['Centenario (1 onza)', 50, 16, 9, null],
            97 => ['Centenario (1 litro)', 120, 16, 9, null], 98 => ['Centenario (botella)', 1100, 16, 9, null],
            99 => ['Tradicional Rep (1 onza)', 60, 16, 9, null], 100 => ['Tradicional Rep (1 litro)', 125, 16, 9, null],
            101 => ['Tradicional Rep (botella)', 1250, 16, 9, null], 102 => ['Tradicional Plata (1 onza)', 70, 16, 9, null],
            103 => ['Tradicional Plata (1 litro)', 135, 16, 9, null], 104 => ['Tradicional Plata (botella)', 1350, 16, 9, null],
            105 => ['Hornitos Reposado (1 onza)', 65, 16, 9, null], 106 => ['Hornitos Reposado (1 litro)', 130, 16, 9, null],
            107 => ['Hornitos Reposado (botella)', 1300, 16, 9, null], 108 => ['1800 Cristalino (1 onza)', 110, 16, 9, null],
            109 => ['1800 Cristalino (1 litro)', 200, 16, 9, null], 110 => ['1800 Cristalino (botella)', 2100, 16, 9, null],
            111 => ['Don Julio (1 onza)', 100, 16, 9, null], 112 => ['Don Julio (1 litro)', 190, 16, 9, null],
            113 => ['Don Julio (botella)', 1950, 16, 9, null], 114 => ['Maestro Dobel (1 onza)', 90, 16, 9, null],
            115 => ['Maestro Dobel (1 litro)', 180, 16, 9, null], 116 => ['Maestro Dobel (botella)', 1800, 16, 9, null],
            117 => ['Jagger (1 onza)', 70, 16, 10, null], 118 => ['Jagger (1 litro)', 140, 16, 10, null],
            119 => ['Licor 43 (1 onza)', 55, 16, 10, null], 120 => ['Licor 43 (1 litro)', 140, 16, 10, null],
            121 => ['Zaverich (1 onza)', 30, 16, 11, null], 122 => ['Zaverich (1 litro)', 80, 16, 11, null],
            123 => ['Zaverich (botella)', 450, 16, 11, null], 124 => ['Oso Negro (1 onza)', 40, 16, 11, null],
            125 => ['Oso Negro (1 litro)', 90, 16, 11, null], 126 => ['Oso Negro (botella)', 550, 16, 11, null],
            127 => ['Absolut (1 onza)', 55, 16, 11, null], 128 => ['Absolut (1 litro)', 110, 16, 11, null],
            129 => ['Absolut (botella)', 1050, 16, 11, null], 130 => ['Absolut Rasberry (1 onza)', 55, 16, 11, null],
            131 => ['Absolut Rasberry (1 litro)', 110, 16, 11, null], 132 => ['Absolut Rasberry (botella)', 1050, 16, 11, null],
            133 => ['Smirnoff (1 onza)', 55, 16, 11, null], 134 => ['Smirnoff (1 litro)', 130, 16, 11, null],
            135 => ['Smirnoff (botella)', 1150, 16, 11, null], 136 => ['Azteca de Oro (1 onza)', 40, 16, 12, null],
            137 => ['Azteca de Oro (1 litro)', 90, 16, 12, null], 138 => ['Azteca de Oro (botella)', 600, 16, 12, null],
            139 => ['Torres 5 (1 onza)', 45, 16, 12, null], 140 => ['Torres 5 (1 litro)', 100, 16, 12, null],
            141 => ['Torres 5 (botella)', 750, 16, 12, null], 142 => ['Torres 10 (1 onza)', 50, 16, 12, null],
            143 => ['Torres 10 (1 litro)', 110, 16, 12, null], 144 => ['Torres 10 (botella)', 900, 16, 12, null],
            145 => ['Presidente (1 onza)', 40, 16, 12, null], 146 => ['Presidente (1 litro)', 90, 16, 12, null],
            147 => ['Presidente (botella)', 750, 16, 12, null], 148 => ['Ron de la Casa (1 onza)', 40, 16, 13, null],
            149 => ['Ron de la Casa (1 litro)', 90, 16, 13, null], 150 => ['Bacardi (1 onza)', 45, 16, 13, null],
            151 => ['Bacardi (1 litro)', 100, 16, 13, null], 152 => ['Bacardi (botella)', 900, 16, 13, null],
            153 => ['Capitán Morgan (1 onza)', 50, 16, 13, null], 154 => ['Capitán Morgan (1 litro)', 110, 16, 13, null],
            155 => ['Capitán Morgan (botella)', 900, 16, 13, null],
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
            72 => ['Passport', 'botella'], 73 => ['Red Label', 'botella'], 74 => ["Jack Daniel's", 'botella'],
            75 => ['Black Label', 'botella'], 76 => ["Buchanan's", 'botella'], 77 => ['Centenario', 'botella'],
            78 => ['Tradicional Rep', 'botella'], 79 => ['Tradicional Plata', 'botella'],
            80 => ['Hornitos Reposado', 'botella'], 81 => ['1800 Cristalino', 'botella'], 82 => ['Don Julio', 'botella'],
            83 => ['Maestro Dobel', 'botella'], 84 => ['Zaverich', 'botella'], 85 => ['Oso Negro', 'botella'],
            86 => ['Absolut', 'botella'], 87 => ['Absolut Rasberry', 'botella'], 88 => ['Smirnoff', 'botella'],
            89 => ['Azteca de Oro', 'botella'], 90 => ['Torres 5', 'botella'], 91 => ['Torres 10', 'botella'],
            92 => ['Presidente', 'botella'], 93 => ['Bacardi', 'botella'], 94 => ['Capitán Morgan', 'botella'],
            95 => ['Bajio', 'botella'], 96 => ['Jagger', 'botella'], 97 => ['Licor 43', 'botella'],
            98 => ['Ron de la Casa', 'botella'],
            // Del conteo físico por fotos del 2026-09-08 (licores/cervezas que
            // no tenían insumo todavía).
            99 => ['Don Julio 70', 'botella'], 100 => ['Jose Cuervo Margarita', 'botella'],
            101 => ['Etiqueta Negra', 'botella'], 102 => ['Ginebra', 'botella'],
            103 => ['Bacardi Sabores', 'botella'], 104 => ['Baileys', 'botella'],
            105 => ['Don Pedro', 'botella'], 106 => ['Hpnotiq', 'botella'],
            107 => ['Chivas 12', 'botella'], 108 => ['Gran Malo Horchata', 'botella'],
            109 => ['Gran Malo Tamarindo', 'botella'], 110 => ['Gran Malo Jamaica', 'botella'],
            111 => ['Don Julio Cristalino', 'botella'], 112 => ['7 Leguas', 'botella'],
            113 => ['Jose Cuervo Cristalino', 'botella'], 114 => ['Centenario Reposado', 'botella'],
            115 => ['Hornitos Cristalino', 'botella'], 116 => ["Jack Daniel's Piña", 'botella'],
            117 => ['Black & White', 'botella'], 118 => ['1800 Añejo', 'botella'],
            119 => ['Conti', 'botella'], 120 => ['100 Conejos', 'botella'],
            121 => ['Campari', 'botella'], 122 => ['Ampevol', 'botella'],
            123 => ['Cinzano', 'botella'], 124 => ['Kahlúa', 'botella'],
            125 => ['Absolut Azul', 'botella'], 126 => ['Cointreau', 'botella'],
            127 => ['Martell', 'botella'], 128 => ["Buchanan's Piña", 'botella'],
            129 => ['Appleton', 'botella'], 130 => ['Anís', 'botella'],
            131 => ['Chichón', 'botella'], 132 => ['Jefe', 'botella'],
            133 => ['Flamingo', 'botella'], 134 => ['VOH', 'botella'],
        ];

        // Existencia real del conteo físico por fotos del 2026-09-08 (licores
        // en onzas convertidas a botella de 750 ml, cervezas/caguamas por
        // unidad). Los insumos que no aparecen acá (comida, limpieza, etc.)
        // quedan en 0 — se cargan con Conteo físico cuando el negocio los
        // cuente. Ver database/data-real/conteo-2026-09-08.md para el detalle.
        $stockCounts = [
            'Don Julio 70' => 1.534, 'Jose Cuervo Margarita' => 2.399, "Jack Daniel's" => 3.776,
            'Red Label' => 5.861, 'Tradicional Plata' => 6.254, 'Tradicional Rep' => 1.809,
            'Capitán Morgan' => 2.832, 'Etiqueta Negra' => 0.826, 'Ginebra' => 2.753,
            'Hornitos Reposado' => 1.298, 'Smirnoff' => 2.242, 'Bacardi Sabores' => 0.157,
            'Presidente' => 1.219, 'Baileys' => 1.337, 'Don Pedro' => 0.983, 'Hpnotiq' => 1.337,
            "Buchanan's" => 3.737, 'Chivas 12' => 3.068, 'Gran Malo Horchata' => 1.416,
            'Gran Malo Tamarindo' => 1.809, 'Gran Malo Jamaica' => 0.983, 'Don Julio Cristalino' => 1.495,
            '7 Leguas' => 1.731, 'Jose Cuervo Cristalino' => 0.983, 'Maestro Dobel' => 1.573,
            'Centenario Reposado' => 0.669, 'Hornitos Cristalino' => 0.157, 'Jagger' => 1.062,
            "Jack Daniel's Piña" => 0.787, 'Azteca de Oro' => 1.731, 'Passport' => 0.590,
            '1800 Cristalino' => 0.079, 'Black & White' => 2.714, '1800 Añejo' => 3.343,
            'Conti' => 1.731, '100 Conejos' => 3.619, 'Campari' => 2.950, 'Ampevol' => 2.714,
            'Cinzano' => 0.983, 'Kahlúa' => 0.747, 'Zaverich' => 1.180, 'Absolut Rasberry' => 0.944,
            'Absolut Azul' => 2.045, 'Cointreau' => 1.180, 'Martell' => 0.826, "Buchanan's Piña" => 0.669,
            'Appleton' => 0.708, 'Anís' => 0.983, 'Torres 10' => 1.495, 'Bacardi' => 0.393,
            'Torres 5' => 0.787, 'Chichón' => 0.905, 'Jefe' => 11.682, 'Flamingo' => 8.024,
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
            [81, 72, 1], [84, 73, 1], [87, 74, 1], [90, 75, 1], [93, 76, 1], [98, 77, 1], [101, 78, 1],
            [104, 79, 1], [107, 80, 1], [110, 81, 1], [113, 82, 1], [116, 83, 1], [123, 84, 1], [126, 85, 1],
            [129, 86, 1], [132, 87, 1], [135, 88, 1], [138, 89, 1], [141, 90, 1], [144, 91, 1], [147, 92, 1],
            [152, 93, 1], [155, 94, 1],
            [79, 72, 0.039], [80, 72, 1.333], [82, 73, 0.039], [83, 73, 1.333], [85, 74, 0.039], [86, 74, 1.333],
            [88, 75, 0.039], [89, 75, 1.333], [91, 76, 0.039], [92, 76, 1.333], [94, 95, 0.039], [95, 95, 1.333],
            [96, 77, 0.039], [97, 77, 1.333], [99, 78, 0.039], [100, 78, 1.333], [102, 79, 0.039], [103, 79, 1.333],
            [105, 80, 0.039], [106, 80, 1.333], [108, 81, 0.039], [109, 81, 1.333], [111, 82, 0.039], [112, 82, 1.333],
            [114, 83, 0.039], [115, 83, 1.333], [117, 96, 0.039], [118, 96, 1.333], [119, 97, 0.039], [120, 97, 1.333],
            [121, 84, 0.039], [122, 84, 1.333], [124, 85, 0.039], [125, 85, 1.333], [127, 86, 0.039], [128, 86, 1.333],
            [130, 87, 0.039], [131, 87, 1.333], [133, 88, 0.039], [134, 88, 1.333], [136, 89, 0.039], [137, 89, 1.333],
            [139, 90, 0.039], [140, 90, 1.333], [142, 91, 0.039], [143, 91, 1.333], [145, 92, 0.039], [146, 92, 1.333],
            [148, 98, 0.039], [149, 98, 1.333], [150, 93, 0.039], [151, 93, 1.333], [153, 94, 0.039], [154, 94, 1.333],
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

        if (! Terminal::where('business_id', $businessId)->where('name', 'CAJA1')->exists()) {
            Terminal::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'cash_register_id' => $cashRegister->id,
                'name' => 'CAJA1',
                'code' => 'caja1',
            ]);
        }
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
