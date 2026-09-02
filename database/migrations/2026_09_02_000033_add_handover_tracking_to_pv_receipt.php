<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/BACKLOG.md §7 "Outstanding PV report" -- keputusan desain diambil
// (autonomous, low-risk/reversibel): opsi PALING SEDERHANA dari 3 yang
// diajukan user dulu ("flag simpel per-PV") -- header-level, BUKAN per-line
// (`c_qty_received` per baris) atau dokumen baru "Bukti Serah Terima
// Barang". Alasan: PV di TMERP sudah 1 dokumen = 1 pengiriman fisik dari 1
// supplier ke 1 gudang (PV-02/PV-03 HARD BLOCK), jadi "sudah diserahkan ke
// departemen peminta atau belum" secara alami 1 keputusan per dokumen, sama
// gaya Outstanding PR/PO (report existing) yang juga header-level.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->table('pv_receipt', function (Blueprint $table) {
            $table->boolean('is_handed_over')->default(false)->after('status');
            $table->timestamp('handed_over_at')->nullable()->after('is_handed_over');
            $table->string('handed_over_by', 100)->nullable()->after('handed_over_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->table('pv_receipt', function (Blueprint $table) {
            $table->dropColumn(['is_handed_over', 'handed_over_at', 'handed_over_by']);
        });
    }
};
