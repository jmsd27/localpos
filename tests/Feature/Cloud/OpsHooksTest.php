<?php

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withHeaders;

test('cron/housekeeping rechaza sin el secreto correcto', function () {
    config()->set('sync.role', 'mirror');
    config()->set('ops.cron_secret', 'secreto-de-prueba');

    get('/cron/housekeeping')->assertForbidden();

    withHeaders(['Authorization' => 'Bearer equivocado'])
        ->get('/cron/housekeeping')
        ->assertForbidden();
});

test('cron/housekeeping queda inerte si no hay CRON_SECRET configurado', function () {
    config()->set('ops.cron_secret', null);

    withHeaders(['Authorization' => 'Bearer lo-que-sea'])
        ->get('/cron/housekeeping')
        ->assertForbidden();
});

test('cron/housekeeping corre la limpieza con el CRON_SECRET', function () {
    config()->set('sync.role', 'mirror');
    config()->set('ops.cron_secret', 'secreto-de-prueba');

    withHeaders(['Authorization' => 'Bearer secreto-de-prueba'])
        ->get('/cron/housekeeping')
        ->assertOk()
        ->assertSee('Sesiones podadas');
});

test('deploy/migrate rechaza sin DEPLOY_KEY configurada', function () {
    config()->set('ops.deploy_key', null);

    withHeaders(['Authorization' => 'Bearer lo-que-sea'])
        ->post('/deploy/migrate')
        ->assertForbidden();
});

test('deploy/migrate rechaza un DEPLOY_KEY equivocado', function () {
    config()->set('ops.deploy_key', 'clave-correcta');

    post('/deploy/migrate')->assertForbidden();

    withHeaders(['Authorization' => 'Bearer clave-mala'])
        ->post('/deploy/migrate')
        ->assertForbidden();
});

test('deploy/importar-datos-reales rechaza sin DEPLOY_KEY configurada', function () {
    config()->set('ops.deploy_key', null);

    withHeaders(['Authorization' => 'Bearer lo-que-sea'])
        ->post('/deploy/importar-datos-reales')
        ->assertForbidden();
});

test('deploy/importar-datos-reales carga el catalogo, los roles y el personal real', function () {
    config()->set('ops.deploy_key', 'clave-correcta');

    withHeaders(['Authorization' => 'Bearer clave-correcta'])
        ->post('/deploy/importar-datos-reales')
        ->assertOk();

    expect(App\Models\Business::where('name', 'Bar La Martina')->exists())->toBeTrue();
    expect(App\Models\Product::count())->toBe(155);
    expect(App\Models\Ingredient::count())->toBe(98);
    expect(App\Models\RecipeItem::count())->toBe(103);
    expect(Spatie\Permission\Models\Role::where('name', 'auditor')->exists())->toBeTrue();
    expect(Spatie\Permission\Models\Role::where('name', 'director')->exists())->toBeTrue();

    $jennifer = App\Models\User::where('email', 'jennifer.franco@barlamartina.local')->firstOrFail();
    expect($jennifer->roles->pluck('name')->sort()->values()->all())->toBe(['auditor', 'mesero']);

    $arturo = App\Models\User::where('email', 'arturo.leon@barlamartina.local')->firstOrFail();
    expect($arturo->hasRole('director'))->toBeTrue();
});

test('deploy/importar-datos-reales se puede reintentar sin duplicar nada', function () {
    config()->set('ops.deploy_key', 'clave-correcta');

    withHeaders(['Authorization' => 'Bearer clave-correcta'])->post('/deploy/importar-datos-reales')->assertOk();
    withHeaders(['Authorization' => 'Bearer clave-correcta'])->post('/deploy/importar-datos-reales')->assertOk();

    expect(App\Models\Business::count())->toBe(1);
    expect(App\Models\Product::count())->toBe(155);
    expect(App\Models\User::where('email', 'like', '%@barlamartina.local')->count())->toBe(6);
});
