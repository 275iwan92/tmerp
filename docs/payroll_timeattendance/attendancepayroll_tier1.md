# TIER 1 — PRESENSI & PAYROLL · SYSTEM FLOW & PROCESS MATRIX
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN & KONTEKS BISNIS

| | |
|---|---|
| **Dokumen** | Tier 1 — Presensi, Payroll, ESS Mobile GPS & Approval Workflows |
| **Versi** | 4.0 (Expanded with Mobile GPS Geofencing & ESS Approval Workflow) |
| **Sumber Acuan** | `PRESENSI & PAYROLL - RULES & POLICY.pptx`, `SOP PERIZINAN KARYAWAN TELASIH MAKMUR.pdf`, `JD & KPI - STAFF FINANCE.pdf`, `DAFTAR KARYAWAN.xlsx`, Form Perizinan Fisik (`NO : 034/FDT.TDP/VIII/2026`) |
| **Target Pembaca** | Pemilik Bisnis, HR Admin, Operations Manager, Kepala Produksi, Lead Developers, dan Karyawan |
| **Aktor Utama** | Karyawan (Mobile App/PWA), Atasan Langsung (SPV/Kepala Divisi), HR Admin, Payroll Officer (Staff Finance) |
| **Goal Utama** | Otomatisasi end-to-end presensi multi-channel (Biometric + Mobile GPS Geofencing), Employee Self-Service (ESS), HR Approval Workflow, serta penggajian transparan dengan dukungan pembayaran Bank Transfer vs Cash. |

---

## SECTION 2 — AKTOR & PERAN (ACTORS & ROLES)

1. **Karyawan (Staff, SPV, Operator Produksi)**
   - Melakukan fingerprint di mesin fisik ATAU presensi via Smartphone (*Mobile GPS Clock-in/out*) dengan verifikasi koordinat lokasi & radius geofence.
   - Mengajukan perizinan mandiri (Sakit, Izin, Cuti, Lembur, Tukar Shift) langsung dari *User Profile Mobile App* lengkap dengan upload foto/dokumen pendukung.
   - Menerima notifikasi status approval dan rincian slip gaji (*e-Payslip*) secara langsung di aplikasi.

2. **Atasan Langsung (Kepala Divisi / SPV / Manager)**
   - Menerima notifikasi pengajuan perizinan dan presensi khusus (*out-of-radius*) dari bawahan.
   - Memeriksa dan memberikan persetujuan (*1st level approval*) atas pengajuan perizinan & lembur.

3. **HR Admin**
   - Mengelola Master Data Kepegawaian mencakup **Data Rekening Bank (Nama Bank, Nomor Rekening, Atas Nama, Status Rekening)** untuk pemisahan otomatis metode penggajian (Bank Transfer vs Cash).
   - Melakukan persetujuan akhir (*2nd level approval*) atas pengajuan Sakit, Izin, Cuti, serta validasi Presensi GPS di luar radius lokasi standar.
   - Memantau **Laporan Kedisiplinan Harian Real-time** (Daftar Karyawan Terlambat, Pulang Awal, dan Tidak Masuk/Alpha/Izin).

4. **Payroll Officer / Staff Finance**
   - Mengelola data impor mesin finger dan sync presensi mobile GPS.
   - Menjalankan proses kalkulasi otomatis penggajian (*payroll batch run*).
   - Memproduksi file batch transfer bank (BCA/Mandiri) untuk pegawai berrekening, serta Roster Pembagian Uang Cash (+ Denomination Breakdown) untuk pegawai tanpa rekening.

---

## SECTION 3 — VALIDASI PROBLEM & SOLUSI FITUR BARU

### 3.1 Integrasi Mobile GPS Attendance & Geofencing
- **Problem**: Karyawan lapangan, sales, dinas luar, atau staff shift sering terkendala antrean panjang di mesin fingerprint fisik.
- **Solusi**: Fitur *Mobile Clock-in/out* berbasis GPS Geofencing. Aplikasi mencatat koordinat Lattitude/Longitude dan menghitung jarak radius ke titik lokasi resmi pabrik/kantor (misal: radius max 50 meter dari Panji Suroso Malang). Apabila di luar radius, presensi membutuhkan **HR GPS Approval**.

### 3.2 Master Employee dengan Rekening Bank & Metode Pembayaran
- **Problem**: Penggajian manual sering tercampur antara transfer bank dan amplop tunai tanpa rekapitulasi pecahan uang yang jelas.
- **Solusi**: Master Employee dilengkapi kolom atribut finansial: `bank_name`, `bank_account_number`, `bank_account_holder`, dan `payment_method` (`BANK_TRANSFER` vs `CASH`).

### 3.3 Laporan Kedisiplinan Harian Real-Time (Terlambat & Tidak Masuk)
- **Problem**: HR baru mengetahui karyawan terlambat atau alpha di akhir minggu saat rekap gaji.
- **Solusi**: Dashboard khusus *Daily Discipline Report* yang menampilkan secara *live* daftar karyawan yang terlambat (menit keterlambatan) dan tidak masuk (Alpha, Izin, Sakit, Cuti).

### 3.4 ESS (Employee Self-Service) & Digital Approval Workflow
- **Problem**: Pengajuan izin kertas fisik sering hilang atau terlambat sampai ke meja HRD.
- **Solusi**: Pengajuan digital dari Smartphone Karyawan $ightarrow$ Approval Atasan $ightarrow$ Approval HRD $ightarrow$ Otomatis memotong kuota cuti / mengubah status harian di grid presensi.

---

## SECTION 4 — DAFTAR FITUR LENGKAP (USER FEATURE WISHLIST)

| # | Kode Fitur | Nama Fitur | Ringkasan Fungsi untuk User |
|---|---|---|---|
| 1 | **F-01** | Import Data Fingerprint | Import file log mesin presensi ke database `att_finger_raw`. |
| 2 | **F-02** | Mobile GPS Geofencing | Presensi mandiri via smartphone dengan deteksi lokasi koordinat & radius. |
| 3 | **F-03** | Auto-Match Jam Shift | Pencocokan otomatis jam finger/GPS ke jadwal shift harian (Shift 1, 2, 3 Cross-Day). |
| 4 | **F-04** | Master Employee & Bank Data | Pengelolaan profil karyawan beserta rincian rekening bank & metode bayar. |
| 5 | **F-05** | ESS Pengajuan Sakit/Izin/Cuti | Form pengajuan perizinan mandiri dari profile karyawan + upload lampiran. |
| 6 | **F-06** | HR Approval Workflow | Panel persetujuan HRD untuk pengajuan perizinan & presensi luar radius. |
| 7 | **F-07** | Laporan Kedisiplinan Harian | Laporan real-time siapa yang terlambat, pulang awal, dan tidak masuk kerja. |
| 8 | **F-08** | Payroll Batch Engine | Kalkulasi otomatis gaji bersih berbasis rule matrix dan potongan prorata. |
| 9 | **F-09** | Bank Transfer Generator | Ekspor file batch kliring bank (BCA/Mandiri) untuk pegawai berrekening. |
| 10 | **F-10** | Cash Roster & Denomination | Roster gaji tunai + kalkulator pecahan lembar uang (Rp 100k, 50k, 20k, 10k). |
| 11 | **F-11** | Cetak e-Payslip Resmi | Cetak slip gaji A5/PDF lengkap dengan rincian pendapatan & denda. |
