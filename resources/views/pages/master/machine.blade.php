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

    public string $panel = 'grid'; // grid | form

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

    public bool $machine_activate = true;

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
        return OrgDivision::query()->where('activate', true)->orderBy('division_name')->get();
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
            $this->machine_activate = (bool) $m->activate;
        } else {
            $this->reset([
                'machine_code', 'machine_name', 'machine_type', 'division_id',
                'brand_model', 'year_acquired', 'serial_no', 'max_paper_size',
                'color_count', 'max_speed_per_hour', 'capacity_per_shift',
                'gsm_min', 'gsm_max', 'notes',
            ]);
            $this->machine_activate = true;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
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
            'machine_activate' => ['boolean'],
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
            'activate' => $data['machine_activate'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            ProdMachine::whereKey($this->editingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            ProdMachine::create($payload);
        }

        unset($this->machines);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $m = ProdMachine::findOrFail($id);
        $m->activate = ! $m->activate;
        $m->updated_by = Auth::user()->login;
        $m->save();

        unset($this->machines, $this->statTotal, $this->statActive, $this->statInactive);
    }

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    #[Computed]
    public function statTotal(): int
    {
        return ProdMachine::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return ProdMachine::where('activate', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return ProdMachine::where('activate', false)->count();
    }

    #[Computed]
    public function statDivisions(): int
    {
        return ProdMachine::where('activate', true)->distinct('division_id')->count('division_id');
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" label="Total Mesin" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Aktif" :value="$this->statActive" />
            <x-grid.stat-box tone="extra" label="Divisi Terpakai" :value="$this->statDivisions" />
            <x-grid.stat-box tone="critical" label="Nonaktif" :value="$this->statInactive" />
        </x-grid.stat-row>

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">
            Hanya <b>Offset Printing</b> yang menggunakan assignment mesin presisi (banyak lini). Digital Printing &amp; Indostry tidak butuh entri di sini — pakai grid shift sederhana tanpa mesin.
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama…"
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
                                <x-grid.identity-cell :id="$m->machine_id" :name="$m->machine_name" :code="$m->machine_code" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $m->machine_type ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $m->brand_model ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $m->activate ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $m->activate ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="✎" title="Edit" action="openModal('edit', {{ $m->machine_id }})" />
                                    <x-grid.action-icon icon="⏻" :title="$m->activate ? 'Nonaktifkan' : 'Aktifkan'" action="toggleActive({{ $m->machine_id }})"
                                        :confirm="($m->activate ? 'Nonaktifkan' : 'Aktifkan').' mesin '.$m->machine_name.'?'"
                                        :variant="$m->activate ? 'danger' : 'default'" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="Tidak ada." />
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
                    {{ $mode === 'edit' ? 'Edit Mesin' : 'Mesin Baru' }}
                    @if ($mode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $machine_code }}</span>@endif
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Identitas</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input label="Kode Mesin *" name="machine_code" wire:model="machine_code" placeholder="mis. CETAK-01" class="uppercase" />
                        </div>
                        <div class="flex-[2]">
                            <x-form.input label="Nama Mesin *" name="machine_name" wire:model="machine_name" placeholder="mis. Cetak 1" />
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select label="Jenis Mesin" wire:model="machine_type" :options="array_combine($machineTypes, $machineTypes)" />
                        </div>
                        <div class="flex-1">
                            <x-form.select label="Divisi (SBU) *" name="division_id" wire:model="division_id"
                                :options="$this->divisionOptions->pluck('division_name', 'division_id')" :selected="$division_id" />
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Aset</div>
                    <div class="flex gap-2.5">
                        <div class="flex-[2]">
                            <x-form.input label="Merk / Model" wire:model="brand_model" placeholder="mis. Heidelberg SM 74" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="Tahun" name="year_acquired" wire:model="year_acquired" placeholder="2015" />
                        </div>
                    </div>
                    <x-form.input label="Nomor Seri Aset" wire:model="serial_no" placeholder="mis. SM74-2015-0231" />

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Spesifikasi Teknis</div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input label="Max Ukuran Kertas" wire:model="max_paper_size" placeholder="52 × 74 cm" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="Jumlah Warna / Unit" name="color_count" wire:model="color_count" placeholder="4" />
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input label="Kecepatan Max (sheet/jam)" name="max_speed_per_hour" wire:model="max_speed_per_hour" placeholder="15000" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="Kapasitas per Shift" wire:model="capacity_per_shift" placeholder="± 100.000 sheet" />
                        </div>
                    </div>
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input label="Gramatur Min (gsm)" name="gsm_min" wire:model="gsm_min" placeholder="60" />
                        </div>
                        <div class="flex-1">
                            <x-form.input label="Gramatur Max (gsm)" name="gsm_max" wire:model="gsm_max" placeholder="350" />
                        </div>
                    </div>
                    <x-form.textarea label="Catatan" wire:model="notes" rows="2" placeholder="Catatan tambahan (mis. keterbatasan, perawatan khusus)" />
                    <x-form.checkbox label="Aktif" wire:model="machine_activate" />

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                    </div>
            </form>
        @endif
    </div>
</div>
