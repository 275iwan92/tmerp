---
name: screen_master_machine
description: Master Mesin/lini produksi (prod_machine) — khusus assignment presisi Offset Printing
---

# Master Mesin

**File**: `resources/views/pages/master/machine.blade.php`
**Route**: `master.machine` (`/master/machine`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
CRUD grid+form-modal untuk `prod_machine` — mesin/lini produksi. Menurut catatan di UI, **hanya Offset Printing** yang butuh assignment mesin presisi (banyak lini); Digital Printing & Indostry cukup pakai grid shift sederhana tanpa mesin. Toggle aktif/nonaktif dipakai (bukan hard delete) karena mesin bisa jadi target FK logis `prod_part_process.machine_id` — mencegah baris orphan.

## Field & Kolom
Form: `machine_code` (required, unique, max:20, auto-uppercase), `machine_name` (required, max:100), `machine_type` (dropdown: Cetak/Coating/Laminasi/Potong/Subkontraktor/Lainnya — `$machineTypes` hardcode di komponen), `division_id` (required, FK `org_division`, dropdown Divisi/SBU), `brand_model`, `year_acquired` (integer 4 digit), `serial_no`, `max_paper_size`, `color_count` (integer 0-255), `max_speed_per_hour` (integer, sheet/jam), `capacity_per_shift`, `gsm_min`/`gsm_max` (integer), `notes`, `machine_activate` (checkbox, disimpan ke kolom `activate`).

Grid: kolom Mesin (identity-cell: avatar+nama+kode), Jenis, Merk/Model, Status (badge AKTIF/NONAKTIF), Aksi (Edit + Toggle-aktif). Search (`search`, live debounce 300ms) di `machine_code`/`machine_name`. Stat box: Total Mesin, Aktif, Divisi Terpakai (distinct `division_id` yang aktif), Nonaktif.

## Aksi / Method
- `machines()` — computed list + search, eager-load `division`, order by `division_id`, `machine_code`
- `divisionOptions()` — computed dropdown Divisi (`activate=true`)
- `openModal(mode, id)` — buka form new/edit, isi field dari `ProdMachine::findOrFail()` kalau edit
- `showGrid()` — kembali ke grid
- `save()` — validasi + `unique:tm2mst.prod_machine,machine_code`, `machine_code` di-uppercase+trim, insert/update
- `toggleActive(id)` — toggle kolom `activate`
- `statTotal/statActive/statInactive/statDivisions()` — computed stat box

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdMachine` → `tm2mst.prod_machine` (koneksi `mst`, PK `machine_id`)
- `App\Models\Master\OrgDivision` → `tm2mst.org_division` (dropdown Divisi/SBU)

## Alur / Business Logic Penting
- Toggle aktif/nonaktif alih-alih hard delete — pola sama dengan `security/index.blade.php` & `master/uom.blade.php` (komentar eksplisit di kode), karena `machine_id` bisa jadi target FK logis dari `prod_part_process.machine_id` (lintas-modul, integritas dijaga di Laravel bukan DB, sesuai CLAUDE.md §2).
- Komponen sudah "componentized" (2026-08-27) — `avatarInitials()`/`avatarGradient()` sudah dipindah ke `App\Support\Grid\Avatar`, dipakai lewat `<x-grid.identity-cell>`.

## Catatan
- Tidak ada TODO/gap eksplisit di komentar kode selain catatan desain di atas.
- `division_id` menggantikan kolom teks bebas `division_sbu` versi lama (disebut di komentar kode).
