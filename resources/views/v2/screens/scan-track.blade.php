{{-- Track via QR / Barcode (user 2026-10-02: "Facility for QR Code, Labelling") lewat App\Support\V2\Data\Labels::track.
     Pindai (scanner / kamera HP) atau ketik kode -> hasil: BIN = label + isi + tombol peta / transfer; ITEM = di bin mana
     saja stoknya + 15 pergerakan terakhir; nomor Stock Transfer = buka dokumennya. Isi QR label = alamat halaman ini. --}}
@php
    use App\Support\V2\Data\Labels;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'scan-track');
    $code = trim((string) request('code', ''));
    $res = $code !== '' ? Labels::track($code) : null;
@endphp

<x-dl::card title="Track via QR / Barcode" icon="fa-magnifying-glass-location" description="Scan a location label, an item label, or type a bin code, an item code or a transfer number.">
    <x-dl::scan name="code" :action="$home" hint="A USB or Bluetooth scanner types the code and presses Enter by itself. On a phone, the camera button scans the QR code." />
</x-dl::card>

@if ($res && $res['type'] === 'none')
    <x-dl::alert tone="warn" :message="raw(str_replace(':code', $res['code'], t('Nothing found for ":code". Check the code, or look it up in the Warehouse Map.')))" />
@elseif ($res && $res['type'] === 'transfer')
    <x-dl::card :title="'Stock Transfer · '.raw($res['transfer']->label())" icon="fa-right-left">
        <x-dl::action-bar align="start">
            <x-dl::button icon="fa-up-right-from-square" label="Open" :href="route('v2.screen', 'stock-transfer').'?id='.$res['transfer']->id_transfer" />
        </x-dl::action-bar>
    </x-dl::card>
@elseif ($res && $res['type'] === 'bin')
    @php
        $bin = $res['bin'];
        $s = $res['summary'];
        $l = $res['label'];
    @endphp
    <x-dl::card :title="'Bin · '.raw($bin->location_code)" icon="fa-location-dot" :description="raw($l['name'])">
        <x-slot:actions>
            <x-dl::button size="sm" variant="secondary" icon="fa-map" label="Show on Map" :href="route('v2.screen', 'warehouse-map').'?wh='.$bin->warehouse_id.'&bin='.$bin->location_id" />
            <x-dl::button size="sm" variant="secondary" icon="fa-qrcode" label="Label" :href="route('v2.wh.bin.label', $bin->location_id)" />
        </x-slot:actions>
        <x-dl::form.row :cols="2">
            <x-dl::label :code="$l['code']" :title="$l['title']" :name="$l['name']" :lines="$l['lines']" :qr="$l['qr']" :barcode="$l['barcode']" :tone="$l['tone']" :footer="$l['footer']" />
            <x-dl::form.col>
                <x-dl::field.display label="Capacity" :value="Warehouse::capacityText($s['used_kg'], $s['capacity_kg']) ?? '—'" :source="Src::of('tm2mst.wh_loc.capacity_kg')" />
                <x-dl::field.display label="Status" :value="$s['dead'] ? t('Dead stock') : t(\Devlib\Components\WhMap::STATUS[$s['status']])" />
                @unless ($bin->is_usable)<x-dl::field.display label="Blocked because" :value="$bin->block_reason ?? '—'" :source="Src::of('tm2mst.wh_loc.block_reason')" />@endunless
            </x-dl::form.col>
        </x-dl::form.row>
        <x-dl::grid id="track-bin-{{ $bin->location_id }}" mode="report" row-tone="tone" :per-page="50" search="Search item..."
            source="{{ Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand') }}"
            :rows="array_map(fn (array $i) => ['id' => $i['key'], 'item' => $i['name'], 'code' => $i['code'], 'class' => $i['class'], 'qty' => $i['qty'], 'unit' => $i['uom'], 'weight' => $i['weight_kg'],
                'last' => $i['last_movement'], 'idle' => $i['idle_days'], 'state' => $i['dead'] ? 'Dead stock' : 'OK', 'tone' => $i['dead'] ? 'danger' : null], $s['items'])"
            :columns="[
                ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
                ['key' => 'class', 'label' => 'Class'],
                ['key' => 'qty', 'label' => 'On Hand', 'type' => 'number'],
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number'],
                ['key' => 'last', 'label' => 'Last Movement', 'type' => 'date'],
                ['key' => 'idle', 'label' => 'Idle (days)', 'type' => 'number'],
                ['key' => 'state', 'label' => 'Stock', 'type' => 'badge', 'tones' => ['Dead stock' => 'danger', 'OK' => 'success']],
            ]" />
    </x-dl::card>
