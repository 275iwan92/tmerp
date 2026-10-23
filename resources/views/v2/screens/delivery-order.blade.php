{{--
 REMARK
 Tujuan       : Menyusun Delivery Order dari jadwal kirim CO yang Confirmed: Draft, Confirm, Shipped, Cancel beralasan.
 Sumber hulu  : App\Support\V2\Data\DeliveryOrder; model Sdi\DoDelivery; grid DeliveryOrderListGrid; jadwal kirim CPDEL.
 Input        : Query id, new; session v2_flash dan errors.
 Output       : Daftar lewat _dbgrid; form pilih customer (route v2.do.create), tarik baris jadwal kirim, qty, harga, alamat kirim; Confirm melahirkan nomor DO dan Surat Jalan.
 Dipakai oleh : Rute v2.screen dengan slug 'delivery-order' (menu v2:delivery-order di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (Shipped = barang keluar = Closed).
--}}
@php
    use App\Models\Sdi\DoDelivery;
    use App\Support\V2\Data\DeliveryOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'delivery-order');
    $do = request('id') ? DoDelivery::with(['customer', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no')])->find((int) request('id')) : null;
    abort_if(request('id') && ! $do, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $do && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Sales\DeliveryOrderListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Sales\DeliveryOrderListGrid]])
