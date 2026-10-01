<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SysConstantCategory extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_constant_category';

    // PK = id_constant_category (BIGINT auto number, aturan PK 2026-09-30); code = kode unik yang dirujuk sys_constant.category_code.
    protected $primaryKey = 'id_constant_category';

    protected $fillable = [
        'code',
        'label',
        'description',
        'sort_order',
        'activate',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function constants(): HasMany
    {
        return $this->hasMany(SysConstant::class, 'category_code', 'code');
    }
}
