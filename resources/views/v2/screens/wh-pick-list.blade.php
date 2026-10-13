{{--
 REMARK
 Tujuan       : Pick List: alokasi palet / batch / bin untuk Work Order dari booking bahan Confirm (FIFO / FEFO), pindai palet lalu bin, Confirm Pick = Stock Out RM ke WO.
 Sumber hulu  : App\Support\V2\Data\PickLists; PickAllocator dan App\Services\Inventory\PickListService; model WhPickList; PrintDocs.
 Input        : Query id (pick list), new (form baru); session v2_flash dan errors.
 Output       : Daftar via _dbgrid (PickListGrid), form pilih WO, rincian dan kotak pindai; POST ke v2.wh.pick.create, .scan, .act.
 Dipakai oleh : route v2.screen (routes/v2.php), slug wh-pick-list (menu Warehouse).
 Status       : Aktif.
--}}
@php
    use App\Models\Inventory\WhPickList;
    use App\Support\V2\Data\PickLists;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $grid = $screen['grid'];
    $home = $grid->url();
    $list = request('id') ? PickLists::find((int) request('id')) : null;
    abort_if(request('id') && ! $list, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $list && ! request('new'))
    @include('v2.screens._dbgrid', ['screen' => $screen])
@elseif (! $list)
    <x-dl::action-form action="{{ route('v2.wh.pick.create') }}">
        <x-dl::card title="+ New Record — Pick List" description="Choose the work order. The pick list is made from its confirmed material booking: the system allocates pallets, batches and bins, and marks what it cannot cover as short.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="wo_id" label="Work Order" required :options="PickLists::workOrderOptions()" :source="Src::of('tm2prd.prd_wo', 'tm2inv.rm_booking')" />
                <x-dl::field.select name="deliver_to_location_id" label="Deliver To" :options="PickLists::stagingOptions()" :source="Src::of('tm2mst.wh_loc')"
                    hint="Production staging bin where the material is taken. Left empty, the first staging bin is used." />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $open = $list->isOpen();
        $cards = PickLists::materialCards($list);
        $shorts = PickLists::shortRows($list);
        $meta = PickLists::header($list);
        $stage = $list->status === 'IN_PROGRESS' ? 'In progress' : ($open ? 'Draft' : null);
        $columns = [
            ['key' => 'no', 'label' => '#', 'type' => 'number'],
            ['key' => 'pallet', 'label' => 'Pallet'],
            ['key' => 'batch', 'label' => 'Batch'],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'received', 'label' => 'Received', 'type' => 'date'],
            ['key' => 'expiry', 'label' => 'Expiry', 'type' => 'date'],
            ['key' => 'pick', 'label' => 'Pick Qty', 'type' => 'number', 'decimals' => 2],
            ['key' => 'quality', 'label' => 'Quality', 'type' => 'badge', 'tones' => ['Released' => 'success', 'Conditional' => 'warn', 'No batch' => 'neutral']],
            ['key' => 'grain', 'label' => 'Grain'],
            ['key' => 'cert', 'label' => 'Cert.'],
            ['key' => 'scan', 'label' => 'Scan', 'type' => 'badge', 'tones' => ['Scanned' => 'success', 'Pallet scanned, scan the bin' => 'info', 'Scan pallet, then bin' => 'neutral']],
        ];
    @endphp
    <x-dl::form id="pick-list-form" :title="'Pick List · '.raw($list->pick_no)" :subtitle="raw(($list->wo_no ?: '—').' · '.($meta['booking'] ?? '—'))"
        panel="top" kind="document" :status="PickLists::formStatus($list)" :stage="$stage" :pipeline="false"
        :action="route('v2.wh.pick.act', $list->id_pick_list)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :print-url="PrintDocs::url('pick-list', $list->id_pick_list)"
        :hide="['new', 'save', 'duplicate', 'draft']" :tabs="['general' => 'General']"
        :created-by="$list->created_by" :created-at="$list->created_at?->toDateTimeString()" :updated-by="$list->updated_by" :updated-at="$list->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if ($open)
                <x-dl::button size="sm" variant="secondary" icon="fa-rotate" label="Reallocate" type="submit" name="action" value="reallocate" formnovalidate />
                <x-dl::button size="sm" variant="success" icon="fa-check" label="Confirm Pick" type="submit" name="action" value="confirm" formnovalidate />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Pick List" :value="$list->pick_no" />
            <x-dl::field.display label="Work Order" :value="$list->wo_no ?: '—'" :source="Src::of('tm2prd.prd_wo')" />
            <x-dl::field.display label="Booking" :value="trim(($meta['booking'] ?? '—').' · '.($meta['co'] ?? ''), ' ·')" :source="Src::of('tm2inv.rm_booking')" />
            <x-dl::field.display label="Need Date" :value="Locale::d($list->need_date) ?? '—'" :source="Src::of('tm2inv.wh_pick_list.need_date')" />
            <x-dl::field.display label="Deliver To" :value="$meta['deliver_to'] ?? '—'" :source="Src::of('tm2mst.wh_loc')" />
        </x-slot:strip>
        <x-slot:general>
            @if ($list->status === 'CANCELLED')
                <x-dl::field.display label="Cancel Reason" :value="$list->cancel_reason ?? '—'" :source="Src::of('tm2inv.wh_pick_list.cancel_reason')" />
            @endif
            @if ($list->stock_out_doc_no)
                <x-dl::field.display label="Stock Out" :value="$list->stock_out_doc_no" :source="Src::of('tm2inv.rm_stock')" />
            @endif
            <x-dl::field.display label="Pick Order" value="FIFO or FEFO per storage area" hint="Set per area in Storage Areas. Only batches that may be allocated are used." />
        </x-slot:general>
    </x-dl::form>

    @if ($open)
        <x-dl::card title="Scan" icon="fa-barcode" description="Scan the pallet label, then the bin label. Type the code or use the scanner and press Enter.">
            <x-dl::action-form action="{{ route('v2.wh.pick.scan', $list->id_pick_list) }}">
                <x-dl::form.row :cols="3">
                    <x-dl::field.text name="code" label="Scanned Code" :case-toggle="false" code placeholder="PLT:… or the bin code" />
                </x-dl::form.row>
                <x-dl::action-bar align="start"><x-dl::button preset="save" label="Scan" /></x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::card>
    @endif

    @foreach ($cards as $i => $c)
        <x-dl::card :title="'Material · '.raw($c['title'])" icon="fa-boxes-stacked"
            :description="raw(t('Need').' '.Locale::number($c['need'], 2).' '.$c['unit'].' · '.t('Allocated').' '.Locale::number($c['allocated'], 2).' '.$c['unit'].($c['requirement'] ? ' · '.$c['requirement'] : '').($c['note'] ? ' · '.t($c['note']) : ''))">
            @if ($c['warning'])<x-dl::alert tone="warn" :message="$c['warning']" />@endif
            @if ($c['short'] > 0)<x-dl::alert tone="danger" :message="raw(t('Short').' '.Locale::number($c['short'], 2).' '.$c['unit'])" />@endif
            <x-dl::grid id="pick-lines-{{ $list->id_pick_list }}-{{ $i }}" mode="report" actions="none" :active-key="null" :new-record="null" :per-page="50" search="Search pallet, bin..."
                source="{{ Src::of('tm2inv.wh_pick_list_line', 'tm2inv.inv_pallet', 'tm2inv.inv_batch', 'tm2mst.wh_loc') }}" :rows="$c['rows']" :columns="$columns" />
        </x-dl::card>
    @endforeach

    <x-dl::card title="Allocation That Could Not Be Covered" icon="fa-triangle-exclamation">
        @if ($shorts)
            <x-dl::alert tone="danger" message="Some material is short. Reallocate when stock arrives, open a Supplier Shortage Request, or cancel this pick list." />
            <x-dl::grid id="pick-short-{{ $list->id_pick_list }}" mode="report" actions="none" :active-key="null" :new-record="null" search="Search item..."
                source="{{ Src::of('tm2inv.wh_pick_list_line', 'tm2inv.rm_booking_d') }}" :rows="$shorts"
                :columns="[
                    ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
                    ['key' => 'need', 'label' => 'Needed', 'type' => 'number', 'decimals' => 2],
                    ['key' => 'allocated', 'label' => 'Allocated', 'type' => 'number', 'decimals' => 2],
                    ['key' => 'short', 'label' => 'Short', 'type' => 'number', 'decimals' => 2],
                    ['key' => 'unit', 'label' => 'Unit'],
                    ['key' => 'requirement', 'label' => 'Requirement'],
                ]" />
        @else
            <x-dl::alert tone="info" message="None. If a line were short, the system would show Short with the shortfall." />
        @endif
    </x-dl::card>
@endif
