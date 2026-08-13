<?php

namespace App\Services\Costing;

use App\Models\Master\MatFluteAllowance;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatSheetDiscountTier;
use App\Models\Master\MatSheetRate;
use App\Models\Master\ProdPartType;
use App\Models\Master\SysConstant;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use Illuminate\Support\Facades\DB;

/**
 * Port dari method Scriptcase `calc_cogs` (docs/SALES_TIER_COSTING.md).
 * part_type DC dan SHEET_DIGITAL yang diimplementasikan — part_type lain
 * (DC+SF, DC+SHEET, KRAFT, SHEET polos) sengaja dilempar exception, lihat
 * ProdPartType::is_engine_ready.
 *
 * SHEET_DIGITAL diverifikasi byte-presisi terhadap Excel asli
 * "OB COCO TOKYO FLAT 427X220X172MM 1800 BOX ....xlsx" (blok UJK, kolom A-H) —
 * lihat tests/Feature/CostingEngineTest.php.
 *
 * DC: dua formula (area cetak, konversi ongkos cetak/plong per-pc) TIDAK
 * dirinci eksplisit di dokumen sumber (yang jadi acuan cuma "sumber kebenaran:
 * calc_cogs v14/v16" — file itu tidak tersedia di repo ini) — diisi dengan
 * asumsi paling masuk akal dari nama kolom. WAJIB diverifikasi ke source
 * asli/Excel NS40 sebelum dipakai harga produksi sungguhan (masih pending,
 * beda dari SHEET_DIGITAL yang sudah punya bukti Excel).
 */
