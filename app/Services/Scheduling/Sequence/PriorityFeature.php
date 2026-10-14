<?php

/*
 * REMARK
 * Tujuan       : Fitur prioritas WO: hot job = 1, URGENT (Move up) = 0.5, biasa = 0.
 * Sumber hulu  : prd_wo.is_hot_job, prd_wo.priority.
 * Input        : operasi (hot, wo_id), konteks woPriority.
 * Output       : 0, 0.5 atau 1.
 * Dipakai oleh : FeatureSet.
 * Status       : Aktif.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

final class PriorityFeature extends Feature
{
    public function name(): string
    {
        return 'priority';
    }

    public function value(array $op, array $ctx): float
    {
        if (! empty($op['hot'])) {
            return 1.0;
        }

        return ($ctx['woPriority'][$op['wo_id']] ?? '') === 'URGENT' ? 0.5 : 0.0;
    }
}
