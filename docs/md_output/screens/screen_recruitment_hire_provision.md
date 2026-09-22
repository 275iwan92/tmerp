---
name: screen_recruitment_hire_provision
description: Provisioning kandidat yang offer-nya diterima menjadi karyawan resmi di ERP (emp_employee + akun login), tahap terakhir alur rekrutmen.
---

# Provisioning ke ERP — Grid + Modal (L9)

**File**: `resources/views/pages/recruitment/hire-provision.blade.php`
**Route**: `hrm.recruitment.hire-provision` — `GET /hrm/recruitment/hire-provision` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr` (proses lintas-DB ke `tm2mst`/`tm2sys`, lihat bagian Tabel & Model)
**Modul**: Recruitment (Internal) — L9, lihat komentar kode: `docs/recruitment_tier4.md` SECTION 14 & Tier2 SECTION 7 (hire trigger)

## Tujuan
Titik akhir alur rekrutmen internal: mengubah kandidat yang offer-nya `ACCEPTED` menjadi karyawan resmi (`emp_employee` + kontrak + akun login) di ERP. Aktor: HR staff. Reuse `App\Services\Migration\EmployeeLoginProvisionService` (persis alur registrasi karyawan baru di `master/employee.blade.php`). `position_id` di `emp_employee` **tidak** dipetakan otomatis dari requisition (requisition hanya simpan `position_title` teks bebas) — HR wajib konfirmasi `org_position` riil di modal ("engine suggests, officer confirms", CLAUDE.md §4), bukan ditebak sistem.

## Field & Kolom
**Grid "Siap Diprovision"**: kolom Kandidat, Lowongan, Aksi (tombol "Provision ke ERP") — sumber: `AppApplication` dengan `offers` berstatus `ACCEPTED` dan `provisioned_employee_id` masih null.

**Grid "Riwayat Provisioning"**: kolom Kandidat, Diprovision Pada (`provisioned_at`, format `d M Y H:i`) — 20 baris terakhir, `whereNotNull('provisioned_employee_id')`.

**Modal konfirmasi**:
- `position_id` (select, wajib) — sumber `positionsFor()`: `OrgPosition::where('activate', true)`, diurutkan department requisition lebih dulu (`orderByRaw('department_id = ? DESC')`) lalu nama posisi

**Modal hasil** (setelah provision): tampilkan `login` & `password` sekali (dari `EmployeeLoginProvisionService`), HR wajib infokan manual ke karyawan baru.

## Aksi / Method
- `readyToProvision()` [Computed] — `AppApplication::whereNull('provisioned_employee_id')->whereHas('offers', status=ACCEPTED)`
- `provisionedRecently()` [Computed] — 20 terakhir yang sudah diprovision
- `positionsFor()` [Computed] — daftar posisi aktif utk dropdown, prioritas department requisition
- `openProvision(int $applicationId)` — buka modal, reset `position_id`/`credentials`
- `provision()` — validasi `position_id` wajib; idempotency guard (`if ($app->provisioned_employee_id) return;`); generate `employee_code` (`{company_code}-{5 digit seq}`, seq = max existing + 1); create `EmpEmployee` (data dari `CndCandidate` — nama, tanggal lahir, KTP, HP, email, alamat) + `EmpContract` (dari `AppOffer` — jenis kontrak, tanggal mulai, gaji); salin semua `CndDocument` kandidat ke `EmpDocument` (`uploaded_by_role='RECRUITMENT_SYNC'`, arsip rekrutmen tetap utuh, bukan dipindah); panggil `EmployeeLoginProvisionService::provisionForEmployee()` utk akun login; update `AppApplication.status='HIRED'` + `provisioned_employee_id`/`provisioned_at`

## Tabel & Model yang Disentuh
- `AppApplication` (`app_application`, `tm2hrr`) — sumber & target update status
- `AppOffer` (`app_offer`, `tm2hrr`) — sumber data gaji/kontrak dari offer `ACCEPTED`
- `CndCandidate`, `CndDocument` (`cnd_candidate`/`cnd_document`, `tm2hrr`) — sumber data pribadi & dokumen kandidat
- `EmpEmployee`, `EmpContract`, `EmpDocument` (`App\Models\Master`, koneksi `tm2mst`) — **lintas-DB**, target penulisan karyawan baru
- `OrgCompany`, `OrgPosition` (`App\Models\Master`, `tm2mst`) — **lintas-DB**, sumber `company_code` & pilihan posisi
- `App\Services\Migration\EmployeeLoginProvisionService` — provisioning akun login (kemungkinan menyentuh `tm2sys.sec_user`, tidak dibaca detail di sesi ini — TBD verifikasi isi service kalau perlu detail lebih)

## Alur / Business Logic Penting
Prasyarat: `AppOffer.status = ACCEPTED` (ditetapkan dari layar Offer Entry). Provisioning bersifat idempotent — guard eksplisit mencegah double-provision kalau tombol diklik dua kali. Tindakan **tidak bisa dibatalkan** (`wire:confirm` di tombol). Setelah sukses, `AppApplication.status` berubah jadi `HIRED` — status akhir siklus hidup lamaran.

## Catatan
- `position_id` murni pilihan manual HR, tidak ada mapping otomatis `position_title` (teks bebas di requisition) → `org_position` (master terstruktur) — gap yang disebutkan eksplisit di komentar kode (baris 19-22).
- `EmployeeLoginProvisionService` tidak dibaca isinya secara mendalam di sesi dokumentasi ini — TBD kalau butuh detail cara generate password/login.
