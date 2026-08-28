<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Dulu MWarehouse (tabel m_warehouse) -- direname 2026-08-27, prefix domain
// baru `wh_` (dipakai lintas modul Sales/Procurement/Inventory, tetap di
// tm2mst). Lihat migration 2026_08_27_000011.
class WhWarehouse extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'wh_warehouse';

    protected $primaryKey = 'id_warehouse';

    protected $fillable = [
        'warehouse_code', 'warehouse_name', 'warehouse_type', 'activate',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(WhLoc::class, 'warehouse_id', 'id_warehouse')->orderBy('row_code')->orderBy('col_code')->orderBy('stack_code');
    }
}
