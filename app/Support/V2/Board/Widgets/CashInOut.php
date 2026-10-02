<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Kas masuk dan kas keluar per periode (batang berdampingan). Pindah buku antar akun kas tidak dihitung.
// Referensi: cash flow in/out per month (Zoho Books, Business Central, Jurnal, Accurate).
final class CashInOut extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'cash_in_out', 'title' => 'Cash In vs Cash Out', 'type' => 'chart', 'dashboard' => 'CASHFLOW', 'module' => 'FINACC', 'min_level' => 3, 'size' => 8,
            'description' => 'Cash received and cash paid per period on the cash and bank accounts.',
            'icon' => 'fa-chart-column', 'phrases' => ['kas masuk', 'kas keluar', 'grafik arus kas', 'cash in', 'cash out', 'cash flow chart'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $buckets = $n->buckets();
        $flow = array_map(fn (array $b) => $n->cashFlow($b['from'], $b['to']), $buckets);

        return ['caption' => 'Posted cash and bank entries', 'source' => 'tm2acc.fin_voucher_line + fin_account (cf_line CASH)',
            'data' => ['chart' => 'bar', 'format' => 'money', 'height' => '300px', 'labels' => array_column($buckets, 'label'),
                'series' => [['name' => t('Cash in'), 'data' => array_column($flow, 'in'), 'tone' => 'success'], ['name' => t('Cash out'), 'data' => array_column($flow, 'out'), 'tone' => 'danger']]]];
    }
}
