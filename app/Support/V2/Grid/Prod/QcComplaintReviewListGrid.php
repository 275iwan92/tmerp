<?php

namespace App\Support\V2\Grid\Prod;

use App\Support\V2\Data\CrmComplaints;
use App\Support\V2\Grid\DocListGrid;

// Daftar Complaint Review (QA) (Grid Transactional) di layar v2 qc-complaint-review: Open / + New Record = form di layar yang sama.
class QcComplaintReviewListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'qc-complaint-review';
    }

    public function title(): string
    {
        return 'Complaint Review (QA)';
    }

    public function module(): string
    {
        return 'PROD';
    }

    protected function listRows(): array
    {
        return CrmComplaints::reviewRows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.crm_complaint (Confirmed, needs_qa = 1)', 'tm2sdi.crm_complaint_item', 'tm2prd.qc_complaint_review'];
    }

    public function search(): string
    {
        return 'Search complaint no., customer, product...';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Complaint No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'ref', 'label' => 'Order / Ref.'],
            ['key' => 'verdict', 'label' => 'Verdict'],
            ['key' => 'disposition', 'label' => 'Returned Goods'],
            ['key' => 'reviewed_at', 'label' => 'Answered', 'type' => 'date'],
            ['key' => 'qa_state', 'label' => 'QA', 'type' => 'badge', 'tones' => ['TO_REVIEW' => 'warn', 'ANSWERED' => 'success']],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty Complained', 'type' => 'number'],
            ['key' => 'returned', 'label' => 'Qty Returned', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'defect', 'label' => 'Problem'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'To review', 'where' => ['qa_state' => 'TO_REVIEW'], 'tone' => 'warn', 'default' => true], ['label' => 'Answered', 'where' => ['qa_state' => 'ANSWERED'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'category' => 'Category'];
    }
}
