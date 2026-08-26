<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Preset tema tampilan (module_status_system TIDAK terkait -- ini tema warna
// APLIKASI keseluruhan, lihat queue.md #8/#9). primary_color/sidebar_color
// dipetakan ke CSS var runtime lewat App\Support\ActiveTheme + partials/theme-vars.
class SysTheme extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_theme';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'primary_color',
        'sidebar_color',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
