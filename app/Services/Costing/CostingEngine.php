<?php

namespace App\Services\Costing;

use App\Models\Master\MatFluteAllowance;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatSheetDiscountTier;
use App\Models\Master\MatSheetRate;
use App\Models\Master\MatSheetSupplier;
use App\Models\Master\ProdPartType;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\DB;

/**
 * Port rumus program HPP lama -- SUMBER KEBENARAN = dump docs/ddl/tmhpp.sql
 * (hpp_parts + map_hpp_parts), bukan ringkasan Gemini. Detail & kasus uji:
 * docs/inquiry_product_costing/procost_formula_library.md §VII.
 *
 * Titik yang dulu salah di V2 & dikoreksi 2026-09-18 (arahan user "perhatikan
 * nilai konstantanya"):
 *  - Faktor DC duplex = 1 + 5,30 = 6,30 (bukan 1 + 5,30%).
 *  - Luas cetak = Ukuran Cetak P x L (input), BUKAN luas plano -- dipakai
 *    coating, film, SF EF, laminasi J38. Plano hanya utk harga kertas.
 *  - Kertas per-kg (ivory/kraft) = harga_kg x gsm/1000 x luas_plano/10000
 *    (identik gsm/2/500) -- KRAFT dulu 500x terlalu mahal.
 *  - KRAFT = keluarga DC (cetak/plong/lem + tooling pisau 1,5jt), bukan TOOL_SKIP.
 *  - DC+SF/DC+SHEET: J36 = input Rp/PC final, J37 SF EF, J38 faktor Rp/cm2,
 *    semua ongkos (hotprint/kupas/qc/kirim/packing) + coating asli.
 *
 * Tooling tetap disimpan ORDER-level & dibagi qty di tier (keputusan V2) --
 * utk tier utama hasilnya identik legacy (tooling/qty_part).
 */
class CostingEngine
{
    public function run(IqpCosting $costing): IqpCosting
    {
        DB::connection('tm2sdi')->transaction(function () use ($costing) {
            $primaryQty = (float) ($costing->priceTiers()->where('is_primary', true)->value('qty_pcs')
                ?? $costing->priceTiers()->value('qty_pcs')
                ?? 0);

            foreach ($costing->parts()->with('coatings')->get() as $part) {
                match ($part->part_type_code) {
                    'DC', 'DC+SF', 'DC+SHEET', 'KRAFT' => $this->calculateOffsetPart($part, $primaryQty),
                    'SHEET', 'SHEET_DIGITAL' => $this->calculateSheetPart($part, $primaryQty),
                    default => throw new \RuntimeException("part_type_code '{$part->part_type_code}' belum diimplementasi CostingEngine."),
                };
            }

            $this->rollUpHeader($costing);

            foreach ($costing->priceTiers()->get() as $tier) {
                $this->calculateTier($costing, $tier);
            }

            $costing->doc_status = 'CALCULATED';
            $costing->save();
        });

        return $costing->refresh();
    }

    private function positive($value, float $fallback = 1.0): float
    {
        $v = (float) $value;

        return $v > 0 ? $v : $fallback;
    }

