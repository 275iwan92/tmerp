<?php

namespace App\Services\Scheduling;

use App\Models\Master\SysHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;

/**
 * Machine Calendar — jam kerja efektif per mesin per shift.
 * Sumber: production_scheduling_tier1.md §4 istilah baku.
 *
 * FIX 2026-09-03 (docs/BACKLOG.md §6, 2 bagian):
 * 1. Hari libur nasional -- `sys_holiday` (dibangun 2026-09-02, sebelumnya
 *    "belum ada 1 modul pun yang membacanya") kini dipakai di `isWorkingDay()`.
 * 2. Kesadaran shift per-MESIN -- kalau `machineId` diberikan ke
 *    `subtractWorkingTime()`, jam kerja hari itu diambil dari
 *    `emp_shift_roster` (siapa dijadwalkan di mesin ini hari itu) + jam
 *    shift dari `emp_shift_detail` (union rentang terluas dari semua shift
 *    yang di-roster hari itu -- BUKAN penjumlahan window terpisah persis
 *    per shift, disederhanakan sengaja, lihat catatan di `machineWorkingWindow()`).
 *    Kalau mesin itu TAK PERNAH punya 1 pun baris roster (belum pakai
 *    fitur roster), fallback ke jam default global (backward-compatible).
 *    Kalau mesin PUNYA histori roster tapi genuinely kosong di tanggal itu,
 *    dianggap hari libur mesin tsb (bukan default, staffing-driven).
 *    `machineId` null (dipanggil tanpa mesin, mis. task MANUAL) = perilaku
 *    lama apa adanya.
 */
class MachineCalendarService
{
    private readonly Carbon $shiftStart;

    private readonly Carbon $shiftEnd;

    // Jendela Maintenance (order yang menghentikan mesin) ikut mengurangi jam kerja mesin.
    public function __construct(private readonly MaintenanceBlockProvider $maintenance = new MaintenanceBlockProvider)
    {
        // Diisi ulang per-hari di dalam method, ini cuma template jam.
        $this->shiftStart = Carbon::createFromTimeString(
            config('production_scheduling.default_shift_start', '08:00')
        );
        $this->shiftEnd = Carbon::createFromTimeString(
            config('production_scheduling.default_shift_end', '20:00')
        );
    }

    /**
     * Geser suatu titik waktu MUNDUR sejauh $duration, melompati jam non-kerja.
     * Dipakai Backward Scheduling (Tier2 §4.1) untuk hitung target_start dari target_finish.
     */
    public function subtractWorkingTime(Carbon $from, CarbonInterval $duration, ?int $machineId = null): Carbon
    {
        $remaining = $duration->copy();
        $cursor = $from->copy();

        // Guard infinite loop kalau durasi absurd besar / kalender salah konfigurasi
        $safetyCounter = 0;

        while ($remaining->totalSeconds > 0 && $safetyCounter < 1000) {
            $safetyCounter++;

            $window = $machineId ? $this->machineWorkingWindow($cursor, $machineId) : null;

            if ($window === false) {
                // Mesin ini pakai roster tapi genuinely tak ada shift hari ini -- hari libur mesin.
                $cursor = $cursor->copy()->subDay()->setTimeFrom($this->shiftEnd);

                continue;
            }

            $dayShiftStart = $window ? $window['start'] : $cursor->copy()->setTimeFrom($this->shiftStart);
            $dayShiftEnd = $window ? $window['end'] : $cursor->copy()->setTimeFrom($this->shiftEnd);

            if (! $this->isWorkingDay($cursor)) {
                $cursor = $cursor->copy()->subDay()->setTimeFrom($this->shiftEnd);

                continue;
            }

            if ($cursor->lte($dayShiftStart)) {
                // Sudah di luar jam kerja (sebelum shift mulai) -> lompat ke hari kerja sebelumnya
                $cursor = $cursor->copy()->subDay()->setTimeFrom($this->shiftEnd);

                continue;
            }

            $windowStart = $cursor->gt($dayShiftEnd) ? $dayShiftEnd : $cursor;

            // Maintenance: kursor di dalam jendela -> lompat ke awalnya; jendela di bawah kursor -> hanya waktu di atasnya yang terpakai, lalu lompat ke awal jendela.
            $floor = $dayShiftStart;
            $jumpTo = null;
            foreach ($machineId ? $this->maintenance->blocksOn($windowStart, $machineId) : [] as $block) {
                if ($block['end']->lte($dayShiftStart) || $block['start']->gte($windowStart)) {
                    continue;
                }
                if ($block['end']->gte($windowStart)) {
                    $cursor = $block['start']->copy();

                    continue 2;
                }
                $floor = $block['end'];
                $jumpTo = $block['start'];

                break;
            }

            // Carbon 3 -- diffInSeconds default berubah jadi signed (bukan absolute
            // seperti Carbon 2), wajib pass `true` eksplisit di sini.
            $availableThisWindow = $windowStart->diffInSeconds($floor, true);
            $needSeconds = $remaining->totalSeconds;

            if ($availableThisWindow >= $needSeconds) {
                $cursor = $windowStart->copy()->subSeconds($needSeconds);
                $remaining = CarbonInterval::seconds(0);
            } else {
                $remaining = CarbonInterval::seconds($needSeconds - $availableThisWindow);
                $cursor = $jumpTo ?? $dayShiftStart->copy()->subDay()->setTimeFrom($this->shiftEnd);
            }
        }

        return $cursor;
    }

    /**
     * @return array{start: Carbon, end: Carbon}|false|null null = mesin belum pernah
     *                                                      pakai roster (fallback jam default), false = mesin pakai roster tapi
     *                                                      kosong hari ini (hari libur mesin), array = window kerja hari ini.
     */
    private function machineWorkingWindow(Carbon $date, int $machineId): array|false|null
    {
        $hasAnyRosterHistory = DB::connection('tm2mst')->table('emp_shift_roster')
            ->where('machine_id', $machineId)
            ->exists();

        if (! $hasAnyRosterHistory) {
            return null;
        }

        $shiftIds = DB::connection('tm2mst')->table('emp_shift_roster')
            ->where('machine_id', $machineId)
            ->whereDate('roster_date', $date->toDateString())
            ->pluck('shift_id')
            ->unique();

        if ($shiftIds->isEmpty()) {
            return false;
        }

        $details = DB::connection('tm2mst')->table('emp_shift_detail')
            ->whereIn('shift_id', $shiftIds)
            ->where('day_of_week', $date->dayOfWeek)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get(['start_time', 'end_time']);

        if ($details->isEmpty()) {
            return false;
        }

        // Union rentang terluas (earliest start .. latest end) -- penyederhanaan sengaja
        // kalau ada >1 shift di-roster hari yg sama (mis. shift pagi+siang berurutan);
        // TIDAK menghitung gap kosong antar-shift secara presisi.
        $starts = $details->map(fn ($d) => $date->copy()->setTimeFromTimeString($d->start_time));
        $ends = $details->map(fn ($d) => $date->copy()->setTimeFromTimeString($d->end_time));

        return ['start' => $starts->min(), 'end' => $ends->max()];
    }

    private function isWorkingDay(Carbon $date): bool
    {
        // Senin(1)-Sabtu(6) kerja, Minggu(0) libur, dikurangi hari libur nasional
        // (sys_holiday, dibangun 2026-09-02) -- libur khusus perusahaan di luar itu
        // masih belum ada sumber datanya.
        return $date->dayOfWeek !== Carbon::SUNDAY && ! SysHoliday::isHoliday($date->toDateString());
    }
}
