<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Services\Acc\LedgerService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Balance Sheet -- mockup blueprint: posisi keuangan dari saldo GL POSTED.
// Laba tahun berjalan masuk Laba Ditahan selama belum ada voucher penutup,
// jadi Aset = Kewajiban + Ekuitas selalu seimbang (lihat LedgerService).
new #[Title('Balance Sheet — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $asOf = '';

    public string $compareTo = '';

    public bool $compare = false;

    public function mount(): void
    {
        $this->asOf = now()->toDateString();
        $this->compareTo = now()->subYear()->endOfYear()->toDateString();
    }

    #[Computed]
    public function bs(): array
    {
        return app(LedgerService::class)->balanceSheet($this->asOf);
    }

    #[Computed]
    public function prior(): array
    {
        return app(LedgerService::class)->balanceSheet($this->compareTo);
    }

    public function growth(string $key): ?float
    {
        $p = $this->prior[$key] ?? 0;

        return $p != 0 ? round((($this->bs[$key] - $p) / abs($p)) * 100, 1) : null;
    }

    public function export()
    {
        $rows = [];
        foreach (['CASH', 'AR', 'INVENTORY', 'OTHER_CA', 'PPE', 'INTANGIBLE', 'total_assets', 'AP', 'ACCRUED', 'BANK_LOAN', 'total_liabilities', 'SHARE_CAPITAL', 'RETAINED', 'total_equity', 'total_le'] as $k) {
            $rows[] = [LedgerService::FS_LABELS[$k] ?? ucwords(str_replace('_', ' ', $k)), $this->bs[$k] ?? 0, $this->prior[$k] ?? 0];
        }

        return $this->csvDownload('balance-sheet-'.$this->asOf.'.csv', ['Line', 'As at '.$this->asOf, 'As at '.$this->compareTo], $rows);
    }
};
?>

