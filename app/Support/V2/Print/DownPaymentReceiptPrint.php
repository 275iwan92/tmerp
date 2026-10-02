<?php

namespace App\Support\V2\Print;

use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinVoucher;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Tanda Terima Uang Muka (belum A; user 2026-10-02): bukti untuk customer bahwa uang mukanya sudah diterima.
// TIDAK punya tabel sendiri -- uang yang diterima sudah tercatat sebagai voucher kas / bank saat pembayaran DP dicatat;
// lembar ini mencetak pembayaran itu: nomor tanda terima = nomor vouchernya, id = id tagihan uang muka.
// Sumber: tm2acc.fin_invoice (CUSTOMER_DP, sudah ada pembayaran) + fin_voucher (CUSTOMER_DP_PAYMENT / alokasi pembayaran)
// + tm2mst.cust_customer + tm2sdi.co_order (query terpisah).
final class DownPaymentReceiptPrint extends Document
{
    public string $type = 'dp-receipt';

    public string $title = 'Down Payment Receipt';

    public string $numberLabel = 'DP Receipt No.';

    public array $refs = [
        ['key' => 'invoice_no', 'label' => 'Invoice No.'],
        ['key' => 'co_no', 'label' => 'CO Ref.'],
        ['key' => 'customer_po_no', 'label' => 'Customer PO No.'],
        ['key' => 'account', 'label' => 'Cash / Bank Account'],
        ['key' => 'outstanding', 'label' => 'Invoice Balance', 'type' => 'money'],
    ];

    public string $partyLabel = 'Received From';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'We have received the down payment for the following order:';

    public bool $nbPlain = true;

    public string $nameLabel = 'Description';

    public bool $qtyCols = false;

    public array $extra = [
        ['key' => 'co_ref', 'label' => 'CO No.', 'width' => 24],
        ['key' => 'order_value', 'label' => 'Order Value', 'width' => 32, 'type' => 'money'],
        ['key' => 'percent', 'label' => 'Percent', 'width' => 18],
    ];

    public bool $priced = true;

    public array $receivers = [['Customer', 'Hands over the money']];

    public array $signLabels = ['approve' => '', 'know' => 'Acknowledged By', 'make' => 'Receiver'];

    public array $signNotes = ['approve' => '', 'know' => '', 'make' => 'Finance'];

    protected function priceColumns(): array
    {
        return [['key' => 'amount', 'label' => 'Received', 'width' => 46, 'type' => 'money']];
    }

    // Voucher penerimaan uang untuk tagihan DP ini: yang dicatat layar Down Payment, kalau tidak ada = alokasi pembayaran terakhir.
    public static function voucher(FinInvoice $inv): ?FinVoucher
    {
        return FinVoucher::query()->with('lines.account')->where('source_type', 'CUSTOMER_DP_PAYMENT')->where('source_id', $inv->invoice_id)
            ->where('stat_cancel', 0)->orderByDesc('voucher_id')->first()
            ?? $inv->allocations()->with('voucher.lines.account')->orderByDesc('alloc_id')->first()?->voucher;
    }

    public static function load(int $id, array $opt = []): static
    {
        $inv = FinInvoice::query()->where('invoice_type', 'CUSTOMER_DP')->findOrFail($id);
        $paid = (float) $inv->paid_amount;
        abort_unless($paid > 0, 422, 'This down payment has not been received yet.');
        $v = self::voucher($inv);
        $cust = CustCustomer::withoutGlobalScopes()->find($inv->partner_id);
        $co = $inv->co_id ? CoOrder::query()->with(['items', 'externalCosts'])->find($inv->co_id) : null;
        $order = $co ? (float) ($co->totals()['total_billing'] ?? 0) : 0.0;
        $bank = $v?->lines->first(fn ($l) => (float) $l->debit > 0)?->account;

        return new self([
            'number' => $v?->voucher_number ?: $inv->invoice_number, 'date' => ($v?->entry_date ?? $inv->updated_at)?->toDateString(), 'status' => 'RECEIVED',
            'printedBy' => $opt['printedBy'] ?? null, 'ppn' => (float) $inv->tax_amount > 0 ? 1 : 0, 'sums' => ['lines' => [], 'grand' => $paid],
            'invoice_no' => $inv->invoice_number, 'co_no' => $co?->co_no, 'customer_po_no' => $co?->customer_po_no,
            'account' => $bank ? $bank->account_code.' '.$bank->account_name : null,
            'outstanding' => $inv->outstanding() > 0 ? $inv->outstanding() : null,
            'contact' => implode(' · ', array_filter([$cust?->contact_name, $cust?->phone])), 'email' => $cust?->email,
            'party' => ['name' => $cust?->customer_name ?? '', 'code' => $cust?->customer_code, 'lines' => PrintDocs::address($cust?->default_address)],
            'rows' => [['name' => $inv->description ?: self::t('Down payment'), 'co_ref' => $co?->co_no, 'order_value' => $order > 0 ? $order : null,
                'percent' => $order > 0 ? Locale::decimal((float) $inv->total_amount / $order * 100, 2).'%' : null, 'amount' => $paid]],
        ]);
    }
}
