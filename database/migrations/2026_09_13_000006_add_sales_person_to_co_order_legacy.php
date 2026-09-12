<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Laporan manajemen "Perolehan Omset per Salesperson" (bagian 5 TM OM DAILY
// REPORT) butuh sdi_co.sales_by yang belum ikut termigrasi.
//
// Disimpan sbg TEKS apa adanya, bukan FK ke sales_person: nilainya campur
// aduk di sumber (nama orang "YUNA" vs "YUNA PUJI", nama divisi "TDP",
// nama perusahaan "LANGGENG"/"TELASIH") dan hanya 42% baris yang terisi.
// Memaksakan FK berarti menebak pemetaan -- biarkan apa adanya dulu,
// normalisasi menyusul setelah manajemen menetapkan daftar resminya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('co_order_legacy', function (Blueprint $table) {
            $table->string('sales_person', 100)->nullable()->after('customer_po_no');
            $table->index('sales_person');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('co_order_legacy', function (Blueprint $table) {
            $table->dropIndex(['sales_person']);
            $table->dropColumn('sales_person');
        });
    }
};
