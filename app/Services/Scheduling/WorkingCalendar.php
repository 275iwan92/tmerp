<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;

/**
 * Working-time axis per machine for finite-capacity planning (pure: no database).
 *
 * A machine works from `dayStart` for shifts x hours each day (max 24 h), Monday to Saturday, minus holidays.
 * The planner thinks in "work minutes" counted from an origin day; this class converts between work minutes and real
 * time so that free-slot search is plain integer arithmetic.
 */
final class WorkingCalendar
{
    /** @var array<int, array{shifts: int, hours: float}> */
    private array $machines;

    /** @var array<string, true> */
    private array $holidays;

    /** @var array<int, array<int, array{from: int, len: int, start: CarbonImmutable}>> machine => day index => window */
    private array $cache = [];

    private CarbonImmutable $origin;

    /**
     * @param  array<int, array{shifts?: int|null, hours?: float|string|null}>  $machines  machine_id => shift settings
     * @param  list<string>  $holidays  Y-m-d dates that are not working days
     */
    public function __construct(
        CarbonImmutable $origin,
        array $machines = [],
        array $holidays = [],
        private readonly string $dayStart = '07:00',
        private readonly int $defaultShifts = 2,
        private readonly float $defaultHours = 8.0,
        private readonly bool $sundayOff = true,
    ) {
        $this->origin = $origin->startOfDay();
        $this->machines = [];
        foreach ($machines as $id => $m) {
            $this->machines[(int) $id] = [
                'shifts' => max(1, (int) ($m['shifts'] ?? $this->defaultShifts)),
                'hours' => (float) ($m['hours'] ?? $this->defaultHours) ?: $this->defaultHours,
            ];
        }
        $this->holidays = array_fill_keys($holidays, true);
    }

    public function origin(): CarbonImmutable
    {
        return $this->origin;
    }

    /** Length of one working day of this machine in minutes. */
    public function dayMinutes(int $machineId): int
    {
        $m = $this->machines[$machineId] ?? ['shifts' => $this->defaultShifts, 'hours' => $this->defaultHours];

        return (int) min(1440, round($m['shifts'] * $m['hours'] * 60));
    }

    public function isWorkingDay(CarbonImmutable $day): bool
    {
        if ($this->sundayOff && $day->dayOfWeek === CarbonImmutable::SUNDAY) {
            return false;
        }

        return ! isset($this->holidays[$day->toDateString()]);
    }

    /** Cumulative work minutes from the origin up to the real time $t (inside a closed period it equals the next opening). */
    public function workMinute(CarbonImmutable $t, int $machineId): int
    {
        $dayIdx = (int) $this->origin->diffInDays($t->startOfDay(), false);
        if ($dayIdx < 0) {
            return 0;
        }
        $w = $this->window($machineId, $dayIdx);
        $into = (int) max(0, min($w['len'], $w['start']->diffInMinutes($t, false)));

        return $w['from'] + ($w['len'] > 0 ? $into : 0);
    }

    /**
     * Real time of a work minute. $asEnd = true: a work minute that sits on a window boundary maps to the END of the previous
     * window (use for finish times); false maps it to the START of the next window (use for start times).
     */
    public function timeAt(int $workMinute, int $machineId, bool $asEnd = false): CarbonImmutable
    {
        $workMinute = max(0, $workMinute);
        for ($d = 0; $d < 400; $d++) {
            $w = $this->window($machineId, $d);
            if ($w['len'] === 0) {
                continue;
            }
            $end = $w['from'] + $w['len'];
            $hit = $asEnd ? ($workMinute > $w['from'] && $workMinute <= $end) : ($workMinute >= $w['from'] && $workMinute < $end);
            if ($hit || ($workMinute === 0 && $d === $this->firstWorkingDay($machineId))) {
                return $w['start']->addMinutes($workMinute - $w['from']);
            }
        }

        throw new \RuntimeException(sprintf('Work minute %d is beyond the planning horizon of the machine calendar.', $workMinute));
    }

    /** First work minute at or after the real time $t. */
    public function nextOpenMinute(CarbonImmutable $t, int $machineId): int
    {
        return $this->workMinute($t, $machineId);
    }

    /** @return array{from: int, len: int, start: CarbonImmutable} */
    private function window(int $machineId, int $dayIdx): array
    {
        if (isset($this->cache[$machineId][$dayIdx])) {
            return $this->cache[$machineId][$dayIdx];
        }
        // Build sequentially so cumulative minutes are correct.
        $from = 0;
        $last = isset($this->cache[$machineId]) ? max(array_keys($this->cache[$machineId])) : -1;
        if ($last >= 0) {
            $prev = $this->cache[$machineId][$last];
            $from = $prev['from'] + $prev['len'];
        }
        for ($i = $last + 1; $i <= $dayIdx; $i++) {
            $day = $this->origin->addDays($i);
            $len = $this->isWorkingDay($day) ? $this->dayMinutes($machineId) : 0;
            $start = $day->setTimeFromTimeString($this->dayStart);
            $this->cache[$machineId][$i] = ['from' => $from, 'len' => $len, 'start' => $start];
            $from += $len;
        }

        return $this->cache[$machineId][$dayIdx];
    }

    private function firstWorkingDay(int $machineId): int
    {
        for ($d = 0; $d < 14; $d++) {
            if ($this->window($machineId, $d)['len'] > 0) {
                return $d;
            }
        }

        return 0;
    }
}
