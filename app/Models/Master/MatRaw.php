<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MatRaw extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_raw';

    protected $primaryKey = 'id_material';

    protected $fillable = [
        'material_code',
        'material_name',
        'item_type',
        'category1',
        'category2',
        'category3',
        'description',
        'brand',
        'color',
        'gram_weight',
        'cm_length',
        'cm_width',
        'mm_thickness',
        'specification',
        'uom_stock',
        'uom_purchase',
        'uom_usage',
        'conversion_to_stock',
        'conversion_to_usage',
        'purchasable',
        'avg_unitprice',
        'last_unitprice',
        'min_stock',
        'max_stock',
        'cur_stock',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchasable' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Tidak ada relasi balik ke iqp_part_material (tm2sdi, rawmat_id) — sengaja,
    // master tetap netral. Lihat IqpPartMaterial::rawMaterial().
}
