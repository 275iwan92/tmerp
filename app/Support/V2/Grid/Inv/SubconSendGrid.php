<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Support\V2\Data\SubconSends;
use App\Support\V2\Grid\DocListGrid;

// Daftar kirim bahan ke subkon (Grid Transactional) di Inventory > Warehouse > Send to Subcontractor: induk = PO Subkon. Open = rincian dan
// penyelesaian (kembali / terpakai); + New Record = kirim baru. Layar: resources/views/v2/screens/subcon-send.blade.php.
class SubconSendGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'subcon-send';
    }

    public function title(): string
    {
        return 'Send to Subcontractor';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Warehouse';
    }

    public function icon(): string
    {
        return 'fa-solid fa-dolly';
    }

    protected function listRows(): array
    {
        return SubconSends::rows();
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_subcon_issue', 'tm2inv.inv_subcon_issue_line', 'tm2pur.po_order', 'tm2mst.sup_supplier'];
    }

    public function search(): string
    {
        return 'Search send no., PO, supplier, item...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Send No. / Subcon PO', 'type' => 'doc', 'party' => 'po'],
            ['key' => 'supplier', 'label' => 'Subcontractor'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due Back', 'type' => 'date'],
            ['key' => 'item', 'label' => 'Material'],
            ['key' => 'sent', 'label' => 'Sent', 'type' => 'number', 'decimals' => 2],
            ['key' => 'at_vendor', 'label' => 'At Vendor', 'type' => 'number', 'decimals' => 2],
            ['key' => 'overdue', 'label' => 'Check', 'type' => 'badge', 'tones' => ['Overdue' => 'danger']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => SubconSends::TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Material', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Sent', 'type' => 'number'],
            ['key' => 'used', 'label' => 'Used', 'type' => 'number'],
            ['key' => 'back', 'label' => 'Returned', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Sent', 'where' => ['status' => 'Sent'], 'tone' => 'info'], ['label' => 'Overdue', 'where' => ['overdue' => 'Overdue'], 'tone' => 'danger'],
            ['label' => 'Closed', 'where' => ['status' => 'Closed'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'neutral']];
    }

    public function filters(): array
    {
        return ['supplier' => 'Subcontractor', 'status' => 'Status'];
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.subcon-send'] + parent::screen();
    }
}
