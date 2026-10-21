<?php

/*
 * REMARK
 * Tujuan       : Data dan simpan layar ProCost v2 Mode Integrasi (Costing Hub per inquiry): menyusun state tampilan (costing, tier, part, konteks InqProd/ProDev, saran engine, Laporan Hitung, ketersediaan BOM, peringatan routing) dan menerapkan isian form ke CostingEntryService per field yang berubah.
 * Sumber hulu  : CostingEntryService (aturan tulis), ProCostContextService (konteks baca), CostingAdvisor (saran + laporan), FormulaAccess (rumus hanya login berhak), MaterialPlanning (Book).
 * Input        : id inquiry, nomor part, id tier; kiriman form (header, tier[id][..], part[id][..], paper_index, coating[id][..]).
 * Output       : array state untuk resources/views/v2/screens/procost.blade.php; save() menulis iqp_costing* lewat service dan mengembalikan pemberitahuan pemulihan isian.
 * Dipakai oleh : layar v2 procost, routes/v2_procost.php, tests/Feature/ProCostV2Test.
 * Status       : Aktif (T10 tahap 3 pindahan v1, 2026-10-21).
 */

namespace App\Support\V2\Data;

use App\Models\Master\MatCoatingRate;
use App\Models\Master\MatFilmRate;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatJoinType;
use App\Models\Master\MatPackingType;
use App\Models\Master\MatSheetRate;
use App\Models\Master\MatSheetSupplier;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPartType;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\IqpReviseChange;
use App\Models\Sys\SysConstant;
use App\Services\Costing\CostingAdvisor;
use App\Services\Costing\CostingEntryService as Entry;
use App\Services\Costing\ProCostContextService;
use App\Support\Costing\FormulaAccess;
use App\Support\V2\Costing\ProCostFields;
use Devlib\Locale;

