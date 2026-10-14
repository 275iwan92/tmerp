<?php

/*
 * REMARK
 * Tujuan       : Fitur durasi proses: setup + run dalam jam, dibagi 8 (satu shift).
 * Sumber hulu  : prd_wo_operation setup_*_min dan run_minutes.
 * Input        : operasi (setup, run).
 * Output       : 0..3.
 * Dipakai oleh : FeatureSet.
 * Status       : Aktif.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

final class DurationFeature extends Feature
{
    public function name(): string
    {
        return 'duration';
    }

    public function value(array $op, array $ctx): float
    {
        return self::clamp(((float) ($op['setup'] ?? 0) + (float) ($op['run'] ?? 0)) / 60 / 8, 0.0, 3.0);
    }
}
