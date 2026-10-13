# SCREEN-INDEX: satu baris per layar (jawaban "ini untuk apa?" tanpa membaca kode)

> Aturan (CLAUDE.md, user 2026-10-13): setiap perubahan kode memperbarui blok REMARK di file yang diubah
> DAN baris layarnya di sini. Format REMARK enam butir: Tujuan, Sumber hulu, Input, Output, Dipakai oleh, Status.
> Aturan DevLib-dulu (CLAUDE.md): layar memakai objek `x-dl::*` / `x-mp::*`; gaya hanya di objek atau token DevLib. Ratchet `DevlibOnlyRatchetTest` menghitung tag mentah, warna literal, kelas lokal; angka hanya boleh turun.
> Kolom Menu: tampil = ada di sidebar; disembunyikan = `sys_app_menu.hide_in_menu=1` (layar tetap terbuka lewat URL).
> Rute layar v2 = `/v2/s/<slug>` (nama rute `v2.screen`); aksi PPIC = `/v2/ppic/*` (`v2.ppic.*`, modul PROD).
> Status data: mockup (database ddt*), kecuali disebut lain.

## Process Entry

| Jalur menu | Slug / rute | Tujuan | File utama | Engine / service yang dipakai | Menu |
|---|---|---|---|---|---|
| Process Entry > Production Output | `v2:production-operator` (`?op=` = job aktif; administrator: `?machine=&process=`) | Entry hasil produksi di mesin untuk crew yang login, tanpa grid di depan: Master Card compact (dieline + artwork zoom, target per jam / qty / durasi, info job), timer SETUP dan PRODUCTION (Play/Pause/Stop, satu timer jalan sekali, Stop mengunci), LIST OF JOBS = operasi CONFIRMED mesin + proses crew, QC GOOD / BAD + SIMPAN HASIL (akumulatif); setiap aksi tercatat di `prd_output_action_log` (kapan, siapa, host, sebelum -> sesudah) | `resources/views/v2/screens/production-operator.blade.php`, `app/Support/V2/Data/ProductionOutput.php`, `packages/mockup-parts/src/Components/Production/OutputDesk.php`, `PpicController` (output*) | OperatorTimerService, OutputActionLog, Ppic::operations | disembunyikan |

## Setup > Others (review)

| Jalur menu | Slug / rute | Tujuan | File utama | Engine / service yang dipakai | Menu |
|---|---|---|---|---|---|
| Setup > Others > MD Review | `v2:setup-md-review` | Gaya blog (`x-dl::review-feed`, huruf besar, teks hitam): satu kartu per berkas .md dengan ringkasan otomatis (`MdDigest`), usulan + alasan Claude, tombol besar Keep / Condense / Discard yang langsung tersimpan; tombol Process with Claude mengantre ke `sys_review_request` | `MdReviewLookup` (`sys_md_review`), `resources/views/v2/screens/setup-review.blade.php` | `ReviewRequestService`, `php artisan review:import`, `review:requests` | tampil |
| Setup > Others > Old Stories Review | `v2:setup-story-review` | Gaya blog (`x-dl::review-feed`): satu kartu per pesan lama Jo, dibaca utuh, tombol besar Adopt / Later / Ignore yang langsung tersimpan; tombol Process with Claude mengantre | `StoryReviewLookup` (`sys_story_review`), `resources/views/v2/screens/setup-review.blade.php` | `ReviewRequestService`, `php artisan review:import`, `review:requests` | tampil |
