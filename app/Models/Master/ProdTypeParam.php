<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdTypeParam extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_type_param';

    protected $primaryKey = 'id_type_param';

    protected $fillable = [
        'type_code',
        'param_key',
        'param_label',
        'param_type',
        'option_json',
        'is_required',
        'default_value',
        'default_str',
        'min_value',
        'max_value',
        'unit',
        'seq',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'option_json' => 'array',
        'is_required' => 'boolean',
        'default_value' => 'decimal:2',
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'activate' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProdType::class, 'type_code', 'type_code');
    }
}
