---
name: screen_setup_configuration
description: Layar Identifikasi — identitas aplikasi (nama/tagline/font/logo). Dulu layar induk "Configuration" gado-gado, dipecah 2026-09-07 jadi 4 route sendiri (lihat docs/configurationstandard.md)
---

# Identifikasi

> **DIPECAH 2026-09-07**: dulu 1 layar "Configuration" menggabung Identitas
> Aplikasi + Bahasa + Terjemahan + Semua Konstanta dalam 1 route. Sekarang
> jadi grup sidebar "Settings" (mirror "Security") dengan 4 submenu route
> sendiri-sendiri: **Identifikasi** (dokumen ini), **Language**
> (`screen_setup_configuration_languages.md`), **Localization**, **Constants**
> (`screen_setup_configuration_constants.md`). Detail lengkap:
> `docs/configurationstandard.md` §3.

**File**: `resources/views/pages/setup/identification.blade.php`
**Route**: `/setup/identification` -> name `setup.identification` (middleware `admin`)
**Kode menu**: `SET_CONFIG` (label sidebar "Identifikasi")
**Koneksi DB utama**: sys (tm2sys, via `SysConstant`)
**Modul**: System & Setup

## Tujuan
Layar admin-only untuk mengelola identitas aplikasi (nama, tagline, font, logo — dipakai di topbar, halaman login, dan panel Filament `/admin`; BUKAN nama company, itu tetap di Master Organization untuk struktur HR & kop dokumen cetak). Berdiri sendiri sejak 2026-09-07 (dulu bagian pertama layar Configuration gado-gado).

## Field & Kolom
Form Identitas Aplikasi (bukan grid):
- `appName` (text, wajib, max 100) — nama aplikasi.
- `appTagline` (text, opsional, max 150) — tagline.
- `appFontKey` (select, wajib) — kunci preset font dari `FontPresets::labels()`.
- `logo` (file upload, opsional, image max 1024KB) — logo aplikasi; ditampilkan sebagai thumbnail 40x40 jika sudah ada (`currentLogoPath`), dengan tombol Hapus.

## Aksi / Method
- `mount()` — load nilai awal dari `SysConstant` (`APP_NAME`, `APP_TAGLINE`, `APP_FONT_FAMILY`, `APP_LOGO_PATH`), default `'ERP TM'` bila belum ada.
- `fontPresetOptions()` — daftar opsi font dari `App\Support\Branding\FontPresets::labels()`.
- `saveBranding()` — validasi lalu `setConstant()` untuk APP_NAME/APP_TAGLINE/APP_FONT_FAMILY; jika ada file logo baru, hapus logo lama dari disk `public`, simpan baru ke folder `branding`, update `APP_LOGO_PATH`.
- `removeLogo()` — hapus file logo dari disk & set `APP_LOGO_PATH` ke null (kembali ke badge teks).
- `setConstant(key, value)` (protected helper) — `SysConstant::updateOrCreate()` dengan `updated_by` = login user.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SysConstant` — tabel `sys_constant` (tm2sys). Semua nilai identitas aplikasi disimpan sebagai baris key-value (`constant_key`/`constant_text`) di tabel yang sama dengan konstanta sistem lainnya.
- File logo disimpan di disk `public` (Laravel Storage), path relatif tersimpan di `SysConstant`.
Sub-komponen anak (`@livewire('pages::setup.configuration-languages')` dan `@livewire('pages::setup.configuration-constants')`) didokumentasikan terpisah — lihat `screen_setup_configuration_languages.md` dan `screen_setup_configuration_constants.md`.

## Alur / Business Logic Penting
- Section "Bahasa" dan "Semua Konstanta Sistem" BUKAN bagian dari komponen ini — dirender via `@livewire(...)` sebagai komponen Livewire anak terpisah, masing-masing dengan siklus hidup sendiri.
- "Tabular per kategori" untuk Semua Konstanta diwujudkan lewat kolom Kategori yang bisa difilter/diurutkan di grid spreadsheet, BUKAN tab terpisah per kategori — keputusan simplifikasi eksplisit (2026-09-02).
- Sidebar layar ini ditempatkan tepat di atas grup "Security" (`main-menu.blade.php`), sesuai instruksi user 2026-09-02.

## Catatan
Tidak ada TODO/gap eksplisit untuk komponen induk ini di komentar kode.
