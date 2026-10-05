{{-- Delivery Notes / Surat Jalan v2 = DO yang sudah CONFIRMED / SHIPPED, dokumen yang dibawa kurir. Sisi B: TANPA harga
     (dispatch_tier2 §F -- kolom harga tidak dibaca sama sekali). Aksi: cetak Surat Jalan (dispatch.do.print-surat-jalan) dan
     Shipped (barang keluar). Data & aturan = App\Support\V2\Data\DeliveryOrder (rute simpan DO yang sama). --}}
@php
    use App\Models\Sdi\DoDelivery;
    use App\Support\V2\Data\DeliveryOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'delivery-notes');
    $do = request('id') ? DoDelivery::with(['customer', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no')
        ->select('id_line', 'delivery_id', 'line_no', 'co_order_id', 'co_order_item_id', 'item_description', 'qty_do', 'qty_unit', 'status')])
        ->whereIn('status', ['CONFIRMED', 'SHIPPED'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $do, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $do)
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Sales\DeliveryNoteListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Sales\DeliveryNoteListGrid]])
@else
    @php
        $lines = $do->lines->map(fn ($l) => ['id_line' => $l->id_line, 'item_description' => $l->item_description, 'qty_do' => (float) $l->qty_do, 'qty_unit' => $l->qty_unit,
            'co' => '#'.$l->co_order_id.' · item '.$l->co_order_item_id])->values()->all();
    @endphp
    <x-dl::form id="sj-form" :title="'Delivery Note · '.raw($do->do_no)" :subtitle="raw(($do->customer?->customer_name ?? '—').' · '.$do->status)"
        panel="top" kind="document" :status="DeliveryOrder::formStatus($do)" :pipeline="true" :can-input="false"
        :action="route('v2.do.save', $do->id_delivery)" action-field="action" :back="$home" :hidden="['from' => 'delivery-notes']" :print-url="\App\Support\V2\Print\PrintDocs::url('delivery-note', $do->id_delivery)" :hide="['cancel', 'draft']"
        :tabs="['delivery' => 'Delivery']"
        :created-by="$do->created_by" :created-at="$do->created_at?->toDateTimeString()" :updated-by="$do->updated_by" :updated-at="$do->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if ($do->status === 'CONFIRMED')<x-dl::button size="sm" variant="success" icon="fa-truck" :label="t('Shipped')" type="submit" name="action" value="ship" formnovalidate :onclick="'return confirm('.json_encode(t('Mark the goods as shipped?')).')'" />@endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Delivery Note No.')</span><b>{{ $do->do_no }}</b></div>
            <x-dl::field.display label="Customer" :value="$do->customer?->customer_name ?? '—'" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::field.display label="Destination" :value="trim(($do->ship_address_detail ? $do->ship_address_detail.', ' : '').($do->ship_address_label ?? ''), ', ') ?: '—'" :source="Src::of('tm2sdi.do_delivery.ship_address_label')" />
            <div class="dl-strip__kv dl-strip__grand"><span>@t('Total Qty')</span><b>{{ \Devlib\Locale::number((float) $do->lines->sum('qty_do'), 0) }}</b></div>
        </x-slot:strip>
        <x-slot:delivery>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="DO Date" :value="\Devlib\Locale::d($do->do_date) ?? '—'" />
                <x-dl::field.display label="Planned Shipment" :value="\Devlib\Locale::d($do->scheduled_ship_date) ?? '—'" />
                <x-dl::field.display label="Shipped" :value="\Devlib\Locale::d($do->actual_ship_date) ?? '—'" />
                <x-dl::field.display label="Postcode" :value="$do->ship_postal_code ?? '—'" />
            </x-dl::form.row>
        </x-slot:delivery>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Goods Shipped" key-field="id_line" :readonly="true" :min-rows="0" :rows="$lines" source="{{ Src::of('tm2sdi.do_line (tanpa harga)') }}"
                :columns="[
                    ['key' => 'item_description', 'label' => 'Item', 'type' => 'display', 'width' => '420px'],
                    ['key' => 'qty_do', 'label' => 'Qty', 'type' => 'number', 'sum' => true, 'width' => '120px'],
                    ['key' => 'qty_unit', 'label' => t('Unit'), 'type' => 'display', 'width' => '90px'],
                    ['key' => 'co', 'label' => 'Customer Order', 'type' => 'display', 'more' => true],
                ]" />
        </x-slot:detail>
    </x-dl::form>
@endif