class CostingEngine
{
    public function run(IqpCosting $costing): IqpCosting
    {
        DB::connection('tm2sdi')->transaction(function () use ($costing) {
            $parts = $costing->parts()->with('coatings')->get();

            // SHEET_DIGITAL: running_meter/discount_rm/c_sheet_cost_pc dihitung SEKALI per
            // part pakai qty tier UTAMA (is_primary) — mengikuti model Excel sumber (1 qty =
            // 1 hitungan), BEDA dari DC yang part-nya genuinely konstan lintas tier. Kalau
            // tier lain melewati batas RM berbeda, c_sheet_cost_pc part TIDAK ikut berubah
            // per tier (keterbatasan yang disengaja, bukan bug — ladder multi-tier utk SHEET
            // adalah pekerjaan lanjutan).
            $primaryQty = (float) ($costing->priceTiers()->where('is_primary', true)->value('qty_pcs')
                ?? $costing->priceTiers()->value('qty_pcs')
                ?? 0);

            foreach ($parts as $part) {
                match ($part->part_type_code) {
                    'DC' => $this->calculateDcPart($part),
                    'SHEET_DIGITAL' => $this->calculateSheetDigitalPart($part, $primaryQty),
                    default => throw new \RuntimeException("part_type_code '{$part->part_type_code}' belum diimplementasi CostingEngine (baru DC & SHEET_DIGITAL)."),
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

    protected function calculateDcPart(IqpCostingPart $part): void
    {
        $rim = SysConstant::number('RIM_SHEETS', 500);
        $planoRefArea = SysConstant::number('PLANO_REF_AREA', 8611);
        $filmRateDefault = SysConstant::number('FILM_RATE_DEFAULT', 55);
        $filmFactorDefault = SysConstant::number('FILM_FACTOR_DEFAULT', 0.25);
        $platePerColor = SysConstant::number('PLATE_PER_COLOR', 90000);

        $cut = max((float) $part->i_cut_per_plano, 0.0001); // guard >0
        $isi = max((float) $part->i_isi_per_lb, 0.0001); // guard >0

        // SESI 1 — area cetak (ASUMSI: sama dgn area plano; lihat catatan class-level)
        $areaCetakCm2 = (float) $part->i_panjang_plano_cm * (float) $part->i_lebar_plano_cm;

        // SESI 2 — bahan (§B.2)
        $grammage = $part->grammage_id ? MatGrammage::find($part->grammage_id) : null;
        $paperCostPc = 0.0;

        if ($grammage && (float) $grammage->duplex_price_list > 0) {
            // Jalur A: duplex
            $dcOverride = (float) $part->i_dc_persen_override;
            $dcPct = $dcOverride > 0 ? $dcOverride : (float) $grammage->duplex_dc_pct;
            $dcf = 1 + ($dcPct / 100);
            $paperCostPc = ($areaCetakCm2 / $planoRefArea) * (float) $grammage->duplex_price_list * $dcf / $rim / $cut / $isi;
        } elseif ($grammage && (float) $grammage->ivory_ap_price_per_kg > 0) {
            // Jalur B: ivory/AP
            $paperCostPc = ($areaCetakCm2 / $planoRefArea) * (float) $grammage->ivory_ap_price_per_kg * ((float) $grammage->grammage_value / 2) / $rim / $cut / $isi;
        }

        // SESI 3 — proses dasar (ASUMSI konversi per-pc, lihat catatan class-level)
        $cetakCostPc = (float) $part->i_ongkos_cetak_rim / $cut / $isi;
        $plongCostPc = (float) $part->i_ongkos_plong_lb / $isi;
        $lemCostPc = (float) $part->i_ongkos_lem_pc;

        // SESI 4 — coating multi-layer (§B.3): COATING dulu, urutan child sudah `seq`
        $totalCoatingPc = 0.0;
        foreach ($part->coatings as $coating) {
            $costPerLc = $areaCetakCm2 * (float) $coating->index_coating;
            $costPerPc = $costPerLc / $isi; // TIDAK dibagi cut
            $coating->c_cost_per_lc = $costPerLc;
            $coating->c_cost_per_pc = $costPerPc;
            $coating->save();
            $totalCoatingPc += $costPerPc;
        }

        // SESI 7 — tooling (§B.6), disimpan ORDER-level (bukan /pc)
        $ff = $part->i_film_faktor !== null ? (float) $part->i_film_faktor : $filmFactorDefault;
        $frate = $part->i_film_rate !== null ? (float) $part->i_film_rate : $filmRateDefault;
        $filmCostOrder = ($ff ** 2) * $areaCetakCm2 * $frate;

        $plateRate = $part->i_ongkos_plate_warna !== null && (float) $part->i_ongkos_plate_warna > 0
            ? (float) $part->i_ongkos_plate_warna
            : $platePerColor;
        $plateCostOrder = $plateRate * (int) $part->i_jumlah_warna;

        $pisauRate = $part->i_pisau_rate_rp !== null
            ? (float) $part->i_pisau_rate_rp
            : (float) (ProdPartType::where('part_type_code', $part->part_type_code)->value('pisau_rate_rp') ?? 0);
        $knifeCostOrder = (float) $part->i_pisau_faktor * $pisauRate;

        $toolingCostOrder = $filmCostOrder + $plateCostOrder + $knifeCostOrder;

        // SESI 10 — total/pc (anti double-count: proses+coating, TANPA tooling — tooling di tier)
        $processCostPc = $cetakCostPc + $plongCostPc + $lemCostPc + $totalCoatingPc;
        $totalPartCostPc = $paperCostPc + $processCostPc;

        $part->fill([
            'c_area_cetak_cm2' => $areaCetakCm2,
            'c_paper_cost_pc' => $paperCostPc,
            'c_cetak_cost_pc' => $cetakCostPc,
            'c_plong_cost_pc' => $plongCostPc,
            'c_lem_cost_pc' => $lemCostPc,
            'c_total_coating_pc' => $totalCoatingPc,
            'c_film_cost_order' => $filmCostOrder,
            'c_plate_cost_order' => $plateCostOrder,
            'c_knife_cost_order' => $knifeCostOrder,
            'c_tooling_cost_order' => $toolingCostOrder,
            'c_process_cost_pc' => $processCostPc,
            'c_total_part_cost_pc' => $totalPartCostPc,
            'description_calc' => 'CostingEngine v1 (DC) · '.now()->toDateTimeString(),
        ])->save();
    }

    /**
     * Formula geometri+harga SHEET_DIGITAL (§B.5 + §B.2 SALES_TIER_COSTING.md),
     * diverifikasi byte-presisi ke Excel "OB COCO TOKYO..." blok UJK (kolom A-H).
     */
    protected function calculateSheetDigitalPart(IqpCostingPart $part, float $qty): void
    {
        $machineWidth = SysConstant::number('SHEET_MACHINE_WIDTH_CM', 180);
        $gap = SysConstant::number('SHEET_TRIM_GAP_CM', 2);

        $p = (float) $part->i_dim_panjang_mm;
        $l = (float) $part->i_dim_lebar_mm;
        $t = (float) $part->i_dim_tinggi_mm;
        $lidahLem = (float) $part->i_lidah_lem_mm;

        $allowance = $part->flute_type ? MatFluteAllowance::where('flute_type', $part->flute_type)->where('is_active', true)->first() : null;
        if (! $allowance) {
            throw new \RuntimeException("flute_type '{$part->flute_type}' tidak ditemukan di mat_flute_allowance.");
        }

        $totalPMm = ((float) $allowance->leb_panjang_mm + (float) $allowance->leb_lebar_mm) * 2;
        $totalLMm = ((float) $allowance->leb_lebar_mm + (float) $allowance->leb_tinggi_mm) + 3;

        // SESI 3b — geometri (§B.5 poin 3)
        $panjangSheetCm = (($p + $l) * 2 + $totalPMm + $lidahLem) / 10;
        $lebarSheetCm = ($l + $t + $totalLMm) / 10;

        // Trim (§B.5 poin 4)
        $isiTrim = $lebarSheetCm > 0 ? floor(($machineWidth - $gap) / $lebarSheetCm) : 0;
        $isiTrim = max($isiTrim, 1); // guard div-by-zero di bawah
        $lebarTrimCm = ceil(($lebarSheetCm * $isiTrim + $gap) / 5) * 5;

        // Running meter + tier diskon (§B.5 poin 5)
        $isiPerLbKerja = max((float) $part->i_isi_per_lb_kerja, 0.0001);
        $jumlahOrder = $qty / $isiPerLbKerja;
        $runningMeter = $jumlahOrder / $isiTrim * ($panjangSheetCm / 100);

        $supplierCode = $part->supplier_sheet_code ?: 'UJK';
        $discountRm = MatSheetDiscountTier::lookup($supplierCode, $runningMeter);
        if ($discountRm === null) {
            throw new \RuntimeException("Running meter {$runningMeter} di bawah tier diskon minimum (KURANG) untuk supplier '{$supplierCode}' — volume order belum cukup utk SHEET, pertimbangkan part_type lain.");
        }

        // Harga sheet (§B.2)
        $sheetRate = $part->sheet_rate_id ? MatSheetRate::find($part->sheet_rate_id) : null;
        if (! $sheetRate) {
            throw new \RuntimeException('sheet_rate_id belum diisi/tidak ditemukan.');
        }
        $sheetCostPc = (($lebarTrimCm * $panjangSheetCm) / 10000) * (float) $sheetRate->index_substance / $isiTrim * (1 - $discountRm);

        // Ongkos proses SHEET (§B.1 tabel part_type) — print dikali 2
        $printCostPc = (float) $part->i_ongkos_print_pc * 2;
        $processCostPc = $printCostPc
            + (float) $part->i_ongkos_diecut_pc
            + (float) $part->i_ongkos_slitter_pc
            + (float) $part->i_ongkos_slotter_pc
            + (float) $part->i_ongkos_join_pc
            + (float) $part->i_ongkos_partisi_pc
            + (float) $part->i_ongkos_layer_pc
            + (float) $part->i_ongkos_qc_pack_pc
            + (float) $part->i_ongkos_kirim_pc;

        // SHEET: TOOL_SKIP (§B.1) — tooling sengaja 0, tak ada film/plate/pisau.
        $totalPartCostPc = $sheetCostPc + $processCostPc;

        $part->fill([
            'c_panjang_sheet_cm' => $panjangSheetCm,
            'c_lebar_sheet_cm' => $lebarSheetCm,
            'c_isi_trim' => $isiTrim,
            'c_lebar_trim_cm' => $lebarTrimCm,
            'c_running_meter' => $runningMeter,
            'c_discount_rm' => $discountRm,
            'c_sheet_cost_pc' => $sheetCostPc,
            'c_print_cost_pc' => $printCostPc,
            // c_paper_cost_pc/c_cetak_cost_pc dipakai ulang sbg bucket generik roll-up
            // header (material/proses) supaya rollUpHeader() type-agnostic — nilai
            // "asli" bernama tetap ada di c_sheet_cost_pc/c_print_cost_pc di atas utk display.
            'c_paper_cost_pc' => $sheetCostPc,
            'c_cetak_cost_pc' => $processCostPc,
            'c_plong_cost_pc' => 0,
            'c_lem_cost_pc' => 0,
            'c_total_coating_pc' => 0,
            'c_film_cost_order' => 0,
            'c_plate_cost_order' => 0,
            'c_knife_cost_order' => 0,
            'c_tooling_cost_order' => 0,
            'c_process_cost_pc' => $processCostPc,
            'c_total_part_cost_pc' => $totalPartCostPc,
            'description_calc' => 'CostingEngine v1 (SHEET_DIGITAL) · '.now()->toDateTimeString(),
        ])->save();
    }

    protected function rollUpHeader(IqpCosting $costing): void
    {
        $parts = $costing->parts()->get();

        $material = 0.0;
        $process = 0.0;
        $finishing = 0.0;
        $tooling = 0.0;

        foreach ($parts as $part) {
            $material += (float) $part->c_paper_cost_pc;
            $process += (float) $part->c_cetak_cost_pc + (float) $part->c_plong_cost_pc + (float) $part->c_lem_cost_pc;
            $finishing += (float) $part->c_total_coating_pc;
            $tooling += (float) $part->c_tooling_cost_order;
        }

        $costing->fill([
            'total_material_cost_pc' => $material,
            'total_process_cost_pc' => $process,
            'total_finishing_cost_pc' => $finishing,
            'c_tooling_cost_order' => $tooling,
        ])->save();
    }

    protected function calculateTier(IqpCosting $costing, \App\Models\Sdi\IqpCostingPriceTier $tier): void
    {
        $qty = max((float) $tier->qty_pcs, 0.0001);

        $totalHppPc = (float) $costing->total_material_cost_pc + (float) $costing->total_process_cost_pc + (float) $costing->total_finishing_cost_pc;
        $toolingPc = (float) $costing->c_tooling_cost_order / $qty;
        $hppPc = $totalHppPc + $toolingPc;

        // Toleransi = mark-up on price, SAMA gaya dgn margin (÷(1-tol%)), BUKAN ×(1+tol%).
        // Diverifikasi byte-presisi ke Excel asli "OB COCO TOKYO..." (C31 = C30/(1-B31)),
        // beda dari ringkasan §0 SALES_TIER_COSTING.md yang keliru menulis additive.
        $tolerancePct = min((float) $tier->tolerance_pct, 99.9999); // guard div-by-zero
        $afterTolerancePc = $hppPc / (1 - ($tolerancePct / 100));

        $step = max((float) $tier->rounding_step, 0.0001);
        $afterRoundPc = match ($tier->rounding_mode) {
            'UP' => ceil($afterTolerancePc / $step) * $step,
            'DOWN' => floor($afterTolerancePc / $step) * $step,
            default => round($afterTolerancePc / $step) * $step, // NEAREST
        };

        $marginPct = min((float) $tier->margin_pct, 99.9999); // guard div-by-zero saat margin=100
        $afterMarginPc = $afterRoundPc / (1 - ($marginPct / 100));

        // Verifikasi Excel "OB COCO TOKYO...": kolom "NON PPN" tidak pernah kena PPN sama
        // sekali (bukan cuma ppn_pct=0 — beda formula). Kolom "PPN" pula membagi rate dasar
        // dgn 1.1 SEBELUM ladder (index_substance sumbernya sudah PPN-inclusive per catatan
        // Sheet2), baru re-add PPN di akhir — nuansa ini BELUM diimplementasikan (base rate
        // di CostingEngine masih diasumsikan excl-PPN apa adanya). Ditunda, bukan bug diam-diam.
        $afterPpnPc = $costing->ppn_mode === 'NON_PPN'
            ? $afterMarginPc
            : $afterMarginPc * (1 + ((float) $costing->ppn_pct / 100));

        $tier->fill([
            'c_tooling_pc' => $toolingPc,
            'c_hpp_pc' => $hppPc,
            'c_after_tolerance_pc' => $afterTolerancePc,
            'c_after_round_pc' => $afterRoundPc,
            'c_after_margin_pc' => $afterMarginPc,
            'c_after_ppn_pc' => $afterPpnPc,
            'price_per_pcs' => $afterMarginPc,
        ])->save();
    }
}
