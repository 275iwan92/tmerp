<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Inventory > Reports > Valuation Check (Average per item): bukti per item bahwa nilai bin = nilai Average item. Idempoten.
// Jalankan di ddt dulu: php artisan migrate --env=mockup --path=database/migrations/2026_10_09_000500_menu_inventory_valuation_check.php --force
return new class extends Migration
{
    private const BY = 'claude (avg per item)';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $group = $menu()->where('code', 'INV_WH_RPT')->first();
        if (! $group || $menu()->where('code', 'INV_RPT_VALCHECK')->exists()) {
            return;
        }
        $menu()->insert(['parent_id' => $group->id, 'code' => 'INV_RPT_VALCHECK', 'label' => 'Valuation Check', 'icon' => 'fa-solid fa-scale-balanced', 'menu_type' => 'item',
            'object_type' => 'report', 'target' => 'v2:inv-rpt-valuation-check', 'sort_order' => 80, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY,
            'status_updated_at' => now(), 'created_at' => now(), 'doc_slug' => 'inventoryreport']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_RPT_VALCHECK')->delete();
    }
};
