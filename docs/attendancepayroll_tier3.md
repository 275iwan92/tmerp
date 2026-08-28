# TIER 3 — PRESENSI & PAYROLL · DATABASE & DATA PROCESS FLOW
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## 1. PENEMPATAN DATABASE (selaras TIER_0)

| Tabel | DB | Alasan |
|---|---|---|
| `att_*` (presensi) | `tm2hrm` | Menempel langsung ke `emp_employee`/`emp_shift` yang sudah ada di HRM, FK lokal tanpa VIEW |
| `pyr_*` (payroll) | `tm2pyr` | Domain terpisah (nominal gaji = data sensitif, akses dibatasi role berbeda dari HR Admin biasa); baca `att_daily_status` via **VIEW read-only** `tm2pyr.v_att_daily_status` → `tm2hrm.att_daily_status` |

> Konsisten dengan prinsip TIER_0 §3: payroll TIDAK boleh menulis balik ke `tm2hrm`, hanya baca via VIEW.

---

## 2. ERD LOGICAL

```
tm2hrm.emp_employee ──┬──► tm2hrm.emp_shift (existing, per-hari)
                       │
                       ├──► tm2hrm.att_finger_import (header upload)
                       │         └──► tm2hrm.att_finger_raw (detail per scan)
                       │
                       ├──► tm2hrm.emp_leave_request (Izin/IS/Cuti/CL/Sakit)
                       ├──► tm2hrm.emp_overtime_request (Lembur)
                       ├──► tm2hrm.emp_disciplinary_status (SP1/SP2/SP3/SKR/DRM, periode aktif)
                       │
                       └──► tm2hrm.att_daily_status (HASIL, 1 baris per pegawai per tanggal)
                                   │  dibaca via VIEW
                                   ▼
                         tm2pyr.v_att_daily_status (VIEW read-only)
                                   │
                         tm2pyr.pyr_status_rule (rule matrix, data-driven — Tier 2 §4)
                                   │
                                   ▼
                         tm2pyr.pyr_payroll_run (header periode)
                                   └──► tm2pyr.pyr_payslip (1 baris per pegawai per periode)
                                             └──► tm2pyr.pyr_payslip_detail (breakdown komponen)
```

---

## 3. DDL — `tm2hrm`

