{{-- Print Item Label (user 2026-10-02: "Facility for QR Code, Labelling") lewat App\Support\V2\Data\Labels: pilih gedung ->
     pilih posisi stok (item di satu bin, saldo sekarang) -> ukuran + jumlah lembar -> Print: riwayat dicatat lalu lembar
     label terbuka. Label = kode + nama item, qty, bin, pergerakan terakhir, QR + barcode; pita merah = dead stock. --}}
@php
    use App\Support\V2\Data\Labels;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Src;
    use Devlib\Components\Label;

    $home = route('v2.screen', 'label-item');
    $wh = Warehouse::current(request()->integer('wh') ?: null);
    $options = $wh ? Labels::positionOptions($wh->id_warehouse) : [];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $wh)
    <x-dl::empty-state title="No warehouse yet" message="Add a building and its bins first." icon="fa-warehouse" />
@else
    <x-dl::card title="Print Item Label" icon="fa-tag" description="A label for stock that is in a bin now: item code and name, quantity, bin, last movement, a QR code and a barcode. Dead stock gets a red stripe.">
        <x-dl::action-form method="get" action="{{ $home }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="wh" label="Building" :options="Warehouse::options()" :value="$wh->id_warehouse" :source="Src::of('tm2mst.wh_warehouse')" />
                <x-dl::action-bar align="start"><x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" /></x-dl::action-bar>
            </x-dl::form.row>
        </x-dl::action-form>
        @if (! $options)
            <x-dl::alert tone="info" message="This building holds no stock, so there is nothing to label." />
        @else
            <x-dl::action-form action="{{ route('v2.wh.labels.print') }}">
                <input type="hidden" name="type" value="item">
                <x-dl::form.row :cols="3">
                    <x-dl::field.multi-select name="keys" label="Stock (item | bin | on hand)" required :options="$options" empty-text="Choose (0 selected)" noun="selected"
                        :source="Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand')" />
                    <x-dl::field.select name="size" label="Label size" required :options="Label::SIZES" value="md" />
                    <x-dl::field.number name="copies" label="Copies per label" :value="1" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="fa-clock-rotate-left" label="Print History" :href="route('v2.screen', 'inv-label-history')" />
                    <x-dl::button icon="print" label="Print" type="submit" />
                </x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </x-dl::card>
@endif