    /** DC, DC+SF, DC+SHEET, KRAFT -- keluarga offset (map_hpp_parts visible_type). */
    protected function calculateOffsetPart(IqpCostingPart $part, float $qty): void
    {
        $type = $part->part_type_code;
        $rim = SysConstant::number('RIM_SHEETS', 500);
        $planoRef = SysConstant::number('PLANO_REF_AREA', 8611);

        $cut = $this->positive($part->i_cut_per_plano);
        $isi = $this->positive($part->i_isi_per_lb);
        $flags = [];

        $areaPlano = (float) $part->i_panjang_plano_cm * (float) $part->i_lebar_plano_cm;

        $ukP = (float) $part->i_uk_cetak_p_cm;
        $ukL = (float) $part->i_uk_cetak_l_cm;
        if ($ukP > 0 && $ukL > 0) {
            $areaCetak = $ukP * $ukL;
        } else {
            $areaCetak = $areaPlano / $cut; // Ukuran Cetak belum diisi -> perkiraan plano/potong
            $flags[] = 'UK_FALLBACK';
        }

        // ── Kertas (map_hpp_parts #62) ──
        $paper = 0.0;
        $grammage = $part->grammage_id ? MatGrammage::find($part->grammage_id) : null;
        $override = (float) $part->i_paper_price_override;
        $isDuplex = $grammage && ($grammage->paper_kind === 'DUPLEX' || (float) $grammage->duplex_price_list > 0);
        $price = $override > 0 ? $override : ($isDuplex ? (float) $grammage?->duplex_price_list : (float) $grammage?->ivory_ap_price_per_kg);
        if ($grammage && $price > 0 && $isDuplex) {
            $dcOverride = (float) $part->i_dc_persen_override;
            $dcf = 1 + ($dcOverride > 0 ? $dcOverride : (float) $grammage->duplex_dc_pct);
            $paper = ($areaPlano / $planoRef) * $price * $dcf / $rim / $cut / $isi;
            $flags[] = 'PAPER_OK';
        } elseif ($grammage && $price > 0) {
            $paper = ($areaPlano / 10000) * $price * ((float) $grammage->grammage_value / 1000) / $cut / $isi;
            $flags[] = 'PAPER_AP_OK';
        } else {
            $flags[] = 'PAPER_GAGAL';
        }

        // ── Proses dasar ──
        $cetak = (float) $part->i_ongkos_cetak_rim / $rim / $cut / $isi;
        $plong = (float) $part->i_ongkos_plong_lb / $isi;
        $lem = (float) $part->i_ongkos_lem_pc;
        $others = (float) $part->i_ongkos_hotprint_pc + (float) $part->i_ongkos_kupas_pc
            + (float) $part->i_ongkos_qc_pack_pc + (float) $part->i_ongkos_kirim_pc + (float) $part->i_ongkos_packing_pc;

        // ── Coating multi-layer: uk_cetak x index / isi (TIDAK dibagi cut) ──
        $coatingPc = 0.0;
        if ($type !== 'KRAFT') {
            foreach ($part->coatings as $coating) {
                $perLc = $areaCetak * (float) $coating->index_coating;
                $coating->c_cost_per_lc = $perLc;
                $coating->c_cost_per_pc = $perLc / $isi;
                $coating->save();
                $coatingPc += $perLc / $isi;
            }
        }
        $flags[] = 'COAT_OK';

        // ── Laminasi J36/J37/J38 (hanya DC+SF & DC+SHEET) ──
        $j36 = $j37 = $j38 = 0.0;
        if (in_array($type, ['DC+SF', 'DC+SHEET'], true)) {
            $lam = $part->pakai_laminasi ? 1.0 : 0.0;
            $j36 = $lam * (float) $part->i_lam_sheet_flat_pc;

            $sfRate = (float) $part->i_sf_ef_rate_override;
            if ($sfRate <= 0 && $part->sf_ef_rate_id) {
                $sfRate = (float) MatSheetRate::whereKey($part->sf_ef_rate_id)->value('index_substance');
            }
            $sfActive = $sfRate > 0 ? 1.0 : 0.0;
            $j37 = $sfActive * ($areaCetak / 10000) * $sfRate / $isi;
            $flags[] = $sfActive ? ((float) $part->i_sf_ef_rate_override > 0 ? 'SFEF_OVR' : 'SFEF_OK') : 'SFEF_SKIP';

            $sheetArea = (float) $part->i_sheet_panjang_cm * (float) $part->i_sheet_lebar_cm;
            $ukLam = ($part->i_lam_pakai_ukuran_sf && $sheetArea > 0) ? $sheetArea : $areaCetak;
            $rateSh = (float) $part->i_ongkos_lam_rp_cm2_sh;
            $rateSf = (float) $part->i_ongkos_lam_rp_cm2_sf;
            $j38 = ($rateSh > 0 || $rateSf > 0)
                ? ($lam * $ukLam * $rateSh / $isi) + ($sfActive * $ukLam * $rateSf / $isi)
                : (float) $part->i_ongkos_laminasi_pc;
            $flags[] = ($j36 + $j38) > 0 ? 'LAM_OK' : 'LAM_SKIP';
        } else {
            $flags[] = 'LAM_SKIP';
        }

        // ── Tooling (order-level): film = ff² x luas_cetak x rate, plate, pisau ──
        $ff = $part->i_film_faktor !== null ? (float) $part->i_film_faktor : SysConstant::number('FILM_FACTOR_DEFAULT', 0.25);
        $filmRate = $part->i_film_rate !== null ? (float) $part->i_film_rate : SysConstant::number('FILM_RATE_DEFAULT', 55);
        $film = ($ff ** 2) * $areaCetak * $filmRate;
        $plateRate = (float) $part->i_ongkos_plate_warna > 0 ? (float) $part->i_ongkos_plate_warna : SysConstant::number('PLATE_PER_COLOR', 90000);
        $plate = $plateRate * (int) $part->i_jumlah_warna;
        $pisauRate = $part->i_pisau_rate_rp !== null
            ? (float) $part->i_pisau_rate_rp
            : (float) (ProdPartType::where('part_type_code', $type)->value('pisau_rate_rp') ?? 0);
        $pisauFaktor = (float) $part->i_pisau_faktor > 0 ? (float) $part->i_pisau_faktor : SysConstant::number('KNIFE_FACTOR_DEFAULT', 0.25);
        $knife = $pisauFaktor * $pisauRate;
        $toolingOrder = $film + $plate + $knife;
        $flags[] = 'TOOL_OK';

        $processPc = $cetak + $plong + $lem + $others + $j36 + $j37 + $j38;
        $totalPc = $paper + $processPc + $coatingPc;
        $lc = $qty > 0 ? $qty / ($cut * $isi) : null;

        $part->fill([
            'c_area_cetak_cm2' => $areaCetak,
            'c_jumlah_cetak_lc' => $lc,
            'c_jumlah_bahan_lb' => $lc,
            'c_paper_cost_pc' => $paper,
            'c_sheet_cost_pc' => 0,
            'c_cetak_cost_pc' => $cetak,
            'c_plong_cost_pc' => $plong,
            'c_lem_cost_pc' => $lem,
            'c_total_coating_pc' => $coatingPc,
            'c_lam_flat_cost_pc' => $j36,
            'c_sf_ef_cost_pc' => $j37,
            'c_ongkos_lam_pc' => $j38,
            'c_film_cost_order' => $film,
            'c_plate_cost_order' => $plate,
            'c_knife_cost_order' => $knife,
            'c_tooling_cost_order' => $toolingOrder,
            'c_process_cost_pc' => $processPc,
            'c_total_part_cost_pc' => $totalPc,
            'c_sheet_compare_info' => $type === 'DC+SHEET' ? $this->compareSuppliers($part, $qty) : null,
        ]);
        $part->description_calc = $this->describe($type, $paper, $processPc + $coatingPc, $coatingPc, $j36 + $j38, $j37, $qty > 0 ? $toolingOrder / $qty : 0, 0, $totalPc + ($qty > 0 ? $toolingOrder / $qty : 0), $flags);
        $part->save();
    }

