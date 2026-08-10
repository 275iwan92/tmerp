<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Layer TAMPILAN menu (mega menu / sidebar / command palette) — TERPISAH dari
// sec_module (unit IZIN, LOCKED 2 level). Baris menu_type=module menaut ke
// sec_module.module_code lewat required_object; group/item mewarisi
// visibilitas dari leluhur module-nya (lihat SecUser::visibleAppMenuModules()).
class SysAppMenu extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_app_menu';

    protected $primaryKey = 'id';

    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'parent_id',
        'code',
        'label',
        'trans_key',
        'icon',
        'menu_type',
        'object_type',
        'target',
        'target_kind',
        'sort_order',
        'is_active',
        'required_object',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')->orderBy('sort_order');
    }
}
