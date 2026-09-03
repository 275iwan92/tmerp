-- ============================================================================
-- SKEMA DATABASE TM2HRM PT. TELASIH MAKMUR (MALANG, JAWA TIMUR)
-- DIALECT: MySQL 8.0+ / MariaDB 10.6+
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `tm2hrm_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `tm2hrm_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `attachment_versions`;
DROP TABLE IF EXISTS `attachment_documents`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `mobile_gps_logs`;
DROP TABLE IF EXISTS `finger_raw_logs`;
DROP TABLE IF EXISTS `payslip_items`;
DROP TABLE IF EXISTS `payslips`;
DROP TABLE IF EXISTS `payroll_periods`;
DROP TABLE IF EXISTS `attendance_daily`;
DROP TABLE IF EXISTS `shifts`;
DROP TABLE IF EXISTS `bank_accounts`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `positions`;
DROP TABLE IF EXISTS `divisions`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. Tabel Master Divisi
-- ----------------------------------------------------------------------------
CREATE TABLE `divisions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabel Master Jabatan
-- ----------------------------------------------------------------------------
CREATE TABLE `positions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `division_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `level` ENUM('STAFF', 'SUPERVISOR', 'MANAGER', 'DIRECTOR') DEFAULT 'STAFF',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_positions_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabel Master Karyawan (Employees)
-- ----------------------------------------------------------------------------
CREATE TABLE `employees` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nik` VARCHAR(20) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `division` VARCHAR(100) NOT NULL,
  `position_name` VARCHAR(100) NOT NULL,
  `employment_type` ENUM('TETAP', 'KONTRAK_PKWT', 'HARIAN_LEPAS', 'BORONGAN') DEFAULT 'KONTRAK_PKWT',
  `join_date` DATE NOT NULL,
  `contract_end_date` DATE NULL,
  `ptkp_status` VARCHAR(10) NOT NULL DEFAULT 'TK/0',
  `basic_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `fixed_allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `daily_attendance_incentive` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `hourly_overtime_rate` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `bank_name` VARCHAR(50) NOT NULL DEFAULT 'BCA',
  `bank_account_no` VARCHAR(50) NOT NULL DEFAULT '',
  `bank_account_holder` VARCHAR(150) NOT NULL DEFAULT '',
  `bpjs_tk_no` VARCHAR(50) NULL,
  `bpjs_kes_no` VARCHAR(50) NULL,
  `npwp_no` VARCHAR(50) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_emp_nik` (`nik`),
  INDEX `idx_emp_division` (`division`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Tabel Master Jam Kerja / Shift
-- ----------------------------------------------------------------------------
CREATE TABLE `shifts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shift_code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `late_tolerance_minutes` INT NOT NULL DEFAULT 15,
  `is_cross_day` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Tabel Raw Log Mesin Fingerprint (Solution / ZKTeco)
