<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Mesin & Master Shift (mockup_master_produksi.html). Master data ->
// tm2mst (CLAUDE.md §2, konsisten dgn prod_process/prod_type existing),
// BUKAN tm2prd (itu untuk transaksi produksi/work order). prod_machine.id_machine
// disiapkan sebagai target FK logis utk prod_part_process.machine_id /
// iqp_part_process.machine_id (lihat TIER_3A_MASTER_inquiry.sql — "menyusul"),
// tapi wiring FK itu di luar cakupan migration ini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_machine', function (Blueprint $table) {
            $table->id('id_machine');
            $table->string('machine_code', 30)->unique();
            $table->string('machine_name', 100);
            $table->string('machine_type', 30)->nullable()->comment('Cetak, Coating, Laminasi, Potong, Lainnya');
            $table->string('division_sbu', 30)->nullable()->comment('Offset Printing, Digital Printing, Indostry');
            $table->string('brand_model', 100)->nullable();
            $table->smallInteger('asset_year')->unsigned()->nullable();
            $table->string('asset_serial_number', 60)->nullable();
            $table->string('max_paper_size', 50)->nullable();
            $table->string('color_units', 30)->nullable()->comment('mis. "4 warna" — teks bebas');
            $table->integer('max_speed_sheet_per_hour')->unsigned()->nullable();
            $table->string('capacity_per_shift', 50)->nullable()->comment('mis. "± 100.000 sheet" — teks bebas');
            $table->smallInteger('gsm_min')->unsigned()->nullable();
            $table->smallInteger('gsm_max')->unsigned()->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('prod_shift', function (Blueprint $table) {
            $table->id('id_shift');
            $table->string('shift_name', 100);
            $table->string('department', 100)->nullable()->comment('mis. "Produksi — Offset Printing"');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        // Direplikasi dari legacy m_shifts_details: 1 baris per hari (1=Senin..
        // 7=Minggu). Jam NULL semua = hari itu tidak beroperasi (mis. Minggu).
        Schema::connection('tm2mst')->create('prod_shift_detail', function (Blueprint $table) {
            $table->id('id_shift_detail');
            $table->foreignId('shift_id')->constrained('prod_shift', 'id_shift')->cascadeOnDelete();
            $table->tinyInteger('day_of_week')->unsigned()->comment('1=Senin .. 7=Minggu (ISO-8601)');
            $table->time('time_in')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->time('time_out')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['shift_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_shift_detail');
        Schema::connection('tm2mst')->dropIfExists('prod_shift');
        Schema::connection('tm2mst')->dropIfExists('prod_machine');
    }
};
