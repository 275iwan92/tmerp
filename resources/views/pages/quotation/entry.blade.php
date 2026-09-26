<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerContact;
use App\Models\Master\FinCurrency;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use App\Services\Sales\QuotationBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// New dan Edit = satu form yang sama (user 2026-09-26). New langsung membuka
// "Choose inquiry window"; customer & header terisi otomatis dari inquiry/customer,
// officer boleh override. Customer tidak pernah diketik: menggantinya = pilih ulang inquiry.
new #[Title('Quotation Entry — TELASIH MAKMUR ERP')] class extends Component
{
    // null = form baru, belum ada baris di DB sampai inquiry dipilih.
    public ?int $quotationId = null;

    public bool $showChooser = false;

    public string $chooserSearch = '';

    public array $chosen = [];

    public string $notes = '';

    public function mount(?int $quotation = null): void
    {
        $this->quotationId = $quotation;
        $this->showChooser = $quotation === null;
        $this->notes = (string) ($quotation ? $this->quotation->notes : '');
    }

    public function updatedNotes(): void
    {
        $this->updateHeader('notes', $this->notes);
    }

    #[Computed]
    public function quotation(): QuoQuotation
    {
        if (! $this->quotationId) {
            $q = new QuoQuotation(['doc_status' => 'DRAFT', 'issued_date' => now(), 'tax_scheme' => 'EXCL', 'currency_code' => 'IDR']);
            $q->setRelation('items', collect());
            $q->setRelation('customer', null);

            return $q;
        }

        return QuoQuotation::with(['customer', 'items' => fn ($q) => $q->orderBy('line_seq')])->findOrFail($this->quotationId);
    }

    #[Computed]
    public function editable(): bool
    {
        return $this->quotationId && $this->quotation->doc_status === 'DRAFT';
    }

    #[Computed]
    public function paymentTerms()
    {
        return FinPaymentTerm::where('activate', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function currencies(): array
    {
        return FinCurrency::where('activate', true)->orderBy('currency_code')->pluck('currency_code', 'currency_code')->all();
    }

    // Kontak default = cust_customer_contact.is_default, cadangan cust_customer.contact_name.
    #[Computed]
    public function contactOptions(): array
    {
        $customer = $this->quotation->customer;

        if (! $customer) {
            return [];
        }

        $names = CustCustomerContact::where('customer_id', $customer->id_customer)->where('activate', true)
            ->orderByDesc('is_default')->pluck('contact_name')
            ->push($customer->contact_name, $this->quotation->attn_contact)
            ->filter()->unique()->values();

        return $names->combine($names)->all();
    }

    #[Computed]
    public function salesOptions(): array
    {
        $names = SalesPerson::where('activate', true)->orderBy('sales_name')->pluck('sales_name')
            ->push($this->quotation->sales_pic)->filter()->unique()->values();

        return $names->combine($names)->all();
    }

    #[Computed]
    public function autoValues(): array
    {
        $q = $this->quotation;

        if (! $q->customer_id) {
            return [];
        }

        $inquiryId = $q->items->where('source_type', 'INQUIRY_TIER')->pluck('inquiry_id')->filter()->first();
        $customer = $q->customer;
        $auto = QuotationBuilder::autoHeader($customer, $inquiryId ? IqpInquiry::find($inquiryId) : null, $q->issued_date);
        $default = CustCustomerContact::where('customer_id', $q->customer_id)->where('activate', true)->where('is_default', true)->value('contact_name');
        $auto['attn_contact'] = $default ?: $auto['attn_contact'];

        return $auto;
    }

    // [field => 'auto'|'override'] -- dibandingkan dengan nilai otomatis saat ini.
    #[Computed]
    public function autoState(): array
    {
        $q = $this->quotation;

        return collect($this->autoValues)->map(function ($auto, $field) use ($q) {
            $current = $q->{$field} instanceof \DateTimeInterface ? $q->{$field}->format('Y-m-d') : $q->{$field};

            return (string) $current === (string) $auto ? 'auto' : 'override';
        })->all();
    }

    #[Computed]
    public function totals(): array
    {
        return $this->quotation->totals();
    }

    // ---------- Choose inquiry window ----------

    #[Computed]
    public function chooserRows()
    {
        $query = QuotationBuilder::availableInquiries()->with(['costing.priceTiers', 'tiers']);

        if (($term = trim($this->chooserSearch)) !== '') {
            // Customer ada di tm2mst: cari ID-nya dulu, lalu whereIn (tanpa whereHas lintas DB).
            $customerIds = CustCustomer::where('customer_name', 'like', "%{$term}%")->pluck('id_customer');
            $query->where(fn ($q) => $q->where('inquiry_no', 'like', "%{$term}%")
                ->orWhere('product_draft_name', 'like', "%{$term}%")
                ->orWhereIn('customer_id', $customerIds));
        }

        $rows = $query->orderByDesc('inquiry_date')->limit(50)->get();
        $customers = CustCustomer::whereIn('id_customer', $rows->pluck('customer_id')->unique())->get()->keyBy('id_customer');

        return $rows->map(function ($inq) use ($customers) {
            $prices = $inq->costing?->priceTiers->pluck('price_per_pcs')->filter();

            return (object) [
                'id' => $inq->id_inquiry,
                'no' => $inq->inquiry_no,
                'date' => $inq->inquiry_date,
                'product' => $inq->product_draft_name,
                'customer_id' => (int) $inq->customer_id,
                'customer' => $customers[$inq->customer_id]->customer_name ?? '—',
                'tiers' => $inq->tiers->count(),
                'price_min' => $prices?->min(),
                'price_max' => $prices?->max(),
            ];
        });
    }

    #[Computed]
    public function chosenCustomerId(): ?int
    {
        $id = collect($this->chosen)->first();

        return $id ? (int) IqpInquiry::whereKey($id)->value('customer_id') : null;
    }

    public function openChooser(): void
    {
        if ($this->quotationId && ! $this->editable) {
            return;
        }

        $this->chosen = [];
        $this->chooserSearch = '';
        $this->showChooser = true;
    }

    public function closeChooser(): void
    {
        $this->showChooser = false;
        $this->chosen = [];
    }

    public function toggleChoose(int $inquiryId): void
    {
        if (in_array($inquiryId, $this->chosen, true)) {
            $this->chosen = array_values(array_diff($this->chosen, [$inquiryId]));
        } elseif (! $this->chosenCustomerId || (int) IqpInquiry::whereKey($inquiryId)->value('customer_id') === $this->chosenCustomerId) {
            $this->chosen[] = $inquiryId;
        }

        unset($this->chosenCustomerId);
    }

    public function blocked(int $customerId): bool
    {
        return $this->chosenCustomerId && $customerId !== $this->chosenCustomerId;
    }

    public function applyChoice()
    {
        if (! $this->chosen) {
            $this->addError('chosen', 'Pilih minimal 1 inquiry.');

            return;
        }

        if (! $this->quotationId) {
            $quotation = QuotationBuilder::createFromInquiries($this->chosen);

            return $this->redirect(route('quotation.entry', ['quotation' => $quotation->id_quotation]));
        }

        if (! $this->editable) {
            return;
        }

        QuotationBuilder::applyInquiries($this->quotation, $this->chosen);
        $this->closeChooser();
        $this->refresh();
    }

    // ---------- Header & baris ----------

    private function refresh(): void
    {
        unset($this->quotation, $this->autoValues, $this->autoState, $this->contactOptions, $this->salesOptions, $this->totals, $this->chooserRows);
    }

    public function updateHeader(string $field, $value): void
    {
        $allowed = ['issued_date', 'valid_until', 'deadline_date', 'payment_term', 'tax_scheme', 'currency_code', 'attn_contact', 'sales_pic', 'notes'];

        if (! $this->editable || ! in_array($field, $allowed, true)) {
            return;
        }

        $changes = [$field => $value === '' ? null : $value];

        // Ganti tanggal dokumen: Berlaku s/d & Deadline ikut bergeser selama belum di-override.
        if ($field === 'issued_date' && $value) {
            $state = $this->autoState;
            $newAuto = QuotationBuilder::autoHeader(null, null, $value);
            foreach (['valid_until', 'deadline_date'] as $f) {
                if (($state[$f] ?? null) === 'auto') {
                    $changes[$f] = $newAuto[$f];
                }
            }
        }

        $this->quotation->update($changes + ['updated_by' => Auth::user()->login]);
        $this->refresh();
    }

    public function resetAuto(string $field): void
    {
        if ($this->editable && array_key_exists($field, $this->autoValues)) {
            $this->updateHeader($field, (string) $this->autoValues[$field]);
        }
    }

    public function addFreeform(): void
    {
        if (! $this->editable) {
            return;
        }

        QuoQuotationItem::create([
            'quotation_id' => $this->quotationId,
            'line_seq' => ((int) $this->quotation->items()->max('line_seq')) + 1,
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

        $this->refresh();
    }

    public function removeItem(int $itemId): void
    {
        if (! $this->editable) {
            return;
        }

        QuoQuotationItem::where('quotation_id', $this->quotationId)->whereKey($itemId)->delete();
        $this->refresh();
    }

    public function updateItem(int $itemId, string $field, $value): void
    {
        if (! $this->editable || ! in_array($field, ['description', 'qty', 'qty_unit', 'unit_price', 'discount_pct'], true)) {
            return;
        }

        $item = QuoQuotationItem::where('quotation_id', $this->quotationId)->findOrFail($itemId);
        $item->{$field} = $value === '' ? 0 : $value;
        $item->recalcSubtotal();
        $item->updated_by = Auth::user()->login;
        $item->save();

        $this->refresh();
    }

    public function publish(): void
    {
        if (! $this->editable || $this->quotation->items->isEmpty()) {
            return;
        }

        $this->quotation->update(['doc_status' => 'PUBLISHED', 'updated_by' => Auth::user()->login]);
        $this->refresh();
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
        <span class="text-xs text-white/75">{{ $this->quotation->quotation_no ?? 'NEW' }}@if ($this->quotation->customer) · {{ $this->quotation->customer->customer_name }}@endif</span>
        <x-form.badge :tone="$this->quotation->doc_status === 'PUBLISHED' ? 'success' : 'warn'">{{ $this->quotationId ? $this->quotation->doc_status : 'NEW' }}</x-form.badge>
        {{-- Cetak cuma boleh setelah diterbitkan (PUBLISHED) -- instruksi user
             2026-08-24, dikunci juga di sisi mount() quotation/print.blade.php. --}}
        @if ($this->quotation->doc_status === 'PUBLISHED')
            <button type="button" x-data @click="$dispatch('tm-print-open')" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md cursor-pointer hover:brightness-110">🖨️ Preview Cetak</button>
        @endif
        <x-back-button :fallback="route('quotation.list')" title="Kembali ke Daftar Quotation" class="ml-auto" />
    </header>

    <div class="max-w-[1280px] mx-auto my-5 px-4">
        <x-alert.banner type="danger" :message="session('error')" />
        {{-- flex, BUKAN class="grid" -- app.css .grid{} unlayered override
             grid-cols/lg:grid-cols manapun jadi 4 kolom sama rata (lihat catatan
             di co/entry.blade.php & docs/FORM_UI_STANDARD.md §2). --}}
        <div class="flex flex-col lg:flex-row gap-4">

            {{-- KIRI --}}
            <div class="lg:flex-1 min-w-0">
                {{-- HEADER --}}
                <div class="bg-white border border-field-border rounded-md p-4 mb-4">
                    <div class="flex items-center mb-3">
                        <h3 class="text-xs text-navy uppercase font-bold flex-1">Header Quotation</h3>
                        <span class="text-[10.5px] text-muted"><x-form.auto-chip state="auto" /> = terisi otomatis · <x-form.auto-chip state="override" /> = diubah officer (klik untuk kembali ke otomatis)</span>
                    </div>
                    <div class="grid">
                        <x-form.input label="No. Quotation" readonly value="{{ $this->quotation->quotation_no ?? '(otomatis)' }}" class="bg-field-soft" />
                        <x-form.date-picker label="Tanggal" value="{{ $this->quotation->issued_date?->format('Y-m-d') }}" :disabled="! $this->editable"
                            wire:change="updateHeader('issued_date', $event.target.value)" class="disabled:bg-field-soft" />
                        <div class="relative">
                            <x-form.date-picker label="Berlaku s/d" value="{{ $this->quotation->valid_until?->format('Y-m-d') }}" :disabled="! $this->editable"
                                wire:change="updateHeader('valid_until', $event.target.value)" class="disabled:bg-field-soft" />
                            <x-form.auto-chip field="valid_until" :state="$this->autoState['valid_until'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.date-picker label="Deadline Quotation" value="{{ $this->quotation->deadline_date?->format('Y-m-d') }}" :disabled="! $this->editable"
                                wire:change="updateHeader('deadline_date', $event.target.value)" class="disabled:bg-field-soft" />
                            <x-form.auto-chip field="deadline_date" :state="$this->autoState['deadline_date'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="col-span-2 flex items-end gap-2">
                            <div class="flex-1">
                                <x-form.input label="Customer (dari inquiry)" readonly value="{{ $this->quotation->customer ? $this->quotation->customer->customer_name.' ('.$this->quotation->customer->customer_code.')' : '' }}"
                                    placeholder="Pilih inquiry dulu — customer terisi otomatis" class="bg-field-soft" />
                            </div>
                            @if (! $this->quotationId || $this->editable)
                                <x-ui.button variant="outline" size="sm" icon="fa-list-check" wire:click="openChooser">Choose inquiry</x-ui.button>
                            @endif
                        </div>
                        <div class="relative">
                            <x-form.select label="Attn. (Contact)" :disabled="! $this->editable" wire:change="updateHeader('attn_contact', $event.target.value)" class="disabled:bg-field-soft"
                                :selected="$this->quotation->attn_contact" :options="$this->contactOptions" />
                            <x-form.auto-chip field="attn_contact" :state="$this->autoState['attn_contact'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.select label="PIC Sales" :disabled="! $this->editable" wire:change="updateHeader('sales_pic', $event.target.value)" class="disabled:bg-field-soft"
                                :selected="$this->quotation->sales_pic" :options="$this->salesOptions" />
                            <x-form.auto-chip field="sales_pic" :state="$this->autoState['sales_pic'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.select label="Termin Pembayaran" :disabled="! $this->editable" wire:change="updateHeader('payment_term', $event.target.value)" class="disabled:bg-field-soft"
                                :selected="$this->quotation->payment_term" :options="$this->paymentTerms->pluck('term_label', 'payment_term_code')->all()" />
                            <x-form.auto-chip field="payment_term" :state="$this->autoState['payment_term'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.select label="Skema Pajak" :disabled="! $this->editable" wire:change="updateHeader('tax_scheme', $event.target.value)" class="disabled:bg-field-soft"
                                :placeholder="false" :selected="$this->quotation->tax_scheme" :options="['EXCL' => 'Exclusive PPN 11%', 'INCL' => 'Inclusive PPN 11%']" />
                            <x-form.auto-chip field="tax_scheme" :state="$this->autoState['tax_scheme'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.select label="Mata Uang" :disabled="! $this->editable" wire:change="updateHeader('currency_code', $event.target.value)" class="disabled:bg-field-soft"
                                :placeholder="false" :selected="$this->quotation->currency_code" :options="$this->currencies" />
                            <x-form.auto-chip field="currency_code" :state="$this->autoState['currency_code'] ?? null" class="absolute top-0 right-0" />
                        </div>
                    </div>
                </div>

                {{-- ITEM LIST --}}
                <div class="bg-white border border-field-border rounded-md mb-4 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-field-soft border-b border-field-border">
                        <h3 class="text-xs text-navy uppercase font-bold">Daftar Inquiry Terkonsolidasi</h3>
                        @if ($this->editable)
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="addFreeform" class="text-[11px] font-bold text-navy bg-field-badge border border-dashed border-field-border rounded px-2.5 py-1 cursor-pointer">＋ Baris Bebas</button>
                                <button type="button" wire:click="openChooser" class="text-[11px] font-bold text-accent bg-accent-soft border border-dashed border-accent rounded px-2.5 py-1 cursor-pointer">＋ Choose inquiry</button>
                            </div>
                        @endif
                    </div>

                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-field-soft">
                                <th class="text-left px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">Ref. Inquiry</th>
                                <th class="text-left px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">Deskripsi & Spesifikasi</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">Qty</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">Harga Satuan</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">Disc %</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">Subtotal</th>
                                @if ($this->editable)
                                    <th class="px-2.5 py-2 border-b border-field-border"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->quotation->items as $item)
                                <tr class="border-b border-field-badge" wire:key="item-{{ $item->id_item }}">
                                    <td class="px-2.5 py-2 font-mono font-bold text-accent">{{ $item->inquiry?->inquiry_no ?? '—' }}</td>
                                    <td class="px-2.5 py-2">
                                        @if ($item->source_type === 'FREEFORM' && $this->editable)
                                            <x-form.input value="{{ $item->description }}" placeholder="Deskripsi baris (mis. jasa/ongkos)..."
                                                wire:change="updateItem({{ $item->id_item }}, 'description', $event.target.value)" class="!py-1 font-medium" />
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
                                        @if ($this->editable)
                                            <x-form.input type="number" step="0.01" value="{{ $item->qty }}" wire:change="updateItem({{ $item->id_item }}, 'qty', $event.target.value)" class="w-20 !py-1 text-right" />
                                        @else
                                            {{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2 text-right">
                                        @if ($this->editable)
                                            <x-form.input type="number" step="0.01" value="{{ $item->unit_price }}" wire:change="updateItem({{ $item->id_item }}, 'unit_price', $event.target.value)" class="w-24 !py-1 text-right" />
                                        @else
                                            {{ number_format($item->unit_price, 0, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2 text-right">
                                        @if ($this->editable)
                                            <x-form.input type="number" step="0.1" value="{{ $item->discount_pct }}" wire:change="updateItem({{ $item->id_item }}, 'discount_pct', $event.target.value)" class="w-14 !py-1 text-right" />
                                        @else
                                            {{ number_format($item->discount_pct, 1) }}%
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2 text-right font-bold text-navy">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                    @if ($this->editable)
                                        <td class="px-2.5 py-2 text-center">
                                            <button type="button" wire:click="removeItem({{ $item->id_item }})" wire:confirm="Hapus baris ini?" class="text-red-500 hover:text-red-700 cursor-pointer">✕</button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-8 text-muted">Belum ada baris. Klik "＋ Choose inquiry" untuk mulai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- KANAN --}}
            <div class="lg:w-[320px] lg:shrink-0">
                <div class="bg-white border border-field-border rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-2.5">Summary &amp; Pajak</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Subtotal Produk &amp; Layanan</span><span>{{ number_format($this->totals['subtotal'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-semibold border-t border-field-badge pt-1"><span>Dasar Pengenaan Pajak (DPP)</span><span>{{ number_format($this->totals['dpp'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">PPN {{ number_format($this->totals['ppn_pct'], 0) }}%</span><span>{{ number_format($this->totals['ppn'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-bold text-accent border-t border-field-badge pt-1 text-[13px]"><span>Grand Total</span><span>{{ number_format($this->totals['grand'], 0, ',', '.') }}</span></div>
                    </div>
                </div>

                <div class="bg-white border border-field-border rounded-md p-4 mb-4">
                    <x-form.textarea label="Syarat & Ketentuan / Catatan" rows="4" :disabled="! $this->editable"
                        wire:model.blur="notes" placeholder="Tuliskan syarat khusus yang tercetak di dokumen..." />
                </div>

                @if (! $this->quotationId || $this->quotation->doc_status === 'DRAFT')
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

    {{-- Preview cetak = panel dari kiri di atas form (user 2026-09-26), bukan tab baru:
         Tutup/Esc/klik area gelap -> panel & iframe-nya hilang (x-if). --}}
    @if ($this->quotation->doc_status === 'PUBLISHED')
        <div wire:ignore x-data="{ open: false }" @tm-print-open.window="open = true" @keydown.escape.window="open = false"
            @message.window="if ($event.origin === location.origin && $event.data === 'tm-print-close') open = false">
            <template x-if="open">
                <div class="fixed inset-0 z-[100] flex">
                    <div class="w-full md:w-[65vw] h-full bg-white shadow-2xl flex flex-col" x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full">
                        <iframe src="{{ route('quotation.print', ['quotation' => $this->quotationId]) }}" class="flex-1 w-full border-0" title="Preview cetak {{ $this->quotation->quotation_no }}"></iframe>
                    </div>
                    <div class="flex-1 bg-black/40 cursor-pointer" @click="open = false" title="Tutup"></div>
                </div>
            </template>
        </div>
    @endif

    @if ($showChooser)
        <x-ui.modal title="Choose inquiry window" close="closeChooser" size="2xl">
            <div class="p-4">
                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="chooserSearch" placeholder="Cari no. inquiry / produk / customer..." class="mb-2" />
                <p class="text-[11px] text-muted mb-2">Inquiry yang costing-nya sudah published dan belum masuk quotation mana pun. Satu quotation = satu customer; semua jenjang qty masuk sebagai baris terpisah.</p>
                @if ($this->quotation->customer_id && $this->chosenCustomerId && $this->chosenCustomerId !== (int) $this->quotation->customer_id)
                    <x-alert.banner type="warning" message="Customer berbeda dari quotation ini: customer akan diganti dan semua baris lama dihapus." />
                @endif
                <div class="max-h-[50vh] overflow-y-auto tm-scroll border border-field-border rounded">
                    <x-ui.table size="sm" sticky hover>
                        <thead>
                            <tr><th class="w-8"></th><th>Inquiry</th><th>Customer · Produk</th><th>Tanggal</th><th class="text-right">Tier</th><th class="text-right">Harga/pcs</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($this->chooserRows as $r)
                                <tr wire:key="ch-{{ $r->id }}" @class(['opacity-45' => $this->blocked($r->customer_id), 'cursor-pointer' => ! $this->blocked($r->customer_id)]) @if (! $this->blocked($r->customer_id)) wire:click="toggleChoose({{ $r->id }})" @endif>
                                    <td><input type="checkbox" @checked(in_array($r->id, $chosen, true)) @disabled($this->blocked($r->customer_id)) class="pointer-events-none"></td>
                                    <td class="font-mono font-bold text-accent">{{ $r->no }}</td>
                                    <td>
                                        <div class="font-semibold text-navy">{{ $r->customer }}</div>
                                        <div class="text-[10.5px] text-muted">{{ $r->product }}@if ($this->blocked($r->customer_id)) · customer lain @endif</div>
                                    </td>
                                    <td>{{ $r->date?->format('d M Y') }}</td>
                                    <td class="text-right">{{ $r->tiers }}</td>
                                    <td class="text-right">{{ $r->price_min !== null ? number_format($r->price_min, 0, ',', '.').($r->price_max != $r->price_min ? ' – '.number_format($r->price_max, 0, ',', '.') : '') : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-6 text-muted">Tidak ada inquiry berharga yang belum masuk quotation.</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </div>
                @error('chosen')<p class="text-[11px] text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>
            <x-slot:footer>
                <span class="text-xs text-muted mr-auto self-center">{{ count($chosen) ? count($chosen).' inquiry dipilih' : '' }}</span>
                <x-ui.button preset="cancel" wire:click="closeChooser" />
                <x-ui.button variant="primary" wire:click="applyChoice">Choose</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
