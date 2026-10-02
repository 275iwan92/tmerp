<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerContact;
use App\Models\Master\FinCurrency;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoActivity;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationCc;
use App\Models\Sdi\QuoQuotationItem;
use App\Services\Sales\CoOrderBuilder;
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

    public bool $showLostForm = false;

    public string $lostReason = '';

    public bool $showActivityForm = false;

    public array $act = [];

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
            $q->setRelation('ccs', collect());

            return $q;
        }

        return QuoQuotation::with(['customer', 'ccs', 'items' => fn ($q) => $q->orderBy('line_seq')])->findOrFail($this->quotationId);
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

    // CC = kontak customer yang sama, selain orang Attn.
    #[Computed]
    public function ccOptions(): array
    {
        return collect($this->contactOptions)->keys()->push(...$this->quotation->ccs->pluck('contact_name'))
            ->unique()->reject(fn ($n) => $n === $this->quotation->attn_contact)
            ->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all();
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
            $current = $q->{$field} instanceof DateTimeInterface ? $q->{$field}->format('Y-m-d') : $q->{$field};

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
            $this->addError('chosen', t('Choose at least 1 inquiry.'));

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
        unset($this->quotation, $this->autoValues, $this->autoState, $this->contactOptions, $this->ccOptions, $this->salesOptions, $this->totals, $this->chooserRows, $this->outcome, $this->lostReasonText);
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

        if ($field === 'attn_contact' && $value) {
            $this->quotation->ccs()->where('contact_name', $value)->delete();
        }

        $this->refresh();
    }

    // $json = array nama dari x-form.multi-select; urutan pilih = urutan cetak.
    public function updateCc(string $json): void
    {
        $names = collect(json_decode($json, true) ?: [])->filter()->unique()
            ->reject(fn ($n) => $n === $this->quotation->attn_contact)->values();

        if (! $this->editable || $names->all() === $this->quotation->ccs->pluck('contact_name')->all()) {
            return;
        }

        $login = Auth::user()->login;
        $this->quotation->ccs()->delete();
        $names->each(fn ($name, $i) => QuoQuotationCc::create([
            'quotation_id' => $this->quotationId, 'line_seq' => $i + 1, 'contact_name' => $name, 'created_by' => $login,
        ]));
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

    // Siklus quotation: DRAFT -> PUBLISHED (confirmed) -> closed WON (CO lahir,
    // inquiry CONVERTED) atau closed LOST (inquiry LOST + alasan).
    #[Computed]
    public function outcome(): ?string
    {
        if (! $this->quotationId) {
            return null;
        }

        $this->quotation->loadMissing('items.inquiry');

        return $this->quotation->outcome();
    }

    #[Computed]
    public function lostReasonText(): ?string
    {
        return $this->quotation->items->pluck('inquiry.cancel_reason')->filter()->first();
    }

    public function openLostForm(): void
    {
        $this->lostReason = '';
        $this->showLostForm = true;
    }

    public function closeLostForm(): void
    {
        $this->showLostForm = false;
    }

    // Sama aturannya dgn markLost() di form Inquiry: semua inquiry di quotation
    // ini yang belum terminal ditandai LOST, alasan disimpan di cancel_reason.
    public function markLost(): void
    {
        if ($this->quotation->doc_status !== 'PUBLISHED' || $this->outcome) {
            return;
        }

        $this->validate(['lostReason' => ['required', 'string', 'max:255']], [], ['lostReason' => t('Lost Reason')]);

        $ids = $this->quotation->items->pluck('inquiry_id')->filter()->unique();

        IqpInquiry::whereIn('id_inquiry', $ids)
            ->whereNotIn('sub_state', ['CANCELLED', 'CONVERTED', 'LOST'])
            ->update(['cancel_reason' => $this->lostReason, 'sub_state' => 'LOST', 'updated_by' => Auth::user()->login]);

        $this->showLostForm = false;
        $this->refresh();
    }

    // ============== Progress follow-up Sales (activity log ala CRM) ==============

    #[Computed]
    public function activities()
    {
        return $this->quotationId ? $this->quotation->activities()->get() : collect();
    }

    public function openActivityForm(): void
    {
        $this->act = ['activity_type' => 'CALL', 'activity_date' => now()->toDateString(), 'contact_person' => $this->quotation->attn_contact,
            'result' => '', 'notes' => '', 'next_action_date' => '', 'next_action' => ''];
        $this->resetValidation();
        $this->showActivityForm = true;
    }

    public function closeActivityForm(): void
    {
        $this->showActivityForm = false;
    }

    public function setActType(string $type): void
    {
        $this->act['activity_type'] = $type;
    }

    public function setActResult(string $result): void
    {
        $this->act['result'] = ($this->act['result'] ?? '') === $result ? '' : $result;
    }

    public function saveActivity(): void
    {
        if (! $this->quotationId) {
            return;
        }

        $data = $this->validate([
            'act.activity_type' => ['required', 'in:'.implode(',', array_keys(QuoActivity::TYPES))],
            'act.activity_date' => ['required', 'date'],
            'act.contact_person' => ['nullable', 'string', 'max:150'],
            'act.result' => ['nullable', 'in:'.implode(',', array_keys(QuoActivity::RESULTS))],
            'act.notes' => ['nullable', 'string', 'max:2000'],
            'act.next_action_date' => ['nullable', 'date'],
            'act.next_action' => ['nullable', 'string', 'max:255'],
        ], [], ['act.activity_date' => t('Date'), 'act.activity_type' => t('Type')])['act'];

        QuoActivity::create(array_map(fn ($v) => $v === '' ? null : $v, $data) + [
            'quotation_id' => $this->quotationId,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        $this->showActivityForm = false;
        unset($this->activities);
    }

    public function deleteActivity(int $id): void
    {
        QuoActivity::where('quotation_id', $this->quotationId)->where('created_by', Auth::user()->login)->whereKey($id)->delete();
        unset($this->activities);
    }

    // QuoActivity::TYPES/RESULTS (model, dipakai juga layar v2) masih berlabel Indonesia: layar ini menampilkan English + t().
    private const ACT_TYPE_EN = ['CALL' => 'Phone call', 'VISIT' => 'Visit', 'WHATSAPP' => 'WhatsApp', 'EMAIL' => 'Email', 'MEETING' => 'Meeting', 'OTHER' => 'Other'];

    private const ACT_RESULT_EN = ['POSITIVE' => 'Positive', 'NEUTRAL' => 'Neutral', 'NEGATIVE' => 'Negative', 'NO_ANSWER' => 'No answer'];

    private function actTypeLabel(string $code): string
    {
        return t(self::ACT_TYPE_EN[$code] ?? $code);
    }

    private function actResultLabel(string $code): string
    {
        return t(self::ACT_RESULT_EN[$code] ?? $code);
    }

    // "1 CO = 1 Quotation" (keputusan user 2026-08-20) -- lihat App\Services\Sales\CoOrderBuilder.
    public function createOrOpenCo()
    {
        if ($this->outcome === 'LOST') {
            return;
        }

        $order = CoOrderBuilder::findOrCreateFromQuotation($this->quotation);

        if (! $order) {
            return;
        }

        return $this->redirect(route('co.entry', ['order' => $order->id_order]));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📄 {{ t('Quotation Entry') }}</b>
        <span class="text-xs text-white/75">{{ $this->quotation->quotation_no ?? t('NEW') }}@if ($this->quotation->customer) · {{ $this->quotation->customer->customer_name }}@endif</span>
        <x-form.badge :tone="$this->quotation->doc_status === 'PUBLISHED' ? 'success' : 'warn'">{{ $this->quotationId ? $this->quotation->doc_status : t('NEW') }}</x-form.badge>
        {{-- Cetak cuma boleh setelah diterbitkan (PUBLISHED) -- instruksi user
             2026-08-24, dikunci juga di sisi mount() quotation/print.blade.php. --}}
        @if ($this->quotation->doc_status === 'PUBLISHED')
            <button type="button" x-data @click="$dispatch('tm-print-open')" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md cursor-pointer hover:brightness-110">🖨️ {{ t('Print Preview') }}</button>
        @endif
        <x-back-button :fallback="route('quotation.list')" :title="t('Back to Quotation List')" class="ml-auto" />
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
                        <h3 class="text-xs text-navy uppercase font-bold flex-1">{{ t('Quotation Header') }}</h3>
                        <span class="text-[10.5px] text-muted"><x-form.auto-chip state="auto" /> = {{ t('filled automatically') }} · <x-form.auto-chip state="override" /> = {{ t('changed by officer (click to revert to automatic)') }}</span>
                    </div>
                    <div class="grid">
                        <x-form.input :label="t('Quotation No.')" readonly value="{{ $this->quotation->quotation_no ?? t('(automatic)') }}" class="bg-field-soft" />
                        <x-form.date-picker :label="t('Date')" value="{{ $this->quotation->issued_date?->format('Y-m-d') }}" :disabled="! $this->editable"
                            wire:change="updateHeader('issued_date', $event.target.value)" class="disabled:bg-field-soft" />
                        <div class="relative">
                            <x-form.date-picker :label="t('Valid Until')" value="{{ $this->quotation->valid_until?->format('Y-m-d') }}" :disabled="! $this->editable"
                                wire:change="updateHeader('valid_until', $event.target.value)" class="disabled:bg-field-soft" />
                            <x-form.auto-chip field="valid_until" :state="$this->autoState['valid_until'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.date-picker :label="t('Quotation Deadline')" value="{{ $this->quotation->deadline_date?->format('Y-m-d') }}" :disabled="! $this->editable"
                                wire:change="updateHeader('deadline_date', $event.target.value)" class="disabled:bg-field-soft" />
                            <x-form.auto-chip field="deadline_date" :state="$this->autoState['deadline_date'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="col-span-2 flex items-end gap-2">
                            <div class="flex-1">
                                <x-form.input :label="t('Customer (from inquiry)')" readonly value="{{ $this->quotation->customer ? $this->quotation->customer->customer_name.' ('.$this->quotation->customer->customer_code.')' : '' }}"
                                    :placeholder="t('Choose an inquiry first — customer is filled automatically')" class="bg-field-soft" />
                            </div>
                            @if (! $this->quotationId || $this->editable)
                                <x-ui.button variant="outline" size="sm" icon="fa-list-check" wire:click="openChooser">{{ t('Choose inquiry') }}</x-ui.button>
                            @endif
                        </div>
                        <div class="relative">
                            <x-form.select :label="t('Attn. (Contact)')" :disabled="! $this->editable" wire:change="updateHeader('attn_contact', $event.target.value)" class="disabled:bg-field-soft"
                                :selected="$this->quotation->attn_contact" :options="$this->contactOptions" />
                            <x-form.auto-chip field="attn_contact" :state="$this->autoState['attn_contact'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div wire:key="cc-{{ md5($this->quotation->attn_contact.'|'.$this->quotation->ccs->pluck('contact_name')->join('|')) }}">
                            @if ($this->editable)
                                <x-form.multi-select :label="t('CC')" wire:change="updateCc($event.target.value)" :placeholder="t('Choose CC contacts...')"
                                    :options="$this->ccOptions" :selected="$this->quotation->ccs->pluck('contact_name')->all()" />
                            @else
                                <x-form.input :label="t('CC')" readonly value="{{ $this->quotation->ccs->pluck('contact_name')->join(', ') }}" class="bg-field-soft" />
                            @endif
                        </div>
                        <div class="relative">
                            <x-form.select :label="t('Sales PIC')" :disabled="! $this->editable" wire:change="updateHeader('sales_pic', $event.target.value)" class="disabled:bg-field-soft"
                                :selected="$this->quotation->sales_pic" :options="$this->salesOptions" />
                            <x-form.auto-chip field="sales_pic" :state="$this->autoState['sales_pic'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.select :label="t('Terms of Payment')" :disabled="! $this->editable" wire:change="updateHeader('payment_term', $event.target.value)" class="disabled:bg-field-soft"
                                :selected="$this->quotation->payment_term" :options="$this->paymentTerms->pluck('term_label', 'payment_term_code')->all()" />
                            <x-form.auto-chip field="payment_term" :state="$this->autoState['payment_term'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.select :label="t('Tax Scheme')" :disabled="! $this->editable" wire:change="updateHeader('tax_scheme', $event.target.value)" class="disabled:bg-field-soft"
                                :placeholder="false" :selected="$this->quotation->tax_scheme" :options="['EXCL' => t('Exclusive VAT 11%'), 'INCL' => t('Inclusive VAT 11%')]" />
                            <x-form.auto-chip field="tax_scheme" :state="$this->autoState['tax_scheme'] ?? null" class="absolute top-0 right-0" />
                        </div>
                        <div class="relative">
                            <x-form.select :label="t('Currency')" :disabled="! $this->editable" wire:change="updateHeader('currency_code', $event.target.value)" class="disabled:bg-field-soft"
                                :placeholder="false" :selected="$this->quotation->currency_code" :options="$this->currencies" />
                            <x-form.auto-chip field="currency_code" :state="$this->autoState['currency_code'] ?? null" class="absolute top-0 right-0" />
                        </div>
                    </div>
                </div>

                {{-- ITEM LIST --}}
                <div class="bg-white border border-field-border rounded-md mb-4 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-field-soft border-b border-field-border">
                        <h3 class="text-xs text-navy uppercase font-bold">{{ t('Consolidated Inquiry List') }}</h3>
                        @if ($this->editable)
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="addFreeform" class="text-[11px] font-bold text-navy bg-field-badge border border-dashed border-field-border rounded px-2.5 py-1 cursor-pointer">＋ {{ t('Free Row') }}</button>
                                <button type="button" wire:click="openChooser" class="text-[11px] font-bold text-accent bg-accent-soft border border-dashed border-accent rounded px-2.5 py-1 cursor-pointer">＋ {{ t('Choose inquiry') }}</button>
                            </div>
                        @endif
                    </div>

                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-field-soft">
                                <th class="text-left px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">{{ t('Ref. Inquiry') }}</th>
                                <th class="text-left px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">{{ t('Description & Specification') }}</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">{{ t('Qty') }}</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">{{ t('Unit Price') }}</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">{{ t('Disc %') }}</th>
                                <th class="text-right px-2.5 py-2 text-[10px] text-muted uppercase font-bold border-b border-field-border">{{ t('Subtotal') }}</th>
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
                                            <x-form.input value="{{ $item->description }}" :placeholder="t('Row description (e.g. service/fee)...')"
                                                wire:change="updateItem({{ $item->id_item }}, 'description', $event.target.value)" class="!py-1 font-medium" />
                                        @else
                                            <div class="font-medium text-navy">{{ $item->description }}</div>
                                        @endif
                                        @if ($item->spec_note)
                                            <div class="text-[10px] text-muted mt-0.5">{{ $item->spec_note }}</div>
                                        @endif
                                        @if ($item->source_type === 'INQUIRY_TOOLING')
                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-[#fff5e0] text-[#8a5a00]">{{ t('TOOLING') }}</span>
                                        @elseif ($item->source_type === 'FREEFORM')
                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-[#ede9fe] text-[#5b21b6]">{{ t('FREEFORM') }}</span>
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
                                            <button type="button" wire:click="removeItem({{ $item->id_item }})" wire:confirm="{{ t('Delete this row?') }}" class="text-red-500 hover:text-red-700 cursor-pointer">✕</button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-8 text-muted">{{ t('No rows yet. Click "＋ Choose inquiry" to start.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- KANAN --}}
            <div class="lg:w-[320px] lg:shrink-0">
                <div class="bg-white border border-field-border rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-2.5">{{ t('Summary & Tax') }}</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">{{ t('Products & Services Subtotal') }}</span><span>{{ number_format($this->totals['subtotal'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-semibold border-t border-field-badge pt-1"><span>{{ t('Tax Base (DPP)') }}</span><span>{{ number_format($this->totals['dpp'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">{{ t('VAT') }} {{ number_format($this->totals['ppn_pct'], 0) }}%</span><span>{{ number_format($this->totals['ppn'], 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-bold text-accent border-t border-field-badge pt-1 text-[13px]"><span>{{ t('Grand Total') }}</span><span>{{ number_format($this->totals['grand'], 0, ',', '.') }}</span></div>
                    </div>
                </div>

                <div class="bg-white border border-field-border rounded-md p-4 mb-4">
                    <x-form.textarea :label="t('Terms & Conditions / Notes')" rows="4" :disabled="! $this->editable"
                        wire:model.blur="notes" :placeholder="t('Write special terms to be printed on the document...')" />
                </div>

                @if (! $this->quotationId || $this->quotation->doc_status === 'DRAFT')
                    <button type="button" wire:click="publish" wire:confirm="{{ t('Publish this quotation? Once published, the content can no longer be edited.') }}"
                        @disabled($this->quotation->items->isEmpty())
                        class="w-full px-4 py-2.5 rounded text-xs font-bold bg-accent text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ t('Issue Quotation') }}
                    </button>
                @elseif ($this->outcome === 'LOST')
                    <x-ui.card variant="muted" size="sm" class="mb-4">
                        <x-ui.badge tone="danger" pill icon="fa-xmark">{{ t('LOST') }}</x-ui.badge>
                        <p class="text-xs mt-2"><b>{{ t('Lost Reason') }}:</b> {{ $this->lostReasonText ?? '—' }}</p>
                    </x-ui.card>
                @else
                    <x-alert.banner type="success" :message="$this->outcome === 'WON' ? '✓ '.t('WON — Customer Order has been created') : '✓ '.t('Published — content locked')" />
                    <div class="flex flex-col gap-2 mt-2.5 mb-4">
                        <x-ui.button variant="success" block icon="fa-box" wire:click="createOrOpenCo">
                            {{ $this->outcome === 'WON' ? t('Open Customer Order') : t('Win → Create Customer Order') }}
                        </x-ui.button>
                        @if (! $this->outcome)
                            <x-ui.button variant="action-danger" block icon="fa-xmark" wire:click="openLostForm">{{ t('Lost → Enter Reason') }}</x-ui.button>
                        @endif
                    </div>
                @endif

                @if ($this->quotationId && $this->quotation->doc_status === 'PUBLISHED')
                    <x-ui.card variant="section" size="sm" :title="t('Follow-up').' ('.$this->activities->count().')'">
                        <x-slot:actions>
                            <x-ui.button size="xs" icon="fa-plus" wire:click="openActivityForm">{{ t('Add') }}</x-ui.button>
                        </x-slot:actions>
                        <div class="flex flex-wrap gap-1 mb-2">
                            @foreach (\App\Models\Sdi\QuoActivity::TYPES as $code => $t)
                                @if ($n = $this->activities->where('activity_type', $code)->count())
                                    <x-ui.badge tone="info" size="xs" pill>{{ $t[0] }} {{ $this->actTypeLabel($code) }} ×{{ $n }}</x-ui.badge>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex flex-col gap-2 max-h-[45vh] overflow-y-auto tm-scroll">
                            @forelse ($this->activities as $a)
                                <div wire:key="act-{{ $a->id_activity }}" class="border-l-2 border-accent pl-2.5 text-[11.5px]">
                                    <div class="flex items-center gap-1.5">
                                        <b>{{ \App\Models\Sdi\QuoActivity::TYPES[$a->activity_type][0] ?? '' }} {{ $this->actTypeLabel($a->activity_type) }}</b>
                                        <span class="text-muted">{{ $a->activity_date?->format('d M Y') }}</span>
                                        @if ($a->result)
                                            <x-ui.badge :tone="match ($a->result) { 'POSITIVE' => 'success', 'NEGATIVE' => 'danger', default => 'muted' }" size="xs" pill>{{ $this->actResultLabel($a->result) }}</x-ui.badge>
                                        @endif
                                        @if ($a->created_by === auth()->user()->login)
                                            <x-ui.icon-button icon="fa-trash" size="xs" class="ml-auto" wire:click="deleteActivity({{ $a->id_activity }})" wire:confirm="{{ t('Delete this follow-up note?') }}" />
                                        @endif
                                    </div>
                                    @if ($a->contact_person)<div class="text-muted">{{ $a->contact_person }}</div>@endif
                                    @if ($a->notes)<div>{{ $a->notes }}</div>@endif
                                    @if ($a->next_action)<div class="text-accent">→ {{ $a->next_action }}@if ($a->next_action_date) ({{ $a->next_action_date->format('d M') }})@endif</div>@endif
                                    <div class="text-[10px] text-muted">{{ $a->created_by }}</div>
                                </div>
                            @empty
                                <p class="text-[11px] text-muted">{{ t('No follow-up yet. Record every call, visit, or message to the customer.') }}</p>
                            @endforelse
                        </div>
                    </x-ui.card>
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
                        <iframe src="{{ route('quotation.print', ['quotation' => $this->quotationId]) }}" class="flex-1 w-full border-0" title="{{ t('Print Preview') }} {{ $this->quotation->quotation_no }}"></iframe>
                    </div>
                    <div class="flex-1 bg-black/40 cursor-pointer" @click="open = false" title="{{ t('Close') }}"></div>
                </div>
            </template>
        </div>
    @endif

    @if ($showLostForm)
        <x-ui.modal :title="t('Lost — reason')" close="closeLostForm" size="md">
            <div class="p-4 flex flex-col gap-2">
                <p class="text-[11px] text-muted">{{ t('All inquiries in this quotation are marked LOST. This reason appears in the Sales Win-Rate report.') }}</p>
                <x-form.textarea :label="t('Lost Reason *')" rows="3" wire:model="lostReason" :placeholder="t('E.g. competitor price is cheaper, customer cancelled the project, lead time too long...')" />
                @error('lostReason')<p class="text-[11px] text-danger-ink">{{ $message }}</p>@enderror
            </div>
            <x-slot:footer>
                <x-ui.button preset="cancel" wire:click="closeLostForm" />
                <x-ui.button variant="danger" icon="fa-xmark" wire:click="markLost">{{ t('Mark as Lost') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($showActivityForm)
        <x-ui.modal :title="t('Quotation follow-up')" close="closeActivityForm" size="lg">
            <div class="p-4 flex flex-col gap-3">
                <x-form.toggle-group :label="t('Kind *')">
                    @foreach (\App\Models\Sdi\QuoActivity::TYPES as $code => $t)
                        <x-form.toggle-option :active="($act['activity_type'] ?? '') === $code" wire:click="setActType('{{ $code }}')">{{ $t[0] }} {{ $this->actTypeLabel($code) }}</x-form.toggle-option>
                    @endforeach
                </x-form.toggle-group>
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="sm:w-48"><x-form.date-picker :label="t('Date *')" wire:model="act.activity_date" /></div>
                    <div class="flex-1"><x-form.input :label="t('Contact reached')" wire:model="act.contact_person" /></div>
                </div>
                <x-form.toggle-group :label="t('Result')">
                    @foreach (\App\Models\Sdi\QuoActivity::RESULTS as $code => $label)
                        <x-form.toggle-option :active="($act['result'] ?? '') === $code" wire:click="setActResult('{{ $code }}')">{{ $this->actResultLabel($code) }}</x-form.toggle-option>
                    @endforeach
                </x-form.toggle-group>
                <x-form.textarea :label="t('Notes')" rows="3" wire:model="act.notes" :placeholder="t('What was discussed, customer response...')" />
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="sm:w-48"><x-form.date-picker :label="t('Next action date')" wire:model="act.next_action_date" /></div>
                    <div class="flex-1"><x-form.input :label="t('Next follow-up action')" wire:model="act.next_action" :placeholder="t('E.g. call back, send a sample...')" /></div>
                </div>
                @if ($errors->any())<x-alert.banner type="danger" :message="$errors->first()" />@endif
            </div>
            <x-slot:footer>
                <x-ui.button preset="cancel" wire:click="closeActivityForm" />
                <x-ui.button preset="save" type="button" wire:click="saveActivity" />
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($showChooser)
        <x-ui.modal :title="t('Choose inquiry window')" close="closeChooser" size="2xl">
            <div class="p-4">
                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="chooserSearch" :placeholder="t('Search inquiry no. / product / customer...')" class="mb-2" />
                <p class="text-[11px] text-muted mb-2">{{ t('Inquiries whose costing is published and not yet in any quotation.') }} {{ t('One quotation = one customer; every qty tier becomes a separate row.') }}</p>
                @if ($this->quotation->customer_id && $this->chosenCustomerId && $this->chosenCustomerId !== (int) $this->quotation->customer_id)
                    <x-alert.banner type="warning" :message="t('Different customer from this quotation: the customer will be replaced and all old rows deleted.')" />
                @endif
                <div class="max-h-[50vh] overflow-y-auto tm-scroll border border-field-border rounded">
                    <x-ui.table size="sm" sticky hover>
                        <thead>
                            <tr><th class="w-8"></th><th>{{ t('Inquiry') }}</th><th>{{ t('Customer · Product') }}</th><th>{{ t('Date') }}</th><th class="text-right">{{ t('Tier') }}</th><th class="text-right">{{ t('Price/pcs') }}</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($this->chooserRows as $r)
                                <tr wire:key="ch-{{ $r->id }}" @class(['opacity-45' => $this->blocked($r->customer_id), 'cursor-pointer' => ! $this->blocked($r->customer_id)]) @if (! $this->blocked($r->customer_id)) wire:click="toggleChoose({{ $r->id }})" @endif>
                                    <td><input type="checkbox" @checked(in_array($r->id, $chosen, true)) @disabled($this->blocked($r->customer_id)) class="pointer-events-none"></td>
                                    <td class="font-mono font-bold text-accent">{{ $r->no }}</td>
                                    <td>
                                        <div class="font-semibold text-navy">{{ $r->customer }}</div>
                                        <div class="text-[10.5px] text-muted">{{ $r->product }}@if ($this->blocked($r->customer_id)) · {{ t('other customer') }} @endif</div>
                                    </td>
                                    <td>{{ $r->date?->format('d M Y') }}</td>
                                    <td class="text-right">{{ $r->tiers }}</td>
                                    <td class="text-right">{{ $r->price_min !== null ? number_format($r->price_min, 0, ',', '.').($r->price_max != $r->price_min ? ' – '.number_format($r->price_max, 0, ',', '.') : '') : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-6 text-muted">{{ t('No priced inquiries that are not yet in a quotation.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </div>
                @error('chosen')<p class="text-[11px] text-red-600 mt-1.5">{{ $message }}</p>@enderror
            </div>
            <x-slot:footer>
                <span class="text-xs text-muted mr-auto self-center">{{ count($chosen) ? str_replace(':n', count($chosen), t(':n inquiry selected')) : '' }}</span>
                <x-ui.button preset="cancel" wire:click="closeChooser" />
                <x-ui.button variant="primary" wire:click="applyChoice">{{ t('Choose') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
