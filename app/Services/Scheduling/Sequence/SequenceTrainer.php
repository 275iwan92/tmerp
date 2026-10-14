<?php

/*
 * REMARK
 * Tujuan       : Melatih model urutan dari susunan officer (FINAL) dan menjaga gerbang: model hanya ACTIVE bila >= 30 pasangan dan lebih akurat dari urutan engine pada 20% APPLY terbaru.
 * Sumber hulu  : prd_plan_trace (status FINAL, from_suggestion = 0, WO tidak dibatalkan).
 * Input        : -
 * Output       : baris prd_sequence_model baru (ACTIVE atau CANDIDATE); idempoten (tanpa data baru tidak membuat versi baru).
 * Dipakai oleh : perintah sequence:train, SequenceSuggester::status (hitung pasangan).
 * Status       : Aktif. Pasangan = tiap dua job satu mesin dari satu snapshot APPLY; hold-out menurut waktu = 20% snapshot terbaru, tidak ikut melatih.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

use App\Models\Prd\PrdPlanTrace;
use App\Models\Prd\PrdSequenceModel;
use Illuminate\Support\Facades\DB;

final class SequenceTrainer
{
    public const MIN_PAIRS = 30;

    public const HOLDOUT = 0.2;

    /**
     * FINAL snapshots oldest first, each a list of rows ordered by officer position. Rows from a suggestion (from_suggestion) and rows of a
     * cancelled Work Order never count (no self feedback, no dead jobs).
     *
     * @return list<array{key: string, rows: list<array<string, mixed>>}>
     */
    public static function snapshots(): array
    {
        $cancelled = DB::connection('tm2prd')->table('prd_wo')->where('wo_status', 'CANCELLED')->pluck('wo_id')->all();
        $rows = PrdPlanTrace::where('status', 'FINAL')->where('from_suggestion', false)->whereNotIn('wo_id', $cancelled)
            ->orderBy('created_at')->orderBy('id_plan_trace')->get();
        $out = [];
        foreach ($rows->groupBy('snapshot_key') as $key => $g) {
            $r = $g->sortBy('officer_pos')->values()->map(fn ($t) => ['features' => (array) $t->features, 'officer' => (int) $t->officer_pos, 'engine' => (int) $t->engine_pos])->all();
            if (count($r) >= 2) {
                $out[] = ['key' => (string) $key, 'rows' => $r, 'at' => $g->min('created_at')];
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

        return $out;
    }

    /**
     * Pairs of one snapshot: the job the officer put first minus the one after it, plus whether the engine had the same order.
     *
     * @return list<array{diff: array<string, float>, engine_ok: bool}>
     */
    public static function pairs(array $snapshot): array
    {
        $out = [];
        $r = $snapshot['rows'];
        $names = FeatureSet::names();
        for ($i = 0; $i < count($r); $i++) {
            for ($j = $i + 1; $j < count($r); $j++) {
                $d = [];
                foreach ($names as $k) {
                    $d[$k] = (float) ($r[$i]['features'][$k] ?? 0) - (float) ($r[$j]['features'][$k] ?? 0);
                }
                $out[] = ['diff' => $d, 'engine_ok' => $r[$i]['engine'] < $r[$j]['engine']];
            }
        }

        return $out;
    }

    /** @return array{pairs: int, arrangements: int} counts of all eligible pairs / snapshots (training + hold-out). */
    public static function counts(): array
    {
        $snaps = self::snapshots();

        return ['pairs' => array_sum(array_map(fn ($s) => count($s['rows']) * (count($s['rows']) - 1) / 2, $snaps)), 'arrangements' => count($snaps)];
    }

    /** Train and store a new version. Returns it, or null when there is nothing new to learn from. */
    public static function run(): ?PrdSequenceModel
    {
        $snaps = self::snapshots();
        $names = FeatureSet::names();
        $signature = count($snaps).'-'.(string) PrdPlanTrace::where('status', 'FINAL')->max('id_plan_trace').'-'.implode(',', $names);
        $last = PrdSequenceModel::orderByDesc('version')->first();
        if ($last && ($last->weights['sig'] ?? null) === $signature) {
            return null;
        }
        $holdN = $snaps ? max(1, (int) ceil(count($snaps) * self::HOLDOUT)) : 0;
        $train = array_slice($snaps, 0, max(0, count($snaps) - $holdN));
        $hold = array_slice($snaps, count($train));
        $trainPairs = array_merge([], ...array_map(fn ($s) => self::pairs($s), $train));
        $holdPairs = array_merge([], ...array_map(fn ($s) => self::pairs($s), $hold));
        $total = count($trainPairs) + count($holdPairs);

        $w = SequenceModel::train(array_column($trainPairs, 'diff'), $names);
        $accModel = SequenceModel::accuracy($w, array_column($holdPairs, 'diff'));
        $accEngine = $holdPairs ? round(count(array_filter($holdPairs, fn ($p) => $p['engine_ok'])) / count($holdPairs), 4) : 0.0;
        // The gate: enough decisions AND better than the engine on the newest APPLYs.
        $passes = $total >= self::MIN_PAIRS && $holdPairs && $accModel > $accEngine;

        return DB::connection('tm2prd')->transaction(function () use ($w, $names, $signature, $total, $snaps, $accModel, $accEngine, $passes, $last) {
            if ($passes) {
                PrdSequenceModel::where('status', 'ACTIVE')->update(['status' => 'FROZEN']);
            }

            return PrdSequenceModel::create([
                'version' => ($last->version ?? 0) + 1, 'status' => $passes ? 'ACTIVE' : 'CANDIDATE',
                'weights' => ['w' => $w, 'sig' => $signature], 'feature_names' => $names,
                'pairs_count' => $total, 'arrangements_count' => count($snaps),
                'holdout_accuracy_model' => $accModel, 'holdout_accuracy_engine' => $accEngine, 'trained_at' => now(),
            ]);
        });
    }
}
