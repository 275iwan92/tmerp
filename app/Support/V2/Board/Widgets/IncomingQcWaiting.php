<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Baris penerimaan barang yang masih menunggu pemeriksaan mutu, per hari ini; baris bawah = hasil pemeriksaan pada periode itu.
final class IncomingQcWaiting extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'incoming_qc_waiting', 'title' => 'Incoming QC Waiting', 'type' => 'kpi', 'dashboard' => 'QUALITY', 'module' => 'PROD',
            'description' => 'Goods receipt lines that still wait for the quality check, as of today, with the checks done in the period.',
            'icon' => 'fa-clipboard-check', 'tone' => 'warn', 'phrases' => ['qc menunggu', 'qc penerimaan', 'menunggu qc', 'incoming qc', 'qc waiting'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $q = $n->incomingQc($n->from(), $n->to());

        return ['source' => 'tm2pur.pv_line (qc_quality_status) · pv_receipt', 'data' => ['value' => $q['waiting'], 'format' => 'number', 'good_when' => 'down', 'hint' => 'Receipt lines',
            'note' => raw($this->say('Checked in the period: passed :a · not passed :b', ['a' => $q['pass'], 'b' => $q['fail']]))]];
    }
}
