<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcDecision;
use App\Models\Pur\PvLine;
use Illuminate\Support\Facades\DB;

// URGENT usage: setiap baris penerimaan yang ditandai URGENT, siapa meminta, untuk WO mana, dan hasil sampling yang menyusul
// (Q-12): supaya penggunaan URGENT tidak melebar tanpa terlihat. Hanya dibaca.
class UrgentUsageReport extends QualityDocGrid
{
    public const TONES = ['Released' => 'success', 'Conditional' => 'warn', 'Rejected' => 'danger', 'Blocked' => 'neutral', 'Quarantine' => 'info'];

    public function slug(): string
    {
        return 'qc-urgent-usage';
    }

    public function title(): string
    {
        return 'URGENT Usage';
    }

    public function icon(): string
    {
        return 'fa-solid fa-bolt';
    }

    public function guide(): ?string
    {
        return 'Receipt lines marked URGENT: who asked, for which work order, who approved FSC / food grade material, and what the sampling that followed decided. Awaiting = the batch is still Conditional.';
    }

    protected function sources(): array
    {
        return ['tm2pur.pv_line', 'tm2pur.pv_receipt', 'tm2inv.inv_batch', 'tm2inv.inv_qc_decision', 'tm2mst.sup_supplier'];
    }

    protected function listRows(): array
    {
        $lines = PvLine::query()->with('receipt')->where('is_urgent', true)->orderByDesc('urgent_at')->limit($this->limit)->get();
        $suppliers = $this->supplierNames($lines->map(fn (PvLine $l) => $l->receipt?->i_supplier_id));
        $batches = InvBatch::query()->whereKey($lines->pluck('i_batch_id')->filter()->unique()->all())->get()->keyBy('id_batch');
        $wos = DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', $lines->pluck('urgent_wo_id')->filter()->unique())->pluck('wo_no', 'wo_id');
        $last = InvQcDecision::query()->whereIn('batch_id', $batches->keys())->orderBy('id_decision')->get()->keyBy('batch_id');

        return $lines->map(function (PvLine $l) use ($suppliers, $batches, $wos, $last) {
            $batch = $batches[$l->i_batch_id] ?? null;
            $decision = $batch ? ($last[$batch->id_batch] ?? null) : null;

            return ['id' => $l->pv_id, 'receipt' => (string) ($l->receipt?->doc_no ?: 'Draft #'.$l->pv_id), 'date' => $l->receipt?->i_doc_date?->toDateString(), 'supplier' => $suppliers[$l->receipt?->i_supplier_id] ?? '',
                'item' => (string) $l->i_description, 'item_code' => (string) $l->c_item_code, 'qty' => (float) $l->i_qty_stock, 'uom' => (string) $l->i_uom_stock, 'by' => (string) $l->urgent_by,
                'at' => $this->dt($l->urgent_at), 'wo' => (string) ($wos[$l->urgent_wo_id] ?? ''), 'reason' => (string) $l->urgent_reason, 'approved' => (string) $l->urgent_approved_by,
                'batch' => (string) ($batch->batch_no ?? ''), 'status' => $batch ? InvBatch::STATUSES[$batch->qc_status]['label'] : 'Not posted', 'result' => $decision ? (string) $decision->test_result : '',
                'decided' => $decision ? $decision->decided_by.' · '.$this->dt($decision->decided_at) : ''];
        })->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'receipt', 'label' => 'Receipt', 'width' => '130px'],
            ['key' => 'date', 'label' => 'Received', 'type' => 'date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'decimals' => 2, 'sum' => true],
            ['key' => 'uom', 'label' => 'UoM', 'width' => '70px'],
            ['key' => 'by', 'label' => 'Requested By'],
            ['key' => 'wo', 'label' => 'Work Order', 'width' => '120px'],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'approved', 'label' => 'Approved By'],
            ['key' => 'batch', 'label' => 'Batch', 'width' => '120px'],
            ['key' => 'status', 'label' => 'Batch Status', 'type' => 'badge', 'tones' => self::TONES + ['Not posted' => 'neutral']],
            ['key' => 'result', 'label' => 'Sampling Result'],
            ['key' => 'decided', 'label' => 'Decided By'],
            ['key' => 'at', 'label' => 'Marked At', 'visible' => false],
        ];
    }

    public function groups(): array
    {
        return ['supplier' => 'Supplier', 'by' => 'Requested By', 'status' => 'Batch Status'];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Awaiting', 'where' => ['status' => 'Conditional'], 'tone' => 'warn'], ['label' => 'Released', 'where' => ['status' => 'Released'], 'tone' => 'success'],
            ['label' => 'Rejected', 'where' => ['status' => 'Rejected'], 'tone' => 'danger']];
    }
}
