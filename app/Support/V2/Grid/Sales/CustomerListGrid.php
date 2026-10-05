<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\Crm;
use App\Support\V2\Grid\DocListGrid;

// Daftar Customer 360 (layar v2 crm-customer): turunan DocListGrid, kolom dan saringannya sama dengan sebelumnya.
class CustomerListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'crm-customer';
    }

    public function title(): string
    {
        return 'Customer 360';
    }

    public function module(): string
    {
        return 'SALES';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    protected function listId(): string
    {
        return 'crm-customer-list';
    }

    protected function listRows(): array
    {
        return Crm::rows();
    }

    protected function sources(): array
    {
        return ['tm2mst.cust_customer', 'tm2sdi.co_order', 'tm2sdi.co_order_legacy', 'tm2sdi.quo_quotation', 'tm2sdi.crm_activity', 'tm2sdi.crm_complaint'];
    }

    public function search(): string
    {
        return 'Search customer, code, group, industry...';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Customer', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'segment', 'label' => 'Segment', 'type' => 'badge', 'tones' => collect(Crm::SEGMENT_TONES)->mapWithKeys(fn ($tone, $label) => [t($label) => $tone])->all()],
            ['key' => 'group', 'label' => 'Group', 'visible' => false],
            ['key' => 'industry', 'label' => 'Industry', 'visible' => false],
            ['key' => 'last_order', 'label' => 'Last Order', 'type' => 'date'],
            ['key' => 'orders_12m', 'label' => 'Orders (12 months)', 'type' => 'number'],
            ['key' => 'open_quo', 'label' => 'Open Quotations', 'type' => 'number'],
            ['key' => 'open_co', 'label' => 'Open Orders', 'type' => 'number'],
            ['key' => 'last_contact', 'label' => 'Last Contact', 'type' => 'date'],
            ['key' => 'next_date', 'label' => 'Next Follow-up', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'complaints', 'label' => 'Open Complaints', 'type' => 'number'],
        ];
    }

    public function stats(): array
    {
        return [...[['label' => 'All'], ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'], ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'], ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow']], ['label' => 'Open complaint', 'where' => ['has_complaint' => true], 'tone' => 'accent']];
    }

    public function tabs(): array
    {
        return [['label' => 'All'], ...collect(Crm::SEGMENT_TONES)->map(fn ($tone, $label) => ['label' => $label, 'where' => ['segment' => t($label)], 'tone' => $tone])->values()->all()];
    }

    public function filters(): array
    {
        return ['segment' => 'Segment', 'group' => 'Group', 'industry' => 'Industry'];
    }

    public function rowTone(): ?string
    {
        return 'row_tone';
    }

    // Sama dengan sebelum menjadi kelas: grid ini memakai activeKey bawaan x-dl::grid.
    public function props(): array
    {
        return ['activeKey' => 'active'] + parent::props();
    }
}
