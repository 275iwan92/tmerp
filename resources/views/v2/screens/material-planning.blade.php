{{-- Material Planning (MRP baru) v2 (docs/materialbooking_tier1.md). Isi = MaterialPlanningEngine lewat App\Support\V2\Data\MaterialPlanning.
     Alur layar: GRID satu baris per bahan (butuh dari CO + forecast MPS, ditutup stok / PO / PR, kurang, pesan paling lambat)
     -> centang bahan yang kurang -> Create PR (satu PR per supplier usulan, qty dibulatkan ke satuan beli)
     -> klik 2x bahan = proyeksi saldo per tanggal. --}}
@php
    use App\Support\V2\Data\MaterialPlanning;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $grid = $screen['grid'];
    $home = $grid->url();
    $materialId = (int) request('id');
    $rows = MaterialPlanning::materialRows();
    $m = $materialId ? collect($rows)->firstWhere('id', $materialId) : null;
    abort_if($materialId && ! $m, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $m)
    @php
        $short = collect($rows)->where('short', '>', 0)->where('id', '>', 0);
        $noItem = collect($rows)->whereIn('status', ['Need item', 'Need layout'])->count();
    @endphp
    <x-dl::action-bar>
        <form method="post" action="{{ route('v2.planning.run-all') }}">@csrf<x-dl::button type="submit" variant="secondary" icon="fa-rotate" :label="t('Recalculate All')" /></form>
        <x-dl::button variant="secondary" icon="fa-boxes-packing" :label="t('Material Booking')" :href="route('v2.screen', 'material-booking')" />
        <x-dl::button variant="secondary" icon="fa-calendar-week" :label="t('Demand Forecast (MPS)')" :href="route('v2.screen', 'demand-forecast')" />
        <x-dl::button variant="secondary" icon="fa-fill-drip" :label="t('Material Usage Standard')" :href="route('v2.screen', 'usage-standard')" />
        <x-dl::button variant="secondary" icon="fa-truck-clock" :label="t('Purchase Lead Time')" :href="route('v2.screen', 'purchase-lead-time')" />
    </x-dl::action-bar>
    @if ($noItem > 0)
        <x-dl::alert tone="warn" :message="raw(str_replace(':n', (string) $noItem, t(':n material needs have no stock item yet (Master Product only has the specification) or no layout. Pick the item in Material Booking; until then they cannot be checked against stock or ordered.')))" />
    @endif
    @include('v2.screens._dbgrid', ['screen' => $screen])

    <form method="post" action="{{ route('v2.planning.create-pr') }}">
        @csrf
        <x-dl::card :title="t('Create Purchase Requisition')" icon="fa-file-circle-plus" :description="t('One requisition per suggested supplier. Quantity is rounded up to the purchase unit; the need date is the first date the material is needed. The requisition is created as Draft in Procurement.')">
            @if ($short->isEmpty())
                <x-dl::empty-state :title="t('No short material with a stock item')" />
            @else
                <x-dl::field.checkboxes name="material_ids" :label="t('Short materials')" :value="$short->pluck('id')->all()"
                    :options="$short->mapWithKeys(fn ($r) => [$r['id'] => $r['material'].' · '.t('short').' '.Locale::number($r['short'], 2).' '.$r['uom'].' · '.t('order by').' '.(Locale::d($r['order_by']) ?? '—').' · '.$r['supplier']])->all()"
                    :source="Src::of('tm2pur.pr_requisition (i_source = MRP)', 'tm2pur.pr_line.i_need_date', 'i_co_line_id')" />
                <x-dl::action-bar><x-dl::button type="submit" icon="fa-file-circle-plus" :label="t('Create PR')" /></x-dl::action-bar>
            @endif
        </x-dl::card>
    </form>
@else
    <x-dl::card>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
            <span class="mp-chips">
                <x-dl::badge :label="$m['code']" />
                <x-dl::badge :label="$m['status']" :tone="MaterialPlanning::COVER_TONES[$m['status']] ?? 'neutral'" />
            </span>
        </x-dl::action-bar>
        <h2 class="mp-bigname">{{ $m['material'] }}</h2>
        <x-dl::form.row cols="4">
            <x-dl::field.display label="Needed" :value="Locale::number($m['need'], 2).' '.$m['uom']" />
            <x-dl::field.display label="Short" :value="Locale::number($m['short'], 2).' '.$m['uom']" />
            <x-dl::field.display label="First Needed" :value="Locale::d($m['first_need']) ?? '—'" />
            <x-dl::field.display label="Order By (latest)" :value="(Locale::d($m['order_by']) ?? '—').' · '.t('lead').' '.$m['lead'].' '.t('working days')" />
        </x-dl::form.row>
    </x-dl::card>
    <x-dl::grid id="material-timeline" :rows="MaterialPlanning::timeline($materialId)" mode="report" actions="none" :active-key="null" :new-record="null" search="Search..."
        source="{{ Src::of('tm2inv.v_rm_available', 'tm2pur.po_line', 'tm2pur.pr_line', 'tm2sdi.co_order_item', 'tm2prd.prd_demand_forecast') }}"
        :columns="[
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'what', 'label' => 'Event'],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'decimals' => 2],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'decimals' => 2],
            ['key' => 'balance', 'label' => 'Projected Balance', 'type' => 'number', 'decimals' => 2],
        ]" />
@endif
