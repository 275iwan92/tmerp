<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Tanda papan user sudah dibuat dari template (satu baris per user); tanpa baris ini papan disalin dari template.
class SysUserBoard extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_user_board';

    protected $primaryKey = 'id_user_board';

    protected $fillable = ['user_id', 'profile_code', 'period_code', 'dashboard_code'];

    protected $casts = [];
}
