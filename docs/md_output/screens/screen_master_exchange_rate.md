---
name: screen_master_exchange_rate
description: Master Kurs (Exchange Rate) — grid spreadsheet-editable, versioned per mata uang
---

# Master Kurs

**File**: `resources/views/pages/master/exchange-rate.blade.php`
**Route**: `master.exchange-rate` (`/master/exchange-rate`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
CRUD untuk `fin_exchange_rate` — sebelumnya cuma tabel tanpa layar. Menyimpan kurs tiap mata uang terhadap IDR (base currency), pola **versioned** persis CLAUDE.md §4: 1 baris `activate=1` per currency = kurs yang berlaku sekarang, `effective_date` untuk riwayat. Pakai pola spreadsheet-editable sama seperti Master Currency.

## Field & Kolom
Kolom spreadsheet (`spreadsheetColumns()`):
- `currency_code` — select, opsi dari `FinCurrency` yang `activate=true` (diurutkan by `currency_code`), wajib
- `rate_to_base` — number, wajib (kurs 1 unit mata uang = sekian IDR)
- `effective_date` — text format `YYYY-MM-DD`
- `activate` — boolean

Validasi (`spreadsheetValidateRow()`):
- `currency_code`: required, string, exists di `tm2mst.fin_currency.currency_code`
- `rate_to_base`: required, numeric, min:0
- `effective_date`: nullable, date
- `activate`: boolean
- Setelah validasi, `currency_id` di-resolve dari `currency_code` lewat lookup `FinCurrency::where('currency_code', ...)->value('id_currency')`.

Default baris baru: `currency_code` kosong, `rate_to_base` kosong, `effective_date` = tanggal hari ini, `activate = true`.

## Aksi / Method
- `spreadsheetKey()` → `'master-exchange-rate'`
- `spreadsheetColumns()`
- `spreadsheetQuery()` — `FinExchangeRate::query()->with('currency')->orderByDesc('activate')->orderBy('currency_id')`
- `spreadsheetRowId($row)` → `$row->id_exchange_rate`
- `spreadsheetNewRowDefaults()`
- `spreadsheetValidateRow()`
- `spreadsheetPersistRow()` — **logic versioned**: kalau baris yang disimpan `activate=true`, semua baris lain dengan `currency_id` sama otomatis di-set `activate=false` terlebih dulu (query `whereKeyNot($existingId)` supaya baris sendiri tidak ikut ter-nonaktifkan saat edit). Baru setelah itu insert/update baris ini.
- `statTotal()`, `statActive()` — computed untuk stat box

Trait `HasSpreadsheetGrid` menyuplai mekanisme CRUD/batch-save.

## Tabel & Model yang Disentuh
- `App\Models\Master\FinExchangeRate` → `tm2mst.fin_exchange_rate` (koneksi `mst`), relasi `currency()` ke `FinCurrency`.
- `App\Models\Master\FinCurrency` → `tm2mst.fin_currency` — sumber dropdown & lookup `currency_id`.

## Alur / Business Logic Penting
- **Rate versioned** (pola CLAUDE.md §4 "Rate fluktuatif"): hanya 1 baris `activate=1` per mata uang yang dianggap kurs berlaku; mengaktifkan baris baru otomatis menonaktifkan baris lama untuk currency yang sama — mirip pola "1 is_default" di Master Bahasa Configuration (disebut di komentar kode).
- Grid diurutkan dengan baris aktif (`activate` desc) muncul lebih dulu.
- Ada teks penjelasan statis di UI: "Kurs 1 unit mata uang = sekian IDR. Aktifkan 1 baris per mata uang untuk jadi kurs berlaku — baris lama untuk mata uang yang sama otomatis dinonaktifkan."

## Catatan
- Tidak ditemukan TODO/gap eksplisit di komentar kode selain referensi ke `docs/configurationstandard.md §2.4` dan `BACKLOG.md §2` sebagai sumber requirement awal (belum diverifikasi isi file tsb pada sesi dokumentasi ini — TBD kalau perlu detail lebih).
