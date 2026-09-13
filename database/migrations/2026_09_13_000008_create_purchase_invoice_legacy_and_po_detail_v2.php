<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Melengkapi sisi Purchasing setelah audit menyeluruh tabel pur_*:
//
// 1. Purchase Invoice (pur_pinv/_detail, 3.414 dok / Rp 30,4 M) sebelumnya
//    TERLEWAT. Ini dasar AP yang sebenarnya -- dan pur_ppym_detail.pinv_no
//    menyambung ke sini 100%, jadi rantai Invoice -> Pembayaran utuh.
//    Catatan: pur_pinv.total_payment SELALU 0 di sumber (kolom mati, pola
//    sama dgn sdi_cinv.remain_of_bill), jadi saldo utang HARUS dihitung
//    grand_total - SUM(pembayaran), bukan dibaca dari header.
//
// 2. Kolom tambahan po_order_legacy_detail agar bisa memakai
//    pur_po_detail_v2 sebagai sumber baris PO. v2 lebih benar dari v1:
//    tersambung ke header lewat FK doc_id (100%), bukan pencocokan teks
//    po_no yang terbukti menghasilkan baris palsu; dan membawa diskon
//    per-baris, departemen, mesin, serta qty_request vs qty_purchase.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->create('po_invoice_legacy', function (Blueprint $table) {
            $table->id('id_pinv_legacy');
            $table->string('legacy_pinv_no', 30)->unique();
            $table->date('pinv_date')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('payment_term', 50)->nullable();
            $table->decimal('subtotal', 20, 2)->nullable();
            $table->decimal('disc_amount', 20, 2)->nullable();
            $table->decimal('ppn_amount', 20, 2)->nullable();
            $table->decimal('grand_total', 20, 2)->nullable();
            $table->string('legacy_status', 20)->nullable();
            $table->string('doc_status_normalized', 20)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->index('supplier_id');
            $table->index('pinv_date');
        });

        Schema::connection('tm2pur')->create('po_invoice_legacy_detail', function (Blueprint $table) {
            $table->id('id_pinv_legacy_detail');
            $table->foreignId('pinv_legacy_id')->constrained('po_invoice_legacy', 'id_pinv_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_pinv_detail_id')->nullable();
            $table->unsignedBigInteger('legacy_po_detail_id')->nullable()->comment('-> rantai ke baris PO');
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('legacy_item_code', 100)->nullable();
            $table->decimal('qty_inv', 14, 2)->nullable();
            $table->string('uom_code', 10)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('qty_unitprice', 20, 2)->nullable();
            $table->timestamps();
            $table->index('legacy_po_detail_id');
            $table->index('material_id');
        });

        // Pembayaran -> Invoice: sebelumnya hanya teks (legacy_pinv_no) karena
        // pur_pinv belum ada. Sekarang bisa jadi FK terindeks yg benar.
        Schema::connection('tm2pur')->table('po_payment_legacy_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('pinv_legacy_id')->nullable()->after('legacy_pinv_no');
            $table->index('pinv_legacy_id');
        });

        // Kolom kekayaan dari pur_po_detail_v2.
        Schema::connection('tm2pur')->table('po_order_legacy_detail', function (Blueprint $table) {
            $table->string('item_description', 500)->nullable()->after('legacy_item_code');
            $table->decimal('qty_request', 20, 4)->nullable()->after('qty');
            $table->string('uom_request', 20)->nullable()->after('uom_code');
            $table->decimal('disc_percent', 5, 2)->nullable()->after('unit_price');
            $table->decimal('disc_amount', 20, 2)->nullable()->after('disc_percent');
            $table->string('department', 50)->nullable();
            $table->string('machine_code', 50)->nullable();
            $table->date('required_date')->nullable();
            $table->string('line_status', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->dropIfExists('po_invoice_legacy_detail');
        Schema::connection('tm2pur')->dropIfExists('po_invoice_legacy');

        Schema::connection('tm2pur')->table('po_payment_legacy_detail', function (Blueprint $table) {
            $table->dropIndex(['pinv_legacy_id']);
            $table->dropColumn('pinv_legacy_id');
        });

        Schema::connection('tm2pur')->table('po_order_legacy_detail', function (Blueprint $table) {
            $table->dropColumn([
                'item_description', 'qty_request', 'uom_request',
                'disc_percent', 'disc_amount', 'department', 'machine_code',
                'required_date', 'line_status',
            ]);
        });
    }
};
