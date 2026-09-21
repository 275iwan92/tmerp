<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinAssetCategory extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_asset_category';

    protected $primaryKey = 'category_id';

    protected $guarded = ['category_id'];

    protected $casts = ['activate' => 'boolean'];
}
