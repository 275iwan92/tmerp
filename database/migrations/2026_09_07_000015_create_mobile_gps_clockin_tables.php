<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Modul 09 (Mobile GPS Clock-in) -- sebelumnya SENGAJA ditunda (BACKLOG
// §12.5: "GPS/selfie mobile clock-in -- tidak diminta, tidak dibangun"),
// dibangun sekarang atas permintaan eksplisit user 2026-09-07 ("selesaikan
// point no 7 dan no 9"). Koordinat kantor default diambil dari referensi
// docs/payroll_timeattendance/tm2hrm_laravel_php83.zip::GpsHaversineService
// (Jl. Raden Panji Suroso No. 12, Blimbing, Malang) -- DATA, bukan hardcode
// di kode, supaya bisa ditambah lokasi lain (multi-pabrik) tanpa ubah kode.
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->create('sys_geofence_location', function (Blueprint $table) {
            $table->id('id_location');
            $table->string('location_name', 100);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedInteger('radius_meters')->default(50);
            $table->boolean('activate')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->string('created_by', 100)->nullable();
        });

        DB::connection('tm2mst')->table('sys_geofence_location')->insert([
            'location_name' => 'Pabrik Panji Suroso Malang',
            'latitude' => -7.94220500,
            'longitude' => 112.65106000,
            'radius_meters' => 50,
            'activate' => true,
            'created_at' => now(),
            'created_by' => 'claude_code',
        ]);

        $mst->create('att_mobile_gps_log', function (Blueprint $table) {
            $table->id('id_gps_log');
            $table->foreignId('employee_id')->constrained('emp_employee', 'employee_id')->cascadeOnDelete();
            $table->string('clock_type', 3)->comment('IN|OUT');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedInteger('distance_meters');
            $table->string('geofence_status', 15)->comment('IN_RADIUS|OUT_OF_RADIUS');
            $table->string('hr_approval_status', 15)->default('AUTO_APPROVED')->comment('AUTO_APPROVED|PENDING|APPROVED|REJECTED');
            $table->string('hr_approved_by', 100)->nullable();
            $table->dateTime('hr_approved_at')->nullable();
            $table->string('hr_approval_note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['employee_id', 'created_at']);
            $table->index('hr_approval_status');
        });

        // Scan dari GPS tidak selalu bagian dari 1 batch upload file --
        // id_import jadi nullable (NULL = sumbernya GPS, bukan upload
        // Modul 01). Raw SQL (bukan ->change()) krn doctrine/dbal tidak
        // terpasang di proyek ini.
        DB::connection('tm2mst')->statement('ALTER TABLE att_finger_raw MODIFY id_import BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::connection('tm2mst')->statement('ALTER TABLE att_finger_raw MODIFY id_import BIGINT UNSIGNED NOT NULL');

        Schema::connection('tm2mst')->dropIfExists('att_mobile_gps_log');
        Schema::connection('tm2mst')->dropIfExists('sys_geofence_location');
    }
};
