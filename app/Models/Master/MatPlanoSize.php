<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Dulu MPlanoSize (tabel m_plano_size) -- direname 2026-08-27 jadi
// mat_plano_size, satu keluarga dgn mat_grammage/mat_sheet_rate (spesifikasi
// fisik lembar kertas). Lihat migration 2026_08_27_000011.
class MatPlanoSize extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_plano_size';

    protected $primaryKey = 'id_plano_size';

    protected $fillable = [
        'plano_name',
        'width_mm',
        'height_mm',
        'sort_order',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
        'activate' => 'boolean',
    ];
}
