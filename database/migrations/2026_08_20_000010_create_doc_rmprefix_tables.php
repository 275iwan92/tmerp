<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Adopsi legacy tm2mst.doc_rmprefix1..5 (pembentuk kode item 5-level:
// kategori->jenis->gramatur/spec->ukuran->merk, mis. P+Y+J+3+1 = PYJ31) —
// procurement_tier1.md §5.5 & procurement_tier3.md §2. Sumber: dump asli dari user
// (database/legacy/doc_rmprefix.sql, 2026-08-20). Struktur kolom dipertahankan
// 1:1 dari dump; hanya baris DROP/CREATE FK yang tak relevan (tak ada FK di
// tabel ini) diabaikan -- generate ulang via Blueprint supaya konsisten
// dengan konvensi migration Laravel proyek ini, data di-import apa adanya.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        if (! $db->hasTable('doc_rmprefix1')) {
            $db->create('doc_rmprefix1', function (Blueprint $table) {
                $table->increments('idprefix');
                $table->string('category1', 100)->nullable();
                $table->string('prefix1', 1)->nullable()->unique();
                $table->text('note')->nullable();
            });
        }

        if (! $db->hasTable('doc_rmprefix2')) {
            $db->create('doc_rmprefix2', function (Blueprint $table) {
                $table->increments('idprefix');
                $table->string('category1', 100)->nullable();
                $table->string('category2', 100)->nullable();
                $table->string('prefix1', 1)->nullable();
                $table->string('prefix2', 1)->nullable();
                $table->text('note')->nullable();
                $table->unique(['prefix1', 'prefix2']);
            });
        }

        if (! $db->hasTable('doc_rmprefix3')) {
            $db->create('doc_rmprefix3', function (Blueprint $table) {
                $table->increments('idprefix');
                $table->string('category1', 100)->nullable();
                $table->string('category2', 100)->nullable();
                $table->string('category3', 100)->nullable();
                $table->string('prefix1', 1)->nullable();
                $table->string('prefix2', 1)->nullable();
                $table->string('prefix3', 1)->nullable();
                $table->text('note')->nullable();
                $table->unique(['prefix1', 'prefix2', 'prefix3']);
            });
        }

        if (! $db->hasTable('doc_rmprefix4')) {
            $db->create('doc_rmprefix4', function (Blueprint $table) {
                $table->increments('idprefix');
                $table->string('category1', 100)->nullable();
                $table->string('category2', 100)->nullable();
                $table->string('category3', 100)->nullable();
                $table->string('category4', 100)->nullable();
                $table->string('prefix1', 1)->nullable();
                $table->string('prefix2', 1)->nullable();
                $table->string('prefix3', 1)->nullable();
                $table->string('prefix4', 3)->nullable();
                $table->text('note')->nullable();
            });
        }

        if (! $db->hasTable('doc_rmprefix5')) {
            $db->create('doc_rmprefix5', function (Blueprint $table) {
                $table->increments('idprefix');
                $table->string('category1', 100)->nullable();
                $table->string('category2', 100)->nullable();
                $table->string('category3', 100)->nullable();
                $table->string('category4', 100)->nullable();
                $table->string('category5', 100)->nullable();
                $table->string('prefix1', 1)->nullable();
                $table->string('prefix2', 1)->nullable();
                $table->string('prefix3', 1)->nullable();
                $table->string('prefix4', 3)->nullable();
                $table->string('prefix5', 3)->nullable();
                $table->text('note')->nullable();
                $table->unique(['prefix1', 'prefix2', 'prefix3', 'prefix4', 'prefix5']);
            });

            $this->importLegacyData($db);
        }
    }

    private function importLegacyData($db): void
    {
        $path = database_path('legacy/doc_rmprefix.sql');

        if (! file_exists($path)) {
            return;
        }

        $sql = file_get_contents($path);
        $lines = preg_split('/\r\n|\r|\n/', $sql);
        $inserts = array_values(array_filter($lines, fn ($line) => str_starts_with(trim($line), 'INSERT INTO')));

        if (empty($inserts)) {
            return;
        }

        DB::connection('tm2mst')->unprepared(implode("\n", $inserts));

        foreach (range(1, 5) as $level) {
            $table = "doc_rmprefix{$level}";
            $max = $db->getConnection()->table($table)->max('idprefix');

            if ($max) {
                $db->getConnection()->statement("ALTER TABLE `{$table}` AUTO_INCREMENT = ".((int) $max + 1));
            }
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        foreach ([5, 4, 3, 2, 1] as $level) {
            $db->dropIfExists("doc_rmprefix{$level}");
        }
    }
};
