<?php

namespace App\Support\V2\Print;

use App\Models\Sdi\CoOrder;
use App\Models\Sys\SysConstant;
use Devlib\Locale;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\Auth;

// Customer Order (format A, user 2026-10-01): dua format, dengan harga dan tanpa harga (CustomerOrderNoPricePrint).
// Sumber: tm2sdi.co_order + co_order_item; total = CoOrder::totals() (sama dengan angka di form).
// CFG = konfigurasi warna part pertama inquiry asal; kolom Type belum punya sumber data (tercetak kosong).
class CustomerOrderPrint extends Document
{
    public string $type = 'customer-order';

    public string $title = 'Customer Order';

    public ?string $subtitle = 'Sales Order';

    public string $numberLabel = 'CO No.';

    public array $refs = [
        ['key' => 'customer', 'label' => 'Customer'],
        ['key' => 'customer_po_no', 'label' => 'Customer PO No.'],
        ['key' => 'quotation_no', 'label' => 'Quotation Ref.'],
        ['key' => 'payment_term', 'label' => 'Payment Terms'],
        ['key' => 'pay_to', 'label' => 'Payment Payable To'],
    ];

    public string $partyLabel = 'Ship To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'The order is binding once confirmed. Changes of quantity / specification through a written revision.';

    public string $nameLabel = 'Product Name';

    public array $extra = [
        ['key' => 'cfg', 'label' => 'CFG', 'width' => 8],
        ['key' => 'type', 'label' => 'Type', 'width' => 9],
        ['key' => 'due_date', 'label' => 'Ship Date', 'width' => 21, 'type' => 'date'],
    ];

    public bool $priced = true;

    public array $receivers = [['Ordered By', 'Customer']];

    // Tanpa harga: diminta lewat ?prices=0, atau dipaksa bila user tidak berhak melihat harga jual.
    public static function load(int $id, array $opt = []): static
    {
        $o = CoOrder::with(['customer', 'quotation:id_quotation,quotation_no', 'paymentTermRef', 'externalCosts',
            'items' => fn ($q) => $q->orderBy('line_seq'), 'items.inquiry.parts' => fn ($q) => $q->orderBy('part_seq')])->findOrFail($id);
        abort_unless($o->doc_status === 'CONFIRMED', 403, t('Print is available once the Customer Order is confirmed.'));
        $prices = ($opt['prices'] ?? true) && (Auth::user()?->canViewSalesPrice() ?? true);
        $class = $prices ? self::class : CustomerOrderNoPricePrint::class;
        $c = $o->customer;
        $approval = PrintDocs::approval('CO', $o->id_order, (float) $o->totals()['net_receivable']);

        return new $class([
            'number' => $o->co_no, 'date' => $o->co_date?->toDateString(), 'status' => $o->doc_status, 'printedBy' => $opt['printedBy'] ?? null,
            'approvedBy' => $approval['by'], 'ppn' => $o->is_ppn ? $o->totals()['ppn_pct'] : 0, 'totals' => $o->totals(),
            'customer' => $c?->customer_name, 'customer_po_no' => $o->customer_po_no, 'quotation_no' => $o->quotation?->quotation_no,
            'payment_term' => $o->paymentTermRef?->term_label ?? $o->payment_term,
            'pay_to' => implode(' · ', array_filter([SysConstant::text('BANK_ACCOUNT_NAME'), SysConstant::text('BANK_NAME'), SysConstant::text('BANK_ACCOUNT_NO')])),
            'contact' => implode(' · ', array_filter([$c?->contact_name, $c?->phone])),
            'party' => ['name' => $c?->customer_name ?? '', 'lines' => PrintDocs::address($o->ship_address_detail, $o->ship_address_label, $o->ship_postal_code)],
            'notes' => $o->notes ? [['Notes', $o->notes]] : [],
            'rows' => $o->items->map(fn ($it) => ['name' => $it->description, 'qty' => (float) $it->qty, 'unit' => $it->qty_unit,
                'cfg' => $it->inquiry?->parts->first()?->cfg_color, 'due_date' => $it->due_date?->toDateString(),
                'price' => (float) $it->unit_price, 'subtotal' => (float) $it->subtotal])->all(),
        ]);
    }

    // Angka total persis milik sistem (diskon dokumen + qty, biaya lain, PPN, PPh 23 jasa) supaya sama dengan form.
    public function totals(): array
    {
        if (! $this->priced) {
            return ['lines' => [], 'grand' => null];
        }
        $t = $this->data['totals'];
        $num = fn ($v) => Locale::number($v, 2);
        $pct = fn ($v) => Locale::decimal($v, 2).'%';
        $lines = [[self::t('Subtotal'), $num($t['item_subtotal']), false]];
        if ($t['discount_amount'] > 0) {
            $lines[] = [self::t('Discount'), '('.$num($t['discount_amount']).')', false];
        }
        if ($t['external_cost'] > 0) {
            $lines[] = [self::t('Other Costs'), $num($t['external_cost']), false];
        }
        $lines[] = [self::t('VAT').' '.$pct($t['ppn_pct']), $num($t['ppn']), false];
        if ($t['wht23'] > 0) {
            $lines[] = [self::t('Income Tax 23').' '.$pct($t['wht23_pct']), '('.$num($t['wht23']).')', false];
        }
        $lines[] = [self::t('Total').' ('.$this->currency.')', $num($t['net_receivable']), true];

        return ['lines' => $lines, 'grand' => (float) $t['net_receivable']];
    }
}
