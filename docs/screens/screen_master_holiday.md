---
name: screen_master_holiday
description: Master Hari Libur (Holiday) — grid spreadsheet-editable, seed tanggal tetap Masehi
---

# Master Hari Libur

**File**: `resources/views/pages/master/holiday.blade.php`
**Route**: `master.holiday` (`/master/holiday`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
CRUD untuk `sys_holiday` — daftar hari libur yang dipakai modul HR (attendance/scheduling, dst — konsumen persis belum diverifikasi di sesi ini, TBD). Seed awal cuma tanggal Masehi tetap (Tahun Baru, Buruh, Pancasila, Kemerdekaan, Natal). Hari libur lunar (Idul Fitri dst, tanggal beda tiap tahun sesuai SKB 3 Menteri) sengaja belum diisi — ditambahkan manual tiap kalender resmi tahun berjalan terbit. Pola spreadsheet-editable sama seperti Master Currency/Kurs.

## Field & Kolom
Kolom spreadsheet (`spreadsheetColumns()`):
- `holiday_date` — text format `YYYY-MM-DD`, wajib, tidak bisa diedit lagi setelah tersimpan (`editableWhenExisting: false`)
- `holiday_name` — text, wajib
- `holiday_type` — select, opsi `NATIONAL` / `COMPANY`
- `activate` — boolean

Validasi (`spreadsheetValidateRow()`):
- `holiday_date`: required, date, unique ke `tm2mst.sys_holiday.holiday_date`
- `holiday_name`: required, string, max:150
- `holiday_type`: required, in `NATIONAL,COMPANY`
- `activate`: boolean

Default baris baru: `holiday_date` kosong, `holiday_name` kosong, `holiday_type = 'NATIONAL'`, `activate = true`.

## Aksi / Method
- `spreadsheetKey()` → `'master-holiday'`
- `spreadsheetColumns()`
- `spreadsheetQuery()` — `SysHoliday::query()->orderBy('holiday_date')`
- `spreadsheetRowId($row)` → `$row->id_holiday`
- `spreadsheetNewRowDefaults()`
- `spreadsheetValidateRow()`
- `spreadsheetPersistRow()` — insert/update biasa, set `updated_by`/`created_by`
- `statTotal()`, `statNational()` — computed untuk stat box

## Tabel & Model yang Disentuh
- `App\Models\Master\SysHoliday` → `tm2mst.sys_holiday` (koneksi `mst`).

## Alur / Business Logic Penting
- Tidak ada workflow confirm/approval — murni master sederhana.
- Catatan UI statis: "Hari libur tanggal-tetap Masehi sudah diisi. Hari libur lunar (Idul Fitri/Idul Adha/Nyepi/Imlek/Waisak/dst) tanggalnya beda tiap tahun — tambah manual lewat '+ Baris' begitu kalender resmi tahun berjalan terbit."

## Catatan
- Referensi asal fitur: `docs/BACKLOG.md` item "mudah" #1 (2026-09-02) — isi detail item tersebut belum diverifikasi ulang di sesi ini.
- Modul konsumen `sys_holiday` (kemungkinan HR Attendance/Time & Shift) belum ditelusuri di sesi ini — TBD perlu verifikasi lanjutan bila dibutuhkan.
