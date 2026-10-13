<?php

/*
 * REMARK
 * Tujuan       : Menjelaskan jeda (idle) antar dua job berurutan di satu mesin: berapa lama dan mengapa, supaya PPIC melihat mesin berhenti karena apa (tidak boleh ada jeda tanpa alasan).
 * Sumber hulu  : Jadwal engine (Ppic::operations: start / finish per operasi), aturan tunggu setelah proses (prod_wait_rule + config ppic.wait_after), kalender kerja mesin (PlanLoader::calendar), material_ready_at.
 * Input        : daftar operasi (array Ppic::operations), kalender kerja, aturan tunggu.
 * Output       : op_id => ['minutes' => int, 'reason' => string, 'explained' => bool] untuk operasi yang didahului jeda lebih dari 5 menit pada mesinnya.
 * Dipakai oleh : Ppic::queue (kolom "Idle before" di QUE every process every machine).
 * Status       : Aktif.
 */

namespace App\Support\V2\Data;

use App\Services\Scheduling\WorkingCalendar;
use Carbon\CarbonImmutable;

final class MachineIdle
{
    private const TOLERANCE_MINUTES = 5;

    /**
     * @param  list<array<string, mixed>>  $all  every operation (Ppic::operations)
     * @param  array<string, int>  $waitRules  upper-case process-name prefix => minutes to wait after it
     * @return array<int, array{minutes: int, reason: string, explained: bool}>
     */
    public static function explain(array $all, WorkingCalendar $cal, array $waitRules, CarbonImmutable $now): array
    {
        $byWo = [];
        foreach ($all as $o) {
            $byWo[$o['wo_id']][] = $o;
        }
        $wait = function (string $process) use ($waitRules): int {
            $name = strtoupper($process);
            $min = 0;
            foreach ($waitRules as $key => $m) {
                if (str_starts_with($name, (string) $key)) {
                    $min = (int) $m;
                }
            }

            return $min;
        };

        $perMachine = [];
        foreach ($all as $o) {
            if ($o['machine_id'] && $o['start'] && $o['finish'] && ! in_array($o['task_status'], Ppic::DONE, true)) {
                $perMachine[$o['machine_id']][] = $o;
            }
        }

        $out = [];
        foreach ($perMachine as $machine => $list) {
            usort($list, fn ($a, $b) => $a['start'] <=> $b['start']);
            for ($i = 1, $n = count($list); $i < $n; $i++) {
                [$a, $b] = [$list[$i - 1], $list[$i]];
                $idle = (int) round(($b['start']->getTimestamp() - $a['finish']->getTimestamp()) / 60);
                if ($idle <= self::TOLERANCE_MINUTES) {
                    continue;
                }
                $out[$b['op_id']] = ['minutes' => $idle] + self::cause($a, $b, $byWo[$b['wo_id']] ?? [], $wait, $cal, (int) $machine, $now);
            }
        }

        return $out;
    }

    /** @return array{reason: string, explained: bool} */
    private static function cause(array $a, array $b, array $woOps, callable $wait, WorkingCalendar $cal, int $machine, CarbonImmutable $now): array
    {
        // Earliest the job could start: its previous process (plus drying / curing), its material, the job before it on the machine, and "now".
        $floor = $a['finish'];
        $why = t('Follows the job before it on this machine');
        $bind = function (CarbonImmutable $at, string $label) use (&$floor, &$why): void {
            if ($at->greaterThan($floor)) {
                [$floor, $why] = [$at, $label];
            }
        };
        if ($b['start']->greaterThanOrEqualTo($now)) {
            $bind($now, t('Earliest possible start (now)'));
        }
        $pred = null;
        foreach ($woOps as $o) {
            if ($o['seq'] < $b['seq'] && $o['finish'] && ($pred === null || $o['seq'] > $pred['seq'])) {
                $pred = $o;
            }
        }
        if ($pred) {
            $w = $wait($pred['process']);
            $bind($pred['finish']->addMinutes($w), t('Waiting for the previous process').': '.$pred['process'].($w > 0 ? ' (+'.$w.' '.t('min').')' : '').self::root($pred, $woOps, $wait, $cal, $machine, $now));
        }
        if ($b['material_ready_at']) {
            $bind($b['material_ready_at'], t('Waiting for material'));
        }
        // The machine only works inside its shifts: the first open minute at or after the floor is the earliest real start.
        $open = $cal->timeAt($cal->workMinute($floor, $machine), $machine);
        if ($b['start']->lessThanOrEqualTo($open->addMinutes(self::TOLERANCE_MINUTES))) {
            return ['reason' => $why.($open->greaterThan($floor->addMinutes(self::TOLERANCE_MINUTES)) ? ' · '.t('next working time') : ''), 'explained' => true];
        }
        if ($b['plan_status'] === 'CONFIRMED' || $b['locked']) {
            return ['reason' => t('Confirmed earlier at this time (fixed)'), 'explained' => true];
        }
        if (in_array('WAIT', $b['gates'], true)) {
            return ['reason' => t('Waiting on a gate').($b['gate_note'] ? ': '.$b['gate_note'] : ''), 'explained' => true];
        }

        return ['reason' => t('No reason found: the engine should fill this gap'), 'explained' => false];
    }

    /** Why the previous process itself started so late: walk back along the Work Order while each step was bound by the one before it. */
    private static function root(array $op, array $woOps, callable $wait, WorkingCalendar $cal, int $machine, CarbonImmutable $now): string
    {
        for ($guard = 0; $guard < 20; $guard++) {
            $pred = null;
            foreach ($woOps as $o) {
                if ($o['seq'] < $op['seq'] && $o['finish'] && ($pred === null || $o['seq'] > $pred['seq'])) {
                    $pred = $o;
                }
            }
            if ($pred && $op['start'] && $op['start']->lessThanOrEqualTo($cal->timeAt($cal->workMinute($pred['finish']->addMinutes($wait($pred['process'])), $op['machine_id'] ?: $machine), $op['machine_id'] ?: $machine)->addMinutes(self::TOLERANCE_MINUTES))) {
                $op = $pred;

                continue;
            }
            break;
        }
        if ($op['material_ready_at'] && $op['start']->lessThanOrEqualTo($op['material_ready_at']->addMinutes(self::TOLERANCE_MINUTES))) {
            return ' ← '.t('waiting for material');
        }
        if ($op['start']->greaterThanOrEqualTo($now) && $op['start']->lessThanOrEqualTo($cal->timeAt($cal->workMinute($now, $op['machine_id'] ?: $machine), $op['machine_id'] ?: $machine)->addMinutes(self::TOLERANCE_MINUTES))) {
            return ' ← '.t('first process starts at the earliest possible time (now / next shift)');
        }
        if (in_array('WAIT', $op['gates'], true)) {
            return ' ← '.t('waiting on a gate').($op['gate_note'] ? ': '.$op['gate_note'] : '');
        }

        return '';
    }

    /** "1 h 40 min" */
    public static function duration(int $minutes): string
    {
        return trim(($minutes >= 60 ? intdiv($minutes, 60).' h ' : '').($minutes % 60 !== 0 || $minutes < 60 ? $minutes % 60 .' min' : ''));
    }
}
