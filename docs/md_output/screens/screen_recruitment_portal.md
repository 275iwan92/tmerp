---
name: screen_recruitment_portal
description: Simulasi lama portal kandidat (KTP-only, 12-step form) — TIDAK LAGI aktif sebagai entry point, digantikan Portal Rekrutmen Profesional di /hrm/recruitment/portal.
---

# Portal Candidate (Simulasi Lama) — Multi-step Form

**File**: `resources/views/pages/recruitment/portal.blade.php`
**Route**: TIDAK ADA route aktif yang menunjuk ke sini (lihat Catatan) — nama Livewire view `pages::recruitment.portal`
**Koneksi DB utama**: `tm2hrr` (baca) — penulisan lewat `PortalBackendAdapterInterface` (Plan A Firebase / Plan B `tm2crp` MariaDB, bukan langsung ke `tm2hrr`)
**Modul**: Recruitment (Internal) — simulasi portal kandidat (P1-P4), dibangun di dalam repo `tmerp` ini

## Tujuan
Simulasi portal kandidat lama: masuk pakai No. KTP saja (tanpa password/OTP sungguhan), lalu isi form 12-langkah yang mengikuti persis formulir resmi HR "DATA CALON KARYAWAN" (No. Dok LJM/HRD/P01/F02) — data pribadi, keluarga, pendidikan, organisasi, pengalaman kerja, aktivitas sosial & referensi, minat & konsep pribadi, riwayat kesehatan, bahasa/komputer/info lowongan, kesediaan & pernyataan, upload dokumen, lalu review & lamar lowongan.

**STATUS: file ini bukan lagi entry point aktif** — lihat bagian Catatan.

## Field & Kolom
Form 12 step (public properties banyak, lihat kode untuk daftar lengkap — >70 property). Ringkasan per step:
1. Data Pribadi (`name`, `nickname`, `national_id`, `gender`, `religion`, `email`, `phone`, `birth_date`, `address`, `address_domisili`, `vehicle_type`, `sim_types[]`)
2. Data Keluarga (`marital_status`, `marital_date`, `family[]` repeatable)
3. Pendidikan (`education[]`, `educationNonformal[]` repeatable)
4. Organisasi (`orgExperience[]` repeatable)
5. Pengalaman Kerja (`workExp[]` repeatable, banyak sub-field per baris)
6. Sosial & Referensi (`company_acquaintance`, `hobby`, dst + `professionalReferences[]`, `emergencyContacts[]`)
7. Minat & Konsep Pribadi (12 pertanyaan open-text)
8. Riwayat Kesehatan (`height_cm`, `weight_kg`, `blood_type`, `illness_history`, dst)
9. Bahasa/Komputer/Info Lowongan (`languageSkills[]`, `computerSkills[]`, `expected_salary`, dst)
10. Kesediaan & Pernyataan (`willingness[]` checklist 8 item, `agreement_signed`, `signature_name`)
11. Upload Dokumen (`cvFile`, `ijazahFile` — wajib)
12. Review & Lamar (`applyVacancyId`)

## Aksi / Method
- `mount()` — init `willingness` checklist semua `false`
- `adapter()` [protected] — resolve `PortalBackendAdapterInterface` dari container tiap dipakai (bukan property, karena service adapter tak aman diserialisasi antar-request Livewire)
- `willingItems()`, `openVacancies()`, `myApplications()` [Computed]
- `enter()` — validasi No. KTP wajib, prefill dari submission terakhir via `adapter()->findLatestByNationalId()`
- `addRow(string $prop, array $template)` / `removeRow(string $prop, int $i)` — helper generik tambah/hapus baris repeatable
- `nextStep()` / `prevStep()` — navigasi step, validasi minimal di step 1 (nama) & step 12 (pilih lowongan)
- `submitApplication()` — validasi lowongan dipilih + persetujuan pernyataan ditandatangani; kirim payload besar via `adapter()->saveSubmission()` (BUKAN tulis langsung ke `tm2hrr`)
- `backToLanding()` — kembali ke step 12 dari step selesai

## Tabel & Model yang Disentuh
- `VacVacancy` (`vac_vacancy`, `tm2hrr`) — baca daftar lowongan terbuka
- `CndCandidate`/`AppApplication` (`tm2hrr`) — dibaca (bukan ditulis langsung) di `myApplications()`, hanya terisi SETELAH sync worker (`recruitment:sync-portal`, tiap 2 menit) menarik dari backend portal aktif
- `PortalBackendAdapterInterface` — `FirebaseSyncAdapter` (Plan A, default) atau `MariaDbPortalSyncAdapter` (Plan B, `tm2crp`) — target tulis submission, dipilih via `config('recruitment.backend')`

## Alur / Business Logic Penting
REVISI 2026-08-30: `submitApplication()` tidak lagi menulis langsung ke `tm2hrr` — payload dikirim ke backend portal aktif (Plan A/B) lewat adapter, sesuai arsitektur 2-host terkunci (`docs/recruitment_tier3.md` §1). Internal (`tm2hrr`) baru terisi setelah sync worker jalan — `myApplications()` bisa tampil KOSONG sesaat setelah kirim lamaran, ini eventual-consistency yang disengaja, bukan bug.

## Catatan
- **BUKAN entry point aktif.** Verifikasi `routes/web.php`: prefix route `/hrm/recruitment/portal` (grup `careers.*`) sekarang mengarah ke `pages::careers.landing`/`careers.apply`/dst (Portal Rekrutmen Profesional baru, live 2026-08-30 dengan akun Firestore real) — BUKAN ke `pages::recruitment.portal` (file ini). Baris registrasi route lama ke file ini sudah dihapus (lihat git blame, disebut eksplisit di komentar kode `web.php`).
- File tetap ada di repo dengan sengaja (dijadikan sumber partial `_apply-form-steps.blade.php` untuk portal baru) — jangan dihapus, tapi jangan dianggap layar yang bisa diakses user sekarang.
- Sesuai CLAUDE.md §1 (Portal Kandidat Recruitment, kanal 2): ini adalah "simulasi lama" yang secara eksplisit sudah digantikan kanal publik `/hrm/recruitment/portal` yang baru.
