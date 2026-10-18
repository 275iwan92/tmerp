<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master T5c (Jo 2026-10-19): Return from Production v2 menyimpan keterangan per baris (wajib untuk SCRAP); halaman v1 hanya memeriksanya lalu membuangnya.
// Kolom tambahan nullable di baris dokumen stok RM dan SFG (tm2inv); tidak mengubah data atau aturan yang ada. Jalankan dengan --path di mockup (--env=mockup) dan tmerp.
return new class extends Migration
{
    private const TABLES = ['rm_stock_d', 'sfg_stock_d'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::connection('tm2inv')->hasColumn($table, 'line_note')) {
                Schema::connection('tm2inv')->table($table, fn (Blueprint $t) => $t->string('line_note', 255)->nullable()->after('return_condition'));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::connection('tm2inv')->hasColumn($table, 'line_note')) {
                Schema::connection('tm2inv')->table($table, fn (Blueprint $t) => $t->dropColumn('line_note'));
            }
        }
    }
};
