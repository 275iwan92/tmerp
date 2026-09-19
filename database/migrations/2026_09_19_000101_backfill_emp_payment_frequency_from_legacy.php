<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// payment_frequency lahir 2026-09-05 dgn default MONTHLY utk semua baris --
// ETL tm2old->tm2mst tidak pernah mengisinya, padahal tm2old.emp.payroll_cycle
// punya nilai asli (87 dari 115 karyawan aktif = WEEKLY). Kolom ini dipakai
// PayrollEngineService memilih pegawai per period_type & box "Mingguan/Bulanan"
// di Master Employee (permintaan user 2026-09-19). Karyawan tanpa legacy
// (baru dari Excel HR) tetap MONTHLY sampai diisi HR.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2mst')->update(
            "UPDATE tm2mst.emp_employee e JOIN tm2old.emp o ON o.employee_id = e.legacy_employee_id
             SET e.payment_frequency = o.payroll_cycle
             WHERE o.payroll_cycle IN ('WEEKLY', 'MONTHLY') AND e.payment_frequency <> o.payroll_cycle"
        );
    }

    public function down(): void
    {
        // Tidak dikembalikan: nilai sebelumnya cuma default kolom, bukan data.
    }
};
