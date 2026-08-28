<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// no_kk (Nomor Kartu Keluarga) -- salah satu dari 8 field "kunci user" wajib
// (keputusan user 2026-08-27, sama sesi dgn perluasan emp_family/security
// recovery): nama, no_ktp, no_kk, no smartphone utama, nama ibu kandung,
// alamat tinggal, email, tanggal lahir. 7 dari 8 sudah ada kolomnya di
// emp_employee -- hanya no_kk yang benar-benar baru.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->string('no_kk', 20)->nullable()->after('nik_ktp');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('emp_employee', function (Blueprint $table) {
            $table->dropColumn('no_kk');
        });
    }
};
