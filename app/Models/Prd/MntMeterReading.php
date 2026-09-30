<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Angka counter mesin (jam jalan / impresi) = dasar pemicu plan METER.
class MntMeterReading extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_meter_reading';

    protected $primaryKey = 'id_meter_reading';

    protected $guarded = ['id_meter_reading'];

    protected $casts = ['reading_at' => 'datetime', 'reading_value' => 'float'];

    public static function latest(int $machineId, string $type): ?float
    {
        $v = static::query()->where('machine_id', $machineId)->where('meter_type', $type)->orderByDesc('reading_at')->value('reading_value');

        return $v === null ? null : (float) $v;
    }
}
