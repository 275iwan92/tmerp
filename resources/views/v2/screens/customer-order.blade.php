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
    $o = request('id') ? CoOrder::with(['customer', 'quotation', 'items.inquiry', 'items.deliveries', 'externalCosts'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $o, 404);
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $trim = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp

<x-mp::sales.flow-steps current="order" note="Customer Order = the order is confirmed. Every product line needs a due date; Confirm creates the default shipping schedule." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $o && ! request('new'))
    <x-dl::grid id="co-list" :rows="CustomerOrder::rows()" search="Cari no. CO, customer, no. PO, quotation..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" :confirm-when="['status' => '__none__']"
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
    @php
        $draft = $o->doc_status === 'DRAFT';
        $totals = $o->totals();
        $missingDue = $o->items->where('line_kind', 'PRODUCT')->whereNull('due_date');
        $missingProduct = $o->itemsMissingProduct();
        $products = $draft ? CustomerOrder::productOptions($o, request('psearch')) : [];
    @endphp
    <form method="post" action="{{ route('v2.co.save', $o->id_order) }}" id="co-form">
        @csrf
        <x-dl::card>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <span class="mp-chips">
                    <x-dl::badge :label="$o->co_no" />
                    <x-dl::badge :label="$o->entry_mode" :tone="CustomerOrder::MODE_TONES[$o->entry_mode] ?? 'neutral'" />
                    <x-dl::badge :label="$o->doc_status" :tone="CustomerOrder::STATUS_TONES[$o->doc_status] ?? 'neutral'" />
                </span>
                <x-dl::button preset="print" :href="route('co.print', $o->id_order)" target="_blank" />
                <x-dl::doc-share target="#co-form" type="customer-order" :doc-id="$o->id_order" :number="$o->co_no" title="Customer Order" />
            </x-dl::action-bar>
            <h2 class="mp-bigname">{{ $o->customer?->customer_name ?? '—' }}</h2>
            <p class="mp-hint">{{ $o->quotation ? 'Dari Quotation '.$o->quotation->quotation_no.' — baris snapshot quotation: hanya Due Date yang bisa diubah.' : 'INSTANT (tanpa Quotation) — baris product dari Master Product.' }}</p>
            @if ($draft && $missingDue->isNotEmpty())<x-dl::alert tone="warn" :message="$missingDue->count().' baris product belum punya Due Date — wajib diisi sebelum Confirm.'" />@endif
            @if ($draft && $missingProduct->isNotEmpty())<x-dl::alert tone="warn" :message="$missingProduct->count().' baris product belum terhubung ke Master Product (wajib untuk INSTANT).'" />@endif
            @if (! $draft)<x-dl::alert tone="success" message="CONFIRMED — jadwal kirim diatur lanjut di Customer Plan Delivery." />@endif
        </x-dl::card>

        <x-dl::card title="Header">
            <x-dl::form.row cols="4">
                <x-dl::field.date name="co_date" label="CO Date" :value="old('co_date', $o->co_date?->toDateString())" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.co_date')" />
                <x-dl::field.text name="customer_po_no" label="No. PO customer" :case-toggle="false" :value="old('customer_po_no', $o->customer_po_no)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.customer_po_no')" />
                <x-dl::field.select name="sales_person_id" label="PIC Sales" :options="SalesPerson::where('activate', true)->orderBy('sales_name')->pluck('sales_name', 'id_sales_person')->all()" :value="old('sales_person_id', $o->sales_person_id)" :disabled="! $draft" :source="Src::of('tm2mst.sales_person')" />
                <x-dl::field.select name="payment_term" label="Termin bayar" :options="FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all()" :value="old('payment_term', $o->payment_term)" :disabled="! $draft" :source="Src::of('tm2mst.fin_payment_term')" />
            </x-dl::form.row>
            <x-dl::form.row cols="4">
                <x-dl::field.select name="is_ppn" label="Pajak" :options="['1' => 'PPN', '0' => 'Non PPN']" :value="$o->is_ppn ? '1' : '0'" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.is_ppn')" />
                <x-dl::field.display label="Mata uang" :value="$o->currency_code ?? 'IDR'" :source="Src::of('tm2sdi.co_order.currency_code')" />
                <x-dl::field.display label="Disc. dokumen (dari inquiry)" :value="$o->discount_doc_pct !== null ? number_format((float) $o->discount_doc_pct, 2).'%' : '—'" :source="Src::of('tm2sdi.co_order.discount_doc_pct')" />
                <x-dl::field.decimal name="discount_qty_pct" label="Disc. qty" unit="%" :value="$trim($o->discount_qty_pct)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.discount_qty_pct')" />
            </x-dl::form.row>
            <x-dl::form.row cols="2">
                <x-dl::field.display label="Area kirim" :value="trim(($o->ship_address_label ?? '—').' '.($o->ship_postal_code ?? ''))" hint="Ubah area lewat layar v1 (picker wilayah belum ada di DevLib)" :source="Src::of('tm2sdi.co_order.ship_area_id', 'ship_address_label')" />
                <x-dl::field.text name="ship_address_detail" label="Detail alamat kirim (jalan, no., RT/RW, patokan)" :value="old('ship_address_detail', $o->ship_address_detail)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.ship_address_detail')" />
            </x-dl::form.row>
            <x-dl::field.text name="notes" label="Catatan" multiline :value="old('notes', $o->notes)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.notes')" />
        </x-dl::card>

        <x-dl::card title="Baris order" description="Due Date = tanggal kirim yang dijanjikan (dasar jadwal CPDEL & produksi).">
            @forelse ($o->items as $it)
                @php $snap = $it->quotation_item_id !== null; @endphp
                <x-dl::divider :label="$loop->iteration.'. '.$it->line_kind.($it->inquiry ? ' · '.$it->inquiry->inquiry_no : '').($it->product_id ? ' · Master Product #'.$it->product_id : '')" />
                <x-dl::form.row cols="7">
                    <x-dl::field.text :name="'items['.$it->id_item.'][description]'" label="Uraian" :value="$it->description" :disabled="! $draft || $snap" :source="Src::of('tm2sdi.co_order_item.description')" />
                    <x-dl::field.decimal :name="'items['.$it->id_item.'][qty]'" label="Qty" :decimals="0" :value="$trim($it->qty)" :disabled="! $draft || $snap" :source="Src::of('tm2sdi.co_order_item.qty')" />
                    <x-dl::field.text :name="'items['.$it->id_item.'][qty_unit]'" label="Satuan" :case-toggle="false" :value="$it->qty_unit" :disabled="! $draft || $snap" :source="Src::of('tm2sdi.co_order_item.qty_unit')" />
                    <x-dl::field.decimal :name="'items['.$it->id_item.'][unit_price]'" label="Harga satuan" :value="$trim($it->unit_price)" :disabled="! $draft || $snap" :source="Src::of('tm2sdi.co_order_item.unit_price')" />
                    <x-dl::field.decimal :name="'items['.$it->id_item.'][discount_pct]'" label="Diskon" unit="%" :value="$trim($it->discount_pct)" :disabled="! $draft || $snap" :source="Src::of('tm2sdi.co_order_item.discount_pct')" />
                    @if ($it->line_kind === 'PRODUCT')
                        <x-dl::field.date :name="'items['.$it->id_item.'][due_date]'" label="Due Date" :required="true" :value="$it->due_date?->toDateString()" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_item.due_date')" />
                    @else
                        <x-dl::field.display label="Due Date" value="— (jasa)" />
                    @endif
                    <x-dl::field.display label="Subtotal" :value="$rp($it->subtotal)" :source="Src::of('tm2sdi.co_order_item.subtotal')" />
                </x-dl::form.row>
                @if ($it->line_kind === 'PRODUCT')
                    @foreach ($it->deliveries as $d)
                        <x-dl::form.row cols="4">
                            <x-dl::field.select :name="'deliveries['.$d->id_delivery.'][shipment_type]'" label="Jadwal kirim" :options="['FULL' => 'FULL', 'PARTIAL' => 'PARTIAL']" :value="$d->shipment_type" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_delivery.shipment_type')" />
                            <x-dl::field.date :name="'deliveries['.$d->id_delivery.'][ship_date]'" label="Tanggal kirim" :value="$d->ship_date?->toDateString()" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_delivery.ship_date')" />
                            <x-dl::field.decimal :name="'deliveries['.$d->id_delivery.'][ship_qty]'" label="Qty kirim" :decimals="0" :value="$trim($d->ship_qty)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_delivery.ship_qty')" />
                            @if ($draft)<x-dl::field.checkbox :name="'remove_delivery['.$d->id_delivery.']'" text="Hapus jadwal ini saat Simpan" />@else<x-dl::field.display label="Status" :value="$d->status ?? '—'" />@endif
                        </x-dl::form.row>
                    @endforeach
                @endif
                @if ($draft)
                    <x-dl::action-bar>
                        @if ($it->line_kind === 'PRODUCT')<x-dl::button variant="secondary" size="sm" icon="plus" label="+ Tambah Jadwal Kirim" type="submit" name="action" :value="'add_delivery_'.$it->id_item" formnovalidate />@endif
                        @if (! $snap)<x-dl::field.checkbox :name="'remove['.$it->id_item.']'" text="Hapus baris ini saat Simpan" />@endif
                    </x-dl::action-bar>
                @endif
            @empty
                <x-dl::empty-state title="Belum ada baris" message="Tambah product dari Master Product atau baris jasa." />
            @endforelse
            @if ($draft)
                <x-dl::divider label="Tambah baris" />
                <x-dl::form.row cols="2">
                    <x-dl::field.select name="product_id" label="Master Product (milik customer ini dulu)" :options="$products" placeholder="Pilih product..." :source="Src::of('tm2mst.prod_product')" />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="plus" label="+ Tambah Product" type="submit" name="action" value="add_product" formnovalidate />
                        <x-dl::button variant="secondary" icon="plus" label="+ Tambah Jasa" type="submit" name="action" value="add_service" formnovalidate />
                    </x-dl::action-bar>
                </x-dl::form.row>
            @endif
        </x-dl::card>

        <x-dl::card title="Biaya luar" description="Trucking / ekspedisi yang ditagihkan ke customer.">
            @foreach ($o->externalCosts as $x)
                <x-dl::form.row cols="5">
                    <x-dl::field.select :name="'costs['.$x->id_cost.'][category]'" label="Kategori" :options="['Trucking' => 'Trucking', 'Expedition' => 'Expedition', 'Lainnya' => 'Lainnya']" :value="$x->category" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.category')" />
                    <x-dl::field.text :name="'costs['.$x->id_cost.'][description]'" label="Uraian" :value="$x->description" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.description')" />
                    <x-dl::field.decimal :name="'costs['.$x->id_cost.'][nominal]'" label="Nominal" :value="$trim($x->nominal)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.nominal')" />
                    <x-dl::field.select :name="'costs['.$x->id_cost.'][tax_treatment]'" label="Pajak" :options="['TAXABLE' => 'Taxable', 'REIMBURSEMENT' => 'Reimbursement']" :value="$x->tax_treatment" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.tax_treatment')" />
                    @if ($draft)<x-dl::field.checkbox :name="'remove_cost['.$x->id_cost.']'" text="Hapus saat Simpan" />@endif
                </x-dl::form.row>
            @endforeach
            @if ($draft)<x-dl::action-bar><x-dl::button variant="secondary" icon="plus" label="+ Tambah Biaya Luar" type="submit" name="action" value="add_cost" formnovalidate /></x-dl::action-bar>@endif
            <x-dl::divider label="Total" />
            <x-dl::form.row cols="4">
                <x-dl::field.display label="Subtotal baris" :value="$rp($totals['item_subtotal'])" />
                <x-dl::field.display label="Diskon" :value="$rp($totals['discount_amount'])" />
                <x-dl::field.display label="Biaya luar" :value="$rp($totals['external_cost'])" />
                <x-dl::field.display label="DPP" :value="$rp($totals['dpp'])" />
            </x-dl::form.row>
            <x-dl::form.row cols="4">
                <x-dl::field.display :label="'PPN '.$totals['ppn_pct'].'%'" :value="$rp($totals['ppn'])" />
                <x-dl::field.display label="Total tagihan" :value="$rp($totals['total_billing'])" />
                <x-dl::field.display :label="'PPh 23 ('.$totals['wht23_pct'].'% jasa)'" :value="$rp($totals['wht23'])" />
                <x-dl::field.display label="Piutang bersih" :value="$rp($totals['net_receivable'])" :source="Src::of('CoOrder::totals()')" />
            </x-dl::form.row>
        </x-dl::card>

        <x-dl::action-bar>
            @if ($draft)
                @if ($o->isInstant())<x-dl::button preset="delete" type="submit" :formaction="route('v2.co.delete', $o->id_order)" formnovalidate onclick="return confirm('Hapus draft {{ $o->co_no }}?')" />@endif
                <x-dl::button preset="save" name="action" value="save" />
                <x-dl::button preset="confirm" type="submit" name="action" value="confirm" onclick="return confirm('Confirm Customer Order ini? Form terkunci dan jadwal kirim default dibuat.')" />
            @else
                <x-dl::button icon="send" label="Buka Customer Plan Delivery" :href="route('v2.screen', 'cpdel')" />
            @endif
        </x-dl::action-bar>
    </form>
@endif
