{{-- Warehouse > In Transit (v2.01, I-31, I-53): transfer dua langkah. Daftar = objek Grid App\Support\V2\Grid\Inv\InTransitGrid (DocListGrid). Open (?id=) =
     Receive (isi qty yang tiba per baris; kurang = Discrepancy) atau, bila ada selisih, selesaikan per baris: Loss (dibukukan ke bin Loss) / Return (kembali ke bin asal).
     Ship dilakukan dari form Stock Transfer (pilihan Two-step, tombol Confirm = Ship). Logika: App\Services\Inventory\StockTransferService (ship / receive / resolveDiscrepancy). --}}
@php
    use App\Support\V2\Data\InTransit;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'wh-in-transit');
    $doc = request('id') ? InTransit::find((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $doc)
    @include('v2.screens._dbgrid', ['screen' => $screen])
@else
    @php
        $status = $doc->transit_status;
        $lines = InTransit::lineRows($doc);
        $open = $doc->lines->filter(fn ($l) => (float) $l->qty_diff > 0.00005 && ! $l->diff_resolution);
        $route = ($doc->fromWarehouse?->warehouse_code ?? '?').' → '.($doc->toWarehouse?->warehouse_code ?? '?');
    @endphp
    <x-dl::card :title="'In Transit · '.raw($doc->transfer_no)" :description="raw($route.' · '.t(InTransit::STATUS[$status] ?? $status))" icon="fa-solid fa-truck-ramp-box">
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Shipped" :value="raw(Locale::stamp($doc->shipped_at).' · '.($doc->shipped_by ?? '—'))" :source="Src::of('tm2inv.inv_transfer.shipped_at', 'shipped_by')" />
            <x-dl::field.display label="Age (days)" :value="raw(Locale::decimal(InTransit::ageDays($doc), 1))" :source="Src::of('tm2inv.inv_transfer.shipped_at')" />
            <x-dl::field.display label="Carried By" :value="$doc->carried_by ?: '—'" :source="Src::of('tm2inv.inv_transfer.carried_by')" />
            <x-dl::field.display label="Received" :value="$doc->received_at ? raw(Locale::stamp($doc->received_at).' · '.($doc->received_by ?? '—')) : '—'" :source="Src::of('tm2inv.inv_transfer.received_at', 'received_by')" />
        </x-dl::form.row>
        <x-dl::table :columns="['line' => ['#', 'num' => true, 'raw' => true], 'item' => ['Item', 'raw' => true], 'sent' => ['Sent', 'num' => true, 'raw' => true], 'received' => ['Received', 'num' => true, 'raw' => true],
            'diff' => ['Difference', 'num' => true, 'raw' => true], 'unit' => ['Unit', 'raw' => true], 'from' => ['From Bin', 'raw' => true], 'to' => ['To Bin', 'raw' => true], 'settled' => ['Difference Settled', 'raw' => true]]"
            :rows="$lines" :source="Src::of('tm2inv.inv_transfer_line', 'tm2mst.wh_loc')" />
    </x-dl::card>

    @if ($status === 'SHIPPED')
        <x-dl::action-form action="{{ route('v2.wh.transit.receive', $doc->id_transfer) }}">
            <x-dl::card title="Receive" description="Enter what arrived for each line. A smaller number than sent leaves the difference in In Transit until you settle it as a loss or return it.">
                @foreach ($doc->lines as $l)
                    <x-dl::field.decimal :name="'received['.$l->id_transfer_line.']'" :label="raw(t('Received').' · '.$l->item_name.' ('.t('sent').' '.Locale::decimal((float) $l->qty, 2).' '.$l->uom_code.')')"
                        :value="old('received.'.$l->id_transfer_line, $l->qty)" :source="Src::of('tm2inv.inv_transfer_line.qty_received')" />
                @endforeach
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button preset="confirm" type="submit" label="Receive" :onclick="'return confirm('.json_encode(t('Receive the transfer? The stock moves from In Transit to the destination bins.')).')'" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @elseif ($status === 'DISCREPANCY' && $open->isNotEmpty())
        <x-dl::card title="Settle Differences" description="Loss books the missing quantity to the Loss bin (it stays in the books until a Stock Adjustment writes it off). Return puts it back in the source bin.">
            @foreach ($open as $l)
                <x-dl::action-form action="{{ route('v2.wh.transit.resolve', $doc->id_transfer) }}">
                    <input type="hidden" name="line" value="{{ $l->id_transfer_line }}">
                    <x-dl::field.display :label="raw(t('Line').' '.$l->line_no.' · '.$l->item_name)" :value="raw(Locale::decimal((float) $l->qty_diff, 2).' '.$l->uom_code.' '.t('short'))" :source="Src::of('tm2inv.inv_transfer_line.qty_diff')" />
                    <x-dl::action-bar align="start">
                        <x-dl::button variant="danger" icon="fa-circle-xmark" label="Loss" type="submit" name="how" value="LOSS" />
                        <x-dl::button variant="secondary" icon="fa-rotate-left" label="Return" type="submit" name="how" value="RETURN" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @endforeach
        </x-dl::card>
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    @else
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    @endif
@endif
