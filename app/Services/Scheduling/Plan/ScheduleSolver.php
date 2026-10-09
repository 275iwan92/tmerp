<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Services\Scheduling\WorkingCalendar;
use Carbon\CarbonImmutable;

/**
 * Finite-capacity scheduler (pure: no database). Serial schedule generation:
 *
 *  1. rank the Work Orders: layer 0 hot job, layer 1 critical ratio below 1 (smallest first), layer 2 by due date; inside layer 2,
 *     jobs whose due dates fall in one similar-urgency window are re-ordered by the setup-aware score;
 *  2. place the operations of each Work Order in sequence, each on the eligible machine that finishes it earliest
 *     (setup after the job before it, drying waits, material arrival, machine calendar and already placed jobs all counted).
 *
 * Operations already confirmed and locked (`fixed`) occupy their slot and are never moved.
 */
final class ScheduleSolver
{
    private const FALLBACK_RUN_MINUTES = 60;

    private const MANUAL_MINUTES = 30;

    private const LOAD_HORIZON_DAYS = 7;

    /** @var array<int, list<array{from: int, to: int, facts: array, opId: int}>> machine => blocks sorted by start (work minutes) */
    private array $blocks = [];

    /**
     * @param  array<string, mixed>  $setupRules  config('ppic.setup') shape
     * @param  array{urgency: float, setup: float, tier: float}  $weights
     */
    public function __construct(
        private readonly SetupTimeEngine $setup,
        private readonly array $setupRules,
        private readonly array $weights = ['urgency' => 0.50, 'setup' => 0.35, 'tier' => 0.15],
        private readonly float $similarWindowHours = 8.0,
        private readonly float $horizonHours = 72.0,
    ) {}

    /**
     * @param  list<PlanWo>  $wos
     */
    public function solve(array $wos, WorkingCalendar $cal, CarbonImmutable $now): PlanResult
    {
        $this->blocks = [];
        $result = new PlanResult;

        // Fixed (confirmed + locked) operations first: they occupy the machines.
        foreach ($wos as $wo) {
            foreach ($wo->ops as $op) {
                if ($op->fixed) {
                    $m = $op->fixed['machine'];
                    $this->occupy($m, $cal->workMinute($op->fixed['start'], $m), $cal->workMinute($op->fixed['finish'], $m), $op->facts, $op->id);
                }
            }
        }

        $ranked = $this->rank($wos, $cal, $now, $result);
        foreach ($ranked as $wo) {
            $this->placeWo($wo, $cal, $now, $result);
        }
        $result->loads = $this->loads($cal, $now);

        return $result;
    }

    // -------------------------------------------------------------------- ranking

    /**
     * @param  list<PlanWo>  $wos
     * @return list<PlanWo>
     */
    private function rank(array $wos, WorkingCalendar $cal, CarbonImmutable $now, PlanResult $result): array
    {
        $info = [];
        foreach ($wos as $wo) {
            $remaining = $this->remainingHours($wo);
            $m = $this->anyMachine($wo);
            $left = ($wo->due && $m !== null)
                ? max(0.0, ($cal->workMinute($wo->due, $m) - $cal->workMinute($now, $m)) / 60) : null;
            $cr = ($left !== null && $remaining > 0) ? $left / $remaining : null;
            $slack = $left !== null ? $left - $remaining : null;
            $layer = $wo->hot ? 0 : (($wo->moveUp || ($cr !== null && $cr < 1.0)) ? 1 : 2);
            $info[$wo->id] = ['wo' => $wo, 'cr' => $cr, 'slack' => $slack, 'layer' => $layer, 'remaining' => $remaining];
            $result->priority[$wo->id] = ['layer' => $layer, 'cr' => $cr === null ? null : round($cr, 3), 'slack_h' => $slack === null ? null : round($slack, 1)];
        }

        $l0 = array_filter($info, fn ($i) => $i['layer'] === 0);
        $l1 = array_filter($info, fn ($i) => $i['layer'] === 1);
        $l2 = array_filter($info, fn ($i) => $i['layer'] === 2);
        uasort($l0, fn ($a, $b) => [$a['cr'] ?? INF, $a['wo']->id] <=> [$b['cr'] ?? INF, $b['wo']->id]);
        uasort($l1, fn ($a, $b) => [$a['wo']->moveUp ? 0 : 1, $a['cr'] ?? INF, $a['wo']->id] <=> [$b['wo']->moveUp ? 0 : 1, $b['cr'] ?? INF, $b['wo']->id]);
        uasort($l2, fn ($a, $b) => [$a['wo']->due?->getTimestamp() ?? PHP_INT_MAX, $a['wo']->tier, $a['wo']->id] <=> [$b['wo']->due?->getTimestamp() ?? PHP_INT_MAX, $b['wo']->tier, $b['wo']->id]);

        $order = array_merge(array_values($l0), array_values($l1), $this->smooth(array_values($l2)));

        return array_map(fn ($i) => $i['wo'], $order);
    }

