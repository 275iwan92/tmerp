<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Item dropdown avatar (topbar) yang data-driven -- BUKAN sys_app_menu
// (itu sidebar/mega-menu modul). Cuma menaungi item FLAT sederhana (drawer/
// modal/menu/url toggle 1 aksi); widget kompleks (Theme matrix, Bahasa,
// grup Configuration) TETAP hardcode di main-menu.blade.php -- terlalu
// bespoke utk direpresentasikan sbg baris generik (keputusan 2026-09-03,
// lihat BACKLOG.md §10 riwayat "QUEUE item #9").
class SysUserMenu extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_user_menu';

    protected $primaryKey = 'id_umenu';

    const UPDATED_AT = null;

    protected $fillable = [
        'section',
        'code',
        'label',
        'icon',
        'action_type',
        'action_target',
        'badge_source',
        'visibility',
        'is_danger',
        'admin_only',
        'sort_order',
        'activate',
    ];

    protected $casts = [
        'is_danger' => 'boolean',
        'admin_only' => 'boolean',
        'sort_order' => 'integer',
        'activate' => 'boolean',
    ];
}
