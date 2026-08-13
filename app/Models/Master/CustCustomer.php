<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        'default_area_level',
        'postal_code',
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

    // default_area_id bisa merujuk geo_city/geo_district/geo_village tergantung
    // default_area_level (lihat FIX_MASTER_CUSTOMER_NAV_ADDRESS.md §B.2c) — bukan
    // relasi BelongsTo tunggal. Resolve label lewat v_geo_full_address, di-filter
    // supaya baris yang cocok konsisten dgn level yang tersimpan (village kosong
    // utk level DISTRICT, district kosong utk level CITY — lihat catatan grain).
    public function addressLabel(): ?string
    {
        if (! $this->default_area_id || ! $this->default_area_level) {
            return null;
        }

        $query = DB::connection('tm2mst')->table('v_geo_full_address');

        $query = match ($this->default_area_level) {
            'VILLAGE' => $query->where('id_village', $this->default_area_id),
            'DISTRICT' => $query->where('id_district', $this->default_area_id)->whereNull('id_village'),
            'CITY' => $query->where('id_city', $this->default_area_id)->whereNull('id_district'),
        };

        return $query->value('full_label');
    }

    // Tidak ada relasi balik ke iqp_inquiry (tm2sdi) di sini secara sengaja:
    // master tetap netral/tidak tahu modul mana saja yang mereferensinya (Tier 0 §1).
    // Arah relasi cross-DB selalu dari sisi transaksi -> master (lihat IqpInquiry::customer()).
}
