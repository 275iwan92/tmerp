<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class UomConversion extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'uom_conversion';

    protected $primaryKey = 'id_conversion';

    protected $fillable = [
        'uom_src',
        'uom_dst',
        'conversion_value',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'conversion_value' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    // uom_src/uom_dst nyimpan uom_code (VARCHAR) langsung, bukan FK id_uom
    // (sesuai DDL asli TIER_3A_MASTER_inquiry.sql) — jadi tidak ada relasi
    // Eloquent BelongsTo ke UomUnit, cukup query manual by uom_code saat perlu.
}
