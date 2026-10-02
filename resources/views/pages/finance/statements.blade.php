<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Services\Acc\LedgerService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Financial Statements -- mockup process-engine blueprint: 3 laporan inti dalam
// 1 halaman (Neraca, Laba Rugi, Arus Kas), semuanya dari GL POSTED.
new #[Title('Financial Statements — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $asOf = '';

    public function mount(): void
    {
        $this->asOf = now()->toDateString();
    }

    private function from(): string
    {
        return date('Y-01-01', strtotime($this->asOf));
    }

    #[Computed]
    public function bs(): array
    {
        return app(LedgerService::class)->balanceSheet($this->asOf);
    }

    #[Computed]
    public function is(): array
    {
        return app(LedgerService::class)->incomeStatement($this->from(), $this->asOf);
    }

    #[Computed]
    public function cf(): array
    {
        return app(LedgerService::class)->cashFlow($this->from(), $this->asOf);
    }

    #[Computed]
    public function priorYear(): array
    {
        $y = (int) date('Y', strtotime($this->asOf)) - 1;

        return app(LedgerService::class)->incomeStatement("{$y}-01-01", date('Y-m-d', strtotime($this->asOf.' -1 year')));
    }

    public function growth(string $key): ?float
    {
        $p = $this->priorYear[$key] ?? 0;

        return $p != 0 ? round((($this->is[$key] - $p) / abs($p)) * 100, 1) : null;
    }
};
?>

