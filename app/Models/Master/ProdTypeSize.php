<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdTypeSize extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_type_size';

    protected $primaryKey = 'id_type_size';

    protected $fillable = [
        'type_code',
        'market_id',
        'size_code',
        'size_label',
        'sort_order',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProdType::class, 'type_code', 'type_code');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(ProdMarket::class, 'market_id', 'id_market');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProdTypeSizeVal::class, 'type_size_id', 'id_type_size');
    }
}
