<?php

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IqpCosting extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_costing';

    protected $primaryKey = 'id_costing';

    // Mode INSTANT = kalkulator standalone (inquiry_id NULL, identitas isi manual
    // customer_id/product_name_draft). Mode INTEGRATED = tetap 1:1 inquiry
    // (customer_id/product_name_draft snapshot dari inquiry saat dibuat/di-reverse).
    public const MODE_INSTANT = 'INSTANT';

    public const MODE_INTEGRATED = 'INTEGRATED';

    protected $fillable = [
        'doc_no',
        'doc_date',
        'inquiry_id',
        'mode',
        'customer_id',
        'customer_name_draft',
        'product_name_draft',
        'reversed_at',
        'reversed_by',
        'calc_type',
        'i_dim_panjang_mm',
        'i_dim_lebar_mm',
        'i_dim_tinggi_mm',
        'i_lidah_lem_mm',
        'total_material_cost_pc',
        'total_process_cost_pc',
        'total_finishing_cost_pc',
        'c_tooling_cost_order',
        'doc_status',
        'rounding_mode',
        'ppn_mode',
        'ppn_pct',
        'currency_code',
        'calc_notes',
        'published_flag',
        'is_approved',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'doc_date' => 'date',
        'reversed_at' => 'datetime',
        'i_dim_panjang_mm' => 'decimal:2',
        'i_dim_lebar_mm' => 'decimal:2',
        'i_dim_tinggi_mm' => 'decimal:2',
        'i_lidah_lem_mm' => 'decimal:2',
        'total_material_cost_pc' => 'decimal:4',
        'total_process_cost_pc' => 'decimal:4',
        'total_finishing_cost_pc' => 'decimal:4',
        'c_tooling_cost_order' => 'decimal:4',
        'ppn_pct' => 'decimal:2',
        'published_flag' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // 1:1 dengan iqp_inquiry (UNIQUE index uq_costing_inquiry). NULL utk mode INSTANT.
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    // customer_id = LOGICAL FK lintas-DB (tm2sdi -> tm2mst), bukan constraint fisik.
    // Snapshot: mode INTEGRATED diisi dari inquiry saat dibuat/di-reverse, mode
    // INSTANT diisi manual officer.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    public function isInstant(): bool
    {
        return $this->mode === self::MODE_INSTANT;
    }

    public function isIntegrated(): bool
    {
        return $this->mode === self::MODE_INTEGRATED;
    }

    public function parts(): HasMany
    {
        return $this->hasMany(IqpCostingPart::class, 'costing_id', 'id_costing')->orderBy('no_urut');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(IqpCostingPriceTier::class, 'costing_id', 'id_costing');
    }

    public function stateLogs(): HasMany
    {
        return $this->hasMany(IqpCostingStateLog::class, 'costing_id', 'id_costing')->orderByDesc('changed_at');
    }
}
