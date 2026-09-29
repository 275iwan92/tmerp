<?php

use App\Support\Database\PkSwap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Aturan PK tahap 2 (user 2026-09-30, Opsi A): tabel ber-PK kode / FK induk / gabungan / tanpa PK mendapat
// id BIGINT auto number sebagai PK; kolom PK lama menjadi UNIQUE dan tetap dipakai sebagai rujukan (FK ke kode
// tetap sah), jadi model & layar yang sudah ada tidak berubah. Metode tabel bayangan: lihat App\Support\Database\PkSwap.
// Tabel lama tersimpan sebagai <tabel>__oldpk sampai user menyuruh hapus.
return new class extends Migration
{
    private const TABLES = [
        'tm2mst' => ['cust_category' => 'id_category', 'fin_payment_term' => 'id_payment_term', 'fin_tax_scheme' => 'id_tax_scheme',
            'prod_type' => 'id_type', 'sup_category' => 'id_category', 'emp_bpjs_kesehatan' => 'id_bpjs_kesehatan',
            'emp_bpjs_ketenagakerjaan' => 'id_bpjs_ketenagakerjaan'],
        'tm2sdi' => ['iqp_type' => 'id_type'],
        'tm2sys' => ['sys_app_menu_color' => 'id_menu_color', 'sys_constant_category' => 'id_constant_category', 'sys_languages' => 'id_language',
            'sys_setting' => 'id_setting', 'sys_theme' => 'id_theme', 'sys_chat_participants' => 'id_participant'],
        'tm2inv' => ['rm_stock_d_bak' => 'id_stock_d_bak'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $conn => $tables) {
            $swap = new PkSwap(DB::connection($conn));
            foreach ($tables as $table => $id) {
                $swap->swap($table, $id);
            }
        }
    }

    // Kembali = hapus tabel baru lalu rename <tabel>__oldpk ke nama asli dan pasang FK dari storage/app/pk-swap (manual, sengaja).
    public function down(): void {}
};
