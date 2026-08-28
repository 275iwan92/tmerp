<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class OrgLevel extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'org_level';

    protected $primaryKey = 'level_id';

    protected $fillable = [
        'level_code',
        'level_name',
        'level_rank',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'level_rank' => 'integer',
    ];
}
