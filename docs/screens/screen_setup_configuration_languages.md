---
name: screen_setup_configuration_languages
description: Sub-komponen daftar bahasa aktif (sys_languages) + grid terjemahan teks UI (sys_translations) per key x per bahasa
---

# Configuration — Bahasa

**File**: `resources/views/pages/setup/configuration-languages.blade.php`
**Route**: TBD - cek routes/web.php (tidak punya route sendiri — dirender sebagai komponen Livewire anak lewat `@livewire('pages::setup.configuration-languages')` di dalam `resources/views/pages/setup/configuration.blade.php`, yang route-nya `/setup/configuration` -> `setup.configuration`)
**Koneksi DB utama**: sys (tm2sys, via `SysLanguage`/`SysTranslation`)
**Modul**: System & Setup

## Tujuan
Sub-komponen Configuration (`docs/configurationstandard.md` §3) berisi 2 bagian: (1) grid spreadsheet-editable daftar bahasa aktif + format angka regional (`sys_languages`, sudah ada tabelnya), dan (2) grid terjemahan teks UI (`sys_translations`, dipakai layar login/forgot-password) berbentuk 1 baris per `trans_key` dengan 1 kolom per bahasa aktif. Menambah bahasa baru di grid pertama otomatis menambah 1 kolom baru di grid terjemahan bawahnya (dibangun live dari `SysLanguage::all()`, bukan daftar statis) — cara "Add new country language" terwujud tanpa infrastruktur baru.

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
