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
