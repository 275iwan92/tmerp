<?php

use App\Models\Master\ProdProduct;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderExternalCost;
use App\Models\Sdi\CoOrderItem;
use Illuminate\Support\Facades\Auth;
use App\Services\Sales\CoOrderBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Customer Order — Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $orderId;

    public bool $productPickerOpen = false;

    // null = tambah baris baru; terisi = ganti Master Product baris itu.
    public ?int $productPickerItemId = null;

    public string $productSearch = '';

    public function mount(int $order): void
    {
        $this->orderId = $order;
    }

    #[Computed]
    public function order(): CoOrder
    {
        return CoOrder::with([
            'customer',
            'salesPerson',
            'quotation',
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

    // Guard Confirm (keputusan user 2026-09-03): due_date WAJIB terisi di semua
    // baris PRODUCT sebelum CO boleh Confirm -- supaya ENGINE CPDEL (confirmCo()
    // di bawah) selalu punya tanggal utk dasar jadwal, tidak ada lagi CO
    // CONFIRMED dgn due_date kosong yg bikin item itu tak pernah muncul di
    // grid Customer Plan Delivery.
    #[Computed]
    public function itemsMissingDueDate()
    {
        return $this->order->items->where('line_kind', 'PRODUCT')->whereNull('due_date');
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

        if ($q = $this->order->quotation) {
            $rows[] = ['type' => 'Quotation', 'label' => $q->quotation_no.' ('.$q->doc_status.')', 'date' => $q->issued_date];
        }
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

        $allowed = ['co_date', 'customer_po_no', 'ship_address_detail', 'discount_qty_pct', 'notes', 'sales_person_id', 'payment_term', 'is_ppn',
            'shipment_method', 'recipient_customer_id', 'shipper_customer_id', 'forwarder_customer_id'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->order->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->order);
    }

    // Baris PRODUK selalu lewat picker Master Product (openProductPicker) --
    // CLAUDE.md §3 "Lookup dulu". addItem() tinggal melayani baris JASA yang
    // memang tidak punya master.
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

    public function openProductPicker(?int $itemId = null): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $this->productPickerItemId = $itemId;
        $this->productSearch = '';
        $this->productPickerOpen = true;
    }

    public function closeProductPicker(): void
    {
        $this->productPickerOpen = false;
        $this->productPickerItemId = null;
    }

    // prod_product 14k+ baris -- wajib ada kata kunci, dan produk milik customer
    // CO ini didahulukan (prod_product.customer_id) karena di bisnis ini produk
    // memang dirancang per-customer.
    #[Computed]
    public function productResults()
    {
        if (mb_strlen(trim($this->productSearch)) < 2) {
            return collect();
        }

        $cid = $this->order->customer_id;

        return ProdProduct::where('activate', true)
            ->where(fn ($q) => $q->where('product_name', 'like', "%{$this->productSearch}%")
                ->orWhere('product_code', 'like', "%{$this->productSearch}%")
                ->orWhere('design_no', 'like', "%{$this->productSearch}%"))
            ->orderByRaw('CASE WHEN customer_id = ? THEN 0 ELSE 1 END', [$cid])
            ->orderBy('product_name')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function salesPeople()
    {
        return SalesPerson::where('activate', true)->orderBy('sales_name')->get();
    }

    #[Computed]
    public function paymentTerms()
    {
        return \App\Models\Master\FinPaymentTerm::where('activate', true)->orderBy('sort_order')->get();
    }

    public function pickProduct(int $productId): void
    {
        if ($this->order->doc_status !== 'DRAFT') {
            return;
        }

        $product = ProdProduct::findOrFail($productId);

        if ($this->productPickerItemId === null) {
            CoOrderBuilder::addProductLine($this->order, $product);
        } else {
            $item = CoOrderItem::where('order_id', $this->orderId)->findOrFail($this->productPickerItemId);
            $item->product_id = $product->id_product;
            $item->qty_min = $product->qty_limit_min;
            $item->qty_max = $product->qty_limit_max;

            // Baris snapshot Quotation: cuma ditautkan ke master, deskripsi/harga
            // hasil negosiasi TIDAK ditimpa (prinsip snapshot §4).
            if ($item->quotation_item_id === null) {
                $item->description = trim($product->product_name.' ('.$product->product_code.')');
                $item->qty_unit = $product->uom_sales ?: $item->qty_unit;
                $item->unit_price = $product->default_selling_price ?? $item->unit_price;
                $item->recalcSubtotal();
            }

            $item->updated_by = Auth::user()->login;
            $item->save();
        }

        $this->closeProductPicker();
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

        // Alasan Sales menimpa due date otomatis (CoDueDate); boleh di baris manapun.
        if ($field === 'due_note') {
            $item->due_note = trim((string) $value) ?: null;
            $item->save();
            unset($this->order);

            return;
        }

        // Flag Qty Minim (v1 sdi_co_detail.qty_minim) boleh di baris manapun, seperti due_date.
        if ($field === 'qty_minim') {
            $item->qty_minim = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $item->recalcSubtotal();
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

    // ENGINE Customer Plan Delivery (CPDEL, keputusan user 2026-09-03): begitu CO
    // Confirm, tiap item PRODUCT yang belum punya baris co_order_delivery sama
    // sekali (Sales tidak sempat isi manual lewat "+ Jadwal Kirim" saat Draft)
    // di-auto-isi 1 baris DEFAULT: qty penuh @ due_date item. Item yang SUDAH
    // punya baris manual (hasil "+ Jadwal Kirim") tidak disentuh -- itu override
    // Sales, engine cuma mengisi yang kosong. due_date WAJIB terisi utk Confirm
    // sama sekali (lihat itemsMissingDueDate() guard di bawah & tombol Confirm
    // di blade) -- jadi TIDAK ADA lagi kasus CO CONFIRMED dgn due_date kosong.
    // Hasilnya lanjut diatur (Split/Gabung) di layar Customer Plan Delivery
    // (co/delivery-plan.blade.php).
    public function confirmCo(): void
    {
        CoOrderBuilder::confirm($this->order);
        unset($this->order);
    }
};
?>

<div x-data="{ tab: 'product' }">
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📦 Customer Order — Entry</b>
        <span class="text-xs text-white/75">{{ $this->order->co_no }} · {{ $this->order->customer?->customer_name ?? '—' }}</span>
        <x-form.badge :tone="$this->order->isInstant() ? 'extra' : 'info'">{{ $this->order->entry_mode }}</x-form.badge>
        <x-form.badge :tone="$this->order->doc_status === 'CONFIRMED' ? 'success' : 'warn'">{{ $this->order->doc_status }}</x-form.badge>

        <div class="ml-auto flex items-center gap-4">
            <div class="text-right leading-tight">
                <div class="text-[9.5px] uppercase text-white/55 font-semibold">Net Receivable</div>
                <div class="text-sm font-extrabold">{{ number_format($this->totals['net_receivable'], 0, ',', '.') }}</div>
            </div>
            @if ($this->order->doc_status === 'DRAFT')
                @if ($this->itemsMissingDueDate->isNotEmpty())
                    <span class="text-[10.5px] font-semibold text-[#ffd7a8]" title="{{ $this->itemsMissingDueDate->pluck('description')->implode(', ') }}">
                        ⚠ {{ $this->itemsMissingDueDate->count() }} item belum ada Due Date
                    </span>
                @endif
                @if ($this->order->itemsMissingProduct()->isNotEmpty())
                    <span class="text-[10.5px] font-semibold text-[#ffd7a8]" title="{{ $this->order->itemsMissingProduct()->pluck('description')->implode(', ') }}">
                        &#9888; {{ $this->order->itemsMissingProduct()->count() }} item belum terkait Master Product
                    </span>
                @endif
                <button type="button" wire:click="confirmCo" wire:confirm="Confirm CO ini? Setelah confirm, isi tidak bisa diedit lagi."
                    @disabled(! $this->order->isConfirmable())
                    class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#22a06b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                    ✓ Confirm CO
                </button>
            @endif
            @if ($this->order->doc_status === 'CONFIRMED')
                <a href="{{ route('co.print', ['order' => $this->orderId]) }}" target="_blank"
                    class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">🖨 Cetak Nota</a>
            @endif
            <x-back-button :fallback="route('co.list')" title="Kembali ke menu grid" />
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
                        <x-form.input label="No. CO" readonly value="{{ $this->order->co_no }}" class="bg-[#f8fafc]" />
                        <x-form.date-picker label="CO Date" value="{{ $this->order->co_date?->format('Y-m-d') }}" :disabled="$this->order->doc_status !== 'DRAFT'"
                            wire:change="updateHeader('co_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                        <x-form.input label="Ref. Quotation" readonly value="{{ $this->order->quotation?->quotation_no ?? '&#8212; (Instant, tanpa Quotation)' }}" class="bg-[#f8fafc]" />

                        <x-form.input label="Cust. PO No." value="{{ $this->order->customer_po_no }}" :disabled="$this->order->doc_status !== 'DRAFT'"
                            wire:change="updateHeader('customer_po_no', $event.target.value)" placeholder="PO/customer/..." class="disabled:bg-[#f8fafc]" />
                        <x-form.input label="Order Type" readonly value="{{ $this->orderType }}" class="bg-[#f8fafc]" />
                        <x-form.input label="Mata Uang" readonly value="{{ $this->order->currency_code ?? 'IDR' }}" class="bg-[#f8fafc]" />

                        <div class="col-span-3">
                            <x-form.input label="Customer" readonly value="{{ $this->order->customer?->customer_name }} ({{ $this->order->customer?->customer_code }})" class="bg-[#f8fafc] font-semibold" />
                        </div>

                        <x-form.select label="PIC Sales" :disabled="$this->order->doc_status !== 'DRAFT'"
                            wire:change="updateHeader('sales_person_id', $event.target.value)" class="disabled:bg-[#f8fafc]">
                            <option value="">&#8212; pilih &#8212;</option>
                            @foreach ($this->salesPeople as $sp)
                                <option value="{{ $sp->id_sales_person }}" @selected((int) $this->order->sales_person_id === (int) $sp->id_sales_person)>{{ $sp->sales_name }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.select label="Termin Bayar" :disabled="$this->order->doc_status !== 'DRAFT'"
                            wire:change="updateHeader('payment_term', $event.target.value)" class="disabled:bg-[#f8fafc]">
                            <option value="">&#8212; pilih &#8212;</option>
                            @foreach ($this->paymentTerms as $pt)
                                <option value="{{ $pt->payment_term_code }}" @selected($this->order->payment_term === $pt->payment_term_code)>{{ $pt->term_label }}</option>
                            @endforeach
                        </x-form.select>
                        {{-- Flag PPN (backlog01 UI-09): default dari Inquiry/customer; Surat Jalan & DO ikut flag ini. --}}
                        <x-form.select label="Pajak" :disabled="$this->order->doc_status !== 'DRAFT'" :placeholder="false"
                            :options="['1' => 'PPN', '0' => 'Non PPN']" :selected="$this->order->is_ppn ? '1' : '0'"
                            wire:change="updateHeader('is_ppn', $event.target.value)" class="disabled:bg-surface-alt" />

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
                            <x-form.input label="Detail Alamat (jalan, no., RT/RW, patokan)" value="{{ $this->order->ship_address_detail }}" :disabled="$this->order->doc_status !== 'DRAFT'"
                                wire:change="updateHeader('ship_address_detail', $event.target.value)" placeholder="Jl. ..., No. ..." class="disabled:bg-[#f8fafc]" />
                        </div>
                        <x-form.input label="Kode Pos" readonly value="{{ $this->order->ship_postal_code }}" class="bg-[#f8fafc]" />
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
                                <button type="button" wire:click="openProductPicker" title="Pilih dari Master Product" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Produk</button>
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
                                                <x-form.input value="{{ $item->description }}" placeholder="Deskripsi..." wire:change="updateItem({{ $item->id_item }}, 'description', $event.target.value)" class="!py-1 font-medium" />
                                            @else
                                                <div class="font-medium text-navy">{{ $item->description }}</div>
                                            @endif
                                            @if ($item->spec_note)<div class="text-[10px] text-muted mt-0.5">{{ $item->spec_note }}</div>@endif
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                @if ($item->inquiry) <span class="text-[9.5px] font-mono font-bold text-accent">{{ $item->inquiry->inquiry_no }}</span> @endif
                                                @if ($item->line_kind === 'PRODUCT')
                                                    @if ($item->product_id)
                                                        <span class="text-[9.5px] font-mono text-[#16a34a]" title="Master Product terkait">{{ $item->product?->product_code ?? $item->product_id }}</span>
                                                    @else
                                                        <span class="text-[9.5px] font-mono text-[#b45309]" title="Belum terkait Master Product">belum terkait master</span>
                                                    @endif
                                                    @if ($this->order->doc_status === 'DRAFT')
                                                        <button type="button" wire:click="openProductPicker({{ $item->id_item }})" class="text-[9.5px] text-accent underline cursor-pointer">pilih</button>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $item->line_kind === 'PRODUCT' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $item->line_kind === 'PRODUCT' ? 'P' : 'S' }}</span>
                                        </td>
                                        <td class="px-2 py-2 text-right">
                                            @if ($item->quotation_item_id === null && $this->order->doc_status === 'DRAFT')
                                                <x-form.input type="number" step="0.01" value="{{ $item->qty }}" wire:change="updateItem({{ $item->id_item }}, 'qty', $event.target.value)" class="w-16 !py-1 text-right" />
                                            @else
                                                {{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-right">
                                            @if ($item->quotation_item_id === null && $this->order->doc_status === 'DRAFT')
                                                <x-form.input type="number" step="0.01" value="{{ $item->unit_price }}" wire:change="updateItem({{ $item->id_item }}, 'unit_price', $event.target.value)" class="w-20 !py-1 text-right" />
                                            @else
                                                {{ number_format($item->unit_price, 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-right">{{ number_format($item->discount_pct, 1) }}%</td>
                                        <td class="px-2 py-2 text-right font-bold text-navy">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-center">
                                            <x-form.date-picker value="{{ $item->due_date?->format('Y-m-d') }}" :disabled="$this->order->doc_status !== 'DRAFT'"
                                                wire:change="updateItem({{ $item->id_item }}, 'due_date', $event.target.value)"
                                                class="!py-1 text-[11px] disabled:bg-[#f8fafc] {{ $item->line_kind === 'PRODUCT' && ! $item->due_date && $this->order->doc_status === 'DRAFT' ? '!border-red-400' : '' }}" />
                                            @if ($item->line_kind === 'PRODUCT' && ! $item->due_date && $this->order->doc_status === 'DRAFT')
                                                <div class="text-[9.5px] text-red-500 mt-0.5">wajib diisi</div>
                                            @endif
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
                                                    <x-form.select :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="updateDelivery({{ $d->id_delivery }}, 'shipment_type', $event.target.value)" class="!py-1 disabled:bg-[#f8fafc]"
                                                        :placeholder="false" :selected="$d->shipment_type" :options="['FULL' => 'FULL', 'PARTIAL' => 'PARTIAL']" />
                                                </td>
                                                <td class="px-2 py-1.5">
                                                    <x-form.date-picker value="{{ $d->ship_date?->format('Y-m-d') }}" :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="updateDelivery({{ $d->id_delivery }}, 'ship_date', $event.target.value)" class="!py-1 disabled:bg-[#f8fafc]" />
                                                </td>
                                                <td class="px-2 py-1.5 text-right">
                                                    <x-form.input type="number" step="0.01" value="{{ $d->ship_qty }}" :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="updateDelivery({{ $d->id_delivery }}, 'ship_qty', $event.target.value)" class="w-20 !py-1 text-right disabled:bg-[#f8fafc]" />
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
                                            <x-form.select :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="updateExternalCost({{ $c->id_cost }}, 'category', $event.target.value)" class="!py-1 disabled:bg-[#f8fafc]"
                                                :placeholder="false" :selected="$c->category" :options="['Trucking' => 'Trucking', 'Expedition' => 'Expedition', 'Lainnya' => 'Lainnya']" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <x-form.input value="{{ $c->description }}" :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="updateExternalCost({{ $c->id_cost }}, 'description', $event.target.value)" class="!py-1 disabled:bg-[#f8fafc]" />
                                        </td>
                                        <td class="px-2 py-1.5 text-right">
                                            <x-form.input type="number" step="0.01" value="{{ $c->nominal }}" :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="updateExternalCost({{ $c->id_cost }}, 'nominal', $event.target.value)" class="w-24 !py-1 text-right disabled:bg-[#f8fafc]" />
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            <x-form.select :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="updateExternalCost({{ $c->id_cost }}, 'tax_treatment', $event.target.value)" class="!py-1 disabled:bg-[#f8fafc]"
                                                :placeholder="false" :selected="$c->tax_treatment" :options="['TAXABLE' => 'Taxable', 'REIMBURSEMENT' => 'Reimbursement']" />
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
                        <x-form.input label="Disc. Doc (fix dari IQ)" readonly value="{{ $this->order->discount_doc_pct !== null ? number_format($this->order->discount_doc_pct, 2).'%' : '—' }}" class="bg-[#f8fafc]" />
                        <x-form.input label="Disc. Qty" type="number" step="0.01" value="{{ $this->order->discount_qty_pct }}" :disabled="$this->order->doc_status !== 'DRAFT'"
                            wire:change="updateHeader('discount_qty_pct', $event.target.value)" class="disabled:bg-[#f8fafc]" />
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

    @if ($productPickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closeProductPicker">
            <div class="bg-white rounded-lg p-4 w-[620px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">
                        {{ $productPickerItemId ? 'Ganti Master Product baris ini' : 'Tambah Baris dari Master Product' }}
                    </h3>
                    <button type="button" wire:click="closeProductPicker" class="text-muted hover:text-navy cursor-pointer">&#10005;</button>
                </div>

                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="productSearch" autofocus
                    placeholder="Ketik min. 2 huruf nama / kode produk / design no..." class="mb-2" />

                <div class="max-h-80 overflow-y-auto tm-scroll">
                    @forelse ($this->productResults as $p)
                        <button type="button" wire:click="pickProduct({{ $p->id_product }})" wire:key="pp-{{ $p->id_product }}"
                            class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-navy truncate">{{ $p->product_name }}</div>
                                    <div class="text-[10.5px] text-muted font-mono">
                                        {{ $p->product_code }}@if ($p->design_no) &middot; design {{ $p->design_no }} @endif
                                        @if ((int) $p->customer_id === (int) $this->order->customer_id)
                                            <span class="text-[#16a34a] font-bold">&middot; produk customer ini</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-[11px] font-bold text-navy">{{ number_format((float) $p->default_selling_price, 0, ',', '.') }}</div>
                                    <div class="text-[9.5px] text-muted">{{ $p->uom_sales ?: 'Pcs' }}</div>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($productSearch)) < 2 ? 'Ketik min. 2 huruf untuk cari.' : 'Tidak ada produk cocok — buat dulu di Master Product.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
