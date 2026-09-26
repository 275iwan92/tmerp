<?php

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoQuotation extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'quo_quotation';

    protected $primaryKey = 'id_quotation';

    protected $fillable = [
        'quotation_no',
        'customer_id',
        'attn_contact',
        'sales_pic',
        'revision_no',
        'issued_date',
        'valid_until',
        'deadline_date',
        'payment_term',
        'tax_scheme',
        'currency_code',
        'notes',
        'doc_status',
        'issued_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'issued_date' => 'date',
        'valid_until' => 'date',
        'deadline_date' => 'date',
    ];

    // customer_id -> cust_customer (tm2mst): FK lintas-DB, relasi logis.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoQuotationItem::class, 'quotation_id', 'id_quotation')->orderBy('line_seq');
    }

    public function ccs(): HasMany
    {
        return $this->hasMany(QuoQuotationCc::class, 'quotation_id', 'id_quotation')->orderBy('line_seq');
    }

    // Dipakai bersama oleh Quotation Entry (builder) & Quotation Print (dokumen resmi)
    // supaya perhitungan pajak selalu konsisten di 2 layar itu.
    public function totals(): array
    {
        $subtotal = (float) $this->items->sum('subtotal');
        $ppnPct = 11.0;

        if ($this->tax_scheme === 'INCL') {
            $dpp = round($subtotal / (1 + $ppnPct / 100), 2);
            $ppn = round($subtotal - $dpp, 2);
            $grand = $subtotal;
        } else {
            $dpp = $subtotal;
            $ppn = round($dpp * $ppnPct / 100, 2);
            $grand = $dpp + $ppn;
        }

        return ['subtotal' => $subtotal, 'dpp' => $dpp, 'ppn' => $ppn, 'ppn_pct' => $ppnPct, 'grand' => $grand];
    }

    // Penomoran selaras pola IqpInquiry::nextInquiryNo(): QUO+YY+NNNNN, counter reset tiap tahun.
    public static function nextQuotationNo(): string
    {
        $prefix = 'QUO'.now()->format('y');

        $last = static::where('quotation_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('quotation_no')
            ->value('quotation_no');

        $seq = $last ? ((int) substr($last, 5)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
