<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// quo_quotation — dicadangkan di TIER_3A_PREFIX_LIST.md, dan iqp_inquiry.quotation_id
// sudah menunggu FK ini sejak awal ("modul belum digarap"). Header tipis: nomor resmi
// QUO+YY+NNNNN dibuat sekali saat quotation pertama diterbitkan (costing harus sudah
// published_flag=1 -- CLAUDE.md §4, jangan buang nomor utk quotation yang belum berharga),
// auto-create on first visit spt pola IqpCosting::firstOrCreate().
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->create('quo_quotation', function (Blueprint $table) {
            $table->id('id_quotation');
            $table->string('quotation_no', 20)->unique();
            $table->unsignedBigInteger('inquiry_id')->unique()->comment('1 quotation per inquiry (revisi lewat revision_no, bukan record baru)');
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->date('issued_date');
            $table->date('valid_until');
            $table->string('issued_by', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('inquiry_id')->references('id_inquiry')->on('iqp_inquiry')->cascadeOnDelete();
        });

        // iqp_inquiry.quotation_id sudah ada dari DDL awal ("FK PENDING") -- baru bisa
        // dipasang fisik sekarang karena quo_quotation baru saja dibuat di atas.
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->foreign('quotation_id')->references('id_quotation')->on('quo_quotation')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_inquiry', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
        });

        Schema::connection('tm2sdi')->dropIfExists('quo_quotation');
    }
};
