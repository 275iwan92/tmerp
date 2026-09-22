---
name: screen_production_master_process
description: Master data Proses produksi (Cetak, Laminasi, Potong, dst) dengan warna Gantt, hasil migrasi dari tm2old.m_process.
---

# Master Proses

**File**: `resources/views/pages/production/master-process.blade.php`
**Route**: `production.master-process` (`/production/master-process`)
**Koneksi DB utama**: mst (tm2mst) — model `App\Models\Master\ProdProcess`
**Modul**: Production Scheduling & Product Development

## Tujuan
Master paling sederhana di antara 3 master baru (Proses/Mesin/Kru) — hanya nama proses + warna untuk Gantt chart (belum diwire ke Gantt manapun saat ini). Data awal 19 baris dimigrasi dari `tm2old.m_process` via migration `2026_08_28_000027_migrate_process_master_from_tm2old.php`. Dipakai sebagai referensi dropdown di Master Crew, Process Rate, dan (rencana) MPS Gantt Chart. Aktor: PPIC/admin produksi.

## Field & Kolom
- `process_name` (string, required, max:200, unique per `tm2mst.prod_process.process_name`) — Nama Proses.
- `gantt_color` (nullable string max:10, input type="color", default `#2c3e69`) — Warna Gantt, dipakai bedakan proses di MPS Gantt Chart (belum diwire).
- `process_activate` (boolean, default true) — status aktif.

Grid: kolom Proses (identity-cell), Warna Gantt (swatch + hex), Status, Aksi. Search live pada `process_name`. Stat-row 3 kotak: Total Proses, Aktif, Nonaktif.

## Aksi / Method
- `processes()` [Computed] — query filter search, order by nama.
- `openModal(mode, id=null)` — buka form.
- `showGrid()` — kembali ke grid.
- `save()` — validasi (unique process_name, exclude current id saat edit) + create/update.
- `toggleActive(id)` — flip `activate`.
- `statTotal()`, `statActive()`, `statInactive()` [Computed].

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdProcess` → `tm2mst.prod_process`.

## Alur / Business Logic Penting
CRUD master murni, pola grid+form identik `master/machine.blade.php` (griduistandard.md). `gantt_color` disiapkan untuk MPS Gantt Chart tapi belum ada konsumen kode yang membacanya (TBD — perlu verifikasi ulang tiap sesi karena scheduling-board mungkin sudah pakai warna proses lain).

## Catatan
- `gantt_color` field sudah ada di skema & form tapi fitur Gantt Chart penuh belum mengonsumsinya secara pasti (perlu cek scheduling-board.blade.php untuk detail).
