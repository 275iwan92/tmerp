---
name: screen_recruitment_sync_log
description: Grid read-only rekap hasil sync worker pull dari Portal Rekrutmen (Firebase/MariaDB) ke tm2hrr, untuk troubleshooting.
---

# Rekap Sync Log Rekrutmen — Grid Read-only (L8)

**File**: `resources/views/pages/recruitment/sync-log.blade.php`
**Route**: `hrm.recruitment.sync-log` — `GET /hrm/recruitment/sync-log` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr`
**Modul**: Recruitment (Internal) — L8, lihat komentar kode: `docs/recruitment_tier4.md` §L8

## Tujuan
Layar murni read-only untuk troubleshooting sinkronisasi. `PortalSyncService` (jadwal tiap 2 menit, `php artisan recruitment:sync-portal`) menulis 1 baris ke `rcr_sync_log` tiap kali pull dari backend portal (Firebase/MariaDB) berhasil atau gagal. Tidak ada Aksi buka/edit di grid ini — sengaja tidak di-link ke layar Offer karena `mount()` layar itu punya efek samping (bikin `AppOffer` baru kalau belum ada), yang tak diinginkan hanya dari klik baris log.

## Field & Kolom
**Filter/toolbar**: `search` (nama kandidat atau `submission_uuid`), `result` (dropdown: Semua/OK/ERROR), `backend` (dropdown: Semua/FIREBASE/MARIADB), tombol "Reset filter" (muncul kalau ada filter aktif).

**Kotak statistik**: Total Sync, Berhasil (OK), Gagal (ERROR).

**Kolom grid**: Kandidat/Submission UUID (identity-cell: nama kandidat atau "— kandidat tak ditemukan —" + UUID sbg kode), Lowongan, Backend (badge), Hasil (badge OK hijau/ERROR merah), Waktu Sync (`fetched_at`, format `d M Y H:i:s`), Catatan Error.

## Aksi / Method
- `rows()` [Computed] — `RcrSyncLog::with(['application.candidate', 'application.vacancy'])`, filter `result`/`backend`/`search`, urut `fetched_at` desc, limit 300 (bukan pagination — hard limit)
- `statTotal()`, `statOk()`, `statError()` [Computed] — 3 angka kotak statistik
- `resetFilter()` — kosongkan `search`/`result`/`backend`

Tidak ada aksi mutasi data — layar 100% read-only.

## Tabel & Model yang Disentuh
- `RcrSyncLog` (`rcr_sync_log`, `tm2hrr`, PK `id_log`) — utama, read-only
- `AppApplication` (`app_application`, `tm2hrr`) — relasi `application.candidate`/`application.vacancy` untuk tampilan nama

## Alur / Business Logic Penting
Sync worker (`PortalSyncService`) menarik submission mentah dari backend portal aktif (Firebase Firestore via `FirebaseSyncAdapter`, atau `tm2crp` MariaDB via `MariaDbPortalSyncAdapter`) dan menuliskan hasilnya sebagai `AppApplication`/`CndCandidate` baru di `tm2hrr`, idempoten via kombinasi log ini + `submission_uuid`. `application_id` di log bisa null/tidak ketemu kalau sync gagal sebelum record application berhasil dibuat (ditampilkan "— kandidat tak ditemukan —").

## Catatan
- Limit hard 300 baris tanpa pagination — kalau volume sync log jauh melebihi itu, baris lama tidak akan pernah terlihat di UI ini (TBD - perlu verifikasi apakah ini masalah nyata di produksi berdasarkan volume sync harian).
- Tidak ada retensi/pembersihan log terlihat di layar ini — kemungkinan tabel `rcr_sync_log` terus bertambah tanpa batas (TBD, tidak diverifikasi apakah ada job pembersihan terpisah).
