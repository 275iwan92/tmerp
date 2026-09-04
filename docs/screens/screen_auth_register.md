---
name: screen_auth_register
description: Form pendaftaran akun baru (customer/supplier/internal) dengan verifikasi email & WhatsApp OTP, akun menunggu approval admin
---

# Registrasi Akun

**File**: `resources/views/pages/auth/register.blade.php`
**Route**: `/register` -> name `register` (grup middleware `guest`)
**Koneksi DB utama**: sys (tm2sys, via `SecUser`/`SysNotification`/`SysQuote`/`SysTranslation`)
**Modul**: System & Setup

## Tujuan
Layar publik (tanpa login) untuk pendaftaran akun baru ke aplikasi ERP, dipakai oleh calon pengguna yang belum punya akun — Customer, Supplier, atau Internal User. Bagian dari halaman hero bertema (`layouts::guest`, sama pola visual dengan login/forgot-password), mendukung 2 bahasa (id/en) via `SysTranslation`. Akun yang berhasil didaftarkan berstatus nonaktif (`activate=false`) sampai disetujui admin — group & hak akses ditentukan admin saat approval, bukan saat registrasi.

## Field & Kolom
Form (bukan grid), seluruhnya public property Livewire:
- `ptype` (select, wajib) — `customer` | `supplier` | `internal`, menentukan label ID (`idLabel()`: NRP untuk internal, NIK KTP untuk lainnya).
- `name` (text, wajib) — nama lengkap.
- `email` (text, wajib, live-debounce 400ms) — divalidasi format + unik (`unique:tm2sys.sec_user,email`), harus diverifikasi (tombol "Verifikasi") sebelum submit.
- `loginPreview` (computed, readonly) — username diturunkan otomatis dari lokal-bagian email (sebelum `@`), ditampilkan readonly; keunikan sesungguhnya diselesaikan server-side saat `submit()` (sufiks angka bila bentrok).
- `phone` (text, wajib) — No. WhatsApp aktif, wajib verifikasi OTP sebelum submit.
- `otp` (text, muncul setelah `sendOtp()`) — kode OTP demo, minimal 4 digit.
- `idno` (text, wajib) — NIK 16 digit (customer/supplier) atau NRP bebas teks (internal).
- `pass` (password, wajib, min 8, kombinasi huruf & angka via regex) + `pass_confirmation` (wajib match `confirmed`).
- `lang` (`#[Url]`, id/en) — toggle bahasa via bendera 🇮🇩/🇬🇧.

## Aksi / Method
- `mount()` — validasi `lang` default ke `id` kalau tidak valid.
- `setLang(string $lang)` — ganti bahasa.
- `isInternal()` / `idLabel()` — helper label ID sesuai `ptype`.
- `updatedEmail()` — reset status verifikasi email saat email diubah.
- `updatedPtype()` — reset `idno` saat tipe pendaftar berubah.
- `verifyEmail()` — validasi format email (demo, hanya format — produksi: cek MX/deliverability + kirim tautan verifikasi, lihat komentar kode).
- `sendOtp()` — kirim OTP WhatsApp (demo — produksi: gateway WA Cloud API/vendor); mensyaratkan nomor tidak kosong.
- `verifyOtp()` — validasi OTP minimal 4 digit (demo, terima digit apa saja).
- `submit()` — validasi via `rules()`, wajib email+WA sudah terverifikasi, resolusi keunikan login (sufiks angka bila bentrok), buat `SecUser` baru (`active=false`, `activate=false`), kirim `SysNotification` ke semua user `is_admin=true` dengan link ke `security.index` tab users.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SecUser` — tabel `sec_user` (tm2sys, koneksi sys). Insert baris baru nonaktif.
- `App\Models\Sys\SysNotification` — tabel notifikasi (tm2sys). Broadcast ke semua admin.
- `App\Models\Sys\SysQuote` — kutipan random ditampilkan di panel hero.
- `App\Models\Sys\SysTranslation` — teks UI multi-bahasa (`SysTranslation::forLang($lang)`).
Semua di koneksi `sys` (tm2sys) — tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Akun baru SELALU lahir nonaktif — approval admin wajib sebelum bisa login (lihat panel "Pendaftaran menunggu approval" setelah `submit()` sukses).
- Verifikasi email & WhatsApp adalah **gate** sebelum submit boleh jalan (dicek manual di `submit()`, bukan lewat Laravel validation rule) — keduanya masih level demo (email cuma cek format, OTP terima sembarang angka ≥4 digit).
- Username (`login`) auto-derive dari email, resolusi tabrakan terjadi di server saat submit (bukan real-time saat mengetik).
- Notifikasi ke admin dikirim ke SEMUA user `is_admin=true` (bukan hanya 1 admin) — pola broadcast sederhana lewat `pluck('login')->each(...)`.

## Catatan
- Verifikasi email (`verifyEmail()`) dan OTP WhatsApp (`sendOtp()`/`verifyOtp()`) eksplisit ditandai "(demo)" di komentar kode — produksi butuh integrasi nyata (cek MX/deliverability + kirim tautan verifikasi untuk email; gateway WA Cloud API/vendor untuk OTP). Belum diimplementasikan.
