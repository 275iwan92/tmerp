---
name: flow_recruitment_portal_public
description: Alur kandidat publik di Portal Rekrutmen Profesional -- landing sampai sync data lamaran ke tm2hrr internal.
---

# Alur Portal Rekrutmen Publik (Careers Portal)

**Modul**: Recruitment Portal (Publik) + sync ke Recruitment internal (`tm2hrr`)
**Prefix route**: `/hrm/recruitment/portal` (name prefix `careers.*`)

## Ringkasan
Portal Rekrutmen Profesional PUBLIK (`resources/views/pages/careers/*`) adalah SATU-SATUNYA kanal aktif untuk kandidat melamar via web saat ini. Kandidat mendaftar akun real (No. KTP + Nama + Email + No. HP + password), memverifikasi email, browse lowongan, mengisi form lamaran lengkap 1-halaman, lalu memantau status di dashboard. Data tersimpan di **Firebase/Firestore** (bukan MySQL) lewat `App\Services\Recruitment\FirebaseSyncAdapter` yang dipanggil LANGSUNG (hardcode, bukan lewat `PortalBackendAdapterInterface` yang switchable). Data baru masuk ke database internal `tm2hrr` lewat worker PULL terjadwal (`recruitment:sync-portal`, tiap 2 menit) yang generic terhadap sumber backend.

## Kanal lama (simulasi) -- TIDAK AKTIF
`resources/views/pages/recruitment/portal.blade.php` (Livewire `pages::recruitment.portal`) adalah simulasi lama (KTP-only, tanpa akun real), pernah dipasang di route `hrm.recruitment.portal` di dalam middleware `auth`+`module:HRM` (perlu login internal ERP). Route itu **sudah dihapus** dari `routes/web.php` -- dikonfirmasi lewat grep: tidak ada `Route::livewire` yang menunjuk `pages::recruitment.portal` di file itu. Komentar eksplisit di `routes/web.php` baris ~198-205 menegaskan: file lama tetap ada di repo (tidak dihapus) karena jadi sumber partial `_apply-form-steps.blade.php` (dipakai ulang oleh kanal baru), tapi tidak ada route aktif ke `pages::recruitment.portal` itu sendiri. Alamat URL publik `/hrm/recruitment/portal` yang sekarang aktif dipertahankan sama persis, tapi diarahkan ke kanal baru (`careers.*` group) yang berada DI LUAR middleware `auth` ERP internal, pakai guard sesi sendiri `portal.candidate`.

Kanal ke-2 rencana (aplikasi Flutter, HP) **belum dibangun** -- baru dicatat sebagai rencana; backend Firestore sudah siap dipakai bareng kalau kanal itu dibangun (lihat CLAUDE.md §1).

## Tahap -> Layar -> Aktor

| # | Tahap | Layar / Proses | Route | Aktor |
|---|---|---|---|---|
| 1 | Lihat daftar lowongan publik | `careers/landing.blade.php` | `careers.landing` (`/hrm/recruitment/portal`) | Publik (belum login) |
| 2 | Lihat detail 1 lowongan | `careers/vacancy-detail.blade.php` | `careers.vacancy-detail` (`/hrm/recruitment/portal/lowongan/{vacancy_code}`) | Publik (belum login) |
| 3 | Daftar akun kandidat | `careers/register.blade.php` | `careers.register` (`/hrm/recruitment/portal/daftar`) | Kandidat baru |
| 4 | Verifikasi email | link signed di email (`CandidateEmailVerification` mailable) -> route closure `verify-email` | `careers.verify-email` (`/hrm/recruitment/portal/verifikasi/{uid}`, middleware `signed`) | Kandidat (via inbox email) |
| 5 | Login | `careers/login.blade.php` | `careers.login` (`/hrm/recruitment/portal/masuk`) | Kandidat terdaftar |
| 6 | Isi & kirim form lamaran | `careers/apply.blade.php` (+ partial `_apply-form-steps.blade.php`) | `careers.apply` (`/hrm/recruitment/portal/lamar/{vacancy_code}`, middleware `portal.candidate`) | Kandidat login |
| 7 | Cek status lamaran | `careers/dashboard.blade.php` | `careers.dashboard` (`/hrm/recruitment/portal/lamaran-saya`, middleware `portal.candidate`) | Kandidat login |
| 8 | Logout | route closure `logout` | `careers.logout` (`/hrm/recruitment/portal/keluar`) | Kandidat login |
| 9 | Sync data ke internal | `App\Console\Commands\SyncRecruitmentPortal` -> `App\Services\Recruitment\PortalSyncService` | command `recruitment:sync-portal`, dijadwalkan `everyTwoMinutes()` (`routes/console.php`) | Sistem (background worker) |
| 10 | Update status balik ke portal | `App\Observers\AppApplicationObserver` (event `updated` pada `app_application.status`) -> `pushStatusUpdate()` | tidak ada route, event-driven internal | Sistem (trigger oleh staf HR yang ubah status lamaran) |

