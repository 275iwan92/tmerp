{{--
 REMARK
 Tujuan       : Form dokumen stok v2 (menggantikan halaman v1 pages::inventory.stock-in dan stock-out) untuk Stock In (RM, FG, SFG) dan Stock Out (RM, SFG): header, baris item (item, batch, bin, qty, satuan; Stock In juga harga dan batch baru), Save = Draft, Confirm = posting ke Kartu Stok, Cancel (Draft atau dokumen Posted dengan alasan dan dokumen pembalik), Duplicate.
 Sumber hulu  : tm2inv.rm_stock + rm_stock_d (FG: fg_stock, SFG: sfg_stock), inv_batch, tm2mst.wh_warehouse / wh_loc / org_department / item master; aturan di App\Services\Inventory\StockDocEntryService, StockPostingService, StockReversalService.
 Input        : Query kind (in / out), class (RM / FG / SFG) dan id (dokumen) atau new=1; session v2_flash dan errors.
 Output       : POST v2.inv.stockdoc.save (Save / Confirm / Cancel / Duplicate) dan v2.inv.stockdoc.reverse (dialog Cancel Document).
 Dipakai oleh : Dibuka dari grid inv-*-stock-in dan inv-*-stock-out (Open dan + New Record).
 Status       : Aktif. Nomor dokumen terbit saat Save; Posted tidak pernah diedit (koreksi = Cancel dengan pembalik atau Duplicate). Stock In: strip memuat total qty dan total nilai (qty x harga satuan); Stock Out tanpa harga (nilai = rata-rata berjalan saat posting).
--}}
@php
    use App\Models\Inventory\FgStock;
    use App\Models\Inventory\RmStock;
    use App\Models\Inventory\SfgStock;
    use App\Services\Inventory\StockDocEntryService as Sd;
    use App\Services\Inventory\StockReversalService;
    use App\Support\V2\Data\StockEntry;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $kind = (string) request('kind', 'in');
    $class = strtoupper((string) request('class', 'RM'));
    abort_unless(Sd::supports($kind, $class), 404);
    $cfg = Sd::KINDS[$kind];
    $out = $kind === 'out';
    $model = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class][$class];
    $table = ['RM' => 'rm_stock', 'FG' => 'fg_stock', 'SFG' => 'sfg_stock'][$class];
    $svc = app(Sd::class);
    $home = route('v2.screen', 'inv-'.strtolower($class).'-stock-'.$kind);
    $doc = request('id') ? $model::with('lines')->find((int) request('id')) : null;
    abort_if(request('id') && (! $doc || ! Sd::ownsDocument($kind, $doc)), 404);
    abort_unless($doc || request('new'), 404);
    $new = ! $doc;
    $doc ??= new $model(['doc_date' => now(), 'doc_cat' => 'STOCK', 'flow_status' => 'DRAFT']);
    [$state, $stage] = $new ? ['new', null] : $svc->formState($doc);
    $editable = $new || $svc->editable($doc);
    $rv = $new ? null : app(StockReversalService::class)->state($class, $doc->doc_id);
    $user = Auth::user();
    $s = fn (string $col) => Src::of('tm2inv.'.$table.'.'.$col);
    $v = fn (string $col, $default = null) => old($col, $default);
    $depts = $out ? $svc->departmentOptions() : [];
    $blank = ['item_id' => '', 'item_id_label' => '', 'batch_id' => '', 'batch_id_label' => '', 'location_id' => '', 'location_id_label' => '', 'qty' => '', 'uom_code' => '', 'unit_price' => '', 'lot_no' => '', 'new_lot' => '', 'new_production' => '', 'new_expiry' => ''];
    $rows = old('lines') ? StockEntry::withLabels($class, old('lines')) : StockEntry::rows($class, $doc);
    $rows = $rows ?: [$blank];
    $qty = (float) collect($rows)->sum(fn ($r) => (float) (Locale::input($r['qty'] ?? 0) ?? 0));
    $value = (float) collect($rows)->sum(fn ($r) => (float) (Locale::input($r['qty'] ?? 0) ?? 0) * (float) (Locale::input($r['unit_price'] ?? 0) ?? 0));
    $filled = collect($rows)->filter(fn ($r) => ! empty($r['item_id']))->count();
    $columns = [
        ['key' => 'item_id', 'label' => 'Item', 'type' => 'lookup', 'lookup' => 'stock_item_'.strtolower($class), 'required' => true, 'width' => '300px'],
        ['key' => 'batch_id', 'label' => 'Batch', 'type' => 'lookup', 'lookup' => 'stock_batch', 'width' => '150px'],
        ['key' => 'location_id', 'label' => $out ? 'Source bin' : 'Bin', 'type' => 'lookup', 'lookup' => 'stock_bin', 'required' => true, 'width' => '190px'],
        ['key' => 'qty', 'label' => $out ? 'Qty out' : 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '100px'],
        ['key' => 'uom_code', 'label' => 'Unit', 'type' => 'text', 'width' => '70px'],
    ];
    if (! $out) {
        $columns[] = ['key' => 'unit_price', 'label' => 'Unit price (IDR)', 'type' => 'money', 'width' => '130px'];
        $columns[] = ['key' => 'value', 'label' => 'Value (IDR)', 'type' => 'money', 'formula' => 'qty * unit_price', 'sum' => true, 'width' => '130px'];
    }
    $columns[] = ['key' => 'lot_no', 'label' => 'Lot text (documents saved before batches; leave empty otherwise)', 'type' => 'text', 'more' => true];
    if (! $out) {
        $columns[] = ['key' => 'new_lot', 'label' => 'New batch: supplier lot', 'type' => 'text', 'more' => true];
        $columns[] = ['key' => 'new_production', 'label' => 'New batch: production date', 'type' => 'date', 'more' => true];
        $columns[] = ['key' => 'new_expiry', 'label' => 'New batch: expiry date (required when the item has a shelf life)', 'type' => 'date', 'more' => true];
    }
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
@if ($rv && $rv['cancelled'])
    <x-dl::alert tone="danger" :message="raw(t('Cancelled').' · '.$rv['reason'].($rv['reversal'] ? ' · '.t('reversed by').' '.$rv['reversal']['no'] : '').' · '.t('correct document: use Duplicate'))" />
