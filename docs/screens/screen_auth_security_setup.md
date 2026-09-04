---
name: screen_auth_security_setup
description: Setup 4 pertanyaan keamanan akun (tgl lahir, nama ibu, HP, pertanyaan+jawaban) dipaksa sekali sebelum akun bisa dipakai normal
---

# Setup Keamanan Akun

**File**: `resources/views/pages/auth/security-setup.blade.php`
**Route**: `security.setup` (dikonfirmasi via komentar kode + pemakaian `route('menu')`/`route('login')` di dalamnya; layout `layouts::guest`)
**Koneksi DB utama**: sys (tm2sys) — `sec_user`, `sec_recovery_question`; menyentuh `emp_employee` (mst) secara tidak langsung lewat `SecurityRecoveryService`
**Modul**: System & Setup

## Tujuan
Layar wajib-isi 1 kali untuk tiap akun (`sec_user.security_setup_at` masih NULL) sebelum akun bisa dipakai normal — dipaksa lewat middleware `ForcePasswordChange` terlepas dari status `must_change_password`, supaya 146 akun lama yang sudah pernah ganti password tetap ter-cover (komentar kode 2026-08-27). Aktor: semua user yang login pertama kali / belum pernah setup. Data yang dikumpulkan dipakai nanti oleh alur "4-question password recovery" (`/forgot-password`, tanpa email link) untuk verifikasi identitas.

## Field & Kolom
- `birthDate` (string/date, wajib) — pre-fill otomatis dari `emp_employee` kalau akun tertaut karyawan (`birthDateFromEmployee` flag menandai sumbernya, ditampilkan sbg hint editable "perubahan akan direview HR dulu").
- `motherName` (string, wajib, min:2 max:150) — nama ibu kandung, input manual murni.
- `phone` (string, wajib, min:8 max:20) — pre-fill dari `emp_employee` kalau ada (`phoneFromEmployee`), kalau tidak diambil dari `$user->phone`.
- `questionId` (int, wajib, `exists:tm2sys.sec_recovery_question,id_question`) — dropdown dari `SecurityRecoveryService::activeQuestions()` (`#[Computed] questionOptions`).
- `answer` (string, wajib, min:2 max:150) — jawaban pertanyaan keamanan.

## Aksi / Method
- `mount(SecurityRecoveryService $service)` — resolve `birthDate`/`phone` dari data karyawan lewat `resolveBirthDateFromEmployee()`/`resolvePhoneFromEmployee()` kalau tersedia.
- `submit(SecurityRecoveryService $service)` — validasi semua field, update `$user->phone` di `sec_user` kalau berubah, panggil `SecurityRecoveryService::saveSetup()` (simpan jawaban+pertanyaan+tgl lahir+nama ibu) lalu `syncIdentityToEmployee()` (tulis balik ke `emp_employee` kalau relevan), redirect ke `route('menu')` (intended URL).
- `logout()` — keluar & invalidate session, opsi "Keluar dan lengkapi nanti" (skip sementara, bukan wajib selesai saat itu juga).

## Tabel & Model yang Disentuh
- `App\Models\Sys\SecUser` (tm2sys, `sec_user`) — kolom `phone`, `security_setup_at` (di-set oleh service saat `saveSetup()`).
- `sec_recovery_question` (tm2sys) — via `SecurityRecoveryService::activeQuestions()`.
- `emp_employee` (mst, tm2mst) — dibaca (resolve default) & ditulis (`syncIdentityToEmployee`) via service, query lintas-DB pola 1 (Eloquent query terpisah, bukan join).

## Alur / Business Logic Penting
- Dipicu paksa oleh `ForcePasswordChange` middleware (di luar file ini) — bukan halaman yang diakses lewat menu biasa.
- Tanggal lahir & HP auto-fill dari data karyawan bila akun tertaut employee, tapi tetap bisa diedit manual — perubahan manual "akan direview HR dulu sebelum berlaku" (bukan langsung overwrite `emp_employee`, lihat hint di UI).
- Bagian dari desain "4-question password recovery" (memory proyek: `/forgot-password` pakai birth date/mother/phone/security question, tanpa email link) — data yang diisi di sini adalah sumber kebenaran untuk verifikasi itu nanti.

## Catatan
Tidak ada TODO/gap eksplisit terlihat di komentar kode file ini.
