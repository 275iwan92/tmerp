<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysNotification extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_notifications';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'link',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    // user_id = sec_user.login (bukan FK fisik — kolomnya varchar bebas, dipakai
    // juga utk NULL=broadcast semua user, lihat komentar tabel di docs/tm2sys.sql).
    public function scopeForUser($query, string $login)
    {
        return $query->where(function ($q) use ($login) {
            $q->whereNull('user_id')->orWhere('user_id', $login);
        });
    }
}
