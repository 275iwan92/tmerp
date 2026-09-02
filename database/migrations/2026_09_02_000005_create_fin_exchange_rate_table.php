<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel kurs (keputusan user 2026-09-02, kebutuhan Finance/Accounting) --
// fin_currency SUDAH ada tapi TIDAK punya kolom rate sama sekali (dicek
// 2026-09-02, bukan asumsi). Pola versioned SAMA PERSIS dgn rate coating/film
// (CLAUDE.md §4): 1 baris activate=1 per currency = kurs berlaku, effective_date
// utk riwayat. rate_to_base = nilai 1 unit currency_id dalam mata uang basis
// aplikasi (IDR, TIDAK disimpan sbg FK terpisah -- basis tunggal cukup utk
// kebutuhan saat ini, ganti currency basis butuh keputusan terpisah).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('fin_exchange_rate', function (Blueprint $table) {
            $table->id('id_exchange_rate');
            $table->unsignedInteger('currency_id');
            $table->decimal('rate_to_base', 20, 6)->comment('1 unit currency_id = sekian IDR');
            $table->date('effective_date')->nullable();
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('currency_id')->references('id_currency')->on('fin_currency')->cascadeOnDelete();
            $table->index(['currency_id', 'activate']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('fin_exchange_rate');
    }
};
