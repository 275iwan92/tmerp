<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Support\V2\Data\PurchaseRequisition;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Daftar Purchase Requisition (Grid Transactional) di layar v2 purchase-requisition: Open / + New Record = form di layar yang sama.
class PurchaseRequisitionGrid extends DbGrid
{
    private array $departments = [];

    public function slug(): string
    {
        return 'purchase-requisition';
    }

    public function title(): string
    {
        return 'Purchase Requisition (PR)';
    }

    public function module(): string
    {
        return 'PROC';
    }

    public function moduleLabel(): string
    {
        return 'Procurement';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Requisition';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-circle-plus';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?new=1';
    }

    protected function models(): array
    {
        return [PrRequisition::class, PrLine::class];
    }

    public function search(): string
    {
        return 'Search PR no., department...';
    }

    protected function query(): Builder
    {
        return PrRequisition::query()->with('lines')->orderByDesc('pr_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->departments = OrgDepartment::query()->whereIn('department_id', $items->pluck('i_department_id')->filter()->unique()->all())
            ->pluck('department_name', 'department_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'PR No. / Department', 'type' => 'doc', 'party' => 'dept'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'need', 'label' => 'Need Date', 'type' => 'date'],
            ['key' => 'total', 'label' => 'Est. Total', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PurchaseRequisition::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'no' => $m->doc_no ?: '(draft #'.$m->pr_id.')', 'dept' => $this->departments[$m->i_department_id] ?? '—',
            'date' => $m->i_doc_date?->toDateString(), 'need' => $m->i_need_date?->toDateString(),
            'total' => (float) $m->c_est_total, 'status' => $m->status,
            'items' => $m->lines->map(fn (PrLine $l) => ['code' => $l->c_item_code, 'name' => $l->i_description, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom,
                'need' => $l->i_need_date?->toDateString(), 'ordered' => (float) $l->c_qty_ordered, 'note' => $l->i_notes])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'need', 'label' => 'Need Date', 'type' => 'date'],
            ['key' => 'ordered', 'label' => 'Ordered', 'type' => 'number'],
            ['key' => 'note', 'label' => 'Notes'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Waiting for approval', 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn'],
            ['label' => 'Approved', 'where' => ['status' => ['APPROVED', 'PARTIALLY_ORDERED']], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['dept' => 'Department', 'status' => 'Status'];
    }
}