<x-ledger.page :title="t('Financial Statements')" :subtitle="t('PSAK-oriented management reporting sourced from posted general ledger balances.')">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="asOf">
        <a class="lg-btn light" href="{{ route('finance.trial-balance') }}">≡ {{ t('Trial Balance') }}</a>
        <a class="lg-btn primary" href="{{ route('finance.balance-sheet') }}">▤ {{ t('Detailed Reports') }}</a>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Total Assets')" icon="◇" :value="Fmt::short($this->bs['total_assets'])" :sub="str_replace(':date', Fmt::date($asOf), t('As at :date'))" />
        <x-ledger.metric :label="t('Total Liabilities')" icon="▣" :value="Fmt::short($this->bs['total_liabilities'])" :sub="t('Debt-to-equity').': '.($this->bs['total_equity'] != 0 ? number_format($this->bs['total_liabilities'] / $this->bs['total_equity'], 2).'x' : '—')" />
        <x-ledger.metric :label="t('Revenue YTD')" icon="↗" :value="Fmt::short($this->is['REVENUE'])" :pill="$this->growth('REVENUE') !== null ? Fmt::pct($this->growth('REVENUE')) : null" :tone="($this->growth('REVENUE') ?? 0) >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric :label="t('Net Profit YTD')" icon="✓" :value="Fmt::short($this->is['net_profit'])" :pill="$this->growth('net_profit') !== null ? Fmt::pct($this->growth('net_profit')) : null" :tone="$this->is['net_profit'] >= 0 ? 'good' : 'bad'" />
    </div>

    <div class="lg-two even">
        <x-ledger.panel :title="t('Balance Sheet')" :subtitle="str_replace(':date', Fmt::date($asOf), t('Statement of financial position as at :date.'))">
            <x-slot:action><a class="lg-link" href="{{ route('finance.balance-sheet') }}">{{ t('Open report') }}</a></x-slot:action>
            <div class="lg-statements">
                <x-ledger.srow kind="group" :label="mb_strtoupper(t('Assets'))" />
                <x-ledger.srow :label="t('Cash and cash equivalents')" :value="Fmt::rp($this->bs['CASH'])" />
                <x-ledger.srow :label="t('Trade accounts receivable, net')" :value="Fmt::rp($this->bs['AR'])" />
                <x-ledger.srow :label="t('Inventories and other current assets')" :value="Fmt::rp($this->bs['INVENTORY'] + $this->bs['OTHER_CA'])" />
                <x-ledger.srow :label="t('Property, plant, equipment and intangibles, net')" :value="Fmt::rp($this->bs['PPE'] + $this->bs['INTANGIBLE'])" />
                <x-ledger.srow kind="total" :label="mb_strtoupper(t('Total Assets'))" :value="Fmt::rp($this->bs['total_assets'])" />
                <x-ledger.srow kind="group" :label="mb_strtoupper(t('Liabilities & Equity'))" />
                <x-ledger.srow :label="t('Total liabilities')" :value="Fmt::rp($this->bs['total_liabilities'])" />
                <x-ledger.srow :label="t('Total equity')" :value="Fmt::rp($this->bs['total_equity'])" />
                <x-ledger.srow kind="net" :label="mb_strtoupper(t('Total Liabilities and Equity'))" :value="Fmt::rp($this->bs['total_le'])" />
            </div>
        </x-ledger.panel>

        <x-ledger.panel :title="t('Income Statement')" :subtitle="str_replace(':date', Fmt::date($asOf), t('Year to date :date.'))">
            <x-slot:action><a class="lg-link" href="{{ route('finance.income-statement') }}">{{ t('Open report') }}</a></x-slot:action>
            <div class="lg-statements">
                <x-ledger.srow kind="group" :label="mb_strtoupper(t('Revenue'))" />
                <x-ledger.srow :label="t('Sales revenue')" :value="Fmt::rp($this->is['REVENUE'])" />
                <x-ledger.srow :label="t('Cost of goods and services sold')" :value="Fmt::rp($this->is['COGS'])" />
                <x-ledger.srow kind="total" :label="t('Gross profit')" :value="Fmt::rp($this->is['gross_profit'])" />
                <x-ledger.srow kind="group" :label="mb_strtoupper(t('Operating Expenses'))" />
                <x-ledger.srow :label="t('Employee benefits expense')" :value="Fmt::rp($this->is['EMPLOYEE'])" />
                <x-ledger.srow :label="t('Sales and marketing expense')" :value="Fmt::rp($this->is['SELLING'])" />
                <x-ledger.srow :label="t('General and administrative expense')" :value="Fmt::rp($this->is['GA'])" />
                <x-ledger.srow kind="total" :label="t('Operating profit')" :value="Fmt::rp($this->is['operating_profit'])" />
                <x-ledger.srow :label="t('Income tax expense')" :value="Fmt::rp($this->is['INCOME_TAX'])" />
                <x-ledger.srow kind="net" :label="mb_strtoupper(t('Net Profit'))" :value="Fmt::rp($this->is['net_profit'])" />
            </div>
        </x-ledger.panel>
    </div>

    <x-ledger.panel :title="t('Cash Flow Statement')" :subtitle="str_replace(':date', Fmt::date($asOf), t('Indirect method · year to date :date.'))">
        <x-slot:action><a class="lg-link" href="{{ route('finance.cash-flow') }}">{{ t('Open report') }}</a></x-slot:action>
        <div class="lg-statements">
            <x-ledger.srow kind="group" :label="mb_strtoupper(t('Operating Activities'))" />
            <x-ledger.srow :label="t('Net cash generated from operations')" :value="Fmt::rp($this->cf['operating'])" />
            <x-ledger.srow kind="group" :label="mb_strtoupper(t('Investing Activities'))" />
            <x-ledger.srow :label="t('Purchase of property, plant and equipment')" :value="Fmt::rp($this->cf['investing'])" />
            <x-ledger.srow kind="group" :label="mb_strtoupper(t('Financing Activities'))" />
            <x-ledger.srow :label="t('Loans and equity movements')" :value="Fmt::rp($this->cf['financing'])" />
            <x-ledger.srow kind="net" :label="mb_strtoupper(t('Net increase in cash'))" :value="Fmt::rp($this->cf['net_change'])" />
        </div>
    </x-ledger.panel>
</x-ledger.page>
