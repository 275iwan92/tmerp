<?php

use App\Services\Report\ManagementReportService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Rincian Pembelian -- sisi lawan dari Rincian Penjualan. Menampilkan rantai
// PR -> PO -> Penerimaan -> Invoice -> Pembayaran, semuanya dari arsip hasil migrasi.
new #[Layout('layouts::report')] #[Title('Purchase Details — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    #[Url(as: 'th')]
    public int $year = 0;

    #[Url(as: 'tab')]
    public string $tab = 'po';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        if ($this->year === 0) {
            $this->year = (int) (app(ManagementReportService::class)->availableYears()[0] ?? now()->year);
        }
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['year', 'tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function years(): array
    {
        return app(ManagementReportService::class)->availableYears();
    }

    #[Computed]
    public function summary(): array
    {
        return app(ManagementReportService::class)->purchaseSummary($this->year);
    }

    #[Computed]
    public function monthly(): array
    {
        return app(ManagementReportService::class)->monthlyPurchase($this->year);
    }

    #[Computed]
    public function topSuppliers(): array
    {
        return app(ManagementReportService::class)->topSuppliers($this->year, 10);
    }

    #[Computed]
    public function payable(): array
    {
        return app(ManagementReportService::class)->accountsPayable();
    }

    #[Computed]
    public function payableSuppliers(): array
    {
        return app(ManagementReportService::class)->topPayableSuppliers(12);
    }

    /** Invoice yang supplier-nya tak terpetakan -- angka per-supplier jadi tak utuh. */
    #[Computed]
    public function unmappedInvoices(): int
    {
        return DB::connection('tm2pur')->table('po_invoice_legacy')->whereNull('supplier_id')->count();
    }

    /** Konfigurasi per tab: tabel, kolom tanggal, kolom nomor, label. */
    private function tabSource(): array
    {
        return match ($this->tab) {
            'pr' => ['pr_requisition_legacy', 'pr_date', 'legacy_pr_no', false],
            'receive' => ['po_receive_legacy', 'prcv_date', 'legacy_prcv_no', false],
            'invoice' => ['po_invoice_legacy', 'pinv_date', 'legacy_pinv_no', true],
            'payment' => ['po_payment_legacy', 'ppym_date', 'legacy_ppym_no', true],
            default => ['po_order_legacy', 'po_date', 'legacy_po_no', true],
        };
    }

    #[Computed]
    public function documents()
    {
        [$table, $dateCol, $noCol, $hasValue] = $this->tabSource();

        return DB::connection('tm2pur')->table($table)
            ->whereYear($dateCol, $this->year)
            ->when($this->search !== '', fn ($q) => $q->where($noCol, 'like', '%' . $this->search . '%'))
            ->orderByDesc($dateCol)
            ->select('*', DB::raw("$noCol as doc_no"), DB::raw("$dateCol as doc_date"),
                DB::raw($hasValue ? 'grand_total as doc_value' : 'NULL as doc_value'))
            ->paginate(25);
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = collect($this->documents->items())->pluck('supplier_id')->filter()->unique();

        return $ids->isEmpty() ? [] : DB::connection('tm2mst')->table('sup_supplier')
            ->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }
}; ?>

