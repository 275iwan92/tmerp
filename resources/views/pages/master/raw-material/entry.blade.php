<?php

use App\Models\Acc\FinCostCenter;
use App\Models\Acc\FinProfitCenter;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
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

    // tab 1 - kategori (pick = id_category dari mat_category, per level)
    public ?int $pick1 = null;

    public ?int $pick2 = null;

    public ?int $pick3 = null;

    public ?int $pick4 = null;

    public ?int $pick5 = null;

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

    // Confirm workflow (2026-08-26) -- kategori & material_code cuma
    // terkunci setelah stat_confirm=true, bukan lagi begitu record pertama
    // kali disimpan. Confirm mewajibkan 3 akun (stock_asset/cogs/gr_ir) terisi.
    public bool $stat_confirm = false;

    public string $confirmedAt = '';

    public string $confirmedBy = '';

    public function mount(?int $material = null): void
    {
        if (! $material) {
            return;
        }

        $this->materialId = $material;
        $m = MatRaw::findOrFail($material);

        $this->stat_confirm = (bool) $m->stat_confirm;
        $this->confirmedAt = $m->confirmed_at?->translatedFormat('d M Y H:i') ?? '';
        $this->confirmedBy = (string) $m->confirmed_by;

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

        // Kategori dikunci setelah item dibuat (dropdown disabled) -- resolve
        // id_category dari teks tersimpan cuma buat tampilan "terpilih".
        $node = $m->category1 ? MatCategory::level(1)->where('category_name', $m->category1)->first() : null;
        $this->pick1 = $node?->id_category;

        $node = ($node && $m->category2) ? MatCategory::level(2)->where('parent_id', $node->id_category)->where('category_name', $m->category2)->first() : null;
        $this->pick2 = $node?->id_category;

        $node = ($node && $m->category3) ? MatCategory::level(3)->where('parent_id', $node->id_category)->where('category_name', $m->category3)->first() : null;
        $this->pick3 = $node?->id_category;

        $node = ($node && $m->category4) ? MatCategory::level(4)->where('parent_id', $node->id_category)->where('category_name', $m->category4)->first() : null;
        $this->pick4 = $node?->id_category;

        $node = ($node && $m->category5) ? MatCategory::level(5)->where('parent_id', $node->id_category)->where('category_name', $m->category5)->first() : null;
        $this->pick5 = $node?->id_category;
    }

    #[Computed]
    public function isEdit(): bool
    {
        return (bool) $this->materialId;
    }

    // Kunci kategori/kode cuma kalau sudah Confirm -- item Draft yang sudah
    // pernah disimpan TETAP boleh diubah kategorinya.
    #[Computed]
    public function isLocked(): bool
    {
        return $this->stat_confirm;
    }

    #[Computed]
    public function currentStock(): array
    {
        if (! $this->isEdit) {
            return ['cur_stock' => 0, 'avg_unitprice' => 0];
        }

        $m = MatRaw::find($this->materialId);

        return ['cur_stock' => (float) $m?->cur_stock, 'avg_unitprice' => (float) $m?->avg_unitprice];
    }

    #[Computed]
    public function cat1Options()
    {
        return MatCategory::level(1)->where('activate', true)->orderBy('category_name')->get();
    }

    #[Computed]
    public function cat2Options()
    {
        return $this->pick1 ? MatCategory::level(2)->where('parent_id', $this->pick1)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    #[Computed]
    public function cat3Options()
    {
        return $this->pick2 ? MatCategory::level(3)->where('parent_id', $this->pick2)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    #[Computed]
    public function cat4Options()
    {
        return $this->pick3 ? MatCategory::level(4)->where('parent_id', $this->pick3)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    #[Computed]
    public function cat5Options()
    {
        return $this->pick4 ? MatCategory::level(5)->where('parent_id', $this->pick4)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    #[Computed]
    public function uomOptions()
    {
        return UomUnit::where('activate', true)->orderBy('uom_code')->get();
    }

    #[Computed]
    public function suppliers()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('activate', 1)->orderBy('supplier_name')->get(['id_supplier', 'supplier_name', 'supplier_code']);
    }

    #[Computed]
    public function coaAccounts()
    {
        return DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_code')->get(['account_id', 'account_code', 'account_name']);
    }

    #[Computed]
    public function costCenters()
    {
        return FinCostCenter::where('activate', true)->orderBy('cc_code')->get();
    }

    #[Computed]
    public function profitCenters()
    {
        return FinProfitCenter::where('activate', true)->orderBy('pc_code')->get();
    }

    public function setPick(int $level, ?string $value): void
    {
        $this->{"pick{$level}"} = $value === '' ? null : (int) $value;

        foreach (range($level + 1, 5) as $deeper) {
            $this->{"pick{$deeper}"} = null;
        }

        if (! $this->isLocked) {
            $this->regenerateCode();
        }
    }

    protected function regenerateCode(): void
    {
        if (! $this->pick1) {
            $this->material_code = '';

            return;
        }

        $ids = array_filter([$this->pick1, $this->pick2, $this->pick3, $this->pick4, $this->pick5]);
        $base = MatCategory::whereIn('id_category', $ids)->orderBy('level')->pluck('prefix_char')->implode('');

        if ($base === '') {
            $this->material_code = '';

            return;
        }

        $code = $base;
        $suffix = 1;

        while (MatRaw::where('material_code', $code)->exists()) {
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
                ? 'unique:tm2mst.mat_raw,material_code,'.$this->materialId.',material_id'
                : 'unique:tm2mst.mat_raw,material_code'],
            'item_type' => ['required', 'in:MATERIAL,SERVICE,FREE_SAMPLE'],
            'uom_stock' => ['nullable', 'string', 'max:50'],
            'uom_purchase' => ['nullable', 'string', 'max:50'],
        ]);

        $login = Auth::user()->login;

        $names = MatCategory::whereIn('id_category', array_filter([$this->pick1, $this->pick2, $this->pick3, $this->pick4, $this->pick5]))
            ->pluck('category_name', 'level');

        $payload = [
            'material_name' => $data['material_name'],
            'material_code' => $data['material_code'],
            'item_type' => $data['item_type'],
            'purchasable' => $this->purchasable,
            'category1' => $names[1] ?? null,
            'category2' => $names[2] ?? null,
            'category3' => $names[3] ?? null,
            'category4' => $names[4] ?? null,
            'category5' => $names[5] ?? null,
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
            MatRaw::whereKey($this->materialId)->update($payload);
        } else {
            $payload['create_by'] = $login;
            $payload['activate'] = true;
            $new = MatRaw::create($payload);
            $this->materialId = $new->material_id;
        }

        $this->redirect(route('master.raw-material.edit', ['material' => $this->materialId]));
    }

    // Confirm = syarat 3 akun terisi (form ini, bukan DB constraint) --
    // begitu confirm, kategori & material_code terkunci permanen dan item
    // baru boleh muncul di picker PR/PQ/PRT (lihat masing-masing entry.blade.php).
    public function confirm(): void
    {
        if (! $this->isEdit || $this->stat_confirm) {
            return;
        }

        if (! $this->stock_asset_account_id || ! $this->cogs_account_id || ! $this->gr_ir_account_id) {
            $this->addError('confirm', t('Fill in all three Accounts (Inventory Asset, COGS, GR/IR) on the Accounting tab before Confirm.'));
            $this->activeTab = 'accounting';

            return;
        }

        $login = Auth::user()->login;

        MatRaw::whereKey($this->materialId)->update([
            'stat_confirm' => true,
            'confirmed_at' => now(),
            'confirmed_by' => $login,
            'update_by' => $login,
        ]);

        $this->stat_confirm = true;
        $this->confirmedAt = now()->translatedFormat('d M Y H:i');
        $this->confirmedBy = $login;
    }

    public function delete()
    {
        if (! $this->isEdit) {
            return;
        }

        MatRaw::whereKey($this->materialId)->delete();

        return $this->redirect(route('master.raw-material.list'));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📦 {{ $this->isEdit ? t('Edit Item').' — '.$material_code : t('New Item') }}</b>
        @if ($this->isEdit)
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $stat_confirm ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $stat_confirm ? t('CONFIRMED') : t('DRAFT') }}</span>
        @endif
        <x-back-button :fallback="route('master.raw-material.list')" :title="t('Back to Item List')" class="ml-auto" />
    </header>

    <div class="max-w-[1300px] mx-auto my-5 px-4">

        {{-- HEADER BAR --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4 grid gap-3">
            <div class="col-span-2">
                <x-form.input :label="t('Item Name')" wire:model="material_name" :placeholder="t('e.g. Art Carton 210gr 65x100 GOLDEN')" name="material_name" />
            </div>
            <x-form.input :label="'🔒 '.t('Item Code')" readonly value="{{ $material_code }}" :placeholder="t('Auto from the category hierarchy')" class="bg-[#f8fafc] font-mono font-bold" name="material_code" />
            <x-form.select :label="t('Item Type', 'master')" wire:model="item_type" :placeholder="false"
                :options="['MATERIAL' => 'MATERIAL ('.t('Raw Material / Stock').')', 'SERVICE' => 'SERVICE ('.t('Service', 'master').')', 'FREE_SAMPLE' => 'FREE_SAMPLE ('.t('Sample from supplier').')']" />
            <x-form.select :label="t('Purchasable (PO)')" wire:model="purchasable" :placeholder="false" :options="['1' => t('Yes'), '0' => t('No')]" />
        </div>

        {{-- TABS --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
            <div class="flex bg-[#eef1f5] border-b border-[#d7dbe2] flex-wrap">
                @foreach (['category' => '1. '.t('Category'), 'specs' => '2. '.t('Specifications & QC'), 'uom' => '3. '.t('UOM & Procurement'), 'inventory' => '4. '.t('Stock & Location'), 'accounting' => '5. '.t('Accounting (GL)')] as $key => $label)
                    <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                        class="px-4 py-2 text-xs font-semibold border-r border-[#d7dbe2] {{ $activeTab === $key ? 'bg-white text-accent border-b-2 border-b-accent' : 'text-[#4b5568]' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="p-4">
                {{-- TAB 1: KATEGORI --}}
                <div @if ($activeTab !== 'category') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Hierarchy That Builds the Item Code') }}</p>
                        <div class="[display:grid] grid-cols-5 gap-2">
                            <x-form.select :label="t('Category')" wire:change="setPick(1, $event.target.value)" :disabled="$this->isLocked"
                                :selected="$pick1" :options="$this->cat1Options->mapWithKeys(fn ($o) => [$o->id_category => $o->category_name.' ('.$o->prefix_char.')'])->all()" />
                            <x-form.select :label="t('Type')" wire:change="setPick(2, $event.target.value)" :disabled="$this->isLocked || ! $pick1" class="disabled:bg-[#f8fafc]"
                                :selected="$pick2" :options="$this->cat2Options->mapWithKeys(fn ($o) => [$o->id_category => $o->category_name.' ('.$o->prefix_char.')'])->all()" />
                            <x-form.select :label="t('Weight/Spec')" wire:change="setPick(3, $event.target.value)" :disabled="$this->isLocked || ! $pick2" class="disabled:bg-[#f8fafc]"
                                :selected="$pick3" :options="$this->cat3Options->mapWithKeys(fn ($o) => [$o->id_category => $o->category_name.' ('.$o->prefix_char.')'])->all()" />
                            <x-form.select wire:change="setPick(4, $event.target.value)" :disabled="$this->isLocked || ! $pick3 || $this->cat4Options->isEmpty()" class="disabled:bg-[#f8fafc]"
                                :placeholder="t('— all —')" :selected="$pick4" :options="$this->cat4Options->mapWithKeys(fn ($o) => [$o->id_category => $o->category_name.' ('.$o->prefix_char.')'])->all()">
                                <x-slot:label>{{ t('Size') }} <span class="normal-case font-normal">{{ t('(optional)') }}</span></x-slot:label>
                            </x-form.select>
                            <x-form.select wire:change="setPick(5, $event.target.value)" :disabled="$this->isLocked || ! $pick4 || $this->cat5Options->isEmpty()" class="disabled:bg-[#f8fafc]"
                                :placeholder="t('— all —')" :selected="$pick5" :options="$this->cat5Options->mapWithKeys(fn ($o) => [$o->id_category => $o->category_name.' ('.$o->prefix_char.')'])->all()">
                                <x-slot:label>{{ t('Brand') }} <span class="normal-case font-normal">{{ t('(optional)') }}</span></x-slot:label>
                            </x-form.select>
                        </div>
                        @if ($this->isLocked)
                            <p class="text-[11px] text-muted mt-2">{{ t('The category is locked because this item is already Confirmed (the item code follows this combination) — contact Admin if reclassification is needed.') }}</p>
                        @elseif ($this->isEdit)
                            <p class="text-[11px] text-[#b45309] mt-2">{{ t('This item is still DRAFT — category & code can still be changed until it is Confirmed (tab 5. Accounting).') }}</p>
                        @endif
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Customs & Legacy Code') }}</p>
                        <div class="grid three">
                            <x-form.input :label="t('HS Code', 'master')" wire:model="hs_code" placeholder="4810.13.00" />
                            <x-form.input :label="t('Old Code')" wire:model="old_item_code" />
                            <x-form.input :label="t('Old Name')" wire:model="old_item_name" />
                        </div>
                    </div>
                </div>

                {{-- TAB 2: SPESIFIKASI & QC --}}
                <div @if ($activeTab !== 'specs') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Dimensions & Physical') }} <span class="text-muted normal-case font-normal">{{ t('(leave empty if not paper/board)') }}</span></p>
                        <div class="grid three">
                            <x-form.input :label="t('Grammage (gsm)')" type="number" wire:model="gram_weight" />
                            <x-form.input :label="t('Plano Length (cm)')" type="number" step="0.0001" wire:model="cm_length" />
                            <x-form.input :label="t('Plano Width (cm)')" type="number" step="0.0001" wire:model="cm_width" />
                            <x-form.input :label="t('Thickness (mm)')" type="number" step="0.0001" wire:model="mm_thickness" />
                            <x-form.input :label="t('Manufacturer Brand')" wire:model="brand" />
                            <x-form.input :label="t('Color')" wire:model="color" />
                        </div>
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('QC & Shelf Life') }}</p>
                        <div class="grid three">
                            <div>
                                <x-form.select :label="t('QC Required on Receipt')" wire:model="qc_required" :placeholder="false"
                                    :options="['1' => t('Yes — lab check GSM/moisture'), '0' => t('No — straight to stock')]" />
                                <p class="text-[10.5px] text-muted mt-1">{{ t('Turns on the quality QC gate on the PV screen.') }}</p>
                            </div>
                            <x-form.input :label="t('Shelf Life (days)')" type="number" wire:model="shelf_life_days" />
                            <x-form.input :label="t('Moisture Limit (%)')" type="number" step="0.1" wire:model="moisture_pct" />
                        </div>
                    </div>
                </div>

                {{-- TAB 3: UOM & PENGADAAN --}}
                <div @if ($activeTab !== 'uom') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Units & Conversion') }}</p>
                        <div class="grid three">
                            <x-form.select :label="t('Stock UoM')" wire:model="uom_stock" :options="$this->uomOptions->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])->all()" />
                            <x-form.select :label="t('Purchase UoM (PO)')" wire:model="uom_purchase" :options="$this->uomOptions->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])->all()" />
                            <x-form.select :label="t('Usage UoM')" wire:model="uom_usage" :options="$this->uomOptions->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])->all()" />
                        </div>
                        <div class="grid two mt-2">
                            <div>
                                <x-form.input :label="t('Conversion Purchase → Stock')" type="number" step="0.000001" wire:model="conversion_to_stock" />
                                <p class="text-[10.5px] text-muted mt-1">⚠ {{ t('INITIAL value only — on the PV it can always be overridden by hand (bulk goods).') }}</p>
                            </div>
                            <x-form.input :label="t('Conversion Stock → Usage')" type="number" step="0.000001" wire:model="conversion_to_usage" />
                        </div>
                    </div>

                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Procurement Parameters') }}</p>
                        <div class="grid">
                            <x-form.input :label="t('Lead Time (days)')" type="number" wire:model="lead_time_days" />
                            <x-form.input :label="t('Receipt Tolerance (%)')" type="number" step="0.1" wire:model="tol_purchase" />
                            <x-form.select :label="t('Input Tax')" wire:model="purchase_tax_code" :placeholder="false" :options="['PPN' => 'PPN (sys_constant)', 'NON_PPN' => t('Non-PPN')]" />
                            <x-form.select :label="t('Suggested Supplier')" wire:model="suggest_supplier_id" :options="$this->suppliers->pluck('supplier_name', 'id_supplier')->all()" />
                        </div>
                    </div>

                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Acquisition Cost Method') }}</p>
                        <div class="grid two">
                            <x-form.select :label="t('Costing Method')" wire:model="costing_method" :placeholder="false"
                                :options="['AVG' => 'AVG — '.t('Moving Average'), 'FIFO' => 'FIFO', 'LIFO' => 'LIFO', 'STANDARD' => 'STANDARD — '.t('fixed price')]" />
                            <div>
                                <x-form.select :label="t('FX Sensitive')" wire:model="is_fx_sensitive" :placeholder="false" :options="['0' => t('No'), '1' => t('Yes')]" />
                                @if ($is_fx_sensitive)
                                    <p class="text-[10.5px] text-[#8a5a00] mt-1">⚠ {{ t('This item will be flagged on the PR/PQ screens: "price follows the exchange rate".') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TAB 4: STOK & LOKASI --}}
                <div @if ($activeTab !== 'inventory') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Inventory Limits') }}</p>
                        <div class="grid">
                            <x-form.input :label="t('Min Stock')" type="number" wire:model="min_stock" />
                            <x-form.input :label="t('Safety Stock')" type="number" wire:model="safety_stock" />
                            <x-form.input :label="t('Reorder Point')" type="number" wire:model="reorder_point" />
                            <x-form.input :label="t('Max Stock')" type="number" wire:model="max_stock" />
                        </div>
                    </div>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Location & Balance') }}</p>
                        <div class="grid three">
                            <div>
                                <x-form.input :label="t('Default Warehouse Location')" wire:model="def_location_id" :placeholder="t('no warehouse master yet -- free text')" />
                                <p class="text-[10.5px] text-muted mt-1">⚠ {{ t('Not yet linked to the Warehouse Master') }} (wh_warehouse) — {{ t('free text for now.') }}</p>
                            </div>
                            <x-form.input :label="'🔒 '.t('On-Hand Stock')" readonly value="{{ number_format($this->currentStock['cur_stock'], 2) }}" class="bg-[#f8fafc]" />
                            <x-form.input :label="'🔒 '.t('Moving Average Price')" readonly value="{{ number_format($this->currentStock['avg_unitprice'], 2) }}" class="bg-[#f8fafc]" />
                        </div>
                        <p class="text-[10.5px] text-muted mt-2">{{ t('Stock & average price are managed by the system (Purchase Receive posting) — not edited by hand here.') }}</p>
                    </div>
                </div>

                {{-- TAB 5: AKUNTANSI --}}
                <div @if ($activeTab !== 'accounting') style="display:none" @endif>
                    <div class="border border-[#d7dbe2] rounded-md p-3">
                        <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Linkage to Chart of Account') }} <span class="text-muted normal-case font-normal">({{ t('directly to') }} fin_account, {{ t('without an account-determination layer') }})</span></p>
                        <div class="grid three">
                            <div>
                                <x-form.input :placeholder="t('e.g. Raw Material - Paper')" wire:model="valuation_class">
                                    <x-slot:label>{{ t('Classification Label') }} <span class="normal-case font-normal">{{ t('(optional)') }}</span></x-slot:label>
                                </x-form.input>
                                <p class="text-[10.5px] text-muted mt-1">{{ t('Just a report label — does not determine the account.') }}</p>
                            </div>
                            <x-form.select :label="t('Inventory Asset Account')" wire:model="stock_asset_account_id" :placeholder="t('— category default —')"
                                :options="$this->coaAccounts->mapWithKeys(fn ($acc) => [$acc->account_id => $acc->account_code.' - '.$acc->account_name])->all()" />
                            <x-form.select :label="t('COGS / Usage Expense Account')" wire:model="cogs_account_id" :placeholder="t('— category default —')"
                                :options="$this->coaAccounts->mapWithKeys(fn ($acc) => [$acc->account_id => $acc->account_code.' - '.$acc->account_name])->all()" />
                        </div>
                        <div class="grid three mt-2">
                            <div>
                                <x-form.select :label="t('GR/IR Clearing Account')" wire:model="gr_ir_account_id" :placeholder="t('— category default —')"
                                    :options="$this->coaAccounts->mapWithKeys(fn ($acc) => [$acc->account_id => $acc->account_code.' - '.$acc->account_name])->all()" />
                                <p class="text-[10.5px] text-muted mt-1">{{ t('Used when the PV is posted (goods received, no invoice yet).') }}</p>
                            </div>
                            <x-form.select :label="t('Default Cost Center', 'master')" wire:model="cost_center_id" :placeholder="t('— no default —', 'master')"
                                :options="$this->costCenters->mapWithKeys(fn ($cc) => [$cc->cc_id => $cc->cc_code.' — '.$cc->cc_name])->all()" />
                            <x-form.select :label="t('Profit Center')" wire:model="profit_center_id" :placeholder="t('— no default —', 'master')"
                                :options="$this->profitCenters->mapWithKeys(fn ($pc) => [$pc->pc_id => $pc->pc_code.' — '.$pc->pc_name])->all()" />
                        </div>
                    </div>

                    @if ($this->isEdit)
                        <div class="border border-[#d7dbe2] rounded-md p-3 mt-3">
                            <p class="text-[10px] font-bold text-accent uppercase mb-2">{{ t('Confirm Status') }}</p>
                            @if ($stat_confirm)
                                <p class="text-[11.5px] text-[#16a34a]">✓ {{ t('Confirmed by') }} <b>{{ $confirmedBy }}</b> {{ t('on') }} {{ $confirmedAt }} — {{ t('category & item code are locked.') }}</p>
                            @else
                                <p class="text-[11.5px] text-muted mb-2">{{ t('This item is still DRAFT. Fill in all three accounts above (Inventory Asset, COGS, GR/IR), then Confirm — after that the category & item code are permanently locked and the item appears in the PR/PQ/PRT search.') }}</p>
                                @error('confirm') <p class="text-[11px] text-[#a3241f] mb-2">{{ $message }}</p> @enderror
                                <button type="button" wire:click="confirm" wire:confirm="{{ t('Confirm this item? The category & item code will be permanently locked.') }}" class="bg-[#16a34a] text-white text-xs font-bold px-4 py-2 rounded-md">✓ {{ t('Confirm') }}</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 my-6">
            @if ($this->isEdit)
                <button type="button" wire:click="delete" wire:confirm="{{ t('Permanently delete this item?') }}" class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">🗑 {{ t('Delete') }}</button>
            @endif
            <button type="button" wire:click="save" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">💾 {{ t('Save') }}</button>
        </div>
    </div>
</div>
