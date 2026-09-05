<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/attendancepayroll_tier3.md §3 emp_disciplinary_status -- emp_disciplinary
// SUDAH ADA (dipakai EmpEmployee::activeSpType()), tinggal tambah kolom
// pelacakan "berturut-turut" utk efek potongan SP1/SP2 (Tier 2 §6), bukan
// bikin tabel baru yang duplikat.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('emp_disciplinary', function (Blueprint $table) {
            $table->date('effective_end')->nullable()->after('incident_date')->comment('NULL = masih berjalan (SKR/DRM tanpa batas pasti)');
            $table->unsignedInteger('working_days_target')->nullable()->after('effective_end')->comment('3 utk SP1, 7 utk SP2 -- hari KERJA berturut, bukan kalender');
            $table->unsignedInteger('working_days_counted')->default(0)->after('working_days_target')->comment('Counter berjalan, diupdate PayrollEngineService per hari kerja terhitung');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('emp_disciplinary', function (Blueprint $table) {
            $table->dropColumn(['effective_end', 'working_days_target', 'working_days_counted']);
        });
    }
};