## Alur Detail

1. **Landing -> Vacancy Detail**: kandidat browse lowongan publik tanpa login (`careers.landing`, `careers.vacancy-detail`). Tombol "Lamar" di detail vacancy mengarah ke `careers.apply` -- kalau belum login, middleware `portal.candidate` akan redirect ke `careers.login` dulu (detail implementasi middleware TBD - perlu verifikasi, tidak dibaca file middleware-nya dalam sesi dokumentasi ini).
2. **Register**: isi No. KTP + Nama + Email + No. HP + Password (`careers.register`). `CandidateAuthService::register()` cek duplikat email & No. KTP di Firestore collection `candidates`, simpan dokumen baru (`password_hash` via `Hash::make()`, `email_verified_at: null`). Setelah sukses: kirim email verifikasi (signed URL 60 menit ke `careers.verify-email`) DAN langsung auto-login (`session(['portal_candidate_uid' => $uid])`).
3. **Verifikasi email**: kandidat klik link di email -> route closure (bukan Livewire) validasi signed URL, panggil `CandidateAuthService::markEmailVerified($uid)` (update Firestore `candidates/{uid}.email_verified_at`), redirect ke `careers.login` dengan flash `verified=true`.
4. **Login**: `careers.login` -- verifikasi credential via `CandidateAuthService::attempt()` (bandingkan `Hash::check()` terhadap `password_hash`), set session `portal_candidate_uid`.
5. **Apply**: `careers.apply` (wajib `portal.candidate`) -- form 1-halaman 11 seksi (`_apply-form-steps.blade.php`), identitas (nama/KTP/email) dikunci dari akun, field lain wajib SEMUA diisi (revisi 2026-08-30). Guard tambahan: kalau email belum diverifikasi, submit ditolak dengan pesan error (form tetap bisa diisi). Detail lengkap field & validasi: `docs/screens/screen_careers_apply.md`. Submit -> `FirebaseSyncAdapter::saveSubmission()` tulis dokumen baru ke Firestore collection `submissions` (UUID baru, `sync_status: 'pending'`).
6. **Dashboard**: `careers.dashboard` (wajib `portal.candidate`) -- tampilkan semua `submissions` milik `candidate_uid` yang sama (`findAllByCandidateUid()`), status dihitung di view dari `app_status` (kalau sudah ditulis balik oleh internal) atau fallback `sync_status`. Ada tombol kirim ulang email verifikasi kalau belum verified.
7. **Sync ke tm2hrr (worker background)**: command `recruitment:sync-portal` (jadwal `everyTwoMinutes()`, `withoutOverlapping()`, `routes/console.php`) menjalankan `PortalSyncService::run()`:
   - Tarik submission `pending` dari adapter aktif (`fetchPendingSubmissions()` -- untuk Firebase, filter `sync_status === 'pending'` di collection `submissions`).
   - Idempotensi: cek `RcrSyncLog` (tabel `tm2hrr.rcr_sync_log`) apakah `submission_uuid` sudah pernah `result='OK'` -- kalau ya, cukup `markSynced()` ulang (jaga-jaga retry setelah gagal tandai), skip proses ulang.
   - Kalau belum: `applyOne()` -- upsert `CndCandidate` (by `national_id`), replace-semantics untuk seksi array tunggal (`CndFamilyMember`, `CndEducation`, `CndEducationNonformal`, `CndOrganizationExperience` -- delete semua baris lama lalu insert ulang dari submission), APPEND+dedupe untuk `CndWorkExperience` (dedupe by `company`+`position`) dan `CndDocument` (dedupe by `doc_type`+`file_path`). Lookup `VacVacancy` by `vacancy_code` (`firstOrFail()` -- gagal kalau vacancy tidak ditemukan). `AppApplication::firstOrCreate(['submission_uuid' => ...], [...status: 'APPLIED', current_stage_seq: 1...])` -- idempoten di level aplikasi juga.
   - Catat hasil ke `RcrSyncLog` (`result: 'OK'`/`'ERROR'`, `error_note` kalau gagal), lalu `markSynced()` ke Firestore (`sync_status: 'synced'`, `synced_at`) kalau sukses.
