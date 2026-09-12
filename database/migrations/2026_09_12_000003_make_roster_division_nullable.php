<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Roster versi baru menjadwalkan SEMUA departemen (termasuk Security, Staff/
// Admin, Harian Lepas) yang di org_department memang punya division_id NULL --
// sebelumnya roster cuma menyentuh departemen produksi yang pasti berdivisi.
// Daripada mengarang divisi palsu demi memenuhi NOT NULL, kolomnya dibuat
// nullable. Baris roster lama tetap membawa division_id-nya apa adanya.
// doctrine/dbal tidak terpasang -> pakai ALTER TABLE mentah, bukan ->change().
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2mst')->statement('ALTER TABLE emp_shift_roster MODIFY division_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::connection('tm2mst')->statement('ALTER TABLE emp_shift_roster MODIFY division_id BIGINT UNSIGNED NOT NULL');
    }
};
