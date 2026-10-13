{{--
 REMARK
 Tujuan       : Purchase Order: GRID PO, buat draft per supplier, tarik baris dari PR Approved, Confirm (nomor PO dan antrian approval), Approve/Reject/Hold, kirim ke supplier, Closed saat semua barang diterima.
 Sumber hulu  : App\Support\V2\Data\PurchaseOrder (flow dan aturan pages/pur/po v1); model Pur\PoOrder; grid App\Support\V2\Grid\Pur\PurchaseOrderGrid.
 Input        : Query id (PO), new (PO baru); session v2_flash dan error validasi.
 Output       : Daftar lewat _dbgrid, form pilih supplier (rute v2.po.create) atau form PO x-dl::* dengan aksi approval dan kirim.
 Dipakai oleh : Layar v2 slug purchase-order lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Models\Pur\PoOrder;
    use App\Support\V2\Data\PurchaseOrder;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'purchase-order');
    $po = request('id') ? PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->find((int) request('id')) : null;
    abort_if(request('id') && ! $po, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $po && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Pur\PurchaseOrderGrid (DbGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => (new \App\Support\V2\Grid\Pur\PurchaseOrderGrid)->screen()])
@elseif (! $po)
    <x-dl::action-form action="{{ route('v2.po.create') }}">
        <x-dl::card title="+ New Record — Purchase Order" :description="raw(tf('Regular PO: choose the supplier (its open draft PO is reused). Cash PO: paid from Petty Cash, max. IDR :max, approved by the Finance Director.', ['max' => \Devlib\Locale::number(PurchaseOrder::maxCash(), 0)]))">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="po_kind" label="PO Kind" required :options="['NORMAL' => t('Regular PO (credit / terms)'), 'CASH' => t('Cash PO (Petty Cash)')]" :value="request('kind', 'NORMAL')" :source="Src::of('tm2pur.po_order.i_procure_type')" />
                <x-dl::field.select name="supplier_id" label="Supplier" required :options="PurchaseOrder::suppliers()" :value="request('kind') === 'CASH' ? PurchaseOrder::cashSupplierId() : null" hint="Cash PO to a retail shop: choose TOKO KONTAN and type the shop name below." :source="Src::of('tm2mst.sup_supplier')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="cash_mode" label="Cash PO Mode" :options="array_map('t', PurchaseOrder::CASH_MODES)" value="ADVANCE" :source="Src::of('tm2pur.po_order.i_cash_mode')" />
                <x-dl::field.text name="shop_name" label="Shop Name (Cash PO)" placeholder="e.g. Toko Maju Jaya" :source="Src::of('tm2pur.po_order.i_shop_name')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create PO" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    {{-- Form Master-Detail + Approval DevLib (disetujui user 2026-09-30). --}}
    @php
        $pending = PurchaseOrder::pendingFor($po->flowModule(), $po->po_id);
        $log = PurchaseOrder::flowLog($po->flowModule(), $po->po_id);
        $held = $po->status === 'SUBMITTED' && strtoupper((string) ($log[0]['action'] ?? '')) === 'HOLD';
        $approved = collect($log)->where('action', 'Approved')->count();
        // Jumlah level ikut jalur flow engine untuk nilai dokumen ini (mis. PO > 1 jt = 3 level) -- sama dgn Approval Center.
        $appr = PurchaseOrder::approvalState($po->flowModule(), $po->po_id, (float) $po->c_grand_total);
        $draft = $po->status === 'DRAFT';
        $sup = \Illuminate\Support\Facades\DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $po->i_supplier_id)->first();
        $money = fn ($v) => 'IDR '.\Devlib\Locale::number((float) $v, 0);
        $trim = fn ($v) => \Devlib\Locale::decimal($v, 2);
        $pqNo = \App\Models\Pur\PqLine::whereIn('pq_line_id', $po->lines->pluck('pq_line_id')->filter())->join('pq_quotation', 'pq_quotation.pq_id', '=', 'pq_line.pq_id')->pluck('pq_quotation.doc_no', 'pq_line.pq_line_id');
        $cash = $po->isCash();
        $expense = $cash ? PurchaseOrder::expenseAccounts() : [];
        $pettyStep = $cash ? \App\Services\Acc\PettyCashService::nextStep($po) : null;
        $lines = $po->lines->map(fn ($l) => [
            'po_line_id' => $l->po_line_id, 'i_description' => $l->i_description, 'i_qty' => (float) $l->i_qty, 'i_uom' => $l->i_uom,
            'i_price' => (float) $l->i_price, 'i_disc_pct' => (float) $l->i_disc_pct,
            'code' => $l->c_item_code ?: '—', 'account' => $l->i_coa_account_id ? ($expense[$l->i_coa_account_id] ?? '#'.$l->i_coa_account_id) : '—', 'class' => $l->i_line_class === 'SERVICE' ? t('Service (subject to PPh 23)') : t('Goods'), 'stock' => $l->is_stock ? t('Stock') : t('Non-stock'),
            'pq' => $pqNo[$l->pq_line_id] ?? '—', 'received' => $trim($l->c_qty_received).' '.$l->i_uom, 'outstanding' => $trim($l->c_qty_outstanding).' '.$l->i_uom, 'tol' => $trim($l->c_tol_pct).'%',
        ])->values()->all();
    @endphp
    <x-dl::form id="po-form" :title="($cash ? 'Cash PO · ' : 'Purchase Order · ').raw($po->doc_no ?: 'Draft #'.$po->po_id)" :subtitle="raw(($sup->supplier_name ?? '—').' · '.$po->status)"
        panel="top" kind="document" :status="PurchaseOrder::formStatus($po, $held)" :pipeline="true" :approval-levels="max(1, $appr['levels'])" :approval-at="$appr['at'] ?: $approved + 1"
        :can-approve="(bool) $pending" :log="$log"
        :action="route('v2.po.save', $po->po_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :print-url="\App\Support\V2\Print\PrintDocs::url('purchase-order', $po->po_id)" :hide="['cancel', 'duplicate']"
        :tabs="['general' => 'General', 'supplier' => 'Supplier', 'finance' => 'Finance', 'delivery' => 'Delivery', 'notes' => 'Notes']"
        :created-by="$po->created_by" :created-at="$po->created_at?->toDateTimeString()" :updated-by="$po->updated_by" :updated-at="$po->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if ($pettyStep)<x-dl::button size="sm" variant="success" icon="fa-wallet" :label="\App\Support\V2\Data\PettyCash::STEP_LABELS[$pettyStep]" :href="route('v2.screen', 'petty-cash').'?po='.$po->po_id" />@endif
            @if ($po->status === 'APPROVED' && ! $cash)<x-dl::button size="sm" variant="success" icon="fa-paper-plane" :label="t('Send to Supplier')" type="submit" name="action" value="send" formnovalidate :onclick="'return confirm('.json_encode(t('Mark this PO as sent to the supplier?')).')'" />@endif
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="PO No." :value="$po->doc_no ?: t('(on Confirm)')" />
            <x-dl::field.date name="i_doc_date" label="PO Date" :value="$po->i_doc_date?->toDateString()" :source="Src::of('tm2pur.po_order.i_doc_date')" />
            <x-dl::field.display label="Supplier" :value="($sup->supplier_name ?? '—').' ('.($sup->supplier_code ?? '—').')'" :source="Src::of('tm2mst.sup_supplier')" />
            <x-dl::strip-kv kind="grand" label="Net Payable" :value="$money($po->c_net_payable)" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="i_po_type" label="PO Type" :options="['LOCAL' => t('Local'), 'IMPORT' => t('Import')]" :value="$po->i_po_type" :source="Src::of('tm2pur.po_order.i_po_type')" />
                @if ($cash)
                    <x-dl::field.display label="Procurement Type" :value="t('Cash (Petty Cash)')" :source="Src::of('tm2pur.po_order.i_procure_type = CASH')" />
                @else
                    <x-dl::field.select name="i_procure_type" label="Procurement Type" :options="['REPEAT' => t('Repeat (routine)'), 'ONE_TIME' => t('One-Time'), 'CAPEX' => t('Capex')]" :value="$po->i_procure_type" :source="Src::of('tm2pur.po_order.i_procure_type')" />
                @endif
                <x-dl::field.display label="Revision no." :value="(string) ($po->revision_no ?? 0)" :source="Src::of('tm2pur.po_order.revision_no')" />
                <x-dl::field.display label="Sent to supplier" :value="\Devlib\Locale::dt($po->sent_at) ?? '—'" :source="Src::of('tm2pur.po_order.sent_at')" />
            </x-dl::form.row>
            @if ($cash)
                <x-dl::form.row :cols="4">
                    <x-dl::field.select name="i_cash_mode" label="Cash PO Mode" required :options="array_map('t', PurchaseOrder::CASH_MODES)" :value="$po->i_cash_mode" :source="Src::of('tm2pur.po_order.i_cash_mode')" />
                    <x-dl::field.select name="i_petty_cash_id" label="Petty Cash" required :options="PurchaseOrder::pettyCashOptions()" :value="$po->i_petty_cash_id" :source="Src::of('tm2acc.fin_petty_cash')" />
                    <x-dl::field.text name="i_shop_name" label="Shop Name" :value="$po->i_shop_name" :source="Src::of('tm2pur.po_order.i_shop_name')" />
                    <x-dl::field.display label="Cash PO Limit" :value="'IDR '.\Devlib\Locale::number(PurchaseOrder::maxCash(), 0).' (Constants PO_CASH_MAX)'" :source="Src::of('tm2sys.sys_constant.PO_CASH_MAX')" />
                </x-dl::form.row>
            @endif
        </x-slot:general>
        <x-slot:supplier>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Address" :value="$sup->default_address ?? '—'" :source="Src::of('tm2mst.sup_supplier.default_address')" />
                <x-dl::field.display label="Contact Person" :value="$sup->contact_name ?? '—'" :source="Src::of('tm2mst.sup_supplier.contact_name')" />
                <x-dl::field.display label="Phone" :value="$sup->phone ?? '—'" :source="Src::of('tm2mst.sup_supplier.phone')" />
                <x-dl::field.display label="Email" :value="$sup->email ?? '—'" :source="Src::of('tm2mst.sup_supplier.email')" />
            </x-dl::form.row>
        </x-slot:supplier>
        <x-slot:finance>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="i_payment_term" label="Payment Terms" :case-toggle="false" placeholder="e.g. Net 30" :value="$po->i_payment_term" :source="Src::of('tm2pur.po_order.i_payment_term')" />
                <x-dl::field.display label="Exchange Rate" :value="$trim($po->i_fx_rate ?? 1)" :source="Src::of('tm2pur.po_order.i_fx_rate')" />
            </x-dl::form.row>
        </x-slot:finance>
        <x-slot:delivery>
            <x-dl::form.row :cols="3">
                <x-dl::field.date name="i_delivery_date" label="Delivery Date" :value="$po->i_delivery_date?->toDateString()" :source="Src::of('tm2pur.po_order.i_delivery_date')" />
                <x-dl::field.text name="i_ship_to" label="Ship To" :value="$po->i_ship_to" :source="Src::of('tm2pur.po_order.i_ship_to')" />
            </x-dl::form.row>
        </x-slot:delivery>
        <x-slot:notes>
            <x-dl::field.text name="i_notes" label="Notes for the supplier" multiline :value="$po->i_notes" :source="Src::of('tm2pur.po_order.i_notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="po_line_id" :addable="false" :duplicable="false" row-lock="__never" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2pur.po_line') }}"
                :columns="[
                    ['key' => 'i_description', 'label' => 'Item', 'type' => 'display', 'width' => '320px'],
                    ['key' => 'i_qty', 'label' => 'Qty', 'type' => $cash ? 'number' : 'display', 'width' => '100px'],
                    ['key' => 'i_uom', 'label' => t('Unit'), 'type' => 'display', 'width' => '80px'],
                    ['key' => 'i_price', 'label' => t('Price'), 'type' => 'money', 'width' => '130px'],
                    ['key' => 'i_disc_pct', 'label' => 'Disc %', 'type' => 'number', 'width' => '80px'],
                    ['key' => 'net', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'i_qty * i_price * (1 - i_disc_pct / 100)', 'sum' => true, 'width' => '140px'],
                    ['key' => 'code', 'label' => t('Item Code'), 'type' => 'display', 'more' => true],
                    ['key' => 'account', 'label' => 'Expense Account (non-stock)', 'type' => 'display', 'more' => true],
                    ['key' => 'pq', 'label' => t('Price from PQ'), 'type' => 'display', 'more' => true],
                    ['key' => 'class', 'label' => t('Type'), 'type' => 'display', 'more' => true],
                    ['key' => 'stock', 'label' => 'Stock', 'type' => 'display', 'more' => true],
                    ['key' => 'tol', 'label' => 'Tolerance', 'type' => 'display', 'more' => true],
                    ['key' => 'received', 'label' => t('Received'), 'type' => 'display', 'more' => true],
                    ['key' => 'outstanding', 'label' => t('Remaining'), 'type' => 'display', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        @php $allocs = PurchaseOrder::allocationOptions($po); @endphp
                        @if ($cash)
                            <x-dl::field.lookup name="material_id" lookup="material" label="+ Item from Raw Material Master" placeholder="Type 2+ letters of item code / name..." :source="Src::of('tm2mst.mat_raw')" />
                            <x-dl::button size="sm" variant="secondary" label="+ Master Item" type="submit" name="action" value="add_material" formnovalidate />
                            <x-dl::field.text name="free_description" label="+ Free Item (non-stock)" placeholder="e.g. office printer ink" />
                            <x-dl::field.text name="free_uom" label="UoM" :case-toggle="false" placeholder="PCS" />
                            <x-dl::field.select name="free_account_id" label="Expense Account" placeholder="— choose account —" :options="$expense" :source="Src::of('tm2acc.fin_account (EXPENSE)')" />
                            <x-dl::button size="sm" variant="secondary" label="+ Free Item" type="submit" name="action" value="add_free" formnovalidate />
                        @endif
                        @if ($allocs)
                            <x-dl::field.select name="alloc_id" label="+ Pull lines from approved PR" :options="$allocs" :source="Src::of('tm2pur.pr_line_alloc (PR APPROVED, belum jadi PO)')" />
                            <x-dl::button size="sm" variant="secondary" :label="t('+ Pull PR Lines')" type="submit" name="action" value="add_alloc" formnovalidate />
                        @endif
                    </x-slot:tools>
                @endif
            </x-dl::lines>
            <x-dl::doc-footer
                :words="\Devlib\Terbilang::words(round((float) $po->c_net_payable), 'IDR', 'id')"
                :summary="array_values(array_filter([
                    [t('Tax Base (DPP)'), $money($po->c_dpp), 'dpp'],
                    [t('VAT'), $money($po->c_ppn)],
                    [t('Grand Total'), $money($po->c_grand_total)],
                    (float) $po->c_pph > 0 ? [t('PPh 23 (service, withheld)'), '− '.$money($po->c_pph)] : null,
                    [t('Net Payable'), $money($po->c_net_payable), 'grand'],
                ]))" />
        </x-slot:detail>
    </x-dl::form>
@endif
