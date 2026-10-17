<?php

/*
 * REMARK
 * Tujuan       : Hapus menu alias Employee > Employee Self-Service (HRM_SELFSERVICE); jalur resmi = panel Profile > Edit profile (/v2/profile). Keputusan Jo 2026-10-18.
 * Sumber hulu  : sys_app_menu (tm2sys); sebelumnya dialihkan ke /v2/profile oleh migrasi 2026_10_07_000130.
 * Input        : -
 * Output       : Baris HRM_SELFSERVICE terhapus; down() memasangnya kembali di grup yang sama dengan HRM_CORRECTION.
 * Dipakai oleh : AppMenu (menu v2), App\Support\Menu\MenuRoutes (v1).
 * Status       : Jalankan di ddt* (--env=mockup) dan tm2* (sebelum UAT, aturan kerja 2026-10-16).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_SELFSERVICE')->delete();
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sibling = $sys->table('sys_app_menu')->where('code', 'HRM_CORRECTION')->first();
        if ($sibling && ! $sys->table('sys_app_menu')->where('code', 'HRM_SELFSERVICE')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $sibling->parent_id, 'code' => 'HRM_SELFSERVICE', 'label' => 'Employee Self-Service', 'icon' => 'fa-solid fa-user-check',
                'menu_type' => 'item', 'object_type' => 'form', 'target' => '/v2/profile', 'sort_order' => 30, 'activate' => 1, 'hide_in_menu' => 0,
                'required_object' => $sibling->required_object, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (rollback)', 'status_updated_at' => now(), 'created_at' => now()]);
        }
    }
};
