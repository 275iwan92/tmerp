<?php

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderExternalCost;
use App\Models\Sdi\CoOrderItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Customer Order — Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $orderId;

    public function mount(int $order): void
    {
        $this->orderId = $order;
    }

    #[Computed]
    public function order(): CoOrder
    {
        return CoOrder::with([
            'quotation.customer',
            'items' => fn ($q) => $q->orderBy('line_seq'),
            'items.inquiry',
            'items.deliveries',
            'externalCosts',
        ])->findOrFail($this->orderId);
    }

    #[Computed]
    public function totals(): array
    {
        return $this->order->totals();
    }

    #[Computed]
    public function orderType(): string
    {
        $kinds = $this->order->items->pluck('line_kind')->unique();

        if ($kinds->count() > 1) {
            return 'Mixed (PRODUCT + SERVICE)';
        }

        return $kinds->first() === 'SERVICE' ? 'Service Only' : 'Product Only';
    }

    // Timeline Inquiry -> Quotation -> CO (History Flow tab), dibangun dinamis dari
    // data asli, bukan hardcode spt mockup.
    #[Computed]
    public function historyTimeline(): array
    {
        $rows = [];

        foreach ($this->order->items->pluck('inquiry')->filter()->unique('id_inquiry') as $inq) {
            $rows[] = ['type' => 'Inquiry', 'label' => $inq->inquiry_no.' — '.($inq->product_draft_name ?? '—'), 'date' => $inq->inquiry_date];
        }

        $q = $this->order->quotation;
        $rows[] = ['type' => 'Quotation', 'label' => $q->quotation_no.' ('.$q->doc_status.')', 'date' => $q->issued_date];
        $rows[] = ['type' => 'Customer Order', 'label' => $this->order->co_no.' ('.$this->order->doc_status.')', 'date' => $this->order->co_date, 'current' => true];

        return $rows;
    }

    #[On('address-picked')]
    public function onAddressPicked(string $fieldName, ?int $areaId, ?string $areaLevel, ?string $label, ?string $postalCode): void
    {
        if ($fieldName !== 'co-ship-address' || $this->order->doc_status !== 'DRAFT') {
            return;
        }

        $this->order->update([
            'ship_area_id' => $areaId,
            'ship_area_level' => $areaLevel,
            'ship_address_label' => $label,
            'ship_postal_code' => $postalCode,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->order);
    }

    public function updateHeader(string $field, $value): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $allowed = ['co_date', 'customer_po_no', 'ship_address_detail', 'discount_qty_pct', 'notes'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->order->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->order);
    }

    public function addItem(string $kind): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $kind = $kind === 'SERVICE' ? 'SERVICE' : 'PRODUCT';
        $nextSeq = ((int) $this->order->items()->max('line_seq')) + 1;

        CoOrderItem::create([
            'order_id' => $this->orderId,
            'line_seq' => $nextSeq,
            'line_kind' => $kind,
            'description' => '',
            'qty' => 1,
            'qty_unit' => 'Pcs',
            'unit_price' => 0,
            'discount_pct' => 0,
            'subtotal' => 0,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->order);
    }

    public function removeItem(int $itemId): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        // Baris hasil snapshot Quotation tidak boleh dihapus (jaga jejak) -- hanya
        // baris yang ditambah manual di CO ini (quotation_item_id null) yang boleh.
        CoOrderItem::where('order_id', $this->orderId)->whereKey($itemId)->whereNull('quotation_item_id')->delete();
        unset($this->order);
    }

    public function updateItem(int $itemId, string $field, $value): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $item = CoOrderItem::where('order_id', $this->orderId)->findOrFail($itemId);

        // due_date boleh diedit di baris manapun (termasuk snapshot Quotation);
        // field harga/qty/deskripsi cuma utk baris manual (bukan snapshot).
        if ($field === 'due_date') {
            $item->due_date = $value ?: null;
            $item->save();
            unset($this->order);

            return;
        }

        if ($item->quotation_item_id !== null) {
            return;
        }

        $allowed = ['description', 'qty', 'qty_unit', 'unit_price', 'discount_pct'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $item->{$field} = $value === '' ? 0 : $value;
        $item->recalcSubtotal();
        $item->updated_by = Auth::user()->login;
        $item->save();
        unset($this->order);
    }

    public function addDelivery(int $itemId): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $item = CoOrderItem::where('order_id', $this->orderId)->where('line_kind', 'PRODUCT')->findOrFail($itemId);

        CoOrderDelivery::create([
            'order_id' => $this->orderId,
            'item_id' => $item->id_item,
            'shipment_type' => 'FULL',
            'ship_qty' => $item->qty,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->order);
    }

    public function updateDelivery(int $deliveryId, string $field, $value): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $allowed = ['shipment_type', 'ship_date', 'ship_qty'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        CoOrderDelivery::where('order_id', $this->orderId)->whereKey($deliveryId)
            ->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->order);
    }

    public function removeDelivery(int $deliveryId): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        CoOrderDelivery::where('order_id', $this->orderId)->whereKey($deliveryId)->delete();
        unset($this->order);
    }

    public function addExternalCost(): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        CoOrderExternalCost::create([
            'order_id' => $this->orderId,
            'category' => 'Trucking',
            'description' => '',
            'nominal' => 0,
            'tax_treatment' => 'TAXABLE',
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->order);
    }

    public function updateExternalCost(int $costId, string $field, $value): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $allowed = ['category', 'description', 'nominal', 'tax_treatment'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        CoOrderExternalCost::where('order_id', $this->orderId)->whereKey($costId)
            ->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->order);
    }

    public function removeExternalCost(int $costId): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        CoOrderExternalCost::where('order_id', $this->orderId)->whereKey($costId)->delete();
        unset($this->order);
    }

    public function confirmCo(): void
    {
        if ($this->order->doc_status !== 'DRAFT' || $this->order->items->isEmpty()) {
            return;
        }

        $this->order->update(['doc_status' => 'CONFIRMED', 'updated_by' => Auth::user()->login]);
        unset($this->order);
    }
};
?>