@elseif ($rv && $rv['reverses'])
    <x-dl::alert tone="info" :message="raw(t('Reversal of').' '.$rv['reverses']['no'])" />
@elseif ($rv && ! $rv['can'] && $rv['refusal'] && $doc->flow_status === 'POSTED')
    <x-dl::alert tone="info" :message="raw($rv['refusal'])" />
@endif
@if (! $new && ! $editable && $state === 'draft')
    <x-dl::alert tone="warning" message="This draft was marked confirmed without being posted, so it cannot be posted, edited or cancelled from the screen." />
@endif

<x-dl::form id="inv-stock-doc-form" :title="($new ? 'New ' : '').$cfg['title']" :subtitle="raw(t(Sd::CLASSES[$class]).($new ? '' : ' · '.$doc->doc_no))"
    panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="false" :can-input="$editable"
    :action="route('v2.inv.stockdoc.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['delete', 'cancel', 'email', 'hold', 'print']"
    :hidden="['stock_kind' => $kind, 'stock_class' => $class] + ($new ? [] : ['id' => $doc->doc_id])" :tabs="['general' => t('Document')]" :log="$new ? [] : $svc->history($doc)"
    :created-by="$doc->create_by" :created-at="$doc->create_log?->toDateTimeString()" :updated-by="$doc->update_by" :updated-at="$doc->update_log?->toDateTimeString()">
    <x-slot:special>
        @if (! $new && $editable)
            <x-dl::button type="submit" name="action" value="cancel" variant="secondary" icon="fa-ban" label="Cancel" formnovalidate data-dl-confirm="{{ t('Cancel this draft?') }}" />
        @endif
        @if ($rv && $rv['can'])
            <x-dl::button type="button" variant="secondary" icon="fa-ban" label="Cancel" data-dl-open="stk-reverse" />
        @endif
        @if ($rv && $state === 'confirmed' && ($rv['can'] || $rv['cancelled']))
            <x-dl::button type="submit" name="action" value="duplicate" variant="secondary" icon="fa-copy" label="Duplicate" formnovalidate />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Document no." :value="$new ? t('(on Save)') : raw($doc->doc_no)" />
        <x-dl::strip-kv kind="grand" label="Lines" :value="(string) $filled" :value-attrs="['data-l-val' => 'lines:count']" />
        <x-dl::strip-kv kind="grand" :label="$out ? 'Total qty out' : 'Total qty'" :value="Locale::number($qty, 3)" :value-attrs="['data-l-val' => 'lines:sum.qty']" />
        @unless ($out)
            <x-dl::strip-kv kind="grand" label="Total value (IDR)" :value="Locale::number($value, 2)" :value-attrs="['data-l-val' => 'lines:sum.value']" />
        @endunless
    </x-slot:strip>
    <x-slot:general>
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="doc_date" label="Date" required :value="$v('doc_date', $doc->doc_date?->toDateString())" :source="$s('doc_date')" />
            @if ($out)
                <x-dl::field.select name="warehouse_id" label="Source warehouse" required placeholder="Choose" :options="$svc->warehouseOptions()" :value="$v('warehouse_id', $doc->warehouse_id)" :source="$s('warehouse_id')" />
                <x-dl::field.select name="issued_to_dept" label="Issued to (department)" required placeholder="Choose" :options="$depts" :value="$v('issued_to_dept', $doc->issued_to_dept)" :source="$s('issued_to_dept')" />
                <x-dl::field.text name="process_code" label="Target process" placeholder="e.g. Offset printing, Lamination" :value="$v('process_code', $doc->process_code)" :source="$s('process_code')" />
            @else
                <x-dl::field.select name="doc_cat" label="Category" required :options="$cfg['cats']" :value="$v('doc_cat', $doc->doc_cat)" :source="$s('doc_cat')" />
                <x-dl::field.select name="warehouse_id" label="Warehouse" required placeholder="Choose" :options="$svc->warehouseOptions()" :value="$v('warehouse_id', $doc->warehouse_id)" :source="$s('warehouse_id')" />
                <x-dl::field.display label="Received by" :value="raw($new ? ($user->name ?: $user->login) : ($doc->create_by ?: '—'))" :source="$s('create_by')" />
            @endif
        </x-dl::form.row>
        @if ($out)
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Issued by" :value="raw($new ? ($user->name ?: $user->login) : ($doc->create_by ?: '—'))" :source="$s('create_by')" />
            </x-dl::form.row>
        @endif
        <x-dl::field.text name="notes" label="Notes" multiline :value="$v('notes', $doc->notes)" :source="$s('notes')" />
    </x-slot:general>
    <x-slot:detail>
        <x-dl::lines name="lines" title="Items" add-label="+ Add line" :min-rows="1" :rows="$rows" :readonly="! $editable" source="{{ Src::of('tm2inv.'.$table.'_d') }}" :columns="$columns" />
    </x-slot:detail>
</x-dl::form>

@if ($rv && $rv['can'])
    <x-dl::modal id="stk-reverse" title="Cancel Document" icon="fa-ban" size="md">
        <x-dl::action-form action="{{ route('v2.inv.stockdoc.reverse', [$kind, $class, $doc->doc_id]) }}">
            <x-dl::field.text name="reason" label="Reason" required multiline :value="$rv['default_reason']" :source="$s('cancel_reason')" />
            <x-dl::alert tone="info" message="The system posts a reversal document in the opposite direction through the normal posting path, so the Stock Card stays continuous. This document becomes Cancelled and keeps pointing to its reversal. It is refused when the date falls in a closed inventory period, or when the reversal would make a balance negative." />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" label="Cancel Document" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>
@endif