    /**
     * Inside layer 2: take jobs whose due dates are within the similar-urgency window of the first one and order that group by
     * the setup-aware score (nearest neighbour of the job before it). Setup savings never leave the window.
     *
     * @param  list<array{wo: PlanWo, slack: ?float}>  $items  already sorted by due date
     * @return list<array{wo: PlanWo, slack: ?float}>
     */
    private function smooth(array $items): array
    {
        $out = [];
        $prev = null;
        while ($items) {
            $first = $items[0];
            $groupIdx = [0];
            foreach ($items as $i => $it) {
                if ($i === 0) {
                    continue;
                }
                $gap = $this->hoursBetween($first['wo']->due, $it['wo']->due);
                if ($gap <= $this->similarWindowHours) {
                    $groupIdx[] = $i;
                }
            }
            $group = array_map(fn ($i) => $items[$i], $groupIdx);
            $maxDist = 1.0;
            foreach ($group as $g) {
                $maxDist = max($maxDist, $this->distance($prev, $g['wo']));
            }
            // Greedy: best score first, then re-evaluate against the chosen one.
            $remaining = $group;
            while ($remaining) {
                $best = null;
                $bestScore = -INF;
                foreach ($remaining as $k => $g) {
                    $urgency = $g['slack'] === null ? 0.5 : 1 - min(1.0, max(0.0, $g['slack'] / $this->horizonHours));
                    $score = $this->weights['urgency'] * $urgency
                        + $this->weights['setup'] * (1 - $this->distance($prev, $g['wo']) / $maxDist)
                        + $this->weights['tier'] * ((4 - min(3, max(1, $g['wo']->tier))) / 3);
                    if ($score > $bestScore + 1e-9) {
                        $bestScore = $score;
                        $best = $k;
                    }
                }
                $pick = $remaining[$best];
                $out[] = $pick;
                $prev = $pick['wo'];
                unset($remaining[$best]);
            }
            $items = array_values(array_filter($items, fn ($it, $i) => ! in_array($i, $groupIdx, true), ARRAY_FILTER_USE_BOTH));
        }

        return $out;
    }

    private function hoursBetween(?CarbonImmutable $a, ?CarbonImmutable $b): float
    {
        if (! $a || ! $b) {
            return INF;
        }

        return abs($b->getTimestamp() - $a->getTimestamp()) / 3600;
    }

    /** Distance between the first operation facts of two Work Orders (0 = same set-up). */
    private function distance(?PlanWo $a, PlanWo $b): float
    {
        $fb = $b->ops[0]->facts ?? [];
        if (! $a) {
            return 0.0;
        }
        $fa = $a->ops[0]->facts ?? [];
        $d = 0.0;
        $d += min(3.0, abs((float) ($fa['gsm'] ?? 0) - (float) ($fb['gsm'] ?? 0)) / 20);
        $d += (($fa['sheet_l'] ?? null) != ($fb['sheet_l'] ?? null) || ($fa['sheet_w'] ?? null) != ($fb['sheet_w'] ?? null)) ? 1.0 : 0.0;
        $d += (($fa['colours'] ?? 0) != ($fb['colours'] ?? 0) || ($fa['spots'] ?? []) != ($fb['spots'] ?? [])) ? 1.5 : 0.0;
        $d += (($fa['ink_type'] ?? 'CONVENTIONAL') !== ($fb['ink_type'] ?? 'CONVENTIONAL')) ? 3.0 : 0.0;
        $d += (($fa['die'] ?? null) !== ($fb['die'] ?? null)) ? 1.0 : 0.0;

        return $d;
    }

