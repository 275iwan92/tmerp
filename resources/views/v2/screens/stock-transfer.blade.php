{{-- Stock Transfer v2 (user 2026-10-02: "input moving item antar warehouse") lewat App\Support\V2\Data\StockTransfer.
     GRID transfer -> + New Record = pilih gudang asal + tujuan -> DRAFT: baris = posisi stok asal (item di satu bin) + bin
     tujuan + qty -> Confirm (stok pindah di kartu stok, nomor lahir, siap dicetak) -> Cancel wajib alasan (stok kembali).
     Di bawah baris: pemeriksaan per baris (kapasitas bin tujuan sesudah pindah, DEAD STOCK merah) dan label tiap bin
     (kode, kapasitas, QR + barcode). Lebih dari kapasitas = peringatan, bukan larangan. Tanpa jurnal (nilai & pemilik sama).
     Approval (Setup > Setting Approval, modul TF) bawaan TIDAK aktif; bila diaktifkan Confirm = diajukan dan stok baru
     pindah saat level terakhir Approve (tombol Approve / Reject / Hold muncul untuk pemeriksa level berjalan). --}}
@php
    use App\Models\Inventory\InvTransfer;
    use App\Support\V2\Data\StockTransfer;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'stock-transfer');
    $doc = request('id') ? StockTransfer::find((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
    $reasons = array_map(fn (string $r) => t($r), InvTransfer::REASONS);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $doc && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Inv\StockTransferListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Inv\StockTransferListGrid]])
