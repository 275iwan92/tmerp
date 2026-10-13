{{--
 REMARK
 Tujuan       : Membuat dan mengelola Customer Order jalur INTEGRATED (dari Quotation) atau INSTANT (Master Product): Draft, Confirm, Cancel, jadwal kirim.
 Sumber hulu  : App\Support\V2\Data\CustomerOrder; model Sdi\CoOrder (customer, quotation, items, deliveries, externalCosts), Master\FinPaymentTerm, SalesPerson; engine CPDEL.
 Input        : Query id, new; session v2_flash dan errors.
 Output       : Daftar lewat _dbgrid; form header, baris + due date, jadwal kirim, biaya luar; Confirm membuat jadwal kirim default; Print / Email.
 Dipakai oleh : Rute v2.screen dengan slug 'customer-order' (menu v2:customer-order di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (Confirm wajib due date tiap baris PRODUCT; Cancel ditolak bila sudah ada WO / DO).
--}}
@php
    use App\Models\Master\FinPaymentTerm;
    use App\Models\Master\SalesPerson;
    use App\Models\Sdi\CoOrder;
    use App\Support\V2\Data\CustomerOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'customer-order');
    $o = request('id') ? CoOrder::with(['customer', 'quotation', 'items.inquiry', 'items.product', 'items.deliveries', 'externalCosts'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $o, 404);
    \App\Support\V2\Crumb::set($o ? raw($o->co_no) : null);
    $rp = fn ($v) => 'IDR '.\Devlib\Locale::number((float) $v, 0);
    $trim = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $o && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Sales\CustomerOrderListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Sales\CustomerOrderListGrid]])
