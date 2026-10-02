<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Saldo tiap akun kas dan bank (akun buku besar ber-arus-kas CASH) dari voucher yang sudah diposting, per hari ini.
// Referensi: cash availability per account (Accurate, Zoho Books, NetSuite).
final class CashBalance extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'cash_balance', 'title' => 'Cash & Bank Balance', 'type' => 'list', 'dashboard' => 'CASHFLOW', 'module' => 'FINACC', 'min_level' => 3,
            'description' => 'Balance of every cash and bank account from posted vouchers, as of today.',
            'icon' => 'fa-building-columns', 'tone' => 'success', 'phrases' => ['saldo kas', 'saldo bank', 'kas dan bank', 'cash balance', 'bank balance'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $accounts = $n->cashBalances();
        $rows = array_map(fn (array $a) => ['title' => $a['name'], 'sub' => $a['code'], 'value' => $this->money($a['balance']), 'tone' => $a['balance'] < 0 ? 'danger' : 'success'], $accounts);

        return ['caption' => raw($this->say('Total :v', ['v' => $this->money(array_sum(array_column($accounts, 'balance')))])), 'source' => 'tm2acc.fin_voucher_line + fin_account (cf_line CASH)',
            'data' => ['rows' => $rows, 'empty' => 'No cash or bank account has a posted entry'], 'open' => $this->open('FIN_CASH', 'Cashier (Cash & Bank)', 'fa-solid fa-building-columns')];
    }
}
