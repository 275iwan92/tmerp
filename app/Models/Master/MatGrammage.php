<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MatGrammage extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_grammage';

    protected $primaryKey = 'id_grammage';

    protected $fillable = [
        'grammage_name',
        'grammage_value',
        'paper_kind',
        'duplex_price_list',
        'duplex_dc_pct',
        'ivory_ap_price_per_kg',
        'effective_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'grammage_value' => 'decimal:2',
        'duplex_price_list' => 'decimal:4',
        'duplex_dc_pct' => 'decimal:4',
        'ivory_ap_price_per_kg' => 'decimal:4',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];
}
