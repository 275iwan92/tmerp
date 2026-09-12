<?php

use App\Services\Report\ManagementReportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Dashboard Eksekutif -- meniru struktur "TM OM DAILY REPORT" yang sudah
// dipakai Direksi (docs/attachments), tapi dihitung langsung dari data hasil
// migrasi tm2old. Setiap angka bisa diklik menuju rinciannya.
new #[Title('Dashboard Eksekutif — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url(as: 'th')]
    public int $year = 0;

    #[Url(as: 'basis')]
    public string $basis = 'shipment';

    #[Url(as: 'confirmed')]
    public bool $confirmedOnly = false;

    public function mount(): void
    {
        if ($this->year === 0) {
            $this->year = (int) (self::svc()->availableYears()[0] ?? now()->year);
        }
    }

    private static function svc(): ManagementReportService
    {
        return app(ManagementReportService::class);
    }

    #[Computed]
    public function years(): array
    {
        return self::svc()->availableYears();
    }

    #[Computed]
    public function summary(): array
    {
        return self::svc()->yearSummary($this->year, $this->confirmedOnly);
    }

    #[Computed]
    public function yoy(): array
    {
        return self::svc()->yearOverYear($this->year, $this->basis, $this->confirmedOnly);
    }

    #[Computed]
    public function quarterly(): array
    {
        return self::svc()->quarterly($this->year);
    }

    #[Computed]
    public function topCustomers(): array
    {
        return self::svc()->topCustomers($this->year, $this->basis, 8);
    }

    #[Computed]
    public function salesPersons(): array
    {
        return array_slice(self::svc()->salesPersonPerformance($this->year), 0, 8);
    }

    #[Computed]
    public function purchase(): array
    {
        return self::svc()->purchaseSummary($this->year);
    }

    #[Computed]
    public function invoice(): array
    {
        return self::svc()->invoiceSummary($this->year);
    }

    #[Computed]
    public function monthlyPurchase(): array
    {
        return self::svc()->monthlyPurchase($this->year);
    }

    // Harga tidak pernah dicatat di Surat Jalan sebelum pertengahan 2023.
    // Peringatan ini mencegah pembaca menyimpulkan "penjualan anjlok".
    #[Computed]
    public function shipmentDataWarning(): bool
    {
        return $this->basis === 'shipment' && $this->year < ManagementReportService::SHIPMENT_VALUE_FROM_YEAR;
    }

    /** Tanggal PO terakhir -- dipakai menjelaskan tahun yang pembeliannya Rp 0. */
    #[Computed]
    public function purchaseLastDate(): ?string
    {
        return self::svc()->purchaseDataLastDate();
    }
}; ?>

@php
    $months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $growthBadge = function (?float $g) {
        if ($g === null) {
            return '<span class="opacity-45">n/a</span>';
        }
        $cls = $g >= 0 ? 'text-[#16a34a]' : 'text-[#c0392b]';

        return '<span class="' . $cls . ' font-semibold">' . ($g >= 0 ? '+' : '') . number_format($g, 2, ',', '.') . '%</span>';
    };
@endphp

