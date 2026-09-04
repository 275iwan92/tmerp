---
name: screen_admin_hrm_orgposition_manage_page
description: Halaman index (list+create+edit dalam 1 halaman) untuk Resource Master Posisi Filament.
---

# Manage Org Positions (Filament Page)

**File**: `app/Filament/Clusters/Hrm/Resources/OrgPositions/Pages/ManageOrgPositions.php`
**Route/Panel URL**: TBD - perlu verifikasi (didaftarkan sebagai `'index' => ManageOrgPositions::route('/')` di `OrgPositionResource::getPages()`, kemungkinan `/admin/hrm/org-positions`).
**Tipe**: Page (`ManageRecords`, bagian dari Resource)
**Koneksi DB utama**: mst (tm2mst) -- mewarisi model `OrgPosition` dari `OrgPositionResource`.
**Modul**: Admin Dashboard (Filament) -- HRM > Organization

## Tujuan
Halaman tunggal (`ManageRecords`) untuk Resource Master Posisi -- grid + form create/edit tampil sebagai modal di halaman yang sama.

## Field & Kolom (Filament Schema)
Tidak ada field sendiri -- seluruh field form/table diwarisi dari `OrgPositionResource::form()`/`table()`.

## Aksi / Method
- `getHeaderActions(): array` -- mendaftarkan 1 aksi header: `CreateAction::make()`.

## Tabel & Model yang Disentuh
- `$resource = OrgPositionResource::class` -- mewarisi model `App\Models\Master\OrgPosition` (tm2mst, tabel `org_position`).

## Alur / Business Logic Penting
Identik pola dengan `ManageEmpEmployees` -- pola standar `ManageRecords` untuk Resource kecil/master di migrasi Filament HRM ini.

## Catatan
Tidak ada logic kustom/override tambahan di file ini selain `getHeaderActions()`. Tidak ada komentar TODO di kode.
