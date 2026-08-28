<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecModule extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sec_module';

    protected $primaryKey = 'id_module';

    protected $fillable = [
        'parent_id',
        'module_code',
        'module_name',
        'icon',
        'route_prefix',
        'min_mgmt_level',
        'is_reportable',
        'sort_order',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_reportable' => 'boolean',
        'activate' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id_module');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id_module')->orderBy('sort_order');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(SecGroupPermission::class, 'module_id', 'id_module');
    }
}
