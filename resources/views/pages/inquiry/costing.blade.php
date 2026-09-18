<?php

use App\Models\Master\MatCoatingRate;
use App\Models\Master\MatFilmRate;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatJoinType;
use App\Models\Master\MatPackingType;
use App\Models\Master\MatSheetRate;
use App\Models\Master\MatSheetSupplier;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartProcess;
use App\Models\Master\ProdPartType;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPart;
use App\Services\Costing\CostingEngine;
use App\Services\Costing\ProCostContextService;
use App\Support\Costing\FormulaAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// ProCost MODE INTEGRASI (per Inquiry Product). Tata letak = mockup
// docs/mockups/ERP_Product_Costing_Mockup.html + TAMBAHAN yang disetujui user
// 2026-09-18 (docs/mockups/procost_integrasi_deviasi.jpg): SEMUA info InqProd &
// ProDev tampil READ-ONLY (spec, artwork, ilustrasi imposition asli, print size,
// pisau, BOM, BOP termasuk mesin/subkon, tinta, finishing) supaya officer tahu
// BOM/BOP/layout saat menghitung; panel padat dibungkus accordion. Rumus:
// App\Services\Costing\CostingEngine (sama dgn Mode Instant). Mode Instant ada di
// pages::inquiry.costing-instant.
new #[Title('Costing (HPP) — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $inquiryId = null;

    public int $costingId = 0;

    public string $engineError = '';

    // Cross-nav read-only (wizard-bar, user 2026-09-04): lihat status/harga saja,
    // tidak bisa mengubah, harga kelihatan setelah PUBLISHED.
    #[Url]
    public bool $view = false;

    #[Url(as: 'part')]
    public int $partNo = 1;

    public ?int $tierId = null;

    public function mount(int $inquiry): void
    {
        $this->inquiryId = $inquiry;

        if ($this->view) {
            // JANGAN firstOrCreate -- navigasi pasif tidak boleh bikin draft.
            $this->costingId = (int) (IqpCosting::where('inquiry_id', $inquiry)->value('id_costing') ?? 0);

            return;
        }

        $inq = IqpInquiry::with(['parts', 'tiers'])->findOrFail($inquiry);
        $costing = IqpCosting::firstOrCreate(['inquiry_id' => $inquiry], [
            'mode' => IqpCosting::MODE_INTEGRATED,
            'calc_type' => $inq->inquiry_type ?: 'NEW',
            'doc_status' => 'DRAFT',
            'customer_id' => $inq->customer_id,
            'product_name_draft' => $inq->product_draft_name,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        if (! $costing->i_dim_panjang_mm && ($first = $inq->parts->sortBy('part_seq')->first())) {
            $costing->fill(['i_dim_panjang_mm' => $first->flat_w, 'i_dim_lebar_mm' => $first->flat_h])->save();
        }

        if ($costing->priceTiers()->count() === 0) {
            foreach ($inq->tiers as $tier) {
                IqpCostingPriceTier::create([
                    'costing_id' => $costing->id_costing, 'tier_id' => $tier->id_tier, 'qty_pcs' => $tier->qty,
                    'is_primary' => $tier->is_primary, 'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login,
                ]);
            }
            // Inquiry tanpa tier qty -> 1 tier default supaya hitungan tidak nol (ditandai di layar).
            if ($inq->tiers->isEmpty()) {
                IqpCostingPriceTier::create(['costing_id' => $costing->id_costing, 'qty_pcs' => 1000, 'is_primary' => true, 'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login]);
            }
        }

        // Mode #1 "Ambil Data": part Inquiry yang SUDAH punya layout ProDev langsung
        // jadi baris costing dgn parameter layout terisi -- officer tinggal isi ongkos.
        if ($costing->parts()->count() === 0) {
            foreach ($inq->parts->sortBy('part_seq') as $ip) {
                $group = $ip->prod_part_id ? ProdPart::find($ip->prod_part_id)?->layoutGroups()->orderByRaw("status = 'CONFIRMED' desc")->orderByDesc('id_layout_group')->first() : null;
                if (! $group) {
                    continue;
                }
                $cp = $this->createPartFromInquiryPart($costing, $ip);
                $this->applyLayout($cp, $group, ProdPart::find($ip->prod_part_id));
            }
        }

        $this->costingId = $costing->id_costing;
    }

    // ───────────── data ─────────────
    #[Computed]
    public function costing(): ?IqpCosting
    {
        return $this->costingId ? IqpCosting::with(['parts.coatings', 'priceTiers', 'inquiry.customer'])->findOrFail($this->costingId) : null;
    }

    #[Computed]
    public function tier(): ?IqpCostingPriceTier
    {
        $tiers = $this->costing?->priceTiers ?? collect();

        return $tiers->firstWhere('id_price_tier', $this->tierId) ?? $tiers->firstWhere('is_primary', true) ?? $tiers->first();
    }

    #[Computed]
    public function part(): ?IqpCostingPart
    {
        $parts = $this->costing?->parts->values() ?? collect();

        return $parts[max(0, min($this->partNo, $parts->count()) - 1)] ?? null;
    }

    #[Computed]
    public function context(): array
    {
        return $this->part ? (new ProCostContextService)->forPart($this->part) : [];
    }

    #[Computed]
    public function canSeeFormula(): bool
    {
        return FormulaAccess::canSee(); // konstanta PROCOST_FORMULA_LOGINS
    }

    #[Computed]
    public function readOnly(): bool
    {
        return in_array($this->costing?->doc_status, ['PENDING_APPROVAL', 'PUBLISHED', 'CANCELLED'], true);
    }

    #[Computed]
    public function inquiryParts()
    {
        return $this->inquiryId ? IqpPart::where('inquiry_id', $this->inquiryId)->orderBy('part_seq')->get() : collect();
    }

    #[Computed]
    public function availableLayoutGroups()
    {
        return ProdLayoutGroup::with(['product', 'parts'])->where('status', 'CONFIRMED')->orderByDesc('id_layout_group')->limit(100)->get();
    }

    #[Computed]
    public function lookups(): array
    {
        $sheet = MatSheetRate::where('activate', true)->orderBy('flute_type')->orderBy('substance_name')->get();

        return [
            'partTypes' => ProdPartType::where('activate', true)->orderBy('id_part_type')->pluck('part_type_label', 'part_type_code')->all(),
            'grammages' => MatGrammage::where('activate', true)->orderBy('paper_kind')->orderBy('grammage_value')->get()
                ->mapWithKeys(fn ($g) => [$g->id_grammage => $g->grammage_name.((float) $g->duplex_price_list > 0 ? ' — Rp '.number_format($g->duplex_price_list).'/rim' : ((float) $g->ivory_ap_price_per_kg > 0 ? ' — Rp '.number_format($g->ivory_ap_price_per_kg).'/kg' : ' — (no price)'))])->all(),
            'sheetRates' => $sheet->filter(fn ($s) => in_array($s->mat_subtype, [null, 'SHEET'], true))->mapWithKeys(fn ($s) => [$s->id_sheet_rate => "{$s->flute_type} - {$s->substance_name} - Rp ".number_format($s->index_substance).'/m²'])->all(),
            'sfSubst' => $sheet->whereIn('mat_subtype', ['SF', 'SINGLE FACE'])->mapWithKeys(fn ($s) => [$s->id_sheet_rate => "{$s->flute_type} - {$s->substance_name} - Rp ".number_format($s->index_substance)])->all(),
            'sfEf' => $sheet->where('mat_subtype', 'SF')->mapWithKeys(fn ($s) => [$s->id_sheet_rate => "{$s->substance_name} — Rp ".number_format($s->index_substance)])->all(),
            'suppliers' => MatSheetSupplier::where('activate', true)->orderBy('sort_order')->pluck('code', 'code')->all(),
            'filmRates' => MatFilmRate::where('activate', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($f) => [(string) (float) $f->rate_value => $f->label])->all(),
            'joinTypes' => MatJoinType::where('activate', true)->orderBy('sort_order')->pluck('label', 'code')->all(),
            'packingTypes' => MatPackingType::where('activate', true)->orderBy('sort_order')->pluck('label', 'code')->all(),
            'coatings' => MatCoatingRate::where('activate', true)->pluck('coating_type', 'coating_type')->all(),
        ];
    }

    private function refreshData(): void
    {
        unset($this->costing, $this->tier, $this->part, $this->context, $this->partRouting);
    }

    private function editable(): bool
    {
        return ! $this->readOnly && $this->costingId > 0;
    }

    // ───────────── part dari Inquiry / ProDev ─────────────
    private function createPartFromInquiryPart(IqpCosting $costing, IqpPart $ip): IqpCostingPart
    {
        return IqpCostingPart::create([
            'costing_id' => $costing->id_costing, 'part_id' => $ip->id_part, 'no_urut' => $costing->parts()->count() + 1,
            'part_name' => $ip->part_name, 'part_type_code' => in_array($ip->part_type, array_keys($this->lookups['partTypes']), true) ? $ip->part_type : 'DC',
            'data_source' => 'MANUAL', 'qty_per_set' => $ip->qty_per_sku ?: 1,
            'i_dim_panjang_mm' => $costing->i_dim_panjang_mm, 'i_dim_lebar_mm' => $costing->i_dim_lebar_mm, 'i_dim_tinggi_mm' => $costing->i_dim_tinggi_mm,
            'i_cut_per_plano' => 1, 'i_isi_per_lb' => 1, 'i_isi_per_lb_kerja' => 1, 'i_jumlah_warna' => 4, 'supplier_sheet_code' => 'UJK',
            'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login,
        ]);
    }

    // Tarik plano/potong/isi/print size/pisau dari layout ProDev (Mode #1 "Ambil Data").
    private function applyLayout(IqpCostingPart $part, ProdLayoutGroup $group, ?ProdPart $prodPart): void
    {
        $geo = (new ProCostContextService)->layoutGeometry($group->loadMissing('parts'), $prodPart);

        $pisauFaktor = (float) ($part->i_pisau_faktor ?? 0); // 0 = engine pakai KNIFE_FACTOR_DEFAULT
        if ($group->die_rule_meter !== null) {
            $isMulti = $group->parts->count() > 1;
            $own = $prodPart ? ($group->parts->firstWhere('id_part', $prodPart->id_part)?->pivot->qty_up_in_group ?? $group->up_count) : $group->up_count;
            $eff = $prodPart ? $group->effectiveCutFor($prodPart) : ($group->up_count ?: 1);
            $pisauFaktor = $isMulti && $eff > 0 ? round((float) $group->die_rule_meter * $own / $eff, 4) : (float) $group->die_rule_meter;
        }

        $part->update([
            'data_source' => 'PRODEV',
            'prod_layout_group_id' => $group->id_layout_group,
            'prodev_part_id' => $prodPart?->id_part,
            'i_panjang_plano_cm' => round($geo['plano_l'], 4),
            'i_lebar_plano_cm' => round($geo['plano_w'], 4),
            'i_cut_per_plano' => round($geo['cut'], 4),
            'i_isi_per_lb' => $geo['isi'],
            'i_uk_cetak_p_cm' => $geo['print_l'],
            'i_uk_cetak_l_cm' => $geo['print_w'],
            'i_pisau_faktor' => $pisauFaktor,
        ]);
    }

    public function addPart(string $partType = 'DC'): void
    {
        if (! $this->editable()) {
            return;
        }

        $c = $this->costing;
        IqpCostingPart::create([
            'costing_id' => $c->id_costing, 'no_urut' => $c->parts->count() + 1, 'part_name' => '',
            'part_type_code' => array_key_exists($partType, $this->lookups['partTypes']) ? $partType : 'DC',
            'data_source' => 'MANUAL', 'qty_per_set' => 1, 'i_cut_per_plano' => 1, 'i_isi_per_lb' => 1, 'i_isi_per_lb_kerja' => 1,
            'i_jumlah_warna' => 4, 'supplier_sheet_code' => 'UJK',
            'created_by' => Auth::user()->login, 'updated_by' => Auth::user()->login,
        ]);
        $this->refreshData();
        $this->partNo = $this->costing->parts->count();
    }

    public function addPartFromInquiry(int $inquiryPartId): void
    {
        if (! $this->editable()) {
            return;
        }

        $ip = IqpPart::where('inquiry_id', $this->inquiryId)->findOrFail($inquiryPartId);
        $cp = $this->createPartFromInquiryPart($this->costing, $ip);

        $group = $ip->prod_part_id ? ProdPart::find($ip->prod_part_id)?->layoutGroups()->orderByRaw("status = 'CONFIRMED' desc")->orderByDesc('id_layout_group')->first() : null;
        if ($group) {
            $this->applyLayout($cp, $group, ProdPart::find($ip->prod_part_id));
        } elseif ($layout = IqpLayout::where('part_id', $ip->id_part)->where('stat_confirm', true)->first()) {
            $cp->update(['i_panjang_plano_cm' => round($layout->plano_w_mm / 10, 4), 'i_lebar_plano_cm' => round($layout->plano_h_mm / 10, 4), 'i_cut_per_plano' => $layout->up_count ?: 1]);
        }

        $this->refreshData();
        $this->partNo = $this->costing->parts->count();
        $this->hitung();
    }

    public function goPart(int $no): void
    {
        $this->partNo = max(1, min($no, $this->costing->parts->count()));
        unset($this->part, $this->context);
    }

    public function selectTier($id): void
    {
        $this->tierId = $id ? (int) $id : null;
        unset($this->tier);
    }

    // Toggle Manual <-> Ambil Data per part. Balik ke MANUAL cuma lepas link,
    // nilai yang sudah terisi tetap (officer lanjut edit manual).
    public function toggleDataSource(int $partId): void
    {
        $part = IqpCostingPart::where('costing_id', $this->costingId)->findOrFail($partId);
        $part->update($part->data_source === 'PRODEV' ? ['data_source' => 'MANUAL', 'prod_layout_group_id' => null] : ['data_source' => 'PRODEV']);
        $this->refreshData();
    }

    // $prodPartId WAJIB kalau group menaungi >1 part (Tool #10) -- alokasi biaya
    // proporsional by luas lewat effectiveCutFor(), tak boleh ditebak.
    public function pickLayoutGroup(int $partId, int $groupId, ?int $prodPartId = null): void
    {
        $part = IqpCostingPart::where('costing_id', $this->costingId)->findOrFail($partId);
        $group = ProdLayoutGroup::with('parts')->find($groupId);
        if (! $group) {
            return;
        }

        $isMulti = $group->parts->count() > 1;
        $prodPart = $prodPartId ? $group->parts->firstWhere('id_part', $prodPartId) : ($isMulti ? null : $group->parts->first());
        if ($isMulti && ! $prodPart) {
            session()->flash('error', 'Layout ini menaungi lebih dari 1 part (Tool #10) -- pilih part yang direpresentasikan baris costing ini dulu.');

            return;
        }

        $this->applyLayout($part, $group, $prodPart);
        $this->refreshData();
        $this->hitung();
    }

    public function removePart(int $partId): void
    {
        if (! $this->editable()) {
            return;
        }

        IqpCostingPartCoating::where('costing_part_id', $partId)->delete();
        IqpCostingPart::whereKey($partId)->where('costing_id', $this->costingId)->delete();
        $this->partNo = max(1, $this->partNo - 1);
        $this->refreshData();
    }

    public function updatePart(int $partId, string $field, $value): void
    {
        if (! $this->editable()) {
            return;
        }

        $allowed = [
            'part_name', 'part_type_code', 'qty_per_set', 'i_jumlah_warna', 'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm',
            'grammage_id', 'i_paper_price_override', 'i_dc_persen_override', 'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_isi_per_lb_kerja',
            'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm', 'sheet_rate_id', 'supplier_sheet_code', 'flute_type', 'i_sheet_panjang_cm', 'i_sheet_lebar_cm', 'i_lebar_trim_cm', 'i_isi_per_trim', 'i_discount_rm',
            'i_ongkos_cetak_rim', 'i_ongkos_plong_lb', 'i_ongkos_lem_pc', 'i_ongkos_hotprint_pc', 'i_ongkos_kupas_pc', 'i_ongkos_qc_pack_pc', 'i_ongkos_kirim_pc', 'packing_type_code', 'i_ongkos_packing_pc', 'jenis_packing',
            'pakai_laminasi', 'i_lam_sheet_flat_pc', 'sf_ef_rate_id', 'i_sf_ef_rate_override', 'i_lam_pakai_ukuran_sf', 'i_ongkos_lam_rp_cm2_sh', 'i_ongkos_lam_rp_cm2_sf', 'i_ongkos_laminasi_pc',
            'i_film_rate', 'i_film_faktor', 'i_ongkos_plate_warna', 'i_pisau_faktor', 'i_pisau_rate_rp',
            'i_ongkos_print_pc', 'i_ongkos_diecut_pc', 'i_ongkos_slitter_pc', 'i_ongkos_slotter_pc', 'join_type_code', 'i_ongkos_join_pc', 'i_ongkos_partisi_pc', 'i_ongkos_layer_pc', 'i_ongkos_pisau_pc', 'calc_notes',
        ];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        // Mode Ambil Data: geometri sumbernya layout ProDev, bukan input manual.
        $lockedWhenProdev = ['i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm'];
        if (in_array($field, $lockedWhenProdev, true)
            && IqpCostingPart::where('costing_id', $this->costingId)->whereKey($partId)->value('data_source') === 'PRODEV') {
            return;
        }

        $value = $value === '' ? null : $value;
        $data = [$field => $value, 'updated_by' => Auth::user()->login];
        match ($field) {
            'part_type_code' => $data['i_pisau_rate_rp'] = ProdPartType::where('part_type_code', $value)->value('pisau_rate_rp'),
            'grammage_id' => ($dc = (float) MatGrammage::whereKey($value)->value('duplex_dc_pct')) > 0 ? $data['i_dc_persen_override'] = $dc : null,
            'sf_ef_rate_id' => $data['i_sf_ef_rate_override'] = $value ? MatSheetRate::whereKey($value)->value('index_substance') : null,
            'join_type_code' => $data['i_ongkos_join_pc'] = $value ? MatJoinType::where('code', $value)->value('default_rate') : 0,
            'packing_type_code' => $data['i_ongkos_packing_pc'] = $value ? MatPackingType::where('code', $value)->value('default_rate') : 0,
            default => null,
        };

        IqpCostingPart::where('costing_id', $this->costingId)->whereKey($partId)->update($data);
        $this->refreshData();
    }

    public function addCoating(int $partId): void
    {
        if (! $this->editable()) {
            return;
        }

        $part = IqpCostingPart::where('costing_id', $this->costingId)->findOrFail($partId);
        IqpCostingPartCoating::create([
            'costing_part_id' => $part->id_costing_part, 'seq' => $part->coatings()->count() + 1, 'coating_type' => 'UV',
            'index_coating' => MatCoatingRate::where('coating_type', 'UV')->where('activate', true)->value('index_coating') ?? 0,
        ]);
        $this->refreshData();
    }

    public function removeCoating(int $coatingId): void
    {
        IqpCostingPartCoating::whereKey($coatingId)->whereHas('costingPart', fn ($q) => $q->where('costing_id', $this->costingId))->delete();
        $this->refreshData();
    }

    public function updateCoating(int $coatingId, string $field, $value): void
    {
        if (! in_array($field, ['coating_type', 'index_coating'], true)) {
            return;
        }

        $coating = IqpCostingPartCoating::whereKey($coatingId)->whereHas('costingPart', fn ($q) => $q->where('costing_id', $this->costingId))->first();
        if (! $coating) {
            return;
        }

        $coating->update($field === 'coating_type'
            ? ['coating_type' => $value, 'index_coating' => MatCoatingRate::where('coating_type', $value)->where('activate', true)->value('index_coating')]
            : [$field => $value === '' ? 0 : $value]);
        $this->refreshData();
    }

    public function updateTier(int $tierId, string $field, $value): void
    {
        if (! in_array($field, ['tolerance_pct', 'margin_pct', 'rounding_mode', 'rounding_step'], true)) {
            return;
        }

        IqpCostingPriceTier::where('costing_id', $this->costingId)->whereKey($tierId)->update([$field => $value === '' ? 0 : $value]);
        $this->refreshData();
    }

    public function updateHeader(string $field, $value): void
    {
        if (! $this->editable() || ! in_array($field, ['ppn_pct', 'ppn_mode', 'calc_notes'], true)) {
            return;
        }

        IqpCosting::whereKey($this->costingId)->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        $this->refreshData();
    }

    // "Paper Index (Rp/Rim)" mockup = override harga kertas part aktif (snapshot).
    public function setPaperIndex($value): void
    {
        if ($this->part) {
            $this->updatePart($this->part->id_costing_part, 'i_paper_price_override', $value);
            $this->hitung();
        }
    }

    public function hitung(): void
    {
        $this->engineError = '';
        $this->refreshData();

        try {
            (new CostingEngine)->run($this->costing);
        } catch (\Throwable $e) {
            $this->engineError = $e->getMessage();
        }

        $this->refreshData();
    }

    public function submitToDireksi(): void
    {
        if ($this->costing->doc_status !== 'CALCULATED') {
            return;
        }

        $this->costing->update(['doc_status' => 'PENDING_APPROVAL']);
        $this->refreshData();
    }

    public function addToQuotation()
    {
        $inquiry = $this->costing->inquiry;
        if (! $inquiry || ! $inquiry->customer_id) {
            return null;
        }

        $quotation = \App\Services\Sales\QuotationBuilder::findOrCreateDraft($inquiry->customer_id);
        \App\Services\Sales\QuotationBuilder::addInquiry($quotation, $inquiry);

        return $this->redirect(route('quotation.entry', ['quotation' => $quotation->id_quotation]));
    }

    // ───────────── routing ProDev vs ongkos manual (warning, bukan blokir) ─────────────
    protected const DC_PROCESS_FIELD_MAP = [
        'CETAK' => ['field' => 'i_ongkos_cetak_rim', 'label' => 'Ongkos Cetak /rim'],
        'PLONG' => ['field' => 'i_ongkos_plong_lb', 'label' => 'Ongkos Plong /lb'],
        'LEM MESIN' => ['field' => 'i_ongkos_lem_pc', 'label' => 'Ongkos Lem /pc'],
    ];

    #[Computed]
    public function partRouting()
    {
        $ids = $this->costing?->parts->pluck('prodev_part_id')->filter()->unique()->values() ?? collect();

        return $ids->isEmpty() ? collect() : ProdPartProcess::whereIn('part_id', $ids)->with('process')->orderBy('step_seq')->get()->groupBy('part_id');
    }

    public function routingNamesFor(IqpCostingPart $part)
    {
        if (! $part->prodev_part_id) {
            return collect();
        }

        return ($this->partRouting[$part->prodev_part_id] ?? collect())->pluck('process.process_name')->filter()
            ->map(fn ($n) => strtoupper(trim($n)))->unique()->values();
    }

    public function routingWarningsFor(IqpCostingPart $part): array
    {
        if ($part->part_type_code !== 'DC' || ! $part->prodev_part_id) {
            return [];
        }

        $names = $this->routingNamesFor($part);
        if ($names->isEmpty()) {
            return [];
        }

        $warnings = [];
        foreach (self::DC_PROCESS_FIELD_MAP as $proc => $meta) {
            $in = $names->contains($proc);
            $filled = (float) $part->{$meta['field']} > 0;
            if ($in && ! $filled) {
                $warnings[] = "Proses {$proc} ada di routing ProDev tapi \"{$meta['label']}\" masih 0 — isi dulu.";
            } elseif (! $in && $filled) {
                $warnings[] = "\"{$meta['label']}\" terisi tapi proses {$proc} tidak ada di routing ProDev — cek kembali, mungkin sisa data lama.";
            }
        }

        return $warnings;
    }

    // ───────────── angka tampilan ─────────────
    public function qty(): float
    {
        return (float) ($this->tier?->qty_pcs ?? 0);
    }

    // Baris "Process & Finishing" mockup: [proses, rate, basis, subtotal/order].
    public function processRows(): array
    {
        $p = $this->part;
        if (! $p) {
            return [];
        }

        $q = $this->qty() * max(1, (float) $p->qty_per_set);
        $cut = max(0.0001, (float) $p->i_cut_per_plano);
        $isi = max(1, (float) $p->i_isi_per_lb);
        $rows = [];
        $add = function (string $name, string $rate, string $basis, float $perPc) use (&$rows, $q) {
            if ($perPc != 0.0) {
                $rows[] = [$name, $rate, $basis, $perPc * $q];
            }
        };

        if (in_array($p->part_type_code, ['SHEET', 'SHEET_DIGITAL'], true)) {
            foreach (['Print (×2)' => (float) $p->c_print_cost_pc, 'Die Cut' => (float) $p->i_ongkos_diecut_pc, 'Slitter' => (float) $p->i_ongkos_slitter_pc, 'Slotter' => (float) $p->i_ongkos_slotter_pc, 'Join' => (float) $p->i_ongkos_join_pc, 'Partisi' => (float) $p->i_ongkos_partisi_pc, 'Layer' => (float) $p->i_ongkos_layer_pc, 'Pisau Diecut' => (float) $p->i_ongkos_pisau_pc] as $n => $v) {
                $add($n, 'Rp '.number_format($v, 2).'/Pc', number_format($q).' Pcs', $v);
            }
        } else {
            $add('Offset Printing', 'Rp '.number_format((float) $p->i_ongkos_cetak_rim, 2).'/Rim', number_format($q / (500 * $cut * $isi), 2).' Rim', (float) $p->c_cetak_cost_pc);
            $add('Plong (Die Cut)', 'Rp '.number_format((float) $p->i_ongkos_plong_lb, 2).'/Lb', number_format($q / $isi).' Lb', (float) $p->c_plong_cost_pc);
            $add('Lem (Gluing)', 'Rp '.number_format((float) $p->i_ongkos_lem_pc, 2).'/Pc', number_format($q).' Pcs', (float) $p->c_lem_cost_pc);
            $add('Hot Print', 'Rp '.number_format((float) $p->i_ongkos_hotprint_pc, 2).'/Pc', number_format($q).' Pcs', (float) $p->i_ongkos_hotprint_pc);
            $add('Laminasi Sheet (flat)', 'Rp '.number_format((float) $p->c_lam_flat_cost_pc, 2).'/Pc', number_format($q).' Pcs', (float) $p->c_lam_flat_cost_pc);
            $add('SF EF', 'Luas × Rate SF', number_format($q).' Pcs', (float) $p->c_sf_ef_cost_pc);
            $add('Ongkos Laminasi', 'Luas × Rp/cm²', number_format($q).' Pcs', (float) $p->c_ongkos_lam_pc);
        }
        foreach (['Kupas' => (float) $p->i_ongkos_kupas_pc, 'QC' => (float) $p->i_ongkos_qc_pack_pc, 'Kirim' => (float) $p->i_ongkos_kirim_pc, 'Packing' => (float) $p->i_ongkos_packing_pc] as $n => $v) {
            $add($n, 'Rp '.number_format($v, 2).'/Pc', number_format($q).' Pcs', $v);
        }

        return $rows;
    }

    // Nilai efektif tooling yang dipakai engine (default konstanta kalau input kosong).
    public function toolingInfo(): array
    {
        $p = $this->part;

        return [
            'film_ff' => $p->i_film_faktor !== null ? (float) $p->i_film_faktor : \App\Models\Sys\SysConstant::number('FILM_FACTOR_DEFAULT', 0.25),
            'film_rate' => $p->i_film_rate !== null ? (float) $p->i_film_rate : \App\Models\Sys\SysConstant::number('FILM_RATE_DEFAULT', 55),
            'plate' => (float) $p->i_ongkos_plate_warna > 0 ? (float) $p->i_ongkos_plate_warna : \App\Models\Sys\SysConstant::number('PLATE_PER_COLOR', 90000),
            'knife_ff' => (float) $p->i_pisau_faktor > 0 ? (float) $p->i_pisau_faktor : \App\Models\Sys\SysConstant::number('KNIFE_FACTOR_DEFAULT', 0.25),
            'knife_rate' => $p->i_pisau_rate_rp !== null ? (float) $p->i_pisau_rate_rp : (float) (ProdPartType::where('part_type_code', $p->part_type_code)->value('pisau_rate_rp') ?? 0),
        ];
    }

    public function materialLabel(): string
    {
        $p = $this->part;
        $bom = collect($this->context['bom'] ?? []);

        return MatGrammage::whereKey($p?->grammage_id)->value('grammage_name')
            ?? ($bom->firstWhere('primary', true)['material'] ?? ($bom->first()['material'] ?? '—'));
    }

    public function wizardSteps(): array
    {
        $prodPartId = IqpPart::where('inquiry_id', $this->inquiryId)->whereNotNull('prod_part_id')->value('prod_part_id');

        return [
            'inquiry' => ['label' => '1. Inquiry', 'url' => route('inquiry.form', ['id' => $this->inquiryId, 'view' => 1])],
            'development' => ['label' => '2. Development', 'url' => $prodPartId ? route('production.prodev', ['part' => $prodPartId]) : null, 'reason' => 'Belum ada part yang dikirim ke ProDev.'],
            'costing' => ['label' => '3. Costing', 'url' => route('inquiry.costing', ['inquiry' => $this->inquiryId])],
            'mastercard' => ['label' => 'Master Card', 'url' => $prodPartId ? route('production.old-prodev.print', ['part' => $prodPartId]) : null, 'reason' => 'Belum ada part yang dikirim ke ProDev.'],
            'analytics' => ['label' => 'Analytics', 'url' => null, 'reason' => 'Modul Analytics belum dibangun.'],
        ];
    }
};
?>

@php
    $c = $this->costing;
    $lock = ! $this->canSeeFormula;
    $n2 = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.');
    $rp = fn ($v) => $v === null ? '—' : 'Rp '.number_format((float) $v, 2, ',', '.');
    // Semua variabel dihitung di sini (bukan di awal blok @else) -- hindari bug compiler
    // Livewire v4 utk @php sbg baris pertama dalam @if/@else (memory wirekey_inline_php).
    $inq = $c?->inquiry;
    $t = $this->tier;
    $p = $this->part;
    $ctx = $this->context;
    $geo = $ctx['geometry'] ?? null;
    $ro = $this->readOnly;
    $lk = $this->lookups;
    $qty = $this->qty();
    $typeLabel = ['NEW' => 'Produk Baru', 'RECALC' => 'Hitung Ulang', 'REVISE' => 'Revisi'][$c?->calc_type] ?? $c?->calc_type;
    $statusCls = match ($c?->doc_status) { 'CALCULATED' => 'bg-[#eaf2ff] text-[#1d4ed8] border-[#bfdbfe]', 'PENDING_APPROVAL' => 'bg-[#fef9c3] text-[#854d0e] border-[#fde68a]', 'PUBLISHED' => 'bg-[#dcfce7] text-[#166534] border-[#bbf7d0]', default => 'bg-[#fef9c3] text-[#854d0e] border-[#fde68a]' };
    $card = 'bg-white rounded-xl shadow-sm border border-[#e2e8f0] p-4';
    $h2 = 'text-[12px] font-bold text-[#0f172a] uppercase tracking-wider mb-3 pb-2 border-b border-[#e2e8f0] flex items-center gap-2';
    $partCount = $c?->parts->count() ?? 0;
    $toolOrder = (float) ($p?->c_tooling_cost_order ?? 0);
    $hppOrder = (float) ($t?->c_hpp_pc ?? 0) * $qty;
    $isDcFam = in_array($p?->part_type_code, ['DC', 'DC+SF', 'DC+SHEET', 'KRAFT'], true);
    $isSheet = in_array($p?->part_type_code, ['SHEET', 'SHEET_DIGITAL'], true);
    $isLam = in_array($p?->part_type_code, ['DC+SF', 'DC+SHEET'], true);
    $geoLock = $ro || $p?->data_source === 'PRODEV';
    $pid = $p?->id_costing_part ?? 0;
@endphp

<div>
    <x-prodev.wizard-bar current="costing" :steps="$this->wizardSteps()" :back-url="route('inquiry.costing.list')" />

    @if ($view)
        {{-- ═════ CROSS-NAV READ-ONLY: status + harga final saja (Sisi A rahasia) ═════ --}}
        <div class="max-w-[720px] mx-auto my-10 px-4">
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">🧮 Product Costing (lihat saja)</h3>
                <div class="p-4">
                    @if (! $c)
                        <div class="text-[12.5px] px-3 py-3 rounded-md bg-[#eef0f3] text-[#5a6272]">⏳ <b>Belum Diproses</b> — Costing utk Inquiry ini belum pernah dibuka/dihitung Tim Costing.</div>
                    @elseif (! $c->published_flag)
                        <div class="text-[12.5px] px-3 py-3 rounded-md bg-[#eef0f3] text-[#5a6272]">⏳ <b>Belum Diproses</b> ({{ $c->doc_status }}) — harga baru kelihatan setelah publish Direksi.</div>
                    @else
                        <table class="w-full text-[12.5px]">
                            <thead><tr><th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase border-b">Qty</th><th class="text-right px-2 py-1.5 text-[9.5px] text-muted uppercase border-b">Harga / pcs</th></tr></thead>
                            <tbody>
                                @foreach ($c->priceTiers as $t)
                                    <tr><td class="px-2 py-1.5 border-b font-semibold">{{ number_format($t->qty_pcs) }}{{ $t->is_primary ? ' ★' : '' }}</td><td class="px-2 py-1.5 border-b text-right font-bold text-navy">{{ $rp($t->price_per_pcs) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    @else
    <div class="max-w-[1320px] mx-auto my-4 px-4 space-y-4 text-[13px]">

        {{-- ═════ Header (mockup: breadcrumb + judul + badge) + tombol alur (deviasi #1) ═════ --}}
        <div class="{{ $card }} !py-3 flex flex-wrap items-center gap-3">
            <div>
                <div class="text-[12px] text-[#64748b] mb-0.5">ERP System / Product Costing / <b class="text-[#334155]">{{ $c->doc_no ?? $inq?->inquiry_no }}</b></div>
                <h1 class="text-[20px] font-bold text-[#0f172a] flex items-center gap-2">🧮 Kalkulasi HPP (Costing)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium border {{ $statusCls }}">{{ $c->doc_status }}</span>
                    @if ($c->reversed_at)<span class="px-2 py-0.5 rounded-full text-[10px] bg-[#f1f5f9] text-[#475569]">dari Instant (Reverse)</span>@endif
                </h1>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" wire:click="hitung" data-dirty-save @disabled($ro) class="px-4 py-2 rounded-lg text-[12px] font-semibold bg-[#2563eb] text-white cursor-pointer disabled:opacity-40">⚙ Hitung</button>
                <button type="button" wire:click="submitToDireksi" @disabled($c->doc_status !== 'CALCULATED') class="px-4 py-2 rounded-lg text-[12px] font-semibold bg-[#16a34a] text-white cursor-pointer disabled:opacity-40">Submit ke Direksi</button>
                <button type="button" wire:click="addToQuotation" class="px-4 py-2 rounded-lg text-[12px] font-semibold bg-[#f1f5f9] border border-[#cbd5e1] cursor-pointer">＋ Quotation</button>
            </div>
        </div>

        @if ($engineError)<div class="bg-red-50 border border-red-300 text-red-700 text-xs px-3.5 py-2.5 rounded-md">⚠ {{ $engineError }}</div>@endif
        @if (session('error'))<div class="bg-red-50 border border-red-300 text-red-700 text-xs px-3.5 py-2.5 rounded-md">⚠ {{ session('error') }}</div>@endif

        {{-- ═════ Baris 1: Doc & Customer | Product Specification (+InqProd) | Dynamic Parameters ═════ --}}
        <div class="[display:grid] grid-cols-3 gap-4">
            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">📄 Doc &amp; Customer Info</h2>
                <div class="space-y-2 text-[13px]">
                    @foreach (['Doc No:' => $c->doc_no ?? '—', 'Doc Date:' => $c->doc_date?->format('d/m/Y') ?? '—', 'Inquiry:' => $inq?->inquiry_no, 'Customer:' => $inq?->customer?->customer_name ?? $inq?->prospect_name, 'Type:' => $typeLabel.' (Rev '.($inq?->revision_no ?? 0).')', 'PIC Sales:' => $inq?->pic_sales ?: '—'] as $k => $v)
                        <div class="[display:grid] grid-cols-2 gap-2"><span class="text-[#64748b]">{{ $k }}</span><span class="font-semibold {{ $k === 'Customer:' ? 'text-[#1d4ed8]' : '' }}">{{ $v ?: '—' }}</span></div>
                    @endforeach
                </div>
            </div>

            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">📦 Product Specification
                    @if ($partCount > 1)
                        <span class="ml-auto flex gap-1 normal-case">
                            @for ($i = 1; $i <= $partCount; $i++)
                                <button type="button" wire:click="goPart({{ $i }})" class="px-1.5 rounded text-[11px] cursor-pointer {{ $i === min($partNo, $partCount) ? 'bg-[#2c3e69] text-white' : 'bg-[#f1f5f9]' }}">{{ $i }}</button>
                            @endfor
                            <span class="text-[10px] text-[#64748b] self-center">of {{ $partCount }}</span>
                        </span>
                    @endif
                </h2>
                <div class="space-y-2">
                    <div class="[display:grid] grid-cols-[90px_1fr] gap-2"><span class="text-[#64748b]">Product:</span><span class="font-semibold leading-tight">{{ $inq?->product_draft_name }}</span></div>
                    <div class="[display:grid] grid-cols-[90px_1fr] gap-2"><span class="text-[#64748b]">Part Name:</span><span class="font-medium">{{ $p?->part_name ?: '—' }} <span class="text-[10px] px-1.5 rounded bg-[#dbeafe] text-[#1d4ed8]">{{ $p?->part_type_code }}</span></span></div>
                    <div class="pt-2 border-t border-[#e2e8f0]">
                        <div class="flex justify-between text-[11px] text-[#64748b]"><span>Panjang (mm)</span><span>Lebar (mm)</span><span>Tinggi (mm)</span></div>
                        <div class="flex justify-between font-mono">{{-- dimensi part, fallback header --}}
                            <span>{{ $n2($p?->i_dim_panjang_mm ?: $c->i_dim_panjang_mm) }}</span><span>{{ $n2($p?->i_dim_lebar_mm ?: $c->i_dim_lebar_mm) }}</span><span>{{ $n2($p?->i_dim_tinggi_mm ?: $c->i_dim_tinggi_mm) }}</span>
                        </div>
                    </div>
                    @if ($ctx)
                        <div class="pt-2 border-t border-[#e2e8f0] [display:grid] grid-cols-[1fr_64px] gap-2 text-[12px]">
                            <div class="[display:grid] grid-cols-[80px_1fr] gap-x-2 gap-y-0.5">
                                <span class="text-[#64748b]">Warna:</span><b>{{ $ctx['iqpPart']?->cfg_color ?: ($ctx['prodPart']?->cfg_color ?: '—') }}</b>
                                <span class="text-[#64748b]">Tinta:</span><b>{{ $ctx['iqpPart']?->cfg_ink ?: count($ctx['inks']).' pass' }}</b>
                                <span class="text-[#64748b]">Finishing:</span><b>{{ collect($ctx['finishings'])->pluck('name')->unique()->implode(', ') ?: '—' }}</b>
                                <span class="text-[#64748b]">Material:</span><b>{{ $this->materialLabel() }}</b>
                                <span class="text-[#64748b]">Qty/Set:</span><b>{{ (float) $p->qty_per_set }} pcs</b>
                                <span class="text-[#64748b]">Sumber:</span><b>{{ $inq?->inquiry_no }}{{ $geo ? ' · ProDev '.$geo['mc'].' ('.$geo['status'].')' : ' · belum ada layout ProDev' }}</b>
                            </div>
                            @if ($ctx['artworkUrl'])
                                <a href="{{ $ctx['artworkUrl'] }}" target="_blank"><img src="{{ $ctx['artworkUrl'] }}" alt="artwork" class="w-16 h-20 object-cover rounded border border-[#cbd5e1]"></a>
                            @else
                                <div class="w-16 h-20 rounded border border-dashed border-[#cbd5e1] flex items-center justify-center text-[9px] text-[#94a3b8] text-center">tanpa artwork</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-[#eff6ff] rounded-xl shadow-sm border border-[#dbeafe] p-4">
                <h2 class="text-[12px] font-bold text-[#1e3a8a] uppercase tracking-wider mb-3 pb-2 border-b border-[#bfdbfe]">⚙ Dynamic Parameters</h2>
                <div class="[display:grid] grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-[#475569] mb-1">Qty Order (Pcs)</label>
                        <select wire:change="selectTier($event.target.value)" class="w-full bg-white border border-[#bfdbfe] rounded-md px-2 py-1.5 font-medium">
                            @foreach ($c->priceTiers as $pt)
                                <option value="{{ $pt->id_price_tier }}" @selected($t?->id_price_tier === $pt->id_price_tier)>{{ number_format($pt->qty_pcs) }}{{ $pt->is_primary ? ' ★' : '' }}</option>
                            @endforeach
                        </select>
                        @if ($t && ! $t->tier_id)
                            <div class="text-[10px] text-[#b45309] mt-0.5">⚠ Inquiry belum punya tier qty — default 1.000</div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-[#475569] mb-1">Margin (%)</label>
                        <input type="number" step="any" value="{{ (float) $t?->margin_pct }}" @readonly($ro) wire:change="updateTier({{ $t?->id_price_tier ?? 0 }}, 'margin_pct', $event.target.value)" class="w-full bg-white border border-[#bfdbfe] rounded-md px-2 py-1.5 font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-[#475569] mb-1">Paper Index (Rp/Rim)</label>
                        <input type="number" step="any" value="{{ $p?->i_paper_price_override !== null ? (float) $p->i_paper_price_override : '' }}" placeholder="master" @readonly($ro || ! $p) wire:change="setPaperIndex($event.target.value)" class="w-full bg-white border border-[#bfdbfe] rounded-md px-2 py-1.5 font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-[#475569] mb-1">PPN (%)</label>
                        <input type="number" step="any" value="{{ (float) $c->ppn_pct }}" @readonly($ro) wire:change="updateHeader('ppn_pct', $event.target.value)" class="w-full bg-white border border-[#bfdbfe] rounded-md px-2 py-1.5 font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-[#475569] mb-1">Toleransi (%)</label>
                        <input type="number" step="any" value="{{ (float) $t?->tolerance_pct }}" @readonly($ro) wire:change="updateTier({{ $t?->id_price_tier ?? 0 }}, 'tolerance_pct', $event.target.value)" class="w-full bg-white border border-[#bfdbfe] rounded-md px-2 py-1.5 font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-[#475569] mb-1">Rounding</label>
                        <select @disabled($ro) wire:change="updateTier({{ $t?->id_price_tier ?? 0 }}, 'rounding_mode', $event.target.value)" class="w-full bg-white border border-[#bfdbfe] rounded-md px-2 py-1.5 font-medium">
                            @foreach (['NONE' => 'TANPA', 'NEAREST' => 'NEAREST', 'UP' => 'CEILING', 'DOWN' => 'FLOOR'] as $rv => $rl)
                                <option value="{{ $rv }}" @selected($t?->rounding_mode === $rv)>{{ $rl }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═════ Tambah part (dari part Inquiry / manual) ═════ --}}
        @if (! $ro)
            <div class="flex items-center gap-2 text-[12px]">
                @if ($this->inquiryParts->isNotEmpty())
                    <select id="add-from-part" class="px-2 py-1 border border-dashed border-[#9cc0f0] rounded text-navy">
                        <option value="">— tambah part dari Inquiry —</option>
                        @foreach ($this->inquiryParts as $ip)
                            <option value="{{ $ip->id_part }}">{{ $ip->part_name ?: 'Part #'.$ip->part_seq }}{{ $ip->prod_part_id ? ' · ProDev' : '' }}</option>
                        @endforeach
                    </select>
                    <button type="button" @click="const s=document.getElementById('add-from-part'); if(s.value){ $wire.addPartFromInquiry(parseInt(s.value)); s.value=''; }" class="font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Part</button>
                @endif
                <button type="button" wire:click="addPart('DC')" class="font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Part Manual</button>
                @if ($p)
                    <button type="button" wire:click="removePart({{ $p->id_costing_part }})" wire:confirm="Hapus part ini dari costing?" class="ml-auto text-red-500 cursor-pointer">✕ Hapus part ini</button>
                @endif
            </div>
        @endif

        @if (! $p)
            <div class="{{ $card }} text-center text-[#64748b] py-10">Belum ada part costing. Part Inquiry yang sudah punya layout ProDev otomatis muncul di sini; selain itu tambah lewat "＋ Part".</div>
        @else
        {{-- ═════ Baris 2: Visual Imposition (ProDev asli) | Granular Cost Breakdown ═════ --}}
        <div class="[display:grid] grid-cols-2 gap-4" wire:key="part-{{ $p->id_costing_part }}">
            <div class="{{ $card }} flex flex-col">
                <h2 class="{{ $h2 }}">🗂 Visual Imposition Layout (ProDev)
                    <button type="button" wire:click="toggleDataSource({{ $p->id_costing_part }})" @disabled($ro) class="ml-auto normal-case text-[10px] font-bold px-2 py-0.5 rounded cursor-pointer {{ $p->data_source === 'PRODEV' ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#f1f5f9] text-[#64748b] border border-[#cbd5e1]' }}">{{ $p->data_source === 'PRODEV' ? '🔗 Ambil Data (ProDev)' : '✎ Manual' }}</button>
                </h2>
                @if ($p->data_source === 'PRODEV' && ! $ro)
                    <select onchange="if(this.value) $wire.pickLayoutGroup({{ $p->id_costing_part }}, parseInt(this.value))" class="text-[11px] px-2 py-1 border border-[#c3d8f5] rounded mb-2">
                        <option value="">— ganti layout ProDev (CONFIRMED) —</option>
                        @foreach ($this->availableLayoutGroups as $g)
                            <option value="{{ $g->id_layout_group }}" @selected($p->prod_layout_group_id === $g->id_layout_group)>{{ $g->mastercard_code }} · {{ $g->product?->product_name }} · {{ $g->up_count }} UP</option>
                        @endforeach
                    </select>
                @endif
                @if ($geo)
                    <div class="text-[12px] text-[#64748b] [display:grid] grid-cols-2 gap-x-4 gap-y-1 mb-2">
                        <div><b class="text-[#334155]">Plano Size:</b> {{ (float) $geo['plano_l'] }} × {{ (float) $geo['plano_w'] }} cm {{ $geo['plano_name'] ? '('.$geo['plano_name'].')' : '' }}</div>
                        <div><b class="text-[#334155]">Material:</b> {{ $this->materialLabel() }}</div>
                        <div><b class="text-[#334155]">Print Area:</b> {{ (float) $geo['print_l'] }} × {{ (float) $geo['print_w'] }} cm</div>
                        <div><b class="text-[#334155]">Imposition:</b> {{ rtrim(rtrim(number_format($geo['cut'], 2), '0'), '.') }} Sheet / Plano ({{ $geo['isi'] }} Up / Sheet)</div>
                    </div>
                    <div class="[display:grid] grid-cols-4 gap-2 bg-[#eff6ff] border border-[#bfdbfe] rounded-lg p-2 text-[12px] mb-2">
                        <div><div class="text-[#64748b] text-[11px]">Print Size</div><b>{{ (float) $geo['print_l'] }} × {{ (float) $geo['print_w'] }} cm</b></div>
                        <div><div class="text-[#64748b] text-[11px]">Metode</div><b>{{ $geo['method'] ?? '—' }}</b></div>
                        <div><div class="text-[#64748b] text-[11px]">UP</div><b>{{ $geo['up'] }}</b></div>
                        <div><div class="text-[#64748b] text-[11px]">Efisiensi / Waste</div><b>{{ $n2($geo['efficiency']) }}% / {{ $n2($geo['waste']) }}%</b></div>
                        <div class="col-span-2"><div class="text-[#64748b] text-[11px]">Pisau</div><b>{{ $geo['knife'] ?: '—' }}</b> {{ $geo['knife_size'] ? '('.$geo['knife_size'].' cm)' : '' }}</div>
                        <div><div class="text-[#64748b] text-[11px]">Meter Lari Pisau</div><b>{{ $geo['die_meter'] !== null ? $n2($geo['die_meter']).' m' : '—' }}</b></div>
                        <div><div class="text-[#64748b] text-[11px]">Status Layout</div><b class="{{ $geo['status'] === 'CONFIRMED' ? 'text-[#16a34a]' : 'text-[#b45309]' }}">{{ $geo['status'] }}</b></div>
                    </div>
                    <div class="flex-1 bg-[#f1f5f9] rounded-lg border border-[#cbd5e1] p-3 flex items-center justify-center">
                        <x-costing.imposition-svg :group="$ctx['group']" :geo="$geo" />
                    </div>
                @else
                    <div class="flex-1 bg-[#f8fafc] rounded-lg border border-dashed border-[#cbd5e1] p-6 text-center text-[#64748b]">Part ini belum punya layout ProDev — ilustrasi & print size muncul setelah layout dibuat di ProDev. Sementara isi geometri di "Parameter Ongkos" di bawah.</div>
                @endif
            </div>

            <div class="{{ $card }} flex flex-col">
                <h2 class="{{ $h2 }}">🧾 Granular Cost Breakdown <span class="ml-auto normal-case text-[11px] text-[#64748b]">Qty {{ number_format($qty * max(1, (float) $p->qty_per_set)) }}</span></h2>
                @if ($lock)
                    <div class="bg-[#f4f6fa] border border-[#d7dbe2] rounded p-4 text-[#64748b]">🔒 Breakdown biaya hanya untuk login yang ditunjuk (konstanta PROCOST_FORMULA_LOGINS). Harga jual akhir tetap tampil di bawah.</div>
                @else
                    <div class="space-y-3">
                        <div class="border border-[#f1f5f9] rounded-lg overflow-hidden">
                            <div class="bg-[#f8fafc] px-4 py-2 font-semibold text-[#334155] border-b border-[#f1f5f9]">🖨 Materials</div>
                            <div class="p-3 [display:grid] grid-cols-2 gap-y-1.5">
                                @if (in_array($p->part_type_code, ['SHEET', 'SHEET_DIGITAL'], true))
                                    <div class="text-[#64748b]">Sheet (trim × panjang):</div><div class="text-right">{{ $n2($p->c_lebar_trim_cm) }} × {{ $n2($p->c_panjang_sheet_cm) }} cm</div>
                                    <div class="text-[#64748b]">Running Meter / Disc:</div><div class="text-right">{{ $n2($p->c_running_meter) }} m / {{ $n2((float) $p->c_discount_rm * 100) }}%</div>
                                    <div class="text-[#64748b]">Harga Sheet /pc:</div><div class="text-right">{{ $rp($p->c_sheet_cost_pc) }}</div>
                                @else
                                    <div class="text-[#64748b]">Plano Sheets Needed:</div><div class="text-right font-medium">{{ number_format($qty * max(1, (float) $p->qty_per_set) / max(0.0001, (float) $p->i_cut_per_plano * max(1, (float) $p->i_isi_per_lb)), 0) }} Sheets</div>
                                    <div class="text-[#64748b]">Cost per Plano:</div><div class="text-right font-medium">{{ $rp((float) $p->c_paper_cost_pc * (float) $p->i_cut_per_plano * max(1, (float) $p->i_isi_per_lb)) }}</div>
                                @endif
                                <div class="font-bold mt-1 pt-1 border-t border-[#f1f5f9]">Total Material Cost:</div>
                                <div class="text-right font-bold text-[#1d4ed8] mt-1 pt-1 border-t border-[#f1f5f9]">{{ $rp(((float) $p->c_paper_cost_pc + (float) $p->c_sheet_cost_pc) * $qty * max(1, (float) $p->qty_per_set)) }}</div>
                            </div>
                        </div>

                        <div class="border border-[#f1f5f9] rounded-lg overflow-hidden">
                            <div class="bg-[#f8fafc] px-4 py-2 font-semibold text-[#334155] border-b border-[#f1f5f9]">🎨 Process &amp; Finishing</div>
                            <table class="w-full text-[12px]">
                                <thead class="text-[11px] text-[#64748b] border-b border-[#f1f5f9]"><tr><th class="px-3 py-1.5 text-left font-medium">Process</th><th class="px-3 py-1.5 text-right font-medium">Rate</th><th class="px-3 py-1.5 text-right font-medium">Basis</th><th class="px-3 py-1.5 text-right font-medium">Subtotal</th></tr></thead>
                                <tbody>
                                    @foreach ($this->processRows() as [$name, $rate, $basis, $sub])
                                        <tr class="border-b border-[#f8fafc]"><td class="px-3 py-1.5 font-medium">{{ $name }}</td><td class="px-3 py-1.5 text-right">{{ $rate }}</td><td class="px-3 py-1.5 text-right">{{ $basis }}</td><td class="px-3 py-1.5 text-right font-medium">{{ $rp($sub) }}</td></tr>
                                    @endforeach
                                    @foreach ($p->coatings as $co)
                                        <tr wire:key="coat-{{ $co->id_coating }}" class="border-b border-[#f8fafc]">
                                            <td class="px-3 py-1.5 font-medium">{{ $co->coating_type }}
                                                <input type="number" step="any" value="{{ (float) $co->index_coating }}" @readonly($ro) wire:change="updateCoating({{ $co->id_coating }}, 'index_coating', $event.target.value)" title="Index coating (Rp/cm²)" class="ml-1 w-16 text-[11px] bg-[#f1f5f9] border border-[#e2e8f0] rounded px-1 text-right">
                                            </td>
                                            <td class="px-3 py-1.5 text-right">Index × Area</td>
                                            <td class="px-3 py-1.5 text-right">{{ number_format($qty * max(1, (float) $p->qty_per_set) / max(1, (float) $p->i_isi_per_lb)) }} Sht</td>
                                            <td class="px-3 py-1.5 text-right font-medium">{{ $rp((float) $co->c_cost_per_pc * $qty * max(1, (float) $p->qty_per_set)) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-[#f8fafc]"><tr><td colspan="3" class="px-3 py-2 text-right font-bold">Total Process:</td><td class="px-3 py-2 text-right font-bold text-[#1d4ed8]">{{ $rp(((float) $p->c_process_cost_pc + (float) $p->c_total_coating_pc) * $qty * max(1, (float) $p->qty_per_set)) }}</td></tr></tfoot>
                            </table>
                        </div>

                        @if ($toolOrder > 0)
                            <div class="border border-[#f1f5f9] rounded-lg overflow-hidden">
                                <div class="bg-[#f8fafc] px-4 py-2 font-semibold text-[#334155] border-b border-[#f1f5f9]">🔧 Tooling (Film · Plate · Pisau)</div>
                                <table class="w-full text-[12px]">
                                    <tbody>
                                        <tr class="border-b border-[#f8fafc]"><td class="px-3 py-1.5">Film</td><td class="px-3 py-1.5 text-right text-[#64748b]">{{ $this->toolingInfo()['film_ff'] }}² × {{ $n2($p->c_area_cetak_cm2) }} cm² × {{ $this->toolingInfo()['film_rate'] }}</td><td class="px-3 py-1.5 text-right">{{ $rp($p->c_film_cost_order) }}</td></tr>
                                        <tr class="border-b border-[#f8fafc]"><td class="px-3 py-1.5">Plate</td><td class="px-3 py-1.5 text-right text-[#64748b]">{{ $n2($this->toolingInfo()['plate']) }} × {{ $p->i_jumlah_warna }} warna</td><td class="px-3 py-1.5 text-right">{{ $rp($p->c_plate_cost_order) }}</td></tr>
                                        <tr class="border-b border-[#f8fafc]"><td class="px-3 py-1.5">Pisau</td><td class="px-3 py-1.5 text-right text-[#64748b]">{{ $this->toolingInfo()['knife_ff'] }} × {{ $n2($this->toolingInfo()['knife_rate']) }}</td><td class="px-3 py-1.5 text-right">{{ $rp($p->c_knife_cost_order) }}</td></tr>
                                    </tbody>
                                    <tfoot class="bg-[#f8fafc]"><tr><td colspan="2" class="px-3 py-2 text-right font-bold">Total Tooling:</td><td class="px-3 py-2 text-right font-bold text-[#1d4ed8]">{{ $rp($toolOrder) }}</td></tr></tfoot>
                                </table>
                            </div>
                        @endif

                        <div class="text-[10px] font-mono whitespace-pre-line text-[#64748b] bg-[#f8fafc] border border-[#e2e8f0] rounded p-2">{{ $p->description_calc }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ═════ Info InqProd & ProDev (READ-ONLY, accordion supaya compact) ═════ --}}
        <div class="[display:grid] grid-cols-2 gap-4">
            <x-ui.accordion title="BOM — Bill of Material (InqProd)" :count="count($ctx['bom'])" :open="true" tone="blue">
                <table class="w-full text-[12px]">
                    <thead class="text-[10px] uppercase text-[#64748b] bg-[#f8fafc]"><tr><th class="p-1.5 text-left">Layer</th><th class="p-1.5 text-left">Material</th><th class="p-1.5 text-left">Merk</th><th class="p-1.5 text-left">Gramatur/Flute</th><th class="p-1.5 text-left">Ukuran (cm)</th><th class="p-1.5 text-left">Qty/Sheet</th><th class="p-1.5 text-left">Sumber</th></tr></thead>
                    <tbody>
                        @forelse ($ctx['bom'] as $b)
                            <tr class="border-t border-[#f1f5f9]"><td class="p-1.5">{{ $b['layer'] }}{{ $b['primary'] ? ' ★' : '' }}</td><td class="p-1.5 font-medium">{{ $b['material'] }}</td><td class="p-1.5">{{ $b['brand'] ?: '—' }}</td><td class="p-1.5">{{ $b['spec'] ?: '—' }}</td><td class="p-1.5">{{ $b['size'] ?: '—' }}</td><td class="p-1.5">{{ $b['qty'] ?: '—' }}</td><td class="p-1.5">{{ $b['source'] }}{{ $b['owner'] === 'CUSTOMER' ? ' · bahan customer' : '' }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="p-2 text-[#64748b]">Belum ada BOM di Inquiry untuk part ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.accordion>

            <x-ui.accordion title="BOP — Routing Proses ({{ $ctx['bop'][0]['origin'] ?? 'ProDev' }})" :count="count($ctx['bop'])" :open="true" tone="green">
                <table class="w-full text-[12px]">
                    <thead class="text-[10px] uppercase text-[#64748b] bg-[#f8fafc]"><tr><th class="p-1.5 text-left">#</th><th class="p-1.5 text-left">Proses</th><th class="p-1.5 text-left">Mesin</th><th class="p-1.5 text-left">In-house / Subkon</th><th class="p-1.5 text-right">Target/jam</th><th class="p-1.5 text-right">Faktor</th><th class="p-1.5 text-right">Waste</th></tr></thead>
                    <tbody>
                        @forelse ($ctx['bop'] as $r)
                            <tr class="border-t border-[#f1f5f9]" title="{{ $r['note'] }}"><td class="p-1.5">{{ $r['branch'] ? $r['branch'].'-' : '' }}{{ $r['step'] }}</td><td class="p-1.5 font-medium">{{ $r['process'] }}</td><td class="p-1.5">{{ $r['machine'] ?: '—' }}</td>
                                <td class="p-1.5">@if ($r['subcon'])<span class="px-1.5 rounded bg-[#fef3c7] text-[#92400e] font-semibold">Subkon: {{ $r['subcon'] }}</span>@else<span class="text-[#16a34a]">In-house</span>@endif</td>
                                <td class="p-1.5 text-right">{{ $r['target'] ? number_format($r['target']) : '—' }}</td><td class="p-1.5 text-right">{{ $r['factor'] !== null ? (float) $r['factor'] : '—' }}</td><td class="p-1.5 text-right">{{ $r['waste'] !== null ? (float) $r['waste'].'%' : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="p-2 text-[#64748b]">Belum ada routing di ProDev/Inquiry untuk part ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @foreach ($this->routingWarningsFor($p) as $w)
                    <div class="mt-1 text-[11px] text-[#8a5a00] bg-[#fff8ec] border border-[#f0dcae] rounded px-2 py-1">⚠ {{ $w }}</div>
                @endforeach
            </x-ui.accordion>

            <x-ui.accordion title="Tinta (Ink)" :count="count($ctx['inks'])" tone="slate">
                <table class="w-full text-[12px]">
                    <thead class="text-[10px] uppercase text-[#64748b] bg-[#f8fafc]"><tr><th class="p-1.5 text-left">Pass</th><th class="p-1.5 text-left">Unit</th><th class="p-1.5 text-left">Tinta</th><th class="p-1.5 text-right">Coverage</th></tr></thead>
                    <tbody>
                        @forelse ($ctx['inks'] as $ink)
                            <tr class="border-t border-[#f1f5f9]"><td class="p-1.5">{{ $ink->pass_label ?: $ink->pass_seq }}</td><td class="p-1.5">{{ $ink->unit_no }}</td><td class="p-1.5"><span class="inline-block w-3 h-3 rounded-sm align-middle border border-[#cbd5e1]" style="background: {{ $ink->color_hex ?: '#fff' }}"></span> {{ $ink->ink_name }}</td><td class="p-1.5 text-right">{{ $ink->coverage_pct !== null ? (float) $ink->coverage_pct.'%' : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="p-2 text-[#64748b]">Belum ada data tinta.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.accordion>

            <x-ui.accordion title="Finishing & Detail Part ProDev" :count="count($ctx['finishings'])" tone="amber">
                <table class="w-full text-[12px] mb-2">
                    <tbody>
                        @forelse ($ctx['finishings'] as $f)
                            <tr class="border-t border-[#f1f5f9]"><td class="p-1.5 font-medium">{{ $f['name'] }}</td><td class="p-1.5">{{ $f['attr'] ?: '—' }}</td><td class="p-1.5 text-[#64748b]">{{ $f['notes'] }}</td><td class="p-1.5 text-right">{{ $f['cost'] !== null ? $rp($f['cost']) : '' }}</td><td class="p-1.5 text-[10px] text-[#64748b]">{{ $f['origin'] }}</td></tr>
                        @empty
                            <tr><td class="p-2 text-[#64748b]">Belum ada finishing.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($pp = $ctx['prodPart'])
                    <div class="[display:grid] grid-cols-3 gap-x-3 gap-y-1 text-[12px] border-t border-[#e2e8f0] pt-2">
                        <div><span class="text-[#64748b]">Kode Pisau:</span> <b>{{ $pp->die_code ?: '—' }}</b></div>
                        <div><span class="text-[#64748b]">Ukuran Pisau:</span> <b>{{ $pp->die_w_cm ? (float) $pp->die_w_cm.' × '.(float) $pp->die_h_cm.' cm' : '—' }}</b></div>
                        <div><span class="text-[#64748b]">Bahan Mentah:</span> <b>{{ $pp->sheet_raw_w_cm ? (float) $pp->sheet_raw_w_cm.' × '.(float) $pp->sheet_raw_h_cm.' cm' : '—' }}</b></div>
                        <div><span class="text-[#64748b]">Sambungan:</span> <b>{{ $pp->joint_type ?: '—' }}</b></div>
                        <div><span class="text-[#64748b]">ECMA:</span> <b>{{ $pp->ecma_code ?: '—' }}</b></div>
                        <div><span class="text-[#64748b]">Lidah:</span> <b>{{ $pp->flap_side ? $pp->flap_side.' '.(float) $pp->flap_length_mm.' mm' : '—' }}</b></div>
                        <div class="col-span-3"><span class="text-[#64748b]">Warna Unit:</span> <b>{{ implode(', ', array_filter($pp->color_set_1 ?? [])) ?: '—' }}{{ ! empty($pp->color_set_2) ? ' / '.implode(', ', array_filter($pp->color_set_2)) : '' }}</b></div>
                    </div>
                @endif
            </x-ui.accordion>
        </div>

        {{-- ═════ Parameter ongkos (input officer; geometri ProDev terkunci) ═════ --}}
        <x-ui.accordion title="✎ Parameter Ongkos & Bahan (input officer)" :open="true" tone="blue">
            <div class="[display:grid] grid-cols-6 gap-x-2 gap-y-1.5 text-xs">
                <x-costing.field label="Nama Part" key="part_name" :value="$p->part_name" type="text" :args="[$pid]" :readonly="$ro" />
                <x-costing.field label="Tipe Part" key="part_type_code" :value="$p->part_type_code" type="select" :options="$lk['partTypes']" :args="[$pid]" :readonly="$ro" />
                <x-costing.field label="Qty per Set" key="qty_per_set" :value="$p->qty_per_set" :args="[$pid]" :readonly="$ro" />
                @if ($isDcFam)
                    <x-costing.field label="Gramatur / Bahan" key="grammage_id" :value="$p->grammage_id" type="select" :options="$lk['grammages']" :args="[$pid]" :readonly="$ro" class="col-span-2" />
                    <x-costing.field label="% DC (override)" key="i_dc_persen_override" :value="$p->i_dc_persen_override" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Panjang Plano (cm)" key="i_panjang_plano_cm" :value="$p->i_panjang_plano_cm" tone="common" :args="[$pid]" :readonly="$geoLock" />
                    <x-costing.field label="Lebar Plano (cm)" key="i_lebar_plano_cm" :value="$p->i_lebar_plano_cm" tone="common" :args="[$pid]" :readonly="$geoLock" />
                    <x-costing.field label="1 Plano dipotong jadi" key="i_cut_per_plano" :value="$p->i_cut_per_plano" tone="common" :args="[$pid]" :readonly="$geoLock" />
                    <x-costing.field label="Isi dalam 1 LB" key="i_isi_per_lb" :value="$p->i_isi_per_lb" tone="common" :args="[$pid]" :readonly="$geoLock" />
                    <x-costing.field label="Ukuran Cetak P (cm)" key="i_uk_cetak_p_cm" :value="$p->i_uk_cetak_p_cm" tone="common" :args="[$pid]" :readonly="$geoLock" />
                    <x-costing.field label="Ukuran Cetak L (cm)" key="i_uk_cetak_l_cm" :value="$p->i_uk_cetak_l_cm" tone="common" :args="[$pid]" :readonly="$geoLock" />
                    <x-costing.field label="Jumlah Warna" key="i_jumlah_warna" :value="$p->i_jumlah_warna" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Cetak/RIM" key="i_ongkos_cetak_rim" :value="$p->i_ongkos_cetak_rim" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Plong/LB" key="i_ongkos_plong_lb" :value="$p->i_ongkos_plong_lb" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos LEM/PC" key="i_ongkos_lem_pc" :value="$p->i_ongkos_lem_pc" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Hot Print/PC" key="i_ongkos_hotprint_pc" :value="$p->i_ongkos_hotprint_pc" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Film Rate" key="i_film_rate" :value="$p->i_film_rate !== null ? (string) (float) $p->i_film_rate : ''" type="select" :options="$lk['filmRates']" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Film Faktor" key="i_film_faktor" :value="$p->i_film_faktor" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Plate (Rp/warna)" key="i_ongkos_plate_warna" :value="$p->i_ongkos_plate_warna" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Pisau Faktor" key="i_pisau_faktor" :value="$p->i_pisau_faktor" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Pisau Rate (Rp/order)" key="i_pisau_rate_rp" :value="$p->i_pisau_rate_rp" :args="[$pid]" :readonly="$ro" />
                @endif
                @if ($isLam || $isSheet)
                    <x-costing.field label="Substansi / Tipe SF" key="sheet_rate_id" :value="$p->sheet_rate_id" type="select" tone="sheet" :options="$p->part_type_code === 'DC+SF' ? $lk['sfSubst'] : $lk['sheetRates']" :args="[$pid]" :readonly="$ro" class="col-span-2" />
                    <x-costing.field label="Supplier Sheet" key="supplier_sheet_code" :value="$p->supplier_sheet_code" type="select" tone="sheet" :options="$lk['suppliers']" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Panjang Piece SF/SH (cm)" key="i_sheet_panjang_cm" :value="$p->i_sheet_panjang_cm" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Lebar Piece SF (cm)" key="i_sheet_lebar_cm" :value="$p->i_sheet_lebar_cm" tone="sheet" :args="[$pid]" :readonly="$ro" />
                @endif
                @if ($isSheet || $p->part_type_code === 'DC+SHEET')
                    <x-costing.field label="Lebar Trim (cm)" key="i_lebar_trim_cm" :value="$p->i_lebar_trim_cm" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Isi / Trim" key="i_isi_per_trim" :value="$p->i_isi_per_trim" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Discount / Surcharge" key="i_discount_rm" :value="$p->i_discount_rm" tone="sheet" :args="[$pid]" :readonly="$ro" />
                @endif
                @if ($isSheet)
                    <x-costing.field label="Jenis Flute" key="flute_type" :value="$p->flute_type" type="select" tone="sheet" :options="['B/F' => 'B/F', 'C/F' => 'C/F', 'BC/F' => 'BC/F']" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Isi/LB Kerja" key="i_isi_per_lb_kerja" :value="$p->i_isi_per_lb_kerja" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Print/PC" key="i_ongkos_print_pc" :value="$p->i_ongkos_print_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Die Cut/PC" key="i_ongkos_diecut_pc" :value="$p->i_ongkos_diecut_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Slitter/PC" key="i_ongkos_slitter_pc" :value="$p->i_ongkos_slitter_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Slotter/PC" key="i_ongkos_slotter_pc" :value="$p->i_ongkos_slotter_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Jenis Join" key="join_type_code" :value="$p->join_type_code" type="select" tone="sheet" :options="$lk['joinTypes']" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Join/PC" key="i_ongkos_join_pc" :value="$p->i_ongkos_join_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Partisi/PC" key="i_ongkos_partisi_pc" :value="$p->i_ongkos_partisi_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Layer/PC" key="i_ongkos_layer_pc" :value="$p->i_ongkos_layer_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Pisau Diecut/PC" key="i_ongkos_pisau_pc" :value="$p->i_ongkos_pisau_pc" tone="sheet" :args="[$pid]" :readonly="$ro" />
                @endif
                @if ($isLam)
                    <x-costing.field label="Pakai Laminasi" key="pakai_laminasi" :value="$p->pakai_laminasi ? '1' : '0'" type="select" tone="lam" :options="['1' => 'Ya', '0' => 'Tidak']" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Lam Sheet Flat (Rp/PC)" key="i_lam_sheet_flat_pc" :value="$p->i_lam_sheet_flat_pc" tone="lam" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Rate SF EF (E/F)" key="sf_ef_rate_id" :value="$p->sf_ef_rate_id" type="select" tone="lam" :options="$lk['sfEf']" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Harga SF EF (override)" key="i_sf_ef_rate_override" :value="$p->i_sf_ef_rate_override" tone="lam" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Pakai Ukuran SF?" key="i_lam_pakai_ukuran_sf" :value="$p->i_lam_pakai_ukuran_sf ? '1' : '0'" type="select" tone="lam" :options="['1' => '1', '0' => '0']" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Lam SH (Rp/cm2)" key="i_ongkos_lam_rp_cm2_sh" :value="$p->i_ongkos_lam_rp_cm2_sh" tone="lam" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Lam SF (Rp/cm2)" key="i_ongkos_lam_rp_cm2_sf" :value="$p->i_ongkos_lam_rp_cm2_sf" tone="lam" :args="[$pid]" :readonly="$ro" />
                    <x-costing.field label="Ongkos Laminasi Manual" key="i_ongkos_laminasi_pc" :value="$p->i_ongkos_laminasi_pc" tone="lam" :args="[$pid]" :readonly="$ro" />
                @endif
                <x-costing.field label="Ongkos Kupas/PC" key="i_ongkos_kupas_pc" :value="$p->i_ongkos_kupas_pc" tone="common" :args="[$pid]" :readonly="$ro" />
                <x-costing.field label="Ongkos QC/PC" key="i_ongkos_qc_pack_pc" :value="$p->i_ongkos_qc_pack_pc" tone="common" :args="[$pid]" :readonly="$ro" />
                <x-costing.field label="Ongkos Kirim/PC" key="i_ongkos_kirim_pc" :value="$p->i_ongkos_kirim_pc" tone="common" :args="[$pid]" :readonly="$ro" />
                <x-costing.field label="Jenis Packing" key="packing_type_code" :value="$p->packing_type_code" type="select" tone="common" :options="$lk['packingTypes']" :args="[$pid]" :readonly="$ro" />
                <x-costing.field label="Ongkos Packing/PC" key="i_ongkos_packing_pc" :value="$p->i_ongkos_packing_pc" tone="common" :args="[$pid]" :readonly="$ro" />
            </div>
            @if (! $isSheet && $p->part_type_code !== 'KRAFT')
                <div class="mt-2 flex items-center gap-2">
                    <span class="text-[11px] font-bold text-[#1d4ed8]">Coating:</span>
                    @foreach ($p->coatings as $co)
                        <span wire:key="chip-{{ $co->id_coating }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#eff6ff] border border-[#bfdbfe] text-[11px]">
                            <select @disabled($ro) wire:change="updateCoating({{ $co->id_coating }}, 'coating_type', $event.target.value)" class="bg-transparent text-[11px]">
                                @foreach ($lk['coatings'] as $ct)<option value="{{ $ct }}" @selected($ct === $co->coating_type)>{{ $ct }}</option>@endforeach
                            </select>
                            <button type="button" wire:click="removeCoating({{ $co->id_coating }})" @disabled($ro) class="text-[#ef4444] cursor-pointer">✕</button>
                        </span>
                    @endforeach
                    <button type="button" wire:click="addCoating({{ $pid }})" @disabled($ro) class="text-[11px] font-bold text-accent cursor-pointer">＋ Layer</button>
                </div>
            @endif
        </x-ui.accordion>
        @endif

        {{-- ═════ Banner hasil (mockup) ═════ --}}
        <div class="bg-[#0f172a] text-white rounded-xl shadow-lg p-5 [display:grid] grid-cols-5 gap-5">
            <div><div class="text-[#94a3b8] text-[11px] font-medium uppercase tracking-wider">Total COGS (HPP)</div>
                <div class="text-[18px] font-bold">{{ $lock ? '🔒' : $rp($hppOrder) }}</div><div class="text-[12px] text-[#cbd5e1]">{{ $lock ? '' : $rp($t?->c_hpp_pc).' / pcs' }}</div></div>
            <div><div class="text-[#94a3b8] text-[11px] font-medium uppercase tracking-wider">Margin (+{{ (float) $t?->margin_pct }}%) · Tol {{ (float) $t?->tolerance_pct }}%</div>
                <div class="text-[18px] font-bold">{{ $lock ? '🔒' : $rp(((float) $t?->price_per_pcs - (float) $t?->c_hpp_pc) * $qty) }}</div><div class="text-[12px] text-[#cbd5e1]">Profit contribution</div></div>
            <div><div class="text-[#94a3b8] text-[11px] font-medium uppercase tracking-wider">PPN (+{{ $c->ppn_mode === 'NON_PPN' ? 0 : (float) $c->ppn_pct }}%)</div>
                <div class="text-[18px] font-bold">{{ $rp(((float) $t?->c_after_ppn_pc - (float) $t?->price_per_pcs) * $qty) }}</div><div class="text-[12px] text-[#cbd5e1]">Tax</div></div>
            <div class="col-span-2 bg-[#2563eb]/30 border border-[#3b82f6]/50 rounded-lg p-4">
                <div class="text-[#bfdbfe] text-[11px] font-semibold uppercase tracking-wider mb-1">✔ Final Selling Price</div>
                <div class="text-[26px] font-extrabold tracking-tight">{{ $rp($t?->c_after_ppn_pc) }} <span class="text-[15px] font-medium text-[#bfdbfe]">/ pcs</span></div>
                <div class="text-[12px] font-medium text-[#93c5fd]">Excl PPN {{ $rp($t?->price_per_pcs) }} · Grand Total: {{ $rp((float) $t?->c_after_ppn_pc * $qty) }}</div>
            </div>
        </div>
    </div>
    @endif
</div>
