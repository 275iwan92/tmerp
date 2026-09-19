<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Jejak merge karyawan dobel (permintaan user 2026-09-19): baris yang digabung
// DIHAPUS dari emp_employee, snapshot lengkapnya disimpan di sini supaya bisa
// diaudit/dipulihkan, dan supaya ETL tm2old->tm2mst tidak menghidupkannya lagi
// (EmployeeMigrationService melewati legacy_employee_id yang tercatat di sini).
return new class extends Migration
{
    protected $connection = 'tm2mst';

    public function up(): void
    {
        Schema::connection('tm2mst')->create('emp_employee_merge', function (Blueprint $t) {
            $t->id('merge_id');
            $t->unsignedBigInteger('survivor_employee_id')->index();
            $t->unsignedBigInteger('merged_employee_id');
            $t->unsignedBigInteger('merged_legacy_employee_id')->nullable()->index();
            $t->string('merged_full_name', 150);
            $t->json('snapshot');
            $t->json('moved_rows')->nullable()->comment('jumlah baris referensi yang dipindah per tabel');
            $t->string('reason', 255)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->string('created_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('emp_employee_merge');
    }
};
