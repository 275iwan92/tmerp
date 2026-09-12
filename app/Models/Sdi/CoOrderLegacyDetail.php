<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;

class CoOrderLegacyDetail extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'co_order_legacy_detail';

    protected $primaryKey = 'id_co_legacy_detail';

    protected $fillable = [
        'co_legacy_id',
        'product_id',
        'legacy_item_code',
        'qty',
        'uom_code',
        'unit_price',
        'qty_unitprice',
    ];
}
