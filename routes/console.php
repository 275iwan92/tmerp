<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// docs/recruitment_tier3.md §1.2 -- tiap 1-2 menit.
Schedule::command('recruitment:sync-portal')->everyTwoMinutes()->withoutOverlapping();
