<?php

use App\Services\ClaudeAssistantService;
use App\Services\DiagnosticsService;
use App\Support\LaravelLogReader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Diagnóstico y asistencia. Solo el super administrador entra (middleware
 * role:super-admin en routes/web.php + verificación defensiva en cada acción).
 *
 * Panel de SOLO LECTURA (DiagnosticsService) + un asistente que manda un error
 * del log a la API de Claude y devuelve el diagnóstico como texto
 * (ClaudeAssistantService). No ejecuta comandos ni escribe archivos: el arreglo
 * lo aplica una persona por el flujo normal (local → git → redeploy).
 */
new #[Layout('layouts.app')] class extends Component
{
    public string $aiMarkdown = '';

    public string $aiError = '';

    public string $aiForKey = '';

    public string $bundle = '';

    public string $bundleForKey = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);
    }

    /** @return array{timestamp?:string,level?:string,summary?:string,body?:string}|null */
    private function entry(string $key): ?array
    {
        return collect(app(LaravelLogReader::class)->recentErrors(50))
            ->firstWhere('key', $key);
    }

    public function explain(string $key): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $this->reset('aiMarkdown', 'aiError', 'bundle', 'bundleForKey');
        $this->aiForKey = $key;

        $entry = $this->entry($key);

        if ($entry === null) {
            $this->aiError = 'Esa entrada del log ya no está disponible (el archivo pudo rotarse).';

            return;
        }

        $result = app(ClaudeAssistantService::class)->explain($entry, Auth::id());

        if ($result['ok']) {
            $this->aiMarkdown = (string) $result['markdown'];
        } else {
            $this->aiError = (string) $result['error'];
        }
    }

    public function showBundle(string $key): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $this->reset('aiMarkdown', 'aiError', 'aiForKey');

        $entry = $this->entry($key);

        $this->bundleForKey = $key;
        $this->bundle = $entry === null
            ? 'Esa entrada del log ya no está disponible.'
            : app(ClaudeAssistantService::class)->contextBundle($entry);
    }

    public function with(): array
    {
        return [
            'snapshot' => app(DiagnosticsService::class)->snapshot(),
            'aiEnabled' => app(ClaudeAssistantService::class)->enabled(),
            'aiHtml' => $this->aiMarkdown === '' ? '' : Str::markdown($this->aiMarkdown),
            'fmtBytes' => function (?int $bytes): string {
                if ($bytes === null) {
                    return '—';
                }
                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
                $i = min($i, count($units) - 1);

                return round($bytes / (1024 ** $i), 1).' '.$units[$i];
            },
        ];
    }
};
?>

