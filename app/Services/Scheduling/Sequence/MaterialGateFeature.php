<?php

/*
 * REMARK
 * Tujuan       : Fitur kesiapan bahan: READY = 1; WAIT = minus jam menunggu bahan / 24; BLOCK = -2.
 * Sumber hulu  : prd_wo_operation.gate_material dan material_ready_at.
 * Input        : operasi (gates[0], material_ready_at), konteks now.
 * Output       : -3..1.
 * Dipakai oleh : FeatureSet.
 * Status       : Aktif.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

final class MaterialGateFeature extends Feature
{
    public function name(): string
    {
        return 'material_gate';
    }

    public function value(array $op, array $ctx): float
    {
        $gate = strtoupper((string) ($op['gates'][0] ?? 'READY'));
        if ($gate === 'BLOCK') {
            return -2.0;
        }
        if ($gate === 'WAIT') {
            $hours = ! empty($op['material_ready_at']) ? max(0, ($op['material_ready_at']->getTimestamp() - $ctx['now']->getTimestamp()) / 3600) : 24;

            return self::clamp(-$hours / 24, -3.0, 0.0);
        }

        return 1.0;
    }
}