    /** SHEET & SHEET_DIGITAL (corrugated, TOOL_SKIP). */
    protected function calculateSheetPart(IqpCostingPart $part, float $qty): void
    {
        $manual = (float) $part->i_lebar_trim_cm > 0 && (int) $part->i_isi_per_trim > 0 && (float) $part->i_sheet_panjang_cm > 0;
        $geo = $manual ? $this->manualSheetGeometry($part, $qty) : $this->computeSheetGeometry($part, $qty);

        $process = (float) $part->i_ongkos_print_pc * 2
            + (float) $part->i_ongkos_diecut_pc + (float) $part->i_ongkos_slitter_pc + (float) $part->i_ongkos_slotter_pc
            + (float) $part->i_ongkos_join_pc + (float) $part->i_ongkos_partisi_pc + (float) $part->i_ongkos_layer_pc
            + (float) $part->i_ongkos_pisau_pc + (float) $part->i_ongkos_kupas_pc + (float) $part->i_ongkos_qc_pack_pc
            + (float) $part->i_ongkos_kirim_pc + (float) $part->i_ongkos_packing_pc;

        $total = $geo['sheet_cost_pc'] + $process;

        $part->fill([
            'c_panjang_sheet_cm' => $geo['panjang_sheet_cm'],
            'c_lebar_sheet_cm' => $geo['lebar_sheet_cm'],
            'c_isi_trim' => $geo['isi_trim'],
            'c_lebar_trim_cm' => $geo['lebar_trim_cm'],
            'c_running_meter' => $geo['running_meter'],
            'c_discount_rm' => $geo['discount_rm'],
            'c_sheet_cost_pc' => $geo['sheet_cost_pc'],
            'c_print_cost_pc' => (float) $part->i_ongkos_print_pc * 2,
            'c_paper_cost_pc' => 0,
            'c_cetak_cost_pc' => 0,
            'c_plong_cost_pc' => 0,
            'c_lem_cost_pc' => 0,
            'c_total_coating_pc' => 0,
            'c_lam_flat_cost_pc' => 0,
            'c_sf_ef_cost_pc' => 0,
            'c_ongkos_lam_pc' => 0,
            'c_film_cost_order' => 0,
            'c_plate_cost_order' => 0,
            'c_knife_cost_order' => 0,
            'c_tooling_cost_order' => 0,
            'c_process_cost_pc' => $process,
            'c_total_part_cost_pc' => $total,
            'c_jumlah_cetak_lc' => null,
            'c_jumlah_bahan_lb' => null,
            'c_sheet_compare_info' => $this->compareSuppliers($part, $qty, $geo),
        ]);
        $part->description_calc = $this->describe($part->part_type_code, 0, $process, 0, 0, 0, 0, $geo['sheet_cost_pc'], $total, ['SHEET_OK', $manual ? 'GEO_MANUAL' : 'GEO_OK', 'COAT_SKIP', 'LAM_SKIP', 'TOOL_SKIP']);
        $part->save();
    }

