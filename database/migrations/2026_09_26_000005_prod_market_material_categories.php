<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bahan awal Product Market = KATEGORI bahan baku (rantai 5 tingkat mat_category), bukan item (user 2026-09-26):
// #1 selalu Paper, #2 selalu Sheet/SF. Item persis dipilih nanti di Inquiry/ProDev.
// mat_category.id_category = INT unsigned (bukan bigint) -> kolom FK harus sama tipenya.
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2mst');

        // Percobaan pertama sempat menambah kolom bertipe salah sebelum FK gagal (DDL MySQL tak ikut rollback).
        foreach (['paper_category_id', 'sheet_category_id'] as $col) {
            if ($s->hasColumn('prod_market', $col)) {
                $s->table('prod_market', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }

        $s->table('prod_market', function (Blueprint $table) use ($s) {
            $table->unsignedInteger('paper_category_id')->nullable()->after('type_code')->comment('#1 Paper: kategori terdalam yang dipilih');
            $table->unsignedInteger('sheet_category_id')->nullable()->after('paper_category_id')->comment('#2 Sheet/SF: kategori terdalam yang dipilih');
            $table->foreign('paper_category_id')->references('id_category')->on('mat_category')->nullOnDelete();
            $table->foreign('sheet_category_id')->references('id_category')->on('mat_category')->nullOnDelete();
            if ($s->hasColumn('prod_market', 'default_material_id')) {
                $table->dropColumn('default_material_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_market', function (Blueprint $table) {
            $table->dropForeign(['paper_category_id']);
            $table->dropForeign(['sheet_category_id']);
            $table->dropColumn(['paper_category_id', 'sheet_category_id']);
            $table->unsignedBigInteger('default_material_id')->nullable()->after('photo_path');
        });
    }
};
