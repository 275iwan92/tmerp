<?php

namespace App\Models\Sdi;

use App\Models\Master\ProdProduct;
use App\Services\Sales\CoDueDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoOrderItem extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'co_order_item';

    protected $primaryKey = 'id_item';

    protected $fillable = [
        'order_id',
        'line_seq',
        'quotation_item_id',
        'line_kind',
        'inquiry_id',
        'product_id',
        'description',
        'spec_note',
        'qty',
        'qty_unit',
        'qty_min',
        'qty_max',
        'qty_minim',
        'unit_price',
        'discount_pct',
        'subtotal',
        'due_date',
        'due_source',
        'due_note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'qty_min' => 'decimal:2',
        'qty_max' => 'decimal:2',
        'qty_minim' => 'boolean',
        'unit_price' => 'decimal:4',
        'discount_pct' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'due_date' => 'date',
    ];

    // Due date otomatis dari ACC Digital produk (CO direct: tanggal CO) + CO_LEAD_WORKDAYS hari kerja (CoDueDate) kecuali Sales menimpanya (due_source MANUAL).
    // Mengosongkan tanggal = kembali ke otomatis.
    protected static function booted(): void
    {
        static::saving(function (self $i) {
            if ($i->line_kind !== 'PRODUCT' || ! $i->product_id) {
                return;
            }
            $auto = CoDueDate::suggest($i);
            if ($i->isDirty('due_date')) {
                if ($i->due_date) {
                    $i->due_source = $auto && $i->due_date->isSameDay($auto) ? 'ACC' : 'MANUAL';

                    return;
                }
                $i->due_source = null; // dikosongkan = kembali otomatis
            }
            if ($i->due_source !== 'MANUAL' && $auto) {
                $i->due_date = $auto;
                $i->due_source = 'ACC';
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CoOrder::class, 'order_id', 'id_order');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    // FK logis lintas-DB ke tm2mst.prod_product -- wajib terisi utk baris
    // PRODUCT di CO mode INSTANT.
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    // quotation_item_id -> quo_quotation_item: logis (snapshot), bukan FK fisik.
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuoQuotationItem::class, 'quotation_item_id', 'id_item');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CoOrderDelivery::class, 'item_id', 'id_item');
    }

    // Qty Minim = harga borongan (sama dengan sistem lama sdi_co_detail): nilai baris = harga, berapa pun qty-nya.
    public function recalcSubtotal(): void
    {
        $gross = $this->qty_minim ? (float) $this->unit_price : (float) $this->qty * (float) $this->unit_price;
        $this->subtotal = round($gross * (1 - (float) $this->discount_pct / 100), 2);
    }
}
