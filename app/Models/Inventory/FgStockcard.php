<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class FgStockcard extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'fg_stockcard';

    protected $primaryKey = 'doc_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $fillable = [
        'previous_doc_id', 'doc_date', 'product_id', 'parts_id', 'doc_type',
        'location_id', 'warehouse_id', 'lot_no', 'qty', 'uom_code',
        'reference_detail_id', 'reference_name',
        'qty_bb', 'qty_in', 'qty_out', 'qty_adj', 'qty_eb', 'post_date',
        'value_bb', 'value_in', 'value_out', 'value_adj', 'value_eb', 'avg_unitprice', 'period_id',
        'batch_id', 'owner_type', 'owner_id',
        'create_by', 'update_by',
    ];

    protected $casts = [
        'doc_date' => 'datetime',
        'post_date' => 'date',
        'qty' => 'decimal:4',
        'qty_bb' => 'decimal:4',
        'qty_in' => 'decimal:4',
        'qty_out' => 'decimal:4',
        'qty_adj' => 'decimal:4',
        'qty_eb' => 'decimal:4',
    ];
}
