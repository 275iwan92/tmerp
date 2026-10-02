{{-- Penerimaan Barang (SPB / PV) v2. Flow & aturan = tmerp pages/pur/pv (index + entry) lewat App\Support\V2\Data\PurchaseReceive.
     Alur: GRID penerimaan -> + New Record = pilih supplier (yang punya PO SENT / sebagian diterima) -> pilih gudang + baris PO ->
     DRAFT: qty diterima (satuan beli & stok), lot, lokasi, QC qty selesai, QC kualitas PASS / FAIL / PARTIAL, override toleransi ->
     Confirm (= Post v1: stok masuk + jurnal pembelian, PO ikut ter-update) -> Closed saat sudah diserahkan / ditagih.
     Jenis lain (user 2026-10-01, App\Services\Pur\SpecialReceiptService): From Customer = bahan customer utk CO tertentu
     (wajib No. CO, tanpa stok & jurnal); From Subcon = barang kembali dari subkon atas PO Subkon, langsung ke proses
     berikutnya; kekurangan yang ketahuan belakangan dicatat lewat Report Shortage (PO & jurnal ikut koreksi). --}}
@php
    use App\Models\Pur\PvReceipt;
    use App\Services\Pur\PvPostingService;
    use App\Services\Pur\SpecialReceiptService;
    use App\Support\V2\Data\PurchaseOrder;
    use App\Support\V2\Data\PurchaseReceive;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'purchase-receive');
    $pv = request('id') ? PvReceipt::with('lines')->find((int) request('id')) : null;
    abort_if(request('id') && ! $pv, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $pv && ! request('new'))
    <x-dl::grid id="pv-list" :rows="PurchaseReceive::rows()" search="Search receipt no., supplier, PO no...." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2pur.pv_receipt', 'tm2pur.pv_line') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Receipt No. / Supplier', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'type', 'label' => 'Receipt Type', 'type' => 'badge', 'tones' => PurchaseReceive::TYPE_TONES],
            ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
            ['key' => 'warehouse', 'label' => t('Warehouse')],
            ['key' => 'po', 'label' => 'No. PO'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PurchaseReceive::STATUS_TONES],
        ]"
        :detail="['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'po', 'label' => 'PO No.'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'accepted', 'label' => 'Accepted', 'type' => 'number'],
            ['key' => 'rejected', 'label' => 'Rejected', 'type' => 'number'],
        ]]"
        :stats="[['label' => t('All')], ['label' => 'Draft / QC', 'where' => ['status' => ['DRAFT', 'QC_PENDING']], 'tone' => 'warn'], ['label' => 'Posted', 'where' => ['status' => 'POSTED'], 'tone' => 'success'], ['label' => 'From Customer', 'where' => ['type' => 'From Customer'], 'tone' => 'info'], ['label' => 'From Subcon', 'where' => ['type' => 'From Subcon'], 'tone' => 'warn']]"
        :filters="['supplier' => 'Supplier', 'warehouse' => 'Gudang', 'status' => 'Status']" />
@elseif (! $pv)
    @php $kind = request('type', 'PO'); @endphp
    <x-dl::action-bar>
        @foreach (PurchaseReceive::TYPES as $k => $label)
            <x-dl::button size="sm" :variant="$kind === $k ? 'primary' : 'secondary'" :label="$label" :href="$home.'?new=1&type='.$k" />
        @endforeach
    </x-dl::action-bar>
