<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C6: HRM > Time Attendance > GPS Clock-In pindah ke layar v2 (v2:hrm-clockin, objek x-dl::geo-clock). Milik sendiri: required_object GENERAL
// supaya semua karyawan melihatnya (sama dengan self-service v1, tanpa gerbang modul).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_CLOCKIN')->update(['target' => 'v2:hrm-clockin', 'object_type' => 'form', 'required_object' => 'GENERAL',
            'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_CLOCKIN')->update(['target' => null, 'required_object' => null]);
    }
};
