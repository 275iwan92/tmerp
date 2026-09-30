{{-- Quotation v2. Flow & aturan = tmerp pages/quotation/entry.blade.php (lewat App\Support\V2\Data\Quotation).
     Alur layar: GRID quotation -> + New Record = pilih inquiry siap ditawarkan (Ready to Quote) -> centang inquiry customer yang
     sama -> DRAFT (header otomatis dari customer/inquiry, boleh diubah; baris = semua jenjang qty + tooling) -> Simpan / Hapus ->
     Confirm (terbit, terkunci) -> Print / Email ke customer + catat follow-up -> WIN (Customer Order lahir) / LOST / Cancel. --}}
@php
    use App\Models\Master\FinCurrency;
    use App\Models\Master\FinPaymentTerm;
    use App\Models\Sdi\IqpInquiry;
    use App\Models\Sdi\QuoActivity;
    use App\Models\Sdi\QuoQuotation;
    use App\Support\V2\Data\Quotation;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'quotation');
    $q = request('id') ? QuoQuotation::with(['customer', 'ccs', 'items.inquiry', 'activities'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $q, 404);
    $rp = fn ($v) => 'Rp '.\Devlib\Locale::number((float) $v, 0);
    $trim = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp

<x-mp::sales.flow-steps current="quotation" note="Quotation = official offer to the customer. Confirm locks it; WIN creates the Customer Order and the Master Product." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $q && ! request('pick'))
    <x-dl::grid id="quotation-list" :rows="Quotation::rows()" search="Search quotation no., customer, sales..." actions="worklist" :active-key="null"
        :new-url="$home.'?pick=1'" :open-url="$home.'?id={id}'"
        source="{{ Src::of('tm2sdi.quo_quotation', 'tm2sdi.quo_quotation_item', 'tm2sdi.quo_activity', 'tm2sdi.iqp_inquiry.sub_state') }}"
        :columns="[
            ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
            ['key' => 'valid', 'label' => 'Berlaku s/d', 'type' => 'date'],
            ['key' => 'sales', 'label' => 'Sales PIC'],
            ['key' => 'lines', 'label' => t('Lines'), 'type' => 'number'],
            ['key' => 'total', 'label' => 'Grand total', 'type' => 'money', 'sum' => true],
            ['key' => 'followups', 'label' => 'Follow-up', 'type' => 'number'],
            ['key' => 'next', 'label' => 'Tindak lanjut berikut', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Quotation::STATUS_TONES],
        ]"
        :stats="[
            ['label' => t('All')],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => t('Issued (waiting for customer)'), 'where' => ['status' => 'PUBLISHED'], 'tone' => 'warn'],
            ['label' => 'WIN', 'where' => ['status' => 'WON'], 'tone' => 'success'],
            ['label' => 'LOST', 'where' => ['status' => 'LOST'], 'tone' => 'danger'],
            ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'neutral'],
        ]"
        :filters="['status' => 'Status', 'sales' => 'Sales PIC']" />
