<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\Quotation;
use App\Support\V2\Grid\DocListGrid;

// Daftar Quotation (Grid Transactional) di layar v2 quotation: Open / + New Record = form di layar yang sama.
class QuotationListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'quotation';
    }

    public function title(): string
    {
        return 'Quotation';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listRows(): array
    {
        return Quotation::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.quo_quotation', 'tm2sdi.quo_quotation_item', 'tm2sdi.quo_activity', 'tm2sdi.iqp_inquiry.sub_state + cancel_reason', 'tm2sdi.co_order'];
    }

    public function search(): string
    {
        return 'Search quotation no., customer, sales, reason...';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?pick=1';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Quotation No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
            ['key' => 'valid', 'label' => 'Valid Until', 'type' => 'date', 'visible' => false],
            ['key' => 'sales', 'label' => 'Sales PIC'],
            ['key' => 'total', 'label' => 'Grand total', 'type' => 'money', 'sum' => true],
            ['key' => 'followups', 'label' => 'Follow-up', 'type' => 'number', 'visible' => false],
            ['key' => 'next', 'label' => 'Upcoming follow-up', 'type' => 'date', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Quotation::STATUS_TONES],
            ['key' => 'closed', 'label' => 'Closed', 'type' => 'date'],
            ['key' => 'co', 'label' => 'Customer Order'],
            ['key' => 'reason', 'label' => 'Reason (LOST / Cancel)'],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'spec', 'label' => 'Specification'],
            ['key' => 'inquiry', 'label' => 'Inquiry No.'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => t('All')],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => t('Issued (waiting for customer)'), 'where' => ['status' => 'PUBLISHED'], 'tone' => 'warn'],
            ['label' => 'WIN', 'where' => ['status' => 'WON'], 'tone' => 'success'],
            ['label' => 'LOST', 'where' => ['status' => 'LOST'], 'tone' => 'danger'],
            ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['status' => 'Status', 'sales' => 'Sales PIC'];
    }
}
