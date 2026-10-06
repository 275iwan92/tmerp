<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Syarat wajib data LEGACY sebelum boleh jadi LIVE (App\Services\Data\DataOriginService::computeReady). Diisi di Setup > Data Origin.
class SysDataRequirement extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_data_requirement';

    protected $primaryKey = 'id_requirement';

    protected $fillable = ['db_name', 'table_name', 'column_name', 'label', 'note', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
