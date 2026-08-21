<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kartu Stok & Master Stok sekarang bisa switch RM/FG/SFG (lihat migration
// 2026_08_22_000004 & commit d3deb47), tapi fg_stockcard/sfg_stockcard masih
// 0 baris -- kategori FG/SFG tampil kosong. feedback_keep_dummy_data: layar
// baru wajib ada data uji. Seed 1 dokumen Stock In FG (2 baris, produk dummy
// dari Master Produk) + 1 part SFG baru + 1 dokumen Stock In SFG (1 baris) --
// pola identik dgn rm_stock/rm_stock_d/rm_stockcard yang sudah ada datanya,
// cuma beda tabel/kelas (sama seperti instruksi user: "similar dengan RM,
// hanya beda tipe saja").
return new class extends Migration
{
    public function up(): void
    {
        $inv = DB::connection('tm2inv');
        $mst = DB::connection('tm2mst');

        // ---- SFG butuh prod_part -- belum ada satu pun, buat 1 part dummy ----
        $partId = $mst->table('prod_part')->insertGetId([
            'product_id' => 1, // FB-DUPLEX-001
            'part_code' => 'FB-DUPLEX-001-BODY',
            'part_name' => 'Body Box (Folding Box Duplex Kosmetik)',
            'part_type' => 'DC',
            'qty_per_sku' => 1,
            'uom_stock' => 'PCS',
            'min_stock' => 100,
            'is_active' => 1,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ================= FG: Stock In 2 produk =================
        // WH-FG-01 (id_warehouse=2), location WH-FG-01-R02-C01-S01 (id=3).
        $fgDocId = $inv->table('fg_stock')->insertGetId([
            'doc_no' => 'FG-IN-260001',
            'doc_date' => now()->subDays(3),
            'doc_type' => 1,
            'doc_cat' => 'IN',
            'ref_doc_type' => 'MANUAL',
            'warehouse_id' => 2,
            'notes' => 'Data uji -- stok awal barang jadi (dummy, bukan dari alur produksi sungguhan).',
            'stat_confirm' => 1,
            'flow_status' => 'CONFIRMED',
            'create_by' => 'system', 'update_by' => 'system',
            'create_log' => now()->subDays(3), 'update_log' => now()->subDays(3),
        ]);

        $fgLines = [
            ['product_id' => 1, 'qty' => 500, 'uom_code' => 'PCS', 'lot_no' => 'LOT-FG-0001'],
            ['product_id' => 2, 'qty' => 200, 'uom_code' => 'PCS', 'lot_no' => 'LOT-FG-0002'],
        ];

        foreach ($fgLines as $seq => $line) {
            $detailId = $inv->table('fg_stock_d')->insertGetId([
                'doc_id' => $fgDocId,
                'seq_no' => $seq,
                'product_id' => $line['product_id'],
                'qty' => $line['qty'],
                'uom_code' => $line['uom_code'],
                'lot_no' => $line['lot_no'],
                'unit_price' => 0,
                'qty_unitprice' => 0,
                'location_id' => 3,
                'create_by' => 'system', 'update_by' => 'system',
                'create_log' => now()->subDays(3), 'update_log' => now()->subDays(3),
            ]);

            $inv->table('fg_stockcard')->insert([
                'doc_date' => now()->subDays(3),
                'product_id' => $line['product_id'],
                'doc_type' => 'IN',
                'location_id' => 3,
                'warehouse_id' => 2,
                'lot_no' => $line['lot_no'],
                'qty' => $line['qty'],
                'uom_code' => $line['uom_code'],
                'reference_detail_id' => $detailId,
                'reference_name' => 'tm2inv.fg_stock_d',
                'qty_bb' => 0,
                'qty_in' => $line['qty'],
                'qty_out' => 0,
                'qty_adj' => 0,
                'qty_eb' => $line['qty'],
                'create_by' => 'system', 'update_by' => 'system',
                'create_log' => now()->subDays(3), 'update_log' => now()->subDays(3),
            ]);
        }

        // ================= SFG: Stock In 1 part (dari subcon, dummy) =================
        // WH-MIX-01 (id_warehouse=3, "Gudang Transit & Buffer") dipakai sbg
        // lokasi buffer WIP -- belum ada tipe gudang khusus SFG di m_warehouse.
        $sfgDocId = $inv->table('sfg_stock')->insertGetId([
            'doc_no' => 'SFG-IN-260001',
            'doc_date' => now()->subDays(1),
            'doc_type' => 1,
            'doc_cat' => 'IN',
            'ref_doc_type' => 'MANUAL',
            'product_id' => 1,
            'warehouse_id' => 3,
            'notes' => 'Data uji -- barang setengah jadi kembali dari subcon (dummy).',
            'stat_confirm' => 1,
            'flow_status' => 'CONFIRMED',
            'create_by' => 'system', 'update_by' => 'system',
            'create_log' => now()->subDay(), 'update_log' => now()->subDay(),
        ]);

        $sfgDetailId = $inv->table('sfg_stock_d')->insertGetId([
            'doc_id' => $sfgDocId,
            'seq_no' => 0,
            'parts_id' => $partId,
            'product_id' => 1,
            'qty' => 300,
            'uom_code' => 'PCS',
            'lot_no' => 'LOT-SFG-0001',
            'unit_price' => 0,
            'qty_unitprice' => 0,
            'location_id' => 4,
            'create_by' => 'system', 'update_by' => 'system',
            'create_log' => now()->subDay(), 'update_log' => now()->subDay(),
        ]);

        $inv->table('sfg_stockcard')->insert([
            'doc_date' => now()->subDay(),
            'parts_id' => $partId,
            'product_id' => 1,
            'doc_type' => 'IN',
            'location_id' => 4,
            'warehouse_id' => 3,
            'lot_no' => 'LOT-SFG-0001',
            'qty' => 300,
            'uom_code' => 'PCS',
            'reference_detail_id' => $sfgDetailId,
            'reference_name' => 'tm2inv.sfg_stock_d',
            'qty_bb' => 0,
            'qty_in' => 300,
            'qty_out' => 0,
            'qty_adj' => 0,
            'qty_eb' => 300,
            'create_by' => 'system', 'update_by' => 'system',
            'create_log' => now()->subDay(), 'update_log' => now()->subDay(),
        ]);
    }

    public function down(): void
    {
        $inv = DB::connection('tm2inv');
        $mst = DB::connection('tm2mst');

        $inv->table('sfg_stockcard')->where('lot_no', 'LOT-SFG-0001')->delete();
        $sfgDocId = $inv->table('sfg_stock')->where('doc_no', 'SFG-IN-260001')->value('doc_id');
        $inv->table('sfg_stock_d')->where('doc_id', $sfgDocId)->delete();
        $inv->table('sfg_stock')->where('doc_no', 'SFG-IN-260001')->delete();

        $inv->table('fg_stockcard')->whereIn('lot_no', ['LOT-FG-0001', 'LOT-FG-0002'])->delete();
        $fgDocId = $inv->table('fg_stock')->where('doc_no', 'FG-IN-260001')->value('doc_id');
        $inv->table('fg_stock_d')->where('doc_id', $fgDocId)->delete();
        $inv->table('fg_stock')->where('doc_no', 'FG-IN-260001')->delete();

        $mst->table('prod_part')->where('part_code', 'FB-DUPLEX-001-BODY')->delete();
    }
};
