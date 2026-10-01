<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// iqp_revise_change.impact varchar(30) lebih pendek dari teks InquiryDerivedWriter::IMPACT['DIMENSION']
// ("ProDev (imposisi, pisau) · ProCost" = 34 huruf) -> Simpan Revisi dengan scope Dimensi selalu gagal (SQL 1406).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_revise_change', fn (Blueprint $t) => $t->string('impact', 100)->nullable()->change());
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_revise_change', fn (Blueprint $t) => $t->string('impact', 30)->nullable()->change());
    }
};
