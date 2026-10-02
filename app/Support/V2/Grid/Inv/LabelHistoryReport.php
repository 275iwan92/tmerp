<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvLabelPrint;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Inventory > Label & QR Code > Report > Label Print History (tm2inv.inv_label_print): siapa mencetak label apa, kapan,
// berapa lembar. Dicatat setiap kali tombol Print di layar label ditekan.
class LabelHistoryReport extends DbGrid
{
    public function slug(): string
    {
        return 'inv-label-history';
    }

    public function title(): string
    {
        return 'Label Print History';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function group(): string
    {
        return 'Label & QR Code › Report';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clock-rotate-left';
    }

    public function search(): string
    {
        return 'Search code, name, printed by...';
    }

    protected function query(): Builder
    {
        return InvLabelPrint::query()->orderByDesc('id_label_print');
    }

    public function columns(): array
    {
        return [
            ['key' => 'printed_at', 'label' => 'Printed At', 'type' => 'datetime'],
            ['key' => 'type', 'label' => 'Label', 'type' => 'badge', 'tones' => ['Location' => 'info', 'Item' => 'accent']],
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'qty', 'label' => 'Qty on Label', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'copies', 'label' => 'Copies', 'type' => 'number', 'sum' => true],
            ['key' => 'size', 'label' => 'Size'],
            ['key' => 'printed_by', 'label' => 'Printed By'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['printed_at' => $m->printed_at?->toDateTimeString(), 'type' => InvLabelPrint::TYPES[$m->label_type] ?? $m->label_type, 'code' => $m->ref_code, 'name' => $m->ref_name,
            'qty' => $m->qty, 'unit' => $m->uom_code, 'copies' => (int) $m->copies, 'size' => $m->label_size, 'printed_by' => $m->printed_by];
    }

    public function filters(): array
    {
        return ['type' => 'Label', 'size' => 'Size', 'printed_by' => 'Printed By'];
    }

    public function groups(): array
    {
        return ['type' => 'Label', 'printed_by' => 'Printed By'];
    }
}
