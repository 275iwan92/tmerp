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
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $trim = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp

<x-mp::sales.flow-steps current="quotation" note="Quotation = official offer to the customer. Confirm locks it; WIN creates the Customer Order and the Master Product." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $q && ! request('pick'))
    <x-dl::grid id="quotation-list" :rows="Quotation::rows()" search="Cari no. quotation, customer, sales..." actions="worklist" :active-key="null"
        :new-url="$home.'?pick=1'" :open-url="$home.'?id={id}'" :confirm-when="['status' => '__none__']"
        source="{{ Src::of('tm2sdi.quo_quotation', 'tm2sdi.quo_quotation_item', 'tm2sdi.quo_activity', 'tm2sdi.iqp_inquiry.sub_state') }}"
        :columns="[
            ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'valid', 'label' => 'Berlaku s/d', 'type' => 'date'],
            ['key' => 'sales', 'label' => 'Sales PIC'],
            ['key' => 'lines', 'label' => 'Baris', 'type' => 'number'],
            ['key' => 'total', 'label' => 'Grand total', 'type' => 'money', 'sum' => true],
            ['key' => 'followups', 'label' => 'Follow-up', 'type' => 'number'],
            ['key' => 'next', 'label' => 'Tindak lanjut berikut', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Quotation::STATUS_TONES],
        ]"
        :stats="[
            ['label' => 'Semua'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Terbit (menunggu customer)', 'where' => ['status' => 'PUBLISHED'], 'tone' => 'warn'],
            ['label' => 'WIN', 'where' => ['status' => 'WON'], 'tone' => 'success'],
            ['label' => 'LOST', 'where' => ['status' => 'LOST'], 'tone' => 'danger'],
            ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'neutral'],
        ]"
        :filters="['status' => 'Status', 'sales' => 'Sales PIC']" />
