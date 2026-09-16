<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Produk dapat gambar dari Master Card legacy (permintaan user 2026-09-16).
//
// Gambar TIDAK disalin per-produk: 389 file dipakai bersama 483 produk (1 Master
// Card bisa jadi induk banyak item), jadi file disimpan sekali per mastercard_id
// dan prod_product cuma menyimpan path-nya. image_source_card_id dipertahankan
// supaya asal-usul tiap gambar bisa dilacak balik ke kartunya.
//
// brand_name/old_goods_* = klasifikasi sumber yang sebelumnya hilang saat migrasi
// (mst_item punya, prod_product tidak). Disimpan apa adanya supaya pemetaan ke
// category_id bisa diperbaiki belakangan tanpa balik lagi ke tm2old.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_product', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable()->after('description');
            $table->string('image_thumb_path', 255)->nullable()->after('image_path');
            $table->unsignedBigInteger('image_source_card_id')->nullable()->after('image_thumb_path');
            $table->string('brand_name', 50)->nullable()->after('old_item_name');
            $table->string('old_goods_belongto', 50)->nullable()->after('brand_name');
            $table->string('old_goods_purpose', 100)->nullable()->after('old_goods_belongto');

            $table->index('image_source_card_id', 'idx_prod_product_image_card');
            $table->index('old_goods_purpose', 'idx_prod_product_goods_purpose');
        });

        Schema::connection('tm2mst')->table('prod_card_legacy', function (Blueprint $table) {
            $table->string('preview_filename', 250)->nullable()->after('type_joint');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_product', function (Blueprint $table) {
            $table->dropIndex('idx_prod_product_image_card');
            $table->dropIndex('idx_prod_product_goods_purpose');
            $table->dropColumn([
                'image_path', 'image_thumb_path', 'image_source_card_id',
                'brand_name', 'old_goods_belongto', 'old_goods_purpose',
            ]);
        });

        Schema::connection('tm2mst')->table('prod_card_legacy', function (Blueprint $table) {
            $table->dropColumn('preview_filename');
        });
    }
};
