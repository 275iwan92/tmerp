-- ============================================================
-- TIER 3A — MASTER EMPLOYEE (HRM), all-new DDL
-- ERP Cetak Offset & Packaging — PT. Telasih Makmur
-- DB: tm2mst (org_*, emp_* = master netral lintas modul, sesuai prefixlist.md)
-- Auth: tm2sys (users) — FK logical, BUKAN constraint lintas-DB (GLOBAL_RULES.fk_cross_db)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- BAGIAN 0 — MASTER MESIN (prod_machine)
-- Ditambahkan krn dibutuhkan sbg FK oleh emp_employee.default_machine_id
-- dan emp_shift_roster.machine_id. Domain sebenarnya milik modul
-- Production/PPIC (belum digarap penuh) — di sini HANYA subset minimal
-- yang dibutuhkan utk assignment shift. Lokasi tetap tm2mst (master netral).
-- ============================================================
DROP TABLE IF EXISTS `prod_machine`;
CREATE TABLE `prod_machine` (
  `machine_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id`  INT UNSIGNED NOT NULL COMMENT 'FK org_division — mesin ini milik SBU mana. Saat ini hanya Offset Printing yang mengisi',
  `machine_code` VARCHAR(20)  NOT NULL COMMENT 'Mis. CETAK-01, COAT-01',
  `machine_name` VARCHAR(100) NOT NULL COMMENT 'Mis. Cetak 1, Coating, Laminasi',
  `machine_type`       VARCHAR(20)  NULL DEFAULT NULL COMMENT 'Cetak|Coating|Laminasi|Potong|Lainnya',
  `brand_model`        VARCHAR(150) NULL DEFAULT NULL COMMENT 'Mis. Heidelberg SM 74, Komori L528',
  `year_acquired`      SMALLINT UNSIGNED NULL DEFAULT NULL,
  `serial_no`          VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nomor seri aset',
  `max_paper_size`     VARCHAR(50)  NULL DEFAULT NULL COMMENT 'Mis. 52 x 74 cm',
  `color_count`        TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Jumlah warna/unit cetak',
  `max_speed_per_hour` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Kecepatan max, sheet/jam',
  `capacity_per_shift` VARCHAR(50)  NULL DEFAULT NULL COMMENT 'Estimasi kapasitas per shift, teks bebas (mis. "± 100.000 sheet")',
  `gsm_min`            SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Gramatur kertas minimal yg didukung',
  `gsm_max`            SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Gramatur kertas maksimal yg didukung',
  `notes`              TEXT NULL DEFAULT NULL,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`machine_id`),
  UNIQUE KEY `uq_machine_division_code` (`division_id`,`machine_code`),
  KEY `idx_machine_division` (`division_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master mesin/lini produksi, termasuk spesifikasi teknis lengkap';

-- ============================================================
-- BAGIAN 1 — STRUKTUR ORGANISASI (org_*)
-- ============================================================

-- 1.1 org_company (grup multi-entity)
DROP TABLE IF EXISTS `org_company`;
CREATE TABLE `org_company` (
  `company_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_code` VARCHAR(10)  NOT NULL COMMENT 'Kode singkat, dipakai prefix employee_code. Mis. TM',
  `company_name` VARCHAR(150) NOT NULL,
  `npwp_number`  VARCHAR(30)  NULL DEFAULT NULL,
  `address`      VARCHAR(255) NULL DEFAULT NULL,
  `phone`        VARCHAR(30)  NULL DEFAULT NULL,
  `email`        VARCHAR(100) NULL DEFAULT NULL,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`company_id`),
  UNIQUE KEY `uq_company_code` (`company_code`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grup usaha (multi-entity). PT. Telasih Makmur = 1 baris di sini';

-- 1.2 org_level (grade/tingkat jabatan, lintas company)
DROP TABLE IF EXISTS `org_level`;
CREATE TABLE `org_level` (
  `level_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_code` VARCHAR(20)  NOT NULL COMMENT 'Mis. STAFF, SPV, MGR, DIR',
  `level_name` VARCHAR(100) NOT NULL,
  `level_rank` SMALLINT UNSIGNED NOT NULL COMMENT 'Urutan hierarki naik = makin tinggi. Dipakai kelak utk matriks approval',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) NULL DEFAULT NULL,
  `updated_by` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`level_id`),
  UNIQUE KEY `uq_level_code` (`level_code`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grade/tingkat jabatan lintas company';

-- 1.3 org_division
DROP TABLE IF EXISTS `org_division`;
CREATE TABLE `org_division` (
  `division_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `division_code` VARCHAR(20)  NOT NULL,
  `division_name` VARCHAR(150) NOT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`division_id`),
  UNIQUE KEY `uq_division_company_code` (`company_id`,`division_code`),
  KEY `idx_division_company` (`company_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Divisi dalam 1 company';

-- 1.4 org_department
DROP TABLE IF EXISTS `org_department`;
CREATE TABLE `org_department` (
  `department_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id`     INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK org_division. NULLABLE — NULL berarti departemen shared/lintas-SBU (mis. Security, Staff/Admin) yang menempel langsung ke company, bukan ke 1 divisi tertentu',
  `company_id`      INT UNSIGNED NOT NULL COMMENT 'FK org_company. WAJIB diisi selalu (baik departemen shared maupun yang di bawah divisi) — mempermudah query tanpa join ke org_division saat division_id NULL',
  `department_code` VARCHAR(20)  NOT NULL,
  `department_name` VARCHAR(150) NOT NULL,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`      VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`      VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_department_company_code` (`company_id`,`department_code`),
  KEY `idx_department_division` (`division_id`),
  KEY `idx_department_company` (`company_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Departemen — di bawah 1 divisi (SBU-spesifik) ATAU langsung company (shared/lintas-SBU spt Security, Staff/Admin)';

-- 1.5 org_position (jabatan)
DROP TABLE IF EXISTS `org_position`;
CREATE TABLE `org_position` (
  `position_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED NOT NULL,
  `level_id`      INT UNSIGNED NOT NULL,
  `position_code` VARCHAR(20)  NOT NULL,
  `position_name` VARCHAR(150) NOT NULL,
  `salary_hidden_from_hr` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'KONFIGURASI, bukan hardcode. 1 = gaji posisi ini SELALU disembunyikan dari role HR Officer di layar Kontrak, terlepas dari employment_type. Default rekomendasi: 1 utk Direksi/General Manager/Manager/Operational Manager — PERLU KONFIRMASI Telma per posisi riil',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`position_id`),
  UNIQUE KEY `uq_position_department_code` (`department_id`,`position_code`),
  KEY `idx_position_department` (`department_id`),
  KEY `idx_position_level` (`level_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jabatan spesifik dalam 1 departemen';

-- ============================================================
-- BAGIAN 2 — DATA PEGAWAI (emp_*)
-- ============================================================

-- 2.1 emp_employee (core)
DROP TABLE IF EXISTS `emp_employee`;
CREATE TABLE `emp_employee` (
  `employee_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_code`  VARCHAR(20)  NOT NULL COMMENT 'Auto: {company_code}-{seq 5 digit}, mis. TM-00001',
  `company_id`     INT UNSIGNED NOT NULL,
  `position_id`    INT UNSIGNED NOT NULL COMMENT 'Posisi saat ini (current, bukan versioned)',
  `full_name`      VARCHAR(150) NOT NULL,
  `nickname`       VARCHAR(50)  NULL DEFAULT NULL,
  `gender`         VARCHAR(1)   NOT NULL COMMENT 'L|P',
  `birth_place`    VARCHAR(100) NULL DEFAULT NULL,
  `birth_date`     DATE         NULL DEFAULT NULL,
  `nik_ktp`        VARCHAR(20)  NULL DEFAULT NULL,
  `npwp_number`    VARCHAR(30)  NULL DEFAULT NULL,
  `religion`       VARCHAR(20)  NULL DEFAULT NULL COMMENT 'Agama — Islam|Kristen|Katolik|Hindu|Buddha|Konghucu|Lainnya. Field sensitif: koreksi dari self-service WAJIB approval HR',
  `phone`          VARCHAR(30)  NULL DEFAULT NULL,
  `email_personal` VARCHAR(100) NULL DEFAULT NULL,
  `village_id_ktp`        BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK geo_village — alamat SESUAI KTP, level kelurahan via AddressPicker',
  `address_detail_ktp`    VARCHAR(255) NULL DEFAULT NULL COMMENT 'Jalan/RT-RW alamat KTP, detail di bawah kelurahan',
  `village_id_domisili`     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK geo_village — alamat domisili/tinggal SAAT INI. NULL berarti sama dgn KTP (belum diisi terpisah)',
  `address_detail_domisili` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Jalan/RT-RW alamat domisili, terpisah dari KTP krn banyak kasus perantau (alamat tinggal != KTP)',
  `join_date`      DATE         NOT NULL,
  `resign_date`    DATE         NULL DEFAULT NULL,
  `resign_reason`  VARCHAR(255) NULL DEFAULT NULL,
  `photo_path`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'Foto kartu pengenal aktif (versi terakhir yg disetujui HR — lihat emp_badge_photo_request utk riwayat pengajuan)',
  `work_status`        VARCHAR(15)  NOT NULL DEFAULT 'WORKING' COMMENT 'WORKING|DIRUMAHKAN|CUTI — status kerja harian, TERPISAH dari is_active (employee tetap is_active=1 saat DIRUMAHKAN)',
  `work_status_since`  DATE         NULL DEFAULT NULL COMMENT 'Tanggal mulai berlaku work_status saat ini',
  `emergency_contact_name`     VARCHAR(150) NULL DEFAULT NULL,
  `emergency_contact_relation` VARCHAR(50)  NULL DEFAULT NULL,
  `emergency_contact_phone`    VARCHAR(30)  NULL DEFAULT NULL,
  `default_machine_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK prod_machine. OPSIONAL — hanya prefill saat PPIC buat shift baru, TIDAK mengunci/membatasi assignment ke mesin lain',
  `default_shift_id`   INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK emp_shift. OPSIONAL — hanya prefill saat create shift schedule, TIDAK mengunci/membatasi assignment ke shift lain',
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=ACTIVE, 0=INACTIVE (resign/terminated)',
  `created_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`     VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`     VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employee_code` (`employee_code`),
  KEY `idx_employee_company` (`company_id`),
  KEY `idx_employee_position` (`position_id`),
  KEY `idx_employee_default_machine` (`default_machine_id`),
  KEY `idx_employee_default_shift` (`default_shift_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master pegawai — sumber kebenaran identitas orang lintas modul';

-- 2.2 emp_contract (versioned — 1 is_active=1 per employee)
DROP TABLE IF EXISTS `emp_contract`;
CREATE TABLE `emp_contract` (
  `contract_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`     BIGINT UNSIGNED NOT NULL,
  `contract_no`     VARCHAR(50)  NULL DEFAULT NULL,
  `employment_type` VARCHAR(10)  NOT NULL COMMENT 'TETAP|KONTRAK|HARIAN|BORONGAN',
  `start_date`      DATE         NOT NULL,
  `end_date`        DATE         NULL DEFAULT NULL COMMENT 'NULL hanya utk TETAP',
  `salary_type`     VARCHAR(10)  NOT NULL COMMENT 'PER_MONTH|PER_DAY|PER_UNIT',
  `salary_amount`   DECIMAL(18,4) NOT NULL DEFAULT 0,
  `is_probation`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Flag, BUKAN tipe kontrak baru. Bisa 1 pada KONTRAK/TETAP awal',
  `probation_end_date` DATE        NULL DEFAULT NULL COMMENT 'Wajib diisi jika is_probation=1. Lolos -> is_probation=0 (tanpa baris baru). Gagal -> nonaktifkan employee',
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Kontrak berlaku saat ini. Hanya 1 aktif per employee (enforced app-level)',
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`      VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`      VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`contract_id`),
  KEY `idx_contract_employee` (`employee_id`),
  KEY `idx_contract_employee_active` (`employee_id`,`is_active`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat kontrak kerja, versioned pattern spt rate fluktuatif Costing';

-- 2.3 emp_family
DROP TABLE IF EXISTS `emp_family`;
CREATE TABLE `emp_family` (
  `family_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `relation`    VARCHAR(15)  NOT NULL COMMENT 'SUAMI|ISTRI|ANAK|ORANG_TUA',
  `full_name`   VARCHAR(150) NOT NULL,
  `birth_date`  DATE         NULL DEFAULT NULL,
  `nik`         VARCHAR(20)  NULL DEFAULT NULL,
  `is_deceased`   TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'Anggota meninggal DITANDAI di sini, TIDAK DIHAPUS — histori tetap ada utk tanggungan/administrasi',
  `deceased_date` DATE        NULL DEFAULT NULL COMMENT 'Wajib diisi jika is_deceased=1',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`  VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`  VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`family_id`),
  KEY `idx_family_employee` (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data keluarga pegawai (utk BPJS/PPh21 kelak). Perubahan (lahir/meninggal/pasangan) via emp_family_request, lihat Bagian 3';

-- 2.4 emp_document
DROP TABLE IF EXISTS `emp_document`;
CREATE TABLE `emp_document` (
  `document_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`   BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(20)  NOT NULL COMMENT 'KTP|NPWP|SIM|KK|SKCK|PASSPORT|IJAZAH|KONTRAK|LAINNYA',
  `document_no`   VARCHAR(50)  NULL DEFAULT NULL,
  `file_path`     VARCHAR(255) NULL DEFAULT NULL,
  `expiry_date`   DATE         NULL DEFAULT NULL COMMENT 'Utk dokumen yg bisa kadaluarsa (SIM/SKCK/Passport). NULL utk KTP WNI (berlaku seumur hidup sejak Kemendagri 2011) kecuali kasus khusus WNA/format lama',
  `uploaded_by_role` VARCHAR(4) NOT NULL DEFAULT 'HR' COMMENT 'SELF|HR — siapa yg upload. Dokumen SELF bisa diganti sendiri oleh karyawan; dokumen HR terkunci read-only di sisi karyawan',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `idx_document_employee` (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dokumen kepegawaian (scan/arsip)';

-- 2.5 emp_bank_account
DROP TABLE IF EXISTS `emp_bank_account`;
CREATE TABLE `emp_bank_account` (
  `bank_account_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`         BIGINT UNSIGNED NOT NULL,
  `bank_name`           VARCHAR(100) NOT NULL,
  `account_no`          VARCHAR(50)  NOT NULL,
  `account_holder_name` VARCHAR(150) NOT NULL,
  `is_primary`          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Hanya 1 primary per employee (enforced app-level)',
  `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`          VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`          VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`bank_account_id`),
  KEY `idx_bankaccount_employee` (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rekening bank pegawai (disiapkan utk payroll kelak)';

-- 2.6 emp_shift (master pola shift — HEADER, direplikasi dari m_shifts legacy)
DROP TABLE IF EXISTS `emp_shift`;
CREATE TABLE `emp_shift` (
  `shift_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED NOT NULL COMMENT 'FK org_department — menggantikan emp_type_id lama. Menentukan shift ini berlaku utk departemen apa (mis. Produksi Offset, Security, Staff/Admin)',
  `shift_name`    VARCHAR(150) NOT NULL COMMENT 'Mis. "TM Production Shift 1", "Security Shift 3" — bebas teks spt legacy, TIDAK dipaksa jadi kode pendek PAGI/SIANG/MALAM krn variasi nama di data asli cukup banyak',
  `description`   TEXT NULL DEFAULT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`shift_id`),
  KEY `idx_shift_department` (`department_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master pola shift (header) — jam aktualnya ada di emp_shift_detail per hari';

-- 2.6b emp_shift_detail (jam kerja PER HARI + jam istirahat — replikasi m_shifts_details)
DROP TABLE IF EXISTS `emp_shift_detail`;
CREATE TABLE `emp_shift_detail` (
  `shift_detail_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shift_id`         INT UNSIGNED NOT NULL,
  `day_of_week`      VARCHAR(10)  NOT NULL COMMENT 'Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday — bahasa Inggris konsisten dgn data legacy m_shifts_details',
  `start_time`       TIME NULL DEFAULT NULL COMMENT 'NULL diperbolehkan (shift terdaftar tapi jam belum diisi, kondisi valid — ditemukan di data legacy)',
  `break_out`        TIME NULL DEFAULT NULL COMMENT 'Jam mulai istirahat',
  `break_in`         TIME NULL DEFAULT NULL COMMENT 'Jam selesai istirahat (masuk kembali)',
  `end_time`         TIME NULL DEFAULT NULL,
  `description`      TEXT NULL DEFAULT NULL,
  `created_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`       VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`       VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`shift_detail_id`),
  UNIQUE KEY `uq_shiftdetail_shift_day` (`shift_id`,`day_of_week`),
  CONSTRAINT `fk_shiftdetail_shift` FOREIGN KEY (`shift_id`) REFERENCES `emp_shift` (`shift_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jam kerja per hari utk 1 shift — hari yg tidak diinput berarti shift tsb tidak beroperasi hari itu (bukan 0 baris = error)';
-- Catatan: emp_shift_roster.roster_date TIDAK menyimpan ulang jam kerja — jam aktual didapat
-- dgn join emp_shift_detail ON day_of_week = DAYNAME(roster_date) saat runtime (hindari duplikasi data).


-- 2.7a emp_shift_roster_batch (Save=DRAFT / Confirm=CONFIRMED / Unlock=balik DRAFT)
DROP TABLE IF EXISTS `emp_shift_roster_batch`;
CREATE TABLE `emp_shift_roster_batch` (
  `batch_id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id`   INT UNSIGNED NOT NULL COMMENT 'FK org_division',
  `date_from`     DATE         NOT NULL COMMENT 'Periode roster bebas (bukan preset mingguan)',
  `date_to`       DATE         NOT NULL,
  `status`        VARCHAR(10)  NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT (hanya terlihat PPIC) | CONFIRMED (terkunci read-only, published ke HR + portal self-service karyawan)',
  `confirmed_by`  VARCHAR(100) NULL DEFAULT NULL,
  `confirmed_at`  TIMESTAMP    NULL DEFAULT NULL,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `idx_batch_division_period` (`division_id`,`date_from`,`date_to`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Batch save/confirm/unlock utk board roster PPIC. Unlock = UPDATE status kembali ke DRAFT';

-- 2.7b emp_shift_roster (jadwal rotasi aktual — hasil board PPIC atau grid HR)
DROP TABLE IF EXISTS `emp_shift_roster`;
CREATE TABLE `emp_shift_roster` (
  `roster_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`         BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK emp_shift_roster_batch. NULL utk baris grid HR sederhana (Digital Printing/Indostry) yg tidak pakai alur save/confirm',
  `employee_id`      BIGINT UNSIGNED NOT NULL,
  `division_id`      INT UNSIGNED NOT NULL COMMENT 'FK org_division — SBU pemilik jadwal ini (Offset/Digital/Indostry)',
  `machine_id`       INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK prod_machine. NULL utk SBU tanpa assignment mesin (Digital Printing/Indostry, grid HR sederhana). Wajib diisi jika division=Offset Printing',
  `shift_id`         INT UNSIGNED NOT NULL,
  `roster_date`      DATE         NOT NULL,
  `assigned_by_role` VARCHAR(10)  NOT NULL DEFAULT 'HR' COMMENT 'PPIC|HR — siapa yang input baris ini. Offset Printing = PPIC, SBU lain = HR',
  `is_overtime`      TINYINT(1)   NOT NULL DEFAULT 0,
  `overtime_type`    VARCHAR(15)  NULL DEFAULT NULL COMMENT 'REGULER|HARI_MINGGU|HARI_BESAR — wajib diisi jika is_overtime=1. Menentukan pengali upah lembur',
  `created_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`       VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`       VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`roster_id`),
  -- Catatan constraint (lihat Tier 2 §A.6 revisi):
  --  - TIDAK unique(employee_id, roster_date) lagi -> 1 orang BOLEH >1 shift di hari sama (soft-conflict, div-cek manual PPIC/HR)
  --  - unique di bawah cegah baris identik dobel-input (bukan double-shift, tapi entri sama persis)
  --  - Bentrok HARD (1 orang, mesin beda, shift+tanggal SAMA) divalidasi di APPLICATION LAYER saat simpan
  --    (query: employee_id + roster_date + shift_id sudah ada dgn machine_id beda -> tolak/warning keras)
  UNIQUE KEY `uq_roster_exact` (`employee_id`,`roster_date`,`shift_id`,`machine_id`),
  KEY `idx_roster_division_date` (`division_id`,`roster_date`),
  KEY `idx_roster_machine_shift_date` (`machine_id`,`shift_id`,`roster_date`),
  KEY `idx_roster_employee_date` (`employee_id`,`roster_date`),
  KEY `idx_roster_batch` (`batch_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jadwal shift aktual. Offset Printing: machine_id terisi, assigned_by_role=PPIC. SBU lain: machine_id NULL, assigned_by_role=HR';

-- ============================================================
-- BAGIAN 3 — SELF-SERVICE & REQUEST (portal karyawan)
-- Semua pengajuan di sini mengikuti pola sama: status PENDING/APPROVED/REJECTED,
-- requested_at/reviewed_by/reviewed_at. Approve = aplikasi UPDATE tabel master
-- terkait (emp_employee/emp_family/dst) dalam 1 transaction, BUKAN trigger DB.
-- Notifikasi ke HR = domain modul 08_security/notification, di luar file ini.
-- ============================================================

-- 3.1 emp_correction_request (koreksi field generik: nama, alamat KTP/domisili,
--     telepon, agama, tanggal lahir, no rekening, no BPJS, dst)
DROP TABLE IF EXISTS `emp_correction_request`;
CREATE TABLE `emp_correction_request` (
  `request_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`  BIGINT UNSIGNED NOT NULL,
  `field_name`   VARCHAR(100) NOT NULL COMMENT 'Nama field yg diajukan koreksi, mis. "Nama Lengkap", "Alamat Domisili", "Tanggal Lahir"',
  `old_value`    VARCHAR(255) NULL DEFAULT NULL,
  `new_value`    VARCHAR(255) NOT NULL,
  `reason`       VARCHAR(255) NULL DEFAULT NULL,
  `attachment_path` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Opsional, mis. lampiran surat nikah utk koreksi status pernikahan',
  `requires_hr_approval` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'SELALU 1 utk field sensitif (NIK KTP, Tanggal Lahir) — TIDAK BOLEH auto-approve',
  `status`       VARCHAR(10)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED',
  `requested_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by`  VARCHAR(100) NULL DEFAULT NULL,
  `reviewed_at`  TIMESTAMP    NULL DEFAULT NULL,
  `review_note`  VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_correction_employee` (`employee_id`),
  KEY `idx_correction_status` (`status`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan koreksi data pribadi dari portal self-service, wajib approval HR';

-- 3.2 emp_leave_balance (saldo cuti per tahun, parameter jatah bisa berubah)
DROP TABLE IF EXISTS `emp_leave_balance`;
CREATE TABLE `emp_leave_balance` (
  `balance_id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`        BIGINT UNSIGNED NOT NULL,
  `year`               SMALLINT UNSIGNED NOT NULL,
  `entitlement_days`   TINYINT UNSIGNED NOT NULL DEFAULT 12 COMMENT 'Jatah cuti tahun ybs — PARAMETER, saat ini 12 hari/tahun, BUKAN hardcode aplikasi',
  `used_days`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `carried_over_days`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '[DELTA] Carryover dari tahun lalu — aturan detail belum ditentukan',
  `created_at`         TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY `uq_balance_employee_year` (`employee_id`,`year`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Saldo cuti tahunan per employee. Sisa = entitlement_days + carried_over_days - used_days';

-- 3.3 emp_leave_request (Cuti Tahunan | Sakit | Izin | Cuti Khusus)
-- SESUAI SOP PERIZINAN 002/RP/TM/VII/2026 (efektif 6 Jul 2026):
DROP TABLE IF EXISTS `emp_leave_request`;
CREATE TABLE `emp_leave_request` (
  `request_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`        BIGINT UNSIGNED NOT NULL,
  `leave_type`         VARCHAR(15)  NOT NULL COMMENT 'CUTI|SAKIT|IZIN|CUTI_KHUSUS',
  `khusus_event_type`  VARCHAR(30)  NULL DEFAULT NULL COMMENT 'Wajib diisi jika leave_type=CUTI_KHUSUS: MENIKAH|MENIKAHKAN_KHITAN_ANAK|ISTRI_MELAHIRKAN|KELUARGA_MENINGGAL|IBADAH',
  `izin_category`      VARCHAR(25)  NULL DEFAULT NULL COMMENT '[SOP] Wajib diisi jika leave_type=IZIN dan sifatnya mendadak: MUSIBAH|INSTANSI_RESMI|KEPENGURUSAN_MUSIBAH. NULL jika izin terjadwal (H-7)',
  `izin_relasi_pemohon` VARCHAR(30) NULL DEFAULT NULL COMMENT '[SOP] Wajib jika izin_category=INSTANSI_RESMI: DIRI_SENDIRI|SUAMI_ISTRI|ANAK_KANDUNG|ANAK_SAMBUNG|ORANG_TUA_KANDUNG|MERTUA',
  `izin_instansi_asal` VARCHAR(40)  NULL DEFAULT NULL COMMENT '[SOP] Wajib jika izin_category=INSTANSI_RESMI: BPJS|RUMAH_SAKIT|SEKOLAH|KEPOLISIAN|INSTANSI_PEMERINTAH_LAINNYA',
  `date_from`          DATE         NOT NULL,
  `date_to`            DATE         NOT NULL,
  `total_days`         TINYINT UNSIGNED NOT NULL COMMENT 'Dihitung app-layer. CUTI: maks 3/pengajuan (PARAMETER). IZIN terjadwal: maks 3 hari berturut/pengajuan (SOP D.2) — hari ke-4 dst otomatis jadi ALPHA, lihat emp_disciplinary',
  `reason`             VARCHAR(255) NULL DEFAULT NULL,
  `attachment_surat_cuti`      VARCHAR(255) NULL DEFAULT NULL COMMENT '[SOP-BARU] Wajib utk CUTI — "Surat Cuti" sbg syarat administrasi (bukan cuma approval atasan)',
  `attachment_surat_dokter`    VARCHAR(255) NULL DEFAULT NULL COMMENT '[SOP] Wajib utk SAKIT — Surat Keterangan Dokter (SKD). Diupload SETELAH masuk kerja kembali, paling lambat hari-H masuk',
  `attachment_kwitansi_resep`  VARCHAR(255) NULL DEFAULT NULL COMMENT '[SOP] Wajib utk SAKIT bersamaan dgn SKD — kwitansi pemeriksaan/resep obat/bukti beli obat. ATAU keduanya diganti attachment_jkn_screenshot',
  `attachment_jkn_screenshot`  VARCHAR(255) NULL DEFAULT NULL COMMENT '[SOP] Alternatif SKD+kwitansi bagi peserta BPJS via app JKN Faskes 1 — screenshot riwayat pendaftaran+diagnosis+obat',
  `attachment_path`    VARCHAR(255) NULL DEFAULT NULL COMMENT 'Lampiran umum lain (mis. CUTI_KHUSUS sesuai jenis peristiwa, atau dokumen instansi resmi utk IZIN mendadak kategori INSTANSI_RESMI)',
  `sakit_lapor_jam`    TIME NULL DEFAULT NULL COMMENT '[SOP] Jam lapor ke atasan+HRD pada hari sakit — WAJIB paling lambat 07:30 WIB (PARAMETER). NULL jika leave_type bukan SAKIT',
  `converted_to_izin`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '[SOP] 1 jika awalnya diajukan SAKIT tapi dokumen tidak lengkap/telat → otomatis dicatat ulang sbg IZIN oleh HR saat review',
  `is_paid`             TINYINT(1)  NOT NULL COMMENT 'Dihitung app-layer saat submit: CUTI=1, CUTI_KHUSUS=1, SAKIT=1 (jika dokumen valid&lengkap), IZIN=0 (no present no pay)',
  `deducts_leave_balance` TINYINT(1) NOT NULL COMMENT 'CUTI=1 (potong saldo), SAKIT/IZIN/CUTI_KHUSUS=0',
  `status`             VARCHAR(10)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED',
  `requested_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by`        VARCHAR(100) NULL DEFAULT NULL,
  `reviewed_at`        TIMESTAMP    NULL DEFAULT NULL,
  `review_note`        VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_leave_employee` (`employee_id`),
  KEY `idx_leave_status` (`status`),
  KEY `idx_leave_type` (`leave_type`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan Cuti/Sakit/Izin/Cuti Khusus, sesuai SOP Perizinan 002/RP/TM/VII/2026. Validasi H+7 (CUTI & IZIN terjadwal), maks 3 hari/pengajuan (CUTI & IZIN), maks 3x IZIN/bulan, dan syarat "saldo cuti habis baru boleh IZIN" dilakukan APLIKASI saat submit, bukan DB constraint';
-- Catatan aturan (app-layer, PARAMETER bisa berubah, sesuai SOP 002/RP/TM/VII/2026):
--  CUTI: min H-7, maks 3 hari kerja/pengajuan, Senin-Sabtu (bukan Minggu/hari besar), wajib attachment_surat_cuti
--  IZIN: HANYA boleh diajukan jika emp_leave_balance sisa=0 pada tahun ybs (app-layer check WAJIB sebelum submit)
--        terjadwal: min H-7. mendadak: harus masuk izin_category (MUSIBAH/INSTANSI_RESMI/KEPENGURUSAN_MUSIBAH), dokumen pendukung maks H+1
--        maks 3 hari berturut/pengajuan DAN maks 3x/bulan — lebih dari itu otomatis jadi ALPHA (insert ke emp_disciplinary)
--  SAKIT: lapor ke atasan+HRD maks jam 07:30 WIB hari-H. Dokumen (SKD+kwitansi/resep, ATAU screenshot JKN) diserahkan
--         paling lambat hari-H masuk kerja. Wajib SKD jika sakit >1 hari ATAU berurutan dgn Minggu/tanggal merah.
--         Jika dokumen tidak lengkap/telat -> converted_to_izin=1, leave_type tetap SAKIT (histori) tapi diperlakukan sbg IZIN

-- 3.4 emp_shift_swap_request (tukar shift, approval bertahap: rekan dulu baru PPIC)
-- [SOP] Deadline pengajuan = H-1 sebelum jadwal kerja (BEDA dari CUTI/IZIN yg H-7).
-- Wajib isi "Form Tukar Shift". Tanpa form sesuai batas waktu -> teguran verbal.
-- Berlaku utk SELURUH karyawan termasuk Leader/Supervisor, tanpa pengecualian jabatan.
DROP TABLE IF EXISTS `emp_shift_swap_request`;
CREATE TABLE `emp_shift_swap_request` (
  `request_id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_employee_id` BIGINT UNSIGNED NOT NULL,
  `requester_roster_id`   BIGINT UNSIGNED NOT NULL COMMENT 'FK emp_shift_roster — jadwal milik requester yg mau ditukar',
  `target_employee_id`    BIGINT UNSIGNED NOT NULL COMMENT 'Rekan yg diajak tukar',
  `target_roster_id`      BIGINT UNSIGNED NOT NULL COMMENT 'FK emp_shift_roster — jadwal milik rekan',
  `reason`                VARCHAR(255) NULL DEFAULT NULL,
  `submitted_on_time`     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '[SOP] 0 jika diajukan KURANG dari H-1 sebelum jadwal kerja — app-layer flag, dipakai HR utk keputusan teguran verbal (SOP H.3), tidak otomatis blokir submit',
  `partner_status`        VARCHAR(10)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED — approval TAHAP 1 dari rekan target',
  `ppic_status`           VARCHAR(10)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED — approval TAHAP 2 dari PPIC, HANYA diproses setelah partner_status=APPROVED',
  `requested_at`          TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `partner_reviewed_at`   TIMESTAMP    NULL DEFAULT NULL,
  `ppic_reviewed_by`      VARCHAR(100) NULL DEFAULT NULL,
  `ppic_reviewed_at`      TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_swap_requester` (`requester_employee_id`),
  KEY `idx_swap_target` (`target_employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan tukar shift, deadline H-1 (SOP 002/RP/TM/VII/2026 poin H). Jika APPROVED kedua tahap, aplikasi swap machine_id/shift_id di 2 baris emp_shift_roster terkait';

-- 3.4b emp_out_permit_request (Surat Izin Keluar pada jam kerja — BARU sesuai SOP poin I)
-- Field fingerprint_* adalah PLACEHOLDER utk integrasi modul Attendance (mesin absensi) yg DITUNDA.
-- Perhitungan otomatis "jam efektif < 4,5 jam = dihitung Izin" TIDAK dibangun sekarang — HR
-- menilai manual dari data fingerprint saat modul Attendance sudah ada.
DROP TABLE IF EXISTS `emp_out_permit_request`;
CREATE TABLE `emp_out_permit_request` (
  `request_id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`          BIGINT UNSIGNED NOT NULL,
  `permit_date`          DATE NOT NULL,
  `planned_out_time`     TIME NOT NULL COMMENT 'Rencana jam keluar',
  `planned_return_time`  TIME NULL DEFAULT NULL COMMENT 'Rencana jam kembali (jika diketahui)',
  `reason`               VARCHAR(255) NOT NULL,
  `fingerprint_out_at`   TIMESTAMP NULL DEFAULT NULL COMMENT '[PLACEHOLDER Attendance] Waktu fingerprint keluar aktual — diisi modul Attendance masa depan, NULL sekarang',
  `fingerprint_in_at`    TIMESTAMP NULL DEFAULT NULL COMMENT '[PLACEHOLDER Attendance] Waktu fingerprint kembali aktual — diisi modul Attendance masa depan, NULL sekarang',
  `atasan_approved_by`   VARCHAR(100) NULL DEFAULT NULL,
  `atasan_approved_at`   TIMESTAMP NULL DEFAULT NULL,
  `hrd_approved_by`      VARCHAR(100) NULL DEFAULT NULL,
  `hrd_approved_at`      TIMESTAMP NULL DEFAULT NULL,
  `status`               VARCHAR(10) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED — HARUS disetujui Atasan Langsung DAN HRD (dual approval, beda dari request lain yg cukup 1 approval)',
  `requested_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_outpermit_employee` (`employee_id`),
  KEY `idx_outpermit_date` (`permit_date`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Surat Izin Keluar pada jam kerja (SOP 002/RP/TM/VII/2026 poin I). Dual approval Atasan+HRD wajib SEBELUM karyawan keluar — Satpam berhak tolak tanpa surat izin yg sudah disetujui';

-- 3.4c emp_disciplinary (Teguran Verbal / SP1 / SP2 / SP3 — sesuai SOP poin F & K)
-- CATATAN: sebelumnya HANYA ada di mockup UI (mockup_master_employee_hr.html), BELUM PERNAH
-- ada DDL. Kolom sanksi_referensi = TEKS INFORMATIF sesuai SOP, BUKAN perhitungan potongan
-- gaji aktual (itu ranah Payroll, ditunda).
DROP TABLE IF EXISTS `emp_disciplinary`;
CREATE TABLE `emp_disciplinary` (
  `disciplinary_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`        BIGINT UNSIGNED NOT NULL,
  `type`               VARCHAR(20) NOT NULL COMMENT 'TEGURAN_VERBAL|SP1|SP2|SP3|ALPHA',
  `incident_date`      DATE NOT NULL,
  `reason`             VARCHAR(255) NOT NULL COMMENT 'Mis. "Alpha 3 hari berturut-turut", "Izin+Alpha >5x dlm 2 bulan", "Dokumen palsu utk pengajuan izin", "Tidak fingerprint 3x/bulan"',
  `related_leave_request_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK emp_leave_request, jika kedisiplinan ini dipicu otomatis dari pelanggaran aturan izin/cuti (mis. izin hari ke-4 berturut jadi Alpha)',
  `sanksi_referensi`   VARCHAR(255) NULL DEFAULT NULL COMMENT '[REFERENSI SAJA] Teks sesuai SOP, mis. "Denda Rp100.000" (Alpha), "Potong 50% pendapatan 1 hari x 3 hari berturut" (SP1), "Potong 50% pendapatan 1 hari x 7 hari berturut" (SP2), "Skorsing 7 hari/PHK" (SP3). BUKAN kolom yg dihitung aplikasi — Payroll yg proses aktual',
  `issued_by`          VARCHAR(100) NOT NULL,
  `created_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`disciplinary_id`),
  KEY `idx_disciplinary_employee` (`employee_id`),
  KEY `idx_disciplinary_type` (`type`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kedisiplinan — Teguran Verbal s/d SP3, sesuai SOP Perizinan 002/RP/TM/VII/2026 poin F & K. Eskalasi: Alpha 3hr berturut->SP; Izin+Alpha >5x/2bulan->SP; Alpha 5hr berturut->proses PHK sesuai PP';

-- 3.5 emp_family_request (kelahiran baru | tandai meninggal | tambah pasangan)
DROP TABLE IF EXISTS `emp_family_request`;
CREATE TABLE `emp_family_request` (
  `request_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`    BIGINT UNSIGNED NOT NULL,
  `request_type`   VARCHAR(12)  NOT NULL COMMENT 'LAHIR|MENINGGAL|PASANGAN',
  `family_id`      BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK emp_family — WAJIB diisi utk request_type=MENINGGAL (anggota mana yg ditandai)',
  `relation`       VARCHAR(15)  NULL DEFAULT NULL COMMENT 'Utk LAHIR/PASANGAN: ANAK|SUAMI|ISTRI',
  `full_name`      VARCHAR(150) NULL DEFAULT NULL COMMENT 'Utk LAHIR/PASANGAN',
  `birth_date`     DATE         NULL DEFAULT NULL COMMENT 'Utk LAHIR/PASANGAN',
  `deceased_date`  DATE         NULL DEFAULT NULL COMMENT 'Utk MENINGGAL',
  `attachment_path` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Akta kelahiran / surat kematian / surat nikah',
  `status`         VARCHAR(10)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED',
  `requested_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by`    VARCHAR(100) NULL DEFAULT NULL,
  `reviewed_at`    TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_familyreq_employee` (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan perubahan data keluarga. Approve MENINGGAL = UPDATE emp_family.is_deceased=1 (bukan DELETE)';

-- 3.6 emp_education (Pendidikan & Pengembangan — dasar nilai investasi employee)
DROP TABLE IF EXISTS `emp_education`;
CREATE TABLE `emp_education` (
  `education_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`    BIGINT UNSIGNED NOT NULL,
  `education_type` VARCHAR(20)  NOT NULL COMMENT 'SERTIFIKASI|SEMINAR|PELATIHAN|PENDIDIKAN_FORMAL',
  `activity_name`  VARCHAR(200) NOT NULL,
  `organizer`      VARCHAR(150) NULL DEFAULT NULL,
  `activity_date`  DATE         NOT NULL,
  `duration_days`  SMALLINT UNSIGNED NULL DEFAULT NULL,
  `cost`           DECIMAL(18,4) NOT NULL DEFAULT 0,
  `funded_by`      VARCHAR(15)  NOT NULL COMMENT 'PERUSAHAAN|PRIBADI|SHARING — HANYA porsi PERUSAHAAN dihitung sbg nilai investasi employee',
  `certificate_path` VARCHAR(255) NULL DEFAULT NULL,
  `valid_until`    DATE         NULL DEFAULT NULL COMMENT 'Utk sertifikasi yg ada masa berlaku',
  `is_bonding`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Flag ikatan dinas',
  `bonding_note`   VARCHAR(255) NULL DEFAULT NULL,
  `uploaded_by_role` VARCHAR(4) NOT NULL DEFAULT 'SELF' COMMENT 'SELF|HR',
  `created_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`education_id`),
  KEY `idx_education_employee` (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat pendidikan/sertifikasi/seminar/pelatihan. SUM(cost) WHERE funded_by=PERUSAHAAN per employee = nilai investasi';

-- 3.7 emp_bpjs_kesehatan (data master kepesertaan — BUKAN engine potongan payroll)
DROP TABLE IF EXISTS `emp_bpjs_kesehatan`;
CREATE TABLE `emp_bpjs_kesehatan` (
  `employee_id`        BIGINT UNSIGNED NOT NULL,
  `no_kepesertaan`     VARCHAR(30)  NULL DEFAULT NULL,
  `kelas_rawat`        VARCHAR(5)   NULL DEFAULT NULL COMMENT 'I|II|III',
  `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
  `dependents_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Jumlah tanggungan terdaftar',
  `updated_at`         TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`         VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='1:1 dgn emp_employee. Skema iuran referensi: Perusahaan 4% + Karyawan 1% dari upah (regulasi saat ini, BISA BERUBAH — TIDAK dihitung otomatis di sini, itu ranah Payroll)';

-- 3.8 emp_bpjs_ketenagakerjaan (5 program tetap: JKK, JKM, JHT, JP, JKP)
DROP TABLE IF EXISTS `emp_bpjs_ketenagakerjaan`;
CREATE TABLE `emp_bpjs_ketenagakerjaan` (
  `employee_id`      BIGINT UNSIGNED NOT NULL,
  `no_kepesertaan`   VARCHAR(30)  NULL DEFAULT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `program_jkk`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Kecelakaan Kerja — 100% perusahaan (0,24-1,74% sesuai kelas risiko)',
  `program_jkm`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Kematian — 100% perusahaan (0,3%)',
  `program_jht`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Hari Tua — Perusahaan 3,7% + Karyawan 2%',
  `program_jp`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Pensiun — Perusahaan 2% + Karyawan 1%',
  `program_jkp`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Kehilangan Pekerjaan — 0,22% perusahaan + subsidi pemerintah',
  `updated_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`       VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='1:1 dgn emp_employee. Persentase iuran = REFERENSI regulasi, BUKAN dihitung otomatis (ranah Payroll, di luar scope)';

-- 3.9 emp_badge_photo_request (pengajuan foto baru utk kartu pengenal)
DROP TABLE IF EXISTS `emp_badge_photo_request`;
CREATE TABLE `emp_badge_photo_request` (
  `request_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`  BIGINT UNSIGNED NOT NULL,
  `photo_path`   VARCHAR(255) NOT NULL,
  `width_px`     INT UNSIGNED NULL DEFAULT NULL COMMENT 'Dimensi aktual file diupload, utk validasi min. 600x900px rasio 2:3',
  `height_px`    INT UNSIGNED NULL DEFAULT NULL,
  `status`       VARCHAR(10)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|APPROVED|REJECTED',
  `requested_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by`  VARCHAR(100) NULL DEFAULT NULL,
  `reviewed_at`  TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_badgephoto_employee` (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan foto baru kartu pengenal. Ketentuan: cetak 4x6cm, min 600x900px, rasio 2:3 (PARAMETER). Approve = UPDATE emp_employee.photo_path + cetak ulang oleh HR (di luar sistem)';

-- 3.10 emp_resign_request (pengunduran diri formal)
DROP TABLE IF EXISTS `emp_resign_request`;
CREATE TABLE `emp_resign_request` (
  `request_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`    BIGINT UNSIGNED NOT NULL,
  `effective_date` DATE         NOT NULL,
  `reason`         VARCHAR(255) NULL DEFAULT NULL,
  `status`         VARCHAR(12)  NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING|PROCESSED — proses lanjutan (serah terima) di luar sistem/manual HR',
  `requested_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by`   VARCHAR(100) NULL DEFAULT NULL,
  `processed_at`   TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_resign_employee` (`employee_id`)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan resign. Saat PROCESSED, aplikasi set emp_employee.is_active=0, resign_date=effective_date, resign_reason=reason';

-- ============================================================
-- BAGIAN 4 — AUTH LINK (referensi saja — tabel users ada di tm2sys)
-- ============================================================
-- Di tm2sys.users WAJIB tambah kolom (bukan bagian file ini, referensi implementasi):
--   employee_id BIGINT UNSIGNED NOT NULL  -- FK LOGICAL ke tm2mst.emp_employee, 1 user = 1 employee
--   role_id     INT UNSIGNED NULL         -- RBAC detail = domain modul 08_security, di luar scope file ini.
--                                          -- Minimal 2 role dipakai modul ini: HR Officer (default staf HR;
--                                          -- TIDAK berhak kelola akun login & gaji level tertentu — lihat
--                                          -- org_position.salary_hidden_from_hr) vs Admin App (akses penuh,
--                                          -- termasuk kelola akun login & semua gaji tanpa masking).
-- Aturan: user TIDAK BISA dibuat berdiri sendiri — hanya dari layar detail employee ("Buat akun login"),
--         dan HANYA role Admin App yang berhak melakukannya (HR Officer tidak punya akses tab "Akun").
-- Saat emp_employee.is_active diset 0 → aplikasi WAJIB set users.is_active=0 (atau kolom setara) di tm2sys.

SET FOREIGN_KEY_CHECKS = 1;
