{{-- Customer Order v2. Flow & aturan = tmerp pages/co/list + entry.blade.php (lewat App\Support\V2\Data\CustomerOrder).
     Alur layar: GRID CO -> + New Record = pilih jalur: INTEGRATED (dari Quotation terbit, baris = snapshot quotation) atau
     INSTANT (pilih customer, baris PRODUCT dari Master Product) -> DRAFT: header, baris + due date, jadwal kirim, biaya luar
     -> Simpan / Hapus (draft INSTANT) -> Confirm (wajib due date tiap baris PRODUCT; INSTANT + Master Product) -> engine CPDEL
     membuat jadwal kirim default -> Print / Email. --}}
@php
    use App\Models\Master\FinPaymentTerm;
    use App\Models\Master\SalesPerson;
    use App\Models\Sdi\CoOrder;
    use App\Support\V2\Data\CustomerOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'customer-order');
    $o = request('id') ? CoOrder::with(['customer', 'quotation', 'items.inquiry', 'items.product', 'items.deliveries', 'externalCosts'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $o, 404);
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $trim = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp

<x-mp::sales.flow-steps current="order" note="Customer Order = the order is confirmed. Every product line needs a due date; Confirm creates the default shipping schedule." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $o && ! request('new'))
    <x-dl::grid id="co-list" :rows="CustomerOrder::rows()" search="Cari no. CO, customer, no. PO, quotation..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'"
        source="{{ Src::of('tm2sdi.co_order', 'tm2sdi.co_order_item', 'tm2sdi.co_order_external_cost', 'tm2sdi.quo_quotation.quotation_no') }}"
        :columns="[
            ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'mode', 'label' => 'Jalur', 'type' => 'badge', 'tones' => CustomerOrder::MODE_TONES],
            ['key' => 'quotation', 'label' => 'Quotation'],
            ['key' => 'po', 'label' => 'No. PO customer'],
            ['key' => 'lines', 'label' => 'Baris', 'type' => 'number'],
            ['key' => 'due', 'label' => 'Due date terdekat', 'type' => 'date'],
            ['key' => 'total', 'label' => 'Total tagihan', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => CustomerOrder::STATUS_TONES],
        ]"
        :stats="[
            ['label' => 'Semua'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
        ]"
        :filters="['mode' => 'Jalur', 'status' => 'Status']" />