<div class="p-4">

    {{-- ============ Filter periode & definisi metrik ============ --}}
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label class="block text-[10.5px] font-bold uppercase opacity-60 mb-1">Tahun</label>
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px] bg-white">
                @foreach ($this->years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10.5px] font-bold uppercase opacity-60 mb-1">Dasar Omset</label>
            <select wire:model.live="basis" class="border rounded-lg px-2.5 py-1.5 text-[12.5px] bg-white">
                <option value="shipment">Shipment (Surat Jalan)</option>
                <option value="order">PO Masuk (Customer Order)</option>
            </select>
        </div>
        <label class="flex items-center gap-2 text-[12px] pb-1.5">
            <input type="checkbox" wire:model.live="confirmedOnly" class="rounded">
            Hanya CO terkonfirmasi
        </label>

        <div class="ml-auto flex gap-2">
            <a href="{{ route('report.sales', ['th' => $year]) }}"
               class="px-3 py-1.5 rounded-lg bg-[#2563eb] text-white text-[12px] font-semibold hover:bg-[#1d4ed8]">
                Rincian Penjualan →
            </a>
            <a href="{{ route('report.purchase', ['th' => $year]) }}"
               class="px-3 py-1.5 rounded-lg bg-[#7c3aed] text-white text-[12px] font-semibold hover:bg-[#6d28d9]">
                Rincian Pembelian →
            </a>
        </div>
    </div>

    @if ($this->shipmentDataWarning)
        <div class="mb-3.5 rounded-lg border border-[#f5e0b3] bg-[#fef6e7] px-3.5 py-2.5 text-[12px] text-[#8a6d00]">
            <strong>Perhatian:</strong> harga pada Surat Jalan baru dicatat sistem lama mulai pertengahan 2023.
            Untuk tahun {{ $year }} angka "by Shipment" akan tampak Rp 0 — itu keterbatasan pencatatan, bukan
            berarti tidak ada pengiriman. Gunakan dasar <em>PO Masuk</em> untuk periode ini.
        </div>
    @endif

    {{-- ============ KPI ============ --}}
    <x-grid.stat-row :count="5">
        <x-grid.stat-box tone="total" label="Omset by Shipment"
            :value="$miliar($this->summary['shipment'])"
            :subtitle="number_format($this->summary['shipment_docs']) . ' surat jalan'" />
        <x-grid.stat-box tone="active" label="Omset by PO Masuk"
            :value="$miliar($this->summary['order'])"
            :subtitle="number_format($this->summary['order_docs']) . ' customer order'" />
        <x-grid.stat-box tone="extra" label="Nilai Invoice Terbit"
            :value="$miliar($this->invoice['value'])"
            :subtitle="number_format($this->invoice['docs']) . ' invoice'" />
        <x-grid.stat-box tone="warn" label="Nilai Pembelian (PO)"
            :value="$miliar($this->purchase['po_value'])"
            :subtitle="number_format($this->purchase['po_docs']) . ' purchase order'" />
        <x-grid.stat-box tone="critical" label="Pembayaran ke Supplier"
            :value="$miliar($this->purchase['payment_value'])"
            :subtitle="number_format($this->purchase['payment_docs']) . ' voucher bayar'" />
    </x-grid.stat-row>

    {{-- ============ Tren omset YoY ============ --}}
    <div class="rounded-xl border bg-white shadow-sm p-4 mb-3.5">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="font-bold text-[13px]">
                Omset Bulanan {{ $year }} vs {{ $year - 1 }}
                <span class="font-normal opacity-60">— dasar {{ $basis === 'shipment' ? 'Shipment' : 'PO Masuk' }}</span>
            </h3>
            <span class="text-[12px]">
                Pertumbuhan tahunan:
                {!! $growthBadge($basis === 'shipment' ? $this->summary['shipment_growth'] : $this->summary['order_growth']) !!}
            </span>
        </div>

        <x-chart.apex type="area" :height="320" :config="[
            'series' => [
                ['name' => (string) $year, 'data' => array_map(fn ($r) => round($r['current']), $this->yoy)],
                ['name' => (string) ($year - 1), 'data' => array_map(fn ($r) => round($r['previous']), $this->yoy)],
            ],
            'xaxis' => ['categories' => $months],
            'stroke' => ['curve' => 'smooth', 'width' => 3],
            'fill' => [
                'type' => 'gradient',
                'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.28, 'opacityTo' => 0.02, 'stops' => [0, 92]],
            ],
            'markers' => ['size' => 0, 'hover' => ['size' => 6]],
        ]" />

        <div class="overflow-x-auto mt-2">
            <table class="w-full text-[11.5px]">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-1.5 px-2">Bulan</th>
                        <th class="py-1.5 px-2 text-right">{{ $year }}</th>
                        <th class="py-1.5 px-2 text-right">{{ $year - 1 }}</th>
                        <th class="py-1.5 px-2 text-right">Growth</th>
                        <th class="py-1.5 px-2 text-right">Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->yoy as $i => $row)
                        <tr class="border-b border-black/5 hover:bg-black/[0.02]">
                            <td class="py-1 px-2 font-medium">{{ $months[$i] }} {{ substr((string) $year, 2) }}</td>
                            <td class="py-1 px-2 text-right tabular-nums">{{ $rp($row['current']) }}</td>
                            <td class="py-1 px-2 text-right tabular-nums opacity-70">{{ $rp($row['previous']) }}</td>
                            <td class="py-1 px-2 text-right tabular-nums">{!! $growthBadge($row['growth']) !!}</td>
                            <td class="py-1 px-2 text-right">
                                <a class="text-[#2563eb] hover:underline"
                                   href="{{ route('report.sales', ['th' => $year, 'bln' => $row['month'], 'basis' => $basis]) }}">buka →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ Kuartal + Top customer + Salesperson ============ --}}
    <div class="[display:grid] grid-cols-1 lg:grid-cols-3 gap-3.5 mb-3.5">
        <div class="rounded-xl border bg-white shadow-sm p-4">
            <h3 class="font-bold text-[13px] mb-2">Pencapaian per Kuartal {{ $year }}</h3>
            <x-chart.apex type="bar" :height="240" :config="[
                'series' => [
                    ['name' => 'Shipment', 'data' => array_map(fn ($q) => round($q['shipment']), $this->quarterly)],
                    ['name' => 'PO Masuk', 'data' => array_map(fn ($q) => round($q['order']), $this->quarterly)],
                ],
                'xaxis' => ['categories' => ['Q1', 'Q2', 'Q3', 'Q4']],
                'plotOptions' => ['bar' => ['borderRadius' => 5, 'columnWidth' => '58%']],
            ]" />
        </div>

        <div class="rounded-xl border bg-white shadow-sm p-4">
            <div class="flex items-baseline justify-between mb-2">
                <h3 class="font-bold text-[13px]">Top Customer</h3>
                <a href="{{ route('report.sales', ['th' => $year, 'tab' => 'customer']) }}"
                   class="text-[11px] text-[#2563eb] hover:underline">semua →</a>
            </div>
            <x-chart.apex type="bar" :height="240" :config="[
                'series' => [['name' => 'Omset', 'data' => array_map(fn ($c) => round($c['value']), $this->topCustomers)]],
                'xaxis' => ['categories' => array_map(fn ($c) => str($c['name'])->limit(22)->value(), $this->topCustomers)],
                'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '65%']],
                'legend' => ['show' => false],
                'colors' => ['#2563eb'],
            ]" />
            <div class="mt-2 space-y-0.5">
                @foreach ($this->topCustomers as $c)
                    <a href="{{ route('report.customer', ['customer' => $c['id'], 'th' => $year]) }}"
                       class="flex justify-between text-[11px] hover:bg-black/[0.03] rounded px-1 py-0.5">
                        <span class="truncate text-[#2563eb] hover:underline">{{ $c['name'] }}</span>
                        <span class="tabular-nums opacity-70 shrink-0 ml-2">{{ $miliar($c['value']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border bg-white shadow-sm p-4">
            <div class="flex items-baseline justify-between mb-2">
                <h3 class="font-bold text-[13px]">Omset per Salesperson</h3>
                <a href="{{ route('report.sales', ['th' => $year, 'tab' => 'sales']) }}"
                   class="text-[11px] text-[#2563eb] hover:underline">semua →</a>
            </div>
            <x-chart.apex type="donut" :height="250" :config="[
                'series' => array_map(fn ($s) => round($s['value']), $this->salesPersons),
                'labels' => array_map(fn ($s) => $s['name'], $this->salesPersons),
                'legend' => ['position' => 'bottom', 'fontSize' => '11px'],
                'plotOptions' => ['pie' => ['donut' => ['size' => '62%']]],
                'yaxis' => ['labels' => ['show' => false]],
                'tooltip' => ['shared' => false, 'intersect' => true],
            ]" />
            <p class="text-[10.5px] opacity-55 mt-1.5">
                Kolom sales hanya terisi pada sebagian CO di sistem lama — sisanya masuk "(tidak tercatat)".
            </p>
        </div>
    </div>

    {{-- ============ Pembelian ============ --}}
    <div class="rounded-xl border bg-white shadow-sm p-4">
        @if ($this->purchase['po_docs'] === 0 && $this->purchaseLastDate)
            <div class="mb-3 rounded-lg border border-[#c8ddfb] bg-[#eaf2ff] px-3.5 py-2.5 text-[12px] text-[#3b5b99]">
                Tidak ada Purchase Order tercatat di {{ $year }}. Pencatatan pembelian di sistem lama
                berhenti pada <strong>{{ $this->purchaseLastDate }}</strong> — angka Rp 0 di sini mencerminkan
                batas data itu, bukan berarti perusahaan berhenti membeli.
            </div>
        @endif

        <div class="flex items-baseline justify-between mb-2">
            <h3 class="font-bold text-[13px]">Tren Pembelian {{ $year }}</h3>
            <a href="{{ route('report.purchase', ['th' => $year]) }}"
               class="text-[11px] text-[#7c3aed] hover:underline">rincian pembelian →</a>
        </div>
        <x-chart.apex type="bar" :height="250" :config="[
            'series' => [['name' => 'Pembelian', 'data' => array_map(fn ($m) => round($m['value']), $this->monthlyPurchase)]],
            'xaxis' => ['categories' => $months],
            'plotOptions' => ['bar' => ['borderRadius' => 5, 'columnWidth' => '55%']],
            'legend' => ['show' => false],
            'colors' => ['#7c3aed'],
        ]" />

        <div class="flex flex-wrap gap-x-6 gap-y-1 text-[11.5px] mt-3 pt-3 border-t">
            <span>Permintaan Pembelian (PR): <strong>{{ number_format($this->purchase['pr_docs']) }}</strong> dokumen</span>
            <span>Purchase Order: <strong>{{ number_format($this->purchase['po_docs']) }}</strong> dokumen</span>
            <span>Penerimaan Barang: <strong>{{ number_format($this->purchase['receive_docs']) }}</strong> dokumen</span>
        </div>
    </div>
</div>
