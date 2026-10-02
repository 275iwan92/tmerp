<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Kehadiran pada periode itu sampai hari ini, dalam hari-orang: hadir, terlambat, cuti / izin, sakit, alpa.
// Referensi: attendance summary (Frappe HR, Odoo).
final class AttendanceSummary extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'attendance_summary', 'title' => 'Attendance', 'type' => 'chart', 'dashboard' => 'HR', 'module' => 'HRM',
            'description' => 'Attendance of the period in person-days: present, late, leave or permit, sick, absent.',
            'icon' => 'fa-fingerprint', 'tone' => 'success', 'phrases' => ['kehadiran', 'presensi', 'absensi', 'attendance', 'absent'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $to = $n->to()->greaterThan($n->now) ? $n->now->startOfDay() : $n->to();
        $a = $n->attendance($n->from(), $to);

        return ['caption' => raw($n->rangeText().' · '.t('person-days')), 'source' => 'tm2mst.att_daily_status', 'open' => $this->open('HRM_ATTENDANCE', 'Attendance', 'fa-solid fa-fingerprint'),
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => [t('Present'), t('Late'), t('Leave / permit'), t('Sick'), t('Absent')],
                'series' => [['name' => t('Person-days'), 'data' => [$a['present'], $a['late'], $a['leave'], $a['sick'], $a['absent']]]]]];
    }
}
