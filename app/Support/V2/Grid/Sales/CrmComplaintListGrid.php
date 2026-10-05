<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sdi\CrmComplaint;
use App\Support\V2\Data\CrmComplaints;
use App\Support\V2\Grid\DocListGrid;

// Daftar Complaint & Return (Grid Transactional) di layar v2 crm-complaint: Open / + New Record = form di layar yang sama.
class CrmComplaintListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'crm-complaint';
    }

    public function title(): string
    {
        return 'Complaint & Return';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listRows(): array
    {
        return CrmComplaints::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.crm_complaint', 'tm2sdi.crm_complaint_item', 'tm2prd.qc_complaint_review', 'tm2mst.cust_customer'];
    }

    public function search(): string
    {
        return 'Search complaint no., customer, product...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Complaint No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'ref', 'label' => 'Order / Ref.'],
            ['key' => 'request', 'label' => 'Customer Asks', 'visible' => false],
            ['key' => 'qa', 'label' => 'QA', 'type' => 'badge', 'tones' => CrmComplaints::QA_TONES],
            ['key' => 'verdict', 'label' => 'QA Verdict', 'visible' => false],
            ['key' => 'resolution', 'label' => 'Resolution'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => CrmComplaints::STATUS_TONES],
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
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Waiting QA', 'where' => ['qa' => 'Waiting QA'], 'tone' => 'warn'],
            ['label' => 'Closed', 'where' => ['status' => 'CLOSED'], 'tone' => 'info'],
            ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger'],
        ];
    }

    public function tabs(): array
    {
        return [['label' => 'All'], ...collect(CrmComplaint::TYPES)->map(fn ($l) => ['label' => $l, 'where' => ['type' => t($l)]])->values()->all()];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'category' => 'Category', 'status' => 'Status'];
    }
}
