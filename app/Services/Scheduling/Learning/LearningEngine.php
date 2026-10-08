<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Learning;

use App\Models\Master\ProdMachineChangeover;
use App\Models\Prd\PrdPlanDecision;
use App\Services\Scheduling\Plan\PpicSettings;
use Illuminate\Support\Facades\DB;

/**
 * Corrects the ASSUMED changeover minutes from what the operators really needed (prd_operation_time_log, SETUP segments with a change kind).
 * Rules (user decision): only values with status Assumed or Learning, never a frozen one; at least N finished jobs (default 5); the typical actual is the
 * median of the last N jobs; new = old + blend x (typical - old), one step moves at most max percent of the old value (default 20 percent).
 * Every change is written to the decision log (event LEARN) and can be reverted, which also freezes the value so the engine leaves it alone.
 * Master data is changed by users only; the engine touches nothing but these assumed changeover values.
 */
final class LearningEngine
{
    /** change kind => key in config('ppic.setup') that holds its start value */
    private const START = ['COLOR' => 'color_add_spot', 'INK_TYPE' => 'ink_type', 'SUBSTRATE' => 'substrate', 'SIZE' => 'size', 'PLATE' => 'plate', 'DIE' => 'die'];

    /** @return list<array{machine: int, kind: string, old: int, new: int, samples: int}> the changes applied */
    public function run(string $actor = 'ENGINE'): array
    {
        $min = max(1, (int) PpicSettings::number('learning.min_samples'));
        $blend = PpicSettings::number('learning.blend');
        $maxStep = PpicSettings::number('learning.max_step_pct') / 100;
        $base = (int) PpicSettings::number('setup.base_minutes');
        $applied = [];

        foreach ($this->samples() as $key => ['minutes' => $minutes, 'last' => $last]) {
            [$machine, $kind] = explode('|', $key);
            if (count($minutes) < $min || ! isset(self::START[$kind])) {
                continue;
            }
            $recent = array_slice($minutes, -$min);
            $typical = max(0.0, $this->median($recent) - $base);

            $row = ProdMachineChangeover::query()->where('machine_id', (int) $machine)->where('change_kind', $kind)->where('activate', 1)->first();
            if ($row && ($row->frozen || ! in_array($row->value_status, ['ASSUMED', 'LEARNING', null], true))) {
                continue;
            }
            // Learn from jobs finished after the last adjustment only, so a repeated run never drifts on the same samples.
            if ($row && $row->updated_at && $last <= $row->updated_at->toDateTimeString()) {
                continue;
            }
            $old = (int) ($row?->extra_minutes ?? (config('ppic.setup.'.self::START[$kind].'.minutes') ?? 0));
            $step = ($typical - $old) * $blend;
            $limit = max(1.0, $old * $maxStep);
            $new = (int) round($old + max(-$limit, min($limit, $step)));
            if ($new === $old && $row) {
                continue;
            }

            DB::connection('tm2mst')->transaction(function () use (&$row, $machine, $kind, $new, $old, $recent, $actor, $min, $typical) {
                $samples = count($recent);
                if (! $row) {
                    $row = ProdMachineChangeover::create(['machine_id' => (int) $machine, 'change_kind' => $kind, 'extra_minutes' => $old, 'extra_waste_qty' => (int) (config('ppic.setup.'.self::START[$kind].'.waste') ?? 0),
                        'activate' => 1, 'value_status' => 'ASSUMED', 'sample_count' => 0, 'frozen' => 0, 'created_by' => $actor, 'updated_by' => $actor, 'note' => 'Created by the learning engine']);
                }
                $row->forceFill(['extra_minutes' => $new, 'value_status' => $samples >= 3 * $min ? 'CALIBRATED' : 'LEARNING', 'sample_count' => $samples, 'updated_by' => $actor])->save();
                PrdPlanDecision::create(['actor' => $actor, 'event' => 'LEARN',
                    'old_value' => 'prod_machine_changeover#'.$row->changeover_id.' extra_minutes='.$old, 'new_value' => 'prod_machine_changeover#'.$row->changeover_id.' extra_minutes='.$new,
                    'reason' => sprintf('Median of the last %d jobs: %.0f min of setup beyond the base (machine %d, %s).', $samples, $typical, $machine, $kind)]);
            });
            $applied[] = ['machine' => (int) $machine, 'kind' => $kind, 'old' => $old, 'new' => $new, 'samples' => count($recent)];
        }
        PpicSettings::forget();

        return $applied;
    }

    /** Put one learned value back and freeze it. @throws \DomainException when the log row is not a learning change or is already reverted */
    public function revert(int $decisionId, string $actor): void
    {
        $d = PrdPlanDecision::findOrFail($decisionId);
        if ($d->event !== 'LEARN' || ! preg_match('/prod_machine_changeover#(\d+) extra_minutes=(\d+)/', (string) $d->old_value, $m)) {
            throw new \DomainException(t('This log row is not a learning change.'));
        }
        if (PrdPlanDecision::query()->where('event', 'LEARN_REVERT')->where('old_value', 'like', '%#'.$d->decision_id)->exists()) {
            throw new \DomainException(t('This change was already reverted.'));
        }
        $row = ProdMachineChangeover::findOrFail((int) $m[1]);
        DB::connection('tm2mst')->transaction(function () use ($row, $m, $d, $actor) {
            $current = (int) $row->extra_minutes;
            $row->forceFill(['extra_minutes' => (int) $m[2], 'frozen' => 1, 'updated_by' => $actor])->save();
            PrdPlanDecision::create(['actor' => $actor, 'event' => 'LEARN_REVERT', 'old_value' => 'extra_minutes='.$current.' of decision#'.$d->decision_id, 'new_value' => 'extra_minutes='.$m[2].' (frozen)',
                'reason' => 'Reverted by the officer; the value is frozen.']);
        });
    }

    /** @return array<string, array{minutes: list<int>, last: string}> "machine|kind" => setup minutes of finished jobs (oldest first) and the finish time of the newest */
    private function samples(): array
    {
        $rows = DB::connection('tm2prd')->table('prd_operation_time_log')->where('kind', 'SETUP')->whereNotNull('change_kind')->whereNotNull('ended_at')
            ->selectRaw('operation_id, machine_id, change_kind, SUM(minutes) as minutes, MAX(ended_at) as finished')->groupBy('operation_id', 'machine_id', 'change_kind')->orderBy('finished')->get();
        $out = [];
        foreach ($rows as $r) {
            $key = (int) $r->machine_id.'|'.$r->change_kind;
            $out[$key]['minutes'][] = (int) $r->minutes;
            $out[$key]['last'] = (string) $r->finished;
        }

        return $out;
    }

    /** @param list<int|float> $values */
    private function median(array $values): float
    {
        sort($values);
        $n = count($values);

        return $n % 2 ? (float) $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }
}
