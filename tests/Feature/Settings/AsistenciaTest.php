<?php

use App\Enums\RoleName;
use App\Services\ClaudeAssistantService;
use App\Support\LaravelLogReader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * Escribe un laravel.log de prueba y apunta el lector a él.
 */
function fakeLog(string $contents): string
{
    $path = sys_get_temp_dir().'/asistencia_test_'.uniqid().'.log';
    file_put_contents($path, $contents);

    app()->bind(LaravelLogReader::class, fn () => new LaravelLogReader($path));

    return $path;
}

test('un administrador que no es super admin no puede entrar', function () {
    loginAsRole(RoleName::Administrador->value);

    get(route('admin.asistencia'))->assertForbidden();
});

test('un invitado es enviado al login', function () {
    get(route('admin.asistencia'))->assertRedirect(route('login'));
});

test('el super admin ve el panel de salud', function () {
    loginAsRole(RoleName::SuperAdmin->value);

    Livewire::test('admin.asistencia.index')
        ->assertOk()
        ->assertSee('Diagnóstico y asistencia')
        ->assertSee('Aplicación')
        ->assertSee('Base de datos')
        ->assertSee('Sincronización');
});

test('lista los errores recientes del log y oculta "Explicar con IA" sin API key', function () {
    config()->set('services.anthropic.key', null);
    fakeLog(
        "[2026-09-02 10:00:00] production.INFO: nada importante\n".
        "[2026-09-02 10:05:00] production.ERROR: Undefined variable \$foo en algún lado\nStack trace:\n#0 {main}\n"
    );

    loginAsRole(RoleName::SuperAdmin->value);

    Livewire::test('admin.asistencia.index')
        ->assertSee('Undefined variable')
        ->assertSee('Ver contexto')
        ->assertDontSee('Explicar con IA');
});

test('con API key aparece "Explicar con IA"', function () {
    config()->set('services.anthropic.key', 'sk-ant-test');
    fakeLog("[2026-09-02 10:05:00] production.ERROR: algo explotó\n");

    loginAsRole(RoleName::SuperAdmin->value);

    Livewire::test('admin.asistencia.index')
        ->assertSee('Explicar con IA');
});

test('el bundle de contexto incluye código del proyecto y redacta secretos', function () {
    $entry = [
        'timestamp' => '2026-09-02 10:05:00',
        'level' => 'ERROR',
        'body' => "production.ERROR: boom en app/Services/FolioGenerator.php:10\n".
            "APP_KEY=base64:SUPERSECRETO123456789\n",
    ];

    $bundle = app(ClaudeAssistantService::class)->contextBundle($entry);

    expect($bundle)->toContain('app/Services/FolioGenerator.php')
        ->and($bundle)->toContain('class FolioGenerator')   // extracto real del archivo
        ->and($bundle)->not->toContain('SUPERSECRETO123456789')
        ->and($bundle)->toContain('***');
});

test('el bundle no lee archivos fuera del proyecto', function () {
    $outside = sys_get_temp_dir().'/fuera_'.uniqid().'.php';
    file_put_contents($outside, '<?php // SECRETO_FUERA_DEL_PROYECTO');

    $bundle = app(ClaudeAssistantService::class)->contextBundle([
        'body' => 'production.ERROR: ver '.$outside.':1',
    ]);

    unlink($outside);

    expect($bundle)->not->toContain('SECRETO_FUERA_DEL_PROYECTO');
});

test('el bundle no lee archivos con .env en la ruta', function () {
    $probe = storage_path('app/private/probe.env.php');
    file_put_contents($probe, '<?php // SECRETO_ENV_EN_RUTA');

    $bundle = app(ClaudeAssistantService::class)->contextBundle([
        'body' => 'production.ERROR: en storage/app/private/probe.env.php:1',
    ]);

    unlink($probe);

    expect($bundle)->not->toContain('SECRETO_ENV_EN_RUTA');
});

test('explain() falla claro cuando no hay API key', function () {
    config()->set('services.anthropic.key', null);

    $result = app(ClaudeAssistantService::class)->explain(['body' => 'x']);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toContain('ANTHROPIC_API_KEY');
});

test('explain() devuelve el texto de Claude y respeta el rate limit', function () {
    config()->set('services.anthropic.key', 'sk-ant-test');
    RateLimiter::clear('claude-assist:7');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'thinking', 'thinking' => ''],
                ['type' => 'text', 'text' => '## Causa raíz\nUna variable sin definir.'],
            ],
            'stop_reason' => 'end_turn',
        ]),
    ]);

    $service = app(ClaudeAssistantService::class);

    $result = $service->explain(['body' => 'production.ERROR: boom'], 7);

    expect($result['ok'])->toBeTrue()
        ->and($result['markdown'])->toContain('Causa raíz');

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'sk-ant-test')
        && $request['model'] === 'claude-opus-5'
        && $request['messages'][0]['role'] === 'user');

    // El noveno intento pega con el límite.
    for ($i = 0; $i < 8; $i++) {
        $service->explain(['body' => 'x'], 7);
    }

    expect($service->explain(['body' => 'x'], 7)['error'])->toContain('Límite');
});

test('explain() propaga un error HTTP de la API sin tragárselo', function () {
    config()->set('services.anthropic.key', 'sk-ant-test');
    RateLimiter::clear('claude-assist:9');

    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529),
    ]);

    $result = app(ClaudeAssistantService::class)->explain(['body' => 'x'], 9);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toContain('529');
});
