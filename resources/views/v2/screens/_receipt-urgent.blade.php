{{--
 REMARK
 Tujuan       : Bagian Goods Receipt: tanda URGENT, sertifikasi batch dan rencana sampling per baris barang stok.
 Sumber hulu  : App\Support\V2\Data\BatchQuality::receiptRows ($pv), aturan App\Services\Pur\UrgentReceiptService, hak di App\Services\Inventory\QcAuthority; tabel tm2pur.pv_line dan tm2inv.inv_qc_sampling_plan.
 Input        : $pv (penerimaan / SPB jenis PO), $draft (status draft); isian pv_line_id, wo_id, reason, note, certification, food_grade, step.
 Output       : x-dl::card dengan x-dl::table dan, untuk draft + berwenang, x-dl::action-form post ke route v2.iq.urgent (Clear, Save, URGENT, Approve).
 Dipakai oleh : Di-include purchase-receive.
 Status       : Aktif.
--}}
@php
    use App\Services\Inventory\QcAuthority;
    use App\Support\V2\Data\BatchQuality;
    use App\Support\V2\Src;

    $rows = BatchQuality::receiptRows($pv);
    $stock = collect($rows)->where('stock', true);
    $canMark = QcAuthority::canMarkUrgent(auth()->user());
    $canApprove = QcAuthority::canApproveUrgent(auth()->user());
@endphp
@if ($stock->isNotEmpty())
    <x-dl::card title="URGENT and Sampling" description="URGENT lines can be posted before the quality QC is done and go straight to production; their batch stays Conditional until the sampling decision. FSC / PEFC and food grade material needs the approval of the head of QC or an operations manager.">
        <x-dl::table :columns="['line' => 'Line', 'item' => 'Item', 'cert' => 'Certification', 'urgent' => 'URGENT', 'wo' => 'Work Order', 'reason' => 'Reason', 'approval' => 'Approval', 'sampling' => 'Sampling']" :rows="$stock->values()->all()" :source="Src::of('tm2pur.pv_line', 'tm2inv.inv_qc_sampling_plan')" />
        @if ($draft && ($canMark || $canApprove))
            <x-dl::action-form action="{{ route('v2.iq.urgent', $pv->pv_id) }}">
                <x-dl::form.row :cols="4">
                    <x-dl::field.select name="pv_line_id" label="Line" required :options="$stock->mapWithKeys(fn ($r) => [$r['id'] => '#'.$r['line'].' '.$r['item']])->all()" :source="Src::of('tm2pur.pv_line')" />
                    <x-dl::field.select name="wo_id" label="Work Order" :options="BatchQuality::workOrders()" :source="Src::of('tm2pur.pv_line.urgent_wo_id')" />
                    <x-dl::field.text name="reason" label="URGENT Reason" :case-toggle="false" :source="Src::of('tm2pur.pv_line.urgent_reason')" />
                    <x-dl::field.text name="note" label="Approval Note" :case-toggle="false" :source="Src::of('tm2pur.pv_line.urgent_approval_note')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="4">
                    <x-dl::field.select name="certification" label="Certification" :options="\App\Models\Inventory\InvBatch::CERTIFICATIONS" value="NONE" :source="Src::of('tm2pur.pv_line.i_certification')" />
                    <x-dl::field.checkbox name="food_grade" text="Food grade" :source="Src::of('tm2pur.pv_line.i_food_grade')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    @if ($canMark)
                        <x-dl::button variant="secondary" icon="fa-eraser" label="Clear" type="submit" name="step" value="unmark" formnovalidate />
                        <x-dl::button variant="secondary" icon="fa-tag" label="Save" type="submit" name="step" value="certify" formnovalidate />
                        <x-dl::button variant="danger" icon="fa-bolt" label="URGENT" type="submit" name="step" value="mark" formnovalidate />
                    @endif
                    @if ($canApprove)
                        <x-dl::button icon="fa-circle-check" label="Approve" type="submit" name="step" value="approve" formnovalidate />
                    @endif
                </x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </x-dl::card>
@endif
