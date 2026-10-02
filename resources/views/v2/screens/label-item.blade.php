{{-- Print Item Label (user 2026-10-02: "Facility for QR Code, Labelling", "cetak label item saat penerimaan barang") lewat
     App\Support\V2\Data\Labels: pilih gedung -> pilih posisi stok (item di satu bin, saldo sekarang) -> ukuran, kertas,
     jumlah lembar -> Print: riwayat dicatat lalu lembar label terbuka. Dibuka dari form Penerimaan Barang (?receipt=):
     item penerimaan itu sudah tercentang. Label item = pita warna kelas stok, kode + nama item, qty, bin, tanggal datang,
     lot + kedaluwarsa (dari palet), QR + barcode; garis merah = dead stock / kedaluwarsa. Beda dari label palet. --}}
@php
    use App\Services\Inventory\PalletService;
    use App\Support\V2\Data\Labels;
    use App\Support\V2\Data\Pallets;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Src;
    use Devlib\Components\Label;
    use Devlib\Components\LabelSheet;
    use Devlib\Locale;

    $home = route('v2.screen', 'label-item');
    $pv = Pallets::receipt(request()->integer('receipt') ?: null);
    $wh = Warehouse::current(request()->integer('wh') ?: null);
    $received = $pv ? collect(PalletService::receiptLines($pv))->pluck('position')->filter()->keyBy('key') : collect();
    $options = $pv ? $received->map(fn (array $p) => Labels::positionText($p))->all() : ($wh ? Labels::positionOptions($wh->id_warehouse) : []);
    $papers = array_map(fn (string $p) => t($p), LabelSheet::PAPERS);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $wh && ! $pv)
    <x-dl::empty-state title="No warehouse yet" message="Add a building and its bins first." icon="fa-warehouse" />
@else
    <x-dl::card title="Print Item Label" icon="fa-tag" description="A label for stock that is in a bin now: item code and name, quantity, bin, arrival date, lot and expiry date, a QR code and a barcode. The colour band shows the stock class; a red stripe means dead stock or expired.">
        @if ($pv)
            <x-dl::alert tone="info" :message="raw(str_replace([':no', ':date'], [$pv->label(), (string) Locale::d($pv->i_doc_date)], t('Item labels for goods receipt :no, received :date. The items of this receipt are already ticked.')))" />
            <x-dl::action-bar align="start">
                <x-dl::button variant="secondary" icon="back" label="Back to Receipt" :href="route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id" />
                <x-dl::button variant="secondary" icon="fa-pallet" label="Pallet Labels of this Receipt" :href="route('v2.screen', 'label-pallet').'?receipt='.$pv->pv_id" />
            </x-dl::action-bar>
        @else
            <x-dl::action-form method="get" action="{{ $home }}">
                <x-dl::form.row :cols="3">
                    <x-dl::field.select name="wh" label="Building" :options="Warehouse::options()" :value="$wh->id_warehouse" :source="Src::of('tm2mst.wh_warehouse')" />
                    <x-dl::action-bar align="start"><x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" /></x-dl::action-bar>
                </x-dl::form.row>
            </x-dl::action-form>
        @endif
        @if (! $options)
            <x-dl::alert tone="info" :message="$pv ? 'This receipt put no stock into a bin, so there is nothing to label.' : 'This building holds no stock, so there is nothing to label.'" />
        @else
            <x-dl::action-form action="{{ route('v2.wh.labels.print') }}">
                <input type="hidden" name="type" value="item"><input type="hidden" name="back" value="label-item">
                @if ($pv)<input type="hidden" name="rid" value="{{ $pv->pv_id }}">@endif
                <x-dl::form.row :cols="2">
                    <x-dl::field.multi-select name="keys" label="Stock (item | bin | on hand)" required :options="$options" :value="$pv ? array_keys($options) : []" empty-text="Choose (0 selected)" noun="selected"
                        :source="Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand')" />
                    <x-dl::field.number name="copies" label="Copies per label" :value="1" hint="One label per pack or stack: type how many packs carry the same label." />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="size" label="Label size" required :options="Label::SIZES" value="md" />
                    <x-dl::field.select name="paper" label="Print On" required :options="$papers" value="a4" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="fa-clock-rotate-left" label="Print History" :href="route('v2.screen', 'inv-label-history')" />
                    <x-dl::button icon="print" label="Print" type="submit" />
                </x-dl::action-bar>
            </x-dl::action-form>
        @endif
        <x-dl::label-guide size="md" :colors="Labels::colorLegend('item')" />
    </x-dl::card>
@endif
