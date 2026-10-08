<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvBatch;
use App\Services\Inventory\QcDecisionService;
use Illuminate\Support\Facades\DB;

// Conditional Stock: batch yang masih menunggu keputusan mutu (Conditional = sudah boleh dipakai, Quarantine = belum), terlama dulu.
// Umur dan tanda tenggat dari QcDecisionService::age (INV_QC_ESCALATE_HOURS, INV_QC_DEADLINE_HOURS). Klik baris = buka batch untuk memutuskan.
class ConditionalStockListGrid extends QualityDocGrid
{
    public function slug(): string
    {
        return 'conditional-stock';
    }

    public function title(): string
    {
        return 'Conditional Stock';
    }

    public function icon(): string
    {
        return 'fa-solid fa-hourglass-half';
    }

    public function guide(): ?string
    {
        return 'Batches that still wait for a quality decision, oldest first. Conditional stock may already be used (URGENT receipt or sampling still running); Quarantine stock may not. Escalate = past the escalation hours, Overdue = past the decision deadline: a usage decision is required.';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_batch', 'tm2inv.inv_qc_decision', 'tm2pur.pv_line', 'tm2mst.sup_supplier'];
    }

    protected function listRows(): array
    {
        $batches = InvBatch::query()->whereIn('qc_status', ['CONDITIONAL', 'QUARANTINE'])->where('activate', true)->orderBy('qc_status_at')->limit($this->limit)->get();
        $suppliers = $this->supplierNames($batches->pluck('supplier_id'));
        $urgent = DB::connection('tm2pur')->table('pv_line')->where('is_urgent', 1)->whereIn('i_batch_id', $batches->modelKeys())->pluck('urgent_reason', 'i_batch_id');

        return $batches->map(function (InvBatch $b) use ($suppliers, $urgent) {
            $age = QcDecisionService::age($b);

            return ['id' => $b->id_batch, 'batch' => $b->batch_no, 'item' => (string) $b->item_name, 'item_code' => (string) $b->item_code, 'lot' => (string) $b->supplier_lot,
                'supplier' => $suppliers[$b->supplier_id] ?? '', 'status' => InvBatch::STATUSES[$b->qc_status]['label'], 'since' => $this->dt($b->qc_status_at),
                'hours' => $age['hours'], 'state' => ['OK' => 'On time', 'ESCALATE' => 'Escalate', 'OVERDUE' => 'Overdue'][$age['state']], 'urgent' => $urgent->has($b->id_batch) ? 'URGENT' : '',
                'reason' => (string) ($urgent[$b->id_batch] ?? ''), 'cert' => $b->certification !== 'NONE' ? $b->certification : ($b->food_grade ? 'Food grade' : '')];
        })->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'batch', 'label' => 'Batch', 'width' => '120px'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'lot', 'label' => 'Supplier Lot', 'width' => '130px'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Conditional' => 'warn', 'Quarantine' => 'info']],
            ['key' => 'state', 'label' => 'Deadline', 'type' => 'badge', 'tones' => ['On time' => 'success', 'Escalate' => 'warn', 'Overdue' => 'danger']],
            ['key' => 'hours', 'label' => 'Hours Waiting', 'type' => 'number', 'decimals' => 1],
            ['key' => 'since', 'label' => 'Since'],
            ['key' => 'urgent', 'label' => 'URGENT', 'type' => 'badge', 'tones' => ['URGENT' => 'danger']],
            ['key' => 'cert', 'label' => 'Certification'],
            ['key' => 'reason', 'label' => 'URGENT Reason', 'visible' => false],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Conditional', 'where' => ['status' => 'Conditional'], 'tone' => 'warn'], ['label' => 'Quarantine', 'where' => ['status' => 'Quarantine'], 'tone' => 'info'],
            ['label' => 'Overdue', 'where' => ['state' => 'Overdue'], 'tone' => 'danger']];
    }
}
