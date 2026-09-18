<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Legacy hpp_parts.calc_notes ("Catatan Kalkulasi" per part, panel Output program lama).
// Catatan bebas user -- engine TIDAK pernah menimpanya (beda dari description_calc).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->text('calc_notes')->nullable()->after('description_calc');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', fn (Blueprint $table) => $table->dropColumn('calc_notes'));
    }
};