@endif
@if (! $pv && request('new') && request('type') === 'CUSTOMER')
    <x-dl::action-form action="{{ route('v2.pv.create-customer') }}">
        <x-dl::card title="+ New Record — Material from Customer" description="Material brought by the customer for a specific Customer Order (inquiry marked 'material from customer'). The CO number is mandatory. Not company stock: no stock value, no journal.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="co_id" label="Customer Order" required :options="PurchaseReceive::coOptions()" :value="request('co')" :source="Src::of('tm2sdi.co_order (CONFIRMED / SUBMITTED)')" />
                <x-dl::field.select name="warehouse" label="Stored At (optional)" :options="PurchaseReceive::warehouses()" :source="Src::of('tm2mst.wh_warehouse')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create Receipt" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@elseif (! $pv && request('new') && request('type') === 'SUBCON')
    @php $sub = (int) request('supplier'); @endphp
    <x-dl::card title="+ New Record — Return from Subcon" description="Goods coming back from a subcontractor against a Subcon PO. They usually go straight to the next process; shortages found later are recorded with Report Shortage.">
        <x-dl::action-form method="get" action="{{ $home }}">
            <input type="hidden" name="new" value="1"><input type="hidden" name="type" value="SUBCON">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="supplier" label="Subcontractor" required :options="PurchaseReceive::subconSupplierOptions()" :value="$sub ?: null" :source="Src::of('tm2pur.po_order (SUBCONTRACT, SENT)')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button type="submit" variant="secondary" :label="t('Next: choose PO lines')" />
            </x-dl::action-bar>
        </x-dl::action-form>
        @if ($sub)
            <x-dl::action-form action="{{ route('v2.pv.create-subcon') }}">
                <input type="hidden" name="supplier_id" value="{{ $sub }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="do_id" label="Subcon Delivery Note (optional)" :options="PurchaseReceive::subconDoOptions($sub)" :source="Src::of('tm2sdi.do_delivery (SUBCON)')" />
                </x-dl::form.row>
                <x-dl::field.checkboxes name="po_line_ids" label="Subcon PO lines coming back" required :options="PurchaseReceive::subconLineOptions($sub)" :source="Src::of('tm2pur.po_line (SUBCONTRACT, open)')" />
                <x-dl::action-bar><x-dl::button preset="save" label="Create Receipt" /></x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </x-dl::card>
@elseif (! $pv && request('new'))
    @php $sid = (int) request('supplier'); @endphp
    <x-dl::card title="+ New Record — Goods Receipt (SPB)" description="Step 1: choose a supplier with sent POs. Step 2: choose the receiving warehouse and the arriving PO lines.">
        <x-dl::action-form method="get" action="{{ $home }}">
            <input type="hidden" name="new" value="1">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="supplier" label="Supplier" required :options="PurchaseReceive::supplierOptions()" :value="$sid ?: null" :source="Src::of('tm2pur.po_order (SENT / PARTIALLY_RECEIVED)')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button type="submit" variant="secondary" :label="t('Next: choose PO lines')" />
            </x-dl::action-bar>
        </x-dl::action-form>
        @if ($sid)
            <x-dl::action-form action="{{ route('v2.pv.create') }}">
                <input type="hidden" name="supplier_id" value="{{ $sid }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="warehouse" label="Receiving Warehouse" required :options="PurchaseReceive::warehouses()" :source="Src::of('tm2mst.wh_warehouse')" />
                </x-dl::form.row>
                <x-dl::field.checkboxes name="po_line_ids" label="Arriving PO lines" required :options="PurchaseReceive::openLineOptions($sid)" :source="Src::of('tm2pur.po_line (sisa > 0)')" />
                <x-dl::action-bar><x-dl::button preset="save" label="Buat Penerimaan" /></x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </x-dl::card>
