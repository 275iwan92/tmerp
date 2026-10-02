<?php

use Illuminate\Database\Migrations\Migration;

// Muat ulang database/data/dictionary_ui_2026_10_02_sweep.json setelah pasangan kelompok berikutnya digabung
// (pemuat yang sama dengan 000300: hanya mengisi terjemahan yang masih kosong).
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/2026_10_02_000300_dictionary_language_sweep.php')->up();
    }

    public function down(): void {}
};