    private function guardDiscount(float $disc): float
    {
        $min = SysConstant::number('OB_DISCOUNT_RM_MIN', -0.99);
        $max = SysConstant::number('OB_DISCOUNT_RM_MAX', 0.99);
        if ($disc < $min || $disc > $max) {
            throw new \RuntimeException("Discount/Surcharge {$disc} di luar batas ({$min} s/d {$max}). Isi pecahan, mis. -0.05 = surcharge 5%.");
        }

        return $disc;
    }

    /** SHEET manual legacy: lebar trim, isi/trim, panjang diketik officer. */
    protected function manualSheetGeometry(IqpCostingPart $part, float $qty, ?string $supplier = null): array
    {
        $trim = (float) $part->i_lebar_trim_cm;
        $isiTrim = max(1, (int) $part->i_isi_per_trim);
        $panjang = (float) $part->i_sheet_panjang_cm;
        $isiLb = $this->positive($part->i_isi_per_lb_kerja);
        $rm = ($qty / $isiLb) / $isiTrim * ($panjang / 100);
        $supplier ??= $part->supplier_sheet_code ?: 'UJK';

        $disc = ($part->i_discount_rm !== null && $supplier === ($part->supplier_sheet_code ?: 'UJK'))
            ? (float) $part->i_discount_rm
            : (MatSheetDiscountTier::lookup($supplier, $rm) ?? 0.0);
        $disc = $this->guardDiscount($disc);

        $index = (float) (MatSheetRate::whereKey($part->sheet_rate_id)->value('index_substance') ?? 0);

        return [
            'panjang_sheet_cm' => $panjang,
            'lebar_sheet_cm' => $part->i_sheet_lebar_cm !== null ? (float) $part->i_sheet_lebar_cm : null,
            'isi_trim' => $isiTrim,
            'lebar_trim_cm' => $trim,
            'running_meter' => $rm,
            'discount_rm' => $disc,
            'sheet_cost_pc' => ($trim * $panjang / 10000) * $index / $isiTrim * (1 - $disc),
        ];
    }

