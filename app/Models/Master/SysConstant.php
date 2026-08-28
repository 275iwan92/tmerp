<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class SysConstant extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sys_constant';

    protected $primaryKey = 'id_constant';

    protected $fillable = [
        'constant_key',
        'constant_group',
        'constant_number',
        'constant_text',
        'description',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'constant_number' => 'decimal:6',
        'activate' => 'boolean',
    ];

    public static function number(string $key, float $default = 0.0): float
    {
        return (float) (static::query()->where('constant_key', $key)->where('activate', true)->value('constant_number') ?? $default);
    }

    public static function text(string $key, ?string $default = null): ?string
    {
        return static::query()->where('constant_key', $key)->where('activate', true)->value('constant_text') ?? $default;
    }
}
