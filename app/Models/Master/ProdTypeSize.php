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
        'size_code',
        'size_label',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProdType::class, 'type_code', 'type_code');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProdTypeSizeVal::class, 'type_size_id', 'id_type_size');
    }
}
