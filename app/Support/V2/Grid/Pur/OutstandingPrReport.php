<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Outstanding PR (v1: pages::pur.report.outstanding-pr): baris PR APPROVED/PARTIALLY_ORDERED yang belum habis jadi PO.
// Aturan sama dengan v1: sisa = i_qty - c_qty_ordered, nilai = sisa x harga estimasi.
class OutstandingPrReport extends PurReportGrid
{
    public function slug(): string
    {
        return 'pur-rpt-outstanding-pr';
    }

    public function title(): string
    {
        return 'Outstanding PR';
    }

    public function group(): string
    {
        return 'Requisition › Report';
    }

    protected function models(): array
    {
        return [PrLine::class, PrRequisition::class, OrgDepartment::class];
    }

    public function search(): string
    {
        return 'Search PR no, item, department...';
    }

    protected function query(): Builder
    {
        return PrLine::query()->with('requisition.department')
            ->whereRaw('i_qty - COALESCE(c_qty_ordered, 0) > 0')
            ->whereHas('requisition', fn ($q) => $q->whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED'])->where('stat_cancel', false))
            ->orderBy('pr_id')->orderBy('line_no');
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_no', 'label' => 'PR No'],
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'need', 'label' => 'Need Date', 'type' => 'date'],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'ordered', 'label' => 'Ordered', 'type' => 'number', 'sum' => true],
            ['key' => 'remain', 'label' => 'Remaining', 'type' => 'number', 'sum' => true],
            ['key' => 'value', 'label' => 'Est. Value', 'type' => 'money', 'sum' => true],
            ['key' => 'age', 'label' => 'Age'],
            ['key' => 'status', 'label' => 'PR Status', 'type' => 'badge', 'tones' => ['APPROVED' => 'info', 'PARTIALLY_ORDERED' => 'warn']],
        ];
    }

    protected function row(Model $m): array
    {
        $pr = $m->requisition;
        $remain = (float) $m->i_qty - (float) ($m->c_qty_ordered ?? 0);

        return [
            'doc_no' => $pr?->doc_no, 'doc_date' => $pr?->i_doc_date ? substr((string) $pr->i_doc_date, 0, 10) : null,
            'department' => $pr?->department?->department_name, 'item' => $m->i_description, 'item_code' => $m->c_item_code,
            'need' => $m->i_need_date ? substr((string) $m->i_need_date, 0, 10) : null, 'uom' => $m->i_uom,
            'qty' => (float) $m->i_qty, 'ordered' => (float) ($m->c_qty_ordered ?? 0), 'remain' => $remain,
            'value' => $remain * (float) ($m->i_est_price ?? 0), 'age' => $this->ageBucket($pr?->i_doc_date ? new \DateTime((string) $pr->i_doc_date) : null),
            'status' => $pr?->status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Not ordered yet', 'where' => ['status' => 'APPROVED'], 'tone' => 'info'],
            ['label' => 'Partially ordered', 'where' => ['status' => 'PARTIALLY_ORDERED'], 'tone' => 'warn'],
            ['label' => 'Older than 30 days', 'where' => ['age' => t('> 30 days')], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['department' => 'Department', 'age' => 'Age', 'status' => 'PR Status'];
    }

    public function groups(): array
    {
        return ['doc_no' => 'PR No', 'department' => 'Department', 'age' => 'Age'];
    }
}
