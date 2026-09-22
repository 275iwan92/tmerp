---
name: screen_production_master_crew
description: Master data Crew (regu/operator) produksi, hasil migrasi dari tm2old.mst_machine (machine_type=CREW), dengan link opsional ke Master Proses.
---

# Master Crew

**File**: `resources/views/pages/production/master-crew.blade.php`
**Route**: `production.master-crew` (`/production/master-crew`)
**Koneksi DB utama**: mst (tm2mst) — model `App\Models\Master\ProdCrew` & `App\Models\Master\ProdProcess`
**Modul**: Production Scheduling & Product Development

## Tujuan
Layar master untuk mengelola daftar Crew (regu/operator produksi, tabel `PROD_MST_CREW`). Data awal 217 baris dimigrasi dari `tm2old.mst_machine` (filter `machine_type=CREW`) via migration `2026_08_28_000030_migrate_machine_crew_from_tm2old.php`. Setiap Crew bisa link opsional ke Proses Default (`process_id`) — link ini hanya terisi otomatis kalau label lama match persis ke Master Proses baru; yang tidak match dibiarkan kosong (kolom `Proses Belum Ter-link` di stat-row). Aktor: PPIC/admin produksi.

## Field & Kolom
Form (grid+form single component, pola sama `master/machine.blade.php`):
- `crew_name` (string, required, max:150) — Nama Crew.
- `process_id` (nullable, exists:tm2mst.prod_process,id_process) — Proses Default, dropdown dari `processOptions()` (ProdProcess aktif saja).
- `legacy_employee_code` (string, read-only) — jejak migrasi, ditampilkan HANYA jika terisi; BUKAN key aktif (beda format dari `emp_employee.employee_code`).
- `notes` (nullable string) — catatan tambahan (sering berisi label proses lama yang tak match).
- `crew_activate` (boolean, default true) — status aktif.

Grid: kolom Crew (identity-cell), Proses Default (badge/—), Status (AKTIF/NONAKTIF), Aksi. Search live-debounce pada `crew_name`. Stat-row 4 kotak: Total Crew, Aktif, Proses Belum Ter-link, Nonaktif.

## Aksi / Method
- `crews()` [Computed] — query ProdCrew + relasi `process`, filter search, order by nama.
- `processOptions()` [Computed] — ProdProcess aktif untuk dropdown.
- `openModal(mode, id=null)` — buka form new/edit, isi properti dari model kalau edit.
- `showGrid()` — kembali ke grid.
- `save()` — validasi + create/update ProdCrew, set `created_by`/`updated_by` dari `Auth::user()->login`.
- `toggleActive(id)` — flip `activate`, invalidasi cache computed terkait.
- `statTotal()`, `statActive()`, `statInactive()`, `statUnlinked()` [Computed] — angka stat-row.

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdCrew` → `tm2mst.PROD_MST_CREW` (nama tabel persis TBD — perlu verifikasi ke model, disebut `PROD_MST_CREW` di komentar kode).
- `App\Models\Master\ProdProcess` → `tm2mst.prod_process` (relasi many-to-one, dalam-DB, FK `process_id`).

## Alur / Business Logic Penting
Pola grid+form generik griduistandard.md (stat-row pastel, identity-cell, toolbar minimal, Aksi = Edit + Toggle-aktif karena ini grid Master). Tidak ada engine khusus di layar ini — murni CRUD master data.

## Catatan
- `legacy_employee_code` sengaja read-only, jejak migrasi saja, jangan dijadikan key aktif.
- Kolom Proses kosong di grid = label proses lama tidak match Master Proses baru saat migrasi — perlu dikoreksi manual via form edit.
- Nama tabel fisik persis (`PROD_MST_CREW` vs snake_case lain) TBD — perlu verifikasi ke definisi model `ProdCrew` bila dibutuhkan detail kolom PK dll.
