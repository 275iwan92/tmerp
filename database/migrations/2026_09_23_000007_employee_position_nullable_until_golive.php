<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Keputusan user 2026-09-23: selama aplikasi belum dinyatakan selesai, HANYA Nama
// yang wajib di Master Employee. emp_employee.position_id satu-satunya kolom
// NOT NULL tanpa default -> sementara dibuat NULLABLE ("remark not null").
// SAAT GO-LIVE: set HRM_EMPLOYEE_MANDATORY_STRICT = 1 DAN jalankan down()
// migration ini (kembalikan NOT NULL) setelah semua pegawai punya jabatan.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2mst')->statement('ALTER TABLE emp_employee MODIFY position_id BIGINT UNSIGNED NULL');

        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'HRM_EMPLOYEE_MANDATORY_STRICT')->update([
            'description' => '0 = longgar (belum go-live): hanya Nama wajib. 1 = ketat: biodata, jabatan & keluarga wajib. Go-live: set 1 + jalankan down() migration 2026_09_23_000007 (position_id NOT NULL).',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2mst')->statement('ALTER TABLE emp_employee MODIFY position_id BIGINT UNSIGNED NOT NULL');
    }
};