@elseif (! $o)
    @php $quotes = CustomerOrder::quotationChoices(); @endphp
    <x-dl::card title="+ New Record — choose the Customer Order path" description="Two entry paths; the document & what follows (CPDEL, production) are the same.">
        <x-dl::cols>
            <x-dl::action-form action="{{ route('v2.co.create') }}">
                <input type="hidden" name="mode" value="INTEGRATED">
                <x-dl::card title="INTEGRATED — from Quotation" description="Customer accepts the quotation. Lines & prices = quotation snapshot (1 Quotation = 1 CO).">
                    @if ($quotes)
                        <x-dl::field.select name="quotation_id" label="Issued Quotation" required :options="collect($quotes)->mapWithKeys(fn ($q) => [$q['id'] => $q['no'].' · '.$q['customer'].' · '.$q['lines'].' '.t('lines').' · '.$rp($q['total'])])->all()" :source="Src::of('tm2sdi.quo_quotation (PUBLISHED, belum ada CO)')" />
                        <x-dl::action-bar><x-dl::button preset="save" :label="t('Create from Quotation')" /></x-dl::action-bar>
                    @else
                        <x-dl::empty-state title="No issued quotation that is not yet a CO" />
                    @endif
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::action-form action="{{ route('v2.co.create') }}">
                <input type="hidden" name="mode" value="INSTANT">
                <x-dl::card title="INSTANT — without Quotation" description="Direct order (repeat order / verbal). Product lines must come from the customer's Master Product.">
                    <x-dl::field.select name="customer_id" label="Customer" required :options="CustomerOrder::customerOptions()" :source="Src::of('tm2mst.cust_customer')" />
                    <x-dl::action-bar><x-dl::button preset="save" label="Create Instant" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        </x-dl::cols>
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
        $num = fn ($v) => \Devlib\Locale::number((float) $v, 0);
        $area = $o->ship_area_id ? $o->ship_area_level.':'.$o->ship_area_id : null;
        $cur = $o->currency_code ?? 'IDR';
        $sym = \Devlib\Terbilang::CURRENCIES[$cur][0] ?? 'IDR';
        $money = fn ($v) => $sym.' '.\Devlib\Locale::number((float) $v, 0);
        $lines = $o->items->map(fn ($it) => [
            'id_item' => $it->id_item, 'description' => $it->description, 'qty' => (float) $it->qty, 'qty_unit' => $it->qty_unit,
            'unit_price' => (float) $it->unit_price, 'discount_pct' => (float) $it->discount_pct, 'due_date' => $it->due_date?->toDateString() ?? '',
            'qty_minim' => (bool) $it->qty_minim,
            'editable' => $draft && $it->quotation_item_id === null,
            'kind' => $it->line_kind === 'PRODUCT' ? t('Product') : t('Service line'), 'inquiry' => $it->inquiry?->inquiry_no ?? '—',
            'source' => $it->quotation_item_id !== null ? t('Quotation snapshot (locked)') : t('Manual'), 'spec_note' => $it->spec_note ?? '—',
            'product_label' => $it->product_id ? ($it->product?->product_name ?? '#'.$it->product_id) : ($it->line_kind === 'PRODUCT' ? t('not linked yet') : '—'),
        ])->values()->all();
        $log = collect($c->historyTimeline())->map(fn ($r) => ['at' => $r['date'], 'by' => '', 'action' => $r['type'].(! empty($r['current']) ? ' '.t('(this one)') : ''), 'note' => $r['label']])->reverse()->values()->all();
        // Approval CO (user 2026-09-30): F&A SPV (cek harga) -> Operational Manager; log keputusan flow_log di atas History.
        $appr = CustomerOrder::approvalState('CO', $o->id_order, (float) $totals['net_receivable']);
        $pending = CustomerOrder::pending($o);
        $log = array_merge(CustomerOrder::log($o), $log);
    @endphp
    @if ($o->doc_status === 'CANCELLED')<x-dl::alert tone="danger" :message="raw(tf('Cancelled by :user — :reason', ['user' => $o->updated_by, 'reason' => $o->cancel_reason]))" />@endif
    @if ($o->doc_status === 'REJECTED')<x-dl::alert tone="danger" :message="raw(tf('Rejected by :user — :reason. Use Duplicate to create a new CO.', ['user' => $o->updated_by, 'reason' => $o->cancel_reason]))" />@endif
    @if ($o->doc_status === 'SUBMITTED')<x-dl::alert tone="warn" :message="raw(tf('Waiting for approval level :at of :levels: :role. The delivery schedule (CPDEL) is created after the last approval.', ['at' => $appr['at'], 'levels' => $appr['levels'], 'role' => $appr['steps'][$appr['at'] - 1]['role'] ?? '—']))" />@endif
    @if ($draft && $missingDue->isNotEmpty())<x-dl::alert tone="warn" :message="raw(tf('⚠ :n Product lines have no Due Date yet (required before Confirm).', ['n' => $missingDue->count()]))" />@endif
    @if ($draft && $missingProduct->isNotEmpty())<x-dl::alert tone="warn" :message="raw(tf('⚠ :n lines are not linked to a Master Product yet.', ['n' => $missingProduct->count()]))" />@endif

    <x-dl::form id="co-form" :title="'Customer Order · '.raw($o->co_no)" :subtitle="raw(($o->customer?->customer_name ?? '—').' · '.$o->entry_mode)"
        panel="top" kind="document" :status="CustomerOrder::formStatus($o, $appr['held'])" :pipeline="true"
        :approval-levels="$appr['levels']" :approval-at="$appr['at'] ?: ($o->doc_status === 'SUBMITTED' ? 1 : $appr['levels'])" :can-approve="(bool) $pending"
        :action="route('v2.co.save', $o->id_order)" action-field="action" :back="$home" :new-url="$home.'?new=1'"
        :print-url="\App\Support\V2\Print\PrintDocs::url('customer-order', $o->id_order)" :hide="array_merge([], $o->isInstant() ? [] : ['delete'])"
        :tabs="['general' => 'General', 'finance' => 'Finance', 'delivery' => 'Delivery', 'material' => 'Customer Material'] + ($draft ? [] : ['billing' => 'Billing']) + ['notes' => 'Notes']"
        :created-by="$o->created_by" :created-at="$o->created_at?->toDateTimeString()" :updated-by="$o->updated_by" :updated-at="$o->updated_at?->toDateTimeString()" :log="$log">
        <x-slot:special>
            @unless ($draft)<x-dl::button size="sm" variant="secondary" icon="fa-truck" label="Customer Plan Delivery" :href="route('v2.screen', 'cpdel')" />@endunless
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="CO No." :value="$o->co_no" />
            <x-dl::field.date name="co_date" label="CO Date" :value="old('co_date', $o->co_date?->toDateString())" :source="Src::of('tm2sdi.co_order.co_date')" />
            <x-dl::field.display label="Customer" :value="($o->customer?->customer_name ?? '—').' ('.($o->customer?->customer_code ?? '—').')'" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::strip-kv kind="grand" label="Net Receivable" :value="$money($totals['net_receivable'])" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Ref. Quotation" :value="$o->quotation?->quotation_no ?? t('— (Instant, without Quotation)')" :source="Src::of('tm2sdi.co_order.quotation_id')" />
                <x-dl::field.text name="customer_po_no" label="Cust. PO No." :case-toggle="false" placeholder="PO/customer/..." :value="old('customer_po_no', $o->customer_po_no)" :source="Src::of('tm2sdi.co_order.customer_po_no')" />
                <x-dl::field.display label="Order Type" :value="t($c->orderType())" :source="Src::calc('jenis baris', 'tm2sdi.co_order_item.line_kind')" />
                <x-dl::field.select name="sales_person_id" label="PIC Sales" placeholder="— choose —" :options="SalesPerson::where('activate', true)->orderBy('sales_name')->pluck('sales_name', 'id_sales_person')->all()" :value="old('sales_person_id', $o->sales_person_id)" :source="Src::of('tm2mst.sales_person')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:finance>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Currency" :value="$cur" :source="Src::of('tm2sdi.co_order.currency_code')" />
                <x-dl::field.select name="payment_term" label="Payment Terms" placeholder="— choose —" :options="FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all()" :value="old('payment_term', $o->payment_term)" :source="Src::of('tm2mst.fin_payment_term')" />
            </x-dl::form.row>
        </x-slot:finance>
        <x-slot:delivery>
            {{-- Metode & pihak pengiriman (v1 sdi_co: shipment_method, recipient/shipper/forwarder = customer). --}}
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="shipment_method" label="Shipment Method" placeholder="— choose —" :options="array_map(fn ($l) => t($l), \App\Models\Sdi\CoOrder::SHIPMENT_METHODS)" :value="old('shipment_method', $o->shipment_method)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.shipment_method')" />
                <x-dl::field.lookup name="recipient_customer_id" lookup="customer" label="Recipient (if different from the customer)" placeholder="Search customer..." :value="$o->recipient_customer_id" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.recipient_customer_id', 'tm2mst.cust_customer.customer_name')" />
                <x-dl::field.lookup name="shipper_customer_id" lookup="customer" label="Shipper" placeholder="Search customer..." :value="$o->shipper_customer_id" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.shipper_customer_id', 'tm2mst.cust_customer.customer_name')" />
                <x-dl::field.lookup name="forwarder_customer_id" lookup="customer" label="Forwarder" placeholder="Search customer..." :value="$o->forwarder_customer_id" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.forwarder_customer_id', 'tm2mst.cust_customer.customer_name')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.lookup name="ship_area" lookup="geo_area" label="Shipment Location" placeholder="Search village / district / city / postcode..." :value="$area" :source="Src::of('tm2sdi.co_order.ship_area_id', 'ship_area_level', 'ship_address_label')" />
                <x-dl::field.text name="ship_address_detail" label="Address Details (street, no., RT/RW, landmark)" placeholder="Jl. ..., No. ..." :value="old('ship_address_detail', $o->ship_address_detail)" :source="Src::of('tm2sdi.co_order.ship_address_detail')" />
                <x-dl::field.display name="ship_postal_code" label="Postcode" :value="$o->ship_postal_code ?? '—'" :source="Src::of('tm2sdi.co_order.ship_postal_code')" />
            </x-dl::form.row>
            {{-- Jadwal kirim per baris Product (kosong = saat Confirm dibuat otomatis 1 baris qty penuh @ Due Date). --}}
            @foreach ($o->items->where('line_kind', 'PRODUCT') as $it)
                <x-dl::divider :label="raw($it->description.' · '.$num($it->qty).' '.$it->qty_unit).' · due '.raw(\Devlib\Locale::d($it->due_date) ?? '—')" />
                @forelse ($it->deliveries as $d)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.select :name="'deliveries['.$d->id_delivery.'][shipment_type]'" label="Type" :options="['FULL' => 'FULL', 'PARTIAL' => 'PARTIAL']" :value="$d->shipment_type" :source="Src::of('tm2sdi.co_order_delivery.shipment_type')" />
                        <x-dl::field.date :name="'deliveries['.$d->id_delivery.'][ship_date]'" label="Ship Date" :value="$d->ship_date?->toDateString()" :source="Src::of('tm2sdi.co_order_delivery.ship_date')" />
                        <x-dl::field.decimal :name="'deliveries['.$d->id_delivery.'][ship_qty]'" label="Qty" :decimals="0" :value="rtrim(rtrim((string) $d->ship_qty, '0'), '.')" :source="Src::of('tm2sdi.co_order_delivery.ship_qty')" />
                        @if ($draft)<x-dl::field.checkbox :name="'remove_delivery['.$d->id_delivery.']'" text="Delete this schedule on Save" />@else<x-dl::field.display label="Status" :value="$d->status ?? '—'" />@endif
                    </x-dl::form.row>
                @empty
                    <x-dl::empty-state title="No delivery schedule yet — on Confirm one full-qty line @ Due Date is created automatically." />
                @endforelse
                @if ($draft)<x-dl::button variant="secondary" size="sm" :label="t('+ Delivery Schedule')" type="submit" name="action" :value="'add_delivery_'.$it->id_item" formnovalidate />@endif
            @endforeach
        </x-slot:delivery>
        <x-slot:material>
            {{-- Bahan dari customer yang sudah diterima gudang utk CO ini (Penerimaan Barang jenis From Customer, user 2026-10-01). --}}
            @php $cm = \App\Support\V2\Data\PurchaseReceive::customerMaterialsForCo($o->id_order); @endphp
            <x-dl::table :source="Src::of('tm2pur.pv_receipt (CUSTOMER, i_co_id)', 'tm2pur.pv_line')" empty="No customer material received for this CO yet."
                :columns="['doc' => 'Receipt No.', 'date' => 'Date', 'item' => 'CO Item', 'material' => 'Material', 'qty' => ['Qty', 'num' => true], 'uom' => 'UoM', 'lot' => 'Lot / Roll No.']"
                :rows="$cm['rows']" />
            <x-dl::action-bar>
                @foreach ($cm['receipts'] as $rc)
                    <x-dl::button size="sm" variant="secondary" icon="fa-truck-ramp-box" :label="$rc['no']" :href="route('v2.screen', 'purchase-receive').'?id='.$rc['id']" />
                @endforeach
                <x-dl::button size="sm" variant="secondary" icon="fa-plus" label="+ Receive Customer Material" :href="route('v2.screen', 'purchase-receive').'?new=1&type=CUSTOMER&co='.$o->id_order" />
            </x-dl::action-bar>
        </x-slot:material>
        <x-slot:billing>
            {{-- Penagihan order ini, hanya baca (user 2026-10-02): tiap invoice berasal dari Surat Jalan yang divalidasi customer, dan
                 uang muka terlacak per order -- ditagih, diterima, dipotong di invoice mana, sisanya. Data = Customer Invoice (Finance). --}}
            @unless ($draft)
                @php $bill = \App\Support\V2\Data\CustomerInvoice::orderBilling($o); @endphp
                <x-dl::alert tone="info" :message="t('Read only. Invoices and money are recorded in Finance > Customer Invoice.')" />
                <x-dl::table :columns="['label' => 'Summary', 'value' => ['Amount', 'num' => true, 'raw' => true]]" :rows="$bill['summary']"
                    source="{{ Src::calc('order value, delivery notes, invoices and down payments of this order', 'tm2acc.fin_invoice', 'tm2sdi.do_line') }}" />
                <x-dl::divider label="Delivery Notes and Invoices" />
                <x-dl::table :columns="['dn' => 'Delivery Note', 'date' => 'Delivery Date', 'product' => 'Product', 'shipped' => ['Shipped', 'num' => true, 'raw' => true],
                        'received' => ['Received', 'num' => true, 'raw' => true], 'diff' => ['Difference', 'num' => true, 'raw' => true], 'reason' => 'Reason for Difference',
                        'invoiced' => ['Qty Invoiced', 'num' => true, 'raw' => true], 'to_invoice' => ['To Invoice', 'num' => true, 'raw' => true], 'invoices' => 'Invoice No.', 'state' => 'Status']"
                    :rows="$bill['chain']" empty="No delivery note for this order yet." source="{{ Src::of('tm2sdi.do_line', 'tm2sdi.do_delivery_pod', 'tm2acc.fin_invoice_line') }}" />
                <x-dl::divider label="Invoices and Down Payments" />
                <x-dl::table :columns="['no' => 'Document No.', 'kind' => 'Type', 'date' => 'Date', 'due' => 'Due Date', 'total' => ['Total', 'num' => true, 'raw' => true],
                        'paid' => ['Paid', 'num' => true, 'raw' => true], 'outstanding' => ['Outstanding', 'num' => true, 'raw' => true], 'state' => 'Status']"
                    :rows="$bill['documents']" empty="No invoice for this order yet." source="{{ Src::of('tm2acc.fin_invoice') }}" />
                <x-dl::action-bar>
                    @foreach ($bill['documents'] as $doc)
                        <x-dl::button size="sm" variant="secondary" icon="fa-file-invoice-dollar" :label="raw($doc['no'])" :href="route('v2.screen', 'customer-invoice').'?id='.$doc['id']" />
                    @endforeach
                </x-dl::action-bar>
                <x-dl::divider label="Down Payment History" />
                <x-dl::table :columns="['date' => 'Date', 'event' => 'Event', 'dp_no' => 'Down Payment No.', 'doc_no' => 'Document No.', 'billed' => ['Billed', 'num' => true, 'raw' => true],
                        'in' => ['Money In', 'num' => true, 'raw' => true], 'out' => ['Deducted', 'num' => true, 'raw' => true], 'balance' => ['Balance', 'num' => true, 'raw' => true]]"
                    :rows="$bill['ledger']" empty="No down payment for this order." source="{{ Src::of('tm2acc.fin_invoice (CUSTOMER_DP)', 'tm2acc.fin_voucher', 'tm2acc.fin_dp_allocation') }}" />
            @endunless
        </x-slot:billing>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Internal notes (Ops / PPIC)" multiline placeholder="Internal notes for Ops/PPIC..." :value="old('notes', $o->notes)" :source="Src::of('tm2sdi.co_order.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            @if ($draft && $o->itemsWithUnpickedQty()->isNotEmpty())
                <x-dl::alert tone="warn" :message="t('The quotation offered several qty options for the same product. The customer picks one: press Choose on the qty ordered; the other options are removed from this order.')" />
                <x-dl::action-bar>
                    @foreach ($o->itemsWithUnpickedQty() as $opt)
                        <x-dl::button size="sm" variant="secondary" icon="fa-check" :label="t('Choose').' '.\Devlib\Locale::number((float) $opt->qty, 0).' '.$opt->qty_unit.' · '.\Illuminate\Support\Str::limit($opt->description, 40)" type="submit" name="action" :value="'pick_qty_'.$opt->id_item" formnovalidate />
                    @endforeach
                </x-dl::action-bar>
            @endif
            <x-dl::lines name="items" title="Details" key-field="id_item" remove-field="remove" row-lock="editable" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2sdi.co_order_item') }}"
                :columns="[
                    ['key' => 'description', 'label' => 'Product / Service', 'readonlyUnless' => 'editable', 'width' => '320px'],
                    ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'readonlyUnless' => 'editable', 'sum' => true, 'width' => '110px'],
                    ['key' => 'qty_unit', 'label' => t('Unit'), 'type' => 'display', 'width' => '80px'],
                    ['key' => 'unit_price', 'label' => t('Price'), 'type' => 'money', 'readonlyUnless' => 'editable', 'width' => '120px'],
                    ['key' => 'discount_pct', 'label' => 'Disc %', 'type' => 'display', 'width' => '70px'],
                    ['key' => 'due_date', 'label' => 'Due Date', 'type' => 'date', 'width' => '150px'],
                    ['key' => 'qty_minim', 'label' => 'Qty Minim', 'type' => 'bool', 'width' => '90px'],
                    ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * unit_price * (1 - discount_pct / 100)', 'sum' => true, 'width' => '140px'],
                    ['key' => 'kind', 'label' => t('Type'), 'type' => 'display', 'more' => true],
                    ['key' => 'source', 'label' => t('Line Source'), 'type' => 'display', 'more' => true],
                    ['key' => 'inquiry', 'label' => 'Inquiry', 'type' => 'display', 'more' => true],
                    ['key' => 'product_label', 'label' => 'Master Product', 'type' => 'display', 'more' => true],
                    ['key' => 'spec_note', 'label' => 'Specification', 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        <x-dl::field.lookup name="product_id" lookup="product" :params="['customer_id' => $o->customer_id]" label="+ Product from Master Product" placeholder="Type at least 2 letters of the product name / code / design no...." :source="Src::of('tm2mst.prod_product')" />
                        <x-dl::button size="sm" variant="secondary" label="+ Product" type="submit" name="action" value="add_product" formnovalidate />
                        <x-dl::button size="sm" variant="secondary" label="+ Service" type="submit" name="action" value="add_service" formnovalidate />
                    </x-slot:tools>
                @endif
            </x-dl::lines>
            <x-dl::doc-footer :tabs="['disc' => 'Discount', 'tax' => 'Tax', 'cost' => 'Other Costs']"
                :words="\Devlib\Terbilang::words(round((float) $totals['net_receivable']), $cur, $cur === 'IDR' ? 'id' : 'en')"
                :summary="array_values(array_filter([
                    [t('Details'), $money($totals['item_subtotal'])],
                    [t('Discount'), ($totals['discount_amount'] > 0 ? '− ' : '').$money($totals['discount_amount'])],
                    [t('Other Costs'), $money($totals['external_cost'])],
                    [t('Tax Base (DPP)'), $money($totals['dpp']), 'dpp'],
                    [t('VAT').' '.\Devlib\Locale::number($totals['ppn_pct']).'%', $money($totals['ppn'])],
                    [t('Total Billing'), $money($totals['total_billing'])],
                    $totals['wht23'] > 0 ? [tf('PPh 23 :pct% (services, withheld)', ['pct' => \Devlib\Locale::number($totals['wht23_pct'])]), '− '.$money($totals['wht23'])] : null,
                    [t('Net Receivable'), $money($totals['net_receivable']), 'grand'],
                ]))">
                <x-slot:disc>
                    <x-dl::form.row :cols="2">
                        <x-dl::field.display label="Doc. Disc. (fixed from Inquiry)" :value="$o->discount_doc_pct !== null ? \Devlib\Locale::decimal($o->discount_doc_pct, 2).'%' : '—'" :source="Src::of('tm2sdi.co_order.discount_doc_pct')" />
                        <x-dl::field.decimal name="discount_qty_pct" label="Disc. Qty" unit="%" :value="$o->discount_qty_pct !== null ? rtrim(rtrim((string) $o->discount_qty_pct, '0'), '.') : ''" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.discount_qty_pct')" />
                    </x-dl::form.row>
                </x-slot:disc>
                <x-slot:tax>
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="is_ppn" label="Tax" :options="['1' => t('VAT'), '0' => t('Non VAT')]" :value="$o->is_ppn ? '1' : '0'" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.is_ppn')" />
                        <x-dl::field.display label="PPh 23 (service lines)" :value="\Devlib\Locale::number($totals['wht23_pct']).'% · '.t('withheld by customer')" :source="Src::of('CoOrder::totals()')" />
                    </x-dl::form.row>
                </x-slot:tax>
                <x-slot:cost>
                    @forelse ($o->externalCosts as $x)
                        <x-dl::form.row :cols="5">
                            <x-dl::field.select :name="'costs['.$x->id_cost.'][category]'" label="Category" :options="['Trucking' => t('Trucking'), 'Expedition' => t('Expedition'), 'Lainnya' => t('Other')]" :value="$x->category" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.category')" />
                            <x-dl::field.text :name="'costs['.$x->id_cost.'][description]'" label="Description" :value="$x->description" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.description')" />
                            <x-dl::field.decimal :name="'costs['.$x->id_cost.'][nominal]'" label="Nominal" :value="rtrim(rtrim((string) $x->nominal, '0'), '.')" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.nominal')" />
                            <x-dl::field.select :name="'costs['.$x->id_cost.'][tax_treatment]'" label="Tax Treatment" :options="['TAXABLE' => t('Taxable'), 'REIMBURSEMENT' => t('Reimbursement')]" :value="$x->tax_treatment" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.tax_treatment')" />
                            @if ($draft)<x-dl::field.checkbox :name="'remove_cost['.$x->id_cost.']'" text="Delete on Save" />@endif
                        </x-dl::form.row>
                    @empty
                        <x-dl::empty-state title="No other costs yet." />
                    @endforelse
                    @if ($draft)<x-dl::button variant="secondary" size="sm" :label="t('+ Add Cost')" type="submit" name="action" value="add_cost" formnovalidate />@endif
                </x-slot:cost>
            </x-dl::doc-footer>
        </x-slot:detail>
    </x-dl::form>
@endif
