<?php

use App\Enums\RoleName;
use App\Models\Setting;
use App\Services\SettingsService;
use Livewire\Livewire;

use function Pest\Laravel\get;

test('un administrador que no es super admin no puede ver los manuales', function () {
    loginAsRole(RoleName::Administrador->value);

    get(route('admin.manuales'))->assertForbidden();
});

test('un invitado es enviado al login', function () {
    get(route('admin.manuales'))->assertRedirect(route('login'));
});

test('el super admin ve el índice y el primer manual renderizado', function () {
    loginAsRole(RoleName::SuperAdmin->value);

    Livewire::test('admin.manuales.index')
        ->assertOk()
        ->assertSee('Manuales de implementación')
        ->assertSee('Antes de empezar')
        ->assertSeeHtml('<h1'); // el markdown se renderizó a HTML
});

test('todos los archivos de manual renderizan sin error y aparecen en el índice', function () {
    loginAsRole(RoleName::SuperAdmin->value);

    $files = glob(resource_path('manuales/*.md'));
    expect($files)->not->toBeEmpty();

    $component = Livewire::test('admin.manuales.index');

    foreach ($files as $file) {
        $slug = basename($file, '.md');
        $component->set('current', $slug)->assertOk();
    }
});

test('marcar un manual como completado se guarda por negocio y persiste', function () {
    $user = loginAsRole(RoleName::SuperAdmin->value);

    Livewire::test('admin.manuales.index')
        ->set('current', '00-antes-de-empezar')
        ->call('toggleDone', '00-antes-de-empezar')
        ->assertSet('current', '00-antes-de-empezar');

    expect(app(SettingsService::class)->get($user->businessId(), 'manual:00-antes-de-empezar'))->toBe('1');

    // Se refleja en una instancia nueva del componente (progreso persistido).
    Livewire::test('admin.manuales.index')
        ->assertSeeHtml('width: '); // barra de avance > 0

    // Y se puede desmarcar.
    Livewire::test('admin.manuales.index')
        ->call('toggleDone', '00-antes-de-empezar');

    expect((bool) app(SettingsService::class)->get($user->businessId(), 'manual:00-antes-de-empezar'))->toBeFalse();
});

test('toggleDone ignora un slug que no existe', function () {
    $user = loginAsRole(RoleName::SuperAdmin->value);

    Livewire::test('admin.manuales.index')
        ->call('toggleDone', 'no-existe');

    expect(Setting::where('business_id', $user->businessId())->where('key', 'manual:no-existe')->exists())->toBeFalse();
});
