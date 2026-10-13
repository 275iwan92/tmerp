<?php

/*
 * REMARK
 * Tujuan       : Item menu yang belum punya layar (v1 maupun v2) tidak tampil sebagai pintu kosong "Layar untuk menu ini belum ada".
 * Sumber hulu  : sys_app_menu; audit item aktif tanpa target v2 dan tanpa pemetaan MenuRoutes (Jo 13 Okt 14:07Z).
 * Input        : -
 * Output       : hide_in_menu = 1 untuk INV_SCAN_ITEM1, MAINT_IMP, MAINT_IMP_REV (barisnya tetap; tampil lagi saat layarnya dibangun).
 * Dipakai oleh : App\Support\V2\AppMenu (sidebar); dijaga tests/Feature/MenuHasScreenTest.
 * Status       : Aktif; idempoten; down() menampilkan lagi.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODES = ['INV_SCAN_ITEM1', 'MAINT_IMP', 'MAINT_IMP_REV'];

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', self::CODES)->update(['hide_in_menu' => 1]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', self::CODES)->update(['hide_in_menu' => 0]);
    }
};
