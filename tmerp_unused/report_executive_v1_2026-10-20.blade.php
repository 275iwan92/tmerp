<?php

use App\Services\Report\ManagementReportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Dashboard Eksekutif -- desain digabung dari mockup referensi user
// (offset_printing_packaging_erp_dashboard_mockup.html) dengan data hasil
// migrasi tm2old. Grafik SVG server-rendered (keputusan user 2026-09-13,
// "boleh bangun sendiri pakai SVG") -- BUKAN ApexCharts lagi: markup murni
// hasil PHP jadi Livewire morph biasa cukup, tak perlu wire:ignore/wire:key
// trik yang dulu dipakai utk menahan ApexCharts.
new #[Layout('layouts::report')] #[Title('Dashboard Eksekutif — TELASIH MAKMUR ERP')] class extends Component
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
        return self::svc()->topCustomers($this->year, $this->basis, 5);
    }

    #[Computed]
    public function salesPersons(): array
    {
        return array_slice(self::svc()->salesPersonPerformance($this->year), 0, 6);
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
    public function payable(): array
    {
        return self::svc()->accountsPayable();
    }

    #[Computed]
    public function monthlyPurchase(): array
    {
        return self::svc()->monthlyPurchase($this->year);
    }

    #[Computed]
    public function dataHealth(): array
    {
        return self::svc()->purchasingDataHealth();
    }

    #[Computed]
    public function attention(): array
    {
        return self::svc()->managementAttention();
    }

    // Harga tidak pernah dicatat di Surat Jalan sebelum pertengahan 2023.
    #[Computed]
    public function shipmentDataWarning(): bool
    {
        return $this->basis === 'shipment' && $this->year < ManagementReportService::SHIPMENT_VALUE_FROM_YEAR;
    }

    #[Computed]
    public function purchaseLastDate(): ?string
    {
        return self::svc()->purchaseDataLastDate();
    }
}; ?>

