{{-- Tampilan objek x-mp::inv.scan-track (MockupParts\Components\Inv\ScanTrack). Judul / keterangan di $desk sudah jadi -> Lang::raw. --}}
@php
    use Devlib\Lang;
    $r = $desk['result'];
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
    <x-dl::card title="Track via QR / Barcode" icon="fa-magnifying-glass-location" description="Scan a location label, an item label or a pallet label, or type a bin code, an item code, a pallet number or a transfer number.">
        <x-dl::scan name="code" :action="$desk['home']" hint="A USB or Bluetooth scanner types the code and presses Enter by itself. On a phone, the camera button scans the QR code." />
    </x-dl::card>

    @if ($type() === 'none')
        <x-dl::alert tone="warn" :message="Lang::raw($r['message'])" />
    @elseif ($type() === 'transfer')
        <x-dl::card :title="Lang::raw($r['title'])" icon="fa-right-left">
            <x-dl::action-bar align="start">
                <x-dl::button icon="fa-up-right-from-square" label="Open" :href="$r['openUrl']" />
            </x-dl::action-bar>
        </x-dl::card>
    @elseif ($type() === 'pallet')
        @php $l = $r['label']; @endphp
        <x-dl::card :title="Lang::raw($r['title'])" icon="fa-pallet" :description="Lang::raw($r['description'])">
            <x-slot:actions>
                @if ($r['moveAction'])
                    <x-dl::action-form action="{{ $r['moveAction'] }}">
                        <x-dl::button size="sm" icon="fa-right-left" label="Move this Pallet" type="submit" />
                    </x-dl::action-form>
                    <x-dl::button size="sm" variant="secondary" icon="fa-rotate" label="Relabel / Print" :href="$r['relabelUrl']" />
                @endif
                @if ($r['mapUrl'])<x-dl::button size="sm" variant="secondary" icon="fa-map" label="Show on Map" :href="$r['mapUrl']" />@endif
            </x-slot:actions>
            <x-dl::form.row :cols="2">
                <x-dl::label :code="$l['code']" :kind="$l['kind'] ?? null" :title="$l['title']" :color="$l['color'] ?? null" :mark="$l['mark'] ?? null" :name="$l['name']"
                    :highlight="$l['highlight'] ?? null" :lines="$l['lines']" :tags="$l['tags'] ?? []" :qr="$l['qr']" :barcode="$l['barcode']" size="md" :tone="$l['tone']" :footer="$l['footer']" />
                <x-dl::form.col>
                    @foreach ($r['facts'] as [$fl, $fv, $fs])
                        <x-dl::field.display :label="$fl" :value="$fv" :source="$src(...$fs)" />
                    @endforeach
                </x-dl::form.col>
            </x-dl::form.row>
            <x-dl::grid id="track-pallet-{{ $r['id'] }}" mode="report" :per-page="50" search="Search..." source="{{ $src('tm2inv.inv_pallet_log') }}"
                :rows="$r['rows']" :columns="$palletColumns($r['actionTones'])" />
        </x-dl::card>
    @elseif ($type() === 'bin')
        @php $l = $r['label']; @endphp
        <x-dl::card :title="Lang::raw($r['title'])" icon="fa-location-dot" :description="Lang::raw($r['description'])">
            <x-slot:actions>
                <x-dl::button size="sm" variant="secondary" icon="fa-map" label="Show on Map" :href="$r['mapUrl']" />
                <x-dl::button size="sm" variant="secondary" icon="fa-qrcode" label="Label" :href="$r['labelUrl']" />
            </x-slot:actions>
            <x-dl::form.row :cols="2">
                <x-dl::label :code="$l['code']" :kind="$l['kind'] ?? null" :title="$l['title']" :color="$l['color'] ?? null" :mark="$l['mark'] ?? null" :name="$l['name']"
                    :highlight="$l['highlight'] ?? null" :lines="$l['lines']" :tags="$l['tags'] ?? []" :qr="$l['qr']" :barcode="$l['barcode']" size="md" :tone="$l['tone']" :footer="$l['footer']" />
                <x-dl::form.col>
                    @foreach ($r['facts'] as [$fl, $fv, $fs])
                        <x-dl::field.display :label="$fl" :value="$fv" :source="$src(...$fs)" />
                    @endforeach
                </x-dl::form.col>
            </x-dl::form.row>
            <x-dl::grid id="track-bin-{{ $r['id'] }}" mode="report" row-tone="tone" :per-page="50" search="Search item..."
                source="{{ $src('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand') }}" :rows="$r['rows']" :columns="$binColumns()" />
        </x-dl::card>
    @elseif ($type() === 'item')
        <x-dl::card :title="Lang::raw($r['title'])" icon="fa-box" :description="Lang::raw($r['description'])">
            <x-dl::grid id="track-item-{{ $r['id'] }}" mode="report" row-tone="tone" :per-page="50" search="Search bin..."
                source="{{ $src('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.wh_loc') }}" :rows="$r['rows']" :columns="$positionColumns()" />
        </x-dl::card>
        @if ($r['pallets'])
            <x-dl::card title="Pallets" icon="fa-pallet" description="Labelled pallets of this item, oldest arrival first: use the oldest first.">
                <x-dl::grid id="track-item-pallets-{{ $r['id'] }}" :rows="$r['pallets']" actions="worklist" :active-key="null" :new-record="null"
                    open-url="{open_url}" row-tone="tone" search="Search pallet no., bin, lot..." source="{{ $src('tm2inv.inv_pallet') }}" :columns="$itemPalletColumns($r['expiryTones'])" />
            </x-dl::card>
        @endif
        <x-dl::card title="Last Movements" icon="fa-clock-rotate-left" description="The 15 latest stock card rows of this item, newest first. TF = stock transfer between bins.">
            <x-dl::grid id="track-moves-{{ $r['id'] }}" mode="report" :per-page="15" search="Search doc no, bin..." source="{{ $src('tm2inv.v_inv_movement') }}"
                :rows="$r['moves']" :columns="$moveColumns()" />
        </x-dl::card>
    @endif
</div>
