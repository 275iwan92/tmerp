<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Hari libur (docs/BACKLOG.md 2026-09-02) -- referensi lintas-domain
// (Attendance/lembur H-7/H-1, Production Scheduling kalender kerja, dst),
// belum ada 1 modul pun yang membacanya (dibangun duluan sbg fondasi).
class SysHoliday extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sys_holiday';

    protected $primaryKey = 'id_holiday';

    protected $fillable = [
        'holiday_date',
        'holiday_name',
        'holiday_type',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'activate' => 'boolean',
    ];

    public static function isHoliday(string $date): bool
    {
        return static::query()->whereDate('holiday_date', $date)->where('activate', true)->exists();
    }
}
