<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Customer 360 -- seluruh riwayat satu customer dalam satu layar, memanfaatkan
// rantai dokumen level baris hasil migrasi (co_detail_id). Di sinilah nilai
// migrasi terasa: 1 baris order bisa ditelusuri ke Surat Jalan & Invoice-nya.
new #[Layout('layouts::report')] #[Title('Customer 360 — TELASIH MAKMUR ERP')] class extends Component
{
    public int $customer;

    #[Url(as: 'th')]
    public ?int $year = null;

    #[Url(as: 'co')]
    public ?int $openOrder = null;

    public function mount(int $customer): void
    {
        $this->customer = $customer;
    }

    #[Computed]
    public function profile(): ?object
    {
        return DB::connection('tm2mst')->table('cust_customer')
            ->where('id_customer', $this->customer)->first();
    }

    #[Computed]
    public function years(): array
    {
        $rows = DB::connection('tm2sdi')->select('
            SELECT DISTINCT YEAR(co_date) y FROM co_order_legacy
            WHERE customer_id = ? AND co_date IS NOT NULL ORDER BY y DESC', [$this->customer]);

        return array_map(fn ($r) => (int) $r->y, $rows);
    }

    #[Computed]
    public function lifetime(): array
    {
        $co = DB::connection('tm2sdi')->selectOne('
            SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v, MIN(co_date) first_date, MAX(co_date) last_date
            FROM co_order_legacy WHERE customer_id = ?', [$this->customer]);
        $sj = DB::connection('tm2sdi')->selectOne('
            SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM co_delivery_legacy WHERE customer_id = ?', [$this->customer]);
        $inv = DB::connection('tm2sdi')->selectOne('
            SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM co_invoice_legacy WHERE customer_id = ?', [$this->customer]);

        return [
            'co_docs' => (int) $co->docs, 'co_value' => (float) $co->v,
            'first_date' => $co->first_date, 'last_date' => $co->last_date,
            'sj_docs' => (int) $sj->docs, 'sj_value' => (float) $sj->v,
            'inv_docs' => (int) $inv->docs, 'inv_value' => (float) $inv->v,
        ];
    }

    /** Tren omset tahunan customer ini -- melihat tumbuh atau menyusut. */
    #[Computed]
    public function yearlyTrend(): array
    {
        $rows = DB::connection('tm2sdi')->select('
            SELECT YEAR(co_date) y, COALESCE(SUM(grand_total),0) v
            FROM co_order_legacy WHERE customer_id = ? AND co_date IS NOT NULL
            GROUP BY y ORDER BY y', [$this->customer]);

        return array_map(fn ($r) => ['year' => (int) $r->y, 'value' => (float) $r->v], $rows);
    }

    #[Computed]
    public function orders()
    {
        return DB::connection('tm2sdi')->table('co_order_legacy')
            ->where('customer_id', $this->customer)
            ->when($this->year, fn ($q) => $q->whereYear('co_date', $this->year))
            ->orderByDesc('co_date')
            ->limit(100)
            ->get();
    }

    /** Produk yang paling sering dipesan customer ini. */
    #[Computed]
    public function topProducts(): array
    {
        // Alias `lines` TIDAK boleh dipakai -- kata tercadang di MySQL 8.
        return DB::connection('tm2sdi')->select('
            SELECT COALESCE(d.legacy_item_code, "(tanpa kode)") item, COUNT(*) line_count,
                   COALESCE(SUM(d.qty),0) qty, COALESCE(SUM(d.qty_unitprice),0) v
            FROM co_order_legacy_detail d
            JOIN co_order_legacy h ON h.id_co_legacy = d.co_legacy_id
            WHERE h.customer_id = ?
            GROUP BY item ORDER BY v DESC LIMIT 10', [$this->customer]);
    }

    /** Rantai dokumen untuk satu CO yang dibuka: baris -> Surat Jalan & Invoice. */
    #[Computed]
    public function orderChain(): array
    {
        if (! $this->openOrder) {
            return [];
        }

        $lines = DB::connection('tm2sdi')->table('co_order_legacy_detail')
            ->where('co_legacy_id', $this->openOrder)->get();

        $out = [];
        foreach ($lines as $line) {
            $key = $line->legacy_co_detail_id;
            $deliveries = $key ? DB::connection('tm2sdi')->select('
                SELECT h.legacy_dnote_no no_dok, h.dnote_date tgl, d.qty_ship qty
                FROM co_delivery_legacy_detail d
                JOIN co_delivery_legacy h ON h.id_delivery_legacy = d.delivery_legacy_id
                WHERE d.legacy_co_detail_id = ? ORDER BY h.dnote_date', [$key]) : [];

            $invoices = $key ? DB::connection('tm2sdi')->select('
                SELECT h.legacy_cinv_no no_dok, h.cinv_date tgl, d.qty_inv qty, d.qty_unitprice nilai
                FROM co_invoice_legacy_detail d
                JOIN co_invoice_legacy h ON h.id_invoice_legacy = d.invoice_legacy_id
                WHERE d.legacy_co_detail_id = ? ORDER BY h.cinv_date', [$key]) : [];

            $out[] = ['line' => $line, 'deliveries' => $deliveries, 'invoices' => $invoices];
        }

        return $out;
    }

    public function toggleOrder(int $id): void
    {
        $this->openOrder = $this->openOrder === $id ? null : $id;
    }
}; ?>

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $statusTone = fn ($s) => match ($s) { 'CONFIRMED' => 'ok', 'CANCELLED' => 'danger', default => 'info' };
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('report.sales', ['th' => $year]) }}" class="text-[12px] font-semibold hover:underline" style="color:var(--sh-accent)">
            <i class="fa-solid fa-arrow-left"></i> Rincian Penjualan
        </a>
        <h2 class="font-bold text-[16px]" style="color:var(--sh-ink-strong)">{{ $this->profile->customer_name ?? 'Customer #' . $customer }}</h2>
        @if ($this->profile?->customer_code)
            <x-report.badge tone="info">{{ $this->profile->customer_code }}</x-report.badge>
        @endif

        <div class="ml-auto">
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                <option value="">Semua tahun</option>
                @foreach ($this->years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <x-report.kpi label="Total Nilai Order" icon="fa-solid fa-file-invoice" tone="blue"
            :value="$miliar($this->lifetime['co_value'])" :note="number_format($this->lifetime['co_docs']) . ' customer order'" />
        <x-report.kpi label="Total Dikirim" icon="fa-solid fa-truck-fast" tone="green"
            :value="$miliar($this->lifetime['sj_value'])" :note="number_format($this->lifetime['sj_docs']) . ' surat jalan'" />
        <x-report.kpi label="Total Ditagih" icon="fa-solid fa-receipt" tone="violet"
            :value="$miliar($this->lifetime['inv_value'])" :note="number_format($this->lifetime['inv_docs']) . ' invoice'" />
        <x-report.kpi label="Rentang Hubungan" icon="fa-solid fa-calendar-days" tone="amber"
            :value="($this->lifetime['first_date'] ? substr($this->lifetime['first_date'], 0, 4) : '—') . ' – ' . ($this->lifetime['last_date'] ? substr($this->lifetime['last_date'], 0, 4) : '—')"
            note="order pertama sampai terakhir" />
    </div>

    <div class="[display:grid] grid-cols-1 lg:grid-cols-2 gap-3.5 mb-4">
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h3 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Tren Order Tahunan</h3>
            <x-report.svg-bars :height="220" :labels="array_map(fn ($t) => (string) $t['year'], $this->yearlyTrend)" :series="[
                ['color' => 'var(--sh-accent)', 'data' => array_column($this->yearlyTrend, 'value')],
            ]" />
        </div>

        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h3 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Produk Terbanyak Dipesan</h3>
            <table class="w-full text-[12px]">
                <thead><tr class="text-left border-b" style="border-color:var(--sh-border)">
                    <th class="py-1.5 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Kode Item</th>
                    <th class="py-1.5 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Baris</th>
                    <th class="py-1.5 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Qty</th>
                    <th class="py-1.5 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Nilai</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->topProducts as $p)
                        <tr class="border-b" style="border-color:var(--sh-border)">
                            <td class="py-1.5 truncate max-w-[220px]" style="color:var(--sh-ink)">{{ $p->item }}</td>
                            <td class="py-1.5 text-right tabular-nums" style="color:var(--sh-ink)">{{ number_format($p->line_count) }}</td>
                            <td class="py-1.5 text-right tabular-nums" style="color:var(--sh-ink)">{{ number_format((float) $p->qty) }}</td>
                            <td class="py-1.5 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($p->v) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center" style="color:var(--sh-muted)">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Daftar order + rantai dokumennya --}}
    <div class="rounded-2xl border overflow-hidden" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <div class="px-4 py-3 border-b" style="border-color:var(--sh-border);background:var(--content-bg)">
            <h3 class="font-bold text-[14px]" style="color:var(--sh-ink-strong)">Riwayat Customer Order</h3>
            <p class="text-[11px] mt-0.5" style="color:var(--sh-muted)">Klik satu baris untuk melihat rantai dokumennya: order → surat jalan → invoice.</p>
        </div>
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left">
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">No. CO</th>
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Tanggal</th>
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">PO Customer</th>
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Sales</th>
                    <th class="py-2 px-3 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Status</th>
                    <th class="py-2 px-3 text-right font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $o)
                    <tr wire:click="toggleOrder({{ $o->id_co_legacy }})"
                        class="border-t cursor-pointer"
                        style="border-color:var(--sh-border);{{ $openOrder === $o->id_co_legacy ? 'background:color-mix(in srgb, var(--sh-accent) 8%, transparent)' : '' }}">
                        <td class="py-2 px-3 font-semibold" style="color:var(--sh-ink-strong)">{{ $o->legacy_co_no }}</td>
                        <td class="py-2 px-3" style="color:var(--sh-ink)">{{ $o->co_date }}</td>
                        <td class="py-2 px-3" style="color:var(--sh-muted)">{{ $o->customer_po_no ?: '—' }}</td>
                        <td class="py-2 px-3" style="color:var(--sh-muted)">{{ $o->sales_person ?: '—' }}</td>
                        <td class="py-2 px-3">
                            <x-report.badge :tone="$statusTone($o->doc_status_normalized)">{{ $o->doc_status_normalized }}</x-report.badge>
                        </td>
                        <td class="py-2 px-3 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($o->grand_total) }}</td>
                    </tr>

                    @if ($openOrder === $o->id_co_legacy)
                        <tr style="background:var(--content-bg)">
                            <td colspan="6" class="px-5 py-3">
                                @forelse ($this->orderChain as $chain)
                                    <div class="mb-3 pb-3 border-b last:border-0 last:mb-0 last:pb-0" style="border-color:var(--sh-border)">
                                        <div class="font-semibold text-[12.5px] mb-1.5" style="color:var(--sh-ink-strong)">
                                            {{ $chain['line']->legacy_item_code ?: '(tanpa kode item)' }}
                                            <span class="font-normal" style="color:var(--sh-muted)">
                                                — qty {{ number_format((float) $chain['line']->qty) }} {{ $chain['line']->uom_code }},
                                                {{ $rp($chain['line']->qty_unitprice) }}
                                            </span>
                                        </div>

                                        <div class="[display:grid] grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Surat Jalan</p>
                                                @forelse ($chain['deliveries'] as $d)
                                                    <div class="flex justify-between text-[11.5px] py-0.5" style="color:var(--sh-ink)">
                                                        <span>{{ $d->no_dok }} <span style="color:var(--sh-muted)">{{ $d->tgl }}</span></span>
                                                        <span class="tabular-nums">{{ number_format((float) $d->qty) }}</span>
                                                    </div>
                                                @empty
                                                    <p class="text-[11.5px]" style="color:var(--sh-muted)">Belum ada pengiriman.</p>
                                                @endforelse
                                            </div>
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Invoice</p>
                                                @forelse ($chain['invoices'] as $inv)
                                                    <div class="flex justify-between text-[11.5px] py-0.5" style="color:var(--sh-ink)">
                                                        <span>{{ $inv->no_dok }} <span style="color:var(--sh-muted)">{{ $inv->tgl }}</span></span>
                                                        <span class="tabular-nums">{{ $rp($inv->nilai) }}</span>
                                                    </div>
                                                @empty
                                                    <p class="text-[11.5px]" style="color:var(--sh-muted)">Belum ditagihkan.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-[12px] py-2" style="color:var(--sh-muted)">Order ini tidak punya baris item.</p>
                                @endforelse
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="py-8 text-center" style="color:var(--sh-muted)">Tidak ada order pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
