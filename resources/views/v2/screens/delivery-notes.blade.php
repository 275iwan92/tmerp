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

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $do)
    <x-dl::grid id="sj-list" :rows="DeliveryOrder::rows(['CONFIRMED', 'SHIPPED'])" search="Cari no. surat jalan, customer, alamat..." actions="worklist" :active-key="null"
        :new-record="false" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2sdi.do_delivery (CONFIRMED / SHIPPED)') }}"
        :columns="[
            ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'ship', 'label' => 'Rencana Kirim', 'type' => 'date'],
            ['key' => 'shipped', 'label' => 'Dikirim', 'type' => 'date'],
            ['key' => 'address', 'label' => 'Tujuan'],
            ['key' => 'lines', 'label' => 'Baris', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => DeliveryOrder::STATUS_TONES],
        ]"
        :stats="[['label' => 'Semua'], ['label' => 'Siap kirim', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Terkirim', 'where' => ['status' => 'SHIPPED'], 'tone' => 'info']]"
        :filters="['customer' => 'Customer', 'status' => 'Status']" />
@else
    @php
        $lines = $do->lines->map(fn ($l) => ['id_line' => $l->id_line, 'item_description' => $l->item_description, 'qty_do' => (float) $l->qty_do, 'qty_unit' => $l->qty_unit,
            'co' => '#'.$l->co_order_id.' · item '.$l->co_order_item_id])->values()->all();
    @endphp
    <x-dl::form id="sj-form" :title="'Surat Jalan · '.$do->do_no" :subtitle="($do->customer?->customer_name ?? '—').' · '.$do->status"
        panel="top" kind="document" :status="DeliveryOrder::formStatus($do)" :pipeline="true" :can-input="false"
        :action="route('v2.do.save', $do->id_delivery)" action-field="action" :back="$home" :hidden="['from' => 'delivery-notes']" :hide="['cancel', 'draft', 'email', 'print']"
        :tabs="['delivery' => 'Delivery']"
        :created-by="$do->created_by" :created-at="$do->created_at?->toDateTimeString()" :updated-by="$do->updated_by" :updated-at="$do->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" preset="print" label="Print Surat Jalan" :href="route('dispatch.do.print-surat-jalan', ['doId' => $do->id_delivery])" target="_blank" />
            @if ($do->status === 'CONFIRMED')<x-dl::button size="sm" variant="success" icon="fa-truck" label="Shipped" type="submit" name="action" value="ship" formnovalidate onclick="return confirm('Tandai barang sudah dikirim?')" />@endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No. Surat Jalan</span><b>{{ $do->do_no }}</b></div>
            <x-dl::field.display label="Customer" :value="$do->customer?->customer_name ?? '—'" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::field.display label="Tujuan" :value="trim(($do->ship_address_detail ? $do->ship_address_detail.', ' : '').($do->ship_address_label ?? ''), ', ') ?: '—'" :source="Src::of('tm2sdi.do_delivery.ship_address_label')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Total Qty</span><b>{{ \Devlib\Locale::number((float) $do->lines->sum('qty_do'), 0) }}</b></div>
        </x-slot:strip>
        <x-slot:delivery>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Tanggal DO" :value="\Devlib\Locale::d($do->do_date) ?? '—'" />
                <x-dl::field.display label="Rencana Kirim" :value="\Devlib\Locale::d($do->scheduled_ship_date) ?? '—'" />
                <x-dl::field.display label="Dikirim" :value="\Devlib\Locale::d($do->actual_ship_date) ?? '—'" />
                <x-dl::field.display label="Kode Pos" :value="$do->ship_postal_code ?? '—'" />
            </x-dl::form.row>
        </x-slot:delivery>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Barang Dikirim" key-field="id_line" :readonly="true" :min-rows="0" :rows="$lines" source="{{ Src::of('tm2sdi.do_line (tanpa harga)') }}"
                :columns="[
                    ['key' => 'item_description', 'label' => 'Item', 'type' => 'display', 'width' => '420px'],
                    ['key' => 'qty_do', 'label' => 'Qty', 'type' => 'number', 'sum' => true, 'width' => '120px'],
                    ['key' => 'qty_unit', 'label' => 'Satuan', 'type' => 'display', 'width' => '90px'],
                    ['key' => 'co', 'label' => 'Customer Order', 'type' => 'display', 'more' => true],
                ]" />
        </x-slot:detail>
    </x-dl::form>
@endif
