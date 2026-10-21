<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menjaga grup Laporan terpisah tidak ada di modul Sales dan laporan Sales tetap di grup induknya (migration 2026_09_05_000009).
 * Sumber hulu  : tm2sys.sys_app_menu.
 * Input        : -
 * Output       : Lulus bila parent_id sesuai.
 * Dipakai oleh : php artisan test --filter=SalesMenuNoReportGroupTest.
 * Status       : Aktif. Tes shell v1 (pages::main-menu) di sini dibuang T10 tahap 6c.
 */
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
            // Inquiry & Quotation = modul menu sendiri (PREORDER) sejak 2026-10-02, bukan lagi grup 938 di dalam Sales;
            // laporannya di grup Report modul itu (sama dengan menu v2 di ddtsys).
            'SALES_WINRATE' => $db->table('sys_app_menu')->where('code', 'SALES_INQGRP_RPT')->value('id'),
            'SALES_GRID_SO' => 939,  // Sales Order
            'SALES_MON_ORD' => 939,
            'SALES_RPT' => 939,
        ];

        foreach ($expectedParents as $code => $expectedParent) {
            $actual = $db->table('sys_app_menu')->where('code', $code)->value('parent_id');
            $this->assertSame($expectedParent, $actual, "{$code} harus punya parent_id={$expectedParent}.");
        }
    }
}
