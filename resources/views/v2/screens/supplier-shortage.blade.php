{{--
 REMARK
 Tujuan       : Shortage Request: bahan yang kurang sejak awal (output + waste standar kurang dari bahan keluar ke WO), dari permintaan, hitung ulang, Confirm PPIC, kirim ke pemasok, sampai Received / Refused / Closed.
 Sumber hulu  : App\Support\V2\Data\ShortagePage; App\Services\Pur\ShortageRequestService; hak langkah di App\Services\Inventory\QcAuthority.
 Input        : Query id (permintaan), new (form baru); user login; session v2_flash dan errors.
 Output       : Daftar, form permintaan, rincian dengan tombol langkah; POST ke v2.iq.shortage.create, .step, .lapsed (Close Lapsed).
 Dipakai oleh : route v2.screen (routes/v2.php), slug supplier-shortage (menu Inventory > Batch & Quality).
 Status       : Aktif.
--}}
@php
    use App\Services\Inventory\QcAuthority;
    use App\Support\V2\Data\ShortagePage;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'supplier-shortage');
    $req = request('id') ? ShortagePage::request((int) request('id')) : null;
    abort_if(request('id') && ! $req, 404);
    $me = auth()->user();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $req && ! request('new'))
    @if (QcAuthority::canFollowUp($me))
        <x-dl::action-form action="{{ route('v2.iq.shortage.lapsed') }}">
            <x-dl::action-bar align="start">
                <x-dl::button variant="secondary" icon="fa-calendar-xmark" label="Close Lapsed" type="submit" :onclick="'return confirm('.json_encode(t('Close every request whose sending window has passed? Their PO lines close at the received quantity.')).')'" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Inv\ShortageListGrid]])
