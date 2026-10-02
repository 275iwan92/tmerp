<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Sisa hutang supplier per umur lewat jatuh tempo, per hari ini. Referensi: AP aging (Business Central, SAP).
final class PayablesAging extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'payables_aging', 'title' => 'Payables Aging', 'type' => 'chart', 'dashboard' => 'AR_AP', 'module' => 'FINACC', 'size' => 6,
            'description' => 'Unpaid supplier invoices grouped by days past the due date.',
            'icon' => 'fa-hourglass-half', 'phrases' => ['umur hutang', 'aging hutang', 'hutang per umur', 'payables aging', 'ap aging'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $rows = $n->payables();
        $buckets = ['Not due' => [null, 0], '1-30' => [1, 30], '31-60' => [31, 60], '61-90' => [61, 90], '> 90' => [91, null]];
        $sum = fn (array $b) => (float) $rows->filter(fn ($r) => ($b[0] === null || $r->days_overdue >= $b[0]) && ($b[1] === null || $r->days_overdue <= $b[1]))->sum('outstanding');

        return ['caption' => 'Days past due, as of today', 'source' => 'tm2acc.v1_fin_ap_aging', 'open' => $this->open('FIN_AP_RECEIPT', 'Supplier Invoice Receipt', 'fa-solid fa-file-invoice'),
            'data' => ['chart' => 'bar', 'format' => 'money', 'height' => '240px', 'legend' => false,
                'labels' => array_map(fn (string $l) => $l === 'Not due' ? t('Not due') : $l, array_keys($buckets)),
                'series' => [['name' => t('Outstanding'), 'data' => array_map($sum, array_values($buckets)), 'tone' => 'danger']]]];
    }
}
