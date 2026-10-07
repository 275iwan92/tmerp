<?php

namespace Tests\Feature;

use App\Support\V2\Data\DataOriginDesk;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

// Dummy & Legacy Data v2 (C1): aturan klasifikasi dan syarat wajib; data uji diberi catatan ZZTEST dan dibersihkan sendiri.
class DataOriginDeskTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::connection('tm2sys')->table('sys_data_origin_rule')->where('note', 'ZZTEST')->delete();
        DB::connection('tm2sys')->table('sys_data_requirement')->where('label', 'ZZTEST')->delete();
        parent::tearDown();
    }

    public function test_settings_roundtrip_keeps_values(): void
    {
        $before = DataOriginDesk::settings();
        DataOriginDesk::saveSettings($before, 'test');
        $this->assertSame($before, DataOriginDesk::settings());
    }

    public function test_rule_create_update_toggle(): void
    {
        $base = ['match_type' => 'CREATED_BY', 'match_value' => 'zztest%', 'origin' => 'DUMMY', 'priority' => 9990, 'note' => 'ZZTEST'];
        DataOriginDesk::saveRule($base, null, 'test');
        $rule = DataOriginDesk::rules()->firstWhere('note', 'ZZTEST');
        $this->assertSame('zztest%', $rule->match_value);
        $this->assertEquals(1, $rule->activate);

        DataOriginDesk::saveRule(['origin' => 'LEGACY'] + $base, $rule->id_rule, 'test');
        $this->assertSame('LEGACY', DataOriginDesk::rule($rule->id_rule)->origin);

        DataOriginDesk::toggle('sys_data_origin_rule', $rule->id_rule, 'test');
        $this->assertEquals(0, DataOriginDesk::rule($rule->id_rule)->activate);
    }

    public function test_rule_needs_pattern_unless_column_is_filled(): void
    {
        $this->expectException(ValidationException::class);
        DataOriginDesk::saveRule(['match_type' => 'CREATED_BY', 'origin' => 'DUMMY', 'priority' => 1, 'note' => 'ZZTEST'], null, 'test');
    }

    public function test_requirement_is_saved_once_per_column(): void
    {
        $in = ['db_name' => 'tm2mst', 'table_name' => 'zz_test_table', 'column_name' => 'zz_col', 'label' => 'ZZTEST'];
        DataOriginDesk::saveRequirement($in, 'test');
        DataOriginDesk::saveRequirement($in, 'test');
        $this->assertCount(1, DataOriginDesk::requirements()->where('table_name', 'zz_test_table'));
        DB::connection('tm2sys')->table('sys_data_requirement')->where('table_name', 'zz_test_table')->delete();
    }

    public function test_unknown_toggle_table_is_rejected(): void
    {
        $this->expectException(NotFoundHttpException::class);
        DataOriginDesk::toggle('sec_user', 1, 'test');
    }
}