final class ProCost
{
    public static function lookups(): array
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
            'fluteChoices' => ProCostFields::FLUTE_INTEGRATED,
        ];
    }

    // State satu layar. $viewOnly = navigasi pasif (tidak membuat draft): status + harga final saja setelah PUBLISHED.
    public static function state(int $inquiryId, int $partNo = 1, ?int $tierId = null, bool $viewOnly = false): array
    {
        $inq = IqpInquiry::with('customer')->findOrFail($inquiryId);
        $c = $viewOnly ? Entry::find($inquiryId) : Entry::open($inquiryId);
        if ($viewOnly) {
            return ['view' => true, 'inquiry' => $inq, 'costing' => $c?->load('priceTiers')];
        }
        $c->load(['parts.coatings', 'priceTiers', 'inquiry.customer']);
        $tiers = $c->priceTiers;
        $t = $tiers->firstWhere('id_price_tier', $tierId) ?? $tiers->firstWhere('is_primary', true) ?? $tiers->first();
        $parts = $c->parts->values();
        $partNo = max(1, min($partNo, max(1, $parts->count())));
        $p = $parts[$partNo - 1] ?? null;
        $ro = Entry::readOnly($c);
        $canSee = FormulaAccess::canSee();
        $advisor = new CostingAdvisor;
        $recs = $advisor->recsFor($c->id_costing);
        $ctx = $p ? (new ProCostContextService)->forPart($p, (float) ($t?->qty_pcs ?? 0)) : [];
        $tid = $t?->id_price_tier ?? 0;
        $pid = $p?->id_costing_part ?? 0;
        $mine = $recs->filter(fn ($r) => $r->source !== 'OFFICER' && $r->recommended_value !== null && ($r->costing_part_id === null ? $r->tier_id === $tid : $r->costing_part_id === $pid));

        return [
            'view' => false, 'inquiry' => $inq, 'costing' => $c, 'tier' => $t, 'part' => $p, 'partNo' => $partNo, 'partCount' => $parts->count(),
            'ctx' => $ctx, 'ro' => $ro, 'canSee' => $canSee, 'canPublish' => Entry::canPublish(), 'recs' => $recs,
            'lookups' => self::lookups(), 'qty' => (float) ($t?->qty_pcs ?? 0),
            'report' => $canSee && $t && (float) $t->c_after_ppn_pc > 0 ? $advisor->report($c, $t) : null,
            'adv' => ['filled' => $mine->count(), 'estimate' => $mine->where('source', 'ESTIMATE')->where('is_override', false)->count(), 'override' => $mine->where('is_override', true)->count(),
                'missing' => $p && ! $ro ? $advisor->missing($p) : []],
            'booking' => Entry::booking($inquiryId),
            'inquiryParts' => IqpPart::where('inquiry_id', $inquiryId)->orderBy('part_seq')->get(),
            'layoutGroups' => $p && $p->data_source === 'PRODEV' && ! $ro ? ProdLayoutGroup::with(['product', 'parts'])->where('status', 'CONFIRMED')->orderByDesc('id_layout_group')->limit(100)->get() : collect(),
            'routingWarnings' => $p ? Entry::routingWarningsFor($p) : [],
            'revise' => IqpReviseChange::where('inquiry_id', $inquiryId)->with('part:id_part,part_name')->get(),
        ];
    }

    // Baris "Process & Finishing": [proses, rate, basis, subtotal/order].
    public static function processRows(IqpCostingPart $p, float $qty): array
    {
        $q = $qty * max(1, (float) $p->qty_per_set);
        $cut = max(0.0001, (float) $p->i_cut_per_plano);
        $isi = max(1, (float) $p->i_isi_per_lb);
        $rows = [];
        $add = function (string $name, string $rate, string $basis, float $perPc) use (&$rows, $q) {
            if ($perPc != 0.0) {
                $rows[] = [$name, $rate, $basis, $perPc * $q];
            }
        };

        if (in_array($p->part_type_code, ProCostFields::SHEET, true)) {
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

    // Nilai efektif tooling yang dipakai engine (default konstanta bila input kosong).
    public static function toolingInfo(IqpCostingPart $p): array
    {
        return [
            'film_ff' => $p->i_film_faktor !== null ? (float) $p->i_film_faktor : SysConstant::number('FILM_FACTOR_DEFAULT', 0.25),
            'film_rate' => $p->i_film_rate !== null ? (float) $p->i_film_rate : SysConstant::number('FILM_RATE_DEFAULT', 55),
            'plate' => (float) $p->i_ongkos_plate_warna > 0 ? (float) $p->i_ongkos_plate_warna : SysConstant::number('PLATE_PER_COLOR', 90000),
            'knife_ff' => (float) $p->i_pisau_faktor > 0 ? (float) $p->i_pisau_faktor : SysConstant::number('KNIFE_FACTOR_DEFAULT', 0.25),
            'knife_rate' => $p->i_pisau_rate_rp !== null ? (float) $p->i_pisau_rate_rp : (float) (ProdPartType::where('part_type_code', $p->part_type_code)->value('pisau_rate_rp') ?? 0),
        ];
    }

    public static function materialLabel(?IqpCostingPart $p, array $ctx): string
    {
        $bom = collect($ctx['bom'] ?? []);

        return MatGrammage::whereKey($p?->grammage_id)->value('grammage_name')
            ?? ($bom->firstWhere('primary', true)['material'] ?? ($bom->first()['material'] ?? '—'));
    }

    // Terapkan isian form: hanya field yang berubah dikirim ke service. Balikan = pemberitahuan pemulihan isian [..] dan galat engine.
    public static function save(IqpCosting $c, array $in): array
    {
        $notices = [];
        $c->loadMissing(['parts', 'priceTiers']);

        foreach (['ppn_pct'] as $f) {
            if (array_key_exists($f, $in) && ! self::same($c->{$f}, $in[$f])) {
                Entry::updateHeader($c, $f, self::val($in[$f], $f));
            }
        }

        $partId = (int) ($in['part_id'] ?? 0);
        $part = $c->parts->firstWhere('id_costing_part', $partId);
        $typeCode = $part?->part_type_code;

        foreach ((array) ($in['tier'] ?? []) as $tid => $row) {
            $tier = $c->priceTiers->firstWhere('id_price_tier', (int) $tid);
            foreach (Entry::TIER_FIELDS as $f) {
                if ($tier && array_key_exists($f, (array) $row) && ! self::same($tier->{$f}, $row[$f])) {
                    $notices[] = Entry::updateTier($c, (int) $tid, $f, self::val($row[$f], $f), $typeCode);
                }
            }
        }

        if ($part) {
            // Tipe part dulu: mengganti tipe mengisi tarif lain (saran engine), sisanya dibandingkan dengan isian lama.
            $row = (array) (($in['part'] ?? [])[$partId] ?? []);
            $order = array_unique(array_merge(['part_type_code'], array_keys($row)));
            foreach ($order as $f) {
                if (! array_key_exists($f, $row) || ! in_array($f, Entry::PART_FIELDS, true) || self::same($part->{$f}, $row[$f])) {
                    continue;
                }
                $notices[] = Entry::updatePart($c, $partId, $f, self::val($row[$f], $f));
            }

            if (array_key_exists('paper_index', $in) && ! self::same($part->i_paper_price_override, $in['paper_index'])) {
                $notices[] = Entry::updatePart($c, $partId, 'i_paper_price_override', self::val($in['paper_index']));
            }

            foreach ((array) ($in['coating'] ?? []) as $cid => $crow) {
                $co = $part->coatings->firstWhere('id_coating', (int) $cid);
                foreach (['coating_type', 'index_coating'] as $f) {
                    if ($co && array_key_exists($f, (array) $crow) && ! self::same($co->{$f}, $crow[$f])) {
                        Entry::updateCoating($c, (int) $cid, $f, self::val($crow[$f], $f));
                    }
                }
            }
        }

        return array_values(array_filter($notices));
    }

    // Teks apa adanya; angka dibaca sesuai bahasa tampilan.
    private const TEXT = ['part_name', 'calc_notes', 'part_type_code', 'supplier_sheet_code', 'flute_type', 'join_type_code', 'packing_type_code', 'jenis_packing', 'coating_type', 'rounding_mode', 'ppn_mode', 'i_film_rate'];

    private static function val(mixed $v, ?string $f = null): string
    {
        $s = is_bool($v) ? (string) (int) $v : trim((string) $v);

        return $s === '' || in_array($f, self::TEXT, true) ? $s : (Locale::input($s) ?? $s);
    }

    // Angka dibandingkan sebagai angka; selain itu sebagai teks.
    private static function same(mixed $old, mixed $new): bool
    {
        $a = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : (is_bool($old) ? (string) (int) $old : trim((string) $old));
        $b = is_bool($new) ? (string) (int) $new : trim((string) $new);
        if ($a === $b) {
            return true;
        }
        $nb = Locale::input($b);

        return is_numeric($a) && is_numeric($nb) && (float) $a === (float) $nb;
    }

    public static function customerName(IqpInquiry $inq): string
    {
        return $inq->customer?->customer_name ?? $inq->prospect_name ?? '—';
    }
}
