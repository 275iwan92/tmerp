<?php

namespace App\Services\Flow;

use App\Models\Master\FlowConfig;
use App\Models\Master\FlowLog;
use App\Models\Master\FlowPositionActing;
use Illuminate\Support\Facades\Auth;

// Engine approval + state-transition generik lintas modul (TIER_1/2/3_FLOW_ENGINE.md).
// Implementasi tahap ini: submit() dokumen ke step APPROVAL pertama yang cocok
// (status_from + rentang amount). approve()/reject() menyusul saat layar
// approval (PrApproval, dst) dibangun -- lihat PROC_STATE.yaml next_step.
class FlowEngineService
{
    // Kirim dokumen dari statusFrom (mis. 'DRAFT') ke step APPROVAL/AUTO pertama
    // yang match. Mengembalikan null bila tidak ada config aktif (flow BLOCKED,
    // TIER_2_FLOW_ENGINE §2 B-12) -- dokumen tetap di statusFrom, caller harus
    // tampilkan peringatan, jangan lanjut mengubah status dokumennya sendiri.
    public function submit(string $moduleCode, int $docId, string $statusFrom, float $amount, ?int $divisionId = null): ?array
    {
        $config = $this->activeConfig($moduleCode, $divisionId);

        if (! $config) {
            return null;
        }

        $step = $config->steps()
            ->where('status_from', $statusFrom)
            ->where(function ($q) use ($amount) {
                $q->whereNull('min_amount')->orWhere('min_amount', '<=', $amount);
            })
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->orderBy('step_no')
            ->first();

        if (! $step) {
            return null;
        }

        $login = Auth::user()->login ?? null;

        if ($step->step_type === 'APPROVAL') {
            FlowLog::create([
                'module_code' => $moduleCode,
                'doc_id' => $docId,
                'flow_config_id' => $config->flow_config_id,
                'flow_step_id' => $step->flow_step_id,
                'status_from' => $statusFrom,
                'status_to' => $statusFrom,
                'actor_position_id' => $step->position_id,
                'actual_user_id' => null,
                'is_acting' => false,
                'action' => 'SUBMITTED',
                'amount_snapshot' => $amount,
                'notes' => null,
                'created_by' => $login,
            ]);

            return [
                'status' => $statusFrom,
                'pending_step_id' => $step->flow_step_id,
                'pending_position_id' => $step->position_id,
                'pending_label' => $step->label,
            ];
        }

        // step_type AUTO/NOTIFY_ONLY: langsung diterapkan, tak menunggu manusia.
        FlowLog::create([
            'module_code' => $moduleCode,
            'doc_id' => $docId,
            'flow_config_id' => $config->flow_config_id,
            'flow_step_id' => $step->flow_step_id,
            'status_from' => $statusFrom,
            'status_to' => $step->status_to,
            'actor_position_id' => $step->position_id,
            'actual_user_id' => null,
            'is_acting' => false,
            'action' => $step->step_type === 'AUTO' ? 'AUTO_ADVANCED' : 'SUBMITTED',
            'amount_snapshot' => $amount,
            'notes' => null,
            'created_by' => $login,
        ]);

        return [
            'status' => $step->status_to,
            'pending_step_id' => null,
            'pending_position_id' => null,
            'pending_label' => null,
        ];
    }

    // Resolusi actor: PLT aktif (flow_position_acting, rentang tanggal mencakup
    // hari ini) menggantikan pemegang tetap -- TIER_2_FLOW_ENGINE §2.
    public function resolveActingUser(int $positionId): ?int
    {
        $acting = FlowPositionActing::query()
            ->where('position_id', $positionId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        return $acting?->acting_user_id;
    }

    public function activeConfig(string $moduleCode, ?int $divisionId = null): ?FlowConfig
    {
        return FlowConfig::query()
            ->where('module_code', $moduleCode)
            ->where('is_active', true)
            ->where(function ($q) use ($divisionId) {
                $q->where('division_id', $divisionId)->orWhereNull('division_id');
            })
            ->orderByRaw('division_id IS NULL') // config spesifik divisi menang atas company-wide
            ->first();
    }

    public function history(string $moduleCode, int $docId)
    {
        return FlowLog::query()
            ->where('module_code', $moduleCode)
            ->where('doc_id', $docId)
            ->orderBy('acted_at')
            ->get();
    }
}