<div>
    <div class="mx-auto max-w-6xl">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Diagnóstico y asistencia</h1>
                <p class="mt-1 text-sm text-gray-500">Estado de la instalación y ayuda para resolver errores. Solo visible para el super administrador.</p>
            </div>
            <button type="button" wire:click="$refresh" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-white">
                <x-icon name="cloud-arrow-down" class="h-4 w-4" />
                Actualizar
            </button>
        </div>

        {{-- Tarjetas de salud --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @php($app = $snapshot['app'])
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Aplicación</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Entorno</dt><dd class="font-medium">{{ $app['env'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Debug</dt>
                        <dd @class(['font-medium', 'text-amber-600' => $app['debug']])>{{ $app['debug'] ? 'activado' : 'desactivado' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Rol de sync</dt><dd class="font-medium">{{ $app['sync_role'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">PHP</dt><dd class="font-medium">{{ $app['php_version'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Laravel</dt><dd class="font-medium">{{ $app['laravel_version'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Config cacheada</dt><dd class="font-medium">{{ $app['config_cached'] ? 'sí' : 'no' }}</dd></div>
                </dl>
            </div>

            @php($db = $snapshot['database'])
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Base de datos</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Conexión</dt>
                        <dd @class(['font-medium', 'text-red-600' => ! $db['connected'], 'text-emerald-600' => $db['connected']])>
                            {{ $db['connected'] ? 'ok' : 'sin conexión' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Driver</dt><dd class="font-medium">{{ $db['driver'] }}</dd></div>
                    @if ($db['name'])
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Esquema</dt><dd class="font-medium">{{ $db['name'] }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Migraciones pendientes</dt>
                        <dd @class(['font-medium', 'text-amber-600' => ($db['pending_migrations'] ?? 0) > 0])>{{ $db['pending_migrations'] ?? '—' }}</dd></div>
                    @if ($db['error'])
                        <p class="mt-1 rounded bg-red-50 px-2 py-1 text-xs text-red-700">{{ $db['error'] }}</p>
                    @endif
                </dl>
            </div>

            @php($queue = $snapshot['queue'])
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Colas</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Pendientes</dt><dd class="font-medium">{{ $queue['pending'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Fallidos</dt>
                        <dd @class(['font-medium', 'text-red-600' => ($queue['failed'] ?? 0) > 0])>{{ $queue['failed'] ?? '—' }}</dd></div>
                </dl>
                @if (! empty($queue['failed_recent']))
                    <ul class="mt-2 space-y-1 border-t border-gray-100 pt-2 text-xs text-gray-500">
                        @foreach ($queue['failed_recent'] as $failed)
                            <li class="truncate" title="{{ $failed['exception'] }}">#{{ $failed['id'] }} · {{ $failed['exception'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @php($storage = $snapshot['storage'])
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Almacenamiento</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Log de Laravel</dt><dd class="font-medium">{{ $fmtBytes($storage['logs_bytes']) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Disco libre</dt><dd class="font-medium">{{ $fmtBytes($storage['disk_free_bytes']) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Disco total</dt><dd class="font-medium">{{ $fmtBytes($storage['disk_total_bytes']) }}</dd></div>
                </dl>
            </div>

            @php($sync = $snapshot['sync'])
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Sincronización</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Rol</dt><dd class="font-medium">{{ $sync['role'] }}</dd></div>
                    @if (array_key_exists('outbox_pending', $sync))
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Outbox pendiente</dt>
                            <dd @class(['font-medium', 'text-amber-600' => ($sync['outbox_pending'] ?? 0) > 50])>{{ $sync['outbox_pending'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Con errores</dt>
                            <dd @class(['font-medium', 'text-red-600' => ($sync['outbox_with_errors'] ?? 0) > 0])>{{ $sync['outbox_with_errors'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">Último enviado</dt><dd class="font-medium">{{ $sync['last_synced_at'] ?? 'nunca' }}</dd></div>
                        @if ($sync['oldest_pending_at'] ?? null)
                            <div class="flex justify-between gap-2"><dt class="text-gray-500">Más antiguo sin enviar</dt><dd class="font-medium">{{ $sync['oldest_pending_at'] }}</dd></div>
                        @endif
                    @else
                        <p class="text-xs text-gray-400">Sin tabla sync_outbox en esta instalación.</p>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Errores recientes --}}
        <div class="mt-8 rounded-xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Errores recientes del log</h2>
                <span class="text-xs text-gray-400">{{ $snapshot['logs']['exists'] ? $fmtBytes($snapshot['logs']['bytes']) : 'sin archivo de log' }}</span>
            </div>

            @php($errors = $snapshot['logs']['errors'])
            @if (empty($errors))
                <p class="px-5 py-6 text-sm text-gray-500">No hay errores recientes en <code>storage/logs/laravel.log</code>. 🎉</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($errors as $err)
                        <li class="px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span @class([
                                            'rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase',
                                            'bg-red-100 text-red-700' => in_array($err['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true),
                                        ])>{{ $err['level'] }}</span>
                                        <span class="text-xs text-gray-400">{{ $err['timestamp'] }}</span>
                                    </div>
                                    <p class="mt-1 break-words text-sm text-gray-800">{{ $err['summary'] }}</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    @if ($aiEnabled)
                                        <button type="button" wire:click="explain('{{ $err['key'] }}')" wire:loading.attr="disabled" wire:target="explain"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-violet-700 disabled:opacity-50">
                                            <x-icon name="wrench-screwdriver" class="h-3.5 w-3.5" />
                                            Explicar con IA
                                        </button>
                                    @endif
                                    <button type="button" wire:click="showBundle('{{ $err['key'] }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                        Ver contexto
                                    </button>
                                </div>
                            </div>

                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-gray-400 hover:text-gray-600">Entrada completa</summary>
                                <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-slate-100">{{ $err['body'] }}</pre>
                            </details>

                            @if ($aiForKey === $err['key'] && ($aiHtml !== '' || $aiError !== ''))
                                <div class="mt-3 rounded-lg border border-violet-200 bg-violet-50/60 p-4">
                                    @if ($aiError !== '')
                                        <p class="text-sm text-red-700">{{ $aiError }}</p>
                                    @else
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-violet-500">Diagnóstico de Claude — revisá y aplicá por local → git → redeploy</p>
                                        <div class="ai-md">{!! $aiHtml !!}</div>
                                    @endif
                                </div>
                            @endif

                            @if ($bundleForKey === $err['key'] && $bundle !== '')
                                <div class="mt-3" x-data="{ copied: false }">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Contexto para pegar en Claude Code (local)</p>
                                        <button type="button"
                                            x-on:click="navigator.clipboard.writeText($refs.bundle.value).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                                            class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50"
                                            x-text="copied ? '¡Copiado!' : 'Copiar'"></button>
                                    </div>
                                    <textarea x-ref="bundle" readonly rows="10"
                                        class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 p-3 font-mono text-xs text-gray-700">{{ $bundle }}</textarea>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @unless ($aiEnabled)
            <p class="mt-4 text-xs text-gray-400">
                El diagnóstico con IA se activa al configurar <code>ANTHROPIC_API_KEY</code> en el <code>.env</code>.
                Mientras tanto, «Ver contexto» arma el texto para pegar en Claude Code en tu equipo.
            </p>
        @endunless
    </div>

    <style>
        .ai-md { color: #374151; font-size: 0.9rem; line-height: 1.65; }
        .ai-md > :first-child { margin-top: 0; }
        .ai-md h1, .ai-md h2, .ai-md h3 { font-weight: 600; color: #111827; margin: 1.1rem 0 0.5rem; }
        .ai-md h1 { font-size: 1.15rem; } .ai-md h2 { font-size: 1.05rem; } .ai-md h3 { font-size: 0.95rem; }
        .ai-md p { margin: 0.6rem 0; }
        .ai-md ul, .ai-md ol { margin: 0.6rem 0; padding-left: 1.4rem; }
        .ai-md ul { list-style: disc; } .ai-md ol { list-style: decimal; }
        .ai-md li { margin: 0.25rem 0; }
        .ai-md code { background: #ede9fe; border-radius: 0.25rem; padding: 0.1rem 0.35rem; font-size: 0.85em; }
        .ai-md pre { background: #1e293b; color: #e2e8f0; border-radius: 0.5rem; padding: 0.9rem; overflow-x: auto; margin: 0.8rem 0; }
        .ai-md pre code { background: transparent; padding: 0; color: inherit; }
        .ai-md strong { color: #111827; font-weight: 600; }
    </style>
</div>
