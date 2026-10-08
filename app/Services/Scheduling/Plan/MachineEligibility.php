<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

/**
 * Can this machine run this operation? Pure check against the machine master and the process-machine capability row.
 * Unknown values (NULL in the master or in the Work Order) are not checked, so missing master data never blocks a job silently:
 * it is reported in `warnings` instead.
 *
 * Machine shape:    machine_id, status, activate, min_l, min_w, max_l, max_w, gsm_min, gsm_max, max_colors
 * Capability shape: found (bool), activate, certified, rank, min_l, min_w, max_l, max_w, gsm_min, gsm_max (override machine limits)
 * Job shape:        stage (PRESS|POSTPRESS|FINISHING|...), sheet_l, sheet_w (mm), gsm, colours, needs_die
 */
final class MachineEligibility
{
    public const DOWN_STATUSES = ['BREAKDOWN', 'RETIRED', 'SCRAPPED'];

    /**
     * @return array{eligible: bool, reason: ?string, rank: int, warnings: list<string>}
     */
    public function check(array $machine, array $capability, array $job): array
    {
        $warnings = [];
        $rank = (int) ($capability['rank'] ?? 99);

        if (empty($capability['found']) || empty($capability['activate'])) {
            return $this->no('Not set up for this process in the machine master.', $rank);
        }
        if (isset($machine['activate']) && ! $machine['activate']) {
            return $this->no('Machine is inactive.', $rank);
        }
        if (in_array(strtoupper((string) ($machine['status'] ?? '')), self::DOWN_STATUSES, true)) {
            return $this->no('Machine is down ('.strtoupper((string) $machine['status']).').', $rank);
        }
        if (array_key_exists('certified', $capability) && ! $capability['certified']) {
            return $this->no('Machine is not certified for this process.', $rank);
        }

        $limit = fn (string $k) => $capability[$k] ?? $machine[$k] ?? null;

        // Sheet size, orientation free: compare the long side with the long side.
        $sheetL = (float) ($job['sheet_l'] ?? 0);
        $sheetW = (float) ($job['sheet_w'] ?? 0);
        if ($sheetL > 0 && $sheetW > 0) {
            [$a, $b] = $sheetL >= $sheetW ? [$sheetL, $sheetW] : [$sheetW, $sheetL];
            $maxL = $limit('max_l');
            $maxW = $limit('max_w');
            if ($maxL || $maxW) {
                $big = max((float) $maxL, (float) $maxW);
                $small = min(array_filter([(float) $maxL, (float) $maxW]) ?: [0.0]);
                if ($maxL && $maxW) {
                    if ($a > $big + 0.5 || $b > $small + 0.5) {
                        return $this->no(sprintf('Sheet %sx%s mm is larger than the machine limit %sx%s mm.', $this->n($a), $this->n($b), $this->n($big), $this->n($small)), $rank);
                    }
                } elseif ($a > $big + 0.5 && $b > $big + 0.5) {
                    return $this->no(sprintf('Sheet %sx%s mm exceeds the machine width limit %s mm.', $this->n($a), $this->n($b), $this->n($big)), $rank);
                }
            } else {
                $warnings[] = 'Machine sheet size limit is not in the master.';
            }
            $minL = $limit('min_l');
            $minW = $limit('min_w');
            if ($minL && $minW) {
                [$ma, $mb] = max($minL, $minW) === $minL ? [(float) $minL, (float) $minW] : [(float) $minW, (float) $minL];
                if ($a < $ma - 0.5 || $b < $mb - 0.5) {
                    return $this->no(sprintf('Sheet %sx%s mm is smaller than the machine minimum %sx%s mm.', $this->n($a), $this->n($b), $this->n($ma), $this->n($mb)), $rank);
                }
            }
        }

        $gsm = (float) ($job['gsm'] ?? 0);
        if ($gsm > 0) {
            $gMin = $limit('gsm_min');
            $gMax = $limit('gsm_max');
            if ($gMax && $gsm > (float) $gMax) {
                return $this->no(sprintf('Board %s g is above the machine maximum %s g.', $this->n($gsm), $this->n((float) $gMax)), $rank);
            }
            if ($gMin && $gsm < (float) $gMin) {
                return $this->no(sprintf('Board %s g is below the machine minimum %s g.', $this->n($gsm), $this->n((float) $gMin)), $rank);
            }
            if (! $gMax && ! $gMin) {
                $warnings[] = 'Machine board weight limit is not in the master.';
            }
        }

        $colours = (int) ($job['colours'] ?? 0);
        if (($job['stage'] ?? '') === 'PRESS' && $colours > 0 && ! empty($job['is_print'])) {
            $perPass = (int) ($machine['max_colors'] ?? 0);
            if ($perPass > 0 && $colours > $perPass) {
                return $this->no(sprintf('Needs %d colours; the machine prints %d per pass.', $colours, $perPass), $rank);
            }
            if ($perPass === 0) {
                $warnings[] = 'Machine colour count is not in the master.';
            }
        }

        return ['eligible' => true, 'reason' => null, 'rank' => $rank, 'warnings' => $warnings];
    }

    /** @return array{eligible: bool, reason: string, rank: int, warnings: list<string>} */
    private function no(string $reason, int $rank): array
    {
        return ['eligible' => false, 'reason' => $reason, 'rank' => $rank, 'warnings' => []];
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
