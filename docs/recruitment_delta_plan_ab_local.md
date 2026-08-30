# DELTA — Portal Kandidat Plan A/B, implementasi lokal 2026-08-30

> Delta atas `recruitment_tier3.md` §1-3 (LOCKED sebagai desain arsitektur
> akhir, TIDAK direvisi). Dokumen ini catat bagian yang menyimpang saat
> implementasi lokal — kenapa, dan apa yang berubah kalau/waktu dipindah ke
> host produksi (VPS Plan B / project Firebase asli Plan A).

---

## 1. Kenapa dokumen delta ini ada

`recruitment_tier3.md` ditulis 2026-08-27, sebelum form kandidat diperluas
jadi ~50 kolom (migration `2026_08_29_000010_expand_candidate_form_fields.php`,
2026-08-29). Skema Plan B (`crp_submission`) yang tertulis di sana cuma
4 kolom inti (national_id/name/birth_date/address) — versi pra-ekspansi.
Saat user minta Plan A/B dibangun sungguhan (2026-08-30), skema itu sudah
tidak cukup untuk menampung form yang sekarang benar-benar dipakai portal
simulasi. Daripada diam-diam menyimpang dari desain terkunci, delta ini
mencatat eksplisit apa yang berubah dan kenapa.

---

## 2. Skema `crp_submission` — DIPERLUAS

**Tier 3 asli**: `submission_uuid, id_account, vacancy_code, national_id, name,
birth_date, address, submitted_at, sync_status, synced_at` + 4 tabel anak
relasional (`crp_family_member`, `crp_education`, `crp_work_experience`,
`crp_document`).

**Implementasi 2026-08-30**: `crp_submission` mirror **1:1 nama kolom skalar**
`tm2hrr.cnd_candidate` (supaya `PortalSyncService` bisa `array_intersect_key()`
langsung tanpa mapping manual) — lihat `database/ddl/tm2crp_plan_b.sql`. Seksi
berulang (family/education/education_nonformal/org_experience/work_experience)
**disimpan JSON di `crp_submission` itu sendiri**, BUKAN tabel anak relasional
terpisah seperti Tier 3 asli — alasan:

1. Layer ini adalah **staging mentah**, di-unpack ulang oleh sync worker ke
   tabel `cnd_*` relasional yang SUDAH ada & jadi sumber kebenaran — tidak ada
   kebutuhan query relasional di sisi `tm2crp` sendiri.
2. Analog struktur Firestore (Plan A) yang natural nested array/map — supaya
   kedua adapter (`MariaDbPortalSyncAdapter`/`FirebaseSyncAdapter`) menghasilkan
   bentuk array yang SAMA PERSIS ke `PortalSyncService`, tanpa transformasi
   beda-beda per backend.
3. Menghindari salin ulang ~30 kolom `cnd_work_experience` dkk ke DDL kedua
   (rawan typo/drift saat salah satu diubah tapi yang lain lupa).

`crp_document` TETAP tabel relasional (bukan JSON) — representasi "1 baris =
1 file" alami, sama seperti Tier 3 asli.

**Konsekuensi kalau field `cnd_candidate` berubah lagi nanti**: update
`database/ddl/tm2crp_plan_b.sql` + `PortalSyncService::applyOne()`
(`array_intersect_key` otomatis ambil kolom yang cocok, tapi kolom BARU di
`cnd_candidate` tetap perlu ditambah manual ke `crp_submission` DDL + payload
`portal.blade.php submitApplication()` kalau mau portal bisa isi field itu).

---

## 3. Interface — `PortalBackendAdapterInterface` (rename + perluasan)

Tier 3 §1.1 mendefinisikan `PortalSyncAdapterInterface` dengan 3 method,
semua sisi **worker** (internal menarik data): `fetchPendingSubmissions()`,
`markSynced()`, `pushStatusUpdate()`.

Implementasi nyata menambah 2 method sisi **portal** (`findLatestByNationalId()`,
`saveSubmission()`) dan rename jadi `PortalBackendAdapterInterface` — kenapa:
Tier 3 mengasumsikan portal = app Flutter terpisah yang bicara ke Firebase
SDK/REST API langsung, jadi tidak perlu kontrak PHP sisi tulis. Portal yang
benar-benar ada sekarang adalah **simulasi Livewire** hidup di app Laravel
yang sama — perlu jalur eksplisit dari `portal.blade.php` ke backend aktif,
dan kontrak ini itu.

