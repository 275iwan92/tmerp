<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master T6 (Jo 2026-10-19): Purchase Return v2 menyimpan Notes / Chronology di header. Halaman v1 punya kotak itu tetapi tabelnya tidak punya kolomnya, jadi isinya tidak pernah tersimpan.
// Kolom tambahan nullable di tm2pur.prt_return; tidak mengubah data atau aturan yang ada. Jalankan dengan --path di mockup (--env=mockup) dan tmerp.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tm2pur')->hasColumn('prt_return', 'i_notes')) {
            Schema::connection('tm2pur')->table('prt_return', fn (Blueprint $t) => $t->text('i_notes')->nullable()->after('i_reason'));
        }
    }

    public function down(): void
    {
        if (Schema::connection('tm2pur')->hasColumn('prt_return', 'i_notes')) {
            Schema::connection('tm2pur')->table('prt_return', fn (Blueprint $t) => $t->dropColumn('i_notes'));
        }
    }
};
