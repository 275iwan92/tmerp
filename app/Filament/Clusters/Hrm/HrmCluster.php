<?php

namespace App\Filament\Clusters\Hrm;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

// Cluster = level "Module" di sys_app_menu (code HRM, label "Human
// Resources", sort_order=45 — lihat migration 2026_08_29_000004). Semua
// Resource/Page di dalam app/Filament/Clusters/Hrm/* otomatis masuk 1
// namespace navigasi ini, mencerminkan hierarki lama Module->Group->Item
// TANPA merombaknya (syarat user 2026-08-30). Sub-grup (Organization/
// Employee/Payroll/Time Attendance/Recruitment) diatur lewat
// $navigationGroup di tiap Resource/Page-nya, bukan di sini.
class HrmCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Human Resources';

    protected static ?int $navigationSort = 45;
}
