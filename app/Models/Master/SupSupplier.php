<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupSupplier extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sup_supplier';

    protected $primaryKey = 'id_supplier';

    protected $fillable = [
        'supplier_code',
        'supplier_name',
        'nickname',
        'alias_code',
        'category',
        'is_subcon',
        'contact_name',
        'default_address',
        'default_area_id',
        'default_area_level',
        'postal_code',
        'email',
        'phone',
        'phone_2',
        'fax',
        'website',
        'payment_term',
        'tax_code',
        'tax_purchase_value',
        'tax_service_value',
        'is_blacklisted',
        'note',
        'is_active',
        'legacy_vendor_code',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_subcon' => 'boolean',
        'is_blacklisted' => 'boolean',
        'is_active' => 'boolean',
        'tax_purchase_value' => 'decimal:2',
        'tax_service_value' => 'decimal:2',
    ];

    public function banks(): HasMany
    {
        return $this->hasMany(SupSupplierBank::class, 'supplier_id', 'id_supplier');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(SupSupplierBranch::class, 'supplier_id', 'id_supplier');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupSupplierContact::class, 'supplier_id', 'id_supplier');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(FinPaymentTerm::class, 'payment_term', 'payment_term_code');
    }

    public function categoryRef(): BelongsTo
    {
        return $this->belongsTo(SupCategory::class, 'category', 'category_code');
    }
}
