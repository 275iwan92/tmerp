{{-- Delivery Order v2. Flow & aturan = tmerp pages/dispatch/do (list + entry) lewat App\Support\V2\Data\DeliveryOrder.
     Alur: GRID DO -> + New Record = pilih customer -> DRAFT: tarik baris dari jadwal kirim CO (CPDEL) yang CONFIRMED, atur qty & harga,
     alamat kirim -> Confirm (nomor DO lahir, Surat Jalan siap dicetak) -> Shipped (barang keluar = Closed). Cancel wajib alasan. --}}
@php
    use App\Models\Sdi\DoDelivery;
    use App\Support\V2\Data\DeliveryOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'delivery-order');
    $do = request('id') ? DoDelivery::with(['customer', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no')])->find((int) request('id')) : null;
    abort_if(request('id') && ! $do, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $do && ! request('new'))
    <x-dl::grid id="do-list" :rows="DeliveryOrder::rows()" search="Cari no. DO, customer, alamat..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2sdi.do_delivery', 'tm2sdi.do_line') }}"
        :columns="[
            ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'ship', 'label' => 'Rencana Kirim', 'type' => 'date'],
            ['key' => 'address', 'label' => 'Tujuan'],
            ['key' => 'lines', 'label' => 'Baris', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => DeliveryOrder::STATUS_TONES],
        ]"
        :stats="[['label' => 'Semua'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Shipped', 'where' => ['status' => 'SHIPPED'], 'tone' => 'info']]"
        :filters="['customer' => 'Customer', 'status' => 'Status']" />
