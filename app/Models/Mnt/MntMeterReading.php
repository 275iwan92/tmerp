<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

// Angka counter aset (jam jalan, impresi, odometer) = dasar pemicu plan berbasis meter. Pembacaan tidak pernah ditimpa;
// koreksi counter fisik = baris baru.
class MntMeterReading extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_meter_reading';

    protected $primaryKey = 'id_meter_reading';

    protected $guarded = ['id_meter_reading'];

    protected $casts = ['reading_at' => 'datetime', 'reading_value' => 'float'];

    public const SOURCES = ['MANUAL' => 'Typed by user', 'PRODUCTION' => 'Production output'];

    public static function latestValue(int $assetId, string $type): ?float
    {
        $v = static::query()->where('asset_id', $assetId)->where('meter_type', $type)->orderByDesc('reading_at')->orderByDesc('id_meter_reading')->value('reading_value');

        return $v === null ? null : (float) $v;
    }
}
