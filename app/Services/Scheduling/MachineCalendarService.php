<?php

namespace App\Services\Scheduling;

use Carbon\Carbon;
use Carbon\CarbonInterval;

/**
 * Machine Calendar — jam kerja efektif per mesin per shift.
 * Sumber: production_scheduling_tier1.md §4 istilah baku.
 *
 * CATATAN JUJUR: implementasi di bawah masih SEDERHANA (satu jam kerja tetap
 * per hari, Senin-Sabtu). Integrasi penuh ke `emp_shift_roster` (HRM) —
 * dicatat sebagai temuan cross-modul wajib di production_scheduling_tier3.md
 * §12 poin 2 — BELUM dikerjakan di sini. Sambungkan saat digarap terpisah,
 * jangan anggap versi ini final.
 */
class MachineCalendarService
{
    private readonly Carbon $shiftStart;

    private readonly Carbon $shiftEnd;

    public function __construct()
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
    public function subtractWorkingTime(Carbon $from, CarbonInterval $duration): Carbon
    {
        $remaining = $duration->copy();
        $cursor = $from->copy();

        // Guard infinite loop kalau durasi absurd besar / kalender salah konfigurasi
        $safetyCounter = 0;

        while ($remaining->totalSeconds > 0 && $safetyCounter < 1000) {
            $safetyCounter++;

            $dayShiftStart = $cursor->copy()->setTimeFrom($this->shiftStart);
            $dayShiftEnd = $cursor->copy()->setTimeFrom($this->shiftEnd);

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
            // Carbon 3 -- diffInSeconds default berubah jadi signed (bukan absolute
            // seperti Carbon 2), wajib pass `true` eksplisit di sini.
            $availableThisWindow = $windowStart->diffInSeconds($dayShiftStart, true);
            $needSeconds = $remaining->totalSeconds;

            if ($availableThisWindow >= $needSeconds) {
                $cursor = $windowStart->copy()->subSeconds($needSeconds);
                $remaining = CarbonInterval::seconds(0);
            } else {
                $remaining = CarbonInterval::seconds($needSeconds - $availableThisWindow);
                $cursor = $dayShiftStart->copy()->subDay()->setTimeFrom($this->shiftEnd);
            }
        }

        return $cursor;
    }

    private function isWorkingDay(Carbon $date): bool
    {
        // Senin(1)-Sabtu(6) kerja, Minggu(0) libur. Sesuaikan bila ada kalender
        // libur nasional/khusus perusahaan — sumber data belum ditentukan.
        return $date->dayOfWeek !== Carbon::SUNDAY;
    }
}
