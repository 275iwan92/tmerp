<?php

/*
 * REMARK
 * Tujuan       : Merekam susunan urutan officer per mesin: TRIAL saat PROCESS lulus (konteks, ditimpa PROCESS berikutnya), FINAL saat APPLY (label latih).
 * Sumber hulu  : Ppic::operations() (urutan Draft per mesin), prd_wo_operation.pending_from (posisi engine sebelum digeser officer), prd_plan_decision SEQ_USE.
 * Input        : id WO, posisi engine yang ditangkap sebelum PROCESS, id run APPLY.
 * Output       : baris prd_plan_trace. Kegagalan apa pun hanya dicatat ke log; PROCESS dan APPLY tidak pernah digagalkan.
 * Dipakai oleh : PpicController::processWo (capturePins, recordTrial), PlanConfirmService::confirm (recordFinal).
 * Status       : Aktif. Mesin bermasalah / job ditarik dicatat sebagai konteks (machine_trouble, pull_reason), tanpa mengubah solver.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

use App\Models\Prd\PrdPlanDecision;
use App\Models\Prd\PrdPlanTrace;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TraceRecorder
{
    /** Engine placement (draft start) of every operation the officer dragged, taken before PROCESS clears the pending moves. @return array<int, string> */
    public static function capturePins(): array
    {
        try {
            $pins = [];
            $ops = DB::connection('tm2prd')->table('prd_wo_operation')->where('pending_move', 1)->whereNotNull('pending_from')->get(['operation_id', 'pending_from']);
            foreach ($ops as $o) {
                $from = json_decode((string) $o->pending_from, true) ?: [];
                if (! empty($from['draft_start'])) {
                    $pins[(int) $o->operation_id] = (string) $from['draft_start'];
                }
            }

            return $pins;
        } catch (\Throwable $e) {
            Log::warning('sequence: capturePins failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * TRIAL snapshot of every machine the Work Order is planned on: all movable Draft operations of the machine in the officer's order.
     * The previous TRIAL of this Work Order and machine is replaced, never stacked.
     *
     * @param  array<int, string>  $pins
     */
    public static function recordTrial(int $woId, array $pins = []): void
    {
        try {
            Ppic::forget();
            $ops = Ppic::operations();
            $ctx = FeatureSet::context($ops);
            foreach (self::machinesOf($ops, $woId) as $machine) {
                $rows = self::arrangement($ops, $machine);
                if (count($rows) < 1) {
                    continue;
                }
                $key = 'T'.$woId.'-'.$machine;
                $engineStart = [];
                foreach ($rows as $r) {
                    $engineStart[$r['op_id']] = isset($pins[$r['op_id']]) ? strtotime($pins[$r['op_id']]) : $r['start']->getTimestamp();
                }
                self::write($rows, $ctx, $engineStart, 'TRIAL', $key, $woId, $machine, null, false, (string) DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->value('process_hash'));
            }
        } catch (\Throwable $e) {
            Log::warning('sequence: recordTrial failed: '.$e->getMessage());
        }
    }

    /** APPLY: the arrangement now in force becomes the training label (FINAL); the TRIAL of this Work Order is dropped. */
    public static function recordFinal(int $woId, int $runId): void
    {
        try {
            Ppic::forget();
            $all = Ppic::operations();
            $ctx = FeatureSet::context($all);
            $db = DB::connection('tm2prd');
            foreach (self::machinesOf($all, $woId, true) as $machine) {
                // the applied operations (now confirmed) and every other movable Draft operation of the machine
                $rows = array_values(array_filter($all, fn ($o) => $o['machine_id'] === $machine && $o['start']
                    && (($o['wo_id'] === $woId) || ($o['plan_status'] === 'DRAFT' && ! $o['locked']))));
                usort($rows, fn ($a, $b) => $a['start'] <=> $b['start'] ?: $a['op_id'] <=> $b['op_id']);
                if (count($rows) < 1) {
                    continue;
                }
                $trial = PrdPlanTrace::where('snapshot_wo_id', $woId)->where('machine_id', $machine)->where('status', 'TRIAL')->get()->keyBy('wo_operation_id');
                $engineStart = [];
                foreach ($rows as $i => $r) {
                    $engineStart[$r['op_id']] = isset($trial[$r['op_id']]) ? (int) $trial[$r['op_id']]->engine_pos : $i;
                }
                self::write($rows, $ctx, $engineStart, 'FINAL', 'F'.$runId.'-'.$woId.'-'.$machine, $woId, $machine, $runId, self::usedSuggestion($machine, array_column($rows, 'op_id')), null);
            }
            $db->table('prd_plan_trace')->where('snapshot_wo_id', $woId)->where('status', 'TRIAL')->delete();
            // a Work Order cancelled before APPLY leaves no trace in the training data
            $dead = $db->table('prd_wo')->where('wo_status', 'CANCELLED')->pluck('wo_id')->all();
            if ($dead) {
                $db->table('prd_plan_trace')->whereIn('snapshot_wo_id', $dead)->where('status', 'TRIAL')->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('sequence: recordFinal failed: '.$e->getMessage());
        }
    }

    /** Machines this Work Order has operations on (Draft, or confirmed just now when $confirmed). @return list<int> */
    private static function machinesOf(array $ops, int $woId, bool $confirmed = false): array
    {
        return array_values(array_unique(array_map(fn ($o) => $o['machine_id'], array_filter($ops, fn ($o) => $o['wo_id'] === $woId && $o['machine_id'] && $o['start']
            && ($confirmed || $o['plan_status'] === 'DRAFT')))));
    }

    /** Movable Draft operations on a machine, in the officer's current order. @return list<array<string, mixed>> */
    public static function arrangement(array $ops, int $machine): array
    {
        $rows = array_values(array_filter($ops, fn ($o) => $o['machine_id'] === $machine && $o['plan_status'] === 'DRAFT' && ! $o['locked'] && $o['start']
            && ! in_array($o['task_status'], ['RUNNING', 'DONE', 'QC_HOLD', 'QC_PASSED', 'QC_REJECTED'], true)));
        usort($rows, fn ($a, $b) => $a['start'] <=> $b['start'] ?: $a['op_id'] <=> $b['op_id']);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  in officer order
     * @param  array<int, int>  $engineStart  op id => engine time (or rank); only the order matters
     */
    private static function write(array $rows, array $ctx, array $engineStart, string $status, string $key, int $snapshotWo, int $machine, ?int $runId, bool $fromSuggestion, ?string $hash): void
    {
        $order = $engineStart;
        asort($order);
        $enginePos = array_flip(array_keys($order));
        $trouble = collect($rows)->pluck('machine_status')->filter(fn ($s) => $s && ! in_array(strtoupper((string) $s), ['ACTIVE', 'RUNNING', 'READY', 'IDLE', 'OK'], true))->first();
        $blocked = collect($rows)->first(fn ($o) => in_array('BLOCK', $o['gates'], true) || in_array('WAIT', $o['gates'], true));
        $reason = $blocked ? mb_substr($blocked['wo_no'].': '.($blocked['gate_note'] ?: 'material or tooling not ready; the next ready job is pulled'), 0, 190) : null;
        $db = DB::connection('tm2prd');
        $db->transaction(function () use ($rows, $ctx, $enginePos, $status, $key, $snapshotWo, $machine, $runId, $fromSuggestion, $hash, $trouble, $reason, $db) {
            $db->table('prd_plan_trace')->where('snapshot_key', $key)->delete();
            if ($status === 'TRIAL') {
                $db->table('prd_plan_trace')->where('snapshot_wo_id', $snapshotWo)->where('machine_id', $machine)->where('status', 'TRIAL')->delete();
            }
            foreach ($rows as $i => $o) {
                PrdPlanTrace::create(['snapshot_wo_id' => $snapshotWo, 'snapshot_key' => $key, 'wo_id' => $o['wo_id'], 'wo_operation_id' => $o['op_id'], 'machine_id' => $machine,
                    'process_id' => $o['process_id'], 'status' => $status, 'process_hash' => $hash, 'engine_pos' => $enginePos[$o['op_id']] ?? $i, 'officer_pos' => $i,
                    'features' => FeatureSet::vector($o, $ctx), 'pull_reason' => $reason, 'machine_trouble' => $trouble ? mb_substr((string) $trouble, 0, 190) : null,
                    'from_suggestion' => $fromSuggestion, 'run_id' => $runId]);
            }
        });
    }

    /** True when the order now on the machine is exactly the one the officer took with USE (SEQ_USE) and left unchanged. */
    private static function usedSuggestion(int $machine, array $currentOrder): bool
    {
        $use = PrdPlanDecision::where('event', 'SEQ_USE')->where('old_value', 'm'.$machine)->orderByDesc('decision_id')->first();
        if (! $use) {
            return false;
        }
        $ids = array_map('intval', explode(',', (string) $use->new_value));

        return array_values(array_intersect($currentOrder, $ids)) === $ids;
    }
}
