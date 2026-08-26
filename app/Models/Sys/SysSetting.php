<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Setting umum aplikasi, key-value (setting_key PK). Dipakai a.l. untuk toggle
// notify_channel (security_defaultpassword_spec.md §5) — manual/email/whatsapp.
class SysSetting extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_setting';

    protected $primaryKey = 'setting_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];
}
