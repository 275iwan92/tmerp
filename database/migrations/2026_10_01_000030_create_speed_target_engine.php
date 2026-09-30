<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Engine target kecepatan mesin (user 2026-10-01: "yang belum punya target kecepatan buatkan ENGINE buat estimasi
// target kecepatan"). Engine hanya MENGUSULKAN (prod_speed_suggestion); PPIC menerima/mengubah di layar — nilai berlaku
// tetap di prod_machine.speed_target_per_hour / prod_process_machine.speed_per_hour. Parameter engine = sys_constant
// grup SPEED_ENGINE (Settings > Constants). prod_machine.legacy_machine_names = nama mesin di sistem lama
// (tm2old.prd_output.machine_name) supaya riwayat produksi lama terbaca per mesin; bisa diubah user.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_speed_suggestion', function (Blueprint $t) {
            $t->id('id_speed_suggestion');
            $t->unsignedBigInteger('machine_id');
            $t->unsignedBigInteger('capability_id')->nullable()->comment('NULL = machine-level target; filled = process x machine pair');
            $t->string('process_name', 200)->nullable()->comment('snapshot of the pair process name');
            $t->decimal('suggested_speed', 12, 2);
            $t->string('uom', 20)->nullable()->comment('unit of the history the number came from');
            $t->string('source_code', 20)->comment('V2_ACTUAL | LEGACY_MACHINE | LEGACY_PROCESS | SAME_MODEL | PEER_RATIO | MODEL_SPEC | PROCESS_DEFAULT');
            $t->string('confidence', 10)->comment('HIGH | MEDIUM | LOW');
            $t->unsignedInteger('sample_size')->default(0);
            $t->string('basis', 500)->comment('how the number was derived (English)');
            $t->decimal('current_value', 12, 2)->nullable()->comment('value in force when the suggestion was made');
            $t->string('status', 12)->default('PENDING')->comment('PENDING | ACCEPTED | REJECTED | SUPERSEDED');
            $t->decimal('decided_value', 12, 2)->nullable()->comment('value PPIC applied (may differ from the suggestion)');
            $t->string('decided_by', 100)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 255)->nullable();
            $t->timestamp('computed_at')->nullable()->comment('last engine run that confirmed this suggestion');
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->index(['machine_id', 'capability_id', 'status']);
            $t->foreign('machine_id')->references('machine_id')->on('prod_machine')->cascadeOnDelete();
            $t->foreign('capability_id')->references('capability_id')->on('prod_process_machine')->cascadeOnDelete();
        });

        Schema::connection('tm2mst')->table('prod_machine', function (Blueprint $t) {
            $t->string('legacy_machine_names', 255)->nullable()->after('nickname')
                ->comment('machine names in the old system (tm2old.prd_output.machine_name), comma separated, * = prefix wildcard');
        });

        // Pemetaan nama lama yang sudah dipakai PPIC baseline 2026-10-01 (memori project_decisions_2026_10_01).
        $legacy = [
            'DIE-01' => 'PLONG 1', 'DIE-02' => 'PLONG 2', 'DIE-03' => 'PLONG 3', 'OFF-01' => 'SAKURA', 'OFF-02' => 'KOMORI_GL440*',
            'DIG-01' => 'HANWAY', 'DIG-02' => 'VERSAFIRE', 'DIG-03' => 'DIGITAL SUNTHINKS',
            'PLT-01' => 'CUTTING_SUMMA', 'PLT-02' => 'CUTTING_SUMMA', 'PLT-06' => 'CUTTING_SUMMA', 'PLT-03' => 'CUTTING_BK4',
            'CUT-01' => 'POTONG ITOH', 'CUT-03' => 'GUOXIN', 'FGL-03' => 'FOLDER GLUER', 'FGL-04' => 'FOLDER GLUER 2',
            'LAM-01' => 'LAMINASI MANUAL', 'LAM-03' => 'LAMINATING', 'LAM-04' => 'LAM_YOUBOND', 'UVC-01' => 'UV', 'WAX-01' => 'LILIN',
        ];
        foreach ($legacy as $code => $names) {
            DB::connection('tm2mst')->table('prod_machine')->where('machine_code', $code)->whereNull('legacy_machine_names')
                ->update(['legacy_machine_names' => $names]);
        }

        $c = fn (string $key, ?float $num, ?string $text, string $desc) => [
            'constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'SPEED_ENGINE',
            'constant_number' => $num, 'constant_text' => $text, 'description' => $desc, 'activate' => true,
        ];
        $rows = [
            $c('SPEED_PERCENTILE', 75, null, 'Speed engine: percentile of historical qty/hour taken as target (75 = a good shift, not the record)'),
            $c('SPEED_MIN_SAMPLE', 20, null, 'Speed engine: minimum production records before history is trusted'),
            $c('SPEED_HIGH_SAMPLE', 200, null, 'Speed engine: records needed for HIGH confidence'),
            $c('SPEED_RUN_MIN_MINUTES', 5, null, 'Speed engine: ignore runs shorter than this (minutes) - typing errors'),
            $c('SPEED_RUN_MAX_MINUTES', 960, null, 'Speed engine: ignore runs longer than this (minutes) - start/stop left open'),
            $c('SPEED_EFF_MANUAL', 0.50, null, 'Speed engine: realistic share of maker max speed for MANUAL machines (plant 2021-2024: manual die cutters 0.47-0.57)'),
            $c('SPEED_EFF_AUTO', 0.60, null, 'Speed engine: realistic share of maker max speed for AUTO machines (plant 2021-2024: offset presses 0.38-0.63)'),
            $c('SPEED_CHANGE_MIN_PCT', 5, null, 'Speed engine: only propose a change when it differs from the value in force by more than this %'),
            $c('SPEED_V2_LOOKBACK_DAYS', 365, null, 'Speed engine: days of v2 work-order actuals used'),
            $c('SPEED_LEGACY_EXCLUDE', null, 'SETTING,GANTI,REPARASI,CLEANING,PERBAIKAN,PERSIAPAN,HEAT UP,HITUNG,QC',
                'Speed engine: legacy process names containing these words are not production runs (comma separated)'),
        ];
        foreach ($rows as $row) {
            if (! DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $row['constant_key'])->exists()) {
                DB::connection('tm2sys')->table('sys_constant')->insert($row + ['created_at' => now(), 'updated_at' => now(), 'created_by' => 'system']);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_group', 'SPEED_ENGINE')->delete();
        Schema::connection('tm2mst')->table('prod_machine', fn (Blueprint $t) => $t->dropColumn('legacy_machine_names'));
        Schema::connection('tm2mst')->dropIfExists('prod_speed_suggestion');
    }
};
