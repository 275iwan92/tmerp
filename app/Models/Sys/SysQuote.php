<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysQuote extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_quotes';

    public $timestamps = false;

    protected $fillable = [
        'quote_text',
        'author',
        'activate',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public static function random(): ?self
    {
        return self::query()->where('activate', true)->inRandomOrder()->first();
    }
}
