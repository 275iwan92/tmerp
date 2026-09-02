<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysLanguage extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_languages';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_default',
        'decimal_separator',
        'thousand_separator',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
