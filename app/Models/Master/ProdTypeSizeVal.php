<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdTypeSizeVal extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_type_size_val';

    protected $primaryKey = 'id_size_val';

    protected $fillable = [
        'type_size_id',
        'param_key',
        'value_num',
        'value_str',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'value_num' => 'decimal:3',
    ];

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProdTypeSize::class, 'type_size_id', 'id_type_size');
    }
}
