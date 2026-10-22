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

        // Aturan C.35 (2026-10-16): laporan ada di baris heading Report di dalam grupnya; id baris itu beda di ddtsys dan tm2sys, jadi dicari lewat kode.
        $orderReport = $db->table('sys_app_menu')->where('code', 'SALES_ORDGRP_RPT')->first(['id', 'parent_id']);
        $this->assertSame(939, $orderReport->parent_id, 'Heading Report pesanan tetap di dalam grup Sales Order (939).');

        $expectedParents = [
            // Inquiry & Quotation = modul menu sendiri (PREORDER) sejak 2026-10-02, bukan lagi grup 938 di dalam Sales;
            // laporannya di grup Report modul itu (sama dengan menu v2 di ddtsys).
            'SALES_WINRATE' => $db->table('sys_app_menu')->where('code', 'SALES_INQGRP_RPT')->value('id'),
            'SALES_GRID_SO' => $orderReport->id,
            'SALES_MON_ORD' => $orderReport->id,
            'SALES_RPT' => $orderReport->id,
        ];

        foreach ($expectedParents as $code => $expectedParent) {
            $actual = $db->table('sys_app_menu')->where('code', $code)->value('parent_id');
            $this->assertSame($expectedParent, $actual, "{$code} harus punya parent_id={$expectedParent}.");
        }
    }
}
