<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Laba bersih periode itu = pendapatan - biaya dari buku besar (voucher POSTED), dibanding periode lalu; baris bawah = margin.
// Referensi: net profit and margin (Business Central, Jurnal, Accurate).
final class NetProfit extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'net_profit', 'title' => 'Net Profit', 'type' => 'kpi', 'group' => 'Finance', 'module' => 'FINACC', 'min_level' => 4, 'size' => 3,
            'description' => 'Income minus expense of the period from posted vouchers, with the margin.',
            'icon' => 'fa-coins', 'tone' => 'success', 'phrases' => ['laba bersih', 'laba', 'keuntungan', 'net profit', 'profit'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $now = $n->profitLoss($n->from(), $n->to());
        $before = $n->profitLoss($prevFrom, $prevTo);
        $margin = $now['income'] > 0 ? ($now['income'] - $now['expense']) / $now['income'] * 100 : null;

        return ['source' => 'tm2acc.fin_voucher_line + fin_account', 'data' => ['value' => $now['income'] - $now['expense'], 'previous' => $before['income'] - $before['expense'], 'format' => 'money',
            'hint' => raw($margin === null ? t('No income posted in this period') : $this->say('Margin :p', ['p' => Locale::percent($margin, 1)])),
            'note' => raw($this->say('Income :a · Expense :b', ['a' => $this->money($now['income']), 'b' => $this->money($now['expense'])]))]];
    }
}
