<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Routing/urutan proses per part BAKU (reusable, Master) -- dipakai ProDev
// (docs/inquiry_product_costing/productdevelopment_tier1.md §4.2a poin 1).
// Tabel ini SUDAH ADA sejak DDL awal TIER_3A (bukan hasil migration baru
// 2026-09-02, lihat catatan migration 2026_09_02_000022) dgn skema simetris
// iqp_part_process (tm2sdi, snapshot transaksional) -- branch_code/flow_type
// dipertahankan default MAIN/LINEAR utk pemakaian ProDev sekarang (belum
// perlu SPLIT/MERGE di level master).
class ProdPartProcess extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part_process';

    protected $primaryKey = 'id_part_process';

    protected $fillable = [
        'part_id',
        'branch_code',
        'step_seq',
        'flow_type',
        'process_id',
        'process_name',
        'machine_id',
        'layer_ref',
        'qty_factor',
        'note',
        'created_by',
        'updated_by',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(ProdPart::class, 'part_id', 'id_part');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }
}
