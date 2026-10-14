<?php

/*
 * REMARK
 * Tujuan       : Model linear pairwise ranking urutan job: skor = bobot . fitur; dilatih dengan regresi logistik + L2 (gradient descent sederhana, deterministik).
 * Sumber hulu  : pasangan latih dari prd_plan_trace FINAL (selisih fitur job yang didahulukan officer minus yang sesudahnya).
 * Input        : bobot (nama => nilai), vektor fitur (nama => nilai), daftar pasangan untuk latihan.
 * Output       : skor float, bobot hasil latih, akurasi pasangan.
 * Dipakai oleh : SequenceTrainer, SequenceSuggester.
 * Status       : Aktif. PHP murni, tanpa pustaka luar; semua angka dibulatkan agar hasil sama di mesin mana pun.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

final class SequenceModel
{
    /** @param array<string, float> $w @param array<string, float> $x */
    public static function score(array $w, array $x): float
    {
        $s = 0.0;
        foreach ($w as $name => $weight) {
            $s += $weight * ($x[$name] ?? 0.0);
        }

        return $s;
    }

    /**
     * Logistic regression on difference vectors. Every pair (a before b) gives the sample (fa - fb) with label 1 and its mirror (fb - fa) with label 0,
     * so the model has no bias and ranks symmetrically.
     *
     * @param  list<array<string, float>>  $diffs  fa - fb of each pair, a = the job the officer put first
     * @param  list<string>  $names
     * @return array<string, float>
     */
    public static function train(array $diffs, array $names, int $epochs = 400, float $lr = 0.3, float $l2 = 0.01): array
    {
        $w = array_fill_keys($names, 0.0);
        $n = count($diffs);
        if ($n === 0) {
            return $w;
        }
        for ($e = 0; $e < $epochs; $e++) {
            $g = array_fill_keys($names, 0.0);
            foreach ($diffs as $d) {
                $p = 1 / (1 + exp(-max(-30, min(30, self::score($w, $d)))));
                foreach ($names as $k) {
                    $g[$k] += ($p - 1) * ($d[$k] ?? 0.0);    // label 1; the mirrored sample gives the same gradient, so one pass is enough
                }
            }
            foreach ($names as $k) {
                $w[$k] -= $lr * ($g[$k] / $n + $l2 * $w[$k]);
            }
        }

        return array_map(fn ($v) => round($v, 6), $w);
    }

    /** Share of pairs where the model puts a before b. @param list<array<string, float>> $diffs */
    public static function accuracy(array $w, array $diffs): float
    {
        if (! $diffs) {
            return 0.0;
        }
        $ok = 0;
        foreach ($diffs as $d) {
            $ok += self::score($w, $d) > 0 ? 1 : 0;
        }

        return round($ok / count($diffs), 4);
    }
}
