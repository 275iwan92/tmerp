<?php

/*
 * REMARK
 * Tujuan       : Hasil KPI bulanan per pegawai (tahap B): nilai aktual per KPI jabatan per bulan, boleh ditandai work center (mesin). Dasar persentase kemampuan yang dihitung, tidak disimpan.
 * Sumber hulu  : tm2mst.emp_employee, org_position_kpi (definisi KPI), emp_work_center (opsional, penanda mesin).
 * Input        : -
 * Output       : emp_kpi_result: actual_value + snapshot target / bobot / arah saat entri (prinsip snapshot A.13: mengubah KPI tidak menulis ulang riwayat). FK fisik: satu database.
 * Dipakai oleh : App\Services\Hrm\KpiResultService, layar v2 hrm-employee-form (tab KPI dan Skills).
 * Status       : Jalankan di ddt* (--env=mockup) dan tm2*; hanya berkas ini lewat --path.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2mst');

        if (! $s->hasTable('emp_kpi_result')) {
            $s->create('emp_kpi_result', function (Blueprint $t) {
                $t->id('id_kpi_result');
                $t->unsignedBigInteger('employee_id');
                $t->unsignedBigInteger('position_kpi_id');
                $t->unsignedBigInteger('work_center_id')->nullable();   // NULL = hasil tidak terikat mesin tertentu
                $t->date('period_month');                               // selalu tanggal 1 bulan itu
                $t->decimal('actual_value', 14, 2);
                $t->decimal('target_snapshot', 14, 2);
                $t->decimal('weight_snapshot', 5, 2);
                $t->string('direction_snapshot', 6);
                $t->string('note', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 40)->nullable();
                $t->string('updated_by', 40)->nullable();
                $t->index(['employee_id', 'period_month'], 'idx_kpi_result_employee_month');
                $t->index(['position_kpi_id', 'period_month'], 'idx_kpi_result_kpi_month');
                $t->foreign('employee_id', 'fk_kpi_result_employee')->references('employee_id')->on('emp_employee')->cascadeOnDelete();
                $t->foreign('position_kpi_id', 'fk_kpi_result_kpi')->references('id_position_kpi')->on('org_position_kpi');
                $t->foreign('work_center_id', 'fk_kpi_result_work_center')->references('work_center_id')->on('emp_work_center')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('emp_kpi_result');
    }
};