<div x-data="{ tab: 'product' }">
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📦 Customer Order — Entry</b>
        <span class="text-xs text-white/75">{{ $this->order->co_no }} · {{ $this->order->quotation->customer?->customer_name }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full {{ $this->order->doc_status === 'CONFIRMED' ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $this->order->doc_status }}</span>

        <div class="ml-auto flex items-center gap-4">
            <div class="text-right leading-tight">
                <div class="text-[9.5px] uppercase text-white/55 font-semibold">Net Receivable</div>
                <div class="text-sm font-extrabold">{{ number_format($this->totals['net_receivable'], 0, ',', '.') }}</div>
            </div>
            @if ($this->order->doc_status === 'DRAFT')
                <button type="button" wire:click="confirmCo" wire:confirm="Confirm CO ini? Setelah confirm, isi tidak bisa diedit lagi."
                    @disabled($this->order->items->isEmpty())
                    class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#22a06b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                    ✓ Confirm CO
                </button>
            @endif
            <a href="{{ route('co.list') }}" title="Kembali ke menu grid"
                class="inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
                <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
            </a>
        </div>
    </header>

    <div class="max-w-[1280px] mx-auto my-5 px-4">
        {{-- flex, BUKAN class="grid" -- app.css baris 60 override literal ".grid"
             jadi grid-cols-4 (unlayered, selalu menang atas grid-cols/lg:grid-cols
             Tailwind manapun, sama persis kasus input{width:full} di
             GRID_UI_STANDARD.md §6). Dulu dipakai grid-cols-[1fr_320px] tapi itu
             ke-override jadi 4 kolom sama rata -- KIRI ikut kepotong ke ~25% lebar,
             bukan cuma soal grid Header Dokumen di dalamnya. --}}
        <div class="flex flex-col lg:flex-row gap-4">

            {{-- KIRI --}}
            <div class="lg:flex-1 min-w-0">
                {{-- HEADER DOKUMEN --}}
                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-3">Header Dokumen</h3>
                    {{-- "grid three" (bukan grid-cols-3) -- ikuti konvensi .grid/.two/.three
                         yang sudah ada di app.css, satu-satunya cara reliable dapat 3 kolom
                         di sini (lihat catatan flex di atas). --}}
                    <div class="grid three">
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">No. CO</label>
                            <input readonly value="{{ $this->order->co_no }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">CO Date</label>
                            <input type="date" value="{{ $this->order->co_date?->format('Y-m-d') }}" @disabled($this->order->doc_status !== 'DRAFT')
                                wire:change="updateHeader('co_date', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Ref. Quotation</label>
                            <input readonly value="{{ $this->order->quotation->quotation_no }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>

                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Cust. PO No.</label>
                            <input type="text" value="{{ $this->order->customer_po_no }}" @disabled($this->order->doc_status !== 'DRAFT')
                                wire:change="updateHeader('customer_po_no', $event.target.value)" placeholder="PO/customer/..." class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Order Type</label>
                            <input readonly value="{{ $this->orderType }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Mata Uang</label>
                            <input readonly value="{{ $this->order->quotation->currency_code }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>

                        <div class="col-span-3">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Customer</label>
                            <input readonly value="{{ $this->order->quotation->customer?->customer_name }} ({{ $this->order->quotation->customer?->customer_code }})" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted font-semibold">
                        </div>

                        <div class="col-span-3">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Shipment Location</label>
                            <livewire:address-picker
                                field-name="co-ship-address"
                                :initial-area-id="$this->order->ship_area_id"
                                :initial-area-level="$this->order->ship_area_level"
                                :initial-label="$this->order->ship_address_label"
                                wire:key="co-ship-picker-{{ $this->orderId }}" />
                        </div>
                        <div class="col-span-2">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Detail Alamat (jalan, no., RT/RW, patokan)</label>
                            <input type="text" value="{{ $this->order->ship_address_detail }}" @disabled($this->order->doc_status !== 'DRAFT')
                                wire:change="updateHeader('ship_address_detail', $event.target.value)" placeholder="Jl. ..., No. ..." class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Kode Pos</label>
                            <input readonly value="{{ $this->order->ship_postal_code }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>
                    </div>
                </div>

                {{-- TABS --}}
                <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-[#d7dbe2] px-3">
                        <div class="flex gap-1">
                            <button type="button" @click="tab = 'product'" :class="tab === 'product' ? 'text-accent border-accent' : 'text-muted border-transparent'" class="px-3 py-2.5 text-xs font-bold border-b-2 cursor-pointer">Product</button>
                            <button type="button" @click="tab = 'delivery'" :class="tab === 'delivery' ? 'text-accent border-accent' : 'text-muted border-transparent'" class="px-3 py-2.5 text-xs font-bold border-b-2 cursor-pointer">Delivery</button>
                            <button type="button" @click="tab = 'external'" :class="tab === 'external' ? 'text-accent border-accent' : 'text-muted border-transparent'" class="px-3 py-2.5 text-xs font-bold border-b-2 cursor-pointer">External Cost</button>
                            <button type="button" @click="tab = 'history'" :class="tab === 'history' ? 'text-accent border-accent' : 'text-muted border-transparent'" class="px-3 py-2.5 text-xs font-bold border-b-2 cursor-pointer">History Flow</button>
                        </div>
                    </div>

                    {{-- TAB: PRODUCT --}}
                    <div x-show="tab === 'product'" class="p-3">
                        @if ($this->order->doc_status === 'DRAFT')
                            <div class="flex justify-end gap-1.5 mb-2">
                                <button type="button" wire:click="addItem('PRODUCT')" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Produk</button>
                                <button type="button" wire:click="addItem('SERVICE')" class="text-[11px] font-bold text-navy bg-[#eef1f5] border border-dashed border-[#c7cfda] rounded px-2.5 py-1 cursor-pointer">＋ Jasa</button>
                            </div>
                        @endif
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f4f5f8]">
                                    <th class="text-left px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Produk / Jasa</th>
                                    <th class="text-center px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Type</th>
                                    <th class="text-right px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Qty</th>
                                    <th class="text-right px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Harga</th>
                                    <th class="text-right px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Disc%</th>
                                    <th class="text-right px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Subtotal</th>
                                    <th class="text-center px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Due Date</th>
                                    <th class="px-2 py-2 border-b border-[#d7dbe2]"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->order->items as $item)
                                    <tr class="border-b border-[#eef1f5]" wire:key="co-item-{{ $item->id_item }}">
                                        <td class="px-2 py-2">
                                            @if ($item->quotation_item_id === null && $this->order->doc_status === 'DRAFT')
                                                <input type="text" value="{{ $item->description }}" placeholder="Deskripsi..." wire:change="updateItem({{ $item->id_item }}, 'description', $event.target.value)" class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs font-medium">
                                            @else
                                                <div class="font-medium text-navy">{{ $item->description }}</div>
                                            @endif
                                            @if ($item->spec_note)<div class="text-[10px] text-muted mt-0.5">{{ $item->spec_note }}</div>@endif
                                            @if ($item->inquiry) <span class="text-[9.5px] font-mono font-bold text-accent">{{ $item->inquiry->inquiry_no }}</span> @endif
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $item->line_kind === 'PRODUCT' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $item->line_kind === 'PRODUCT' ? 'P' : 'S' }}</span>
                                        </td>
                                        <td class="px-2 py-2 text-right">
                                            @if ($item->quotation_item_id === null && $this->order->doc_status === 'DRAFT')
                                                <input type="number" step="0.01" value="{{ $item->qty }}" wire:change="updateItem({{ $item->id_item }}, 'qty', $event.target.value)" class="w-16 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right">
                                            @else
                                                {{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-right">
                                            @if ($item->quotation_item_id === null && $this->order->doc_status === 'DRAFT')
                                                <input type="number" step="0.01" value="{{ $item->unit_price }}" wire:change="updateItem({{ $item->id_item }}, 'unit_price', $event.target.value)" class="w-20 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right">
                                            @else
                                                {{ number_format($item->unit_price, 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-right">{{ number_format($item->discount_pct, 1) }}%</td>
                                        <td class="px-2 py-2 text-right font-bold text-navy">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-center">
                                            <input type="date" value="{{ $item->due_date?->format('Y-m-d') }}" @disabled($this->order->doc_status !== 'DRAFT')
                                                wire:change="updateItem({{ $item->id_item }}, 'due_date', $event.target.value)" class="px-1.5 py-1 border border-[#d7dbe2] rounded text-[11px] disabled:bg-[#f8fafc]">
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            @if ($item->quotation_item_id === null && $this->order->doc_status === 'DRAFT')
                                                <button type="button" wire:click="removeItem({{ $item->id_item }})" wire:confirm="Hapus baris ini?" class="text-red-500 hover:text-red-700 cursor-pointer">✕</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center py-8 text-muted">Belum ada baris.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <p class="text-[10.5px] text-muted mt-2 italic">*Baris dari Quotation adalah snapshot terkunci (harga/qty tidak bisa diubah di sini) — hanya Due Date yang editable. Baris "+ Produk"/"+ Jasa" manual boleh diedit penuh.</p>
                    </div>

                    {{-- TAB: DELIVERY --}}
                    <div x-show="tab === 'delivery'" x-cloak class="p-3">
                        @forelse ($this->order->items->where('line_kind', 'PRODUCT') as $item)
                            <div class="border border-[#eef1f5] rounded-md mb-2.5 overflow-hidden" wire:key="dlv-item-{{ $item->id_item }}">
                                <div class="flex items-center justify-between px-2.5 py-2 bg-[#f8fafc]">
                                    <span class="text-xs font-semibold text-navy">{{ $item->description }}</span>
                                    @if ($this->order->doc_status === 'DRAFT')
                                        <button type="button" wire:click="addDelivery({{ $item->id_item }})" class="text-[10.5px] font-bold text-accent cursor-pointer">＋ Jadwal Kirim</button>
                                    @endif
                                </div>
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-[#fbfcfe]">
                                            <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-bold border-b border-[#eef1f5]">Shipment Type</th>
                                            <th class="text-left px-2 py-1.5 text-[10px] text-muted uppercase font-bold border-b border-[#eef1f5]">Ship Date</th>
                                            <th class="text-right px-2 py-1.5 text-[10px] text-muted uppercase font-bold border-b border-[#eef1f5]">Ship Qty</th>
                                            <th class="px-2 py-1.5 border-b border-[#eef1f5]"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($item->deliveries as $d)
                                            <tr wire:key="dlv-{{ $d->id_delivery }}">
                                                <td class="px-2 py-1.5">
                                                    <select @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateDelivery({{ $d->id_delivery }}, 'shipment_type', $event.target.value)" class="px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                                        <option value="FULL" @selected($d->shipment_type === 'FULL')>FULL</option>
                                                        <option value="PARTIAL" @selected($d->shipment_type === 'PARTIAL')>PARTIAL</option>
                                                    </select>
                                                </td>
                                                <td class="px-2 py-1.5">
                                                    <input type="date" value="{{ $d->ship_date?->format('Y-m-d') }}" @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateDelivery({{ $d->id_delivery }}, 'ship_date', $event.target.value)" class="px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                                </td>
                                                <td class="px-2 py-1.5 text-right">
                                                    <input type="number" step="0.01" value="{{ $d->ship_qty }}" @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateDelivery({{ $d->id_delivery }}, 'ship_qty', $event.target.value)" class="w-20 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-[#f8fafc]">
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    @if ($this->order->doc_status === 'DRAFT')
                                                        <button type="button" wire:click="removeDelivery({{ $d->id_delivery }})" class="text-red-500 hover:text-red-700 cursor-pointer">✕</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center py-3 text-muted text-[11px]">Belum ada jadwal kirim.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @empty
                            <div class="text-center py-8 text-muted text-xs">Tidak ada baris PRODUCT untuk dijadwalkan.</div>
                        @endforelse
                    </div>

                    {{-- TAB: EXTERNAL COST --}}
                    <div x-show="tab === 'external'" x-cloak class="p-3">
                        @if ($this->order->doc_status === 'DRAFT')
                            <div class="flex justify-end mb-2">
                                <button type="button" wire:click="addExternalCost" class="text-[11px] font-bold text-accent cursor-pointer">＋ Add Row</button>
                            </div>
                        @endif
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f4f5f8]">
                                    <th class="text-left px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Category</th>
                                    <th class="text-left px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Description</th>
                                    <th class="text-right px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Nominal</th>
                                    <th class="text-center px-2 py-2 text-[10px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tax Treatment</th>
                                    <th class="px-2 py-2 border-b border-[#d7dbe2]"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->order->externalCosts as $c)
                                    <tr wire:key="ext-{{ $c->id_cost }}">
                                        <td class="px-2 py-1.5">
                                            <select @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateExternalCost({{ $c->id_cost }}, 'category', $event.target.value)" class="px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                                @foreach (['Trucking', 'Expedition', 'Lainnya'] as $cat)
                                                    <option value="{{ $cat }}" @selected($c->category === $cat)>{{ $cat }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <input type="text" value="{{ $c->description }}" @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateExternalCost({{ $c->id_cost }}, 'description', $event.target.value)" class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                        </td>
                                        <td class="px-2 py-1.5 text-right">
                                            <input type="number" step="0.01" value="{{ $c->nominal }}" @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateExternalCost({{ $c->id_cost }}, 'nominal', $event.target.value)" class="w-24 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-[#f8fafc]">
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            <select @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateExternalCost({{ $c->id_cost }}, 'tax_treatment', $event.target.value)" class="px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                                <option value="TAXABLE" @selected($c->tax_treatment === 'TAXABLE')>Taxable</option>
                                                <option value="REIMBURSEMENT" @selected($c->tax_treatment === 'REIMBURSEMENT')>Reimbursement</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            @if ($this->order->doc_status === 'DRAFT')
                                                <button type="button" wire:click="removeExternalCost({{ $c->id_cost }})" class="text-red-500 hover:text-red-700 cursor-pointer">✕</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center py-6 text-muted">Belum ada biaya eksternal.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- TAB: HISTORY FLOW --}}
                    <div x-show="tab === 'history'" x-cloak class="p-3 space-y-2">
                        @foreach ($this->historyTimeline as $row)
                            <div class="flex items-center justify-between border-b border-[#eef1f5] pb-2 {{ $row['current'] ?? false ? 'font-bold' : '' }}">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $row['type'] === 'Inquiry' ? 'bg-[#dbeafe] text-[#1e40af]' : ($row['type'] === 'Quotation' ? 'bg-[#ede9fe] text-[#5b21b6]' : 'bg-[#fff5e0] text-[#8a5a00]') }}">{{ $row['type'] }}</span>
                                    <span class="text-navy">{{ $row['label'] }}</span>
                                </div>
                                <span class="text-[10px] text-muted">{{ \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('d M Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- KANAN --}}
            <div class="lg:w-[320px] lg:shrink-0">
                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-2.5">Document Discount</h3>
                    <div class="space-y-2">
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Disc. Doc (fix dari IQ)</label>
                            <input readonly value="{{ $this->order->discount_doc_pct !== null ? number_format($this->order->discount_doc_pct, 2).'%' : '—' }}" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Disc. Qty</label>
                            <input type="number" step="0.01" value="{{ $this->order->discount_qty_pct }}" @disabled($this->order->doc_status !== 'DRAFT')
                                wire:change="updateHeader('discount_qty_pct', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-2.5">Summary &amp; Tax</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Item Subtotal</span><span>{{ number_format($this->totals['item_subtotal'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">− Total Discounts</span><span>({{ number_format($this->totals['discount_amount'], 0, ',', '.') }})</span></div>
                        <div class="flex justify-between"><span class="text-muted">+ External Cost</span><span>{{ number_format($this->totals['external_cost'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-semibold border-t border-[#eef1f5] pt-1"><span>DPP</span><span>{{ number_format($this->totals['dpp'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">VAT {{ number_format($this->totals['ppn_pct'], 0) }}% (PPN)</span><span>{{ number_format($this->totals['ppn'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-semibold"><span>Total Billing</span><span>{{ number_format($this->totals['total_billing'], 0, ',', '.') }}</span></div>
                        @if ($this->totals['wht23'] > 0)
                            <div class="flex justify-between text-[#5b21b6]"><span>WHT {{ number_format($this->totals['wht23_pct'], 0) }}% (PPh Service)</span><span>({{ number_format($this->totals['wht23'], 0, ',', '.') }})</span></div>
                        @endif
                        <div class="flex justify-between font-bold text-accent border-t border-[#eef1f5] pt-1 text-[13px]"><span>Net Receivable</span><span>{{ number_format($this->totals['net_receivable'], 0, ',', '.') }}</span></div>
                    </div>
                </div>

                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Order Notes</label>
                    <textarea rows="3" @disabled($this->order->doc_status !== 'DRAFT') wire:change="updateHeader('notes', $event.target.value)"
                        placeholder="Internal notes for Ops/PPIC..." class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->order->notes }}</textarea>
                </div>

                @if ($this->order->doc_status !== 'DRAFT')
                    <div class="text-center text-xs text-[#1f8a4c] font-semibold bg-[#eaf7ee] border border-[#a9d9bb] rounded-md py-2.5">✓ Sudah di-confirm — isi terkunci</div>
                @endif
            </div>

        </div>
    </div>
</div>
