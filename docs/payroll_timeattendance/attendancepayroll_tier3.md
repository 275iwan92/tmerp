# TIER 3 — PRESENSI & PAYROLL · DATABASE SCHEMA, NORMALIZATION & ACCESS OPTIMIZATION
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — UPDATED DDL MYSQL (EXPANDED FOR MOBILE GPS, BANK DATA & APPROVALS)

```sql
-- ========================================================
-- DATABASE: tm2hrm (Human Resource Management)
-- ========================================================

-- Master Karyawan Lengkap dengan Data Rekening Bank & Metode Bayar
CREATE TABLE tm2hrm.emp_master (
    employee_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    division_id INT UNSIGNED NOT NULL,
    position_name VARCHAR(50) NOT NULL,
    employment_status ENUM('PERMANENT','CONTRACT','PROBATION') NOT NULL DEFAULT 'CONTRACT',
    payment_method ENUM('BANK_TRANSFER','CASH') NOT NULL DEFAULT 'BANK_TRANSFER',
    bank_name VARCHAR(50) NULL COMMENT 'BCA, Mandiri, BRI, BNI',
    bank_account_number VARCHAR(30) NULL,
    bank_account_holder VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emp_pay_method (payment_method, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Presensi Mobile GPS & Geofencing Log
CREATE TABLE tm2hrm.att_mobile_gps_log (
    id_gps_log BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    clock_type ENUM('IN','OUT') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy_meters DECIMAL(6,2) NULL,
    distance_from_office_meters INT UNSIGNED NOT NULL,
    geofence_status ENUM('IN_RADIUS','OUT_OF_RADIUS') NOT NULL,
    hr_approval_status ENUM('AUTO_APPROVED','PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    hr_approved_by BIGINT UNSIGNED NULL,
    hr_approval_note VARCHAR(255) NULL,
    photo_selfie_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gps_emp FOREIGN KEY (employee_id) REFERENCES emp_master(employee_id) ON DELETE CASCADE,
    INDEX idx_gps_hr_approval (hr_approval_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Master Geofence Location (Titik Kantor/Pabrik Resmi)
CREATE TABLE tm2hrm.sys_geofence_location (
    id_location INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL COMMENT 'Pabrik Panji Suroso Malang',
    center_latitude DECIMAL(10,8) NOT NULL,
    center_longitude DECIMAL(11,8) NOT NULL,
    allowed_radius_meters INT UNSIGNED NOT NULL DEFAULT 50,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Updated Leave Request with Multi-Level HR Approval
CREATE TABLE tm2hrm.emp_leave_request (
    id_leave BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type ENUM('I','IS','C','CL','S') NOT NULL COMMENT 'Izin, Izin Sangat Penting, Cuti, Cuti Melahirkan, Sakit',
    date_start DATE NOT NULL,
    date_end DATE NOT NULL,
    reason TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL COMMENT 'Wajib untuk Sakit (Surat Dokter + Kwitansi)',
    spv_approval_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    spv_approved_by BIGINT UNSIGNED NULL,
    hr_approval_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    hr_approved_by BIGINT UNSIGNED NULL,
    hr_rejection_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_emp FOREIGN KEY (employee_id) REFERENCES emp_master(employee_id) ON DELETE CASCADE,
    INDEX idx_leave_hr_status (hr_approval_status, date_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## SECTION 2 — OPTIMIZED DAILY DISCIPLINE REPORT VIEW

```sql
CREATE VIEW tm2hrm.v_daily_discipline_report AS
SELECT 
    e.employee_id,
    e.nik,
    e.full_name,
    e.position_name,
    ads.attendance_date,
    ads.status_code,
    ads.finger_in,
    ads.finger_out,
    ads.minutes_late,
    CASE 
        WHEN ads.status_code IN ('T-5','T+5','T+15') THEN 'TERLAMBAT'
        WHEN ads.status_code IN ('A','I','S','C') THEN 'TIDAK MASUK'
        ELSE 'HADIR TEPAT WAKTU'
    END AS discipline_category
FROM tm2hrm.emp_master e
LEFT JOIN tm2hrm.att_daily_status ads ON e.employee_id = ads.employee_id;
```
