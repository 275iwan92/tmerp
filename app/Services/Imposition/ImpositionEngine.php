<?php

namespace App\Services\Imposition;

// Port 1:1 dari algoritma JS docs/mockups/inquiry_imposition.html.
// Coba 3 metode (tegak/putar/campur), pilih yang menghasilkan UP terbanyak.
class ImpositionEngine
{
    public function run(float $planoW, float $planoH, float $flatW, float $flatH, float $gap, float $gripper, float $margin): array
    {
        $areaW = $planoW - 2 * $margin;
        $areaH = $planoH - $gripper - 2 * $margin;

        $up = $this->gridCount($areaW, $areaH, $flatW, $flatH, $gap);
        $rot = $this->gridCount($areaW, $areaH, $flatH, $flatW, $gap);

        if ($up['n'] >= $rot['n']) {
            $best = $up['n'];
            $method = 'TEGAK';
            $layout = ['type' => 'grid', 'rot' => false, 'cols' => $up['cols'], 'rows' => $up['rows'], 'iw' => $flatW, 'ih' => $flatH];
        } else {
            $best = $rot['n'];
            $method = 'PUTAR';
            $layout = ['type' => 'grid', 'rot' => true, 'cols' => $rot['cols'], 'rows' => $rot['rows'], 'iw' => $flatH, 'ih' => $flatW];
        }

        $mainCols = $this->fit($areaW, $flatW, $gap);
        $usedW = $mainCols * ($flatW + $gap) - $gap;
        $restW = $areaW - $usedW - $gap;
        $mainRows = $this->fit($areaH, $flatH, $gap);
        $mixMain = $mainCols * $mainRows;

        $rotCols = $this->fit($restW, $flatH, $gap);
        $rotRows = $this->fit($areaH, $flatW, $gap);
        $mixRot = $rotCols * $rotRows;
        $mixTotal = $mixMain + $mixRot;

        if ($mixTotal > $best && $mixRot > 0) {
            $best = $mixTotal;
            $method = 'CAMPUR';
            $layout = [
                'type' => 'mix',
                'main' => ['cols' => $mainCols, 'rows' => $mainRows, 'iw' => $flatW, 'ih' => $flatH],
                'rot' => ['cols' => $rotCols, 'rows' => $rotRows, 'iw' => $flatH, 'ih' => $flatW, 'offX' => $usedW + $gap],
            ];
        }

        $areaTotal = $areaW * $areaH;
        $used = $best * $flatW * $flatH;
        $eff = $areaTotal > 0 ? ($used / $areaTotal * 100) : 0;

        return [
            'up' => $best,
            'method' => $method,
            'efficiency_pct' => round(max(0, min(100, $eff)), 2),
            'waste_pct' => round(max(0, min(100, 100 - $eff)), 2),
            'layout' => $layout,
            'area_w' => $areaW,
            'area_h' => $areaH,
        ];
    }

    /**
     * Sel-sel individual (koordinat mm dari sudut kiri-atas plano) untuk render visual.
     */
    public function cells(array $result, float $margin, float $gripper, float $gap): array
    {
        $layout = $result['layout'];
        $ox = $margin;
        $oy = $gripper + $margin;
        $cells = [];
        $n = 0;

        if ($layout['type'] === 'grid') {
            for ($r = 0; $r < $layout['rows']; $r++) {
                for ($c = 0; $c < $layout['cols']; $c++) {
                    $n++;
                    $cells[] = [
                        'x' => $ox + $c * ($layout['iw'] + $gap),
                        'y' => $oy + $r * ($layout['ih'] + $gap),
                        'w' => $layout['iw'],
                        'h' => $layout['ih'],
                        'rot' => $layout['rot'],
                        'n' => $n,
                    ];
                }
            }

            return $cells;
        }

        $main = $layout['main'];
        for ($r = 0; $r < $main['rows']; $r++) {
            for ($c = 0; $c < $main['cols']; $c++) {
                $n++;
                $cells[] = [
                    'x' => $ox + $c * ($main['iw'] + $gap),
                    'y' => $oy + $r * ($main['ih'] + $gap),
                    'w' => $main['iw'],
                    'h' => $main['ih'],
                    'rot' => false,
                    'n' => $n,
                ];
            }
        }

        $rot = $layout['rot'];
        $rox = $ox + $rot['offX'];
        for ($r = 0; $r < $rot['rows']; $r++) {
            for ($c = 0; $c < $rot['cols']; $c++) {
                $n++;
                $cells[] = [
                    'x' => $rox + $c * ($rot['iw'] + $gap),
                    'y' => $oy + $r * ($rot['ih'] + $gap),
                    'w' => $rot['iw'],
                    'h' => $rot['ih'],
                    'rot' => true,
                    'n' => $n,
                ];
            }
        }

        return $cells;
    }

