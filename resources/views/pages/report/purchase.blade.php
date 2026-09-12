<?php

use App\Services\Report\ManagementReportService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Rincian Pembelian -- sisi lawan dari Rincian Penjualan. Menampilkan rantai
// PR -> PO -> Penerimaan -> Pembayaran, semuanya dari arsip hasil migrasi.
new #[Title('Rincian Pembelian — TELASIH MAKMUR ERP')] class extends Component
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
        return app(ManagementReportService::class)->topSuppliers($this->year, 20);
    }

    /** Konfigurasi per tab: tabel, kolom tanggal, kolom nomor, label. */
    private function tabSource(): array
    {
        return match ($this->tab) {
            'pr' => ['pr_requisition_legacy', 'pr_date', 'legacy_pr_no', false],
            'receive' => ['po_receive_legacy', 'prcv_date', 'legacy_prcv_no', false],
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
    $months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $tabs = [
        'pr' => 'Permintaan (PR)',
        'po' => 'Purchase Order',
        'receive' => 'Penerimaan Barang',
        'payment' => 'Pembayaran',
    ];
@endphp

<div class="p-4">
    <div class="flex flex-wrap items-end gap-3 mb-3.5">
        <a href="{{ route('report.executive', ['th' => $year]) }}"
           class="text-[12px] text-[#7c3aed] hover:underline pb-1.5">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
        <div>
            <label class="block text-[10.5px] font-bold uppercase opacity-60 mb-1">Tahun</label>
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px] bg-white">
                @foreach ($this->years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10.5px] font-bold uppercase opacity-60 mb-1">Cari nomor dokumen</label>
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="Ketik nomor dokumen…"
                   class="w-full border rounded-lg px-2.5 py-1.5 text-[12.5px] bg-white">
        </div>
    </div>

    <x-grid.stat-row :count="4">
        <x-grid.stat-box tone="total" label="Nilai Purchase Order"
            :value="$miliar($this->summary['po_value'])"
            :subtitle="number_format($this->summary['po_docs']) . ' dokumen PO'" />
        <x-grid.stat-box tone="warn" label="Permintaan Pembelian"
            :value="number_format($this->summary['pr_docs'])" subtitle="dokumen PR" />
        <x-grid.stat-box tone="active" label="Penerimaan Barang"
            :value="number_format($this->summary['receive_docs'])" subtitle="dokumen penerimaan" />
        <x-grid.stat-box tone="critical" label="Pembayaran ke Supplier"
            :value="$miliar($this->summary['payment_value'])"
            :subtitle="number_format($this->summary['payment_docs']) . ' voucher'" />
    </x-grid.stat-row>

    <div class="[display:grid] grid-cols-1 lg:grid-cols-2 gap-3.5 mb-3.5">
        <div class="rounded-xl border bg-white shadow-sm p-4">
            <h3 class="font-bold text-[13px] mb-2">Tren Pembelian Bulanan {{ $year }}</h3>
            <x-chart.apex type="area" :height="260" :config="[
                'series' => [['name' => 'Pembelian', 'data' => array_map(fn ($m) => round($m['value']), $this->monthly)]],
                'xaxis' => ['categories' => $months],
                'stroke' => ['curve' => 'smooth', 'width' => 3],
                'fill' => ['type' => 'gradient', 'gradient' => ['opacityFrom' => 0.3, 'opacityTo' => 0.02]],
                'legend' => ['show' => false],
                'colors' => ['#7c3aed'],
            ]" />
        </div>

        <div class="rounded-xl border bg-white shadow-sm p-4">
            <h3 class="font-bold text-[13px] mb-2">Supplier Terbesar {{ $year }}</h3>
            <x-chart.apex type="bar" :height="260" :config="[
                'series' => [['name' => 'Nilai PO', 'data' => array_map(fn ($s) => round($s['value']), array_slice($this->topSuppliers, 0, 10))]],
                'xaxis' => ['categories' => array_map(fn ($s) => str($s['name'])->limit(24)->value(), array_slice($this->topSuppliers, 0, 10))],
                'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '65%']],
                'legend' => ['show' => false],
                'colors' => ['#7c3aed'],
            ]" />
        </div>
    </div>

    <div class="flex gap-1 mb-3 border-b">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="px-3.5 py-2 text-[12px] font-semibold border-b-2 -mb-px
                           {{ $tab === $key ? 'border-[#7c3aed] text-[#7c3aed]' : 'border-transparent opacity-60 hover:opacity-100' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="rounded-xl border bg-white shadow-sm overflow-hidden">
        <table class="w-full text-[11.5px]">
            <thead class="bg-black/[0.03]">
                <tr class="text-left">
                    <th class="py-2 px-3">Nomor</th>
                    <th class="py-2 px-3">Tanggal</th>
                    <th class="py-2 px-3">Supplier</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3 text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->documents as $doc)
                    <tr class="border-t border-black/5 hover:bg-black/[0.02]">
                        <td class="py-1.5 px-3 font-medium">{{ $doc->doc_no }}</td>
                        <td class="py-1.5 px-3">{{ $doc->doc_date }}</td>
                        <td class="py-1.5 px-3">
                            {{ isset($doc->supplier_id) ? ($this->supplierNames[$doc->supplier_id] ?? '(tidak dikenal)') : '—' }}
                        </td>
                        <td class="py-1.5 px-3">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-black/5">
                                {{ $doc->doc_status_normalized ?? '—' }}
                            </span>
                        </td>
                        <td class="py-1.5 px-3 text-right tabular-nums">
                            {{ $doc->doc_value !== null ? $rp($doc->doc_value) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center opacity-55">Tidak ada dokumen pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3 border-t">{{ $this->documents->links() }}</div>
    </div>
</div>
