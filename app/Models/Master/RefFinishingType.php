<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class RefFinishingType extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'ref_finishing_type';

    protected $primaryKey = 'id_finishing_type';

    protected $fillable = [
        'finishing_name',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
