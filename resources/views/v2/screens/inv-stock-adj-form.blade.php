{{--
 REMARK
 Tujuan       : Form Stock Adjustment dan Stock Opname v2 (menggantikan halaman v1 pages::inventory.stock-adjustment) untuk RM, FG, SFG: kartu Start (pilih jenis), header (tanggal, gudang, alasan, catatan; Opname: petugas, mulai, selesai, saksi, cakupan penuh), baris (item, bin, saldo sistem, hitung fisik, selisih, nilai selisih, Description), Save = Draft, Submit = ajukan approval, Cancel Draft, tarik semua item bersaldo (Opname cakupan penuh), Duplicate, tab History (dokumen + keputusan approval).
 Sumber hulu  : tm2inv.rm_stock + rm_stock_d (FG: fg_stock, SFG: sfg_stock; doc_cat AD atau OP), Kartu Stok (saldo sistem dan harga dibaca server saat Save), tm2mst.wh_warehouse / wh_loc, flow_config AD / OP dan flow_log; aturan di App\Services\Inventory\StockAdjustmentService.
 Input        : Query class (RM / FG / SFG) dan id (dokumen) atau new=1 (&type=AD|OP setelah kartu Start); session v2_flash dan errors; old input (hasil tarik cakupan penuh).
 Output       : POST v2.inv.stockadj.save (Save / Submit / Cancel / Pull / Duplicate) dan v2.inv.stockadj.start (kartu Start).
 Dipakai oleh : Dibuka dari grid inv-*-stock-adj (Open dan + New Record) dan dari Approval Center Adjustment & Opname (tautan Buka).
 Status       : Aktif (Master T5d). Keputusan Approve / Reject / Hold tetap di Approval Center; form ini hanya-baca setelah Submit. Selisih dan nilai selisih dihitung langsung di layar dari saldo sistem yang terakhir tersimpan; saldo dan harga dibaca ulang dari Kartu Stok setiap Save. Dokumen buatan Count Task hanya-baca.
--}}
@php
    use App\Models\Inventory\FgStock;
    use App\Models\Inventory\RmStock;
    use App\Models\Inventory\SfgStock;
    use App\Services\Inventory\StockAdjustmentService as Sa;
    use App\Services\Inventory\StockDocEntryService;
    use App\Support\V2\Data\ApprovalCenter;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $class = strtoupper((string) request('class', 'RM'));
    abort_unless(Sa::supports($class), 404);
    $model = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class][$class];
    $table = ['RM' => 'rm_stock', 'FG' => 'fg_stock', 'SFG' => 'sfg_stock'][$class];
    $svc = app(Sa::class);
    $home = route('v2.screen', 'inv-'.strtolower($class).'-stock-adj');
    $doc = request('id') ? $model::with('lines')->find((int) request('id')) : null;
    abort_if(request('id') && (! $doc || ! Sa::ownsDocument($doc)), 404);
    abort_unless($doc || request('new'), 404);
    $new = ! $doc;
    $kind = $new ? (string) request('type', '') : $doc->doc_cat;
    $start = $new && ! isset(Sa::KINDS[$kind]);
    $doc ??= new $model(['doc_date' => now(), 'doc_cat' => $kind ?: 'AD', 'flow_status' => 'DRAFT']);
    $op = $kind === 'OP';
    [$state, $stage] = $new ? ['new', null] : $svc->formState($doc);
    $editable = $new || $svc->editable($doc);
    $user = Auth::user();
    $s = fn (string $col) => Src::of('tm2inv.'.$table.'.'.$col);
    $v = fn (string $col, $default = null) => old($col, $default);
    $oldLines = old('lines');
    $rows = $svc->rows($class, $new ? null : $doc, is_array($oldLines) ? $oldLines : null);
    $abs = $new ? 0.0 : $svc->absoluteValue($doc);
    $appr = ! $new && $state === 'approval' ? ApprovalCenter::approvalState($kind, (int) $doc->doc_id, $abs) : ['levels' => 0, 'at' => 0];
    $warehouses = app(StockDocEntryService::class)->warehouseOptions();
    $columns = [
        ['key' => 'item_id', 'label' => 'Item', 'type' => 'lookup', 'lookup' => 'stock_item_'.strtolower($class), 'required' => true, 'width' => '280px'],
        ['key' => 'location_id', 'label' => 'Bin', 'type' => 'lookup', 'lookup' => 'stock_return_bin', 'required' => true, 'width' => '170px'],
        ['key' => 'system', 'label' => 'System', 'type' => 'number', 'formula' => 'qty_system * 1', 'width' => '100px'],
        ['key' => 'physical', 'label' => 'Counted', 'type' => 'number', 'required' => true, 'width' => '100px'],
        ['key' => 'variance', 'label' => 'Difference', 'type' => 'number', 'formula' => 'physical - qty_system', 'sum' => true, 'width' => '100px'],
        ['key' => 'value', 'label' => 'Difference value (IDR)', 'type' => 'money', 'formula' => '(physical - qty_system) * unit_price', 'sum' => true, 'width' => '140px'],
        ['key' => 'line_note', 'label' => 'Description (required when the count differs)', 'type' => 'text', 'width' => '230px'],
        ['key' => 'qty_system', 'label' => 'System balance at last Save', 'type' => 'display', 'more' => true],
        ['key' => 'unit_price', 'label' => 'Average price at last Save (IDR)', 'type' => 'display', 'more' => true],
        ['key' => 'uom_code', 'label' => 'Unit', 'type' => 'text', 'more' => true],
        ['key' => 'lot_no', 'label' => 'Lot text', 'type' => 'text', 'more' => true],
    ];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($start)
    <x-dl::action-form action="{{ route('v2.inv.stockadj.start', [$class]) }}">
        <x-dl::card title="Start a correction" description="Choose Adjustment for a single correction, or Stock Opname for a scheduled physical count. The type cannot change after the first Save.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="type" label="Type" required :options="['AD' => t('Adjustment (single correction)'), 'OP' => t('Stock Opname (scheduled physical count)')]" :value="request('type', 'AD')" :source="$s('doc_cat')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Start" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
