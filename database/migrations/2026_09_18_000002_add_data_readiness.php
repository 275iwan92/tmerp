<?php

use App\Services\Data\DataOriginService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kelengkapan data (keputusan user 2026-09-18): data LEGACY yang sudah
// memenuhi syarat data saat ini SAH dipakai sebagai data real -- contohnya
// master shift yang praktis sama dengan kebutuhan sekarang. Yang belum
// memenuhi syarat (mis. master produk tanpa kepemilikan customer) tetap
// ditandai BELUM LENGKAP dan tidak boleh dijadikan acuan.
//
// - Kolom data_ready (default 1) di tabel yang punya data_origin.
// - sys_data_requirement: syarat wajib per tabel, diatur admin.
// - Syarat awal: prod_product.customer_id (kepemilikan customer) -- contoh
//   yang disebut user. Syarat lain ditambahkan admin sesuai kebutuhan.
// - Setting DATA_HIDE_LEGACY_INCOMPLETE: sembunyikan legacy yang belum lengkap
//   (default 0 = tetap tampil, supaya bisa dilengkapi).
return new class extends Migration
{
    private const REQUIREMENTS = [
        ['tm2mst', 'prod_product', 'customer_id', 'Kepemilikan customer', 'Master produk tanpa customer belum bisa jadi acuan data real'],
    ];

    public function up(): void
    {
        Schema::connection('tm2sys')->create('sys_data_requirement', function (Blueprint $t) {
            $t->bigIncrements('id_requirement');
            $t->string('db_name', 20);
            $t->string('table_name', 64);
            $t->string('column_name', 64);
            $t->string('label', 100)->comment('nama syarat, tampil di layar');
            $t->string('note', 255)->nullable();
            $t->boolean('activate')->default(true);
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->unique(['db_name', 'table_name', 'column_name']);
        });

        $now = now();
        $db = DB::connection('tm2sys');

        foreach (self::REQUIREMENTS as [$dbName, $table, $col, $label, $note]) {
            $db->table('sys_data_requirement')->insert([
                'db_name' => $dbName, 'table_name' => $table, 'column_name' => $col,
                'label' => $label, 'note' => $note, 'activate' => 1,
                'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'claude_code', 'updated_by' => 'claude_code',
            ]);
        }

        $db->table('sys_constant')->updateOrInsert(['constant_key' => 'DATA_HIDE_LEGACY_INCOMPLETE'], [
            'category_code' => 'DATA', 'constant_group' => 'DATA', 'constant_number' => 0,
            'description' => 'Sembunyikan data LEGACY yang belum memenuhi syarat wajib: 1 = sembunyikan',
            'activate' => 1, 'created_at' => $now, 'updated_at' => $now,
            'created_by' => 'claude_code', 'updated_by' => 'claude_code',
        ]);

        $service = app(DataOriginService::class);

        foreach ($service->tables(true) as $t) {
            if (! $t['has_flag']) {
                continue;
            }
            Schema::connection($t['db'])->table($t['table'], function (Blueprint $b) {
                $b->boolean('data_ready')->default(true)->comment('1 = memenuhi syarat wajib data saat ini');
            });
        }

        $service->computeReady(false);
    }

    public function down(): void
    {
        $service = app(DataOriginService::class);

        foreach ($service->tables(true) as $t) {
            if (! $t['has_flag']) {
                continue;
            }
            Schema::connection($t['db'])->table($t['table'], fn (Blueprint $b) => $b->dropColumn('data_ready'));
        }

        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'DATA_HIDE_LEGACY_INCOMPLETE')->delete();
        Schema::connection('tm2sys')->dropIfExists('sys_data_requirement');
    }
};
