<?php

use App\Models\Pyr\PyrEmployeeSalary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Data uji VALIDASI (permintaan user 2026-09-07: "baca folder attachment
// buat simulasi data ... migrasi dan proses sebagai ujud simulasi dan
// pencocokan hasil datanya") -- 2 nama & jadwal jam masuk/pulang di bawah
// ini DIAMBIL APA ADANYA dari sheet `TA` (presensi harian mentah) file
// riil `docs/attachments/8. GAJI KARYAWAN PERIODE 22 - 28 AGUSTUS 2026
// WEEKLY 1657.xlsb`, dibuka via Python pyxlsb (PhpSpreadsheet tak punya
// reader .xlsb). BUKAN pegawai aktif sungguhan di sistem produksi manapun
// -- device_pin (DP-354/DP-421) dikarang mengikuti pola device_id asli di
// sheet yg sama (354/421), gaji pokok DIESTIMASI (bukan angka rahasia
// asli HR, formula rate asli mereka berbasis faktor-jabatan yg tidak
// terbaca dari file export -- lihat catatan di LOG_JOB_DONE.md).
return new class extends Migration
{
    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $shiftId = $mst->table('emp_shift')->insertGetId([
            'department_id' => 1,
            'shift_name' => 'Shift Validasi 07:30-15:30',
            'description' => 'Dipakai khusus utk data validasi payroll engine (sesuai jam kerja riil sampel Excel 22-28 Agu 2026)',
            'activate' => true,
            'created_by' => 'claude_code',
        ]);

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($days as $day) {
            $mst->table('emp_shift_detail')->insert([
                'shift_id' => $shiftId,
                'day_of_week' => $day,
                'start_time' => '07:30:00',
                'end_time' => '15:30:00',
                'created_by' => 'claude_code',
            ]);
        }

        $employees = [
            ['code' => 'VAL-001', 'pin' => 'DP-354', 'name' => "MAHMUD SUPA'AT"],
            ['code' => 'VAL-002', 'pin' => 'DP-421', 'name' => 'HERU KURNIAWAN'],
        ];

        foreach ($employees as $emp) {
            $employeeId = $mst->table('emp_employee')->insertGetId([
                'employee_code' => $emp['code'],
                'device_pin' => $emp['pin'],
                'company_id' => 1,
                'position_id' => 1,
                'full_name' => $emp['name'],
                'gender' => 'L',
                'join_date' => '2025-01-01',
                'work_status' => 'WORKING',
                'default_shift_id' => $shiftId,
                'payment_frequency' => 'WEEKLY',
                'activate' => true,
                'created_by' => 'claude_code',
            ]);

            PyrEmployeeSalary::create([
                'employee_id' => $employeeId,
                'basic_salary_monthly' => 3200000,
                'tj_keahlian_monthly' => 200000,
                'tj_loyalitas_monthly' => 0,
                'tj_prestasi_monthly' => 0,
                'bonus_pagi_amount' => 0,
                'bonus_hadir_amount' => 0,
                'bpjs_active' => false,
                'pph21_active' => false,
                'effective_from' => '2026-01-01',
                'activate' => true,
                'created_by' => 'claude_code',
            ]);
        }
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $employeeIds = $mst->table('emp_employee')->whereIn('employee_code', ['VAL-001', 'VAL-002'])->pluck('employee_id');

        DB::connection('tm2pyr')->table('pyr_employee_salary')->whereIn('employee_id', $employeeIds)->delete();
        $mst->table('emp_employee')->whereIn('employee_id', $employeeIds)->delete();

        $shiftId = $mst->table('emp_shift')->where('shift_name', 'Shift Validasi 07:30-15:30')->value('shift_id');
        if ($shiftId) {
            $mst->table('emp_shift_detail')->where('shift_id', $shiftId)->delete();
            $mst->table('emp_shift')->where('shift_id', $shiftId)->delete();
        }
    }
};