```sql
-- ========================================================
-- ATT_FINGER_IMPORT — header upload file mesin fingerprint
-- ========================================================
CREATE TABLE att_finger_import (
    id_import BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_date DATE NOT NULL COMMENT 'tanggal batch diimport',
    file_name VARCHAR(255) NOT NULL,
    imported_by BIGINT UNSIGNED NOT NULL COMMENT 'FK user tm2sys',
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('PENDING','PROCESSED','FAILED') NOT NULL DEFAULT 'PENDING',
    error_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Header batch import data fingerprint';

-- ========================================================
-- ATT_FINGER_RAW — detail per scan, sebelum diolah jadi status
-- ========================================================
CREATE TABLE att_finger_raw (
    id_finger_raw BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_import BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL COMMENT 'FK emp_employee',
    scan_datetime DATETIME NOT NULL,
    scan_type ENUM('IN','OUT','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN'
        COMMENT 'UNKNOWN jika mesin tidak bedakan in/out, ditentukan job berdasar urutan',
    raw_device_code VARCHAR(50) NULL COMMENT 'ID mesin asal, untuk audit multi-lokasi',
    CONSTRAINT fk_fingerraw_import FOREIGN KEY (id_import) REFERENCES att_finger_import(id_import),
    CONSTRAINT fk_fingerraw_employee FOREIGN KEY (employee_id) REFERENCES emp_employee(id_employee),
    INDEX idx_fingerraw_emp_date (employee_id, scan_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Data mentah tiap scan fingerprint, sebelum diproses jadi status harian';

-- ========================================================
-- EMP_LEAVE_REQUEST — Izin/IS/Cuti/CL/Sakit
-- ========================================================
CREATE TABLE emp_leave_request (
    id_leave BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type ENUM('I','IS','C','CL','S') NOT NULL,
    date_start DATE NOT NULL,
    date_end DATE NOT NULL,
    cl_scheme ENUM('A','B') NULL COMMENT 'Hanya diisi jika leave_type=CL; A=2bulan penuh, B=3bulan(2 dibayar+1 tidak)',
    submitted_at DATETIME NOT NULL,
    attachment_path VARCHAR(255) NULL COMMENT 'wajib untuk S (surat dokter) dan CL',
    status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    reject_reason VARCHAR(255) NULL,
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES emp_employee(id_employee),
    INDEX idx_leave_emp_range (employee_id, date_start, date_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pengajuan izin/cuti/sakit — lead-time & approval berbeda per jenis (Tier 2 SECTION 1)';

-- ========================================================
-- EMP_OVERTIME_REQUEST — Lembur
-- ========================================================
CREATE TABLE emp_overtime_request (
    id_overtime BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    overtime_date DATE NOT NULL,
    ot_type ENUM('AWAL','AKHIR','KEDUANYA') NOT NULL,
    duration_minutes INT UNSIGNED NOT NULL COMMENT 'Total durasi lembur hari itu, dari rekap — bukan dihitung ulang dari finger',
    status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    CONSTRAINT fk_ot_employee FOREIGN KEY (employee_id) REFERENCES emp_employee(id_employee),
    UNIQUE KEY uq_ot_emp_date (employee_id, overtime_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pengajuan lembur, sumber kebenaran durasi (Tier 2 SECTION 4.4)';

-- ========================================================
-- EMP_DISCIPLINARY_STATUS — SP1/SP2/SP3/SKR/DRM, periode aktif
-- ========================================================
CREATE TABLE emp_disciplinary_status (
    id_disc BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    disc_type ENUM('SP1','SP2','SP3','SKR','DRM') NOT NULL,
    effective_start DATE NOT NULL,
    effective_end DATE NULL COMMENT 'NULL = masih berjalan (relevan utk DRM/SKR tanpa batas pasti)',
    working_days_target INT UNSIGNED NULL COMMENT '3 untuk SP1, 7 untuk SP2 — hari KERJA berturut, bukan kalender',
    working_days_counted INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'counter berjalan, diupdate job harian',
    issued_by BIGINT UNSIGNED NOT NULL,
    note TEXT NULL,
    CONSTRAINT fk_disc_employee FOREIGN KEY (employee_id) REFERENCES emp_employee(id_employee),
    INDEX idx_disc_emp_active (employee_id, effective_start, effective_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Status administratif periode: SP/Skorsing/Dirumahkan (Tier 2 SECTION 6)';

-- ========================================================
-- ATT_DAILY_STATUS — HASIL kalkulasi harian (satu sumber kebenaran untuk payroll)
-- ========================================================
CREATE TABLE att_daily_status (
    id_daily BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status_code VARCHAR(10) NOT NULL COMMENT 'H,T-5,T+5,T+15,PA,PAT-5,PAT+5,KJK,I,IS,C,CL,S,A,F,FF',
    finger_in DATETIME NULL,
    finger_out DATETIME NULL,
    shift_start TIME NULL COMMENT 'snapshot dari emp_shift pada tanggal tsb, untuk audit histori',
    shift_end TIME NULL,
    minutes_late INT UNSIGNED NOT NULL DEFAULT 0,
    minutes_early_leave INT UNSIGNED NOT NULL DEFAULT 0,
    minutes_out_during_shift INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'akumulasi KJK, lihat Tier 2 SECTION 3',
    overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    is_uang_roti TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'lembur>2jam & role eligible, Tier 2 SECTION 4.4',
    late_violation_seq_in_period INT UNSIGNED NULL COMMENT 'urutan pelanggaran T+5 dlm periode berjalan, utk cek >3x (Tier 2 SECTION 4.2)',
    source ENUM('AUTO','MANUAL_OVERRIDE') NOT NULL DEFAULT 'AUTO',
    override_by BIGINT UNSIGNED NULL,
    override_reason VARCHAR(255) NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_daily_employee FOREIGN KEY (employee_id) REFERENCES emp_employee(id_employee),
    UNIQUE KEY uq_daily_emp_date (employee_id, attendance_date),
    INDEX idx_daily_status (status_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Hasil kalkulasi presensi harian — satu sumber kebenaran, payroll tinggal baca (Tier 2 SECTION 7)';
```

