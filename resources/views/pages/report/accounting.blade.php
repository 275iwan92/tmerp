<?php

use App\Services\Report\ManagementReportService;
use App\Support\DataOrigin\Visible;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Laporan Akuntansi & Keuangan -- salah satu "macam laporan" yang diminta
// user 2026-09-13 ("saya maunya laporan itu ada berbagai macam") saat menunjuk
// mockup referensi (printflow_erp_complete_dashboard_mockup.html, sidebar
// bergrup Executive/Purchasing/Sales/Accounting/Manufacturing/HR/dst).
// Menu MGMT_EXEC/MGMT_SALES/MGMT_PURCHASE ternyata SUDAH ADA di sys_app_menu
// tapi TIDAK PERNAH dipetakan ke route di main-menu.blade.php::itemRoutes()
// -- itu sebab "tidak ada menu di panel kiri" yang dikeluhkan. Diperbaiki
// bareng penambahan 4 laporan baru ini (lihat migration
// 2026_09_13_000004_add_report_menu_items.php).
//
// Data 100% nyata (fin_voucher/fin_voucher_line/fin_account), TIDAK ada
// angka mockup yang dikarang. Hutang usaha reuse ManagementReportService
// yang sudah ada (bukan duplikasi logic).
new #[Layout('layouts::report')] #[Title('Laporan Akuntansi — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url(as: 'th')]
    public int $year = 0;

    public function mount(): void
    {
        if ($this->year === 0) {
            $this->year = (int) now()->year;
        }
    }

    #[Computed]
    public function coaActive(): int
    {
        return DB::connection('tm2acc')->table('fin_account')->where('activate', true)->count();
    }

    #[Computed]
    public function voucherStats(): array
    {
        $rows = Visible::apply(DB::connection('tm2acc')->table('fin_voucher'), 'tm2acc', 'fin_voucher')
            ->whereYear('posting_date', $this->year)
            ->selectRaw("status, COUNT(*) as n")
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'posted' => (int) ($rows['POSTED'] ?? 0),
            'draft' => (int) ($rows['DRAFT'] ?? 0),
            'cancelled' => (int) ($rows['CANCELLED'] ?? 0),
        ];
    }

    #[Computed]
    public function postedValue(): float
    {
        return (float) Visible::apply(DB::connection('tm2acc')->table('fin_voucher_line as l')
            ->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id'), 'tm2acc', 'fin_voucher', 'v')
            ->where('v.status', 'POSTED')
            ->whereYear('v.posting_date', $this->year)
            ->sum('l.debit');
    }

    #[Computed]
    public function payable(): array
    {
        return app(ManagementReportService::class)->accountsPayable();
    }

    #[Computed]
    public function topPayableSuppliers(): array
    {
        return app(ManagementReportService::class)->topPayableSuppliers(5);
    }

    #[Computed]
    public function monthlyPostedValue(): array
    {
        $rows = Visible::apply(DB::connection('tm2acc')->table('fin_voucher_line as l')
            ->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id'), 'tm2acc', 'fin_voucher', 'v')
            ->where('v.status', 'POSTED')
            ->whereYear('v.posting_date', $this->year)
            ->selectRaw('MONTH(v.posting_date) as m, SUM(l.debit) as total')
            ->groupBy('m')
            ->pluck('total', 'm');

        return collect(range(1, 12))->map(fn ($m) => (float) ($rows[$m] ?? 0))->all();
    }
};
?>

@php
    $months = array_map(fn ($m) => t($m, 'date.month_short'), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">{{ t('Accounting & Finance Report') }}</h1>
            <p class="text-[13px] mt-0.5" style="color:var(--sh-muted)">{{ t('Journal postings, accounts, and trade payables') }} — CV. Telasih Makmur.</p>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">{{ t('Year') }}</label>
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                @for ($y = now()->year; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <x-report.kpi :label="t('Active COA Accounts')" icon="fa-solid fa-sitemap" tone="blue" :value="number_format($this->coaActive)" :note="t('Chart of Accounts')" />
        <x-report.kpi :label="t('Posted Vouchers')" icon="fa-solid fa-check-double" tone="green" :value="number_format($this->voucherStats['posted'])" :note="str_replace([':draft', ':cancelled'], [$this->voucherStats['draft'], $this->voucherStats['cancelled']], t(':draft draft · :cancelled cancelled'))" />
        <x-report.kpi :label="t('Posted Value This Year')" icon="fa-solid fa-money-bill-trend-up" tone="violet" :value="$miliar($this->postedValue)" :note="t('Total posted debit side')" />
        <x-report.kpi :label="t('Outstanding Trade Payables')" icon="fa-solid fa-scale-balanced" tone="danger" :value="$miliar($this->payable['outstanding'])" :note="str_replace(':amount', $miliar($this->payable['invoice_value']), t('of :amount billed'))" />
    </div>

    <div class="rounded-2xl border p-4 mb-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ t('Monthly Journal Posting Value') }} {{ $year }}</h2>
        <x-report.svg-trend :height="220" :labels="$months" :series="[
            ['label' => t('Posting'), 'color' => 'var(--sh-accent)', 'data' => $this->monthlyPostedValue, 'area' => true],
        ]" />
    </div>

    <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ t('Suppliers with the Largest Payables') }}</h2>
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left border-b" style="border-color:var(--sh-border)">
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Supplier') }}</th>
                    <th class="pb-2 font-bold uppercase text-[10px] text-right" style="color:var(--sh-muted)">{{ t('Outstanding Payable') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->topPayableSuppliers as $s)
                    <tr class="border-b" style="border-color:var(--sh-border)">
                        <td class="py-2 font-semibold" style="color:var(--sh-ink-strong)">{{ $s['name'] }}</td>
                        <td class="py-2 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($s['outstanding']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="py-3 text-center" style="color:var(--sh-muted)">{{ t('No open payables.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
