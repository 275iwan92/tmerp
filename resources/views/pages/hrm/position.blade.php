<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgLevel;
use App\Models\Master\OrgPosition;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Master Posisi -- grup sidebar "Organization" (HRM_ORG). Field baru
// "Melapor ke" (reports_to_position_id) sumber hierarki utk layar Struktur
// Organisasi (org-chart.blade.php) -- instruksi user 2026-08-29, dipilih
// "form dropdown per posisi" (bukan drag-connect kanvas) supaya chart tetap
// murni tampilan read-only dari data ini.
new #[Title('Master Posisi — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;
    use HasGridPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $position_code = '';

    public string $position_name = '';

    public ?int $department_id = null;

    public ?int $level_id = null;

    public ?int $reports_to_position_id = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id');
    }

    #[Computed]
    public function levels()
    {
        return OrgLevel::where('activate', true)->orderBy('level_rank')->pluck('level_name', 'level_id');
    }

    // Kandidat atasan -- kecualikan diri sendiri (cegah self-loop trivial).
    // Cegah siklus lebih dalam divalidasi server-side saat simpan.
    #[Computed]
    public function reportsToOptions()
    {
        return OrgPosition::where('activate', true)
            ->when($this->editingId, fn ($q) => $q->where('position_id', '!=', $this->editingId))
            ->orderBy('position_name')
            ->pluck('position_name', 'position_id');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => OrgPosition::count(),
            'active' => OrgPosition::where('activate', true)->count(),
            'unassigned' => OrgPosition::whereNull('reports_to_position_id')->where('activate', true)->count(),
        ];
    }

    #[Computed]
    public function rows()
    {
        return OrgPosition::query()
            ->with(['department', 'level', 'reportsTo'])
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('position_name', 'like', "%{$this->search}%")->orWhere('position_code', 'like', "%{$this->search}%");
            }))
            ->orderBy('position_name')
            ->paginate($this->perPage);
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'position_code', 'position_name', 'department_id', 'level_id', 'reports_to_position_id']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $p = OrgPosition::findOrFail($id);
        $this->editingId = $p->position_id;
        $this->position_code = $p->position_code;
        $this->position_name = $p->position_name;
        $this->department_id = $p->department_id;
        $this->level_id = $p->level_id;
        $this->reports_to_position_id = $p->reports_to_position_id;
        $this->showModal = true;
    }

    // Cegah siklus: reports_to tidak boleh salah satu keturunan (anak/cucu/dst)
    // posisi yang sedang diedit -- kalau lolos, chart jadi loop tak terhingga.
    protected function wouldCreateCycle(int $editingId, ?int $newReportsTo): bool
    {
        $cursor = $newReportsTo;
        $guard = 0;

        while ($cursor !== null && $guard++ < 50) {
            if ($cursor === $editingId) {
                return true;
            }

            $cursor = OrgPosition::where('position_id', $cursor)->value('reports_to_position_id');
        }

        return false;
    }

    public function save(): void
    {
        if (trim($this->position_code) === '' || trim($this->position_name) === '' || ! $this->department_id || ! $this->level_id) {
            $this->addError('form', 'Kode, nama posisi, departemen, dan level wajib diisi.');

            return;
        }

        if ($this->editingId && $this->reports_to_position_id && $this->wouldCreateCycle($this->editingId, $this->reports_to_position_id)) {
            $this->addError('form', 'Tidak bisa: posisi ini akan melapor ke salah satu bawahannya sendiri (siklus).');

            return;
        }

        $payload = [
            'position_code' => $this->position_code,
            'position_name' => $this->position_name,
            'department_id' => $this->department_id,
            'level_id' => $this->level_id,
            'reports_to_position_id' => $this->reports_to_position_id ?: null,
            'updated_by' => Auth::user()->login ?? 'system',
        ];

        if ($this->editingId) {
            OrgPosition::where('position_id', $this->editingId)->update($payload);
        } else {
            OrgPosition::create([...$payload, 'created_by' => Auth::user()->login ?? 'system', 'activate' => true]);
        }

        $this->showModal = false;
        unset($this->rows, $this->stats);
    }

    public function toggleActive(int $id): void
    {
        $p = OrgPosition::findOrFail($id);
        $p->update(['activate' => ! $p->activate]);
        unset($this->rows, $this->stats);
    }
};
?>

