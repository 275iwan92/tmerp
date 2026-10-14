<?php

/*
 * REMARK
 * Tujuan       : Model baris bahan WO (prd_wo_material).
 * Sumber hulu  : Ditulis oleh WoGenerationService dari BOM SpecEngine; diedit di tab Bill of Material.
 * Input        : qty_required (Required), qty_standar (angka engine), qty_release, qty_purchase, override_reason, process_id.
 * Output       : Baris bahan per WO, termasuk proses BOP yang memakainya.
 * Dipakai oleh : Ppic::bomSheet, GateService, PpicController::bomCells.
 * Status       : Aktif; kolom process/release/purchase baru hanya di ddt* sampai rilis.
 */

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrdWoMaterial extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_wo_material';

    protected $primaryKey = 'detail_id';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'wo_id', 'seq_no', 'material_id', 'material_role', 'process_id', 'material_name', 'uom', 'qty_required', 'qty_standar', 'qty_release', 'qty_purchase', 'override_reason',
        'is_substituted', 'substitute_material_id', 'substitute_reason',
        'substitute_approved_by', 'substitute_approved_at', 'cutting_yield_waste_qty',
        'waste_engine_qty', 'spill_engine_qty', 'waste_override_qty', 'spill_override_qty',
        'material_needed_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_substituted' => 'boolean',
        'substitute_approved_at' => 'datetime',
        'material_needed_by' => 'datetime',
        'qty_required' => 'decimal:4',
        'qty_standar' => 'decimal:4',
        'cutting_yield_waste_qty' => 'decimal:4',
    ];

    public function wo(): BelongsTo
    {
        return $this->belongsTo(PrdWo::class, 'wo_id', 'wo_id');
    }
}
