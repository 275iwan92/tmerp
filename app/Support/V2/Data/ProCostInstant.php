<?php

/*
 * REMARK
 * Tujuan       : Data dan simpan layar ProCost v2 Mode Instant (kalkulator standalone per dokumen): state tampilan (costing, tier, part, hasil kalkulasi, rincian per pcs dan per order) dan penerapan isian form ke InstantCostingService per field yang berubah (setiap perubahan = hitung ulang, seperti v1).
 * Sumber hulu  : InstantCostingService (aturan tulis + CostingEngine), FormulaAccess (rincian hanya login berhak), master Mat* / ProdPartType (pilihan).
 * Input        : id costing, nomor part; kiriman form (header, tier[..], part[id][..], coating[part][id][..]).
 * Output       : array state untuk resources/views/v2/screens/procost-instant.blade.php; save() menulis iqp_costing* lewat service dan mengembalikan galat engine ('' = baik).
 * Dipakai oleh : layar v2 procost-instant, routes/v2_procost.php, tests/Feature/CostingInstantModeTest.
 * Status       : Aktif (T10 tahap 3 pindahan v1, 2026-10-21).
 */

namespace App\Support\V2\Data;

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
use App\Services\Costing\InstantCostingService as Svc;
use App\Support\Costing\FormulaAccess;
use Devlib\Locale;

final class ProCostInstant
{
    public static function lookups(): array
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

    public static function state(int $costingId, int $partNo = 1): array
    {
        $c = Svc::open($costingId);
        $c->load(['parts.coatings', 'priceTiers', 'customer']);
        $parts = $c->parts->values();
        $partNo = max(1, min($partNo, max(1, $parts->count())));
        $p = $parts[$partNo - 1] ?? null;
        $t = Svc::tier($c);
        $lk = self::lookups();
        $lk['sfSubstInstant'] = $lk['sfEf'] + $lk['singleFace'];
        $qty = (float) ($t->qty_pcs ?? 0);

        return ['costing' => $c, 'tier' => $t, 'part' => $p, 'partNo' => $partNo, 'partCount' => $parts->count(), 'lookups' => $lk,
            'ro' => Svc::readOnly($c), 'canSee' => FormulaAccess::canSee(), 'qty' => $qty,
            'breakdown' => $p ? self::breakdown($p, $qty) : ['pc' => [], 'qty' => 0], 'cells' => $p ? self::outputCells($p, $qty) : []];
    }

    public static function breakdown($p, float $qty): array
    {
        $q = $qty * max(1, (float) $p->qty_per_set);
        $toolQ = $q > 0 ? $q : 1;
        $pc = [
            'Kertas' => (float) $p->c_paper_cost_pc, 'Cetak' => (float) $p->c_cetak_cost_pc, 'Plong' => (float) $p->c_plong_cost_pc,
            'LEM' => (float) $p->c_lem_cost_pc, 'Hot Print' => (float) $p->i_ongkos_hotprint_pc, 'Kupas' => (float) $p->i_ongkos_kupas_pc,
            'QC' => (float) $p->i_ongkos_qc_pack_pc, 'Kirim' => (float) $p->i_ongkos_kirim_pc, 'Packing' => (float) $p->i_ongkos_packing_pc,
            'Coating' => (float) $p->c_total_coating_pc, 'SF EF' => (float) $p->c_sf_ef_cost_pc, 'Ongkos Laminasi' => (float) $p->c_ongkos_lam_pc,
            'C I Lam Sheet Flat' => (float) $p->c_lam_flat_cost_pc, 'Harga Sheet' => (float) $p->c_sheet_cost_pc, 'Print' => (float) $p->c_print_cost_pc,
            'Die Cut' => (float) $p->i_ongkos_diecut_pc, 'Slitter' => (float) $p->i_ongkos_slitter_pc, 'Slotter' => (float) $p->i_ongkos_slotter_pc,
            'Join' => (float) $p->i_ongkos_join_pc, 'Partisi' => (float) $p->i_ongkos_partisi_pc, 'Layer' => (float) $p->i_ongkos_layer_pc,
            'Pisau Diecut' => (float) $p->i_ongkos_pisau_pc,
            'Film' => (float) $p->c_film_cost_order / $toolQ, 'Plate' => (float) $p->c_plate_cost_order / $toolQ, 'Pisau' => (float) $p->c_knife_cost_order / $toolQ,
        ];

        return ['pc' => $pc, 'qty' => $q];
    }

