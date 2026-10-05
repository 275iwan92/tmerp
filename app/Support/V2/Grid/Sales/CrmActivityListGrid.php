<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sdi\CrmActivity;
use App\Support\V2\Data\CrmActivities;
use App\Support\V2\Grid\DocListGrid;

// Daftar Activities & Follow-up (Grid Transactional) di layar v2 crm-activity: Open / + New Record = form di layar yang sama.
class CrmActivityListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'crm-activity';
    }

    public function title(): string
    {
        return 'Activities & Follow-up';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listRows(): array
    {
        return CrmActivities::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.crm_activity', 'tm2mst.cust_customer', 'tm2sdi.quo_quotation'];
    }

    public function search(): string
    {
        return 'Search customer, contact, notes, next action...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'type', 'label' => 'Activity / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'topic', 'label' => 'Topic'],
            ['key' => 'contact', 'label' => 'Contact'],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'toneKey' => 'result_tone'],
            ['key' => 'quotation', 'label' => 'Quotation', 'visible' => false],
            ['key' => 'notes', 'label' => 'Notes', 'visible' => false],
            ['key' => 'next_date', 'label' => 'Follow-up', 'type' => 'date'],
            ['key' => 'next', 'label' => 'Next Action'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'by', 'label' => 'By'],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
            ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
            ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
            ['label' => 'Later', 'where' => ['bucket' => 'LATER'], 'tone' => 'accent'],
        ];
    }

    public function tabs(): array
    {
        return [['label' => 'All'], ...collect(CrmActivity::TOPICS)->map(fn ($l) => ['label' => $l, 'where' => ['topic' => t($l)]])->values()->all()];
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'topic' => 'Topic', 'result' => 'Result', 'by' => 'By'];
    }

    public function rowTone(): ?string
    {
        return 'row_tone';
    }
}
