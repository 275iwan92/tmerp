<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Arsip riwayat CO dari tm2old.sdi_co -- read-only bagi aplikasi, sumber
// dashboard/laporan (mis. total belanja per customer). BUKAN co_order.
class CoOrderLegacy extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'co_order_legacy';

    protected $primaryKey = 'id_co_legacy';

    protected $fillable = [
        'legacy_co_no',
        'co_date',
        'customer_id',
        'customer_po_no',
        'subtotal',
        'disc_amount',
        'ppn_amount',
        'grand_total',
        'legacy_status',
        'doc_status_normalized',
        'created_by',
    ];

    protected $casts = [
        'co_date' => 'date',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(CoOrderLegacyDetail::class, 'co_legacy_id', 'id_co_legacy');
    }
}
