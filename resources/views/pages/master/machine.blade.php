<?php

use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// mockup_master_produksi.html #page-machine. Master mesin/lini produksi ->
// tm2mst.prod_machine (master data terpusat, CLAUDE.md §2). division_id FK ke
// org_division (TIER_3A_HRM.sql), menggantikan division_sbu teks bebas. Toggle
// aktif/nonaktif dipakai alih-alih hard delete (pola sudah dipakai
// security/index.blade.php & master/uom.blade.php) — mesin bisa jadi target
// FK logis prod_part_process.machine_id, jangan sampai orphan.
new #[Title('Master Mesin — TELASIH MAKMUR ERP')] class extends Component
{
    public array $machineTypes = ['Cetak', 'Coating', 'Laminasi', 'Potong', 'Lainnya'];

    public string $search = '';

    public bool $showModal = false;

    public string $mode = 'new'; // new | edit

    public ?int $editingId = null;

    public string $machine_code = '';

    public string $machine_name = '';

    public string $machine_type = '';

    public ?int $division_id = null;

    public string $brand_model = '';

    public string $year_acquired = '';

    public string $serial_no = '';

    public string $max_paper_size = '';

    public string $color_count = '';

    public string $max_speed_per_hour = '';

    public string $capacity_per_shift = '';

    public string $gsm_min = '';

    public string $gsm_max = '';

    public string $notes = '';

    public bool $machine_is_active = true;

    #[Computed]
    public function machines()
    {
        return ProdMachine::query()
            ->with('division')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('machine_code', 'like', "%{$this->search}%")
                ->orWhere('machine_name', 'like', "%{$this->search}%")))
            ->orderBy('division_id')->orderBy('machine_code')
            ->get();
    }

    #[Computed]
    public function divisionOptions()
    {
        return OrgDivision::query()->where('is_active', true)->orderBy('division_name')->get();
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;

        if ($this->mode === 'edit' && $id) {
            $m = ProdMachine::findOrFail($id);
            $this->machine_code = $m->machine_code;
            $this->machine_name = $m->machine_name;
            $this->machine_type = (string) $m->machine_type;
            $this->division_id = $m->division_id;
            $this->brand_model = (string) $m->brand_model;
            $this->year_acquired = (string) ($m->year_acquired ?? '');
            $this->serial_no = (string) $m->serial_no;
            $this->max_paper_size = (string) $m->max_paper_size;
            $this->color_count = (string) ($m->color_count ?? '');
            $this->max_speed_per_hour = (string) ($m->max_speed_per_hour ?? '');
            $this->capacity_per_shift = (string) $m->capacity_per_shift;
            $this->gsm_min = (string) ($m->gsm_min ?? '');
            $this->gsm_max = (string) ($m->gsm_max ?? '');
            $this->notes = (string) $m->notes;
            $this->machine_is_active = (bool) $m->is_active;
        } else {
            $this->reset([
                'machine_code', 'machine_name', 'machine_type', 'division_id',
                'brand_model', 'year_acquired', 'serial_no', 'max_paper_size',
                'color_count', 'max_speed_per_hour', 'capacity_per_shift',
                'gsm_min', 'gsm_max', 'notes',
            ]);
            $this->machine_is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $unique = 'unique:tm2mst.prod_machine,machine_code';
        if ($this->mode === 'edit' && $this->editingId) {
            $unique .= ','.$this->editingId.',machine_id';
        }

        $data = $this->validate([
            'machine_code' => ['required', 'string', 'max:20', $unique],
            'machine_name' => ['required', 'string', 'max:100'],
            'machine_type' => ['nullable', 'string', 'in:'.implode(',', $this->machineTypes)],
            'division_id' => ['required', 'integer', 'exists:tm2mst.org_division,division_id'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'year_acquired' => ['nullable', 'integer', 'digits:4'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'max_paper_size' => ['nullable', 'string', 'max:50'],
            'color_count' => ['nullable', 'integer', 'min:0', 'max:255'],
            'max_speed_per_hour' => ['nullable', 'integer', 'min:0'],
            'capacity_per_shift' => ['nullable', 'string', 'max:50'],
            'gsm_min' => ['nullable', 'integer', 'min:0'],
            'gsm_max' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'machine_is_active' => ['boolean'],
        ]);

        $payload = [
            'machine_code' => strtoupper(trim($data['machine_code'])),
            'machine_name' => $data['machine_name'],
            'machine_type' => $data['machine_type'] ?: null,
            'division_id' => $data['division_id'],
            'brand_model' => $data['brand_model'] ?: null,
            'year_acquired' => $data['year_acquired'] !== null && $data['year_acquired'] !== '' ? $data['year_acquired'] : null,
            'serial_no' => $data['serial_no'] ?: null,
            'max_paper_size' => $data['max_paper_size'] ?: null,
            'color_count' => $data['color_count'] !== null && $data['color_count'] !== '' ? $data['color_count'] : null,
            'max_speed_per_hour' => $data['max_speed_per_hour'] !== null && $data['max_speed_per_hour'] !== '' ? $data['max_speed_per_hour'] : null,
            'capacity_per_shift' => $data['capacity_per_shift'] ?: null,
            'gsm_min' => $data['gsm_min'] !== null && $data['gsm_min'] !== '' ? $data['gsm_min'] : null,
            'gsm_max' => $data['gsm_max'] !== null && $data['gsm_max'] !== '' ? $data['gsm_max'] : null,
            'notes' => $data['notes'] ?: null,
            'is_active' => $data['machine_is_active'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            ProdMachine::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            ProdMachine::create($payload);
        }

        unset($this->machines);
        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        $m = ProdMachine::findOrFail($id);
        $m->is_active = ! $m->is_active;
        $m->updated_by = Auth::user()->login;
        $m->save();

        unset($this->machines, $this->statTotal, $this->statActive, $this->statInactive);
    }

    // GRID_UI_STANDARD.md #3 -- avatar inisial+warna utk kolom identitas.
    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }

    #[Computed]
    public function statTotal(): int
    {
        return ProdMachine::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdMachine::where('is_active', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdMachine::where('is_active', false)->count();
    }

    #[Computed]
    public function statDivisions(): int
    {
        return ProdMachine::where('is_active', true)->distinct('division_id')->count('division_id');
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Mesin</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Aktif</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statActive }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Divisi Terpakai</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->statDivisions }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Nonaktif</p>
                <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->statInactive }}</p>
            </div>
        </div>

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">
            Hanya <b>Offset Printing</b> yang menggunakan assignment mesin presisi (banyak lini). Digital Printing &amp; Indostry tidak butuh entri di sini — pakai grid shift sederhana tanpa mesin.
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <button type="button" wire:click="openModal('new')"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Mesin</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Jenis</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Merk/Model</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->machines as $m)
                            <tr wire:key="machine-{{ $m->machine_id }}" wire:dblclick="openModal('edit', {{ $m->machine_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($m->machine_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($m->machine_name) }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $m->machine_name }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $m->machine_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $m->machine_type ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $m->brand_model ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $m->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $m->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openModal('edit', {{ $m->machine_id }})" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                    <button type="button" wire:click.stop="toggleActive({{ $m->machine_id }})" wire:confirm="{{ $m->is_active ? 'Nonaktifkan' : 'Aktifkan' }} mesin {{ $m->machine_name }}?"
                                        title="{{ $m->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $m->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-8 text-muted text-sm">Tidak ada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══ MODAL: MESIN ══ --}}
    @if ($showModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeModal">
            <div class="bg-white rounded-xl w-[560px] max-w-[92vw] max-h-[92vh] overflow-y-auto shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2 sticky top-0">
                    <span>{{ $mode === 'edit' ? 'Edit Mesin · '.$machine_code : 'Tambah Mesin Baru' }}</span>
                    <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeModal">✕</span>
                </div>
                <form wire:submit="save" class="p-4 flex flex-col gap-3">
                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Identitas</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kode Mesin *</label>
                            <input type="text" wire:model="machine_code" placeholder="mis. CETAK-01" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] uppercase">
                            @error('machine_code') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-[2] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Nama Mesin *</label>
                            <input type="text" wire:model="machine_name" placeholder="mis. Cetak 1" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('machine_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Jenis Mesin</label>
                            <select wire:model="machine_type" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($machineTypes as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Divisi (SBU) *</label>
                            <select wire:model="division_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->divisionOptions as $d)
                                    <option value="{{ $d->division_id }}">{{ $d->division_name }}</option>
                                @endforeach
                            </select>
                            @error('division_id') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Aset</div>
                    <div class="flex gap-2.5">
                        <div class="flex-[2] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Merk / Model</label>
                            <input type="text" wire:model="brand_model" placeholder="mis. Heidelberg SM 74" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tahun</label>
                            <input type="text" wire:model="year_acquired" placeholder="2015" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('year_acquired') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Nomor Seri Aset</label>
                        <input type="text" wire:model="serial_no" placeholder="mis. SM74-2015-0231" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Spesifikasi Teknis</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Max Ukuran Kertas</label>
                            <input type="text" wire:model="max_paper_size" placeholder="52 × 74 cm" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Jumlah Warna / Unit</label>
                            <input type="text" wire:model="color_count" placeholder="4" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('color_count') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kecepatan Max (sheet/jam)</label>
                            <input type="text" wire:model="max_speed_per_hour" placeholder="15000" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('max_speed_per_hour') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kapasitas per Shift</label>
                            <input type="text" wire:model="capacity_per_shift" placeholder="± 100.000 sheet" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Gramatur Min (gsm)</label>
                            <input type="text" wire:model="gsm_min" placeholder="60" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('gsm_min') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Gramatur Max (gsm)</label>
                            <input type="text" wire:model="gsm_max" placeholder="350" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('gsm_max') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Catatan</label>
                        <textarea wire:model="notes" rows="2" placeholder="Catatan tambahan (mis. keterbatasan, perawatan khusus)" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="machine_is_active"> Aktif
                    </label>

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
