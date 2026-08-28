<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Crew (PROD_MST_CREW) -- tabel baru, belum pernah ada. tm2old.mst_machine
// (255 baris, PK machine_name varchar) mencampur MESIN & CREW jadi 1 tabel via
// kolom machine_type -- dipisah sesuai request user 2026-08-28: MESIN -> tabel
// prod_machine yang sudah ada, CREW -> tabel baru ini.
//
// employee_code legacy (mis. "20150021") TIDAK match sama sekali ke
// tm2mst.emp_employee.employee_code (skema "TM-00001", beda sumber migrasi) --
// dicoba cocokkan by full_name juga cuma nemu 8/217. Jadi Master Crew SENGAJA
// TIDAK di-FK-kan ke emp_employee (data belum bisa direkonsiliasi otomatis),
// disimpan longgar (legacy_employee_code teks) + employee_id nullable FK utk
// direkonsiliasi manual belakangan (pola sama EmpEmployee::legacy_employee_id).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_crew', function (Blueprint $table) {
            $table->id('id_crew');
            $table->string('crew_name', 150);
            $table->unsignedBigInteger('process_id')->nullable()->comment('Proses default -- link ke prod_process, null kalau label lama tak match persis');
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Link manual ke emp_employee -- belum ada rekonsiliasi otomatis, isi kalau HR sudah verifikasi orangnya');
            $table->string('legacy_employee_code', 30)->nullable()->comment('Kode lama dari tm2old, format beda dgn emp_employee.employee_code -- disimpan buat jejak saja');
            $table->text('notes')->nullable();
            $table->boolean('activate')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('process_id')->references('id_process')->on('prod_process')->nullOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('emp_employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_crew');
    }
};
