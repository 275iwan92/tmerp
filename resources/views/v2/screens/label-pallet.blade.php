{{-- Print Pallet Label (user 2026-10-02: "wajib buat label setiap kedatangan dan setiap habis pemakaian dalam satu palet",
     "bedakan label item dan label palet", "kode plate dari supplier harus disertakan", "kertas, tinta pasti ada tanggal
     kedaluarsa") lewat App\Support\V2\Data\Pallets + App\Services\Inventory\PalletService.
     Alur: pilih gedung (atau datang dari form Penerimaan Barang: ?receipt=) -> DAFTAR PEKERJAAN LABEL = posisi stok yang
     label paletnya belum cocok dengan saldo (Arrival label = barang datang belum berlabel; Relabel after use = isinya
     sudah dipakai) -> buka satu baris -> isi palet (qty, kode palet supplier, lot, kedaluwarsa) -> Create & Print;
     atau Relabel (qty sisa; 0 = palet ditutup); atau cetak ulang. Palet tidak mengubah stok.
     Sisa (user 2026-10-03): jenis satuan Pallet / Pack (otomatis Pack bila lembaran di bawah batas sisa); saat label ulang
     sisa boleh langsung dipindah ke tumpukan lain ("Now in bin") = Stock Transfer dibuat dan di-Confirm. --}}
@php
    use App\Support\V2\Data\Labels;
    use App\Support\V2\Data\Pallets;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Src;
    use App\Models\Inventory\InvPallet;
    use App\Models\Master\WhLoc;
    use App\Services\Inventory\WarehouseStock;
    use Devlib\Components\Label;
    use Devlib\Components\LabelSheet;
    use Devlib\Locale;

    $home = route('v2.screen', 'label-pallet');
    $pv = Pallets::receipt(request()->integer('receipt') ?: null);
    // ?unit= dari Stack Monitor: angka = id palet / pak, selain itu kunci posisi stok yang belum berlabel.
    $unit = (string) request('unit', '');
    $palletId = request()->integer('pallet') ?: (ctype_digit($unit) ? (int) $unit : 0);
    $posKey = (string) request('pos', ctype_digit($unit) ? '' : $unit);
    $picked = $palletId ? InvPallet::query()->find($palletId) : null;
    $pos = $picked ? Pallets::position($picked->positionKey()) : ($posKey !== '' ? Pallets::position($posKey) : null);
    $posBin = $pos ? WhLoc::query()->find($pos['location_id']) : null;
    $wh = Warehouse::current($posBin?->warehouse_id ?: (request()->integer('wh') ?: null));
    $todo = $wh || $pv ? Pallets::todo($home, $wh?->id_warehouse, $pv) : [];
    $line = $pv && $pos ? collect($todo)->firstWhere('id', $pos['key']) : null;
    $self = $home.'?'.http_build_query(array_filter(['receipt' => $pv?->pv_id, 'wh' => $pv ? null : $wh?->id_warehouse]));
    $sizes = Label::SIZES;
    $papers = array_map(fn (string $p) => t($p), LabelSheet::PAPERS);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $wh && ! $pv)
    <x-dl::empty-state title="No warehouse yet" message="Add a building and its bins first." icon="fa-warehouse" />
