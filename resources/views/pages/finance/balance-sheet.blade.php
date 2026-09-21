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

<x-ledger.page title="Balance Sheet" :subtitle="'Statement of financial position as at '.Fmt::date($asOf).'.'">
    <x-slot:actions>
        <input type="date" class="lg-period" wire:model.live="asOf">
        <button type="button" class="lg-btn light" wire:click="export">⇩ Export</button>
        <button type="button" class="lg-btn primary" wire:click="$toggle('compare')">▤ Compare Periods</button>
    </x-slot:actions>

    <div class="lg-cards">
        <x-ledger.metric label="Total Assets" icon="◇" :value="Fmt::short($this->bs['total_assets'])" :pill="$this->growth('total_assets') !== null ? Fmt::pct($this->growth('total_assets')) : null" :tone="($this->growth('total_assets') ?? 0) >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric label="Total Liabilities" icon="▣" :value="Fmt::short($this->bs['total_liabilities'])" :sub="'Debt-to-equity: '.($this->bs['total_equity'] != 0 ? number_format($this->bs['total_liabilities'] / $this->bs['total_equity'], 2).'x' : '—')" />
        <x-ledger.metric label="Total Equity" icon="◈" :value="Fmt::short($this->bs['total_equity'])" :pill="$this->growth('total_equity') !== null ? Fmt::pct($this->growth('total_equity')) : null" :tone="($this->growth('total_equity') ?? 0) >= 0 ? 'good' : 'bad'" />
        <x-ledger.metric label="Current Ratio" icon="↔" :value="$this->bs['current_liabilities'] != 0 ? number_format($this->bs['current_assets'] / $this->bs['current_liabilities'], 2).'x' : '—'" :pill="$this->bs['current_liabilities'] != 0 && $this->bs['current_assets'] / $this->bs['current_liabilities'] >= 1.5 ? 'Healthy liquidity' : 'Watch liquidity'" :tone="$this->bs['current_liabilities'] != 0 && $this->bs['current_assets'] / $this->bs['current_liabilities'] >= 1.5 ? 'good' : 'warn'" />
    </div>

    <x-ledger.panel title="Statement of Financial Position" subtitle="Unaudited management accounts · IDR">
        @if ($compare)
            <x-slot:action>
                <span class="lg-sub">Compare with</span>
                <input type="date" class="lg-input" wire:model.live="compareTo">
            </x-slot:action>
        @endif
        <div class="lg-statements">
            <x-ledger.srow kind="group" label="ASSETS" />
            <x-ledger.srow kind="group" label="Current assets" />
            <x-ledger.srow label="Cash and cash equivalents" :value="Fmt::rp($this->bs['CASH'])" />
            <x-ledger.srow label="Trade accounts receivable, net" :value="Fmt::rp($this->bs['AR'])" />
            <x-ledger.srow label="Inventories" :value="Fmt::rp($this->bs['INVENTORY'])" />
            <x-ledger.srow label="Prepayments, tax and other current assets" :value="Fmt::rp($this->bs['OTHER_CA'])" />
            <x-ledger.srow kind="total" label="Total current assets" :value="Fmt::rp($this->bs['current_assets'])" />
            <x-ledger.srow kind="group" label="Non-current assets" />
            <x-ledger.srow label="Property, plant and equipment, net" :value="Fmt::rp($this->bs['PPE'])" />
            <x-ledger.srow label="Intangible assets, net" :value="Fmt::rp($this->bs['INTANGIBLE'])" />
            <x-ledger.srow kind="total" label="TOTAL ASSETS" :value="Fmt::rp($this->bs['total_assets'])" />

            <x-ledger.srow kind="group" label="LIABILITIES" />
            <x-ledger.srow label="Trade accounts payable" :value="Fmt::rp($this->bs['AP'])" />
            <x-ledger.srow label="Taxes, accruals and other liabilities" :value="Fmt::rp($this->bs['ACCRUED'])" />
            <x-ledger.srow label="Bank loans" :value="Fmt::rp($this->bs['BANK_LOAN'])" />
            <x-ledger.srow kind="total" label="Total liabilities" :value="Fmt::rp($this->bs['total_liabilities'])" />
            <x-ledger.srow kind="group" label="EQUITY" />
            <x-ledger.srow label="Share capital" :value="Fmt::rp($this->bs['SHARE_CAPITAL'])" />
            <x-ledger.srow label="Retained earnings (incl. current year result)" :value="Fmt::rp($this->bs['RETAINED'])" />
            <x-ledger.srow kind="total" label="Total equity" :value="Fmt::rp($this->bs['total_equity'])" />
            <x-ledger.srow kind="net" label="TOTAL LIABILITIES AND EQUITY" :value="Fmt::rp($this->bs['total_le'])" />
        </div>
        @if (abs($this->bs['total_assets'] - $this->bs['total_le']) > 0.01)
            <div class="lg-alert err" style="margin:0 19px 16px">Neraca tidak seimbang {{ Fmt::rp($this->bs['total_assets'] - $this->bs['total_le']) }} — cek Trial Balance.</div>
        @endif
    </x-ledger.panel>

    @if ($compare)
        <x-ledger.panel title="Comparative View" :subtitle="Fmt::date($asOf).' vs '.Fmt::date($compareTo)">
            <div class="lg-tablewrap">
                <table>
                    <thead><tr><th>Line</th><th class="r">{{ Fmt::date($asOf) }}</th><th class="r">{{ Fmt::date($compareTo) }}</th><th class="r">Change</th></tr></thead>
                    <tbody>
                        @foreach (['CASH', 'AR', 'INVENTORY', 'OTHER_CA', 'PPE', 'INTANGIBLE', 'total_assets', 'AP', 'ACCRUED', 'BANK_LOAN', 'SHARE_CAPITAL', 'RETAINED', 'total_le'] as $k)
                            <tr>
                                <td>{{ \App\Services\Acc\LedgerService::FS_LABELS[$k] ?? ucwords(str_replace('_', ' ', $k)) }}</td>
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
