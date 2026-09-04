---
name: screen_auth_login
description: Halaman login utama (username/email + password), termasuk panel akun contoh UAT.
---

# Login

**File**: `resources/views/pages/auth/login.blade.php`
**Route**: `login` (GET/handled by Livewire, `/login`, middleware `guest`)
**Koneksi DB utama**: sys (tm2sys) -- `sec_user`, `sec_login_attempt`, `sys_login_hero`, `sys_quotes`, `sys_translations`
**Modul**: System & Setup

## Tujuan
Halaman masuk aplikasi (full-screen, layout `layouts::guest`, terpisah dari shell aplikasi). Berisi panel hero (foto acak dari `sys_login_hero`, jam+tanggal live, kutipan acak dari `sys_quotes`) dan form login (username-atau-email + password). Menu bahasa (ID/EN) diterjemahkan lewat `sys_translations`. Juga menampilkan panel "Akun Uji Coba (UAT)" berisi 7 akun contoh lintas level (operator s/d direksi) dengan password sama -- ditandai secara eksplisit di komentar kode SEBAGAI HAL YANG HARUS DIHAPUS SEBELUM RILIS PRODUKSI.

## Field & Kolom
- `lang` (`#[Url]`, default 'id') -- bahasa.
- `loginField` (required) -- username atau email.
- `pswd` (required) -- password.
- `loginTouched`, `pswdTouched` -- flag untuk highlight border merah field kosong.
- `errorMessage` -- pesan error setelah submit gagal.

## Aksi / Method
- `t()` (Computed) -- ambil array terjemahan sesuai `lang` (fallback ke 'id').
- `setLang(string $lang)` -- ganti bahasa.
- `quote()` (Computed) -- kutipan acak dari `SysQuote::random()`.
- `hero()` (Computed) -- foto hero acak dari `SysLoginHero::random()`.
- `submit()` -- validasi field tidak kosong, cari user by login/email, cek `active` + `activate` + `Hash::check` password, catat SETIAP percobaan (sukses/gagal) ke `sec_login_attempt`, jika sukses `Auth::login()` + regenerate session + redirect ke `intended(route('menu'))`.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SecUser` -- akun login.
- `App\Models\Sys\SecLoginAttempt` -- log setiap percobaan login (login, success, ip_address, user_agent).
- `App\Models\Sys\SysLoginHero` -- foto hero panel kiri.
- `App\Models\Sys\SysQuote` -- kutipan random.
- `App\Models\Sys\SysTranslation` -- teks terjemahan UI.
Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Login diterima lewat username ATAU email pada field yang sama.
- Syarat login sukses: user ditemukan DAN `active` DAN `activate` DAN password cocok (dua kolom status berbeda dicek sekaligus -- `active` legacy dan `activate` kanonik, lihat CLAUDE.md §3 soal DEC-FIELDNAME-STATUS).
- Redirect setelah login pakai `redirect()->intended()` -- kembali ke halaman yang dituju sebelum diarahkan ke login (kalau ada), default ke `menu`.
- Panel drag-resize lebar panel form (`hero-side`) via Alpine `panelWidth`, murni UI, tak berpengaruh ke logic.

## Catatan
Panel "Akun Uji Coba (UAT)" (7 akun contoh, password sama `contoh!23`) SECARA EKSPLISIT ditandai di komentar kode sebagai sesuatu yang harus dihapus/disembunyikan sebelum rilis produksi sungguhan -- saat ini masih terlihat di layar login demi kemudahan tim UAT.
