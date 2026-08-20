<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoOrder extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'co_order';

    protected $primaryKey = 'id_order';

    protected $fillable = [
        'co_no',
        'co_date',
        'quotation_id',
        'customer_po_no',
        'ship_area_id',
        'ship_area_level',
        'ship_address_label',
        'ship_address_detail',
        'ship_postal_code',
        'discount_doc_pct',
        'discount_qty_pct',
        'doc_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'co_date' => 'date',
        'discount_doc_pct' => 'decimal:2',
        'discount_qty_pct' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(QuoQuotation::class, 'quotation_id', 'id_quotation');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CoOrderItem::class, 'order_id', 'id_order')->orderBy('line_seq');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CoOrderDelivery::class, 'order_id', 'id_order');
    }

    public function externalCosts(): HasMany
    {
        return $this->hasMany(CoOrderExternalCost::class, 'order_id', 'id_order');
    }

    // Penomoran selaras pola IqpInquiry::nextInquiryNo()/QuoQuotation::nextQuotationNo(): CO+YY+NNNNN.
    public static function nextCoNo(): string
    {
        $prefix = 'CO'.now()->format('y');

        $last = static::where('co_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('co_no')
            ->value('co_no');

        $seq = $last ? ((int) substr($last, 4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function totals(): array
    {
        $productSubtotal = (float) $this->items->where('line_kind', 'PRODUCT')->sum('subtotal');
        $serviceSubtotal = (float) $this->items->where('line_kind', 'SERVICE')->sum('subtotal');
        $itemSubtotal = $productSubtotal + $serviceSubtotal;

        $docDiscPct = (float) ($this->discount_doc_pct ?? 0);
        $qtyDiscPct = (float) ($this->discount_qty_pct ?? 0);
        $totalDiscPct = $docDiscPct + $qtyDiscPct;
        $discountAmount = round($itemSubtotal * $totalDiscPct / 100, 2);

        $externalCostTotal = (float) $this->externalCosts->sum('nominal');

        $dpp = $itemSubtotal - $discountAmount + $externalCostTotal;
        $ppnPct = 11.0;
        $ppn = round($dpp * $ppnPct / 100, 2);
        $totalBilling = $dpp + $ppn;

        // PPh 23 (jasa) 2% dari nilai baris SERVICE sebelum diskon dokumen -- konvensi
        // umum PPh 23 Indonesia utk jasa, dipotong pembeli dari tagihan penjual jasa.
        $wht23Pct = 2.0;
        $wht23 = round($serviceSubtotal * $wht23Pct / 100, 2);

        $netReceivable = $totalBilling - $wht23;

        return [
            'item_subtotal' => $itemSubtotal,
            'discount_amount' => $discountAmount,
            'external_cost' => $externalCostTotal,
            'dpp' => $dpp,
            'ppn_pct' => $ppnPct,
            'ppn' => $ppn,
            'total_billing' => $totalBilling,
            'wht23_pct' => $wht23Pct,
            'wht23' => $wht23,
            'net_receivable' => $netReceivable,
        ];
    }
}
