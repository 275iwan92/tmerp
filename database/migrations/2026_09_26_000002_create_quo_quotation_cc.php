<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Quotation: Attn tetap 1 orang (quo_quotation.attn_contact), CC boleh banyak (user 2026-09-26).
// Nama kontak disimpan sebagai snapshot teks, sama seperti attn_contact.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->create('quo_quotation_cc', function (Blueprint $table) {
            $table->id('id_quotation_cc');
            $table->unsignedBigInteger('quotation_id');
            $table->unsignedSmallInteger('line_seq')->default(1);
            $table->string('contact_name', 150);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->unique(['quotation_id', 'contact_name']);
            $table->foreign('quotation_id')->references('id_quotation')->on('quo_quotation')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->dropIfExists('quo_quotation_cc');
    }
};
