<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Seed posisi approval Procurement + flow_config PR (PROC_TIER_3.md §9).
//
// DEVIASI dari draf Tier 3 §9 (dicatat di PROC_STATE.yaml, perlu review Jo --
// issue PROC-FLOW-POSITION): org_position saat ini HANYA berisi 5 posisi
// lantai produksi (Operator/Specialist/Inspector), TIDAK ADA jabatan
// manajerial (Kepala Departemen/Manajer Operasional/Direksi). Draf step-1 PR
// "Kepala Departemen" juga secara struktural tidak bisa jadi SATU position_id
// tetap (flow_config_step.position_id itu tunggal) karena PR bisa berasal
// dari departemen manapun -- per-departemen head belum dimodelkan. Disederhanakan
// jadi APPROVAL TERPUSAT (konsisten dgn seed PO yang sudah pakai "Manajer
// Operasional" company-wide): PR step 1 = Manajer Operasional (semua nilai),
// step 2 = Direksi (>= Rp 50 juta, sesuai draf). Posisi baru dibuat di
// departemen ADMIN sbg placeholder -- BELUM ada user riil ditugaskan ke sini.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();

        $adminDeptId = $db->table('org_department')->where('department_code', 'ADMIN')->value('department_id');

        if (! $adminDeptId) {
            return;
        }

        $mgrLevelId = $db->table('org_level')->where('level_code', 'MGR')->value('level_id');
        $dirLevelId = $db->table('org_level')->where('level_code', 'DIR')->value('level_id');
        $staffLevelId = $db->table('org_level')->where('level_code', 'STAFF')->value('level_id');

        $positions = [
            ['position_code' => 'MGR-OPS', 'position_name' => 'Manajer Operasional', 'level_id' => $mgrLevelId],
            ['position_code' => 'DIREKSI-OPS', 'position_name' => 'Direksi', 'level_id' => $dirLevelId],
            ['position_code' => 'PURCH-OFFICER', 'position_name' => 'Purchasing Officer', 'level_id' => $staffLevelId],
        ];

        $positionIds = [];

        foreach ($positions as $p) {
            $positionIds[$p['position_code']] = $db->table('org_position')->updateOrInsert(
                ['position_code' => $p['position_code']],
                [
                    'department_id' => $adminDeptId,
                    'level_id' => $p['level_id'],
                    'position_name' => $p['position_name'],
                    'salary_hidden_from_hr' => 0,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $mgrOpsId = $db->table('org_position')->where('position_code', 'MGR-OPS')->value('position_id');
        $direksiId = $db->table('org_position')->where('position_code', 'DIREKSI-OPS')->value('position_id');

        if (! $mgrOpsId || ! $direksiId) {
            return;
        }

        $flowConfigId = $db->table('flow_config')->where('module_code', 'PR')->where('division_id', null)->value('flow_config_id');

        if (! $flowConfigId) {
            $flowConfigId = $db->table('flow_config')->insertGetId([
                'module_code' => 'PR',
                'division_id' => null,
                'total_step' => 3,
                'notes' => 'Approval Permintaan Pembelian (company-wide) - PROC_TIER_3 §9',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => '_system',
            ]);

            $db->table('flow_config_step')->insert([
                [
                    'flow_config_id' => $flowConfigId, 'step_no' => 1, 'step_type' => 'APPROVAL',
                    'status_from' => 'SUBMITTED', 'status_to' => 'APPROVED', 'status_to_reject' => 'REVISION_NEEDED',
                    'position_id' => $mgrOpsId, 'min_amount' => 0, 'max_amount' => null, 'label' => 'Menyetujui',
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => '_system',
                ],
                [
                    'flow_config_id' => $flowConfigId, 'step_no' => 2, 'step_type' => 'APPROVAL',
                    'status_from' => 'APPROVED', 'status_to' => 'APPROVED', 'status_to_reject' => 'REVISION_NEEDED',
                    'position_id' => $direksiId, 'min_amount' => 50000000, 'max_amount' => null, 'label' => 'Menyetujui (>= Rp 50 juta)',
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => '_system',
                ],
                [
                    'flow_config_id' => $flowConfigId, 'step_no' => 3, 'step_type' => 'AUTO',
                    'status_from' => 'FULLY_ORDERED', 'status_to' => 'CLOSED', 'status_to_reject' => null,
                    'position_id' => null, 'min_amount' => 0, 'max_amount' => null, 'label' => null,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => '_system',
                ],
            ]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');

        $flowConfigId = $db->table('flow_config')->where('module_code', 'PR')->value('flow_config_id');

        if ($flowConfigId) {
            $db->table('flow_config_step')->where('flow_config_id', $flowConfigId)->delete();
            $db->table('flow_config')->where('flow_config_id', $flowConfigId)->delete();
        }

        $db->table('org_position')->whereIn('position_code', ['MGR-OPS', 'DIREKSI-OPS', 'PURCH-OFFICER'])->delete();
    }
};
