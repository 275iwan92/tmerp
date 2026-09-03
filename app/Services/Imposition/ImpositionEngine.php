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
     * Public (bukan cuma dipakai internal sweepFlap/sweepInterlock) -- CostingEngine
     * reuse ini utk hitung luas flat per-part saat alokasi proporsional §4.2b
     * (lihat IqpCostingPart::allocateProportionalCost()).
     */
    public function applyFlap(float $bodyW, float $bodyH, string $flapSide, float $flap): array
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

    /**
     * Tool #2 — Pcs→Plano / Best Sheet Finder (BACKLOG.md §0, tier1 §4.3 tabel
     * baris #2; formula acuan `docs/imposition_nesting/prepress_imposition_packaging_guide.md`
     * §3 poin 1 "Pcs to Plano" + Modul B "Gross Sheets"). Kebalikan dari
     * sweepFlap() yang mengunci 1 plano dan mencoba rentang lidah — di sini
     * LOOP semua plano standar aktif (bukan cuma yang dipilih officer),
     * tiap plano dicoba dgn sweepFlap() sendiri (top 1 saja per plano cukup,
     * biar ranking across-plano tidak kebanjiran hasil 1 plano yg sama),
     * lalu di-ranking ulang lintas-plano.
     *
     * Metrik ranking ("biaya termurah", Modul B guide) — 2 mode:
     * - `targetQty` diisi (>0): bandingkan `gross_sheets_needed × luas_plano`
     *   (persis formula "Gross Sheets" guide §Modul B poin 1) — makin kecil
     *   makin murah UTK qty spesifik ini (memperhitungkan sisa/waste di
     *   lembar terakhir, bukan cuma efisiensi rata-rata).
     * - `targetQty` kosong/0: fallback `luas_plano ÷ up_count` (luas per pc)
     *   — proxy biaya krn `mat_plano_size` sendiri tidak simpan harga per
     *   material (harga baru dipilih di tahap Costing via grammage_id),
     *   jadi asumsinya: material/gsm SAMA di semua opsi plano, makin kecil
     *   luas terpakai per pc = makin murah (sejalan `Efficiency %` Modul B
     *   poin 3 — plano lebih efisien = plano lebih murah kalau bahan sama).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Master\MatPlanoSize>  $planos
     * @return array<int, array> tiap elemen = hasil sweepFlap() top-1 + 'plano_size_id'/'plano_name'/'cost_metric'/'gross_sheets'
     */
    public function bestSheet(
        \Illuminate\Support\Collection $planos, float $bodyW, float $bodyH, string $flapSide,
        float $flapMin, float $flapMax, float $step, float $gap, float $gripper, float $margin,
        ?float $targetQty = null, int $topN = 5
    ): array {
        $results = [];

        foreach ($planos as $plano) {
            $planoW = (float) $plano->width_mm;
            $planoH = (float) $plano->height_mm;
            $planoAreaMm2 = $planoW * $planoH;

            $best = $this->sweepFlap($planoW, $planoH, $bodyW, $bodyH, $flapSide, $flapMin, $flapMax, $step, $gap, $gripper, $margin, topN: 1)[0] ?? null;

            if (! $best || $best['up'] < 1) {
                continue; // plano ini tidak muat 1 pun -- skip dari ranking
            }

            if ($targetQty !== null && $targetQty > 0) {
                $grossSheets = (int) ceil($targetQty / $best['up']);
                $costMetric = $grossSheets * $planoAreaMm2;
                $best['gross_sheets'] = $grossSheets;
            } else {
                $costMetric = $planoAreaMm2 / $best['up'];
                $best['gross_sheets'] = null;
            }

            $best['plano_size_id'] = $plano->id_plano_size;
            $best['plano_name'] = $plano->plano_name;
            $best['cost_metric'] = $costMetric;
            $results[] = $best;
        }

        usort($results, fn (array $a, array $b) => $a['cost_metric'] <=> $b['cost_metric']);

        return array_slice($results, 0, $topN);
    }

    /**
     * Tool #10 — Intra-Product Part Combiner, MVP 2-part (BACKLOG.md §0,
     * tier1 §4.2b/§4.3 baris #10, "prioritas tertinggi dari sisa katalog").
     * BUKAN general heterogeneous bin-packing (itu literatur NP-hard, di
     * luar scope MVP) — pendekatan **guillotine vertical-split**, perluasan
     * LANGSUNG dari metode "CAMPUR" yang sudah ada di run() (yang sudah
     * membagi area jadi blok utama + blok sisa rotasi): di sini area usable
     * dibagi 2 kolom (part A di kiri lebar X, part B di sisa lebar kanan),
     * X di-sweep dalam rentang usable width (step tetap), tiap X dihitung
     * up count masing-masing part via `gridCount()` (coba 2 orientasi per
     * part, ambil yang lebih banyak — sama prinsip run()).
     *
     * Metrik keputusan (tier1 §4.2b: "rekomendasikan yang paling ekonomis"):
     * `qty_per_sku` (ProdPart, rasio kebutuhan riil antar-part dalam 1 set
     * produk jadi, mis. 1 outer : 2 inner) dipakai sbg bobot -- "sets per
     * sheet" = MIN(up_A / qtyPerSkuA, up_B / qtyPerSkuB) (part dgn rasio
     * paling njomplang jadi bottleneck, sisa up count part lain terbuang).
     * Dibandingkan ke skenario SEPISAH (tiap part psno sendiri² penuh) via
     * `area_per_set`: makin kecil area terpakai per 1 SET produk jadi,
     * makin ekonomis.
     *
     * @return array{recommend: string, combined: ?array, separate: array, saving_pct: float}
     *         recommend = 'COMBINED'|'SEPARATE'|'NONE' (NONE = kedua part
     *         sama sekali tidak muat digabung di plano manapun dlm sweep)
     */
    public function combinePair(
        float $planoW, float $planoH,
        float $bodyWA, float $bodyHA, float $qtyPerSkuA,
        float $bodyWB, float $bodyHB, float $qtyPerSkuB,
        float $gap, float $gripper, float $margin, float $splitStep = 5.0
    ): array {
        $areaW = $planoW - 2 * $margin;
        $areaH = $planoH - $gripper - 2 * $margin;
        $planoAreaMm2 = $planoW * $planoH;
        $qtyPerSkuA = max($qtyPerSkuA, 0.0001);
        $qtyPerSkuB = max($qtyPerSkuB, 0.0001);

        // Baseline SEPISAH: tiap part dapat plano penuh sendiri (best-of-2-orientasi,
        // level "Plano→Pcs" polos, TANPA sweep lidah -- itu tanggung jawab Tool #1/#2
        // terpisah, di sini cuma perbandingan solo vs gabung pada 1 body dim yg sama).
        $soloA = $this->run($planoW, $planoH, $bodyWA, $bodyHA, $gap, $gripper, $margin);
        $soloB = $this->run($planoW, $planoH, $bodyWB, $bodyHB, $gap, $gripper, $margin);
        $areaPerSetSeparate = $soloA['up'] > 0 && $soloB['up'] > 0
            ? ($planoAreaMm2 / $soloA['up']) * $qtyPerSkuA + ($planoAreaMm2 / $soloB['up']) * $qtyPerSkuB
            : null;

        // Sweep split vertikal X: kolom kiri utk part A (lebar X), kolom kanan utk
        // part B (lebar sisa areaW-X-gap). Common Knife (ArtiosCAD, lihat
        // prepress_imposition_packaging_guide.md §1B) TIDAK diterapkan di batas
        // antar-kolom (2 part beda bentuk, garis potongnya secara alami tidak
        // pernah persis berimpit) -- cukup 1 `gap` pemisah biasa.
        $bestCombined = null;
        $bestSets = 0.0;

        // Batas bawah/atas sweep pakai dimensi TERKECIL dari 2 orientasi (bukan cuma
        // bodyWA/bodyWB apa adanya) -- part yang cuma muat kalau diputar 90 derajat
        // (mis. lebih lebar dari areaW tapi lebih pendek dari areaH) tetap ikut
        // dieksplorasi, bukan langsung ter-skip krn starting point sweep salah asumsi.
        $minWA = min($bodyWA, $bodyHA);
        $minWB = min($bodyWB, $bodyHB);

        for ($x = $minWA; $x <= $areaW - $minWB - $gap + 0.0001; $x += $splitStep) {
            $colA = $this->gridCount($x, $areaH, $bodyWA, $bodyHA, $gap);
            $colARot = $this->gridCount($x, $areaH, $bodyHA, $bodyWA, $gap);
            $upA = max($colA['n'], $colARot['n']);

            $remW = $areaW - $x - $gap;
            $colB = $this->gridCount($remW, $areaH, $bodyWB, $bodyHB, $gap);
            $colBRot = $this->gridCount($remW, $areaH, $bodyHB, $bodyWB, $gap);
            $upB = max($colB['n'], $colBRot['n']);

            if ($upA < 1 || $upB < 1) {
                continue;
            }

            $sets = min($upA / $qtyPerSkuA, $upB / $qtyPerSkuB);

            if ($sets > $bestSets) {
                $bestSets = $sets;
                $bestCombined = [
                    'split_x_mm' => round($x, 2),
                    'up_a' => $upA,
                    'up_b' => $upB,
                    'sets_per_sheet' => round($sets, 4),
                    'area_per_set' => $planoAreaMm2 / $sets,
                ];
            }
        }

        if (! $bestCombined) {
            return [
                'recommend' => 'NONE',
                'combined' => null,
                'separate' => ['up_a' => $soloA['up'], 'up_b' => $soloB['up'], 'area_per_set' => $areaPerSetSeparate],
                'saving_pct' => 0.0,
            ];
        }

        $savingPct = $areaPerSetSeparate && $areaPerSetSeparate > 0
            ? round((1 - $bestCombined['area_per_set'] / $areaPerSetSeparate) * 100, 2)
            : 0.0;

        return [
            'recommend' => $areaPerSetSeparate !== null && $bestCombined['area_per_set'] < $areaPerSetSeparate ? 'COMBINED' : 'SEPARATE',
            'combined' => $bestCombined,
            'separate' => ['up_a' => $soloA['up'], 'up_b' => $soloB['up'], 'area_per_set' => $areaPerSetSeparate],
            'saving_pct' => $savingPct,
        ];
    }

    /**
     * Tool #4 — Die-Rule Estimator (BACKLOG.md §0, tier1 §4.3 baris #4;
     * formula acuan guide §3 poin 4 "Kalkulator Pisau Pond": total panjang
     * pisau potong + pisau rel/lipat + perforasi dlm meter lari). Model
     * bounding-box (body + 1 lidah), BUKAN polygon dieline asli (itu Tool
     * #8, effort jauh lebih besar, lihat disclaimer tier1 §4.3a).
     *
     * Cutting rule pakai prinsip **Common Knife** (Esko ArtiosCAD, guide §1B
     * "Double Cut Elimination" -- pisau tidak memotong garis yang sama 2x):
     * utk layout method GRID rows×cols, total garis potong = (cols+1) garis
     * vertikal (masing2 sepanjang tinggi grid) + (rows+1) garis horizontal
     * (masing2 sepanjang lebar grid) -- garis internal dipakai bersama 2 sel
     * bertetangga, garis terluar = tepi fisik pond. Method CAMPUR/MIX
     * (grid utama + blok rotasi) dihitung sbg 2 grid terpisah dijumlah --
     * TIDAK optimasi Common Knife di batas antar-blok (beda orientasi,
     * garis potongnya secara alami tidak berimpit persis), sedikit
     * overestimate & dicatat sbg batasan yang disadari.
     *
     * Creasing rule = 1 garis lipat badan↔lidah PER UNIT (lidah cuma di 1
     * sisi per model bounding-box saat ini) -- garis ini di DALAM tiap sel,
     * tidak bisa dipakai bersama sel lain, jadi dikali up_count penuh.
     *
     * Perforasi = 0 (belum ada data source geometri perforasi di skema
     * manapun -- TIDAK ditebak, bukan diabaikan sengaja).
     *
     * @param  array  $layout  hasil `run()['layout']` (grid atau mix)
     */
    public function dieRuleMeterLari(array $layout, int $upCount, float $flapLengthMm, string $flapSide, float $bodyWMm, float $bodyHMm, float $gap): float
    {
        if ($upCount < 1) {
            return 0.0;
        }

        $cuttingMm = $layout['type'] === 'grid'
            ? $this->gridCommonKnifeLengthMm($layout['cols'], $layout['rows'], $layout['iw'], $layout['ih'], $gap)
            : $this->gridCommonKnifeLengthMm($layout['main']['cols'], $layout['main']['rows'], $layout['main']['iw'], $layout['main']['ih'], $gap)
                + $this->gridCommonKnifeLengthMm($layout['rot']['cols'], $layout['rot']['rows'], $layout['rot']['iw'], $layout['rot']['ih'], $gap);

        // Garis lipat badan<->lidah: sepanjang sisi tempat lidah menempel (lebar
        // badan kalau TOP/BOTTOM, tinggi badan kalau LEFT/RIGHT), per unit.
        $creaseLineMm = in_array($flapSide, ['LEFT', 'RIGHT'], true) ? $bodyHMm : $bodyWMm;
        $creasingMm = $upCount * $creaseLineMm;

        return round(($cuttingMm + $creasingMm) / 1000, 4); // mm -> meter
    }

    private function gridCommonKnifeLengthMm(int $cols, int $rows, float $itemW, float $itemH, float $gap): float
    {
        if ($cols < 1 || $rows < 1) {
            return 0.0;
        }

        $gridW = $cols * $itemW + max(0, $cols - 1) * $gap;
        $gridH = $rows * $itemH + max(0, $rows - 1) * $gap;

        return ($cols + 1) * $gridH + ($rows + 1) * $gridW;
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