@elseif ($pv && $pv->i_receipt_type !== 'PO')
    @php
        $draft = $pv->status === 'DRAFT';
        $isCust = $pv->i_receipt_type === 'CUSTOMER';
        $party = $isCust ? \Illuminate\Support\Facades\DB::connection('tm2mst')->table('cust_customer')->where('id_customer', $pv->i_customer_id)->value('customer_name')
            : (PurchaseOrder::suppliers([$pv->i_supplier_id])[$pv->i_supplier_id] ?? '—');
        $co = $pv->i_co_id ? \Illuminate\Support\Facades\DB::connection('tm2sdi')->table('co_order')->where('id_order', $pv->i_co_id)->value('co_no') : null;
        $sj = $pv->i_do_id ? \Illuminate\Support\Facades\DB::connection('tm2sdi')->table('do_delivery')->where('id_delivery', $pv->i_do_id)->value('do_no') : null;
        $coItems = $isCust ? PurchaseReceive::coItemOptions($pv->i_co_id) : [];
        $mat = \Illuminate\Support\Facades\DB::connection('tm2mst')->table('mat_raw')->whereIn('material_id', $pv->lines->pluck('item_id')->filter())->get(['material_id', 'material_code', 'material_name'])->keyBy('material_id');
        $lines = $pv->lines->sortBy('line_no')->map(fn ($l) => [
            'pv_line_id' => $l->pv_line_id, 'i_description' => $l->i_description, 'i_co_line_id' => $l->i_co_line_id, 'item_id' => $l->item_id,
            'item_id_label' => isset($mat[$l->item_id]) ? $mat[$l->item_id]->material_code.' · '.$mat[$l->item_id]->material_name : '',
            'po' => $l->c_po_doc_no ? $l->c_po_doc_no.' #'.$l->c_po_line_no : '—', 'i_qty_purchase' => (float) $l->i_qty_purchase, 'i_uom_purchase' => $l->i_uom_purchase,
            'i_next_process' => $l->i_next_process, 'i_lot_no' => $l->i_lot_no, 'short' => (float) $l->c_qty_short > 0 ? \Devlib\Locale::decimal($l->c_qty_short, 2).' — '.$l->i_short_note : '—',
        ])->values()->all();
    @endphp
    <x-dl::form id="pv-special" :title="($isCust ? 'Material from Customer · ' : 'Return from Subcon · ').raw($pv->label())" :subtitle="raw($party.' · '.$pv->status)"
        panel="top" kind="document" :status="PurchaseReceive::formStatus($pv)" :pipeline="true"
        :action="route('v2.pv.save', $pv->pv_id)" action-field="action" :back="$home" :new-url="$home.'?new=1&type='.$pv->i_receipt_type"
        :hide="array_merge(['duplicate', 'email', 'print'], $pv->status === 'POSTED' ? [] : ['cancel'], $draft ? [] : ['delete'])"
        :tabs="['general' => 'General', 'notes' => 'Notes']"
        :created-by="$pv->created_by" :created-at="$pv->created_at?->toDateTimeString()" :updated-by="$pv->updated_by" :updated-at="$pv->updated_at?->toDateTimeString()">
        @if ($pv->status === 'POSTED')
            <x-slot:special>
                <x-dl::button size="sm" preset="print" :href="\App\Support\V2\Print\PrintDocs::url('goods-receipt', $pv->pv_id)" target="_blank" />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No.</span><b>{{ $pv->doc_no ?: t('(on Post)') }}</b></div>
            <x-dl::field.date name="i_doc_date" label="Received Date" :value="$pv->i_doc_date?->toDateString()" :source="Src::of('tm2pur.pv_receipt.i_doc_date')" />
            <x-dl::field.display :label="$isCust ? 'Customer' : 'Subcontractor'" :value="$party" :source="Src::of($isCust ? 'tm2mst.cust_customer' : 'tm2mst.sup_supplier')" />
            <x-dl::field.display :label="$isCust ? 'Customer Order' : 'Subcon PO'" :value="$isCust ? $co : ($pv->c_po_doc_list ?: '—')" :source="Src::of($isCust ? 'tm2pur.pv_receipt.i_co_id' : 'tm2pur.pv_receipt.c_po_doc_list')" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="i_supplier_do_no" :label="$isCust ? 'Customer Delivery Note No.' : 'Subcon Delivery Note No.'" :case-toggle="false" :value="$pv->i_supplier_do_no" :source="Src::of('tm2pur.pv_receipt.i_supplier_do_no')" />
                <x-dl::field.text name="i_vehicle_no" label="Vehicle No." :case-toggle="false" :value="$pv->i_vehicle_no" :source="Src::of('tm2pur.pv_receipt.i_vehicle_no')" />
                @if ($isCust)
                    <x-dl::field.select name="i_warehouse_code" label="Stored At" :options="PurchaseReceive::warehouses()" :value="$pv->i_warehouse_code" :source="Src::of('tm2pur.pv_receipt.i_warehouse_code')" />
                @else
                    <x-dl::field.display label="Our Delivery Note (to subcon)" :value="$sj ?: '—'" :source="Src::of('tm2pur.pv_receipt.i_do_id')" />
                @endif
                <x-dl::field.display label="Posted" :value="$pv->posted_at ? \Devlib\Locale::dt($pv->posted_at).' · '.$pv->posted_by : '—'" :source="Src::of('tm2pur.pv_receipt.posted_at')" />
            </x-dl::form.row>
            @unless ($isCust)
                <x-dl::field.display label="Journal" :value="implode(', ', SpecialReceiptService::vouchers($pv)) ?: '—'" :source="Src::of('tm2acc.fin_voucher (PV, PV_SHORT)')" />
            @endunless
        </x-slot:general>
        <x-slot:notes>
            <x-dl::field.text name="i_notes" label="Receipt Notes" multiline :value="$pv->i_notes" :source="Src::of('tm2pur.pv_receipt.i_notes')" />
            @if ($pv->status === 'CANCELLED')
                <x-dl::field.display label="Cancelled" :value="$pv->cancelled_by.' · '.\Devlib\Locale::dt($pv->cancelled_at).' — '.$pv->cancel_reason" :source="Src::of('tm2pur.pv_receipt.cancel_reason')" />
            @endif
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="pv_line_id" remove-field="remove" :addable="$draft && $isCust" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2pur.pv_line') }}"
                :columns="array_values(array_filter([
                    $isCust ? ['key' => 'i_co_line_id', 'label' => 'CO Item', 'type' => 'select', 'options' => $coItems, 'width' => '220px'] : ['key' => 'po', 'label' => 'Subcon PO', 'type' => 'display', 'width' => '130px'],
                    ['key' => 'i_description', 'label' => $isCust ? 'Material (as delivered)' : 'Item', 'type' => $isCust ? 'text' : 'display', 'required' => true, 'width' => '280px'],
                    $isCust ? ['key' => 'item_id', 'label' => 'Master Material (if any)', 'type' => 'lookup', 'lookup' => 'material', 'width' => '220px'] : null,
                    ['key' => 'i_qty_purchase', 'label' => 'Qty Received', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '110px'],
                    ['key' => 'i_uom_purchase', 'label' => 'UoM', 'type' => $isCust ? 'text' : 'display', 'width' => '80px'],
                    $isCust ? ['key' => 'i_lot_no', 'label' => 'Lot / Roll No.', 'width' => '120px'] : ['key' => 'i_next_process', 'label' => 'Sent To (next process)', 'width' => '180px'],
                    $isCust ? null : ['key' => 'short', 'label' => 'Shortage found later', 'type' => 'display', 'more' => true],
                ]))" />
        </x-slot:detail>
    </x-dl::form>
    @if (! $isCust && $pv->status === 'POSTED')
        <x-dl::action-form action="{{ route('v2.pv.short', $pv->pv_id) }}">
            <x-dl::card title="Report Shortage" description="A shortage found later in the next process lowers the received quantity; the Subcon PO and the subcon cost journal are corrected automatically.">
                <x-dl::form.row :cols="3">
                    <x-dl::field.select name="pv_line_id" label="Line" required :options="$pv->lines->mapWithKeys(fn ($l) => [$l->pv_line_id => '#'.$l->line_no.' '.$l->i_description.' ('.\Devlib\Locale::decimal($l->i_qty_purchase, 2).' '.$l->i_uom_purchase.')'])->all()" :source="Src::of('tm2pur.pv_line')" />
                    <x-dl::field.decimal name="short_qty" label="Short Qty" required :source="Src::of('tm2pur.pv_line.c_qty_short')" />
                    <x-dl::field.text name="short_note" label="Found At / Remark" required placeholder="e.g. counted at gluing: 50 pcs missing" :source="Src::of('tm2pur.pv_line.i_short_note')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button variant="danger" icon="fa-triangle-exclamation" label="Record Shortage" type="submit" onclick="return confirm('Record this shortage? Received qty, PO and journal will be corrected.')" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @endif
