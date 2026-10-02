<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Baris Delivery Order -- kunci identitas co_order_item_id, TIDAK PERNAH
// digabung lintas baris (DISPATCH-02, no-merge rule). dispatch_tier3.md §3.
class DoLine extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'do_line';

    protected $primaryKey = 'id_line';

    protected $fillable = [
        'delivery_id', 'line_no',
        'co_order_id', 'co_order_item_id', 'co_delivery_id',
        'item_description', 'qty_do', 'qty_received', 'qty_unit', 'unit_price', 'subtotal',
        'price_overridden', 'original_unit_price',
        'qty_invoiced',
        'status', 'split_from_line_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'qty_do' => 'decimal:2',
        'qty_received' => 'decimal:2',   // diterima customer (Proof of Delivery); NULL = Surat Jalan belum kembali
        'unit_price' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'price_overridden' => 'boolean',
        'original_unit_price' => 'decimal:4',
        'qty_invoiced' => 'decimal:2',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(DoDelivery::class, 'delivery_id', 'id_delivery');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'co_order_id', 'id_order');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(CoOrderItem::class, 'co_order_item_id', 'id_item');
    }

    public function coDelivery(): BelongsTo
    {
        return $this->belongsTo(CoOrderDelivery::class, 'co_delivery_id', 'id_delivery');
    }

    // Baris CO berharga borongan (Qty Minim): nilai baris DO = harga, bukan qty x harga. Menagihnya hanya satu
    // kali adalah tugas invoice (docs/md_for_systems/customerinvoice_accounting.md bagian 7).
    public function recalcSubtotal(): void
    {
        $lumpSum = $this->co_order_item_id && $this->orderItem?->qty_minim;
        $this->subtotal = round($lumpSum ? (float) $this->unit_price : (float) $this->qty_do * (float) $this->unit_price, 2);
    }
}
