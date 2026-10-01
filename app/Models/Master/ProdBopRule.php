<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Aturan Bill of Process (tm2mst.prod_bop_rule): sumber engine BOP / BOM ProDev (App\Services\ProDev\SpecEngine).
// Model ini untuk MEMBACA (grid v2); aturan simpan tetap di layar pages::production.bop-rule (hanya admin).
class ProdBopRule extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_bop_rule';

    protected $primaryKey = 'id_bop_rule';

    protected $guarded = ['id_bop_rule'];

    protected $casts = ['activate' => 'boolean', 'auto_apply' => 'boolean', 'step_seq' => 'integer'];

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProdProcess::class, 'process_id', 'id_process');
    }

    public function finishing(): BelongsTo
    {
        return $this->belongsTo(RefFinishingType::class, 'finishing_type_id', 'id_finishing_type');
    }
}
