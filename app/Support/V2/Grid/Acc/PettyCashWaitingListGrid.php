<?php

namespace App\Support\V2\Grid\Acc;

use App\Support\V2\Data\PettyCash;
use App\Support\V2\Grid\DocListGrid;

// Daftar Petty Cash: Cash PO Waiting (layar v2 petty-cash): turunan DocListGrid, kolom dan saringannya sama dengan sebelumnya.
class PettyCashWaitingListGrid extends DocListGrid
{
    public function __construct(private array $waiting) {}

    public function slug(): string
    {
        return 'petty-cash';
    }

    public function title(): string
    {
        return 'Petty Cash: Cash PO Waiting';
    }

    public function module(): string
    {
        return 'FINACC';
    }

    protected function listId(): string
    {
        return 'petty-waiting';
    }

    protected function listRows(): array
    {
        return $this->waiting;
    }

    protected function sources(): array
    {
        return ['tm2pur.po_order (CASH, APPROVED)'];
    }

    public function search(): string
    {
        return 'Search cash PO...';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?po={id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Cash PO No. / Shop', 'type' => 'doc', 'party' => 'shop'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
            ['key' => 'step', 'label' => 'Next Step', 'type' => 'badge', 'tones' => PettyCash::STEP_TONES],
            ['key' => 'by', 'label' => 'Created By'],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }
}
