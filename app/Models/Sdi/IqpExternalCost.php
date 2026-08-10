<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpExternalCost extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_external_cost';

    protected $primaryKey = 'id_external_cost';

    protected $fillable = [
        'inquiry_id',
        'cost_kind',
        'description',
        'amount',
        'tax_treatment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }
}
