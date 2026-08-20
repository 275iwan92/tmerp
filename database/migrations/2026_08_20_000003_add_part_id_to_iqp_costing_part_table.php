<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Link opsional costing_part -> iqp_part asal, supaya Imposition Engine bisa
// auto-fill i_panjang_plano_cm/i_lebar_plano_cm/i_cut_per_plano saat confirm.
// Nullable — costing_part lama (dibuat freeform lewat "+ Part DC/Sheet") tetap valid.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->unsignedBigInteger('part_id')->nullable()->after('costing_id')->comment('FK -> iqp_part, opsional (freeform kalau NULL)');
            $table->foreign('part_id')->references('id_part')->on('iqp_part')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->dropForeign(['part_id']);
            $table->dropColumn('part_id');
        });
    }
};
