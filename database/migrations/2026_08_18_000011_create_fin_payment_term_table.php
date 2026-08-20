<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// cust_customer.payment_term & sup_supplier.payment_term sejak awal cuma
// varchar bebas (comment DDL sudah bilang "FK -> fin_payment_term (menyusul)").
// Master-nya belum pernah dibuat. Isi seed diambil dari nilai yang SUDAH
// benar-benar dipakai di data (bukan dikarang) -- lihat distinct payment_term
// cust_customer/sup_supplier hasil migrasi vendor 2026-08-18.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('fin_payment_term', function (Blueprint $table) {
            $table->string('payment_term_code', 20)->primary();
            $table->string('term_label', 60);
            $table->smallInteger('days')->unsigned()->nullable()->comment('Jumlah hari jatuh tempo, NULL kalau tidak relevan (mis. dimuka/tanpa bayar)');
            $table->smallInteger('sort_order')->default(999);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        $db = DB::connection('tm2mst');
        $now = now();

        $terms = [
            ['payment_term_code' => 'CASH', 'term_label' => 'Bayar Kontan', 'days' => 0, 'sort_order' => 10],
            ['payment_term_code' => 'COD', 'term_label' => 'Bayar di Tempat', 'days' => 0, 'sort_order' => 20],
            ['payment_term_code' => 'ADVANCE', 'term_label' => 'Bayar Dimuka', 'days' => null, 'sort_order' => 30],
            ['payment_term_code' => 'NET7', 'term_label' => 'Bayar 7 Hari', 'days' => 7, 'sort_order' => 40],
            ['payment_term_code' => 'NET14', 'term_label' => 'Bayar 14 Hari', 'days' => 14, 'sort_order' => 50],
            ['payment_term_code' => 'NET21', 'term_label' => 'Bayar 21 Hari', 'days' => 21, 'sort_order' => 60],
            ['payment_term_code' => 'NET30', 'term_label' => 'Bayar 30 Hari', 'days' => 30, 'sort_order' => 70],
            ['payment_term_code' => 'NET45', 'term_label' => 'Bayar 45 Hari', 'days' => 45, 'sort_order' => 80],
            ['payment_term_code' => 'NET60', 'term_label' => 'Bayar 60 Hari', 'days' => 60, 'sort_order' => 90],
            ['payment_term_code' => 'NET90', 'term_label' => 'Bayar 90 Hari', 'days' => 90, 'sort_order' => 100],
            ['payment_term_code' => 'NONE', 'term_label' => 'Tidak Bayar', 'days' => null, 'sort_order' => 110],
        ];

        foreach ($terms as $t) {
            $db->table('fin_payment_term')->insert($t + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Petakan teks lama -> kode baru (exact match, case-insensitive).
        $map = [
            'BAYAR KONTAN' => 'CASH',
            'BAYAR DI TEMPAT' => 'COD',
            'BAYAR DIMUKA' => 'ADVANCE',
            'BAYAR 7 HARI' => 'NET7',
            'BAYAR 14 HARI' => 'NET14',
            'BAYAR 21 HARI' => 'NET21',
            'BAYAR 30 HARI' => 'NET30',
            'BAYAR 45 HARI' => 'NET45',
            'BAYAR 60 HARI' => 'NET60',
            'BAYAR 90 HARI' => 'NET90',
            'TIDAK BAYAR' => 'NONE',
        ];

        $validCodes = array_column($terms, 'payment_term_code');

        foreach (['cust_customer', 'sup_supplier'] as $table) {
            foreach ($map as $oldText => $code) {
                $db->table($table)->whereRaw('UPPER(payment_term) = ?', [$oldText])->update(['payment_term' => $code]);
            }
            // Sisa yang tidak cocok (kosong, atau teks "1" peninggalan sistem
            // lama tanpa konteks) -> NULL, bukan dipaksa nebak. Admin isi ulang
            // manual. Wajib dibereskan sebelum FK constraint di bawah dipasang.
            $db->table($table)
                ->whereNotNull('payment_term')
                ->whereNotIn('payment_term', $validCodes)
                ->update(['payment_term' => null]);
        }

        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->foreign('payment_term')->references('payment_term_code')->on('fin_payment_term')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::connection('tm2mst')->table('sup_supplier', function (Blueprint $table) {
            $table->foreign('payment_term')->references('payment_term_code')->on('fin_payment_term')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->dropForeign(['payment_term']);
        });
        Schema::connection('tm2mst')->table('sup_supplier', function (Blueprint $table) {
            $table->dropForeign(['payment_term']);
        });
        Schema::connection('tm2mst')->dropIfExists('fin_payment_term');
    }
};
