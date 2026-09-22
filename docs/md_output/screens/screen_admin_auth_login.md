---
name: screen_admin_auth_login
description: Halaman login panel Filament /admin, dioverride agar cocok dengan skema login kustom (kolom login/pswd, bukan email/password).
---

# Login (Filament Auth Page, panel /admin)

**File**: `app/Filament/Pages/Auth/Login.php`
**Route/Panel URL**: TBD - perlu verifikasi (didaftarkan via `->login(Login::class)` di `AdminPanelProvider`; halaman login bawaan Filament, biasanya `/admin/login`).
**Tipe**: Page (override `Filament\Auth\Pages\Login`)
**Koneksi DB utama**: sys (tm2sys) -- tabel `sec_user` via model `App\Models\Sys\SecUser` (dipakai lewat guard `web` bawaan Laravel/Filament, bukan query manual di file ini).
**Modul**: Admin Dashboard (Filament) -- Auth (lintas-modul, bukan bagian cluster HRM).

## Tujuan
Override halaman login bawaan Filament supaya field & proses autentikasi cocok dengan skema `sec_user` yang sudah ada (kolom `login`+`pswd`, BUKAN `email`+`password` bawaan Filament). Menurut komentar kode, tanpa override ini `attempt(['email' => ...])` akan SELALU gagal karena kolom `email` bukan identifier login di `SecUser` (lihat `SecUser::getAuthPassword()`). Guard `web` yang sama dipakai `/login` custom (shell lama) tetap dipakai apa adanya -- artinya sesi login yang sama dikenali baik di `/login` maupun `/admin` (tidak perlu login 2x).

## Field & Kolom (Filament Schema)
- Field `login` (menggantikan field `email` bawaan) -- `TextInput::make('login')`, label "Username", required, autocomplete, autofocus.
- Field `password` -- diwarisi dari `Filament\Auth\Pages\Login` (tidak didefinisikan ulang di file ini).

## Aksi / Method
- `getEmailFormComponent(): Component` -- override, ganti field `email` bawaan jadi `login` (lihat detail di atas). Nama method tetap `getEmailFormComponent` (kontrak dari parent class), meski isinya sudah bukan email.
- `getCredentialsFromFormData(array $data): array` -- override, susun array credentials untuk `Auth::attempt()` jadi `['login' => $data['login'], 'password' => $data['password']]` (bukan `['email' => ..., 'password' => ...]` bawaan).
- `throwFailureValidationException(): never` -- override, lempar `ValidationException` dengan key `'data.login'` (bukan `'data.email'` bawaan) supaya pesan error login gagal tetap muncul menempel ke field yang benar -- tanpa override ini pesan error akan "hilang" diam-diam karena field `data.email` sudah tidak ada di form.

## Tabel & Model yang Disentuh
- Model: `App\Models\Sys\SecUser` (tm2sys, tabel `sec_user`) -- disentuh secara tidak langsung lewat `Auth::attempt()`/guard `web` (provider `SecUser` didaftarkan di `config/auth.php`), bukan query eksplisit di file ini.

## Alur / Business Logic Penting
- Ini BUKAN pengganti halaman `/login` custom (shell lama) -- keduanya hidup berdampingan. `Login::class` di sini hanya dipakai Filament saat guest (belum ada sesi) mencoba akses `/admin` dan perlu diarahkan ke halaman login.
- Validasi kredensial sesungguhnya (cek `active`/`activate`, `Hash::check`, dsb.) ada di dalam `Auth::attempt()` bawaan Laravel yang memanggil `SecUser::getAuthPassword()` -- TIDAK ada logic autentikasi kustom tambahan (seperti pencatatan `sec_login_attempt` di layar `/login` custom) yang terlihat di file Filament ini.

## Catatan
- Perbedaan dengan `screen_auth_login.md` (halaman `/login` custom shell lama): halaman itu mencatat SETIAP percobaan login (sukses/gagal) ke `sec_login_attempt` dan punya panel hero/UAT -- fitur-fitur itu TIDAK terlihat di `Login.php` versi Filament ini (scope file ini hanya field mapping + credentials, tidak ada logging eksplisit). Perlu verifikasi terpisah apakah `sec_login_attempt` tetap tercatat lewat mekanisme lain (mis. event listener global) saat login lewat `/admin/login`, atau memang belum ada.
- Tidak ada komentar TODO eksplisit lain di file ini.
