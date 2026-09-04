---
name: screen_admin_hrm_orgposition_resource
description: Resource Filament Master Posisi (CRUD struktur posisi organisasi) di panel admin, cluster HRM.
---

# Master Posisi (Filament Resource)

**File**: `app/Filament/Clusters/Hrm/Resources/OrgPositions/OrgPositionResource.php`
**Route/Panel URL**: TBD - perlu verifikasi (kemungkinan `/admin/hrm/org-positions`; halaman `index` didaftarkan via `ManageOrgPositions::route('/')`).
**Tipe**: Resource
**Koneksi DB utama**: mst (tm2mst) -- tabel `org_position` via model `App\Models\Master\OrgPosition`.
**Modul**: Admin Dashboard (Filament) -- HRM > Organization

## Tujuan
Resource ini adalah terjemahan Filament dari layar lama `resources/views/pages/hrm/position.blade.php` (menu code `HRM_ORG_STRUCTURE`, "Master Posisi") -- CRUD posisi jabatan dalam struktur organisasi (kode, nama posisi, departemen, posisi atasan/"melapor ke", status aktif).

## Field & Kolom (Filament Schema)
**Form (`form()`)**:
- `position_code` (TextInput, label "Kode Posisi", required, maxLength 30)
- `position_name` (TextInput, label "Nama Posisi", required, maxLength 150)
- `department_id` (Select, label "Departemen", relationship `department` -> `department_name`, searchable, preload)
- `reports_to_position_id` (Select, label "Melapor ke", relationship `reportsTo` -> `position_name`, searchable, preload)
- `activate` (Toggle, label "Aktif", default true)

**Table (`table()`)** kolom:
- `position_code` (label "Kode", searchable)
- `position_name` (label "Nama Posisi", searchable, sortable)
- `department.department_name` (label "Departemen", placeholder "--")
- `reportsTo.position_name` (label "Melapor ke", placeholder "--")
- `activate` (IconColumn, label "Aktif", boolean)

Tidak ada filter kustom di `table()` (beda dari `EmpEmployeeResource` yang punya `SelectFilter`).

**Aksi baris**: `EditAction`, `DeleteAction`.
**Aksi toolbar**: `BulkActionGroup` berisi `DeleteBulkAction`.

## Aksi / Method
- `form(Schema $schema): Schema` -- definisi field form di atas.
- `table(Table $table): Table` -- definisi kolom & aksi grid di atas.
- `getPages(): array` -- mendaftarkan 1 halaman: `'index' => ManageOrgPositions::route('/')`.

## Tabel & Model yang Disentuh
- Model: `App\Models\Master\OrgPosition` (`$connection = 'tm2mst'`, `$table = 'org_position'`, `$primaryKey = 'position_id'`).
- Relasi yang dipakai: `department()` -> `belongsTo(OrgDepartment::class, 'department_id', 'department_id')`; `reportsTo()` -> `belongsTo(self::class, 'reports_to_position_id', 'position_id')` (self-referencing, tabel sama).
- Tidak ada query lintas-DB.

## Alur / Business Logic Penting
- Berada dalam `HrmCluster`, `$navigationGroup = 'Organization'` (data real `sys_app_menu`: parent group `HRM_ORG` label "Organization", `sort_order=5` -- grup PALING ATAS di cluster HRM, lebih dulu dari "Employee"), `$navigationLabel = 'Master Posisi'`, `$navigationSort = 10`.
- Posisi menu dipertahankan persis sesuai `sys_app_menu` sama seperti pola di `EmpEmployeeResource` (syarat mutlak: tidak merombak hierarki/urutan menu lama).
- Model `OrgPosition` juga punya `directReports()` (`hasMany` self-referencing kebalikan dari `reportsTo()`), tapi relasi ini TIDAK dipakai di Resource ini.

## Catatan
- Form ini mencakup field inti (`position_code`, `position_name`, `department_id`, `reports_to_position_id`, `activate`) tapi TIDAK mencakup field lain di `$fillable` model `OrgPosition`: `legacy_position_id`, `legacy_skill_rate`, `level_id`, `salary_hidden_from_hr` -- kemungkinan sama seperti `EmpEmployeeResource`, ini POC/contoh migrasi, bukan replikasi penuh layar shell lama. Perlu konfirmasi user apakah field ini akan ditambahkan.
- Tidak ada komentar TODO eksplisit di kode file ini.
