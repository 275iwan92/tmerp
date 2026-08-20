<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Warna badge status kesiapan-build menu (module_status_system, GLOBAL_RULES.yaml)
// -- TERPISAH dari sys_theme (itu tema warna aplikasi keseluruhan, ini status
// per-menu: abu=belum dibangun, kuning=siap di-QC, hijau=terkunci user).
class SysAppMenuColor extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_app_menu_color';

    protected $primaryKey = 'status_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'status_code',
        'color_hex',
        'label',
        'is_clickable',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'is_clickable' => 'boolean',
        'updated_at' => 'datetime',
    ];
}
