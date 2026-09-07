<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Modul 01 (Upload Finger/Excel) -- mesin finger fisik mengenal pegawai
// lewat PIN nomor (bukan employee_code), lihat docs/ddl/tmhrm.sql
// sp_process_attlogs (`e.device_pin = r.device_id`). Kolom ini belum ada
// sama sekali di emp_employee -- ditambah sbg prasyarat matching upload.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->string('device_pin', 20)->nullable()->after('employee_code')->comment('PIN nomor mesin fingerprint fisik, dasar matching upload raw presensi');
            $table->unique('device_pin');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->dropUnique(['device_pin']);
            $table->dropColumn('device_pin');
        });
    }
};
