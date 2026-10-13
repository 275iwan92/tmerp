<?php

/*
 * REMARK
 * Tujuan       : Timer operator di mesin: dua timer per operasi (SETUP dan PRODUCTION), Play / Pause / Stop, dan simpan hasil Good / Bad.
 * Sumber hulu  : Antrean CONFIRMED dari PPIC Planning (prd_wo_operation); durasi dari prd_operation_time_log; Stop dari prd_output_action_log.
 * Input        : Operasi WO, timer (setup | prod), aksi (play | pause | stop), qty good / bad, login pelaku.
 * Output       : prd_operation_time_log (segmen SETUP / RUN / PAUSE), prd_wo_operation (task_status, actual_start / actual_finish / actual_setup_min),
 *                prd_wo_operation_output + _defect (hasil; BAD terpecah per alasan, satu baris defect per alasan), prd_output_action_log (satu baris per aksi, lewat OutputActionLog).
 * Dipakai oleh : PpicController::outputTimer / outputResult (layar Production Output), Ppic::shopFloor (cermin PPIC), ProductionOutput.
 * Status       : Aktif di mockup (ddt*).
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Operator;

use App\Models\Prd\PrdOutputActionLog;
use App\Models\Prd\PrdWoOperation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * One timer runs per machine at a time. Play on one timer pauses whatever else runs on that machine; Stop locks that timer.
 * Time is the sum of the log segments of its kind (prd_operation_time_log); quantities go to the same output tables the Work Order
 * screen uses (no second output table). Every action also writes one row to prd_output_action_log in the same transaction.
 */
final class OperatorTimerService
{
    public const PAUSE_REASONS = ['MATERIAL' => 'Waiting material', 'BREAK' => 'Break', 'MACHINE' => 'Machine problem', 'QUALITY' => 'Quality hold', 'CREW' => 'Waiting crew', 'OTHER' => 'Other'];


    /** Timer on the screen => kind written to prd_operation_time_log (and prefix of its log actions: SETUP_PLAY, RUN_STOP, ...). */
    public const TIMERS = ['setup' => 'SETUP', 'prod' => 'RUN'];

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
     * State of both timers of several operations: state idle | run | pause | stop, seconds worked, first start, stop time.
     * Stop is read from the action log (a stopped timer is locked); a legacy operation already DONE counts as stopped.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, array{state: string, seconds: int, first: ?string, stop: ?string}>>
     */
    public function timersFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $segments = $this->log()->whereIn('operation_id', $ids)->orderBy('log_id')->get()->groupBy('operation_id');
        $stops = PrdOutputActionLog::query()->whereIn('operation_id', $ids)->whereIn('action', OutputActionLog::STOPS)->get(['operation_id', 'action', 'acted_at'])
            ->groupBy('operation_id');
        $done = DB::connection('tm2prd')->table('prd_wo_operation')->whereIn('operation_id', $ids)->where('task_status', 'DONE')->pluck('operation_id')->flip();
        $now = CarbonImmutable::now();

        $out = [];
        foreach ($ids as $id) {
            foreach (self::TIMERS as $timer => $kind) {
                $segs = ($segments[$id] ?? collect())->where('kind', $kind);
                $stop = ($stops[$id] ?? collect())->firstWhere('action', $kind.'_STOP');
                $seconds = (int) $segs->sum(fn ($s) => max(0, CarbonImmutable::parse($s->started_at)->diffInSeconds($s->ended_at ? CarbonImmutable::parse($s->ended_at) : $now, false)));
                $running = $segs->contains(fn ($s) => $s->ended_at === null);
                $locked = $stop || ($kind === 'RUN' && isset($done[$id]));
                $out[$id][$timer] = [
                    'state' => $locked ? 'stop' : ($running ? 'run' : ($segs->isNotEmpty() ? 'pause' : 'idle')),
                    'seconds' => $seconds,
                    'first' => $segs->isNotEmpty() ? CarbonImmutable::parse($segs->first()->started_at)->toIso8601String() : null,
                    'stop' => $stop ? CarbonImmutable::parse($stop->acted_at)->toIso8601String() : null,
                ];
            }
        }

