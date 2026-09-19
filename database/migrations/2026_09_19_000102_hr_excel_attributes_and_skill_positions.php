<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Adopsi kolom Excel HR "daftar karyawan - database.xlsx" + file gaji mingguan
// (permintaan user 2026-09-19, poin 1-6):
// - BERKAS DATA DIRI (ADA/TIDAK ADA) & DEPOSIT (ADA/NO/PROSES/BELUM) belum
//   punya tempat -> kolom baru di emp_employee.
// - BPJS (2P/3P/BATCH 3/BELUM/NO) + KET: 2P/3P = jumlah program BPJS
//   Ketenagakerjaan (JKK+JKM / +JHT); KET = kategori (STAFF/OPERATOR/HIGH RISK/
//   UMUR) atau alasan (TIDAK BERKENAN, KTP BERMASALAH, ...) -> kolom status &
//   catatan kepesertaan di emp_bpjs_ketenagakerjaan.
// - Jabatan keahlian legacy (org_position 10-58, = sheet REFF_KEAHLIAN file gaji)
//   selama ini menggantung di dept "Belum Dipetakan" tanpa atasan -> dipasang ke
//   departemen & SPV-nya supaya karyawan mingguan bisa dipetakan ke sana dan
//   Struktur Organisasi menampilkan operator di bawah SPV masing-masing.
// - Shift REG + work center yang dipakai formasi shift (DIECUT, FG 3, FG 4,
//   BLANKING) belum ada di master.
return new class extends Migration
{
    // position_id => [department_id, reports_to_position_id]
    private const SKILL_PLACEMENT = [
        10 => [16, 95], 11 => [16, 95], 12 => [16, 95], 13 => [16, 95],
        14 => [17, 102],
        16 => [18, 103], 17 => [18, 103], 18 => [18, 103],
        19 => [10, 104], 20 => [10, 104], 21 => [10, 104], 22 => [10, 104], 23 => [10, 104], 24 => [10, 104],
        25 => [15, 105], 26 => [15, 105],
        27 => [28, 70],
        28 => [14, 105], 29 => [14, 105], 30 => [14, 105],
        31 => [13, 106], 32 => [13, 106], 33 => [13, 106], 34 => [13, 106], 35 => [13, 106], 36 => [13, 106],
        37 => [19, 107], 38 => [19, 107], 39 => [19, 107], 40 => [19, 107], 41 => [19, 107], 42 => [19, 107],
        43 => [22, 107], 44 => [20, 107], 45 => [20, 107],
        46 => [21, 106], 47 => [21, 106],
        48 => [23, 108], 49 => [23, 108], 50 => [23, 108],
        51 => [24, 92], 52 => [25, 92], 53 => [25, 92], 54 => [25, 92],
        55 => [4, 71], 56 => [4, 71], 57 => [4, 71], 58 => [4, 71],
    ];

    private const NEW_WORK_CENTERS = [
        ['WC-DIECUT', 'DIECUT', 13],
        ['WC-FG3', 'FG 3', 19],
        ['WC-FG4', 'FG 4', 19],
        ['WC-BLANKING', 'BLANKING', 21],
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $t) {
            $t->boolean('personal_file_complete')->nullable()->after('payment_frequency')->comment('Berkas data diri lengkap (Excel HR: ADA/TIDAK ADA)');
            $t->string('deposit_status', 10)->nullable()->after('personal_file_complete')->comment('ADA | TIDAK | PROSES | BELUM (Excel HR kolom DEPOSIT)');
        });

        Schema::connection('tm2mst')->table('emp_bpjs_ketenagakerjaan', function (Blueprint $t) {
            $t->string('enrollment_status', 15)->nullable()->after('program_jkp')->comment('TERDAFTAR | BELUM | ANTRE | TIDAK_IKUT');
            $t->string('registration_batch', 20)->nullable()->after('enrollment_status');
            $t->string('bpjs_category', 20)->nullable()->after('registration_batch')->comment('STAFF | OPERATOR | HIGH RISK | UMUR (Excel HR kolom KET)');
            $t->string('enrollment_note', 150)->nullable()->after('bpjs_category');
        });

        $db = DB::connection('tm2mst');
        $now = now();
        $by = 'hr-excel-2026-09-19';

        foreach (self::SKILL_PLACEMENT as $positionId => [$deptId, $reportsTo]) {
            $db->table('org_position')->where('position_id', $positionId)
                ->update(['department_id' => $deptId, 'reports_to_position_id' => $reportsTo, 'updated_at' => $now, 'updated_by' => $by]);
        }

        // Jabatan "UMUM" di file gaji (tj. keahlian 0) tidak ada di REFF_KEAHLIAN.
        $db->table('org_position')->insert([
            'department_id' => 26, 'reports_to_position_id' => 67, 'level_id' => 1,
            'position_code' => 'UMUM', 'position_name' => 'UMUM', 'legacy_skill_rate' => 0,
            'activate' => 1, 'created_at' => $now, 'created_by' => $by, 'updated_at' => $now, 'updated_by' => $by,
        ]);

        $shiftId = $db->table('emp_shift')->insertGetId([
            'department_id' => 1, 'shift_code' => 'REG', 'shift_name' => 'Reguler 07:30-16:30',
            'color_hex' => '#64748b', 'capacity_per_wc' => 10,
            'description' => 'Jam kerja normatif file gaji mingguan HR: Senin-Jumat 07:30-16:30, Sabtu 07:30-15:30',
            'activate' => 1, 'created_at' => $now, 'created_by' => $by, 'updated_at' => $now, 'updated_by' => $by,
        ]);
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day) {
            $db->table('emp_shift_detail')->insert([
                'shift_id' => $shiftId, 'day_of_week' => $day, 'start_time' => '07:30:00',
                'end_time' => $day === 'Saturday' ? '15:30:00' : '16:30:00',
                'created_at' => $now, 'created_by' => $by, 'updated_at' => $now, 'updated_by' => $by,
            ]);
        }

        $sort = (int) $db->table('emp_work_center')->max('sort_order');
        foreach (self::NEW_WORK_CENTERS as [$code, $name, $deptId]) {
            $db->table('emp_work_center')->insert([
                'department_id' => $deptId, 'work_center_code' => $code, 'work_center_name' => $name,
                'sort_order' => ++$sort, 'activate' => 1,
                'created_at' => $now, 'created_by' => $by, 'updated_at' => $now, 'updated_by' => $by,
            ]);
        }

        // Deliveryman ada di formasi produksi -> dept Delivery ikut Jadwal Produksi Offset.
        if (! $db->table('emp_roster_schedule_department')->where(['schedule_id' => 1, 'department_id' => 24])->exists()) {
            $db->table('emp_roster_schedule_department')->insert(['schedule_id' => 1, 'department_id' => 24]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');
        $db->table('emp_roster_schedule_department')->where(['schedule_id' => 1, 'department_id' => 24])->delete();
        $db->table('emp_work_center')->whereIn('work_center_code', array_column(self::NEW_WORK_CENTERS, 0))->delete();
        $shiftId = $db->table('emp_shift')->where('shift_code', 'REG')->value('shift_id');
        if ($shiftId) {
            $db->table('emp_shift_detail')->where('shift_id', $shiftId)->delete();
            $db->table('emp_shift')->where('shift_id', $shiftId)->delete();
        }
        $db->table('org_position')->where('position_code', 'UMUM')->delete();
        $db->table('org_position')->whereIn('position_id', array_keys(self::SKILL_PLACEMENT))
            ->update(['department_id' => 8, 'reports_to_position_id' => null]);

        Schema::connection('tm2mst')->table('emp_bpjs_ketenagakerjaan', function (Blueprint $t) {
            $t->dropColumn(['enrollment_status', 'registration_batch', 'bpjs_category', 'enrollment_note']);
        });
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $t) {
            $t->dropColumn(['personal_file_complete', 'deposit_status']);
        });
    }
};
