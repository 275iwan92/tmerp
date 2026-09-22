---
name: screen_self_service_index
description: Portal Karyawan self-service — 9 tab (profil read-only+koreksi, keluarga, dokumen, cuti/sakit/izin, izin keluar, tukar shift, jadwal, kartu pengenal, security akun)
---

# Portal Karyawan (Self-Service)

**File**: `resources/views/pages/self-service/index.blade.php`
**Route**: `/self-service` -> name `self-service.index` (grup `auth`+`force.password`, tanpa gate modul — personal, guard "linked employee" ditangani di dalam komponen)
**Koneksi DB utama**: mst (tm2mst, via model-model `App\Models\Master\Emp*`) untuk data karyawan; sys (tm2sys, via `Auth::user()`/`SecUser`) untuk tab Security
**Modul**: System & Setup

## Tujuan
Portal untuk karyawan melihat data pribadinya sendiri (read-only, HANYA bisa diubah lewat pengajuan+approval HR — beda dari Master Employee sisi HR yang full-editable) dan mengajukan berbagai permohonan: koreksi data, perubahan keluarga, upload dokumen/riwayat pendidikan, cuti/sakit/izin, izin keluar area kerja, tukar shift, serta lihat jadwal kerja terpublikasi & kartu ID digital. Tab "Security" khusus mengelola akun login (`sec_user`) sendiri — BEDA dari data HR lain di tab-tab sebelumnya karena diedit LANGSUNG tanpa alur pengajuan+approval (gabungan dari modal "Edit profil"+"Ubah password" yang dulu terpisah di dropdown topbar). Berdasarkan `mockup_personal_data_self_service.html`, mengimplementasikan nyata engine cuti dari SOP 002/RP/TM/VII/2026 (bukan sekadar tampilan).

## Field & Kolom
Guard `mount()`: `employeeId = Auth::user()->employee_id`; jika null, tampil pesan "Akun belum tertaut ke data pegawai" (seluruh portal terkunci).

**Tab `profil` (Data Saya)** — read-only + tombol "Ajukan Koreksi" per field (Nama Lengkap, Tanggal Lahir, NIK KTP, Agama, Telepon/WA, Alamat KTP, Alamat Domisili, No. BPJS Kesehatan, No. BPJS Ketenagakerjaan); Posisi read-only murni (badge "Hanya HR", tanpa tombol koreksi). Tabel riwayat pengajuan koreksi. Box merah "Pengunduran Diri (Resign)".

**Tab `keluarga`** — tabel anggota keluarga (`emp->families`), tombol "+ Ajukan Kelahiran"/"Tandai Meninggal Dunia"/"Ajukan Perubahan" (status pernikahan), koreksi Rekening Bank Payroll & Kontak Darurat.

**Tab `dokumen`** — 6 jenis dokumen identitas (KTP/NPWP/SIM/KK/PASSPORT/SKCK) upload per jenis (terkunci jika `uploaded_by_role='HR'`), tabel riwayat pendidikan/pelatihan dengan tombol "+ Tambah Riwayat".

**Tab `cuti`** — kartu saldo cuti tahun berjalan (Hak/Terpakai/Sisa), form pengajuan `leave_type` (CUTI/SAKIT/IZIN/CUTI_KHUSUS) dengan field kondisional per jenis (lihat Alur di bawah), tabel riwayat pengajuan.

**Tab `izinkeluar`** — form (`op_permit_date`, `op_out_time`, `op_return_time`, `op_reason`), tabel riwayat.

**Tab `tukarshift`** — form pilih `swap_requester_roster_id` (jadwal sendiri), `swap_target_employee_id` (rekan 1 divisi, aktif), `swap_target_roster_id` (jadwal rekan), `swap_reason`; tabel riwayat dengan status approval terpisah (`partner_status`, `ppic_status`).

**Tab `jadwal`** — daftar jadwal kerja terpublikasi (hanya batch `status=CONFIRMED`), read-only.

**Tab `badge`** — preview kartu ID digital, tombol upload foto ID baru (`badge_photo`, image, review HRD).

**Tab `security`** — form akun: `acc_name`, `acc_email`, `acc_phone` (edit langsung); form ganti password: `acc_old_password`, `acc_new_password`+konfirmasi.

## Aksi / Method
- `mount()` — resolve `employeeId`, validasi `tab`, load data akun awal.
- **Security**: `saveAccountProfile()`, `changeAccountPassword()` (cek `Hash::check` password lama dulu).
- `employee()` / `currentYearBalance()` (computed, `firstOrCreate` saldo cuti default 12 hari/tahun) / `storeUpload()` (helper simpan file ke `hrm/{employeeId}/{folder}`).
- **Tab profil**: `openCorrection()`, `saveCorrection()` (→ `EmpCorrectionRequest`, `requires_hr_approval=true` selalu), `openResign()`, `saveResign()` (→ `EmpResignRequest`), `myCorrections()`.
- **Tab keluarga**: `openFamilyReq()`, `saveFamilyReq()` (→ `EmpFamilyRequest`, guard maks 20 anak per karyawan untuk kelahiran ANAK), `myFamilyRequests()`.
- **Tab dokumen**: `openDocModal()`, `saveDoc()` (blokir ganti jika `uploaded_by_role=HR`), `openEduModal()`, `saveEdu()`.
- **Tab cuti** (engine, lihat Alur): `updatedLeaveType()`, `izinLocked()` (computed), `izinCountForMonth()`, `recordAutoAlpha()`, `sakitLaporTerlambat()` (computed), `submitLeave()`, `myLeaveRequests()`.
- **Tab izinkeluar**: `submitOutPermit()`, `myOutPermits()`.
- **Tab tukarshift**: `myUpcomingRoster()`, `swapColleagues()`, `targetRosterOptions()`, `submitSwap()`, `mySwapRequests()`.
- **Tab jadwal**: `mySchedule()`.
- **Tab badge**: `openBadgeModal()`, `saveBadgePhoto()` (baca dimensi via `getimagesize`).

