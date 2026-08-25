<?php

use App\Enums\RoleName;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Livewire\Livewire;

test('un administrador puede crear un grupo de modificadores con opciones', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.modificadores.index')
        ->call('createGroup')
        ->set('group_name', 'Tipo de carne')
        ->set('max_selections', 1)
        ->call('saveGroup')
        ->assertHasNoErrors();

    $group = ModifierGroup::where('name', 'Tipo de carne')->where('business_id', $user->businessId())->firstOrFail();

    Livewire::test('admin.modificadores.index')
        ->call('createOption', $group->id)
        ->set('option_name', 'Res')
        ->set('price_delta', '0')
        ->call('saveOption')
        ->assertHasNoErrors();

    expect(ModifierOption::where('modifier_group_id', $group->id)->where('name', 'Res')->exists())->toBeTrue();
});

test('el máximo de selecciones no puede ser menor al mínimo', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.modificadores.index')
        ->call('createGroup')
        ->set('group_name', 'Extras')
        ->set('min_selections', 3)
        ->set('max_selections', 1)
        ->call('saveGroup')
        ->assertHasErrors('max_selections');
});
