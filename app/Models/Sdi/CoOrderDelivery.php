<?php

namespace App\Models\Sdi;

use App\Models\Prd\PrdWo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'status',
        'split_from_id',
        'posted_at',
        'posted_by',
        'original_date',
        'ship_time',
        'ready_at',
        'ready_source',
        'ready_by',
        'escalated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ship_date' => 'date',
        'ship_qty' => 'decimal:2',
        'posted_at' => 'datetime',
        'original_date' => 'date',
        'ready_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function negotiations(): HasMany
    {
        return $this->hasMany(CoDeliveryNegotiation::class, 'delivery_id', 'id_delivery')->orderByDesc('created_at')->orderByDesc('id_negotiation');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'order_id', 'id_order');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CoOrderItem::class, 'item_id', 'id_item');
    }

    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_id', 'id_delivery');
    }

    // Lintas-DB (tm2sdi -> tm2prd) -- query terpisah, bukan join fisik.
    // docs/production_scheduling_wo_generation_delta.md §1 -- WoGenerationService
    // mengisi prd_wo.source_delivery_id, dipakai Screen #1 (wo-generate.blade.php)
    // cegah baris yang sudah digenerate muncul lagi di picklist.
    public function generatedWos(): HasMany
    {
        return $this->hasMany(PrdWo::class, 'source_delivery_id', 'id_delivery');
    }
}
