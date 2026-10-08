<?php

namespace App\Models\Inventory;

use App\Models\Master\WhLoc;
use App\Models\Master\ProdPart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SfgStockD extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'sfg_stock_d';

    protected $primaryKey = 'doc_detail_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $fillable = [
        'doc_id', 'seq_no', 'parts_id', 'product_id', 'qty', 'qty_returned',
        'qty_system', 'qty_physical', 'action_type', 'return_condition', 'source_detail_id',
        'uom_code', 'lot_no', 'unit_price', 'qty_unitprice', 'location_id', 'cancel_reason',
        'batch_id', 'owner_type', 'owner_id',
        'create_by', 'update_by',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'qty_returned' => 'decimal:4',
        'qty_system' => 'decimal:4',
        'qty_physical' => 'decimal:4',
        'unit_price' => 'decimal:4',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(ProdPart::class, 'parts_id', 'id_part');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WhLoc::class, 'location_id', 'location_id');
    }
}
