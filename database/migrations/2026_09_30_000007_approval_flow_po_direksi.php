<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PO di atas Rp 1 juta: level 3 = Presiden Direktur (Pak Carrij) -- user 2026-09-30.
// Flow engine memilih langkah berikut dari step_no lebih besar + status_from + rentang nilai, jadi Operational Manager
// punya 2 baris: <= 1 jt (akhir, -> APPROVED) dan > 1 jt (lanjut, tetap SUBMITTED) -> Presiden Direktur (-> APPROVED).
return new class extends Migration
{
    private const LIMIT = 1000000;

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $po = $db->table('flow_config')->where('module_code', 'PO')->value('flow_config_id');
        $ops = $db->table('org_position')->where('position_code', 'OPERATIONALMANA')->value('position_id');
        $presdir = $db->table('org_position')->where('position_code', 'PRESIDENDIREKTU')->value('position_id');
        $now = now();
        if ($db->table('flow_config_step')->where('flow_config_id', $po)->where('step_no', 4)->exists()) {
            return;
        }

        $db->transaction(function () use ($db, $po, $ops, $presdir, $now) {
            $opsSmall = $db->table('flow_config_step')->where('flow_config_id', $po)->where('step_no', 2)->value('flow_step_id');
            $db->table('flow_config_step')->where('flow_step_id', $opsSmall)
                ->update(['max_amount' => self::LIMIT, 'label' => 'Operational Manager (s.d. Rp 1 juta)', 'updated_at' => $now, 'updated_by' => 'migration']);
            $row = fn ($no, $posId, $to, $label) => ['flow_config_id' => $po, 'step_no' => $no, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED',
                'status_to' => $to, 'status_to_reject' => 'REJECTED', 'position_id' => $posId, 'min_amount' => self::LIMIT + 0.01, 'max_amount' => null,
                'label' => $label, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration', 'data_origin' => 'LIVE', 'data_ready' => 1];
            $opsBig = $db->table('flow_config_step')->insertGetId($row(3, $ops, 'SUBMITTED', 'Operational Manager (> Rp 1 juta)'));
            $db->table('flow_config_step')->insert($row(4, $presdir, 'APPROVED', 'Presiden Direktur (> Rp 1 juta)'));
            $db->table('flow_config')->where('flow_config_id', $po)->update(['total_step' => 3, 'updated_at' => $now, 'updated_by' => 'migration']);

            // PO > 1 jt yang sedang menunggu Operational Manager dipindah ke baris "> 1 jt" supaya lanjut ke Presiden Direktur.
            $latest = $db->table('flow_log')->where('module_code', 'PO')->selectRaw('MAX(flow_log_id) id')->groupBy('doc_id')->pluck('id');
            $db->table('flow_log')->whereIn('flow_log_id', $latest)->whereIn('action', ['SUBMITTED', 'HOLD'])->where('flow_step_id', $opsSmall)
                ->where('amount_snapshot', '>', self::LIMIT)->update(['flow_step_id' => $opsBig]);
        });
    }

    public function down(): void
    {
        // Konfigurasi approval = data bisnis; tidak di-rollback otomatis.
    }
};
