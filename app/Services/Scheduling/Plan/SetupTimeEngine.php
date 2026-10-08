<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

/**
 * Setup time between two jobs on one machine = base setup + changeover + tool setup (pure calculation, no database).
 *
 * Facts of a job: colours (process colours), spots (list of spot colour names), ink_type (CONVENTIONAL|UV), gsm, board (type name),
 * sheet_l, sheet_w (mm), die (code or null), plate (code or null), tool_min (minutes to mount its tooling).
 * Rules: the config('ppic.setup') shape, optionally overridden per machine by prod_machine_changeover rows.
 *
 * Which changes count depends on the process stage: a press cares about ink, plates, board and size; die-cutting about the die,
 * board and size; other stages only pay the base setup.
 */
final class SetupTimeEngine
{
    private const PRESS_KINDS = ['colour', 'ink_type', 'substrate', 'size', 'plate', 'die'];

    private const DIE_KINDS = ['substrate', 'size', 'die'];

    private const FEED_KINDS = ['size', 'substrate'];

    /**
     * @param  array<string, mixed>  $rules
     * @return array{base: int, change: int, tool: int, total: int, extra_waste: int, parts: list<array{kind: string, label: string, minutes: int}>}
     */
    public function between(?array $prev, array $next, array $rules, string $stage = 'PRESS', ?int $baseMinutes = null): array
    {
        $base = $baseMinutes ?? (int) ($rules['base_minutes'] ?? 15);
        $parts = [];
        $change = 0;
        $waste = 0;
        $tool = 0;
        $kinds = $this->kindsFor($stage);

        if ($prev !== null) {
            if (in_array('colour', $kinds, true)) {
                [$minutes, $w, $label] = $this->colour($prev, $next, $rules);
                if ($minutes > 0) {
                    $parts[] = ['kind' => 'colour', 'label' => $label, 'minutes' => $minutes];
                    $change += $minutes;
                    $waste += $w;
                }
            }
            if (in_array('ink_type', $kinds, true) && ($prev['ink_type'] ?? 'CONVENTIONAL') !== ($next['ink_type'] ?? 'CONVENTIONAL')) {
                $r = $rules['ink_type'] ?? ['minutes' => 55, 'waste' => 80];
                $parts[] = ['kind' => 'ink_type', 'label' => 'Conventional to UV ink', 'minutes' => (int) $r['minutes']];
                $change += (int) $r['minutes'];
                $waste += (int) ($r['waste'] ?? 0);
            }
            if (in_array('substrate', $kinds, true) && $this->substrateChanged($prev, $next, $rules)) {
                $r = $rules['substrate'] ?? ['minutes' => 15, 'waste' => 20];
                $parts[] = ['kind' => 'substrate', 'label' => 'Board change', 'minutes' => (int) $r['minutes']];
                $change += (int) $r['minutes'];
                $waste += (int) ($r['waste'] ?? 0);
            }
            if (in_array('size', $kinds, true) && $this->sizeChanged($prev, $next)) {
                $r = $rules['size'] ?? ['minutes' => 12, 'waste' => 15];
                $parts[] = ['kind' => 'size', 'label' => 'Sheet size change', 'minutes' => (int) $r['minutes']];
                $change += (int) $r['minutes'];
                $waste += (int) ($r['waste'] ?? 0);
            }
        }

        // Tooling: mounted when the die / plate differs from the previous job (or on a cold start).
        if (in_array('die', $kinds, true) && ($next['die'] ?? null) && ($prev['die'] ?? null) !== $next['die']) {
            $m = (int) ($next['tool_min'] ?? $rules['die']['minutes'] ?? 15);
            $parts[] = ['kind' => 'die', 'label' => 'Mount die '.$next['die'], 'minutes' => $m];
            $tool += $m;
        }
        if (in_array('plate', $kinds, true) && ($next['plate'] ?? null) && ($prev['plate'] ?? null) !== $next['plate']) {
            $m = (int) ($next['plate_min'] ?? $rules['plate']['minutes'] ?? 10);
            $parts[] = ['kind' => 'plate', 'label' => 'Mount plates '.$next['plate'], 'minutes' => $m];
            $tool += $m;
        }

        return ['base' => $base, 'change' => $change, 'tool' => $tool, 'total' => $base + $change + $tool, 'extra_waste' => $waste, 'parts' => $parts];
    }

    /** @return list<string> */
    private function kindsFor(string $stage): array
    {
        return match (strtoupper($stage)) {
            'PRESS' => self::PRESS_KINDS,
            'FINISHING' => self::DIE_KINDS,
            'POSTPRESS' => self::FEED_KINDS,
            default => [],
        };
    }

    /** @return array{0: int, 1: int, 2: string} */
    private function colour(array $prev, array $next, array $rules): array
    {
        $a = $this->inkSet($prev);
        $b = $this->inkSet($next);
        sort($a);
        sort($b);
        if ($a === $b) {
            return [0, 0, ''];
        }
        // The next job only adds spot colours on top of the previous set: partial wash. Anything else: full wash.
        if (! array_diff($a, $b)) {
            $r = $rules['color_add_spot'] ?? ['minutes' => 20, 'waste' => 30];

            return [(int) $r['minutes'], (int) ($r['waste'] ?? 0), 'Add spot colour (partial wash)'];
        }
        $r = $rules['color_full_wash'] ?? ['minutes' => 38, 'waste' => 60];

        return [(int) $r['minutes'], (int) ($r['waste'] ?? 0), 'Different inks (full wash)'];
    }

    /** @return list<string> */
    private function inkSet(array $job): array
    {
        $set = [];
        $n = (int) ($job['colours'] ?? 0);
        foreach (array_slice(['C', 'M', 'Y', 'K'], 0, min(4, $n)) as $c) {
            $set[] = $c;
        }
        foreach ($job['spots'] ?? [] as $s) {
            $set[] = strtoupper((string) $s);
        }

        return $set;
    }

    private function substrateChanged(array $prev, array $next, array $rules): bool
    {
        $gap = (float) ($rules['substrate']['gsm_gap'] ?? 20);
        $g1 = (float) ($prev['gsm'] ?? 0);
        $g2 = (float) ($next['gsm'] ?? 0);
        if ($g1 > 0 && $g2 > 0 && abs($g1 - $g2) > $gap) {
            return true;
        }

        return ($prev['board'] ?? null) !== null && ($next['board'] ?? null) !== null && $prev['board'] !== $next['board'];
    }

    private function sizeChanged(array $prev, array $next): bool
    {
        $a = [(float) ($prev['sheet_l'] ?? 0), (float) ($prev['sheet_w'] ?? 0)];
        $b = [(float) ($next['sheet_l'] ?? 0), (float) ($next['sheet_w'] ?? 0)];
        if (! $a[0] || ! $b[0]) {
            return false;
        }
        rsort($a);
        rsort($b);

        return abs($a[0] - $b[0]) > 1 || abs($a[1] - $b[1]) > 1;
    }
}
