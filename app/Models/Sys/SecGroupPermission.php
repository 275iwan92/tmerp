<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecGroupPermission extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sec_group_permission';

    protected $primaryKey = 'id_permission';

    protected $fillable = [
        'group_id',
        'module_id',
        'can_access',
        'can_input',
        'can_approve',
        'can_export',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'can_access' => 'boolean',
        'can_input' => 'boolean',
        'can_approve' => 'boolean',
        'can_export' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SecGroup::class, 'group_id', 'id_group');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(SecModule::class, 'module_id', 'id_module');
    }
}