<x-ledger.page :title="t('Balance Sheet')" :subtitle="str_replace(':date', Fmt::date($asOf), t('Statement of financial position as at :date.'))">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="asOf">
        <button type="button" class="lg-btn light" wire:click="export">⇩ {{ t('Export') }}</button>
        <button type="button" class="lg-btn primary" wire:click="$toggle('compare')">▤ {{ t('Compare Periods') }}</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Total Assets')" icon="◇" :value="Fmt::short($this->bs['total_assets'])" :pill="$this->growth('total_assets') !== null ? Fmt::pct($this->growth('total_assets')) : null" :tone="($this->growth('total_assets') ?? 0) >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric :label="t('Total Liabilities')" icon="▣" :value="Fmt::short($this->bs['total_liabilities'])" :sub="t('Debt-to-equity').': '.($this->bs['total_equity'] != 0 ? number_format($this->bs['total_liabilities'] / $this->bs['total_equity'], 2).'x' : '—')" />
        <x-ledger.metric :label="t('Total Equity')" icon="◈" :value="Fmt::short($this->bs['total_equity'])" :pill="$this->growth('total_equity') !== null ? Fmt::pct($this->growth('total_equity')) : null" :tone="($this->growth('total_equity') ?? 0) >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric :label="t('Current Ratio')" icon="↔" :value="$this->bs['current_liabilities'] != 0 ? number_format($this->bs['current_assets'] / $this->bs['current_liabilities'], 2).'x' : '—'" :pill="$this->bs['current_liabilities'] != 0 && $this->bs['current_assets'] / $this->bs['current_liabilities'] >= 1.5 ? t('Healthy liquidity') : t('Watch liquidity')" :tone="$this->bs['current_liabilities'] != 0 && $this->bs['current_assets'] / $this->bs['current_liabilities'] >= 1.5 ? 'good' : 'warn'" />
    </div>

    <x-ledger.panel :title="t('Statement of Financial Position')" :subtitle="t('Unaudited management accounts · IDR')">
        @if ($compare)
            <x-slot:action>
                <span class="lg-sub">{{ t('Compare with') }}</span>
                <input type="date" class="lg-input" wire:model.live="compareTo">
            </x-slot:action>
        @endif
        <div class="lg-statements">
            <x-ledger.srow kind="group" :label="mb_strtoupper(t('Assets'))" />
            <x-ledger.srow kind="group" :label="t('Current assets')" />
            <x-ledger.srow :label="t('Cash and cash equivalents')" :value="Fmt::rp($this->bs['CASH'])" />
            <x-ledger.srow :label="t('Trade accounts receivable, net')" :value="Fmt::rp($this->bs['AR'])" />
            <x-ledger.srow :label="t('Inventories')" :value="Fmt::rp($this->bs['INVENTORY'])" />
            <x-ledger.srow :label="t('Prepayments, tax and other current assets')" :value="Fmt::rp($this->bs['OTHER_CA'])" />
            <x-ledger.srow kind="total" :label="t('Total current assets')" :value="Fmt::rp($this->bs['current_assets'])" />
            <x-ledger.srow kind="group" :label="t('Non-current assets')" />
            <x-ledger.srow :label="t('Property, plant and equipment, net')" :value="Fmt::rp($this->bs['PPE'])" />
            <x-ledger.srow :label="t('Intangible assets, net')" :value="Fmt::rp($this->bs['INTANGIBLE'])" />
            <x-ledger.srow kind="total" :label="mb_strtoupper(t('Total Assets'))" :value="Fmt::rp($this->bs['total_assets'])" />

            <x-ledger.srow kind="group" :label="mb_strtoupper(t('Liabilities'))" />
            <x-ledger.srow :label="t('Trade accounts payable')" :value="Fmt::rp($this->bs['AP'])" />
            <x-ledger.srow :label="t('Taxes, accruals and other liabilities')" :value="Fmt::rp($this->bs['ACCRUED'])" />
            <x-ledger.srow :label="t('Bank loans')" :value="Fmt::rp($this->bs['BANK_LOAN'])" />
            <x-ledger.srow kind="total" :label="t('Total liabilities')" :value="Fmt::rp($this->bs['total_liabilities'])" />
            <x-ledger.srow kind="group" :label="mb_strtoupper(t('Equity'))" />
            <x-ledger.srow :label="t('Share capital')" :value="Fmt::rp($this->bs['SHARE_CAPITAL'])" />
            <x-ledger.srow :label="t('Retained earnings (incl. current year result)')" :value="Fmt::rp($this->bs['RETAINED'])" />
            <x-ledger.srow kind="total" :label="t('Total equity')" :value="Fmt::rp($this->bs['total_equity'])" />
            <x-ledger.srow kind="net" :label="mb_strtoupper(t('Total Liabilities and Equity'))" :value="Fmt::rp($this->bs['total_le'])" />
        </div>
        @if (abs($this->bs['total_assets'] - $this->bs['total_le']) > 0.01)
            <div class="lg-alert err" style="margin:0 19px 16px">{{ str_replace(':amount', Fmt::rp($this->bs['total_assets'] - $this->bs['total_le']), t('Balance sheet out of balance by :amount — check Trial Balance.')) }}</div>
        @endif
    </x-ledger.panel>

    @if ($compare)
        <x-ledger.panel :title="t('Comparative View')" :subtitle="Fmt::date($asOf).' vs '.Fmt::date($compareTo)">
            <div class="lg-tablewrap">
                <table>
                    <thead><tr><th>{{ t('Line') }}</th><th class="r">{{ Fmt::date($asOf) }}</th><th class="r">{{ Fmt::date($compareTo) }}</th><th class="r">{{ t('Change') }}</th></tr></thead>
                    <tbody>
                        @foreach (['CASH', 'AR', 'INVENTORY', 'OTHER_CA', 'PPE', 'INTANGIBLE', 'total_assets', 'AP', 'ACCRUED', 'BANK_LOAN', 'SHARE_CAPITAL', 'RETAINED', 'total_le'] as $k)
                            <tr>
                                <td>{{ t(\App\Services\Acc\LedgerService::FS_LABELS[$k] ?? ucwords(str_replace('_', ' ', $k))) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($this->bs[$k]) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($this->prior[$k]) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($this->bs[$k] - $this->prior[$k]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ledger.panel>
    @endif
</x-ledger.page>