@else
    @if ($pv)
        <x-dl::alert tone="info" :message="raw(str_replace([':no', ':date'], [$pv->label(), (string) Locale::d($pv->i_doc_date)], t('Pallet labels for goods receipt :no, received :date. Every pallet that arrived needs a label before it is put away.')))" />
        <x-dl::action-bar align="start">
            <x-dl::button variant="secondary" icon="back" label="Back to Receipt" :href="route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id" />
            <x-dl::button variant="secondary" icon="fa-tag" label="Item Labels of this Receipt" :href="route('v2.screen', 'label-item').'?receipt='.$pv->pv_id" />
            <x-dl::button variant="secondary" icon="fa-pallet" label="All Pallet Label Work" :href="$home" />
        </x-dl::action-bar>
    @else
        <x-dl::action-form method="get" action="{{ $home }}">
            <x-dl::action-bar align="start">
                <x-dl::field.select name="wh" label="Building" :options="Warehouse::options()" :value="$wh->id_warehouse" :source="Src::of('tm2mst.wh_warehouse')" />
                <x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" />
                <x-dl::button variant="secondary" icon="fa-clock-rotate-left" label="Print History" :href="route('v2.screen', 'inv-label-history')" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif

    <x-dl::label-guide size="lg" :colors="Labels::colorLegend('pallet')" />

    @if ($pos)
        @php
            $pallets = $pos['pallets'];
            $arrived = $pv?->i_doc_date?->toDateString() ?? $pos['arrived'] ?? now()->toDateString();
        @endphp
        <x-dl::card :title="raw($pos['code'].' · '.$pos['name'])" icon="fa-pallet"
            :description="raw(str_replace([':bin', ':onhand', ':labelled', ':n'], [$pos['location_code'], Locale::decimal($pos['qty'], 2).' '.$pos['uom'], Locale::decimal($pos['pallet_qty'], 2).' '.$pos['uom'], (string) $pallets->count()],
                t('Bin :bin · :onhand on hand · :labelled on :n pallet label(s)')))">
            <x-slot:actions>
                <x-dl::button size="sm" variant="secondary" icon="back" label="Back" :href="$self" />
            </x-slot:actions>

            @if ($pos['need'] === 'RELABEL')
                <x-dl::alert tone="warn" :message="raw(str_replace(':qty', Locale::decimal($pos['excess'], 2).' '.$pos['uom'], t('The pallet labels show :qty more than the stock in this bin: stock was used. Relabel the pallet that was opened with what is left on it.')))" />
            @endif

            @if ($pos['unlabelled'] > 0)
                <x-dl::action-form action="{{ route('v2.wh.pallet.create') }}">
                    <input type="hidden" name="position" value="{{ $pos['key'] }}">
                    <input type="hidden" name="back" value="label-pallet">
                    @if ($pv)<input type="hidden" name="rid" value="{{ $pv->pv_id }}">@endif
                    <x-dl::alert tone="info" :message="raw(str_replace(':qty', Locale::decimal($pos['unlabelled'], 2).' '.$pos['uom'], t(':qty in this bin is not on a pallet label yet. One line = one pallet: split the quantity with + Pallet, and copy the pallet code printed on the supplier label.')))" />
                    <x-dl::form.row :cols="4">
                        <x-dl::field.date name="arrived_at" label="Arrival date" required :value="$arrived" :source="Src::of('tm2inv.inv_pallet.arrived_at')" />
                        <x-dl::field.text name="ref_doc_no" label="Receipt No." :case-toggle="false" :value="$pv?->doc_no" :readonly="(bool) $pv" :source="Src::of('tm2inv.inv_pallet.ref_doc_no')" />
                        <x-dl::field.select name="size" label="Label size" required :options="$sizes" value="lg" />
                        <x-dl::field.select name="paper" label="Print On" required :options="$papers" value="a4" />
                    </x-dl::form.row>
                    <x-dl::lines name="lines" title="New Pallets" add-label="+ Pallet" :duplicable="true" :min-rows="1" :rows="Pallets::newRows($pos, $line['lot'] ?? null, $arrived, $line ? $line['received'] - $line['receipt_labelled'] : null)"
                        source="{{ Src::of('tm2inv.inv_pallet') }}"
                        :columns="[
                            ['key' => 'qty', 'label' => 'Qty on the Pallet', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '160px'],
                            ['key' => 'supplier_pallet_code', 'label' => 'Supplier Pallet Code', 'type' => 'text', 'width' => '220px', 'placeholder' => t('as printed on the supplier label')],
                            ['key' => 'lot_no', 'label' => 'Lot No.', 'type' => 'text', 'width' => '180px'],
                            ['key' => 'expiry_date', 'label' => 'Expiry Date', 'type' => 'date', 'width' => '170px'],
                        ]" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.select name="unit_kind" label="Kind of the new units" :options="Pallets::kindOptions()" value="" :source="Src::of('tm2inv.inv_pallet.unit_kind')"
                            hint="Stack 1 is the bottom. A new unit goes on top; when a unit leaves, the ones above move down by themselves." />
                        <x-dl::field.text name="notes" label="Notes" :source="Src::of('tm2inv.inv_pallet.notes')" />
                    </x-dl::form.row>
                    <x-dl::action-bar>
                        <x-dl::button icon="print" label="Create & Print" type="submit" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @elseif ($pos['need'] === null)
                <x-dl::alert tone="success" message="Every unit of this stock is on a pallet label. Relabel a pallet after its contents are used." />
            @endif
        </x-dl::card>

        @if ($pallets->isNotEmpty())
            <x-dl::card title="Pallets of this Stock" icon="fa-layer-group" description="Oldest arrival first: use the oldest pallet first. After using from a pallet, relabel it with what is left; 0 closes the pallet.">
                <x-dl::grid id="pallets-of-position" mode="report" row-tone="tone" :per-page="50" search="Search pallet no., lot, supplier code..." source="{{ Src::of('tm2inv.inv_pallet') }}"
                    :rows="Pallets::rows($pallets, $home)"
                    :columns="[
                        ['key' => 'no', 'label' => 'Pallet / Pack No.'],
                        ['key' => 'stack', 'label' => 'Stack No.', 'type' => 'number'],
                        ['key' => 'kind', 'label' => 'Kind'],
                        ['key' => 'qty', 'label' => 'Qty Now', 'type' => 'number', 'sum' => true],
                        ['key' => 'initial', 'label' => 'Qty at Arrival', 'type' => 'number'],
                        ['key' => 'unit', 'label' => 'Unit'],
                        ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
                        ['key' => 'supplier_code', 'label' => 'Supplier Pallet Code'],
                        ['key' => 'lot', 'label' => 'Lot No.'],
                        ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date'],
                        ['key' => 'expiry_state', 'label' => 'Expiry', 'type' => 'badge', 'tones' => Pallets::expiryTones()],
                        ['key' => 'labels', 'label' => 'Labels Printed', 'type' => 'number'],
                    ]" />
            </x-dl::card>
            <x-dl::form.row :cols="2">
                <x-dl::action-form action="{{ route('v2.wh.pallet.relabel') }}">
                    <x-dl::card title="Relabel after Use" icon="fa-rotate" description="Type what is left on the pallet that was opened. At or below the remnant limit it becomes a remnant pack. If the remnant was put on another pile, choose that bin: a Stock Transfer is made and the pack goes on top.">
                        <x-dl::form.row :cols="2">
                            <x-dl::field.select name="pallet_id" label="Pallet" required :options="Pallets::options($pallets)" :value="($picked ?? $pallets->first())->id_pallet" :source="Src::of('tm2inv.inv_pallet')" />
                            <x-dl::field.decimal name="remaining" label="Remaining quantity" required :value="$picked || $pallets->count() === 1 ? ($picked ?? $pallets->first())->qty : null" :source="Src::of('tm2inv.inv_pallet.qty')" />
                        </x-dl::form.row>
                        <x-dl::form.row :cols="2">
                            <x-dl::field.select name="unit_kind" label="Unit kind" :options="Pallets::kindOptions()" value="" :source="Src::of('tm2inv.inv_pallet.unit_kind')"
                                :hint="raw(str_replace(':limit', Locale::number(WarehouseStock::remnantSheets()), t('Remnant limit: :limit sheets (Settings, Constants).')))" />
                            <x-dl::field.select name="to_location_id" label="Now in bin" :options="['' => t('Stays in the same bin')] + Labels::binOptions((int) ($posBin?->warehouse_id ?? 0))" value="" :source="Src::of('tm2mst.wh_loc')" />
                        </x-dl::form.row>
                        <x-dl::form.row :cols="3">
                            <x-dl::field.text name="note" label="Note" placeholder="e.g. taken for a work order" :source="Src::of('tm2inv.inv_pallet_log.note')" />
                            <x-dl::field.select name="size" label="Label size" :options="$sizes" value="lg" />
                            <x-dl::field.select name="paper" label="Print On" :options="$papers" value="a4" />
                        </x-dl::form.row>
                        <x-dl::action-bar><x-dl::button icon="print" label="Relabel & Print" type="submit" /></x-dl::action-bar>
                    </x-dl::card>
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.wh.labels.print') }}">
                    <input type="hidden" name="type" value="pallet"><input type="hidden" name="back" value="label-pallet">
                    @if ($pv)<input type="hidden" name="rid" value="{{ $pv->pv_id }}">@endif
                    <x-dl::card title="Print Again" icon="fa-print" description="For a label that is damaged or lost. The quantity on the label does not change.">
                        <x-dl::field.multi-select name="keys" label="Pallets" required :options="Pallets::options($pallets)" :value="$picked ? [(string) $picked->id_pallet] : $pallets->pluck('id_pallet')->map(fn ($v) => (string) $v)->all()"
                            empty-text="Choose (0 selected)" noun="selected" :source="Src::of('tm2inv.inv_pallet')" />
                        <x-dl::form.row :cols="2">
                            <x-dl::field.select name="size" label="Label size" required :options="$sizes" value="lg" />
                            <x-dl::field.select name="paper" label="Print On" required :options="$papers" value="a4" />
                        </x-dl::form.row>
                        <x-dl::action-bar><x-dl::button variant="secondary" icon="print" label="Print" type="submit" /></x-dl::action-bar>
                    </x-dl::card>
                </x-dl::action-form>
            </x-dl::form.row>
        @endif
    @endif

    <x-dl::card :title="$pv ? 'Receipt Lines' : 'Pallet Label Work'" icon="fa-list-check"
        :description="$pv ? 'One row per item and bin of this receipt. Open a row to make its pallet labels.' : 'Stock whose pallet labels do not match what is in the bin. Arrival label: goods came in without a pallet label. Relabel after use: stock was taken and the pallet label still shows the old quantity.'">
        @if (! $todo)
            <x-dl::alert tone="success" :message="$pv ? 'This receipt put no stock into a bin.' : 'All stock in this building is on an up-to-date pallet label.'" />
        @else
            <x-dl::grid id="pallet-todo-{{ $pv?->pv_id ?? 'wh'.$wh?->id_warehouse }}" :rows="$todo" actions="worklist" :active-key="null" :new-record="null" open-url="{open_url}" row-tone="tone"
                search="Search item, bin..." source="{{ Src::of('tm2inv.inv_pallet', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand') }}"
                :columns="array_values(array_filter([
                    ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
                    ['key' => 'bin', 'label' => 'Bin'],
                    $pv ? ['key' => 'received', 'label' => 'Received', 'type' => 'number'] : null,
                    ['key' => 'onhand', 'label' => 'On Hand in Bin', 'type' => 'number'],
                    ['key' => 'labelled', 'label' => 'On Pallet Labels', 'type' => 'number'],
                    ['key' => 'diff', 'label' => 'Difference', 'type' => 'number'],
                    ['key' => 'unit', 'label' => 'Unit'],
                    ['key' => 'pallets', 'label' => 'Pallets', 'type' => 'number'],
                    ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
                    ['key' => 'need', 'label' => 'To Do', 'type' => 'badge', 'tones' => Pallets::needTones()],
                ]))"
                :stats="[['label' => 'All'], ['label' => 'Arrival label', 'where' => ['need' => Pallets::need('NEW')], 'tone' => 'warn'], ['label' => 'Relabel after use', 'where' => ['need' => Pallets::need('RELABEL')], 'tone' => 'tangerine']]" />
        @endif
    </x-dl::card>

    @unless ($pv)
        @php $all = Pallets::ofWarehouse($wh->id_warehouse); @endphp
        <x-dl::card title="Pallets in this Building" icon="fa-pallet" description="Every pallet with a label that still holds stock. Open a pallet to relabel it or print its label again.">
            @if ($all->isEmpty())
                <x-dl::alert tone="info" message="No pallet has a label yet in this building." />
            @else
                <x-dl::grid id="pallets-wh-{{ $wh->id_warehouse }}" :rows="Pallets::rows($all, $home)" actions="worklist" :active-key="null" :new-record="null" open-url="{open_url}" row-tone="tone"
                    search="Search pallet no., item, bin, lot, supplier code..." source="{{ Src::of('tm2inv.inv_pallet', 'tm2mst.wh_loc') }}"
                    :columns="[
                        ['key' => 'no', 'label' => 'Pallet No. / Item', 'type' => 'doc', 'party' => 'item'],
                        ['key' => 'bin', 'label' => 'Bin'],
                        ['key' => 'stack', 'label' => 'Stack No.', 'type' => 'number'],
                        ['key' => 'kind', 'label' => 'Kind'],
                        ['key' => 'qty', 'label' => 'Qty Now', 'type' => 'number'],
                        ['key' => 'unit', 'label' => 'Unit'],
                        ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
                        ['key' => 'supplier_code', 'label' => 'Supplier Pallet Code'],
                        ['key' => 'lot', 'label' => 'Lot No.'],
                        ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date'],
                        ['key' => 'expiry_state', 'label' => 'Expiry', 'type' => 'badge', 'tones' => Pallets::expiryTones()],
                    ]"
                    :stats="[['label' => 'All'], ['label' => 'Expired', 'where' => ['expiry_state' => Pallets::expiry('EXPIRED')], 'tone' => 'danger'], ['label' => 'Expires soon', 'where' => ['expiry_state' => Pallets::expiry('SOON')], 'tone' => 'warn'],
                        ['label' => 'No expiry date', 'where' => ['expiry_state' => Pallets::expiry('NONE')], 'tone' => 'neutral']]"
                    :filters="['bin' => 'Bin', 'kind' => 'Kind', 'expiry_state' => 'Expiry']" />
            @endif
        </x-dl::card>
    @endunless
@endif
