<?php

use App\Models\Master\MatPlanoSize;
use App\Models\Master\ProdType;
use App\Services\Imposition\ImpositionEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migration 000012 isi 66 iqp_part & 33 prod_part dummy tapi CUMA angka acak
// (flat_w/flat_h asal, cm_length/cm_width acak, layout_detail null) -- tidak
// cukup buat lihat ENGINE beneran jalan (visual picker InqProd butuh
// type_code+dims asli nunjuk ke prod_type_param yang benar; ProDev Tool #1
// butuh body dims+flap yang masuk akal supaya sweepFlap() bukan cuma
// placeholder). User minta bisa "lihat engine bekerja dengan baik" --
// migration ini PANGGIL ImpositionEngine sungguhan (App\Services\Imposition\
// ImpositionEngine, formula sama persis dgn yg dipakai prodev.blade.php::
// runEngineTingkat1()/pickCandidate()), bukan menghasilkan angka bohongan.
// Cakupan: SEMUA baris dummy dari 000012 (tag IQ2690%/PDDUM%), tidak bikin
// baris baru -- murni enrichment kolom.
return new class extends Migration
{
    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');
        $now = now();
        $engine = new ImpositionEngine;

        $boxTypes = ProdType::with('params')->whereIn('type_code', [
            'CARD_BOX', 'RTE_BOX', 'STE_BOX', 'CRASH_LOCK', 'SLEEVE', 'GIFT_BOX',
        ])->get()->keyBy('type_code');
        $typeCodes = $boxTypes->keys()->values()->all();

        $planos = MatPlanoSize::where('activate', true)->orderBy('sort_order')->get();

        // 1. InqProd: kasih tiap iqp_part dummy sebuah prod_type BOX sungguhan +
        //    dims yang mengikuti param definisi type itu (jitter +-15% dari
        //    default_value, dibulatkan ke kelipatan 5mm) -- ini yang bikin visual
        //    picker & live dieline preview di inquiry/form.blade.php nunjuk ke
        //    data yang valid, bukan field kosong.
        $parts = $sdi->table('iqp_part')
            ->join('iqp_inquiry', 'iqp_part.inquiry_id', '=', 'iqp_inquiry.id_inquiry')
            ->where('iqp_inquiry.inquiry_no', 'like', 'IQ2690%')
            ->select('iqp_part.id_part', 'iqp_part.inquiry_id')
            ->orderBy('iqp_part.id_part')
            ->get();

        $dimsByPart = [];

        foreach ($parts as $i => $row) {
            $typeCode = $typeCodes[$i % count($typeCodes)];
            $type = $boxTypes[$typeCode];
            $dims = [];

            foreach ($type->params as $p) {
                $default = (float) ($p->default_value ?? 0);
                if ($default <= 0) {
                    continue;
                }
                $jitter = $default * (random_int(-15, 15) / 100);
                $dims[$p->param_key] = (float) (max(5, round(($default + $jitter) / 5) * 5));
            }

            $dimsByPart[$row->id_part] = $dims;

            $flatW = $dims['product_length'] ?? 100.0;
            $flatH = $dims['product_width'] ?? 100.0;

            $sdi->table('iqp_part')->where('id_part', $row->id_part)->update([
                'dieline_spec' => json_encode(['type_code' => $typeCode, 'dims' => $dims]),
                'flat_w' => $flatW,
                'flat_h' => $flatH,
                'flat_area' => $flatW * $flatH,
                'updated_by' => 'system',
                'updated_at' => $now,
            ]);
        }

        // 2. ProDev Mode #1 (link ke iqp_part) -- body dims TURUNAN LANGSUNG dari
        //    dims InqProd di atas (cm_length<-product_length/10, cm_width<-
        //    product_width/10), PERSIS formula sendToProDev() di inquiry/form.blade.php
        //    -- supaya part ProDev & inkuiri asalnya konsisten geometrinya.
        $mode1Parts = $mst->table('prod_part')->where('part_code', 'like', 'PDDUM1-%')->get();
        $iqpByProdPartId = $sdi->table('iqp_part')->whereNotNull('prod_part_id')
            ->whereIn('prod_part_id', $mode1Parts->pluck('id_part'))
            ->get()->keyBy('prod_part_id');

        $planoIdx = 0;

        foreach ($mode1Parts as $p) {
            $iqp = $iqpByProdPartId[$p->id_part] ?? null;
            $dims = $iqp ? ($dimsByPart[$iqp->id_part] ?? []) : [];

            $cmLength = round(($dims['product_length'] ?? 200.0) / 10, 2);
            $cmWidth = round(($dims['product_width'] ?? 150.0) / 10, 2);
            $flapLen = $dims['top_tuck_flap'] ?? $dims['glue_flap'] ?? 20.0;

            $this->applyEngineeredPart($mst, $engine, $planos, $planoIdx, $now, $p->id_part, $cmLength, $cmWidth, (float) $flapLen);
        }

        // 3. ProDev Mode #2 (standalone) -- body dims independen, tidak ada
        //    sumber InqProd (memang begitu, Mode #2).
        $mode2Parts = $mst->table('prod_part')->where('part_code', 'like', 'PDDUM2-%')->get();

        foreach ($mode2Parts as $p) {
            $cmLength = random_int(15, 40);
            $cmWidth = random_int(15, 35);
            $flapLen = random_int(15, 30);

            $this->applyEngineeredPart($mst, $engine, $planos, $planoIdx, $now, $p->id_part, (float) $cmLength, (float) $cmWidth, (float) $flapLen);
        }
    }

    // Isi field teknis prod_part (dipakai form + jadi input engine) lalu
    // PANGGIL ImpositionEngine::sweepFlap() sungguhan buat kandidat terbaik,
    // simpan ke prod_layout_group yang sudah dibuat migration 000012 (status
    // DRAFT/CONFIRMED-nya TIDAK disentuh, murni isi hasil engine).
    private function applyEngineeredPart($mst, ImpositionEngine $engine, $planos, int &$planoIdx, $now, int $partId, float $cmLength, float $cmWidth, float $flapLen): void
    {
        $flapMin = max(5.0, round($flapLen * 0.6));
        $flapMax = round($flapLen * 1.6);
        $side = 'TOP';

        $mst->table('prod_part')->where('id_part', $partId)->update([
            'cm_length' => $cmLength,
            'cm_width' => $cmWidth,
            'die_w_cm' => round($cmWidth + 2, 2),
            'die_h_cm' => round($cmLength + 2, 2),
            'sheet_raw_w_cm' => round($cmWidth + 4, 2),
            'sheet_raw_h_cm' => round($cmLength + 4, 2),
            'flap_side' => $side,
            'flap_length_mm' => $flapLen,
            'flap_min_mm' => $flapMin,
            'flap_max_mm' => $flapMax,
            'flap_offset_mm' => 0,
            'joint_type' => 'Lem Samping',
            'up_per_print' => 1,
            'updated_by' => 'system',
            'updated_at' => $now,
        ]);

        $plano = $planos[$planoIdx % $planos->count()];
        $planoIdx++;

        $bodyWmm = $cmWidth * 10;
        $bodyHmm = $cmLength * 10;

        $candidates = $engine->sweepFlap(
            (float) $plano->width_mm, (float) $plano->height_mm,
            $bodyWmm, $bodyHmm, $side, $flapMin, $flapMax, 5.0,
            gap: 3.0, gripper: 12.0, margin: 8.0, topN: 1
        );
        $best = $candidates[0] ?? null;

        if (! $best) {
            return; // dims tak muat di plano manapun -- biarkan layout_group apa adanya (jarang, guard aman)
        }

        $dieRuleMeter = $engine->dieRuleMeterLari($best['layout'], $best['up'], $best['flap_length_mm'], $side, $bodyWmm, $bodyHmm, 3.0);

        $mst->table('prod_layout_group_part')->where('part_id', $partId)->get()
            ->each(function ($pivot) use ($mst, $plano, $best, $dieRuleMeter, $now) {
                $mst->table('prod_layout_group')->where('id_layout_group', $pivot->id_layout_group)->update([
                    'plano_size_id' => $plano->id_plano_size,
                    'plano_w_mm' => $plano->width_mm,
                    'plano_h_mm' => $plano->height_mm,
                    'method' => $best['method'],
                    'up_count' => $best['up'],
                    'efficiency_pct' => $best['efficiency_pct'],
                    'waste_pct' => $best['waste_pct'],
                    'die_rule_meter' => $dieRuleMeter,
                    'layout_detail' => json_encode([
                        'layout' => $best['layout'],
                        'flap_length_mm' => $best['flap_length_mm'],
                        'shift_offset_mm' => $best['shift_offset_mm'] ?? null,
                    ]),
                    'updated_by' => 'system',
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        // Murni enrichment kolom di atas baris yang SUDAH dibuat migration
        // 2026_09_03_000012 -- rollback baris itu sendiri (hapus total) sudah
        // ditangani down() migration itu, tidak perlu diulang di sini.
    }
};
