<?php

namespace App\Support\V2\Print;

use App\Models\Pur\PoOrder;
use Devlib\Locale;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\DB;

// Purchase Order (format A, user 2026-10-01): dokumen ke supplier. Kop PPN (badan hukum + NPWP) bila PO kena PPN.
// Tanda tangan dari kiri: Supplier, Menyetujui, Mengetahui, Pembuat. Total = angka tersimpan PO (c_dpp / c_ppn / c_pph /
// c_net_payable). Sumber: tm2pur.po_order + po_line; Ref. PR dari alokasi baris PO (pr_line_alloc -> pr_line -> pr_requisition).
final class PurchaseOrderPrint extends Document
{
    public string $type = 'purchase-order';

    public string $title = 'Purchase Order';

    public ?string $subtitle = 'Order Letter';

    public string $numberLabel = 'PO No.';

    public array $refs = [
        ['key' => 'pr_no', 'label' => 'PR Ref.'],
        ['key' => 'payment_term', 'label' => 'Payment Terms'],
        ['key' => 'handover', 'label' => 'Delivered / Picked Up', 'hand' => true],
        ['key' => 'delivery_date', 'label' => 'Estimated Date', 'type' => 'date', 'hand' => true],
        ['key' => 'ship_to', 'label' => 'Destination'],
    ];

    public string $partyLabel = 'To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'Dear Sir / Madam, we hereby order the following goods:';

    public bool $nbPlain = true;

    public string $nameLabel = 'Goods / Services';

    public array $extra = [
        ['key' => 'disc', 'label' => 'Disc.', 'width' => 17],
        ['key' => 'due_date', 'label' => 'Ship Date', 'width' => 21, 'type' => 'date'],
    ];

    public bool $priced = true;

    public array $receivers = [['Supplier', 'Accepting the order']];

    public static function load(int $id, array $opt = []): static
    {
        $po = PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($id);
        $sup = DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $po->i_supplier_id)->first();
        $allocs = $po->lines->pluck('alloc_id')->filter()->all();
        $prNo = $allocs ? DB::connection('tm2pur')->table('pr_line_alloc as a')->join('pr_line as l', 'l.pr_line_id', '=', 'a.pr_line_id')
            ->join('pr_requisition as r', 'r.pr_id', '=', 'l.pr_id')->whereIn('a.alloc_id', $allocs)->distinct()->pluck('r.doc_no')->filter()->implode(', ') : null;
        $approval = PrintDocs::approval('PO', $po->po_id, (float) $po->c_grand_total);
        $dpp = (float) $po->c_dpp;

        return new self([
            'number' => $po->doc_no ?: 'Draft #'.$po->po_id, 'date' => $po->i_doc_date?->toDateString(), 'status' => $po->status, 'printedBy' => $opt['printedBy'] ?? null,
            'approvedBy' => $approval['by'], 'ppn' => $dpp > 0 ? round((float) $po->c_ppn / $dpp * 100, 2) : 0,
            'stored' => ['dpp' => $dpp, 'ppn' => (float) $po->c_ppn, 'pph' => (float) $po->c_pph, 'net' => (float) ($po->c_net_payable ?? $po->c_grand_total)],
            'pr_no' => $prNo ?: null, 'payment_term' => $po->i_payment_term, 'delivery_date' => $po->i_delivery_date?->toDateString(), 'ship_to' => $po->i_ship_to,
            'contact' => implode(' · ', array_filter([$sup->contact_name ?? null, $sup->phone ?? null])),
            'party' => ['name' => $sup->supplier_name ?? ($po->i_shop_name ?? ''), 'lines' => PrintDocs::address($sup->default_address ?? null)],
            'notes' => [['Notes', trim((string) $po->i_notes) ?: self::t('Thank you for your cooperation.')]],
            'rows' => $po->lines->map(fn ($l) => ['name' => $l->i_description, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom,
                'disc' => (float) $l->i_disc_pct > 0 ? Locale::decimal($l->i_disc_pct, 2).'%' : null,
                'price' => (float) $l->i_price, 'subtotal' => (float) $l->c_line_net])->all(),
        ]);
    }

    // Angka pajak dan total persis yang tersimpan di PO (bukan dihitung ulang di cetakan).
    public function totals(): array
    {
        $s = $this->data['stored'];
        $num = fn ($v) => Locale::number($v, 2);
        $pct = fn (float $v) => $s['dpp'] > 0 ? ' '.Locale::decimal($v / $s['dpp'] * 100, 2).'%' : '';
        $lines = [[self::t('Subtotal'), $num($s['dpp']), false], [self::t('VAT').$pct($s['ppn']), $num($s['ppn']), false]];
        if ($s['pph'] > 0) {
            $lines[] = [self::t('Income Tax 23').$pct($s['pph']), '('.$num($s['pph']).')', false];
        }
        $lines[] = [self::t('Total').' ('.$this->currency.')', $num($s['net']), true];

        return ['lines' => $lines, 'grand' => $s['net']];
    }
}
