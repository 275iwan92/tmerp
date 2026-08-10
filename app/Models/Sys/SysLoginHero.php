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
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function random(): ?self
    {
        return self::query()->where('is_active', true)->inRandomOrder()->first();
    }
}
