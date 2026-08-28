<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Screen #1 (production/wo-generate.blade.php) picklist ternyata KOSONG di
// demo user 2026-08-28 -- satu-satunya co_order_delivery eligible (id=4, dari
// migration 000018) sudah dipakai generate WO2600261, jadi picklist-nya
// genuinely tak ada baris tersisa (bukan bug, cuma kehabisan data demo).
// Tambah 1 baris lagi -- SHIPMENT PARTIAL kedua utk item yang sama (kasus
// bisnis nyata: 1 CO, kirim bertahap), TIDAK generate WO (sengaja dibiarkan
// PLANNED tanpa prd_wo) supaya PPIC officer py sesuatu utk diklik "Generate".
return new class extends Migration
{
    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');

        $itemId = $sdi->table('co_order_item')->where('order_id', 2)->where('inquiry_id', 5)->value('id_item');
        if (! $itemId || $sdi->table('co_order_delivery')->where('item_id', $itemId)->where('shipment_type', 'PARTIAL')->exists()) {
            return;
        }

        $sdi->table('co_order_delivery')->insert([
            'order_id' => 2, 'item_id' => $itemId, 'shipment_type' => 'PARTIAL',
            'ship_date' => now()->addDays(25)->toDateString(), 'ship_qty' => 200, 'status' => 'PLANNED',
            'created_by' => 'system', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $sdi = DB::connection('tm2sdi');
        $itemId = $sdi->table('co_order_item')->where('order_id', 2)->where('inquiry_id', 5)->value('id_item');
        if ($itemId) {
            $sdi->table('co_order_delivery')->where('item_id', $itemId)->where('shipment_type', 'PARTIAL')->delete();
        }
    }
};
