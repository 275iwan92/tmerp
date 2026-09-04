---
name: screen_admin_hrm_empemployee_manage_page
description: Halaman index (list+create+edit dalam 1 halaman) untuk Resource Master Employee Filament.
---

# Manage Emp Employees (Filament Page)

**File**: `app/Filament/Clusters/Hrm/Resources/EmpEmployees/Pages/ManageEmpEmployees.php`
**Route/Panel URL**: TBD - perlu verifikasi (didaftarkan sebagai `'index' => ManageEmpEmployees::route('/')` di `EmpEmployeeResource::getPages()`, jadi path relatif root resource, kemungkinan `/admin/hrm/emp-employees`).
**Tipe**: Page (`ManageRecords`, bagian dari Resource)
**Koneksi DB utama**: mst (tm2mst) -- mewarisi model `EmpEmployee` dari `EmpEmployeeResource`.
**Modul**: Admin Dashboard (Filament) -- HRM > Employee

## Tujuan
Halaman tunggal (single-page CRUD pattern `ManageRecords` bawaan Filament) untuk Resource Master Employee -- grid + form create/edit tampil sebagai modal di halaman yang sama, tanpa halaman `create`/`edit` terpisah.

## Field & Kolom (Filament Schema)
Tidak ada field sendiri -- seluruh field form/table diwarisi dari `EmpEmployeeResource::form()`/`table()`.

## Aksi / Method
- `getHeaderActions(): array` -- mendaftarkan 1 aksi header: `CreateAction::make()` (tombol "+ New" untuk buka modal form create).

## Tabel & Model yang Disentuh
- `$resource = EmpEmployeeResource::class` -- mewarisi model `App\Models\Master\EmpEmployee` (tm2mst, tabel `emp_employee`).

## Alur / Business Logic Penting
- Pola `ManageRecords` = grid + modal create/edit dalam 1 halaman (beda dari pola `ListRecords`+`CreateRecords`+`EditRecords` terpisah) -- konsisten dipakai juga di `ManageOrgPositions`.

## Catatan
Tidak ada logic kustom/override tambahan di file ini selain `getHeaderActions()`. Tidak ada komentar TODO di kode.