@elseif (! $doc)
    <x-dl::action-form action="{{ route('v2.wh.transfer.create') }}">
        <x-dl::card title="+ New Record — Stock Transfer" description="Choose where the stock is now and where it goes. Same warehouse = a move between bins. The items are added on the next screen.">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="from_warehouse_id" label="From warehouse" required :options="Warehouse::options()" :value="request()->integer('from') ?: null" :source="Src::of('tm2mst.wh_warehouse')" />
                <x-dl::field.select name="to_warehouse_id" label="To warehouse" required :options="Warehouse::options()" :value="request()->integer('to') ?: null" :source="Src::of('tm2mst.wh_warehouse')" />
                <x-dl::field.select name="reason" label="Reason" :options="$reasons" :source="Src::of('tm2inv.inv_transfer.reason')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $draft = $doc->status() === 'DRAFT';
        $appr = StockTransfer::approval($doc);
        $formStatus = StockTransfer::formStatus($doc, $appr['held']);
        $positions = StockTransfer::positions($doc);
        $checks = StockTransfer::checks($doc, $positions);
        $labels = StockTransfer::binLabels($doc);
        $over = collect($checks)->where('over', true)->count();
        $dead = collect($checks)->where('dead', true)->count();
        $route = ($doc->fromWarehouse?->warehouse_code ?? '?').' → '.($doc->toWarehouse?->warehouse_code ?? '?');
    @endphp
    <x-dl::form id="stock-transfer-form" :title="'Stock Transfer · '.raw($doc->label())" :subtitle="raw($route.' · '.$doc->status())"
        panel="top" kind="document" :status="$formStatus" :pipeline="$appr['used']" :approval-levels="$appr['used'] ? max(1, $appr['levels']) : 0" :approval-at="$appr['at']"
        :can-approve="(bool) $appr['pending']" :log="$appr['log']"
        :action="route('v2.wh.transfer.save', $doc->id_transfer)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :print-url="PrintDocs::url('stock-transfer', $doc->id_transfer)" :hide="[]"
        :tabs="['general' => 'General', 'notes' => 'Notes']"
        :created-by="$doc->created_by" :created-at="$doc->created_at?->toDateTimeString()" :updated-by="$doc->updated_by" :updated-at="$doc->updated_at?->toDateTimeString()">
        <x-slot:special>
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Transfer No.')</span><b>{{ $doc->transfer_no ?: t('(on Confirm)') }}</b></div>
            <x-dl::field.date name="transfer_date" label="Date" :value="$doc->transfer_date?->toDateString()" :readonly="! $draft" :source="Src::of('tm2inv.inv_transfer.transfer_date')" />
            <x-dl::field.display label="From warehouse" :value="($doc->fromWarehouse?->warehouse_code ?? '—').' · '.($doc->fromWarehouse?->warehouse_name ?? '')" :source="Src::of('tm2mst.wh_warehouse')" />
            <x-dl::field.select name="to_warehouse_id" label="To warehouse" :options="Warehouse::options()" :value="$doc->to_warehouse_id" :disabled="! $draft" :source="Src::of('tm2mst.wh_warehouse')"
                hint="Changing it: Save first, then choose the destination bins again." />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="reason" label="Reason" :options="$reasons" :value="$doc->reason" :disabled="! $draft" :source="Src::of('tm2inv.inv_transfer.reason')" />
                <x-dl::field.text name="carried_by" label="Carried By" :value="$doc->carried_by" :readonly="! $draft" placeholder="Name of the person who moves the goods" :source="Src::of('tm2inv.inv_transfer.carried_by')" />
                @if ($doc->stat_confirm)
                    <x-dl::field.display label="Confirmed" :value="Locale::stamp($doc->confirmed_at).' · '.(PrintDocs::person($doc->confirmed_by) ?? '—')" :source="Src::of('tm2inv.inv_transfer.confirmed_at')" />
                @endif
            </x-dl::form.row>
            @if ($doc->stat_cancel)
                <x-dl::field.display label="Cancel Reason" :value="$doc->cancel_reason ?? '—'" :source="Src::of('tm2inv.inv_transfer.cancel_reason')" />
            @endif
        </x-slot:general>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes" multiline :value="$doc->notes" :readonly="! $draft" :source="Src::of('tm2inv.inv_transfer.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="id_transfer_line" remove-field="remove" add-label="+ Item" :addable="$draft" :duplicable="false" :min-rows="0"
                :rows="StockTransfer::lineRows($doc, $positions)" :readonly="! $draft"
                source="{{ Src::of('tm2inv.inv_transfer_line', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.wh_loc') }}"
                :columns="[
                    ['key' => 'position', 'label' => 'Item | Source Bin | On Hand', 'type' => 'select', 'options' => StockTransfer::positionOptions($doc, $positions), 'required' => true, 'width' => '520px'],
                    ['key' => 'to_location_id', 'label' => 'Destination Bin | Free Capacity', 'type' => 'select', 'options' => Warehouse::binOptions((int) $doc->to_warehouse_id), 'width' => '360px'],
                    ['key' => 'qty', 'label' => 'Qty to Move', 'type' => 'number', 'required' => true, 'width' => '130px'],
                    ['key' => 'unit', 'label' => 'Unit', 'type' => 'display', 'width' => '70px'],
                    ['key' => 'lot_no', 'label' => 'Lot No.', 'type' => 'text', 'width' => '140px'],
                    ['key' => 'pallet', 'label' => 'Pallet', 'type' => 'display', 'more' => true],
                ]" />
            @if ($draft)
                <x-dl::alert tone="info" :message="StockTransfer::approvalActive()
                    ? 'Pick the item in its source bin, the destination bin and the quantity, then Save: the check below shows the destination capacity after the move. Confirm sends the transfer for approval; the stock moves after the last approval.'
                    : 'Pick the item in its source bin, the destination bin and the quantity, then Save: the check below shows the destination capacity after the move. Confirm moves the stock.'" />
            @elseif ($doc->status() === 'SUBMITTED')
                <x-dl::alert tone="warn" message="Waiting for approval: the stock has not moved yet. It moves when the last level approves." />
            @elseif ($doc->status() === 'REJECTED')
                <x-dl::alert tone="danger" message="Rejected: no stock moved. Use Duplicate to make a new Draft with the same lines." />
            @endif
        </x-slot:detail>
    </x-dl::form>

    @if ($checks)
        @if ($dead)<x-dl::alert tone="danger" :message="raw(str_replace(':n', (string) $dead, t(':n line(s) carry DEAD STOCK: the item has not moved for months. Moving it does not make it alive again.')))" />@endif
        @if ($over && ! $doc->stat_confirm && ! $doc->stat_cancel)<x-dl::alert tone="warn" :message="raw(str_replace(':n', (string) $over, t(':n line(s) bring the destination bin over its capacity. You may still Confirm; consider another bin.')))" />@endif
        <x-dl::card title="Bins & Capacity" icon="fa-weight-hanging" description="One row per line: where it comes from, where it goes, and how full the destination bin is after the move.">
            <x-dl::grid id="stock-transfer-check-{{ $doc->id_transfer }}" mode="report" row-tone="tone" :per-page="50" search="Search item, bin..."
                source="{{ Src::of('tm2inv.inv_transfer_line', 'tm2mst.wh_loc', 'tm2inv.v_rm_onhand') }}"
                :rows="array_map(fn (array $c) => ['id' => $c['line']] + $c + ['tone' => $c['dead'] ? 'danger' : ($c['over'] ? 'tangerine' : null), 'flag_tone' => $c['dead'] ? 'danger' : ($c['over'] ? 'tangerine' : ($c['to'] ? 'success' : 'neutral'))], $checks)"
                :columns="[
                    ['key' => 'line', 'label' => 'Line', 'type' => 'number'],
                    ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
                    ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
                    ['key' => 'unit', 'label' => 'Unit'],
                    ['key' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number'],
                    ['key' => 'volume', 'label' => 'Volume (m³)', 'type' => 'number'],
                    ['key' => 'from', 'label' => 'From Bin'],
                    ['key' => 'to', 'label' => 'To Bin'],
                    ['key' => 'after', 'label' => $doc->stat_confirm ? 'Destination Weight Now' : 'Destination Weight After Move'],
                    ['key' => 'after_m3', 'label' => $doc->stat_confirm ? 'Destination Volume Now' : 'Destination Volume After Move'],
                    ['key' => 'flag', 'label' => 'Check', 'type' => 'badge', 'toneKey' => 'flag_tone'],
                ]" />
        </x-dl::card>
        <x-dl::card title="Bin Labels" icon="fa-qrcode" description="Capacity label of every bin on this transfer, with its QR code and barcode. Scan the QR code with a phone to see what is in the bin.">
            <x-slot:actions>
                <x-dl::action-form action="{{ route('v2.wh.labels.print') }}">
                    <input type="hidden" name="type" value="location"><input type="hidden" name="size" value="md"><input type="hidden" name="back" value="stock-transfer">
                    @foreach ($doc->lines->pluck('from_location_id')->merge($doc->lines->pluck('to_location_id'))->filter()->unique() as $binId)<input type="hidden" name="keys[]" value="{{ $binId }}">@endforeach
                    <x-dl::button size="sm" variant="secondary" icon="print" label="Print Labels" type="submit" />
                </x-dl::action-form>
            </x-slot:actions>
            <x-dl::form.row :cols="3">
                @foreach ($labels as $l)
                    @include('v2.partials.label', ['l' => $l, 'title' => $l['role'], 'size' => 'md'])
                @endforeach
            </x-dl::form.row>
        </x-dl::card>
    @endif
@endif
