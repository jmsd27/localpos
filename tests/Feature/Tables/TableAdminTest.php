<?php

use App\Enums\RoleName;
use App\Models\Table;
use App\Models\TableArea;
use Livewire\Livewire;

test('un administrador puede crear un salon y una mesa', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.salones.index')
        ->call('create')
        ->set('name', 'Salón Principal')
        ->call('save')
        ->assertHasNoErrors();

    $area = TableArea::where('name', 'Salón Principal')->where('business_id', $user->businessId())->firstOrFail();

    Livewire::test('admin.mesas.index')
        ->call('create')
        ->set('name', 'Mesa 1')
        ->set('table_area_id', $area->id)
        ->set('capacity', 4)
        ->call('save')
        ->assertHasNoErrors();

    expect(Table::where('name', 'Mesa 1')->where('table_area_id', $area->id)->exists())->toBeTrue();
});

test('una mesa nueva requiere un salon', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.mesas.index')
        ->call('create')
        ->set('name', 'Mesa huérfana')
        ->set('table_area_id', '')
        ->call('save')
        ->assertHasErrors('table_area_id');
});
