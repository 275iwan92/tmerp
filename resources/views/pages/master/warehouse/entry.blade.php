<?php

use App\Models\Master\WhWarehouse;
use App\Models\Master\WhLoc;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Form Gudang — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $warehouseId = null;

    public bool $isEdit = false;

    public string $warehouse_code = '';

    public string $warehouse_name = '';

    public string $warehouse_type = 'RM';

    public bool $activate = true;

    // Baris bin baru (ditambah lewat mini-form, bukan wire:model per kolom --
    // sama pola dgn addCoating() di inquiry/costing.blade.php).
    public string $newRow = '';

    public string $newCol = '';

    public string $newStack = '';

    public ?float $newCapacity = null;

    public string $formError = '';

    public function mount(?int $warehouse = null): void
    {
        if ($warehouse) {
            $wh = WhWarehouse::findOrFail($warehouse);
            $this->warehouseId = $wh->id_warehouse;
            $this->isEdit = true;
            $this->warehouse_code = $wh->warehouse_code;
            $this->warehouse_name = $wh->warehouse_name;
            $this->warehouse_type = $wh->warehouse_type;
            $this->activate = $wh->activate;
        }
    }

    #[Computed]
    public function locations()
    {
        if (! $this->warehouseId) {
            return collect();
        }

        return WhLoc::where('warehouse_id', $this->warehouseId)
            ->orderBy('row_code')->orderBy('col_code')->orderBy('stack_code')->get();
    }

    public function save(): void
    {
        $this->formError = '';

        $this->validate([
            'warehouse_code' => 'required|string|max:20|unique:tm2mst.wh_warehouse,warehouse_code,'.($this->warehouseId ?: 'NULL').',id_warehouse',
            'warehouse_name' => 'required|string|max:100',
            'warehouse_type' => 'required|in:RM,SFG,FG,MIX,WASTE,TRANSIT',
        ]);

        $login = Auth::user()->login;

        if ($this->isEdit) {
            WhWarehouse::whereKey($this->warehouseId)->update([
                'warehouse_code' => $this->warehouse_code,
                'warehouse_name' => $this->warehouse_name,
                'warehouse_type' => $this->warehouse_type,
                'activate' => $this->activate,
                'updated_by' => $login,
            ]);
        } else {
            $wh = WhWarehouse::create([
                'warehouse_code' => $this->warehouse_code,
                'warehouse_name' => $this->warehouse_name,
                'warehouse_type' => $this->warehouse_type,
                'activate' => $this->activate,
                'created_by' => $login,
                'updated_by' => $login,
            ]);

            $this->redirect(route('master.warehouse.edit', ['warehouse' => $wh->id_warehouse]));

            return;
        }

        session()->flash('saved', 'Gudang tersimpan.');
    }

    public function addLocation(): void
    {
        $this->formError = '';

        if (! $this->isEdit) {
            $this->formError = 'Simpan data gudang dulu sebelum menambah lokasi/bin.';

            return;
        }

        if ($this->newRow === '' || $this->newCol === '' || $this->newStack === '') {
            $this->formError = 'Row, Column, dan Stack wajib diisi.';

            return;
        }

        $binCode = "{$this->warehouse_code}-{$this->newRow}-{$this->newCol}-{$this->newStack}";

        if (WhLoc::where('location_code', $binCode)->exists()) {
            $this->formError = "Bin {$binCode} sudah ada.";

            return;
        }

        $isWaste = $this->warehouse_type === 'WASTE';

        WhLoc::create([
            'warehouse_id' => $this->warehouseId,
            'row_code' => $this->newRow,
            'col_code' => $this->newCol,
            'stack_code' => $this->newStack,
            'location_code' => $binCode,
            'location_type' => $isWaste ? 'WASTE' : 'STORAGE',
            'is_usable' => ! $isWaste,
            'capacity_kg' => $this->newCapacity,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        $this->reset(['newRow', 'newCol', 'newStack', 'newCapacity']);
        unset($this->locations);
    }

    public function removeLocation(int $locationId): void
    {
        WhLoc::where('warehouse_id', $this->warehouseId)->whereKey($locationId)->delete();
        unset($this->locations);
    }
};
?>
<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🏬 {{ $isEdit ? 'Edit Gudang: '.$warehouse_code : 'Gudang Baru' }}</b>
        @if ($isEdit)
            <a href="{{ route('master.warehouse.print-labels', ['warehouse' => $warehouseId]) }}" target="_blank"
                class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">🏷 Cetak Label Rak</a>
        @endif
        <x-back-button :fallback="route('master.warehouse.list')" title="Kembali ke Daftar Gudang" class="ml-auto" />
    </header>

    <div class="max-w-[900px] mx-auto my-5 px-4">

        <x-alert.banner type="success" :message="session('saved')" />
        <x-alert.banner type="danger" :message="$formError" />

        {{-- ============ IDENTITAS ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">1. Identitas & Klasifikasi</h3>
            <div class="grid two mb-3">
                <x-form.input label="Kode Gudang" wire:model="warehouse_code" placeholder="WH-RAW-01" class="font-mono" name="warehouse_code" />
                <x-form.input label="Nama Gudang" wire:model="warehouse_name" placeholder="Gudang Bahan Baku Utama" name="warehouse_name" />
            </div>
            <div class="flex flex-col gap-1 mb-3">
                <label class="text-[10px] text-muted uppercase font-semibold">Tipe Gudang</label>
                <div class="flex gap-4">
                    @foreach (['RM' => 'Bahan Baku', 'SFG' => 'Semi Finished Goods', 'FG' => 'Barang Jadi', 'MIX' => 'Mix (Transit/Buffer)', 'WASTE' => 'Afkir / Waste', 'TRANSIT' => 'Transit'] as $val => $label)
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" wire:model="warehouse_type" value="{{ $val }}"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-3">
                <x-form.checkbox wire:model="activate" label="Aktif" />
            </div>
            <button type="button" wire:click="save" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">💾 Simpan Gudang</button>
        </div>

        {{-- ============ LOKASI / BIN ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden {{ ! $isEdit ? 'opacity-50 pointer-events-none' : '' }}">
            <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">2. Lokasi / Storage Bin</h3>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Row</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Col</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Stack</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Bin Code</th>
                        <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Kapasitas (Kg)</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->locations as $loc)
                        <tr class="border-b border-[#eef1f5]" wire:key="loc-{{ $loc->location_id }}">
                            <td class="px-3 py-2">{{ $loc->row_code }}</td>
                            <td class="px-3 py-2">{{ $loc->col_code }}</td>
                            <td class="px-3 py-2">{{ $loc->stack_code }}</td>
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $loc->location_code }}</td>
                            <td class="px-3 py-2 text-right">{{ $loc->capacity_kg ? number_format((float) $loc->capacity_kg, 0, ',', '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="removeLocation({{ $loc->location_id }})" wire:confirm="Hapus lokasi {{ $loc->location_code }}?" class="text-red-500 text-xs cursor-pointer">✕ Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-6 text-muted">Belum ada lokasi/bin.</td></tr>
                    @endforelse
                    <tr class="bg-[#fbfcfe]">
                        <td class="px-3 py-2"><input type="text" wire:model="newRow" placeholder="R01" class="w-full px-2 py-1 border border-dashed border-[#9cc0f0] rounded text-xs"></td>
                        <td class="px-3 py-2"><input type="text" wire:model="newCol" placeholder="C01" class="w-full px-2 py-1 border border-dashed border-[#9cc0f0] rounded text-xs"></td>
                        <td class="px-3 py-2"><input type="text" wire:model="newStack" placeholder="S01" class="w-full px-2 py-1 border border-dashed border-[#9cc0f0] rounded text-xs"></td>
                        <td class="px-3 py-2 text-[10.5px] text-muted italic">auto</td>
                        <td class="px-3 py-2"><input type="number" wire:model="newCapacity" placeholder="2000" class="w-full px-2 py-1 border border-dashed border-[#9cc0f0] rounded text-xs text-right"></td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="addLocation" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Tambah</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            @if (! $isEdit)
                <p class="text-[10.5px] text-muted italic px-4 py-2">Simpan data gudang dulu sebelum menambah lokasi/bin.</p>
            @endif
        </div>
    </div>
</div>
