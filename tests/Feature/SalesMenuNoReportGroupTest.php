<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Permintaan user 2026-09-05 (screenshot sidebar Sales): "di menu sales ga
// boleh ada menu group laporan, semua laporan masuk ke menu-menu group
// atasnya" -- pola sama dgn Procurement (2026_09_05_000008). Migration
// terkait: 2026_09_05_000009_move_sales_reports_into_stage_groups.php.
class SalesMenuNoReportGroupTest extends TestCase
{
    public function test_sales_module_has_no_standalone_laporan_group(): void
    {
        $db = DB::connection('tm2sys');

        $reportGroup = $db->table('sys_app_menu')
            ->where('parent_id', 1) // SALES module
            ->where('menu_type', 'group')
            ->where('label', 'Laporan')
            ->first();

        $this->assertNull($reportGroup, 'Grup "Laporan" terpisah tidak boleh ada lagi di modul Sales.');

        $expectedParents = [
            // Inquiry & Quotation = modul menu sendiri (PREORDER) sejak 2026-10-02, bukan lagi grup 938 di dalam Sales.
            'SALES_WINRATE' => $db->table('sys_app_menu')->where('code', 'PREORDER')->value('id'),
            'SALES_GRID_SO' => 939,  // Sales Order
            'SALES_MON_ORD' => 939,
            'SALES_RPT' => 939,
        ];

        foreach ($expectedParents as $code => $expectedParent) {
            $actual = $db->table('sys_app_menu')->where('code', $code)->value('parent_id');
            $this->assertSame($expectedParent, $actual, "{$code} harus punya parent_id={$expectedParent}.");
        }
    }

    public function test_main_menu_still_renders_after_sales_regroup(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::main-menu')->assertOk();
    }
}
