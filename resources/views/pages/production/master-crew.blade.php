<?php

use App\Models\Master\ProdCrew;
use App\Models\Master\ProdProcess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Master Crew (PROD_MST_CREW) -- data riil dimigrasi dari tm2old.mst_machine
// (machine_type=CREW, 217 baris) via
// database/migrations/2026_08_28_000030_migrate_machine_crew_from_tm2old.php.
// Proses default cuma ter-link (process_id) kalau label lama match persis ke
// Master Proses -- yang tak match dibiarkan kosong, label asli ada di notes
// (lihat migration). legacy_employee_code sengaja read-only (jejak migrasi,
// BUKAN key aktif -- format beda dgn emp_employee.employee_code, lihat
// komentar migration 000029). Pola grid+form sama master/machine.blade.php.
new #[Title('Master Crew — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $this->openModal('edit', $id);
    }

    protected function newFromQuery(): void
    {
        $this->openModal('new');
    }

    public string $search = '';

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new'; // new | edit

    public ?int $editingId = null;

    public string $crew_name = '';

    public ?int $process_id = null;

    public string $legacy_employee_code = '';

    public string $notes = '';

    public bool $crew_activate = true;

    #[Computed]
    public function crews()
    {
        return ProdCrew::query()
            ->with('process')
            ->when($this->search !== '', fn ($q) => $q->where('crew_name', 'like', "%{$this->search}%"))
            ->orderBy('crew_name')
            ->get();
    }

    #[Computed]
    public function processOptions()
    {
        return ProdProcess::where('activate', true)->orderBy('process_name')->get();
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;

        if ($this->mode === 'edit' && $id) {
            $c = ProdCrew::findOrFail($id);
            $this->crew_name = $c->crew_name;
            $this->process_id = $c->process_id;
            $this->legacy_employee_code = (string) $c->legacy_employee_code;
            $this->notes = (string) $c->notes;
            $this->crew_activate = (bool) $c->activate;
        } else {
            $this->reset(['crew_name', 'process_id', 'legacy_employee_code', 'notes']);
            $this->crew_activate = true;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function save(): void
    {
        $data = $this->validate([
            'crew_name' => ['required', 'string', 'max:150'],
            'process_id' => ['nullable', 'integer', 'exists:tm2mst.prod_process,id_process'],
            'notes' => ['nullable', 'string'],
            'crew_activate' => ['boolean'],
        ]);

        $payload = [
            'crew_name' => $data['crew_name'],
            'process_id' => $data['process_id'] ?: null,
            'notes' => $data['notes'] ?: null,
            'activate' => $data['crew_activate'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            ProdCrew::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            ProdCrew::create($payload);
        }

        unset($this->crews);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $c = ProdCrew::findOrFail($id);
        $c->activate = ! $c->activate;
        $c->updated_by = Auth::user()->login;
        $c->save();

        unset($this->crews, $this->statTotal, $this->statActive, $this->statInactive, $this->statUnlinked);
    }

    #[Computed]
    public function statTotal(): int
    {
        return ProdCrew::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdCrew::where('activate', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdCrew::where('activate', false)->count();
    }

    #[Computed]
    public function statUnlinked(): int
    {
        return ProdCrew::whereNull('process_id')->count();
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" :label="t('Total Crew')" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" :label="t('Active')" :value="$this->statActive" />
            <x-grid.stat-box tone="extra" :label="t('Process Not Linked')" :value="$this->statUnlinked" />
            <x-grid.stat-box tone="critical" :label="t('Inactive')" :value="$this->statInactive" />
        </x-grid.stat-row>

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">
            {{ t('Data migrated from the old system.') }} {!! t('An empty <b>Process</b> column means the old process label does not exactly match the current Process Master — check the notes in the edit form, then choose the right process.') !!}
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search crew name…') }}"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="openModal('new')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Crew') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[160px]">{{ t('Default Process') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->crews as $c)
                            <tr wire:key="crew-{{ $c->id_crew }}" wire:dblclick="openModal('edit', {{ $c->id_crew }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open detail') }}">
                                <x-grid.identity-cell :id="$c->id_crew" :name="$c->crew_name" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($c->process)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#eef1f8] text-[#2c3e69]">{{ $c->process->process_name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $c->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $c->activate ? t('ACTIVE') : t('INACTIVE') }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" :title="t('Edit')" action="openModal('edit', {{ $c->id_crew }})" />
                                    <x-grid.action-icon :active="$c->activate" :title="$c->activate ? t('Deactivate') : t('Activate')" action="toggleActive({{ $c->id_crew }})"
                                        :confirm="str_replace(':name', $c->crew_name, $c->activate ? t('Deactivate crew :name?') : t('Activate crew :name?'))"
                                        :variant="$c->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="4" :message="t('None.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $mode === 'edit' ? t('Edit Crew') : t('New Crew') }}
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    <x-form.input :label="t('Crew Name').' *'" name="crew_name" wire:model="crew_name" :placeholder="t('e.g. Ahmad Fauzi')" />

                    <x-form.select :label="t('Default Process')" wire:model="process_id"
                        :options="$this->processOptions->pluck('process_name', 'id_process')" :selected="$process_id" />

                    @if ($legacy_employee_code)
                        <x-form.input :label="t('Old Employee Code (migration trace)')" wire:model="legacy_employee_code" disabled />
                    @endif

                    <x-form.textarea :label="t('Notes')" wire:model="notes" rows="3" :placeholder="t('Additional notes')" />
                    <x-form.checkbox :label="t('Active')" wire:model="crew_activate" />

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                    </div>
            </form>
        @endif
    </div>
</div>
