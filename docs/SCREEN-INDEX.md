# SCREEN-INDEX: satu baris per layar (jawaban "ini untuk apa?" tanpa membaca kode)

> Aturan (CLAUDE.md, user 2026-10-13): setiap perubahan kode memperbarui blok REMARK di file yang diubah
> DAN baris layarnya di sini. Format REMARK enam butir: Tujuan, Sumber hulu, Input, Output, Dipakai oleh, Status.
> Kolom Menu: tampil = ada di sidebar; disembunyikan = `sys_app_menu.hide_in_menu=1` (layar tetap terbuka lewat URL).
> Rute layar v2 = `/v2/s/<slug>` (nama rute `v2.screen`); aksi PPIC = `/v2/ppic/*` (`v2.ppic.*`, modul PROD).
> Status data: mockup (database ddt*), kecuali disebut lain.

## Process Entry

| Jalur menu | Slug / rute | Tujuan | File utama | Engine / service yang dipakai | Menu |
|---|---|---|---|---|---|
| Process Entry > Production Output | `v2:production-operator` (`?op=` = job aktif; administrator: `?machine=&process=`) | Entry hasil produksi di mesin untuk crew yang login, tanpa grid di depan: Master Card compact (dieline + artwork zoom, target per jam / qty / durasi, info job), timer SETUP dan PRODUCTION (Play/Pause/Stop, satu timer jalan sekali, Stop mengunci), LIST OF JOBS = operasi CONFIRMED mesin + proses crew, QC GOOD / BAD + SIMPAN HASIL (akumulatif); setiap aksi tercatat di `prd_output_action_log` (kapan, siapa, host, sebelum -> sesudah) | `resources/views/v2/screens/production-operator.blade.php`, `app/Support/V2/Data/ProductionOutput.php`, `packages/mockup-parts/src/Components/Production/OutputDesk.php`, `PpicController` (output*) | OperatorTimerService, OutputActionLog, Ppic::operations | disembunyikan |
