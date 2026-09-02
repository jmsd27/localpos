<?php

use App\Models\Business;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Manuales de implementación paso a paso para poner en marcha un negocio
 * nuevo. Solo el super administrador entra aquí (middleware role:super-admin
 * en routes/web.php + verificación defensiva en mount()).
 *
 * El contenido son archivos Markdown en resources/manuales/. El avance de
 * lectura se guarda por negocio en la tabla settings (grupo "onboarding").
 */
new #[Layout('layouts.app')] class extends Component
{
    #[Url(as: 'm')]
    public string $current = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $slugs = $this->manuals()->pluck('slug');

        if (! $slugs->contains($this->current)) {
            $this->current = (string) $slugs->first();
        }
    }

    /** @return \Illuminate\Support\Collection<int, array{slug:string,number:string,title:string}> */
    public function manuals(): \Illuminate\Support\Collection
    {
        return collect(glob(resource_path('manuales/*.md')))
            ->sort()
            ->values()
            ->map(function (string $path) {
                $slug = basename($path, '.md');
                $firstLine = trim((string) strtok(file_get_contents($path), "\n"));
                $heading = trim(ltrim($firstLine, '# '));
                [$number] = array_pad(explode('·', $heading, 2), 2, '');

                return [
                    'slug' => $slug,
                    'number' => trim($number),
                    'title' => $heading,
                ];
            });
    }

    private function businessId(): ?int
    {
        return Auth::user()?->businessId() ?? Business::query()->value('id');
    }

    /** @return array<string, bool> */
    public function progress(): array
    {
        $businessId = $this->businessId();

        if ($businessId === null) {
            return [];
        }

        $settings = app(SettingsService::class);

        return $this->manuals()
            ->mapWithKeys(fn (array $m) => [
                $m['slug'] => (bool) $settings->get($businessId, "manual:{$m['slug']}", false),
            ])
            ->all();
    }

    public function toggleDone(string $slug): void
    {
        $businessId = $this->businessId();

        if ($businessId === null || ! $this->manuals()->pluck('slug')->contains($slug)) {
            return;
        }

        $settings = app(SettingsService::class);
        $done = (bool) $settings->get($businessId, "manual:{$slug}", false);
        $settings->set($businessId, "manual:{$slug}", $done ? '' : '1', 'onboarding');
    }

    public function with(): array
    {
        $manuals = $this->manuals();
        $progress = $this->progress();
        $path = resource_path("manuales/{$this->current}.md");

        return [
            'manuals' => $manuals,
            'progress' => $progress,
            'doneCount' => count(array_filter($progress)),
            'currentHtml' => is_file($path) ? Str::markdown(file_get_contents($path)) : '',
            'currentDone' => $progress[$this->current] ?? false,
            'currentTitle' => optional($manuals->firstWhere('slug', $this->current))['title'] ?? '',
        ];
    }
};
?>

<div>
    <div class="mx-auto max-w-6xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Manuales de implementación</h1>
            <p class="mt-1 text-sm text-gray-500">Guía paso a paso para poner en marcha un negocio nuevo. Solo visible para el super administrador.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[18rem_1fr]">
            {{-- Índice --}}
            <aside class="lg:sticky lg:top-20 lg:self-start">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                            <span>Avance</span>
                            <span>{{ $doneCount }} / {{ $manuals->count() }}</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-violet-600 transition-all"
                                style="width: {{ $manuals->count() ? round($doneCount / $manuals->count() * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    <ol class="space-y-0.5">
                        @foreach ($manuals as $manual)
                            <li>
                                <button type="button" wire:click="$set('current', '{{ $manual['slug'] }}')"
                                    class="flex w-full items-start gap-2 rounded-lg px-2.5 py-2 text-left text-sm
                                        {{ $current === $manual['slug'] ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    @php($isDone = $progress[$manual['slug']] ?? false)
                                    <span @class([
                                        'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] leading-none',
                                        'border-violet-600 bg-violet-600 text-white' => $isDone,
                                        'border-gray-300' => ! $isDone,
                                    ])>{!! $isDone ? '&check;' : '' !!}</span>
                                    <span>{{ $manual['title'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </aside>

            {{-- Contenido --}}
            <div class="min-w-0">
                <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                        <span class="text-sm font-medium text-gray-400">{{ $currentTitle }}</span>
                        <button type="button" wire:click="toggleDone('{{ $current }}')" @class([
                            'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium',
                            'bg-violet-600 text-white hover:bg-violet-700' => ! $currentDone,
                            'border border-violet-200 bg-violet-50 text-violet-700' => $currentDone,
                        ])>
                            {{ $currentDone ? '✓ Completado' : 'Marcar como completado' }}
                        </button>
                    </div>

                    <article class="manual">{!! $currentHtml !!}</article>
                </div>
            </div>
        </div>
    </div>

    <style>
        .manual { color: #374151; font-size: 0.925rem; line-height: 1.7; }
        .manual > :first-child { margin-top: 0; }
        .manual h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0 0 1rem; }
        .manual h2 { font-size: 1.15rem; font-weight: 600; color: #111827; margin: 2rem 0 0.75rem; padding-top: 1rem; border-top: 1px solid #f3f4f6; }
        .manual h3 { font-size: 1rem; font-weight: 600; color: #111827; margin: 1.5rem 0 0.5rem; }
        .manual p { margin: 0.75rem 0; }
        .manual ul, .manual ol { margin: 0.75rem 0; padding-left: 1.5rem; }
        .manual ul { list-style: disc; }
        .manual ol { list-style: decimal; }
        .manual li { margin: 0.35rem 0; }
        .manual li::marker { color: #9ca3af; }
        .manual ul.contains-task-list { list-style: none; padding-left: 0.25rem; }
        .manual .task-list-item { display: flex; align-items: flex-start; gap: 0.5rem; }
        .manual .task-list-item input { margin-top: 0.35rem; }
        .manual a { color: #7c3aed; text-decoration: underline; }
        .manual code { background: #f3f4f6; border-radius: 0.25rem; padding: 0.1rem 0.35rem; font-size: 0.85em; }
        .manual pre { background: #1e293b; color: #e2e8f0; border-radius: 0.5rem; padding: 1rem; overflow-x: auto; margin: 1rem 0; }
        .manual pre code { background: transparent; padding: 0; color: inherit; }
        .manual blockquote { border-left: 3px solid #ddd6fe; background: #faf5ff; padding: 0.5rem 1rem; margin: 1rem 0; color: #6b21a8; border-radius: 0 0.375rem 0.375rem 0; }
        .manual blockquote p { margin: 0.25rem 0; }
        .manual table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.875rem; display: block; overflow-x: auto; }
        .manual th, .manual td { border: 1px solid #e5e7eb; padding: 0.5rem 0.75rem; text-align: left; vertical-align: top; }
        .manual th { background: #f9fafb; font-weight: 600; color: #111827; }
        .manual strong { color: #111827; font-weight: 600; }
        .manual hr { border: 0; border-top: 1px solid #e5e7eb; margin: 2rem 0; }
    </style>
</div>
