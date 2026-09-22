---
name: screen_careers_dashboard
description: Dashboard "Lamaran Saya" -- riwayat & status lamaran kandidat yang sudah login.
---

# Lamaran Saya (Dashboard Karir)

**File**: `resources/views/pages/careers/dashboard.blade.php`
**Route**: `careers.dashboard` (GET `/hrm/recruitment/portal/lamaran-saya`, DI DALAM `Route::middleware('portal.candidate')` -- wajib sudah login sebagai kandidat)
**Koneksi DB utama**: Firebase/Firestore (via `App\Services\Portal\CandidateAuthService` + `App\Services\Recruitment\FirebaseSyncAdapter`)
**Modul**: Recruitment Portal (Publik)

## Tujuan
Halaman kandidat yang sudah login untuk melihat identitas akunnya dan riwayat semua lamaran yang pernah dikirim (`submissions`), lengkap dengan status terkini. Juga jadi tempat kandidat mengirim ulang email verifikasi kalau belum diverifikasi.

## Field & Kolom
Tidak ada form input. Dua computed property:
- `candidate` (`#[Computed]`) -- `CandidateAuthService::findByUid(session('portal_candidate_uid'))`.
- `submissions` (`#[Computed]`) -- `collect(FirebaseSyncAdapter::findAllByCandidateUid(session('portal_candidate_uid')))`.

## Aksi / Method
- `resendVerification()`: generate ulang signed URL verifikasi (`temporarySignedRoute('careers.verify-email', 60 menit, ['uid' => ...])`), kirim ulang `CandidateEmailVerification` mail, lalu `$this->dispatch('verification-resent')` (event Livewire/Alpine untuk update UI "Terkirim ✓" tanpa reload).

## Tabel & Model / Firestore Collection yang Disentuh
- **Firestore collection `candidates`** -- baca (`getDocument`) via `findByUid()`.
- **Firestore collection `submissions`** -- baca semua dokumen lalu difilter `candidate_uid` sama di PHP (`findAllByCandidateUid()`, `listDocuments('submissions')`), diurutkan `sortByDesc('submitted_at')`.
- Tidak menyentuh MySQL.

## Alur / Business Logic Penting
- Kalau `email_verified_at` kosong pada dokumen kandidat -- tampil banner kuning peringatan + tombol "Kirim ulang" verifikasi.
- Status tiap lamaran dihitung di view (bukan di component):
  - `$status = $s['app_status'] ?? ($s['sync_status'] === 'synced' ? 'DIPROSES' : 'MENUNGGU SINKRONISASI')` -- field `app_status` ditulis balik oleh `App\Observers\AppApplicationObserver` (internal, saat status lamaran berubah di sisi HR); kalau belum pernah ditulis (baru submit, belum diproses HR), fallback ke `sync_status` (`pending`/`synced`, ditulis oleh worker `PortalSyncService`).
  - Badge warna: `HIRED` = hijau, `REJECTED`/`WITHDRAWN` = merah, `OFFERING` = ungu, `MENUNGGU SINKRONISASI` = abu-abu, default (mis. `APPLIED`/`IN_PROGRESS`, ditampilkan sebagai label "DIPROSES") = kuning.
- Layout: `layouts::portal`, title: "Lamaran Saya".

## Catatan
Komentar kode menegaskan `pushStatusUpdate` (dipanggil observer) "real, bukan no-op lagi" -- artinya sebelumnya pernah jadi stub. Tidak ada TODO lain tertulis di file ini.
