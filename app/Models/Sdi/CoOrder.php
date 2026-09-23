<?php

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SalesPerson;
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
        'entry_mode',
        'quotation_id',
        'customer_id',
        'sales_person_id',
        'payment_term',
        'currency_code',
        'is_ppn',
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
        'share_token',
        'share_expires_at',
        'shared_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'co_date' => 'date',
        'is_ppn' => 'boolean',
        'share_expires_at' => 'datetime',
        'shared_at' => 'datetime',
        'discount_doc_pct' => 'decimal:2',
        'discount_qty_pct' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(QuoQuotation::class, 'quotation_id', 'id_quotation');
    }

    // FK logis lintas-DB (tm2sdi -> tm2mst): relasi Eloquent biasa, query terpisah
    // yang dicocokkan di PHP (CLAUDE.md §2 pola 1) -- bukan join SQL.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(SalesPerson::class, 'sales_person_id', 'id_sales_person');
    }

    public function paymentTermRef(): BelongsTo
    {
        return $this->belongsTo(FinPaymentTerm::class, 'payment_term', 'payment_term_code');
    }

    public function isInstant(): bool
    {
        return $this->entry_mode === 'INSTANT';
    }

    // Baris CO yang belum layak Confirm. INSTANT wajib punya Master Product di
    // tiap baris PRODUCT (syarat mode, keputusan user 2026-09-21); INTEGRATED
    // tidak -- barisnya snapshot Quotation yang sudah pasti asal-usulnya.
    public function itemsMissingProduct()
    {
        if (! $this->isInstant()) {
            return $this->items->take(0);
        }

        return $this->items->where('line_kind', 'PRODUCT')->whereNull('product_id');
    }

    // Syarat Confirm yang sama dipakai di grid maupun layar entry: ada isi,
    // semua baris PRODUCT punya due_date (dasar jadwal CPDEL), dan -- khusus
    // mode INSTANT -- semua baris PRODUCT sudah terkait Master Product.
    public function isConfirmable(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->where('line_kind', 'PRODUCT')->whereNull('due_date')->isEmpty()
            && $this->itemsMissingProduct()->isEmpty();
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

    // Link publik nota CO untuk di-copy-paste ke customer (keputusan user
    // 2026-09-21). Token acak sekali pakai per CO, read-only, bisa dicabut
    // (revokeShareToken) -- sengaja BUKAN signed URL Laravel: link harus tetap
    // valid setelah di-paste ke WhatsApp/email berhari-hari, dan masa berlakunya
    // dikontrol dari DB supaya bisa dicabut tanpa ganti APP_KEY.
    public function ensureShareToken(int $validDays = 30): string
    {
        if (! $this->share_token) {
            $this->share_token = bin2hex(random_bytes(16));
        }

        $this->share_expires_at = now()->addDays($validDays);
        $this->shared_at = now();
        $this->save();

        return $this->share_token;
    }

    public function revokeShareToken(): void
    {
        $this->forceFill(['share_token' => null, 'share_expires_at' => null])->save();
    }

    public function shareLinkIsLive(): bool
    {
        return $this->share_token !== null
            && ($this->share_expires_at === null || $this->share_expires_at->isFuture());
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
