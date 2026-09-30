<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Purchase Quotation v2 (user 2026-10-01): PQ lahir dari file penawaran supplier yang di-upload (PDF/Excel/foto), dibaca
// otomatis lalu dicek officer. Supplier boleh kosong dulu (belum cocok ke master) -- wajib terisi sebelum Confirm.
// po_line.pq_line_id = jejak harga PO -> baris penawaran; PO dari PQ tetap lewat pr_line_alloc supaya PR ikut ter-update.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->table('pq_quotation', function (Blueprint $t) {
            $t->unsignedBigInteger('i_supplier_id')->nullable()->change();
            $t->string('i_supplier_text', 150)->nullable()->after('i_supplier_id')->comment('nama supplier seperti tertulis di file');
            $t->string('source_path', 255)->nullable()->after('i_notes')->comment('file penawaran asli (disk public)');
            $t->string('source_name', 255)->nullable()->after('source_path');
            $t->string('extract_status', 20)->nullable()->after('source_name')->comment('READ | CHECK | MANUAL | FAILED');
            $t->string('extract_note', 255)->nullable()->after('extract_status');
        });
        Schema::connection('tm2pur')->table('pq_line', function (Blueprint $t) {
            $t->string('i_source_text', 255)->nullable()->after('i_description')->comment('teks baris seperti tertulis di file');
            $t->unsignedTinyInteger('c_match_pct')->nullable()->after('i_source_text')->comment('keyakinan pencocokan ke bahan/baris PR, 0-100');
        });
        Schema::connection('tm2pur')->table('po_line', function (Blueprint $t) {
            $t->unsignedBigInteger('pq_line_id')->nullable()->after('alloc_id')->index()->comment('baris PQ asal harga (pq_line)');
        });

        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROC_PQ')->update([
            'target' => 'v2:purchase-quotation', 'target_kind' => 'app', 'status_code' => 'READY_FOR_QC',
            'status_note' => 'v2 PQ: upload file penawaran -> dibaca -> bandingkan -> Buat PO', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROC_PQ')->update(['target' => 'form_purchase_quotation.php', 'status_code' => 'NOT_BUILT']);
        Schema::connection('tm2pur')->table('po_line', fn (Blueprint $t) => $t->dropColumn('pq_line_id'));
        Schema::connection('tm2pur')->table('pq_line', fn (Blueprint $t) => $t->dropColumn(['i_source_text', 'c_match_pct']));
        Schema::connection('tm2pur')->table('pq_quotation', function (Blueprint $t) {
            $t->dropColumn(['i_supplier_text', 'source_path', 'source_name', 'extract_status', 'extract_note']);
        });
    }
};
