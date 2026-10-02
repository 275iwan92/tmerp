{{-- Print Location Label (user 2026-10-02: "Facility for QR Code, Labelling") lewat App\Support\V2\Data\Labels: pilih gedung ->
     pilih bin (kosong = tidak ada yang dicetak; tombol "all bins of an area" lewat pilihan area) -> ukuran + jumlah lembar
     -> Print: riwayat dicatat (Label Print History) lalu lembar label terbuka. Label = kode bin, area, kapasitas, QR + barcode. --}}
@php
    use App\Models\Master\WhLoc;
    use App\Support\V2\Data\Labels;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Src;
    use Devlib\Components\Label;

    $home = route('v2.screen', 'label-location');
    $wh = Warehouse::current(request()->integer('wh') ?: null);
    $zones = $wh ? $wh->zones()->where('activate', true)->get() : collect();
    $zoneId = request()->integer('zone') ?: null;
    // Area dipilih = semua bin area itu langsung tercentang.
    $picked = $zoneId ? WhLoc::query()->where('zone_id', $zoneId)->where('activate', true)->pluck('location_id')->map(fn ($v) => (string) $v)->all() : [];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $wh)
    <x-dl::empty-state title="No warehouse yet" message="Add a building and its bins first." icon="fa-warehouse" />
@else
    <x-dl::card title="Print Location Label" icon="fa-qrcode" description="A label for every bin: its code, storage area, capacity, a QR code for the phone camera and a barcode for the scanner.">
        <x-dl::action-form method="get" action="{{ $home }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="wh" label="Building" :options="Warehouse::options()" :value="$wh->id_warehouse" :source="Src::of('tm2mst.wh_warehouse')" />
                <x-dl::field.select name="zone" label="Storage area (ticks all its bins)" :value="$zoneId" :source="Src::of('tm2mst.wh_zone')"
                    :options="$zones->mapWithKeys(fn ($z) => [$z->id_zone => $z->zone_code.' · '.$z->zone_name.' · '.t('Floor').' '.$z->floor_no])->all()" />
                <x-dl::action-bar align="start"><x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" /></x-dl::action-bar>
            </x-dl::form.row>
        </x-dl::action-form>
        <x-dl::action-form action="{{ route('v2.wh.labels.print') }}">
            <input type="hidden" name="type" value="location">
            <x-dl::form.row :cols="3">
                <x-dl::field.multi-select name="keys" label="Bins" required :options="Labels::binOptions($wh->id_warehouse)" :value="$picked" empty-text="Choose (0 selected)" noun="selected" :source="Src::of('tm2mst.wh_loc')" />
                <x-dl::field.select name="size" label="Label size" required :options="Label::SIZES" value="md" />
                <x-dl::field.number name="copies" label="Copies per label" :value="1" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="fa-clock-rotate-left" label="Print History" :href="route('v2.screen', 'inv-label-history')" />
                <x-dl::button icon="print" label="Print" type="submit" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>
@endif
