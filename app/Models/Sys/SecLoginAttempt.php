<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SecLoginAttempt extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sec_login_attempt';

    protected $primaryKey = 'id_attempt';

    const CREATED_AT = 'attempted_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'login',
        'success',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];
}
