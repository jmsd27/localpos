<?php

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withHeaders;

test('cron/housekeeping rechaza sin el secreto correcto', function () {
    config()->set('sync.role', 'mirror');

    get('/cron/housekeeping')->assertForbidden();

    withHeaders(['Authorization' => 'Bearer equivocado'])
        ->get('/cron/housekeeping')
        ->assertForbidden();
});

test('cron/housekeeping corre la limpieza con el CRON_SECRET', function () {
    config()->set('sync.role', 'mirror');
    putenv('CRON_SECRET=secreto-de-prueba');

    withHeaders(['Authorization' => 'Bearer secreto-de-prueba'])
        ->get('/cron/housekeeping')
        ->assertOk()
        ->assertSee('Sesiones podadas');

    putenv('CRON_SECRET');
});

test('deploy/migrate rechaza sin DEPLOY_KEY configurada', function () {
    // Sin la variable, el endpoint queda inerte aunque manden cualquier token.
    withHeaders(['Authorization' => 'Bearer lo-que-sea'])
        ->post('/deploy/migrate')
        ->assertForbidden();
});

test('deploy/migrate rechaza un DEPLOY_KEY equivocado', function () {
    putenv('DEPLOY_KEY=clave-correcta');

    post('/deploy/migrate')->assertForbidden();

    withHeaders(['Authorization' => 'Bearer clave-mala'])
        ->post('/deploy/migrate')
        ->assertForbidden();

    putenv('DEPLOY_KEY');
});
