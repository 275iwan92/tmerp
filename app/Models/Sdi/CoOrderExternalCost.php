<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoOrderExternalCost extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'co_order_external_cost';

    protected $primaryKey = 'id_cost';

    protected $fillable = [
        'order_id',
        'category',
        'description',
        'nominal',
        'tax_treatment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'order_id', 'id_order');
    }
}
