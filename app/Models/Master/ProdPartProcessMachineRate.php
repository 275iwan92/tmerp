<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Target rate default (qty/jam) per produk+part+proses+MESIN -- dipakai
// WoGenerationService sbg nilai awal rate_band_value tiap prd_wo_operation
// (selalu bisa di-override PPIC di WO Detail). Lihat
// docs/production_scheduling_wo_generation_delta.md §4.2 -- dikunci ke
// product_id+part_code (teks), BUKAN FK prod_part yang tak pernah diisi.
class ProdPartProcessMachineRate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part_process_machine_rate';

    protected $primaryKey = 'rate_id';

    protected $fillable = [
        'product_id', 'part_code', 'process_id', 'machine_id',
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

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function scopeActive($query)
    {
        return $query->where('activate', true);
    }
}
