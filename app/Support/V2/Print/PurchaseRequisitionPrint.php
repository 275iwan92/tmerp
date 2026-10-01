<?php

namespace App\Support\V2\Print;

use App\Models\Pur\PrRequisition;
use App\Models\Sdi\CoOrderItem;
use Devlib\PrintDoc\Document;

// Purchase Requisition (format A, user 2026-10-01): satu format untuk dua jenis. Produksi = baris terikat ke baris CO
// (pr_line.i_co_line_id): No CO di kolom, nama product di baris kedua nama barang. Non Produksi (ATK, komputer):
// No CO kosong, baris kedua = catatan baris. Harga = estimasi. Sumber: tm2pur.pr_requisition + pr_line.
final class PurchaseRequisitionPrint extends Document
{
    public string $type = 'purchase-requisition';

    public string $title = 'Purchase Requisition';

    public ?string $subtitle = 'Purchase Request · Internal';

    public string $numberLabel = 'PR No.';

    public array $refs = [
        ['key' => 'category', 'label' => 'Kind'],
        ['key' => 'source', 'label' => 'Source'],
        ['key' => 'purpose', 'label' => 'For'],
        ['key' => 'approval', 'label' => 'Approval'],
    ];

    public string $partyLabel = 'Requested By';

    public string $nb = 'Prices are estimates. Final prices follow the Purchase Quotation and the Purchase Order.';

    public array $extra = [
        ['key' => 'co_no', 'label' => 'CO No.', 'width' => 17],
        ['key' => 'need_date', 'label' => 'Needed Date', 'width' => 21, 'type' => 'date'],
    ];

    public bool $priced = true;

    public array $receivers = [['Received By', 'Purchasing']];

    public static function load(int $id, array $opt = []): static
    {
        $pr = PrRequisition::with(['department', 'lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($id);
        $items = CoOrderItem::with('order:id_order,co_no')->whereIn('id_item', $pr->lines->pluck('i_co_line_id')->filter()->all())->get()->keyBy('id_item');
        $approval = PrintDocs::approval('PR', $pr->pr_id, (float) $pr->c_est_total);

        return new self([
            'number' => $pr->doc_no ?: 'Draft #'.$pr->pr_id, 'date' => $pr->i_doc_date?->toDateString(), 'status' => $pr->status, 'printedBy' => $opt['printedBy'] ?? null,
            'approvedBy' => $approval['by'], 'ppn' => 0,
            'category' => self::t($items->isNotEmpty() ? 'Production' : 'Non Production'), 'source' => $pr->i_source, 'purpose' => $pr->i_notes,
            'approval' => $approval['level'] ? self::t('Level').' '.$approval['level'] : null,
            'party' => ['name' => $pr->department?->department_name ?? '', 'lines' => [self::t('Requested by').': '.PrintDocs::person($pr->created_by)]],
            'rows' => $pr->lines->map(fn ($l) => ['name' => $l->i_description, 'sub' => $items[$l->i_co_line_id]->description ?? $l->i_notes,
                'qty' => (float) $l->i_qty, 'unit' => $l->i_uom, 'co_no' => $items[$l->i_co_line_id]->order->co_no ?? null,
                'need_date' => ($l->i_need_date ?? $pr->i_need_date)?->toDateString(),
                'price' => (float) $l->i_est_price, 'subtotal' => (float) $l->c_est_subtotal])->all(),
        ]);
    }
}
