<?php

use App\Livewire\Concerns\FinanceScreen;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Support\Finance\Fmt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// General Ledger -- mockup blueprint: register baris jurnal POSTED per periode,
// filter kelompok akun, kartu Posted/Draft/Debit/Credit.
new #[Title('General Ledger — TELASIH MAKMUR ERP')] class extends Component
{
    use FinanceScreen;

    public string $period = '';

    public string $search = '';

    public string $group = '';

    public ?int $accountId = null;

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $this->accountId = request()->integer('account') ?: null;
    }

    private function range(): array
    {
        $from = $this->period.'-01';

        return [$from, date('Y-m-t', strtotime($from))];
    }

    #[Computed]
    public function lines()
    {
        [$from, $to] = $this->range();

        return FinVoucherLine::with(['voucher', 'account'])
            ->whereHas('voucher', fn ($q) => $q->where('status', 'POSTED')->where('stat_cancel', 0)->whereBetween('entry_date', [$from, $to])
                ->when($this->search !== '', fn ($w) => $w->where(fn ($x) => $x->where('voucher_number', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%"))))
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->when($this->group !== '', fn ($q) => $q->whereHas('account', fn ($a) => match ($this->group) {
                'CASH' => $a->where('account_code', 'like', '111%'),
                'REVENUE' => $a->whereIn('account_type', ['REVENUE', 'OTHER_INCOME']),
                'EXPENSE' => $a->whereIn('account_type', ['COGS', 'EXPENSE', 'OTHER_EXPENSE']),
                'ASSET' => $a->where('account_type', 'ASSET'),
                'LIABILITY' => $a->whereIn('account_type', ['LIABILITY', 'EQUITY']),
            }))
            ->get()
            ->sortBy(fn ($l) => $l->voucher->entry_date->format('Ymd').str_pad((string) $l->voucher_id, 10, '0', STR_PAD_LEFT).str_pad((string) $l->line_id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    // Saldo berjalan akun terpilih = saldo awal (s.d. akhir bulan lalu) + mutasi.
    #[Computed]
    public function running(): array
    {
        if (! $this->accountId) {
            return [];
        }
        [$from] = $this->range();
        $code = \App\Models\Acc\FinAccount::find($this->accountId)?->account_code;
        $bal = $code ? app(\App\Services\Acc\LedgerService::class)->balancesAsOf(date('Y-m-d', strtotime($from.' -1 day')))[$this->accountId] ?? 0 : 0;
        $out = [];
        foreach ($this->lines as $l) {
            $bal += (float) $l->debit - (float) $l->credit;
            $out[$l->line_id] = $bal;
        }

        return $out;
    }

    #[Computed]
    public function stats(): array
    {
        [$from, $to] = $this->range();
        $year = substr($this->period, 0, 4);
        $mov = FinVoucherLine::whereHas('voucher', fn ($q) => $q->where('status', 'POSTED')->where('stat_cancel', 0)->whereBetween('entry_date', [$from, $to]));

        return [
            'posted' => FinVoucher::where('status', 'POSTED')->whereYear('entry_date', $year)->count(),
            'draft' => FinVoucher::whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED'])->count(),
            'debit' => (float) (clone $mov)->sum('debit'),
            'credit' => (float) (clone $mov)->sum('credit'),
        ];
    }

    #[Computed]
    public function periodOptions(): array
    {
        $out = [];
        for ($i = 0; $i < 18; $i++) {
            $d = now()->startOfMonth()->subMonths($i);
            $out[$d->format('Y-m')] = $d->format('F Y');
        }

        return $out;
    }

    #[Computed]
    public function accountOptions(): array
    {
        return $this->postableAccountOptions();
    }
};
?>

<x-ledger.page :title="t('General Ledger')" :subtitle="t('Account movements, journal postings, and ledger balances.')">
    <x-slot:actions>
        <select class="lg-period" wire:model.live="period" x-data x-effect="$el.value = @js($period)">
            @foreach ($this->periodOptions as $k => $label)<option value="{{ $k }}">▣ {{ $label }}</option>@endforeach
        </select>
        <a class="lg-btn light" href="{{ route('finance.trial-balance') }}">≡ {{ t('Trial Balance') }}</a>
        <a class="lg-btn primary" href="{{ route('finance.journals') }}">＋ {{ t('New Journal Entry') }}</a>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric :label="t('Posted Entries')" icon="✓" :value="number_format($this->stats['posted'])" :sub="t('Fiscal year').' '.substr($period, 0, 4)" />
        <x-ledger.metric :label="t('Draft Entries')" icon="◷" :value="$this->stats['draft']" :pill="$this->stats['draft'] ? t('Review needed') : t('None pending')" :tone="$this->stats['draft'] ? 'warn' : 'good'" />
        <x-ledger.metric :label="t('Debits This Period')" icon="↓" :value="Fmt::short($this->stats['debit'])" :sub="$this->periodOptions[$period] ?? $period" />
        <x-ledger.metric :label="t('Credits This Period')" icon="↑" :value="Fmt::short($this->stats['credit'])" :pill="abs($this->stats['debit'] - $this->stats['credit']) < 0.01 ? t('Balanced') : t('Out of balance')" :tone="abs($this->stats['debit'] - $this->stats['credit']) < 0.01 ? 'good' : 'bad'" />
    </div>

    <x-ledger.panel :title="t('General Ledger Register')" :subtitle="str_replace(':period', $this->periodOptions[$period] ?? $period, t('Entries posted in :period'))">
        <div class="lg-toolbar">
            <input class="lg-search" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search journal number or description...') }}">
            <select class="lg-select" wire:model.live="group" x-data x-effect="$el.value = @js($group)">
                <option value="">{{ t('All accounts') }}</option>
                <option value="CASH">{{ t('Cash & Bank') }}</option>
                <option value="ASSET">{{ t('Assets') }}</option>
                <option value="LIABILITY">{{ t('Liabilities & Equity') }}</option>
                <option value="REVENUE">{{ t('Revenue') }}</option>
                <option value="EXPENSE">{{ t('Expense') }}</option>
            </select>
            <select class="lg-select" wire:model.live="accountId" x-data x-effect="$el.value = @js((string) $accountId)" style="max-width:320px">
                <option value="">{{ t('Any account') }}</option>
                @foreach ($this->accountOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="lg-tablewrap">
            <table class="lg-min">
                <thead><tr><th>{{ t('Journal No.') }}</th><th>{{ t('Date') }}</th><th>{{ t('Description') }}</th><th>{{ t('Account') }}</th><th class="r">{{ t('Debit') }}</th><th class="r">{{ t('Credit') }}</th>@if ($accountId)<th class="r">{{ t('Running Balance') }}</th>@endif<th>{{ t('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($this->lines as $l)
                        <tr wire:key="gl-{{ $l->line_id }}">
                            <td><span class="lg-name">{{ $l->voucher->voucher_number }}</span><span class="lg-code">{{ $l->voucher->source_type }}</span></td>
                            <td>{{ Fmt::date($l->voucher->entry_date) }}</td>
                            <td>{{ $l->narration ?: $l->voucher->description }}</td>
                            <td>{{ $l->account?->account_code }} · {{ $l->account?->account_name }}</td>
                            <td class="r lg-money">{{ Fmt::rp($l->debit, true) }}</td>
                            <td class="r lg-money">{{ Fmt::rp($l->credit, true) }}</td>
                            @if ($accountId)<td class="r lg-money">{{ Fmt::rp($this->running[$l->line_id] ?? 0) }}</td>@endif
                            <td><x-ledger.tag :tone="$l->voucher->reversed_by_id ? 'purple' : 'good'">{{ $l->voucher->reversed_by_id ? t('Reversed') : t('Posted') }}</x-ledger.tag></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="lg-empty">{{ t('No posted entries in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="lg-foot"><b>{{ str_replace(':n', $this->lines->count(), t('Total (:n lines)')) }}</b><span>{{ t('Debit') }} <b class="lg-money">{{ Fmt::rp($this->lines->sum('debit')) }}</b> · {{ t('Credit') }} <b class="lg-money">{{ Fmt::rp($this->lines->sum('credit')) }}</b></span></div>
    </x-ledger.panel>
</x-ledger.page>
