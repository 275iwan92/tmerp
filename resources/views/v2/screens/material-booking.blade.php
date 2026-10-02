{{-- Material Booking v2 (docs/materialbooking_tier1.md). Isi = App\Services\Planning\MaterialPlanningEngine lewat App\Support\V2\Data\MaterialPlanning.
     Alur layar: GRID semua CO aktif + forecast (kebutuhan sudah tampil sejak CO Draft, tanpa memesan stok) -> klik 2x CO ->
     baris bahan dengan rumus & sumber penutup -> Recalculate / Pick Item (item belum dipilih) / Confirm (kunci stok) / Back to Draft.
     Booking hanya menulis transaksi (rm_booking*); Master Product tidak diubah. --}}
@php
    use App\Models\Inventory\RmBooking;
    use App\Models\Inventory\RmBookingD;
    use App\Models\Sdi\CoOrder;
    use App\Support\V2\Data\MaterialPlanning;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $grid = $screen['grid'];
    $home = $grid->url();
    $co = request('id') ? CoOrder::find((int) request('id')) : null;
    abort_if(request('id') && ! $co, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $co)
    <x-dl::alert tone="info" :message="str_replace(':n', Locale::number((float) \App\Models\Sys\SysConstant::number('BOOKING_NEED_WORKDAYS', 3), 0), t('Material needs are shown from the moment a Customer Order is entered. Stock is reserved only after the order is Confirmed and the booking is confirmed (locked) here. Material must be ready by the due date, or at the latest :n working days after the CO date.'))" />
    <x-dl::action-bar>
        <form method="post" action="{{ route('v2.planning.run-all') }}">@csrf<x-dl::button type="submit" variant="secondary" icon="fa-rotate" :label="t('Recalculate All')" /></form>
        <x-dl::button variant="secondary" icon="fa-chart-gantt" :label="t('Material Planning (MRP)')" :href="route('v2.screen', 'material-planning')" />
        <x-dl::button variant="secondary" icon="fa-calendar-week" :label="t('Demand Forecast (MPS)')" :href="route('v2.screen', 'demand-forecast')" />
    </x-dl::action-bar>
    @include('v2.screens._dbgrid', ['screen' => $screen])
