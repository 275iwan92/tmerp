<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Susunan papan milik satu user: widget terpasang, urutan, lebar (3 / 4 / 6 / 8 / 12 dari 12 kolom).
class SysUserWidget extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_user_widget';

    protected $primaryKey = 'id_user_widget';

    protected $fillable = ['user_id', 'widget_key', 'sort_order', 'size', 'created_by', 'updated_by'];

    protected $casts = ['size' => 'integer', 'sort_order' => 'integer'];
}
