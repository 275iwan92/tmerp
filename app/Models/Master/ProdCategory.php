<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class ProdCategory extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_category';

    protected $primaryKey = 'id_category';

    protected $fillable = [
        'category_name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
