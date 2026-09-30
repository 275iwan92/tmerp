<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Approver per dokumen (user 2026-09-30), lewat jabatan (org_position) supaya PLT/pengganti otomatis ikut:
//   PR = Operational Manager (Bu Nisa), 1 level.
//   PO = Product Costing (Wiwik) -> Operational Manager (Bu Nisa), selalu 2 level.
//   CO = F&A SPV (Mia) -> Operational Manager (Bu Nisa), selalu 2 level; CPDEL baru dibuat setelah level terakhir.
// Reject = final (status REJECTED, dokumen baru lewat Duplicate). Level antara tidak mengubah status dokumen (tetap SUBMITTED).
return new class extends Migration
{
    private const OPS_MANAGER = 'OPERATIONALMANA';

    private const COSTING = 'PRODUCTCOSTING';

    private const FA_SPV = 'FASPV';

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $pos = fn (string $code) => $db->table('org_position')->where('position_code', $code)->value('position_id')
            ?? throw new RuntimeException("org_position {$code} tidak ada");
        $ops = $pos(self::OPS_MANAGER);
        $now = now();

        $db->transaction(function () use ($db, $pos, $ops, $now) {
            $pr = $db->table('flow_config')->where('module_code', 'PR')->value('flow_config_id');
            $prSteps = $db->table('flow_config_step')->where('flow_config_id', $pr)->where('step_type', 'APPROVAL')->orderBy('step_no')->pluck('flow_step_id');
            $db->table('flow_config_step')->where('flow_step_id', $prSteps[0])
                ->update(['position_id' => $ops, 'status_to_reject' => 'REJECTED', 'label' => 'Operational Manager', 'updated_at' => $now, 'updated_by' => 'migration']);
            // Level Direksi PR tidak dipakai lagi; baris dipertahankan (dirujuk flow_log lama), dibuat tak terjangkau.
            $db->table('flow_config_step')->whereIn('flow_step_id', $prSteps->slice(1))
                ->update(['status_from' => 'UNUSED', 'label' => 'Tidak dipakai (2026-09-30: PR cukup Operational Manager)', 'updated_at' => $now, 'updated_by' => 'migration']);
            $db->table('flow_config')->where('flow_config_id', $pr)->update(['total_step' => 1, 'updated_at' => $now, 'updated_by' => 'migration']);

            $po = $db->table('flow_config')->where('module_code', 'PO')->value('flow_config_id');
            $poSteps = $db->table('flow_config_step')->where('flow_config_id', $po)->where('step_type', 'APPROVAL')->orderBy('step_no')->pluck('flow_step_id');
            $db->table('flow_config_step')->where('flow_step_id', $poSteps[0])->update(['position_id' => $pos(self::COSTING), 'status_from' => 'SUBMITTED',
                'status_to' => 'SUBMITTED', 'status_to_reject' => 'REJECTED', 'min_amount' => 0, 'label' => 'Product Costing', 'updated_at' => $now, 'updated_by' => 'migration']);
            $db->table('flow_config_step')->where('flow_step_id', $poSteps[1])->update(['position_id' => $ops, 'status_from' => 'SUBMITTED',
                'status_to' => 'APPROVED', 'status_to_reject' => 'REJECTED', 'min_amount' => 0, 'label' => 'Operational Manager', 'updated_at' => $now, 'updated_by' => 'migration']);
            $db->table('flow_config')->where('flow_config_id', $po)->update(['total_step' => 2, 'updated_at' => $now, 'updated_by' => 'migration']);

            if (! $db->table('flow_config')->where('module_code', 'CO')->exists()) {
                $co = $db->table('flow_config')->insertGetId(['module_code' => 'CO', 'division_id' => null, 'total_step' => 2,
                    'notes' => 'Approval Customer Order: F&A SPV -> Operational Manager; CPDEL dibuat setelah approval terakhir', 'activate' => 1,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration', 'data_origin' => 'LIVE', 'data_ready' => 1]);
                $step = fn ($no, $posId, $to, $label) => ['flow_config_id' => $co, 'step_no' => $no, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED',
                    'status_to' => $to, 'status_to_reject' => 'REJECTED', 'position_id' => $posId, 'min_amount' => 0, 'max_amount' => null, 'label' => $label,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration', 'data_origin' => 'LIVE', 'data_ready' => 1];
                $db->table('flow_config_step')->insert([$step(1, $pos(self::FA_SPV), 'SUBMITTED', 'F&A SPV'), $step(2, $ops, 'CONFIRMED', 'Operational Manager')]);
            }

            // Dokumen yang sedang menunggu (baris flow_log terakhir = SUBMITTED/HOLD) pindah ke approver baru tahap itu.
            $latest = $db->table('flow_log')->whereIn('module_code', ['PR', 'PO'])->selectRaw('MAX(flow_log_id) id')->groupBy('module_code', 'doc_id')->pluck('id');
            foreach ($db->table('flow_log')->whereIn('flow_log_id', $latest)->whereIn('action', ['SUBMITTED', 'HOLD'])->get() as $l) {
                $target = $db->table('flow_config_step')->where('flow_config_id', $l->flow_config_id)->where('step_type', 'APPROVAL')->where('status_from', 'SUBMITTED')->orderBy('step_no')->first();
                $db->table('flow_log')->where('flow_log_id', $l->flow_log_id)->update(['flow_step_id' => $target->flow_step_id, 'actor_position_id' => $target->position_id]);
            }
        });
    }

    public function down(): void
    {
        // Konfigurasi approval = data bisnis; dikembalikan lewat layar/DB, bukan rollback otomatis.
    }
};