<div class="max-w-[1400px] mx-auto my-5 px-4">
    <x-grid.stat-row :count="3">
        <x-grid.stat-box tone="total" label="Total Posisi" :value="$this->stats['total']" />
        <x-grid.stat-box tone="active" label="Aktif" :value="$this->stats['active']" />
        <x-grid.stat-box tone="warn" label="Belum Ada Atasan" :value="$this->stats['unassigned']" />
    </x-grid.stat-row>

    <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
        <x-grid.toolbar>
            <div class="flex items-center gap-1.5 shrink-0">
                <span class="text-sm">🔍</span>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau kode posisi…"
                    class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>
            <x-slot:tools>
                <a href="{{ route('hrm.org-chart') }}" class="shrink-0 text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded no-underline">🌳 Lihat Struktur Organisasi</a>
            </x-slot:tools>
            <x-slot:newRecord>
                <button type="button" wire:click="openNew"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
            </x-slot:newRecord>
        </x-grid.toolbar>

        <x-grid.table-shell>
        <table class="w-full text-xs">
            <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5] bg-[#f4f6fa]">
                <tr>
                    <th class="text-left px-3 py-2">Posisi</th>
                    <th class="text-left px-3 py-2">Departemen</th>
                    <th class="text-left px-3 py-2">Level</th>
                    <th class="text-left px-3 py-2">Melapor Ke</th>
                    <th class="px-3 py-2 text-right w-[70px]">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef1f5]">
                @forelse ($this->rows as $p)
                    <tr wire:key="pos-{{ $p->position_id }}" wire:dblclick="edit({{ $p->position_id }})" class="cursor-pointer hover:bg-[#f8fafc]" title="Klik 2x untuk buka detail">
                        <td class="px-3 py-2">
                            <div class="font-semibold text-[13px]">{{ $p->position_name }}</div>
                            <div class="text-[11px] text-muted font-mono">{{ $p->position_code }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $p->department->department_name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $p->level->level_name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $p->reportsTo->position_name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <x-grid.action-icon icon="✎" title="Edit" action="edit({{ $p->position_id }})" />
                            <x-grid.action-icon icon="⏻" :title="$p->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $p->position_id }})"
                                :confirm="($p->activate ? 'Nonaktifkan' : 'Aktifkan').' posisi '.$p->position_name.'?'"
                                :variant="$p->activate ? 'danger' : 'default'" />
                        </td>
                    </tr>
                @empty
                    <x-grid.empty-row :colspan="5" message="Belum ada posisi." />
                @endforelse
            </tbody>
        </table>
        </x-grid.table-shell>
    </div>

    @include('components.grid.pagination-footer', ['paginator' => $this->rows])

    @if ($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl w-full max-w-[460px] p-5">
                <h3 class="text-sm font-bold text-navy mb-3">{{ $editingId ? 'Edit Posisi' : 'Tambah Posisi Baru' }}</h3>
                @error('form') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <div class="space-y-3">
                    <div class="grid two">
                        <x-form.input label="Kode Posisi" wire:model="position_code" />
                        <x-form.select wire:model="level_id" :options="$this->levels" placeholder="— pilih level —"><x-slot:label>Level</x-slot:label></x-form.select>
                    </div>
                    <x-form.input label="Nama Posisi" wire:model="position_name" />
                    <x-form.select wire:model="department_id" :options="$this->departments" placeholder="— pilih departemen —"><x-slot:label>Departemen</x-slot:label></x-form.select>
                    <x-form.select wire:model="reports_to_position_id" :options="$this->reportsToOptions" placeholder="— tidak melapor ke siapa pun (puncak) —">
                        <x-slot:label>Melapor Ke</x-slot:label>
                    </x-form.select>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="$set('showModal', false)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">Batal</button>
                    <button type="button" wire:click="save" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
