<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// production_scheduling_tier4.md §6 (CATATAN VALIDASI SILANG, "wajib"): dropdown
// pemilihan operator di Scheduling Board hanya boleh tampilkan orang yang
// ter-roster di mesin & tanggal itu (emp_shift_roster) -- tabelnya sudah ada
// dari HRM tapi belum pernah diisi sama sekali (0 baris apa pun tanggalnya),
// jadi filter itu tak bisa diverifikasi. Isi roster dummy utk tanggal anchor
// papan (2026-08-28, sama dgn 2026_08_28_000002_seed_prd_scheduling_board_dummy)
// memakai 5 karyawan yang sama supaya crew yang sudah tampil di papan memang
// konsisten dgn siapa yang "dijadwalkan masuk" hari itu (Keep dummy data).
return new class extends Migration
{
    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $offsetDivisionId = $mst->table('org_division')->where('division_code', 'OFFSET')->value('division_id');
        $shiftId = $mst->table('emp_shift')->orderBy('shift_id')->value('shift_id');

        $cetak1 = $mst->table('prod_machine')->where('machine_code', 'CETAK-01')->value('machine_id');
        $laminasi = $mst->table('prod_machine')->where('machine_code', 'LAM-01')->value('machine_id');

        $byName = fn (string $name) => $mst->table('emp_employee')->where('full_name', $name)->value('employee_id');

        // [employee_id, machine_id] -- null machine_id = roster umum (manual job, bukan mesin tertentu)
        $rows = [
            [$byName('Budi Santoso'), $cetak1],
            [$byName('JOKO'), $cetak1],
            [$byName('RUDI'), $laminasi],
            [$byName('SITI FATIMAH'), null],
            [$byName('RIZTA HARIF SHABRINA'), null],
        ];

        foreach ($rows as [$employeeId, $machineId]) {
            if (! $employeeId) {
                continue;
            }

            $mst->table('emp_shift_roster')->updateOrInsert(
                ['employee_id' => $employeeId, 'roster_date' => '2026-08-28'],
                [
                    'division_id' => $offsetDivisionId,
                    'machine_id' => $machineId,
                    'shift_id' => $shiftId,
                    'assigned_by_role' => 'HR',
                    'is_overtime' => false,
                    'created_by' => 'system',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('emp_shift_roster')
            ->where('roster_date', '2026-08-28')
            ->where('assigned_by_role', 'HR')
            ->where('created_by', 'system')
            ->delete();
    }
};
