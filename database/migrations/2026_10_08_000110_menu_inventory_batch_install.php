<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Inventory > Batch & Quality > Batch (Inventory & Warehouse v2.01, layar inv-batch). Grup Batch & Quality dipakai bersama
// jalur QC (sampling, keputusan mutu): dibuat di sini bila belum ada, tidak diubah bila sudah ada. Dijalankan di ddt* dulu
// (php artisan migrate --env=mockup --path=database/migrations/2026_10_08_000110_menu_inventory_batch_install.php).
return new class extends Migration
{
    private const BY = 'claude (inventory v2.01)';

    private function row(array $v): array
    {
        return $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(), 'doc_slug' => 'inventory'];
    }

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->first();
        if (! $inv) {
            return;
        }
        $group = $menu()->where('code', 'INV_BQ')->value('id')
            ?? $menu()->insertGetId($this->row(['parent_id' => $inv->id, 'code' => 'INV_BQ', 'label' => 'Batch & Quality', 'icon' => 'fa-solid fa-flask-vial', 'menu_type' => 'group', 'sort_order' => 7]));
        if (! $menu()->where('code', 'INV_BQ_BATCH')->exists()) {
            $menu()->insert($this->row(['parent_id' => $group, 'code' => 'INV_BQ_BATCH', 'label' => 'Batch', 'icon' => 'fa-solid fa-tags', 'menu_type' => 'item', 'object_type' => 'grid',
                'target' => 'v2:inv-batch', 'sort_order' => 10]));
        }
    }

    public function down(): void
    {
        // Menu dibiarkan: grup Batch & Quality dipakai bersama jalur QC.
    }
};
