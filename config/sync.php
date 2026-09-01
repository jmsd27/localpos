<?php

return [
    /*
     * Rol que juega esta instancia dentro del espejo:
     * - "source": instalación local en la sucursal, siempre es la fuente de verdad.
     * - "mirror": copia en la nube, de solo lectura, alimentada por instancias "source".
     */
    'role' => env('SYNC_ROLE', 'source'),

    /*
     * URL base de la instancia "mirror" en la nube (sin slash final), ej.
     * https://mi-espejo.vercel.app — vacía mientras no exista el espejo: en
     * ese caso SyncPushService no hace nada.
     */
    'cloud_url' => env('SYNC_CLOUD_URL'),

    'push_batch_size' => (int) env('SYNC_PUSH_BATCH_SIZE', 200),
    'push_timeout' => (int) env('SYNC_PUSH_TIMEOUT', 15),

    /*
     * Cada cuántos minutos corre sync:push como red de seguridad. El envío
     * casi inmediato lo hace PushSyncBatchJob tras cada escritura (si el
     * worker de cola está activo); este valor es el peor caso de atraso del
     * espejo cuando el worker está apagado. routes/console.php lo traduce a
     * la frecuencia soportada más cercana (1, 5, 10, 15, 30, 60).
     */
    'schedule_frequency_minutes' => (int) env('SYNC_SCHEDULE_MINUTES', 15),
    'outbox_retention_days' => (int) env('SYNC_OUTBOX_RETENTION_DAYS', 30),

    /*
     * Modelos que se sincronizan hacia la nube. La clave corta (ej. "order")
     * es la que viaja en el payload, no el FQCN, para que un refactor de
     * namespaces no rompa la compatibilidad con lotes ya enviados.
     *
     * - "business_via" / "branch_via": ruta de relación (notación con punto)
     *   a caminar cuando el modelo no tiene business_id/branch_id propio.
     *   null significa "usar la columna directa del modelo".
     * - "exclude_fields": columnas que nunca viajan en el payload (ej. hashes
     *   de contraseña), aunque el resto de la fila sí se sincroniza completa.
     */
    'models' => [
        'business' => [
            'model' => \App\Models\Business::class,
        ],
        'branch' => [
            'model' => \App\Models\Branch::class,
        ],
        'user' => [
            'model' => \App\Models\User::class,
            'exclude_fields' => ['password', 'pin_hash', 'remember_token'],
        ],
        'product_category' => [
            'model' => \App\Models\ProductCategory::class,
        ],
        'product' => [
            'model' => \App\Models\Product::class,
        ],
        'modifier_group' => [
            'model' => \App\Models\ModifierGroup::class,
        ],
        'modifier_option' => [
            'model' => \App\Models\ModifierOption::class,
            'business_via' => 'group',
        ],
        'customer' => [
            'model' => \App\Models\Customer::class,
        ],
        'supplier' => [
            'model' => \App\Models\Supplier::class,
        ],
        'setting' => [
            'model' => \App\Models\Setting::class,
        ],
        'table_area' => [
            'model' => \App\Models\TableArea::class,
        ],
        'table' => [
            'model' => \App\Models\Table::class,
        ],
        'kitchen_station' => [
            'model' => \App\Models\KitchenStation::class,
        ],
        'ingredient' => [
            'model' => \App\Models\Ingredient::class,
        ],
        'recipe_item' => [
            'model' => \App\Models\RecipeItem::class,
            'business_via' => 'product',
        ],
        'terminal' => [
            'model' => \App\Models\Terminal::class,
        ],
        'cash_register' => [
            'model' => \App\Models\CashRegister::class,
        ],
        'cash_register_session' => [
            'model' => \App\Models\CashRegisterSession::class,
            'business_via' => 'cashRegister',
            'branch_via' => 'cashRegister',
        ],
        'order' => [
            'model' => \App\Models\Order::class,
        ],
        'order_item' => [
            'model' => \App\Models\OrderItem::class,
            'business_via' => 'order',
            'branch_via' => 'order',
        ],
        'order_item_modifier' => [
            'model' => \App\Models\OrderItemModifier::class,
            'business_via' => 'orderItem.order',
            'branch_via' => 'orderItem.order',
        ],
        'payment' => [
            'model' => \App\Models\Payment::class,
            'business_via' => 'order',
            'branch_via' => 'order',
        ],
        'order_cancellation' => [
            'model' => \App\Models\OrderCancellation::class,
            'business_via' => 'order',
            'branch_via' => 'order',
        ],
        'cash_movement' => [
            'model' => \App\Models\CashMovement::class,
            'business_via' => 'session.cashRegister',
            'branch_via' => 'session.cashRegister',
        ],
        'inventory_movement' => [
            'model' => \App\Models\InventoryMovement::class,
        ],
        'purchase' => [
            'model' => \App\Models\Purchase::class,
        ],
        'purchase_item' => [
            'model' => \App\Models\PurchaseItem::class,
            'business_via' => 'purchase',
            'branch_via' => 'purchase',
        ],
        'audit_log' => [
            'model' => \App\Models\AuditLog::class,
        ],
    ],

    /*
     * Mapa explícito de columnas FK por modelo, usado del lado de ingesta en
     * la nube para reescribir referencias locales a IDs de la nube (vía
     * sync_id_map). No se adivina por el nombre de la columna a propósito:
     * un "modifier_option_id" o "cash_register_session_id" mal adivinado
     * corrompería datos en la nube silenciosamente.
     */
    'fk_map' => [
        'branch' => ['business_id' => 'business'],
        'user' => ['branch_id' => 'branch'],
        'product_category' => ['business_id' => 'business'],
        'product' => ['business_id' => 'business', 'product_category_id' => 'product_category', 'kitchen_station_id' => 'kitchen_station'],
        'modifier_group' => ['business_id' => 'business'],
        'modifier_option' => ['modifier_group_id' => 'modifier_group'],
        'customer' => ['business_id' => 'business'],
        'supplier' => ['business_id' => 'business'],
        'setting' => ['business_id' => 'business'],
        'table_area' => ['business_id' => 'business', 'branch_id' => 'branch'],
        'table' => ['business_id' => 'business', 'branch_id' => 'branch', 'table_area_id' => 'table_area'],
        'kitchen_station' => ['business_id' => 'business', 'branch_id' => 'branch', 'printer_terminal_id' => 'terminal'],
        'ingredient' => ['business_id' => 'business', 'branch_id' => 'branch'],
        'recipe_item' => ['product_id' => 'product', 'ingredient_id' => 'ingredient'],
        'terminal' => ['business_id' => 'business', 'branch_id' => 'branch', 'cash_register_id' => 'cash_register'],
        'cash_register' => ['business_id' => 'business', 'branch_id' => 'branch'],
        'cash_register_session' => ['cash_register_id' => 'cash_register', 'terminal_id' => 'terminal', 'opened_by_user_id' => 'user', 'closed_by_user_id' => 'user'],
        'order' => ['business_id' => 'business', 'branch_id' => 'branch', 'terminal_id' => 'terminal', 'cash_register_session_id' => 'cash_register_session', 'user_id' => 'user', 'customer_id' => 'customer', 'table_id' => 'table'],
        'order_item' => ['order_id' => 'order', 'product_id' => 'product', 'kitchen_station_id' => 'kitchen_station'],
        'order_item_modifier' => ['order_item_id' => 'order_item', 'modifier_option_id' => 'modifier_option'],
        'payment' => ['order_id' => 'order'],
        'order_cancellation' => ['order_id' => 'order', 'user_id' => 'user'],
        'cash_movement' => ['cash_register_session_id' => 'cash_register_session', 'user_id' => 'user', 'order_id' => 'order'],
        'inventory_movement' => ['business_id' => 'business', 'ingredient_id' => 'ingredient', 'user_id' => 'user'],
        'purchase' => ['business_id' => 'business', 'branch_id' => 'branch', 'supplier_id' => 'supplier', 'user_id' => 'user'],
        'purchase_item' => ['purchase_id' => 'purchase', 'ingredient_id' => 'ingredient'],
        'audit_log' => ['user_id' => 'user', 'business_id' => 'business', 'branch_id' => 'branch', 'terminal_id' => 'terminal'],
    ],
];
