<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
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

// Maintenance: meter mesin dari output produksi (05:30), lalu Preventive Plan jatuh tempo -> Preventive Maintenance Order Draft (06:00).
Schedule::command('maintenance:accumulate-meters')->dailyAt('05:30')->withoutOverlapping();
Schedule::command('maintenance:generate-pm')->dailyAt('06:00')->withoutOverlapping();

// Material Planning: lepas booking CO yang batal + hitung ulang usulan booking CO CONFIRMED yang belum dikunci (tiap malam).
// Lingkungan yang belum punya skema Material Planning (tm2* sebelum disetujui user) dilewati.
Schedule::command('planning:run')->dailyAt('01:30')->withoutOverlapping()
    ->when(fn () => Schema::connection('tm2inv')->hasColumn('rm_booking', 'co_id'));

// Asisten AI: riwayat percakapan yang lewat masa simpan per level (30 / 14 / 7 hari, Settings > Constants) dibuang tiap malam.
// Lingkungan yang belum punya tabel asisten dilewati.
Schedule::command('assistant:prune')->dailyAt('02:30')->withoutOverlapping()
    ->when(fn () => Schema::connection('tm2sys')->hasTable('sys_assistant_message'));

// PPIC autopilot: engines prepare a Draft plan nightly and at each shift start (the officer confirms; nothing becomes the real schedule on its own),
// and the learning engine corrects assumed setup values after the night's jobs. Environments without the scheduling tables are skipped.
$ppicReady = fn () => Schema::connection('tm2prd')->hasTable('prd_plan_run');
Schedule::command('ppic:auto-plan --trigger=DAILY')->dailyAt('02:00')->withoutOverlapping()->when($ppicReady);
foreach (range(0, max(0, (int) config('ppic.default_shifts', 2) - 1)) as $shift) {
    $start = Carbon::createFromFormat('H:i', (string) config('ppic.day_start', '07:00'))->addMinutes((int) round($shift * (float) config('ppic.default_hours_per_shift', 8) * 60));
    Schedule::command('ppic:auto-plan --trigger=SHIFT')->dailyAt($start->format('H:i'))->withoutOverlapping()->when($ppicReady);
}
Schedule::command('ppic:learn')->dailyAt('03:30')->withoutOverlapping()->when($ppicReady);

// Inventory & Warehouse v2.01 (user 2026-10-10 menyetujui jadwal): rekonsiliasi nilai harian, eskalasi keputusan QC per jam, booking Hold/basi,
// tugas hitung stok jatuh tempo, deteksi kurang kirim. Lingkungan yang belum punya tabelnya (tm2* sebelum rilis) dilewati.
$invWhReady = fn (string $conn, string $table) => fn () => Schema::connection($conn)->hasTable($table);
Schedule::command('inv:reconcile')->dailyAt('04:00')->withoutOverlapping()->when($invWhReady('tm2inv', 'inv_reconcile_log'));
Schedule::command('inv:qc-escalate')->hourly()->withoutOverlapping()->when($invWhReady('tm2inv', 'inv_qc_decision'));
Schedule::command('inventory:booking-maintain')->dailyAt('01:45')->withoutOverlapping()->when(fn () => Schema::connection('tm2inv')->hasColumn('rm_booking', 'hold_until'));
Schedule::command('inv:count-due')->dailyAt('05:00')->withoutOverlapping()->when($invWhReady('tm2inv', 'wh_task'));
Schedule::command('inv:detect-shortage')->dailyAt('05:15')->withoutOverlapping()->when($invWhReady('tm2inv', 'inv_shortage_request'));
