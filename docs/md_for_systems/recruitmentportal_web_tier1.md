# TIER 1 — PORTAL REKRUTMEN PROFESIONAL (Web)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-08-30 atas instruksi user: "Bertindaklah sebagai Senior
> Full-Stack Developer. Saya ingin membangun Portal Rekrutmen tingkat
> profesional dengan md file. Silakan gunakan Laravel untuk backend, firebase
> untuk database." Dokumen ini spec + catatan implementasi, LIVE (bukan
> rencana) — dibangun & diuji end-to-end sesi yang sama.

---

## 1. Status vs dokumen lain (baca urutan ini)

1. `recruitment_tier1.md`–`tier4.md` — desain ASLI modul Recruitment penuh
   (internal L1-L9 + portal P1-P4), arsitektur 2-host TERKUNCI (`dua_host_arsitektur`):
   portal = host terpisah (rencana akhir: **Flutter**, Android+iOS+Web),
   internal `tm2hrr` tarik data via PULL sync worker. **TIDAK berubah** —
   dokumen ini adalah IMPLEMENTASI KANAL WEB dari desain itu, bukan revisi.
2. `recruitment_delta_plan_ab_local.md` — delta teknis Plan A (Firebase)/Plan
   B (tm2crp MariaDB) versi lokal-dev, ditulis SAAT membangun adapter pattern
   (sebelum portal profesional ini ada). Kelas `FirebaseSyncAdapter`,
   `MariaDbPortalSyncAdapter`, `PortalSyncService` dari situ **dipakai ulang**
   di sini, bukan ditulis dobel.
3. **Dokumen ini** — portal PUBLIK sungguhan (bukan simulasi KTP-only lagi),
   akun real (register/login/verifikasi email), UI didesain lintas-perangkat
   (tablet/laptop/desktop) — 1 dari 2 kanal yang direncanakan (§6).

---

## 2. Keputusan terkunci sesi ini

- **Alamat URL dipertahankan**: `/hrm/recruitment/portal` (instruksi user
  eksplisit "portalnya tetap di alamat ini") — TAPI sekarang **publik**
  (di luar middleware `auth` internal ERP), bukan lagi wizard KTP-only di
  dalam shell admin. Sub-halaman menggantung di bawahnya (`/daftar`,
  `/masuk`, `/lowongan/{code}`, `/lamar/{code}`, `/lamaran-saya`,
  `/verifikasi/{uid}`, `/keluar`).
- **Database = Firebase (Firestore)**, hardcode — BUKAN switchable ke
  MariaDB seperti simulasi lama. Alasan: instruksi eksplisit user "firebase
  untuk database" untuk portal profesional ini. `careers/apply.blade.php` &
  `careers/dashboard.blade.php` type-hint `FirebaseSyncAdapter` LANGSUNG
  (bukan `PortalBackendAdapterInterface`), supaya tidak ikut berubah kalau
  `.env PORTAL_BACKEND` diganti untuk keperluan lain.
- **Akun kandidat REAL** (bukan KTP-only lagi): registrasi = No. KTP + Nama
  (sesuai KTP) + Email + No. HP/Smartphone + Password (field persis sesuai
  instruksi user). Verifikasi email wajib (signed URL Laravel, 60 menit)
  sebelum bisa mengirim lamaran — belum bisa browse+isi form, tapi tidak
  bisa `submitApplication()` (dicek `emailVerified` computed).
- **Dual-channel diakui, cuma 1 dibangun sekarang**: user konfirmasi mau 2
  kanal — web (dokumen ini, utk tablet/laptop/desktop) DAN Flutter (utk
  HP Android/iOS, BELUM dibangun — scope Flutter native jauh lebih besar,
  tooling beda total, lihat §6).

---

## 3. Arsitektur & alur data

```
Kandidat (browser publik)
   │
   ▼
/hrm/recruitment/portal/*  (Livewire, layouts::portal — HTML lengkap sendiri,
│                            BUKAN layouts::shell, tanpa login ERP internal)
│
├─ Akun (Firestore collection 'candidates/{uid}')
│    App\Services\Portal\CandidateAuthService (register/attempt/verify)
│    -- session-based guard sendiri: session('portal_candidate_uid'),
│       middleware 'portal.candidate' (App\Http\Middleware\EnsurePortalCandidateAuthenticated)
│    -- SENGAJA BUKAN Illuminate\Contracts\Auth\UserProvider formal (provider
│       itu didesain sekitar Eloquent, Firestore REST tidak cocok tanpa
│       banyak adapter tambahan -- lihat komentar CandidateAuthService)
│
└─ Submission (Firestore collection 'submissions/{uuid}')
     App\Services\Recruitment\FirebaseSyncAdapter::saveSubmission()
     (KELAS SAMA dgn simulasi lama, lihat recruitment_delta_plan_ab_local.md)
        │
        │  PULL tiap 2 menit (routes/console.php Schedule::command)
        ▼
   php artisan recruitment:sync-portal
   App\Services\Recruitment\PortalSyncService
        │
        ▼
   tm2hrr (CndCandidate + child tables + AppApplication + RcrSyncLog)
        │
        │  App\Observers\AppApplicationObserver (event 'updated', field 'status')
        │  -- generik, nempel di MANA PUN app_application.status diubah
        │  (Pipeline Board drag-drop, approval Offer, dst) -- TIDAK perlu
        │  sentuh kode masing-masing layar internal itu.
        ▼
   FirebaseSyncAdapter::pushStatusUpdate() -- tulis balik field 'app_status'
   ke Firestore submissions/{uuid}
        │
        ▼
   careers/dashboard.blade.php baca 'app_status' -- status ter-update
   begitu HR ubah di internal, TANPA portal baca tm2hrr langsung (isolasi
   2-host tetap terjaga meski portal & internal masih 1 proses Laravel
   utk sekarang).
```

