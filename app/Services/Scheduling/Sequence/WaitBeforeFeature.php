<?php

/*
 * REMARK
 * Tujuan       : Fitur jam tunggu proses sebelumnya (mis. pengeringan sesudah CETAK): menit tunggu aturan proses sebelumnya dari WO yang sama / 60 / 24.
 * Sumber hulu  : Wait Rules (prod_wait_rule + config ppic.wait_after) lewat PlanLoader::waitRules, urutan proses WO.
 * Input        : operasi (wo_id, seq), konteks opsByWo dan waitRules.
 * Output       : 0..3.
 * Dipakai oleh : FeatureSet.
 * Status       : Aktif.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

final class WaitBeforeFeature extends Feature
{
    public function name(): string
    {
        return 'wait_before';
    }

    public function value(array $op, array $ctx): float
    {
        $prev = null;
        foreach ($ctx['opsByWo'][$op['wo_id']] ?? [] as $o) {
            if ($o['seq'] < $op['seq'] && (! $prev || $o['seq'] > $prev['seq'])) {
                $prev = $o;
            }
        }
        if (! $prev) {
            return 0.0;
        }
        $name = strtoupper((string) $prev['process']);
        foreach ($ctx['waitRules'] as $keyword => $minutes) {
            if ((string) $keyword !== '' && str_contains($name, (string) $keyword)) {
                return self::clamp($minutes / 60 / 24, 0.0, 3.0);
            }
        }

        return 0.0;
    }
}
