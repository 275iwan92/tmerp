<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PV - Purchase Receive / SPB (PROC_TIER_3 §5 + DELTA_PROC_PV_MULTIPO.md PV-01..PV-11).
// Desain ULANG dari patch asli user: patch mengasumsikan kolom snapshot penuh
// (c_supplier_name, c_department_name, dst) yang tak ada di po_order/po_line
// SAYA (gaya minimal-FK, PROC_TIER_3 asli) -- prasyarat DDL_PROCUREMENT_tm2pur.sql
// & SEED_PROC_DUMMY.sql dari patch juga tak ada di project ini. Aturan bisnis
// PV-01..PV-11 (multi-PO 1 supplier, referensi di baris bukan header, dst)
// DIADOPSI PENUH, hanya kolom fisiknya disesuaikan ke konvensi tm2pur yang
// sudah berjalan (pr_requisition/po_order).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2pur');

        if (! $db->hasColumn('po_line', 'is_receivable')) {
            $db->table('po_line', function (Blueprint $table) {
                $table->boolean('is_receivable')->default(true)->after('is_stock')
                    ->comment('[PV-09] 0 = baris SERVICE, ditutup lewat PO bukan PV, tanpa gerak stok');
                $table->index('is_receivable');
            });

            $db->getConnection()->table('po_line')->where('i_line_class', 'SERVICE')->update(['is_receivable' => false]);
        }

        if (! $db->hasTable('pv_receipt')) {
            $db->create('pv_receipt', function (Blueprint $table) {
                $table->id('pv_id');
                $table->string('doc_no', 30)->nullable()->unique()->comment('SPB/YY/##### di mockup -- dipakai SPB+YY+##### tanpa slash utk konsisten format nomor dokumen lain di TMERP');
                $table->date('i_doc_date');
                $table->unsignedBigInteger('i_supplier_id')->comment('[PV-02] KUNCI DOKUMEN -- semua baris wajib dari supplier ini (HARD BLOCK)');
                $table->string('i_warehouse_code', 50)->comment('[PV-03] KUNCI DOKUMEN -- semua baris wajib ke gudang ini (HARD BLOCK). Teks bebas: m_warehouse belum ada (PROC-WAREHOUSE-MASTER)');
                $table->string('i_supplier_do_no', 150)->nullable()->comment('[PV-07] SJ utama -- boleh lebih dari satu nomor bila beberapa SJ dalam satu kiriman');
                $table->string('i_vehicle_no', 30)->nullable();
                $table->string('i_notes', 255)->nullable();

                $table->unsignedSmallInteger('c_po_count')->nullable()->comment('[PV-01] ringkasan cetak/grid SAJA -- logika wajib baca pv_line');
                $table->string('c_po_doc_list', 255)->nullable()->comment('[PV-01] daftar no PO dipisah koma -- ringkasan cetak/grid SAJA');

                $table->string('status', 20)->default('DRAFT')->comment('DRAFT|QC_PENDING|POSTED|CANCELLED');
                $table->dateTime('posted_at')->nullable();
                $table->string('posted_by', 100)->nullable();
                $table->unsignedBigInteger('rm_stock_doc_id')->nullable()->comment('LOGICAL -> tm2inv.rm_stock -- NULL sampai tminv legacy tersedia, lihat PROC_STATE.yaml');

                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('status');
                $table->index('i_supplier_id');
                $table->index('i_doc_date');
            });
        }

        if (! $db->hasTable('pv_line')) {
            $db->create('pv_line', function (Blueprint $table) {
                $table->id('pv_line_id');
                $table->unsignedBigInteger('pv_id');
                $table->unsignedSmallInteger('line_no');

                $table->unsignedBigInteger('po_line_id')->comment('Pengikat sesungguhnya (fisik, sama-DB)');
                $table->unsignedBigInteger('c_po_id')->comment('[PV-01/PV-08] snapshot po_order.po_id -- kunci rekalkulasi per PO');
                $table->string('c_po_doc_no', 30)->comment('[PV-01] snapshot -- dicetak per baris di nota SPB');
                $table->unsignedSmallInteger('c_po_line_no')->comment('[PV-01] snapshot nomor baris PO asal');

                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('c_item_code', 60)->nullable();
                $table->string('i_description', 255);
                $table->boolean('is_stock')->default(true);
                $table->string('i_supplier_do_no', 50)->nullable()->comment('[PV-07] SJ khusus baris ini; NULL = ikut SJ header');

                // Dual-UOM (PROC_TIER_2 §A-4.1) -- dua sisi independen.
                $table->decimal('i_qty_purchase', 20, 4)->comment('satuan BELI -- menutup po_line & dasar tagihan');
                $table->string('i_uom_purchase', 50);
                $table->decimal('i_qty_stock', 20, 4)->comment('satuan STOK aktual -- hasil hitung/timbang');
                $table->string('i_uom_stock', 50);
                $table->decimal('c_conv_used', 20, 6)->nullable()->comment('i_qty_stock / i_qty_purchase -- jejak konversi nyata');
                $table->boolean('is_conv_manual')->default(false)->comment('1 = officer menimpa konversi master (barang curah)');

                // QC dua gerbang.
                $table->enum('qc_qty_status', ['PENDING', 'DONE'])->default('PENDING');
                $table->enum('qc_quality_status', ['PENDING', 'PASS', 'PARTIAL', 'FAIL'])->default('PENDING');
                $table->decimal('c_qty_accepted', 20, 4)->nullable()->comment('satuan stok -- HANYA INI yang masuk gudang');
                $table->decimal('c_qty_rejected', 20, 4)->nullable()->comment('satuan stok -- kandidat retur');
                $table->string('qc_note', 255)->nullable();
                $table->string('qc_by', 100)->nullable();
                $table->dateTime('qc_at')->nullable();

                // Toleransi.
                $table->decimal('c_tol_pct', 5, 2)->nullable()->comment('snapshot po_line.c_tol_pct');
                $table->decimal('c_qty_variance_pct', 8, 4)->nullable();
                $table->boolean('is_tol_exceeded')->default(false);
                $table->string('tol_override_by', 100)->nullable();
                $table->dateTime('tol_override_at')->nullable();
                $table->string('tol_override_reason', 255)->nullable();

                // Nilai (Sisi A) -- tersimpan selalu, TIDAK ditampilkan di layar gudang.
                $table->decimal('c_unit_price', 18, 4)->nullable()->comment('snapshot po_line.i_price');
                $table->decimal('c_line_value', 18, 4)->nullable()->comment('c_qty_accepted * c_unit_price');

                $table->string('i_lot_no', 100)->nullable();
                $table->string('i_location_code', 50)->nullable()->comment('Teks bebas: master lokasi gudang belum ada');
                $table->unsignedBigInteger('i_co_line_id')->nullable()->comment('snapshot dari po_line');
                $table->decimal('c_qty_invoiced', 20, 4)->default(0);
                $table->decimal('c_qty_returned', 20, 4)->default(0);

                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['pv_id', 'line_no']);
                $table->index('po_line_id');
                $table->index('c_po_id');
                $table->index('qc_quality_status');
                $table->foreign('pv_id')->references('pv_id')->on('pv_receipt')->cascadeOnDelete();
                $table->foreign('po_line_id')->references('po_line_id')->on('po_line')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2pur');
        $db->dropIfExists('pv_line');
        $db->dropIfExists('pv_receipt');

        if ($db->hasColumn('po_line', 'is_receivable')) {
            $db->table('po_line', function (Blueprint $table) {
                $table->dropColumn('is_receivable');
            });
        }
    }
};
