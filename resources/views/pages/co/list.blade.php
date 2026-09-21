<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\QuoQuotation;
use App\Services\Sales\CoOrderBuilder;
use App\Support\Localization\Translator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md §1-§6 + §9 (expand-row). Ditulis ulang 2026-09-21 atas
// instruksi user ("grid CO tidak seperti grid lainnya, buat sama"): chrome pilot
// PowerGrid/Filament (§10 Aksi-kiri + checkbox massal, §12 dropdown ⋮ + panel
// Filters + tombol Kolom/Sort/Export) DILEPAS dari layar ini supaya bentuknya
// identik dengan grid lain -- acuan bentuk: quotation/list.blade.php.
//
// "+ New Record" sekarang SAH ada di sini (sebelumnya sengaja tidak ada, diganti
// catatan pastel) karena CO mode INSTANT memang lahir dari grid ini. Tombolnya
// membuka pemilih mode dulu: INTEGRATED (pilih Quotation PUBLISHED, input
// minimal) atau INSTANT (pilih Customer, input manual + Master Product).
new #[Title('Daftar Customer Order — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    public bool $pickerOpen = false;

    public string $pickerMode = '';

    public string $pickerSearch = '';

    public array $expanded = [];

    // Nama customer ada di tm2mst, grid ini di tm2sdi -- whereHas() lintas-DB
    // bikin subquery di koneksi yang salah (CLAUDE.md §2 pola 3). Resolusi
    // 2 tahap: cari id_customer di tm2mst, lalu whereIn ke co_order.customer_id
    // (kolom baru 2026-09-21, dulu harus lewat quo_quotation dulu).
    #[Computed]
    public function orders()
    {
        return CoOrder::with(['customer', 'quotation', 'items', 'externalCosts'])
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $customerIds = CustCustomer::where('customer_name', 'like', "%{$term}%")->pluck('id_customer');

                $q->where(fn ($qq) => $qq->where('co_no', 'like', "%{$term}%")
                    ->orWhere('customer_po_no', 'like', "%{$term}%")
                    ->orWhereIn('customer_id', $customerIds));
            })
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderByDesc('id_order')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function grandTotals(): array
    {
        return $this->orders->mapWithKeys(fn ($o) => [$o->id_order => $o->totals()['net_receivable']])->all();
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

    public function toggleExpand(int $id): void
    {
        $this->expanded = in_array($id, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$id]))
            : [...$this->expanded, $id];
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
        $this->pickerMode = '';
        $this->pickerSearch = '';
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function chooseMode(string $mode): void
    {
        $this->pickerMode = in_array($mode, ['INTEGRATED', 'INSTANT'], true) ? $mode : '';
        $this->pickerSearch = '';
    }

    // Quotation yang layak jadi CO INTEGRATED: sudah PUBLISHED dan belum pernah
    // diserap CO mana pun ("1 CO = 1 Quotation", keputusan 2026-08-20).
    #[Computed]
    public function quotationChoices()
    {
        $taken = CoOrder::whereNotNull('quotation_id')->pluck('quotation_id');

        return QuoQuotation::with('customer')
            ->where('doc_status', 'PUBLISHED')
            ->whereNotIn('id_quotation', $taken)
            ->when($this->pickerSearch !== '', function ($q) {
                $customerIds = CustCustomer::where('customer_name', 'like', "%{$this->pickerSearch}%")->pluck('id_customer');
                $q->where(fn ($qq) => $qq->where('quotation_no', 'like', "%{$this->pickerSearch}%")->orWhereIn('customer_id', $customerIds));
            })
            ->orderByDesc('id_quotation')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function customerChoices()
    {
        if (mb_strlen(trim($this->pickerSearch)) < 2) {
            return collect();
        }

        return CustCustomer::where('activate', true)
            ->where(fn ($q) => $q->where('customer_name', 'like', "%{$this->pickerSearch}%")
                ->orWhere('customer_code', 'like', "%{$this->pickerSearch}%"))
            ->orderBy('customer_name')
            ->limit(15)
            ->get();
    }

    public function startIntegrated(int $quotationId)
    {
        $quotation = QuoQuotation::findOrFail($quotationId);
        $order = CoOrderBuilder::findOrCreateFromQuotation($quotation);

        if (! $order) {
            return null;
        }

        return $this->redirect(route('co.entry', ['order' => $order->id_order]));
    }

    public function startInstant(int $customerId)
    {
        $order = CoOrderBuilder::createInstant(CustCustomer::findOrFail($customerId));

        return $this->redirect(route('co.entry', ['order' => $order->id_order]));
    }

    public function openOrder(int $id)
    {
        return $this->redirect(route('co.entry', ['order' => $id]));
    }

    // Bukan master data -- tidak ada konsep aktif/nonaktif, jadi kolom Aksi
    // ganti "toggle" jadi CONFIRM (DRAFT -> CONFIRMED) langsung dari grid.
    // Guard sama dengan tombol Confirm di layar entry: CO INSTANT wajib punya
    // Master Product di tiap baris PRODUCT, semua baris PRODUCT wajib due_date.
    public function confirmOrder(int $id): void
    {
        // Lewat service, BUKAN flip status langsung -- sebelum 2026-09-21 grid ini
        // cuma mengubah doc_status sehingga ENGINE CPDEL dilewati diam-diam dan
        // CO yang di-confirm dari grid tidak pernah muncul di Customer Plan
        // Delivery.
        CoOrderBuilder::confirm(CoOrder::with('items.deliveries')->findOrFail($id));

        unset($this->orders);
    }

    // Kotak statistik = filter data (griduistandard.md §2b).
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => Translator::t('sales_co_stat_total', 'Total Customer Order'), 'value' => $this->statTotal, 'tone' => 'total'],
            'draft' => ['label' => Translator::t('sales_co_stat_draft', 'Draft'), 'value' => $this->statDraft, 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('doc_status', 'DRAFT')],
            'confirmed' => ['label' => Translator::t('sales_co_stat_confirmed', 'Confirmed'), 'value' => $this->statConfirmed, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('doc_status', 'CONFIRMED')],
        ];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('sales_co_search_placeholder', 'Cari no. CO / customer / no. PO…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="openPicker"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1] w-[32px]"></th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('sales_co_col_customer_order', 'Customer Order') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('sales_co_col_mode', 'Mode') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ Translator::t('sales_co_col_ref_quotation', 'Ref. Quotation') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('sales_co_col_date', 'Tanggal') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ Translator::t('sales_co_col_status', 'Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ Translator::t('sales_co_col_net_receivable', 'Net Receivable') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ Translator::t('sales_co_col_action', 'Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->orders as $o)
                            @php($isOpen = in_array($o->id_order, $this->expanded, true))
                            <tr wire:key="co-{{ $o->id_order }}" wire:dblclick="openOrder({{ $o->id_order }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ Translator::t('shared_grid_dblclick_hint', 'Klik 2x untuk buka') }}">
                                <td class="px-2 py-2 border-b border-[#eef1f5] text-center">
                                    <button type="button" wire:click.stop="toggleExpand({{ $o->id_order }})" title="{{ $isOpen ? Translator::t('sales_co_close_item_preview', 'Tutup preview item') : Translator::t('sales_co_view_item_preview', 'Lihat item') }}"
                                        class="inline-flex items-center justify-center w-6 h-6 rounded text-muted hover:bg-[#eef3fb] hover:text-accent cursor-pointer">
                                        <span class="inline-block transition-transform duration-150 {{ $isOpen ? 'rotate-90' : '' }}">›</span>
                                    </button>
                                </td>
                                <x-grid.identity-cell :id="$o->id_order" :name="$o->customer?->customer_name ?? '—'" :code="$o->co_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $o->entry_mode === 'INSTANT' ? 'bg-[#f1ecfc] text-[#6d28d9]' : 'bg-[#eaf2ff] text-[#1d4ed8]' }}">{{ $o->entry_mode }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono text-muted">{{ $o->quotation?->quotation_no ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $o->co_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $o->doc_status === 'CONFIRMED' ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $o->doc_status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($this->grandTotals[$o->id_order] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ Translator::t('shared_grid_action_open', 'Buka') }}" action="openOrder({{ $o->id_order }})" />
                                    @if ($o->doc_status === 'DRAFT' && $o->isConfirmable())
                                        <x-grid.action-icon icon="fa-check" title="{{ Translator::t('sales_form_btn_confirm', 'Confirm') }}" action="confirmOrder({{ $o->id_order }})"
                                            confirm="{{ Translator::t('sales_co_action_confirm_confirm', 'Confirm CO') }} {{ $o->co_no }}?" variant="success" />
                                    @endif
                                </td>
                            </tr>
                            @if ($isOpen)
                                <tr wire:key="co-detail-{{ $o->id_order }}" class="bg-[#fafbfd]">
                                    <td colspan="8" class="px-3 py-2.5 border-b border-[#eef1f5]">
                                        <table class="w-full text-[11.5px] border-collapse ml-8" style="width:calc(100% - 2rem)">
                                            <thead>
                                                <tr class="text-[10px] text-muted uppercase font-bold">
                                                    <th class="text-left px-2 py-1">{{ Translator::t('sales_co_detail_col_product_service', 'Produk / Jasa') }}</th>
                                                    <th class="text-center px-2 py-1 w-14">{{ Translator::t('sales_co_detail_col_type', 'Type') }}</th>
                                                    <th class="text-right px-2 py-1 w-28">{{ Translator::t('sales_co_detail_col_qty', 'Qty') }}</th>
                                                    <th class="text-right px-2 py-1 w-24">{{ Translator::t('sales_co_detail_col_price', 'Harga') }}</th>
                                                    <th class="text-right px-2 py-1 w-16">{{ Translator::t('sales_co_detail_col_disc', 'Disc%') }}</th>
                                                    <th class="text-right px-2 py-1 w-28">{{ Translator::t('sales_co_detail_col_subtotal', 'Subtotal') }}</th>
                                                    <th class="text-center px-2 py-1 w-24">{{ Translator::t('sales_co_detail_col_due_date', 'Due Date') }}</th>
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
                                                    <tr><td colspan="7" class="text-center py-3 text-muted text-[11px]">{{ Translator::t('sales_co_detail_no_items', 'Belum ada item.') }}</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>

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
                            <x-grid.empty-row :colspan="8" message="{{ Translator::t('sales_co_empty', 'Belum ada Customer Order.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->orders])
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[560px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">
                        {{ $pickerMode === '' ? Translator::t('sales_co_picker_title_mode', 'Customer Order Baru — Pilih Mode') : ($pickerMode === 'INTEGRATED' ? Translator::t('sales_co_picker_title_integrated', 'Mode Integrated — Pilih Quotation') : Translator::t('sales_co_picker_title_instant', 'Mode Instant — Pilih Customer')) }}
                    </h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>

                @if ($pickerMode === '')
                    <div class="flex flex-col gap-2.5">
                        <button type="button" wire:click="chooseMode('INTEGRATED')"
                            class="text-left px-3.5 py-3 rounded-lg border border-[#c8ddfb] bg-[#eaf2ff] hover:border-accent cursor-pointer">
                            <div class="text-[13px] font-bold text-[#1d4ed8]">🔗 INTEGRATED</div>
                            <div class="text-[11px] text-[#3b5b99] mt-0.5 leading-relaxed">
                                {{ Translator::t('sales_co_picker_integrated_desc', 'Tarik dari Sales Quotation yang sudah PUBLISHED. Header, item, harga, dan Master Product sudah lengkap — input user paling minimal.') }}
                            </div>
                        </button>
                        <button type="button" wire:click="chooseMode('INSTANT')"
                            class="text-left px-3.5 py-3 rounded-lg border border-[#ddd0f7] bg-[#f1ecfc] hover:border-[#6d28d9] cursor-pointer">
                            <div class="text-[13px] font-bold text-[#6d28d9]">⚡ INSTANT</div>
                            <div class="text-[11px] text-[#6b4fa0] mt-0.5 leading-relaxed">
                                {{ Translator::t('sales_co_picker_instant_desc', 'Tanpa lewat Inquiry/Quotation — untuk order verbal & kompatibilitas cara lama. Tiap baris WAJIB dipilih dari Master Product (buat Master Product-nya dulu kalau belum ada).') }}
                            </div>
                        </button>
                    </div>
                @elseif ($pickerMode === 'INTEGRATED')
                    <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="pickerSearch" autofocus
                        placeholder="{{ Translator::t('sales_co_picker_quotation_search', 'Cari no. quotation / nama customer...') }}" class="mb-2" />
                    <div class="max-h-72 overflow-y-auto tm-scroll">
                        @forelse ($this->quotationChoices as $q)
                            <button type="button" wire:click="startIntegrated({{ $q->id_quotation }})"
                                class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                                <div class="text-xs font-semibold text-navy">{{ $q->customer?->customer_name ?? '—' }}</div>
                                <div class="text-[10.5px] text-muted font-mono">{{ $q->quotation_no }} · {{ $q->issued_date?->translatedFormat('d M Y') }}</div>
                            </button>
                        @empty
                            <div class="text-[11px] text-muted text-center py-4">
                                {{ Translator::t('sales_co_picker_no_quotation', 'Tidak ada Quotation PUBLISHED yang belum punya CO.') }}
                            </div>
                        @endforelse
                    </div>
                @else
                    <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="pickerSearch" autofocus
                        placeholder="{{ Translator::t('sales_co_picker_customer_search', 'Ketik min. 2 huruf nama/kode customer...') }}" class="mb-2" />
                    <div class="max-h-72 overflow-y-auto tm-scroll">
                        @forelse ($this->customerChoices as $c)
                            <button type="button" wire:click="startInstant({{ $c->id_customer }})"
                                class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                                <div class="text-xs font-semibold text-navy">{{ $c->customer_name }}</div>
                                <div class="text-[10.5px] text-muted">{{ $c->customer_code }}</div>
                            </button>
                        @empty
                            <div class="text-[11px] text-muted text-center py-4">
                                {{ mb_strlen(trim($pickerSearch)) < 2 ? Translator::t('sales_co_picker_min_chars', 'Ketik min. 2 huruf untuk cari.') : Translator::t('sales_co_picker_no_match', 'Tidak ada customer cocok.') }}
                            </div>
                        @endforelse
                    </div>
                @endif

                @if ($pickerMode !== '')
                    <div class="mt-3 pt-2.5 border-t border-[#eef1f5]">
                        <button type="button" wire:click="chooseMode('')" class="text-[11px] text-muted hover:text-navy cursor-pointer">← Back</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