@elseif (! $req)
    <x-dl::action-form action="{{ route('v2.iq.shortage.create') }}">
        <x-dl::card title="+ New Record — Shortage Request" description="Choose the work order and material that was issued, and the receipt line it came with. Output and standard waste are in the material unit. The request is only made when the issued material is more than output plus standard waste by more than the PO tolerance.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="wo_material" label="Work Order / Material Issued" required :options="ShortagePage::issuedOptions()" :source="Src::of('tm2inv.v_inv_wo_reconcile')" />
                <x-dl::field.select name="pv_line_id" label="Receipt Line" required :options="ShortagePage::receiptLineOptions()" :source="Src::of('tm2pur.pv_line')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.decimal name="qty_output_good" label="Good Output (material unit)" required :source="Src::of('tm2inv.inv_shortage_request.qty_output_good')" />
                <x-dl::field.decimal name="qty_waste_std" label="Standard Waste (material unit)" hint="Empty = the work order standard." :source="Src::of('tm2prd.prd_wo_material.cutting_yield_waste_qty')" />
                <x-dl::field.date name="need_date" label="Needed By" :source="Src::of('tm2inv.inv_shortage_request.need_date')" />
                <x-dl::field.text name="notes" label="Notes" :case-toggle="false" :source="Src::of('tm2inv.inv_shortage_request.notes')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $status = $req->status;
        $money = fn ($v) => Locale::decimal((float) $v, 2).' '.$req->uom_code;
        $step = fn (string $name) => route('v2.iq.shortage.step', [$req->id_shortage, $name]);
    @endphp
    <x-dl::card :title="'Shortage Request · '.raw($req->request_no)" :description="raw(ShortagePage::party($req).' · '.$req->wo_no.' · '.$req->material_code.' '.$req->material_name)" icon="fa-solid fa-boxes-packing">
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Status" :value="t(ShortagePage::statusLabel($status))" :source="Src::of('tm2inv.inv_shortage_request.status')" />
            <x-dl::field.display label="Source" :value="t(\App\Services\Pur\ShortageRequestService::SOURCES[$req->source_type])" :source="Src::of('tm2inv.inv_shortage_request.source_type')" />
            <x-dl::field.display label="Needed By" format="date" :value="$req->need_date?->toDateString()" :source="Src::of('tm2inv.inv_shortage_request.need_date')" />
            <x-dl::field.display label="Send Until" format="date" :value="$req->window_until?->toDateString()" :source="Src::of('tm2inv.inv_shortage_request.window_until')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Material Issued (net)" :value="raw($money($req->qty_issued_net))" :source="Src::of('tm2inv.v_inv_wo_reconcile.qty_net_used')" />
            <x-dl::field.display label="Good Output" :value="raw($money($req->qty_output_good))" :source="Src::of('tm2inv.inv_shortage_request.qty_output_good')" />
            <x-dl::field.display label="Standard Waste" :value="raw($money($req->qty_waste_std))" :source="Src::of('tm2inv.inv_shortage_request.qty_waste_std')" />
            <x-dl::field.display label="Suspected Shortage" :value="raw($money($req->qty_candidate).' · '.t('tolerance').' '.Locale::decimal((float) $req->tol_pct, 2).'%')" :source="Src::of('tm2inv.inv_shortage_request.qty_candidate', 'tol_pct')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Recount" :value="$req->recount_at ? raw(Locale::decimal((float) $req->recount_qty, 2).' · '.$req->recount_by.' · '.Locale::dt($req->recount_at)) : '—'" :source="Src::of('tm2inv.inv_shortage_request.recount_qty', 'recount_by', 'recount_at')" />
            <x-dl::field.display label="Proven Shortage" :value="(float) $req->qty_short > 0 ? raw($money($req->qty_short)) : '—'" :source="Src::of('tm2inv.inv_shortage_request.qty_short')" />
            <x-dl::field.display label="Confirmed" :value="$req->confirmed_at ? raw($req->confirmed_by.' · '.Locale::dt($req->confirmed_at)) : '—'" :source="Src::of('tm2inv.inv_shortage_request.confirmed_by', 'confirmed_at')" />
            <x-dl::field.display label="Outcome" :value="$req->outcome ? raw(t(\App\Services\Pur\ShortageRequestService::OUTCOMES[$req->outcome] ?? $req->outcome).($req->outcome_note ? ' — '.$req->outcome_note : '')) : '—'" :source="Src::of('tm2inv.inv_shortage_request.outcome', 'outcome_note')" />
        </x-dl::form.row>
        @if ($req->recount_note)<x-dl::field.display label="Recount Note" :value="raw($req->recount_note)" :source="Src::of('tm2inv.inv_shortage_request.recount_note')" />@endif
    </x-dl::card>

    @if ($req->stock_warning)<x-dl::alert tone="warn" :message="raw($req->stock_warning)" />@endif
    @if ($req->debit_note_required)<x-dl::alert tone="warn" message="The PO line is closed at the received quantity. Ask the supplier for a debit note or deduct the shortage from the invoice." />@endif

    @if ($status === 'DRAFT')
        @if (QcAuthority::canRecount($me))
            <x-dl::action-form action="{{ $step('recount') }}">
                <x-dl::card title="Recount" description="Count or weigh the material again and enter the quantity that is proven missing (not more than the suspected shortage). Without proof the difference stays a usage variance.">
                    <x-dl::form.row :cols="4">
                        <x-dl::field.decimal name="counted_qty" label="Counted Quantity" required :value="$req->recount_qty" :source="Src::of('tm2inv.inv_shortage_request.recount_qty')" />
                        <x-dl::field.decimal name="recount_weight" label="Weighed (kg)" :value="$req->recount_weight" :source="Src::of('tm2inv.inv_shortage_request.recount_weight')" />
                        <x-dl::field.decimal name="qty_short" label="Proven Shortage" required :value="(float) $req->qty_short > 0 ? $req->qty_short : $req->qty_candidate" :source="Src::of('tm2inv.inv_shortage_request.qty_short')" />
                        <x-dl::field.text name="note" label="How It Was Counted" required :case-toggle="false" :value="$req->recount_note" :source="Src::of('tm2inv.inv_shortage_request.recount_note')" />
                    </x-dl::form.row>
                    <x-dl::action-bar><x-dl::button preset="save" label="Save" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        @endif
        @if (QcAuthority::canConfirmShortage($me))
            <x-dl::action-form action="{{ $step('confirm') }}">
                <x-dl::card title="PPIC Decision" description="Confirm corrects the received quantity, the PO line (it opens again for the backorder), the GR/IR journal and the stock. Dismiss keeps the difference as a usage variance.">
                    <x-dl::field.text name="note" label="Dismiss Note" :case-toggle="false" hint="Required to dismiss." :source="Src::of('tm2inv.inv_shortage_request.outcome_note')" />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                        <x-dl::button variant="secondary" icon="fa-circle-xmark" label="Dismiss" type="submit" formaction="{{ $step('dismiss') }}" formnovalidate />
                        <x-dl::button icon="fa-circle-check" label="Confirm" type="submit" :onclick="'return confirm('.json_encode(t('Confirm the shortage? The receipt, PO, journal and stock will be corrected.')).')'" />
                    </x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        @endif
    @elseif ($status === 'CONFIRMED' && QcAuthority::canFollowUp($me))
        <x-dl::action-form action="{{ $step('send') }}">
            <x-dl::card title="Ask the Supplier" description="Tell the supplier (or subcontractor / customer) to send the shortfall. They have the number of days in the setting INV_SHORTAGE_WINDOW_DAYS.">
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button icon="fa-paper-plane" label="Send" type="submit" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @elseif ($status === 'SENT' && QcAuthority::canFollowUp($me))
        <x-dl::action-form action="{{ $step('receive') }}">
            <x-dl::card title="Supplier Answer" description="Received = the shortfall arrived (receive it on a normal goods receipt from the reopened PO line). Refused closes the PO line at the received quantity and asks for a debit note.">
                <x-dl::field.text name="note" label="Note" :case-toggle="false" :source="Src::of('tm2inv.inv_shortage_request.outcome_note')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button variant="danger" icon="fa-circle-xmark" label="Refuse" type="submit" formaction="{{ $step('refuse') }}" formnovalidate />
                    <x-dl::button icon="fa-circle-check" label="Receive" type="submit" formnovalidate />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @elseif (in_array($status, ['RECEIVED', 'REFUSED'], true) && QcAuthority::canFollowUp($me))
        <x-dl::action-form action="{{ $step('close') }}">
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button icon="fa-lock" label="Close" type="submit" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @else
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    @endif
@endif