    /** Remaining processing hours of a Work Order using the fastest candidate of every operation. */
    private function remainingHours(PlanWo $wo): float
    {
        $minutes = 0.0;
        foreach ($wo->ops as $op) {
            if ($op->fixed) {
                continue;
            }
            $best = null;
            foreach ($op->candidates as $c) {
                $run = $this->runMinutes($op, $c);
                $total = $run + (float) ($c['setup_base'] ?? $this->setupRules['base_minutes'] ?? 15);
                $best = $best === null ? $total : min($best, $total);
            }
            $minutes += ($best ?? self::FALLBACK_RUN_MINUTES) + $op->waitAfterMinutes;
        }

        return $minutes / 60;
    }

    private function anyMachine(PlanWo $wo): ?int
    {
        foreach ($wo->ops as $op) {
            foreach ($op->candidates as $m => $_) {
                return (int) $m;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------- placement

    private function placeWo(PlanWo $wo, WorkingCalendar $cal, CarbonImmutable $now, PlanResult $result): void
    {
        $predFinish = $now;
        $blockedBy = null;
        foreach ($wo->ops as $op) {
            if ($op->fixed) {
                $predFinish = $op->fixed['finish']->addMinutes($op->waitAfterMinutes);

                continue;
            }
            if ($blockedBy !== null) {
                $result->exception($wo->id, $op->id, 'CHAIN_BLOCKED', $blockedBy);

                continue;
            }
            if ($op->gate === 'BLOCK') {
                $blockedBy = 'Waiting for: '.($op->gateNote ?: 'a blocked gate');
                $result->exception($wo->id, $op->id, 'BLOCKED', $op->gateNote ?: 'Gate blocked.');

                continue;
            }
            if ($op->kind !== 'MACHINE') {
                $earliest = $predFinish->greaterThan($now) ? $predFinish : $now;
                if ($op->minStart && $op->minStart->greaterThan($earliest)) {
                    $earliest = $op->minStart;
                }
                $p = $this->placeOutsideMachines($op, $wo, $cal, $earliest);
                $result->place($p);
                $predFinish = $p->finish->addMinutes($op->waitAfterMinutes);

                continue;
            }
            if (! $op->candidates) {
                $blockedBy = 'No eligible machine for '.$op->processName.'.';
                $result->exception($wo->id, $op->id, 'NO_MACHINE', 'No eligible machine for '.$op->processName.'.');

                continue;
            }

            $earliest = $predFinish->greaterThan($now) ? $predFinish : $now;
            if ($op->minStart && $op->minStart->greaterThan($earliest)) {
                $earliest = $op->minStart;
            }

            $best = null;
            foreach ($op->candidates as $m => $cand) {
                $m = (int) $m;
                $slot = $this->bestSlot($op, $m, $cand, $cal->workMinute($earliest, $m), $cal);
                // Compare real clock time: work minutes are counted on each machine's own calendar, so they are not comparable across machines.
                $key = [$cal->timeAt($slot['end'], $m, true)->getTimestamp(), $cand['rank'], $this->machineLoad($m), $m];
                if ($best === null || $key < $best['key']) {
                    $best = ['key' => $key, 'm' => $m, 'slot' => $slot, 'cand' => $cand];
                }
            }

            $m = $best['m'];
            $slot = $best['slot'];
            $this->occupy($m, $slot['start'], $slot['end'], $op->facts, $op->id);
            $start = $cal->timeAt($slot['start'], $m);
            $finish = $cal->timeAt($slot['end'], $m, true);
            $result->place(new Placement(
                opId: $op->id, woId: $wo->id, machineId: $m, start: $start, finish: $finish,
                setupMinutes: $slot['setup']['total'], runMinutes: $slot['run'], setup: $slot['setup'],
                warnings: $slot['warnings'], rank: $best['cand']['rank'],
            ));
            $predFinish = $finish->addMinutes($op->waitAfterMinutes);
            if ($op->gate === 'WAIT' && $op->minStart && $start->lessThan($op->minStart)) {
                $result->exception($wo->id, $op->id, 'WAITING', $op->gateNote ?: 'Waiting for material.');
            }
        }

        $lastOp = $wo->ops[array_key_last($wo->ops)];
        $last = $result->placements[$lastOp->id] ?? null;
        if ($last && $wo->due && $last->finish->greaterThan($wo->due)) {
            $hours = round($wo->due->diffInMinutes($last->finish) / 60, 1);
            $result->exception($wo->id, $lastOp->id, 'LATE_RISK', sprintf('Forecast finish %s is %s h after the due date.', $last->finish->format('D j M H:i'), $hours), ['hours_late' => $hours]);
        }
    }

    /** Crew jobs and subcontract steps take time but no machine capacity (machine id 0). */
    private function placeOutsideMachines(PlanOp $op, PlanWo $wo, WorkingCalendar $cal, CarbonImmutable $earliest): Placement
    {
        $zero = ['base' => 0, 'change' => 0, 'tool' => 0, 'total' => 0, 'extra_waste' => 0, 'parts' => []];
        if ($op->kind === 'SUBCON') {
            $days = max(1, $op->leadDays);

            return new Placement($op->id, $wo->id, 0, $earliest, $earliest->addDays($days), 0, $days * 1440, $zero, [sprintf('Subcontract: %d days lead time.', $days)]);
        }
        $minutes = self::MANUAL_MINUTES;
        $start = $cal->timeAt($cal->workMinute($earliest, 0), 0);
        $finish = $cal->timeAt($cal->workMinute($earliest, 0) + $minutes, 0, true);

        return new Placement($op->id, $wo->id, 0, $start, $finish, 0, $minutes, $zero, ['Crew job: '.$minutes.' minutes assumed.']);
    }

    /**
     * @param  array{rank: int, rate: ?float, setup_base: ?int}  $cand
     * @return array{start: int, end: int, run: int, setup: array, warnings: list<string>}
     */
    private function bestSlot(PlanOp $op, int $machine, array $cand, int $earliest, WorkingCalendar $cal): array
    {
        $warnings = [];
        $run = $this->runMinutes($op, $cand);
        if (! $cand['rate']) {
            $warnings[] = 'No target per hour in the master; '.self::FALLBACK_RUN_MINUTES.' minutes assumed.';
        }
        $blocks = $this->blocks[$machine] ?? [];
        $prevFacts = null;
        $cursor = $earliest;
        $n = count($blocks);

        for ($i = 0; $i <= $n; $i++) {
            // Gap before block $i (or after the last block when $i === $n); previous job = block $i-1.
            $gapStart = $i === 0 ? 0 : $blocks[$i - 1]['to'];
            $prevFacts = $i === 0 ? null : $blocks[$i - 1]['facts'];
            $start = max($cursor, $gapStart);
            $setup = $this->setup->between($prevFacts, $op->facts, $this->setupRules, $op->stage, $cand['setup_base']);
            $dur = $setup['total'] + $run;
            if ($i === $n || $start + $dur <= $blocks[$i]['from']) {
                return ['start' => $start, 'end' => $start + $dur, 'run' => $run, 'setup' => $setup, 'warnings' => $warnings];
            }
        }

        throw new \LogicException('Unreachable: the last gap is unbounded.');
    }

    /** @param  array{rank: int, rate: ?float, setup_base: ?int}  $cand */
    private function runMinutes(PlanOp $op, array $cand): int
    {
        if (empty($cand['rate']) || $cand['rate'] <= 0) {
            return self::FALLBACK_RUN_MINUTES;
        }

        return (int) max(1, ceil($op->qtyIn / $cand['rate'] * 60));
    }

    private function occupy(int $machine, int $from, int $to, array $facts, int $opId): void
    {
        $this->blocks[$machine][] = ['from' => $from, 'to' => max($to, $from + 1), 'facts' => $facts, 'opId' => $opId];
        usort($this->blocks[$machine], fn ($a, $b) => $a['from'] <=> $b['from']);
    }

    private function machineLoad(int $machine): int
    {
        return array_sum(array_map(fn ($b) => $b['to'] - $b['from'], $this->blocks[$machine] ?? []));
    }

    /**
     * Load per machine over the next week of working time: busy work minutes in the horizon over the work minutes available in it.
     *
     * @return array<int, array{busy: int, span: int, pct: float}>
     */
    private function loads(WorkingCalendar $cal, CarbonImmutable $now): array
    {
        $out = [];
        foreach ($this->blocks as $m => $blocks) {
            $from = $cal->workMinute($now, (int) $m);
            $span = max(1, $cal->workMinute($now->addDays(self::LOAD_HORIZON_DAYS), (int) $m) - $from);
            $busy = 0;
            foreach ($blocks as $b) {
                $busy += max(0, min($b['to'], $from + $span) - max($b['from'], $from));
            }
            $out[(int) $m] = ['busy' => $busy, 'span' => $span, 'pct' => round(min(100, $busy / $span * 100), 1)];
        }

        return $out;
    }
}
