<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.2a poin 3 --
// upload referensi/preview JPEG (wajib utk preview)/PDF/AI (opsional).
// SENGAJA dibuat tabel BARU, bukan reuse prod_part_artwork (sudah ada,
// tm2mst) -- keduanya beda tujuan: prod_part_artwork = artwork RESMI
// ber-versi+approval (proof_required/is_approved, dipakai Production WO
// Detail), prod_part_attachment = file referensi kerja ProDev (bisa nempel
// ke part ATAU ke layout_group gabungan, tanpa alur approval sendiri --
// approvalnya nempel ke Master Card/prod_mastercard_signoff, bukan per
// file). 2 FK nullable, isi SALAH SATU (part_id utk dieline 1 part, id_layout_group
// utk preview hasil nesting gabungan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_part_attachment', function (Blueprint $table) {
            $table->id('id_attachment');
            $table->foreignId('part_id')->nullable()->constrained('prod_part', 'id_part')->cascadeOnDelete();
            $table->foreignId('id_layout_group')->nullable()->constrained('prod_layout_group', 'id_layout_group')->cascadeOnDelete();
            $table->enum('file_type', ['JPEG', 'PDF', 'AI'])->comment('JPEG wajib ada utk preview visual inline, PDF/AI opsional referensi/download');
            $table->string('file_path', 255);
            $table->string('original_filename', 255)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->string('uploaded_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_part_attachment');
    }
};
