---
name: screen_recruitment_interview_schedule
description: Kalender jadwal interview (Day/Month/3-Month/Year) dengan drag-drop kandidat dari sidebar ke slot tanggal/jam.
---

# Jadwal Interview — Kalender Drag-Drop (L5)

**File**: `resources/views/pages/recruitment/interview-schedule.blade.php`
**Route**: `hrm.recruitment.interview-schedule` — `GET /hrm/recruitment/interview-schedule` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr` (+ `tm2sys` lintas-DB untuk notifikasi)
**Modul**: Recruitment (Internal) — L5, lihat komentar kode: `docs/recruitment_tier4.md` SECTION 10

## Tujuan
Menjadwalkan interview kandidat yang berada di tahap `INTERVIEW_HR`/`INTERVIEW_USER`. REVISI 2026-08-29 (instruksi user): dibangun ulang jadi kalender ala Google Calendar (Day/Month/3-Month/Year view) dengan drag-drop kartu kandidat dari sidebar kiri ke tanggal/jam untuk membuat jadwal — pola drag-drop sama dengan `pipeline-board.blade.php` (`x-data="{draggedId}"` + `dragover.prevent`/`drop`). Setelah dijadwalkan, HR bisa menandai selesai (`markDone`) atau membatalkan (`cancelSchedule`) lewat modal detail.

## Field & Kolom
**Sidebar kiri "Menunggu Dijadwalkan"**: kartu draggable per kandidat (nama + nama stage + judul lowongan) — sumber `candidatesAwaiting()`.

**Toolbar kalender**: tombol Hari Ini / ← / → navigasi, judul periode dinamis, toggle view mode (Hari/Bulan/3 Bulan/Tahun).

**View kalender**:
- Day: timeline jam 07:00-20:00, tiap slot jam bisa didrop
- Month/3-Month: grid 7 kolom (Min-Sab), tiap sel tanggal bisa didrop, tampil maks 2-3 chip jadwal + "+N lagi"
- Year: 12 mini-kalender bulan, klik tanggal → pindah ke Day view

**Modal "Jadwalkan Interview"** (muncul saat drop):
- `schedulingDate` (date picker, pre-filled dari tempat drop)
- `schedulingTime` (time, pre-filled dari jam slot kalau Day view, default `09:00`)
- `interviewer_id` (select, wajib) — sumber `interviewers()`: `SecUser::where('activate', true)`
- `location` (text, opsional)

**Modal detail jadwal** (klik chip existing): nama kandidat, stage+waktu+lokasi, badge status (`SCHEDULED`/`DONE`/`CANCELLED`), tombol Batalkan/Tandai Selesai (hanya kalau status `SCHEDULED`).

## Aksi / Method
- `mount()` — `focusDate` default hari ini
- `interviewers()` [Computed] — daftar `SecUser` aktif
- `candidatesAwaiting()` [Computed] — kandidat di stage `INTERVIEW_HR`/`INTERVIEW_USER` yang belum punya jadwal `SCHEDULED` aktif untuk stage-nya
- `focus()`, `rangeStart()`/`rangeEnd()` [Computed] — hitung rentang tanggal tampil sesuai `viewMode`
- `schedulesByDate()` [Computed] — jadwal dalam rentang, dikelompokkan per tanggal
- `setViewMode(string $mode)`, `navPrev()`, `navNext()`, `navToday()`, `goToDate(string $date)` — navigasi kalender
- `dropOnDate(?int $applicationId, string $date, ?int $hour = null)` — dipanggil saat drop, buka modal pre-filled
- `save()` — validasi `interviewer_id`+`schedulingDate`+`schedulingTime` wajib; `AppInterviewSchedule::create()` (`stage_code` dari `AppPipelineStageConfig` sesuai `current_stage_seq` app, fallback `INTERVIEW_HR`); kirim `SysNotification` ke interviewer
- `selectSchedule(int $id)` — buka modal detail
- `markDone()` — update status `DONE`
- `cancelSchedule()` — update status `CANCELLED`
- `weeksFor(Carbon $monthAnchor)` — helper grid tanggal per minggu (public, dipanggil langsung dari Blade — SFC compiled-view tidak bisa akses method protected/private)

## Tabel & Model yang Disentuh
- `AppInterviewSchedule` (`app_interview_schedule`, `tm2hrr`, PK `id_schedule`) — utama, CRUD (create + update status)
- `AppApplication` (`app_application`, `tm2hrr`) — sumber kandidat & relasi `vacancy.requisition`
- `AppPipelineStageConfig` (`app_pipeline_stage_config`, `tm2hrr`) — cocokkan stage aktif kandidat
- `App\Models\Sys\SecUser` (`tm2sys`) — **lintas-DB**, daftar interviewer
- `App\Models\Sys\SysNotification` (`tm2sys`) — **lintas-DB**, notifikasi ke interviewer saat jadwal dibuat (`user_id` diisi `login`, bukan `id_user`)

## Alur / Business Logic Penting
Kandidat hilang dari sidebar begitu punya 1 baris jadwal `SCHEDULED` aktif untuk stage berjalannya (tidak dicek ulang setelah dibatalkan — `cancelSchedule()` meng-unset `candidatesAwaiting` supaya kandidat itu muncul lagi di sidebar). Drop di kalender murni UI convenience — validasi bisnis (interviewer wajib dsb) tetap di server lewat `save()`.

## Catatan
- Tidak ada guard bentrok jadwal (2 interview di jam yang sama utk 1 interviewer tidak dicegah) — TBD, tidak ada komentar kode soal ini.
- `SysNotification.user_id` diisi dengan `login` (string), bukan `id_user` (int) — perlu konsisten dengan skema `sys_notification` aktual (tidak diverifikasi ulang skemanya di sesi ini).
