<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gap analysis 2026-09-03 (permintaan user, bandingkan Costing Hub vs
// screenshot legacy ScriptCase "Kalkulasih HPP") -- ternyata mayoritas
// field sudah ada (bahkan lebih canggih, iqp_costing_price_tier). Cuma 2
// gap nyata: (1) tidak ada biaya "Kupas" sama sekali, (2) QC & Packing
// digabung 1 kolom (i_ongkos_qc_pack_pc), legacy-nya 2 field terpisah +
// dropdown Jenis Packing. i_ongkos_qc_pack_pc TIDAK di-drop/rename (hindari
// migrasi data existing) -- makna kolomnya dipersempit jadi "QC saja" mulai
// sekarang, kolom baru i_ongkos_packing_pc menampung sisi Packing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table): void {
            $table->decimal('i_ongkos_kupas_pc', 18, 4)->default(0)->after('i_ongkos_layer_pc');
            $table->decimal('i_ongkos_packing_pc', 18, 4)->default(0)->after('i_ongkos_qc_pack_pc');
            $table->string('jenis_packing', 50)->nullable()->after('i_ongkos_packing_pc');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table): void {
            $table->dropColumn(['i_ongkos_kupas_pc', 'i_ongkos_packing_pc', 'jenis_packing']);
        });
    }
};
