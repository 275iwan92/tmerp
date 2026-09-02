<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SysConstantCategory extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_constant_category';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

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