@elseif (! $q && ! request('inq'))
    <x-dl::card title="+ New Record — pilih inquiry yang ditawarkan" description="Hanya inquiry yang sudah berharga (Pro-Cost published) dan belum ada di quotation lain. Klik 2x baris.">
        <x-dl::grid id="quotation-pick" :rows="Quotation::available()" search="Cari no. inkuiri, product, customer..." :new-record="null" actions="worklist" :active-key="null"
            :open-url="$home.'?pick=1&inq={id}'" source="{{ Src::of('tm2sdi.iqp_inquiry', 'tm2sdi.iqp_costing.published_flag', 'tm2sdi.iqp_costing_price_tier') }}"
            :columns="[
                ['key' => 'product', 'label' => 'Product', 'type' => 'identity', 'code' => 'no'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
                ['key' => 'type', 'label' => 'Jenis', 'type' => 'badge', 'tones' => ['NEW' => 'info', 'RECALC' => 'accent', 'REVISE' => 'warn']],
                ['key' => 'tiers', 'label' => 'Jenjang qty', 'type' => 'number'],
                ['key' => 'price', 'label' => 'Harga /pcs'],
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
        <x-dl::card :title="'Quotation baru — '.($same[0]['customer'] ?? 'customer')" description="Satu quotation = satu customer. Centang inquiry lain milik customer yang sama bila ikut ditawarkan; semua jenjang qty jadi baris.">
            @if (! $same)
                <x-dl::alert tone="warn" message="Inquiry ini sudah tidak tersedia (sudah ditawarkan atau belum berharga)." />
            @else
                <x-dl::field.checkboxes name="inquiry_ids" label="Inquiry yang ditawarkan" required :value="[(string) request('inq')]"
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
    @php
        $c = Quotation::component($q->id_quotation);
        $status = Quotation::status($q);
        $draft = $q->doc_status === 'DRAFT';
        $totals = $q->totals();
        $order = \App\Models\Sdi\CoOrder::where('quotation_id', $q->id_quotation)->first();
        $lostReason = $q->items->pluck('inquiry.cancel_reason')->filter()->first();
        $more = $draft ? Quotation::available((int) $q->customer_id) : [];
    @endphp
    <form method="post" action="{{ route('v2.quotation.save', $q->id_quotation) }}" id="quotation-form" data-dl-guard>
        @csrf
        <x-dl::card>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <span class="mp-chips">
                    <x-dl::badge :label="$q->quotation_no" />
                    <x-dl::badge :label="$status" :tone="Quotation::STATUS_TONES[$status] ?? 'neutral'" />
                    @if ($order)<x-dl::badge tone="success" :label="'CO '.$order->co_no" />@endif
                </span>
                <x-dl::button preset="print" :href="route('quotation.print', $q->id_quotation)" target="_blank" />
                <x-dl::doc-share target="#quotation-form" type="quotation" :doc-id="$q->id_quotation" :number="$q->quotation_no" title="Quotation" />
            </x-dl::action-bar>
            <h2 class="mp-bigname">{{ $q->customer?->customer_name ?? '—' }}</h2>
            <p class="mp-hint">Customer mengikuti inquiry yang ditawarkan — untuk mengganti customer buat quotation baru.</p>
            @if ($status === 'LOST')<x-dl::alert tone="danger" :message="'LOST — '.($lostReason ?: 'tanpa alasan')" />@endif
            @if ($status === 'CANCELLED')<x-dl::alert tone="neutral" :message="'Cancelled — '.($q->cancel_reason ?: 'tanpa alasan')" />@endif
            @if ($status === 'WON' && $order)<x-dl::alert tone="success" :message="'WIN — Customer Order '.$order->co_no.' sudah dibuat.'" />@endif
        </x-dl::card>

        <x-dl::card title="Header" description="Terisi otomatis dari customer & inquiry; boleh diubah selama DRAFT. Ganti tanggal dokumen = Berlaku s/d & Deadline ikut bergeser (kecuali sudah diubah manual).">
            <x-dl::form.row cols="3">
                <x-dl::field.select name="attn_contact" label="Attn (kontak)" :options="$c->contactOptions()" :value="old('attn_contact', $q->attn_contact)" :disabled="! $draft" :source="Src::of('tm2mst.cust_customer_contact', 'tm2sdi.quo_quotation.attn_contact')" />
                <x-dl::field.multi-select name="cc" label="CC" :options="collect($c->ccOptions())->pluck('label', 'value')->all()" :value="$q->ccs->pluck('contact_name')->all()" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation_cc')" />
                <x-dl::field.select name="sales_pic" label="Sales PIC" :options="$c->salesOptions()" :value="old('sales_pic', $q->sales_pic)" :disabled="! $draft" :source="Src::of('tm2mst.sales_person', 'tm2sdi.quo_quotation.sales_pic')" />
            </x-dl::form.row>
            <input type="hidden" name="cc_present" value="1">
            <x-dl::form.row cols="3">
                <x-dl::field.date name="issued_date" label="Tanggal dokumen" required :value="old('issued_date', $q->issued_date?->toDateString())" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation.issued_date')" />
                <x-dl::field.date name="valid_until" label="Berlaku s/d" :value="old('valid_until', $q->valid_until?->toDateString())" :disabled="! $draft" hint="Otomatis +30 hari" :source="Src::of('tm2sdi.quo_quotation.valid_until')" />
                <x-dl::field.date name="deadline_date" label="Deadline jawaban" :value="old('deadline_date', $q->deadline_date?->toDateString())" :disabled="! $draft" hint="Otomatis +14 hari" :source="Src::of('tm2sdi.quo_quotation.deadline_date')" />
            </x-dl::form.row>
            <x-dl::form.row cols="3">
                <x-dl::field.select name="payment_term" label="Termin pembayaran" :options="FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all()" :value="old('payment_term', $q->payment_term)" :disabled="! $draft" :source="Src::of('tm2mst.fin_payment_term')" />
                <x-dl::field.select name="currency_code" label="Mata uang" :options="FinCurrency::where('activate', true)->orderBy('currency_code')->pluck('currency_code', 'currency_code')->all()" :value="old('currency_code', $q->currency_code)" :disabled="! $draft" :source="Src::of('tm2mst.fin_currency')" />
                <x-dl::field.select name="tax_scheme" label="Skema pajak" :options="['EXCL' => 'Exclusive PPN 11%', 'INCL' => 'Inclusive PPN 11%']" :value="old('tax_scheme', $q->tax_scheme)" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation.tax_scheme')" />
            </x-dl::form.row>
            <x-dl::field.text name="notes" label="Catatan untuk customer" multiline :value="old('notes', $q->notes)" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation.notes')" />
        </x-dl::card>

        <x-dl::card title="Baris penawaran" description="Jenjang qty dari inquiry (harga dari Pro-Cost) + biaya sekali (pisau, plat, film). Harga boleh disesuaikan sebelum Confirm.">
            @forelse ($q->items as $it)
                <x-dl::form.row cols="6">
                    <x-dl::field.text :name="'items['.$it->id_item.'][description]'" :label="$loop->iteration.'. Uraian'" :value="$it->description" :disabled="! $draft"
                        :hint="$it->inquiry ? $it->inquiry->inquiry_no.($it->source_type === 'INQUIRY_TOOLING' ? ' · tooling' : '') : 'baris bebas'" :source="Src::of('tm2sdi.quo_quotation_item.description')" />
                    <x-dl::field.decimal :name="'items['.$it->id_item.'][qty]'" label="Qty" :decimals="0" :value="$trim($it->qty)" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation_item.qty')" />
                    <x-dl::field.text :name="'items['.$it->id_item.'][qty_unit]'" label="Satuan" :value="$it->qty_unit" :case-toggle="false" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation_item.qty_unit')" />
                    <x-dl::field.decimal :name="'items['.$it->id_item.'][unit_price]'" label="Harga satuan" :value="$trim($it->unit_price)" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation_item.unit_price')" />
                    <x-dl::field.decimal :name="'items['.$it->id_item.'][discount_pct]'" label="Diskon" unit="%" :value="$trim($it->discount_pct)" :disabled="! $draft" :source="Src::of('tm2sdi.quo_quotation_item.discount_pct')" />
                    <x-dl::field.display label="Subtotal" :value="$rp($it->subtotal)" :source="Src::of('tm2sdi.quo_quotation_item.subtotal')" />
                </x-dl::form.row>
                @if ($draft)<x-dl::field.checkbox :name="'remove['.$it->id_item.']'" text="Hapus baris ini saat Simpan" />@endif
            @empty
                <x-dl::empty-state title="Belum ada baris" />
            @endforelse
            @if ($draft)
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="plus" label="+ Tambah Baris" type="submit" name="action" value="add_line" />
                    @if ($more)<x-dl::button variant="secondary" icon="plus" label="+ Tambah Inquiry" data-dl-open="quotation-add" />@endif
                </x-dl::action-bar>
            @endif
            <x-dl::form.row cols="4">
                <x-dl::field.display label="Subtotal" :value="$rp($totals['subtotal'])" />
                <x-dl::field.display label="DPP" :value="$rp($totals['dpp'])" />
                <x-dl::field.display :label="'PPN '.$totals['ppn_pct'].'%'" :value="$rp($totals['ppn'])" />
                <x-dl::field.display label="Grand total" :value="$rp($totals['grand'])" :source="Src::of('QuoQuotation::totals()')" />
            </x-dl::form.row>
        </x-dl::card>

        <x-dl::action-bar>
            @if ($draft)
                <x-dl::button preset="delete" type="submit" :formaction="route('v2.quotation.delete', $q->id_quotation)" formnovalidate onclick="return confirm('Hapus draft {{ $q->quotation_no }}? Inquiry-nya kembali siap ditawarkan.')" />
                <x-dl::button preset="save" name="action" value="save" />
                <x-dl::button preset="confirm" type="submit" name="action" value="confirm" onclick="return confirm('Confirm quotation ini? Form terkunci dan siap dikirim ke customer.')" />
            @elseif ($status === 'PUBLISHED')
                <x-dl::button preset="cancel" data-dl-open="quotation-cancel" />
                <x-dl::button variant="danger" icon="reject" label="LOST" data-dl-open="quotation-lost" />
                <x-dl::button variant="success" icon="approve" label="WIN — Buat Customer Order" type="submit" :formaction="route('v2.quotation.win', $q->id_quotation)" formnovalidate onclick="return confirm('Customer setuju? Customer Order dibuat dari quotation ini.')" />
            @elseif ($status === 'WON' && $order)
                <x-dl::button icon="send" label="Buka Customer Order" :href="route('v2.screen', 'customer-order').'?id='.$order->id_order" />
            @endif
        </x-dl::action-bar>
    </form>

    <x-dl::card title="Follow-up customer" description="Catatan telepon / kunjungan / WhatsApp / email sampai customer memutuskan.">
        <x-dl::table :columns="['date' => 'Tanggal', 'type' => 'Jenis', 'contact' => 'Kontak', 'result' => 'Hasil', 'notes' => 'Catatan', 'next' => 'Tindak lanjut', 'by' => 'Oleh']"
            :rows="$q->activities->map(fn ($a) => ['date' => $a->activity_date?->format('d M Y'), 'type' => implode(' ', QuoActivity::TYPES[$a->activity_type] ?? [$a->activity_type]),
                'contact' => $a->contact_person ?? '—', 'result' => QuoActivity::RESULTS[$a->result] ?? '—', 'notes' => $a->notes ?? '—',
                'next' => trim(($a->next_action_date?->format('d M Y') ?? '').' '.($a->next_action ?? '')) ?: '—', 'by' => $a->created_by])->all()"
            empty="Belum ada follow-up." source="{{ Src::of('tm2sdi.quo_activity') }}" />
        @if (in_array($status, ['DRAFT', 'PUBLISHED'], true))
            <x-dl::action-bar><x-dl::button variant="secondary" icon="plus" label="+ Tambah Follow-up" data-dl-open="quotation-activity" /></x-dl::action-bar>
        @endif
    </x-dl::card>

    @if ($more)
        <x-dl::modal id="quotation-add" title="Tambah inquiry customer yang sama" icon="plus">
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
                <x-dl::field.text name="cancel_reason" label="Alasan batal" required />
                <x-dl::action-bar><x-dl::button preset="save" label="Cancel dokumen" variant="danger" /></x-dl::action-bar>
            </form>
        </x-dl::modal>
        <x-dl::modal id="quotation-lost" title="Tutup sebagai LOST" icon="reject">
            <form method="post" action="{{ route('v2.quotation.lost', $q->id_quotation) }}">
                @csrf
                <x-dl::field.text name="lost_reason" label="Alasan kalah (harga, waktu, kompetitor, ...)" required />
                <x-dl::action-bar><x-dl::button preset="save" label="Tandai LOST" variant="danger" /></x-dl::action-bar>
            </form>
        </x-dl::modal>
    @endif
    <x-dl::modal id="quotation-activity" title="Tambah follow-up" icon="plus">
        <form method="post" action="{{ route('v2.quotation.activity', $q->id_quotation) }}">
            @csrf
            <x-dl::form.row cols="2">
                <x-dl::field.select name="act[activity_type]" label="Jenis" required :options="collect(QuoActivity::TYPES)->map(fn ($t) => implode(' ', $t))->all()" value="CALL" />
                <x-dl::field.date name="act[activity_date]" label="Tanggal" required :value="now()->toDateString()" />
            </x-dl::form.row>
            <x-dl::form.row cols="2">
                <x-dl::field.select name="act[contact_person]" label="Kontak" :options="$c->contactOptions()" :value="$q->attn_contact" />
                <x-dl::field.select name="act[result]" label="Hasil" :options="QuoActivity::RESULTS" />
            </x-dl::form.row>
            <x-dl::field.text name="act[notes]" label="Catatan" multiline />
            <x-dl::form.row cols="2">
                <x-dl::field.date name="act[next_action_date]" label="Tindak lanjut tanggal" />
                <x-dl::field.text name="act[next_action]" label="Tindak lanjut apa" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
        </form>
    </x-dl::modal>
@endif
