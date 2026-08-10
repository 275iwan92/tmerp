<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpTooling extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_tooling';

    protected $primaryKey = 'id_tooling';

    protected $fillable = [
        'inquiry_id',
        'tooling_kind',
        'tooling_asset_id',
        'tooling_status',
        'is_new_charge',
        'cost',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_new_charge' => 'boolean',
        'cost' => 'decimal:4',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    // tooling_asset_id -> m_tooling_asset: master tooling belum digarap Tier 3A (prod_tooling_asset).
}
