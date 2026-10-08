{{-- Inventory > Batch & Quality > Conditional Stock (v2.01, Q-05..Q-09). GRID batch yang menunggu keputusan mutu (Conditional / Quarantine) -> buka satu batch ->
     rencana sampling untuk lotnya, keputusan (Release / Reject / Block / Conditional use + alasan), riwayat keputusan, pemakaian batch (telusur maju),
     WO yang ditahan, dan klaim supplier. Aturan di App\Services\Inventory\QcDecisionService / QcTraceService, data lewat App\Support\V2\Data\BatchQuality.
     Batch Rejected tetap bisa dibuka dari Supplier Claims atau Stock in Inspection. --}}
@php
    use App\Models\Inventory\InvBatch;
    use App\Services\Inventory\QcAuthority;
    use App\Support\V2\Data\BatchQuality;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'conditional-stock');
    $batch = request('id') ? BatchQuality::batch((int) request('id')) : null;
    abort_if(request('id') && ! $batch, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $batch)
    {{-- Daftar = objek Grid App\Support\V2\Grid\Inv\ConditionalStockListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Inv\ConditionalStockListGrid]])
@else
    @php
        $f = BatchQuality::facts($batch);
        $plan = $f['plan'];
        $meta = InvBatch::STATUSES[$batch->qc_status];
        $canDecide = QcAuthority::canDecide(auth()->user());
        $canFollowUp = QcAuthority::canFollowUp(auth()->user());
        $intensity = t(\App\Services\Inventory\QcSamplingService::INTENSITIES[$plan['intensity']] ?? $plan['intensity']);
    @endphp
    <x-dl::card :title="'Batch · '.raw($batch->batch_no)" :description="raw($batch->item_name.' · '.($batch->supplier_lot ?: 'NO-LOT').' · '.$f['supplier'])" icon="fa-solid fa-boxes-stacked">
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Status" :value="t($meta['label'])" :source="Src::of('tm2inv.inv_batch.qc_status')" />
            <x-dl::field.display label="Status Since" :value="$batch->qc_status_at ? raw(Locale::dt($batch->qc_status_at).' · '.$batch->qc_status_by) : '—'" :source="Src::of('tm2inv.inv_batch.qc_status_at', 'qc_status_by')" />
            <x-dl::field.display label="Hours Waiting" :value="in_array($batch->qc_status, ['CONDITIONAL', 'QUARANTINE'], true) ? raw(Locale::decimal($f['age']['hours'], 1).' · '.t(['OK' => 'On time', 'ESCALATE' => 'Escalate', 'OVERDUE' => 'Overdue'][$f['age']['state']])) : '—'" :source="Src::of('tm2sys.sys_constant (INV_QC_ESCALATE_HOURS, INV_QC_DEADLINE_HOURS)')" />
            <x-dl::field.display label="Received" :value="raw(Locale::decimal($f['received'], 2).' '.$f['uom'])" :source="Src::of('tm2pur.pv_line.c_qty_accepted')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Certification" :value="$batch->certification !== 'NONE' ? $batch->certification : ($batch->food_grade ? t('Food grade') : '—')" :source="Src::of('tm2inv.inv_batch.certification', 'food_grade')" />
            <x-dl::field.display label="Expiry" format="date" :value="$batch->expiry_date?->toDateString()" :source="Src::of('tm2inv.inv_batch.expiry_date')" />
            <x-dl::field.display label="URGENT" :value="$f['urgent'] ? raw($f['urgent']->urgent_by.' — '.$f['urgent']->urgent_reason) : '—'" :source="Src::of('tm2pur.pv_line.urgent_by', 'urgent_reason')" />
            <x-dl::field.display label="Sampling Plan for this Lot" :value="raw($plan['risk_class'].' · '.$intensity.' · '.($plan['sample_size'] > 0 ? $plan['sample_size'].' '.t('pcs') : t('certificate only')))" :source="Src::of('tm2inv.inv_qc_sampling_plan', 'inv_qc_intensity_rule', 'inv_qc_risk_class')" />
        </x-dl::form.row>
    </x-dl::card>

    @if ($canDecide)
        <x-dl::action-form action="{{ route('v2.iq.decide', $batch->id_batch) }}">
            <x-dl::card title="Quality Decision" description="Record what the inspection found. Release makes the stock normal, Reject holds the work orders that used it and opens a supplier claim, Block holds it without a claim. A reason is required to reject or block.">
                <x-dl::form.row :cols="4">
                    <x-dl::field.select name="decision_type" label="Inspection" required :options="BatchQuality::typeOptions()" :value="$batch->qc_status === 'CONDITIONAL' ? 'SAMPLING_DOCK' : 'USAGE_DECISION'" :source="Src::of('tm2inv.inv_qc_decision.decision_type')" />
                    <x-dl::field.decimal name="lot_qty" label="Lot Quantity" :value="$f['received'] ?: null" :source="Src::of('tm2inv.inv_qc_decision.lot_qty')" />
                    <x-dl::field.number name="sample_size" label="Sample Size" :value="$plan['sample_size'] ?: null" :source="Src::of('tm2inv.inv_qc_decision.sample_size')" />
                    <x-dl::field.number name="sample_defects" label="Defective in Sample" :source="Src::of('tm2inv.inv_qc_decision.sample_defects')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.text name="test_result" label="Test Result" :case-toggle="false" placeholder="e.g. gsm 250.4, moisture 7.1%, colour ok" :source="Src::of('tm2inv.inv_qc_decision.test_result')" />
                    <x-dl::field.text name="reason" label="Reason" :case-toggle="false" :source="Src::of('tm2inv.inv_qc_decision.reason')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button variant="secondary" icon="fa-hourglass-half" label="Conditional" type="submit" name="decision" value="CONDITIONAL_USE" formnovalidate />
                    <x-dl::button variant="secondary" icon="fa-ban" label="Block" type="submit" name="decision" value="BLOCKED" formnovalidate />
                    <x-dl::button variant="danger" icon="fa-circle-xmark" label="Reject" type="submit" name="decision" value="REJECTED" formnovalidate />
                    <x-dl::button icon="fa-circle-check" label="Release" type="submit" name="decision" value="RELEASED" formnovalidate />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @else
        <x-dl::alert tone="info" message="Only Quality Assurance or an operations manager can record the decision." />
    @endif

    <x-dl::card title="Decision History" icon="fa-solid fa-clock-rotate-left">
        <x-dl::table :columns="['at' => 'When', 'type' => 'Inspection', 'decision' => 'Decision', 'sample' => 'Sample / Defective', 'result' => 'Test Result', 'reason' => 'Reason', 'by' => 'By']" :rows="$f['decisions']" empty="No decision yet." :source="Src::of('tm2inv.inv_qc_decision')" />
    </x-dl::card>

    <x-dl::card title="Where This Batch Was Used" description="Material issued from this batch to work orders, followed forward to the customer order and delivery. A rejected or blocked batch holds these work orders.">
        <x-dl::table :columns="['doc' => 'Issue Document', 'date' => 'Date', 'wo' => 'Work Order', 'qty' => 'Qty', 'co' => 'Customer Order', 'delivery' => 'Delivery']" :rows="$f['usage']" empty="Not issued to any work order yet." :source="Src::of('tm2inv.rm_stock_d.batch_id', 'tm2inv.rm_stock', 'tm2prd.prd_wo')" />
        @if ($f['holds'])
            <x-dl::table :columns="['wo' => 'Held Work Order', 'qty' => 'Qty Issued', 'status' => 'Status', 'since' => 'Since']" :rows="$f['holds']" :source="Src::of('tm2inv.inv_qc_hold')" />
        @endif
    </x-dl::card>

    @if ($f['claims']->isNotEmpty())
        <x-dl::card title="Supplier Claim" icon="fa-solid fa-file-circle-exclamation">
            @foreach ($f['claims'] as $claim)
                <x-dl::action-form action="{{ route('v2.iq.claim', $claim->id_claim) }}">
                    <x-dl::form.row :cols="4">
                        <x-dl::field.display label="Claim No." :value="raw($claim->claim_no)" :source="Src::of('tm2inv.inv_supplier_claim.claim_no')" />
                        <x-dl::field.display label="Qty Claimed" :value="raw(Locale::decimal((float) $claim->qty_claimed, 2).' '.$claim->uom_code)" :source="Src::of('tm2inv.inv_supplier_claim.qty_claimed')" />
                        <x-dl::field.select name="status" label="Status" :options="BatchQuality::claimStatuses()" :value="$claim->status" :readonly="! ($canDecide || $canFollowUp)" :source="Src::of('tm2inv.inv_supplier_claim.status')" />
                        <x-dl::field.text name="notes" label="Notes" :case-toggle="false" :value="$claim->notes" :readonly="! ($canDecide || $canFollowUp)" :source="Src::of('tm2inv.inv_supplier_claim.notes')" />
                    </x-dl::form.row>
                    @if ($canDecide || $canFollowUp)
                        <x-dl::action-bar><x-dl::button preset="save" label="Save" /></x-dl::action-bar>
                    @endif
                </x-dl::action-form>
            @endforeach
        </x-dl::card>
    @endif
@endif
