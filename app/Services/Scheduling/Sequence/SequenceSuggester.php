<?php

/*
 * REMARK
 * Tujuan       : Menyodorkan urutan job per mesin dari model ACTIVE, menjalankan USE (lewat queueMove, tetap DRAFT) dan DISMISS (SEQ_REJECT).
 * Sumber hulu  : prd_sequence_model (ACTIVE), Ppic::operations(), PlanChangeService::queueMove, prd_plan_decision.
 * Input        : id mesin, login.
 * Output       : usulan {order, current, rank}, status belajar (n / 30), baris untuk strip Simulation, lajur bayangan dan kolom Suggested di QUE.
 * Dipakai oleh : Ppic::sequencePanel / queue, PpicController (use / dismiss).
 * Status       : Aktif. Usulan hanya bila model ACTIVE (gerbang SequenceTrainer); Preview tidak menyimpan apa pun.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

use App\Models\Prd\PrdPlanDecision;
use App\Models\Prd\PrdSequenceModel;
use App\Services\Scheduling\Plan\PlanChangeService;
use App\Support\V2\Data\Ppic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class SequenceSuggester
{
    public static function active(): ?PrdSequenceModel
    {
        return PrdSequenceModel::where('status', 'ACTIVE')->orderByDesc('version')->first();
    }

    /**
     * Learning state for the strip: how many officer decisions (pairs) the model has, against the 30 it needs.
     *
     * @return array{active: ?PrdSequenceModel, pairs: int, needed: int, arrangements: int}
     */
    public static function status(): array
    {
        $active = self::active();
        $c = SequenceTrainer::counts();

        return ['active' => $active, 'pairs' => (int) $c['pairs'], 'needed' => SequenceTrainer::MIN_PAIRS, 'arrangements' => $active ? (int) $active->arrangements_count : (int) $c['arrangements']];
    }

    /**
     * Suggested order of the movable Draft operations of one machine. Null when no model is ACTIVE or fewer than two operations wait there.
     *
     * @return ?array{order: list<int>, current: list<int>, rank: array<int, array{suggested: int, now: int}>, differs: bool, model: PrdSequenceModel}
     */
    public static function suggest(int $machine): ?array
    {
        $model = self::active();
        if (! $model) {
            return null;
        }
        $ops = Ppic::operations();
        $rows = TraceRecorder::arrangement($ops, $machine);
        if (count($rows) < 2) {
            return null;
        }
        $current = array_column($rows, 'op_id');
        $key = 'seq-suggest-'.$model->id_sequence_model.'-'.$machine.'-'.md5(implode(',', $current).'|'.implode(',', array_map(fn ($r) => $r['start']->format('YmdHi'), $rows)));

        // only plain arrays are cached (the model is attached afterwards)
        $plain = Cache::remember($key, 30, function () use ($model, $rows, $ops, $current) {
            $ctx = FeatureSet::context($ops, CarbonImmutable::now());
            $w = (array) ($model->weights['w'] ?? []);
            $score = [];
            foreach ($rows as $i => $r) {
                $score[$r['op_id']] = [SequenceModel::score($w, FeatureSet::vector($r, $ctx)), $i];
            }
            uasort($score, fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
            $order = array_map('intval', array_keys($score));
            $rank = [];
            foreach ($order as $i => $id) {
                $rank[$id] = ['suggested' => $i + 1, 'now' => array_search($id, $current, true) + 1];
            }

            return ['order' => $order, 'current' => $current, 'rank' => $rank, 'differs' => $order !== $current];
        });

        return $plain + ['model' => $model];
    }

    /** Was this exact suggestion dismissed (SEQ_REJECT) and the arrangement not changed since? */
    public static function dismissed(int $machine, array $order): bool
    {
        $last = PrdPlanDecision::where('event', 'SEQ_REJECT')->where('old_value', 'm'.$machine)->orderByDesc('decision_id')->first();

        return $last && (string) $last->new_value === implode(',', $order);
    }

    /**
     * USE: the suggested order is put on the machine through the same move as the officer's drag (queueMove, pending, DRAFT): operation k takes the
     * start of slot k. Not an APPLY.
     *
     * @return array{moved: int, skipped: int}
     */
    public static function use(int $machine, PlanChangeService $changes, string $login): array
    {
        $s = self::suggest($machine);
        if (! $s || ! $s['differs']) {
            return ['moved' => 0, 'skipped' => 0];
        }
        $ops = collect(TraceRecorder::arrangement(Ppic::operations(), $machine))->keyBy('op_id');
        $slots = array_map(fn ($id) => $ops[$id]['start'], $s['current']);
        $moved = $skipped = 0;
        foreach ($s['order'] as $k => $opId) {
            if ($ops[$opId]['start']->eq($slots[$k])) {
                continue;
            }
            try {
                $changes->queueMove($opId, $machine, $slots[$k], 'Suggested order (sequence model v'.$s['model']->version.')', $login);
                $moved++;
            } catch (\DomainException $e) {
                $skipped++;
            }
        }
        PrdPlanDecision::create(['actor' => $login, 'event' => 'SEQ_USE', 'old_value' => 'm'.$machine, 'new_value' => mb_substr(implode(',', $s['order']), 0, 190),
            'reason' => 'Model v'.$s['model']->version.': '.$moved.' moved, '.$skipped.' not allowed']);
        Ppic::forget();

        return ['moved' => $moved, 'skipped' => $skipped];
    }

    /** DISMISS: logged as SEQ_REJECT (machine, suggested order, model version); the strip stays hidden until the arrangement changes. */
    public static function dismiss(int $machine, string $login): bool
    {
        $s = self::suggest($machine);
        if (! $s) {
            return false;
        }
        PrdPlanDecision::create(['actor' => $login, 'event' => 'SEQ_REJECT', 'old_value' => 'm'.$machine, 'new_value' => mb_substr(implode(',', $s['order']), 0, 190),
            'reason' => 'Model v'.$s['model']->version.' suggestion dismissed']);

        return true;
    }
}
