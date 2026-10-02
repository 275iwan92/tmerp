<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Lima customer dengan sisa tagihan terbesar, per hari ini. Referensi: top 10 debtors (SAP).
final class TopDebtors extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'top_debtors', 'title' => 'Top Debtors', 'type' => 'chart', 'dashboard' => 'AR_AP', 'module' => 'FINACC', 'size' => 6,
            'description' => 'The five customers with the largest unpaid invoice balance, as of today.',
            'icon' => 'fa-user-clock', 'tone' => 'warn', 'phrases' => ['piutang terbesar', 'customer piutang terbesar', 'debitur terbesar', 'top debtors'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->receivables()->groupBy(fn ($r) => (int) $r->partner_id)->map(fn ($set) => (float) $set->sum('outstanding'))->sortDesc()->take(5);
        $names = $n->customerNames($by->keys()->all());

        return ['caption' => 'Unpaid balance, as of today', 'source' => 'tm2acc.v1_fin_ar_aging · tm2mst.cust_customer',
            'data' => ['chart' => 'hbar', 'format' => 'money', 'height' => '240px', 'legend' => false,
                'labels' => $by->keys()->map(fn ($id) => $names[$id] ?? '#'.$id)->all(), 'series' => [['name' => t('Outstanding'), 'data' => $by->values()->all(), 'tone' => 'warn']]],
            'open' => $this->open('FIN_AR_INVOICE', 'Customer Invoice', 'fa-solid fa-file-invoice-dollar')];
    }
}
