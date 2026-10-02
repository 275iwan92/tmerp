<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Enam akun biaya terbesar pada periode itu (HPP, beban, beban lain), sisanya digabung. Referensi: top expenses (Zoho Books, Jurnal, Accurate).
final class TopExpenses extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'top_expenses', 'title' => 'Top Expenses', 'type' => 'chart', 'dashboard' => 'CASHFLOW', 'module' => 'FINACC', 'min_level' => 3,
            'description' => 'The largest expense accounts of the period from posted vouchers.',
            'icon' => 'fa-chart-pie', 'tone' => 'danger', 'phrases' => ['biaya terbesar', 'pengeluaran terbesar', 'beban terbesar', 'top expenses'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $all = collect($n->profitLoss($n->from(), $n->to())['expenses']);
        $top = $all->take(6);
        $rest = (float) $all->slice(6)->sum();
        if ($rest > 0) {
            $top = $top->put(t('Other'), $rest);
        }

        return ['caption' => raw($n->rangeText()), 'source' => 'tm2acc.fin_voucher_line + fin_account (COGS, EXPENSE)',
            'data' => ['chart' => 'donut', 'format' => 'money', 'height' => '240px', 'labels' => $top->keys()->all(), 'series' => [['name' => t('Expense'), 'data' => $top->values()->all()]]]];
    }
}
