<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_HRM.sql Bagian 0 — prod_machine dibutuhkan sbg FK emp_employee &
// emp_shift_roster. Direstrukturisasi dari division_sbu (enum teks) ->
// division_id (FK org_division), konsisten dgn struktur organisasi baru.
// Hanya data seed (4 mesin demo) yg ada — drop+recreate aman, bukan ALTER
// data produksi nyata. Master Mesin (machine.blade.php) di-update menyusul.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_machine');

        Schema::connection('tm2mst')->create('prod_machine', function (Blueprint $table) {
            $table->id('machine_id');
            $table->foreignId('division_id')->constrained('org_division', 'division_id')->restrictOnDelete();
            $table->string('machine_code', 20);
            $table->string('machine_name', 100);
            $table->string('machine_type', 20)->nullable()->comment('Cetak|Coating|Laminasi|Potong|Lainnya');
            $table->string('brand_model', 150)->nullable();
            $table->unsignedSmallInteger('year_acquired')->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->string('max_paper_size', 50)->nullable();
            $table->unsignedTinyInteger('color_count')->nullable();
            $table->unsignedInteger('max_speed_per_hour')->nullable();
            $table->string('capacity_per_shift', 50)->nullable();
            $table->unsignedSmallInteger('gsm_min')->nullable();
            $table->unsignedSmallInteger('gsm_max')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['division_id', 'machine_code']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_machine');

        // Skema lama (pra-HRM) tidak direstorasi otomatis — lihat
        // 2026_08_18_000002_create_prod_machine_and_shift_tables.php jika perlu rollback penuh.
    }
};