@elseif (! $o)
    @php $quotes = CustomerOrder::quotationChoices(); @endphp
    <x-dl::card title="+ New Record — pilih jalur Customer Order" description="Dua jalur masuk, dokumen & lanjutannya (CPDEL, produksi) sama.">
        <div class="mp-grid2">
            <form method="post" action="{{ route('v2.co.create') }}">
                @csrf
                <input type="hidden" name="mode" value="INTEGRATED">
                <x-dl::card title="INTEGRATED — dari Quotation" description="Customer setuju penawaran. Baris & harga = snapshot quotation (1 Quotation = 1 CO).">
                    @if ($quotes)
                        <x-dl::field.select name="quotation_id" label="Quotation terbit" required :options="collect($quotes)->mapWithKeys(fn ($q) => [$q['id'] => $q['no'].' · '.$q['customer'].' · '.$q['lines'].' baris · '.$rp($q['total'])])->all()" :source="Src::of('tm2sdi.quo_quotation (PUBLISHED, belum ada CO)')" />
                        <x-dl::action-bar><x-dl::button preset="save" label="Buat dari Quotation" /></x-dl::action-bar>
                    @else
                        <x-dl::empty-state title="Tidak ada quotation terbit yang belum menjadi CO" />
                    @endif
                </x-dl::card>
            </form>
            <form method="post" action="{{ route('v2.co.create') }}">
                @csrf
                <input type="hidden" name="mode" value="INSTANT">
                <x-dl::card title="INSTANT — tanpa Quotation" description="Order langsung (repeat order / verbal). Baris product wajib dari Master Product customer.">
                    <x-dl::field.select name="customer_id" label="Customer" required :options="CustomerOrder::customerOptions()" :source="Src::of('tm2mst.cust_customer')" />
                    <x-dl::action-bar><x-dl::button preset="save" label="Buat Instant" /></x-dl::action-bar>
                </x-dl::card>
            </form>
        </div>
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    </x-dl::card>
@else
    {{-- Form Master-Detail DevLib (disetujui user 2026-09-30): panel aksi + tahapan -> ringkasan -> tab header -> Details di tengah
         -> footer (Discount / Tax / Other Costs + Summary + terbilang). Data & simpan = v1 co/entry lewat CustomerOrder::save()
         (field items[id][..], deliveries[id][..], costs[id][..], remove*[id], action=...); total dari CoOrder::totals() (server). --}}
    @php
        $c = CustomerOrder::component($o->id_order);
        $draft = $o->doc_status === 'DRAFT';
        $totals = $o->totals();
        $missingDue = $o->items->where('line_kind', 'PRODUCT')->whereNull('due_date');
        $missingProduct = $o->itemsMissingProduct();
        $num = fn ($v) => number_format((float) $v, 0, ',', '.');
        $area = $o->ship_area_id ? $o->ship_area_level.':'.$o->ship_area_id : null;
        $cur = $o->currency_code ?? 'IDR';
        $sym = \Devlib\Terbilang::CURRENCIES[$cur][0] ?? 'Rp';
        $money = fn ($v) => $sym.' '.number_format((float) $v, 0, ',', '.');
        $lines = $o->items->map(fn ($it) => [
            'id_item' => $it->id_item, 'description' => $it->description, 'qty' => (float) $it->qty, 'qty_unit' => $it->qty_unit,
            'unit_price' => (float) $it->unit_price, 'discount_pct' => (float) $it->discount_pct, 'due_date' => $it->due_date?->toDateString() ?? '',
            'editable' => $draft && $it->quotation_item_id === null,
            'kind' => $it->line_kind === 'PRODUCT' ? 'Product' : 'Jasa', 'inquiry' => $it->inquiry?->inquiry_no ?? '—',
            'source' => $it->quotation_item_id !== null ? 'Snapshot quotation (terkunci)' : 'Manual', 'spec_note' => $it->spec_note ?? '—',
            'product_label' => $it->product_id ? ($it->product?->product_name ?? '#'.$it->product_id) : ($it->line_kind === 'PRODUCT' ? 'belum terkait' : '—'),
        ])->values()->all();
        $log = collect($c->historyTimeline())->map(fn ($r) => ['at' => $r['date'], 'by' => '', 'action' => $r['type'].(! empty($r['current']) ? ' (ini)' : ''), 'note' => $r['label']])->reverse()->values()->all();
    @endphp
    @if ($draft && $missingDue->isNotEmpty())<x-dl::alert tone="warn" :message="'⚠ '.$missingDue->count().' baris Product belum ada Due Date (wajib sebelum Confirm).'" />@endif
    @if ($draft && $missingProduct->isNotEmpty())<x-dl::alert tone="warn" :message="'⚠ '.$missingProduct->count().' baris belum terkait Master Product.'" />@endif

    <x-dl::form id="co-form" :title="'Customer Order · '.$o->co_no" :subtitle="($o->customer?->customer_name ?? '—').' · '.$o->entry_mode"
        panel="top" kind="document" :status="$draft ? 'draft' : strtolower($o->doc_status)" :pipeline="true"
        :action="route('v2.co.save', $o->id_order)" action-field="action" :back="$home" :new-url="$home.'?new=1'"
        :hide="array_merge(['print', 'email', 'cancel'], $o->isInstant() ? [] : ['delete'])"
        :tabs="['general' => 'General', 'finance' => 'Finance', 'delivery' => 'Delivery', 'notes' => 'Notes']"
        :created-by="$o->created_by" :created-at="$o->created_at?->toDateTimeString()" :updated-by="$o->updated_by" :updated-at="$o->updated_at?->toDateTimeString()" :log="$log">
        <x-slot:special>
            <x-dl::button size="sm" preset="print" label="Nota" :href="route('co.print', $o->id_order)" target="_blank" />
            <x-dl::doc-share size="sm" target="#co-form" type="customer-order" :doc-id="$o->id_order" :number="$o->co_no" title="Customer Order" />
            @unless ($draft)<x-dl::button size="sm" variant="secondary" icon="fa-truck" label="Customer Plan Delivery" :href="route('v2.screen', 'cpdel')" />@endunless
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No. CO</span><b>{{ $o->co_no }}</b></div>
            <x-dl::field.date name="co_date" label="CO Date" :value="old('co_date', $o->co_date?->toDateString())" :source="Src::of('tm2sdi.co_order.co_date')" />
            <x-dl::field.display label="Customer" :value="($o->customer?->customer_name ?? '—').' ('.($o->customer?->customer_code ?? '—').')'" :source="Src::of('tm2mst.cust_customer')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Net Receivable</span><b>{{ $money($totals['net_receivable']) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Ref. Quotation" :value="$o->quotation?->quotation_no ?? '— (Instant, tanpa Quotation)'" :source="Src::of('tm2sdi.co_order.quotation_id')" />
                <x-dl::field.text name="customer_po_no" label="Cust. PO No." :case-toggle="false" placeholder="PO/customer/..." :value="old('customer_po_no', $o->customer_po_no)" :source="Src::of('tm2sdi.co_order.customer_po_no')" />
                <x-dl::field.display label="Order Type" :value="$c->orderType()" :source="Src::calc('jenis baris', 'tm2sdi.co_order_item.line_kind')" />
                <x-dl::field.select name="sales_person_id" label="PIC Sales" placeholder="— pilih —" :options="SalesPerson::where('activate', true)->orderBy('sales_name')->pluck('sales_name', 'id_sales_person')->all()" :value="old('sales_person_id', $o->sales_person_id)" :source="Src::of('tm2mst.sales_person')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:finance>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Currency" :value="$cur" :source="Src::of('tm2sdi.co_order.currency_code')" />
                <x-dl::field.select name="payment_term" label="Payment Terms" placeholder="— pilih —" :options="FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all()" :value="old('payment_term', $o->payment_term)" :source="Src::of('tm2mst.fin_payment_term')" />
            </x-dl::form.row>
        </x-slot:finance>
        <x-slot:delivery>
            <x-dl::form.row :cols="3">
                <x-dl::field.lookup name="ship_area" lookup="geo_area" label="Shipment Location" placeholder="Cari kelurahan / kecamatan / kota / kode pos..." :value="$area" :source="Src::of('tm2sdi.co_order.ship_area_id', 'ship_area_level', 'ship_address_label')" />
                <x-dl::field.text name="ship_address_detail" label="Detail Alamat (jalan, no., RT/RW, patokan)" placeholder="Jl. ..., No. ..." :value="old('ship_address_detail', $o->ship_address_detail)" :source="Src::of('tm2sdi.co_order.ship_address_detail')" />
                <x-dl::field.display name="ship_postal_code" label="Kode Pos" :value="$o->ship_postal_code ?? '—'" :source="Src::of('tm2sdi.co_order.ship_postal_code')" />
            </x-dl::form.row>
            {{-- Jadwal kirim per baris Product (kosong = saat Confirm dibuat otomatis 1 baris qty penuh @ Due Date). --}}
            @foreach ($o->items->where('line_kind', 'PRODUCT') as $it)
                <x-dl::divider :label="$it->description.' · '.$num($it->qty).' '.$it->qty_unit.' · due '.($it->due_date?->format('d-M-Y') ?? '—')" />
                @forelse ($it->deliveries as $d)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.select :name="'deliveries['.$d->id_delivery.'][shipment_type]'" label="Type" :options="['FULL' => 'FULL', 'PARTIAL' => 'PARTIAL']" :value="$d->shipment_type" :source="Src::of('tm2sdi.co_order_delivery.shipment_type')" />
                        <x-dl::field.date :name="'deliveries['.$d->id_delivery.'][ship_date]'" label="Ship Date" :value="$d->ship_date?->toDateString()" :source="Src::of('tm2sdi.co_order_delivery.ship_date')" />
                        <x-dl::field.decimal :name="'deliveries['.$d->id_delivery.'][ship_qty]'" label="Qty" :decimals="0" :value="rtrim(rtrim((string) $d->ship_qty, '0'), '.')" :source="Src::of('tm2sdi.co_order_delivery.ship_qty')" />
                        @if ($draft)<x-dl::field.checkbox :name="'remove_delivery['.$d->id_delivery.']'" text="Hapus jadwal ini saat Save" />@else<x-dl::field.display label="Status" :value="$d->status ?? '—'" />@endif
                    </x-dl::form.row>
                @empty
                    <x-dl::empty-state title="Belum ada jadwal kirim — saat Confirm dibuat otomatis 1 baris qty penuh @ Due Date." />
                @endforelse
                @if ($draft)<x-dl::button variant="secondary" size="sm" label="+ Jadwal Kirim" type="submit" name="action" :value="'add_delivery_'.$it->id_item" formnovalidate />@endif
            @endforeach
        </x-slot:delivery>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Catatan internal (Ops / PPIC)" multiline placeholder="Internal notes for Ops/PPIC..." :value="old('notes', $o->notes)" :source="Src::of('tm2sdi.co_order.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="items" title="Details" key-field="id_item" remove-field="remove" row-lock="editable" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2sdi.co_order_item') }}"
                :columns="[
                    ['key' => 'description', 'label' => 'Produk / Jasa', 'readonlyUnless' => 'editable', 'width' => '320px'],
                    ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'readonlyUnless' => 'editable', 'sum' => true, 'width' => '110px'],
                    ['key' => 'qty_unit', 'label' => 'Satuan', 'type' => 'display', 'width' => '80px'],
                    ['key' => 'unit_price', 'label' => 'Harga', 'type' => 'money', 'readonlyUnless' => 'editable', 'width' => '120px'],
                    ['key' => 'discount_pct', 'label' => 'Disc %', 'type' => 'display', 'width' => '70px'],
                    ['key' => 'due_date', 'label' => 'Due Date', 'type' => 'date', 'width' => '150px'],
                    ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * unit_price * (1 - discount_pct / 100)', 'sum' => true, 'width' => '140px'],
                    ['key' => 'kind', 'label' => 'Jenis', 'type' => 'display', 'more' => true],
                    ['key' => 'source', 'label' => 'Asal Baris', 'type' => 'display', 'more' => true],
                    ['key' => 'inquiry', 'label' => 'Inquiry', 'type' => 'display', 'more' => true],
                    ['key' => 'product_label', 'label' => 'Master Product', 'type' => 'display', 'more' => true],
                    ['key' => 'spec_note', 'label' => 'Spesifikasi', 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        <x-dl::field.lookup name="product_id" lookup="product" :params="['customer_id' => $o->customer_id]" label="+ Produk dari Master Product" placeholder="Ketik min. 2 huruf nama / kode produk / design no..." :source="Src::of('tm2mst.prod_product')" />
                        <x-dl::button size="sm" variant="secondary" label="+ Produk" type="submit" name="action" value="add_product" formnovalidate />
                        <x-dl::button size="sm" variant="secondary" label="+ Jasa" type="submit" name="action" value="add_service" formnovalidate />
                    </x-slot:tools>
                @endif
            </x-dl::lines>
            <x-dl::doc-footer :tabs="['disc' => 'Discount', 'tax' => 'Tax', 'cost' => 'Other Costs']"
                :words="\Devlib\Terbilang::words(round((float) $totals['net_receivable']), $cur, $cur === 'IDR' ? 'id' : 'en')"
                :summary="array_values(array_filter([
                    ['Details', $money($totals['item_subtotal'])],
                    ['Discount', ($totals['discount_amount'] > 0 ? '− ' : '').$money($totals['discount_amount'])],
                    ['Other Costs', $money($totals['external_cost'])],
                    ['DPP', $money($totals['dpp']), 'dpp'],
                    ['PPN '.number_format($totals['ppn_pct'], 0).'%', $money($totals['ppn'])],
                    ['Total Billing', $money($totals['total_billing'])],
                    $totals['wht23'] > 0 ? ['PPh 23 '.number_format($totals['wht23_pct'], 0).'% (jasa, dipotong)', '− '.$money($totals['wht23'])] : null,
                    ['Net Receivable', $money($totals['net_receivable']), 'grand'],
                ]))">
                <x-slot:disc>
                    <x-dl::form.row :cols="2">
                        <x-dl::field.display label="Disc. Doc (fix dari Inquiry)" :value="$o->discount_doc_pct !== null ? number_format((float) $o->discount_doc_pct, 2).'%' : '—'" :source="Src::of('tm2sdi.co_order.discount_doc_pct')" />
                        <x-dl::field.decimal name="discount_qty_pct" label="Disc. Qty" unit="%" :value="$o->discount_qty_pct !== null ? rtrim(rtrim((string) $o->discount_qty_pct, '0'), '.') : ''" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.discount_qty_pct')" />
                    </x-dl::form.row>
                </x-slot:disc>
                <x-slot:tax>
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="is_ppn" label="Pajak" :options="['1' => 'PPN', '0' => 'Non PPN']" :value="$o->is_ppn ? '1' : '0'" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.is_ppn')" />
                        <x-dl::field.display label="PPh 23 (baris jasa)" :value="number_format($totals['wht23_pct'], 0).'% · dipotong customer'" :source="Src::of('CoOrder::totals()')" />
                    </x-dl::form.row>
                </x-slot:tax>
                <x-slot:cost>
                    @forelse ($o->externalCosts as $x)
                        <x-dl::form.row :cols="5">
                            <x-dl::field.select :name="'costs['.$x->id_cost.'][category]'" label="Category" :options="['Trucking' => 'Trucking', 'Expedition' => 'Expedition', 'Lainnya' => 'Lainnya']" :value="$x->category" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.category')" />
                            <x-dl::field.text :name="'costs['.$x->id_cost.'][description]'" label="Description" :value="$x->description" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.description')" />
                            <x-dl::field.decimal :name="'costs['.$x->id_cost.'][nominal]'" label="Nominal" :value="rtrim(rtrim((string) $x->nominal, '0'), '.')" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.nominal')" />
                            <x-dl::field.select :name="'costs['.$x->id_cost.'][tax_treatment]'" label="Tax Treatment" :options="['TAXABLE' => 'Taxable', 'REIMBURSEMENT' => 'Reimbursement']" :value="$x->tax_treatment" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.tax_treatment')" />
                            @if ($draft)<x-dl::field.checkbox :name="'remove_cost['.$x->id_cost.']'" text="Hapus saat Save" />@endif
                        </x-dl::form.row>
                    @empty
                        <x-dl::empty-state title="Belum ada biaya lain." />
                    @endforelse
                    @if ($draft)<x-dl::button variant="secondary" size="sm" label="+ Add Cost" type="submit" name="action" value="add_cost" formnovalidate />@endif
                </x-slot:cost>
            </x-dl::doc-footer>
        </x-slot:detail>
    </x-dl::form>
@endif
