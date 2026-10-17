<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * REMARK
 * Tujuan       : Melebarkan iqp_part.cfg_ink dari varchar(50) ke varchar(200), sama dengan prod_part.cfg_ink (izin Jo 2026-10-18).
 *                Kartu Power X punya warna khusus "BLACK, DGM 11021 S Champion Red (DIC) / Kopiko Red (Tanindo)" (60 karakter) yang
 *                menggagalkan pembuatan Inquiry dari kartu.
 * Sumber hulu  : prod_master_card.colors_json (daftar warna sisi pertama) -> MasterCardService::makeInquiry.
 * Input        : -
 * Output       : Kolom lebih lebar; data tidak berubah.
 * Dipakai oleh : Inquiry Product (iqp_part), ProDev, Master Card.
 * Status       : Dijalankan di ddt* (mockup) dan tm2* (tmerp); simpan ulang baseline ddt sesudahnya (B.12).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sdi')->statement('ALTER TABLE iqp_part MODIFY cfg_ink VARCHAR(200) NULL');
    }

    public function down(): void
    {
        DB::connection('tm2sdi')->statement('ALTER TABLE iqp_part MODIFY cfg_ink VARCHAR(50) NULL');
    }
};
