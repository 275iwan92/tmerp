<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Persen baris penerimaan barang yang lulus pemeriksaan mutu dari yang diperiksa pada periode itu.
final class IncomingPassRate extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'incoming_pass_rate', 'title' => 'Incoming QC Pass Rate', 'type' => 'kpi', 'dashboard' => 'QUALITY', 'module' => 'PROD',
            'description' => 'Share of goods receipt lines that passed the quality check, of those checked in the period.',
            'icon' => 'fa-circle-check', 'tone' => 'success', 'phrases' => ['lulus qc', 'tingkat lulus qc', 'qc pass rate', 'pass rate'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $pct = fn (array $q) => ($q['pass'] + $q['fail']) ? $q['pass'] / ($q['pass'] + $q['fail']) * 100 : null;
        $now = $n->incomingQc($n->from(), $n->to());
        if ($pct($now) === null) {
            return ['source' => 'tm2pur.pv_line (qc_quality_status)', 'data' => ['value' => '—', 'format' => 'percent', 'hint' => 'No receipt line was checked in this period']];
        }

        return ['source' => 'tm2pur.pv_line (qc_quality_status)', 'data' => ['value' => $pct($now), 'previous' => $pct($n->incomingQc($prevFrom, $prevTo)), 'format' => 'percent',
            'note' => raw($this->say('Passed :a of :b lines', ['a' => $now['pass'], 'b' => $now['pass'] + $now['fail']]))]];
    }
}
