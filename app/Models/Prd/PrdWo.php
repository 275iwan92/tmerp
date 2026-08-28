<?php

namespace App\Models\Prd;

use App\Models\Master\ProdProduct;
use App\Models\Sdi\CoOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrdWo extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_wo';

    protected $primaryKey = 'wo_id';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'wo_no', 'wo_type', 'source_type', 'source_co_id', 'source_forecast_id',
        'product_id', 'parts_id', 'part_code', 'source_delivery_id', 'source_iqp_part_id',
        'qty', 'due_date', 'material_needed_by',
        'wo_status', 'schedule_layer', 'is_hot_job', 'hot_job_source', 'hot_job_customer_id', 'priority',
        'confirmed_at', 'cancel_reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'material_needed_by' => 'datetime',
        'confirmed_at' => 'datetime',
        'is_hot_job' => 'boolean',
        'qty' => 'decimal:4',
    ];

    // Tier2 §1 — state machine WO. Guard wajib di service layer, bukan cuma UI.
    public const TRANSITIONS = [
        'DRAFT' => ['SIMULATED'],
        'SIMULATED' => ['SCHEDULED'],           // tidak boleh lompat ke IN_PROGRESS
        'SCHEDULED' => ['IN_PROGRESS', 'CANCELLED'],
        'IN_PROGRESS' => ['COMPLETED', 'ON_HOLD'],
        'ON_HOLD' => ['IN_PROGRESS', 'CANCELLED'],
        'COMPLETED' => [],                      // final
        'CANCELLED' => [],                      // final
    ];

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->wo_status] ?? [], true);
    }

    public function transitionTo(string $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw new \DomainException("Transisi status WO tidak sah: {$this->wo_status} -> {$target}");
        }

        $this->wo_status = $target;

        if ($target === 'SCHEDULED') {
            $this->schedule_layer = 'COMMITTED'; // Tier1 §5A.1 — submit = commit ke Master Schedule real
        }

        $this->save();
    }

    public function operations(): HasMany
    {
        return $this->hasMany(PrdWoOperation::class, 'wo_id', 'wo_id')->orderBy('seq_no');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(PrdWoMaterial::class, 'wo_id', 'wo_id')->orderBy('seq_no');
    }

    // Lintas-DB (tm2prd -> tm2sdi) -- query terpisah (Eloquent eager-load
    // batching), bukan join fisik. Dipakai Worklist WO utk resolve nama
    // customer: PrdWo -> co_order -> quo_quotation -> cust_customer (tm2mst).
    public function coOrder(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'source_co_id', 'id_order');
    }

    // Lintas-DB (tm2prd -> tm2mst) -- query terpisah, bukan join fisik.
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    // Penomoran WO -- pola sama CoOrder::nextCoNo()/QuoQuotation::nextQuotationNo().
    public static function nextWoNo(): string
    {
        $prefix = 'WO'.now()->format('y');

        $last = static::where('wo_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('wo_no')
            ->value('wo_no');

        $seq = $last ? ((int) substr($last, 4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
