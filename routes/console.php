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
