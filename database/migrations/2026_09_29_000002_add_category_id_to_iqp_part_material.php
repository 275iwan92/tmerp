<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bahan Inquiry New (user 2026-09-29): sales cuma tahu Jenis+Gramatur, tidak
// tahu/berwenang pilih Brand & ukuran plano -- keduanya (bersama rawmat_id
// spesifik) diserahkan ke Purchasing/ProDev belakangan. category_id = FK
// logis lintas-DB (tm2sdi -> tm2mst.mat_category, sama pola dgn rawmat_id)
// ke node terdalam yang dipilih (level 2 Jenis atau level 3 Gramatur/Spec).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part_material', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->nullable()->after('rawmat_id')
                ->comment('LOGICAL -> tm2mst.mat_category.id_category (node terdalam: Jenis atau Gramatur/Spec)');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_part_material', function (Blueprint $table): void {
            $table->dropColumn('category_id');
        });
    }
};
