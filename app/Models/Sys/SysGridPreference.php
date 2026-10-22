<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Preferensi tampilan per user (dulu custom view/sort/group-by grid v1, keputusan user 2026-08-27; kini dipakai papan Shift Roster
// v2 untuk menyimpan shift yang disembunyikan: grid_key + visible_columns).
class SysGridPreference extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_grid_preference';

    protected $primaryKey = 'id_grid_preference';

    const CREATED_AT = null;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_login',
        'grid_key',
        'visible_columns',
        'sort_column',
        'sort_direction',
        'group_by_column',
    ];

    protected $casts = [
        'visible_columns' => 'array',
    ];
}
