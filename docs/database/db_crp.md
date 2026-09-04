---
name: db_crp
description: Portal Kandidat Plan B (MariaDB, host terpisah) — 3 tabel staging submission mentah sebelum ditarik ke tm2hrr
---

# Database: tm2crp (koneksi Eloquent: `crp`, **MariaDB** — bukan salah satu dari 10 DB tm2* MySQL utama)

**Sumber DDL**: tidak ada dump di `docs/ddl/` (tidak dicoba dicari lewat migration khusus di sesi ini — schema diringkas dari `$fillable`/`$table` 3 model Eloquent langsung).
**Jumlah tabel**: 3 (dari model yang ditemukan — kemungkinan ada tabel lain yang belum termodelkan, TBD)
**Host**: terpisah secara arsitektur (VPS/DMZ di desain final; lokal port 3307 utk dev — CLAUDE.md §2).

## Tujuan
"Portal Kandidat Plan B" — staging submission mentah dari portal eksternal SEBELUM ditarik ke `tm2hrr` (internal). Ini kanal cadangan/alternatif; kanal utama yang LIVE sekarang adalah Portal Rekrutmen Profesional (Firebase/Firestore, `App\Services\Recruitment\FirebaseSyncAdapter`), bukan tm2crp — tm2crp dipakai kalau `.env PORTAL_BACKEND=mariadb` (simulasi lama).

## Daftar Tabel (dari model, bukan dump — verifikasi ke DB langsung disarankan)

### `crp_candidate_account`
Akun kandidat sisi portal (password login portal, terpisah dari `sec_user` internal).
- PK: `id_account`
- Kolom: `national_id`, `email`, `phone`, `password_hash`, `email_verified` (boolean)
- Tanpa timestamps (`$timestamps = false`)

### `crp_submission`
Submission lamaran mentah dari portal — PK berupa UUID (bukan auto-increment), digenerate `Str::uuid()` di adapter, BUKAN oleh DB.
- PK: `submission_uuid` (string, non-incrementing)
- Kolom: TBD detail lengkap (tidak dibaca file penuh sesi ini) — minimal berisi identitas kandidat+data lamaran mentah sebelum sync ke `app_application`/`cnd_candidate` (tm2hrr).
- Catatan kode: `$guarded` sengaja TIDAK menutup `submission_uuid` — kalau ditutup, `create()` gagal isi PK (bug nyata pernah ditemukan via tinker, dicatat di komentar model).

### `crp_document`
Dokumen terkait 1 submission.
- Kolom: `submission_uuid` (FK logis -> `crp_submission`), `doc_type`, `file_path`, `uploaded_at`
- Tanpa timestamps (`$timestamps = false`)

## Catatan
Sync ke tm2hrr internal terjadi via `App\Services\Recruitment\PortalSyncService`/`MariaDbPortalSyncAdapter` — pola sync PULL periodik (`php artisan recruitment:sync-portal`, jadwal tiap 2 menit), idempoten via `tm2hrr.rcr_sync_log` + `app_application.submission_uuid`. Internal (tm2hrr) tidak pernah tahu backend mana yang aktif (Firebase vs MariaDB) — abstraksi lewat adapter pattern.
