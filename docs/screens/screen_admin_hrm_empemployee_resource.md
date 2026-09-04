---
name: screen_admin_hrm_empemployee_resource
description: Resource Filament Master Employee (CRUD karyawan) di panel admin, cluster HRM.
---

# Master Employee (Filament Resource)

**File**: `app/Filament/Clusters/Hrm/Resources/EmpEmployees/EmpEmployeeResource.php`
**Route/Panel URL**: TBD - perlu verifikasi (mengikuti pola cluster Filament, kemungkinan `/admin/hrm/emp-employees`; halaman `index` didaftarkan via `ManageEmpEmployees::route('/')`).
**Tipe**: Resource
**Koneksi DB utama**: mst (tm2mst) -- tabel `emp_employee` via model `App\Models\Master\EmpEmployee`.
**Modul**: Admin Dashboard (Filament) -- HRM > Employee

## Tujuan
Resource ini adalah terjemahan Filament dari layar lama `resources/views/pages/hrm/employee.blade.php` (menu code `HRM_EMPLOYEE`, "Master Employee") -- CRUD data karyawan (kode, nama, posisi, KTP, telepon, tanggal bergabung, status kerja, status aktif). Menurut komentar kode, ini "terjemahan contoh" pertama dari layar shell lama ke Filament Resource dalam migrasi bertahap modul HRM.

## Field & Kolom (Filament Schema)
**Form (`form()`)**:
- `employee_code` (TextInput, label "Kode Karyawan", required, maxLength 30)
- `full_name` (TextInput, label "Nama Lengkap", required, maxLength 150)
- `position_id` (Select, label "Posisi", relationship `position` -> `position_name`, searchable, preload)
- `nik_ktp` (TextInput, label "No. KTP", maxLength 20)
- `phone` (TextInput, label "No. Telepon", tel, maxLength 30)
- `join_date` (DatePicker, label "Tanggal Bergabung")
- `work_status` (Select, label "Status Kerja", opsi: PERMANENT="Karyawan Tetap", CONTRACT="Kontrak", PROBATION="Masa Percobaan")
- `activate` (Toggle, label "Aktif", default true)

**Table (`table()`)** kolom:
- `employee_code` (label "Kode", searchable)
- `full_name` (label "Nama", searchable, sortable)
- `position.position_name` (label "Posisi", placeholder "--")
- `join_date` (label "Bergabung", format `d M Y`, sortable)
- `work_status` (label "Status Kerja", badge)
- `activate` (IconColumn, label "Aktif", boolean)

**Filter**: `SelectFilter` pada `work_status` (opsi sama seperti form).

**Aksi baris**: `EditAction`, `DeleteAction`.
**Aksi toolbar**: `BulkActionGroup` berisi `DeleteBulkAction`.

## Aksi / Method
- `form(Schema $schema): Schema` -- definisi field form di atas.
- `table(Table $table): Table` -- definisi kolom, filter, dan aksi grid di atas.
- `getPages(): array` -- mendaftarkan 1 halaman: `'index' => ManageEmpEmployees::route('/')`. Hanya 1 halaman (pola `ManageRecords`, form create/edit tampil sebagai modal di halaman index yang sama, bukan halaman terpisah `create`/`edit`).

## Tabel & Model yang Disentuh
- Model: `App\Models\Master\EmpEmployee` (`$connection = 'tm2mst'`, `$table = 'emp_employee'`, `$primaryKey = 'employee_id'`).
- Relasi yang dipakai di Resource ini: `position()` -> `belongsTo(OrgPosition::class, 'position_id', 'position_id')` (tabel `org_position`, sama-sama tm2mst, FK dalam-DB).
- Tidak ada query lintas-DB di Resource ini.

## Alur / Business Logic Penting
- Berada dalam `HrmCluster` (level Module), `$navigationGroup = 'Employee'` (level Group, data real `sys_app_menu`: parent group `HRM_MASTER` label "Employee", `sort_order=10`), `$navigationLabel = 'Master Employee'`, `$navigationSort = 10` (item `HRM_EMPLOYEE`, `sort_order=10`) -- posisi menu dipertahankan persis sesuai hierarki lama, sesuai syarat mutlak migrasi (tidak boleh merombak urutan/hierarki menu).
- Komentar kode menandaskan properti override (`$navigationGroup` bertipe `UnitEnum|string|null` dari parent) harus invariant persis dengan tipe di parent class `Resource`, walau isinya cuma string biasa.
- Tidak ada `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave` kustom -- data form disimpan apa adanya oleh Filament (`$fillable` di model `EmpEmployee` menentukan kolom yang bisa diisi massal).

## Catatan
- Form Filament ini HANYA mencakup subset kolom dari model `EmpEmployee` yang sebenarnya jauh lebih kaya (lihat `$fillable` model: ada `legacy_employee_id`, `company_id`, `nickname`, `gender`, `birth_place`, `birth_date`, `no_kk`, `npwp_number`, `religion`, `marital_status`, `email_personal`, alamat KTP/domisili, `resign_date`/`resign_reason`, `photo_path`, kontak darurat, `default_machine_id`, `default_shift_id`, dst.) -- field-field ini TIDAK muncul di Resource Filament ini, kemungkinan besar karena ini memang "terjemahan contoh" (POC migrasi), bukan replikasi penuh dari layar Master Employee shell lama yang sudah lebih lengkap (lihat memory `project_employee_photo_feature.md`, `project_family_data_mandatory_fields.md`).
- Tidak ada komentar TODO eksplisit di file ini soal gap tersebut -- perlu konfirmasi user apakah Resource ini akan dilengkapi lebih lanjut atau memang tetap sebatas subset untuk keperluan POC/migrasi HRM cluster.