@if (! $new && ($doc->ref_doc_type ?: 'MANUAL') !== 'MANUAL')
    <x-dl::alert tone="info" message="This document was created by a Count Task, so it is read-only here." />
@elseif ($state === 'approval')
    <x-dl::alert tone="info" message="This document is waiting for approval. The decision is made in the Adjustment and Opname Approval Center; the form is read-only until then." />
@elseif (! $new && $doc->flow_status === 'REVISION_NEEDED')
    <x-dl::alert tone="warning" message="The approver returned this document for revision: correct it, then Submit again. The reason is in the History tab." />
@endif

<x-dl::form id="inv-stock-adj-form" :title="($new ? 'New ' : '').Sa::KINDS[$kind]['title']" :subtitle="raw(t(StockDocEntryService::CLASSES[$class]).($new ? '' : ' · '.$doc->doc_no))"
    panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="$state === 'approval'" :approval-levels="$state === 'approval' ? max(1, $appr['levels']) : 0" :approval-at="$appr['at']" :can-input="$editable"
    :action="route('v2.inv.stockadj.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['delete', 'cancel', 'email', 'hold', 'print', 'confirm']"
    :hidden="['stock_class' => $class, 'doc_kind' => $kind] + ($new ? [] : ['id' => $doc->doc_id])" :tabs="['general' => t('Document')]" :log="$new ? [] : $svc->history($doc)"
    :created-by="$doc->create_by" :created-at="$doc->create_log?->toDateTimeString()" :updated-by="$doc->update_by" :updated-at="$doc->update_log?->toDateTimeString()">
    <x-slot:special>
        @if ($editable && ! $new)
            <x-dl::button type="submit" name="action" value="cancel" variant="secondary" icon="fa-ban" label="Cancel" formnovalidate data-dl-confirm="{{ t('Cancel this draft?') }}" />
        @endif
        @if ($editable && ! $new)
            <x-dl::button type="submit" name="action" value="submit" variant="primary" icon="fa-paper-plane" label="Submit" data-dl-confirm="{{ t('Submit this document for approval? The lines cannot be edited while it is in approval.') }}" />
        @endif
        @if ($editable && $op)
            <x-dl::button type="submit" name="action" value="pull" variant="secondary" icon="fa-download" label="Pull all items with a balance" formnovalidate data-dl-confirm="{{ t('Pull every item with a balance in this warehouse? The lines now on the form are replaced.') }}" />
        @endif
        @if (! $new && $state === 'confirmed')
            <x-dl::button type="submit" name="action" value="duplicate" variant="secondary" icon="fa-copy" label="Duplicate" formnovalidate />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Document no." :value="$new ? t('(on Save)') : raw($doc->doc_no)" />
        <x-dl::strip-kv kind="grand" label="Lines" :value="(string) count($rows)" :value-attrs="['data-l-val' => 'lines:count']" />
        <x-dl::strip-kv kind="grand" label="Net difference qty" :value="Locale::number(collect($rows)->sum(fn ($r) => (float) Locale::input($r['physical'] ?? 0) - (float) $r['qty_system']), 3)" :value-attrs="['data-l-val' => 'lines:sum.variance']" />
        <x-dl::strip-kv kind="grand" label="Net difference value (IDR)" :value="Locale::number(collect($rows)->sum(fn ($r) => ((float) Locale::input($r['physical'] ?? 0) - (float) $r['qty_system']) * (float) $r['unit_price']), 0)" :value-attrs="['data-l-val' => 'lines:sum.value']" />
        @unless ($new)
            <x-dl::strip-kv kind="grand" label="Approval value (absolute, IDR)" :value="Locale::number($abs, 0)" />
        @endunless
    </x-slot:strip>
    <x-slot:general>
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="doc_date" label="Date" required :value="$v('doc_date', $doc->doc_date?->toDateString())" :source="$s('doc_date')" />
            <x-dl::field.display label="Type" :value="raw(t(Sa::KINDS[$kind]['title']))" :source="$s('doc_cat')" />
            <x-dl::field.select name="warehouse_id" label="Warehouse" required placeholder="Choose" :options="$warehouses" :value="$v('warehouse_id', $doc->warehouse_id)" :source="$s('warehouse_id')" />
            <x-dl::field.select name="reason_code" label="Reason" required placeholder="Choose" :options="array_map('t', $svc->reasonOptions($doc->reason_code))" :value="$v('reason_code', $doc->reason_code)" :source="$s('reason_code')" />
        </x-dl::form.row>
        @if ($op)
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="opn_officer" label="Count officer" required :value="$v('opn_officer', $doc->opn_officer)" :source="$s('opn_officer')" />
                <x-dl::field.datetime name="opn_start" label="Count start" required :value="$v('opn_start', $doc->opn_start?->format('Y-m-d H:i'))" :source="$s('opn_start')" />
                <x-dl::field.datetime name="opn_stop" label="Count stop" required :value="$v('opn_stop', $doc->opn_stop?->format('Y-m-d H:i'))" :source="$s('opn_stop')" />
                <x-dl::field.text name="opn_witness" label="Witness" required :value="$v('opn_witness', $doc->opn_witness)" :source="$s('opn_witness')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.checkbox name="full_scope" label="Full scope (every item with a balance)" :value="(bool) $v('full_scope', $doc->full_scope)" :source="$s('full_scope')" />
            </x-dl::form.row>
        @endif
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Submitted by" :value="raw($new ? ($user->name ?: $user->login) : ($doc->create_by ?: '—'))" :source="$s('create_by')" />
        </x-dl::form.row>
        <x-dl::field.text name="notes" label="Notes / chronology" multiline :value="$v('notes', $doc->notes)" :source="$s('notes')" />
    </x-slot:general>
    <x-slot:detail>
        <x-dl::lines name="lines" title="Items" add-label="+ Add line" :min-rows="1" :rows="$rows ?: [['item_id' => '', 'item_id_label' => '', 'location_id' => '', 'location_id_label' => '', 'qty_system' => 0, 'unit_price' => 0, 'physical' => '', 'line_note' => '', 'uom_code' => '', 'lot_no' => '']]"
            :readonly="! $editable" source="{{ Src::of('tm2inv.'.$table.'_d') }}" :columns="$columns" />
    </x-slot:detail>
</x-dl::form>
@endif
