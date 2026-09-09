<?php

use App\Models\Business;
use App\Models\Order;
use App\Models\Setting;
use App\Services\FolioGenerator;

function folioContext(): Business
{
    return Business::factory()->create();
}

test('genera folios secuenciales', function () {
    $business = folioContext();
    $gen = app(FolioGenerator::class);

    expect($gen->next($business->id, 'venta'))->toBe('VENTA-000001')
        ->and($gen->next($business->id, 'venta'))->toBe('VENTA-000002');
});

test('salta un folio ya usado cuando el contador quedo atrasado', function () {
    $business = folioContext();

    // Simula el estado roto: ya existe VENTA-000009 pero el contador dice 8
    // (pasa al reimportar el catálogo, que traía un folio_seq_venta viejo).
    Order::factory()->create(['business_id' => $business->id, 'folio' => 'VENTA-000009']);
    Setting::create(['business_id' => $business->id, 'key' => 'folio_seq_venta', 'value' => '8', 'group' => 'folios']);

    $folio = app(FolioGenerator::class)->next($business->id, 'venta');

    expect($folio)->toBe('VENTA-000010');
    expect(Setting::where('business_id', $business->id)->where('key', 'folio_seq_venta')->value('value'))->toBe('10');
});

test('salta un bloque de folios ya usados', function () {
    $business = folioContext();

    foreach (['VENTA-000003', 'VENTA-000004', 'VENTA-000005'] as $folio) {
        Order::factory()->create(['business_id' => $business->id, 'folio' => $folio]);
    }
    Setting::create(['business_id' => $business->id, 'key' => 'folio_seq_venta', 'value' => '2', 'group' => 'folios']);

    expect(app(FolioGenerator::class)->next($business->id, 'venta'))->toBe('VENTA-000006');
});

test('el salto es por negocio: el folio de otro negocio no estorba', function () {
    $a = folioContext();
    $b = folioContext();

    Order::factory()->create(['business_id' => $b->id, 'folio' => 'VENTA-000001']);

    expect(app(FolioGenerator::class)->next($a->id, 'venta'))->toBe('VENTA-000001');
});

test('el mismo salto aplica a los folios de comanda', function () {
    $business = folioContext();

    Order::factory()->create(['business_id' => $business->id, 'comanda_folio' => 'COMANDA-000003']);
    Setting::create(['business_id' => $business->id, 'key' => 'folio_seq_comanda', 'value' => '2', 'group' => 'folios']);

    expect(app(FolioGenerator::class)->next($business->id, 'comanda'))->toBe('COMANDA-000004');
});
