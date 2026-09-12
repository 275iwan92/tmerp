<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;

class PoOrderLegacyDetail extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'po_order_legacy_detail';

    protected $primaryKey = 'id_po_legacy_detail';

    protected $fillable = [
        'po_legacy_id',
        'material_id',
        'legacy_item_code',
        'qty',
        'uom_code',
        'unit_price',
        'qty_unitprice',
    ];
}
