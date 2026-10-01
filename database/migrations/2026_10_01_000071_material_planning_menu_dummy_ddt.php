<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Material Planning: menu MRP / Forecast diaktifkan lagi (dulu dimatikan bersama engine lama) + perbaikan dummy (aturan user
// 2026-10-01 B.12: dummy salah dihapus & diganti benar) + contoh booking. Bagian dummy HANYA jalan di database ddt*.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['PROD_MRP', 'PROD_MTS_FORECAST'])->update(['activate' => 1]);

        // Baris booking boleh belum punya item (NEED_ITEM: Master Product baru berisi spesifikasi) atau berupa stok FG.
        Schema::connection('tm2inv')->table('rm_booking_d', function (Blueprint $t) {
            $t->unsignedBigInteger('material_id')->nullable()->change();
            $t->string('uom_code', 50)->nullable()->change();
        });

        $inv = DB::connection('tm2inv');
        if (! str_starts_with($inv->getDatabaseName(), 'ddt')) {
            return;
        }

        // 1. Stok dummy salah: Stock Out tanpa stok masuk (AC 210 GR, saldo -10) -> dihapus beserta barisnya.
        foreach ($inv->table('rm_stockcard')->where('material_id', 606)->where('doc_type', 'OUT')->get() as $c) {
            $docId = $inv->table('rm_stock_d')->where('doc_detail_id', $c->reference_detail_id)->value('doc_id');
            $inv->table('rm_stockcard')->where('doc_id', $c->doc_id)->delete();
            $inv->table('rm_stock_d')->where('doc_detail_id', $c->reference_detail_id)->delete();
            if ($docId && ! $inv->table('rm_stock_d')->where('doc_id', $docId)->exists()) {
                $inv->table('rm_stock')->where('doc_id', $docId)->delete();
            }
        }
        // 2. Saldo berjalan kartu stok (qty_bb / qty_eb) dihitung ulang per bahan + lokasi menurut urutan tanggal:
        //    data lama menyimpan saldo awal yang salah (mis. ANIMAL GLUE, ISI FREON AC tampil -1000 padahal masuk 1000 keluar 1000).
        $bal = [];
        foreach ($inv->table('rm_stockcard')->orderBy('doc_date')->orderBy('doc_id')->get() as $c) {
            $k = $c->material_id.'|'.$c->location_id;
            $bb = $bal[$k] ?? 0.0;
            $eb = $bb + (float) $c->qty_in - (float) $c->qty_out + (float) $c->qty_adj;
            $bal[$k] = $eb;
            if (abs($bb - (float) $c->qty_bb) > 0.00001 || abs($eb - (float) $c->qty_eb) > 0.00001) {
                $inv->table('rm_stockcard')->where('doc_id', $c->doc_id)->update(['qty_bb' => $bb, 'qty_eb' => $eb]);
            }
        }

        // 3. Forecast MPS contoh + contoh booking: lihat perintah `php artisan planning:run --demo` (dinamis terhadap CO dummy
        //    yang ada, karena CO dummy bisa dibuat ulang oleh Reset Data Dummy).
        Artisan::call('planning:run', ['--demo' => true]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['PROD_MRP', 'PROD_MTS_FORECAST'])->update(['activate' => 0]);
    }
};
