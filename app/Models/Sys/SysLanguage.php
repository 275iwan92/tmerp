<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysLanguage extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_languages';

    // PK BIGINT auto number sejak 2026-09-30 (pk_bigint_phase2); kode bahasa = kolom UNIQUE.
    protected $primaryKey = 'id_language';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'intl_locale',
        'is_default',
        'decimal_separator',
        'thousand_separator',
        'activate',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'activate' => 'boolean',
    ];
}
