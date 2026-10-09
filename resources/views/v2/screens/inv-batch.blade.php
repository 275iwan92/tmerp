{{-- Inventory > Batch & Quality > Batch (v2.01, gambar docs/mockups/inv_v201_batch_grid.jpg + inv_v201_batch_form.jpg). Daftar = objek Grid BatchListGrid (DocListGrid);
     Open / + New Record = form master batch: nomor otomatis, status HANYA dibaca (berubah oleh keputusan QC), tab Stock of this batch (saldo per bin dari kartu stok + palet),
     Quality decisions, dan History (pergerakan kartu stok + perubahan status). Simpan = BatchLookup::save; Release / Reject = QcDecisionService lewat route v2.iq.decide. --}}
@php
    use App\Models\Inventory\InvBatch;
    use App\Services\Inventory\QcAuthority;
    use App\Support\V2\Data\Batches;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'inv-batch');
    $batch = request('id') ? Batches::find((int) request('id')) : null;
    abort_if(request('id') && ! $batch, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $batch && ! request('new'))
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Inv\BatchListGrid]])
@else
    @php
        $new = ! $batch;
        $b = $batch ?? new InvBatch(['stock_class' => 'RM', 'certification' => 'NONE', 'qc_status' => 'RELEASED']);
        $status = InvBatch::STATUSES[$b->qc_status]['label'] ?? $b->qc_status;
        $stock = $new ? [] : Batches::stock($b);
        $canDecide = ! $new && QcAuthority::canDecide(auth()->user());
        $classes = ['RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];
        $stockRows = array_map(fn (array $r) => ['warehouse' => $r['warehouse'], 'bin' => $r['bin'], 'pallet' => $r['pallet'], 'owner' => t($r['owner']), 'arrived' => Locale::d($r['arrived']) ?? '—',
            'qty' => Locale::decimal($r['qty'], 2), 'unit' => $r['unit']], $stock);
        $decisions = $new ? [] : Batches::decisions($b);
        $firstArticles = $new ? [] : Batches::firstArticles($b);
    @endphp
    <x-dl::form id="inv-batch-form" :title="$new ? t('New Batch') : 'Batch · '.raw($b->batch_no)" subtitle="Inventory · Batch & Quality"
        panel="top" kind="master" :status="$new ? 'new' : 'active'" :stage="$new ? null : $status" :hide="['deactivate', 'activate', 'new']"
        :action="route('v2.iq.batch-save', $new ? [] : ['id' => $b->id_batch])" action-field="action" :back="$home" :new-url="$home.'?new=1'"
        :tabs="['general' => 'Batch', 'stock' => 'Stock of this batch', 'quality' => 'Quality decisions']"
        :log="$new ? [] : Batches::history($b)"
        :created-by="$b->created_by" :created-at="$b->created_at?->toDateTimeString()" :updated-by="$b->updated_by" :updated-at="$b->updated_at?->toDateTimeString()">
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Batch No." :value="$new ? t('(automatic)') : raw($b->batch_no)" :source="Src::of('tm2inv.inv_batch.batch_no')" />
                @if ($new)
                    <x-dl::field.select name="stock_class" label="Class" required :options="$classes" :value="old('stock_class', 'RM')" :source="Src::of('tm2inv.inv_batch.stock_class')" />
                    <x-dl::field.text name="item_code" label="Item Code" required :case-toggle="false" :value="old('item_code')" :source="Src::of('tm2mst.mat_raw')" />
                @else
                    <x-dl::field.display label="Item" :value="raw($b->item_name.' · '.$b->item_code)" :source="Src::of('tm2inv.inv_batch.item_name', 'item_code')" />
                @endif
                <x-dl::field.select name="supplier_id" label="Supplier" :options="Batches::supplierOptions()" :value="old('supplier_id', $b->supplier_id)" :source="Src::of('tm2mst.sup_supplier')" />
                <x-dl::field.text name="supplier_lot" label="Supplier Lot" :value="old('supplier_lot', $b->supplier_lot)" :source="Src::of('tm2inv.inv_batch.supplier_lot')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="production_date" label="Production Date" :value="old('production_date', $b->production_date?->toDateString())" :source="Src::of('tm2inv.inv_batch.production_date')" />
                <x-dl::field.date name="expiry_date" label="Expiry Date" :value="old('expiry_date', $b->expiry_date?->toDateString())" :source="Src::of('tm2inv.inv_batch.expiry_date')" />
                <x-dl::field.date name="retest_date" label="Retest Date" :value="old('retest_date', $b->retest_date?->toDateString())" :source="Src::of('tm2inv.inv_batch.retest_date')" />
                <x-dl::field.display label="Status" :value="t($status)" hint="Set by the quality decision, not typed." :source="Src::of('tm2inv.inv_batch.qc_status')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="certification" label="Certification" :options="InvBatch::CERTIFICATIONS" :value="old('certification', $b->certification)" :source="Src::of('tm2inv.inv_batch.certification')" />
                <x-dl::field.checkbox name="food_grade" label="Food Grade" text="Food grade" :value="(bool) old('food_grade', $b->food_grade)" :source="Src::of('tm2inv.inv_batch.food_grade')" />
                <x-dl::field.select name="grain_direction" label="Grain Direction" :options="InvBatch::GRAINS" :value="old('grain_direction', $b->grain_direction)" hint="Only for paper." :source="Src::of('tm2inv.inv_batch.grain_direction')" />
                <x-dl::field.display label="Certificate of Analysis" :value="$b->coa_path ? raw(basename($b->coa_path)) : '—'" :source="Src::of('tm2inv.inv_batch.coa_path')" />
            </x-dl::form.row>
            <x-dl::field.text name="notes" label="Notes" multiline :value="old('notes', $b->notes)" :source="Src::of('tm2inv.inv_batch.notes')" />
        </x-slot:general>
        <x-slot:stock>
            @if ($new)
                <x-dl::alert tone="info" message="Save the batch first. Its stock appears here once goods of this batch are received." />
            @else
                <x-dl::table :columns="['warehouse' => ['Warehouse', 'raw' => true], 'bin' => ['Location', 'raw' => true], 'pallet' => ['Pallet', 'raw' => true], 'owner' => ['Owner', 'raw' => true], 'arrived' => ['Arrived', 'raw' => true],
                    'qty' => ['Qty', 'num' => true, 'raw' => true], 'unit' => ['Unit', 'raw' => true]]" :rows="$stockRows" empty="No stock recorded for this batch yet." :source="Src::of('tm2inv.'.strtolower($b->stock_class).'_stockcard', 'tm2inv.inv_pallet')" />
                <x-dl::field.display label="Total on hand" :value="raw(Locale::decimal(array_sum(array_column($stock, 'qty')), 2).' '.($stock[0]['unit'] ?? ''))" :source="Src::of('tm2inv.'.strtolower($b->stock_class).'_stockcard')" />
                <x-dl::alert tone="info" message="Pallets are managed in Warehouse; the batch only shows them." />
            @endif
        </x-slot:stock>
        <x-slot:quality>
            @if ($new)
                <x-dl::alert tone="info" message="Quality decisions appear here after the batch is saved." />
            @else
                <x-dl::table :columns="['at' => 'When', 'type' => 'Inspection', 'decision' => 'Decision', 'sample' => 'Sample / Defective', 'result' => 'Test Result', 'reason' => 'Reason', 'by' => 'By']" :rows="$decisions" empty="No decision yet." :source="Src::of('tm2inv.inv_qc_decision')" />
                <x-dl::table :columns="['at' => 'When', 'wo' => 'Work Order', 'result' => 'First Article', 'notes' => 'Notes', 'by' => 'By']" :rows="$firstArticles" empty="No first article yet." :source="Src::of('tm2inv.inv_qc_first_article')" />
            @endif
        </x-slot:quality>
    </x-dl::form>

    @if ($canDecide)
        <x-dl::action-form action="{{ route('v2.iq.decide', $b->id_batch) }}?from=inv-batch">
            <x-dl::card title="Quality Decision" description="Release makes the stock normal. Reject holds the work orders that used it and opens a supplier claim; a reason is required.">
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="decision_type" label="Inspection" required :options="\App\Support\V2\Data\BatchQuality::typeOptions()" :value="$b->qc_status === 'CONDITIONAL' ? 'SAMPLING_DOCK' : 'USAGE_DECISION'" :source="Src::of('tm2inv.inv_qc_decision.decision_type')" />
                    <x-dl::field.text name="reason" label="Reason" :case-toggle="false" :source="Src::of('tm2inv.inv_qc_decision.reason')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="danger" icon="fa-circle-xmark" label="Reject" type="submit" name="decision" value="REJECTED" formnovalidate />
                    <x-dl::button icon="fa-circle-check" label="Release" type="submit" name="decision" value="RELEASED" formnovalidate />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @endif
@endif
