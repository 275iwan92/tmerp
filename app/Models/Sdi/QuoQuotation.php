<?php

/*
 * REMARK
 * Tujuan       : Header Quotation; totals() menghitung subtotal, DPP, PPN (tarif pada tanggal terbit), grand total untuk Entry dan Print.
 * Sumber hulu  : quo_item, tax_scheme (EXCL/INCL), TaxRate::ppn(issued_date) (layar Tax Rate).
 * Input        : Baris quotation, issued_date, tax_scheme.
 * Output       : Array totals (subtotal, dpp, ppn, ppn_pct, grand).
 * Dipakai oleh : Quotation v2, Quotation Entry, QuotationPrint, CoOrderBuilder.
 * Status       : Aktif.
 */

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use App\Support\Finance\TaxRate;
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
        'stat_cancel',
        'cancel_reason',
        'issued_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'stat_cancel' => 'boolean',
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

    public function activities(): HasMany
    {
        return $this->hasMany(QuoActivity::class, 'quotation_id', 'id_quotation')->orderByDesc('activity_date')->orderByDesc('id_activity');
    }

    // Menang/kalah tetap disimpan di iqp_inquiry.sub_state (sumber layar Win-Rate
    // Sales): CONVERTED ditulis CoOrderBuilder, LOST ditulis markLost di sini.
    public function outcome(): ?string
    {
        $states = $this->items->pluck('inquiry.sub_state')->filter()->unique();

        return match (true) {
            $states->contains('CONVERTED') => 'WON',
            $states->isNotEmpty() && $states->every(fn ($s) => $s === 'LOST') => 'LOST',
            default => null,
        };
    }

    // Dipakai bersama oleh Quotation Entry (builder) & Quotation Print (dokumen resmi)
    // supaya perhitungan pajak selalu konsisten di 2 layar itu.
    public function totals(): array
    {
        $subtotal = (float) $this->items->sum('subtotal');
        $ppnPct = TaxRate::ppn($this->issued_date); // Master Data > Tax Rate menurut tanggal quotation, sama dengan CO & PO

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
