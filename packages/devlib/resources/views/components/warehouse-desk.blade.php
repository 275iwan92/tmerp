{{-- Tampilan objek x-dl::warehouse-desk (Devlib\Components\WarehouseDesk). --}}
@php
    use Devlib\Lang;
    $d = $desk;
    $m = $d['map'] ?? null;
    $g = $d['generate'] ?? null;
@endphp
<div {{ $attributes->class('dl-flat') }} {!! $dl() !!}>
    @if (! $d['wh'])
        <x-dl::empty-state title="No warehouse yet" message="Add the first building, then its storage areas and bins." icon="fa-warehouse">
            <x-dl::button label="Buildings" icon="fa-building" :href="$d['buildingsUrl']" />
        </x-dl::empty-state>
    @else
        <x-dl::action-form method="get" action="{{ $d['home'] }}">
            <x-dl::action-bar align="start">
                <x-dl::field.select name="wh" label="Building" :options="$d['options']" :value="$d['wh']" :source="$src('tm2mst.wh_warehouse')" />
                @if (! empty($d['pickOptions']))<x-dl::field.select name="pick" label="Pick allocation" :options="$d['pickOptions']" :value="$d['pick'] ?? null" placeholder="None" hint="Shows where an open pick list takes its material. None = the plain map." />@endif
                <x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" />
                @foreach ($d['links'] as [$icon, $label, $url])
                    <x-dl::button variant="secondary" :icon="$icon" :label="$label" :href="$url" />
                @endforeach
                @if ($g['show'])<x-dl::button variant="secondary" icon="fa-wand-magic-sparkles" label="Generate Bins" data-dl-open="wh-generate" />@endif
            </x-dl::action-bar>
        </x-dl::action-form>

        @if (! $m)
            <x-dl::empty-state :title="Lang::raw($d['title'])" message="This building has no size yet. Fill in its length and width (metres) in Buildings to draw the map." icon="fa-ruler-combined">
                <x-dl::button label="Buildings" icon="fa-building" :href="$d['buildingsUrl']" />
            </x-dl::empty-state>
        @else
            <x-dl::wh-map id="wh-map-{{ $d['wh'] }}" :title="Lang::raw($d['title'])" :width="$m['width']" :height="$m['height']" :outline="$m['outline']"
                :floors="$m['floors']" :bins="$m['bins']" :floor="$m['floor']" :selected="$m['selected']" :save-url="$m['saveUrl']" :restack-url="$m['restackUrl']"
                source="{{ $src('tm2mst.wh_warehouse', 'tm2mst.wh_zone', 'tm2mst.wh_loc', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2inv.inv_pallet (stack_no, unit_kind)') }}"
                :actions="$m['actions']" :layer="$m['layer'] ?? []" />
            <x-dl::card title="Capacity per Floor" icon="fa-ruler-combined" description="Floor area and clear height give the room volume. Storage volume = area of the storage areas x their stacking height. In use = estimated volume and weight of the stock (paper: sheets x size x thickness).">
                <x-slot:actions>
                    <x-dl::button size="sm" variant="secondary" icon="fa-building" label="Building Size & Height" :href="$d['buildingsUrl']" />
                    <x-dl::button size="sm" variant="secondary" icon="fa-vector-square" label="Stacking Height per Area" :href="$d['zonesUrl']" />
                </x-slot:actions>
                <x-dl::grid id="wh-capacity-{{ $d['wh'] }}" mode="report" :per-page="10" search="Search..." source="{{ $src('tm2mst.wh_warehouse (length, width, height per floor)', 'tm2mst.wh_zone (size, stack height)', 'tm2mst.wh_loc.capacity_kg') }}"
                    :rows="$m['capacity']" :columns="$capacityColumns()" />
                @if ($m['missingHeight'])
                    <x-dl::alert tone="warn" message="A floor without a clear height has no volume. Fill in the height of every floor in Buildings (and the stacking height per area in Storage Areas if it is lower than the ceiling)." />
                @endif
            </x-dl::card>
            <x-dl::alert tone="info" :message="Lang::raw($m['summary'])" />
            @if ($m['unassigned'])
                <x-dl::alert tone="warn" :message="Lang::raw($m['unassigned'])" />
            @endif
        @endif

        <x-dl::modal id="wh-generate" title="Generate Bins" icon="fa-wand-magic-sparkles" size="md">
            <x-dl::action-form action="{{ $g['action'] }}" id="wh-generate-form">
                <x-dl::form.row :cols="1">
                    <x-dl::field.select name="zone_id" label="Storage area" required :source="$src('tm2mst.wh_zone')" :options="$g['zones']"
                        hint="Floor area: lanes and slots. Rack area: racks, columns and levels. Open area: one bin, the numbers are ignored." />
                </x-dl::form.row>
                <x-dl::form.row :cols="4">
                    <x-dl::field.number name="rows_from" label="Lane / rack from" :value="1" />
                    <x-dl::field.number name="rows_to" label="Lane / rack to" :value="4" />
                    <x-dl::field.number name="cols_from" label="Slot / column from" :value="1" />
                    <x-dl::field.number name="cols_to" label="Slot / column to" :value="4" />
                </x-dl::form.row>
                <x-dl::form.row :cols="3">
                    <x-dl::field.number name="levels" label="Levels (rack)" :value="1" />
                    <x-dl::field.number name="capacity_kg" label="Capacity per bin (kg)" />
                    <x-dl::field.number name="capacity_pallet" label="Pallets per bin" />
                </x-dl::form.row>
                <x-dl::alert tone="info" message="Bin codes are made from the building, the area and the position, for example A10-PPR-L03-05. Bins that already exist are skipped, so you can run it again to add lanes." />
            </x-dl::action-form>
            <x-slot:footer>
                <x-dl::button variant="secondary" label="Cancel" data-dl-close-modal />
                <x-dl::button label="Generate" icon="fa-wand-magic-sparkles" type="submit" form="wh-generate-form" />
            </x-slot:footer>
        </x-dl::modal>
    @endif
</div>