@elseif ($res && $res['type'] === 'item')
    @php
        $item = $res['item'];
        $pos = $res['positions'];
    @endphp
    <x-dl::card :title="raw($item->code.' · '.$item->name)" icon="fa-box" :description="raw(str_replace([':class', ':n', ':qty'], [t(\App\Services\Inventory\WarehouseStock::CLASSES[$res['class']]), (string) $pos->count(), Locale::decimal($pos->sum('qty'), 2)], t(':class · in :n bin(s) · :qty on hand in total')))">
        <x-dl::grid id="track-item-{{ $res['class'] }}-{{ $item->id }}" mode="report" row-tone="tone" :per-page="50" search="Search bin..."
            source="{{ Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.wh_loc') }}"
            :rows="$pos->map(fn (array $p) => ['id' => $p['key'], 'bin' => $p['location_code'], 'qty' => $p['qty'], 'unit' => $p['uom'], 'weight' => $p['weight_kg'], 'value' => $p['value'],
                'last' => $p['last_movement'], 'idle' => $p['idle_days'], 'state' => $p['dead'] ? 'Dead stock' : 'OK', 'tone' => $p['dead'] ? 'danger' : null])->all()"
            :columns="[
                ['key' => 'bin', 'label' => 'Bin'],
                ['key' => 'qty', 'label' => 'On Hand', 'type' => 'number', 'sum' => true],
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number', 'sum' => true],
                ['key' => 'last', 'label' => 'Last Movement', 'type' => 'date'],
                ['key' => 'idle', 'label' => 'Idle (days)', 'type' => 'number'],
                ['key' => 'state', 'label' => 'Stock', 'type' => 'badge', 'tones' => ['Dead stock' => 'danger', 'OK' => 'success']],
            ]" />
    </x-dl::card>
    <x-dl::card title="Last Movements" icon="fa-clock-rotate-left" description="The 15 latest stock card rows of this item, newest first. TF = stock transfer between bins.">
        <x-dl::grid id="track-moves-{{ $res['class'] }}-{{ $item->id }}" mode="report" :per-page="15" search="Search doc no, bin..." source="{{ Src::of('tm2inv.v_inv_movement') }}"
            :rows="$res['moves']->map(fn ($m) => ['id' => $m->doc_id, 'date' => $m->doc_date?->toDateString(), 'doc_no' => $m->doc_no, 'move' => $m->doc_type, 'warehouse' => $m->warehouse_name,
                'bin' => $m->location_name, 'in' => (float) $m->qty_in, 'out' => (float) $m->qty_out, 'adj' => (float) $m->qty_adj, 'balance' => (float) $m->qty_eb])->all()"
            :columns="[
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'doc_no', 'label' => 'Doc No.'],
                ['key' => 'move', 'label' => 'Movement', 'type' => 'badge', 'tones' => ['IN' => 'success', 'OUT' => 'danger', 'AD' => 'warn', 'TF' => 'info', 'BB' => 'neutral']],
                ['key' => 'warehouse', 'label' => 'Warehouse'],
                ['key' => 'bin', 'label' => 'Bin'],
                ['key' => 'in', 'label' => 'In', 'type' => 'number'],
                ['key' => 'out', 'label' => 'Out', 'type' => 'number'],
                ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number'],
                ['key' => 'balance', 'label' => 'Balance in Bin', 'type' => 'number'],
            ]" />
    </x-dl::card>
@endif
