<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpCostingDetail extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing_detail';

    protected $primaryKey = 'id_costing_detail';

    protected $fillable = [
        'costing_id',
        'tier_id',
        'part_id',
        'line_kind',
        'bucket_type',
        'ref_id',
        'ref_name',
        'qty',
        'rate_snapshot',
        'amount',
        'tolerance_pct',
        'margin_pct',
        'hpp_per_pc',
        'after_margin',
        'net_excl',
        'price_incl',
        'calc_trace',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'calc_trace' => 'array',
        'qty' => 'decimal:4',
        'rate_snapshot' => 'decimal:4',
        'amount' => 'decimal:4',
        'tolerance_pct' => 'decimal:4',
        'margin_pct' => 'decimal:4',
        'hpp_per_pc' => 'decimal:4',
        'after_margin' => 'decimal:4',
        'net_excl' => 'decimal:4',
        'price_incl' => 'decimal:4',
    ];

    public function costing(): BelongsTo
    {
        return $this->belongsTo(IqpCosting::class, 'costing_id', 'id_costing');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(IqpTier::class, 'tier_id', 'id_tier');
    }

    // part_id nullable (level tier/dokumen bila NULL) — FK DDL: ON DELETE SET NULL.
    public function part(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }

    // ref_id = FK generik snapshot ke master (mat_/prod_ rate) — tidak ada 1 model tetap
    // untuk dituju (tergantung bucket_type), jadi sengaja tidak dibuat relasi Eloquent.
}