## Tabel & Model yang Disentuh
Semua model di namespace `App\Models\Master` (koneksi mst / tm2mst):
`EmpEmployee`, `EmpFamily`, `EmpFamilyRequest`, `EmpCorrectionRequest`, `EmpResignRequest`, `EmpDocument`, `EmpEducation`, `EmpDisciplinary`, `EmpLeaveBalance`, `EmpLeaveRequest`, `EmpOutPermitRequest`, `EmpShiftRoster`, `EmpShiftSwapRequest`, `EmpBadgePhotoRequest`.
Tab Security memakai `Auth::user()` (model `SecUser`, tm2sys) — update langsung tanpa tabel pengajuan.
Tidak ada query lintas-DB langsung (relasi lintas modul, jika ada, ditangani terpisah per koneksi sesuai aturan CLAUDE.md).

## Alur / Business Logic Penting
- **Prinsip inti**: semua data HR (bukan akun login) READ-ONLY di sini — perubahan SELALU lewat tabel pengajuan (`*Request`) + approval HR terpisah (panel approval HR TIDAK dibangun di layar ini, di luar scope).
- **Engine Cuti/Sakit/Izin** (implementasi nyata SOP 002/RP/TM/VII/2026, bukan sekadar UI):
  - **CUTI**: wajib H+7 dari hari pengajuan, maksimal 3 hari kerja (Minggu tidak dihitung), wajib upload Surat Cuti, mengurangi saldo cuti (`deducts_leave_balance=true`), dibayar.
  - **SAKIT**: dibayar, tidak mengurangi saldo cuti; via JKN cukup screenshot riwayat, non-JKN wajib Surat Dokter DAN kwitansi/resep (keduanya); field `sakit_lapor_jam` dicek terhadap batas 07:30 WIB (`sakitLaporTerlambat`, hanya warning visual, tidak memblokir submit).
  - **IZIN**: unpaid, hanya bisa diajukan bila saldo cuti tahun ini HABIS (`izinLocked` — cuti>0 mengunci opsi Izin), maksimal 3 hari berturut, maksimal 3x pengajuan per bulan (dihitung dari bulan tanggal pengajuan `date_from`, bukan bulan kalender berjalan) — percobaan ke-4+ di bulan sama diblokir DAN otomatis dicatat sebagai `EmpDisciplinary` type ALPHA (dedup per employee+tanggal supaya klik berulang tidak dobel-catat); izin terjadwal wajib H-7, izin mendadak (musibah/instansi resmi/duka) wajib lampiran + kategori tambahan.
  - **CUTI_KHUSUS**: dibayar, tidak mengurangi saldo cuti, wajib lampiran bukti (nikah/akta/dst), punya `khusus_event_type` (Menikah/Menikahkan-Khitan Anak/Istri Melahirkan/Keluarga Meninggal/Ibadah).
- **Tukar Shift**: hanya bisa pilih jadwal dari batch `CONFIRMED`, rekan pengganti dibatasi 1 divisi (`division_id` sama) & aktif; `submitted_on_time` dihitung otomatis (H-1 dari `roster_date`); approval 2 tahap terpisah (`partner_status` rekan, `ppic_status` PPIC).
- **Koreksi data**: field sensitif (NIK KTP, Tanggal Lahir) tetap lewat jalur sama (`requires_hr_approval=true` untuk SEMUA koreksi, bukan hanya sensitif) — tapi ditandai `$sensitive` di kode untuk kemungkinan pembedaan masa depan.
- **Keluarga**: anggota keluarga yang meninggal TIDAK dihapus dari histori (demi akurasi rekaman tunjangan/klaim masa lalu) — hanya ditandai `is_deceased`.
- **Dokumen**: dokumen yang diunggah HR (`uploaded_by_role=HR`) terkunci dari karyawan — hanya bisa upload/ganti dokumen yang berstatus SELF atau belum ada.
- Kalender pemilihan tanggal cuti dari mockup asli (grid custom klik-multi) DISEDERHANAKAN jadi date-range input + validasi server penuh — mengganti widget visual saja, bukan aturan bisnisnya.

## Catatan
- Validasi hari libur nasional/cuti bersama BELUM ada tabel referensi — perhitungan hari kerja cuti hanya exclude hari Minggu, tidak exclude hari libur nasional (ditandai "DELTA" di komentar kode `submitLeave()`).
- Panel approval HR untuk semua pengajuan di portal ini (koreksi, keluarga, cuti, izin keluar, tukar shift, resign, badge photo) secara eksplisit di luar scope layar ini — belum didokumentasikan/dibangun di sini.
