<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// flow_config PO (PROC_TIER_3 §9): step1 Manajer Operasional (semua nilai),
// step2 Direksi (>=100jt). Posisi pakai yang sudah dibuat migration 000015
// (MGR-OPS/DIREKSI-OPS) -- deviasi sama seperti PR (approval terpusat,
// bukan per-departemen), lihat procurement_state.yaml issue PROC-FLOW-POSITION.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();

        $mgrOpsId = $db->table('org_position')->where('position_code', 'MGR-OPS')->value('position_id');
        $direksiId = $db->table('org_position')->where('position_code', 'DIREKSI-OPS')->value('position_id');

        if (! $mgrOpsId || ! $direksiId) {
            return;
        }

        $flowConfigId = $db->table('flow_config')->where('module_code', 'PO')->where('division_id', null)->value('flow_config_id');

        if ($flowConfigId) {
            return;
        }

        $flowConfigId = $db->table('flow_config')->insertGetId([
            'module_code' => 'PO',
            'division_id' => null,
            'total_step' => 2,
            'notes' => 'Approval Pesanan Pembelian (company-wide) - PROC_TIER_3 §9',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => '_system',
        ]);

        $db->table('flow_config_step')->insert([
            [
                'flow_config_id' => $flowConfigId, 'step_no' => 1, 'step_type' => 'APPROVAL',
                'status_from' => 'SUBMITTED', 'status_to' => 'APPROVED', 'status_to_reject' => 'REJECTED',
                'position_id' => $mgrOpsId, 'min_amount' => 0, 'max_amount' => null, 'label' => 'Menyetujui',
                'created_at' => $now, 'updated_at' => $now, 'created_by' => '_system',
            ],
            [
                'flow_config_id' => $flowConfigId, 'step_no' => 2, 'step_type' => 'APPROVAL',
                'status_from' => 'APPROVED', 'status_to' => 'APPROVED', 'status_to_reject' => 'REJECTED',
                'position_id' => $direksiId, 'min_amount' => 100000000, 'max_amount' => null, 'label' => 'Menyetujui (>= Rp 100 juta)',
                'created_at' => $now, 'updated_at' => $now, 'created_by' => '_system',
            ],
        ]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');

        $flowConfigId = $db->table('flow_config')->where('module_code', 'PO')->value('flow_config_id');

        if ($flowConfigId) {
            $db->table('flow_config_step')->where('flow_config_id', $flowConfigId)->delete();
            $db->table('flow_config')->where('flow_config_id', $flowConfigId)->delete();
        }
    }
};
