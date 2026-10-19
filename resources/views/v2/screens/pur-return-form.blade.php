{{--
 REMARK
 Tujuan       : Form Purchase Return v2 (Nota Retur Barang; menggantikan halaman v1 pages::pur.prt.index dan pur.prt.entry): kartu Start (dari penerimaan dengan reject QC, atau dari stok per supplier), header (tanggal, disposisi Credit note / Replace goods, alasan, tindak lanjut: komunikasi, PIC, tanggal kirim, ekspedisi, nota kredit, catatan), baris retur (QC: satu baris per baris penerimaan yang ditolak, qty dibatasi sisa reject, harga mengikuti penerimaan; stok: lookup item, qty, harga), Save = Draft, Submit = nomor NRB terbit + flow approval, Cancel Draft dengan alasan, Mark Complete, Duplicate, tab History.
 Sumber hulu  : tm2pur.prt_return + prt_line, pv_receipt + pv_line (reject QC, harga), tm2mst.sup_supplier dan mat_raw, flow_config PRT dan flow_log; aturan di App\Services\Pur\PurchaseReturnService.
 Input        : Query id (dokumen) atau new=1 (&source=QC_REJECT&pv_id= atau source=FROM_STOCK&supplier_id= setelah kartu Start); session v2_flash dan errors; old input.
 Output       : POST v2.pur.return.save (Save / Submit / Duplicate), .start (kartu Start), .cancel (dialog alasan), .complete (Mark Complete).
 Dipakai oleh : Dibuka dari grid purchase-return (Open dan + New Record) dan dari Approval Center Purchase Return (tautan Buka).
 Status       : Aktif (Master T6). Keputusan Approve / Reject / Hold tetap di Approval Center; form hanya-baca setelah Submit. Tidak ada baris tersimpan sebelum Save pertama. Notes / Chronology disimpan di kolom i_notes (baru).
--}}
@php
    use App\Models\Pur\PrtReturn;
    use App\Services\Pur\PurchaseReturnService as Prs;
    use App\Support\V2\Data\ApprovalCenter;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $svc = app(Prs::class);
    $home = route('v2.screen', 'purchase-return');
    $doc = request('id') ? PrtReturn::with('lines')->find((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
    abort_unless($doc || request('new'), 404);
    $new = ! $doc;
    $source = $new ? (string) request('source', '') : $doc->i_source;
    $pvId = $new ? (int) request('pv_id') : (int) $doc?->pv_id;
    $supplierId = $new ? (int) request('supplier_id') : (int) $doc->i_supplier_id;
    $pv = $pvId ? \App\Models\Pur\PvReceipt::where('status', 'POSTED')->find($pvId) : null;
    if ($new && $source === 'QC_REJECT' && $pv) {
        $supplierId = (int) $pv->i_supplier_id;
    }
    $start = $new && ! (($source === 'QC_REJECT' && $pv) || ($source === 'FROM_STOCK' && $supplierId));
    abort_if($new && $source !== '' && $start, 404);
    $qc = $source === 'QC_REJECT';
    $supplier = $supplierId ? \Illuminate\Support\Facades\DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $supplierId)->first() : null;
    [$state, $stage] = $new ? ['new', null] : $svc->formState($doc);
    $editable = $new || $svc->editable($doc);
    $s = fn (string $col) => Src::of('tm2pur.prt_return.'.$col);
    $v = fn (string $col, $default = null) => old($col, $default);
    $oldLines = old('lines');
    $rows = $start ? [] : $svc->rows($source, $new ? null : $doc, $new ? $pvId : null, is_array($oldLines) ? $oldLines : null);
    $amount = $new ? 0.0 : (float) $doc->lines->sum('c_line_value');
    $appr = ! $new && $state === 'approval' ? ApprovalCenter::approvalState('PRT', (int) $doc->prt_id, $amount) : ['levels' => 0, 'at' => 0];
    $hold = ! $new && $state === 'hold' ? \App\Support\V2\Data\ApprovalCenter::flowLog('PRT', (int) $doc->prt_id)[0] ?? null : null;
    $columns = $qc ? [
        ['key' => 'item', 'label' => 'Item', 'type' => 'display', 'width' => '300px'],
        ['key' => 'rejected', 'label' => 'QC rejected', 'type' => 'display', 'width' => '100px'],
        ['key' => 'returnable', 'label' => 'Returnable', 'type' => 'display', 'width' => '100px'],
        ['key' => 'qty', 'label' => 'Return qty', 'type' => 'number', 'sum' => true, 'width' => '110px'],
        ['key' => 'uom', 'label' => 'Unit', 'type' => 'display', 'width' => '70px'],
        ['key' => 'unit_price', 'label' => 'Price (IDR)', 'type' => 'money', 'formula' => 'price * 1', 'width' => '120px'],
        ['key' => 'value', 'label' => 'Value (IDR)', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true, 'width' => '130px'],
        ['key' => 'i_lot_no', 'label' => 'Lot', 'type' => 'text', 'width' => '110px'],
        ['key' => 'price', 'label' => 'Receipt price', 'type' => 'display', 'more' => true],
    ] : [
        ['key' => 'item_id', 'label' => 'Item', 'type' => 'lookup', 'lookup' => 'stock_item_rm', 'required' => true, 'width' => '320px'],
        ['key' => 'qty', 'label' => 'Return qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '110px'],
        ['key' => 'uom_code', 'label' => 'Unit', 'type' => 'text', 'width' => '80px'],
        ['key' => 'price', 'label' => 'Price (IDR, empty = last price)', 'type' => 'money', 'width' => '160px'],
        ['key' => 'value', 'label' => 'Value (IDR)', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true, 'width' => '130px'],
        ['key' => 'i_lot_no', 'label' => 'Lot', 'type' => 'text', 'width' => '110px'],
    ];
    $qty = (float) collect($rows)->sum(fn ($r) => (float) (Locale::input($r['qty'] ?? 0) ?? 0));
    $value = (float) collect($rows)->sum(fn ($r) => (float) (Locale::input($r['qty'] ?? 0) ?? 0) * (float) ($r['price'] === '' ? 0 : $r['price']));
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($start)
    <x-dl::card title="Return from a receipt (QC reject)" description="Goods that failed QC when they arrived. Choose the posted receipt: its rejected lines are listed for you, then enter the quantity to return.">
        <x-dl::action-form action="{{ route('v2.pur.return.start').'?source=QC_REJECT' }}">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="pv_id" label="Receipt" required placeholder="Choose" :options="collect($svc->returnableReceipts())->mapWithKeys(fn ($r) => [$r['id'] => $r['doc_no'].' · '.$r['supplier'].' · '.t('returnable').' '.Locale::number($r['balance'], 3)])->all()" :source="Src::of('tm2pur.prt_return.pv_id')" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button preset="save" label="Start" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>
    <x-dl::card title="Return from stock" description="Goods already in the warehouse whose problem was found later. Choose the supplier, then add the items to return.">
        <x-dl::action-form action="{{ route('v2.pur.return.start').'?source=FROM_STOCK' }}">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="supplier_id" label="Supplier" required placeholder="Choose" :options="$svc->supplierOptions()" :source="Src::of('tm2pur.prt_return.i_supplier_id')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Start" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>
@else
@if ($hold)
    <x-dl::alert tone="warning" :message="raw(t('Info requested by').' '.($hold['by'] ?? '—').' · '.($hold['at'] ?? '').' — '.($hold['note'] ?? ''))" />
@elseif ($state === 'approval')
    <x-dl::alert tone="info" message="This return is waiting for approval. The decision is made in the Purchase Return Approval Center; the form is read-only until then." />
@elseif ($state === 'confirmed')
    <x-dl::alert tone="info" message="This return is approved but not linked to any warehouse Stock Out. If the goods already entered stock, record a separate Stock Out in Inventory." />
@endif

<x-dl::form id="pur-return-form" :title="($new ? 'New ' : '').'Purchase Return'" :subtitle="raw(t($qc ? Prs::SOURCES['QC_REJECT'] : Prs::SOURCES['FROM_STOCK']).' · '.($supplier->supplier_name ?? '').($new || ! $doc->doc_no ? '' : ' · '.$doc->doc_no))"
    panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="$state === 'approval'" :approval-levels="$state === 'approval' ? max(1, $appr['levels']) : 0" :approval-at="$appr['at']" :can-input="$editable"
    :action="route('v2.pur.return.save')" action-field="action" :back="$home" :new-url="route('v2.screen', 'pur-return-form').'?new=1'" :hide="['delete', 'cancel', 'email', 'hold', 'print', 'confirm']"
    :hidden="($new ? ['source' => $source, 'pv_id' => $pvId ?: '', 'supplier_id' => $supplierId] : ['id' => $doc->prt_id])" :tabs="['general' => t('Document'), 'follow' => t('Disposition and follow-up')]" :log="$new ? [] : $svc->history($doc)"
    :created-by="$doc?->created_by" :created-at="$doc?->created_at?->toDateTimeString()" :updated-by="$doc?->updated_by" :updated-at="$doc?->updated_at?->toDateTimeString()">
    <x-slot:special>
        @if ($editable && ! $new)
            <x-dl::button type="button" variant="secondary" icon="fa-ban" label="Cancel" data-dl-open="prt-cancel" />
            <x-dl::button type="submit" name="action" value="submit" variant="primary" icon="fa-paper-plane" label="Submit" data-dl-confirm="{{ t('Submit this return for approval? A document number will be issued and the lines cannot be edited while it is in approval.') }}" />
        @endif
        @if (! $new && $state === 'confirmed')
            <x-dl::button type="submit" form="prt-complete" variant="primary" icon="fa-check" label="Mark Complete" data-dl-confirm="{{ t('Mark this return as complete? The replacement goods or the credit note have been received.') }}" />
        @endif
        @if (! $new && in_array($state, ['rejected', 'cancelled'], true))
            <x-dl::button type="submit" name="action" value="duplicate" variant="secondary" icon="fa-copy" label="Duplicate" formnovalidate />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Document no." :value="$new || ! $doc->doc_no ? t('(on Submit)') : raw($doc->doc_no)" />
        <x-dl::strip-kv kind="grand" label="Total return qty" :value="Locale::number($qty, 3)" :value-attrs="['data-l-val' => 'lines:sum.qty']" />
        <x-dl::strip-kv kind="grand" label="Total return value (IDR)" :value="Locale::number($value, 2)" :value-attrs="['data-l-val' => 'lines:sum.value']" />
    </x-slot:strip>
    <x-slot:general>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Supplier" :value="raw(($supplier->supplier_name ?? '—').' ('.($supplier->supplier_code ?? '').')')" :source="$s('i_supplier_id')" />
            <x-dl::field.date name="i_doc_date" label="Return date" required :value="$v('i_doc_date', ($doc?->i_doc_date ?? now())->toDateString())" :source="$s('i_doc_date')" />
            @if ($qc)
                <x-dl::field.display label="Ref. receipt" :value="raw($pv?->doc_no ?? '—')" :source="$s('pv_id')" />
                <x-dl::field.text name="qc_inspector" label="QC inspector" :value="$v('qc_inspector', $doc?->qc_inspector)" :source="$s('qc_inspector')" />
            @endif
            <x-dl::field.select name="i_disposition" label="Disposition" required :options="array_map('t', Prs::DISPOSITIONS)" :value="$v('i_disposition', $doc?->i_disposition ?? 'CREDIT_NOTE')" :source="$s('i_disposition')" />
        </x-dl::form.row>
        <x-dl::field.text name="i_reason" label="Main reason (required to Submit)" multiline :value="$v('i_reason', $doc?->i_reason)" :source="$s('i_reason')" />
    </x-slot:general>
    <x-slot:follow>
        <x-dl::form.row :cols="4">
            <x-dl::field.text name="comm_method" label="Communication method" placeholder="Email / Phone / WhatsApp" :value="$v('comm_method', $doc?->comm_method)" :source="$s('comm_method')" />
            <x-dl::field.text name="supplier_pic" label="Supplier PIC" :value="$v('supplier_pic', $doc?->supplier_pic)" :source="$s('supplier_pic')" />
            <x-dl::field.date name="physical_return_date" label="Physical ship date" :value="$v('physical_return_date', $doc?->physical_return_date?->toDateString())" :source="$s('physical_return_date')" />
            <x-dl::field.text name="expedition" label="Expedition" :value="$v('expedition', $doc?->expedition)" :source="$s('expedition')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="replacement_due_date" label="Replacement due date (Replace goods)" :value="$v('replacement_due_date', $doc?->replacement_due_date?->toDateString())" :source="$s('replacement_due_date')" />
            <x-dl::field.text name="cn_supplier_no" label="Supplier CN no. (Credit note)" :value="$v('cn_supplier_no', $doc?->cn_supplier_no)" :source="$s('cn_supplier_no')" />
            <x-dl::field.date name="cn_received_date" label="CN received date (Credit note)" :value="$v('cn_received_date', $doc?->cn_received_date?->toDateString())" :source="$s('cn_received_date')" />
        </x-dl::form.row>
        <x-dl::field.text name="i_notes" label="Notes / chronology" multiline :value="$v('i_notes', $doc?->i_notes)" :source="$s('i_notes')" />
    </x-slot:follow>
    <x-slot:detail>
        <x-dl::lines name="lines" title="Goods to return" add-label="+ Add line" :min-rows="$qc ? 0 : 1" :rows="$rows ?: [['item_id' => '', 'item_id_label' => '', 'qty' => '', 'uom_code' => '', 'price' => '', 'i_lot_no' => '']]"
            :readonly="! $editable" source="{{ Src::of('tm2pur.prt_line') }}" :columns="$columns"
            :key-field="$qc ? 'pv_line_id' : null" :addable="! $qc" :duplicable="! $qc" :row-lock="$qc ? '__never' : null" />
    </x-slot:detail>
</x-dl::form>

@if (! $new && $state === 'confirmed')
    <x-dl::action-form id="prt-complete" action="{{ route('v2.pur.return.complete', $doc->prt_id) }}" />
@endif
@if ($editable && ! $new)
    <x-dl::modal id="prt-cancel" title="Cancel Return" icon="fa-ban" size="md">
        <x-dl::action-form action="{{ route('v2.pur.return.cancel', $doc->prt_id) }}">
            <x-dl::field.text name="cancel_reason" label="Reason (at least 10 characters)" required multiline :source="$s('cancel_reason')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" label="Cancel Return" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>
@endif
@endif