    /**
     * Tingkat 1 ENGINE (docs/inquiry_product_costing/productdevelopment_tier1.md §4.3a) --
     * parametric sweep: lidah (flap) bukan angka tetap tapi variabel dalam rentang valid
     * struktural [flapMin, flapMax]. Tiap nilai lidah dihitung ulang pakai run() (yang sudah
     * otomatis coba TEGAK/PUTAR/CAMPUR), lalu di-ranking by UP DESC, efficiency DESC.
     * Officer pilih salah satu dari top-N ("Engine suggests, Officer confirms" CLAUDE.md §4).
     *
     * @return array<int, array> tiap elemen = hasil run() + 'flap_length_mm'/'flat_w'/'flat_h'
     */
    public function sweepFlap(
        float $planoW, float $planoH, float $bodyW, float $bodyH, string $flapSide,
        float $flapMin, float $flapMax, float $step, float $gap, float $gripper, float $margin,
        int $topN = 5
    ): array {
        $results = [];

        if ($flapMax < $flapMin || $step <= 0) {
            return $results;
        }

        for ($flap = $flapMin; $flap <= $flapMax + 0.0001; $flap += $step) {
            [$flatW, $flatH] = $this->applyFlap($bodyW, $bodyH, $flapSide, $flap);
            $r = $this->run($planoW, $planoH, $flatW, $flatH, $gap, $gripper, $margin);
            $r['flap_length_mm'] = round($flap, 2);
            $r['flat_w'] = $flatW;
            $r['flat_h'] = $flatH;
            $results[] = $r;
        }

        usort($results, fn (array $a, array $b) => $b['up'] <=> $a['up'] ?: $b['efficiency_pct'] <=> $a['efficiency_pct']);

        return array_slice($results, 0, $topN);
    }

    /**
     * Dimensi bentangan (flat) efektif = body + lidah, tergantung sisi tempat lidah menempel.
     */
    private function applyFlap(float $bodyW, float $bodyH, string $flapSide, float $flap): array
    {
        return match ($flapSide) {
            'LEFT', 'RIGHT' => [$bodyW + $flap, $bodyH],
            default => [$bodyW, $bodyH + $flap], // TOP|BOTTOM
        };
    }

    /**
     * Tingkat 2 ENGINE (docs/inquiry_product_costing/productdevelopment_tier1.md §4.3a,
     * REVISI 2026-09-02 — shift jadi variabel UTAMA, flap-shrink SEKUNDER) — pola interlock
     * "69": copy kedua diputar 180° & digeser (shift_offset_mm) sepanjang sisi berhimpitan
     * supaya lidahnya saling mengunci, memperkecil jarak ulang (pitch) antar baris/kolom
     * dibanding grid biasa (yang jaraknya = body + lidah penuh, tanpa overlap).
     *
     * CATATAN JUJUR: dokumen sumber (§4.3a) sendiri masih level pseudocode/observasi
     * lapangan, BUKAN spesifikasi presisi (§7 poin 9: user akan trial engine sampai
     * cocok dgn keputusan officer riil, belum ada dataset validasi). Model yang dipakai
     * di sini: pitch efektif pada sumbu tempat lidah menempel = (body + flap - shift),
     * shift dibatasi maks = flap (tak bisa menyerap lebih dari panjang lidah itu sendiri).
     * shift=0 pada flap manapun = SAMA PERSIS dgn hasil sweepFlap() (Tingkat 1) — Tingkat 2
     * murni MEMPERLUAS ruang pencarian Tingkat 1 dgn 1 variabel baru, bukan mekanisme lain.
     *
     * @return array<int, array> tiap elemen = hasil run() + 'shift_offset_mm'/'flap_length_mm'
     */
    public function sweepInterlock(
        float $planoW, float $planoH, float $bodyW, float $bodyH, string $flapSide,
        float $flapMin, float $flapMax, float $flapStep,
        float $shiftStep, float $gap, float $gripper, float $margin,
        int $topN = 5
    ): array {
        $results = [];

        if ($flapMax < $flapMin || $flapStep <= 0 || $shiftStep <= 0) {
            return $results;
        }

        for ($flap = $flapMin; $flap <= $flapMax + 0.0001; $flap += $flapStep) {
            $maxShift = max(0.0, $flap); // tak bisa menyerap overlap lebih dari panjang lidah sendiri
            for ($shift = 0.0; $shift <= $maxShift + 0.0001; $shift += $shiftStep) {
                [$effW, $effH] = $this->applyFlap($bodyW, $bodyH, $flapSide, $flap - $shift);
                $r = $this->run($planoW, $planoH, $effW, $effH, $gap, $gripper, $margin);
                $r['flap_length_mm'] = round($flap, 2);
                $r['shift_offset_mm'] = round($shift, 2);
                $r['flat_w'] = $effW;
                $r['flat_h'] = $effH;
                $results[] = $r;
            }
        }

        usort($results, fn (array $a, array $b) => $b['up'] <=> $a['up'] ?: $b['efficiency_pct'] <=> $a['efficiency_pct']);

        return array_slice($results, 0, $topN);
    }

    private function fit(float $available, float $item, float $gap): int
    {
        if ($item + $gap <= 0) {
            return 0;
        }

        return max(0, (int) floor(($available + $gap) / ($item + $gap)));
    }

    private function gridCount(float $areaW, float $areaH, float $itemW, float $itemH, float $gap): array
    {
        if ($itemW <= 0 || $itemH <= 0) {
            return ['cols' => 0, 'rows' => 0, 'n' => 0];
        }

        $cols = $this->fit($areaW, $itemW, $gap);
        $rows = $this->fit($areaH, $itemH, $gap);

        return ['cols' => $cols, 'rows' => $rows, 'n' => $cols * $rows];
    }
}
