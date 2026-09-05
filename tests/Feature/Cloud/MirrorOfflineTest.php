<?php

use App\Enums\RoleName;

test('el layout expone el rol de sync para que el service worker sepa si cachear paginas', function () {
    loginAsRole(RoleName::Administrador->value);

    config()->set('sync.role', 'mirror');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee("window.__SYNC_ROLE__ = 'mirror'", false)
        ->assertSee('Sin conexión — mostrando la última información disponible', false);
});

test('en la instalacion local (source) no aparece el banner de sin conexion del espejo', function () {
    loginAsRole(RoleName::Administrador->value);

    config()->set('sync.role', 'source');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee("window.__SYNC_ROLE__ = 'source'", false)
        ->assertDontSee('Sin conexión — mostrando la última información disponible');
});
