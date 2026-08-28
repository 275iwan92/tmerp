<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// 2 hal sekaligus, sama-sama koreksi dari review mockup WO Detail final
// 2026-08-28 (docs/production_scheduling_wo_generation_delta.md §5 revisi):
// (1) prd_wo.priority (Normal/Urgent/Super Urgent) -- field baru diminta
// user, cuma is_hot_job boolean sebelumnya; (2) menu utk layar kelola
// prod_part_process_machine_rate/_employee_rate (dulu cuma bisa diisi
// tinker, sekarang py layar production/process-rate.blade.php), dipasang
// di bawah PROD_PLAN sbg data pendukung Bill of Process.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tm2prd')->hasColumn('prd_wo', 'priority')) {
            Schema::connection('tm2prd')->table('prd_wo', function (Blueprint $table) {
                $table->enum('priority', ['NORMAL', 'URGENT', 'SUPER_URGENT'])->default('NORMAL')->after('is_hot_job');
            });
        }

        $sys = DB::connection('tm2sys');
        $planId = $sys->table('sys_app_menu')->where('code', 'PROD_PLAN')->value('id');
        if ($planId && $sys->table('sys_app_menu')->where('code', 'PROD_PROCESS_RATE')->doesntExist()) {
            $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId,
                'parent_id' => $planId,
                'code' => 'PROD_PROCESS_RATE',
                'label' => 'Target Rate Proses x Mesin/Pegawai',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 16,
                'activate' => true,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::connection('tm2prd')->hasColumn('prd_wo', 'priority')) {
            Schema::connection('tm2prd')->table('prd_wo', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROD_PROCESS_RATE')->delete();
    }
};
