<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Progress follow-up Sales per quotation (telepon, kunjungan, dst) -- pola
// "activity log" ala CRM (user 2026-09-28). Jumlah telepon/visit = COUNT per
// activity_type, bukan kolom counter, supaya riwayatnya tetap utuh.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->create('quo_activity', function (Blueprint $table) {
            $table->id('id_activity');
            $table->unsignedBigInteger('quotation_id');
            $table->string('activity_type', 20);
            $table->date('activity_date');
            $table->string('contact_person', 150)->nullable();
            $table->string('result', 20)->nullable();
            $table->text('notes')->nullable();
            $table->date('next_action_date')->nullable();
            $table->string('next_action', 255)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->index(['quotation_id', 'activity_date']);
            $table->foreign('quotation_id')->references('id_quotation')->on('quo_quotation')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->dropIfExists('quo_activity');
    }
};
