<?php

/*
 * REMARK
 * Tujuan       : Menjaga langkah Lem + bahan LEM BOX muncul dari Joint Type GLUE_* (bukan rakitan, bukan Stitching).
 * Sumber hulu  : SpecEngine::generate, mat_join_type, prod_bop_rule (aturan NEEDS_GLUE).
 * Input        : Model ProdPart/IqpPart di memori (tidak disimpan) + master aturan aktif.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif; skip bila tidak ada aturan NEEDS_GLUE atau master mat_join_type.
 */

namespace Tests\Feature;

use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use App\Services\ProDev\SpecEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Joint Type picked in the Inquiry (join_type_id) drives the glue step; generate() writes nothing. */
class SpecEngineJointGlueTest extends TestCase
{
    private const CTX = ['sheetL' => 70, 'sheetW' => 50, 'planoL' => 100, 'planoW' => 70, 'sheetsPerPlano' => 1, 'upCount' => 2, 'orderQty' => 1000];

    protected function setUp(): void
    {
        parent::setUp();

        $rules = DB::connection('tm2mst')->table('prod_bop_rule')->where('activate', 1)->where('condition_code', 'NEEDS_GLUE')->count();
        $joins = DB::connection('tm2mst')->table('mat_join_type')->whereIn('code', ['GLUE_SINGLE', 'GLUE_DOUBLE', 'STITCHING_SINGLE'])->count();
        if ($rules === 0 || $joins < 3) {
            $this->markTestSkipped('No NEEDS_GLUE rule or join types in this database.');
        }
    }

    private function run_(?string $joinCode, string $kind, ?string $jointText = null): array
    {
        $id = $joinCode ? DB::connection('tm2mst')->table('mat_join_type')->where('code', $joinCode)->value('id_join_type') : null;
        $part = new ProdPart(['part_type' => 'DC', 'part_kind' => $kind, 'joint_type' => $jointText, 'join_type_id' => $id, 'pack_qty' => 10]);
        $iqp = new IqpPart(['part_kind' => $kind, 'join_type_id' => $id, 'pack_qty' => 10]);

        $r = app(SpecEngine::class)->generate($part, $iqp, self::CTX);

        return [
            'glue' => collect($r['bop'])->contains(fn ($s) => $s['label'] === 'Lem'),
            'box' => collect($r['bom'])->contains(fn ($b) => $b['role'] === 'LEM BOX'),
        ];
    }

    public function test_glue_single_and_double_component_get_glue_step_and_box_glue(): void
    {
        foreach (['GLUE_SINGLE', 'GLUE_DOUBLE'] as $code) {
            $this->assertSame(['glue' => true, 'box' => true], $this->run_($code, 'COMPONENT'), $code);
        }
    }

    public function test_stitching_assembly_and_no_joint_get_no_glue(): void
    {
        $none = ['glue' => false, 'box' => false];

        $this->assertSame($none, $this->run_('STITCHING_SINGLE', 'COMPONENT'), 'stitching');
        $this->assertSame($none, $this->run_('GLUE_DOUBLE', 'ASSEMBLY'), 'assembly held');
        $this->assertSame($none, $this->run_(null, 'COMPONENT'), 'no joint');
    }

    public function test_legacy_joint_text_still_triggers_glue(): void
    {
        $this->assertSame(['glue' => true, 'box' => true], $this->run_(null, 'COMPONENT', 'Crash lock bottom'));
    }
}
