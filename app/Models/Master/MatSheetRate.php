<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MatSheetRate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_sheet_rate';

    protected $primaryKey = 'id_sheet_rate';

    protected $fillable = [
        'substance_name',
        'flute_type',
        'index_substance',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'index_substance' => 'decimal:4',
        'activate' => 'boolean',
    ];
}
