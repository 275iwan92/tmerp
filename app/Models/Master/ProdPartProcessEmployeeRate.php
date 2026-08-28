<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Sama seperti ProdPartProcessMachineRate tapi utk task MANUAL (per-orang) --
// docs/production_scheduling_wo_generation_delta.md §4.3.
class ProdPartProcessEmployeeRate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part_process_employee_rate';

    protected $primaryKey = 'rate_id';

    protected $fillable = [
        'product_id', 'part_code', 'process_id', 'employee_id',
        'target_qty_per_hour', 'activate', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'target_qty_per_hour' => 'decimal:4',
        'activate' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('activate', true);
    }
}
