<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysMailSetting extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_mail_setting';

    protected $primaryKey = 'id_mail_setting';

    protected $fillable = ['mailer', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name',
        'from_user', 'require_verified', 'created_by', 'updated_by'];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
        'from_user' => 'boolean',
        'require_verified' => 'boolean',
    ];
}
