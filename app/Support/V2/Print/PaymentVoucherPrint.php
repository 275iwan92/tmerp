<?php

namespace App\Support\V2\Print;

use App\Models\Acc\FinVoucher;
use Devlib\PrintDoc\Document;

// Bukti kas / bank (belum A; backlog01 UI-19 "Pembayaran Pembelian"): satu lembar per voucher Cash & Bank -- kas / bank
// masuk dan keluar. Baris = baris jurnal voucher (akun + uraian, Debit / Credit); total = jumlah debit voucher.
// Kop PPN bila voucher bertanda PPN (ppn_type). Sumber: tm2acc.fin_voucher + fin_voucher_line + fin_account.
final class PaymentVoucherPrint extends Document
{
    private const TITLES = ['BKM' => 'Cash Receipt Voucher', 'BKK' => 'Cash Payment Voucher', 'BBM' => 'Bank Receipt Voucher', 'BBK' => 'Bank Payment Voucher'];

    public string $type = 'payment-voucher';

    public string $title = 'Journal Voucher';

    public string $numberLabel = 'Voucher No.';

    public array $refs = [
        ['key' => 'account', 'label' => 'Cash / Bank Account'],
        ['key' => 'reference', 'label' => 'Reference'],
        ['key' => 'tax_invoice_no', 'label' => 'Tax Invoice No.'],
        ['key' => 'withholding_no', 'label' => 'Withholding Slip No.'],
        ['key' => 'posted', 'label' => 'Posted', 'type' => 'date'],
    ];

    public string $partyLabel = 'Paid To';

    public string $nb = 'Attach the supporting documents (invoice, receipt, transfer slip).';

    public string $nameLabel = 'Account';

    public bool $qtyCols = false;

    public array $extra = [
        ['key' => 'code', 'label' => 'Account Code', 'width' => 24],
        ['key' => 'narration', 'label' => 'Description', 'width' => 50, 'wrap' => true],
    ];

    public bool $priced = true;

    public array $receivers = [['Receiver', 'Receives the money']];

    protected function priceColumns(): array
    {
        return [['key' => 'debit', 'label' => 'Debit', 'width' => 23, 'type' => 'money'], ['key' => 'credit', 'label' => 'Credit', 'width' => 23, 'type' => 'money']];
    }

    public static function load(int $id, array $opt = []): static
    {
        $v = FinVoucher::query()->with(['lines.account', 'mainAccount'])->findOrFail($id);
        $incoming = in_array($v->voucher_type, ['BKM', 'BBM'], true);

        $doc = new self([
            'number' => $v->voucher_number ?: 'Draft #'.$v->voucher_id, 'date' => $v->entry_date?->toDateString(), 'status' => $v->stat_cancel ? 'CANCELLED' : $v->status,
            'printedBy' => $opt['printedBy'] ?? null, 'ppn' => $v->ppn_type && $v->ppn_type !== 'NONE' ? 1 : 0,
            'sums' => ['lines' => [], 'grand' => $v->totalAmount()],
            'account' => $v->mainAccount ? $v->mainAccount->account_code.' '.$v->mainAccount->account_name : null, 'reference' => $v->reference_no,
            'tax_invoice_no' => $v->efaktur_no, 'withholding_no' => $v->bupot_no, 'posted' => $v->posted_at?->toDateString(),
            'party' => ['name' => $v->partner_name ?? '', 'lines' => []],
            'notes' => $v->description ? [['Notes', $v->description]] : [],
            // Kolom kosong dicetak kosong (bukan 0,00) supaya sisi debit / kredit langsung terbaca.
            'rows' => $v->lines->map(fn ($l) => ['name' => $l->account?->account_name, 'code' => $l->account?->account_code, 'narration' => $l->narration,
                'debit' => (float) $l->debit ?: null, 'credit' => (float) $l->credit ?: null])->all(),
        ]);
        $doc->title = self::TITLES[$v->voucher_type] ?? 'Journal Voucher';
        if ($incoming) {
            $doc->partyLabel = 'Received From';
            $doc->receivers = [['Depositor', 'Hands over the money']];
        }

        return $doc;
    }
}
