---
name: screen_master_employee
description: Master Employee — HR Admin full-editable (tanpa approval), 6-tab profil karyawan, auto-provision login saat registrasi baru
---

# Master Employee

**File**: `resources/views/pages/master/employee.blade.php`
**Route**: `hrm.employee` (`/hrm/employee`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data (HRM)

## Tujuan
Layar HR Admin **full-editable** untuk profil karyawan (`emp_employee`) — **tanpa workflow approval**, beda dari sisi self-service karyawan yang selalu lewat pengajuan (`masteremployee_tier1.md` §10). Form 6-tab: Overview & Identitas, Job Alignment, Pendidikan & Sertifikasi, Keluarga, Karier & Kontrak, Kedisiplinan. Tab "Historical Audit Trail" dari mockup **sengaja tidak direplikasi** — tidak ada tabel audit generik di DDL (`TIER_3A_HRM.sql`), log kedisiplinan sudah representatif untuk kebutuhan ini.

## Field & Kolom
**Grid**: 2 mode tampilan — List (default, tabel) atau Card (`griduistandard.md` §11, **khusus Employee saja** karena satu-satunya master dengan foto asli, bukan avatar inisial generik — jangan roll out ke grid lain tanpa ditanya). Kolom: Employee (foto/avatar+nama+kode+posisi), Departemen, Kedisiplinan (badge `disciplineBadge()`: "Clean/No SP" hijau, "Verbal: N/6" kuning, "{SP} AKTIF" merah), Status Kerja (WORKING/DIRUMAHKAN/CUTI), Aksi (Edit+Toggle). Search di `full_name`/`employee_code`/`nik_ktp`. Stat box: Total Headcount, Clean/No SP, Verbal Warning, SP Aktif.

**Tab Overview & Identitas**: `employee_code` (readonly, auto-generate format `{company_code}-{seq5digit}`), `full_name`, `nickname`, `gender` (L/P), `birth_place`, `birth_date`, `nik_ktp`, `no_kk`, `npwp_number`, `religion` (dropdown 7 pilihan), `phone`, `email_personal`, `address_detail_ktp`/`address_detail_domisili` (minimal salah satu wajib diisi saat registrasi baru — domisili boleh kosong = "sama dengan KTP"), `join_date`, `work_status` (WORKING/DIRUMAHKAN/CUTI), `work_status_since`, `emergency_contact_name`/`relation`/`phone`, `emp_activate`. Upload foto profil (`photo`, Livewire temp file, image max 5MB, disimpan ke `hrm/{employee_id}/profile`).

**Tab Job Alignment**: `position_id` (required, FK `org_position`), `default_machine_id` (FK `prod_machine`), `default_shift_id` (FK `emp_shift`).

**Tab Pendidikan & Sertifikasi** (mini-modal `EmpEducation`): `edu_education_type` (SERTIFIKASI/SEMINAR/PELATIHAN/PENDIDIKAN_FORMAL), `edu_activity_name`, `edu_organizer`, `edu_activity_date`, `edu_cost`, `edu_funded_by` (PERUSAHAAN/PRIBADI/SHARING), `edu_is_bonding` (ikatan dinas).

**Tab Keluarga** (mini-modal `EmpFamily`): `fam_relation` (SUAMI/ISTRI/ANAK/AYAH/IBU/MERTUA_AYAH/MERTUA_IBU), `fam_full_name`, `fam_birth_date`, `fam_nik`. Maksimal 20 anak per karyawan; AYAH/IBU/MERTUA_AYAH/MERTUA_IBU masing-masing hanya boleh 1 baris.

**Tab Karier & Kontrak** (mini-modal `EmpContract`): `ctr_employment_type` (TETAP/KONTRAK/HARIAN/BORONGAN), `ctr_start_date`, `ctr_end_date` (harus setelah start), `ctr_salary_type` (PER_MONTH/PER_DAY/PER_UNIT), `ctr_salary_amount` (visibilitas digated `canSeeSalary()`), `ctr_is_probation`, `ctr_probation_end_date` (wajib kalau probation).

**Tab Kedisiplinan** (mini-modal `EmpDisciplinary`): `dis_type` (TEGURAN_VERBAL/SP1/SP2/SP3/ALPHA), `dis_incident_date`, `dis_reason`.

## Aksi / Method
- `employees()` — computed list + search, eager-load `position.department`+`disciplinaries`
- `metricTotal/metricClean/metricVerbal/metricSp()` — computed stat box (dihitung dari `verbalWarningCount()`/`activeSpType()` di model)
- `positionOptions/machineOptions/shiftOptions()` — computed dropdown
- `currentEmployee()` — computed, eager-load lengkap (position.department, defaultMachine, defaultShift, documents, contracts, educations, families, disciplinaries) — catatan: relasi `documents` di-eager-load tapi **tidak ditemukan render/UI-nya** di blade (TBD - perlu verifikasi apakah upload dokumen karyawan memang belum ada UI-nya).
- `canSeeSalary(contract)` — gate: admin selalu lihat; kalau posisi `salary_hidden_from_hr=true` → non-admin tidak lihat; selain itu gaji KONTRAK/BORONGAN saja yang terlihat non-admin (gaji TETAP/HARIAN disembunyikan dari HR Officer biasa)
- `disciplineBadge($e)` — helper badge kedisiplinan
- `setEmployeeView(view)` — toggle List/Card
- `showDirectory()` — kembali ke grid; **kalau baru registrasi** (`justRegistered`), cek dulu 4 relasi keluarga wajib (`missingMandatoryFamily()`) → paksa ke tab Keluarga kalau belum lengkap; lalu provisioning login otomatis (`EmployeeLoginProvisionService`) → tampilkan modal kredensial sekali-tampil
- `closeCredentialModal()` — tutup modal kredensial lalu lanjut ke grid
- `relationLabel()`, `missingMandatoryFamily()` — helper validasi keluarga wajib
- `openNew()/openEdit(id)` — buka form baru/edit
- `nextEmployeeCode(companyId)` (private) — generate kode `{company_code}-{seq}` (5 digit, per company)
- `save()` — 8 field "kunci user" **wajib hanya saat registrasi baru** (nama, NIK KTP, No KK, No HP, tanggal lahir, alamat, email — plus nama ibu kandung dicek terpisah di tab Keluarga), **tidak retroaktif** ke karyawan lama (editingId sudah ada → tetap nullable saat edit); set `justRegistered=true` kalau insert baru
- `removePhoto()`, `openPhotoModal()/closePhotoModal()` — kelola foto profil (lightbox)
- `toggleActive(id)` — toggle `activate`
- `openEducationModal()/saveEducation()` — CRUD `EmpEducation`
- `openFamilyModal()/saveFamily()` — CRUD `EmpFamily`, validasi max 20 anak + unik untuk relasi wajib, panggil `FamilyEmploymentCrossCheckService::checkFamilyMember()` (cek nepotisme silang)
- `markDeceased(familyId)` — tandai anggota keluarga meninggal
- `openContractModal()/saveContract()` — CRUD `EmpContract`, **hanya 1 kontrak aktif per employee** (nonaktifkan kontrak lama sebelum insert baru)
- `openDisciplinaryModal()/saveDisciplinary()` — CRUD `EmpDisciplinary`

## Tabel & Model yang Disentuh
- `App\Models\Master\EmpEmployee` → `tm2mst.emp_employee` (koneksi `mst`, PK `employee_id`)
- `App\Models\Master\EmpContract` → `tm2mst.emp_contract`
- `App\Models\Master\EmpEducation` → `tm2mst.emp_education`
- `App\Models\Master\EmpFamily` → `tm2mst.emp_family`
- `App\Models\Master\EmpDisciplinary` → `tm2mst.emp_disciplinary`
- `App\Models\Master\EmpShift` → `tm2mst.emp_shift` (dropdown default shift)
- `App\Models\Master\OrgCompany` → `tm2mst.org_company` (sumber prefix kode employee)
- `App\Models\Master\OrgPosition` → `tm2mst.org_position` (dropdown posisi, kolom `salary_hidden_from_hr`)
- `App\Models\Master\ProdMachine` → `tm2mst.prod_machine` (dropdown mesin default)
- `App\Models\Sys\SecUser` → koneksi `sys` (tm2sys) — dipakai tidak langsung lewat `EmployeeLoginProvisionService`, lintas-DB (provisioning akun login dari data employee di `mst`)
- `App\Services\Hrm\FamilyEmploymentCrossCheckService` — cek nepotisme silang saat tambah anggota keluarga
- `App\Services\Migration\EmployeeLoginProvisionService` — auto-provision akun `sec_user` saat karyawan baru "diangkat"

## Alur / Business Logic Penting
- **8 field wajib + 4 relasi keluarga wajib hanya saat registrasi baru** (keputusan user 2026-08-27) — dicek di `showDirectory()` via `$justRegistered`, **bukan retroaktif** ke 136+ karyawan lama yang sudah ada sebelum aturan ini.
- **Auto-provision login**: begitu karyawan baru selesai registrasi + keluarga wajib lengkap, sistem otomatis bikin akun login (`EmployeeLoginProvisionService`) dan menampilkan kredensial **sekali-tampil** (session-only, tidak disimpan) via modal — untuk dicetak IT/HR dan diserahkan ke karyawan. Ini menggantikan alur manual "Tambah Pengguna" yang khusus untuk akun non-employee (customer/supplier).
- **Gaji tersembunyi dari HR Officer**: gaji TETAP/HARIAN & posisi dengan `salary_hidden_from_hr=1` disembunyikan dari non-admin. `Auth::user()->is_admin` dipakai sebagai proxy role "Admin App" karena RBAC granular belum digarap.
- **1 kontrak aktif per employee** (`TIER_3A_HRM.sql` §2.2, di-enforce di level aplikasi bukan DB constraint) — kontrak lama otomatis dinonaktifkan saat kontrak baru dibuat.
- Maksimal 20 anak per karyawan; AYAH/IBU/MERTUA_AYAH/MERTUA_IBU unik 1 baris per relasi.
- Cross-check nepotisme otomatis tiap kali anggota keluarga baru ditambahkan.

## Catatan
- Relasi `documents` di-eager-load di `currentEmployee()` tapi tidak ditemukan tab/section yang merendernya di blade — TBD, perlu verifikasi apakah upload dokumen karyawan (KTP scan, ijazah, dll.) memang belum dibangun UI-nya atau ada di tempat lain.
- Lihat memory `project_family_data_mandatory_fields.md` (8-field wajib + cross-check nepotisme + dual-stored mother's name) dan `project_employee_photo_feature.md` (upload+thumbnail+lightbox foto profil).
