<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Services\Acc\LedgerService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Trial Balance -- mockup blueprint: kontrol saldo buku besar POSTED sebelum
// laporan keuangan & tutup periode. Sumber: LedgerService (bukan angka statis).
new #[Title('Trial Balance — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $asOf = '';

    public string $search = '';

    public string $classFilter = '';

    public function mount(): void
    {
        $this->asOf = now()->toDateString();
    }

    #[Computed]
    public function rows()
    {
        return app(LedgerService::class)->trialBalance($this->asOf)
            ->when($this->classFilter !== '', fn ($c) => $c->filter(fn ($r) => match ($this->classFilter) {
                'ASSET' => $r->account->account_type === 'ASSET',
                'LIABILITY' => in_array($r->account->account_type, ['LIABILITY', 'EQUITY'], true),
                'REVENUE' => in_array($r->account->account_type, ['REVENUE', 'OTHER_INCOME'], true),
                'EXPENSE' => in_array($r->account->account_type, ['COGS', 'EXPENSE', 'OTHER_EXPENSE'], true),
                default => true,
            }))
            ->when($this->search !== '', fn ($c) => $c->filter(fn ($r) => str_contains(strtolower($r->account->account_code.' '.$r->account->account_name), strtolower($this->search))))
            ->values();
    }

    #[Computed]
    public function totals(): array
    {
        $all = app(LedgerService::class)->trialBalance($this->asOf);

        return [
            'debit' => round($all->sum('debit'), 2), 'credit' => round($all->sum('credit'), 2),
            'accounts' => $all->filter(fn ($r) => $r->debit > 0 || $r->credit > 0)->count(),
            'diff' => round($all->sum('debit') - $all->sum('credit'), 2),
        ];
    }

    public function classLabel(string $type): string
    {
        return ['ASSET' => 'Asset', 'LIABILITY' => 'Liability', 'EQUITY' => 'Equity', 'REVENUE' => 'Revenue', 'COGS' => 'Cost of sales', 'EXPENSE' => 'Expense', 'OTHER_INCOME' => 'Other income', 'OTHER_EXPENSE' => 'Other expense'][$type] ?? $type;
    }

    public function export()
    {
        return $this->csvDownload('trial-balance-'.$this->asOf.'.csv',
            ['Account code', 'Account name', 'Account class', 'Debit', 'Credit'],
            $this->rows->map(fn ($r) => [$r->account->account_code, $r->account->account_name, $this->classLabel($r->account->account_type), $r->debit, $r->credit]));
    }

    public function refreshView(): void
    {
        unset($this->rows, $this->totals);
        session()->flash('flash', str_replace(':date', Fmt::date($this->asOf), t('Trial balance recalculated from POSTED vouchers as at :date.')));
    }
};
?>

<x-ledger.page :title="t('Trial Balance')" :subtitle="t('Control report for posted ledger balances before financial reporting and close.')">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="asOf">
        <button type="button" class="lg-btn light" wire:click="export">⇩ {{ t('Export Excel') }}</button>
        <button type="button" class="lg-btn primary" wire:click="refreshView">↻ {{ t('Refresh') }}</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Total Debits')" icon="↓" :value="Fmt::short($this->totals['debit'])" :pill="abs($this->totals['diff']) < 0.01 ? t('Balanced') : t('Check')" :tone="abs($this->totals['diff']) < 0.01 ? 'good' : 'bad'" />
        <x-ledger.metric :label="t('Total Credits')" icon="↑" :value="Fmt::short($this->totals['credit'])" :pill="abs($this->totals['diff']) < 0.01 ? t('Balanced') : t('Check')" :tone="abs($this->totals['diff']) < 0.01 ? 'good' : 'bad'" />
        <x-ledger.metric :label="t('Accounts With Balance')" icon="☷" :value="$this->totals['accounts']" :sub="t('Chart of accounts')" />
        <x-ledger.metric :label="t('Out-of-Balance')" icon="✓" :value="Fmt::rp($this->totals['diff'])" :pill="abs($this->totals['diff']) < 0.01 ? t('No exceptions') : t('Exception')" :tone="abs($this->totals['diff']) < 0.01 ? 'good' : 'bad'" />
    </div>

    <x-ledger.panel :title="t('Trial Balance Register')" :subtitle="str_replace(':date', Fmt::date($asOf), t('As at :date')).' · IDR'">
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search account code or name...') }}">
            <select class="lg-select" wire:model.live="classFilter" x-data x-effect="$el.value = @js($classFilter)">
                <option value="">{{ t('All account classes') }}</option><option value="ASSET">{{ t('Assets') }}</option><option value="LIABILITY">{{ t('Liabilities & Equity') }}</option><option value="REVENUE">{{ t('Revenue') }}</option><option value="EXPENSE">{{ t('Expenses') }}</option>
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>{{ t('Account Code') }}</th><th>{{ t('Account Name') }}</th><th>{{ t('Account Class') }}</th><th class="r">{{ t('Debit') }}</th><th class="r">{{ t('Credit') }}</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $r)
                        <tr wire:key="tb-{{ $r->account->account_id }}">
                            <td class="lg-money">{{ $r->account->account_code }}</td>
                            <td><span class="lg-name">{{ $r->account->account_name }}</span></td>
                            <td>{{ t($this->classLabel($r->account->account_type)) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($r->debit, true) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($r->credit, true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="lg-empty">{{ t('No POSTED vouchers up to this date yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><b>{{ str_replace(':n', $this->rows->count(), t('Total (:n accounts shown)')) }}</b><b class="lg-money">{{ Fmt::rp($this->rows->sum('debit')) }}</b><b class="lg-money">{{ Fmt::rp($this->rows->sum('credit')) }}</b></div>
    </x-ledger.panel>
</x-ledger.page>
