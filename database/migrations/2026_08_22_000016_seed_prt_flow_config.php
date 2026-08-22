<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// flow_config PRT (Purchase Return ke supplier) -- mockup_purchase_return.html
// cuma minta 1 approver (Manajer Operasional), tanpa tingkatan nilai, beda dari
// PR/PO/Adjustment yang berjenjang. Reject -> DRAFT (bukan REVISION_NEEDED --
// prt_return.status tak punya nilai itu, DRAFT sudah cukup krn tetap editable).
// Module code 'PRT' beda dari Inventory 'RTP' (Return from Production) -- dua
// konsep berbeda yang kebetulan disingkat mirip, TIDAK bentrok flow_config.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();

        $mgrOpsId = $db->table('org_position')->where('position_code', 'MGR-OPS')->value('position_id');

        if (! $mgrOpsId) {
            return;
        }

        $existing = $db->table('flow_config')->where('module_code', 'PRT')->where('division_id', null)->value('flow_config_id');

        if ($existing) {
            return;
        }

        $flowConfigId = $db->table('flow_config')->insertGetId([
            'module_code' => 'PRT',
            'division_id' => null,
            'total_step' => 1,
            'notes' => 'Approval Purchase Return ke supplier (company-wide, 1 level) - mockup_purchase_return.html',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => '_system',
        ]);

        $db->table('flow_config_step')->insert([
            'flow_config_id' => $flowConfigId, 'step_no' => 1, 'step_type' => 'APPROVAL',
            'status_from' => 'SUBMITTED', 'status_to' => 'CONFIRMED', 'status_to_reject' => 'DRAFT',
            'position_id' => $mgrOpsId, 'min_amount' => 0, 'max_amount' => null, 'label' => 'Menyetujui Retur',
            'created_at' => $now, 'updated_at' => $now, 'created_by' => '_system',
        ]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');

        $flowConfigId = $db->table('flow_config')->where('module_code', 'PRT')->value('flow_config_id');

        if ($flowConfigId) {
            $db->table('flow_config_step')->where('flow_config_id', $flowConfigId)->delete();
            $db->table('flow_config')->where('flow_config_id', $flowConfigId)->delete();
        }
    }
};
