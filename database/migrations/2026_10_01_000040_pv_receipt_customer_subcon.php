<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Penerimaan Barang 3 jenis (user 2026-10-01) dalam satu dokumen SPB (pv_receipt):
// - PO       : seperti sekarang (supplier, PO, gudang, QC, stok + jurnal).
// - CUSTOMER : bahan dari customer untuk CO tertentu (Inquiry "bahan dari customer") -- WAJIB No. CO; bukan stok
//              titipan umum: tidak masuk stok bernilai, tidak dijurnal. Baris menunjuk co_order_item (i_co_line_id).
// - SUBCON   : barang kembali dari subkon atas PO Subkon; langsung diteruskan ke proses berikutnya (tidak masuk gudang).
//              Kekurangan yang baru ketahuan di proses berikutnya dicatat belakangan (c_qty_short) -> PO & jurnal ikut koreksi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->table('pv_receipt', function (Blueprint $t) {
            $t->string('i_receipt_type', 10)->default('PO')->after('doc_no')->comment('PO | CUSTOMER | SUBCON');
            $t->unsignedBigInteger('i_supplier_id')->nullable()->change();
            $t->string('i_warehouse_code', 50)->nullable()->change();
            $t->unsignedBigInteger('i_customer_id')->nullable()->after('i_supplier_id')->comment('CUSTOMER: tm2mst.cust_customer');
            $t->unsignedBigInteger('i_co_id')->nullable()->after('i_customer_id')->comment('CUSTOMER: tm2sdi.co_order (wajib)');
            $t->unsignedBigInteger('i_do_id')->nullable()->after('i_co_id')->comment('SUBCON: Surat Jalan Subkon tm2sdi.do_delivery (opsional)');
        });
        Schema::connection('tm2pur')->table('pv_line', function (Blueprint $t) {
            foreach (['po_line_id', 'c_po_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable()->change();
            }
            $t->string('c_po_doc_no', 30)->nullable()->change();
            $t->unsignedSmallInteger('c_po_line_no')->nullable()->change();
            $t->decimal('i_qty_stock', 20, 4)->nullable()->change();
            $t->string('i_uom_stock', 50)->nullable()->change();
            $t->string('i_next_process', 100)->nullable()->after('i_location_code')->comment('SUBCON: proses berikutnya penerima barang');
            $t->decimal('c_qty_short', 20, 4)->default(0)->after('i_next_process')->comment('SUBCON: kekurangan yang ketahuan belakangan');
            $t->string('i_short_note', 255)->nullable()->after('c_qty_short');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->table('pv_line', fn (Blueprint $t) => $t->dropColumn(['i_next_process', 'c_qty_short', 'i_short_note']));
        Schema::connection('tm2pur')->table('pv_receipt', fn (Blueprint $t) => $t->dropColumn(['i_receipt_type', 'i_customer_id', 'i_co_id', 'i_do_id']));
    }
};
