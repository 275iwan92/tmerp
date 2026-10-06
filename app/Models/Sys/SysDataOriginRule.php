<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Aturan klasifikasi DUMMY / LEGACY (App\Services\Data\DataOriginService::classify). Diisi di Setup > Data Origin.
class SysDataOriginRule extends Model
{
    public const TYPES = ['CREATED_BY' => 'Created by matches pattern', 'COLUMN_LIKE' => 'Column value matches pattern', 'COLUMN_FILLED' => 'Column is filled'];

    protected $connection = 'tm2sys';

    protected $table = 'sys_data_origin_rule';

    protected $primaryKey = 'id_rule';

    protected $fillable = ['match_type', 'db_name', 'table_name', 'column_name', 'match_value', 'origin', 'priority', 'note', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean', 'priority' => 'integer'];
}
