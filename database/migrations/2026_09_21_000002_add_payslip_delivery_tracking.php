<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Slip gaji punya 2 kanal penyerahan yang berjalan berdampingan: cetak (jalur
// sekarang, default menyala untuk SEMUA pegawai) dan WhatsApp (jalur tujuan,
// dinyalakan bertahap per pegawai). Flag = rencana kanal, *_at = bukti kanal
// itu benar-benar sudah dieksekusi -- sengaja dipisah supaya officer bisa lihat
// sisa yang belum diserahkan, bukan cuma niatnya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pyr')->table('pyr_payslip', function (Blueprint $t) {
            $t->boolean('print_flag')->default(true)->after('anomaly_note');
            $t->timestamp('printed_at')->nullable()->after('print_flag');
            $t->boolean('wa_flag')->default(false)->after('printed_at');
            $t->timestamp('wa_sent_at')->nullable()->after('wa_flag');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pyr')->table('pyr_payslip', function (Blueprint $t) {
            $t->dropColumn(['print_flag', 'printed_at', 'wa_flag', 'wa_sent_at']);
        });
    }
};
