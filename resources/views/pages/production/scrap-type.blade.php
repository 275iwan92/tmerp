<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Prd\PrdScrapType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Kelola Tipe Scrap -- data pendukung F15 Production Output Recording
// (prd_scrap_type). PPIC/QA kelola sendiri lewat layar ini, bukan hardcode
// di kode -- data awal (migration 2026_08_28_000013) dari kategori umum
// print-industry MIS (Systrade/PrintVis/eProductivity), user akan susulkan
// daftar riil PT Telasih Makmur. Pola grid sama Rule Library.
new #[Title('Kelola Tipe Scrap — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    use HasGridStatFilter;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $category = 'REJECT';

    public string $type_code = '';

    public string $type_name = '';

    #[Computed]
    public function types()
    {
        return $this->applyStatFilter(PrdScrapType::query())->orderBy('category')->orderBy('sort_order')->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return PrdScrapType::count();
    }

    #[Computed]
    public function statReject(): int
    {
        return PrdScrapType::reject()->count();
    }

    #[Computed]
    public function statWaste(): int
    {
        return PrdScrapType::waste()->count();
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'type_code', 'type_name']);
        $this->category = 'REJECT';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $type = PrdScrapType::findOrFail($id);
        $this->editingId = $type->type_id;
        $this->category = $type->category;
        $this->type_code = $type->type_code;
        $this->type_name = $type->type_name;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $this->validate([
            'type_code' => ['required', 'string', 'max:30'],
            'type_name' => ['required', 'string', 'max:100'],
        ]);

        $data = [
            'category' => $this->category,
            'type_code' => strtoupper(str_replace(' ', '_', $this->type_code)),
            'type_name' => $this->type_name,
            'updated_by' => Auth::user()->login,
        ];

        if ($this->editingId) {
            PrdScrapType::whereKey($this->editingId)->update($data);
        } else {
            PrdScrapType::create($data + ['activate' => true, 'sort_order' => 500, 'created_by' => Auth::user()->login]);
        }

        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        $type = PrdScrapType::findOrFail($id);
        $type->update(['activate' => ! $type->activate, 'updated_by' => Auth::user()->login]);
    }

    // Kotak statistik = filter data (griduistandard.md §2b). Reject/Waste =
    // scope model yang sama dgn yang menghitung angkanya.
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => 'Total Tipe', 'value' => $this->statTotal, 'tone' => 'total'],
            'reject' => ['label' => 'Tipe Reject', 'value' => $this->statReject, 'tone' => 'critical',
                'filter' => fn ($q) => $q->reject()],
            'waste' => ['label' => 'Tipe Waste', 'value' => $this->statWaste, 'tone' => 'warn',
                'filter' => fn ($q) => $q->waste()],
        ];
    }
};
?>

<div>
    <div class="max-w-[1000px] mx-auto my-5 px-4">
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <span class="text-[11px] text-muted italic">Dipakai di "Catat Output" (WO Detail) — data awal dari referensi print-industry MIS, sesuaikan dengan istilah PT Telasih Makmur.</span>
                <x-slot:newRecord>
                    <button type="button" wire:click="openNew" class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Kategori</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Kode</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Nama Tipe</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->types as $t)
                            <tr wire:key="scrap-{{ $t->type_id }}">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $t->category === 'REJECT' ? 'bg-[#fbeaea] text-[#a3241f]' : 'bg-[#fffbeb] text-[#92400e]' }}">{{ $t->category }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono text-[11px]">{{ $t->type_code }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-semibold">{{ $t->type_name }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $t->activate ? 'bg-[#dcfce7] text-[#15803d]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $t->activate ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit" action="openEdit({{ $t->type_id }})" />
                                    <button type="button" wire:click="toggleActive({{ $t->type_id }})" class="w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer" title="{{ $t->activate ? 'Nonaktifkan' : 'Aktifkan' }}">⏻</button>
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="Belum ada tipe scrap." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeModal">
            <div class="bg-white rounded-xl w-[420px] max-w-[95vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>{{ $editingId ? 'Edit Tipe Scrap' : 'Tipe Scrap Baru' }}</span>
                    <button type="button" wire:click="closeModal" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Kategori</label>
                        <select wire:model="category" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            <option value="REJECT">Reject (barang jadi cacat)</option>
                            <option value="WASTE">Waste (bahan/proses terbuang)</option>
                        </select>
                    </div>
                    <x-form.input label="Kode" name="type_code" wire:model="type_code" placeholder="mis. COLOR_OFF" class="font-mono" />
                    <x-form.input label="Nama Tipe" name="type_name" wire:model="type_name" placeholder="mis. Warna Tidak Sesuai" />
                    <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="button" wire:click="save" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
