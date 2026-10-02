<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\MatCoatingRate;
use App\Models\Master\MatFilmRate;
use App\Models\Master\MatFluteAllowance;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatJoinType;
use App\Models\Master\MatPackingType;
use App\Models\Master\MatSheetRate;
use App\Models\Master\MatSheetSupplier;
use App\Models\Master\ProdPartType;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Services\Costing\CostingEngine;
use App\Services\Costing\ReverseCostingService;
use App\Support\Costing\FormulaAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// ProCost MODE INSTANT (kalkulator standalone). Tata letak = program HPP lama
// ("UPDATE HPP V3.027", screenshot user 2026-09-18) + mockup Kalkulasi_HPP_UAT.html:
// semua parameter manual ada; field tampil per Tipe Part mengikuti
// map_hpp_parts.visible_type (docs/ddl/tmhpp.sql). Deviasi yang disetujui user
// (docs/mockups/procost_instant_deviasi.jpg): tombol Reverse, Currency hanya
// label, Total After Ppn = After Margin x (1+PPN) bila INCLUDE, LC/LB angka asli,
// breakdown & HPP hanya login PROCOST_FORMULA_LOGINS. Rumus: CostingEngine.
new #[Title('Kalkulasi HPP (Instant) — TELASIH MAKMUR ERP')] class extends Component
{
    public int $costingId;

    #[Url(as: 'part')]
    public int $partNo = 1;

    public string $tab = 'parts';

    public string $engineError = '';

    public string $flash = '';

    public function mount(int $costing): void
    {
        $row = IqpCosting::where('mode', IqpCosting::MODE_INSTANT)->findOrFail($costing);
        $this->costingId = $row->id_costing;

        if ($row->priceTiers()->count() === 0) {
            IqpCostingPriceTier::create(['costing_id' => $row->id_costing, 'qty_pcs' => 1000, 'is_primary' => true, 'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login]);
        }
    }

    // ───────────── data ─────────────
    #[Computed]
    public function costing(): IqpCosting
    {
        return IqpCosting::with(['parts.coatings', 'priceTiers', 'customer'])->findOrFail($this->costingId);
    }

    #[Computed]
    public function tier(): IqpCostingPriceTier
    {
        return $this->costing->priceTiers->firstWhere('is_primary', true) ?? $this->costing->priceTiers->first();
    }

    #[Computed]
    public function part(): ?IqpCostingPart
    {
        $parts = $this->costing->parts->values();

        return $parts[max(0, min($this->partNo, $parts->count()) - 1)] ?? null;
    }

    #[Computed]
    public function readOnly(): bool
    {
        return in_array($this->costing->doc_status, ['PENDING_APPROVAL', 'PUBLISHED', 'CANCELLED'], true);
    }

    #[Computed]
    public function canSeeFormula(): bool
    {
        return FormulaAccess::canSee();
    }

    #[Computed]
    public function lookups(): array
    {
        $sheet = MatSheetRate::where('activate', true)->orderBy('flute_type')->orderBy('substance_name')->get();

        return [
            'customers' => CustCustomer::where('activate', true)->orderBy('customer_name')->limit(500)->pluck('customer_name', 'id_customer')->all(),
            'partTypes' => ProdPartType::where('activate', true)->orderBy('id_part_type')->pluck('part_type_label', 'part_type_code')->all(),
            'grammages' => MatGrammage::where('activate', true)->orderBy('paper_kind')->orderBy('grammage_value')->get()
                ->mapWithKeys(fn ($g) => [$g->id_grammage => $g->grammage_name.((float) $g->duplex_price_list > 0 ? ' — Rp '.number_format($g->duplex_price_list).'/rim' : ((float) $g->ivory_ap_price_per_kg > 0 ? ' — Rp '.number_format($g->ivory_ap_price_per_kg).'/kg' : ' — (no price)'))])->all(),
            'sheetRates' => $sheet->filter(fn ($s) => in_array($s->mat_subtype, [null, 'SHEET'], true))->mapWithKeys(fn ($s) => [$s->id_sheet_rate => "{$s->flute_type} - {$s->substance_name} - Rp ".number_format($s->index_substance).'/m²'])->all(),
            'singleFace' => $sheet->where('mat_subtype', 'SINGLE FACE')->mapWithKeys(fn ($s) => [$s->id_sheet_rate => "{$s->flute_type} - {$s->substance_name} - Rp ".number_format($s->index_substance).'/m²'])->all(),
            'sfEf' => $sheet->where('mat_subtype', 'SF')->mapWithKeys(fn ($s) => [$s->id_sheet_rate => "{$s->substance_name} — Rp ".number_format($s->index_substance)])->all(),
            'suppliers' => MatSheetSupplier::where('activate', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($s) => [$s->code => "{$s->code} — {$s->name}"])->all(),
            'filmRates' => MatFilmRate::where('activate', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($f) => [(string) (float) $f->rate_value => $f->label])->all(),
            'joinTypes' => MatJoinType::where('activate', true)->orderBy('sort_order')->pluck('label', 'code')->all(),
            'packingTypes' => MatPackingType::where('activate', true)->orderBy('sort_order')->pluck('label', 'code')->all(),
            'flutes' => MatFluteAllowance::where('activate', true)->pluck('flute_type', 'flute_type')->all(),
            'coatings' => MatCoatingRate::where('activate', true)->pluck('coating_type', 'coating_type')->all(),
        ];
    }

    // ───────────── helper ─────────────
    private function login(): string
    {
        return Auth::user()->login;
    }

    private function guardEditable(): bool
    {
        if ($this->readOnly) {
            $this->flash = str_replace(':status', $this->costing->doc_status, t('Document is already :status — view only.'));

            return false;
        }

        return true;
    }

    private function refreshData(): void
    {
        unset($this->costing, $this->tier, $this->part);
    }

    public function recalc(): void
    {
        $this->engineError = '';
        $this->refreshData();

        if ($this->costing->parts->isEmpty()) {
            return;
        }

        try {
            (new CostingEngine)->run($this->costing);
        } catch (\Throwable $e) {
            $this->engineError = $e->getMessage();
        }

        $this->refreshData();
    }

    // ───────────── header dokumen ─────────────
    public function updateHeader(string $field, $value): void
    {
        if (! $this->guardEditable()) {
            return;
        }

        $allowed = ['doc_date', 'calc_type', 'customer_id', 'customer_name_draft', 'product_name_draft', 'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm', 'ppn_mode', 'ppn_pct', 'currency_code', 'calc_notes'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $data = [$field => $value === '' ? null : $value, 'updated_by' => $this->login()];
        // Legacy Ajax customer_id onChange: auto-fill customer_name.
        if ($field === 'customer_id' && $value) {
            $data['customer_name_draft'] = CustCustomer::whereKey($value)->value('customer_name');
        }
        IqpCosting::whereKey($this->costingId)->update($data);
        $this->recalc();
    }

    public function updateTier(string $field, $value): void
    {
        if (! $this->guardEditable() || ! in_array($field, ['qty_pcs', 'margin_pct', 'tolerance_pct', 'rounding_mode'], true)) {
            return;
        }

        $this->tier->update([$field => $value === '' ? 0 : $value, 'updated_by' => $this->login()]);
        $this->recalc();
    }

    public function newDoc()
    {
        $new = IqpCosting::create(['mode' => IqpCosting::MODE_INSTANT, 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'created_by' => $this->login(), 'updated_by' => $this->login()]);

        return redirect()->route('inquiry.costing.instant', ['costing' => $new->id_costing]);
    }

    public function saveDoc(): void
    {
        $this->recalc();
        $this->flash = $this->engineError ? '' : t('Saved & recalculated.');
    }

    public function deleteDoc()
    {
        if (! $this->guardEditable()) {
            return null;
        }

        $partIds = $this->costing->parts->pluck('id_costing_part');
        IqpCostingPartCoating::whereIn('costing_part_id', $partIds)->delete();
        IqpCostingPart::whereIn('id_costing_part', $partIds)->delete();
        IqpCostingPriceTier::where('costing_id', $this->costingId)->delete();
        IqpCosting::whereKey($this->costingId)->delete();

        return redirect()->route('inquiry.costing.instant.list');
    }

    public function confirmDoc(): void
    {
        $this->recalc();
        if ($this->engineError || $this->costing->doc_status !== 'CALCULATED') {
            $this->flash = t('COGS calculation must succeed before Confirm.');

            return;
        }
        $this->costing->update(['doc_status' => 'PENDING_APPROVAL', 'updated_by' => $this->login()]);
        $this->refreshData();
    }

    public function cancelDoc(): void
    {
        if ($this->costing->doc_status === 'PUBLISHED') {
            return;
        }
        $this->costing->update(['doc_status' => 'CANCELLED', 'updated_by' => $this->login()]);
        $this->refreshData();
    }

    public function reverse()
    {
        try {
            $inquiry = (new ReverseCostingService)->reverse($this->costing, $this->login());
        } catch (\Throwable $e) {
            $this->engineError = $e->getMessage();

            return null;
        }

        return redirect()->route('inquiry.costing', ['inquiry' => $inquiry->id_inquiry]);
    }

    // ───────────── part ─────────────
    public function goPart(int $no): void
    {
        $this->partNo = max(1, min($no, $this->costing->parts->count()));
        unset($this->part);
    }

    public function addPart(): void
    {
        if (! $this->guardEditable()) {
            return;
        }

        $c = $this->costing;
        IqpCostingPart::create([
            'costing_id' => $c->id_costing,
            'no_urut' => $c->parts->count() + 1,
            'part_name' => '',
            'part_type_code' => 'DC',
            'data_source' => 'MANUAL',
            'qty_per_set' => 1,
            'i_dim_panjang_mm' => $c->i_dim_panjang_mm,
            'i_dim_lebar_mm' => $c->i_dim_lebar_mm,
            'i_dim_tinggi_mm' => $c->i_dim_tinggi_mm,
            'i_lidah_lem_mm' => $c->i_lidah_lem_mm,
            'i_cut_per_plano' => 1,
            'i_isi_per_lb' => 1,
            'i_isi_per_lb_kerja' => 1,
            'i_jumlah_warna' => 4,
            'i_ongkos_plate_warna' => 90000,
            'supplier_sheet_code' => 'UJK',
            'created_by' => $this->login(),
            'updated_by' => $this->login(),
        ]);

        $this->refreshData();
        $this->partNo = $this->costing->parts->count();
    }

    public function removePart(): void
    {
        if (! $this->guardEditable() || ! $this->part) {
            return;
        }

        IqpCostingPartCoating::where('costing_part_id', $this->part->id_costing_part)->delete();
        $this->part->delete();
        $this->partNo = max(1, $this->partNo - 1);
        $this->recalc();
    }

    public function updatePart(string $field, $value): void
    {
        if (! $this->guardEditable() || ! $this->part) {
            return;
        }

        $allowed = [
            'no_urut', 'part_name', 'part_type_code', 'qty_per_set', 'i_jumlah_warna', 'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm',
            'grammage_id', 'i_paper_price_override', 'i_dc_persen_override', 'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_isi_per_lb_kerja',
            'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm', 'sheet_rate_id', 'supplier_sheet_code', 'flute_type', 'i_sheet_panjang_cm', 'i_sheet_lebar_cm', 'i_lebar_trim_cm', 'i_isi_per_trim', 'i_discount_rm',
            'i_ongkos_cetak_rim', 'i_ongkos_plong_lb', 'i_ongkos_lem_pc', 'i_ongkos_hotprint_pc', 'i_ongkos_kupas_pc', 'i_ongkos_qc_pack_pc', 'i_ongkos_kirim_pc', 'packing_type_code', 'i_ongkos_packing_pc',
            'pakai_laminasi', 'i_lam_sheet_flat_pc', 'sf_ef_rate_id', 'i_sf_ef_rate_override', 'i_lam_pakai_ukuran_sf', 'i_ongkos_lam_rp_cm2_sh', 'i_ongkos_lam_rp_cm2_sf', 'i_ongkos_laminasi_pc',
            'i_film_rate', 'i_film_faktor', 'i_ongkos_plate_warna', 'i_pisau_faktor', 'i_pisau_rate_rp',
            'i_ongkos_print_pc', 'i_ongkos_diecut_pc', 'i_ongkos_slitter_pc', 'i_ongkos_slotter_pc', 'join_type_code', 'i_ongkos_join_pc', 'i_ongkos_partisi_pc', 'i_ongkos_layer_pc', 'i_ongkos_pisau_pc',
            'calc_notes',
        ];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $value = $value === '' ? null : $value;
        $data = [$field => $value, 'updated_by' => $this->login()];

        // Auto-fill ala Ajax event legacy (map_hpp_parts / katalog Ajax Gemini §II).
        match ($field) {
            'part_type_code' => $data['i_pisau_rate_rp'] = ProdPartType::where('part_type_code', $value)->value('pisau_rate_rp'),
            'grammage_id' => ($dc = (float) MatGrammage::whereKey($value)->value('duplex_dc_pct')) > 0 ? $data['i_dc_persen_override'] = $dc : null,
            'sf_ef_rate_id' => $data['i_sf_ef_rate_override'] = $value ? MatSheetRate::whereKey($value)->value('index_substance') : null,
            'join_type_code' => $data['i_ongkos_join_pc'] = $value ? MatJoinType::where('code', $value)->value('default_rate') : 0,
            'packing_type_code' => $data['i_ongkos_packing_pc'] = $value ? MatPackingType::where('code', $value)->value('default_rate') : 0,
            default => null,
        };
        $this->part->update($data);
        $this->recalc();
    }

    // ───────────── coating ─────────────
    public function addCoating(): void
    {
        if (! $this->guardEditable() || ! $this->part) {
            return;
        }

        IqpCostingPartCoating::create([
            'costing_part_id' => $this->part->id_costing_part,
            'seq' => $this->part->coatings->count() + 1,
            'coating_type' => 'UV',
            'index_coating' => MatCoatingRate::where('coating_type', 'UV')->where('activate', true)->value('index_coating') ?? 0,
        ]);
        $this->recalc();
    }

    public function updateCoating(int $id, string $field, $value): void
    {
        if (! $this->guardEditable() || ! in_array($field, ['coating_type', 'index_coating', 'notes'], true)) {
            return;
        }

        $coating = IqpCostingPartCoating::whereKey($id)->where('costing_part_id', $this->part?->id_costing_part)->first();
        if (! $coating) {
            return;
        }

        if ($field === 'coating_type') {
            $coating->update(['coating_type' => $value, 'index_coating' => MatCoatingRate::where('coating_type', $value)->where('activate', true)->value('index_coating') ?? 0]);
        } else {
            $coating->update([$field === 'notes' ? 'notes_coating' : $field => $value === '' ? 0 : $value]);
        }
        $this->recalc();
    }

    public function removeCoating(int $id): void
    {
        if (! $this->guardEditable()) {
            return;
        }

        IqpCostingPartCoating::whereKey($id)->where('costing_part_id', $this->part?->id_costing_part)->delete();
        $this->recalc();
    }

    // ───────────── angka tampilan ─────────────
    #[Computed]
    public function qty(): float
    {
        return (float) ($this->tier->qty_pcs ?? 0);
    }

    #[Computed]
    public function breakdown(): array
    {
        $p = $this->part;
        if (! $p) {
            return [];
        }

        $q = $this->qty * max(1, (float) $p->qty_per_set);
        $toolQ = $q > 0 ? $q : 1;
        $pc = [
            'Paper' => (float) $p->c_paper_cost_pc, 'Printing' => (float) $p->c_cetak_cost_pc, 'Punching' => (float) $p->c_plong_cost_pc,
            'GLUE' => (float) $p->c_lem_cost_pc, 'Hot Print' => (float) $p->i_ongkos_hotprint_pc, 'Stripping' => (float) $p->i_ongkos_kupas_pc,
            'QC' => (float) $p->i_ongkos_qc_pack_pc, 'Shipping' => (float) $p->i_ongkos_kirim_pc, 'Packing' => (float) $p->i_ongkos_packing_pc,
            'Coating' => (float) $p->c_total_coating_pc, 'SF EF' => (float) $p->c_sf_ef_cost_pc, 'Lamination Cost' => (float) $p->c_ongkos_lam_pc,
            'C I Lam Sheet Flat' => (float) $p->c_lam_flat_cost_pc, 'Sheet Price' => (float) $p->c_sheet_cost_pc, 'Print' => (float) $p->c_print_cost_pc,
            'Die Cut' => (float) $p->i_ongkos_diecut_pc, 'Slitter' => (float) $p->i_ongkos_slitter_pc, 'Slotter' => (float) $p->i_ongkos_slotter_pc,
            'Join' => (float) $p->i_ongkos_join_pc, 'Partition' => (float) $p->i_ongkos_partisi_pc, 'Layer' => (float) $p->i_ongkos_layer_pc,
            'Diecut Knife' => (float) $p->i_ongkos_pisau_pc,
            'Film' => (float) $p->c_film_cost_order / $toolQ, 'Plate' => (float) $p->c_plate_cost_order / $toolQ, 'Knife' => (float) $p->c_knife_cost_order / $toolQ,
        ];

        return ['pc' => $pc, 'qty' => $q];
    }

    // Urutan sel = mockup Kalkulasi_HPP_UAT.html "Detailed Breakdown Per PC". [label, nilai, 'k'=subtotal biru]
    public function outputCells(): array
    {
        $p = $this->part;
        $pc = $this->breakdown['pc'] ?? [];
        $toolPc = $this->qty > 0 ? (float) $p->c_tooling_cost_order / $this->qty : 0;

        return [
            ['Print Area (cm2)', $p->c_area_cetak_cm2, 'k'], ['Material Qty (Sheets)', $p->c_jumlah_bahan_lb, 'k'],
            ['Paper Price/PC', $p->c_paper_cost_pc, ''], ['Printing/PC', $p->c_cetak_cost_pc, ''],
            ['Punching/PC', $p->c_plong_cost_pc, ''], ['Glue/PC', $p->c_lem_cost_pc, ''],
            ['Coating/PC', $p->c_total_coating_pc, ''], ['SF EF/PC', $p->c_sf_ef_cost_pc, ''],
            ['Lamination Cost/PC', (float) $p->c_ongkos_lam_pc + (float) $p->c_lam_flat_cost_pc, ''], ['Sheet Price/PC', $p->c_sheet_cost_pc, ''],
            ['Print/PC', $p->c_print_cost_pc, ''], ['Film/PC', $pc['Film'] ?? 0, ''],
            ['Process Cost/PC', (float) $p->c_process_cost_pc + (float) $p->c_total_coating_pc, 'k'], ['Plate/PC', $pc['Plate'] ?? 0, ''],
            ['Knife/PC', $pc['Knife'] ?? 0, ''], ['Tooling/PC', $toolPc, 'k'],
        ];
    }
};
?>

@php
    $c = $this->costing;
    $t = $this->tier;
    $p = $this->part;
    $lk = $this->lookups;
    $ro = $this->readOnly;
    $type = $p?->part_type_code;
    $is = fn (string ...$types) => $p && in_array($type, $types, true);
    $n4 = fn ($v) => $v === null ? '—' : number_format((float) $v, 4);
    $n2 = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $lock = ! $this->canSeeFormula;
    $bd = $this->breakdown;
    $toolPc = $p && $this->qty > 0 ? (float) $p->c_tooling_cost_order / $this->qty : 0;
    $statusCls = match ($c->doc_status) { 'CALCULATED' => 'bg-[#eaf2ff] text-[#1d4ed8]', 'PENDING_APPROVAL' => 'bg-[#fef6e7] text-[#b45309]', 'PUBLISHED' => 'bg-[#e9f9ef] text-[#16a34a]', 'CANCELLED' => 'bg-[#fdecec] text-[#c0392b]', default => 'bg-[#eef0f3] text-[#5a6272]' };
    $panel = 'bg-white border border-[#d7dbe2] rounded-md';
    $ph = 'px-3 py-1.5 border-b border-[#e2e8f0] bg-[#f8fafc] text-[11px] font-bold text-[#334155]';
@endphp

<div class="max-w-[1600px] mx-auto my-3 px-3 text-xs">
    {{-- ═════ Toolbar dokumen (program lama: New · Save · Delete | Hitung HPP · Confirm · Cancel | ⟳ · Back) ═════ --}}
    <div class="{{ $panel }} px-3 py-2 mb-2 flex items-center gap-2 flex-wrap">
        <b class="text-[12px] text-[#0f172a]">{{ t('UPDATE COGS — INSTANT MODE') }}</b>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $statusCls }}">{{ $c->doc_status }}</span>
        <span class="ml-auto"></span>
        <button type="button" wire:click="newDoc" class="px-3 py-1.5 rounded bg-[#10b981] text-white font-semibold cursor-pointer">＋ {{ t('New') }}</button>
        <button type="button" wire:click="saveDoc" data-dirty-save @disabled($ro) class="px-3 py-1.5 rounded bg-[#f1f5f9] border border-[#cbd5e1] font-semibold cursor-pointer disabled:opacity-40">💾 {{ t('Save') }}</button>
        <button type="button" wire:click="deleteDoc" wire:confirm="{{ t('Delete this COGS document along with all its parts?') }}" @disabled($ro) class="px-3 py-1.5 rounded bg-[#ef4444] text-white font-semibold cursor-pointer disabled:opacity-40">🗑 {{ t('Delete') }}</button>
        <span class="w-px h-6 bg-[#e2e8f0] mx-1"></span>
        <button type="button" wire:click="recalc" @disabled($ro) class="px-3 py-1.5 rounded bg-[#f59e0b] text-white font-semibold cursor-pointer disabled:opacity-40">🧮 {{ t('Calculate COGS') }}</button>
        <button type="button" wire:click="confirmDoc" wire:confirm="{{ t('Confirm this document? After Confirm the data becomes read-only & awaits Directors approval.') }}" @disabled($ro) class="px-3 py-1.5 rounded bg-[#10b981] text-white font-semibold cursor-pointer disabled:opacity-40">✓ {{ t('Confirm') }}</button>
        <button type="button" wire:click="cancelDoc" wire:confirm="{{ t('Cancel this document?') }}" @disabled($c->doc_status === 'CANCELLED' || $c->doc_status === 'PUBLISHED') class="px-3 py-1.5 rounded bg-[#ef4444] text-white font-semibold cursor-pointer disabled:opacity-40">✕ {{ t('Cancel') }}</button>
        <span class="w-px h-6 bg-[#e2e8f0] mx-1"></span>
        <button type="button" wire:click="reverse" wire:confirm="{{ t('Reverse: create a NEW Inquiry Product + ProDev DRAFT + Master Product draft from this data, then this document becomes Integrated mode. Continue?') }}" @disabled($ro) title="{{ t('Change to Integrated (Inquiry + ProDev created automatically)') }}" class="px-3 py-1.5 rounded bg-[#2c3e69] text-white font-semibold cursor-pointer disabled:opacity-40">⇄ {{ t('Reverse') }}</button>
        <button type="button" wire:click="recalc" title="{{ t('Refresh') }}" class="px-2 py-1.5 rounded bg-[#f1f5f9] border border-[#cbd5e1] cursor-pointer">⟳</button>
        <x-back-button :fallback="route('inquiry.costing.instant.list')" />
    </div>

    @if ($engineError)
        <div class="bg-[#fdecec] border border-[#f5c2c2] text-[#9b1c1c] px-3 py-1.5 rounded mb-2">⚠ {{ $engineError }}</div>
    @endif
    @if ($flash)
        <div class="bg-[#e9f9ef] border border-[#bbe5c9] text-[#15803d] px-3 py-1.5 rounded mb-2">{{ $flash }}</div>
    @endif

    {{-- ═════ Header 6 panel (layout program lama) ═════ --}}
    <div class="[display:grid] grid-cols-12 gap-2 mb-2">
        <div class="{{ $panel }} col-span-4">
            <div class="{{ $ph }}">{{ t('Document Identity') }}</div>
            <div class="p-2 [display:grid] grid-cols-5 gap-2">
                <x-costing.field :label="t('Doc No')" key="doc_no" :value="$c->doc_no ?? t('Draft').' #'.$c->id_costing" type="text" readonly class="col-span-1" />
                <x-costing.field :label="t('Doc Date')" key="doc_date" :value="$c->doc_date?->format('Y-m-d')" type="date" action="updateHeader" :readonly="$ro" class="col-span-1" />
                <x-costing.field :label="t('Status')" key="doc_status" :value="$c->doc_status" type="text" readonly />
                <x-costing.field :label="t('Calculation Type *')" key="calc_type" :value="$c->calc_type" type="select" action="updateHeader" :readonly="$ro" :options="['NEW' => t('New Product'), 'RECALC' => t('Recalculate'), 'REVISE' => t('Product Revision')]" />
                <x-costing.field :label="t('Rev. No.')" key="revision_no" value="0" type="text" readonly />
            </div>
        </div>
        <div class="{{ $panel }} col-span-4">
            <div class="{{ $ph }}">{{ t('Customer & Product') }}</div>
            <div class="p-2 [display:grid] grid-cols-2 gap-2">
                <x-costing.field :label="t('Choose Customer (*)')" key="customer_id" :value="$c->customer_id" type="select" action="updateHeader" :readonly="$ro" :options="$lk['customers']" />
                <x-costing.field :label="t('Prospective Customer Name / Lead')" key="customer_name_draft" :value="$c->customer_name_draft" type="text" action="updateHeader" :readonly="$ro" />
            </div>
        </div>
        <div class="{{ $panel }} col-span-4">
            <div class="{{ $ph }}">{{ t('Product Dimensions') }}</div>
            <div class="p-2 [display:grid] grid-cols-2 gap-2">
                <x-costing.field :label="t('Product Name')" key="product_name_draft" :value="$c->product_name_draft" type="text" action="updateHeader" :readonly="$ro" class="col-span-2" />
            </div>
        </div>

        <div class="{{ $panel }} col-span-3">
            <div class="{{ $ph }}">{{ t('Dimension') }}</div>
            <div class="p-2 [display:grid] grid-cols-3 gap-2">
                <x-costing.field :label="t('Length (mm)')" key="i_dim_panjang_mm" :value="$c->i_dim_panjang_mm" action="updateHeader" :readonly="$ro" />
                <x-costing.field :label="t('Width (mm)')" key="i_dim_lebar_mm" :value="$c->i_dim_lebar_mm" action="updateHeader" :readonly="$ro" />
                <x-costing.field :label="t('Height (mm)')" key="i_dim_tinggi_mm" :value="$c->i_dim_tinggi_mm" action="updateHeader" :readonly="$ro" />
                <x-costing.field :label="t('Glue Flap (mm)')" key="i_lidah_lem_mm" :value="$c->i_lidah_lem_mm" action="updateHeader" :readonly="$ro" />
            </div>
        </div>
        <div class="{{ $panel }} col-span-6">
            <div class="{{ $ph }}">{{ t('Parameter') }}</div>
            <div class="p-2 [display:grid] grid-cols-6 gap-2">
                <x-costing.field :label="t('Qty Order Pcs *')" key="qty_pcs" :value="$t->qty_pcs" action="updateTier" :readonly="$ro" />
                <div class="flex flex-col">
                    <label class="text-[10px] font-semibold uppercase text-[#64748b] px-1 mb-0.5">{{ t('Choose VAT') }}</label>
                    <label class="flex items-center gap-1"><input type="radio" name="ppn_mode" @checked($c->ppn_mode !== 'NON_PPN') @disabled($ro) wire:click="updateHeader('ppn_mode', 'PPN')"> {{ t('INCLUDE VAT') }}</label>
                    <label class="flex items-center gap-1"><input type="radio" name="ppn_mode" @checked($c->ppn_mode === 'NON_PPN') @disabled($ro) wire:click="updateHeader('ppn_mode', 'NON_PPN')"> {{ t('EXCLUDE VAT') }}</label>
                </div>
                <x-costing.field :label="t('VAT Percent')" key="ppn_pct" :value="$c->ppn_pct" action="updateHeader" :readonly="$ro" />
                <x-costing.field :label="t('Margin Percent *')" key="margin_pct" :value="$t->margin_pct" action="updateTier" :readonly="$ro" />
                <x-costing.field :label="t('Tolerance Percent *')" key="tolerance_pct" :value="$t->tolerance_pct" action="updateTier" :readonly="$ro" />
                <x-costing.field :label="t('Rounding Mode')" key="rounding_mode" :value="$t->rounding_mode" type="select" action="updateTier" :readonly="$ro" :options="['NONE' => t('NONE'), 'NEAREST' => t('NEAREST'), 'UP' => t('CEILING'), 'DOWN' => t('FLOOR')]" />
            </div>
        </div>
        <div class="{{ $panel }} col-span-3">
            <div class="{{ $ph }}">{{ t('Note & Attachment') }}</div>
            <div class="p-2 [display:grid] grid-cols-3 gap-2">
                <x-costing.field :label="t('Notes')" key="calc_notes" :value="$c->calc_notes" type="textarea" action="updateHeader" :readonly="$ro" class="col-span-2" />
                <x-costing.field :label="t('Currency')" key="currency_code" :value="$c->currency_code" type="select" action="updateHeader" :readonly="$ro" :options="['IDR' => 'IDR', 'USD' => 'USD', 'EUR' => 'EUR']" title="{{ t('No exchange rate conversion yet — all master rates are IDR') }}" />
            </div>
        </div>
    </div>

    {{-- ═════ Hasil Kalkulasi (nilai per PC, seperti program lama) ═════ --}}
    <div class="border border-[#a7f3d0] rounded-md mb-2 overflow-hidden">
        <div class="px-3 py-1 bg-[#a7f3d0]/60 text-[11px] font-bold text-[#065f46]">{{ t('Calculation Result') }}</div>
        <div class="px-3 py-2 bg-white flex flex-wrap items-center gap-x-8 gap-y-1">
            @foreach ([
                'Price Per Pcs' => $t->c_after_round_pc,
                'Total Hpp' => $lock ? null : $t->c_hpp_pc,
                'Total Material Cost' => $lock ? null : $c->total_material_cost_pc,
                'Total Process Cost' => $lock ? null : (float) $c->total_process_cost_pc + (float) $c->total_finishing_cost_pc,
                'Total Tooling Cost' => $lock ? null : $t->c_tooling_pc,
            ] as $label => $val)
                <div><div class="text-[10px] font-bold text-[#475569]">{{ t($label) }}</div><div class="font-mono">{{ $val === null ? ($lock && $label !== 'Price Per Pcs' ? '🔒' : '—') : $n4($val) }}</div></div>
            @endforeach
            <div class="ml-auto flex gap-6">
                <div><div class="text-[10px] font-bold bg-[#fde047] px-1">{{ t('Total After Margin') }}</div><div class="font-mono font-bold text-[13px]">{{ $n2($t->c_after_margin_pc) }}</div></div>
                <div><div class="text-[10px] font-bold bg-[#fde047] px-1">{{ t('Total After VAT') }}</div><div class="font-mono font-bold text-[13px]">{{ $n2($t->c_after_ppn_pc) }}</div></div>
            </div>
        </div>
    </div>

    {{-- ═════ Tab Parts Details | Info ═════ --}}
    <div class="flex border-b border-[#cbd5e1] mb-2">
        <button type="button" wire:click="$set('tab', 'parts')" class="px-3 py-1.5 text-[11px] font-bold cursor-pointer {{ $tab === 'parts' ? 'bg-white border border-b-0 border-[#cbd5e1] rounded-t -mb-px text-[#1d4ed8]' : 'text-[#64748b]' }}">{{ t('Parts Details') }}</button>
        <button type="button" wire:click="$set('tab', 'info')" class="px-3 py-1.5 text-[11px] font-bold cursor-pointer {{ $tab === 'info' ? 'bg-white border border-b-0 border-[#cbd5e1] rounded-t -mb-px text-[#1d4ed8]' : 'text-[#64748b]' }}">{{ t('Info') }}</button>
    </div>

    @if ($tab === 'info')
        <div class="{{ $panel }} p-3 [display:grid] grid-cols-4 gap-2">
            <x-costing.field :label="t('COGS ID')" key="x" :value="$c->id_costing" type="text" readonly />
            <x-costing.field :label="t('Create Log')" key="x" :value="$c->created_at" type="text" readonly />
            <x-costing.field :label="t('Create By')" key="x" :value="$c->created_by" type="text" readonly />
            <x-costing.field :label="t('Update Log')" key="x" :value="$c->updated_at" type="text" readonly />
            <x-costing.field :label="t('Update By')" key="x" :value="$c->updated_by" type="text" readonly />
            <x-costing.field :label="t('Mode')" key="x" :value="$c->mode" type="text" readonly />
            <x-costing.field :label="t('Reversed At')" key="x" :value="$c->reversed_at" type="text" readonly />
            <x-costing.field :label="t('Reversed By')" key="x" :value="$c->reversed_by" type="text" readonly />
        </div>
    @else
    <div class="{{ $panel }} p-2">
        <div class="flex items-center gap-2 mb-2">
            <button type="button" wire:click="addPart" @disabled($ro) class="px-3 py-1 rounded bg-[#10b981] text-white font-semibold cursor-pointer disabled:opacity-40">＋ {{ t('New') }}</button>
            <button type="button" wire:click="saveDoc" @disabled($ro || ! $p) class="px-3 py-1 rounded bg-[#f1f5f9] border border-[#cbd5e1] font-semibold cursor-pointer disabled:opacity-40">💾 {{ t('Save') }}</button>
            <button type="button" wire:click="removePart" wire:confirm="{{ t('Delete this part?') }}" @disabled($ro || ! $p) class="px-3 py-1 rounded bg-[#ef4444] text-white font-semibold cursor-pointer disabled:opacity-40">🗑 {{ t('Delete') }}</button>
            <span class="ml-auto"></span>
            <button type="button" wire:click="recalc" @disabled($ro || ! $p) class="px-3 py-1 rounded bg-[#f59e0b] text-white font-semibold cursor-pointer disabled:opacity-40">🧮 {{ t('Calculate COGS Detail') }}</button>
        </div>

        @if (! $p)
            <div class="text-center py-10 text-[#64748b]">{{ t('No parts yet. Click "＋ New" to add a part.') }}</div>
        @else
        <div class="[display:grid] grid-cols-12 gap-2" wire:key="part-{{ $p->id_costing_part }}">
            {{-- ─────── INPUT ─────── --}}
            <div class="col-span-7 border border-[#e2e8f0] rounded p-2 bg-[#fcfdff]">
                <div class="text-[11px] font-bold mb-1">{{ t('Input') }}</div>
                <div class="[display:grid] grid-cols-4 gap-x-2 gap-y-1.5">
                    <x-costing.field :label="t('Part Seq. No.')" key="no_urut" :value="$p->no_urut" :readonly="$ro" />
                    <x-costing.field :label="t('Part Name')" key="part_name" :value="$p->part_name" type="text" :readonly="$ro" />
                    <x-costing.field :label="t('Part Qty')" key="qty_part" :value="number_format($this->qty * max(1, (float) $p->qty_per_set), 2, '.', '')" type="text" readonly />
                    <x-costing.field :label="t('Part Type')" key="part_type_code" :value="$type" type="select" :options="$lk['partTypes']" :readonly="$ro" />

                    @if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT'))
                        <x-costing.field :label="t('Number of Colors')" key="i_jumlah_warna" :value="$p->i_jumlah_warna" :readonly="$ro" />
                        <x-costing.field :label="t('Grammage / Material')" key="grammage_id" :value="$p->grammage_id" type="select" :options="$lk['grammages']" :readonly="$ro" class="col-span-2" />
                        <x-costing.field :label="t('Paper Price (override)')" key="i_paper_price_override" :value="$p->i_paper_price_override" :readonly="$ro" />
                    @endif

                    <x-costing.field :label="t('Length Dimension (mm)')" key="i_dim_panjang_mm" :value="$p->i_dim_panjang_mm" :readonly="$ro" />
                    <x-costing.field :label="t('Width Dimension (mm)')" key="i_dim_lebar_mm" :value="$p->i_dim_lebar_mm" :readonly="$ro" />
                    <x-costing.field :label="t('Height Dim. (mm)')" key="i_dim_tinggi_mm" :value="$p->i_dim_tinggi_mm" :readonly="$ro" />
                    <x-costing.field :label="t('Glue Flap (mm)')" key="i_lidah_lem_mm" :value="$p->i_lidah_lem_mm" :readonly="$ro" />

                    @if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT'))
                        <x-costing.field :label="t('Plano Length (cm)')" key="i_panjang_plano_cm" :value="$p->i_panjang_plano_cm" tone="common" :readonly="$ro" />
                        <x-costing.field :label="t('Plano Width (cm)')" key="i_lebar_plano_cm" :value="$p->i_lebar_plano_cm" tone="common" :readonly="$ro" />
                        <x-costing.field :label="t('1 Plano cut into')" key="i_cut_per_plano" :value="$p->i_cut_per_plano" tone="common" :readonly="$ro" />
                        <x-costing.field :label="t('Ups per 1 Print Sheet')" key="i_isi_per_lb" :value="$p->i_isi_per_lb" tone="common" :readonly="$ro" />
                    @else
                        <x-costing.field :label="t('1 Plano cut into')" key="i_cut_per_plano" :value="$p->i_cut_per_plano" tone="common" :readonly="$ro" />
                        <x-costing.field :label="t('Ups per 1 Print Sheet')" key="i_isi_per_lb_kerja" :value="$p->i_isi_per_lb_kerja" tone="common" :readonly="$ro" />
                    @endif
                    @if ($is('DC', 'DC+SF', 'DC+SHEET'))
                        <x-costing.field :label="t('% DC (override)')" key="i_dc_persen_override" :value="$p->i_dc_persen_override" :readonly="$ro" />
                        <x-costing.field :label="t('Print Size L (cm)')" key="i_uk_cetak_p_cm" :value="$p->i_uk_cetak_p_cm" :readonly="$ro" />
                        <x-costing.field :label="t('Print Size W (cm)')" key="i_uk_cetak_l_cm" :value="$p->i_uk_cetak_l_cm" :readonly="$ro" />
                    @endif

                    @if ($is('DC+SF', 'DC+SHEET', 'SHEET', 'SHEET_DIGITAL'))
                        <x-costing.field :label="t('Substance / SF Type')" key="sheet_rate_id" :value="$p->sheet_rate_id" type="select" tone="sheet" :options="$type === 'DC+SF' ? $lk['sfEf'] + $lk['singleFace'] : $lk['sheetRates']" :readonly="$ro" class="col-span-2" />
                        <x-costing.field :label="t('Sheet Supplier')" key="supplier_sheet_code" :value="$p->supplier_sheet_code" type="select" tone="sheet" :options="$lk['suppliers']" :readonly="$ro" />
                        <x-costing.field :label="t('SF/SH Piece Length (cm)')" key="i_sheet_panjang_cm" :value="$p->i_sheet_panjang_cm" tone="sheet" :readonly="$ro" />
                    @endif
                    @if ($is('DC+SF'))
                        <x-costing.field :label="t('SF Piece Width (cm)')" key="i_sheet_lebar_cm" :value="$p->i_sheet_lebar_cm" tone="sheet" :readonly="$ro" />
                    @endif
                    @if ($is('DC+SHEET', 'SHEET', 'SHEET_DIGITAL'))
                        <x-costing.field :label="t('Trim Width (cm)')" key="i_lebar_trim_cm" :value="$p->i_lebar_trim_cm" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Ups / Trim')" key="i_isi_per_trim" :value="$p->i_isi_per_trim" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Discount / Surcharge')" key="i_discount_rm" :value="$p->i_discount_rm" tone="sheet" :readonly="$ro" />
                    @endif
                    @if ($is('SHEET_DIGITAL', 'SHEET'))
                        <x-costing.field :label="t('Flute Type (automatic geometry)')" key="flute_type" :value="$p->flute_type" type="select" tone="sheet" :options="$lk['flutes']" :readonly="$ro" />
                    @endif

                    @if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT'))
                        <x-costing.field :label="t('Printing Cost/REAM')" key="i_ongkos_cetak_rim" :value="$p->i_ongkos_cetak_rim" :readonly="$ro" />
                        <x-costing.field :label="t('Punching Cost/Sheet')" key="i_ongkos_plong_lb" :value="$p->i_ongkos_plong_lb" :readonly="$ro" />
                        <x-costing.field :label="t('GLUE Cost/PC')" key="i_ongkos_lem_pc" :value="$p->i_ongkos_lem_pc" :readonly="$ro" />
                    @endif
                    @if ($is('DC', 'DC+SF', 'DC+SHEET'))
                        <x-costing.field :label="t('Hot Print Cost/PC')" key="i_ongkos_hotprint_pc" :value="$p->i_ongkos_hotprint_pc" :readonly="$ro" />
                    @endif
                    <x-costing.field :label="t('Stripping Cost/PC')" key="i_ongkos_kupas_pc" :value="$p->i_ongkos_kupas_pc" tone="common" :readonly="$ro" />
                    <x-costing.field :label="t('QC Cost/PC')" key="i_ongkos_qc_pack_pc" :value="$p->i_ongkos_qc_pack_pc" tone="common" :readonly="$ro" />
                    <x-costing.field :label="t('Delivery Cost/PC')" key="i_ongkos_kirim_pc" :value="$p->i_ongkos_kirim_pc" tone="common" :readonly="$ro" />
                    <x-costing.field :label="t('Packing Type')" key="packing_type_code" :value="$p->packing_type_code" type="select" tone="common" :options="$lk['packingTypes']" :readonly="$ro" />
                    <x-costing.field :label="t('Packing Cost/PC')" key="i_ongkos_packing_pc" :value="$p->i_ongkos_packing_pc" tone="common" :readonly="$ro" />

                    @if ($is('DC+SF', 'DC+SHEET'))
                        <x-costing.field :label="t('Use Lamination')" key="pakai_laminasi" :value="$p->pakai_laminasi ? '1' : '0'" type="select" tone="lam" :options="['1' => t('Yes'), '0' => t('No')]" :readonly="$ro" />
                        <x-costing.field :label="t('Lam Sheet Flat (Rp/PC)')" key="i_lam_sheet_flat_pc" :value="$p->i_lam_sheet_flat_pc" tone="lam" :readonly="$ro" />
                        <x-costing.field :label="t('SF EF Rate (E/F)')" key="sf_ef_rate_id" :value="$p->sf_ef_rate_id" type="select" tone="lam" :options="$lk['sfEf']" :readonly="$ro" />
                        <x-costing.field :label="t('SF EF Price (override)')" key="i_sf_ef_rate_override" :value="$p->i_sf_ef_rate_override" tone="lam" :readonly="$ro" />
                        <x-costing.field :label="t('Use SF Size?')" key="i_lam_pakai_ukuran_sf" :value="$p->i_lam_pakai_ukuran_sf ? '1' : '0'" type="select" tone="lam" :options="['1' => '1', '0' => '0']" :readonly="$ro" />
                        <x-costing.field :label="t('Lam Cost SH (Rp/cm2)')" key="i_ongkos_lam_rp_cm2_sh" :value="$p->i_ongkos_lam_rp_cm2_sh" tone="lam" :readonly="$ro" />
                        <x-costing.field :label="t('Lam Cost SF (Rp/cm2)')" key="i_ongkos_lam_rp_cm2_sf" :value="$p->i_ongkos_lam_rp_cm2_sf" tone="lam" :readonly="$ro" />
                        <x-costing.field :label="t('Manual Lamination Cost')" key="i_ongkos_laminasi_pc" :value="$p->i_ongkos_laminasi_pc" tone="lam" :readonly="$ro" />
                    @endif

                    @if ($is('DC', 'DC+SF', 'DC+SHEET', 'KRAFT'))
                        <x-costing.field :label="t('Film Rate')" key="i_film_rate" :value="$p->i_film_rate !== null ? (string) (float) $p->i_film_rate : ''" type="select" :options="$lk['filmRates']" :readonly="$ro" />
                        <x-costing.field :label="t('Film Factor')" key="i_film_faktor" :value="$p->i_film_faktor" :readonly="$ro" />
                        <x-costing.field :label="t('Plate (Rp/color)')" key="i_ongkos_plate_warna" :value="$p->i_ongkos_plate_warna" :readonly="$ro" />
                        <x-costing.field :label="t('Knife Factor')" key="i_pisau_faktor" :value="$p->i_pisau_faktor" :readonly="$ro" />
                        <x-costing.field :label="t('Knife Rate (Rp/order)')" key="i_pisau_rate_rp" :value="$p->i_pisau_rate_rp" :readonly="$ro" />
                    @endif

                    @if ($is('SHEET', 'SHEET_DIGITAL'))
                        <x-costing.field :label="t('Print Cost/PC')" key="i_ongkos_print_pc" :value="$p->i_ongkos_print_pc" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Die Cut Cost/PC')" key="i_ongkos_diecut_pc" :value="$p->i_ongkos_diecut_pc" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Slitter Cost/PC')" key="i_ongkos_slitter_pc" :value="$p->i_ongkos_slitter_pc" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Slotter Cost/PC')" key="i_ongkos_slotter_pc" :value="$p->i_ongkos_slotter_pc" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Join Type')" key="join_type_code" :value="$p->join_type_code" type="select" tone="sheet" :options="$lk['joinTypes']" :readonly="$ro" />
                        <x-costing.field :label="t('Join Cost/PC')" key="i_ongkos_join_pc" :value="$p->i_ongkos_join_pc" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Partition Cost/PC')" key="i_ongkos_partisi_pc" :value="$p->i_ongkos_partisi_pc" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Layer Cost/PC')" key="i_ongkos_layer_pc" :value="$p->i_ongkos_layer_pc" tone="sheet" :readonly="$ro" />
                        <x-costing.field :label="t('Diecut Knife Cost/PC')" key="i_ongkos_pisau_pc" :value="$p->i_ongkos_pisau_pc" tone="sheet" :readonly="$ro" />
                    @endif
                </div>
            </div>

            {{-- ─────── OUTPUT ─────── --}}
            <div class="col-span-5 border border-[#e2e8f0] rounded p-2">
                <div class="text-[11px] font-bold mb-1">{{ t('Output') }}</div>
                <div class="[display:grid] grid-cols-4 gap-2 mb-2">
                    <div class="col-span-1 rounded bg-[#059669] text-white p-2">
                        <div class="text-[10px] font-bold uppercase">{{ t('Cost of Goods (COGS)/PC') }}</div>
                        <div class="font-mono font-bold text-[14px]">{{ $lock ? '🔒' : $n4((float) $p->c_total_part_cost_pc + $toolPc) }}</div>
                    </div>
                    <div class="col-span-2">
                        <label class="text-[10px] font-semibold uppercase text-[#64748b]">{{ t('Description Calc') }}</label>
                        <textarea readonly rows="4" class="w-full text-[10px] font-mono p-1 border border-[#e2e8f0] bg-[#f8fafc] rounded-sm resize-none">{{ $lock ? '🔒 '.t('designated logins only') : $p->description_calc }}</textarea>
                    </div>
                    <div class="col-span-1 rounded bg-[#eaf2ff] p-2">
                        <div class="text-[10px] font-bold uppercase text-[#1d4ed8]">{{ t('Print Count (LC)') }}</div>
                        <div class="font-mono font-bold">{{ $n4($p->c_jumlah_cetak_lc) }}</div>
                    </div>
                    <div class="col-span-4">
                        <x-costing.field :label="t('Calculation Notes')" key="calc_notes" :value="$p->calc_notes" type="textarea" :readonly="$ro" />
                    </div>
                </div>

                @if ($lock)
                    <div class="bg-[#f4f6fa] border border-[#d7dbe2] rounded p-3 text-[#64748b]">🔒 {{ t('Calculation details (breakdown per PC/Order) are only for designated logins (constant PROCOST_FORMULA_LOGINS).') }}</div>
                @else
                    <div class="[display:grid] grid-cols-4 gap-px bg-[#e2e8f0] border border-[#e2e8f0] rounded overflow-hidden mb-2">
                        @foreach ($this->outputCells() as [$label, $val, $key])
                            <div class="p-1.5 {{ $key === 'k' ? 'bg-[#eff6ff]' : 'bg-white' }}">
                                <div class="text-[10px] text-[#64748b] leading-tight">{{ t($label) }}</div>
                                <div class="font-mono font-semibold {{ $key === 'k' ? 'text-[#1d4ed8]' : '' }}">{{ $n4($val) }}</div>
                            </div>
                        @endforeach
                        <div class="p-1.5 bg-[#f1f5f9] col-span-4">
                            <div class="text-[10px] text-[#64748b]">{{ t('Supplier Price Info') }}</div>
                            <div class="text-[10px] font-mono whitespace-pre-line leading-tight">{{ $p->c_sheet_compare_info ?: '—' }}</div>
                        </div>
                    </div>

                    <x-ui.accordion :title="t('Detail per Order').' (× '.t('Qty').' '.number_format($bd['qty'] ?? 0).')'" tone="green">
                        <div class="[display:grid] grid-cols-4 gap-px bg-[#bbf7d0] border border-[#bbf7d0] rounded overflow-hidden">
                            @foreach ($bd['pc'] ?? [] as $label => $val)
                                <div class="p-1.5 bg-white">
                                    <div class="text-[10px] text-[#64748b] leading-tight">{{ t($label, 'cost_item') }}/{{ t('Order', 'document') }}</div>
                                    <div class="font-mono">{{ number_format($val * ($bd['qty'] ?? 0), 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.accordion>
                @endif
            </div>
        </div>

        {{-- ─────── Coating (hanya DC family, bukan KRAFT/SHEET) ─────── --}}
        @if ($is('DC', 'DC+SF', 'DC+SHEET'))
            <div class="mt-2">
                <x-ui.accordion :title="t('Coating')" :count="$p->coatings->count()" :open="true" tone="blue">
                    <div class="flex justify-end mb-1">
                        <button type="button" wire:click="addCoating" @disabled($ro) class="px-2.5 py-1 rounded bg-[#10b981] text-white font-semibold cursor-pointer disabled:opacity-40">＋ {{ t('New') }}</button>
                    </div>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="text-[10px] uppercase text-[#64748b] bg-[#f1f5f9]">
                                <th class="p-1.5 text-left w-10">{{ t('Act') }}</th><th class="p-1.5 text-left">{{ t('Coating Type') }}</th><th class="p-1.5 text-left">{{ t('Index Coating') }}</th>
                                <th class="p-1.5 text-right">{{ t('Cost Per Print Sheet') }}</th><th class="p-1.5 text-right">{{ t('Cost Per Pcs') }}</th><th class="p-1.5 text-left">{{ t('Notes Coating') }}</th>
                                <th class="p-1.5 text-center">{{ t('Ups per Sheet') }}</th><th class="p-1.5 text-right">{{ t('Print Area') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($p->coatings as $co)
                                <tr wire:key="coat-{{ $co->id_coating }}" class="border-t border-[#f1f5f9]">
                                    <td class="p-1"><button type="button" wire:click="removeCoating({{ $co->id_coating }})" @disabled($ro) class="text-[#ef4444] cursor-pointer">🗑</button></td>
                                    <td class="p-1">
                                        <select @disabled($ro) wire:change="updateCoating({{ $co->id_coating }}, 'coating_type', $event.target.value)" class="text-xs border border-[#cbd5e1] rounded-sm px-1 py-0.5">
                                            @foreach ($lk['coatings'] as $ct)
                                                <option value="{{ $ct }}" @selected($ct === $co->coating_type)>{{ $ct }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-1"><input type="number" step="any" value="{{ $co->index_coating }}" @readonly($ro) wire:change="updateCoating({{ $co->id_coating }}, 'index_coating', $event.target.value)" class="w-24 text-xs border border-[#cbd5e1] rounded-sm px-1 py-0.5 text-right font-mono"></td>
                                    <td class="p-1 text-right font-mono">{{ $lock ? '🔒' : number_format((float) $co->c_cost_per_lc, 6) }}</td>
                                    <td class="p-1 text-right font-mono">{{ $lock ? '🔒' : $n4($co->c_cost_per_pc) }}</td>
                                    <td class="p-1"><input type="text" value="{{ $co->notes_coating }}" @readonly($ro) wire:change="updateCoating({{ $co->id_coating }}, 'notes', $event.target.value)" class="w-full text-xs border border-[#cbd5e1] rounded-sm px-1 py-0.5"></td>
                                    <td class="p-1 text-center">{{ (float) $p->i_isi_per_lb }}</td>
                                    <td class="p-1 text-right font-mono">{{ $n4($p->c_area_cetak_cm2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-2 text-[#64748b]">{{ t('No Records Found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.accordion>
            </div>
        @endif
        @endif

        {{-- ─────── Pager part (program lama: Go to · 1 2 … · [x of N]) ─────── --}}
        @php $partCount = $c->parts->count(); @endphp
        <div class="flex items-center gap-2 mt-2 pt-2 border-t border-[#e2e8f0]">
            <span class="text-[#64748b]">{{ t('Part') }}</span>
            @for ($i = 1; $i <= $partCount; $i++)
                <button type="button" wire:click="goPart({{ $i }})" class="px-2 py-0.5 rounded cursor-pointer {{ $i === $partNo ? 'bg-[#2c3e69] text-white' : 'bg-[#f1f5f9] border border-[#cbd5e1]' }}">{{ $i }}</button>
            @endfor
            <span class="ml-auto text-[#64748b]">[{{ str_replace([':n', ':total'], [$partCount ? min($partNo, $partCount) : 0, $partCount], t(':n of :total')) }}]</span>
        </div>
    </div>
    @endif
</div>
