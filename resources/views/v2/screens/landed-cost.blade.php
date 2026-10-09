{{-- Inventory > Valuation & Period > Landed Cost (v2.01, D17, V-04). Biaya angkut / bea / asuransi yang datang SETELAH penerimaan: GRID dokumen -> + New Record (tanggal, jenis biaya, jumlah,
     dasar bagi) -> tambah penerimaan barang -> Confirm membagi biaya ke baris penerimaan dan memposting jurnal (bagian yang masih di gudang menaikkan persediaan, bagian yang sudah keluar
     ke Selisih Landed Cost). Dokumen Confirmed: Revise (jurnal koreksi bertumpuk) atau Cancel (jurnal pembalik). Aturan: App\Services\Inventory\Valuation\LandedCostService. --}}
@php
    use App\Models\Inventory\InvLandedCost;
    use App\Support\V2\Data\ValuationDesk;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'landed-cost');
    $doc = request('id') ? ValuationDesk::landed((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $doc && ! request('new'))
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Inv\LandedCostListGrid]])
@elseif (! $doc)
    <x-dl::action-form action="{{ route('v2.val.landed.create') }}">
        <x-dl::card title="+ New Record — Landed Cost" description="Freight, duty or insurance that arrived after the goods. Next you choose the goods receipts it belongs to.">
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="doc_date" label="Date" required :value="now()->toDateString()" :source="Src::of('tm2inv.inv_landed_cost.doc_date')" />
                <x-dl::field.select name="cost_type" label="Cost Type" required :options="ValuationDesk::typeOptions()" :value="'FREIGHT'" :source="Src::of('tm2inv.inv_landed_cost.cost_type')" />
                <x-dl::field.decimal name="amount" label="Amount" required :source="Src::of('tm2inv.inv_landed_cost.amount')" />
                <x-dl::field.select name="alloc_basis" label="Share By" required :options="ValuationDesk::basisOptions()" :value="'VALUE'" :source="Src::of('tm2inv.inv_landed_cost.alloc_basis')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="supplier_id" label="Charged By" :options="ValuationDesk::supplierOptions()" :source="Src::of('tm2mst.sup_supplier')" />
                <x-dl::field.text name="description" label="Description" :case-toggle="false" :source="Src::of('tm2inv.inv_landed_cost.description')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $status = $doc->status();
        $draft = $status === 'DRAFT';
        $tones = ['DRAFT' => 'Draft', 'CONFIRMED' => 'Confirmed', 'CANCELLED' => 'Cancelled'];
        $columns = ['line' => ['#', 'num' => true, 'raw' => true], 'item' => ['Item', 'raw' => true], 'qty' => ['Received', 'num' => true, 'raw' => true], 'value' => ['Receipt Value', 'num' => true, 'raw' => true],
            'allocated' => ['Share of Cost', 'num' => true, 'raw' => true], 'to_stock' => ['To Inventory', 'num' => true, 'raw' => true], 'to_variance' => ['To Variance', 'num' => true, 'raw' => true]];
    @endphp
    <x-dl::card :title="'Landed Cost · '.raw($doc->doc_no ?: 'DRAFT-'.$doc->id_landed_cost)" :description="raw(t($tones[$status]).' · '.t(InvLandedCost::TYPES[$doc->cost_type] ?? $doc->cost_type).' · '.Locale::money($doc->amount))" icon="fa-solid fa-truck-ramp-box">
        @if ($draft)
            <x-dl::action-form action="{{ route('v2.val.landed.save', $doc->id_landed_cost) }}">
                <x-dl::form.row :cols="4">
                    <x-dl::field.date name="doc_date" label="Date" required :value="$doc->doc_date?->toDateString()" :source="Src::of('tm2inv.inv_landed_cost.doc_date')" />
                    <x-dl::field.select name="cost_type" label="Cost Type" required :options="ValuationDesk::typeOptions()" :value="$doc->cost_type" :source="Src::of('tm2inv.inv_landed_cost.cost_type')" />
                    <x-dl::field.decimal name="amount" label="Amount" required :value="$doc->amount" :source="Src::of('tm2inv.inv_landed_cost.amount')" />
                    <x-dl::field.select name="alloc_basis" label="Share By" required :options="ValuationDesk::basisOptions()" :value="$doc->alloc_basis" :source="Src::of('tm2inv.inv_landed_cost.alloc_basis')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="supplier_id" label="Charged By" :options="ValuationDesk::supplierOptions()" :value="$doc->supplier_id" :source="Src::of('tm2mst.sup_supplier')" />
                    <x-dl::field.text name="description" label="Description" :case-toggle="false" :value="$doc->description" :source="Src::of('tm2inv.inv_landed_cost.description')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" label="Save" /></x-dl::action-bar>
            </x-dl::action-form>
        @else
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Date" format="date" :value="$doc->doc_date?->toDateString()" :source="Src::of('tm2inv.inv_landed_cost.doc_date')" />
                <x-dl::field.display label="Amount" :value="raw(Locale::money($doc->amount))" :source="Src::of('tm2inv.inv_landed_cost.amount')" />
                <x-dl::field.display label="To Inventory" :value="raw(Locale::money($doc->amount_to_stock))" :source="Src::of('tm2inv.inv_landed_cost.amount_to_stock')" />
                <x-dl::field.display label="To Variance" :value="raw(Locale::money($doc->amount_to_variance))" :source="Src::of('tm2inv.inv_landed_cost.amount_to_variance')" />
            </x-dl::form.row>
            @if ($doc->description)<x-dl::field.display label="Description" :value="raw($doc->description)" :source="Src::of('tm2inv.inv_landed_cost.description')" />@endif
            @if ($doc->cancel_reason)<x-dl::field.display label="Cancel Reason" :value="raw($doc->cancel_reason)" :source="Src::of('tm2inv.inv_landed_cost.cancel_reason')" />@endif
        @endif
        <x-dl::table :columns="$columns" :rows="ValuationDesk::lineRows($doc)" empty="No receipt line yet." :source="Src::of('tm2inv.inv_landed_cost_line')" />
    </x-dl::card>

    @if ($draft)
        <x-dl::action-form action="{{ route('v2.val.landed.receipt', $doc->id_landed_cost) }}">
            <x-dl::card title="Goods Receipt" description="Choose a posted goods receipt. All its stock lines are added; the cost is shared over them when you Confirm. A planned share is shown above.">
                <x-dl::field.select name="pv_id" label="Goods Receipt" required :options="ValuationDesk::receiptOptions()" :source="Src::of('tm2pur.pv_receipt')" />
                <x-dl::action-bar><x-dl::button icon="fa-plus" label="Add" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
        @foreach ($doc->lines as $l)
            <x-dl::action-form action="{{ route('v2.val.landed.remove', [$doc->id_landed_cost, $l->id_landed_cost_line]) }}">
                <x-dl::action-bar align="start"><x-dl::button variant="secondary" icon="fa-trash" :label="raw(t('Remove').' · '.$l->item_code)" type="submit" formnovalidate /></x-dl::action-bar>
            </x-dl::action-form>
        @endforeach
        <x-dl::action-form action="{{ route('v2.val.landed.confirm', $doc->id_landed_cost) }}">
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="confirm" type="submit" label="Confirm" :onclick="'return confirm('.json_encode(t('Confirm the landed cost? The journal is posted.')).')'" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @elseif ($status === 'CONFIRMED')
        <x-dl::action-form action="{{ route('v2.val.landed.revise', $doc->id_landed_cost) }}">
            <x-dl::card title="Revise Amount" description="The forwarder bill changed. The original journal stays; a correction journal with the difference is stacked on it (see Repost).">
                <x-dl::form.row :cols="2">
                    <x-dl::field.decimal name="new_amount" label="New Amount" required :value="$doc->amount" :source="Src::of('tm2inv.inv_landed_cost.amount')" />
                    <x-dl::field.text name="reason" label="Reason" required :case-toggle="false" hint="At least 10 characters." :source="Src::of('tm2inv.inv_repost_log.reason')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button icon="fa-pen" label="Revise" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
        <x-dl::action-form action="{{ route('v2.val.landed.cancel', $doc->id_landed_cost) }}">
            <x-dl::card title="Cancel" description="Cancel posts a reversing journal. A landed cost whose variance was already closed to cost of goods sold cannot be cancelled.">
                <x-dl::field.text name="reason" label="Cancel Reason" required :case-toggle="false" hint="At least 10 characters." :source="Src::of('tm2inv.inv_landed_cost.cancel_reason')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button variant="danger" icon="fa-ban" label="Cancel" type="submit" :onclick="'return confirm('.json_encode(t('Cancel this landed cost? A reversing journal is posted.')).')'" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @else
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    @endif
@endif
