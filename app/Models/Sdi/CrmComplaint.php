<?php

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use App\Models\Prd\QcComplaintReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Komplain / retur penjualan (user 2026-10-02: "retur penjualan, informasinya harus ke sales, tetapi mutu kualitas masuk
// ke QA"). Dokumen ini milik Sales: Draft -> Confirmed (nomor lahir; bila needs_qa, muncul di meja QA) -> Closed (Sales
// mencatat penyelesaian) | Cancelled. Jawaban mutu = tm2prd.qc_complaint_review, diisi QA.
class CrmComplaint extends Model
{
    public const TYPES = ['COMPLAINT' => 'Complaint', 'RETURN' => 'Sales Return'];

    public const CATEGORIES = ['QUALITY' => 'Product quality', 'QUANTITY' => 'Quantity (short / over)', 'DELIVERY' => 'Delivery (late / wrong address)',
        'WRONG_ITEM' => 'Wrong item', 'DOCUMENT' => 'Document', 'SERVICE' => 'Service', 'OTHER' => 'Other'];

    public const REQUESTS = ['REPLACE' => 'Replace goods', 'REWORK' => 'Rework / repair', 'PRICE_CUT' => 'Price reduction', 'REFUND' => 'Refund', 'EXPLANATION' => 'Explanation only'];

    public const RESOLUTIONS = ['REPLACED' => 'Goods replaced', 'REWORKED' => 'Reworked / repaired', 'PRICE_CUT' => 'Price reduced', 'REFUNDED' => 'Refunded',
        'EXPLAINED' => 'Explained to customer', 'NOT_ACCEPTED' => 'Claim not accepted'];

    protected $connection = 'tm2sdi';

    protected $table = 'crm_complaint';

    protected $primaryKey = 'id_complaint';

    protected $guarded = ['id_complaint'];

    protected $casts = ['complaint_date' => 'date', 'needs_qa' => 'boolean', 'stat_confirm' => 'boolean', 'stat_close' => 'boolean', 'stat_cancel' => 'boolean',
        'confirmed_at' => 'datetime', 'closed_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(CrmComplaintItem::class, 'complaint_id', 'id_complaint')->orderBy('line_no');
    }

    // Lintas-DB (tm2sdi -> tm2mst / tm2prd): query terpisah lewat relasi Eloquent, bukan join.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'co_order_id', 'id_order');
    }

    public function review(): HasOne
    {
        return $this->hasOne(QcComplaintReview::class, 'complaint_id', 'id_complaint');
    }

    public function status(): string
    {
        return $this->stat_cancel ? 'CANCELLED' : ($this->stat_close ? 'CLOSED' : ($this->stat_confirm ? 'CONFIRMED' : 'DRAFT'));
    }

    // Tahap QA untuk komplain yang sudah Confirmed: NONE (tidak perlu QA) | WAITING | ANSWERED.
    public function qaStage(): string
    {
        return ! $this->needs_qa ? 'NONE' : ($this->review?->stat_confirm ? 'ANSWERED' : 'WAITING');
    }

    public static function nextNo(): string
    {
        $prefix = 'CC'.now()->format('y');
        $last = static::where('complaint_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('complaint_no')->value('complaint_no');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, 4)) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}
