<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Konstanta sistem/aplikasi, key-value (docs/configurationstandard.md) --
// pindah dari tm2mst 2026-09-02 (lokasi lama cuma kebetulan historis, isinya
// konfigurasi sistem: PPN/PPh, info bank, parameter costing, identitas app --
// bukan master data bisnis). category_code = tab tampilan di layar
// Configuration, constant_group = sub-label lama (tetap dipakai kode existing
// spt CostingEngine).
class SysConstant extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_constant';

    protected $primaryKey = 'id_constant';

    protected $fillable = [
        'constant_key',
        'category_code',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(SysConstantCategory::class, 'category_code', 'code');
    }

    public static function number(string $key, float $default = 0.0): float
    {
        return (float) (static::query()->where('constant_key', $key)->where('activate', true)->value('constant_number') ?? $default);
    }

    public static function text(string $key, ?string $default = null): ?string
    {
        return static::query()->where('constant_key', $key)->where('activate', true)->value('constant_text') ?? $default;
    }
}
