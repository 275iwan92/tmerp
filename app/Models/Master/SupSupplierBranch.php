<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupSupplierBranch extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_supplier_branch';

    protected $primaryKey = 'id_supplier_branch';

    protected $fillable = [
        'supplier_id',
        'branch_name',
        'address',
        'area_id',
        'area_level',
        'postal_code',
        'phone',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupSupplier::class, 'supplier_id', 'id_supplier');
    }
}
