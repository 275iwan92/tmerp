{{-- Penerimaan Barang (SPB / PV) v2. Flow & aturan = tmerp pages/pur/pv (index + entry) lewat App\Support\V2\Data\PurchaseReceive.
     Alur: GRID penerimaan -> + New Record = pilih supplier (yang punya PO SENT / sebagian diterima) -> pilih gudang + baris PO ->
     DRAFT: qty diterima (satuan beli & stok), lot, lokasi, QC qty selesai, QC kualitas PASS / FAIL / PARTIAL, override toleransi ->
     Confirm (= Post v1: stok masuk + jurnal pembelian, PO ikut ter-update) -> Closed saat sudah diserahkan / ditagih. --}}
@php
    use App\Models\Pur\PvReceipt;
    use App\Services\Pur\PvPostingService;
    use App\Support\V2\Data\PurchaseReceive;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'purchase-receive');
    $pv = request('id') ? PvReceipt::with('lines')->find((int) request('id')) : null;
    abort_if(request('id') && ! $pv, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $pv && ! request('new'))
    <x-dl::grid id="pv-list" :rows="PurchaseReceive::rows()" search="Cari no. penerimaan, supplier, no. PO..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2pur.pv_receipt', 'tm2pur.pv_line') }}"
        :columns="[
            ['key' => 'supplier', 'label' => 'Supplier', 'type' => 'identity', 'code' => 'no'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'warehouse', 'label' => 'Gudang'],
            ['key' => 'po', 'label' => 'No. PO'],
            ['key' => 'lines', 'label' => 'Baris', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PurchaseReceive::STATUS_TONES],
        ]"
        :stats="[['label' => 'Semua'], ['label' => 'Draft / QC', 'where' => ['status' => ['DRAFT', 'QC_PENDING']], 'tone' => 'warn'], ['label' => 'Posted', 'where' => ['status' => 'POSTED'], 'tone' => 'success']]"
        :filters="['supplier' => 'Supplier', 'warehouse' => 'Gudang', 'status' => 'Status']" />
@elseif (! $pv)
    @php $sid = (int) request('supplier'); @endphp
    <x-dl::card title="+ New Record — Penerimaan Barang (SPB)" description="Langkah 1: pilih supplier yang punya PO terkirim. Langkah 2: pilih gudang penerima dan baris PO yang datang.">
        <form method="get" action="{{ $home }}">
            <input type="hidden" name="new" value="1">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="supplier" label="Supplier" required :options="PurchaseReceive::supplierOptions()" :value="$sid ?: null" :source="Src::of('tm2pur.po_order (SENT / PARTIALLY_RECEIVED)')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button type="submit" variant="secondary" label="Lanjut: pilih baris PO" />
            </x-dl::action-bar>
        </form>
        @if ($sid)
            <form method="post" action="{{ route('v2.pv.create') }}">
                @csrf
                <input type="hidden" name="supplier_id" value="{{ $sid }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="warehouse" label="Gudang Penerima" required :options="PurchaseReceive::warehouses()" :source="Src::of('tm2mst.wh_warehouse')" />
                </x-dl::form.row>
                <x-dl::field.checkboxes name="po_line_ids" label="Baris PO yang datang" required :options="PurchaseReceive::openLineOptions($sid)" :source="Src::of('tm2pur.po_line (sisa > 0)')" />
                <x-dl::action-bar><x-dl::button preset="save" label="Buat Penerimaan" /></x-dl::action-bar>
            </form>
        @endif
    </x-dl::card>
@else
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

    <x-dl::form id="pv-form" :title="'Penerimaan Barang · '.$pv->doc_no" :subtitle="($sup->supplier_name ?? '—').' · '.$pv->status"
        panel="top" kind="document" :status="PurchaseReceive::formStatus($pv)" :pipeline="true"
        :action="route('v2.pv.save', $pv->pv_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="array_merge(['duplicate', 'email', 'print'], $pv->status === 'POSTED' ? [] : ['cancel'], $pv->status === 'DRAFT' ? [] : ['delete'])"
        :tabs="['general' => 'General', 'supplier' => 'Supplier', 'notes' => 'Notes']"
        :created-by="$pv->created_by" :created-at="$pv->created_at?->toDateTimeString()" :updated-by="$pv->updated_by" :updated-at="$pv->updated_at?->toDateTimeString()">
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No. SPB</span><b>{{ $pv->doc_no }}</b></div>
            <x-dl::field.date name="i_doc_date" label="Tanggal Terima" :value="$pv->i_doc_date?->toDateString()" :source="Src::of('tm2pur.pv_receipt.i_doc_date')" />
            <x-dl::field.display label="Gudang Penerima" :value="$pv->i_warehouse_code" :source="Src::of('tm2pur.pv_receipt.i_warehouse_code')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Nilai Diterima</span><b>{{ $money($value) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="i_supplier_do_no" label="No. Surat Jalan Supplier" :case-toggle="false" :value="$pv->i_supplier_do_no" :source="Src::of('tm2pur.pv_receipt.i_supplier_do_no')" />
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
            <x-dl::field.text name="i_notes" label="Catatan penerimaan" multiline :value="$pv->i_notes" :source="Src::of('tm2pur.pv_receipt.i_notes')" />
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
                    ['key' => 'c_qty_accepted', 'label' => 'Qty Diterima (Partial)', 'type' => 'number', 'more' => true],
                    ['key' => 'c_qty_rejected', 'label' => 'Qty Ditolak (Partial)', 'type' => 'number', 'more' => true],
                    ['key' => 'variance', 'label' => 'Selisih vs PO', 'type' => 'display', 'more' => true],
                    ['key' => 'tol_override_reason', 'label' => 'Alasan Override Toleransi', 'more' => true],
                    ['key' => 'i_lot_no', 'label' => 'No. Lot', 'more' => true],
                    ['key' => 'i_location_code', 'label' => 'Lokasi Rak', 'more' => true],
                    ['key' => 'value', 'label' => 'Nilai Diterima', 'type' => 'display', 'more' => true],
                    ['key' => 'code', 'label' => 'Kode Item', 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        @php $more = PurchaseReceive::openLineOptions($pv->i_supplier_id); @endphp
                        @if ($more)
                            <x-dl::field.multi-select name="po_line_ids" label="+ Baris PO lain dari supplier ini" :options="$more" :source="Src::of('tm2pur.po_line (sisa > 0)')" />
                            <x-dl::button size="sm" variant="secondary" label="+ Tambah Baris PO" type="submit" name="action" value="add_lines" formnovalidate />
                        @endif
                    </x-slot:tools>
                @endif
            </x-dl::lines>
            <x-dl::doc-footer :words="\Devlib\Terbilang::words(round($value), 'IDR', 'id')"
                :summary="[['Baris', (string) $pv->lines->count()], ['Nilai Diterima', $money($value), 'grand']]" />
        </x-slot:detail>
    </x-dl::form>
@endif
