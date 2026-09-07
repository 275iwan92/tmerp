<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Lanjutan migration 000013 -- lembur HARUS berasal dari emp_overtime_request
// APPROVED (Tier 2 §4.4: "bukan dari selisih jam finger murni"), bukan
// otomatis dari finger_out lebih lambat dari shift_end. Durasi diambil apa
// adanya dari selisih jam pulang riil vs shift 15:30 di sheet `TA` (26 &
// 27 Agustus keduanya pulang 18:30 = 180 menit lembur, memenuhi syarat
// Uang Roti >120 menit; 24 & 28 Agustus pulang 16:30 = 60 menit, TIDAK
// memenuhi syarat Uang Roti -- ini yang akan divalidasi cocok/tidak dgn
// kolom "U. ROTI" (count=2) di sheet REKAP ABSEN).
return new class extends Migration
{
    private const OVERTIME_DAYS = [
        '2026-08-24' => 60,
        '2026-08-26' => 180,
        '2026-08-27' => 180,
        '2026-08-28' => 60,
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $employeeIds = $mst->table('emp_employee')->whereIn('employee_code', ['VAL-001', 'VAL-002'])->pluck('employee_id', 'employee_code');

        foreach ($employeeIds as $employeeId) {
            foreach (self::OVERTIME_DAYS as $date => $minutes) {
                $mst->table('emp_overtime_request')->insert([
                    'employee_id' => $employeeId,
                    'overtime_date' => $date,
                    'ot_type' => 'AKHIR',
                    'duration_minutes' => $minutes,
                    'status' => 'APPROVED',
                    'approved_by' => 'claude_code',
                    'approved_at' => now(),
                    'created_at' => now(),
                    'created_by' => 'claude_code',
                ]);
            }
        }
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $employeeIds = $mst->table('emp_employee')->whereIn('employee_code', ['VAL-001', 'VAL-002'])->pluck('employee_id');
        $mst->table('emp_overtime_request')->whereIn('employee_id', $employeeIds)->whereIn('overtime_date', array_keys(self::OVERTIME_DAYS))->delete();
    }
};
