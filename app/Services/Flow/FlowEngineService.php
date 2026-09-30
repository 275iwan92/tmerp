<?php

namespace App\Services\Flow;

use App\Models\Master\FlowConfig;
use App\Models\Master\FlowConfigStep;
use App\Models\Master\FlowLog;
use App\Models\Master\FlowPositionActing;
use App\Models\Sys\SecUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

// Engine approval + state-transition generik lintas modul (TIER_1/2/3_FLOW_ENGINE.md).
// Implementasi tahap ini: submit() dokumen ke step APPROVAL pertama yang cocok
// (status_from + rentang amount). approve()/reject() menyusul saat layar
// approval (PrApproval, dst) dibangun -- lihat procurement_state.yaml next_step.
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
            ->where('activate', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        return $acting?->acting_user_id;
    }

    public function activeConfig(string $moduleCode, ?int $divisionId = null): ?FlowConfig
    {
        return FlowConfig::query()
            ->where('module_code', $moduleCode)
            ->where('activate', true)
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

    // Antrian approval user login ini: posisi tetapnya + posisi manapun yang
    // sedang di-PLT-kan ke dia (TIER_2_FLOW_ENGINE §2). "Pending" = baris
    // flow_log TERAKHIR utk doc itu masih action=SUBMITTED di posisi tsb --
    // begitu di-approve/reject, baris terbaru berubah jadi APPROVED/REJECTED
    // (atau SUBMITTED lagi tapi utk step berikutnya/posisi lain).
    public function pendingForUser(string $moduleCode, int $userId): Collection
    {
        $user = SecUser::find($userId);
        $ownPositionId = $user?->currentPositionId();

        $pltPositionIds = FlowPositionActing::where('acting_user_id', $userId)
            ->where('activate', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->pluck('position_id');

        $positionIds = collect([$ownPositionId])->filter()->merge($pltPositionIds)->unique()->values();

        if ($positionIds->isEmpty()) {
            return collect();
        }

        $latestIds = FlowLog::where('module_code', $moduleCode)
            ->selectRaw('MAX(flow_log_id) as id')
            ->groupBy('doc_id')
            ->pluck('id');

        // HOLD tidak mengubah status_to (dokumen tetap di posisi yang sama),
        // jadi tetap dianggap "pending" -- approver yang menahan tetap
        // melihatnya di antrian sendiri sampai dia putuskan Approve/Tolak.
        return FlowLog::whereIn('flow_log_id', $latestIds)
            ->whereIn('action', ['SUBMITTED', 'HOLD'])
            ->whereIn('actor_position_id', $positionIds)
            ->orderByDesc('acted_at')
            ->get();
    }

    // Setujui step yang sedang pending. Bila ada step berikutnya yang match
    // (status_from = status_to step ini, dalam rentang amount) -> catat sbg
    // pending baru (APPROVAL) atau eksekusi langsung (AUTO/NOTIFY_ONLY).
    // Bila tidak ada -> flow selesai di status_to step ini.
    public function approve(string $moduleCode, int $docId, int $stepId, float $amount): array
    {
        $step = FlowConfigStep::findOrFail($stepId);
        $userId = Auth::id();
        $login = Auth::user()->login ?? null;

        $isActing = FlowPositionActing::where('position_id', $step->position_id)
            ->where('activate', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->where('acting_user_id', $userId)
            ->exists();

        FlowLog::create([
            'module_code' => $moduleCode,
            'doc_id' => $docId,
            'flow_config_id' => $step->flow_config_id,
            'flow_step_id' => $step->flow_step_id,
            'status_from' => $step->status_from,
            'status_to' => $step->status_to,
            'actor_position_id' => $step->position_id,
            'actual_user_id' => $userId,
            'is_acting' => $isActing,
            'action' => 'APPROVED',
            'amount_snapshot' => $amount,
            'notes' => null,
            'created_by' => $login,
        ]);

        $nextStep = FlowConfigStep::where('flow_config_id', $step->flow_config_id)
            ->where('activate', true)
            ->where('step_no', '>', $step->step_no)
            ->where('status_from', $step->status_to)
            ->where(function ($q) use ($amount) {
                $q->whereNull('min_amount')->orWhere('min_amount', '<=', $amount);
            })
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->orderBy('step_no')
            ->first();

        // Setting Approval (2026-09-30): level antara SUBMITTED -> SUBMITTED, status akhir dari header (final_status).
        if (! $nextStep) {
            $final = FlowConfig::whereKey($step->flow_config_id)->value('final_status');

            return ['status' => $final ?: $step->status_to, 'final' => true, 'pending_step_id' => null, 'pending_position_id' => null];
        }

        if ($nextStep->step_type !== 'APPROVAL') {
            FlowLog::create([
                'module_code' => $moduleCode, 'doc_id' => $docId,
                'flow_config_id' => $nextStep->flow_config_id, 'flow_step_id' => $nextStep->flow_step_id,
                'status_from' => $nextStep->status_from, 'status_to' => $nextStep->status_to,
                'actor_position_id' => $nextStep->position_id, 'actual_user_id' => null, 'is_acting' => false,
                'action' => $nextStep->step_type === 'AUTO' ? 'AUTO_ADVANCED' : 'SUBMITTED',
                'amount_snapshot' => $amount, 'notes' => null, 'created_by' => $login,
            ]);

            return ['status' => $nextStep->status_to, 'final' => true, 'pending_step_id' => null, 'pending_position_id' => null];
        }

        FlowLog::create([
            'module_code' => $moduleCode, 'doc_id' => $docId,
            'flow_config_id' => $nextStep->flow_config_id, 'flow_step_id' => $nextStep->flow_step_id,
            'status_from' => $nextStep->status_from, 'status_to' => $nextStep->status_from,
            'actor_position_id' => $nextStep->position_id, 'actual_user_id' => null, 'is_acting' => false,
            'action' => 'SUBMITTED', 'amount_snapshot' => $amount, 'notes' => null, 'created_by' => $login,
        ]);

        return [
            'status' => $step->status_to,
            'final' => false,
            'pending_step_id' => $nextStep->flow_step_id,
            'pending_position_id' => $nextStep->position_id,
            'pending_label' => $nextStep->label,
        ];
    }

    // Tolak step yang sedang pending. status_to_reject wajib ada (divalidasi
    // caller) -- TIER_2_FLOW_ENGINE §4. Alasan wajib diisi.
    public function reject(string $moduleCode, int $docId, int $stepId, string $reason, float $amount): array
    {
        $step = FlowConfigStep::findOrFail($stepId);
        $userId = Auth::id();
        $login = Auth::user()->login ?? null;

        $isActing = FlowPositionActing::where('position_id', $step->position_id)
            ->where('activate', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->where('acting_user_id', $userId)
            ->exists();

        FlowLog::create([
            'module_code' => $moduleCode,
            'doc_id' => $docId,
            'flow_config_id' => $step->flow_config_id,
            'flow_step_id' => $step->flow_step_id,
            'status_from' => $step->status_from,
            'status_to' => $step->status_to_reject,
            'actor_position_id' => $step->position_id,
            'actual_user_id' => $userId,
            'is_acting' => $isActing,
            'action' => 'REJECTED',
            'amount_snapshot' => $amount,
            'notes' => $reason,
            'created_by' => $login,
        ]);

        return ['status' => FlowConfig::whereKey($step->flow_config_id)->value('reject_status') ?: $step->status_to_reject];
    }

    // Hold: approver belum mau memutuskan (mis. cash flow belum siap) --
    // BUKAN approve/reject, status dokumen TIDAK berubah, dokumen tetap
    // muncul di antrian approver yang sama utk diputuskan lagi nanti.
    // Cuma catatan (notes wajib, divalidasi caller) yang tercatat di riwayat.
    public function hold(string $moduleCode, int $docId, int $stepId, string $reason, float $amount): array
    {
        $step = FlowConfigStep::findOrFail($stepId);
        $userId = Auth::id();
        $login = Auth::user()->login ?? null;

        $isActing = FlowPositionActing::where('position_id', $step->position_id)
            ->where('activate', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->where('acting_user_id', $userId)
            ->exists();

        FlowLog::create([
            'module_code' => $moduleCode,
            'doc_id' => $docId,
            'flow_config_id' => $step->flow_config_id,
            'flow_step_id' => $step->flow_step_id,
            'status_from' => $step->status_from,
            'status_to' => $step->status_from,
            'actor_position_id' => $step->position_id,
            'actual_user_id' => $userId,
            'is_acting' => $isActing,
            'action' => 'HOLD',
            'amount_snapshot' => $amount,
            'notes' => $reason,
            'created_by' => $login,
        ]);

        return ['status' => $step->status_from];
    }
}
