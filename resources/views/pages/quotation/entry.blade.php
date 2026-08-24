<?php

use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use App\Services\Sales\QuotationBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quotation Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $quotationId;

    public string $addInquirySearch = '';

    public function mount(int $quotation): void
    {
        $this->quotationId = $quotation;
    }

    #[Computed]
    public function quotation(): QuoQuotation
    {
        return QuoQuotation::with(['customer', 'items' => fn ($q) => $q->orderBy('line_seq')])->findOrFail($this->quotationId);
    }

    #[Computed]
    public function paymentTerms()
    {
        return FinPaymentTerm::where('is_active', true)->orderBy('sort_order')->get();
    }

    // Inquiry customer yang sama, costing sudah published, belum ditarik ke quotation ini.
    #[Computed]
    public function availableInquiries()
    {
        if (! $this->quotation->customer_id) {
            return collect();
        }

        $existingIds = $this->quotation->items->where('source_type', 'INQUIRY_TIER')->pluck('inquiry_id')->filter();

        $query = IqpInquiry::where('customer_id', $this->quotation->customer_id)
            ->whereHas('costing', fn ($q) => $q->where('published_flag', true))
            ->whereNotIn('id_inquiry', $existingIds);

        if ($this->addInquirySearch !== '') {
            $term = $this->addInquirySearch;
            $query->where(function ($q) use ($term) {
                $q->where('inquiry_no', 'like', "%{$term}%")
                    ->orWhere('product_draft_name', 'like', "%{$term}%");
            });
        }

        return $query->orderByDesc('inquiry_date')->limit(20)->get();
    }

    #[Computed]
    public function totals(): array
    {
        return $this->quotation->totals();
    }

    public function addInquiry(int $inquiryId): void
    {
        if ($this->quotation->doc_status !== 'DRAFT') {
            return;
        }

        $inquiry = IqpInquiry::findOrFail($inquiryId);
        QuotationBuilder::addInquiry($this->quotation, $inquiry);

        $this->addInquirySearch = '';
        unset($this->quotation, $this->availableInquiries);
    }

    public function addFreeform(): void
    {
        if ($this->quotation->doc_status !== 'DRAFT') {
            return;
        }

        $nextSeq = ((int) $this->quotation->items()->max('line_seq')) + 1;

        QuoQuotationItem::create([
            'quotation_id' => $this->quotationId,
            'line_seq' => $nextSeq,
            'source_type' => 'FREEFORM',
            'description' => '',
            'qty' => 1,
            'qty_unit' => 'Pcs',
            'unit_price' => 0,
            'discount_pct' => 0,
            'subtotal' => 0,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->quotation);
    }

    public function removeItem(int $itemId): void
    {
        if ($this->quotation->doc_status !== 'DRAFT') {
            return;
        }

        QuoQuotationItem::where('quotation_id', $this->quotationId)->whereKey($itemId)->delete();
        unset($this->quotation, $this->availableInquiries);
    }

    public function updateItem(int $itemId, string $field, $value): void
    {
        if ($this->quotation->doc_status !== 'DRAFT') {
            return;
        }

        $allowed = ['description', 'qty', 'qty_unit', 'unit_price', 'discount_pct'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $item = QuoQuotationItem::where('quotation_id', $this->quotationId)->findOrFail($itemId);
        $item->{$field} = $value === '' ? 0 : $value;
        $item->recalcSubtotal();
        $item->updated_by = Auth::user()->login;
        $item->save();

        unset($this->quotation);
    }

    public function updateHeader(string $field, $value): void
    {
        if ($this->quotation->doc_status !== 'DRAFT') {
            return;
        }

        $allowed = ['issued_date', 'valid_until', 'payment_term', 'tax_scheme', 'notes'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->quotation->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->quotation);
    }

    public function publish(): void
    {
        if ($this->quotation->doc_status !== 'DRAFT' || $this->quotation->items->isEmpty()) {
            return;
        }

        $this->quotation->update(['doc_status' => 'PUBLISHED', 'updated_by' => Auth::user()->login]);
        unset($this->quotation);
    }

    // "1 CO = 1 Quotation" (keputusan user 2026-08-20) -- lihat App\Services\Sales\CoOrderBuilder.
    public function createOrOpenCo()
    {
        $order = \App\Services\Sales\CoOrderBuilder::findOrCreateFromQuotation($this->quotation);

        if (! $order) {
            return;
        }

        return $this->redirect(route('co.entry', ['order' => $order->id_order]));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📄 Quotation Entry</b>
        <span class="text-xs text-white/75">{{ $this->quotation->quotation_no }} · {{ $this->quotation->customer?->customer_name }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full {{ $this->quotation->doc_status === 'PUBLISHED' ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $this->quotation->doc_status }}</span>
        {{-- Cetak cuma boleh setelah diterbitkan (PUBLISHED) -- instruksi user
             2026-08-24, dikunci juga di sisi mount() quotation/print.blade.php. --}}
        @if ($this->quotation->doc_status === 'PUBLISHED')
            <a href="{{ route('quotation.print', ['quotation' => $this->quotationId]) }}" target="_blank" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">🖨️ Preview Cetak</a>
        @endif
        <a href="{{ route('quotation.list') }}" title="Kembali ke Daftar Quotation"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    <div class="max-w-[1280px] mx-auto my-5 px-4">
        @if (session('error'))
            <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f]">⚠️ {{ session('error') }}</div>
        @endif
        {{-- flex, BUKAN class="grid" -- app.css .grid{} unlayered override
             grid-cols/lg:grid-cols manapun jadi 4 kolom sama rata (lihat catatan
             di co/entry.blade.php & docs/FORM_UI_STANDARD.md §2). --}}
        <div class="flex flex-col lg:flex-row gap-4">

            {{-- KIRI --}}
            <div class="lg:flex-1 min-w-0">
                {{-- HEADER --}}
                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-3">Header Quotation</h3>
                    <div class="grid">
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">No. Quotation</label>
                            <input readonly value="{{ $this->quotation->quotation_no }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tanggal</label>
                            <input type="date" value="{{ $this->quotation->issued_date?->format('Y-m-d') }}" @disabled($this->quotation->doc_status !== 'DRAFT')
                                wire:change="updateHeader('issued_date', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Berlaku s/d</label>
                            <input type="date" value="{{ $this->quotation->valid_until?->format('Y-m-d') }}" @disabled($this->quotation->doc_status !== 'DRAFT')
                                wire:change="updateHeader('valid_until', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Termin Pembayaran</label>
                            <select @disabled($this->quotation->doc_status !== 'DRAFT') wire:change="updateHeader('payment_term', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                <option value="">— pilih —</option>
                                @foreach ($this->paymentTerms as $pt)
                                    <option value="{{ $pt->payment_term_code }}" @selected($this->quotation->payment_term === $pt->payment_term_code)>{{ $pt->term_label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Customer</label>
                            <input readonly value="{{ $this->quotation->customer?->customer_name }} ({{ $this->quotation->customer?->customer_code }})" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Skema Pajak</label>
                            <select @disabled($this->quotation->doc_status !== 'DRAFT') wire:change="updateHeader('tax_scheme', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                <option value="EXCL" @selected($this->quotation->tax_scheme === 'EXCL')>Exclusive PPN 11%</option>
                                <option value="INCL" @selected($this->quotation->tax_scheme === 'INCL')>Inclusive PPN 11%</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Mata Uang</label>
                            <input readonly value="{{ $this->quotation->currency_code }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>
                    </div>
                </div>

                {{-- ITEM LIST --}}
                <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                        <h3 class="text-xs text-navy uppercase font-bold">Daftar Inquiry Terkonsolidasi</h3>
                        @if ($this->quotation->doc_status === 'DRAFT')
                            <div class="flex items-center gap-1.5" x-data="{ open: false }">
                                <button type="button" wire:click="addFreeform" class="text-[11px] font-bold text-navy bg-[#eef1f5] border border-dashed border-[#c7cfda] rounded px-2.5 py-1 cursor-pointer">＋ Baris Bebas</button>
                                <div class="relative">
                                    <button type="button" @click="open = !open" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Tarik Inquiry Lain</button>
                                    <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 top-full mt-1 w-80 bg-white border border-[#d7dbe2] rounded-md shadow-lg z-20 p-2">
                                        <input type="text" placeholder="Cari no. inquiry / nama produk..." wire:model.live.debounce.300ms="addInquirySearch" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs mb-1.5">
                                        <div class="max-h-64 overflow-y-auto">
                                            @forelse ($this->availableInquiries as $inq)
                                                <button type="button" wire:click="addInquiry({{ $inq->id_inquiry }})" @click="open = false"
                                                    class="w-full text-left px-2 py-1.5 rounded hover:bg-[#eef3fb] cursor-pointer">
                                                    <div class="text-[11px] font-bold text-accent">{{ $inq->inquiry_no }}</div>
                                                    <div class="text-[11px] text-navy">{{ $inq->product_draft_name }}</div>
                                                </button>
                                            @empty
                                                <div class="text-[11px] text-muted px-2 py-3 text-center">
                                                    @if ($this->quotation->customer_id)
                                                        Tidak ada inquiry published yang bisa ditarik.
                                                    @else
                                                        Quotation ini belum ada customer-nya.
                                                    @endif
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-[#f4f5f8]">
                                <th class="text-left px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Ref. Inquiry</th>
                                <th class="text-left px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Deskripsi & Spesifikasi</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Qty</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Harga Satuan</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Disc %</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Subtotal</th>
                                @if ($this->quotation->doc_status === 'DRAFT')
                                    <th class="px-2.5 py-2 border-b border-[#d7dbe2]"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->quotation->items as $item)
                                <tr class="border-b border-[#eef1f5]" wire:key="item-{{ $item->id_item }}">
                                    <td class="px-2.5 py-2 font-mono font-bold text-accent">{{ $item->inquiry?->inquiry_no ?? '—' }}</td>
                                    <td class="px-2.5 py-2">
                                        @if ($item->source_type === 'FREEFORM' && $this->quotation->doc_status === 'DRAFT')
                                            <input type="text" value="{{ $item->description }}" placeholder="Deskripsi baris (mis. jasa/ongkos)..."
                                                wire:change="updateItem({{ $item->id_item }}, 'description', $event.target.value)" class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs font-medium">
                                        @else
                                            <div class="font-medium text-navy">{{ $item->description }}</div>
                                        @endif
                                        @if ($item->spec_note)
                                            <div class="text-[10px] text-muted mt-0.5">{{ $item->spec_note }}</div>
                                        @endif
                                        @if ($item->source_type === 'INQUIRY_TOOLING')
                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-[#fff5e0] text-[#8a5a00]">TOOLING</span>
                                        @elseif ($item->source_type === 'FREEFORM')
                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-[#ede9fe] text-[#5b21b6]">BEBAS</span>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2 text-right">
                                        @if ($this->quotation->doc_status === 'DRAFT')
                                            <input type="number" step="0.01" value="{{ $item->qty }}" wire:change="updateItem({{ $item->id_item }}, 'qty', $event.target.value)" class="w-20 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right">
                                        @else
                                            {{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2 text-right">
                                        @if ($this->quotation->doc_status === 'DRAFT')
                                            <input type="number" step="0.01" value="{{ $item->unit_price }}" wire:change="updateItem({{ $item->id_item }}, 'unit_price', $event.target.value)" class="w-24 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right">
                                        @else
                                            {{ number_format($item->unit_price, 0, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2 text-right">
                                        @if ($this->quotation->doc_status === 'DRAFT')
                                            <input type="number" step="0.1" value="{{ $item->discount_pct }}" wire:change="updateItem({{ $item->id_item }}, 'discount_pct', $event.target.value)" class="w-14 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right">
                                        @else
                                            {{ number_format($item->discount_pct, 1) }}%
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2 text-right font-bold text-navy">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                    @if ($this->quotation->doc_status === 'DRAFT')
                                        <td class="px-2.5 py-2 text-center">
                                            <button type="button" wire:click="removeItem({{ $item->id_item }})" wire:confirm="Hapus baris ini?" class="text-red-500 hover:text-red-700 cursor-pointer">✕</button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-8 text-muted">Belum ada baris. Klik "＋ Tarik Inquiry Lain" untuk mulai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- KANAN --}}
            <div class="lg:w-[320px] lg:shrink-0">
                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-2.5">Summary &amp; Pajak</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Subtotal Produk &amp; Layanan</span><span>{{ number_format($this->totals['subtotal'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-semibold border-t border-[#eef1f5] pt-1"><span>Dasar Pengenaan Pajak (DPP)</span><span>{{ number_format($this->totals['dpp'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">PPN {{ number_format($this->totals['ppn_pct'], 0) }}%</span><span>{{ number_format($this->totals['ppn'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-bold text-accent border-t border-[#eef1f5] pt-1 text-[13px]"><span>Grand Total</span><span>{{ number_format($this->totals['grand'], 0, ',', '.') }}</span></div>
                    </div>
                </div>

                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Syarat &amp; Ketentuan / Catatan</label>
                    <textarea rows="4" @disabled($this->quotation->doc_status !== 'DRAFT')
                        wire:change="updateHeader('notes', $event.target.value)"
                        placeholder="Tuliskan syarat khusus yang tercetak di dokumen..."
                        class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->quotation->notes }}</textarea>
                </div>

                @if ($this->quotation->doc_status === 'DRAFT')
                    <button type="button" wire:click="publish" wire:confirm="Terbitkan quotation ini? Setelah terbit, isi tidak bisa diedit lagi."
                        @disabled($this->quotation->items->isEmpty())
                        class="w-full px-4 py-2.5 rounded text-xs font-bold bg-accent text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        Terbitkan Quotation
                    </button>
                @else
                    <div class="text-center text-xs text-[#1f8a4c] font-semibold bg-[#eaf7ee] border border-[#a9d9bb] rounded-md py-2.5 mb-2.5">✓ Sudah diterbitkan — isi terkunci</div>
                    <button type="button" wire:click="createOrOpenCo"
                        class="w-full px-4 py-2.5 rounded text-xs font-bold bg-[#22a06b] text-white cursor-pointer">
                        📦 Buat Customer Order
                    </button>
                @endif
            </div>

        </div>
    </div>
</div>
