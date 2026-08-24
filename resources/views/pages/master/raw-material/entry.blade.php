<?php

use App\Models\Acc\FinCostCenter;
use App\Models\Acc\FinProfitCenter;
use App\Models\Master\DocRmprefix1;
use App\Models\Master\DocRmprefix2;
use App\Models\Master\DocRmprefix3;
use App\Models\Master\DocRmprefix4;
use App\Models\Master\DocRmprefix5;
use App\Models\Master\MRawMaterial;
use App\Models\Master\UomUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Master Item Bahan Baku — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $materialId = null;

    public string $activeTab = 'category';

    // header
    public string $material_name = '';

    public string $material_code = '';

    public string $item_type = 'MATERIAL';

    public bool $purchasable = true;

    // tab 1 - kategori
    public ?string $pick1 = null;

    public ?string $pick2 = null;

    public ?string $pick3 = null;

    public ?string $pick4 = null;

    public ?string $pick5 = null;

    public string $hs_code = '';

    public string $old_item_code = '';

    public string $old_item_name = '';

    // tab 2 - spesifikasi & QC
    public ?float $gram_weight = null;

    public ?float $cm_length = null;

    public ?float $cm_width = null;

    public ?float $mm_thickness = null;

    public string $brand = '';

    public string $color = '';

    public bool $qc_required = false;

    public ?int $shelf_life_days = null;

    public ?float $moisture_pct = null;

    // tab 3 - uom & pengadaan
    public string $uom_stock = '';

    public string $uom_purchase = '';

    public string $uom_usage = '';

    public float $conversion_to_stock = 1;

    public ?float $conversion_to_usage = null;

    public ?int $lead_time_days = null;

    public ?float $tol_purchase = null;

    public string $purchase_tax_code = 'PPN';

    public ?int $suggest_supplier_id = null;

    public string $costing_method = 'AVG';

    public bool $is_fx_sensitive = false;

    // tab 4 - stok & lokasi
    public ?float $min_stock = null;

    public ?float $safety_stock = null;

    public ?float $reorder_point = null;

    public ?float $max_stock = null;

    public string $def_location_id = '';

    // tab 5 - akuntansi
    public string $valuation_class = '';

    public ?int $stock_asset_account_id = null;

    public ?int $cogs_account_id = null;

    public ?int $gr_ir_account_id = null;

    public ?int $cost_center_id = null;

    public ?int $profit_center_id = null;

    public function mount(?int $material = null): void
    {
        if (! $material) {
            return;
        }

        $this->materialId = $material;
        $m = MRawMaterial::findOrFail($material);

        foreach ([
            'material_name', 'material_code', 'item_type', 'purchasable',
            'hs_code', 'old_item_code', 'old_item_name',
            'gram_weight', 'cm_length', 'cm_width', 'mm_thickness', 'brand', 'color',
            'qc_required', 'shelf_life_days', 'moisture_pct',
            'uom_stock', 'uom_purchase', 'uom_usage', 'conversion_to_stock', 'conversion_to_usage',
            'lead_time_days', 'tol_purchase', 'purchase_tax_code', 'suggest_supplier_id',
            'costing_method', 'is_fx_sensitive',
            'min_stock', 'safety_stock', 'reorder_point', 'max_stock', 'def_location_id',
            'valuation_class', 'stock_asset_account_id', 'cogs_account_id', 'gr_ir_account_id',
            'cost_center_id', 'profit_center_id',
        ] as $field) {
            if ($m->{$field} !== null) {
                $this->{$field} = $m->{$field};
            }
        }

        $this->pick1 = $m->category1;
        $this->pick2 = $m->category2;
        $this->pick3 = $m->category3;
        $this->pick4 = $m->category4;
        $this->pick5 = $m->category5;
    }

    #[Computed]
    public function isEdit(): bool
    {
        return (bool) $this->materialId;
    }

    #[Computed]
    public function currentStock(): array
    {
        if (! $this->isEdit) {
            return ['cur_stock' => 0, 'avg_unitprice' => 0];
        }

        $m = MRawMaterial::find($this->materialId);

        return ['cur_stock' => (float) $m?->cur_stock, 'avg_unitprice' => (float) $m?->avg_unitprice];
    }

    #[Computed]
    public function cat1Options()
    {
        return DocRmprefix1::orderBy('category1')->get();
    }

    #[Computed]
    public function cat2Options()
    {
        return $this->pick1 ? DocRmprefix2::where('category1', $this->pick1)->orderBy('category2')->get() : collect();
    }

    #[Computed]
    public function cat3Options()
    {
        return ($this->pick1 && $this->pick2)
            ? DocRmprefix3::where('category1', $this->pick1)->where('category2', $this->pick2)->orderBy('category3')->get()
            : collect();
    }

    #[Computed]
    public function cat4Options()
    {
        return ($this->pick1 && $this->pick2 && $this->pick3)
            ? DocRmprefix4::where('category1', $this->pick1)->where('category2', $this->pick2)->where('category3', $this->pick3)->orderBy('category4')->get()
            : collect();
    }

    #[Computed]
    public function cat5Options()
    {
        return ($this->pick1 && $this->pick2 && $this->pick3 && $this->pick4)
            ? DocRmprefix5::where('category1', $this->pick1)->where('category2', $this->pick2)
                ->where('category3', $this->pick3)->where('category4', $this->pick4)->orderBy('category5')->get()
            : collect();
    }

    #[Computed]
    public function uomOptions()
    {
        return UomUnit::where('is_active', true)->orderBy('uom_code')->get();
    }

    #[Computed]
    public function suppliers()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('is_active', 1)->orderBy('supplier_name')->get(['id_supplier', 'supplier_name', 'supplier_code']);
    }

    #[Computed]
    public function coaAccounts()
    {
        return DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_code')->get(['account_id', 'account_code', 'account_name']);
    }

    #[Computed]
    public function costCenters()
    {
        return FinCostCenter::where('is_active', true)->orderBy('cc_code')->get();
    }

    #[Computed]
    public function profitCenters()
    {
        return FinProfitCenter::where('is_active', true)->orderBy('pc_code')->get();
    }

    public function setPick(int $level, ?string $value): void
    {
        $this->{"pick{$level}"} = $value === '' ? null : $value;

        foreach (range($level + 1, 5) as $deeper) {
            $this->{"pick{$deeper}"} = null;
        }

        if (! $this->isEdit) {
            $this->regenerateCode();
        }
    }

    protected function regenerateCode(): void
    {
        if (! $this->pick1) {
            $this->material_code = '';

            return;
        }

        $prefix1 = DocRmprefix1::where('category1', $this->pick1)->value('prefix1') ?? '';
        $prefix2 = $this->pick2 ? (DocRmprefix2::where('category1', $this->pick1)->where('category2', $this->pick2)->value('prefix2') ?? '') : '';
        $prefix3 = $this->pick3 ? (DocRmprefix3::where('category1', $this->pick1)->where('category2', $this->pick2)->where('category3', $this->pick3)->value('prefix3') ?? '') : '';
        $prefix4 = $this->pick4 ? (DocRmprefix4::where('category1', $this->pick1)->where('category2', $this->pick2)->where('category3', $this->pick3)->where('category4', $this->pick4)->value('prefix4') ?? '') : '';
        $prefix5 = $this->pick5 ? (DocRmprefix5::where('category1', $this->pick1)->where('category2', $this->pick2)->where('category3', $this->pick3)->where('category4', $this->pick4)->where('category5', $this->pick5)->value('prefix5') ?? '') : '';

        $base = $prefix1.$prefix2.$prefix3.$prefix4.$prefix5;

        if ($base === '') {
            $this->material_code = '';

            return;
        }

        $code = $base;
        $suffix = 1;

        while (MRawMaterial::where('material_code', $code)->exists()) {
            $suffix++;
            $code = $base.$suffix;
        }

        $this->material_code = $code;
    }

    public function save(): void
    {
        $data = $this->validate([
            'material_name' => ['required', 'string', 'max:200'],
            'material_code' => ['required', 'string', 'max:60', $this->isEdit
                ? 'unique:tm2mst.m_raw_material,material_code,'.$this->materialId.',material_id'
                : 'unique:tm2mst.m_raw_material,material_code'],
            'item_type' => ['required', 'in:MATERIAL,SERVICE,FREE_SAMPLE'],
            'uom_stock' => ['nullable', 'string', 'max:50'],
            'uom_purchase' => ['nullable', 'string', 'max:50'],
        ]);

        $login = Auth::user()->login;

        $payload = [
            'material_name' => $data['material_name'],
            'material_code' => $data['material_code'],
            'item_type' => $data['item_type'],
            'purchasable' => $this->purchasable,
            'category1' => $this->pick1,
            'category2' => $this->pick2,
            'category3' => $this->pick3,
            'category4' => $this->pick4,
            'category5' => $this->pick5,
            'hs_code' => $this->hs_code ?: null,
            'old_item_code' => $this->old_item_code ?: null,
            'old_item_name' => $this->old_item_name ?: null,
            'gram_weight' => $this->gram_weight,
            'cm_length' => $this->cm_length,
            'cm_width' => $this->cm_width,
            'mm_thickness' => $this->mm_thickness,
            'brand' => $this->brand ?: null,
            'color' => $this->color ?: null,
            'qc_required' => $this->qc_required,
            'shelf_life_days' => $this->shelf_life_days,
            'moisture_pct' => $this->moisture_pct,
            'uom_stock' => $this->uom_stock ?: null,
            'uom_purchase' => $this->uom_purchase ?: null,
            'uom_usage' => $this->uom_usage ?: null,
            'conversion_to_stock' => $this->conversion_to_stock ?: 1,
            'conversion_to_usage' => $this->conversion_to_usage,
            'lead_time_days' => $this->lead_time_days,
            'tol_purchase' => $this->tol_purchase,
            'purchase_tax_code' => $this->purchase_tax_code,
            'suggest_supplier_id' => $this->suggest_supplier_id,
            'costing_method' => $this->costing_method,
            'is_fx_sensitive' => $this->is_fx_sensitive,
            'min_stock' => $this->min_stock,
            'safety_stock' => $this->safety_stock,
            'reorder_point' => $this->reorder_point,
            'max_stock' => $this->max_stock,
            'def_location_id' => $this->def_location_id ?: null,
            'valuation_class' => $this->valuation_class ?: null,
            'stock_asset_account_id' => $this->stock_asset_account_id,
            'cogs_account_id' => $this->cogs_account_id,
            'gr_ir_account_id' => $this->gr_ir_account_id,
            'cost_center_id' => $this->cost_center_id,
            'profit_center_id' => $this->profit_center_id,
            'update_by' => $login,
        ];

        if ($this->isEdit) {
            MRawMaterial::whereKey($this->materialId)->update($payload);
        } else {
            $payload['create_by'] = $login;
            $payload['is_active'] = true;
            $new = MRawMaterial::create($payload);
            $this->materialId = $new->material_id;
        }

        $this->redirect(route('master.raw-material.edit', ['material' => $this->materialId]));
    }

    public function delete()
    {
        if (! $this->isEdit) {
            return;
        }

        MRawMaterial::whereKey($this->materialId)->delete();

        return $this->redirect(route('master.raw-material.list'));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📦 {{ $this->isEdit ? 'Edit Item — '.$material_code : 'Item Baru' }}</b>
        <a href="{{ route('master.raw-material.list') }}" title="Kembali ke Daftar Item"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    <div class="max-w-[1300px] mx-auto my-5 px-4">

        {{-- HEADER BAR --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4 grid grid-cols-4 gap-3">
            <div class="col-span-2">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Nama Item</label>
                <input type="text" wire:model="material_name" placeholder="mis. Art Carton 210gr 65x100 GOLDEN" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                @error('material_name') <span class="text-[10.5px] text-[#a3241f]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">🔒 Kode Item</label>
                <input type="text" readonly value="{{ $material_code }}" placeholder="Auto dari hirarki kategori" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted font-mono font-bold">
                @error('material_code') <span class="text-[10.5px] text-[#a3241f]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tipe Item</label>
                <select wire:model="item_type" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="MATERIAL">MATERIAL (Bahan Baku / Stok)</option>
                    <option value="SERVICE">SERVICE (Jasa)</option>
                    <option value="FREE_SAMPLE">FREE_SAMPLE (Contoh dari supplier)</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Dapat Dibeli (PO)</label>
                <select wire:model="purchasable" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="1">Ya</option>
                    <option value="0">Tidak</option>
                </select>
            </div>
        </div>

        {{-- TABS --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
            <div class="flex bg-[#eef1f5] border-b border-[#d7dbe2] flex-wrap">
                @foreach (['category' => '1. Kategori', 'specs' => '2. Spesifikasi & QC', 'uom' => '3. UOM & Pengadaan', 'inventory' => '4. Stok & Lokasi', 'accounting' => '5. Akuntansi (GL)'] as $key => $label)
                    <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                        class="px-4 py-2 text-xs font-semibold border-r border-[#d7dbe2] {{ $activeTab === $key ? 'bg-white text-accent border-b-2 border-b-accent' : 'text-[#4b5568]' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="p-4">
                {{-- TAB 1: KATEGORI --}}
                <div @if ($activeTab !== 'category') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Hirarki Pembentuk Kode Item</p>
                        <div class="grid grid-cols-5 gap-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Kategori</label>
                                <select wire:change="setPick(1, $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs" @disabled($this->isEdit)>
                                    <option value="">— pilih —</option>
                                    @foreach ($this->cat1Options as $o)
                                        <option value="{{ $o->category1 }}" @selected($pick1 === $o->category1)>{{ $o->category1 }} ({{ $o->prefix1 }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Jenis</label>
                                <select wire:change="setPick(2, $event.target.value)" @disabled($this->isEdit || ! $pick1) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->cat2Options as $o)
                                        <option value="{{ $o->category2 }}" @selected($pick2 === $o->category2)>{{ $o->category2 }} ({{ $o->prefix2 }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Gramatur/Spec</label>
                                <select wire:change="setPick(3, $event.target.value)" @disabled($this->isEdit || ! $pick2) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->cat3Options as $o)
                                        <option value="{{ $o->category3 }}" @selected($pick3 === $o->category3)>{{ $o->category3 }} ({{ $o->prefix3 }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Ukuran <span class="normal-case font-normal">(opsional)</span></label>
                                <select wire:change="setPick(4, $event.target.value)" @disabled($this->isEdit || ! $pick3 || $this->cat4Options->isEmpty()) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                    <option value="">— semua —</option>
                                    @foreach ($this->cat4Options as $o)
                                        <option value="{{ $o->category4 }}" @selected($pick4 === $o->category4)>{{ $o->category4 }} ({{ $o->prefix4 }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Merek <span class="normal-case font-normal">(opsional)</span></label>
                                <select wire:change="setPick(5, $event.target.value)" @disabled($this->isEdit || ! $pick4 || $this->cat5Options->isEmpty()) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                    <option value="">— semua —</option>
                                    @foreach ($this->cat5Options as $o)
                                        <option value="{{ $o->category5 }}" @selected($pick5 === $o->category5)>{{ $o->category5 }} ({{ $o->prefix5 }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if ($this->isEdit)
                            <p class="text-[11px] text-muted mt-2">Kategori dikunci setelah item dibuat (kode item mengikuti kombinasi ini) — hubungi Admin bila perlu reklasifikasi.</p>
                        @endif
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Kepabeanan &amp; Kode Legacy</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Kode HS</label>
                                <input type="text" wire:model="hs_code" placeholder="4810.13.00" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Kode Lama</label>
                                <input type="text" wire:model="old_item_code" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Nama Lama</label>
                                <input type="text" wire:model="old_item_name" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TAB 2: SPESIFIKASI & QC --}}
                <div @if ($activeTab !== 'specs') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Dimensi &amp; Fisik <span class="text-muted normal-case font-normal">(kosongkan bila bukan kertas/board)</span></p>
                        <div class="grid grid-cols-3 gap-2">
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Gramatur (gsm)</label><input type="number" wire:model="gram_weight" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Panjang Plano (cm)</label><input type="number" step="0.0001" wire:model="cm_length" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Lebar Plano (cm)</label><input type="number" step="0.0001" wire:model="cm_width" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Ketebalan (mm)</label><input type="number" step="0.0001" wire:model="mm_thickness" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Merek Pabrik</label><input type="text" wire:model="brand" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Warna</label><input type="text" wire:model="color" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                        </div>
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">QC &amp; Masa Simpan</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Wajib QC Saat Terima</label>
                                <select wire:model="qc_required" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="1">Ya — cek lab GSM/kelembaban</option>
                                    <option value="0">Tidak — langsung ke stok</option>
                                </select>
                                <p class="text-[10.5px] text-muted mt-1">Menyalakan gerbang QC kualitas di layar PV.</p>
                            </div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Masa Simpan (hari)</label><input type="number" wire:model="shelf_life_days" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Batas Kelembaban (%)</label><input type="number" step="0.1" wire:model="moisture_pct" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                        </div>
                    </div>
                </div>

                {{-- TAB 3: UOM & PENGADAAN --}}
                <div @if ($activeTab !== 'uom') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Satuan &amp; Konversi</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">UoM Stok</label>
                                <select wire:model="uom_stock" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->uomOptions as $u)
                                        <option value="{{ $u->uom_code }}">{{ $u->uom_code }} — {{ $u->uom_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">UoM Beli (PO)</label>
                                <select wire:model="uom_purchase" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->uomOptions as $u)
                                        <option value="{{ $u->uom_code }}">{{ $u->uom_code }} — {{ $u->uom_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">UoM Pemakaian</label>
                                <select wire:model="uom_usage" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->uomOptions as $u)
                                        <option value="{{ $u->uom_code }}">{{ $u->uom_code }} — {{ $u->uom_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Konversi Beli → Stok</label>
                                <input type="number" step="0.000001" wire:model="conversion_to_stock" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <p class="text-[10.5px] text-muted mt-1">⚠ Nilai AWAL saja — di PV selalu boleh ditimpa manual (barang curah).</p>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Konversi Stok → Pakai</label>
                                <input type="number" step="0.000001" wire:model="conversion_to_usage" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                        </div>
                    </div>

                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Parameter Pengadaan</p>
                        <div class="grid grid-cols-4 gap-2">
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Lead Time (hari)</label><input type="number" wire:model="lead_time_days" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Toleransi Terima (%)</label><input type="number" step="0.1" wire:model="tol_purchase" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Pajak Masukan</label>
                                <select wire:model="purchase_tax_code" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="PPN">PPN (sys_constant)</option>
                                    <option value="NON_PPN">Non-PPN</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Supplier Saran</label>
                                <select wire:model="suggest_supplier_id" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->suppliers as $s)
                                        <option value="{{ $s->id_supplier }}">{{ $s->supplier_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Metode Harga Perolehan</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Costing Method</label>
                                <select wire:model="costing_method" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="AVG">AVG — Moving Average</option>
                                    <option value="FIFO">FIFO</option>
                                    <option value="LIFO">LIFO</option>
                                    <option value="STANDARD">STANDARD — harga tetap</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Sensitif Kurs</label>
                                <select wire:model="is_fx_sensitive" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="0">Tidak</option>
                                    <option value="1">Ya</option>
                                </select>
                                @if ($is_fx_sensitive)
                                    <p class="text-[10.5px] text-[#8a5a00] mt-1">⚠ Item ini akan ditandai di layar PR/PQ: "harga mengikuti kurs".</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TAB 4: STOK & LOKASI --}}
                <div @if ($activeTab !== 'inventory') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Batas Persediaan</p>
                        <div class="grid grid-cols-4 gap-2">
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Min Stock</label><input type="number" wire:model="min_stock" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Safety Stock</label><input type="number" wire:model="safety_stock" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Reorder Point</label><input type="number" wire:model="reorder_point" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                            <div><label class="text-[10px] text-muted uppercase font-semibold block mb-1">Max Stock</label><input type="number" wire:model="max_stock" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs"></div>
                        </div>
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Lokasi &amp; Saldo</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Default Lokasi Gudang</label>
                                <input type="text" wire:model="def_location_id" placeholder="belum ada master gudang -- isi bebas" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <p class="text-[10.5px] text-muted mt-1">⚠ Master gudang (m_warehouse) belum dibangun — teks bebas sementara.</p>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">🔒 Stok On-Hand</label>
                                <input type="text" readonly value="{{ number_format($this->currentStock['cur_stock'], 2) }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">🔒 Harga Rata² Bergerak</label>
                                <input type="text" readonly value="{{ number_format($this->currentStock['avg_unitprice'], 2) }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                            </div>
                        </div>
                        <p class="text-[10.5px] text-muted mt-2">Stok &amp; harga rata² dikelola sistem (posting Purchase Receive) — tidak diedit manual di sini.</p>
                    </div>
                </div>

                {{-- TAB 5: AKUNTANSI --}}
                <div @if ($activeTab !== 'accounting') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">Linkage ke Chart of Account <span class="text-muted normal-case font-normal">(langsung ke fin_account, tanpa lapisan penentu-akun)</span></p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Label Klasifikasi <span class="normal-case font-normal">(opsional)</span></label>
                                <input type="text" wire:model="valuation_class" placeholder="mis. Raw Material - Kertas" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <p class="text-[10.5px] text-muted mt-1">Cuma label laporan — bukan penentu akun.</p>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Akun Aset Persediaan</label>
                                <select wire:model="stock_asset_account_id" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— default kategori —</option>
                                    @foreach ($this->coaAccounts as $acc)
                                        <option value="{{ $acc->account_id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Akun HPP / Beban Pakai</label>
                                <select wire:model="cogs_account_id" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— default kategori —</option>
                                    @foreach ($this->coaAccounts as $acc)
                                        <option value="{{ $acc->account_id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Akun Kliring GR/IR</label>
                                <select wire:model="gr_ir_account_id" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— default kategori —</option>
                                    @foreach ($this->coaAccounts as $acc)
                                        <option value="{{ $acc->account_id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[10.5px] text-muted mt-1">Terpakai saat PV posting (barang masuk, faktur belum ada).</p>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Cost Center Default</label>
                                <select wire:model="cost_center_id" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— tak ada default —</option>
                                    @foreach ($this->costCenters as $cc)
                                        <option value="{{ $cc->cc_id }}">{{ $cc->cc_code }} — {{ $cc->cc_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Profit Center</label>
                                <select wire:model="profit_center_id" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                    <option value="">— tak ada default —</option>
                                    @foreach ($this->profitCenters as $pc)
                                        <option value="{{ $pc->pc_id }}">{{ $pc->pc_code }} — {{ $pc->pc_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 my-6">
            @if ($this->isEdit)
                <button type="button" wire:click="delete" wire:confirm="Hapus item ini secara permanen?" class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">🗑 Hapus</button>
            @endif
            <button type="button" wire:click="save" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">💾 Simpan</button>
        </div>
    </div>
</div>
