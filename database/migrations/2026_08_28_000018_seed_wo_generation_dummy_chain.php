<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Rantai dummy Sales->Production ternyata nyaris kosong (0 co_order_delivery,
// 0 iqp_part_process, 0 prod_process) -- WoGenerationService (F1) tak bisa
// diuji nyata tanpa ini. Isi rantai MINIMAL tapi realistis: pakai CO/inquiry
// yang SUDAH ADA (CO2600001 confirmed, inquiry #5 part "Body"), tambah yang
// kurang (part_code, routing, material, item+delivery baru) -- TIDAK
// mengubah/menghapus baris existing yang tak terkait (Keep dummy data).
return new class extends Migration
{
    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');

        // 1. prod_process (kosong total sebelumnya) -- 3 proses dasar, nama
        //    konsisten sama yang sudah dipakai dummy Scheduling Board.
        $processIds = [];
        foreach (['Cetak', 'Laminasi', 'Plong'] as $name) {
            $processIds[$name] = $mst->table('prod_process')->updateOrInsert(
                ['process_name' => $name],
                ['activate' => true, 'created_by' => 'system']
            );
        }
        $processIds = $mst->table('prod_process')->whereIn('process_name', ['Cetak', 'Laminasi', 'Plong'])
            ->pluck('id_process', 'process_name');

        $cetak1 = $mst->table('prod_machine')->where('machine_code', 'CETAK-01')->value('machine_id');
        $laminasi = $mst->table('prod_machine')->where('machine_code', 'LAM-01')->value('machine_id');
        $plong1 = $mst->table('prod_machine')->where('machine_code', 'PLONG-01')->value('machine_id');

        // 2. Inquiry #5 / Part #1 ("Body") -- sudah ada, lengkapi yang kurang.
        $sdi->table('iqp_inquiry')->where('id_inquiry', 5)->update(['parent_product_id' => 1]);
        $sdi->table('iqp_part')->where('id_part', 1)->update(['part_code' => 'BODY']);

        if ($sdi->table('iqp_part_process')->where('part_id', 1)->doesntExist()) {
            $sdi->table('iqp_part_process')->insert([
                ['part_id' => 1, 'step_seq' => 1, 'process_id' => $processIds['Cetak'], 'process_name' => 'Cetak', 'machine_id' => $cetak1, 'created_by' => 'system', 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => 1, 'step_seq' => 2, 'process_id' => $processIds['Laminasi'], 'process_name' => 'Laminasi', 'machine_id' => $laminasi, 'created_by' => 'system', 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => 1, 'step_seq' => 3, 'process_id' => $processIds['Plong'], 'process_name' => 'Plong', 'machine_id' => $plong1, 'created_by' => 'system', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if ($sdi->table('iqp_part_material')->where('part_id', 1)->doesntExist()) {
            $sdi->table('iqp_part_material')->insert([
                'part_id' => 1, 'layer_seq' => 1, 'rawmat_name' => 'Duplex Board 350gsm', 'gram_weight' => 350,
                'is_primary' => true, 'created_by' => 'system', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 3. CO item + delivery baru di CO yang SUDAH CONFIRMED (order_id=2,
        //    CO2600001) -- tak menyentuh item id=3 yang sudah ada (tak terkait).
        $itemId = $sdi->table('co_order_item')->where('order_id', 2)->where('inquiry_id', 5)->value('id_item');
        if (! $itemId) {
            $itemId = $sdi->table('co_order_item')->insertGetId([
                'order_id' => 2, 'line_seq' => 2, 'inquiry_id' => 5, 'line_kind' => 'PRODUCT',
                'description' => 'Folding Box Duplex 350gr Kosmetik — Body',
                'qty' => 1000, 'qty_unit' => 'PCS', 'unit_price' => 0, 'subtotal' => 0,
                'due_date' => now()->addDays(10)->toDateString(),
                'created_by' => 'system', 'created_at' => now(), 'updated_at' => now(),
            ], 'id_item');
        }

        if ($sdi->table('co_order_delivery')->where('item_id', $itemId)->doesntExist()) {
            $sdi->table('co_order_delivery')->insert([
                'order_id' => 2, 'item_id' => $itemId, 'shipment_type' => 'FULL',
                'ship_date' => now()->addDays(10)->toDateString(), 'ship_qty' => 500, 'status' => 'PLANNED',
                'created_by' => 'system', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 4. 1 baris target rate default (Cetak x mesin Cetak-01) -- supaya
        //    lookup default WoGenerationService genuinely teruji, bukan cuma fallback null.
        $mst->table('prod_part_process_machine_rate')->updateOrInsert(
            ['product_id' => 1, 'part_code' => 'BODY', 'process_id' => $processIds['Cetak'], 'machine_id' => $cetak1],
            ['target_qty_per_hour' => 6000, 'activate' => true, 'created_by' => 'system', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');

        $mst->table('prod_part_process_machine_rate')->where('product_id', 1)->where('part_code', 'BODY')->delete();

        $itemId = $sdi->table('co_order_item')->where('order_id', 2)->where('inquiry_id', 5)->value('id_item');
        if ($itemId) {
            $sdi->table('co_order_delivery')->where('item_id', $itemId)->delete();
            $sdi->table('co_order_item')->where('id_item', $itemId)->delete();
        }

        $sdi->table('iqp_part_material')->where('part_id', 1)->delete();
        $sdi->table('iqp_part_process')->where('part_id', 1)->delete();
    }
};
