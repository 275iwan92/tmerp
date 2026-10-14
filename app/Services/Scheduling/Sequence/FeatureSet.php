<?php

/*
 * REMARK
 * Tujuan       : Kumpulan fitur urutan job + konteks bersama; satu tempat menambah fitur baru.
 * Sumber hulu  : Ppic::operations(), prd_wo.priority, PlanLoader::waitRules.
 * Input        : daftar operasi (semua WO), waktu.
 * Output       : vektor fitur per operasi (nama => nilai) dan daftar nama.
 * Dipakai oleh : TraceRecorder, SequenceTrainer, SequenceSuggester.
 * Status       : Aktif. Enam fitur awal; tambah turunan Feature lalu daftarkan di all().
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

use App\Services\Scheduling\Plan\PlanLoader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FeatureSet
{
    /** @return list<Feature> */
    public static function all(): array
    {
        return [new UrgencyFeature, new PriorityFeature, new DurationFeature, new SimilarityFeature, new MaterialGateFeature, new WaitBeforeFeature];
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(fn (Feature $f) => $f->name(), self::all());
    }

    /**
     * Shared context. anchor[machine] = the last operation that is already fixed (confirmed / running / locked) before the Draft block.
     *
     * @param  list<array<string, mixed>>  $ops  Ppic::operations()
     * @return array<string, mixed>
     */
    public static function context(array $ops, ?CarbonImmutable $now = null): array
    {
        $anchor = [];
        $byWo = [];
        foreach ($ops as $o) {
            $byWo[$o['wo_id']][] = $o;
            $fixed = $o['plan_status'] === 'CONFIRMED' || $o['locked'] || $o['task_status'] === 'RUNNING';
            if ($fixed && $o['machine_id'] && $o['start'] && (! isset($anchor[$o['machine_id']]) || $o['start'] > $anchor[$o['machine_id']]['start'])) {
                $anchor[$o['machine_id']] = $o;
            }
        }

        return [
            'now' => $now ?? CarbonImmutable::now(),
            'anchor' => $anchor,
            'opsByWo' => $byWo,
            'woPriority' => DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', array_keys($byWo))->pluck('priority', 'wo_id')->all(),
            'waitRules' => app(PlanLoader::class)->waitRules(),
        ];
    }

    /** @return array<string, float> */
    public static function vector(array $op, array $ctx): array
    {
        $v = [];
        foreach (self::all() as $f) {
            $v[$f->name()] = round($f->value($op, $ctx), 4);
        }

        return $v;
    }
}
