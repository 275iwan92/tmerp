# TIER 2 — PRESENSI & PAYROLL · SYSTEM ARCHITECTURE, LEAN PRD & BLUEPRINT
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS & TARGET AUDIENS

| | |
|---|---|
| **Dokumen** | Tier 2 — System Architecture, Mobile GPS Geofencing PRD & Blueprint |
| **Versi** | 4.0 (Expanded with Mobile Geofencing & Approval Workflow Engine) |
| **Target Pembaca** | System Analysts, Software Architects, Lead Developers, dan Mobile Frontend Engineers |
| **Prinsip Utama** | **Multi-Channel Attendance Engine**. Mendukung penggabungan data Biometric Fingerprint Hardware dan Mobile GPS Clock-In dengan validasi Haversine Formula Geofencing. |

---

## SECTION 2 — LEAN PRD & CORE USER FLOWS

### 2.1 Mobile GPS Geofencing & Location Approval
1. Karyawan membuka aplikasi mobile / PWA di HP.
2. Aplikasi mengambil koordinat GPS device (`latitude`, `longitude`, `accuracy`).
3. System Engine mengkalkulasi jarak menggunakan **Formula Haversine**:
   $$d = 2r rcsin \left( \sqrt{\sin^2\left(rac{\Delta \phi}{2}ight) + \cos(\phi_1)\cos(\phi_2)\sin^2\left(rac{\Delta \lambda}{2}ight)} ight)$$
4. Jika $d \le 	ext{radius\_max}$ (misal 50 meter dari Kantor Panji Suroso Malang):
   - Status: `VERIFIED_IN_RADIUS` $ightarrow$ Presensi langsung tercatat di `att_daily_status`.
5. Jika $d > 	ext{radius\_max}$ (Dinas Luar / Remote):
   - Status: `PENDING_HR_LOCATION_APPROVAL` $ightarrow$ Masuk ke Queue **HR Location Approval Panel**.

### 2.2 ESS Leave & Permit Request Workflow
1. Karyawan memilih jenis pengajuan (Sakit / Izin / Cuti / Lembur) dari menu Profile.
2. Mengisi tanggal, alasan, dan melampirkan foto/PDF (Surat Dokter + Kwitansi Obat untuk Sakit).
3. Record disimpan ke `emp_leave_request` dengan status `SUBMITTED`.
4. Notifikasi terkirim ke Atasan & HR.
5. HR melakukan `APPROVED` / `REJECTED`:
   - Jika `APPROVED`: System Engine otomatis mengupdate status pada `att_daily_status` untuk rentang tanggal terkait dan memotong kuota cuti tahunan jika berupa Cuti.

### 2.3 Laporan Kedisiplinan Harian (Daily Discipline Report)
- Engine mengolah data presensi jam $08:00$ WIB setiap hari.
- Mengelompokkan karyawan ke dalam 3 kategori utama:
  1. **Hadir Tepat Waktu**: $T \le 	ext{Shift Start}$.
  2. **Terlambat**: $T > 	ext{Shift Start}$ (Diurutkan berdasarkan menit keterlambatan terbesar).
  3. **Tidak Masuk Kerja**: Karyawan tanpa log finger/GPS hingga batas cut-off (Sub-kategori: Alpha, Sakit Approved, Izin Approved, Cuti Approved).

---

## SECTION 3 — TECH STACK & SYSTEM BLUEPRINT

- **Backend**: Laravel v13.x (PHP 8.3+)
- **Mobile/Web Frontend**: Livewire v4 + Alpine.js (PWA Geolocation API Integration)
- **CSS System**: Tailwind CSS v3.4+ / v4
- **Database Engine**: MySQL 8.0+ (Spatial/Haversine Functions)

```
+-----------------------------------------------------------------------------------+
|                            MOBILE & WEB APPLICATION LAYER                         |
+-----------------------------------------------------------------------------------+
|  [ Mobile GPS Clock-In ]    [ ESS Leave Request Form ]    [ Admin HR Dashboard ]  |
|            │                             │                          │             |
|            ▼                             ▼                          ▼             |
|  [ Geofence Evaluator ]     [ HR Approval Workflow ]      [ Daily Discipline Rpt ]|
|            │                             │                          │             |
|            +-----------------------------+--------------------------+             |
|                                          │                                        |
|                                          ▼                                        |
|                       [ ATTENDANCE & PAYROLL CORE ENGINE ]                        |
+-----------------------------------------------------------------------------------+
```
