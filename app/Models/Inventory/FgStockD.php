<?php

namespace App\Models\Inventory;

use App\Models\Master\MWhLocation;
use App\Models\Master\ProdProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FgStockD extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'fg_stock_d';

    protected $primaryKey = 'doc_detail_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $fillable = [
        'doc_id', 'seq_no', 'product_id', 'parts_id', 'qty', 'qty_returned',
        'qty_system', 'qty_physical', 'action_type', 'return_condition', 'source_detail_id',
        'uom_code', 'lot_no', 'unit_price', 'qty_unitprice', 'location_id',
        'create_by', 'update_by',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'qty_returned' => 'decimal:4',
        'qty_system' => 'decimal:4',
        'qty_physical' => 'decimal:4',
        'unit_price' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MWhLocation::class, 'location_id', 'location_id');
    }
}
