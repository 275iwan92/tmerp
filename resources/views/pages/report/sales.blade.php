<?php

use App\Services\Report\ManagementReportService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

// Rincian Penjualan -- lapis kedua setelah Dashboard Eksekutif. Dari sini
// pembaca bisa turun sampai daftar dokumen (CO / Surat Jalan / Invoice) dan
// menembus ke Customer 360.
new #[Title('Rincian Penjualan — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    #[Url(as: 'th')]
    public int $year = 0;

    #[Url(as: 'bln')]
    public ?int $month = null;

    #[Url(as: 'basis')]
    public string $basis = 'order';

    #[Url(as: 'tab')]
    public string $tab = 'dokumen';

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
        if (in_array($prop, ['year', 'month', 'basis', 'search', 'tab'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function years(): array
    {
        return app(ManagementReportService::class)->availableYears();
    }

    /** Tabel & kolom sumber mengikuti dasar omset yang dipilih. */
    private function source(): array
    {
        return $this->basis === 'shipment'
            ? ['co_delivery_legacy', 'dnote_date', 'legacy_dnote_no', 'Surat Jalan']
            : ['co_order_legacy', 'co_date', 'legacy_co_no', 'Customer Order'];
    }

    #[Computed]
    public function documents()
    {
        [$table, $dateCol, $noCol] = $this->source();

        $q = DB::connection('tm2sdi')->table($table . ' as d')
            ->whereYear("d.$dateCol", $this->year)
            ->when($this->month, fn ($qq) => $qq->whereMonth("d.$dateCol", $this->month))
            ->when($this->search !== '', function ($qq) use ($noCol) {
                $term = '%' . $this->search . '%';
                $ids = DB::connection('tm2mst')->table('cust_customer')
                    ->where('customer_name', 'like', $term)->pluck('id_customer');

                return $qq->where(fn ($w) => $w->where("d.$noCol", 'like', $term)
                    ->orWhereIn('d.customer_id', $ids));
            })
            ->orderByDesc("d.$dateCol")
            ->select("d.*", DB::raw("d.$noCol as doc_no"), DB::raw("d.$dateCol as doc_date"));

        return $q->paginate(25);
    }

    #[Computed]
    public function customerNames(): array
    {
        $ids = collect($this->documents->items())->pluck('customer_id')->filter()->unique();

        return $ids->isEmpty() ? [] : DB::connection('tm2mst')->table('cust_customer')
            ->whereIn('id_customer', $ids)->pluck('customer_name', 'id_customer')->all();
    }

    #[Computed]
    public function periodTotal(): array
    {
        [$table, $dateCol] = $this->source();

        $r = DB::connection('tm2sdi')->table($table)
            ->whereYear($dateCol, $this->year)
            ->when($this->month, fn ($qq) => $qq->whereMonth($dateCol, $this->month))
            ->selectRaw('COUNT(*) docs, COALESCE(SUM(grand_total),0) v')->first();

        return ['docs' => (int) $r->docs, 'value' => (float) $r->v];
    }

    #[Computed]
    public function customerRanking(): array
    {
        return app(ManagementReportService::class)->topCustomers($this->year, $this->basis, 30);
    }

    #[Computed]
    public function salesRanking(): array
    {
        return app(ManagementReportService::class)->salesPersonPerformance($this->year);
    }
}; ?>

@php
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $tabs = ['dokumen' => 'Daftar Dokumen', 'customer' => 'Peringkat Customer', 'sales' => 'Per Salesperson'];
@endphp

<div class="p-4">
    <div class="flex flex-wrap items-end gap-3 mb-3.5">
        <a href="{{ route('report.executive', ['th' => $year]) }}"
           class="text-[12px] text-[#2563eb] hover:underline pb-1.5">
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
        <div>
            <label class="block text-[10.5px] font-bold uppercase opacity-60 mb-1">Bulan</label>
            <select wire:model.live="month" class="border rounded-lg px-2.5 py-1.5 text-[12.5px] bg-white">
                <option value="">Semua bulan</option>
                @foreach ($months as $n => $nama)
                    <option value="{{ $n }}">{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10.5px] font-bold uppercase opacity-60 mb-1">Dasar</label>
            <select wire:model.live="basis" class="border rounded-lg px-2.5 py-1.5 text-[12.5px] bg-white">
                <option value="order">PO Masuk (Customer Order)</option>
                <option value="shipment">Shipment (Surat Jalan)</option>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10.5px] font-bold uppercase opacity-60 mb-1">Cari</label>
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="Nomor dokumen atau nama customer…"
                   class="w-full border rounded-lg px-2.5 py-1.5 text-[12.5px] bg-white">
        </div>
    </div>

    <x-grid.stat-row :count="2">
        <x-grid.stat-box tone="total" label="Nilai Periode Terpilih"
            :value="$miliar($this->periodTotal['value'])"
            :subtitle="$basis === 'shipment' ? 'berdasarkan Surat Jalan' : 'berdasarkan Customer Order'" />
        <x-grid.stat-box tone="active" label="Jumlah Dokumen"
            :value="number_format($this->periodTotal['docs'])"
            :subtitle="$month ? $months[$month] . ' ' . $year : 'Sepanjang ' . $year" />
    </x-grid.stat-row>

    {{-- Tab --}}
    <div class="flex gap-1 mb-3 border-b">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="px-3.5 py-2 text-[12px] font-semibold border-b-2 -mb-px
                           {{ $tab === $key ? 'border-[#2563eb] text-[#2563eb]' : 'border-transparent opacity-60 hover:opacity-100' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'dokumen')
        <div class="rounded-xl border bg-white shadow-sm overflow-hidden">
            <table class="w-full text-[11.5px]">
                <thead class="bg-black/[0.03]">
                    <tr class="text-left">
                        <th class="py-2 px-3">Nomor</th>
                        <th class="py-2 px-3">Tanggal</th>
                        <th class="py-2 px-3">Customer</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3 text-right">Nilai</th>
                        <th class="py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->documents as $doc)
                        <tr class="border-t border-black/5 hover:bg-black/[0.02]">
                            <td class="py-1.5 px-3 font-medium">{{ $doc->doc_no }}</td>
                            <td class="py-1.5 px-3">{{ $doc->doc_date }}</td>
                            <td class="py-1.5 px-3">
                                @if ($doc->customer_id)
                                    <a href="{{ route('report.customer', ['customer' => $doc->customer_id, 'th' => $year]) }}"
                                       class="text-[#2563eb] hover:underline">
                                        {{ $this->customerNames[$doc->customer_id] ?? '(tidak dikenal)' }}
                                    </a>
                                @else
                                    <span class="opacity-50">—</span>
                                @endif
                            </td>
                            <td class="py-1.5 px-3">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-black/5">
                                    {{ $doc->doc_status_normalized ?? '—' }}
                                </span>
                            </td>
                            <td class="py-1.5 px-3 text-right tabular-nums">{{ $rp($doc->grand_total) }}</td>
                            <td class="py-1.5 px-3 text-right">
                                @if ($doc->customer_id)
                                    <a href="{{ route('report.customer', ['customer' => $doc->customer_id, 'th' => $year]) }}"
                                       class="text-[#2563eb] hover:underline">telusuri →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center opacity-55">Tidak ada dokumen pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3 border-t">{{ $this->documents->links() }}</div>
        </div>

    @elseif ($tab === 'customer')
        <div class="rounded-xl border bg-white shadow-sm p-4">
            <x-chart.apex type="bar" :height="520" :config="[
                'series' => [['name' => 'Omset', 'data' => array_map(fn ($c) => round($c['value']), $this->customerRanking)]],
                'xaxis' => ['categories' => array_map(fn ($c) => str($c['name'])->limit(28)->value(), $this->customerRanking)],
                'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '70%']],
                'legend' => ['show' => false],
                'colors' => ['#2563eb'],
            ]" />
            <table class="w-full text-[11.5px] mt-3">
                <thead><tr class="text-left border-b">
                    <th class="py-1.5 px-2">#</th><th class="py-1.5 px-2">Customer</th>
                    <th class="py-1.5 px-2 text-right">Dokumen</th><th class="py-1.5 px-2 text-right">Nilai</th>
                </tr></thead>
                <tbody>
                    @foreach ($this->customerRanking as $i => $c)
                        <tr class="border-b border-black/5 hover:bg-black/[0.02]">
                            <td class="py-1 px-2 opacity-50">{{ $i + 1 }}</td>
                            <td class="py-1 px-2">
                                <a href="{{ route('report.customer', ['customer' => $c['id'], 'th' => $year]) }}"
                                   class="text-[#2563eb] hover:underline">{{ $c['name'] }}</a>
                            </td>
                            <td class="py-1 px-2 text-right tabular-nums">{{ number_format($c['docs']) }}</td>
                            <td class="py-1 px-2 text-right tabular-nums">{{ $rp($c['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @else
        <div class="rounded-xl border bg-white shadow-sm p-4">
            <x-chart.apex type="bar" :height="360" :config="[
                'series' => [['name' => 'Omset', 'data' => array_map(fn ($s) => round($s['value']), $this->salesRanking)]],
                'xaxis' => ['categories' => array_map(fn ($s) => $s['name'], $this->salesRanking)],
                'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '65%']],
                'legend' => ['show' => false],
                'colors' => ['#16a34a'],
            ]" />
            <table class="w-full text-[11.5px] mt-3">
                <thead><tr class="text-left border-b">
                    <th class="py-1.5 px-2">Salesperson</th><th class="py-1.5 px-2 text-right">CO</th>
                    <th class="py-1.5 px-2 text-right">Nilai</th><th class="py-1.5 px-2 text-right">Porsi</th>
                </tr></thead>
                <tbody>
                    @foreach ($this->salesRanking as $s)
                        <tr class="border-b border-black/5">
                            <td class="py-1 px-2 font-medium">{{ $s['name'] }}</td>
                            <td class="py-1 px-2 text-right tabular-nums">{{ number_format($s['docs']) }}</td>
                            <td class="py-1 px-2 text-right tabular-nums">{{ $rp($s['value']) }}</td>
                            <td class="py-1 px-2 text-right tabular-nums">{{ number_format($s['pct'], 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
