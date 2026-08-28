<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class ProdPartType extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part_type';

    protected $primaryKey = 'id_part_type';

    protected $fillable = [
        'part_type_code',
        'part_type_label',
        'pisau_rate_rp',
        'pakai_laminasi',
        'is_engine_ready',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'pisau_rate_rp' => 'decimal:4',
        'pakai_laminasi' => 'boolean',
        'is_engine_ready' => 'boolean',
        'activate' => 'boolean',
    ];
}
