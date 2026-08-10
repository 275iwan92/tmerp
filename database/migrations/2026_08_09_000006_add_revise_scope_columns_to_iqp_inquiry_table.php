<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// GAP tertunda (docs/DELTA_TIER_revise_scope_CO.md) — layar REVISE (mockup
// inquiry_revise_v2.html) butuh scope manual (MATERIAL/DIMENSION/ARTWORK/
// COLOR/PROCESS) + alasan, belum ada kolomnya di TIER_3A_INQUIRY.sql. Simpan
// scope sebagai CSV (bukan MySQL SET) — Eloquent tak punya cast SET native,
// CSV lebih portabel dan cukup untuk kebutuhan filter/tampil.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->string('revise_scope', 100)->nullable()->after('revision_no')
                ->comment('CSV subset dari MATERIAL,DIMENSION,ARTWORK,COLOR,PROCESS — NULL untuk NEW/RECALC');
            $table->string('revise_reason', 255)->nullable()->after('revise_scope')
                ->comment('Alasan revisi (mis. tambah logo Halal)');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->dropColumn(['revise_scope', 'revise_reason']);
        });
    }
};
