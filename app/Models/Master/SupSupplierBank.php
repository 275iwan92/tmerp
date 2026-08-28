<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupSupplierBank extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_supplier_bank';

    protected $primaryKey = 'id_supplier_bank';

    protected $fillable = [
        'supplier_id',
        'bank_code',
        'bank_account_no',
        'bank_account_name',
        'activate',
        'legacy_vendor_bank_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupSupplier::class, 'supplier_id', 'id_supplier');
    }
}
