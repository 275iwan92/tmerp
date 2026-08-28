<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdPart extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_part';

    protected $primaryKey = 'id_part';

    protected $fillable = [
        'product_id',
        'part_code',
        'part_name',
        'part_type',
        'qty_per_sku',
        'description',
        'uom_stock',
        'cfg_color',
        'cfg_ink',
        'min_stock',
        'max_stock',
        'cur_stock',
        'activate',
        'stock_asset_account_id',
        'cogs_account_id',
        'cost_center_id',
        'profit_center_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }
}