@php
    $months = array_map(fn ($m) => t($m, 'date.month_short'), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $tabs = [
        'pr' => t('Requisition (PR)'),
        'po' => t('Purchase Order'),
        'receive' => t('Goods Receipt'),
        'invoice' => t('Supplier Invoice'),
        'payment' => t('Payment'),
    ];
    $statusTone = fn ($s) => match ($s) { 'APPROVED', 'RECEIVED', 'PAID', 'INVOICED' => 'ok', 'CANCELLED' => 'danger', 'DRAFT' => 'info', default => 'warn' };
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <a href="{{ route('report.executive', ['th' => $year]) }}"
           class="text-[12px] font-semibold hover:underline pb-1.5" style="color:var(--sh-violet)">
            <i class="fa-solid fa-arrow-left"></i> {{ t('Dashboard') }}
        </a>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">{{ t('Year') }}</label>
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                @foreach ($this->years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">{{ t('Search document number') }}</label>
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="{{ t('Type a document number…') }}"
                   class="w-full border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
        </div>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <x-report.kpi :label="t('Purchase Order Value')" icon="fa-solid fa-cart-shopping" tone="blue"
            :value="$miliar($this->summary['po_value'])" :note="str_replace(':n', number_format($this->summary['po_docs']), t(':n PO documents'))" />
        <x-report.kpi :label="t('Purchase Requisitions')" icon="fa-solid fa-clipboard-list" tone="amber"
            :value="number_format($this->summary['pr_docs'])" :note="t('PR documents')" />
        <x-report.kpi :label="t('Goods Receipt')" icon="fa-solid fa-truck-ramp-box" tone="green"
            :value="number_format($this->summary['receive_docs'])" :note="t('receipt documents')" />
        <x-report.kpi :label="t('Payments to Suppliers')" icon="fa-solid fa-money-bill-transfer" tone="violet"
            :value="$miliar($this->summary['payment_value'])" :note="str_replace(':n', number_format($this->summary['payment_docs']), t(':n vouchers'))" />
    </div>

    <div class="[display:grid] grid-cols-1 lg:grid-cols-2 gap-3.5 mb-4">
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h3 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ t('Monthly Purchase Trend') }} {{ $year }}</h3>
            <x-report.svg-trend :height="240" :labels="$months" :series="[
                ['label' => t('Purchases'), 'color' => 'var(--sh-violet)', 'data' => array_column($this->monthly, 'value'), 'area' => true],
            ]" />
        </div>

        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h3 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ t('Largest Suppliers') }} {{ $year }}</h3>
            <x-report.svg-bars horizontal :labels="array_map(fn ($s) => $s['name'], $this->topSuppliers)" :series="[
                ['data' => array_map(fn ($s) => $s['value'], $this->topSuppliers), 'color' => 'var(--sh-violet)'],
            ]" />
        </div>
    </div>

    {{-- ============ Utang Usaha (AP) ============ --}}
    <div class="rounded-2xl border p-4 mb-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <div class="flex items-baseline justify-between mb-3">
            <h3 class="font-bold text-[14px]" style="color:var(--sh-ink-strong)">{{ t('Trade Payables (AP) — all periods') }}</h3>
            <span class="text-[11px]" style="color:var(--sh-muted)">{{ t('from supplier invoices less actual payments') }}</span>
        </div>

        <div class="[display:grid] grid-cols-1 sm:grid-cols-3 gap-3.5 mb-4">
            <x-report.kpi :label="t('Total Supplier Invoices')" icon="fa-solid fa-file-invoice-dollar" tone="blue"
                :value="$miliar($this->payable['invoice_value'])" :note="str_replace(':n', number_format($this->payable['invoice_docs']), t(':n invoices'))" />
            <x-report.kpi :label="t('Already Paid')" icon="fa-solid fa-circle-check" tone="green"
                :value="$miliar($this->payable['paid_value'])" :note="t('recorded actual payments')" />
            <x-report.kpi :label="t('Outstanding Payable')" icon="fa-solid fa-triangle-exclamation" tone="danger"
                :value="$miliar($this->payable['outstanding'])" :note="t('invoices less payments')" />
        </div>

        <table class="w-full text-[12px]">
            <thead><tr class="text-left border-b" style="border-color:var(--sh-border)">
                <th class="py-1.5 px-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Supplier') }}</th>
                <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Invoice') }}</th>
                <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Amount Invoiced') }}</th>
                <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Paid') }}</th>
                <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Outstanding Payable') }}</th>
            </tr></thead>
            <tbody>
                @foreach ($this->payableSuppliers as $s)
                    <tr class="border-b" style="border-color:var(--sh-border)">
                        <td class="py-1.5 px-2" style="color:var(--sh-ink)">{{ $s['name'] }}</td>
                        <td class="py-1.5 px-2 text-right tabular-nums" style="color:var(--sh-ink)">{{ number_format($s['docs']) }}</td>
                        <td class="py-1.5 px-2 text-right tabular-nums" style="color:var(--sh-ink)">{{ $rp($s['invoiced']) }}</td>
                        <td class="py-1.5 px-2 text-right tabular-nums" style="color:var(--sh-muted)">{{ $rp($s['paid']) }}</td>
                        <td class="py-1.5 px-2 text-right tabular-nums font-semibold" style="color:{{ $s['outstanding'] > 0 ? 'var(--sh-danger)' : 'var(--sh-ink-strong)' }}">
                            {{ $rp($s['outstanding']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-3 pt-3 border-t space-y-2" style="border-color:var(--sh-border)">
            @if ($this->unmappedInvoices > 0)
                <x-report.insight tone="danger">
                    <strong>{{ number_format($this->unmappedInvoices) }}</strong> {{ t('invoices cannot be linked to') }}
                    {{ t('any supplier (the legacy system stored the company name in the code column, e.g. MADJU/TANINDO/KEISHA),') }}
                    {{ t('so the per-supplier table above does not yet cover the full value.') }}
                </x-report.insight>
            @endif
            <p class="text-[11px]" style="color:var(--sh-muted)">
                {{ t('A negative outstanding payable means recorded payments exceed the bills — the invoice was probably') }}
                {{ t('not recorded in the legacy system. Shown as is, not forced to zero.') }} {{ t('The sales-side counterpart') }}
                {{ t('(receivables/AR) cannot be calculated: the customer payment receipt table in the legacy system') }}
                (<code>sdi_cpym</code>) {{ t('is completely empty.') }}
            </p>
        </div>
    </div>

    <div class="flex gap-1 mb-3 border-b" style="border-color:var(--sh-border)">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="px-3.5 py-2 text-[12px] font-semibold border-b-2 -mb-px"
                    style="{{ $tab === $key ? 'border-color:var(--sh-violet);color:var(--sh-violet)' : 'border-color:transparent;color:var(--sh-muted)' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="rounded-2xl border overflow-hidden" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <table class="w-full text-[12px]">
            <thead style="background:var(--content-bg)">
                <tr class="text-left">
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Number') }}</th>
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Date') }}</th>
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Supplier') }}</th>
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Status') }}</th>
                    <th class="py-2 px-3 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->documents as $doc)
                    <tr class="border-t" style="border-color:var(--sh-border)">
                        <td class="py-2 px-3 font-semibold" style="color:var(--sh-ink-strong)">{{ $doc->doc_no }}</td>
                        <td class="py-2 px-3" style="color:var(--sh-ink)">{{ $doc->doc_date }}</td>
                        <td class="py-2 px-3" style="color:var(--sh-ink)">
                            {{ isset($doc->supplier_id) ? ($this->supplierNames[$doc->supplier_id] ?? t('(unknown)')) : '—' }}
                        </td>
                        <td class="py-2 px-3">
                            <x-report.badge :tone="$statusTone($doc->doc_status_normalized ?? '')">{{ $doc->doc_status_normalized ?? '—' }}</x-report.badge>
                        </td>
                        <td class="py-2 px-3 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">
                            {{ $doc->doc_value !== null ? $rp($doc->doc_value) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center" style="color:var(--sh-muted)">{{ t('No documents match this filter.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3 border-t" style="border-color:var(--sh-border)">{{ $this->documents->links() }}</div>
    </div>
</div>
