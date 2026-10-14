<?php

/*
 * REMARK
 * Tujuan       : Tabel Assign Crew: crew per mesin (nama = nickname mesin + indeks), periode berlaku, dan anggota (karyawan + peran).
 * Sumber hulu  : tm2mst.prod_machine (mesin, nickname), tm2mst.emp_shift (Master Shift), tm2mst.emp_employee (karyawan produksi).
 * Input        : -
 * Output       : prod_machine_crew (machine_id, crew_index, shift_id, activate), prod_machine_crew_period (valid_from, valid_to),
 *                prod_machine_crew_member (employee_id, role). Satu database, jadi FK fisik; nama crew tidak disimpan.
 * Dipakai oleh : App\Services\Scheduling\Crew\MachineCrewService, layar v2 crew-form.
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis. Grup tetap menyusul (belum ada tabelnya).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2mst');

        if (! $s->hasTable('prod_machine_crew')) {
            $s->create('prod_machine_crew', function (Blueprint $t) {
                $t->id('id_machine_crew');
                $t->unsignedBigInteger('machine_id');
                $t->unsignedSmallInteger('crew_index');   // 1,2,3,... per mesin; tidak dipakai ulang (crew dinonaktifkan, bukan dihapus)
                $t->unsignedBigInteger('shift_id')->nullable();   // logis ke emp_shift.shift_id
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 40)->nullable();
                $t->string('updated_by', 40)->nullable();
                $t->unique(['machine_id', 'crew_index'], 'uq_machine_crew');
            });
        }

        if (! $s->hasTable('prod_machine_crew_period')) {
            $s->create('prod_machine_crew_period', function (Blueprint $t) {
                $t->id('id_crew_period');
                $t->unsignedBigInteger('id_machine_crew');
                $t->date('valid_from');
                $t->date('valid_to')->nullable();         // NULL = terbuka
                $t->timestamps();
                $t->string('created_by', 40)->nullable();
                $t->string('updated_by', 40)->nullable();
                $t->index(['id_machine_crew', 'valid_from'], 'idx_crew_period');
                $t->foreign('id_machine_crew', 'fk_crew_period_crew')->references('id_machine_crew')->on('prod_machine_crew')->cascadeOnDelete();
            });
        }

        if (! $s->hasTable('prod_machine_crew_member')) {
            $s->create('prod_machine_crew_member', function (Blueprint $t) {
                $t->id('id_crew_member');
                $t->unsignedBigInteger('id_crew_period');
                $t->unsignedBigInteger('employee_id');    // logis ke emp_employee.employee_id
                $t->string('role', 40)->nullable();
                $t->timestamps();
                $t->unique(['id_crew_period', 'employee_id'], 'uq_crew_member');
                $t->foreign('id_crew_period', 'fk_crew_member_period')->references('id_crew_period')->on('prod_machine_crew_period')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('tm2mst');
        $s->dropIfExists('prod_machine_crew_member');
        $s->dropIfExists('prod_machine_crew_period');
        $s->dropIfExists('prod_machine_crew');
    }
};
