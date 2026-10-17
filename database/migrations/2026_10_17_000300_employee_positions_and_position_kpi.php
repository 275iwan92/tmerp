<?php

/*
 * REMARK
 * Tujuan       : Satu pegawai boleh memegang lebih dari satu jabatan, dan tiap jabatan punya KPI (terkait job description). Dua tabel baru di tm2mst.
 * Sumber hulu  : tm2mst.emp_employee (pegawai; posisi utama tetap emp_employee.position_id), tm2mst.org_position (jabatan dan job_description).
 * Input        : -
 * Output       : emp_employee_position (jabatan tambahan per pegawai) dan org_position_kpi (KPI per jabatan: nama, satuan, arah, target, bobot). FK fisik: satu database.
 * Dipakai oleh : App\Services\Hrm\EmployeeRoleService, App\Services\Hrm\PositionService, layar v2 hrm-employee-form (tab Job) dan hrm-position-form.
 * Status       : Jalankan di ddt* (--env=mockup) dan tm2* (aturan: perubahan skema mockup wajib juga di tmerp); hanya berkas ini lewat --path.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2mst');

        // Jabatan tambahan: posisi utama TIDAK disimpan di sini (tetap emp_employee.position_id, penentu approval, struktur organisasi, payroll).
        if (! $s->hasTable('emp_employee_position')) {
            $s->create('emp_employee_position', function (Blueprint $t) {
                $t->id('id_employee_position');
                $t->unsignedBigInteger('employee_id');
                $t->unsignedBigInteger('position_id');
                $t->date('since_date')->nullable();
                $t->boolean('activate')->default(true);   // dihapus = dinonaktifkan, baris tetap sebagai riwayat
                $t->timestamps();
                $t->string('created_by', 40)->nullable();
                $t->string('updated_by', 40)->nullable();
                $t->unique(['employee_id', 'position_id'], 'uq_employee_position');
                $t->foreign('employee_id', 'fk_emp_position_employee')->references('employee_id')->on('emp_employee')->cascadeOnDelete();
                $t->foreign('position_id', 'fk_emp_position_position')->references('position_id')->on('org_position');
            });
        }

        // KPI per jabatan; direction HIGHER = makin besar makin baik, LOWER = makin kecil makin baik.
        if (! $s->hasTable('org_position_kpi')) {
            $s->create('org_position_kpi', function (Blueprint $t) {
                $t->id('id_position_kpi');
                $t->unsignedBigInteger('position_id');
                $t->string('kpi_name', 150);
                $t->string('unit', 20)->nullable();
                $t->string('direction', 6)->default('HIGHER');
                $t->decimal('target_value', 14, 2);
                $t->decimal('weight_pct', 5, 2);
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 40)->nullable();
                $t->string('updated_by', 40)->nullable();
                $t->index('position_id', 'idx_position_kpi_position');
                $t->foreign('position_id', 'fk_position_kpi_position')->references('position_id')->on('org_position')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('org_position_kpi');
        Schema::connection('tm2mst')->dropIfExists('emp_employee_position');
    }
};
