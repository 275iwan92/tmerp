{{-- Warehouse Map v2 (user 2026-10-02: "warehouse mapping") lewat App\Support\V2\Data\Warehouse: pilih gedung -> denah per
     lantai (garis luar gedung, area, bin) -> klik bin = isi, kapasitas, label QR, tombol Transfer / Label / Block.
     Merah = bin yang memuat dead stock. Edit Layout = geser / ubah ukuran area. Generate Bins = pembuat bin per area.
     Gedung, area, dan bin ditambah / diubah di layar Buildings, Storage Areas, Bins (tombol di kanan atas). --}}
@php
    use App\Models\Master\WhWarehouse;
    use App\Models\Master\WhZone;
    use App\Services\Inventory\WarehouseStock;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'warehouse-map');
    $wh = Warehouse::current(request()->integer('wh') ?: null);
    $map = $wh && $wh->hasLayout() ? Warehouse::map($wh) : null;
    $url = fn (string $name, array $p = []) => str_replace('987654321', '{id}', route($name, $p + ['id' => 987654321]));
    $zones = $wh ? $wh->zones()->where('activate', true)->get() : collect();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $wh)
    <x-dl::empty-state title="No warehouse yet" message="Add the first building, then its storage areas and bins." icon="fa-warehouse">
        <x-dl::button label="Buildings" icon="fa-building" :href="route('v2.screen', 'wh-buildings')" />
    </x-dl::empty-state>
@else
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::action-bar align="start">
            <x-dl::field.select name="wh" label="Building" :options="Warehouse::options()" :value="$wh->id_warehouse" :source="Src::of('tm2mst.wh_warehouse')" />
            <x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" />
            <x-dl::button variant="secondary" icon="fa-building" label="Buildings" :href="route('v2.screen', 'wh-buildings')" />
            <x-dl::button variant="secondary" icon="fa-vector-square" label="Storage Areas" :href="route('v2.screen', 'wh-zones')" />
            <x-dl::button variant="secondary" icon="fa-table-cells" label="Bins" :href="route('v2.screen', 'wh-bins')" />
            @if ($zones->isNotEmpty())<x-dl::button variant="secondary" icon="fa-wand-magic-sparkles" label="Generate Bins" data-dl-open="wh-generate" />@endif
        </x-dl::action-bar>
    </x-dl::action-form>

    @if (! $map)
        <x-dl::empty-state :title="raw($wh->warehouse_code.' · '.$wh->warehouse_name)" message="This building has no size yet. Fill in its length and width (metres) in Buildings to draw the map." icon="fa-ruler-combined">
            <x-dl::button label="Buildings" icon="fa-building" :href="route('v2.screen', 'wh-buildings')" />
        </x-dl::empty-state>
    @else
        <x-dl::wh-map id="wh-map-{{ $wh->id_warehouse }}" :title="raw($wh->warehouse_code.' · '.$wh->warehouse_name)" :width="$map['width']" :height="$map['height']" :outline="$map['outline']"
            :floors="$map['floors']" :bins="$map['bins']" :floor="request()->integer('floor') ?: null" :selected="request()->integer('bin') ?: null"
            :save-url="route('v2.wh.zone-move')" source="{{ Src::of('tm2mst.wh_warehouse', 'tm2mst.wh_zone', 'tm2mst.wh_loc', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand') }}"
            :actions="[
                ['label' => t('Transfer'), 'icon' => 'fa-right-left', 'tone' => 'primary', 'when' => 'stock', 'post' => true, 'url' => $url('v2.wh.bin.transfer')],
                ['label' => t('Label'), 'icon' => 'fa-qrcode', 'url' => $url('v2.wh.bin.label')],
                ['label' => t('Block'), 'icon' => 'fa-ban', 'tone' => 'danger', 'when' => 'usable', 'post' => true, 'reason' => true, 'confirm' => t('Block this bin? It can no longer receive stock until it is unblocked.'), 'url' => $url('v2.wh.bin.block')],
                ['label' => t('Unblock'), 'icon' => 'fa-circle-check', 'when' => 'blocked', 'post' => true, 'url' => $url('v2.wh.bin.unblock')],
            ]" />
        <x-dl::alert tone="info" :message="raw(str_replace([':area', ':floors', ':bins', ':used', ':cap', ':months'],
            [Locale::number($map['totals']['area']), (string) $wh->floors(), Locale::number($map['totals']['bins']), Locale::number($map['totals']['used_kg']), Locale::number($map['totals']['capacity_kg']), (string) WarehouseStock::deadMonths()],
            t('Floor area :area m2 on :floors floor(s), :bins bins, :used of :cap kg in use. Red = dead stock: the item has not moved for more than :months months (moving it to another bin does not count).')))" />
        @if ($map['unassigned'])
            <x-dl::alert tone="warn" :message="raw(str_replace(':n', (string) $map['unassigned'], t(':n bins of this building have no storage area yet, so they are not on the map. Set their area in Bins.')))" />
        @endif
    @endif

    <x-dl::modal id="wh-generate" title="Generate Bins" icon="fa-wand-magic-sparkles" size="md">
        <x-dl::action-form action="{{ route('v2.wh.bins.generate') }}" id="wh-generate-form">
            <x-dl::form.row :cols="1">
                <x-dl::field.select name="zone_id" label="Storage area" required :source="Src::of('tm2mst.wh_zone')"
                    :options="$zones->mapWithKeys(fn (WhZone $z) => [$z->id_zone => $z->zone_code.' · '.$z->zone_name.' · '.t('Floor').' '.$z->floor_no.' · '.$z->storage_mode])->all()"
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
