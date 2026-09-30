<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Flow approval PO Kontan (user 2026-10-01): cukup Direktur F&A (org position 61), terpisah dari PO biasa (module PO).
// Dipisah dari 000030 karena memakai kolom flow_config.final_status/reject_status & flow_config_step.print_label/activate
// dari 2026_09_30_000009_flow_config_approval_setting (urutan nama file menjamin migration itu jalan lebih dulu).
return new class extends Migration
{
    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        if ($mst->table('flow_config')->where('module_code', 'POC')->exists()) {
            return;
        }
        $now = now();
        $cfg = $mst->table('flow_config')->insertGetId(['module_code' => 'POC', 'total_step' => 1, 'final_status' => 'APPROVED', 'reject_status' => 'REJECTED',
            'notes' => 'PO Kontan: approval cukup Direktur F&A (user 2026-10-01)', 'activate' => 1, 'created_at' => $now, 'updated_at' => $now,
            'created_by' => 'migration', 'updated_by' => 'migration', 'data_origin' => 'LIVE', 'data_ready' => 1]);
        $mst->table('flow_config_step')->insert(['flow_config_id' => $cfg, 'step_no' => 1, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED', 'status_to' => 'SUBMITTED',
            'status_to_reject' => 'REJECTED', 'position_id' => 61, 'min_amount' => 0, 'print_label' => 'Menyetujui', 'activate' => 1, 'created_at' => $now,
            'updated_at' => $now, 'created_by' => 'migration', 'updated_by' => 'migration', 'data_origin' => 'LIVE', 'data_ready' => 1]);
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $cfg = $mst->table('flow_config')->where('module_code', 'POC')->value('flow_config_id');
        $mst->table('flow_config_step')->where('flow_config_id', $cfg)->delete();
        $mst->table('flow_config')->where('flow_config_id', $cfg)->delete();
    }
};
