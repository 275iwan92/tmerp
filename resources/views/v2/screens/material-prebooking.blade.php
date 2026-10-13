{{--
 REMARK
 Tujuan       : Pre-Booking kebutuhan kertas sejak ProCost terbit sebelum ada Customer Order (B0 sampai B2), bersifat lunak: tidak mengurangi Available; Hold menahan stok beberapa hari kerja.
 Sumber hulu  : App\Support\V2\Data\PreBookings dan MaterialPlanning (spec docs/md_for_systems/inventorywarehouse_v201_scope.md bagian 6).
 Input        : Query id (pre-booking); session v2_flash dan error validasi.
 Output       : Grid pre-booking (view _dbgrid) dengan tombol Recalculate All (rute v2.planning.prebooking.run), atau kartu rincian x-dl::card per pre-booking.
 Dipakai oleh : Layar v2 slug material-prebooking lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\MaterialPlanning;
    use App\Support\V2\Data\PreBookings;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $grid = $screen['grid'];
    $home = $grid->url();
    $b = request('id') ? PreBookings::find((int) request('id')) : null;
    abort_if(request('id') && ! $b, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $b)
    <x-dl::action-form action="{{ route('v2.planning.prebooking.run') }}">
        <x-dl::action-bar align="start">
            <x-dl::button variant="secondary" icon="fa-rotate" label="Recalculate All" type="submit" />
        </x-dl::action-bar>
    </x-dl::action-form>
    <x-dl::alert tone="info" message="Material needs appear here as soon as Pro-Cost is published, before a Customer Order exists. They do not reduce the available stock; a shortage of a long lead time material shows in Material Planning marked Not CO yet." />
    @include('v2.screens._dbgrid', ['screen' => $screen])
@else
    @php
        $desk = PreBookings::desk($b);
        $row = $desk['row'];
    @endphp
    <x-dl::card :title="'Pre-Booking · '.raw($b->booking_no)" icon="fa-hourglass-half"
        :description="raw(($row['inquiry'] ?? '—').' · '.($row['customer'] ?? '—').' · '.($row['product'] ?? '—'))">
        <x-dl::alert :tone="$desk['alert'][0]" :message="raw($desk['alert'][1])" />
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Stage" :value="t($row['stage'] ?? '—')" :source="Src::of('tm2inv.rm_booking.stage')" />
            <x-dl::field.display label="Booking" :value="t($row['status'] ?? '—')" :source="Src::of('tm2inv.rm_booking.doc_status')" />
            <x-dl::field.display label="Hold Until" :value="Locale::d($b->hold_until) ?? '—'" :source="Src::of('tm2inv.rm_booking.hold_until')" />
            <x-dl::field.display label="Last Calculated" :value="$b->last_run_at ? Locale::dt($b->last_run_at) : '—'" :source="Src::of('tm2inv.rm_booking.last_run_at')" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
            @if ($desk['held'])
                <x-dl::action-form action="{{ route('v2.planning.prebooking.act', $b->booking_id) }}">
                    <input type="hidden" name="action" value="release">
                    <x-dl::button variant="secondary" icon="fa-unlock" label="Release Hold" type="submit" />
                </x-dl::action-form>
            @else
                <x-dl::action-form action="{{ route('v2.planning.prebooking.act', $b->booking_id) }}">
                    <input type="hidden" name="action" value="hold">
                    <x-dl::field.text name="reason" label="Reason" placeholder="Why this material must be held" :maxlength="255" />
                    <x-dl::button icon="fa-lock" label="Hold" type="submit" />
                </x-dl::action-form>
            @endif
        </x-dl::action-bar>
    </x-dl::card>

    @if ($desk['pick'])
        <x-dl::card title="Pick Item" icon="fa-hand-pointer" description="The costing gives the paper size and grammage but not the stock item. Choose the item for each line so it can be checked against stock and ordered.">
            <x-dl::action-form action="{{ route('v2.planning.prebooking.pick', $b->booking_id) }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="line_id" label="Line" required :options="$desk['pick']" />
                    <x-dl::field.lookup name="material_id" lookup="material" label="Stock Item" required placeholder="Type at least 2 letters of the item name / code..." :source="Src::of('tm2mst.mat_raw')" />
                </x-dl::form.row>
                <x-dl::action-bar align="start"><x-dl::button preset="save" label="Pick" /></x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::card>
    @endif

    <x-dl::grid id="material-prebooking-lines" :rows="PreBookings::lineRows($b)" mode="report" actions="none" :active-key="null" :new-record="null" search="Search material..."
        source="{{ Src::of('tm2inv.rm_booking_d', 'tm2inv.rm_booking_alloc', 'tm2sdi.iqp_costing_part', 'tm2inv.v_rm_available', 'tm2pur.po_line', 'tm2pur.pr_line') }}"
        :columns="[
            ['key' => 'material', 'label' => 'Material', 'type' => 'identity', 'code' => 'role', 'avatar' => false],
            ['key' => 'spec', 'label' => 'Specification'],
            ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
            ['key' => 'qty', 'label' => 'Qty Needed', 'type' => 'number', 'decimals' => 2],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'covered', 'label' => 'Covered By'],
            ['key' => 'short', 'label' => 'Short', 'type' => 'number', 'decimals' => 2],
            ['key' => 'status', 'label' => 'Coverage', 'type' => 'badge', 'tones' => MaterialPlanning::COVER_TONES],
            ['key' => 'calc', 'label' => 'Calculation'],
            ['key' => 'role', 'label' => 'Kind', 'visible' => false],
        ]" />
@endif
