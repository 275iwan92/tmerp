<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Isi awal fondasi Roster Shift Mingguan (pasangan migration ...000001).
//
// ASUMSI YANG PERLU DIKOREKSI HR NANTI (sengaja ditulis di sini supaya jelas
// mana data riil mana tebakan awal):
//  1. prod_machine TIDAK punya kolom department_id (cuma division_id), jadi
//     pemetaan mesin -> departemen di bawah pakai pencocokan kata kunci nama
//     mesin (PLONG->Plong+DieCut, LAMIN->Laminasi, dst). Kalau ada mesin yang
//     nyasar departemennya, betulkan lewat data emp_work_center -- BUKAN bug
//     kode, memang belum ada sumber kebenarannya di skema lama.
//  2. Skill pegawai di-seed = SEMUA work center di departemennya sendiri
//     (asumsi "mampu di unit sendiri"). Sengaja TIDAK mengarang spesialisasi
//     per orang; HR yang mempersempit belakangan.
//  3. Kuota per shift default 4 (mockup pakai 3-6 tergantung shift).
return new class extends Migration
{
    private const MACHINE_DEPT_KEYWORDS = [
        'PLONG' => 'PLONG-DIECUT',
        'DIECUT' => 'PLONG-DIECUT',
        'LAMIN' => 'LAMINASI',
        'LAM_' => 'LAMINASI',
        'COAT' => 'LAMINASI',
        'UV' => 'LAMINASI',
        'POTONG' => 'POTONG',
        'CUTTING' => 'POTONG',
        'FOLDER' => 'FOLDER-GLUE',
        'LIPAT' => 'FOLDER-GLUE',
        'LILIN' => 'FOLDER-GLUE',
        'DIGITAL' => 'DIGITAL',
        'VERSAFIRE' => 'DIGITAL',
        'HOTPRINT' => 'PLONG-DIECUT',
    ];

    private const SHIFT_SEED = [
        // shift_id => [kode, warna, kuota]
        1 => ['SH1', '#2f6fed', 6],
        2 => ['SH2', '#d08700', 6],
        3 => ['SEC3', '#8b5cf6', 3],
        4 => ['TDP1', '#16a34a', 4],
        5 => ['TDP2', '#0891b2', 4],
        6 => ['IND1', '#db2777', 4],
        7 => ['IND2', '#64748b', 4],
        8 => ['VAL', '#0891b2', 3],
    ];

    private const SCHEDULE_SEED = [
        'Jadwal Produksi Offset' => ['PRESS', 'CETAK', 'PRA-CETAK', 'PLONG-DIECUT', 'LAMINASI', 'LAMINATING', 'POTONG', 'FOLDER-GLUE', 'KUPAS', 'SHRINK', 'MANUAL'],
        'Jadwal Digital & Indostry' => ['DIGITALPROD', 'INDOSTRYPROD', 'DIGITAL'],
        'Jadwal Support (QC - PPIC - Gudang - Ekspedisi)' => ['QC', 'QC-2', 'PPIC', 'GUDANG', 'EKSPEDISI', 'PREPRESS', 'DESIGN'],
    ];

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();
        $by = 'claude_code';

        $deptByCode = $db->table('org_department')->pluck('department_id', 'department_code');
        $deptById = $db->table('org_department')->where('activate', true)->get()->keyBy('department_id');

        // --- 1. Work center dari mesin aktif ---------------------------------
        $fallbackByDivision = [
            1 => $deptByCode['PRESS'] ?? null,
            2 => $deptByCode['DIGITALPROD'] ?? null,
            3 => $deptByCode['INDOSTRYPROD'] ?? null,
        ];

        $rows = [];
        $sort = 0;

        foreach ($db->table('prod_machine')->where('activate', true)->orderBy('machine_id')->get() as $machine) {
            $deptId = null;
            $haystack = strtoupper($machine->machine_code.' '.$machine->machine_name);

            foreach (self::MACHINE_DEPT_KEYWORDS as $needle => $deptCode) {
                if (str_contains($haystack, $needle) && isset($deptByCode[$deptCode])) {
                    $deptId = $deptByCode[$deptCode];
                    break;
                }
            }

            $deptId ??= $fallbackByDivision[$machine->division_id] ?? null;

            if (! $deptId) {
                continue;
            }

            $rows[] = [
                'department_id' => $deptId,
                'machine_id' => $machine->machine_id,
                'work_center_code' => 'WC-'.substr($machine->machine_code, 0, 26),
                'work_center_name' => $machine->machine_name,
                'sort_order' => $sort += 10,
                'activate' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $by,
                'updated_by' => $by,
            ];
        }

        // --- 2. Work center non-mesin (1 default per departemen) -------------
        // Departemen yang punya pegawai tapi tak punya mesin (QC, PPIC, Gudang,
        // Ekspedisi, Admin, Security, dst) tetap harus bisa dijadwalkan --
        // inilah yang dulu memaksa adanya "Grid HR" terpisah tanpa mesin.
        $deptWithMachine = collect($rows)->pluck('department_id')->unique()->all();

        foreach ($deptById as $dept) {
            if (in_array($dept->department_id, $deptWithMachine, true)) {
                continue;
            }

            $rows[] = [
                'department_id' => $dept->department_id,
                'machine_id' => null,
                'work_center_code' => 'WC-'.substr($dept->department_code, 0, 26),
                'work_center_name' => $dept->department_name,
                'sort_order' => $sort += 10,
                'activate' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $by,
                'updated_by' => $by,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            $db->table('emp_work_center')->insertOrIgnore($chunk);
        }

        // --- 3. Kode + warna + kuota shift yang sudah ada --------------------
        foreach (self::SHIFT_SEED as $shiftId => [$code, $color, $cap]) {
            $db->table('emp_shift')->where('shift_id', $shiftId)->update([
                'shift_code' => $code,
                'color_hex' => $color,
                'capacity_per_wc' => $cap,
                'updated_by' => $by,
            ]);
        }

        // Shift lain (kalau ada yang ditambah user di luar 8 baris di atas)
        // tetap dapat kode & warna supaya tidak kosong di papan roster.
        $palette = ['#2f6fed', '#d08700', '#8b5cf6', '#16a34a', '#0891b2', '#db2777', '#64748b'];
        $i = 0;
        foreach ($db->table('emp_shift')->whereNull('shift_code')->get() as $shift) {
            $db->table('emp_shift')->where('shift_id', $shift->shift_id)->update([
                'shift_code' => 'SHF'.$shift->shift_id,
                'color_hex' => $palette[$i++ % count($palette)],
                'updated_by' => $by,
            ]);
        }

        // --- 4. Istirahat dalam menit utk detail shift yang sudah punya jam --
        // Diturunkan dari break_out/break_in yang sudah ada supaya layar baru
        // tidak menampilkan "0 menit" padahal jam istirahatnya sebenarnya ada.
        foreach ($db->table('emp_shift_detail')->whereNotNull('break_out')->whereNotNull('break_in')->get() as $detail) {
            $out = strtotime($detail->break_out);
            $in = strtotime($detail->break_in);
            $minutes = (int) round(($in - $out) / 60);

            if ($minutes > 0 && $minutes <= 240) {
                $db->table('emp_shift_detail')->where('shift_detail_id', $detail->shift_detail_id)->update(['break_minutes' => $minutes]);
            }
        }

        // --- 5. Jadwal awal + cakupan departemen -----------------------------
        foreach (self::SCHEDULE_SEED as $name => $deptCodes) {
            $exists = $db->table('emp_roster_schedule')->where('schedule_name', $name)->value('schedule_id');

            $scheduleId = $exists ?: $db->table('emp_roster_schedule')->insertGetId([
                'schedule_name' => $name,
                'activate' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $by,
                'updated_by' => $by,
            ], 'schedule_id');

            foreach ($deptCodes as $code) {
                if (! isset($deptByCode[$code])) {
                    continue;
                }

                $db->table('emp_roster_schedule_department')->insertOrIgnore([
                    'schedule_id' => $scheduleId,
                    'department_id' => $deptByCode[$code],
                ]);
            }
        }

        // --- 6. Skill pegawai = semua work center di departemennya sendiri ---
        $wcByDept = $db->table('emp_work_center')->get()->groupBy('department_id');

        $employees = $db->table('emp_employee as e')
            ->join('org_position as p', 'p.position_id', '=', 'e.position_id')
            ->where('e.activate', true)
            ->select('e.employee_id', 'p.department_id')
            ->get();

        $skills = [];
        foreach ($employees as $emp) {
            foreach ($wcByDept[$emp->department_id] ?? [] as $wc) {
                $skills[] = [
                    'employee_id' => $emp->employee_id,
                    'work_center_id' => $wc->work_center_id,
                    'activate' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => $by,
                    'updated_by' => $by,
                ];
            }
        }

        foreach (array_chunk($skills, 500) as $chunk) {
            $db->table('emp_employee_work_center_skill')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');

        $db->table('emp_employee_work_center_skill')->where('created_by', 'claude_code')->delete();
        $db->table('emp_roster_schedule_department')->whereIn(
            'schedule_id',
            $db->table('emp_roster_schedule')->whereIn('schedule_name', array_keys(self::SCHEDULE_SEED))->pluck('schedule_id')
        )->delete();
        $db->table('emp_roster_schedule')->whereIn('schedule_name', array_keys(self::SCHEDULE_SEED))->delete();
        $db->table('emp_work_center')->where('created_by', 'claude_code')->delete();
        $db->table('emp_shift')->update(['shift_code' => null, 'color_hex' => null]);
        $db->table('emp_shift_detail')->update(['break_minutes' => null]);
    }
};
