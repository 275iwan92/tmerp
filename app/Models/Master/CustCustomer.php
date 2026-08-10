<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustCustomer extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'cust_customer';

    protected $primaryKey = 'id_customer';

    protected $fillable = [
        'customer_code',
        'customer_name',
        'tax_code',
        'mr_ms',
        'contact_name',
        'default_address',
        'default_area_id',
        'email',
        'phone',
        'website',
        'customer_type',
        'payment_term',
        'currency_code',
        'credit_limit',
        'current_balance',
        'is_prospect',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_prospect' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Same-DB (tm2mst): FK fisik product.customer_id -> cust_customer.id_customer.
    public function products(): HasMany
    {
        return $this->hasMany(ProdProduct::class, 'customer_id', 'id_customer');
    }

    // Tidak ada relasi balik ke iqp_inquiry (tm2sdi) di sini secara sengaja:
    // master tetap netral/tidak tahu modul mana saja yang mereferensinya (Tier 0 §1).
    // Arah relasi cross-DB selalu dari sisi transaksi -> master (lihat IqpInquiry::customer()).
}
