---
name: screen_setup_configuration_constants
description: Sub-komponen spreadsheet-grid untuk mengelola semua baris sys_constant (semua kategori dalam 1 grid, difilter kolom Kategori)
---

# Configuration — Semua Konstanta Sistem

**File**: `resources/views/pages/setup/configuration-constants.blade.php`
**Route**: TBD - cek routes/web.php (tidak punya route sendiri — dirender sebagai komponen Livewire anak lewat `@livewire('pages::setup.configuration-constants')` di dalam `resources/views/pages/setup/configuration.blade.php`, yang route-nya `/setup/configuration` -> `setup.configuration`)
**Koneksi DB utama**: sys (tm2sys, via `SysConstant`/`SysConstantCategory`)
**Modul**: System & Setup

## Tujuan
Sub-komponen Configuration (`docs/configurationstandard.md` §3) yang menampilkan SEMUA baris `sys_constant` (lintas kategori) dalam 1 grid spreadsheet-editable (pola `spreadsheetgridstandard.md`), bukan tab/komponen terpisah per kategori — karena trait `HasSpreadsheetGrid` hanya mendukung 1 grid per komponen Livewire. Pengelompokan per kategori dicapai lewat kolom `category_code` yang bisa difilter/diurutkan.

## Field & Kolom
Grid spreadsheet-editable (`x-grid.spreadsheet-table`), kolom:
- `category_code` (select, opsi dari `SysConstantCategory::orderBy('sort_order')->pluck('code')` — kode kategori polos mis. BRANDING/FINANCE, bukan label ramah).
- `constant_key` (text, wajib, tidak bisa diedit setelah baris ada — `editableWhenExisting: false`; auto-uppercase saat validasi).
- `constant_text` (text, opsional).
- `constant_number` (number, opsional).
- `description` (text, opsional).
- `activate` (boolean).

## Aksi / Method
Semua method berasal dari trait `App\Livewire\Concerns\HasSpreadsheetGrid` (kontrak standar spreadsheet grid):
- `spreadsheetKey()` — `'setup-constants'`.
- `spreadsheetColumns()` — definisi kolom di atas.
- `spreadsheetQuery()` — `SysConstant::orderBy('category_code')->orderBy('constant_key')`.
- `spreadsheetRowId($row)` — `id_constant`.
- `spreadsheetNewRowDefaults()` — baris kosong default.
- `spreadsheetValidateRow($data, $existingId)` — validasi (uppercase key, unique `tm2sys.sys_constant.constant_key`, `exists:tm2sys.sys_constant_category,code` untuk kategori).
- `spreadsheetPersistRow($data, $existingId)` — create/update dengan `created_by`/`updated_by` = login user.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SysConstant` — tabel `sys_constant` (tm2sys), read+write penuh via spreadsheet grid.
- `App\Models\Sys\SysConstantCategory` — tabel `sys_constant_category` (tm2sys), hanya dibaca untuk opsi dropdown kategori.
Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Tombol "+ New Record" langsung dari tabel, klik sel langsung edit, drag-fill AutoFill, simpan batch lewat "Simpan Semua" — mengikuti kontrak `x-grid.spreadsheet-table` (lihat `docs/spreadsheetgridstandard.md`).
- Catatan teknis kode: `<x-grid.spreadsheet-table>` select TIDAK mendukung opsi key=>label berbeda dari value — opsi kategori jadi kode mentah (BRANDING/FINANCE/dst), bukan label ramah "Identitas Aplikasi".

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode.