@elseif (! $do)
    <x-dl::action-form action="{{ route('v2.do.create') }}">
        <x-dl::card title="+ New Record — Delivery Order" description="Choose a customer. DO lines are pulled from the delivery schedule of Confirmed Customer Orders.">
            <x-dl::field.select name="customer_id" label="Customer" required :options="DeliveryOrder::customerOptions()" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create DO" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    {{-- Form Master-Detail DevLib (disetujui user 2026-09-30). --}}
    @php
        $draft = $do->status === 'DRAFT';
        $price = (bool) auth()->user()?->canViewSalesPrice(); // harga jual hanya untuk yang berhak (rulebook A.9)
        $money = fn ($v) => 'IDR '.\Devlib\Locale::number((float) $v, 0);
        $area = $do->ship_area_id ? $do->ship_area_level.':'.$do->ship_area_id : null;
        $value = (float) $do->lines->sum('subtotal');
        $lines = $do->lines->map(fn ($l) => [
            'id_line' => $l->id_line, 'item_description' => $l->item_description, 'qty_do' => (float) $l->qty_do, 'qty_unit' => $l->qty_unit,
            'unit_price' => (float) $l->unit_price, 'co' => '#'.$l->co_order_id.' · item '.$l->co_order_item_id,
            'price_note' => $l->price_overridden ? tf('Changed from :price', ['price' => $money($l->original_unit_price)]) : t('CO price'), 'invoiced' => \Devlib\Locale::decimal($l->qty_invoiced, 2),
        ])->values()->all();
    @endphp
    <x-dl::form id="do-form" :title="'Delivery Order · '.raw($do->do_no ?: 'Draft #'.$do->id_delivery)" :subtitle="raw(($do->customer?->customer_name ?? '—').' · '.$do->status)"
        panel="top" kind="document" :status="DeliveryOrder::formStatus($do)" :pipeline="true"
        :action="route('v2.do.save', $do->id_delivery)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :print-url="\App\Support\V2\Print\PrintDocs::url('delivery-order', $do->id_delivery)" :hide="['delete', 'duplicate']"
        :tabs="['general' => 'General', 'delivery' => 'Delivery', 'notes' => 'Notes']"
        :created-by="$do->created_by" :created-at="$do->created_at?->toDateTimeString()" :updated-by="$do->updated_by" :updated-at="$do->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if (in_array($do->status, ['CONFIRMED', 'SHIPPED'], true))
                <x-dl::button size="sm" variant="secondary" icon="print" :label="t('Delivery Note')" :href="route('v2.screen', 'delivery-notes').'?id='.$do->id_delivery" />
            @endif
            @if ($do->status === 'CONFIRMED')<x-dl::button size="sm" variant="success" icon="fa-truck" :label="t('Shipped')" type="submit" name="action" value="ship" formnovalidate :onclick="'return confirm('.json_encode(t('Mark the goods as shipped?')).')'" />@endif
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="DO No." :value="$do->do_no ?: t('(on Confirm)')" />
            <x-dl::field.date name="do_date" label="DO Date" :value="$do->do_date?->toDateString()" :source="Src::of('tm2sdi.do_delivery.do_date')" />
            <x-dl::field.display label="Customer" :value="($do->customer?->customer_name ?? '—').' ('.($do->customer?->customer_code ?? '—').')'" :source="Src::of('tm2mst.cust_customer')" />
            @if ($price)<x-dl::strip-kv kind="grand" label="Shipment Value" :value="$money($value)" :value-attrs="['data-l-val' => 'lines:grand']" />@endif
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="DO Type" :value="($do->do_type ?? 'CUSTOMER').($do->subcon_process ? ' · '.$do->subcon_process : '')" :source="Src::of('tm2sdi.do_delivery.do_type')" />
                <x-dl::field.display label="Tax" :value="$do->is_ppn ? t('VAT (follows the CO)') : t('Non VAT')" :source="Src::of('tm2sdi.do_delivery.is_ppn')" />
                @if ($price)<x-dl::field.display label="Price changes" :value="$do->price_change_flag ? t('Yes').' ('.($do->price_change_approval_status ?? 'pending').')' : t('No')" :source="Src::of('tm2sdi.do_delivery.price_change_flag')" />@endif
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:delivery>
            @php $track = \App\Support\V2\Data\DeliveryTrack::props($do); @endphp
            <x-dl::delivery-track :stage="$track['stage']" :from="$track['from']" :to="$track['to']" :times="$track['times']" />
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="scheduled_ship_date" label="Planned Shipment" :value="$do->scheduled_ship_date?->toDateString()" :source="Src::of('tm2sdi.do_delivery.scheduled_ship_date')" />
                <x-dl::field.display label="Actual Delivery Date" :value="\Devlib\Locale::d($do->actual_ship_date) ?? '—'" :source="Src::of('tm2sdi.do_delivery.actual_ship_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.lookup name="ship_area" lookup="geo_area" label="Destination Area" placeholder="Search village / district / city / postcode..." :value="$area" :source="Src::of('tm2sdi.do_delivery.ship_area_id', 'ship_area_level', 'ship_address_label')" />
                <x-dl::field.text name="ship_address_detail" label="Address Details (street, no., RT/RW, landmark)" :value="$do->ship_address_detail" :source="Src::of('tm2sdi.do_delivery.ship_address_detail')" />
                <x-dl::field.display label="Postcode" :value="$do->ship_postal_code ?? '—'" :source="Src::of('tm2sdi.do_delivery.ship_postal_code')" />
            </x-dl::form.row>
        </x-slot:delivery>
        <x-slot:notes>
            <x-dl::field.display label="Notes" :value="$do->notes ?? '—'" :source="Src::of('tm2sdi.do_delivery.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="id_line" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                :totals="$price ? ['of' => 'subtotal'] : []" source="{{ Src::of('tm2sdi.do_line') }}"
                :columns="[
                    ['key' => 'item_description', 'label' => 'Item', 'type' => 'display', 'width' => '340px'],
                    ['key' => 'qty_do', 'label' => t('Ship Qty'), 'type' => 'number', 'sum' => true, 'width' => '110px'],
                    ['key' => 'qty_unit', 'label' => t('Unit'), 'type' => 'display', 'width' => '80px'],
                    ...($price ? [
                        ['key' => 'unit_price', 'label' => t('Price'), 'type' => 'money', 'width' => '130px'],
                        ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty_do * unit_price', 'sum' => true, 'width' => '140px'],
                    ] : []),
                    ['key' => 'co', 'label' => 'Customer Order', 'type' => 'display', 'more' => true],
                    ...($price ? [['key' => 'price_note', 'label' => t('Price Source'), 'type' => 'display', 'more' => true]] : []),
                    ['key' => 'invoiced', 'label' => t('Invoiced'), 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        @php $opts = DeliveryOrder::outstandingOptions($do); @endphp
                        @if ($opts)
                            <x-dl::field.multi-select name="co_delivery_ids" label="+ Pull from CO delivery schedule" :options="$opts" :source="Src::of('tm2sdi.v_co_delivery_outstanding')" />
                            <x-dl::button size="sm" variant="secondary" :label="t('+ Pull Lines')" type="submit" name="action" value="pull" formnovalidate />
                        @else
                            <x-dl::badge tone="info" icon="fa-industry" :label="t('Only schedule lines that are Posted to Production (Customer Plan Delivery) can be pulled')" />
                        @endif
                    </x-slot:tools>
                @endif
            </x-dl::lines>
        </x-slot:detail>
    </x-dl::form>
@endif
