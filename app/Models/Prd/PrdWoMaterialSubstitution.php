<?php

namespace App\Models\Prd;

use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Detail/histori substitusi bahan per baris Bill of Material (master =
// tm2sdi.iqp_part_material, snapshot Inquiry yang tetap beku -- lihat komentar
// migration). Cuma 1 baris `is_active=true` yang berlaku per
// iqp_part_material_id di satu waktu -- itu yang dipakai override tampilan
// BOM di WO Detail (wo-entry.blade.php).
class PrdWoMaterialSubstitution extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_wo_material_substitution';

    protected $fillable = [
        'wo_id', 'iqp_part_material_id', 'original_rawmat_id',
        'substitute_rawmat_id', 'substitute_rawmat_name',
        'substitute_length_mm', 'substitute_width_mm',
        'computed_up_count', 'computed_efficiency_pct', 'computed_waste_pct', 'computed_qty_output_pcs',
        'original_unit_cost', 'substitute_unit_cost', 'total_cost_delta',
        'reason', 'approval_status', 'approved_by', 'approved_at', 'rejection_reason',
        'is_active', 'undone_by', 'undone_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'substitute_length_mm' => 'decimal:2',
        'substitute_width_mm' => 'decimal:2',
        'computed_up_count' => 'integer',
        'computed_efficiency_pct' => 'decimal:2',
        'computed_waste_pct' => 'decimal:2',
        'computed_qty_output_pcs' => 'decimal:4',
        'original_unit_cost' => 'decimal:5',
        'substitute_unit_cost' => 'decimal:5',
        'total_cost_delta' => 'decimal:5',
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
        'undone_at' => 'datetime',
    ];

    public function wo(): BelongsTo
    {
        return $this->belongsTo(PrdWo::class, 'wo_id', 'wo_id');
    }

    // Lintas-DB (tm2prd -> tm2mst) -- query terpisah, bukan join fisik.
    public function substituteMaterial(): BelongsTo
    {
        return $this->belongsTo(MatRaw::class, 'substitute_rawmat_id', 'material_id');
    }
}