---

## 4. DDL — `tm2pyr`

```sql
-- ========================================================
-- VIEW read-only ke tm2hrm (via aplikasi Laravel, cross-DB view
-- hanya valid jika 1 server MySQL yang sama; jika beda server,
-- baca via API/Model lintas-koneksi, bukan SQL VIEW native)
-- ========================================================
-- CREATE VIEW v_att_daily_status AS SELECT * FROM tm2hrm.att_daily_status;
-- (dieksekusi hanya jika tm2hrm & tm2pyr satu server MySQL — sesuai TIER_0 fase awal)

-- ========================================================
-- PYR_STATUS_RULE — rule matrix data-driven (Tier 2 SECTION 4)
-- ========================================================
CREATE TABLE pyr_status_rule (
    id_rule BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_code VARCHAR(10) NOT NULL,
    component_code ENUM('POKOK','TJ_KEAHLIAN','TJ_LOYALITAS','TJ_PRESTASI','BONUS_PAGI','BONUS_HADIR') NOT NULL,
    effect ENUM('FULL','ZERO','PRORATA') NOT NULL COMMENT 'FULL=komponen penuh, ZERO=hilang, PRORATA=hitung menit_hilang',
    condition_note VARCHAR(255) NULL COMMENT 'mis. "hanya jika pelanggaran ke-4 dst dalam periode"',
    effective_from DATE NOT NULL,
    effective_to DATE NULL COMMENT 'NULL = masih berlaku; histori perubahan kebijakan disimpan, bukan di-overwrite',
    UNIQUE KEY uq_rule_status_component_effdate (status_code, component_code, effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rule matrix status presensi -> komponen gaji, data-driven & versioned by effective_from/to';

-- ========================================================
-- PYR_DENDA_RULE — nominal denda tetap per status (terpisah dari komponen di atas)
-- ========================================================
CREATE TABLE pyr_denda_rule (
    id_denda BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_code VARCHAR(10) NOT NULL,
    denda_amount DECIMAL(12,2) NOT NULL,
    per_event TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=per kejadian (mis. F/FF), 0=flat per periode',
    condition_note VARCHAR(255) NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Nominal denda tetap (Rp) per status, terpisah dari rule komponen — Tier 2 SECTION 4';

-- ========================================================
-- PYR_PAYROLL_RUN — header per periode
-- ========================================================
CREATE TABLE pyr_payroll_run (
    id_run BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    run_status ENUM('DRAFT','CALCULATED','REVIEWED','FINALIZED') NOT NULL DEFAULT 'DRAFT',
    triggered_by BIGINT UNSIGNED NOT NULL,
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finalized_by BIGINT UNSIGNED NULL,
    finalized_at DATETIME NULL,
    UNIQUE KEY uq_run_period (period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Header payroll run per periode bulanan';

-- ========================================================
-- PYR_PAYSLIP — hasil per pegawai per periode
-- ========================================================
CREATE TABLE pyr_payslip (
    id_payslip BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_run BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    total_pokok DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_tj_keahlian DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_tj_loyalitas DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_tj_prestasi DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_bonus_pagi DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_bonus_hadir DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_uang_roti DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_denda DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_gaji_bersih DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_payslip_run FOREIGN KEY (id_run) REFERENCES pyr_payroll_run(id_run),
    UNIQUE KEY uq_payslip_run_emp (id_run, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Hasil payroll per pegawai per periode';

-- ========================================================
-- PYR_PAYSLIP_DETAIL — breakdown harian, untuk audit/transparansi
-- ========================================================
CREATE TABLE pyr_payslip_detail (
    id_detail BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_payslip BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status_code VARCHAR(10) NOT NULL,
    component_code VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL COMMENT 'nilai +/- terhadap komponen, bisa negatif utk potongan',
    note VARCHAR(255) NULL,
    CONSTRAINT fk_detail_payslip FOREIGN KEY (id_payslip) REFERENCES pyr_payslip(id_payslip),
    INDEX idx_detail_payslip_date (id_payslip, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Breakdown harian tiap payslip — jejak audit kenapa nominal sebesar itu';
```

