<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Layar Jurnal Kas & Bank (BKM/BKK/BBM/BBK) -- mockup HTML user 2026-08-22
// (gemini-code-1787379102109.html). fin_voucher/fin_voucher_line sudah ada &
// dipakai DP Invoice, tapi cuma utk posting OTOMATIS (status langsung POSTED,
// tak pernah DRAFT). Layar ini yang PERTAMA butuh entri MANUAL dgn draft dulu
// -- fin_voucher.status sudah ENUM(DRAFT,POSTED,CANCELLED) dari awal (blm ada
// yg pernah pakai DRAFT-nya), jadi tinggal dipakai apa adanya.
//
// Kolom baru: `voucher_type` (BKM/BKK/BBM/BBK -- vokasi kas/bank, JANGAN
// disamakan dgn `doc_type` yang artinya NEW/RECALC/REVISE, sumbu berbeda),
// `main_account_id` (kemudahan tampilan grid, baris tetap sumber kebenaran),
// `partner_name` (mitra/penerima -- teks bebas, tidak selalu customer/supplier
// terdaftar, mockup sendiri pakai <input type=text> bukan picker), 4 kolom
// perpajakan (PPN/PPh/e-Faktur/Bukti Potong -- tangkap datanya saja, TIDAK ada
// validasi/generate e-Faktur otomatis, itu di luar cakupan permintaan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->table('fin_voucher', function (Blueprint $table) {
            $table->string('voucher_type', 10)->nullable()->after('journal_id')->comment('BKM|BKK|BBM|BBK');
            $table->unsignedBigInteger('main_account_id')->nullable()->after('voucher_type')->comment('LOGICAL -> fin_account, akun kas/bank utama voucher ini');
            $table->string('partner_name', 150)->nullable()->after('reference_no');
            $table->string('ppn_type', 20)->nullable()->comment('NONE|MASUKAN_11|KELUARAN_11');
            $table->string('efaktur_no', 50)->nullable();
            $table->string('pph_type', 20)->nullable()->comment('NONE|PPH23_2|PPH4_2_10|PPH21');
            $table->string('bupot_no', 50)->nullable();

            $table->index('voucher_type', 'idx_vch_type');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->table('fin_voucher', function (Blueprint $table) {
            $table->dropIndex('idx_vch_type');
            $table->dropColumn(['voucher_type', 'main_account_id', 'partner_name', 'ppn_type', 'efaktur_no', 'pph_type', 'bupot_no']);
        });
    }
};
