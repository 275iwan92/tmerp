<?php

use App\Livewire\Concerns\HasCustomizableGrid;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderExternalCost;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\QuoQuotation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti (2026-08-22, diperluas ke grid worklist/transaksi).
// Tidak ada "+ New Record" -- CO lahir dari Quotation yang sudah PUBLISHED
// (tombol "Buat Customer Order" di layar Quotation), bukan dibuat dari grid ini.
// Kolom Aksi non-master: bukan Edit+Toggle-aktif (§5), tapi ↗ Buka + ✓ Confirm
// (DRAFT->CONFIRMED langsung dari grid, sesuai instruksi user 2026-08-22).
//
// Rollout ke-2 fasilitas grid custom view/sort/group-by (griduistandard.md
// §10, keputusan user 2026-08-27) -- Status & Net Receivable TETAP kolom
// tetap (anchor finansial grid Sales, tak ikut disembunyikan), yang
// dikustomisasi cuma kolom sekunder. "Ref. Quotation" TIDAK sortable
// (nilainya dari relasi quotation.quotation_no, bukan kolom co_order
// langsung -- orderBy() SQL tak bisa menjangkaunya tanpa join).
new #[Title('Daftar Customer Order — TELASIH MAKMUR ERP')] class extends Component
{
    use HasCustomizableGrid;
    use WithPagination;

    protected function gridKey(): string
    {
        return 'sales.co-list';
    }

    protected function gridColumnDefs(): array
    {
        return [
            'quotation.quotation_no' => ['label' => 'Ref. Quotation', 'sortable' => false],
            'co_date' => ['label' => 'Tanggal'],
            'customer_po_no' => ['label' => 'No. PO Customer'],
            'ship_address_label' => ['label' => 'Label Alamat Kirim'],
            'discount_doc_pct' => ['label' => 'Diskon Dokumen %'],
            'discount_qty_pct' => ['label' => 'Diskon Qty %'],
            'notes' => ['label' => 'Catatan'],
            'created_by' => ['label' => 'Dibuat Oleh'],
            'updated_by' => ['label' => 'Diubah Oleh'],
        ];
    }

    protected function gridDefaultColumns(): array
    {
        return ['quotation.quotation_no', 'co_date', 'customer_po_no'];
    }

    protected function gridBaseQuery()
    {
        return CoOrder::with(['quotation.customer', 'items' => fn ($q) => $q->orderBy('line_seq'), 'externalCosts']);
    }

    protected function gridIdentitySearchColumns(): array
    {
        return ['co_no' => 'No. CO', 'quotation.customer.customer_name' => 'Nama Customer'];
    }

    // 'quotation.customer.customer_name' melintas tm2sdi (co_order/quo_quotation)
    // -> tm2mst (cust_customer) -- whereHas() bertingkat TAK BISA dipakai
    // (Eloquent bikin 1 EXISTS subquery nested di koneksi yg sama, error
    // "table doesn't exist" begitu nyentuh cust_customer). Resolusi manual
    // 2-tahap sesuai CLAUDE.md §2 ("query terpisah, cocokkan di PHP"): cari
    // id_customer yg cocok (tm2mst) -> id_quotation yg customer_id-nya masuk
    // situ (tm2sdi) -> baru filter co_order.quotation_id.
    protected function gridCrossDbSearchResolvers(): array
    {
        return [
            'quotation.customer.customer_name' => function (string $operator, string $pattern) {
                $customerIds = $operator === '='
                    ? CustCustomer::whereRaw('CAST(customer_name AS CHAR) = ?', [$pattern])->pluck('id_customer')
                    : CustCustomer::where('customer_name', $operator, $pattern)->pluck('id_customer');

                if ($customerIds->isEmpty()) {
                    return [];
                }

                $quotationIds = QuoQuotation::whereIn('customer_id', $customerIds)->pluck('id_quotation');

                if ($quotationIds->isEmpty()) {
                    return [];
                }

                return CoOrder::whereIn('quotation_id', $quotationIds)->pluck('id_order')->all();
            },
        ];
    }

    public function columnValue($row, string $key): string
    {
        return match ($key) {
            'quotation.quotation_no' => $row->quotation?->quotation_no ?? '—',
            'co_date' => $row->co_date?->translatedFormat('d M Y') ?? '—',
            'discount_doc_pct', 'discount_qty_pct' => data_get($row, $key) !== null ? number_format((float) data_get($row, $key), 1).'%' : '—',
            default => (string) (data_get($row, $key) ?: '—'),
        };
    }

    // Master-detail expand-row (2026-08-24, instruksi user): klik chevron buka
    // preview item CO tanpa pindah halaman -- meniru ScriptCase report lama
    // (row-expand) + pola Odoo/SAP/Dynamics (list-detail inline). $expanded
    // simpan id_order yang lagi kebuka, bukan single value, krn user boleh
    // expand beberapa baris sekaligus. Diperluas 2026-08-29 (permintaan user):
    // preview sekarang ikut nampilin External Cost (sebelumnya cuma Product/
    // Service items), krn External Cost jg masuk hitungan Net Receivable
    // (CoOrder::totals()) -- dummy data uji di migration
    // 2026_08_29_000001_seed_co_external_cost_dummy.php.
    public array $expanded = [];

    public function toggleExpand(int $id): void
    {
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$id]));

            return;
        }

        $this->expanded[] = $id;
    }

    // grid_performance (globalrules.yaml): server-side pagination + WHERE.
    #[Computed]
    public function orders()
    {
        return $this->gridSortedQuery()
            ->when(! $this->gridSortColumn, fn ($q) => $q->orderByDesc('id_order'))
            ->paginate($this->perPage);
    }

    // Hapus massal (keputusan user 2026-08-27, pilot Aksi-kiri): HANYA CO
    // berstatus DRAFT boleh dicentang & dihapus -- guard di level query juga.
    public function bulkDelete(): void
    {
        $ids = CoOrder::whereIn('id_order', $this->selectedRows)->where('doc_status', 'DRAFT')->pluck('id_order');
        CoOrderItem::whereIn('order_id', $ids)->delete();
        CoOrderExternalCost::whereIn('order_id', $ids)->delete();
        CoOrder::whereKey($ids)->delete();

        $this->clearSelection();
        unset($this->orders);
    }

    #[Computed]
    public function grandTotals(): array
    {
        $ids = $this->orders->pluck('id_order');
        $orders = CoOrder::with('items', 'externalCosts')->whereIn('id_order', $ids)->get();

        return $orders->mapWithKeys(fn ($o) => [$o->id_order => $o->totals()['net_receivable']])->all();
    }

    #[Computed]
    public function statTotal(): int
    {
        return CoOrder::count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return CoOrder::where('doc_status', 'DRAFT')->count();
    }

    #[Computed]
    public function statConfirmed(): int
    {
        return CoOrder::where('doc_status', 'CONFIRMED')->count();
    }

    public function openOrder(int $id)
    {
        return $this->redirect(route('co.entry', ['order' => $id]));
    }

    // Bukan master data -- tidak ada konsep aktif/nonaktif, jadi kolom Aksi
    // ganti "toggle" jadi CONFIRM (DRAFT -> CONFIRMED) langsung dari grid.
    public function confirmOrder(int $id): void
    {
        $o = CoOrder::with('items')->findOrFail($id);
        if ($o->doc_status !== 'DRAFT' || $o->items->isEmpty()) {
            return;
        }

        $o->doc_status = 'CONFIRMED';
        $o->updated_by = Auth::user()->login;
        $o->save();

        unset($this->orders);
    }

    // Rekonsiliasi component grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        {{-- "grid three" bukan grid-cols-3 -- app.css .grid{} unlayered override
             grid-cols-3 Tailwind jadi 4 kolom, lihat catatan di co/entry.blade.php --}}
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total Customer Order" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="Draft" :value="$this->statDraft" />
            <x-grid.stat-box tone="active" label="Confirmed" :value="$this->statConfirmed" />
        </x-grid.stat-row>

        <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fef6e7] border border-[#f5e0b3] text-[#8a4b00]">
            CO dibuat dari Quotation yang sudah <b>PUBLISHED</b> — buka Quotation, klik "Buat Customer Order". Tidak ada "+ New Record" di sini.
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            {{-- Pilot chrome ala PowerGrid (2026-08-30, permintaan user) -- search
                 polos + tombol "Filters" terpisah (bukan caret di search box),
                 panel filter jadi baris penuh di bawah toolbar. HANYA grid ini. --}}
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. CO / customer…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    @include('components.grid.filters-panel')
                    <x-grid.toolbar-buttons />
                </x-slot:tools>
            </x-grid.toolbar>
            @include('components.grid.filters-row', ['columnDefs' => $this->gridSearchColumnDefs()])

            @if (count($selectedRows))
                <div class="flex items-center gap-2.5 bg-[#fdecec] border-b border-[#f5c6c6] px-3.5 py-2 text-[12px]">
                    <span class="font-semibold text-[#a3453f]">{{ count($selectedRows) }} CO draft dipilih</span>
                    <button type="button" wire:click="bulkDelete" wire:confirm="Hapus permanen {{ count($selectedRows) }} Customer Order draft terpilih beserta item-nya? Tindakan ini tidak bisa dibatalkan."
                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-[#c0392b] text-white cursor-pointer">🗑 Hapus</button>
                    <button type="button" wire:click="clearSelection" class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-white border border-[#f5c6c6] text-[#a3453f] cursor-pointer">Batal</button>
                </div>
            @endif

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1] w-[32px]"></th>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]"></th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Customer Order</th>
                            @foreach ($gridVisibleColumns as $key)
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] whitespace-nowrap">
                                    @if (($this->gridColumnDefs()[$key]['sortable'] ?? true) === false)
                                        {{ $this->gridColumnDefs()[$key]['label'] }}
                                    @else
                                        <button type="button" wire:click="toggleGridSort('{{ $key }}')"
                                            class="inline-flex items-center gap-1 cursor-pointer hover:text-navy {{ $gridSortColumn === $key ? 'text-accent' : '' }}">
                                            {{ $this->gridColumnDefs()[$key]['label'] }}
                                            <span class="text-[9px] {{ $gridSortColumn === $key ? 'text-accent' : 'opacity-40' }}">{{ match (true) {
                                                $gridSortColumn === $key && $gridSortDirection === 'asc' => '▲',
                                                $gridSortColumn === $key && $gridSortDirection === 'desc' => '▼',
                                                default => '▲',
                                            } }}</span>
                                        </button>
                                    @endif
                                </th>
                            @endforeach
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Net Receivable</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $colspan = count($gridVisibleColumns) + 5; @endphp
                        @forelse ($this->orders as $o)
                            @if ($gridGroupBy && $loop->first)
                                @php $renderedGroups = []; @endphp
                            @endif
                            @if ($gridGroupBy)
                                @php $groupVal = $this->columnValue($o, $gridGroupBy); @endphp
                                @if (! in_array($groupVal, $renderedGroups, true))
                                    @php $renderedGroups[] = $groupVal; @endphp
                                    <x-grid.group-header-row :label="$this->gridColumnDefs()[$gridGroupBy]['label']" :value="$groupVal" :colspan="$colspan" />
                                @endif
                            @endif
                            @php($isOpen = in_array($o->id_order, $this->expanded, true))
                            <tr wire:key="co-{{ $o->id_order }}" wire:dblclick="openOrder({{ $o->id_order }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-2 py-2 border-b border-[#eef1f5] text-center">
                                    <button type="button" wire:click.stop="toggleExpand({{ $o->id_order }})" title="{{ $isOpen ? 'Tutup preview item' : 'Lihat item' }}"
                                        class="inline-flex items-center justify-center w-6 h-6 rounded text-muted hover:bg-[#eef3fb] hover:text-accent cursor-pointer">{{ $isOpen ? '▾' : '▸' }}</button>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        @if ($o->doc_status === 'DRAFT')
                                            <input type="checkbox" wire:click.stop="toggleRowSelection({{ $o->id_order }})" @checked(in_array($o->id_order, $selectedRows, true))
                                                title="Pilih untuk hapus massal (draft)" class="w-3.5 h-3.5 cursor-pointer mr-0.5">
                                        @else
                                            <span class="w-3.5 inline-block shrink-0"></span>
                                        @endif
                                        <x-grid.action-icon icon="↗" title="Buka Customer Order ini" action="openOrder({{ $o->id_order }})" :bordered="true" />
                                        @if ($o->doc_status === 'DRAFT')
                                            <x-grid.action-icon icon="✓" title="Confirm CO ini (DRAFT → CONFIRMED)" action="confirmOrder({{ $o->id_order }})"
                                                confirm="Confirm CO {{ $o->co_no }}?" variant="success" :bordered="true" />
                                        @endif
                                    </div>
                                </td>
                                <x-grid.identity-cell :id="$o->id_order" :name="$o->quotation?->customer?->customer_name ?? '—'" :code="$o->co_no" />
                                @foreach ($gridVisibleColumns as $key)
                                    <td class="px-3 py-2 border-b border-[#eef1f5] {{ in_array($key, ['discount_doc_pct', 'discount_qty_pct'], true) ? 'text-right' : '' }} {{ $key === 'quotation.quotation_no' ? 'font-mono text-muted' : '' }}">{{ $this->columnValue($o, $key) }}</td>
                                @endforeach
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $o->doc_status === 'CONFIRMED' ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $o->doc_status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($this->grandTotals[$o->id_order] ?? 0, 0, ',', '.') }}</td>
                            </tr>
                            @if ($isOpen)
                                <tr wire:key="co-detail-{{ $o->id_order }}" class="bg-[#fafbfd]">
                                    <td colspan="{{ $colspan }}" class="px-3 py-2.5 border-b border-[#eef1f5]">
                                        <table class="w-full text-[11.5px] border-collapse ml-8" style="width:calc(100% - 2rem)">
                                            <thead>
                                                <tr class="text-[10px] text-muted uppercase font-bold">
                                                    <th class="text-left px-2 py-1">Produk / Jasa</th>
                                                    <th class="text-center px-2 py-1 w-14">Type</th>
                                                    <th class="text-right px-2 py-1 w-28">Qty</th>
                                                    <th class="text-right px-2 py-1 w-24">Harga</th>
                                                    <th class="text-right px-2 py-1 w-16">Disc%</th>
                                                    <th class="text-right px-2 py-1 w-28">Subtotal</th>
                                                    <th class="text-center px-2 py-1 w-24">Due Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($o->items as $item)
                                                    <tr class="border-t border-[#eef1f5]" wire:key="co-detail-item-{{ $item->id_item }}">
                                                        <td class="px-2 py-1.5">{{ $item->description }}</td>
                                                        <td class="px-2 py-1.5 text-center">
                                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $item->line_kind === 'PRODUCT' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $item->line_kind === 'PRODUCT' ? 'P' : 'S' }}</span>
                                                        </td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}</td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($item->discount_pct, 1) }}%</td>
                                                        <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                                        <td class="px-2 py-1.5 text-center text-muted">{{ $item->due_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="7" class="text-center py-3 text-muted text-[11px]">Belum ada item.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>

                                        {{-- External Cost (2026-08-29, permintaan user) -- sebelumnya preview
                                             expand-row cuma tampilkan Product/Service items, padahal External
                                             Cost (trucking/ekspedisi dll) ikut masuk hitungan Net Receivable
                                             (lihat CoOrder::totals()) -- ditambah supaya preview-nya lengkap. --}}
                                        @if ($o->externalCosts->isNotEmpty())
                                            <table class="w-full text-[11.5px] border-collapse ml-8 mt-2.5" style="width:calc(100% - 2rem)">
                                                <thead>
                                                    <tr class="text-[10px] text-muted uppercase font-bold">
                                                        <th class="text-left px-2 py-1">External Cost</th>
                                                        <th class="text-left px-2 py-1">Description</th>
                                                        <th class="text-right px-2 py-1 w-28">Nominal</th>
                                                        <th class="text-center px-2 py-1 w-32">Tax Treatment</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($o->externalCosts as $cost)
                                                        <tr class="border-t border-[#eef1f5]" wire:key="co-detail-cost-{{ $cost->id_cost }}">
                                                            <td class="px-2 py-1.5">{{ $cost->category }}</td>
                                                            <td class="px-2 py-1.5 text-muted">{{ $cost->description ?: '—' }}</td>
                                                            <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($cost->nominal, 0, ',', '.') }}</td>
                                                            <td class="px-2 py-1.5 text-center">
                                                                <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $cost->tax_treatment === 'TAXABLE' ? 'bg-[#fff5e0] text-[#8a5a00]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $cost->tax_treatment }}</span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <x-grid.empty-row :colspan="$colspan" message="Belum ada Customer Order." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->orders])
        </div>
    </div>

    @include('components.grid.column-modal', ['columnDefs' => $this->gridColumnDefs()])
    @include('components.grid.sort-modal', ['columnDefs' => $this->gridColumnDefs()])
    @include('components.grid.export-modal', ['columnDefs' => $this->gridColumnDefs()])
</div>
