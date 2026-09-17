<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rencana subkon per langkah BOP (user 2026-09-17). Supplier subkon disimpan
// di kedua arah (master & Inquiry). Dokumen PR & surat jalan hanya di sisi
// Inquiry, karena dokumen itu milik satu order -- surat jalan subkon WAJIB
// menunjuk CO asal (do_line.co_order_id) supaya biaya subkon bisa dilacak ke
// costing order tersebut; CO baru ada setelah PO customer masuk.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_part_process', function (Blueprint $t) {
            $t->unsignedBigInteger('subcon_supplier_id')->nullable()->after('machine_id')->comment('tm2mst.sup_supplier, kosong = in-house');
        });

        Schema::connection('tm2sdi')->table('iqp_part_process', function (Blueprint $t) {
            $t->unsignedBigInteger('subcon_supplier_id')->nullable()->after('machine_id');
            $t->unsignedBigInteger('subcon_pr_id')->nullable()->after('subcon_supplier_id')->comment('tm2pur.pr_requisition');
            $t->unsignedBigInteger('subcon_do_id')->nullable()->after('subcon_pr_id')->comment('do_delivery SUBCON');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part_process', fn (Blueprint $t) => $t->dropColumn(['subcon_supplier_id', 'subcon_pr_id', 'subcon_do_id']));
        Schema::connection('tm2mst')->table('prod_part_process', fn (Blueprint $t) => $t->dropColumn('subcon_supplier_id'));
    }
};
