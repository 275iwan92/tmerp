<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Operator;

use App\Models\Prd\PrdWoOperation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The operator timer at the machine: Play / Pause / Stop on the confirmed queue, written to prd_operation_time_log.
 * One timer runs per machine at a time. Play on a waiting job starts SETUP, Play again starts RUN, Pause records a reason, Stop closes the job
 * with good / waste / reject quantities. Quantities go to the same output tables the Work Order screen uses (no second output table);
 * the time log feeds Monitoring and LearningEngine.
 */
final class OperatorTimerService
{
    public const PAUSE_REASONS = ['MATERIAL' => 'Waiting material', 'BREAK' => 'Break', 'MACHINE' => 'Machine problem', 'QUALITY' => 'Quality hold', 'CREW' => 'Waiting crew', 'OTHER' => 'Other'];

    public const CHANGE_KINDS = ['COLOR' => 'Colour change', 'INK_TYPE' => 'Ink type change', 'SUBSTRATE' => 'Substrate change', 'SIZE' => 'Size change', 'PLATE' => 'Plate change', 'DIE' => 'Die change'];

    private function log()
    {
        return DB::connection('tm2prd')->table('prd_operation_time_log');
    }

    /** The open segment on a machine (at most one), or null. */
    public function open(int $machineId): ?object
    {
        return $this->log()->where('machine_id', $machineId)->whereNull('ended_at')->orderByDesc('log_id')->first();
    }

    /** Seconds of one segment up to now. */
    public function elapsed(object $segment, ?CarbonImmutable $now = null): int
    {
        return max(0, (int) CarbonImmutable::parse($segment->started_at)->diffInSeconds($now ?? CarbonImmutable::now(), false));
    }

    /**
     * Play: waiting job -> SETUP; SETUP -> RUN; PAUSE -> back to what it was before the pause.
     *
     * @throws \DomainException when another job already has a running timer on this machine
     */
    public function play(int $operationId, string $operator): string
    {
        $op = PrdWoOperation::findOrFail($operationId);
        $machineId = (int) $op->machine_id;
        $open = $this->open($machineId);
        if ($open && (int) $open->operation_id !== $operationId) {
            throw new \DomainException(t('Another job has a running timer on this machine. Pause or Stop it first.'));
        }

        $next = 'SETUP';
        if ($open) {
            $prior = $this->log()->where('operation_id', $operationId)->where('kind', '!=', 'PAUSE')->orderByDesc('log_id')->value('kind');
            $next = $open->kind === 'SETUP' ? 'RUN' : ($open->kind === 'PAUSE' ? ($prior ?: 'RUN') : $open->kind);
            if ($open->kind === 'RUN') {
                return 'RUN';
            }
            $this->close($open);
        } elseif ($this->log()->where('operation_id', $operationId)->where('kind', 'RUN')->exists()) {
            $next = 'RUN';
        }

        $this->begin($op, $next, null, $operator);
        $this->markRunning($op);

        return $next;
    }

    /** @throws \DomainException when there is no running timer for this job */
    public function pause(int $operationId, string $reason, string $operator): void
    {
        $op = PrdWoOperation::findOrFail($operationId);
        $open = $this->open((int) $op->machine_id);
        if (! $open || (int) $open->operation_id !== $operationId || $open->kind === 'PAUSE') {
            throw new \DomainException(t('There is no running timer to pause for this job.'));
        }
        if (! isset(self::PAUSE_REASONS[$reason])) {
            $reason = 'OTHER';
        }
        $this->close($open);
        $this->begin($op, 'PAUSE', $reason, $operator);
    }