        return $out;
    }

    /** @return array<string, array{state: string, seconds: int, first: ?string, stop: ?string}> */
    public function timers(int $operationId): array
    {
        return $this->timersFor([$operationId])[$operationId];
    }

    /**
     * Job status shown in LIST OF JOBS from the two timers: setup | prod | pause | done | idle.
     * Setup stopped but production not started yet reads as pause (the machine waits).
     *
     * @param  array<string, array{state: string}>  $t
     */
    public static function statusKey(array $t): string
    {
        $a = $t['setup']['state'];
        $b = $t['prod']['state'];

        return match (true) {
            $a === 'run' => 'setup',
            $b === 'run' => 'prod',
            $b === 'stop' => 'done',
            $a === 'pause', $b === 'pause', $a === 'stop' => 'pause',
            default => 'idle',
        };
    }

    /**
     * Play / Pause / Stop of one timer. Only one timer runs on a machine: Play closes whatever else is open on this machine (it
     * becomes paused), Pause starts a PAUSE segment, Stop closes the timer for good (and Stop of production finishes the operation).
     *
     * @return array<string, array{state: string, seconds: int, first: ?string, stop: ?string}> both timers afterwards
     *
     * @throws \DomainException when the move is not allowed
     */
    public function act(int $operationId, string $timer, string $action, string $operator): array
    {
        $kind = self::TIMERS[$timer] ?? throw new \DomainException(t('Unknown timer.'));
        if (! in_array($action, ['play', 'pause', 'stop'], true)) {
            throw new \DomainException(t('Unknown action.'));
        }
        $op = PrdWoOperation::findOrFail($operationId);

        DB::connection('tm2prd')->transaction(function () use ($op, $timer, $kind, $action, $operator) {
            $before = $this->timers((int) $op->operation_id)[$timer]['state'];
            if ($before === 'stop') {
                throw new \DomainException(t('This timer is stopped and locked.'));
            }
            $open = $this->open((int) $op->machine_id);
            // Only a SETUP / RUN segment of another job blocks the machine; a paused job (open PAUSE segment) lets the next job start.
            if ($open && (int) $open->operation_id !== (int) $op->operation_id && in_array($open->kind, ['SETUP', 'RUN'], true)) {
                throw new \DomainException(t('Another job has a running timer on this machine. Pause or Stop it first.'));
            }
            if ($action === 'pause' && $before !== 'run') {
                throw new \DomainException(t('There is no running timer to pause for this job.'));
            }
            // Stop of a timer that never started would lock the job (finish() marks the operation DONE).
            if ($action === 'stop' && $before === 'idle') {
                throw new \DomainException(t('Start the timer before stopping it.'));
            }
            if ($action === 'play' && $before === 'run') {
                return;
            }

            if ($open) {
                $this->close($open);
            }
            if ($action === 'play') {
                $this->begin($op, $kind, null, $operator);
                $this->markRunning($op);
            } elseif ($action === 'pause') {
                $this->begin($op, 'PAUSE', 'OTHER', $operator);
            } else {
                $this->finish($op, $kind, $operator);
            }
            $after = $action === 'play' ? 'run' : ($action === 'pause' ? 'pause' : 'stop');
            OutputActionLog::record($op, $kind.'_'.strtoupper($action), ['before' => $before, 'after' => $after], $operator);
        });

        return $this->timers($operationId);
    }

    /**
     * SIMPAN HASIL: Good and Bad are added to what is saved (accumulative). Bad is stored as a reject line (reason "Lainnya").
     *
     * @param  list<array{scrap_type_id: int, qty: float}>  $defects  BAD split by reason (multi row); empty = one row with the default reason
     * @return array{good: float, bad: float} totals saved for the operation
     *
     * @throws \DomainException when both are zero or negative, or the reason rows do not add up to BAD
     */
    public function saveResult(int $operationId, float $good, float $bad, string $operator, array $defects = []): array
    {
        if ($good < 0 || $bad < 0 || ($good == 0.0 && $bad == 0.0)) {
            throw new \DomainException(t('Enter GOOD or BAD first.'));
        }
        if ($defects !== []) {
            $valid = array_keys($this->rejectReasons());
            foreach ($defects as $d) {
                if (! in_array((int) $d['scrap_type_id'], $valid, true) || (float) $d['qty'] <= 0) {
                    throw new \DomainException(t('Choose a reason and a quantity for every BAD row.'));
                }
            }
            if (abs(array_sum(array_column($defects, 'qty')) - $bad) > 0.0001) {
                throw new \DomainException(t('The BAD reasons must add up to the BAD quantity.'));
            }
        }
        $op = PrdWoOperation::findOrFail($operationId);
        $prd = DB::connection('tm2prd');

        $prd->transaction(function () use ($op, $good, $bad, $operator, $prd, $defects) {
            $before = $this->results((int) $op->operation_id);
            $outputId = $prd->table('prd_wo_operation_output')->insertGetId([
                'operation_id' => $op->operation_id, 'qty_good' => $good, 'notes' => 'Entry (Production Output)', 'recorded_by' => $operator, 'recorded_at' => now(), 'created_at' => now(),
            ]);
            if ($bad > 0) {
                if ($defects === []) {
                    $scrap = $prd->table('prd_scrap_type')->where('category', 'REJECT')->where('activate', 1)->orderByRaw("type_name = 'Lainnya' desc")->orderBy('type_id')->value('type_id');
                    if (! $scrap) {
                        throw new \DomainException(t('No reject reason is set up for BAD quantity.'));
                    }
                    $defects = [['scrap_type_id' => $scrap, 'qty' => $bad]];
                }
                foreach ($defects as $d) {
                    $prd->table('prd_wo_operation_output_defect')->insert(['output_id' => $outputId, 'scrap_type_id' => (int) $d['scrap_type_id'], 'qty' => (float) $d['qty'], 'created_at' => now()]);
                }
            }
            OutputActionLog::record($op, 'SAVE_RESULT', ['before' => $before, 'add' => ['good' => $good, 'bad' => $bad], 'after' => $this->results((int) $op->operation_id)], $operator);
        });

        return $this->results($operationId);
    }

    /** Active REJECT reasons for the BAD pop-up. @return array<int, string> id => name */
    public function rejectReasons(): array
    {
        return DB::connection('tm2prd')->table('prd_scrap_type')->where('category', 'REJECT')->where('activate', 1)->orderBy('type_name')->pluck('type_name', 'type_id')
            ->map(fn ($n) => (string) $n)->all();
    }

    /** Good and bad quantity saved so far for one operation. @return array{good: float, bad: float} */
    public function results(int $operationId): array
    {
        $prd = DB::connection('tm2prd');
        $good = (float) $prd->table('prd_wo_operation_output')->where('operation_id', $operationId)->sum('qty_good');
        $bad = (float) $prd->table('prd_wo_operation_output as o')->join('prd_wo_operation_output_defect as d', 'd.output_id', '=', 'o.output_id')
            ->where('o.operation_id', $operationId)->sum('d.qty');

        return ['good' => $good, 'bad' => $bad];
    }

    /** Minutes by kind and the good quantity so far for one operation. @return array{setup: int, run: int, pause: int, good: float} */
    public function summary(int $operationId): array
    {
        $rows = $this->log()->where('operation_id', $operationId)->get(['kind', 'minutes', 'started_at', 'ended_at']);
        $min = fn (string $k) => (int) $rows->where('kind', $k)->sum(fn ($r) => $r->ended_at ? (int) $r->minutes : (int) floor(CarbonImmutable::parse($r->started_at)->diffInSeconds(now(), false) / 60));

        return ['setup' => $min('SETUP'), 'run' => $min('RUN'), 'pause' => $min('PAUSE'),
            'good' => (float) DB::connection('tm2prd')->table('prd_wo_operation_output')->where('operation_id', $operationId)->sum('qty_good')];
    }

    /** Stop: close the open segment; Stop of production finishes the operation, Stop of setup stores the setup minutes. */
    private function finish(PrdWoOperation $op, string $kind, string $operator): void
    {
        $setupMinutes = (int) round($this->timers((int) $op->operation_id)['setup']['seconds'] / 60);
        $op->forceFill(['actual_setup_min' => $setupMinutes, 'updated_by' => $operator]);
        if ($kind === 'RUN') {
            $op->actual_finish = now();
            if ($op->task_status === 'ASSIGNED' || $op->task_status === 'PENDING') {
                $op->task_status = 'RUNNING';
            }
            if ($op->canTransitionTo('DONE')) {
                $op->task_status = 'DONE';
            }
        }
        $op->save();
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
