{{-- Warehouse > Send to Subcontractor (v2.01, I-23, D2, D22): kirim bahan ke subkon dengan PO Subkon sebagai induk. Stok pindah dari bin gudang ke bin SUBCON vendor
     (lokasi virtual milik perusahaan, tetap bernilai) lewat dokumen Stock Transfer. Open (?id=) = rincian: bahan kembali ke gudang atau tercatat terpakai di vendor
     (penerimaan hasil subkon di Procurement mengurangi saldo vendor sebanding qty yang diterima). Laporan: Stock at Vendor.
     Daftar = objek Grid App\Support\V2\Grid\Inv\SubconSendGrid. Logika: App\Services\Inventory\SubconStockService. --}}
@php
    use App\Models\Inventory\InvSubconIssue;
    use App\Models\Master\WhLoc;
    use App\Support\V2\Data\SubconSends;
    use App\Support\V2\Data\Warehouse;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'subcon-send');
    $issue = request('id') ? SubconSends::find((int) request('id')) : null;
    abort_if(request('id') && ! $issue, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $issue && ! request('new'))
    @include('v2.screens._dbgrid', ['screen' => $screen])
@elseif (! $issue)
    <x-dl::action-form action="{{ route('v2.wh.subcon.create') }}">
        <x-dl::card title="+ New Record — Send to Subcontractor" description="Choose the open Subcon PO, the material and the bin it is taken from. The stock moves to the subcontractor's virtual bin and stays ours and valued. One material per send; make another send for the next material.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="po_id" label="Subcon PO" required :options="SubconSends::poOptions()" :value="old('po_id')" :source="Src::of('tm2pur.po_order')" />
                <x-dl::field.select name="position" label="Material and Bin" required :options="SubconSends::positionOptions()" :value="old('position')" :source="Src::of('tm2inv.v_rm_onhand', 'tm2mst.wh_loc')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.decimal name="qty" label="Quantity" required :value="old('qty')" :source="Src::of('tm2inv.inv_subcon_issue_line.qty_sent')" />
                <x-dl::field.date name="due_return_date" label="Due Back" :value="old('due_return_date')" :source="Src::of('tm2inv.inv_subcon_issue.due_return_date')" />
                <x-dl::field.text name="notes" label="Notes" :case-toggle="false" :value="old('notes')" :source="Src::of('tm2inv.inv_subcon_issue.notes')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Send" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $sent = $issue->status === 'SENT';
        $act = route('v2.wh.subcon.act', $issue->id_subcon_issue);
        $rows = $issue->lines->map(fn ($l) => ['line' => $l->line_no, 'item' => $l->item_name, 'sent' => Locale::decimal($l->qty_sent, 2), 'used' => Locale::decimal($l->qty_consumed, 2),
            'back' => Locale::decimal($l->qty_returned, 2), 'left' => Locale::decimal($l->atVendor(), 2), 'unit' => $l->uom_code])->all();
    @endphp
    <x-dl::card :title="'Send to Subcontractor · '.raw($issue->issue_no)" :description="raw(($issue->po_no ?: '—').' · '.t(InvSubconIssue::STATUSES[$issue->status] ?? $issue->status))" icon="fa-solid fa-dolly">
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Subcon PO" :value="$issue->po_no ?: '—'" :source="Src::of('tm2inv.inv_subcon_issue.po_no')" />
            <x-dl::field.display label="Date" format="date" :value="$issue->issue_date?->toDateString()" :source="Src::of('tm2inv.inv_subcon_issue.issue_date')" />
            <x-dl::field.display label="Due Back" format="date" :value="$issue->due_return_date?->toDateString()" :source="Src::of('tm2inv.inv_subcon_issue.due_return_date')" />
            <x-dl::field.display label="Notes" :value="$issue->notes ?: ($issue->cancel_reason ?: '—')" :source="Src::of('tm2inv.inv_subcon_issue.notes')" />
        </x-dl::form.row>
        <x-dl::table :columns="['line' => ['#', 'num' => true, 'raw' => true], 'item' => ['Item', 'raw' => true], 'sent' => ['Sent', 'num' => true, 'raw' => true], 'used' => ['Used', 'num' => true, 'raw' => true],
            'back' => ['Returned', 'num' => true, 'raw' => true], 'left' => ['At Vendor', 'num' => true, 'raw' => true], 'unit' => ['Unit', 'raw' => true]]" :rows="$rows" :source="Src::of('tm2inv.inv_subcon_issue_line')" />
    </x-dl::card>

    @if ($sent)
        @foreach ($issue->lines->filter(fn ($l) => $l->atVendor() > 0.00005) as $l)
            <x-dl::card :title="'Settle · '.raw($l->item_name)" :description="raw(t('At vendor').' '.Locale::decimal($l->atVendor(), 2).' '.$l->uom_code)">
                <x-dl::action-form action="{{ $act }}">
                    <input type="hidden" name="line" value="{{ $l->id_subcon_issue_line }}">
                    <x-dl::form.row :cols="3">
                        <x-dl::field.decimal name="qty" label="Quantity" required :source="Src::of('tm2inv.inv_subcon_issue_line.qty_consumed')" />
                        <x-dl::field.select name="to_location_id" label="Return To Bin" :options="Warehouse::binOptions((int) WhLoc::query()->whereKey($l->from_location_id)->value('warehouse_id'))" :value="$l->from_location_id" :source="Src::of('tm2mst.wh_loc')"
                            hint="Only used for Return." />
                    </x-dl::form.row>
                    <x-dl::action-bar align="start">
                        <x-dl::button variant="secondary" icon="fa-rotate-left" label="Return" type="submit" name="action" value="return" />
                        <x-dl::button icon="fa-check" label="Used" type="submit" name="action" value="consume" :onclick="'return confirm('.json_encode(t('Record this material as used at the subcontractor? It leaves the stock as a normal usage.')).')'" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::card>
        @endforeach
        @if ($issue->lines->every(fn ($l) => $l->qty_consumed <= 0 && $l->qty_returned <= 0))
            <x-dl::action-form action="{{ $act }}">
                <input type="hidden" name="action" value="cancel">
                <x-dl::card title="Cancel This Send" description="Only while nothing was used or returned: the material goes back to the bin it came from.">
                    <x-dl::field.text name="_reason" label="Reason" required :case-toggle="false" />
                    <x-dl::action-bar><x-dl::button variant="danger" preset="cancel" type="submit" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        @endif
    @endif
    <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
@endif
