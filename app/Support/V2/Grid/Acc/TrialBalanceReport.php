<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucherLine;
use App\Services\Acc\LedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Trial Balance (v1: pages::finance.trial-balance): saldo tiap akun postable per HARI INI, dihitung LedgerService dari voucher
// POSTED (rumus yang sama dengan v1). Akun nonaktif hanya tampil bila masih bersaldo.
class TrialBalanceReport extends AccGrid
{
    // id akun => saldo bersih (debit positif, kredit negatif).
    private array $balances = [];

    public function slug(): string
    {
        return 'acc-rpt-trial-balance';
    }

    public function title(): string
    {
        return 'Trial Balance';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function group(): string
    {
        return 'Financial Reports';
    }

    public function icon(): string
    {
        return 'fa-solid fa-scale-balanced';
    }

    public function guide(): ?string
    {
        return 'Balance of every postable account as of today, from posted journals. Total Debit and total Credit must be equal; '
            .'the journal lines behind a balance are in General Ledger.';
    }

    protected function models(): array
    {
        return [FinAccount::class, FinVoucherLine::class];
    }

    public function search(): string
    {
        return 'Search account code, account name...';
    }

    protected function query(): Builder
    {
        return FinAccount::query()->where('is_postable', true)->orderBy('account_code');
    }

    protected function prefetch(Collection $items): void
    {
        $this->balances = app(LedgerService::class)->balancesAsOf(now()->toDateString())->all();
    }

    public function rows(): array
    {
        // Akun nonaktif tanpa saldo dibuang (aturan LedgerService::trialBalance).
        return array_values(array_filter(parent::rows(), fn (array $r) => $r['active'] || $r['debit'] > 0 || $r['credit'] > 0));
    }

    public function columns(): array
    {
        return [
            ['key' => 'account', 'label' => 'Account', 'type' => 'identity', 'code' => 'account_code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Account Class'],
            ['key' => 'normal', 'label' => 'Normal Balance', 'visible' => false],
            ['key' => 'debit', 'label' => 'Debit', 'type' => 'money', 'sum' => true],
            ['key' => 'credit', 'label' => 'Credit', 'type' => 'money', 'sum' => true],
            ['key' => 'state', 'label' => 'Balance', 'type' => 'badge', 'tones' => ['Has balance' => 'info', 'Zero' => 'neutral']],
        ];
    }

    protected function row(Model $m): array
    {
        $net = round((float) ($this->balances[$m->account_id] ?? 0), 2);

        return [
            'account' => $m->account_name, 'account_code' => $m->account_code, 'class' => $this->accountClass($m->account_type),
            'normal' => $m->normal_balance === 'DEBIT' ? t('Debit') : t('Credit'), 'debit' => $net > 0 ? $net : 0.0, 'credit' => $net < 0 ? -$net : 0.0,
            'state' => abs($net) > 0.004 ? 'Has balance' : 'Zero', 'active' => (bool) $m->activate,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Has balance', 'where' => ['state' => 'Has balance'], 'tone' => 'info'],
            ['label' => 'Zero', 'where' => ['state' => 'Zero'], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['class' => 'Account Class', 'state' => 'Balance'];
    }

    public function groups(): array
    {
        return ['class' => 'Account Class'];
    }
}
