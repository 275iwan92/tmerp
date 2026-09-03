<?php

namespace Tests\Unit;

use App\Services\Imposition\ImpositionEngine;
use PHPUnit\Framework\TestCase;

// Unit test murni (tanpa DB) utk 3 tool baru BACKLOG.md §0: dieRuleMeterLari
// (Tool #4), bestSheet (Tool #2), combinePair (Tool #10). Nilai expected
// dihitung manual dari formula di docblock masing-masing method.
class ImpositionEngineProDevToolsTest extends TestCase
{
    private ImpositionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ImpositionEngine;
    }

    public function test_die_rule_meter_lari_common_knife_grid(): void
    {
        // Grid 2 kolom x 3 baris, item 100x80mm, gap 3mm.
        // gridW = 2*100 + 1*3 = 203; gridH = 3*80 + 2*3 = 246.
        // cutting = (2+1)*246 + (3+1)*203 = 738 + 812 = 1550mm.
        // crease (flap TOP, bodyW=100mm) = 6 up * 100mm = 600mm.
        // total = (1550+600)/1000 = 2.15m.
        $layout = ['type' => 'grid', 'rot' => false, 'cols' => 2, 'rows' => 3, 'iw' => 100, 'ih' => 80];

        $result = $this->engine->dieRuleMeterLari($layout, 6, 20, 'TOP', 100, 80, 3);

        $this->assertSame(2.15, $result);
    }

    public function test_die_rule_meter_lari_zero_up_returns_zero(): void
    {
        $layout = ['type' => 'grid', 'cols' => 0, 'rows' => 0, 'iw' => 100, 'ih' => 80];

        $this->assertSame(0.0, $this->engine->dieRuleMeterLari($layout, 0, 20, 'TOP', 100, 80, 3));
    }

    public function test_best_sheet_ranks_by_gross_sheets_when_qty_given(): void
    {
        $planos = collect([
            (object) ['id_plano_size' => 1, 'plano_name' => '65x100', 'width_mm' => 650, 'height_mm' => 1000],
            (object) ['id_plano_size' => 2, 'plano_name' => '79x109', 'width_mm' => 790, 'height_mm' => 1090],
        ]);

        $results = $this->engine->bestSheet($planos, 200, 150, 'TOP', 20, 50, 5, 3, 12, 8, targetQty: 5000, topN: 5);

        $this->assertNotEmpty($results);
        // Hasil harus terurut cost_metric ASC (termurah dulu).
        for ($i = 1; $i < count($results); $i++) {
            $this->assertLessThanOrEqual($results[$i]['cost_metric'], $results[$i - 1]['cost_metric']);
        }
        $this->assertArrayHasKey('plano_name', $results[0]);
        $this->assertArrayHasKey('gross_sheets', $results[0]);
    }

    public function test_best_sheet_fallback_area_per_pc_when_no_qty(): void
    {
        $planos = collect([
            (object) ['id_plano_size' => 1, 'plano_name' => '65x100', 'width_mm' => 650, 'height_mm' => 1000],
        ]);

        $results = $this->engine->bestSheet($planos, 200, 150, 'TOP', 20, 50, 5, 3, 12, 8, targetQty: null, topN: 5);

        $this->assertNotEmpty($results);
        $this->assertNull($results[0]['gross_sheets']);
    }

    public function test_combine_pair_recommends_combined_for_complementary_sizes(): void
    {
        // Part besar (300x200) + part kecil (150x100) di plano 79x109 --
        // part kecil ngisi sisa strip di samping part besar, harus lebih
        // hemat drpd 2 plano terpisah.
        $result = $this->engine->combinePair(790, 1090, 300, 200, 1, 150, 100, 1, 3, 12, 8, splitStep: 10);

        $this->assertSame('COMBINED', $result['recommend']);
        $this->assertGreaterThan(0, $result['saving_pct']);
        $this->assertGreaterThanOrEqual(1, $result['combined']['up_a']);
        $this->assertGreaterThanOrEqual(1, $result['combined']['up_b']);
    }

    public function test_combine_pair_considers_rotated_orientation_for_oversized_part(): void
    {
        // Part A (700x500) tidak muat lebar TEGAK di kolom manapun kecuali
        // diputar 90 derajat -- regression test utk bug rotasi yang sempat
        // bikin sweep salah mulai dari bodyWA mentah (skip opsi rotasi).
        $result = $this->engine->combinePair(650, 1000, 700, 500, 1, 100, 100, 1, 3, 12, 8, splitStep: 10);

        $this->assertSame('COMBINED', $result['recommend']);
        $this->assertSame(1, $result['combined']['up_a']);
    }

    public function test_combine_pair_returns_none_when_truly_impossible(): void
    {
        // Part A lebih besar dari plano di KEDUA orientasi -- tidak mungkin
        // muat sama sekali, harus NONE bukan error/false-positive.
        $result = $this->engine->combinePair(650, 1000, 2000, 2000, 1, 100, 100, 1, 3, 12, 8, splitStep: 10);

        $this->assertSame('NONE', $result['recommend']);
        $this->assertNull($result['combined']);
    }
}
