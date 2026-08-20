<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdMachine extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine';

    protected $primaryKey = 'machine_id';

    protected $fillable = [
        'division_id',
        'machine_code',
        'machine_name',
        'machine_type',
        'brand_model',
        'year_acquired',
        'serial_no',
        'max_paper_size',
        'color_count',
        'max_speed_per_hour',
        'capacity_per_shift',
        'gsm_min',
        'gsm_max',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'year_acquired' => 'integer',
        'color_count' => 'integer',
        'max_speed_per_hour' => 'integer',
        'gsm_min' => 'integer',
        'gsm_max' => 'integer',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(OrgDivision::class, 'division_id', 'division_id');
    }
}
