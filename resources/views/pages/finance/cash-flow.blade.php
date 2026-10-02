<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Services\Acc\LedgerService;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Cash Flow Statement (metode tidak langsung) -- mockup blueprint.
// Setiap akun non-kas diklasifikasi lewat fin_account.cf_line, jadi
// total arus kas SELALU sama dengan perubahan saldo akun kas/bank.
new #[Title('Cash Flow Statement — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = now()->startOfYear()->toDateString();
        $this->to = now()->toDateString();
    }

    #[Computed]
    public function cf(): array
    {
        return app(LedgerService::class)->cashFlow($this->from, $this->to);
    }

    #[Computed]
    public function monthly(): array
    {
        return app(LedgerService::class)->monthlyOperatingCash((int) substr($this->from, 0, 4), (int) substr($this->to, 5, 2));
    }

    // Titik polyline grafik dihitung di PHP (bukan @php di Blade -- lihat
    // feedback_blade_inline_php_no_closing_tag_gotcha).
    #[Computed]
    public function chartPoints(): string
    {
        $vals = array_values($this->monthly);
        if (! $vals) {
            return '';
        }
        $max = max(array_map('abs', $vals)) ?: 1;
        $step = count($vals) > 1 ? 640 / (count($vals) - 1) : 0;
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = round(42 + $i * $step).' '.round(114 - ($v / $max) * 88);
        }

        return implode(' ', $pts);
    }

    public function export()
    {
        $rows = [];
        foreach (LedgerService::CF_LABELS as $k => $label) {
            $rows[] = [$label, $this->cf[$k]];
        }
        foreach (['operating', 'investing', 'financing', 'net_change', 'opening_cash', 'closing_cash'] as $k) {
            $rows[] = [ucwords(str_replace('_', ' ', $k)), $this->cf[$k]];
        }

        return $this->csvDownload('cash-flow-'.$this->from.'-'.$this->to.'.csv', ['Line', 'Amount (IDR)'], $rows);
    }
};
?>

