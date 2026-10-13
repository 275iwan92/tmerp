<?php

/*
 * REMARK
 * Tujuan       : Grup Planning naik satu tingkat ke bawah Manufacture menggantikan PPIC (Manufacture = Planning, Production, Maintenance, QA).
 * Sumber hulu  : sys_app_menu (tm2sys), kode PROD_PLAN (Planning), PROD_PPIC (PPIC), PROD (Manufacture).
 * Input        : -
 * Output       : PROD_PLAN.parent_id = PROD, sort_order 10; PROD_PPIC.hide_in_menu = 1 (baris tidak dihapus, anak-anaknya tetap di bawah PPIC).
 * Dipakai oleh : AppMenu (menu v2).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis. down() mengembalikan keadaan semula.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function menu()
    {
        return DB::connection('tm2sys')->table('sys_app_menu');
    }

    public function up(): void
    {
        $prod = $this->menu()->where('code', 'PROD')->value('id');
        $ppic = $this->menu()->where('code', 'PROD_PPIC')->value('id');
        if (! $prod || ! $ppic) {
            return;
        }
        $this->menu()->where('code', 'PROD_PLAN')->update(['parent_id' => $prod, 'sort_order' => 10]);
        $this->menu()->where('id', $ppic)->update(['hide_in_menu' => 1]);
    }

    public function down(): void
    {
        $ppic = $this->menu()->where('code', 'PROD_PPIC')->value('id');
        if (! $ppic) {
            return;
        }
        $this->menu()->where('code', 'PROD_PLAN')->update(['parent_id' => $ppic, 'sort_order' => 20]);
        $this->menu()->where('id', $ppic)->update(['hide_in_menu' => 0]);
    }
};
