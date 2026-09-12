<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Arsip riwayat PO dari tm2old.pur_po(_v2) -- read-only bagi aplikasi, sumber
// dashboard/laporan (mis. total AP per supplier). BUKAN po_order.
class PoOrderLegacy extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'po_order_legacy';

    protected $primaryKey = 'id_po_legacy';

    protected $fillable = [
        'legacy_po_no',
        'po_date',
        'supplier_id',
        'subtotal',
        'disc_amount',
        'ppn_amount',
        'grand_total',
        'legacy_status',
        'doc_status_normalized',
        'created_by',
    ];

    protected $casts = [
        'po_date' => 'date',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(PoOrderLegacyDetail::class, 'po_legacy_id', 'id_po_legacy');
    }
}
