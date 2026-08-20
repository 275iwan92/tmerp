<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoOrderDelivery extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'co_order_delivery';

    protected $primaryKey = 'id_delivery';

    protected $fillable = [
        'order_id',
        'item_id',
        'shipment_type',
        'ship_date',
        'ship_qty',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ship_date' => 'date',
        'ship_qty' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'order_id', 'id_order');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CoOrderItem::class, 'item_id', 'id_item');
    }
}