    /**
     * SHEET_DIGITAL: geometri diturunkan dari dimensi + flute (diverifikasi
     * byte-presisi ke Excel "OB COCO TOKYO..." blok UJK). Lebar mesin =
     * konstanta SHEET_MACHINE_WIDTH_CM (default 180, keputusan user 2026-09-18).
     */
    protected function computeSheetGeometry(IqpCostingPart $part, float $qty, ?string $supplier = null): array
    {
        $supplier ??= $part->supplier_sheet_code ?: 'UJK';
        $default = SysConstant::number('SHEET_MACHINE_WIDTH_CM', 180);
        $machineWidth = SysConstant::number('SHEET_MACHINE_WIDTH_CM_'.$supplier, $default);
        $gap = SysConstant::number('SHEET_TRIM_GAP_CM', 2);

        $allowance = $part->flute_type ? MatFluteAllowance::where('flute_type', $part->flute_type)->where('activate', true)->first() : null;
        if (! $allowance) {
            throw new \RuntimeException("flute_type '{$part->flute_type}' tidak ditemukan di mat_flute_allowance -- isi Jenis Flute, atau isi Lebar Trim / Isi Trim / Panjang Piece manual.");
        }

        $p = (float) $part->i_dim_panjang_mm;
        $l = (float) $part->i_dim_lebar_mm;
        $t = (float) $part->i_dim_tinggi_mm;
        $totalPMm = ((float) $allowance->leb_panjang_mm + (float) $allowance->leb_lebar_mm) * 2;
        $totalLMm = ((float) $allowance->leb_lebar_mm + (float) $allowance->leb_tinggi_mm) + 3;

        $panjang = (($p + $l) * 2 + $totalPMm + (float) $part->i_lidah_lem_mm) / 10;
        $lebar = ($l + $t + $totalLMm) / 10;
        $isiTrim = max($lebar > 0 ? floor(($machineWidth - $gap) / $lebar) : 0, 1);
        $trim = ceil(($lebar * $isiTrim + $gap) / 5) * 5;

        $rm = ($qty / $this->positive($part->i_isi_per_lb_kerja, 0.0001)) / $isiTrim * ($panjang / 100);

        if ($part->i_discount_rm !== null && $supplier === ($part->supplier_sheet_code ?: 'UJK')) {
            $disc = (float) $part->i_discount_rm;
        } else {
            $disc = MatSheetDiscountTier::lookup($supplier, $rm);
            if ($disc === null) {
                throw new \RuntimeException("Running meter {$rm} di bawah tier diskon minimum (KURANG) untuk supplier '{$supplier}' — volume order belum cukup, pertimbangkan part_type lain.");
            }
        }
        $disc = $this->guardDiscount((float) $disc);

        $sheetRate = $part->sheet_rate_id ? MatSheetRate::find($part->sheet_rate_id) : null;
        if (! $sheetRate) {
            throw new \RuntimeException('Substansi sheet (sheet_rate_id) belum diisi/tidak ditemukan.');
        }

        return [
            'panjang_sheet_cm' => $panjang,
            'lebar_sheet_cm' => $lebar,
            'isi_trim' => $isiTrim,
            'lebar_trim_cm' => $trim,
            'running_meter' => $rm,
            'discount_rm' => $disc,
            'sheet_cost_pc' => (($trim * $panjang) / 10000) * (float) $sheetRate->index_substance / $isiTrim * (1 - $disc),
        ];
    }

    /** "Info Harga Supplier" (legacy compare_sheet_suppliers): harga sheet/PC tiap supplier aktif. */
    protected function compareSuppliers(IqpCostingPart $part, float $qty, ?array $geo = null): ?string
    {
        if (! $part->sheet_rate_id || $qty <= 0) {
            return null;
        }

        $lines = [];
        foreach (MatSheetSupplier::where('activate', true)->orderBy('sort_order')->pluck('code') as $code) {
            try {
                $g = ((float) $part->i_lebar_trim_cm > 0 && (int) $part->i_isi_per_trim > 0 && (float) $part->i_sheet_panjang_cm > 0)
                    ? $this->manualSheetGeometry($part, $qty, $code)
                    : $this->computeSheetGeometry($part, $qty, $code);
            } catch (\Throwable) {
                $lines[] = "{$code}: tier tidak tersedia";

                continue;
            }
            $d = $g['discount_rm'];
            $label = $d < 0 ? 'surcharge '.number_format(abs($d) * 100, 1).'%' : 'disc '.number_format($d * 100, 1).'%';
            $lines[] = "{$code}: Rp ".number_format($g['sheet_cost_pc'], 2)." ({$label}, RM=".number_format($g['running_meter'], 1).')';
        }

        return $lines ? implode("\n", $lines) : null;
    }

