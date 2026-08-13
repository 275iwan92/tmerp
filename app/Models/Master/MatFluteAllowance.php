<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MatFluteAllowance extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_flute_allowance';

    protected $primaryKey = 'id_flute_allowance';

    protected $fillable = [
        'flute_type',
        'leb_panjang_mm',
        'leb_lebar_mm',
        'leb_tinggi_mm',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'leb_panjang_mm' => 'decimal:2',
        'leb_lebar_mm' => 'decimal:2',
        'leb_tinggi_mm' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Turunan (dihitung di CostingEngine, bukan di sini/VIEW — lihat §B.5):
    // total_p_mm = (leb_panjang_mm + leb_lebar_mm) * 2
    // total_l_mm = (leb_lebar_mm + leb_tinggi_mm) + 3
}
