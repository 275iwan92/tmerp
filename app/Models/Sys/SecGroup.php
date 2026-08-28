<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecGroup extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sec_group';

    protected $primaryKey = 'id_group';

    protected $fillable = [
        'group_code',
        'group_name',
        'mgmt_level',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'mgmt_level' => 'integer',
        'activate' => 'boolean',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(SecGroupPermission::class, 'group_id', 'id_group');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(SecUser::class, 'sec_user_group', 'group_id', 'user_id')
            ->withPivot('id_user_group');
    }
}
