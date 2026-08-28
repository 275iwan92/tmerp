<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CustCustomer extends Model
{
    // Status pipeline (redesign UI 2026-08-19) — urutan tetap, dipakai untuk
    // render step action-bar. Label Indonesia, kode di kolom tetap Inggris.
    public const APPROVAL_STATUSES = [
        'DRAFT' => 'Draft',
        'CREDIT_CHECK' => 'Credit Check',
        'APPROVED' => 'Approved / Active',
        'BLOCKED' => 'Blocked',
    ];

    protected $connection = 'tm2mst';

    protected $table = 'cust_customer';

    protected $primaryKey = 'id_customer';

    public const CREDIT_RATINGS = [
        'A' => 'A — Low Risk',
        'B' => 'B — Medium Risk',
        'C' => 'C — High Risk',
    ];

    public const KYC_STATUSES = [
        'PENDING' => 'Menunggu Verifikasi',
        'APPROVED' => 'Terverifikasi',
        'REJECTED' => 'Ditolak',
    ];

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
        'activate',
        'created_by',
        'updated_by',
        'legacy_vendor_code',
        'nickname',
        'alias_code',
        'category',
        'is_blacklisted',
        'approval_status',
        'phone_2',
        'fax',
        'tax_purchase_value',
        'tax_service_value',
        'note',
        'trade_name',
        'parent_group',
        'industry_category',
        'primary_product',
        'secondary_product',
        'default_substrate',
        'color_standard',
        'proofing_method',
        'artwork_approval',
        'retained_sample',
        'barcode_verification',
        'finishing_preference',
        'die_owner',
        'packing_instruction',
        'quality_notes',
        'sales_division_id',
        'price_list_code',
        'tax_profile',
        'credit_check_enabled',
        'credit_rating',
        'tax_validated',
        'kyc_status',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_prospect' => 'boolean',
        'activate' => 'boolean',
        'is_blacklisted' => 'boolean',
        'tax_purchase_value' => 'decimal:2',
        'tax_service_value' => 'decimal:2',
        'credit_check_enabled' => 'boolean',
        'tax_validated' => 'boolean',
    ];

    // Same-DB (tm2mst): FK fisik product.customer_id -> cust_customer.id_customer.
    public function products(): HasMany
    {
        return $this->hasMany(ProdProduct::class, 'customer_id', 'id_customer');
    }

    public function banks(): HasMany
    {
        return $this->hasMany(CustCustomerBank::class, 'customer_id', 'id_customer');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(CustCustomerBranch::class, 'customer_id', 'id_customer');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustCustomerContact::class, 'customer_id', 'id_customer');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(FinPaymentTerm::class, 'payment_term', 'payment_term_code');
    }

    public function categoryRef(): BelongsTo
    {
        return $this->belongsTo(CustCategory::class, 'category', 'category_code');
    }

    public function salesDivision(): BelongsTo
    {
        return $this->belongsTo(OrgDivision::class, 'sales_division_id', 'division_id');
    }

    // default_area_id bisa merujuk geo_city/geo_district/geo_village tergantung
    // default_area_level (lihat customer_fix_nav_address.md §B.2c) — bukan
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
