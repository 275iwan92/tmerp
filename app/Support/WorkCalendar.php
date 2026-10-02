<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Hari kerja kantor (user 2026-10-02): Sabtu kerja, Minggu libur, hari besar ikut Holiday Calendar.
// Hari tutup dibaca dari Office Hours (sys_office_hour.is_workday = 0), hari besar dari sys_holiday -- keduanya diubah user di layar.
final class WorkCalendar
{
    private array $holidays;

    private array $offDays;

    // Argumen hanya untuk test; kosong = baca Office Hours + Holiday Calendar.
    public function __construct(?array $holidays = null, ?array $offDays = null)
    {
        $this->holidays = array_flip($holidays ?? DB::connection('tm2mst')->table('sys_holiday')->where('activate', true)->pluck('holiday_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all());
        $this->offDays = $offDays ?? (DB::connection('tm2mst')->table('sys_office_hour')->where('is_workday', false)->where('activate', true)
            ->pluck('day_of_week')->map(fn ($d) => (int) $d)->all() ?: [7]);
    }

    public function isWorkday(CarbonImmutable $d): bool
    {
        return ! in_array($d->dayOfWeekIso, $this->offDays, true) && ! isset($this->holidays[$d->toDateString()]);
    }

    // Jumlah hari kerja sesudah $from sampai dengan $to (Jumat -> Senin = 2: Sabtu, Senin). $to <= $from = 0.
    public function workdaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $n = 0;
        for ($d = $from->startOfDay()->addDay(), $end = $to->startOfDay(); $d->lte($end); $d = $d->addDay()) {
            $n += (int) $this->isWorkday($d);
        }

        return $n;
    }
}
