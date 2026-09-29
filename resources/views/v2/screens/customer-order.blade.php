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
    {{-- Struktur = v1 co/entry: header dokumen, tab Product / Delivery / External Cost / History Flow, panel kanan diskon + pajak + catatan. --}}
    @php
        $c = CustomerOrder::component($o->id_order);
        $draft = $o->doc_status === 'DRAFT';
        $totals = $o->totals();
        $missingDue = $o->items->where('line_kind', 'PRODUCT')->whereNull('due_date');
        $missingProduct = $o->itemsMissingProduct();
        $num = fn ($v) => number_format((float) $v, 0, ',', '.');
        $area = $o->ship_area_id ? $o->ship_area_level.':'.$o->ship_area_id : null;
    @endphp
    <form method="post" action="{{ route('v2.co.save', $o->id_order) }}" id="co-form" data-dl-guard>
        @csrf
        <x-dl::card>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <span class="mp-chips">
                    <x-dl::badge :label="$o->co_no" />
                    <x-dl::badge :label="$o->entry_mode" :tone="CustomerOrder::MODE_TONES[$o->entry_mode] ?? 'neutral'" />
                    <x-dl::badge :label="$o->doc_status" :tone="CustomerOrder::STATUS_TONES[$o->doc_status] ?? 'neutral'" />
                    <x-dl::badge tone="accent" :label="'Net Receivable '.$rp($totals['net_receivable'])" />
                    @if ($draft && $missingDue->isNotEmpty())<x-dl::badge tone="warn" :label="'⚠ '.$missingDue->count().' item belum ada Due Date'" />@endif
                    @if ($draft && $missingProduct->isNotEmpty())<x-dl::badge tone="warn" :label="'⚠ '.$missingProduct->count().' item belum terkait Master Product'" />@endif
                </span>
                <x-dl::button preset="print" :href="route('co.print', $o->id_order)" target="_blank" />
                <x-dl::doc-share target="#co-form" type="customer-order" :doc-id="$o->id_order" :number="$o->co_no" title="Customer Order" />
            </x-dl::action-bar>
            @if (! $draft)<x-dl::alert tone="success" message="✓ Sudah di-confirm — isi terkunci. Jadwal kirim diatur lanjut di Customer Plan Delivery." />@endif
        </x-dl::card>

        <x-dl::split aside="320px">
            <x-dl::card title="Header Dokumen">
                <x-dl::form.row cols="3">
                    <x-dl::field.display label="No. CO" :value="$o->co_no" format="mono" :source="Src::of('tm2sdi.co_order.co_no')" />
                    <x-dl::field.date name="co_date" label="CO Date" :value="old('co_date', $o->co_date?->toDateString())" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.co_date')" />
                    <x-dl::field.display label="Ref. Quotation" :value="$o->quotation?->quotation_no ?? '— (Instant, tanpa Quotation)'" :source="Src::of('tm2sdi.co_order.quotation_id')" />
                </x-dl::form.row>
                <x-dl::form.row cols="3">
                    <x-dl::field.text name="customer_po_no" label="Cust. PO No." :case-toggle="false" placeholder="PO/customer/..." :value="old('customer_po_no', $o->customer_po_no)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.customer_po_no')" />
                    <x-dl::field.display label="Order Type" :value="$c->orderType()" :source="Src::calc('jenis baris', 'tm2sdi.co_order_item.line_kind')" />
                    <x-dl::field.display label="Mata Uang" :value="$o->currency_code ?? 'IDR'" :source="Src::of('tm2sdi.co_order.currency_code')" />
                </x-dl::form.row>
                <x-dl::form.row cols="1">
                    <x-dl::field.display label="Customer" :value="($o->customer?->customer_name ?? '—').' ('.($o->customer?->customer_code ?? '—').')'" :source="Src::of('tm2mst.cust_customer')" />
                </x-dl::form.row>
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="sales_person_id" label="PIC Sales" placeholder="— pilih —" :options="SalesPerson::where('activate', true)->orderBy('sales_name')->pluck('sales_name', 'id_sales_person')->all()" :value="old('sales_person_id', $o->sales_person_id)" :disabled="! $draft" :source="Src::of('tm2mst.sales_person')" />
                    <x-dl::field.select name="payment_term" label="Termin Bayar" placeholder="— pilih —" :options="FinPaymentTerm::where('activate', true)->orderBy('sort_order')->pluck('term_label', 'payment_term_code')->all()" :value="old('payment_term', $o->payment_term)" :disabled="! $draft" :source="Src::of('tm2mst.fin_payment_term')" />
                    <x-dl::field.select name="is_ppn" label="Pajak" :options="['1' => 'PPN', '0' => 'Non PPN']" :value="$o->is_ppn ? '1' : '0'" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.is_ppn')" />
                </x-dl::form.row>
                <x-dl::form.row cols="1">
                    <x-dl::field.lookup name="ship_area" lookup="geo_area" label="Shipment Location" placeholder="Cari kelurahan / kecamatan / kota / kode pos..." :value="$area" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.ship_area_id', 'ship_area_level', 'ship_address_label')" />
                </x-dl::form.row>
                <x-dl::form.row cols="3">
                    <x-dl::field.text name="ship_address_detail" label="Detail Alamat (jalan, no., RT/RW, patokan)" placeholder="Jl. ..., No. ..." :value="old('ship_address_detail', $o->ship_address_detail)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.ship_address_detail')" />
                    <x-dl::field.display name="ship_postal_code" label="Kode Pos" :value="$o->ship_postal_code ?? '—'" :source="Src::of('tm2sdi.co_order.ship_postal_code')" />
                </x-dl::form.row>
            </x-dl::card>

            <x-dl::tabs :tabs="['product' => 'Product', 'delivery' => 'Delivery', 'external' => 'External Cost', 'history' => 'History Flow']">
                <x-slot:product>
                    @forelse ($o->items as $it)
                        @php $snap = $it->quotation_item_id !== null; $edit = $draft && ! $snap; @endphp
                        <x-dl::divider :label="$loop->iteration.'. '.($it->line_kind === 'PRODUCT' ? 'P' : 'S').($it->inquiry ? ' · '.$it->inquiry->inquiry_no : '').($snap ? ' · snapshot quotation' : '')" />
                        <x-dl::form.row cols="6">
                            @if ($edit)
                                <x-dl::field.text :name="'items['.$it->id_item.'][description]'" label="Produk / Jasa" placeholder="Deskripsi..." :value="$it->description" :hint="$it->spec_note" :source="Src::of('tm2sdi.co_order_item.description')" />
                                <x-dl::field.decimal :name="'items['.$it->id_item.'][qty]'" label="Qty" :decimals="2" :unit="$it->qty_unit" :value="rtrim(rtrim((string) $it->qty, '0'), '.')" :source="Src::of('tm2sdi.co_order_item.qty')" />
                                <x-dl::field.decimal :name="'items['.$it->id_item.'][unit_price]'" label="Harga" :decimals="2" :value="rtrim(rtrim((string) $it->unit_price, '0'), '.')" :source="Src::of('tm2sdi.co_order_item.unit_price')" />
                            @else
                                <x-dl::field.display label="Produk / Jasa" :value="$it->description" :hint="$it->spec_note" :source="Src::of('tm2sdi.co_order_item.description')" />
                                <x-dl::field.display label="Qty" :value="$num($it->qty).' '.$it->qty_unit" :source="Src::of('tm2sdi.co_order_item.qty')" />
                                <x-dl::field.display label="Harga" :value="$num($it->unit_price)" :source="Src::of('tm2sdi.co_order_item.unit_price')" />
                            @endif
                            <x-dl::field.display label="Disc%" :value="number_format((float) $it->discount_pct, 1).'%'" :source="Src::of('tm2sdi.co_order_item.discount_pct')" />
                            <x-dl::field.display label="Subtotal" :value="$num($it->subtotal)" :source="Src::of('tm2sdi.co_order_item.subtotal')" />
                            <x-dl::field.date :name="'items['.$it->id_item.'][due_date]'" label="Due Date" :required="$it->line_kind === 'PRODUCT'" :value="$it->due_date?->toDateString()" :disabled="! $draft"
                                :error="$draft && $it->line_kind === 'PRODUCT' && ! $it->due_date ? 'wajib diisi' : null" :source="Src::of('tm2sdi.co_order_item.due_date')" />
                        </x-dl::form.row>
                        @if ($it->line_kind === 'PRODUCT')
                            <x-dl::form.row cols="2">
                                <x-dl::field.lookup :name="'items['.$it->id_item.'][product_id]'" lookup="product" :params="['customer_id' => $o->customer_id]" label="Master Product"
                                    :value="$it->product_id" :disabled="! $draft" placeholder="belum terkait master — pilih" :source="Src::of('tm2sdi.co_order_item.product_id')" />
                                @if ($draft && ! $snap)<x-dl::field.checkbox :name="'remove['.$it->id_item.']'" text="Hapus baris ini saat Simpan" />@endif
                            </x-dl::form.row>
                        @elseif ($draft && ! $snap)
                            <x-dl::field.checkbox :name="'remove['.$it->id_item.']'" text="Hapus baris ini saat Simpan" />
                        @endif
                    @empty
                        <x-dl::empty-state title="Belum ada baris." />
                    @endforelse
                    @if ($draft)
                        <x-dl::divider label="Tambah baris" />
                        <x-dl::form.row cols="2">
                            <x-dl::field.lookup name="product_id" lookup="product" :params="['customer_id' => $o->customer_id]" label="＋ Produk dari Master Product" placeholder="Ketik min. 2 huruf nama / kode produk / design no..." :source="Src::of('tm2mst.prod_product')" />
                            <x-dl::action-bar>
                                <x-dl::button variant="secondary" icon="plus" label="＋ Produk" type="submit" name="action" value="add_product" formnovalidate />
                                <x-dl::button variant="secondary" icon="plus" label="＋ Jasa" type="submit" name="action" value="add_service" formnovalidate />
                            </x-dl::action-bar>
                        </x-dl::form.row>
                    @endif
                    <p class="mp-hint">*Baris dari Quotation adalah snapshot terkunci (harga/qty tidak bisa diubah di sini) — hanya Due Date yang editable. Baris "＋ Produk"/"＋ Jasa" manual boleh diedit.</p>
                </x-slot:product>

                <x-slot:delivery>
                    @forelse ($o->items->where('line_kind', 'PRODUCT') as $it)
                        <x-dl::divider :label="$it->description.' · '.$num($it->qty).' '.$it->qty_unit.' · due '.($it->due_date?->format('d M Y') ?? '—')" />
                        @forelse ($it->deliveries as $d)
                            <x-dl::form.row cols="4">
                                <x-dl::field.select :name="'deliveries['.$d->id_delivery.'][shipment_type]'" label="Type" :options="['FULL' => 'FULL', 'PARTIAL' => 'PARTIAL']" :value="$d->shipment_type" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_delivery.shipment_type')" />
                                <x-dl::field.date :name="'deliveries['.$d->id_delivery.'][ship_date]'" label="Ship Date" :value="$d->ship_date?->toDateString()" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_delivery.ship_date')" />
                                <x-dl::field.decimal :name="'deliveries['.$d->id_delivery.'][ship_qty]'" label="Qty" :decimals="0" :value="rtrim(rtrim((string) $d->ship_qty, '0'), '.')" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_delivery.ship_qty')" />
                                @if ($draft)<x-dl::field.checkbox :name="'remove_delivery['.$d->id_delivery.']'" text="Hapus jadwal ini saat Simpan" />@else<x-dl::field.display label="Status" :value="$d->status ?? '—'" />@endif
                            </x-dl::form.row>
                        @empty
                            <p class="mp-hint">Belum ada jadwal kirim.{{ $draft ? ' Kosong = saat Confirm dibuat otomatis 1 baris qty penuh @ Due Date.' : '' }}</p>
                        @endforelse
                        @if ($draft)<x-dl::action-bar><x-dl::button variant="secondary" size="sm" icon="plus" label="＋ Jadwal Kirim" type="submit" name="action" :value="'add_delivery_'.$it->id_item" formnovalidate /></x-dl::action-bar>@endif
                    @empty
                        <x-dl::empty-state title="Tidak ada baris PRODUCT untuk dijadwalkan." />
                    @endforelse
                </x-slot:delivery>

                <x-slot:external>
                    @forelse ($o->externalCosts as $x)
                        <x-dl::form.row cols="5">
                            <x-dl::field.select :name="'costs['.$x->id_cost.'][category]'" label="Category" :options="['Trucking' => 'Trucking', 'Expedition' => 'Expedition', 'Lainnya' => 'Lainnya']" :value="$x->category" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.category')" />
                            <x-dl::field.text :name="'costs['.$x->id_cost.'][description]'" label="Description" :value="$x->description" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.description')" />
                            <x-dl::field.decimal :name="'costs['.$x->id_cost.'][nominal]'" label="Nominal" :value="rtrim(rtrim((string) $x->nominal, '0'), '.')" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.nominal')" />
                            <x-dl::field.select :name="'costs['.$x->id_cost.'][tax_treatment]'" label="Tax Treatment" :options="['TAXABLE' => 'Taxable', 'REIMBURSEMENT' => 'Reimbursement']" :value="$x->tax_treatment" :disabled="! $draft" :source="Src::of('tm2sdi.co_order_external_cost.tax_treatment')" />
                            @if ($draft)<x-dl::field.checkbox :name="'remove_cost['.$x->id_cost.']'" text="Hapus saat Simpan" />@endif
                        </x-dl::form.row>
                    @empty
                        <x-dl::empty-state title="Belum ada biaya eksternal." />
                    @endforelse
                    @if ($draft)<x-dl::action-bar><x-dl::button variant="secondary" icon="plus" label="＋ Add Row" type="submit" name="action" value="add_cost" formnovalidate /></x-dl::action-bar>@endif
                </x-slot:external>

                <x-slot:history>
                    <x-dl::table :columns="['type' => 'Dokumen', 'label' => 'Nomor', 'date' => 'Tanggal']"
                        :rows="array_map(fn ($r) => ['type' => $r['type'].(! empty($r['current']) ? ' (ini)' : ''), 'label' => $r['label'], 'date' => $r['date'] ? \Illuminate\Support\Carbon::parse($r['date'])->translatedFormat('d M Y') : '—'], $c->historyTimeline())"
                        source="{{ Src::of('tm2sdi.iqp_inquiry', 'tm2sdi.quo_quotation', 'tm2sdi.co_order') }}" />
                </x-slot:history>
            </x-dl::tabs>

            <x-slot:side>
                <x-dl::card title="Document Discount">
                    <x-dl::field.display label="Disc. Doc (fix dari IQ)" :value="$o->discount_doc_pct !== null ? number_format((float) $o->discount_doc_pct, 2).'%' : '—'" :source="Src::of('tm2sdi.co_order.discount_doc_pct')" />
                    <x-dl::field.decimal name="discount_qty_pct" label="Disc. Qty" unit="%" :value="$o->discount_qty_pct !== null ? rtrim(rtrim((string) $o->discount_qty_pct, '0'), '.') : ''" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.discount_qty_pct')" />
                </x-dl::card>
                <x-dl::card title="Summary & Tax">
                    <x-dl::table :columns="['k' => 'Komponen', 'v' => 'Nilai']" :striped="false"
                        :rows="array_values(array_filter([
                            ['k' => 'Item Subtotal', 'v' => $num($totals['item_subtotal'])],
                            ['k' => '− Total Discounts', 'v' => '('.$num($totals['discount_amount']).')'],
                            ['k' => '+ External Cost', 'v' => $num($totals['external_cost'])],
                            ['k' => 'DPP', 'v' => $num($totals['dpp'])],
                            ['k' => 'VAT '.number_format($totals['ppn_pct'], 0).'% (PPN)', 'v' => $num($totals['ppn'])],
                            ['k' => 'Total Billing', 'v' => $num($totals['total_billing'])],
                            $totals['wht23'] > 0 ? ['k' => 'WHT '.number_format($totals['wht23_pct'], 0).'% (PPh Service)', 'v' => '('.$num($totals['wht23']).')'] : null,
                            ['k' => 'Net Receivable', 'v' => $num($totals['net_receivable'])],
                        ]))" source="{{ Src::of('CoOrder::totals()') }}" />
                </x-dl::card>
                <x-dl::card title="Order Notes">
                    <x-dl::field.text name="notes" label="Catatan internal" multiline placeholder="Internal notes for Ops/PPIC..." :value="old('notes', $o->notes)" :disabled="! $draft" :source="Src::of('tm2sdi.co_order.notes')" />
                </x-dl::card>
            </x-slot:side>
        </x-dl::split>

        <x-dl::action-bar>
            @if ($draft)
                @if ($o->isInstant())<x-dl::button preset="delete" type="submit" :formaction="route('v2.co.delete', $o->id_order)" formnovalidate onclick="return confirm('Hapus draft {{ $o->co_no }}?')" />@endif
                <x-dl::button preset="save" name="action" value="save" />
                <x-dl::button preset="confirm" type="submit" name="action" value="confirm" onclick="return confirm('Confirm CO ini? Setelah confirm, isi tidak bisa diedit lagi.')" />
            @else
                <x-dl::button icon="send" label="Buka Customer Plan Delivery" :href="route('v2.screen', 'cpdel')" />
            @endif
        </x-dl::action-bar>
    </form>
@endif
