<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Baris menu Sales > Delivery > Ready to Ship (v2:ready-to-ship), tepat sebelum Delivery Order; hak akses mengikuti Delivery Order.
 * Sumber hulu  : sys_app_menu (tm2sys; ddtsys di mockup lewat konfigurasi koneksi), baris SALES_DO.
 * Input        : kode SALES_DO (induk, required_object, urutan).
 * Output       : baris SALES_READY_SHIP; down menghapusnya.
 * Dipakai oleh : App\Support\V2\AppMenu, App\Support\Menu\MenuRoutes.
 * Status       : Siap dijalankan (belum dijalankan; menunggu Jo).
 */
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $do = $sys->table('sys_app_menu')->where('code', 'SALES_DO')->first();
        if (! $do || $sys->table('sys_app_menu')->where('code', 'SALES_READY_SHIP')->exists()) {
            return;
        }

        $sys->table('sys_app_menu')->insert([
            'parent_id' => $do->parent_id, 'code' => 'SALES_READY_SHIP', 'label' => 'Ready to Ship', 'icon' => 'fa-solid fa-boxes-packing',
            'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:ready-to-ship', 'sort_order' => max(1, $do->sort_order - 5),
            'activate' => 1, 'required_object' => $do->required_object, 'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SALES_READY_SHIP')->delete();
    }
};
