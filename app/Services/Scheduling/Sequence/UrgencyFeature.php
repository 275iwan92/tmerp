<?php

/*
 * REMARK
 * Tujuan       : Fitur urgensi: sisa waktu ke due date dikurangi jam proses operasi (slack), makin kecil makin mendesak.
 * Sumber hulu  : prd_wo.due_date, setup + run menit operasi.
 * Input        : operasi (due, setup, run), konteks now.
 * Output       : -slack/240 jam, terbatas -3..3 (makin besar = makin mendesak; tanpa due date = paling tidak mendesak).
 * Dipakai oleh : FeatureSet.
 * Status       : Aktif.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

final class UrgencyFeature extends Feature
{
    public function name(): string
    {
        return 'urgency';
    }

    public function value(array $op, array $ctx): float
    {
        if (empty($op['due'])) {
            return -3.0;
        }
        $hoursLeft = ($op['due']->getTimestamp() - $ctx['now']->getTimestamp()) / 3600;
        $work = ((float) ($op['setup'] ?? 0) + (float) ($op['run'] ?? 0)) / 60;

        return self::clamp(-($hoursLeft - $work) / 240);
    }
}
