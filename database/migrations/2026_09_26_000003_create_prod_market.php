<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Lookup Product Market (user 2026-09-26): "Kategori Produk" di Inquiry New tadinya 8 pilihan tertanam
// di kode. Sekarang master yang didefinisikan officer ProDev: nama pasar -> Tipe Produk + nilai awal
// (bahan, warna, jenjang qty). Ukuran Pasaran (prod_type_size) bisa ditempel ke satu product market.
return new class extends Migration
{
    private const SEED = [
        ['BOX_KOSMETIK', 'Box Kosmetik', 'BOX_WITH_LID'],
        ['BOX_SEPATU', 'Box Sepatu', 'BOX_WITH_LID'],
        ['BOX_KUE', 'Box Kue / Pizza', 'SHALLOW_BOX'],
        ['BOX_MAKANAN', 'Box Makanan (Tuck End)', 'CARD_BOX'],
        ['BOX_FARMASI', 'Kemasan Farmasi / Obat', 'STE_BOX'],
        ['BOX_SOUVENIR', 'Box Souvenir / Gift', 'GIFT_BOX'],
        ['BOX_SILINDER', 'Box Silinder / Tube', 'ROUND_BOX'],
        ['HANG_TAG', 'Hang Tag / Label Produk', 'HANG_TAG'],
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_market', function (Blueprint $table) {
            $table->id('id_market');
            $table->string('market_code', 40)->unique();
            $table->string('market_label', 100);
            $table->string('type_code', 30)->index()->comment('-> prod_type.type_code');
            $table->string('photo_path', 255)->nullable();
            $table->unsignedBigInteger('default_material_id')->nullable()->comment('-> mat_raw.material_id');
            $table->string('default_color', 10)->nullable()->comment('notasi sisiA/sisiB, mis. 4/0');
            $table->string('default_tiers', 100)->nullable()->comment('qty dipisah koma, mis. 1000,3000,5000');
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(999);
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('type_code')->references('type_code')->on('prod_type')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::connection('tm2mst')->table('prod_type_size', function (Blueprint $table) {
            $table->unsignedBigInteger('market_id')->nullable()->after('type_code')->comment('null = ukuran umum Tipe Produk');
            $table->foreign('market_id')->references('id_market')->on('prod_market')->nullOnDelete();
        });

        $now = now();
        DB::connection('tm2mst')->table('prod_market')->insert(array_map(fn ($r, $i) => [
            'market_code' => $r[0], 'market_label' => $r[1], 'type_code' => $r[2], 'sort_order' => ($i + 1) * 10,
            'activate' => true, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system',
        ], self::SEED, array_keys(self::SEED)));
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_type_size', function (Blueprint $table) {
            $table->dropForeign(['market_id']);
            $table->dropColumn('market_id');
        });
        Schema::connection('tm2mst')->dropIfExists('prod_market');
    }
};
