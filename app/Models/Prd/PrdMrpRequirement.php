<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Baris per material hasil netting satu Engine MRP run -- qty_required
// (dari prd_wo_material) vs stok on-hand snapshot (tm2inv.v_rm_onhand) saat
// run, dan shortage yang dihasilkan (basis PR draft bila > 0).
class PrdMrpRequirement extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2prd';

    protected $table = 'prd_mrp_requirement';

    protected $primaryKey = 'requirement_id';

    protected $fillable = [
        'mrp_run_id', 'material_id', 'qty_required', 'qty_onhand_snapshot', 'qty_shortage',
    ];

    protected $casts = [
        'qty_required' => 'decimal:4',
        'qty_onhand_snapshot' => 'decimal:4',
        'qty_shortage' => 'decimal:4',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PrdMrpRun::class, 'mrp_run_id', 'mrp_run_id');
    }
}