    /**
     * Stop: close the timer, record good output and the waste / reject lines, finish the operation.
     *
     * @param  array{good: float, waste?: float, reject?: float, scrap_type_id?: ?int, change_kind?: ?string}  $q
     * @return array<string, float|int>
     */
    public function stop(int $operationId, array $q, string $operator): array
    {
        $op = PrdWoOperation::findOrFail($operationId);
        $open = $this->open((int) $op->machine_id);
        if ($open && (int) $open->operation_id === $operationId) {
            $this->close($open);
        }
        $good = max(0.0, (float) ($q['good'] ?? 0));
        $waste = max(0.0, (float) ($q['waste'] ?? 0));
        $reject = max(0.0, (float) ($q['reject'] ?? 0));

        $prd = DB::connection('tm2prd');
        $prd->transaction(function () use ($op, $q, $good, $waste, $reject, $operator, $prd) {
            $outputId = $prd->table('prd_wo_operation_output')->insertGetId([
                'operation_id' => $op->operation_id, 'qty_good' => $good, 'notes' => 'Stop (operator timer)', 'recorded_by' => $operator, 'recorded_at' => now(), 'created_at' => now(),
            ]);
            foreach ([['WASTE', $waste, null], ['REJECT', $reject, $q['scrap_type_id'] ?? null]] as [$category, $qty, $scrapId]) {
                $scrap = $scrapId ?: $prd->table('prd_scrap_type')->where('category', $category)->orderBy('type_id')->value('type_id');
                if ($qty > 0 && $scrap) {
                    $prd->table('prd_wo_operation_output_defect')->insert(['output_id' => $outputId, 'scrap_type_id' => $scrap, 'qty' => $qty, 'created_at' => now()]);
                }
            }
            if (! empty($q['change_kind']) && isset(self::CHANGE_KINDS[$q['change_kind']])) {
                $this->log()->where('operation_id', $op->operation_id)->where('kind', 'SETUP')->update(['change_kind' => $q['change_kind']]);
            }
            $op->forceFill(['actual_finish' => now(), 'actual_setup_min' => (int) $this->log()->where('operation_id', $op->operation_id)->where('kind', 'SETUP')->sum('minutes'), 'updated_by' => $operator]);
            if ($op->task_status === 'ASSIGNED' || $op->task_status === 'PENDING') {
                $op->task_status = 'RUNNING';
            }
            if ($op->canTransitionTo('DONE')) {
                $op->task_status = 'DONE';
            }
            $op->save();
        });

        return $this->summary($operationId) + ['good' => $good, 'waste' => $waste, 'reject' => $reject];
    }

    /** A checkpoint of good quantity during the run (the Add quantity button). */
    public function addQuantity(int $operationId, float $qty, string $operator): void
    {
        if ($qty <= 0) {
            throw new \DomainException(t('Enter a quantity above zero.'));
        }
        DB::connection('tm2prd')->table('prd_wo_operation_output')->insert([
            'operation_id' => $operationId, 'qty_good' => $qty, 'notes' => 'Checkpoint (operator timer)', 'recorded_by' => $operator, 'recorded_at' => now(), 'created_at' => now(),
        ]);
    }

    /** Minutes by kind and the good quantity so far for one operation. @return array{setup: int, run: int, pause: int, good: float} */
    public function summary(int $operationId): array
    {
        $rows = $this->log()->where('operation_id', $operationId)->get(['kind', 'minutes', 'started_at', 'ended_at']);
        $min = fn (string $k) => (int) $rows->where('kind', $k)->sum(fn ($r) => $r->ended_at ? (int) $r->minutes : (int) floor(CarbonImmutable::parse($r->started_at)->diffInSeconds(now(), false) / 60));

        return ['setup' => $min('SETUP'), 'run' => $min('RUN'), 'pause' => $min('PAUSE'),
            'good' => (float) DB::connection('tm2prd')->table('prd_wo_operation_output')->where('operation_id', $operationId)->sum('qty_good')];
    }

    private function begin(PrdWoOperation $op, string $kind, ?string $reason, string $operator): void
    {
        $this->log()->insert(['operation_id' => $op->operation_id, 'machine_id' => $op->machine_id, 'operator' => $operator, 'kind' => $kind, 'pause_reason' => $reason, 'started_at' => now(), 'created_at' => now()]);
    }

    private function close(object $segment): void
    {
        $seconds = $this->elapsed($segment);
        $this->log()->where('log_id', $segment->log_id)->update(['ended_at' => now(), 'minutes' => (int) round($seconds / 60)]);
    }

    private function markRunning(PrdWoOperation $op): void
    {
        if ($op->task_status === 'PENDING' && $op->canTransitionTo('ASSIGNED')) {
            $op->task_status = 'ASSIGNED';
        }
        if ($op->canTransitionTo('RUNNING')) {
            $op->task_status = 'RUNNING';
            $op->actual_start ??= now();
        }
        $op->save();
    }
}
