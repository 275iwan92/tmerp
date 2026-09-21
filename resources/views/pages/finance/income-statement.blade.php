<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Services\Acc\LedgerService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Income Statement -- mockup blueprint. Nilai = mutasi akun L/R periode
// berjalan (voucher penutup tahun dikecualikan supaya laba tidak dobel).
new #[Title('Income Statement — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $from = '';

    public string $to = '';

    public bool $monthly = false;

    public function mount(): void
    {
        $this->from = now()->startOfYear()->toDateString();
        $this->to = now()->toDateString();
    }

    #[Computed]
    public function is(): array
    {
        return app(LedgerService::class)->incomeStatement($this->from, $this->to);
    }

    #[Computed]
    public function byMonth(): array
    {
        $svc = app(LedgerService::class);
        $out = [];
        $cursor = \Carbon\Carbon::parse($this->from)->startOfMonth();
        $end = \Carbon\Carbon::parse($this->to);
        while ($cursor->lte($end)) {
            $out[$cursor->format('M Y')] = $svc->incomeStatement($cursor->toDateString(), min($cursor->copy()->endOfMonth(), $end)->toDateString());
            $cursor->addMonth();
        }

        return $out;
    }

    public function margin(float $value): string
    {
        $rev = $this->is['REVENUE'];

        return $rev > 0 ? number_format($value / $rev * 100, 1).'%' : '—';
    }

    public function export()
    {
        $rows = [];
        foreach (['REVENUE', 'COGS', 'gross_profit', 'EMPLOYEE', 'SELLING', 'GA', 'operating_profit', 'OTHER', 'profit_before_tax', 'INCOME_TAX', 'net_profit'] as $k) {
            $rows[] = [LedgerService::FS_LABELS[$k] ?? ucwords(str_replace('_', ' ', $k)), $this->is[$k]];
        }

        return $this->csvDownload('income-statement-'.$this->from.'-'.$this->to.'.csv', ['Line', 'Amount (IDR)'], $rows);
    }
};
?>

<x-ledger.page title="Income Statement" :subtitle="'Profit and loss statement · '.Fmt::date($from).' — '.Fmt::date($to).'.'">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="from">
        <input type="date" class="lg-period" wire:model.live="to">
        <button type="button" class="lg-btn light" wire:click="export">⇩ Export</button>
        <button type="button" class="lg-btn primary" wire:click="$toggle('monthly')">▤ {{ $monthly ? 'Hide' : 'Compare' }} Monthly</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Revenue" icon="↗" :value="Fmt::short($this->is['REVENUE'])" :sub="Fmt::date($from).' — '.Fmt::date($to)" />
        <x-ledger.metric label="Gross Profit" icon="◒" :value="Fmt::short($this->is['gross_profit'])" :sub="'Gross margin '.$this->margin($this->is['gross_profit'])" />
        <x-ledger.metric label="Operating Profit" icon="▤" :value="Fmt::short($this->is['operating_profit'])" :pill="'Margin '.$this->margin($this->is['operating_profit'])" :tone="$this->is['operating_profit'] >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric label="Net Profit" icon="✓" :value="Fmt::short($this->is['net_profit'])" :pill="$this->is['net_profit'] >= 0 ? 'Profit' : 'Loss'" :tone="$this->is['net_profit'] >= 0 ? 'good' : 'bad'" />
    </div>

    <x-ledger.panel title="Statement of Profit or Loss" subtitle="Unaudited management accounts · IDR">
        <div class="lg-statements">
            <x-ledger.srow kind="group" label="REVENUE" />
            <x-ledger.srow label="Sales revenue (net of discounts and returns)" :value="Fmt::rp($this->is['REVENUE'])" />
            <x-ledger.srow kind="total" label="Total revenue" :value="Fmt::rp($this->is['REVENUE'])" />
            <x-ledger.srow kind="group" label="COST OF REVENUE" />
            <x-ledger.srow label="Cost of goods and services sold" :value="Fmt::rp($this->is['COGS'])" />
            <x-ledger.srow kind="total" label="Gross profit" :value="Fmt::rp($this->is['gross_profit'])" />
            <x-ledger.srow kind="group" label="OPERATING EXPENSES" />
            <x-ledger.srow label="Employee benefits expense" :value="Fmt::rp($this->is['EMPLOYEE'])" />
            <x-ledger.srow label="Sales and marketing expense" :value="Fmt::rp($this->is['SELLING'])" />
            <x-ledger.srow label="General and administrative expense" :value="Fmt::rp($this->is['GA'])" />
            <x-ledger.srow kind="total" label="Operating profit" :value="Fmt::rp($this->is['operating_profit'])" />
            <x-ledger.srow label="Other income / (expense), net" :value="Fmt::rp($this->is['OTHER'])" />
            <x-ledger.srow kind="total" label="Profit before income tax" :value="Fmt::rp($this->is['profit_before_tax'])" />
            <x-ledger.srow label="Income tax expense" :value="Fmt::rp($this->is['INCOME_TAX'])" />
            <x-ledger.srow kind="net" label="NET PROFIT FOR THE PERIOD" :value="Fmt::rp($this->is['net_profit'])" />
        </div>
    </x-ledger.panel>

    @if ($monthly)
        <x-ledger.panel title="Monthly Comparison" subtitle="Revenue, gross profit and net result per month.">
            <div class="lg-tablewrap">
                <table class="lg-min">
                    <thead><tr><th>Month</th><th class="r">Revenue</th><th class="r">COGS</th><th class="r">Gross profit</th><th class="r">Operating expenses</th><th class="r">Net profit</th></tr></thead>
                    <tbody>
                        @foreach ($this->byMonth as $label => $m)
                            <tr>
                                <td><span class="lg-name">{{ $label }}</span></td>
                                <td class="r lg-money">{{ Fmt::rp($m['REVENUE'], true) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($m['COGS'], true) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($m['gross_profit'], true) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($m['EMPLOYEE'] + $m['SELLING'] + $m['GA'], true) }}</td>
                                <td class="r lg-money">{{ Fmt::rp($m['net_profit'], true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ledger.panel>
    @endif
</x-ledger.page>
