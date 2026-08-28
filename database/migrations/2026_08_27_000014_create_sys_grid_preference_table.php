<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fasilitas grid custom view/sort/group-by (keputusan user 2026-08-27):
// user pilih kolom mana yang tampil (dari sekian banyak yang tersedia, mis.
// 15 kolom, tapi default cuma 5), sort per kolom (toggle asc/desc/none via
// icon di judul kolom), group-by dari kolom yang lagi tampil. Preferensi
// per user PER grid (grid_key = identitas layar, mis. 'master.raw-material'),
// bukan global -- 2 user beda bisa punya susunan kolom beda di grid yang sama.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->create('sys_grid_preference', function (Blueprint $table) {
            $table->id('id_grid_preference');
            $table->string('user_login', 100);
            $table->string('grid_key', 100);
            $table->json('visible_columns');
            $table->string('sort_column', 100)->nullable();
            $table->enum('sort_direction', ['asc', 'desc'])->nullable();
            $table->string('group_by_column', 100)->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_login', 'grid_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->dropIfExists('sys_grid_preference');
    }
};