---

## 5. DATA PROCESS FLOW (urutan job)

### 5.1 Job Import & Kalkulasi Harian (trigger: setelah upload `att_finger_import`)
1. Parse file → insert ke `att_finger_raw`.
2. Untuk tiap `employee_id` + tanggal yang muncul di raw: ambil `emp_shift` aktif pegawai pada tanggal tsb.
3. Tentukan `finger_in`/`finger_out` dari scan pertama & terakhir hari itu (kecuali `scan_type` sudah eksplisit IN/OUT dari mesin).
4. Cek `emp_leave_request`/`emp_overtime_request` approved untuk tanggal tsb → override status jika ada (lihat Tier 2 §7 langkah 2).
5. Jika tidak ada leave/overtime → hitung status time-variant sesuai Tier 2 §4.2, termasuk baca `late_violation_seq_in_period` (COUNT status T+5 sejak awal periode payroll berjalan, milik pegawai yang sama) untuk tentukan kena denda atau tidak.
6. Insert/update `att_daily_status` (idempotent by `uq_daily_emp_date` — re-import hari yang sama harus overwrite, bukan duplikat).
7. Jika tidak ada data finger sama sekali & tidak ada leave/disciplinary aktif → set status `A` otomatis (butuh review HR sebelum payroll finalize).

### 5.2 Job Payroll Run (trigger: manual oleh Payroll Officer, per periode)
1. Create/update `pyr_payroll_run` (status DRAFT).
2. Untuk tiap pegawai aktif: ambil semua `att_daily_status` dalam rentang periode via `v_att_daily_status`.
3. Untuk tiap baris harian: lookup `pyr_status_rule` (WHERE status_code = ... AND effective_from <= tanggal AND (effective_to IS NULL OR effective_to >= tanggal)) → tentukan efek per komponen (FULL/ZERO/PRORATA).
4. Terapkan `pyr_denda_rule` untuk denda tetap.
5. Terapkan efek `emp_disciplinary_status` aktif (SP1/SP2 → 50% × N hari kerja pertama sejak `effective_start`; increment `working_days_counted` setelah terhitung).
6. Insert breakdown ke `pyr_payslip_detail`, SUM ke `pyr_payslip`.
7. Set `run_status = CALCULATED`. Payroll Officer review manual → `REVIEWED` → `FINALIZED` (lock, tidak bisa dihitung ulang tanpa buat run baru).

---

## 6. SEED WAJIB

- `pyr_status_rule` — seed semua kombinasi status × komponen dari Tier 2 §4.1–4.2 (SETELAH Open Item #1/#2 dikonfirmasi Telma/Jo — jangan seed sebelum itu, supaya tidak salah nominal dari awal).
- `pyr_denda_rule` — seed: I=25000, A=100000, T+5(>3x)=25000, T+15=25000, PAT+5=25000, F/FF=10000/event.

## 7. CHECKLIST DBA (urutan deploy)

1. Pastikan `emp_employee`, `emp_shift` (HRM) sudah live — prasyarat mutlak.
2. Deploy tabel `tm2hrm.att_*` dan `tm2hrm.emp_leave_request`/`emp_overtime_request`/`emp_disciplinary_status`.
3. Deploy tabel `tm2pyr.pyr_*`.
4. Seed `pyr_status_rule` & `pyr_denda_rule` — **hanya setelah Open Items Tier 1 §4 + Tier 2 dikonfirmasi**.
5. Setup VIEW/koneksi lintas-DB `tm2pyr` → `tm2hrm` sesuai keputusan single-server vs multi-server (TIER_0 §5).
