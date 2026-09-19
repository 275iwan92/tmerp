<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Nama di formasi shift Sep 2026 yang belum ada di master karyawan = karyawan
// BORONGAN produksi TM (keputusan user 2026-09-19): dipanggil saat pekerjaan
// berlebih, berpindah titik kerja sesuai beban (job Manual habis -> FG 3 -> dst).
// Hanya nama panggilan yang diketahui -- nama lengkap/NIK/PIN finger diisi HR.
// ARIF ada 2 orang (di formasi tercatat di FG 3 dan Kupas pada hari yang sama).
return new class extends Migration
{
    // code => [nama, work center yang tercatat di formasi]
    private const WORKERS = [
        'TM-00544' => ['ARIF A', ['WC-FG3']],
        'TM-00545' => ['ARIF B', ['WC-KUPAS']],
        'TM-00546' => ['MUCHLAS', ['WC-SHRINK']],
        'TM-00547' => ['RENDA', ['WC-SHRINK']],
        'TM-00548' => ['ERNA', []],
        'TM-00549' => ['MUJI', []],
        'TM-00550' => ['DEA', []],
        'TM-00551' => ['SITI', []],
        'TM-00552' => ['TYAS', []],
    ];

    // Titik rotasi borongan: Manual -> FG 3 -> FG 4.
    private const ROTATION = ['WC-MANUAL', 'WC-FG3', 'WC-FG4'];

    private const BY = 'hr-borongan-2026-09-19';

    public function up(): void
    {
        DB::connection('tm2mst')->transaction(fn () => $this->seed());
    }

    private function seed(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();
        $stamp = ['created_at' => $now, 'created_by' => self::BY, 'updated_at' => $now, 'updated_by' => self::BY];

        $positionId = $db->table('org_position')->insertGetId([
            'department_id' => 28, 'reports_to_position_id' => 70, 'level_id' => 1,
            'position_code' => 'BORONGAN', 'position_name' => 'BORONGAN',
            'job_description' => 'Karyawan borongan produksi, dipanggil saat pekerjaan berlebih; berpindah titik kerja sesuai beban (Manual, FG 3, FG 4, dst).',
            'legacy_skill_rate' => 0, 'activate' => 1,
        ] + $stamp);

        $centers = $db->table('emp_work_center')->pluck('work_center_id', 'work_center_code');

        foreach (self::WORKERS as $code => [$name, $seen]) {
            if ($db->table('emp_employee')->where('employee_code', $code)->exists()) {
                throw new RuntimeException("Kode {$code} sudah dipakai.");
            }
            $id = $db->table('emp_employee')->insertGetId([
                'employee_code' => $code, 'company_id' => 1, 'position_id' => $positionId,
                'full_name' => $name, 'nickname' => explode(' ', $name)[0],
                'join_date' => '2026-09-07', 'work_status' => 'WORKING', 'work_status_since' => '2026-09-07',
                'payment_frequency' => 'WEEKLY', 'activate' => 1, 'data_origin' => 'LIVE',
            ] + $stamp);

            $db->table('emp_contract')->insert([
                'employee_id' => $id, 'contract_no' => 'CTR-'.$code, 'employment_type' => 'BORONGAN',
                'start_date' => '2026-09-07', 'salary_type' => 'PER_UNIT', 'salary_amount' => 0, // tarif per unit diisi HR
                'is_probation' => 0, 'activate' => 1, 'data_origin' => 'LIVE',
            ] + $stamp);

            foreach (array_unique([...self::ROTATION, ...$seen]) as $wc) {
                $db->table('emp_employee_work_center_skill')->insert([
                    'employee_id' => $id, 'work_center_id' => $centers[$wc], 'activate' => 1, 'data_origin' => 'LIVE',
                ] + $stamp);
            }
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');
        $ids = $db->table('emp_employee')->whereIn('employee_code', array_keys(self::WORKERS))->pluck('employee_id');
        $db->table('emp_shift_roster')->whereIn('employee_id', $ids)->delete();
        $db->table('emp_employee_work_center_skill')->whereIn('employee_id', $ids)->delete();
        $db->table('emp_contract')->whereIn('employee_id', $ids)->delete();
        $db->table('emp_employee')->whereIn('employee_id', $ids)->delete();
        $db->table('org_position')->where('position_code', 'BORONGAN')->delete();
    }
};
