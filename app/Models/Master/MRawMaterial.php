<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MRawMaterial extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'm_raw_material';

    protected $primaryKey = 'material_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $fillable = [
        'material_code', 'material_name', 'item_type',
        'category1', 'category2', 'category3', 'category4', 'category5',
        'description', 'brand', 'color', 'gram_weight', 'cm_length', 'cm_width', 'mm_thickness', 'specification',
        'uom_stock', 'uom_purchase', 'purchasable', 'uom_usage',
        'conversion_to_stock', 'conversion_to_usage',
        'suggest_supplier_id', 'tol_purchase', 'free_note',
        'min_stock', 'max_stock', 'cur_stock', 'is_active',
        'avg_unitprice', 'last_unitprice', 'def_location_id',
        'costing_method', 'is_fx_sensitive', 'prefix5_id',
        'create_by', 'update_by',
    ];
}