@elseif (! $do)
    <form method="post" action="{{ route('v2.do.create') }}">
        @csrf
        <x-dl::card title="+ New Record — Delivery Order" description="Pilih customer. Baris DO ditarik dari jadwal kirim Customer Order yang sudah Confirm.">
            <x-dl::field.select name="customer_id" label="Customer" required :options="DeliveryOrder::customerOptions()" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Buat DO" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    {{-- Form Master-Detail DevLib (disetujui user 2026-09-30). --}}
    @php
        $draft = $do->status === 'DRAFT';
        $money = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        $area = $do->ship_area_id ? $do->ship_area_level.':'.$do->ship_area_id : null;
        $value = (float) $do->lines->sum('subtotal');
        $lines = $do->lines->map(fn ($l) => [
            'id_line' => $l->id_line, 'item_description' => $l->item_description, 'qty_do' => (float) $l->qty_do, 'qty_unit' => $l->qty_unit,
            'unit_price' => (float) $l->unit_price, 'co' => '#'.$l->co_order_id.' · item '.$l->co_order_item_id,
            'price_note' => $l->price_overridden ? 'Diubah dari '.$money($l->original_unit_price) : 'Harga CO', 'invoiced' => rtrim(rtrim(number_format((float) $l->qty_invoiced, 2, '.', ''), '0'), '.') ?: '0',
        ])->values()->all();
    @endphp
    <x-dl::form id="do-form" :title="'Delivery Order · '.($do->do_no ?: 'Draft #'.$do->id_delivery)" :subtitle="($do->customer?->customer_name ?? '—').' · '.$do->status"
        panel="top" kind="document" :status="DeliveryOrder::formStatus($do)" :pipeline="true"
        :action="route('v2.do.save', $do->id_delivery)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['delete', 'duplicate', 'email', 'print']"
        :tabs="['general' => 'General', 'delivery' => 'Delivery', 'notes' => 'Notes']"
        :created-by="$do->created_by" :created-at="$do->created_at?->toDateTimeString()" :updated-by="$do->updated_by" :updated-at="$do->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if (in_array($do->status, ['CONFIRMED', 'SHIPPED'], true))
                <x-dl::button size="sm" preset="print" label="Nota DO" :href="route('dispatch.do.print', ['doId' => $do->id_delivery])" target="_blank" />
                <x-dl::button size="sm" variant="secondary" icon="print" label="Surat Jalan" :href="route('v2.screen', 'delivery-notes').'?id='.$do->id_delivery" />
            @endif
            @if ($do->status === 'CONFIRMED')<x-dl::button size="sm" variant="success" icon="fa-truck" label="Shipped" type="submit" name="action" value="ship" formnovalidate onclick="return confirm('Tandai barang sudah dikirim?')" />@endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No. DO</span><b>{{ $do->do_no ?: '(saat Confirm)' }}</b></div>
            <x-dl::field.date name="do_date" label="Tanggal DO" :value="$do->do_date?->toDateString()" :source="Src::of('tm2sdi.do_delivery.do_date')" />
            <x-dl::field.display label="Customer" :value="($do->customer?->customer_name ?? '—').' ('.($do->customer?->customer_code ?? '—').')'" :source="Src::of('tm2mst.cust_customer')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Nilai Kirim</span><b data-l-val="lines:grand">{{ $money($value) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Jenis DO" :value="($do->do_type ?? 'CUSTOMER').($do->subcon_process ? ' · '.$do->subcon_process : '')" :source="Src::of('tm2sdi.do_delivery.do_type')" />
                <x-dl::field.display label="Pajak" :value="$do->is_ppn ? 'PPN (ikut CO)' : 'Non PPN'" :source="Src::of('tm2sdi.do_delivery.is_ppn')" />
                <x-dl::field.display label="Perubahan harga" :value="$do->price_change_flag ? 'Ada ('.($do->price_change_approval_status ?? 'pending').')' : 'Tidak'" :source="Src::of('tm2sdi.do_delivery.price_change_flag')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:delivery>
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="scheduled_ship_date" label="Rencana Kirim" :value="$do->scheduled_ship_date?->toDateString()" :source="Src::of('tm2sdi.do_delivery.scheduled_ship_date')" />
                <x-dl::field.display label="Tanggal Kirim Aktual" :value="$do->actual_ship_date?->format('d-M-Y') ?? '—'" :source="Src::of('tm2sdi.do_delivery.actual_ship_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.lookup name="ship_area" lookup="geo_area" label="Wilayah Tujuan" placeholder="Cari kelurahan / kecamatan / kota / kode pos..." :value="$area" :source="Src::of('tm2sdi.do_delivery.ship_area_id', 'ship_area_level', 'ship_address_label')" />
                <x-dl::field.text name="ship_address_detail" label="Detail Alamat (jalan, no., RT/RW, patokan)" :value="$do->ship_address_detail" :source="Src::of('tm2sdi.do_delivery.ship_address_detail')" />
                <x-dl::field.display label="Kode Pos" :value="$do->ship_postal_code ?? '—'" :source="Src::of('tm2sdi.do_delivery.ship_postal_code')" />
            </x-dl::form.row>
        </x-slot:delivery>
        <x-slot:notes>
            <x-dl::field.display label="Catatan" :value="$do->notes ?? '—'" :source="Src::of('tm2sdi.do_delivery.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="id_line" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                :totals="['of' => 'subtotal']" source="{{ Src::of('tm2sdi.do_line') }}"
                :columns="[
                    ['key' => 'item_description', 'label' => 'Item', 'type' => 'display', 'width' => '340px'],
                    ['key' => 'qty_do', 'label' => 'Qty Kirim', 'type' => 'number', 'sum' => true, 'width' => '110px'],
                    ['key' => 'qty_unit', 'label' => 'Satuan', 'type' => 'display', 'width' => '80px'],
                    ['key' => 'unit_price', 'label' => 'Harga', 'type' => 'money', 'width' => '130px'],
                    ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty_do * unit_price', 'sum' => true, 'width' => '140px'],
                    ['key' => 'co', 'label' => 'Customer Order', 'type' => 'display', 'more' => true],
                    ['key' => 'price_note', 'label' => 'Sumber Harga', 'type' => 'display', 'more' => true],
                    ['key' => 'invoiced', 'label' => 'Sudah Ditagih', 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        @php $opts = DeliveryOrder::outstandingOptions($do); @endphp
                        @if ($opts)
                            <x-dl::field.multi-select name="co_delivery_ids" label="+ Tarik dari jadwal kirim CO" :options="$opts" :source="Src::of('tm2sdi.v_co_delivery_outstanding')" />
                            <x-dl::button size="sm" variant="secondary" label="+ Tarik Baris" type="submit" name="action" value="pull" formnovalidate />
                        @endif
                    </x-slot:tools>
                @endif
            </x-dl::lines>
        </x-slot:detail>
    </x-dl::form>
@endif
