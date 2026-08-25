<?php

use App\Enums\RoleName;

test('un usuario con permiso de reportes puede ver el reporte de ventas', function () {
    loginAsRole(RoleName::Reportes->value);

    $this->get(route('reportes.index'))->assertOk();
});

test('un mesero no puede ver el reporte de ventas', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('reportes.index'))->assertForbidden();
});

test('un administrador puede ver la auditoria', function () {
    loginAsRole(RoleName::Administrador->value);

    $this->get(route('admin.auditoria'))->assertOk();
});

test('un cajero no puede ver la auditoria', function () {
    loginAsRole(RoleName::Cajero->value);

    $this->get(route('admin.auditoria'))->assertForbidden();
});

test('un administrador puede ver la pantalla de respaldos', function () {
    loginAsRole(RoleName::Administrador->value);

    $this->get(route('admin.backups'))->assertOk();
});

test('un cajero no puede ver la pantalla de respaldos', function () {
    loginAsRole(RoleName::Cajero->value);

    $this->get(route('admin.backups'))->assertForbidden();
});