@php
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $palette = ['var(--sh-accent)', 'var(--sh-ok)', 'var(--sh-warn)', 'var(--sh-violet)', 'var(--sh-danger)', 'var(--sh-amber)'];
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">

    {{-- ============ Headline ============ --}}
    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">Ringkasan Kinerja Eksekutif</h1>
            <p class="text-[13px] mt-0.5" style="color:var(--sh-muted)">Penjualan, pembelian, dan utang usaha terintegrasi — CV. Telasih Makmur.</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Tahun</label>
                <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                    @foreach ($this->years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Dasar Omset</label>
                <select wire:model.live="basis" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                    <option value="shipment">Shipment (Surat Jalan)</option>
                    <option value="order">PO Masuk (Customer Order)</option>
                </select>
            </div>
            <label class="flex items-center gap-1.5 text-[11.5px] pb-1.5" style="color:var(--sh-ink)">
                <input type="checkbox" wire:model.live="confirmedOnly" class="rounded">
                CO terkonfirmasi saja
            </label>
        </div>
    </div>

    @if ($this->shipmentDataWarning)
        <div class="mb-4">
            <x-report.insight tone="warn">
                <strong>Perhatian:</strong> harga pada Surat Jalan baru dicatat sistem lama mulai pertengahan 2023.
                Untuk tahun {{ $year }}, angka "by Shipment" akan tampak Rp 0 — gunakan dasar <em>PO Masuk</em> untuk periode ini.
            </x-report.insight>
        </div>
    @endif

    {{-- ============ KPI ============ --}}
    <div class="[display:grid] grid-cols-2 lg:grid-cols-5 gap-3.5 mb-4">
        <x-report.kpi label="Omset by Shipment" icon="fa-solid fa-truck-fast" tone="blue"
            :value="$miliar($this->summary['shipment'])"
            :trend="$this->summary['shipment_growth'] === null ? null : ($this->summary['shipment_growth'] >= 0 ? 'up' : 'down')"
            :delta="$this->summary['shipment_growth'] === null ? null : number_format(abs($this->summary['shipment_growth']), 1, ',', '.') . '%'"
            :note="'vs '.($year-1).' · '.number_format($this->summary['shipment_docs']).' SJ'" />

        <x-report.kpi label="Omset by PO Masuk" icon="fa-solid fa-file-invoice" tone="green"
            :value="$miliar($this->summary['order'])"
            :trend="$this->summary['order_growth'] === null ? null : ($this->summary['order_growth'] >= 0 ? 'up' : 'down')"
            :delta="$this->summary['order_growth'] === null ? null : number_format(abs($this->summary['order_growth']), 1, ',', '.') . '%'"
            :note="'vs '.($year-1).' · '.number_format($this->summary['order_docs']).' CO'" />

        <x-report.kpi label="Invoice Terbit" icon="fa-solid fa-receipt" tone="violet"
            :value="$miliar($this->invoice['value'])"
            :note="number_format($this->invoice['docs']).' invoice diterbitkan'" />

        <x-report.kpi label="Nilai Pembelian (PO)" icon="fa-solid fa-cart-shopping" tone="amber"
            :value="$miliar($this->purchase['po_value'])"
            :note="number_format($this->purchase['po_docs']).' purchase order'" />

        <x-report.kpi label="Sisa Utang Usaha" icon="fa-solid fa-scale-balanced" tone="danger"
            :value="$miliar($this->payable['outstanding'])"
            :note="'dari '.$miliar($this->payable['invoice_value']).' tertagih'" />
    </div>

    {{-- ============ Tren omset + Komposisi customer ============ --}}
    <div class="[display:grid] grid-cols-1 lg:grid-cols-[1.55fr_1fr] gap-3.5 mb-4">
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h2 class="font-bold text-[14px]" style="color:var(--sh-ink-strong)">Omset Bulanan {{ $year }} vs {{ $year - 1 }}</h2>
                    <p class="text-[11.5px] mt-0.5" style="color:var(--sh-muted)">Dasar: {{ $basis === 'shipment' ? 'Shipment (Surat Jalan)' : 'PO Masuk (Customer Order)' }}</p>
                </div>
            </div>
            <x-report.svg-trend :height="260" :labels="$months" :series="[
                ['label' => (string) $year, 'color' => 'var(--sh-accent)', 'data' => array_column($this->yoy, 'current'), 'area' => true],
                ['label' => (string) ($year - 1), 'color' => 'var(--sh-muted)', 'data' => array_column($this->yoy, 'previous')],
            ]" />
        </div>

        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h2 class="font-bold text-[14px]" style="color:var(--sh-ink-strong)">Komposisi Customer</h2>
                    <p class="text-[11.5px] mt-0.5" style="color:var(--sh-muted)">5 customer terbesar {{ $year }}</p>
                </div>
            </div>
            <x-report.svg-donut :center-value="$miliar($this->summary[$basis])" center-label="Total omset"
                :segments="collect($this->topCustomers)->values()->map(fn ($c, $i) => [
                    'label' => $c['name'],
                    'value' => $c['value'],
                    'color' => $palette[$i % count($palette)],
                ])->all()" />
        </div>
    </div>

    {{-- ============ Kuartal ============ --}}
    <div class="rounded-2xl border p-4 mb-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Pencapaian per Kuartal {{ $year }}</h2>
        <x-report.svg-bars :height="200" :labels="['Q1', 'Q2', 'Q3', 'Q4']" :series="[
            ['label' => 'Shipment', 'color' => 'var(--sh-accent)', 'data' => array_column($this->quarterly, 'shipment')],
            ['label' => 'PO Masuk', 'color' => 'var(--sh-ok)', 'data' => array_column($this->quarterly, 'order')],
        ]" />
    </div>

    {{-- ============ 3 kolom: Top Customer / Kesehatan Data / Perhatian Manajemen ============ --}}
    <div class="[display:grid] grid-cols-1 lg:grid-cols-3 gap-3.5 mb-4">
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <div class="flex items-start justify-between mb-3">
                <h2 class="font-bold text-[14px]" style="color:var(--sh-ink-strong)">Customer Teratas</h2>
                <a href="{{ route('report.sales', ['th' => $year, 'tab' => 'customer']) }}" class="text-[11px] font-semibold" style="color:var(--sh-accent)">semua →</a>
            </div>
            <table class="w-full text-[12px]">
                <thead>
                    <tr class="text-left border-b" style="border-color:var(--sh-border)">
                        <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Customer</th>
                        <th class="pb-2 font-bold uppercase text-[10px] text-right" style="color:var(--sh-muted)">Omset</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->topCustomers as $c)
                        <tr class="border-b" style="border-color:var(--sh-border)">
                            <td class="py-2">
                                <a href="{{ route('report.customer', ['customer' => $c['id'], 'th' => $year]) }}"
                                   class="font-semibold hover:underline" style="color:var(--sh-ink-strong)">{{ $c['name'] }}</a>
                                <div class="text-[10.5px]" style="color:var(--sh-muted)">{{ number_format($c['docs']) }} dokumen</div>
                            </td>
                            <td class="py-2 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($c['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h2 class="font-bold text-[14px] mb-1" style="color:var(--sh-ink-strong)">Kesehatan Rantai Dokumen</h2>
            <p class="text-[11px] mb-3" style="color:var(--sh-muted)">Purchasing, seluruh periode</p>
            @foreach ($this->dataHealth as $h)
                <x-report.progress-row :label="$h['label']" :pct="$h['pct']" :value="number_format($h['pct'], 1).'%'"
                    :color="$h['pct'] >= 95 ? 'var(--sh-ok)' : ($h['pct'] >= 70 ? 'var(--sh-warn)' : 'var(--sh-danger)')" />
            @endforeach
        </div>

        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <div class="flex items-start justify-between mb-3">
                <h2 class="font-bold text-[14px]" style="color:var(--sh-ink-strong)">Perhatian Manajemen</h2>
                @if (count($this->attention) > 0)
                    <x-report.badge tone="warn">{{ count($this->attention) }} temuan</x-report.badge>
                @endif
            </div>
            <div class="space-y-2.5">
                @forelse ($this->attention as $a)
                    <x-report.insight :tone="$a['tone']">
                        <strong>{{ $a['title'] }}:</strong> {{ $a['text'] }}
                    </x-report.insight>
                @empty
                    <p class="text-[12px]" style="color:var(--sh-muted)">Tidak ada temuan terbuka saat ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============ Pembelian ============ --}}
    <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        @if ($this->purchase['po_docs'] === 0 && $this->purchaseLastDate)
            <div class="mb-3">
                <x-report.insight tone="info">
                    Tidak ada Purchase Order tercatat di {{ $year }}. Pencatatan pembelian di sistem lama
                    berhenti pada <strong>{{ $this->purchaseLastDate }}</strong> — bukan berarti perusahaan berhenti membeli.
                </x-report.insight>
            </div>
        @endif
        <div class="flex items-start justify-between mb-3">
            <h2 class="font-bold text-[14px]" style="color:var(--sh-ink-strong)">Tren Pembelian {{ $year }}</h2>
            <a href="{{ route('report.purchase', ['th' => $year]) }}" class="text-[11px] font-semibold" style="color:var(--sh-violet)">rincian pembelian →</a>
        </div>
        <x-report.svg-trend :height="220" :labels="$months" :series="[
            ['label' => 'Pembelian', 'color' => 'var(--sh-violet)', 'data' => array_column($this->monthlyPurchase, 'value'), 'area' => true],
        ]" />
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-[11.5px] mt-3 pt-3 border-t" style="border-color:var(--sh-border);color:var(--sh-muted)">
            <span>Permintaan Pembelian (PR): <b style="color:var(--sh-ink-strong)">{{ number_format($this->purchase['pr_docs']) }}</b> dokumen</span>
            <span>Purchase Order: <b style="color:var(--sh-ink-strong)">{{ number_format($this->purchase['po_docs']) }}</b> dokumen</span>
            <span>Penerimaan Barang: <b style="color:var(--sh-ink-strong)">{{ number_format($this->purchase['receive_docs']) }}</b> dokumen</span>
        </div>
    </div>
</div>
