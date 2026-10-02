<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Pendapatan dan biaya per periode dari buku besar (voucher POSTED). Referensi: income vs expense (Zoho Books, Business Central).
final class IncomeVsExpense extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'income_vs_expense', 'title' => 'Income vs Expense', 'type' => 'chart', 'group' => 'Finance', 'module' => 'FINACC', 'min_level' => 4, 'size' => 8,
            'description' => 'Income and expense per period from posted vouchers.',
            'icon' => 'fa-scale-balanced', 'phrases' => ['pendapatan dan biaya', 'pendapatan vs biaya', 'laba rugi', 'income vs expense', 'profit and loss'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $buckets = $n->buckets();
        $pl = array_map(fn (array $b) => $n->profitLoss($b['from'], $b['to']), $buckets);

        return ['caption' => 'Posted vouchers', 'source' => 'tm2acc.fin_voucher_line + fin_account (REVENUE, COGS, EXPENSE)',
            'data' => ['chart' => 'bar', 'format' => 'money', 'height' => '300px', 'labels' => array_column($buckets, 'label'),
                'series' => [['name' => t('Income'), 'data' => array_column($pl, 'income'), 'tone' => 'success'], ['name' => t('Expense'), 'data' => array_column($pl, 'expense'), 'tone' => 'danger']]],
            'open' => $this->open('ACC_TB', 'Trial Balance', 'fa-solid fa-scale-balanced')];
    }
}
