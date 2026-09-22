---
name: screen_auth_forgot_password
description: Lupa password berbasis 4 pertanyaan keamanan (tanpa email/link), reset password ke default_password.
---

# Lupa Password

**File**: `resources/views/pages/auth/forgot-password.blade.php`
**Route**: `password.request` (GET `/forgot-password`, middleware `guest`)
**Koneksi DB utama**: sys (tm2sys) -- `sec_user`, `sec_recovery_question`
**Modul**: System & Setup

## Tujuan
Alur "Lupa Password" self-service berbasis 4 pertanyaan keamanan (tanggal lahir, nama ibu kandung, nomor HP, jawaban pertanyaan keamanan pilihan user), MENGGANTIKAN alur link-email lama karena gateway email/WA belum tentu tersedia di server. Digunakan oleh siapa pun yang belum login (`guest` middleware) dan lupa password. Berhasil verifikasi 4-4nya -> password user dikembalikan ke `default_password` yang tersimpan (password awal yang diberikan Admin), BUKAN generate password baru.

## Field & Kolom
- `lang` (`#[Url]`, default 'id') -- bahasa halaman (id/en).
- Step 1: `loginField` (required) -- username atau email.
- Step 2: `birthDate` (required, date), `motherName` (required), `phone` (required), `answer` (required) -- jawaban 4 pertanyaan; label pertanyaan (`questionLabel`) diambil dari `sec_recovery_question` milik user yang sudah setup.
- `step` (int 1-3) -- 1=cari akun, 2=jawab 4 pertanyaan, 3=sukses.
- `errorMessage` -- pesan error generik (sengaja sama untuk "akun tak ada" & "belum setup" demi cegah enumerasi akun).

## Aksi / Method
- `findAccount(SecurityRecoveryService $service)` -- cari user by login/email, cek rate limit (`RateLimiter`, 8 percobaan/900 detik), cek `isSetupComplete()`, lanjut ke step 2.
- `verifyAnswers(SecurityRecoveryService $service)` -- validasi 4 jawaban via `service->verify()`, jika cocok panggil `service->resetToDefaultPassword($user)`, lanjut ke step 3.
- `backToStart()` -- reset semua state kembali ke step 1.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SecUser` -- akun login, relasi `securityQuestion`.
- `App\Services\Security\SecurityRecoveryService` -- logic verifikasi & reset password (tidak dibaca detail isinya di sesi ini, hanya dipanggil).
Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Rate limiting per kombinasi `loginField` + IP (8 percobaan, throttle 900 detik / 15 menit), diterapkan di kedua step (cari akun & verifikasi jawaban).
- Pesan error SENGAJA generik di kedua kasus gagal (akun tidak ada vs belum setup keamanan) untuk mencegah penyerang menebak akun mana yang valid.
- Reset mengembalikan ke `default_password` tersimpan (bukan generate acak) -- kalau user belum pernah punya `default_password` tersimpan, muncul error "Hubungi Administrator".

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode file ini.
