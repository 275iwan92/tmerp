<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecUserGroup extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sec_user_group';

    protected $primaryKey = 'id_user_group';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'group_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(SecUser::class, 'user_id', 'id_user');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SecGroup::class, 'group_id', 'id_group');
    }
}
