---
name: screen_auth_password_change
description: Halaman paksa ganti password saat user masih memakai password default.
---

# Ganti Password (Paksa)

**File**: `resources/views/pages/auth/password-change.blade.php`
**Route**: `password.change` (`/password/change`, middleware `auth`, DI LUAR `force.password` supaya tidak redirect loop)
**Koneksi DB utama**: sys (tm2sys) -- `sec_user` (lewat `PasswordResetService`)
**Modul**: System & Setup

## Tujuan
Halaman yang dipaksa tampil (lewat middleware `force.password`, dicek di route group lain) ketika user login memakai password default (habis di-reset Admin, atau akun baru). User wajib membuat password baru sebelum bisa lanjut ke aplikasi. `default_password` TIDAK diubah oleh alur ini -- tetap tersimpan sebagai fallback untuk fitur "Lupa Password" (lihat `screen_auth_forgot_password.md`).

## Field & Kolom
- `pswdNew` (required, string, min:8) -- password baru.
- `pswdConfirm` (required) -- konfirmasi, dicek manual sama dengan `pswdNew` (bukan rule `confirmed` Laravel, field name beda).
- `errorMessage` -- pesan error validasi/mismatch.

## Aksi / Method
- `submit(PasswordResetService $service)` -- validasi field, cek `pswdNew === pswdConfirm`, panggil `service->changeByUser($user, $pswdNew)` (bisa lempar `InvalidArgumentException` yang ditangkap jadi `errorMessage`), redirect ke `intended(route('menu'))` bila sukses.
- `logout()` -- keluar & kembali ke halaman login (opsi "Keluar dan masuk lagi nanti").

## Tabel & Model yang Disentuh
- `App\Models\Sys\SecUser` -- user yang sedang login.
- `App\Services\Security\PasswordResetService::changeByUser()` -- logic ganti password (validasi tambahan kemungkinan ada di service ini, tidak dibaca detail).
Tidak ada query lintas-DB langsung di file blade ini.

## Alur / Business Logic Penting
- Halaman ini SENGAJA ditaruh di luar middleware `force.password` supaya tidak terjadi redirect loop (halaman yang memicu force-redirect tidak boleh ikut kena redirect itu sendiri).
- Setelah sukses ganti password, redirect `intended()` -- kembali ke halaman tujuan awal sebelum dipaksa ke sini.

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode file ini.
