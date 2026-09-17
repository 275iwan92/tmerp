<?php

use App\Services\Data\DataOriginService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Flag asal data LIVE / DUMMY / LEGACY di setiap tabel data kerja (BACKLOG
// §18.7, user 2026-09-17: "setiap table hendaknya punya flag dummy data dan
// flag from legacy data ... dummy data bisa di reset ... legacy data bisa di
// tampilkan bisa tidak ... Set hide ... dari setting admin").
//
// - Kolom data_origin (default LIVE) ditambahkan ke tabel ber-PK di database
//   bisnis (lihat DataOriginService::DATABASES/EXCLUDE). tm2sys (konfigurasi
//   & framework), tm2old (arsip), tm2crp (portal eksternal) tidak disentuh.
// - Siapa DUMMY / LEGACY ditentukan ATURAN (sys_data_origin_rule) yang bisa
//   diubah admin, karena penanda di data lama tidak seragam. Aturan awal di
//   bawah diambil dari nilai created_by yang benar-benar ada di DB (dicek
//   2026-09-17). Nilai ambigu ('system', 'claude_code', kosong) SENGAJA
//   tidak dijadikan aturan umum -- lebih aman tetap LIVE daripada salah
//   menyembunyikan data nyata.
//   'SEED' (uom_unit) juga tidak dianggap dummy: itu master satuan yang sah.
// - Snapshot awal DUMMY diambil supaya "Reset dummy" punya titik kembali.
return new class extends Migration
{
    private const RULES = [
        // [match_type, db, table, column, value, origin, note]
        ['CREATED_BY', null, null, null, 'migration:%', 'LEGACY', 'Migrasi dari tm2old'],
        ['CREATED_BY', null, null, null, 'claude_migration', 'LEGACY', 'Migrasi data lama'],
        ['CREATED_BY', null, null, null, '_migrate', 'LEGACY', 'Migrasi data lama'],
        ['COLUMN_FILLED', 'tm2mst', null, 'old_item_code', null, 'LEGACY', 'Punya kode item sistem lama'],
        ['COLUMN_FILLED', 'tm2mst', 'sup_supplier', 'legacy_vendor_code', null, 'LEGACY', 'Punya kode vendor sistem lama'],
        // 'tinker-test' BUKAN data uji: isinya impor pelanggan (kode legacy
        // 113xxxxxxx) & karyawan nyata (TM-000xx) tgl 18/21-08 -- dicek 2026-09-17.
        ['CREATED_BY', null, null, null, 'tinker-test', 'LEGACY', 'Impor data sistem lama (label skrip tinker-test)'],
        ['CREATED_BY', null, null, null, 'dummy%', 'DUMMY', 'Seed dummy'],
        ['CREATED_BY', null, null, null, 'seed_dummy', 'DUMMY', 'Seed dummy'],
        ['CREATED_BY', 'tm2mst', 'emp_employee', null, 'claude_code', 'DUMMY', 'Karyawan dummy pengisi jabatan kosong (2026-09-13)'],
        ['CREATED_BY', null, null, null, 'test%', 'DUMMY', 'Data uji'],
        ['CREATED_BY', null, null, null, 'functional_test', 'DUMMY', 'Data uji'],
        ['CREATED_BY', null, null, null, 'smoke-test', 'DUMMY', 'Data uji'],
        ['CREATED_BY', null, null, null, 'portal_sim', 'DUMMY', 'Simulasi portal rekrutmen'],
        ['COLUMN_LIKE', 'tm2sdi', 'iqp_inquiry', 'inquiry_no', 'IQ2690%', 'DUMMY', 'Workload dummy Inquiry/ProDev'],
        ['COLUMN_LIKE', 'tm2sdi', 'iqp_inquiry', 'inquiry_no', 'IQUAT%', 'DUMMY', 'Sampel UAT AL SAMSI / OSIMA'],
        ['COLUMN_LIKE', 'tm2mst', 'prod_part', 'part_code', 'PDDUM%', 'DUMMY', 'Workload dummy ProDev'],
        ['COLUMN_LIKE', 'tm2mst', 'prod_part', 'mastercard_code', 'UAT-%', 'DUMMY', 'Sampel UAT'],
        ['COLUMN_LIKE', 'tm2mst', 'prod_product', 'product_code', 'UAT-%', 'DUMMY', 'Sampel UAT'],
    ];

    public function up(): void
    {
        $sys = Schema::connection('tm2sys');

        $sys->create('sys_data_origin_rule', function (Blueprint $t) {
            $t->bigIncrements('id_rule');
            $t->string('match_type', 20)->comment('CREATED_BY | COLUMN_LIKE | COLUMN_FILLED');
            $t->string('db_name', 20)->nullable()->comment('NULL = semua database');
            $t->string('table_name', 64)->nullable()->comment('NULL = semua tabel');
            $t->string('column_name', 64)->nullable();
            $t->string('match_value', 100)->nullable()->comment('pola LIKE');
            $t->string('origin', 10)->comment('DUMMY | LEGACY');
            $t->integer('priority')->default(100);
            $t->string('note', 255)->nullable();
            $t->boolean('activate')->default(true);
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
        });

        $sys->create('sys_dummy_snapshot', function (Blueprint $t) {
            $t->bigIncrements('id_snapshot');
            $t->string('db_name', 20);
            $t->string('table_name', 64);
            $t->string('pk_key', 191);
            $t->longText('row_json');
            $t->timestamp('captured_at')->nullable();
            $t->string('captured_by', 100)->nullable();
            $t->index(['db_name', 'table_name', 'pk_key']);
        });

        $now = now();
        $db = DB::connection('tm2sys');

        if (! $db->table('sys_constant_category')->where('code', 'DATA')->exists()) {
            $db->table('sys_constant_category')->insert([
                'code' => 'DATA', 'label' => 'Data Dummy & Legacy', 'sort_order' => 70,
                'activate' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            ['DATA_SHOW_DUMMY', 1, 'Tampilkan data DUMMY (demo/UAT) di layar: 1 = ya, 0 = sembunyikan'],
            ['DATA_SHOW_LEGACY', 1, 'Tampilkan data LEGACY (migrasi sistem lama): 1 = ya, 0 = sembunyikan'],
            ['DATA_NEW_AS_DUMMY', 0, 'Mode UAT: data baru ditandai DUMMY (1) supaya ikut di-reset'],
        ] as [$key, $val, $desc]) {
            $db->table('sys_constant')->updateOrInsert(['constant_key' => $key], [
                'category_code' => 'DATA', 'constant_group' => 'DATA', 'constant_number' => $val,
                'description' => $desc, 'activate' => 1,
                'created_at' => $now, 'updated_at' => $now, 'created_by' => 'claude_code', 'updated_by' => 'claude_code',
            ]);
        }

        foreach (self::RULES as $i => [$type, $dbName, $table, $col, $value, $origin, $note]) {
            $db->table('sys_data_origin_rule')->insert([
                'match_type' => $type, 'db_name' => $dbName, 'table_name' => $table, 'column_name' => $col,
                'match_value' => $value, 'origin' => $origin, 'priority' => ($i + 1) * 10, 'note' => $note,
                'activate' => 1, 'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'claude_code', 'updated_by' => 'claude_code',
            ]);
        }

        $service = app(DataOriginService::class);

        foreach ($service->tables(true) as $t) {
            if ($t['has_flag']) {
                continue;
            }
            Schema::connection($t['db'])->table($t['table'], function (Blueprint $b) {
                $b->string('data_origin', 10)->default('LIVE')->comment('LIVE | DUMMY | LEGACY');
                $b->index('data_origin');
            });
        }

        $service->tables(true);
        $service->classify(false);
        $service->snapshot('claude_code');
    }

    public function down(): void
    {
        $service = app(DataOriginService::class);

        foreach ($service->tables(true) as $t) {
            if (! $t['has_flag']) {
                continue;
            }
            Schema::connection($t['db'])->table($t['table'], function (Blueprint $b) {
                $b->dropIndex(['data_origin']);
                $b->dropColumn('data_origin');
            });
        }
        $service->tables(true);

        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['DATA_SHOW_DUMMY', 'DATA_SHOW_LEGACY', 'DATA_NEW_AS_DUMMY'])->delete();
        DB::connection('tm2sys')->table('sys_constant_category')->where('code', 'DATA')->delete();
        Schema::connection('tm2sys')->dropIfExists('sys_dummy_snapshot');
        Schema::connection('tm2sys')->dropIfExists('sys_data_origin_rule');
    }
};
