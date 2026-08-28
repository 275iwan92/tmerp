<?php

namespace App\Models\Sdi;

use App\Models\Master\MatPlanoSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpLayout extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_layout';

    protected $primaryKey = 'id_layout';

    protected $fillable = [
        'part_id',
        'flat_w_mm',
        'flat_h_mm',
        'gap_mm',
        'plano_size_id',
        'plano_w_mm',
        'plano_h_mm',
        'gripper_mm',
        'margin_mm',
        'method',
        'up_count',
        'efficiency_pct',
        'waste_pct',
        'layout_detail',
        'stat_confirm',
        'confirmed_by',
        'confirmed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'flat_w_mm' => 'decimal:2',
        'flat_h_mm' => 'decimal:2',
        'gap_mm' => 'decimal:2',
        'plano_w_mm' => 'decimal:2',
        'plano_h_mm' => 'decimal:2',
        'gripper_mm' => 'decimal:2',
        'margin_mm' => 'decimal:2',
        'up_count' => 'integer',
        'efficiency_pct' => 'decimal:2',
        'waste_pct' => 'decimal:2',
        'layout_detail' => 'array',
        'stat_confirm' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }

    // plano_size_id -> mat_plano_size (tm2mst): FK lintas-DB, relasi logis.
    public function planoSize(): BelongsTo
    {
        return $this->belongsTo(MatPlanoSize::class, 'plano_size_id', 'id_plano_size');
    }
}