Saat portal Flutter asli dibangun nanti, 2 method sisi-portal ini jadi
tidak terpakai dari PHP (Flutter bicara Firestore SDK/REST langsung) — boleh
dibiarkan menganggur di interface (harmless) atau dipisah ke interface
sendiri kalau mau lebih bersih. Bukan keputusan mendesak.

---

## 4. Firebase — Local Emulator Suite, bukan project cloud asli

`firebase/firebase.json` + `.firebaserc` (project id `demo-tmerp-crp`) — project
**demo**, bukan project Firebase sungguhan (tak ada akun Google Cloud/billing
terhubung). `FirebaseSyncAdapter` diarahkan ke emulator via env
`FIRESTORE_EMULATOR_HOST=127.0.0.1:8090` (`.env FIREBASE_USE_EMULATOR=true`).

**Menyalakan emulator** (manual, bukan bagian `npm run dev`/`php artisan serve`):
```bash
cd firebase
firebase emulators:start --only firestore,auth
```
Emulator UI: `http://127.0.0.1:4000` (atau port fallback kalau 4000 dipakai,
cek output CLI). Firestore: port 8090. Auth: port 9099.

**Java Runtime** — emulator Firestore/Auth butuh JRE. Installer MSI resmi
(`winget install Microsoft.OpenJDK.21`) **macet nunggu UAC elevation** di
sesi Claude Code non-interaktif (tidak ada yang bisa klik prompt). Diganti
unduhan **zip portable** (`https://aka.ms/download-jdk/microsoft-jdk-21.0.12.1-windows-x64.zip`)
ke `C:\Users\iwanp\.local\jdk\` — tidak perlu install/elevation, tinggal
tambahkan ke `PATH` kalau mau `java` langsung kepanggil di shell.

**Migrasi ke project Firebase asli nanti**: ganti `FIREBASE_USE_EMULATOR=false`,
isi `FIREBASE_PROJECT_ID` project asli + `FIREBASE_CREDENTIALS` (path
service-account JSON) — kode `FirebaseSyncAdapter` tidak perlu berubah,
`kreait/firebase-php` otomatis pakai kredensial asli begitu env emulator
kosong.

---

## 5. Simplifikasi yang sengaja TIDAK ditangani

- **Dokumen (CV/ijazah)** disimpan di disk `public` Laravel untuk KEDUA
  backend — bukan Firebase Storage emulator maupun object storage tm2crp
  sungguhan. `firebase/storage.rules` tetap disiapkan (sesuai Tier 3 §2)
  untuk saat portal Flutter asli dibangun dan benar-benar butuh Cloud
  Storage; demo ini fokus ke jalur data submission, bukan integrasi Storage.
- **`pushStatusUpdate()`** (Tier 3 §1.2, arah internal→portal) — no-op di
  kedua adapter. Portal simulasi baca status lamaran langsung query
  `tm2hrr.app_application` (`myApplications()` di `portal.blade.php`) karena
  portal & internal kebetulan 1 codebase di simulasi ini — TIDAK
  merepresentasikan desain akhir (portal Flutter asli wajib pakai
  `pushStatusUpdate` beneran, tidak boleh baca `tm2hrr` langsung).
- **Autentikasi** — `crp_candidate_account` (Plan B)/Firebase Auth (Plan A)
  ada di skema tapi TIDAK dipakai untuk login sungguhan; portal simulasi
  tetap KTP-only sesuai keputusan awal 2026-08-29 ("jangan investasi
  berlebihan di sisi auth" untuk simulasi/prototipe).

---

## 6. Cara switch Plan A ↔ Plan B

Ubah `.env`:
```
PORTAL_BACKEND=mariadb   # atau: firebase
```
Tidak perlu ubah kode — `App\Providers\RecruitmentServiceProvider` bind
`PortalBackendAdapterInterface` ke implementasi yang sesuai saat boot.
Kalau `PORTAL_BACKEND=firebase`, pastikan emulator (§4) sedang jalan sebelum
membuka portal atau menjalankan `php artisan recruitment:sync-portal`.
