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
