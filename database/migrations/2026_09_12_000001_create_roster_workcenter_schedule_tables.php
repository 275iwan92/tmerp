<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fondasi Roster Shift Mingguan versi mockup_roster_shift.html (keputusan user
// 2026-09-12 "mockup untuk jadwal roster HARUS BISA" + "terhubung dengan
// database").
//
// ATURAN KOMPATIBILITAS (dipegang ketat): TIDAK ada kolom lama yang dihapus /
// di-rename / dipindah arti. Tiga pemakai nyata emp_shift_roster tetap jalan
// apa adanya:
//   - AttendanceCalculationService::resolveShiftDetail() -> cuma baca
//     employee_id + roster_date + shift_id.
//   - production/scheduling-board.blade.php -> cuma baca machine_id IS NULL
//     (kolam pegawai non-mesin utk crew assignment harian).
//   - self-service/index.blade.php (tukar shift) -> akses per roster_id + relasi.
// Semua kolom baru di bawah NULLABLE supaya baris roster lama tetap valid.
//
// doctrine/dbal TIDAK terpasang di project ini -> perubahan nullability kolom
// lama WAJIB pakai DB::statement('ALTER TABLE ... MODIFY ...'), bukan
// Blueprint ->change().
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        // Generalisasi "Work Center" mockup: unit kerja tempat orang ditempatkan.
        // Utk departemen Offset 1 baris = 1 prod_machine yang sudah ada (machine_id
        // diisi, TIDAK menduplikasi data mesin). Utk departemen non-mesin (QC,
        // PPIC, Gudang, Maintenance) baris berdiri sendiri (machine_id NULL) --
        // sebelumnya departemen seperti ini sama sekali tidak punya unit kerja
        // di sistem, itu sebabnya papan lama memisahkan "Papan PPIC" vs "Grid HR".
        $mst->create('emp_work_center', function (Blueprint $table) {
            $table->bigIncrements('work_center_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->string('work_center_code', 30)->unique();
            $table->string('work_center_name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->index('department_id');
            $table->index('machine_id');
        });

        // "Jadwal" di mockup: wadah bernama yang mencakup 1..n departemen, mis.
        // "Jadwal Cetak Offset" atau "Jadwal Support (QC - PPIC - MTC)".
        $mst->create('emp_roster_schedule', function (Blueprint $table) {
            $table->bigIncrements('schedule_id');
            $table->string('schedule_name', 150);
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        $mst->create('emp_roster_schedule_department', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('department_id');

            $table->unique(['schedule_id', 'department_id'], 'uq_sched_dept');
            $table->foreign('schedule_id')->references('schedule_id')->on('emp_roster_schedule')->cascadeOnDelete();
        });

        // Sertifikasi/skill pegawai per work center (mockup: EMP.skills + filter
        // "Hanya yang punya skill mesin ini" + chip kuning "belum bersertifikat").
        $mst->create('emp_employee_work_center_skill', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('work_center_id');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->unique(['employee_id', 'work_center_id'], 'uq_emp_wc_skill');
            $table->index('work_center_id');
        });

        // Master Shift: kode + warna chip + kuota per work center (mockup
        // "Kuota/WC"). department_id jadi nullable = katalog shift global lintas
        // departemen (REG/SH1/SH2/SH3 dipakai semua departemen di mockup);
        // shift lama yang sudah terikat departemen tetap valid apa adanya.
        $mst->table('emp_shift', function (Blueprint $table) {
            $table->string('shift_code', 10)->nullable()->after('department_id');
            $table->string('color_hex', 7)->nullable()->after('shift_name');
            $table->unsignedSmallInteger('capacity_per_wc')->default(4)->after('color_hex');
        });
        DB::connection('tm2mst')->statement('ALTER TABLE emp_shift MODIFY department_id BIGINT UNSIGNED NULL');

        // Mockup mengedit istirahat dalam MENIT, skema lama menyimpan jam
        // break_out/break_in. Kolom baru ini yang dipakai layar roster; kolom
        // jam lama TIDAK disentuh (masih dipakai layar Master Shift lama).
        $mst->table('emp_shift_detail', function (Blueprint $table) {
            $table->unsignedSmallInteger('break_minutes')->nullable()->after('break_in');
        });

        $mst->table('emp_shift_roster', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable()->after('machine_id');
            $table->unsignedBigInteger('schedule_id')->nullable()->after('work_center_id');

            $table->index('work_center_id');
            $table->index('schedule_id');
        });

        // Lifecycle Draft/Confirm DIPERTAHANKAN (mockup tidak punya, tapi kunci
        // publish ini pengaman ke payroll/attendance -- sengaja tidak dibuang),
        // cuma pindah sumbu dari division_id ke schedule_id utk jalur baru.
        $mst->table('emp_shift_roster_batch', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->after('division_id');
            $table->index('schedule_id');
        });
        DB::connection('tm2mst')->statement('ALTER TABLE emp_shift_roster_batch MODIFY division_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->table('emp_shift_roster_batch', function (Blueprint $table) {
            $table->dropIndex(['schedule_id']);
            $table->dropColumn('schedule_id');
        });

        $mst->table('emp_shift_roster', function (Blueprint $table) {
            $table->dropIndex(['work_center_id']);
            $table->dropIndex(['schedule_id']);
            $table->dropColumn(['work_center_id', 'schedule_id']);
        });

        $mst->table('emp_shift_detail', function (Blueprint $table) {
            $table->dropColumn('break_minutes');
        });

        $mst->table('emp_shift', function (Blueprint $table) {
            $table->dropColumn(['shift_code', 'color_hex', 'capacity_per_wc']);
        });

        $mst->dropIfExists('emp_employee_work_center_skill');
        $mst->dropIfExists('emp_roster_schedule_department');
        $mst->dropIfExists('emp_roster_schedule');
        $mst->dropIfExists('emp_work_center');

        DB::connection('tm2mst')->statement('ALTER TABLE emp_shift MODIFY department_id BIGINT UNSIGNED NOT NULL');
        DB::connection('tm2mst')->statement('ALTER TABLE emp_shift_roster_batch MODIFY division_id BIGINT UNSIGNED NOT NULL');
    }
};
