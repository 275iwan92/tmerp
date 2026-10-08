<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvSubconIssueLine;

// Stock at Vendor (v2.01, D2): bahan milik perusahaan yang sedang di tangan subkon, per subkon dan bahan, dengan umur sejak dikirim. Sumber = kirim bahan
// (InvSubconIssue) yang belum habis: dikirim - terpakai - dikembalikan. Hanya dibaca.
class StockAtVendorReport extends QualityDocGrid
{
    public function slug(): string
    {
        return 'stock-at-vendor';
    }

    public function title(): string
    {
        return 'Stock at Vendor';
    }

    public function group(): string
    {
        return 'Warehouse › Report';
    }

    public function icon(): string
    {
        return 'fa-solid fa-industry';
    }

    public function guide(): ?string
    {
        return 'Material we own that is at a subcontractor: sent minus used minus returned. Age counts from the day it was sent; Overdue means the due-back date has passed.';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_subcon_issue', 'tm2inv.inv_subcon_issue_line', 'tm2mst.sup_supplier'];
    }

    protected function listRows(): array
    {
        $lines = InvSubconIssueLine::query()->with('issue')->whereHas('issue', fn ($q) => $q->where('status', 'SENT'))->get()->filter(fn (InvSubconIssueLine $l) => $l->atVendor() > 0.00005);
        $names = $this->supplierNames($lines->map(fn ($l) => $l->issue->supplier_id));

        return $lines->map(fn (InvSubconIssueLine $l) => ['id' => $l->id_subcon_issue_line, 'supplier' => trim((string) ($names[$l->issue->supplier_id] ?? '#'.$l->issue->supplier_id)),
            'item' => $l->item_name, 'item_code' => $l->item_code, 'qty' => $l->atVendor(), 'unit' => $l->uom_code, 'send' => $l->issue->issue_no, 'po' => $l->issue->po_no,
            'sent_on' => $l->issue->issue_date?->toDateString(), 'age' => (int) $l->issue->issue_date?->diffInDays(today(), true), 'due' => $l->issue->due_return_date?->toDateString(),
            'check' => $l->issue->due_return_date && $l->issue->due_return_date->lt(today()) ? 'Overdue' : null])->sortBy([['supplier', 'asc'], ['sent_on', 'asc']])->values()->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'supplier', 'label' => 'Subcontractor'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'qty', 'label' => 'At Vendor', 'type' => 'number', 'decimals' => 2, 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'send', 'label' => 'Send No.'],
            ['key' => 'po', 'label' => 'Subcon PO'],
            ['key' => 'sent_on', 'label' => 'Sent', 'type' => 'date'],
            ['key' => 'age', 'label' => 'Age (days)', 'type' => 'number', 'decimals' => 0],
            ['key' => 'due', 'label' => 'Due Back', 'type' => 'date'],
            ['key' => 'check', 'label' => 'Check', 'type' => 'badge', 'tones' => ['Overdue' => 'danger']],
        ];
    }

    public function filters(): array
    {
        return ['supplier' => 'Subcontractor'];
    }
}