-- ----------------------------------------------------------------------------
CREATE TABLE `finger_raw_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `machine_id` VARCHAR(50) NOT NULL DEFAULT 'MESIN_FINGER_PANJI_01',
  `device_pin` VARCHAR(30) NOT NULL,
  `nik` VARCHAR(20) NOT NULL,
  `log_timestamp` DATETIME NOT NULL,
  `verify_type` VARCHAR(20) DEFAULT 'FINGER',
  `source_file` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_finger_nik_time` (`nik`, `log_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Tabel Mobile GPS & Biometric Face Scan Log (ESS PWA)
-- ----------------------------------------------------------------------------
CREATE TABLE `mobile_gps_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED NOT NULL,
  `clock_type` ENUM('IN', 'OUT') NOT NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `distance_from_office_meters` DECIMAL(8,2) NOT NULL,
  `is_in_radius` TINYINT(1) NOT NULL DEFAULT 0,
  `gps_accuracy` DECIMAL(5,2) DEFAULT 5.00,
  `face_biometric_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `face_confidence_score` DECIMAL(5,2) DEFAULT 99.40,
  `face_liveness_score` DECIMAL(5,2) DEFAULT 98.70,
  `face_landmarks_detected` INT DEFAULT 68,
  `photo_selfie_path` VARCHAR(255) NULL,
  `approval_status` ENUM('AUTO_VERIFIED', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED') DEFAULT 'AUTO_VERIFIED',
  `hr_approval_note` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_gps_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  INDEX `idx_gps_emp_created` (`employee_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Tabel Kehadiran Harian (Daily Attendance Grid)
-- ----------------------------------------------------------------------------
CREATE TABLE `attendance_daily` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `shift_id` INT UNSIGNED NULL,
  `clock_in` TIME NULL,
  `clock_out` TIME NULL,
  `status_code` ENUM('H', 'T', 'PC', 'I', 'S', 'C', 'A', 'OFF') NOT NULL DEFAULT 'A',
  `late_minutes` INT NOT NULL DEFAULT 0,
  `early_leave_minutes` INT NOT NULL DEFAULT 0,
  `overtime_hours` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  `fdt_leave_form_no` VARCHAR(50) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_emp_date` (`employee_id`, `attendance_date`),
  CONSTRAINT `fk_att_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Tabel Pengajuan Cuti & Izin FDT (Leave Requests & Workflow)
-- ----------------------------------------------------------------------------
CREATE TABLE `leave_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `form_no` VARCHAR(50) NOT NULL UNIQUE,
  `employee_id` INT UNSIGNED NOT NULL,
  `leave_type` ENUM('ANNUAL_LEAVE', 'SICK_LEAVE', 'SPECIAL_PERMISSION', 'MATERNITY', 'UNPAID_LEAVE') NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` INT NOT NULL DEFAULT 1,
  `reason` TEXT NOT NULL,
  `attachment_doc_path` VARCHAR(255) NULL,
  `hr_approval_status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
  `approver_name` VARCHAR(100) NULL,
  `approval_date` DATETIME NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_leave_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Tabel Periode Payroll
-- ----------------------------------------------------------------------------
CREATE TABLE `payroll_periods` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `period_name` VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `pay_date` DATE NOT NULL,
  `period_type` ENUM('WEEKLY', 'MONTHLY') DEFAULT 'WEEKLY',
  `status` ENUM('DRAFT', 'CALCULATED', 'LOCKED', 'PAID') DEFAULT 'DRAFT',
  `total_disbursement` DECIMAL(15,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. Tabel e-Payslip & Tanda Tangan Elektronik (UU ITE)
-- ----------------------------------------------------------------------------
CREATE TABLE `payslips` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payroll_period_id` INT UNSIGNED NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `nik` VARCHAR(20) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `division` VARCHAR(100) NOT NULL,
  `position_name` VARCHAR(100) NOT NULL,
  `payment_method` ENUM('BANK_TRANSFER', 'CASH') DEFAULT 'BANK_TRANSFER',
  `bank_name` VARCHAR(50) DEFAULT 'BCA',
  `bank_account_no` VARCHAR(50) DEFAULT '',
  
  -- Rincian Pendapatan
  `basic_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `fixed_allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `overtime_pay` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `attendance_days` INT NOT NULL DEFAULT 0,
  `attendance_incentive` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `gross_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  
  -- Rincian Potongan
  `late_minutes` INT NOT NULL DEFAULT 0,
  `late_penalty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `alpha_days` INT NOT NULL DEFAULT 0,
  `alpha_penalty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `bpjs_tk_jht_employee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `bpjs_tk_jp_employee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `bpjs_kes_employee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `pph21_ter_rate` DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
  `pph21_ter_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `net_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  
  -- Kontribusi Perusahaan
  `bpjs_tk_company` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `bpjs_kes_company` DECIMAL(15,2) NOT NULL DEFAULT 0.00,

  -- Tanda Tangan Elektronik
  `is_signed_by_employee` TINYINT(1) NOT NULL DEFAULT 0,
  `signature_id` VARCHAR(50) NULL,
  `signature_method` ENUM('DRAWN_CANVAS', 'CRYPTOGRAPHIC_TOKEN') NULL,
  `signature_data_url` MEDIUMTEXT NULL,
  `verification_hash` VARCHAR(100) NULL,
  `signature_date` DATETIME NULL,
  `signer_ip_address` VARCHAR(50) NULL,
  `signer_device_info` VARCHAR(150) NULL,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payslip_period` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payslip_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Tabel Pusat Arsip Lampiran (Attachment Documents)
-- ----------------------------------------------------------------------------
CREATE TABLE `attachment_documents` (
  `id` VARCHAR(50) PRIMARY KEY, -- DOC-2026-001
  `related_form_no` VARCHAR(50) NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `employee_name` VARCHAR(150) NOT NULL,
  `nik` VARCHAR(20) NOT NULL,
  `division` VARCHAR(100) NOT NULL,
  `category` ENUM('DOCTOR_LETTER', 'RECEIPT', 'LEAVE_FORM', 'EMPLOYMENT_CONTRACT', 'IDENTITY_CARD', 'OTHER') NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `current_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `tags` JSON NULL,
  `status` ENUM('VERIFIED', 'PENDING_REVIEW', 'REJECTED') DEFAULT 'VERIFIED',
  `extracted_summary` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_attdoc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Tabel Version Control Lampiran (Attachment Versions & History)
-- ----------------------------------------------------------------------------
CREATE TABLE `attachment_versions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `document_id` VARCHAR(50) NOT NULL,
  `version_id` VARCHAR(60) NOT NULL UNIQUE, -- DOC-2026-001-v1
  `version_number` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_size_kb` INT UNSIGNED NOT NULL DEFAULT 0,
  `file_type` ENUM('PDF', 'IMAGE_JPG', 'IMAGE_PNG', 'DOCX', 'XLSX') DEFAULT 'PDF',
  `uploaded_by` VARCHAR(100) NOT NULL,
  `change_notes` TEXT NULL,
  `extracted_text` LONGTEXT NULL,
  `ocr_confidence` DECIMAL(5,2) DEFAULT 98.50,
  `doctor_name` VARCHAR(150) NULL,
  `clinic_hospital` VARCHAR(150) NULL,
  `diagnosis` VARCHAR(200) NULL,
  `cost_amount` DECIMAL(15,2) NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ver_doc` FOREIGN KEY (`document_id`) REFERENCES `attachment_documents` (`id`) ON DELETE CASCADE,
  INDEX `idx_ver_doc_num` (`document_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA AWAL (PT. TELASIH MAKMUR - BLIMBING, MALANG)
-- ============================================================================

INSERT INTO `divisions` (`id`, `code`, `name`) VALUES
(1, 'PROD', 'Divisi Produksi Mesin & Komponen'),
(2, 'ENG', 'Divisi Engineering & Maintenance'),
(3, 'HRD', 'Human Resource & General Affairs'),
(4, 'FIN', 'Finance & Payroll'),
(5, 'LOG', 'Logistik & Gudang Material');

INSERT INTO `shifts` (`id`, `shift_code`, `name`, `start_time`, `end_time`, `late_tolerance_minutes`) VALUES
(1, 'SHIFT-1', 'Shift Pagi Reguler', '07:30:00', '16:30:00', 15),
(2, 'SHIFT-2', 'Shift Siang Produksi', '15:30:00', '23:30:00', 15),
(3, 'SHIFT-3', 'Shift Malam Pabrik', '23:30:00', '07:30:00', 15);

INSERT INTO `employees` (`id`, `nik`, `full_name`, `division`, `position_name`, `employment_type`, `join_date`, `ptkp_status`, `basic_salary`, `fixed_allowance`, `daily_attendance_incentive`, `hourly_overtime_rate`, `bank_name`, `bank_account_no`, `bank_account_holder`) VALUES
(1, 'TM-2024-001', 'Ahmad Pratama', 'Divisi Produksi Mesin & Komponen', 'Senior Operator Mesin Bubut CNC', 'TETAP', '2022-03-01', 'K/1', 4500000.00, 500000.00, 25000.00, 28901.00, 'BCA', '1420988771', 'Ahmad Pratama'),
(2, 'TM-2024-002', 'Budi Santoso', 'Divisi Produksi Mesin & Komponen', 'Operator Mesin Milling', 'KONTRAK_PKWT', '2023-01-15', 'TK/0', 4200000.00, 350000.00, 25000.00, 26300.00, 'MANDIRI', '1370019283741', 'Budi Santoso'),
(3, 'TM-2024-003', 'Citra Dewi', 'Human Resource & General Affairs', 'HR Officer & Payroll Staff', 'TETAP', '2021-08-10', 'TK/0', 4800000.00, 600000.00, 30000.00, 31213.00, 'BCA', '1420766542', 'Citra Dewi'),
(4, 'TM-2024-004', 'Dedi Kurniawan', 'Divisi Engineering & Maintenance', 'Teknisi Listrik & Otomasi PLC', 'TETAP', '2020-05-18', 'K/2', 5200000.00, 750000.00, 30000.00, 34393.00, 'BCA', '1420554433', 'Dedi Kurniawan'),
(5, 'TM-2024-005', 'Eko Prasetyo', 'Logistik & Gudang Material', 'Staff Inventory & Forklift Driver', 'KONTRAK_PKWT', '2023-06-01', 'TK/1', 4100000.00, 300000.00, 25000.00, 25433.00, 'CASH', '-', 'Eko Prasetyo');

-- Dokumen Lampiran Contoh (Modul 12)
INSERT INTO `attachment_documents` (`id`, `related_form_no`, `employee_id`, `employee_name`, `nik`, `division`, `category`, `title`, `current_version`, `tags`, `status`, `extracted_summary`) VALUES
('DOC-2026-001', 'FDT-2026-08-012', 1, 'Ahmad Pratama', 'TM-2024-001', 'Divisi Produksi Mesin & Komponen', 'DOCTOR_LETTER', 'Surat Sakit & Kwitansi Rawat Inap RS Lavalette Malang', 2, '["surat-dokter", "rawat-inap", "reimbursement"]', 'VERIFIED', 'Diagnosis Gejala Tipes Ringan. Istirahat medis 3 hari.'),
('DOC-2026-002', 'FDT-2026-08-019', 2, 'Budi Santoso', 'TM-2024-002', 'Divisi Produksi Mesin & Komponen', 'LEAVE_FORM', 'Formulir FDT Cuti Tahunan - Acara Keluarga Blitar', 1, '["cuti-tahunan", "fdt-resmi"]', 'VERIFIED', 'Formulir cuti disetujui atasan.');

INSERT INTO `attachment_versions` (`document_id`, `version_id`, `version_number`, `file_name`, `file_size_kb`, `file_type`, `uploaded_by`, `change_notes`, `extracted_text`, `ocr_confidence`, `doctor_name`, `clinic_hospital`, `diagnosis`, `cost_amount`, `is_current`) VALUES
('DOC-2026-001', 'DOC-2026-001-v1', 1, 'Surat_Dokter_Ahmad_Pratama.pdf', 540, 'PDF', 'Ahmad Pratama (ESS)', 'Unggahan awal surat istirahat dokter.', 'SURAT KETERANGAN DOKTER RS LAVALETTE MALANG. Pasien: Ahmad Pratama. Diagnosis: Demam Observasi Tipes.', 98.20, 'dr. Bambang Irawan, Sp.PD', 'RS Lavalette Malang', 'Gejala Tipes', 450000.00, 0),
('DOC-2026-001', 'DOC-2026-001-v2', 2, 'Surat_Dokter_Ahmad_Pratama_Revisi_Kwitansi.pdf', 820, 'PDF', 'HR Admin', 'Revisi penambahan kwitansi tebus obat resmi.', 'LAMPIRAN LENGKAP RS LAVALETTE & KWITANSI APOTEK. Pasien: Ahmad Pratama. Total Kwitansi: Rp 750.000.', 99.10, 'dr. Bambang Irawan, Sp.PD', 'RS Lavalette Malang', 'Gejala Tipes (Rawat Inap 3 Hari)', 750000.00, 1),
('DOC-2026-002', 'DOC-2026-002-v1', 1, 'FDT_Cuti_Budi_Santoso.pdf', 310, 'PDF', 'Budi Santoso (ESS)', 'Unggahan lembar form cuti ditandatangani supervisor.', 'FORMULIR DISPENSASI & CUTI PT. TELASIH MAKMUR. Pemohon: Budi Santoso. Cuti Tahunan 2 Hari.', 99.50, NULL, NULL, NULL, NULL, 1);
