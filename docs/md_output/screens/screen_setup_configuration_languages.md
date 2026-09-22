---
name: screen_setup_configuration_languages
description: 2 layar terpisah sejak 2026-09-07 — Language (sys_languages) dan Localization (sys_translations, grid terjemahan per key x per bahasa)
---

# Language & Localization

> **DIPECAH 2026-09-07** dari 1 komponen gabungan jadi 2 route sendiri
> (lihat `docs/configurationstandard.md` §3.2-3.3). Dulu tidak punya route
> sendiri (di-embed di layar Configuration gado-gado).

## Language

**File**: `resources/views/pages/setup/language.blade.php`
**Route**: `/setup/language` -> name `setup.language` (middleware `admin`)
**Kode menu**: `SET_LANG`
**Koneksi DB utama**: sys (tm2sys, via `SysLanguage`)

Grid spreadsheet-editable daftar bahasa aktif + format angka regional (code/name/decimal_separator/thousand_separator/is_default). Menambah baris di sini otomatis menambah 1 kolom baru di grid Localization.

## Localization

**File**: `resources/views/pages/setup/localization.blade.php`
**Route**: `/setup/localization` -> name `setup.localization` (middleware `admin`)
**Kode menu**: `SET_LOCALIZATION`
**Koneksi DB utama**: sys (tm2sys, via `SysTranslation`)

Grid terjemahan teks UI (`sys_translations`, dipakai layar login/forgot-password lewat `SysTranslation::forLang()`) berbentuk 1 baris per `trans_key` dengan 1 kolom per bahasa aktif — bukan spreadsheet-editable biasa (di luar kontrak `HasSpreadsheetGrid`), kolom bahasa dibangun live dari `SysLanguage::all()`.

## Field & Kolom
**Grid 1 — Bahasa Tersedia** (spreadsheet-editable):
- `code` (text, wajib, tidak bisa diedit setelah ada — `editableWhenExisting: false`, auto-lowercase).
- `name` (text, wajib) — nama bahasa.
- `decimal_separator` (text, wajib, max 1 char).
- `thousand_separator` (text, wajib, max 1 char).
- `is_default` (boolean) — set true otomatis mematikan `is_default` bahasa lain (hanya 1 default).

**Grid 2 — Terjemahan Teks UI** (tabel manual, bukan `HasSpreadsheetGrid` — bentuknya 1 baris = N kolom per bahasa, di luar kontrak trait "1 baris = 1 record"):
- Kolom `trans_key` (readonly, dari `SysTranslation` distinct keys).
- 1 kolom teks per bahasa aktif — input Alpine `x-model` lokal (`edits` object), disimpan batch lewat tombol "Simpan Terjemahan".

## Aksi / Method
Bahasa (dari trait `HasSpreadsheetGrid`):
- `spreadsheetKey()` — `'setup-languages'`.
- `spreadsheetColumns()`, `spreadsheetQuery()` (`orderByDesc('is_default')->orderBy('code')`), `spreadsheetRowId()` (pakai `code` sebagai id, bukan integer), `spreadsheetNewRowDefaults()`.
- `spreadsheetValidateRow()` — lowercase code, unique `tm2sys.sys_languages.code`.
- `spreadsheetPersistRow()` — jika `is_default` true, reset semua `is_default` lain jadi false dulu.

Terjemahan (method biasa, bukan bagian trait):
- `getLanguagesProperty()` — computed list bahasa (urut default dulu).
- `getTranslationRowsProperty()` — bangun array 1 baris per `trans_key`, kolom = kode bahasa, isi dari `SysTranslation`.
- `saveTranslations()` — terima `translationEdits` array (dikirim dari Alpine `edits` via `$wire.translationEdits = edits`), `updateOrCreate` per (lang_code, trans_key).

## Tabel & Model yang Disentuh
- `App\Models\Sys\SysLanguage` — tabel `sys_languages` (tm2sys), full CRUD via spreadsheet grid.
- `App\Models\Sys\SysTranslation` — tabel `sys_translations` (tm2sys), read (pivot per key x lang) + write (`saveTranslations()`).
Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Hanya 1 bahasa boleh `is_default=true` — dijamin di `spreadsheetPersistRow()` (reset massal sebelum set baru), bukan constraint DB.
- Grid terjemahan sengaja TIDAK pakai `HasSpreadsheetGrid` karena bentuk datanya beda dari kontrak trait (1 baris trait = 1 record 1 tabel; di sini 1 baris = N kolom lintas bahasa) — ditangani manual dengan state Alpine lokal (`edits`) yang di-flush ke Livewire hanya saat tombol Simpan diklik (bukan live per-keystroke, demi performa).

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode.
