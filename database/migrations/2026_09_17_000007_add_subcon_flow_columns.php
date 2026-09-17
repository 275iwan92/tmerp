<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Alur subkon dari Bill of Process (user 2026-09-17): baris BOP yang prosesnya
// tidak dikerjakan sendiri punya tombol Subkon -> Purchase Requisition subkon
// -> Surat Jalan ke pihak subkon.
//
// 1) prod_process.inhouse -- proses yang TIDAK bisa dikerjakan sendiri = 0.
//    Awal: HOT PRINT & FOIL STAMPING (legacy hanya punya mesin SUBKON_HOTPRINT,
//    tidak ada mesin hot print sendiri). Bisa diubah di Master Proses.
// 2) Surat jalan subkon memakai tabel surat jalan yang SUDAH ADA (do_delivery /
//    do_line, syarat user "tidak ada tabel dobel"): do_type CUSTOMER|SUBCON,
//    tujuan supplier (bukan customer), rujukan PR & part. Kolom CO di do_line
//    jadi nullable karena surat jalan subkon tidak berasal dari Customer Order.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_process', function (Blueprint $t) {
            $t->boolean('inhouse')->default(true)->after('gantt_color')->comment('0 = dikerjakan subkon (pihak ke-3)');
        });

        DB::connection('tm2mst')->table('prod_process')
            ->whereIn('process_name', ['HOT PRINT', 'FOIL STAMPING'])
            ->update(['inhouse' => 0, 'updated_by' => 'claude_code', 'updated_at' => now()]);

        Schema::connection('tm2sdi')->table('do_delivery', function (Blueprint $t) {
            $t->string('do_type', 10)->default('CUSTOMER')->after('do_no')->comment('CUSTOMER | SUBCON');
            $t->unsignedBigInteger('customer_id')->nullable()->change();
            $t->unsignedBigInteger('supplier_id')->nullable()->after('customer_id')->comment('tm2mst.sup_supplier, utk SUBCON');
            $t->unsignedBigInteger('pr_id')->nullable()->after('supplier_id')->comment('tm2pur.pr_requisition subkon');
            $t->unsignedBigInteger('ref_part_id')->nullable()->after('pr_id')->comment('tm2mst.prod_part yang disubkonkan');
            $t->string('subcon_process', 80)->nullable()->after('ref_part_id');
            $t->index('do_type');
        });

        Schema::connection('tm2sdi')->table('do_line', function (Blueprint $t) {
            $t->unsignedBigInteger('co_order_id')->nullable()->change();
            $t->unsignedBigInteger('co_order_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('do_delivery', function (Blueprint $t) {
            $t->dropIndex(['do_type']);
            $t->dropColumn(['do_type', 'supplier_id', 'pr_id', 'ref_part_id', 'subcon_process']);
        });

        Schema::connection('tm2mst')->table('prod_process', fn (Blueprint $t) => $t->dropColumn('inhouse'));
    }
};
