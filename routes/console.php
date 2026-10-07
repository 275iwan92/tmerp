<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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

// Material Planning: lepas booking CO yang batal + hitung ulang usulan booking CO CONFIRMED yang belum dikunci (tiap malam).
// Lingkungan yang belum punya skema Material Planning (tm2* sebelum disetujui user) dilewati.
Schedule::command('planning:run')->dailyAt('01:30')->withoutOverlapping()
    ->when(fn () => Schema::connection('tm2inv')->hasColumn('rm_booking', 'co_id'));

// Asisten AI: riwayat percakapan yang lewat masa simpan per level (30 / 14 / 7 hari, Settings > Constants) dibuang tiap malam.
// Lingkungan yang belum punya tabel asisten dilewati.
Schedule::command('assistant:prune')->dailyAt('02:30')->withoutOverlapping()
    ->when(fn () => Schema::connection('tm2sys')->hasTable('sys_assistant_message'));
