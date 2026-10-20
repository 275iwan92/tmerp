<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Menu Management Reports > Sales / Purchase / Accounting / Production / HR / Warehouse pindah ke layar v2 (v2:mgmt-*), v1 pages::report.* diarsipkan (rule D.25, T9).
 * Sumber hulu  : sys_app_menu (tm2sys; ddtsys di mockup lewat konfigurasi koneksi).
 * Input        : kode menu MGMT_SALES, MGMT_PURCHASE, MGMT_ACCOUNTING, MGMT_PRODUCTION, MGMT_HR, MGMT_WAREHOUSE.
 * Output       : kolom target menjadi v2:<slug>; down mengembalikan nilai lama.
 * Dipakai oleh : App\Support\V2\AppMenu, App\Support\Menu\MenuRoutes (cadangan menu v1).
 * Status       : Aktif.
 */
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = ['MGMT_SALES' => ['report.sales', 'v2:mgmt-sales'], 'MGMT_PURCHASE' => ['report.purchase', 'v2:mgmt-purchase'], 'MGMT_ACCOUNTING' => [null, 'v2:mgmt-accounting'],
        'MGMT_PRODUCTION' => [null, 'v2:mgmt-production'], 'MGMT_HR' => [null, 'v2:mgmt-hr'], 'MGMT_WAREHOUSE' => [null, 'v2:mgmt-warehouse']];

    public function up(): void
    {
        foreach (self::MAP as $code => [, $target]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->update(['target' => $target, 'object_type' => 'report']);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $code => [$old]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->update(['target' => $old, 'object_type' => $old ? 'report' : 'grid']);
        }
    }
};