8. **Update status balik ke portal**: saat staf HR internal mengubah `app_application.status` (mis. di layar pipeline/offer internal), `App\Observers\AppApplicationObserver` (generic, menempel event `updated` kolom `status`, bukan ditempel manual per layar) memanggil `FirebaseSyncAdapter::pushStatusUpdate($submissionUuid, $status)` -- update `app_status` + `status_pushed_at` di dokumen Firestore `submissions`. Dashboard kandidat (`careers.dashboard`) membaca `app_status` ini untuk menampilkan status terkini.

## Catatan Arsitektur Penting
- Internal (`tm2hrr`) TIDAK PERNAH tahu backend portal mana yang aktif (Firebase/Firestore vs `tm2crp` MariaDB Plan B) -- `PortalSyncService` hanya bergantung pada kontrak `PortalBackendAdapterInterface`, adapter konkret (`FirebaseSyncAdapter`) yang menyeragamkan bentuk data.
- Portal profesional (`careers.*`) SENGAJA hardcode `FirebaseSyncAdapter` langsung di `apply.blade.php`/`register.blade.php`/dll (bukan resolve lewat interface + `.env PORTAL_BACKEND`) -- supaya tidak ikut berubah kalau env itu diubah untuk keperluan simulasi internal yang berbeda (`portal.blade.php` lama, sudah tidak ada route aktif, lihat bagian "Kanal lama" di atas).
- Arah data: portal -> internal SELALU PULL (`recruitment:sync-portal` dari sisi internal menarik keluar) -- portal TIDAK PERNAH memanggil masuk ke jaringan internal (komentar kode di `SyncRecruitmentPortal.php`). Arah balik (status) lewat push eksplisit dari Observer, bukan portal yang polling internal.
- Idempotensi dobel-lapis: `RcrSyncLog` (level submission_uuid, cegah reproses) + `AppApplication.submission_uuid` unik/`firstOrCreate` (level aplikasi, cegah entri dobel kalau `RcrSyncLog` sendiri belum tercatat tapi proses sempat jalan sebagian).

## Layar terkait (dokumentasi detail per-layar)
- `docs/screens/screen_careers_landing.md`
- `docs/screens/screen_careers_vacancy_detail.md`
- `docs/screens/screen_careers_register.md`
- `docs/screens/screen_careers_login.md`
- `docs/screens/screen_careers_apply.md`
- `docs/screens/screen_careers_dashboard.md`

## Gap / TBD
- Detail middleware `portal.candidate` (redirect behavior saat belum login, nama file middleware) tidak dibaca langsung dalam sesi dokumentasi ini -- TBD - perlu verifikasi kalau butuh detail presisi.
- Kanal ke-2 (aplikasi Flutter/HP) belum dibangun sama sekali (0 kode) -- baru rencana, dicatat di CLAUDE.md §1.
