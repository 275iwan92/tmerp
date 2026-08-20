<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupSupplierContact extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_supplier_contact';

    protected $primaryKey = 'id_supplier_contact';

    protected $fillable = [
        'supplier_id',
        'contact_name',
        'position',
        'phone',
        'email',
        'is_default',
        'is_active',
        'note',
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
