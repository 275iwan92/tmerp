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

<x-ledger.page title="Financial Statements" subtitle="PSAK-oriented management reporting sourced from posted general ledger balances.">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="asOf">
        <a class="lg-btn light" href="{{ route('finance.trial-balance') }}">≡ Trial Balance</a>
        <a class="lg-btn primary" href="{{ route('finance.balance-sheet') }}">▤ Detailed Reports</a>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Total Assets" icon="◇" :value="Fmt::short($this->bs['total_assets'])" :sub="'As at '.Fmt::date($asOf)" />
        <x-ledger.metric label="Total Liabilities" icon="▣" :value="Fmt::short($this->bs['total_liabilities'])" :sub="'Debt-to-equity: '.($this->bs['total_equity'] != 0 ? number_format($this->bs['total_liabilities'] / $this->bs['total_equity'], 2).'x' : '—')" />
        <x-ledger.metric label="Revenue YTD" icon="↗" :value="Fmt::short($this->is['REVENUE'])" :pill="$this->growth('REVENUE') !== null ? Fmt::pct($this->growth('REVENUE')) : null" :tone="($this->growth('REVENUE') ?? 0) >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric label="Net Profit YTD" icon="✓" :value="Fmt::short($this->is['net_profit'])" :pill="$this->growth('net_profit') !== null ? Fmt::pct($this->growth('net_profit')) : null" :tone="$this->is['net_profit'] >= 0 ? 'good' : 'bad'" />
    </div>

    <div class="lg-two even">
        <x-ledger.panel title="Balance Sheet" :subtitle="'Statement of financial position as at '.Fmt::date($asOf).'.'">
            <x-slot:action><a class="lg-link" href="{{ route('finance.balance-sheet') }}">Open report</a></x-slot:action>
            <div class="lg-statements">
                <x-ledger.srow kind="group" label="ASSETS" />
                <x-ledger.srow label="Cash and cash equivalents" :value="Fmt::rp($this->bs['CASH'])" />
                <x-ledger.srow label="Trade accounts receivable, net" :value="Fmt::rp($this->bs['AR'])" />
                <x-ledger.srow label="Inventories and other current assets" :value="Fmt::rp($this->bs['INVENTORY'] + $this->bs['OTHER_CA'])" />
                <x-ledger.srow label="Property, plant, equipment and intangibles, net" :value="Fmt::rp($this->bs['PPE'] + $this->bs['INTANGIBLE'])" />
                <x-ledger.srow kind="total" label="TOTAL ASSETS" :value="Fmt::rp($this->bs['total_assets'])" />
                <x-ledger.srow kind="group" label="LIABILITIES & EQUITY" />
                <x-ledger.srow label="Total liabilities" :value="Fmt::rp($this->bs['total_liabilities'])" />
                <x-ledger.srow label="Total equity" :value="Fmt::rp($this->bs['total_equity'])" />
                <x-ledger.srow kind="net" label="TOTAL LIABILITIES AND EQUITY" :value="Fmt::rp($this->bs['total_le'])" />
            </div>
        </x-ledger.panel>

        <x-ledger.panel title="Income Statement" :subtitle="'Year to date '.Fmt::date($asOf).'.'">
            <x-slot:action><a class="lg-link" href="{{ route('finance.income-statement') }}">Open report</a></x-slot:action>
            <div class="lg-statements">
                <x-ledger.srow kind="group" label="REVENUE" />
                <x-ledger.srow label="Sales revenue" :value="Fmt::rp($this->is['REVENUE'])" />
                <x-ledger.srow label="Cost of goods and services sold" :value="Fmt::rp($this->is['COGS'])" />
                <x-ledger.srow kind="total" label="Gross profit" :value="Fmt::rp($this->is['gross_profit'])" />
                <x-ledger.srow kind="group" label="OPERATING EXPENSES" />
                <x-ledger.srow label="Employee benefits expense" :value="Fmt::rp($this->is['EMPLOYEE'])" />
                <x-ledger.srow label="Sales and marketing expense" :value="Fmt::rp($this->is['SELLING'])" />
                <x-ledger.srow label="General and administrative expense" :value="Fmt::rp($this->is['GA'])" />
                <x-ledger.srow kind="total" label="Operating profit" :value="Fmt::rp($this->is['operating_profit'])" />
                <x-ledger.srow label="Income tax expense" :value="Fmt::rp($this->is['INCOME_TAX'])" />
                <x-ledger.srow kind="net" label="NET PROFIT" :value="Fmt::rp($this->is['net_profit'])" />
            </div>
        </x-ledger.panel>
    </div>

    <x-ledger.panel title="Cash Flow Statement" :subtitle="'Indirect method · year to date '.Fmt::date($asOf).'.'">
        <x-slot:action><a class="lg-link" href="{{ route('finance.cash-flow') }}">Open report</a></x-slot:action>
        <div class="lg-statements">
            <x-ledger.srow kind="group" label="OPERATING ACTIVITIES" />
            <x-ledger.srow label="Net cash generated from operations" :value="Fmt::rp($this->cf['operating'])" />
            <x-ledger.srow kind="group" label="INVESTING ACTIVITIES" />
            <x-ledger.srow label="Purchase of property, plant and equipment" :value="Fmt::rp($this->cf['investing'])" />
            <x-ledger.srow kind="group" label="FINANCING ACTIVITIES" />
            <x-ledger.srow label="Loans and equity movements" :value="Fmt::rp($this->cf['financing'])" />
            <x-ledger.srow kind="net" label="NET INCREASE IN CASH" :value="Fmt::rp($this->cf['net_change'])" />
        </div>
    </x-ledger.panel>
</x-ledger.page>