---

## 4. Field registrasi & identitas terkunci

Sesuai instruksi user, form registrasi (`careers/register.blade.php`):

| Field | Validasi | Catatan |
|---|---|---|
| No. KTP | `required, digits:16` | Unik (dicek `CandidateAuthService::findByNationalId`) |
| Nama Lengkap | `required, min:3` | Harus sesuai KTP (instruksi user, tidak divalidasi otomatis — kepercayaan kandidat) |
| Email | `required, email` | Unik, jadi identitas login |
| No. HP | `required, min:9, max:15` | |
| Password | `required, min:8, confirmed` | `Hash::make`, standar Laravel |

Setelah login, field **Nama/No. KTP/Email** di form lamar (`_apply-form-steps.blade.php`)
jadi **readonly** — dikunci dari akun terverifikasi, beda dari simulasi lama
(KTP-only, semua field bebas diisi ulang tiap kali).

---

## 5. Reuse dari simulasi lama (tidak ditulis dobel)

- **Field lamaran (step 1-11)** — SAMA PERSIS `portal.blade.php` (form
  "Data Calon Karyawan" LJM/HRD/P01/F02), diekstrak ke partial
  `resources/views/pages/careers/_apply-form-steps.blade.php`. `portal.blade.php`
  (simulasi lama, sekarang tidak ada route yang menunjuknya lagi) **TIDAK
  diubah** pakai partial ini — hindari risiko regresi di file yang sudah
  teruji, sedikit duplikasi kode diterima demi itu.
- **Adapter Plan A/B** (`FirebaseSyncAdapter`, `MariaDbPortalSyncAdapter`,
  `PortalSyncService`, `PortalBackendAdapterInterface`) — dari
  `recruitment_delta_plan_ab_local.md`, DIPERLUAS bukan ditulis ulang:
  - `PortalBackendAdapterInterface` +1 method: `findAllByCandidateUid()`
    (dashboard butuh SEMUA lamaran 1 akun, bukan cuma yang pending/terbaru).
  - `FirebaseSyncAdapter::pushStatusUpdate()` — dari no-op jadi IMPLEMENTASI
    NYATA (tulis field `app_status`/`status_pushed_at` ke Firestore).
  - `MariaDbPortalSyncAdapter::pushStatusUpdate()` — juga diimplementasi
    (kolom baru `app_status`/`status_pushed_at` di `crp_submission`, ALTER
    TABLE) supaya kontrak interface tetap simetris Plan A/B, meski portal
    profesional ini sendiri tidak lewat MariaDB.
  - `App\Support\Firestore\FirestoreRestClient` — diekstrak dari
    `FirebaseSyncAdapter` (tadinya isi REST-call+encode/decode ada di situ
    langsung) supaya dipakai bareng `CandidateAuthService` juga.

---

## 6. Yang BELUM dibangun (scope Flutter, jangan dikira lupa)

- **App Flutter (Android + iOS)** — kanal ke-2 yang dikonfirmasi user, BELUM
  digarap sama sekali. Beda tooling total (Dart/Flutter SDK, Firebase SDK
  native, App Store/Play Store), scope besar setara proyek sendiri — bukan
  perluasan kecil dari kanal web ini. Backend SUDAH siap dipakai bareng
  (Firestore collections `candidates`/`submissions` sama persis, tinggal
  Flutter app bicara ke Firestore langsung/Admin SDK sisi Laravel yang sama).
- **Firebase Storage sungguhan** — dokumen (CV/ijazah) masih ke disk `public`
  Laravel utk KEDUA kanal (lihat delta doc §5), bukan Cloud Storage.
- **Firebase project cloud asli** — masih Local Emulator Suite (`firebase/`),
  belum project GCP sungguhan (perlu akun+billing, keputusan user tersendiri).
- **OTP HP/SMS verification** — registrasi cuma verifikasi email (Laravel
  Mail, signed URL). No. HP ditangkap sbg data tapi tidak diverifikasi via
  OTP (butuh SMS gateway berbayar, di luar scope sesi ini).

---

## 7. File-file terkait (peta cepat)

```
app/Contracts/Recruitment/PortalBackendAdapterInterface.php
app/Services/Recruitment/{FirebaseSyncAdapter,MariaDbPortalSyncAdapter,PortalSyncService}.php
app/Services/Portal/CandidateAuthService.php
app/Support/Firestore/FirestoreRestClient.php
app/Observers/AppApplicationObserver.php
app/Providers/RecruitmentServiceProvider.php   -- binding adapter + observer
app/Http/Middleware/EnsurePortalCandidateAuthenticated.php
app/Mail/Portal/CandidateEmailVerification.php
app/Exceptions/Portal/CandidateAlreadyExistsException.php
app/Console/Commands/SyncRecruitmentPortal.php  -- php artisan recruitment:sync-portal

resources/views/layouts/portal.blade.php        -- layout publik (nav+footer sendiri)
resources/views/pages/careers/landing.blade.php
resources/views/pages/careers/vacancy-detail.blade.php
resources/views/pages/careers/register.blade.php
resources/views/pages/careers/login.blade.php
resources/views/pages/careers/apply.blade.php
resources/views/pages/careers/dashboard.blade.php
resources/views/pages/careers/_apply-form-steps.blade.php  -- partial step 1-11
resources/views/emails/portal/verify-email.blade.php

routes/web.php          -- grup prefix 'hrm/recruitment/portal', name 'careers.*'
tests/Feature/CareersPortalTest.php  -- 8 test, end-to-end termasuk sync+status-push
```

Firestore collections (emulator lokal, `firebase/firebase.json`):
`candidates/{uid}` (akun), `submissions/{uuid}` (lamaran).
