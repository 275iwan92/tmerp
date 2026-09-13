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
    $statusTone = fn ($s) => match ($s) { 'CONFIRMED', 'SHIPPED', 'PAID' => 'ok', 'CANCELLED' => 'danger', 'DRAFT' => 'info', default => 'warn' };
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <a href="{{ route('report.executive', ['th' => $year]) }}"
           class="text-[12px] font-semibold hover:underline pb-1.5" style="color:var(--sh-accent)">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Tahun</label>
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                @foreach ($this->years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Bulan</label>
            <select wire:model.live="month" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                <option value="">Semua bulan</option>
                @foreach ($months as $n => $nama)
                    <option value="{{ $n }}">{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Dasar</label>
            <select wire:model.live="basis" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                <option value="order">PO Masuk (Customer Order)</option>
                <option value="shipment">Shipment (Surat Jalan)</option>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Cari</label>
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="Nomor dokumen atau nama customer…"
                   class="w-full border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
        </div>
    </div>

    <div class="[display:grid] grid-cols-2 gap-3.5 mb-4">
        <x-report.kpi label="Nilai Periode Terpilih" icon="fa-solid fa-coins" tone="blue"
            :value="$miliar($this->periodTotal['value'])"
            :note="$basis === 'shipment' ? 'berdasarkan Surat Jalan' : 'berdasarkan Customer Order'" />
        <x-report.kpi label="Jumlah Dokumen" icon="fa-solid fa-file-lines" tone="green"
            :value="number_format($this->periodTotal['docs'])"
            :note="$month ? $months[$month] . ' ' . $year : 'Sepanjang ' . $year" />
    </div>

    {{-- Tab --}}
    <div class="flex gap-1 mb-3 border-b" style="border-color:var(--sh-border)">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="px-3.5 py-2 text-[12px] font-semibold border-b-2 -mb-px"
                    style="{{ $tab === $key ? 'border-color:var(--sh-accent);color:var(--sh-accent)' : 'border-color:transparent;color:var(--sh-muted)' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'dokumen')
        <div class="rounded-2xl border overflow-hidden" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <table class="w-full text-[12px]">
                <thead style="background:var(--content-bg)">
                    <tr class="text-left">
                        <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Nomor</th>
                        <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Tanggal</th>
                        <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Customer</th>
                        <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Status</th>
                        <th class="py-2 px-3 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Nilai</th>
                        <th class="py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->documents as $doc)
                        <tr class="border-t" style="border-color:var(--sh-border)">
                            <td class="py-2 px-3 font-semibold" style="color:var(--sh-ink-strong)">{{ $doc->doc_no }}</td>
                            <td class="py-2 px-3" style="color:var(--sh-ink)">{{ $doc->doc_date }}</td>
                            <td class="py-2 px-3">
                                @if ($doc->customer_id)
                                    <a href="{{ route('report.customer', ['customer' => $doc->customer_id, 'th' => $year]) }}"
                                       class="hover:underline" style="color:var(--sh-accent)">
                                        {{ $this->customerNames[$doc->customer_id] ?? '(tidak dikenal)' }}
                                    </a>
                                @else
                                    <span style="color:var(--sh-muted)">—</span>
                                @endif
                            </td>
                            <td class="py-2 px-3">
                                <x-report.badge :tone="$statusTone($doc->doc_status_normalized ?? '')">{{ $doc->doc_status_normalized ?? '—' }}</x-report.badge>
                            </td>
                            <td class="py-2 px-3 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($doc->grand_total) }}</td>
                            <td class="py-2 px-3 text-right">
                                @if ($doc->customer_id)
                                    <a href="{{ route('report.customer', ['customer' => $doc->customer_id, 'th' => $year]) }}"
                                       class="hover:underline" style="color:var(--sh-accent)">telusuri →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center" style="color:var(--sh-muted)">Tidak ada dokumen pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3 border-t" style="border-color:var(--sh-border)">{{ $this->documents->links() }}</div>
        </div>

    @elseif ($tab === 'customer')
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <x-report.svg-bars horizontal :labels="array_map(fn ($c) => $c['name'], $this->customerRanking)" :series="[
                ['data' => array_map(fn ($c) => $c['value'], $this->customerRanking), 'color' => 'var(--sh-accent)'],
            ]" />
            <table class="w-full text-[12px] mt-4">
                <thead><tr class="text-left border-b" style="border-color:var(--sh-border)">
                    <th class="py-1.5 px-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">#</th>
                    <th class="py-1.5 px-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Customer</th>
                    <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Dokumen</th>
                    <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Nilai</th>
                </tr></thead>
                <tbody>
                    @foreach ($this->customerRanking as $i => $c)
                        <tr class="border-b" style="border-color:var(--sh-border)">
                            <td class="py-1.5 px-2" style="color:var(--sh-muted)">{{ $i + 1 }}</td>
                            <td class="py-1.5 px-2">
                                <a href="{{ route('report.customer', ['customer' => $c['id'], 'th' => $year]) }}"
                                   class="hover:underline font-medium" style="color:var(--sh-accent)">{{ $c['name'] }}</a>
                            </td>
                            <td class="py-1.5 px-2 text-right tabular-nums" style="color:var(--sh-ink)">{{ number_format($c['docs']) }}</td>
                            <td class="py-1.5 px-2 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($c['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @else
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <x-report.svg-bars horizontal :labels="array_map(fn ($s) => $s['name'], $this->salesRanking)" :series="[
                ['data' => array_map(fn ($s) => $s['value'], $this->salesRanking), 'color' => 'var(--sh-ok)'],
            ]" />
            <table class="w-full text-[12px] mt-4">
                <thead><tr class="text-left border-b" style="border-color:var(--sh-border)">
                    <th class="py-1.5 px-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Salesperson</th>
                    <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">CO</th>
                    <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Nilai</th>
                    <th class="py-1.5 px-2 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Porsi</th>
                </tr></thead>
                <tbody>
                    @foreach ($this->salesRanking as $s)
                        <tr class="border-b" style="border-color:var(--sh-border)">
                            <td class="py-1.5 px-2 font-medium" style="color:var(--sh-ink-strong)">{{ $s['name'] }}</td>
                            <td class="py-1.5 px-2 text-right tabular-nums" style="color:var(--sh-ink)">{{ number_format($s['docs']) }}</td>
                            <td class="py-1.5 px-2 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($s['value']) }}</td>
                            <td class="py-1.5 px-2 text-right tabular-nums" style="color:var(--sh-ink)">{{ number_format($s['pct'], 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
