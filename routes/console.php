<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// docs/recruitment_tier3.md §1.2 -- tiap 1-2 menit.
Schedule::command('recruitment:sync-portal')->everyTwoMinutes()->withoutOverlapping();

// BACKLOG 19.2 -- usulan takaran pemakaian & waste dari data produksi nyata,
// disegarkan tiap awal bulan (officer tetap bisa isi manual di Setting Aturan BOP).
Schedule::command('prodev:usage-rates')->monthlyOn(1, '02:00')->withoutOverlapping();

// Engine target kecepatan mesin -- belajar dari realisasi WO (usulan PENDING, PPIC yang memutuskan di Master Mesin).
Schedule::command('production:suggest-speed-targets')->dailyAt('03:00')->withoutOverlapping();

// Maintenance: plan preventive jatuh tempo -> MWO Draft (docs/maintenance_tier1.md §6).
Schedule::command('maintenance:generate-pm')->dailyAt('06:00')->withoutOverlapping();

// Material Planning: lepas booking CO yang batal + hitung ulang usulan booking CO CONFIRMED yang belum dikunci (tiap malam).
// Lingkungan yang belum punya skema Material Planning (tm2* sebelum disetujui user) dilewati.
Schedule::command('planning:run')->dailyAt('01:30')->withoutOverlapping()
    ->when(fn () => \Illuminate\Support\Facades\Schema::connection('tm2inv')->hasColumn('rm_booking', 'co_id'));