@elseif (! $q && ! request('inq'))
    <x-dl::card title="+ New Record — choose the inquiries to quote" description="Only priced inquiries (Pro-Cost published) not yet in another quotation. Double-click a row.">
        <x-dl::grid id="quotation-pick" :rows="Quotation::available()" search="Search inquiry no., product, customer..." :new-record="null" actions="worklist" :active-key="null"
            :open-url="$home.'?pick=1&inq={id}'" source="{{ Src::of('tm2sdi.iqp_inquiry', 'tm2sdi.iqp_costing.published_flag', 'tm2sdi.iqp_costing_price_tier') }}"
            :columns="[
                ['key' => 'product', 'label' => 'Product', 'type' => 'identity', 'code' => 'no'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
                ['key' => 'type', 'label' => t('Type'), 'type' => 'badge', 'tones' => ['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn']],
                ['key' => 'tiers', 'label' => 'Jenjang qty', 'type' => 'number'],
                ['key' => 'price', 'label' => t('Price /pc')],
            ]" />
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    </x-dl::card>
@elseif (! $q)
    @php
        $first = IqpInquiry::find((int) request('inq'));
        $same = $first ? Quotation::available((int) $first->customer_id) : [];
    @endphp
    <form method="post" action="{{ route('v2.quotation.create') }}">
        @csrf
        <x-dl::card :title="'Quotation baru — '.($same[0]['customer'] ?? 'customer')" description="One quotation = one customer. Tick other inquiries of the same customer if they are quoted too; every qty tier becomes a line.">
            @if (! $same)
                <x-dl::alert tone="warn" message="This inquiry is no longer available (already quoted or not yet priced)." />
            @else
                <x-dl::field.checkboxes name="inquiry_ids" label="Quoted inquiries" required :value="[(string) request('inq')]"
                    :options="collect($same)->mapWithKeys(fn ($r) => [$r['id'] => $r['no'].' · '.$r['product'].' · '.$r['tiers'].' jenjang'.($r['price'] ? ' · Rp '.$r['price'].'/pcs' : '')])->all()"
                    :source="Src::of('tm2sdi.iqp_inquiry')" />
            @endif
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home.'?pick=1'" />
                @if ($same)<x-dl::button preset="save" label="Buat Quotation" />@endif
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    {{-- Form Master-Detail DevLib (disetujui user 2026-09-30): panel aksi + tahapan -> ringkasan -> tab header -> Details di tengah
         -> footer (Tax + Summary + terbilang). Data & simpan = v1 quotation/entry lewat Quotation::save() (items[id][..], remove[id],
         action=save|confirm|add_line|delete); Cancel / LOST / WIN tetap lewat modal & rute masing-masing. Total = QuoQuotation::totals(). --}}
    @php
        $c = Quotation::component($q->id_quotation);
        $status = Quotation::status($q);
        $draft = $q->doc_status === 'DRAFT';
        $totals = $q->totals();
        $order = \App\Models\Sdi\CoOrder::where('quotation_id', $q->id_quotation)->first();
        $lostReason = $q->items->pluck('inquiry.cancel_reason')->filter()->first();
        $more = $draft ? Quotation::available((int) $q->customer_id) : [];
        $cur = $q->currency_code ?: 'IDR';
        $sym = \Devlib\Terbilang::CURRENCIES[$cur][0] ?? 'Rp';
        $money = fn ($v) => $sym.' '.\Devlib\Locale::number((float) $v, 0);
        // WON (CO lahir = pindah modul) & LOST = selesai -> Closed; PUBLISHED = Confirmed (terbit, terkunci).
        $formStatus = match ($status) { 'DRAFT' => 'draft', 'PUBLISHED' => 'confirmed', 'CANCELLED' => 'cancelled', default => 'closed' };
        $lines = $q->items->map(fn ($it) => [
            'id_item' => $it->id_item, 'description' => $it->description, 'qty' => (float) $it->qty, 'qty_unit' => $it->qty_unit,
            'unit_price' => (float) $it->unit_price, 'discount_pct' => (float) $it->discount_pct,
            'source' => $it->inquiry ? $it->inquiry->inquiry_no.($it->source_type === 'INQUIRY_TOOLING' ? ' · tooling (biaya sekali)' : ' · jenjang qty') : 'Baris bebas',
        ])->values()->all();
    @endphp
    @if ($status === 'LOST')<x-dl::alert tone="danger" :message="'LOST — '.($lostReason ?: 'tanpa alasan')" />@endif
    @if ($status === 'CANCELLED')<x-dl::alert tone="info" :message="'Cancelled — '.($q->cancel_reason ?: 'tanpa alasan')" />@endif
    @if ($status === 'WON' && $order)<x-dl::alert tone="success" :message="'WIN — Customer Order '.$order->co_no.' sudah dibuat.'" />@endif

    <x-dl::form id="quotation-form" :title="'Quotation · '.$q->quotation_no" :subtitle="($q->customer?->customer_name ?? '—').' · '.$status"
        panel="top" kind="document" :status="$formStatus" :pipeline="true"
        :action="route('v2.quotation.save', $q->id_quotation)" action-field="action" :back="$home" :new-url="$home.'?pick=1'"
        :hide="['print', 'email', 'cancel']"
        :tabs="['general' => 'General', 'finance' => 'Finance', 'notes' => 'Notes']"
        :created-by="$q->created_by" :created-at="$q->created_at?->toDateTimeString()" :updated-by="$q->updated_by" :updated-at="$q->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" preset="print" label="Nota" :href="route('quotation.print', $q->id_quotation)" target="_blank" />
            <x-dl::doc-share size="sm" target="#quotation-form" type="quotation" :doc-id="$q->id_quotation" :number="$q->quotation_no" title="Quotation" />
            @if ($status === 'PUBLISHED')
                <x-dl::button size="sm" preset="cancel" variant="danger" data-dl-open="quotation-cancel" />
                <x-dl::button size="sm" variant="danger" icon="reject" label="LOST" data-dl-open="quotation-lost" />
                <x-dl::button size="sm" variant="success" icon="approve" label="WIN — Buat Customer Order" type="submit" :formaction="route('v2.quotation.win', $q->id_quotation)" formnovalidate onclick="return confirm('Customer setuju? Customer Order dibuat dari quotation ini.')" />
            @elseif ($status === 'WON' && $order)
                <x-dl::button size="sm" variant="secondary" icon="fa-file-invoice" label="Buka Customer Order" :href="route('v2.screen', 'customer-order').'?id='.$order->id_order" />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No. Quotation</span><b>{{ $q->quotation_no }}</b></div>
            <x-dl::field.date name="issued_date" label="Document date" required :value="old('issued_date', $q->issued_date?->toDateString())" :source="Src::of('tm2sdi.quo_quotation.issued_date')" />
            <x-dl::field.display label="Customer" :value="$q->customer?->customer_name ?? '—'" hint="Follows the inquiry; a different customer = a new quotation." :source="Src::of('tm2mst.cust_customer')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Grand Total</span><b>{{ $money($totals['grand']) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="attn_contact" label="Attn (kontak)" :options="$c->contactOptions()" :value="old('attn_contact', $q->attn_contact)" :source="Src::of('tm2mst.cust_customer_contact', 'tm2sdi.quo_quotation.attn_contact')" />
                <x-dl::field.multi-select name="cc" label="CC" :options="collect($c->ccOptions())->pluck('label', 'value')->all()" :value="$q->ccs->pluck('contact_name')->all()" :source="Src::of('tm2sdi.quo_quotation_cc')" />
                <x-dl::field.select name="sales_pic" label="Sales PIC" :options="$c->salesOptions()" :value="old('sales_pic', $q->sales_pic)" :source="Src::of('tm2mst.sales_person', 'tm2sdi.quo_quotation.sales_pic')" />
            </x-dl::form.row>
            <input type="hidden" name="cc_present" value="1">
            <x-dl::form.row :cols="3">
                <x-dl::field.date name="valid_until" label="Berlaku s/d" :value="old('valid_until', $q->valid_until?->toDateString())" hint="Automatic +30 days" :source="Src::of('tm2sdi.quo_quotation.valid_until')" />
                <x-dl::field.date name="deadline_date" label="Deadline jawaban" :value="old('deadline_date', $q->deadline_date?->toDateString())" hint="Automatic +14 days" :source="Src::of('tm2sdi.quo_quotation.deadline_date')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:finance>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="currency_code" label="Currency" :options="FinCurrency::where('activate', true)->orderBy('currency_code')->pluck('currency_code', 'currency_code')->all()" :value="old('currency_code', $q->currency_code)" :source="Src::of('tm2mst.fin_currency')" />
                <x-dl::field.select name="payment_term" label="Payment Terms" :options="FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all()" :value="old('payment_term', $q->payment_term)" :source="Src::of('tm2mst.fin_payment_term')" />
            </x-dl::form.row>
        </x-slot:finance>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes for the customer (printed)" multiline :value="old('notes', $q->notes)" :source="Src::of('tm2sdi.quo_quotation.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="items" title="Details" key-field="id_item" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2sdi.quo_quotation_item') }}"
                :columns="[
                    ['key' => 'description', 'label' => 'Uraian', 'width' => '340px'],
                    ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'width' => '110px'],
                    ['key' => 'qty_unit', 'label' => t('Unit'), 'width' => '90px'],
                    ['key' => 'unit_price', 'label' => t('Unit price'), 'type' => 'money', 'width' => '130px'],
                    ['key' => 'discount_pct', 'label' => 'Disc %', 'type' => 'number', 'width' => '80px'],
                    ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * unit_price * (1 - discount_pct / 100)', 'width' => '140px'],
                    ['key' => 'source', 'label' => t('Line Source'), 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        <x-dl::button size="sm" variant="secondary" :label="t('+ Add Row')" type="submit" name="action" value="add_line" formnovalidate />
                        @if ($more)<x-dl::button size="sm" variant="secondary" :label="t('+ Add Inquiry')" data-dl-open="quotation-add" />@endif
                    </x-slot:tools>
                @endif
            </x-dl::lines>
            <x-dl::doc-footer :tabs="['tax' => 'Tax']"
                :words="\Devlib\Terbilang::words(round((float) $totals['grand']), $cur, $cur === 'IDR' ? 'id' : 'en')"
                :summary="[
                    ['Details', $money($totals['subtotal'])],
                    ['DPP', $money($totals['dpp']), 'dpp'],
                    ['PPN '.$totals['ppn_pct'].'%', $money($totals['ppn'])],
                    ['Grand Total', $money($totals['grand']), 'grand'],
                ]">
                <x-slot:tax>
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="tax_scheme" label="Tax scheme" :options="['EXCL' => 'Exclusive PPN 11%', 'INCL' => 'Inclusive PPN 11%']" :value="old('tax_scheme', $q->tax_scheme)" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation.tax_scheme')" />
                    </x-dl::form.row>
                </x-slot:tax>
            </x-dl::doc-footer>
        </x-slot:detail>
    </x-dl::form>

    <x-dl::card title="Follow-up customer" description="Phone / visit / WhatsApp / email notes until the customer decides.">
        <x-dl::table :columns="['date' => 'Tanggal', 'type' => 'Jenis', 'contact' => 'Kontak', 'result' => 'Hasil', 'notes' => 'Catatan', 'next' => 'Tindak lanjut', 'by' => 'Oleh']"
            :rows="$q->activities->map(fn ($a) => ['date' => \Devlib\Locale::d($a->activity_date), 'type' => implode(' ', QuoActivity::TYPES[$a->activity_type] ?? [$a->activity_type]),
                'contact' => $a->contact_person ?? '—', 'result' => QuoActivity::RESULTS[$a->result] ?? '—', 'notes' => $a->notes ?? '—',
                'next' => trim((\Devlib\Locale::d($a->next_action_date) ?? '').' '.($a->next_action ?? '')) ?: '—', 'by' => $a->created_by])->all()"
            empty="Belum ada follow-up." source="{{ Src::of('tm2sdi.quo_activity') }}" />
        @if (in_array($status, ['DRAFT', 'PUBLISHED'], true))
            <x-dl::action-bar><x-dl::button variant="secondary" icon="plus" :label="t('+ Add Follow-up')" data-dl-open="quotation-activity" /></x-dl::action-bar>
        @endif
    </x-dl::card>

    @if ($more)
        <x-dl::modal id="quotation-add" title="Add inquiries of the same customer" icon="plus">
            <form method="post" action="{{ route('v2.quotation.add-inquiry', $q->id_quotation) }}">
                @csrf
                <x-dl::field.checkboxes name="inquiry_ids" label="Inquiry siap ditawarkan" :options="collect($more)->mapWithKeys(fn ($r) => [$r['id'] => $r['no'].' · '.$r['product']])->all()" />
                <x-dl::action-bar><x-dl::button preset="apply" type="submit" /></x-dl::action-bar>
            </form>
        </x-dl::modal>
    @endif
    @if ($status === 'PUBLISHED')
        <x-dl::modal id="quotation-cancel" title="Cancel quotation" icon="cancel">
            <form method="post" action="{{ route('v2.quotation.cancel', $q->id_quotation) }}">
                @csrf
                <x-dl::field.text name="cancel_reason" label="Cancellation reason" required />
                <x-dl::action-bar><x-dl::button preset="save" label="Cancel dokumen" variant="danger" /></x-dl::action-bar>
            </form>
        </x-dl::modal>
        <x-dl::modal id="quotation-lost" title="Close as LOST" icon="reject">
            <form method="post" action="{{ route('v2.quotation.lost', $q->id_quotation) }}">
                @csrf
                <x-dl::field.text name="lost_reason" label="Reason lost (price, time, competitor, ...)" required />
                <x-dl::action-bar><x-dl::button preset="save" label="Tandai LOST" variant="danger" /></x-dl::action-bar>
            </form>
        </x-dl::modal>
    @endif
    <x-dl::modal id="quotation-activity" title="Add follow-up" icon="plus">
        <form method="post" action="{{ route('v2.quotation.activity', $q->id_quotation) }}">
            @csrf
            <x-dl::form.row cols="2">
                <x-dl::field.select name="act[activity_type]" label="Type" required :options="collect(QuoActivity::TYPES)->map(fn ($t) => implode(' ', $t))->all()" value="CALL" />
                <x-dl::field.date name="act[activity_date]" label="Date" required :value="now()->toDateString()" />
            </x-dl::form.row>
            <x-dl::form.row cols="2">
                <x-dl::field.select name="act[contact_person]" label="Kontak" :options="$c->contactOptions()" :value="$q->attn_contact" />
                <x-dl::field.select name="act[result]" label="Hasil" :options="QuoActivity::RESULTS" />
            </x-dl::form.row>
            <x-dl::field.text name="act[notes]" label="Notes" multiline />
            <x-dl::form.row cols="2">
                <x-dl::field.date name="act[next_action_date]" label="Follow-up date" />
                <x-dl::field.text name="act[next_action]" label="Tindak lanjut apa" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
        </form>
    </x-dl::modal>
@endif
