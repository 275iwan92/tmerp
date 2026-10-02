<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Katalog widget opening screen. Baris lahir otomatis dari kelas widget (App\Support\V2\Board\WidgetCatalog); judul, kelompok,
// modul, level minimum, frasa asisten, dan aktif / tidak dirawat user di Setup > Opening Board Widgets.
class SysWidget extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_widget';

    protected $primaryKey = 'id_widget';

    protected $fillable = ['widget_key', 'title', 'description', 'group_name', 'module_code', 'min_level', 'default_size', 'phrases', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean', 'min_level' => 'integer', 'default_size' => 'integer'];
}