<x-ledger.page :title="t('Cash Flow Statement')" :subtitle="t('Indirect method').' · '.Fmt::date($from).' — '.Fmt::date($to).'.'">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="from">
        <input type="date" class="lg-period" wire:model.live="to">
        <button type="button" class="lg-btn light" wire:click="export">⇩ {{ t('Export') }}</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Opening Cash')" icon="◉" :value="Fmt::short($this->cf['opening_cash'])" :sub="Fmt::date($from)" />
        <x-ledger.metric :label="t('Net Operating Cash')" icon="↗" :value="Fmt::short($this->cf['operating'])" :pill="$this->cf['operating'] >= 0 ? t('Positive') : t('Negative')" :tone="$this->cf['operating'] >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric :label="t('Net Investing Cash')" icon="↘" :value="Fmt::short($this->cf['investing'])" :sub="t('CAPEX and intangibles')" />
        <x-ledger.metric :label="t('Closing Cash')" icon="✓" :value="Fmt::short($this->cf['closing_cash'])" :pill="str_replace(':amount', Fmt::rp($this->cf['net_change']), t(':amount change'))" :tone="$this->cf['net_change'] >= 0 ? 'good' : 'warn'" />
    </div>

    <x-ledger.panel class="lg-stack" :title="t('Cash Flow Detail')" :subtitle="t('Indirect method').' · '.Fmt::date($from).' — '.Fmt::date($to)">
        <div class="lg-statements">
            <x-ledger.srow kind="group" :label="t('Cash flows from operating activities')" />
            <x-ledger.srow :label="t('Profit before income tax')" :value="Fmt::rp($this->cf['PROFIT'])" />
            <x-ledger.srow :label="t('Depreciation and amortization')" :value="Fmt::rp($this->cf['DA'])" />
            <x-ledger.srow :label="t('(Increase) / decrease in trade receivables')" :value="Fmt::rp($this->cf['WC_AR'])" />
            <x-ledger.srow :label="t('(Increase) / decrease in inventories')" :value="Fmt::rp($this->cf['WC_INV'])" />
            <x-ledger.srow :label="t('(Increase) / decrease in other current assets')" :value="Fmt::rp($this->cf['WC_OTHER_CA'])" />
            <x-ledger.srow :label="t('Increase / (decrease) in trade payables')" :value="Fmt::rp($this->cf['WC_AP'])" />
            <x-ledger.srow :label="t('Increase / (decrease) in accruals and tax liabilities')" :value="Fmt::rp($this->cf['WC_OTHER_CL'])" />
            <x-ledger.srow :label="t('Income tax paid')" :value="Fmt::rp($this->cf['TAX'])" />
            <x-ledger.srow kind="total" :label="t('Net cash generated from operations')" :value="Fmt::rp($this->cf['operating'])" />
            <x-ledger.srow kind="group" :label="t('Cash flows from investing activities')" />
            <x-ledger.srow :label="t('Purchase of property, plant, equipment and intangibles')" :value="Fmt::rp($this->cf['INV_PPE'])" />
            <x-ledger.srow kind="total" :label="t('Net cash used in investing activities')" :value="Fmt::rp($this->cf['investing'])" />
            <x-ledger.srow kind="group" :label="t('Cash flows from financing activities')" />
            <x-ledger.srow :label="t('Proceeds / (repayment) of bank loans')" :value="Fmt::rp($this->cf['FIN_LOAN'])" />
            <x-ledger.srow :label="t('Capital contribution / (dividends)')" :value="Fmt::rp($this->cf['FIN_EQUITY'])" />
            <x-ledger.srow kind="total" :label="t('Net cash from financing activities')" :value="Fmt::rp($this->cf['financing'])" />
            <x-ledger.srow kind="net" :label="mb_strtoupper(t('Net increase / (decrease) in cash'))" :value="Fmt::rp($this->cf['net_change'])" />
            <x-ledger.srow :label="t('Cash at beginning of period')" :value="Fmt::rp($this->cf['opening_cash'])" />
            <x-ledger.srow kind="total" :label="t('Cash at end of period')" :value="Fmt::rp($this->cf['closing_cash'])" />
        </div>
        @if (abs($this->cf['opening_cash'] + $this->cf['net_change'] - $this->cf['closing_cash']) > 0.01)
            <div class="lg-alert warn" style="margin:0 19px 16px">{{ str_replace(':amount', Fmt::rp($this->cf['opening_cash'] + $this->cf['net_change'] - $this->cf['closing_cash']), t('Cash reconciliation difference :amount — some accounts have no cash flow classification (cf_line).')) }}</div>
        @endif
    </x-ledger.panel>

    <x-ledger.panel :title="t('Operating Cash Trend')" :subtitle="t('Monthly operating cash movement — IDR')">
        <div class="lg-chart">
            @if (array_filter($this->monthly))
                <svg viewBox="0 0 700 235" width="100%" role="img">
                    <g stroke="#e7edf5">
                        @foreach ([20, 67, 114, 161, 208] as $y)<line x1="38" y1="{{ $y }}" x2="690" y2="{{ $y }}" />@endforeach
                    </g>
                    <polyline points="{{ $this->chartPoints }}" fill="none" stroke="#2855d9" stroke-width="3" />
                    <line x1="38" y1="114" x2="690" y2="114" stroke="#a3afc4" stroke-dasharray="4 4" />
                </svg>
                <div class="lg-foot" style="padding:6px 4px">
                    @foreach ($this->monthly as $m => $v)
                        <span class="lg-sub">{{ \Carbon\Carbon::create((int) substr($from, 0, 4), $m, 1)->format('M') }} {{ Fmt::short($v) }}</span>
                    @endforeach
                </div>
            @else
                <div class="lg-empty">{{ t('No operating cash flow in this period yet.') }}</div>
            @endif
        </div>
    </x-ledger.panel>
</x-ledger.page>
