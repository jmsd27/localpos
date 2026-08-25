<?php

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showGroupForm = false;

    public ?int $editingGroupId = null;

    public string $group_name = '';

    public bool $is_required = false;

    public int $min_selections = 0;

    public int $max_selections = 1;

    public ?int $expandedGroupId = null;

    public bool $showOptionForm = false;

    public ?int $editingOptionId = null;

    public ?int $optionGroupId = null;

    public string $option_name = '';

    public string $price_delta = '0';

    public bool $option_is_active = true;

    public function createGroup(): void
    {
        abort_unless(Auth::user()->can('productos.crear'), 403);

        $this->resetGroupForm();
        $this->showGroupForm = true;
    }

    public function editGroup(int $id): void
    {
        $group = ModifierGroup::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingGroupId = $group->id;
        $this->group_name = $group->name;
        $this->is_required = $group->is_required;
        $this->min_selections = $group->min_selections;
        $this->max_selections = $group->max_selections;
        $this->showGroupForm = true;
    }

    public function saveGroup(): void
    {
        abort_unless(Auth::user()->can($this->editingGroupId ? 'productos.editar' : 'productos.crear'), 403);

        $data = $this->validate([
            'group_name' => 'required|string|max:255',
            'is_required' => 'boolean',
            'min_selections' => 'required|integer|min:0',
            'max_selections' => 'required|integer|min:1|gte:min_selections',
        ]);

        $payload = [
            'name' => $data['group_name'],
            'is_required' => $data['is_required'],
            'min_selections' => $data['min_selections'],
            'max_selections' => $data['max_selections'],
            'business_id' => Auth::user()->businessId(),
        ];

        if ($this->editingGroupId) {
            ModifierGroup::query()->where('business_id', $payload['business_id'])->findOrFail($this->editingGroupId)->update($payload);
        } else {
            ModifierGroup::create($payload);
        }

        $this->resetGroupForm();
        $this->showGroupForm = false;
    }

    public function deleteGroup(int $id): void
    {
        abort_unless(Auth::user()->can('productos.eliminar'), 403);

        ModifierGroup::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();

        if ($this->expandedGroupId === $id) {
            $this->expandedGroupId = null;
        }
    }

    public function cancelGroup(): void
    {
        $this->resetGroupForm();
        $this->showGroupForm = false;
    }

    private function resetGroupForm(): void
    {
        $this->reset(['editingGroupId', 'group_name', 'is_required']);
        $this->min_selections = 0;
        $this->max_selections = 1;
    }

    public function toggleOptions(int $groupId): void
    {
        $this->expandedGroupId = $this->expandedGroupId === $groupId ? null : $groupId;
        $this->showOptionForm = false;
    }

    public function createOption(int $groupId): void
    {
        abort_unless(Auth::user()->can('productos.crear'), 403);

        $this->resetOptionForm();
        $this->optionGroupId = $groupId;
        $this->showOptionForm = true;
    }

    public function editOption(int $id): void
    {
        $option = ModifierOption::query()
            ->whereHas('group', fn ($q) => $q->where('business_id', Auth::user()->businessId()))
            ->findOrFail($id);

        $this->editingOptionId = $option->id;
        $this->optionGroupId = $option->modifier_group_id;
        $this->option_name = $option->name;
        $this->price_delta = (string) $option->price_delta;
        $this->option_is_active = $option->is_active;
        $this->showOptionForm = true;
    }

    public function saveOption(): void
    {
        abort_unless(Auth::user()->can($this->editingOptionId ? 'productos.editar' : 'productos.crear'), 403);

        $data = $this->validate([
            'option_name' => 'required|string|max:255',
            'price_delta' => 'required|numeric',
            'option_is_active' => 'boolean',
        ]);

        $payload = [
            'name' => $data['option_name'],
            'price_delta' => $data['price_delta'],
            'is_active' => $data['option_is_active'],
            'modifier_group_id' => $this->optionGroupId,
        ];

        if ($this->editingOptionId) {
            ModifierOption::query()->findOrFail($this->editingOptionId)->update($payload);
        } else {
            ModifierOption::create($payload);
        }

        $this->resetOptionForm();
        $this->showOptionForm = false;
    }

    public function deleteOption(int $id): void
    {
        abort_unless(Auth::user()->can('productos.eliminar'), 403);

        ModifierOption::query()
            ->whereHas('group', fn ($q) => $q->where('business_id', Auth::user()->businessId()))
            ->findOrFail($id)
            ->delete();
    }

    public function cancelOption(): void
    {
        $this->resetOptionForm();
        $this->showOptionForm = false;
    }

    private function resetOptionForm(): void
    {
        $this->reset(['editingOptionId', 'option_name', 'optionGroupId']);
        $this->price_delta = '0';
        $this->option_is_active = true;
    }

    public function with(): array
    {
        return [
            'groups' => ModifierGroup::query()
                ->where('business_id', Auth::user()->businessId())
                ->withCount('options')
                ->with('options')
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Modificadores</h1>
            </div>
            @can('productos.crear')
                <button wire:click="createGroup" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                    Nuevo grupo
                </button>
            @endcan
        </div>

        @if ($showGroupForm)
            <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
                <form wire:submit="saveGroup" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Nombre del grupo</label>
                        <input type="text" wire:model="group_name" placeholder="p. ej. Tipo de carne" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        @error('group_name') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Mínimo de selecciones</label>
                            <input type="number" min="0" wire:model="min_selections" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Máximo de selecciones</label>
                            <input type="number" min="1" wire:model="max_selections" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('max_selections') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" wire:model="is_required" class="rounded border-slate-700 bg-slate-800">
                        Obligatorio
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">Guardar</button>
                        <button type="button" wire:click="cancelGroup" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="space-y-3">
            @forelse ($groups as $group)
                <div class="rounded-xl border border-slate-800 bg-slate-900">
                    <div class="flex items-center justify-between p-4">
                        <button wire:click="toggleOptions({{ $group->id }})" class="flex items-center gap-2 text-left">
                            <span class="font-medium">{{ $group->name }}</span>
                            <span class="text-xs text-slate-500">
                                {{ $group->options_count }} {{ Str::plural('opción', $group->options_count) }}
                                · {{ $group->is_required ? 'obligatorio' : 'opcional' }}
                                · min {{ $group->min_selections }} / max {{ $group->max_selections }}
                            </span>
                        </button>
                        <div class="flex items-center gap-3 text-sm">
                            @can('productos.crear')
                                <button wire:click="createOption({{ $group->id }})" class="text-emerald-400 hover:text-emerald-300">+ Opción</button>
                            @endcan
                            @can('productos.editar')
                                <button wire:click="editGroup({{ $group->id }})" class="text-indigo-400 hover:text-indigo-300">Editar</button>
                            @endcan
                            @can('productos.eliminar')
                                <button wire:click="deleteGroup({{ $group->id }})" wire:confirm="¿Eliminar este grupo y sus opciones?" class="text-red-400 hover:text-red-300">Eliminar</button>
                            @endcan
                        </div>
                    </div>

                    @if ($expandedGroupId === $group->id)
                        <div class="border-t border-slate-800 p-4">
                            @if ($showOptionForm && $optionGroupId === $group->id)
                                <form wire:submit="saveOption" class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-slate-800/50 p-3 sm:grid-cols-[1fr_auto_auto_auto]">
                                    <input type="text" wire:model="option_name" placeholder="Nombre" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                    <input type="number" step="0.01" wire:model="price_delta" placeholder="Precio adicional" class="w-32 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm hover:bg-indigo-500">Guardar</button>
                                    <button type="button" wire:click="cancelOption" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                                </form>
                                @error('option_name') <span class="mb-2 block text-sm text-red-400">{{ $message }}</span> @enderror
                            @endif

                            <ul class="divide-y divide-slate-800">
                                @forelse ($group->options as $option)
                                    <li class="flex items-center justify-between py-2 text-sm">
                                        <span>
                                            {{ $option->name }}
                                            @if ((float) $option->price_delta > 0)
                                                <span class="text-slate-500">+${{ number_format((float) $option->price_delta, 2) }}</span>
                                            @elseif ((float) $option->price_delta < 0)
                                                <span class="text-slate-500">-${{ number_format(abs((float) $option->price_delta), 2) }}</span>
                                            @else
                                                <span class="text-slate-500">gratis</span>
                                            @endif
                                        </span>
                                        <span class="flex items-center gap-3">
                                            @can('productos.editar')
                                                <button wire:click="editOption({{ $option->id }})" class="text-indigo-400 hover:text-indigo-300">Editar</button>
                                            @endcan
                                            @can('productos.eliminar')
                                                <button wire:click="deleteOption({{ $option->id }})" wire:confirm="¿Eliminar esta opción?" class="text-red-400 hover:text-red-300">Eliminar</button>
                                            @endcan
                                        </span>
                                    </li>
                                @empty
                                    <li class="py-2 text-sm text-slate-500">Sin opciones todavía.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-6 text-center text-slate-500">
                    Sin grupos de modificadores todavía.
                </div>
            @endforelse
        </div>
    </div>
</div>
