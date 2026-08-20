<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoQuotationItem extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'quo_quotation_item';

    protected $primaryKey = 'id_item';

    protected $fillable = [
        'quotation_id',
        'line_seq',
        'source_type',
        'inquiry_id',
        'tier_id',
        'description',
        'spec_note',
        'qty',
        'qty_unit',
        'unit_price',
        'discount_pct',
        'subtotal',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'line_seq' => 'integer',
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'discount_pct' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(QuoQuotation::class, 'quotation_id', 'id_quotation');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    // tier_id -> iqp_tier: logis (snapshot), bukan FK fisik -- lihat komentar migration.
    public function tier(): BelongsTo
    {
        return $this->belongsTo(IqpTier::class, 'tier_id', 'id_tier');
    }

    public function recalcSubtotal(): void
    {
        $gross = (float) $this->qty * (float) $this->unit_price;
        $this->subtotal = round($gross * (1 - (float) $this->discount_pct / 100), 2);
    }
}
