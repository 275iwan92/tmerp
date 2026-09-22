---
name: screen_careers_register
description: Halaman registrasi akun kandidat baru Portal Karir (No. KTP+Nama+Email+No.HP+password) dengan verifikasi email.
---

# Daftar Akun Kandidat (Register Karir)

**File**: `resources/views/pages/careers/register.blade.php`
**Route**: `careers.register` (GET/POST `/hrm/recruitment/portal/daftar`, di luar middleware `auth`/`portal.candidate` -- publik)
**Koneksi DB utama**: Firebase/Firestore (via `App\Services\Portal\CandidateAuthService`, collection `candidates`)
**Modul**: Recruitment Portal (Publik)

## Tujuan
Form registrasi akun kandidat baru. Field sesuai instruksi user 2026-08-30: No. KTP, Nama (sesuai KTP), Email, No. HP, Password. Setelah submit sukses, akun langsung auto-login (session diisi) supaya kandidat bisa browse/mulai isi lamaran, TAPI email verifikasi tetap wajib sebelum bisa `submitApplication()` di halaman apply.

## Field & Kolom
Public property Livewire:
- `national_id` (string, required, `digits:16`, pesan custom "No. KTP harus 16 digit angka.") -- `wire:model="national_id"`
- `name` (string, required, `min:3`) -- `wire:model="name"`
- `email` (string, required, format email) -- `wire:model="email"`
- `phone` (string, required, `min:9`, `max:15`) -- `wire:model="phone"`
- `password` (string, required, `min:8`, `confirmed`) -- `wire:model="password"`
- `password_confirmation` (string) -- `wire:model="password_confirmation"`
- `registered` (bool, default false) -- flag tampilan sukses (bukan input)

Tidak ada dropdown/lookup.

## Aksi / Method
- `register(CandidateAuthService $auth)`:
  1. Validasi 5 field wajib di atas.
  2. Panggil `$auth->register([...])` -- cek duplikat via `findByEmail()` dan `findByNationalId()`, lempar `CandidateAlreadyExistsException` kalau salah satu sudah ada (pesan berbeda: "Email sudah terdaftar." vs "No. KTP sudah terdaftar.").
  3. Kalau exception tertangkap -- `addError('email', $e->getMessage())`, return (tidak lanjut kirim email).
  4. Kalau sukses -- generate `URL::temporarySignedRoute('careers.verify-email', 60 menit, ['uid' => $candidate['_docId']])`, kirim `Mail::to($email)->send(new CandidateEmailVerification(...))`.
  5. Auto-login: `session(['portal_candidate_uid' => $candidate['_docId']])`, `session()->regenerate()`.
  6. Set `$this->registered = true` (tampilkan layar "Cek Email Anda").

## Tabel & Model / Firestore Collection yang Disentuh
- **Firestore collection `candidates`**:
  - Baca: `findByEmail()`, `findByNationalId()` (cek duplikat, `listDocuments('candidates')` lalu filter di PHP).
  - Tulis: `setDocument('candidates', $uid, $candidate)` -- dokumen baru berisi `national_id`, `name`, `email`, `phone`, `password_hash` (`Hash::make()`), `email_verified_at` (null), `created_at`.
- Kirim email via `App\Mail\Portal\CandidateEmailVerification` (mailable, bukan tabel/collection).

## Alur / Business Logic Penting
- UID kandidat = UUID v4 (`Str::uuid()`), dipakai sebagai document ID Firestore DAN sebagai `_docId` yang disimpan ke session (`portal_candidate_uid`).
- Link verifikasi email pakai Laravel signed URL (`temporarySignedRoute`), berlaku 60 menit, route target `careers.verify-email` (didefinisikan di `routes/web.php`, middleware `signed`) yang memanggil `CandidateAuthService::markEmailVerified($uid)`.
- Setelah `registered=true`, form disembunyikan, diganti kartu "Cek Email Anda" dengan tombol "Lihat Lowongan" (ke `careers.landing`).
- Layout: `layouts::portal`, title: "Daftar Akun".

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode. Catatan desain: field identitas ini nantinya dikunci (readonly) saat pengisian form apply -- lihat `screen_careers_apply.md`.
