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
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