    // Urutan sel = mockup Kalkulasi_HPP_UAT.html "Detailed Breakdown Per PC". [label, nilai, 'k' = subtotal]
    public static function outputCells($p, float $qty): array
    {
        $pc = self::breakdown($p, $qty)['pc'];
        $toolPc = $qty > 0 ? (float) $p->c_tooling_cost_order / $qty : 0;

        return [
            ['Luas Cetak (cm2)', $p->c_area_cetak_cm2, 'k'], ['Jumlah Bahan (LB)', $p->c_jumlah_bahan_lb, 'k'],
            ['Harga Kertas/PC', $p->c_paper_cost_pc, ''], ['Cetak/PC', $p->c_cetak_cost_pc, ''],
            ['Plong/PC', $p->c_plong_cost_pc, ''], ['Lem/PC', $p->c_lem_cost_pc, ''],
            ['Coating/PC', $p->c_total_coating_pc, ''], ['SF EF/PC', $p->c_sf_ef_cost_pc, ''],
            ['Ongkos Laminasi/PC', (float) $p->c_ongkos_lam_pc + (float) $p->c_lam_flat_cost_pc, ''], ['Harga Sheet/PC', $p->c_sheet_cost_pc, ''],
            ['Print/PC', $p->c_print_cost_pc, ''], ['Film/PC', $pc['Film'] ?? 0, ''],
            ['Ongkos Proses/PC', (float) $p->c_process_cost_pc + (float) $p->c_total_coating_pc, 'k'], ['Plate/PC', $pc['Plate'] ?? 0, ''],
            ['Pisau/PC', $pc['Pisau'] ?? 0, ''], ['Tooling/PC', $toolPc, 'k'],
        ];
    }

    // Terapkan isian form; balikan = galat engine terakhir ('' = baik). Hanya field yang berubah dikirim ke service.
    public static function save(IqpCosting $c, array $in): string
    {
        $err = '';
        $c->load(['parts.coatings', 'priceTiers']);
        $tier = Svc::tier($c);

        foreach (Svc::HEADER_FIELDS as $f) {
            if (array_key_exists($f, $in) && ! self::same($c->{$f}, $in[$f])) {
                $err = Svc::updateHeader($c, $f, self::val($in[$f], $f)) ?: $err;
            }
        }
        foreach (Svc::TIER_FIELDS as $f) {
            if (array_key_exists($f, (array) ($in['tier'] ?? [])) && ! self::same($tier->{$f}, $in['tier'][$f])) {
                $err = Svc::updateTier($c, $f, self::val($in['tier'][$f])) ?: $err;
            }
        }
        foreach ((array) ($in['part'] ?? []) as $pid => $row) {
            $part = $c->parts->firstWhere('id_costing_part', (int) $pid);
            if (! $part) {
                continue;
            }
            foreach (array_unique(array_merge(['part_type_code'], array_keys((array) $row))) as $f) {
                if (array_key_exists($f, (array) $row) && in_array($f, Svc::PART_FIELDS, true) && ! self::same($part->{$f}, $row[$f])) {
                    $err = Svc::updatePart($c, (int) $pid, $f, self::val($row[$f])) ?: $err;
                }
            }
            foreach ((array) (($in['coating'] ?? [])[$pid] ?? []) as $cid => $crow) {
                $co = $part->coatings->firstWhere('id_coating', (int) $cid);
                foreach (['coating_type' => 'coating_type', 'index_coating' => 'index_coating', 'notes' => 'notes_coating'] as $f => $col) {
                    if ($co && array_key_exists($f, (array) $crow) && ! self::same($co->{$col}, $crow[$f])) {
                        $err = Svc::updateCoating($c, (int) $pid, (int) $cid, $f, self::val($crow[$f], $f)) ?: $err;
                    }
                }
            }
        }

        return $err;
    }

    // Teks (nama, catatan) apa adanya; angka dibaca sesuai bahasa tampilan.
    private static function val(mixed $v, ?string $f = null): string
    {
        $s = is_bool($v) ? (string) (int) $v : trim((string) $v);
        if ($s === '' || in_array($f, ['customer_name_draft', 'product_name_draft', 'calc_notes', 'notes', 'doc_date', 'calc_type', 'ppn_mode', 'currency_code', 'coating_type'], true)) {
            return $s;
        }

        return Locale::input($s) ?? $s;
    }

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
}