@elseif ($pv)
    {{-- Form Master-Detail DevLib (disetujui user 2026-09-30). --}}
    @php
        $draft = in_array($pv->status, ['DRAFT', 'QC_PENDING'], true);
        $blockers = $draft ? PvPostingService::blockers($pv) : [];
        $sup = \Illuminate\Support\Facades\DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $pv->i_supplier_id)->first();
        $money = fn ($v) => 'Rp '.\Devlib\Locale::number((float) $v, 0);
        $trim = fn ($v) => \Devlib\Locale::decimal($v, 2);
        $value = (float) $pv->lines->sum('c_line_value');
        $lines = $pv->lines->sortBy('line_no')->map(fn ($l) => [
            'pv_line_id' => $l->pv_line_id, 'i_description' => $l->i_description, 'po' => ($l->c_po_doc_no ?? '—').' #'.$l->c_po_line_no,
            'i_qty_purchase' => (float) $l->i_qty_purchase, 'i_uom_purchase' => $l->i_uom_purchase, 'i_qty_stock' => (float) $l->i_qty_stock, 'i_uom_stock' => $l->i_uom_stock,
            'qc_qty' => $l->qc_qty_status === 'DONE' ? 'DONE' : '', 'qc_quality_status' => $l->qc_quality_status ?: 'PENDING',
            'c_qty_accepted' => (float) $l->c_qty_accepted, 'c_qty_rejected' => (float) $l->c_qty_rejected,
            'variance' => $trim($l->c_qty_variance_pct).'% (toleransi '.$trim($l->c_tol_pct).'%)'.($l->is_tol_exceeded ? ($l->tol_override_by ? ' · override '.$l->tol_override_by : ' · MELAMPAUI') : ''),
            'tol_override_reason' => $l->tol_override_reason ?? '', 'i_lot_no' => $l->i_lot_no, 'i_location_code' => $l->i_location_code,
            'value' => $money($l->c_line_value), 'code' => $l->c_item_code ?: '—',
        ])->values()->all();
    @endphp
    @if ($blockers)<x-dl::alert tone="warn" :message="'Belum bisa Confirm (Post): '.implode(' ', $blockers)" />@endif

    <x-dl::form id="pv-form" :title="'Penerimaan Barang · '.raw($pv->label())" :subtitle="raw(($sup->supplier_name ?? '—').' · '.$pv->status)"
        panel="top" kind="document" :status="PurchaseReceive::formStatus($pv)" :pipeline="true"
        :action="route('v2.pv.save', $pv->pv_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="array_merge(['duplicate', 'email', 'print'], $pv->status === 'POSTED' ? [] : ['cancel'], $pv->status === 'DRAFT' ? [] : ['delete'])"
        :tabs="['general' => 'General', 'supplier' => 'Supplier', 'notes' => 'Notes']"
        :created-by="$pv->created_by" :created-at="$pv->created_at?->toDateTimeString()" :updated-by="$pv->updated_by" :updated-at="$pv->updated_at?->toDateTimeString()">
        @if ($pv->status === 'POSTED')
            <x-slot:special>
                {{-- Label wajib dibuat saat barang datang (user 2026-10-02): label palet + label item langsung dari penerimaan ini. --}}
                @if ($pv->rm_stock_doc_id)
                    <x-dl::button size="sm" variant="secondary" icon="fa-pallet" label="Pallet Labels" :href="route('v2.screen', 'label-pallet').'?receipt='.$pv->pv_id" />
                    <x-dl::button size="sm" variant="secondary" icon="fa-tag" label="Item Labels" :href="route('v2.screen', 'label-item').'?receipt='.$pv->pv_id" />
                @endif
                <x-dl::button size="sm" preset="print" :href="\App\Support\V2\Print\PrintDocs::url('goods-receipt', $pv->pv_id)" target="_blank" />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No. SPB</span><b>{{ $pv->doc_no ?: t('(on Post)') }}</b></div>
            <x-dl::field.date name="i_doc_date" label="Receipt Date" :value="$pv->i_doc_date?->toDateString()" :source="Src::of('tm2pur.pv_receipt.i_doc_date')" />
            <x-dl::field.display label="Receiving Warehouse" :value="$pv->i_warehouse_code" :source="Src::of('tm2pur.pv_receipt.i_warehouse_code')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Nilai Diterima</span><b>{{ $money($value) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="i_supplier_do_no" label="Supplier Delivery Note No." :case-toggle="false" :value="$pv->i_supplier_do_no" :source="Src::of('tm2pur.pv_receipt.i_supplier_do_no')" />
                <x-dl::field.text name="i_vehicle_no" label="No. Kendaraan" :case-toggle="false" :value="$pv->i_vehicle_no" :source="Src::of('tm2pur.pv_receipt.i_vehicle_no')" />
                <x-dl::field.display label="PO" :value="$pv->c_po_doc_list ?: '—'" :source="Src::of('tm2pur.pv_receipt.c_po_doc_list')" />
                <x-dl::field.display label="Diposting" :value="$pv->posted_at ? \Devlib\Locale::dt($pv->posted_at).' · '.$pv->posted_by : '—'" :source="Src::of('tm2pur.pv_receipt.posted_at')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:supplier>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Supplier" :value="$sup->supplier_name ?? '—'" />
                <x-dl::field.display label="Contact Person" :value="$sup->contact_name ?? '—'" />
                <x-dl::field.display label="Telepon" :value="$sup->phone ?? '—'" />
                <x-dl::field.display label="Alamat" :value="$sup->default_address ?? '—'" />
            </x-dl::form.row>
        </x-slot:supplier>
        <x-slot:notes>
            <x-dl::field.text name="i_notes" label="Receipt notes" multiline :value="$pv->i_notes" :source="Src::of('tm2pur.pv_receipt.i_notes')" />
            @if ($pv->status === 'CANCELLED')
                <x-dl::field.display label="Dibatalkan" :value="$pv->cancelled_by.' · '.\Devlib\Locale::dt($pv->cancelled_at).' — '.$pv->cancel_reason" :source="Src::of('tm2pur.pv_receipt.cancel_reason', 'cancelled_by', 'cancelled_at', 'rm_stock_rev_doc_id')" />
            @endif
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="pv_line_id" :addable="false" :duplicable="false" row-lock="__never" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2pur.pv_line') }}"
                :columns="[
                    ['key' => 'i_description', 'label' => 'Item', 'type' => 'display', 'width' => '260px'],
                    ['key' => 'po', 'label' => 'PO', 'type' => 'display', 'width' => '130px'],
                    ['key' => 'i_qty_purchase', 'label' => 'Qty Beli', 'type' => 'number', 'sum' => true, 'width' => '100px'],
                    ['key' => 'i_uom_purchase', 'label' => 'Sat. Beli', 'type' => 'display', 'width' => '70px'],
                    ['key' => 'i_qty_stock', 'label' => 'Qty Stok', 'type' => 'number', 'width' => '100px'],
                    ['key' => 'i_uom_stock', 'label' => 'Sat. Stok', 'type' => 'display', 'width' => '70px'],
                    ['key' => 'qc_qty', 'label' => 'QC Qty', 'type' => 'select', 'options' => ['DONE' => 'Selesai'], 'width' => '100px'],
                    ['key' => 'qc_quality_status', 'label' => 'QC Kualitas', 'type' => 'select', 'options' => ['PENDING' => 'Pending', 'PASS' => 'Pass', 'FAIL' => 'Fail', 'PARTIAL' => 'Partial'], 'width' => '110px'],
                    ['key' => 'c_qty_accepted', 'label' => t('Qty Received (Partial)'), 'type' => 'number', 'more' => true],
                    ['key' => 'c_qty_rejected', 'label' => t('Qty Rejected (Partial)'), 'type' => 'number', 'more' => true],
                    ['key' => 'variance', 'label' => 'Selisih vs PO', 'type' => 'display', 'more' => true],
                    ['key' => 'tol_override_reason', 'label' => 'Tolerance Override Reason', 'more' => true],
                    ['key' => 'i_lot_no', 'label' => 'No. Lot', 'more' => true],
                    ['key' => 'i_location_code', 'label' => 'Rack Location', 'more' => true],
                    ['key' => 'value', 'label' => t('Received Value'), 'type' => 'display', 'more' => true],
                    ['key' => 'code', 'label' => t('Item Code'), 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        @php $more = PurchaseReceive::openLineOptions($pv->i_supplier_id); @endphp
                        @if ($more)
                            <x-dl::field.multi-select name="po_line_ids" label="+ Other PO lines from this supplier" :options="$more" :source="Src::of('tm2pur.po_line (sisa > 0)')" />
                            <x-dl::button size="sm" variant="secondary" :label="t('+ Add PO Line')" type="submit" name="action" value="add_lines" formnovalidate />
                        @endif
                    </x-slot:tools>
                @endif
            </x-dl::lines>
            <x-dl::doc-footer :words="\Devlib\Terbilang::words(round($value), 'IDR', 'id')"
                :summary="[['Baris', (string) $pv->lines->count()], ['Nilai Diterima', $money($value), 'grand']]" />
        </x-slot:detail>
    </x-dl::form>
@endif