@else
    @php
        $b = RmBooking::where('co_id', $co->id_order)->where('stat_cancel', 0)->first();
        $rows = MaterialPlanning::lineRows((int) $co->id_order);
        $confirmedCo = $co->doc_status === 'CONFIRMED';
        $locked = (bool) $b?->stat_confirm;
        $changed = $locked && $b->co_fingerprint !== MaterialPlanning::engine()->fingerprint((int) $co->id_order);
        $pickable = $b && ! $locked ? RmBookingD::where('booking_id', $b->booking_id)->whereIn('line_status', ['NEED_ITEM'])->orderBy('seq_no')->get() : collect();
        $open = collect($rows)->whereIn('status', ['Short', 'Need item', 'Need layout'])->count();
        $customer = \Illuminate\Support\Facades\DB::connection('tm2mst')->table('cust_customer')->where('id_customer', $co->customer_id)->value('customer_name');
    @endphp
    <x-dl::card>
        <form method="post" action="{{ route('v2.planning.booking', $co->id_order) }}">
            @csrf
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <span class="mp-chips">
                    <x-dl::badge :label="$co->co_no" />
                    <x-dl::badge :label="$co->doc_status" :tone="MaterialPlanning::CO_TONES[$co->doc_status] ?? 'neutral'" />
                    <x-dl::badge :label="$b ? $b->booking_no.' · '.$b->doc_status : 'NOT BOOKED'" :tone="MaterialPlanning::BOOKING_TONES[$b->doc_status ?? 'NOT BOOKED'] ?? 'neutral'" />
                </span>
                <x-dl::button variant="secondary" icon="send" :label="t('Open Customer Order')" :href="route('v2.screen', 'customer-order').'?id='.$co->id_order" />
                <x-dl::button preset="print" :href="\App\Support\V2\Print\PrintDocs::url('material-booking', $co->id_order)" target="_blank" />
                @if ($confirmedCo && ! $locked)
                    <x-dl::button type="submit" name="action" value="run" variant="secondary" icon="fa-rotate" :label="t('Recalculate')" />
                    @if ($b)<x-dl::button type="submit" name="action" value="confirm" variant="success" icon="confirm" label="Confirm" />@endif
                @elseif ($locked)
                    <x-dl::button type="submit" name="action" value="unlock" variant="secondary" icon="fa-lock-open" label="Back to Draft" />
                @endif
            </x-dl::action-bar>
        </form>
        <x-dl::form.row cols="4">
            <x-dl::field.display label="Customer" :value="$customer ?? '—'" :source="Src::of('tm2sdi.co_order.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <x-dl::field.display label="CO Date" :value="Locale::d($co->co_date) ?? '—'" :source="Src::of('tm2sdi.co_order.co_date')" />
            <x-dl::field.display label="Last Calculated" :value="$b?->last_run_at ? Locale::dt($b->last_run_at) : '—'" :source="Src::of('tm2inv.rm_booking.last_run_at')" />
            <x-dl::field.display label="Confirmed By" :value="$locked ? $b->confirm_by.' · '.Locale::dt($b->confirm_date) : '—'" :source="Src::of('tm2inv.rm_booking.confirm_by', 'confirm_date')" />
        </x-dl::form.row>
        @if (! $confirmedCo)
            <x-dl::alert tone="info" :message="t('This Customer Order is not Confirmed yet: the material need below is a preview only. No stock is reserved until the order is Confirmed.')" />
        @elseif ($changed)
            <x-dl::alert tone="danger" :message="t('The Customer Order changed after this booking was confirmed (quantity, date or delivery plan). Use Back to Draft to recalculate, then Confirm again.')" />
        @elseif ($locked)
            <x-dl::alert tone="success" :message="t('Booking confirmed: the stock part is locked for this order and no longer counted as Available.')" />
        @elseif ($open > 0)
            <x-dl::alert tone="danger" :message="raw(str_replace(':n', (string) $open, t(':n material lines are not covered yet (short, item not chosen, or layout missing). You can still Confirm to lock the stock that is available; create a Purchase Requisition for the rest in Material Planning.')))" />
        @endif
    </x-dl::card>

    <x-dl::grid id="material-booking-lines" :rows="$rows" mode="report" actions="none" :active-key="null" :new-record="null" search="Search product, material..."
        source="{{ Src::of('tm2inv.rm_booking_d', 'tm2inv.rm_booking_alloc', 'tm2mst.prod_layout_group', 'tm2mst.mat_usage_standard', 'tm2inv.v_rm_available', 'tm2pur.po_line', 'tm2pur.pr_line') }}"
        :columns="[
            ['key' => 'material', 'label' => 'Material', 'type' => 'identity', 'code' => 'role', 'avatar' => false],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'part', 'label' => 'Part'],
            ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
            ['key' => 'qty', 'label' => 'Qty Needed', 'type' => 'number', 'decimals' => 2],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'covered', 'label' => 'Covered By'],
            ['key' => 'short', 'label' => 'Short', 'type' => 'number', 'decimals' => 2],
            ['key' => 'status', 'label' => 'Coverage', 'type' => 'badge', 'tones' => MaterialPlanning::COVER_TONES],
            ['key' => 'calc', 'label' => 'Calculation'],
            ['key' => 'role', 'label' => 'Kind', 'visible' => false],
        ]"
        :stats="[
            ['label' => 'All'],
            ['label' => 'Not covered', 'where' => ['status' => ['Short', 'Need item', 'Need layout']], 'tone' => 'danger'],
            ['label' => 'Late', 'where' => ['status' => 'Late'], 'tone' => 'warn'],
            ['label' => 'Waiting PO/PR', 'where' => ['status' => 'Waiting PO/PR'], 'tone' => 'info'],
            ['label' => 'Covered', 'where' => ['status' => ['Covered by stock', 'Booked']], 'tone' => 'success'],
        ]"
        :filters="['product' => 'Product', 'role' => 'Kind', 'status' => 'Coverage']" />

    @if ($pickable->isNotEmpty())
        <form method="post" action="{{ route('v2.planning.pick', $co->id_order) }}">
            @csrf
            <x-dl::card :title="t('Pick Item')" icon="fa-hand-pointer" :description="t('Master Product only has the specification for these lines. Choose the stock item to book; the choice is kept on this booking (Master Product is not changed).')">
                <x-dl::form.row cols="2">
                    <x-dl::field.select name="line_id" label="Line" required :options="$pickable->mapWithKeys(fn ($d) => [$d->booking_detail_id => $d->seq_no.' · '.$d->material_role.' · '.($d->spec_text ?: 'no specification').' · '.Locale::number((float) $d->qty_need, 2).' '.$d->uom_code])->all()"
                        :source="Src::of('tm2inv.rm_booking_d (line_status = NEED_ITEM)')" />
                    <x-dl::field.lookup name="material_id" lookup="material" label="Stock Item" required placeholder="Type at least 2 letters of the item name / code..." :source="Src::of('tm2mst.mat_raw')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button type="submit" icon="confirm" :label="t('Pick Item')" /></x-dl::action-bar>
            </x-dl::card>
        </form>
    @elseif ($confirmedCo && ! $b)
        <x-dl::alert tone="info" :message="t('Click Recalculate to save the booking proposal for this order; after that items can be picked and the booking confirmed.')" />
    @endif
@endif
