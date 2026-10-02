<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Services\Acc\FinanceMetrics;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Accounting Dashboard -- mockup blueprint: likuiditas, piutang/hutang,
// profitabilitas, tren arus kas operasi, compliance & action center.
new #[Title('Accounting Dashboard — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $asOf = '';

    public function mount(): void
    {
        $this->asOf = now()->toDateString();
    }

    #[Computed]
    public function h(): array
    {
        return app(FinanceMetrics::class)->headline($this->asOf);
    }

    #[Computed]
    public function trend(): array
    {
        return app(FinanceMetrics::class)->cashTrend();
    }

    #[Computed]
    public function compliance(): array
    {
        return app(FinanceMetrics::class)->compliance();
    }

    #[Computed]
    public function actions(): array
    {
        return app(FinanceMetrics::class)->actions();
    }

    // Area + garis grafik tren (dihitung di PHP, bukan di Blade).
    #[Computed]
    public function chart(): array
    {
        $vals = array_values($this->trend);
        $max = max(array_map('abs', $vals) ?: [1]) ?: 1;
        $step = count($vals) > 1 ? 646 / (count($vals) - 1) : 0;
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [round(42 + $i * $step), round(114 - ($v / $max) * 84)];
        }
        $line = implode(' ', array_map(fn ($p) => $p[0].' '.$p[1], $pts));
        $area = $line ? $line.' '.end($pts)[0].' 208 42 208' : '';

        return ['line' => $line, 'area' => $area, 'max' => $max];
    }
};
?>

<x-ledger.page :title="t('Accounting Dashboard')" :subtitle="t('Liquidity, receivables, payables, profitability, and compliance status.')">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="asOf">
        <a class="lg-btn light" href="{{ route('finance.statements') }}">⇩ {{ t('Financial Statements') }}</a>
        <a class="lg-btn primary" href="{{ route('finance.journals') }}">＋ {{ t('New Journal') }}</a>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Cash & Bank')" icon="◉" :value="Fmt::short($this->h['cash'])" :pill="$this->h['cash_change'] !== null ? Fmt::pct($this->h['cash_change']) : null" :tone="($this->h['cash_change'] ?? 0) >= 0 ? 'good' : 'bad'" :sub="t('vs. prior month')" />
        <x-ledger.metric :label="t('Accounts Receivable')" icon="⇧" :value="Fmt::short($this->h['ar'])" :pill="str_replace(':n', $this->h['ar_n'], t(':n invoices'))" tone="warn" :sub="str_replace(':amount', Fmt::short($this->h['ar_overdue']), t(':amount overdue'))" />
        <x-ledger.metric :label="t('Accounts Payable')" icon="⇩" :value="Fmt::short($this->h['ap'])" :pill="str_replace(':n', $this->h['ap_n'], t(':n bills'))" tone="warn" :sub="str_replace(':amount', Fmt::short($this->h['ap_due']), t(':amount due this week'))" />
        <x-ledger.metric :label="t('Net Profit YTD')" icon="↗" :value="Fmt::short($this->h['net_profit'])" :pill="$this->h['profit_change'] !== null ? Fmt::pct($this->h['profit_change']) : null" :tone="$this->h['net_profit'] >= 0 ? 'good' : 'bad'" :sub="str_replace(':amount', Fmt::short($this->h['revenue']), t('Revenue :amount'))" />
    </div>

    <div class="lg-two wide">
        <x-ledger.panel :title="t('Cash Flow Overview')" :subtitle="t('Monthly operating cash movement — last 12 months')">
            <x-slot:action><a class="lg-link" href="{{ route('finance.cash-flow') }}">{{ t('View report') }}</a></x-slot:action>
            <div class="lg-chart">
                @if ($this->chart['line'])
                    <svg viewBox="0 0 700 235" width="100%" role="img">
                        <g stroke="#e7edf5">
                            @foreach ([20, 67, 114, 161, 208] as $y)<line x1="38" y1="{{ $y }}" x2="690" y2="{{ $y }}" />@endforeach
                        </g>
                        <polygon points="{{ $this->chart['area'] }}" fill="#2855d91a" />
                        <polyline points="{{ $this->chart['line'] }}" fill="none" stroke="#2855d9" stroke-width="3" />
                    </svg>
                    <div class="lg-foot" style="padding:6px 4px; flex-wrap:wrap; gap:6px">
                        @foreach ($this->trend as $m => $v)
                            <span class="lg-sub">{{ $m }} <b>{{ Fmt::short($v) }}</b></span>
                        @endforeach
                    </div>
                @else
                    <div class="lg-empty">{{ t('No cash flow to chart yet.') }}</div>
                @endif
            </div>
        </x-ledger.panel>

        <x-ledger.panel :title="t('Compliance Center')" :subtitle="t('Closing and tax deadlines')">
            <x-slot:action><a class="lg-link" href="{{ route('finance.tax') }}">{{ t('Open tax') }}</a></x-slot:action>
            <div class="lg-list">
                @forelse ($this->compliance as $c)
                    <x-ledger.row :icon="$c[0]" :tone="$c[1]" :title="$c[2]" :text="$c[3]"><div class="lg-n">{{ $c[4] }}</div></x-ledger.row>
                @empty
                    <div class="lg-empty">{{ t('No active deadlines.') }}</div>
                @endforelse
            </div>
        </x-ledger.panel>
    </div>

    <div class="lg-two even">
        <x-ledger.panel :title="t('Finance Action Center')" :subtitle="t('Prioritized tasks')">
            <div class="lg-list">
                @foreach ($this->actions as $a)
                    <x-ledger.row :icon="$a[0]" :tone="$a[1]" :title="$a[2]" :text="$a[3]"><div class="lg-n">{{ $a[4] }}</div></x-ledger.row>
                @endforeach
            </div>
        </x-ledger.panel>

        <x-ledger.panel :title="t('Shortcuts')" :subtitle="t('Screens the Finance team uses most often')">
            <div class="lg-list">
                <x-ledger.row icon="✎" tone="bl" :title="t('Journal Entries')" :text="t('Create, submit, approve & post manual journals')"><a class="lg-link" href="{{ route('finance.journals') }}">{{ t('Open') }}</a></x-ledger.row>
                <x-ledger.row icon="▥" tone="gr" :title="t('Cashier (BKM/BKK/BBM/BBK)')" :text="t('Daily cash & bank vouchers')"><a class="lg-link" href="{{ route('finance.cash-bank') }}">{{ t('Open') }}</a></x-ledger.row>
                <x-ledger.row icon="☷" tone="pu" :title="t('Master of Accounts')" :text="t('Chart of accounts & report mapping')"><a class="lg-link" href="{{ route('finance.coa') }}">{{ t('Open') }}</a></x-ledger.row>
                <x-ledger.row icon="≡" tone="bl" :title="t('Trial Balance')" :text="t('Balance control before closing the books')"><a class="lg-link" href="{{ route('finance.trial-balance') }}">{{ t('Open') }}</a></x-ledger.row>
                <x-ledger.row icon="⌘" tone="or" :title="t('Process Engine')" :text="t('Approval rules & module integration map')"><a class="lg-link" href="{{ route('finance.engine') }}">{{ t('Open') }}</a></x-ledger.row>
            </div>
        </x-ledger.panel>
    </div>
</x-ledger.page>
