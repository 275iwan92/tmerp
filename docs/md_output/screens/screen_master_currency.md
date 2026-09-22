---
name: screen_master_currency
description: Master Mata Uang (Currency) — grid spreadsheet-editable, pilot pertama spreadsheetgridstandard.md
---

# Master Currency

**File**: `resources/views/pages/master/currency.blade.php`
**Route**: `master.currency` (`/master/currency`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
Layar CRUD untuk `fin_currency` — sebelumnya tabel ini cuma dipakai lewat dropdown di layar lain, belum pernah punya CRUD sendiri. Jadi **pilot #1** pola `docs/spreadsheetgridstandard.md` (keputusan user 2026-08-30): grid spreadsheet-editable (klik sel langsung edit, drag-fill AutoFill, "+ Baris" langsung dari tabel, simpan batch "Simpan Semua"), bukan pola grid+form modal standar (`griduistandard.md`). Cocok karena kolomnya sedikit & scalar murni.

## Field & Kolom
Kolom spreadsheet (`spreadsheetColumns()`):
- `currency_code` — text, wajib, tidak bisa diedit lagi setelah baris tersimpan (`editableWhenExisting: false`)
- `currency_name` — text, wajib
- `symbol` — text, opsional
- `counted_word` — text, opsional (kata hitung/terbilang)
- `activate` — boolean (toggle aktif)

Validasi (`spreadsheetValidateRow()`):
- `currency_code`: required, string, max:10, unique ke `tm2mst.fin_currency.currency_code` (di-uppercase & trim sebelum validasi)
- `currency_name`: required, string, max:100
- `symbol`: nullable, string, max:10
- `counted_word`: nullable, string, max:100
- `activate`: boolean

Default baris baru: semua string kosong, `activate = true`.

## Aksi / Method
- `spreadsheetKey()` → `'master-currency'`
- `spreadsheetColumns()` — definisi kolom grid
- `spreadsheetQuery()` — `FinCurrency::query()->orderBy('currency_code')`
- `spreadsheetRowId($row)` → `$row->id_currency`
- `spreadsheetNewRowDefaults()` — default baris baru
- `spreadsheetValidateRow()` — validasi per baris
- `spreadsheetPersistRow()` — insert/update, set `updated_by`/`created_by` dari `Auth::user()->login`
- `statTotal()`, `statActive()`, `statInactive()` — computed properties untuk stat box

Semua behavior CRUD/batch-save/AutoFill datang dari trait `App\Livewire\Concerns\HasSpreadsheetGrid` (dipakai lewat `use HasSpreadsheetGrid;`), komponen ini hanya mengisi kontrak (kolom/query/validasi/persist).

## Tabel & Model yang Disentuh
- `App\Models\Master\FinCurrency` → tabel `tm2mst.fin_currency` (koneksi `mst`). Tidak ada tabel lain disentuh langsung di layar ini.

## Alur / Business Logic Penting
- Tidak ada workflow confirm/approval — murni master sederhana dengan toggle `activate`.
- Layout: 3 stat box (Total/Aktif/Nonaktif) + 1 komponen `<x-grid.spreadsheet-table>`.

## Catatan
- Tidak ditemukan TODO/gap eksplisit di komentar kode.
- Konsumen: `master/exchange-rate.blade.php` memakai `FinCurrency::where('activate', true)` untuk populate dropdown mata uang di kolom `currency_code`-nya.
