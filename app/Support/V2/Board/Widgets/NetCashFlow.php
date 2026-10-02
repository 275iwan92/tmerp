<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Kas masuk dikurangi kas keluar pada periode itu (akun kas dan bank, voucher POSTED), dibanding periode lalu.
// Referensi: net cash flow KPI (NetSuite), cash flow in/out (Zoho Books, Jurnal, Accurate).
final class NetCashFlow extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'net_cash_flow', 'title' => 'Net Cash Flow', 'type' => 'kpi', 'dashboard' => 'CASHFLOW', 'module' => 'FINACC', 'min_level' => 3,
            'description' => 'Cash in minus cash out of the period on the cash and bank accounts.',
            'icon' => 'fa-arrow-right-arrow-left', 'phrases' => ['arus kas', 'arus kas bersih', 'kas masuk keluar', 'net cash flow', 'cashflow'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $now = $n->cashFlow($n->from(), $n->to());
        $before = $n->cashFlow($prevFrom, $prevTo);

        return ['source' => 'tm2acc.fin_voucher_line + fin_account (cf_line CASH)', 'data' => ['value' => $now['in'] - $now['out'], 'previous' => $before['in'] - $before['out'], 'format' => 'money',
            'hint' => raw($n->rangeText()), 'note' => raw($this->say('In :a · Out :b', ['a' => $this->money($now['in']), 'b' => $this->money($now['out'])]))]];
    }
}
