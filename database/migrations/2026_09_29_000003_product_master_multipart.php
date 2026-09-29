<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Master Product = data pokok hasil Quotation WIN (pedoman docs/flow_product_master.md, user 2026-09-29).
// 1. Product bercabang: Revisi = product turunan (parent_product_id), bukan menimpa.
// 2. Part bertingkat: rakitan (ASSEMBLY) menggabungkan part lain dengan sambungan mat_join_type
//    (GLUE_SINGLE bisa dibentangkan / GLUE_DOUBLE tidak), tanpa batas tingkat. Sama di iqp_part supaya
//    struktur lahir dari Inquiry lalu tersalin utuh ke master.
// 3. Part master menyimpan dieline & bentangan; bahan (prod_part_material, sudah diisi ProDev) + Jenis/Gramatur;
//    artwork luar/dalam di prod_part_artwork (berversi). Perbedaan Revisi dicatat per field (iqp_revise_change).
// 4. iqp_inquiry.parent_product_id punya 2 arti (inquiry induk REVISE/RECALC vs prod_product dari
//    Kirim ke ProDev) -> dipecah: parent_inquiry_id + product_id.
return new class extends Migration
{
    private function origin(Blueprint $t): void
    {
        $t->string('data_origin', 10)->default('LIVE');
        $t->boolean('data_ready')->default(true);
        $t->boolean('data_origin_manual')->default(false);
    }

    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_product', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_product_id')->nullable()->after('id_product')->comment('Induk bila product ini hasil Revisi (cabang)');
            $t->unsignedSmallInteger('revision_no')->default(0)->after('parent_product_id');
            $t->unsignedBigInteger('source_inquiry_id')->nullable()->after('revision_no')->comment('LOGICAL -> tm2sdi.iqp_inquiry (asal data pokok, diisi saat Quotation WIN)');
            $t->foreign('parent_product_id')->references('id_product')->on('prod_product')->nullOnDelete();
        });

        Schema::connection('tm2mst')->table('prod_part', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_part_id')->nullable()->after('product_id')->comment('Rakitan induk; null = langsung di bawah product');
            $t->string('part_kind', 10)->default('COMPONENT')->after('parent_part_id')->comment('COMPONENT | ASSEMBLY');
            $t->unsignedBigInteger('join_type_id')->nullable()->after('part_kind')->comment('Rakitan: sambungan (mat_join_type)');
            $t->decimal('qty_in_parent', 10, 4)->default(1)->after('join_type_id');
            $t->json('dieline_spec')->nullable()->after('dieline_ref')->comment('Snapshot DSL prod_type + dims (sama dgn iqp_part)');
            $t->decimal('flat_w', 20, 4)->nullable()->after('dieline_spec');
            $t->decimal('flat_h', 20, 4)->nullable()->after('flat_w');
            $t->decimal('flat_area', 20, 4)->nullable()->after('flat_h');
            $t->unsignedBigInteger('source_iqp_part_id')->nullable()->after('flat_area')->comment('LOGICAL -> tm2sdi.iqp_part');
            $t->foreign('parent_part_id')->references('id_part')->on('prod_part')->nullOnDelete();
            $t->foreign('join_type_id')->references('id_join_type')->on('mat_join_type')->nullOnDelete();
        });

        // Artwork master = prod_part_artwork (berversi + approval, dasar ACC Digital); tambah sisi luar/dalam.
        Schema::connection('tm2mst')->table('prod_part_artwork', function (Blueprint $t) {
            $t->string('side', 5)->default('OUTER')->after('version_no')->comment('OUTER | INNER (artwork cetak 2 sisi)');
            $t->string('original_filename', 255)->nullable()->after('artwork_thumb');
        });

        // Bahan master (sudah diisi ProDev/SpecEngine): + Jenis/Gramatur pilihan Sales & peran baris.
        Schema::connection('tm2mst')->table('prod_part_material', function (Blueprint $t) {
            $t->unsignedInteger('category_id')->nullable()->after('material_id')->comment('Jenis/Gramatur (mat_category)');
            $t->string('role', 12)->default('SUBSTRATE')->after('layer_seq')->comment('SUBSTRATE (bahan lapis) | CONSUMABLE (lem, packing)');
            $t->foreign('category_id')->references('id_category')->on('mat_category')->nullOnDelete();
        });

        // Perbedaan REVISI (sebelum -> sesudah) per field: dibaca ProDev & ProCost supaya tahu apa yang diulang.
        Schema::connection('tm2sdi')->create('iqp_revise_change', function (Blueprint $t) {
            $t->id('id_change');
            $t->unsignedBigInteger('inquiry_id')->comment('Inquiry REVISE');
            $t->unsignedBigInteger('part_id')->nullable()->comment('iqp_part milik REVISE; null = level dokumen');
            $t->string('scope', 12)->comment('ARTWORK | COLOR | DIMENSION | MATERIAL | PROCESS | JOIN');
            $t->string('field_label', 100);
            $t->string('old_value', 500)->nullable();
            $t->string('new_value', 500)->nullable();
            $t->string('impact', 30)->nullable()->comment('PRODEV | PROCOST | PRODEV+PROCOST | PROOF');
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $this->origin($t);
            $t->foreign('inquiry_id')->references('id_inquiry')->on('iqp_inquiry')->cascadeOnDelete();
            $t->foreign('part_id')->references('id_part')->on('iqp_part')->cascadeOnDelete();
        });

        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_part_id')->nullable()->after('inquiry_id')->comment('Rakitan induk (iqp_part)');
            $t->string('part_kind', 10)->default('COMPONENT')->after('parent_part_id')->comment('COMPONENT | ASSEMBLY');
            $t->unsignedBigInteger('join_type_id')->nullable()->after('part_kind')->comment('LOGICAL -> tm2mst.mat_join_type');
            $t->decimal('qty_in_parent', 10, 4)->default(1)->after('join_type_id');
            $t->foreign('parent_part_id')->references('id_part')->on('iqp_part')->nullOnDelete();
        });

        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_inquiry_id')->nullable()->after('parent_product_id')->comment('Inquiry asal REVISE/RECALC');
            $t->unsignedBigInteger('product_id')->nullable()->after('parent_inquiry_id')->comment('LOGICAL -> tm2mst.prod_product (draft sejak Kirim ke ProDev, resmi saat WIN)');
            $t->index('parent_inquiry_id');
            $t->index('product_id');
        });
        DB::connection('tm2sdi')->statement("UPDATE iqp_inquiry SET product_id = parent_product_id WHERE inquiry_type = 'NEW'");
        DB::connection('tm2sdi')->statement("UPDATE iqp_inquiry SET parent_inquiry_id = parent_product_id WHERE inquiry_type IN ('REVISE','RECALC')");
        Schema::connection('tm2sdi')->table('iqp_inquiry', fn (Blueprint $t) => $t->dropColumn('parent_product_id'));
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_product_id')->nullable()->after('parent_inquiry_id');
        });
        DB::connection('tm2sdi')->statement('UPDATE iqp_inquiry SET parent_product_id = COALESCE(parent_inquiry_id, product_id)');
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $t) {
            $t->dropIndex(['parent_inquiry_id']);
            $t->dropIndex(['product_id']);
            $t->dropColumn(['parent_inquiry_id', 'product_id']);
        });

        Schema::connection('tm2sdi')->table('iqp_part', function (Blueprint $t) {
            $t->dropForeign(['parent_part_id']);
            $t->dropColumn(['parent_part_id', 'part_kind', 'join_type_id', 'qty_in_parent']);
        });

        Schema::connection('tm2sdi')->dropIfExists('iqp_revise_change');
        Schema::connection('tm2mst')->table('prod_part_material', function (Blueprint $t) {
            $t->dropForeign(['category_id']);
            $t->dropColumn(['category_id', 'role']);
        });
        Schema::connection('tm2mst')->table('prod_part_artwork', fn (Blueprint $t) => $t->dropColumn(['side', 'original_filename']));
        Schema::connection('tm2mst')->table('prod_part', function (Blueprint $t) {
            $t->dropForeign(['parent_part_id']);
            $t->dropForeign(['join_type_id']);
            $t->dropColumn(['parent_part_id', 'part_kind', 'join_type_id', 'qty_in_parent', 'dieline_spec', 'flat_w', 'flat_h', 'flat_area', 'source_iqp_part_id']);
        });
        Schema::connection('tm2mst')->table('prod_product', function (Blueprint $t) {
            $t->dropForeign(['parent_product_id']);
            $t->dropColumn(['parent_product_id', 'revision_no', 'source_inquiry_id']);
        });
    }
};
