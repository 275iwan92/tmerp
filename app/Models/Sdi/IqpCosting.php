<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqpCosting extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing';

    protected $primaryKey = 'id_costing';

    protected $fillable = [
        'inquiry_id',
        'doc_status',
        'rounding_mode',
        'ppn_mode',
        'published_flag',
        'is_approved',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'published_flag' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // 1:1 dengan iqp_inquiry (UNIQUE index uq_costing_inquiry).
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    public function details(): HasMany
    {
        return $this->hasMany(IqpCostingDetail::class, 'costing_id', 'id_costing');
    }
}
