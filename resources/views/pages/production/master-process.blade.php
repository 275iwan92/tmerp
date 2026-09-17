<?php

use App\Models\Master\ProdProcess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Proses (PROD_MST_PROCESS) -- data riil dimigrasi dari tm2old.m_process
// (database/migrations/2026_08_28_000027_migrate_process_master_from_tm2old.php),
// 19 baris. Skema paling sederhana di antara 3 Master baru (Proses/Mesin/Kru):
// cuma nama + warna gantt (dipakai kelak di MPS Gantt Chart, belum diwire).
// Pola grid+form sama persis master/machine.blade.php (griduistandard.md).
new #[Title('Master Proses — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new'; // new | edit

    public ?int $editingId = null;

    public string $process_name = '';

    public string $gantt_color = '#2c3e69';

    public bool $process_activate = true;

    // 0 = proses dikerjakan pihak ke-3 -> tombol Subkon di BOP ProDev.
    public bool $inhouse = true;

    #[Computed]
    public function processes()
    {
        return ProdProcess::query()
            ->when($this->search !== '', fn ($q) => $q->where('process_name', 'like', "%{$this->search}%"))
            ->orderBy('process_name')
            ->get();
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;

        if ($this->mode === 'edit' && $id) {
            $p = ProdProcess::findOrFail($id);
            $this->process_name = $p->process_name;
            $this->gantt_color = $p->gantt_color ?: '#2c3e69';
            $this->process_activate = (bool) $p->activate;
            $this->inhouse = (bool) $p->inhouse;
        } else {
            $this->reset(['process_name']);
            $this->gantt_color = '#2c3e69';
            $this->process_activate = true;
            $this->inhouse = true;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.prod_process,process_name';
        if ($this->mode === 'edit' && $this->editingId) {
            $unique .= ','.$this->editingId.',id_process';
        }

        $data = $this->validate([
            'process_name' => ['required', 'string', 'max:200', $unique],
            'gantt_color' => ['nullable', 'string', 'max:10'],
            'process_activate' => ['boolean'],
            'inhouse' => ['boolean'],
        ]);

        $payload = [
            'process_name' => $data['process_name'],
            'gantt_color' => $data['gantt_color'] ?: null,
            'activate' => $data['process_activate'],
            'inhouse' => $data['inhouse'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            ProdProcess::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            ProdProcess::create($payload);
        }

        unset($this->processes);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $p = ProdProcess::findOrFail($id);
        $p->activate = ! $p->activate;
        $p->updated_by = Auth::user()->login;
        $p->save();

        unset($this->processes, $this->statTotal, $this->statActive, $this->statInactive);
    }

    #[Computed]
    public function statTotal(): int
    {
        return ProdProcess::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdProcess::where('activate', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdProcess::where('activate', false)->count();
    }
};
?>

<div>
    <div class="max-w-[1000px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total Proses" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Aktif" :value="$this->statActive" />
            <x-grid.stat-box tone="critical" label="Nonaktif" :value="$this->statInactive" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama proses…"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Proses</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Warna Gantt</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->processes as $p)
                            <tr wire:key="process-{{ $p->id_process }}" wire:dblclick="openModal('edit', {{ $p->id_process }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                <x-grid.identity-cell :id="$p->id_process" :name="$p->process_name" :code="$p->inhouse ? '' : 'SUBKON'" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($p->gantt_color)
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="w-3.5 h-3.5 rounded border border-[#d7dbe2] inline-block" style="background:{{ $p->gantt_color }}"></span>
                                            <span class="text-[11px] text-muted font-mono">{{ $p->gantt_color }}</span>
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $p->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $p->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="✎" title="Edit" action="openModal('edit', {{ $p->id_process }})" />
                                    <x-grid.action-icon icon="⏻" :title="$p->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $p->id_process }})"
                                        :confirm="($p->activate ? 'Nonaktifkan' : 'Aktifkan').' proses '.$p->process_name.'?'"
                                        :variant="$p->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="4" message="Tidak ada." />
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
                    {{ $mode === 'edit' ? 'Edit Proses' : 'Proses Baru' }}
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    <x-form.input label="Nama Proses *" name="process_name" wire:model="process_name" placeholder="mis. Cetak, Laminasi, Potong" />

                    <div class="flex gap-2.5 items-end">
                        <div class="w-24">
                            <x-form.input label="Warna Gantt" type="color" wire:model="gantt_color" />
                        </div>
                        <div class="text-[11px] text-muted pb-2">Dipakai buat bedakan proses di MPS Gantt Chart.</div>
                    </div>

                    <x-form.checkbox label="Dikerjakan sendiri (in-house) — kosongkan bila dikerjakan subkon" wire:model="inhouse" />
                    <x-form.checkbox label="Aktif" wire:model="process_activate" />

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                    </div>
            </form>
        @endif
    </div>
</div>
