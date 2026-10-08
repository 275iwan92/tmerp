<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Lembar cek operator per aset per shift, dibuat dari watch point operator yang aktif. NOT_OK saat Submit membuka Maintenance Request.
class MntDailyCheck extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_daily_check';

    protected $primaryKey = 'id_daily_check';

    protected $guarded = ['id_daily_check'];

    protected $casts = ['check_date' => 'date', 'submitted_at' => 'datetime'];

    public const SHIFTS = ['1' => 'Shift 1', '2' => 'Shift 2', '3' => 'Shift 3'];

    public function lines(): HasMany
    {
        return $this->hasMany(MntDailyCheckLine::class, 'daily_check_id', 'id_daily_check')->orderBy('id_daily_check_line');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MntAsset::class, 'asset_id', 'id_asset');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'SUBMITTED';
    }
}
