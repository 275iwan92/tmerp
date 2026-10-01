<?php

namespace App\Support\V2\Print;

use App\Models\Acc\FinPettyCashTx;
use App\Models\Pur\PoOrder;
use App\Support\V2\Data\PettyCash;
use Devlib\PrintDoc\Document;

// Bukti Kas Kecil (belum A): satu lembar per transaksi kas kecil (uang muka, penyelesaian / reimburse, top up).
// Baris = barang Cash PO transaksi itu; tanpa Cash PO (top up) = satu baris nilai transaksi. Total = nilai transaksi
// tersimpan. Sumber: tm2acc.fin_petty_cash_tx (+ tm2pur.po_line, query terpisah). $id = id transaksi.
final class PettyCashPrint extends Document
{
    public string $type = 'petty-cash';

    public string $title = 'Petty Cash Voucher';

    public string $numberLabel = 'Transaction No.';

    public array $refs = [
        ['key' => 'tx_type', 'label' => 'Kind'],
        ['key' => 'fund', 'label' => 'Cash Fund'],
        ['key' => 'cash_po', 'label' => 'Cash PO'],
        ['key' => 'advance', 'label' => 'Cash Advance', 'type' => 'money'],
        ['key' => 'journal', 'label' => 'Journal'],
    ];

    public string $partyLabel = 'Paid To';

    public string $nb = 'Attach the original receipts. The cash advance difference is returned to the fund or paid to the receiver.';

    public array $extra = [
        ['key' => 'code', 'label' => 'Code', 'width' => 17],
        ['key' => 'receipt_no', 'label' => 'Receipt No.', 'width' => 21, 'hand' => true],
    ];

    public bool $priced = true;

    public array $receivers = [['Receiver', 'Receives the money']];

    public static function load(int $id, array $opt = []): static
    {
        $tx = FinPettyCashTx::with('pettyCash')->findOrFail($id);
        $po = $tx->po_id ? PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->find($tx->po_id) : null;
        // Nilai transaksi per jenis: nota (Settlement / Reimburse) -> kasbon (Cash Advance) -> uang masuk kas (Top Up).
        // actual_amount dan advance_amount tidak pernah NULL (bawaan 0), jadi dipilih yang terisi, bukan lewat ??.
        $amount = (float) $tx->actual_amount ?: ((float) $tx->advance_amount ?: abs((float) $tx->cash_effect));
        $kind = self::t(FinPettyCashTx::TYPES[$tx->tx_type] ?? $tx->tx_type);
        $rows = $po ? $po->lines->map(fn ($l) => ['name' => $l->i_description, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom, 'code' => $l->c_item_code,
            'price' => (float) $l->i_price, 'subtotal' => (float) $l->c_line_net])->all() : [['name' => $kind, 'qty' => 1, 'unit' => '', 'price' => $amount, 'subtotal' => $amount]];

        return new self([
            'number' => $tx->tx_no, 'date' => $tx->tx_date?->toDateString(), 'status' => $tx->status, 'printedBy' => $opt['printedBy'] ?? null,
            'sums' => ['lines' => [], 'grand' => $amount],
            'tx_type' => $kind, 'fund' => $tx->pettyCash ? $tx->pettyCash->petty_name.' · '.$tx->pettyCash->petty_code : null, 'cash_po' => $tx->po_no,
            'advance' => $tx->advance_amount, 'journal' => $tx->voucher_id ? PettyCash::voucherNo($tx->voucher_id) : null,
            'party' => ['name' => $tx->receiver_name ?? '', 'lines' => PrintDocs::address($po?->i_shop_name)],
            'notes' => $tx->notes ? [['Notes', $tx->notes]] : [],
            'rows' => $rows,
        ]);
    }
}
