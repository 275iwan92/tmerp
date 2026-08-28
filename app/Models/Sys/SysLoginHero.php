<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Hero foto panel login (Tier 3B-S3) — analog sys_quotes, dipilih acak
// server-side per pemuatan halaman login.
class SysLoginHero extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_login_hero';

    public $timestamps = false;

    protected $fillable = [
        'image_url',
        'caption',
        'sort_order',
        'activate',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function random(): ?self
    {
        return self::query()->where('activate', true)->inRandomOrder()->first();
    }
}
