<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Delta wajib PROC_TIER_3.md §7.1: kolom tambahan di doc_rmprefix1 (kelas
// stok + akun COA usulan, penentu klasifikasi beban PR/PO) dan m_raw_material
// (metode harga perolehan per item, penanda sensitif kurs, FK ke prefix5).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        if (! $db->hasColumn('doc_rmprefix1', 'stock_class')) {
            $db->table('doc_rmprefix1', function (Blueprint $table) {
                $table->enum('stock_class', ['RAW', 'INDIRECT', 'SERVICE'])
                    ->default('RAW')
                    ->after('note')
                    ->comment('Penentu apakah kategori masuk stok / beban langsung / jasa - PROC_TIER_1 §1.1');
                $table->unsignedBigInteger('default_coa_account_id')->nullable()->after('stock_class')
                    ->comment('LOGICAL -> tm2acc.fin_account (is_postable=1); usulan akun, boleh diubah officer');
            });
        }

        if (! $db->hasColumn('m_raw_material', 'costing_method')) {
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->enum('costing_method', ['AVG', 'FIFO', 'LIFO'])->default('AVG')->after('def_location_id')
                    ->comment('Metode harga perolehan PER ITEM - PROC_TIER_1 §5.2');
                $table->boolean('is_fx_sensitive')->default(false)->after('costing_method')
                    ->comment('1 = harga mengikuti kurs, tampilkan peringatan di PR/PQ');
                $table->unsignedInteger('prefix5_id')->nullable()->after('is_fx_sensitive')
                    ->comment('FK doc_rmprefix5.idprefix - sumber kebenaran baru pembentuk material_code');

                $table->foreign('prefix5_id')->references('idprefix')->on('doc_rmprefix5')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        if ($db->hasColumn('m_raw_material', 'prefix5_id')) {
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->dropForeign(['prefix5_id']);
                $table->dropColumn(['costing_method', 'is_fx_sensitive', 'prefix5_id']);
            });
        }

        if ($db->hasColumn('doc_rmprefix1', 'stock_class')) {
            $db->table('doc_rmprefix1', function (Blueprint $table) {
                $table->dropColumn(['stock_class', 'default_coa_account_id']);
            });
        }
    }
};
