<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ganti doc_rmprefix1..5 (5 tabel snowflake, nyambung antar-level via TEXT match
// category1..N, prefix "doc_" tak sesuai konvensi LOCKED CLAUDE.md §3 -- domain
// material harusnya "mat_") dengan SATU tabel self-referencing mat_category
// (parent_id, level, category_name, prefix_char) -- pola adjacency-list standar.
// Keputusan user 2026-08-26 setelah diskusi soal penamaan & desain berantai.
//
// mat_category = sumber NAMA (category_name, dipakai isi m_raw_material.category1..5)
// DAN KODE item (prefix_char, dipakai bentuk material_code) sekaligus -- rule
// eksplisit dari user, jangan dipisah jadi 2 tabel beda.
//
// m_raw_material.prefix5_id (FK ke doc_rmprefix5) di-repoint ke mat_category --
// kolom ini sudah ada tapi TIDAK PERNAH diisi kode (dicek: 0 baris terisi),
// jadi tak ada data yang perlu dikonversi, cuma constraint-nya dipindah.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');
        $mst = DB::connection('tm2mst');

        if (! $db->hasTable('mat_category')) {
            $db->create('mat_category', function (Blueprint $table) {
                $table->increments('id_category');
                $table->unsignedInteger('parent_id')->nullable();
                $table->unsignedTinyInteger('level');
                $table->string('category_name', 100);
                $table->string('prefix_char', 3);
                $table->text('note')->nullable();
                $table->enum('stock_class', ['RAW', 'INDIRECT', 'SERVICE'])->nullable()
                    ->comment('Cuma diisi utk level=1 -- penentu kelas stok/beban/jasa, PROC_TIER_1 §1.1');
                $table->unsignedBigInteger('default_coa_account_id')->nullable()
                    ->comment('Cuma diisi utk level=1 -- LOGICAL -> tm2acc.fin_account, usulan akun beban/HPP default kategori');
                $table->unsignedBigInteger('default_stock_asset_account_id')->nullable()
                    ->comment('Cuma diisi utk level=1 -- LOGICAL -> tm2acc.fin_account (Persediaan)');
                $table->unsignedBigInteger('default_gr_ir_account_id')->nullable()
                    ->comment('Cuma diisi utk level=1 -- LOGICAL -> tm2acc.fin_account (GR/IR Clearing)');
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('updated_by', 100)->nullable();
                $table->unique(['parent_id', 'prefix_char']);
                $table->foreign('parent_id')->references('id_category')->on('mat_category')->nullOnDelete();
            });

            $this->migrateLegacyData($mst);
        }

        if ($db->hasColumn('m_raw_material', 'prefix5_id')) {
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->dropForeign('m_raw_material_prefix5_id_foreign');
            });
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->foreign('prefix5_id')->references('id_category')->on('mat_category')->nullOnDelete();
            });
        }

        foreach ([5, 4, 3, 2, 1] as $level) {
            $db->dropIfExists("doc_rmprefix{$level}");
        }
    }

    private function migrateLegacyData($mst): void
    {
        $now = now();

        $rows1 = $mst->table('doc_rmprefix1')->orderBy('idprefix')->get();
        $rows2 = $mst->table('doc_rmprefix2')->orderBy('idprefix')->get();
        $rows3 = $mst->table('doc_rmprefix3')->orderBy('idprefix')->get();
        $rows4 = $mst->table('doc_rmprefix4')->orderBy('idprefix')->get();
        $rows5 = $mst->table('doc_rmprefix5')->orderBy('idprefix')->get();

        $idByKey = [];

        foreach ($rows1 as $r) {
            if (! $r->prefix1) {
                continue;
            }
            $id = $mst->table('mat_category')->insertGetId([
                'parent_id' => null,
                'level' => 1,
                'category_name' => $r->category1,
                'prefix_char' => $r->prefix1,
                'note' => $r->note ?: null,
                'stock_class' => $r->stock_class ?? null,
                'default_coa_account_id' => $r->default_coa_account_id ?? null,
                'default_stock_asset_account_id' => $r->default_stock_asset_account_id ?? null,
                'default_gr_ir_account_id' => $r->default_gr_ir_account_id ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'id_category');
            $idByKey['1|'.$r->prefix1] = $id;
        }

        foreach ($rows2 as $r) {
            if (! $r->prefix1 || ! $r->prefix2) {
                continue;
            }
            $parentId = $idByKey['1|'.$r->prefix1] ?? null;
            $id = $mst->table('mat_category')->insertGetId([
                'parent_id' => $parentId,
                'level' => 2,
                'category_name' => $r->category2,
                'prefix_char' => $r->prefix2,
                'note' => $r->note ?: null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'id_category');
            $idByKey['2|'.$r->prefix1.$r->prefix2] = $id;
        }

        foreach ($rows3 as $r) {
            if (! $r->prefix1 || ! $r->prefix2 || ! $r->prefix3) {
                continue;
            }
            $parentId = $idByKey['2|'.$r->prefix1.$r->prefix2] ?? null;
            $id = $mst->table('mat_category')->insertGetId([
                'parent_id' => $parentId,
                'level' => 3,
                'category_name' => $r->category3,
                'prefix_char' => $r->prefix3,
                'note' => $r->note ?: null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'id_category');
            $idByKey['3|'.$r->prefix1.$r->prefix2.$r->prefix3] = $id;
        }

        foreach ($rows4 as $r) {
            if (! $r->prefix1 || ! $r->prefix2 || ! $r->prefix3 || ! $r->prefix4) {
                continue;
            }
            $parentId = $idByKey['3|'.$r->prefix1.$r->prefix2.$r->prefix3] ?? null;
            $id = $mst->table('mat_category')->insertGetId([
                'parent_id' => $parentId,
                'level' => 4,
                'category_name' => $r->category4,
                'prefix_char' => $r->prefix4,
                'note' => $r->note ?: null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'id_category');
            $idByKey['4|'.$r->prefix1.$r->prefix2.$r->prefix3.$r->prefix4] = $id;
        }

        foreach ($rows5 as $r) {
            if (! $r->prefix1 || ! $r->prefix2 || ! $r->prefix3 || ! $r->prefix4 || ! $r->prefix5) {
                continue;
            }
            $parentId = $idByKey['4|'.$r->prefix1.$r->prefix2.$r->prefix3.$r->prefix4] ?? null;
            $mst->table('mat_category')->insert([
                'parent_id' => $parentId,
                'level' => 5,
                'category_name' => $r->category5,
                'prefix_char' => $r->prefix5,
                'note' => $r->note ?: null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        if ($db->hasColumn('m_raw_material', 'prefix5_id')) {
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->dropForeign(['prefix5_id']);
            });
        }

        if (! $db->hasTable('doc_rmprefix1')) {
            $db->create('doc_rmprefix1', function (Blueprint $table) {
                $table->increments('idprefix');
                $table->string('category1', 100)->nullable();
                $table->string('prefix1', 1)->nullable()->unique();
                $table->text('note')->nullable();
                $table->enum('stock_class', ['RAW', 'INDIRECT', 'SERVICE'])->default('RAW');
                $table->unsignedBigInteger('default_coa_account_id')->nullable();
                $table->unsignedBigInteger('default_stock_asset_account_id')->nullable();
                $table->unsignedBigInteger('default_gr_ir_account_id')->nullable();
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

            $path = database_path('legacy/doc_rmprefix.sql');

            if (file_exists($path)) {
                $sql = file_get_contents($path);
                $lines = preg_split('/\r\n|\r|\n/', $sql);
                $inserts = array_values(array_filter($lines, fn ($line) => str_starts_with(trim($line), 'INSERT INTO')));

                if (! empty($inserts)) {
                    DB::connection('tm2mst')->unprepared(implode("\n", $inserts));

                    foreach (range(1, 5) as $level) {
                        $table = "doc_rmprefix{$level}";
                        $max = $db->getConnection()->table($table)->max('idprefix');

                        if ($max) {
                            $db->getConnection()->statement("ALTER TABLE `{$table}` AUTO_INCREMENT = ".((int) $max + 1));
                        }
                    }
                }
            }
        }

        if ($db->hasColumn('m_raw_material', 'prefix5_id')) {
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->foreign('prefix5_id')->references('idprefix')->on('doc_rmprefix5')->nullOnDelete();
            });
        }

        $db->dropIfExists('mat_category');
    }
};