    private function describe(string $type, float $paper, float $proc, float $coat, float $lam, float $sfef, float $toolPc, float $sheet, float $total, array $flags): string
    {
        $f = fn ($v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return 'v2 '.now()->format('d/m/Y H:i:s')."\n{$type} paper=".$f($paper)."\nproc=".$f($proc).' coat='.$f($coat)
            ."\nlam=".$f($lam).' sfef='.$f($sfef)."\ntool=".$f($toolPc).' sheet='.$f($sheet)."\nTOTAL=".$f($total)
            ."\nflag=".implode(' ', array_unique($flags));
    }

    protected function rollUpHeader(IqpCosting $costing): void
    {
        $material = $process = $finishing = $tooling = 0.0;

        foreach ($costing->parts()->get() as $part) {
            $perSet = $this->positive($part->qty_per_set);
            $material += ((float) $part->c_paper_cost_pc + (float) $part->c_sheet_cost_pc) * $perSet;
            $process += (float) $part->c_process_cost_pc * $perSet;
            $finishing += (float) $part->c_total_coating_pc * $perSet;
            $tooling += (float) $part->c_tooling_cost_order;
        }

        $costing->fill([
            'total_material_cost_pc' => $material,
            'total_process_cost_pc' => $process,
            'total_finishing_cost_pc' => $finishing,
            'c_tooling_cost_order' => $tooling,
        ])->save();
    }

    /**
     * Ladder harga = Excel HPP asli (procost_formula_library.md §VIII), dihitung
     * per part krn aturan toleransinya beda per tipe:
     *  - DC/DC+SF/DC+SHEET/KRAFT: toleransi ADITIF ×(1+t) (J44=J43×B44, = legacy hpp #5/#6/#8).
     *  - SHEET/SHEET_DIGITAL: toleransi ÷(1−t) (OB COCO C31/D31, = legacy hpp #9).
     * Jalur INCLUDE PPN (kolom K/D Excel): bahan beli ber-PPN (kertas, sheet,
     * laminasi sheet, SF EF) ÷ PPN_INPUT_MATERIAL_DIVISOR -> DPP; toleransi DC tetap
     * dari HPP kotor (K44=J44, quirk Excel yg juga dipakai legacy). Harga akhir =
     * DPP ÷(1−m) ×(1+PPN). Jalur EXCLUDE (NON_PPN, kolom J/C) = HPP kotor ÷(1−m).
     */
    protected function calculateTier(IqpCosting $costing, \App\Models\Sdi\IqpCostingPriceTier $tier): void
    {
        $qty = max((float) $tier->qty_pcs, 0.0001);
        $t = min((float) $tier->tolerance_pct, 99.9999) / 100;
        $withPpn = $costing->ppn_mode !== 'NON_PPN';
        $credit = 1 - 1 / $this->positive(SysConstant::number('PPN_INPUT_MATERIAL_DIVISOR', 1.1));

        $hppPc = $toolingPc = $creditPc = $afterTolGross = $afterTolNet = 0.0;
        foreach ($costing->parts()->get() as $part) {
            $perSet = $this->positive($part->qty_per_set);
            $toolPc = (float) $part->c_tooling_cost_order / $qty;
            $gross = (float) $part->c_total_part_cost_pc * $perSet + $toolPc;
            $inputMat = ((float) $part->c_paper_cost_pc + (float) $part->c_sheet_cost_pc
                + (float) $part->c_lam_flat_cost_pc + (float) $part->c_sf_ef_cost_pc) * $perSet;
            $net = $withPpn ? $gross - $inputMat * $credit : $gross;

            if (in_array($part->part_type_code, ['SHEET', 'SHEET_DIGITAL'], true)) {
                $afterTolGross += $gross / (1 - $t);
                $afterTolNet += $net / (1 - $t);
            } else {
                $afterTolGross += $gross * (1 + $t);
                $afterTolNet += $net + $gross * $t;
            }
            $hppPc += $gross;
            $toolingPc += $toolPc;
            $creditPc += $gross - $net;
        }

        $step = (float) $tier->rounding_step;
        $round = fn (float $v) => $step <= 0 ? $v : match ($tier->rounding_mode) {
            'UP', 'CEILING' => ceil($v / $step) * $step,
            'DOWN', 'FLOOR' => floor($v / $step) * $step,
            'NEAREST' => round($v / $step) * $step,
            default => $v, // NONE -- Excel & legacy tidak membulatkan
        };

        $m = min((float) $tier->margin_pct, 99.9999) / 100;
        $afterMarginGross = $round($afterTolGross) / (1 - $m);
        $dpp = $withPpn ? $round($afterTolNet) / (1 - $m) : $afterMarginGross;

        $tier->fill([
            'c_tooling_pc' => $toolingPc,
            'c_hpp_pc' => $hppPc,
            'c_ppn_input_credit_pc' => $creditPc,
            'c_after_tolerance_pc' => $afterTolGross,
            'c_after_round_pc' => $round($afterTolGross),
            'c_after_margin_pc' => $afterMarginGross,
            'c_after_ppn_pc' => $withPpn ? $dpp * (1 + (float) $costing->ppn_pct / 100) : $dpp,
            'price_per_pcs' => $dpp,
        ])->save();
    }
}
