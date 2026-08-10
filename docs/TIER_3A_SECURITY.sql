-- ============================================================
-- TIER 3A — SECURITY (Modul 9) — adopsi ScriptCase + modifikasi permission
-- ERP Cetak Offset & Packaging — PT. Telasih Makmur
-- Skema: tm2sys · Prefix: sec_ (security)
-- ------------------------------------------------------------
-- PANDUAN UNTUK CLAUDE CODE (baca sebelum bikin model/auth):
--   * `sec_user` = tabel autentikasi UTAMA (BUKAN `users` bawaan Laravel).
--     Model User HARUS: connection 'sys', table 'sec_user', primaryKey 'id_user',
--     username = kolom `login`, password = kolom `pswd` (bcrypt).
--     config/auth.php provider diarahkan ke model ini. Tabel `users` dibuang
--     (lihat CLEANUP_tm2sys.sql).
--   * Sesi pakai `sessions` bawaan Laravel (bukan tabel sec sendiri).
--   * IZIN diberikan per MODUL (sec_module), bukan per form/route individual.
--     Route Laravel dipetakan ke modul via `sec_module.route_prefix` +
--     middleware. Contoh: route 'inquiry/*' -> modul INQUIRY.
--   * Cek izin: lihat blok "LOGIKA CEK IZIN" di akhir file.
-- ------------------------------------------------------------
-- Basis: struktur sec_* ScriptCase (teruji), dimodifikasi agar:
--   1. Izin per MODUL BISNIS berhierarki (2 level), bukan per form/grid/blank.
--   2. 4 aksi bermakna: access, input, approve, export (readonly = access tanpa input).
--   3. Level manajemen (STAFF..DIREKSI) untuk report sensitif.
--   4. Default DENY: tak ada baris izin / can_access=0 = modul tak terlihat.
-- Konvensi 3A: tinyint(1) untuk flag (bukan varchar(1) 'Y'/'N' ScriptCase).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. sec_module — HIERARKI modul & sub-modul (2 level)
--    parent_id NULL = modul induk; terisi = sub-modul.
-- ============================================================
DROP TABLE IF EXISTS `sec_module`;
CREATE TABLE `sec_module` (
  `id_module`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`      INT UNSIGNED NULL DEFAULT NULL   COMMENT 'NULL=modul induk | isi=sub-modul (mis. HPP di bawah INQUIRY)',
  `module_code`    VARCHAR(30)  NOT NULL            COMMENT 'Kode unik, mis. INQUIRY, HPP, MASTER',
  `module_name`    VARCHAR(100) NOT NULL            COMMENT 'Label menu',
  `icon`           VARCHAR(30)  NULL DEFAULT NULL   COMMENT 'Emoji/ikon menu',
  `route_prefix`   VARCHAR(60)  NULL DEFAULT NULL   COMMENT 'Prefix route Laravel, mis. inquiry (utk mapping middleware)',
  `min_mgmt_level` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Level manajemen MINIMUM utk akses (1=STAFF..5=DIREKSI). Report sensitif set tinggi',
  `is_reportable`  TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '1=modul ini punya report (info tampilan)',
  `sort_order`     SMALLINT UNSIGNED NOT NULL DEFAULT 999,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`     VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`     VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_module`) USING BTREE,
  UNIQUE INDEX `uq_module_code` (`module_code` ASC) USING BTREE,
  INDEX `idx_module_parent` (`parent_id` ASC) USING BTREE,
  CONSTRAINT `fk_module_parent` FOREIGN KEY (`parent_id`)
      REFERENCES `sec_module` (`id_module`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Hierarki modul bisnis (2 level). Unit pemberian izin — bukan form/grid';

-- ============================================================
-- 2. sec_group — grup/role + LEVEL MANAJEMEN
-- ============================================================
DROP TABLE IF EXISTS `sec_group`;
CREATE TABLE `sec_group` (
  `id_group`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_code`   VARCHAR(40)  NOT NULL             COMMENT 'Kode, mis. SALES_STAFF, DIREKSI, PRODUKSI',
  `group_name`   VARCHAR(100) NOT NULL             COMMENT 'Deskripsi (ex sec_groups.description ScriptCase)',
  `mgmt_level`   TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=STAFF 2=SUPERVISOR 3=MANAGER 4=GM 5=DIREKSI. Utk report sensitif',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_group`) USING BTREE,
  UNIQUE INDEX `uq_group_code` (`group_code` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Grup/role + level manajemen (STAFF..DIREKSI)';

-- ============================================================
-- 3. sec_group_permission — IZIN per group per modul (inti perubahan)
--    Menggantikan sec_groups_apps (per-app) -> per-modul (jauh lebih sedikit).
--    readonly = can_access=1 & can_input=0 (bukan flag terpisah).
-- ============================================================
DROP TABLE IF EXISTS `sec_group_permission`;
CREATE TABLE `sec_group_permission` (
  `id_permission` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`      INT UNSIGNED NOT NULL            COMMENT 'FK -> sec_group',
  `module_id`     INT UNSIGNED NOT NULL            COMMENT 'FK -> sec_module',
  `can_access`    TINYINT(1)  NOT NULL DEFAULT 0   COMMENT '1=boleh buka & lihat (termasuk report yg diizinkan level). 0=modul TAK terlihat',
  `can_input`     TINYINT(1)  NOT NULL DEFAULT 0   COMMENT '1=boleh create/edit (readwrite). 0=readonly',
  `can_approve`   TINYINT(1)  NOT NULL DEFAULT 0   COMMENT '1=boleh approve (mis. Direksi approve harga)',
  `can_export`    TINYINT(1)  NOT NULL DEFAULT 0   COMMENT '1=boleh export data (KRITIKAL: bisa bawa data perusahaan keluar)',
  `created_at`    TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP   NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_permission`) USING BTREE,
  UNIQUE INDEX `uq_perm_group_module` (`group_id` ASC, `module_id` ASC) USING BTREE,
  INDEX `idx_perm_module` (`module_id` ASC) USING BTREE,
  CONSTRAINT `fk_perm_group` FOREIGN KEY (`group_id`)
      REFERENCES `sec_group` (`id_group`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perm_module` FOREIGN KEY (`module_id`)
      REFERENCES `sec_module` (`id_module`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Izin per group per modul. Default DENY (tak ada baris = tak boleh). readonly=access tanpa input';

-- ============================================================
-- 4. sec_user — adopsi sec_users ScriptCase (pertahankan link employee)
-- ============================================================
DROP TABLE IF EXISTS `sec_user`;
CREATE TABLE `sec_user` (
  `id_user`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`           VARCHAR(190) NOT NULL           COMMENT 'Username login',
  `pswd`            VARCHAR(255) NULL DEFAULT NULL   COMMENT 'Hash password (bcrypt Laravel)',
  `name`            VARCHAR(255) NULL DEFAULT NULL,
  `email`           VARCHAR(255) NULL DEFAULT NULL,
  `phone`           VARCHAR(64)  NULL DEFAULT NULL,
  `active`          TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'ex active varchar(1) -> tinyint',
  `is_admin`        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'ex priv_admin: superadmin bypass semua izin',
  `activation_code` VARCHAR(32)  NULL DEFAULT NULL,
  `mfa`             VARCHAR(255) NULL DEFAULT NULL   COMMENT 'Secret MFA (bila aktif)',
  `mfa_last_updated` TIMESTAMP   NULL DEFAULT NULL,
  `pswd_last_updated` TIMESTAMP  NULL DEFAULT NULL,
  -- link ke master (lintas-DB, dijaga aplikasi) — dari ScriptCase
  `employee_id`     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis -> emp_employee (tm2mst)',
  `linked_type`     VARCHAR(20)  NULL DEFAULT NULL   COMMENT 'Jenis entitas tertaut, mis. EMPLOYEE',
  `linked_id`       BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'ID entitas master (lintas-DB, dijaga app)',
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`      VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`      VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_user`) USING BTREE,
  UNIQUE INDEX `uq_user_login` (`login` ASC) USING BTREE,
  INDEX `idx_user_employee` (`employee_id` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='User login. Tertaut ke emp_employee (master) via FK logis lintas-DB';

-- ============================================================
-- 5. sec_user_group — user <-> group (M:N, dari sec_users_groups)
-- ============================================================
DROP TABLE IF EXISTS `sec_user_group`;
CREATE TABLE `sec_user_group` (
  `id_user_group` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL          COMMENT 'FK -> sec_user',
  `group_id`      INT UNSIGNED NOT NULL             COMMENT 'FK -> sec_group',
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user_group`) USING BTREE,
  UNIQUE INDEX `uq_user_group` (`user_id` ASC, `group_id` ASC) USING BTREE,
  INDEX `idx_ug_group` (`group_id` ASC) USING BTREE,
  CONSTRAINT `fk_ug_user` FOREIGN KEY (`user_id`)
      REFERENCES `sec_user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ug_group` FOREIGN KEY (`group_id`)
      REFERENCES `sec_group` (`id_group`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Keanggotaan user dalam group (M:N). Izin efektif = gabungan izin semua group user';

-- ============================================================
-- 6. sec_login_attempt — audit percobaan login (dari login_attempts + sc_log)
-- ============================================================
DROP TABLE IF EXISTS `sec_login_attempt`;
CREATE TABLE `sec_login_attempt` (
  `id_attempt`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`        VARCHAR(190) NOT NULL,
  `success`      TINYINT(1)   NOT NULL              COMMENT '1=berhasil | 0=gagal',
  `ip_address`   VARCHAR(45)  NULL DEFAULT NULL     COMMENT 'IPv4/IPv6',
  `user_agent`   VARCHAR(255) NULL DEFAULT NULL,
  `attempted_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_attempt`) USING BTREE,
  INDEX `idx_attempt_login_time` (`login` ASC, `attempted_at` ASC) USING BTREE,
  INDEX `idx_attempt_success` (`success` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit login (deteksi brute force). Diisi server-side tiap percobaan';

-- ============================================================
-- 7. SESI LOGIN — TIDAK dibuat tabel sendiri.
--    Pakai tabel `sessions` BAWAAN LARAVEL (SESSION_DRIVER=database).
--    Tabel sec_session versi lama DIBUANG (redundan) — lihat CLEANUP_tm2sys.sql.
--    Bila butuh audit "siapa login kapan dari IP mana" yang lebih detail dari
--    `sessions`, gunakan sec_login_attempt (tabel 6) — sudah mencatat itu.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED contoh (hierarki modul + group + izin) — sesuaikan nanti
-- ============================================================
-- Modul induk & sub-modul
INSERT INTO `sec_module` (`id_module`,`parent_id`,`module_code`,`module_name`,`icon`,`route_prefix`,`min_mgmt_level`,`is_reportable`,`sort_order`) VALUES
  (1, NULL, 'INQUIRY',   'Sales & Inquiry',      '📋', 'inquiry',  1, 1, 10),
  (2, 1,    'HPP',       'HPP / Costing',        '💹', 'inquiry/hpp', 4, 1, 11),   -- sub Inquiry, min GM
  (3, 1,    'PROOF',     'Proof',                '🎨', 'inquiry/proof', 1, 0, 12), -- sub Inquiry
  (4, NULL, 'MASTER',    'Master Data',          '🧱', 'master',   2, 0, 20),
  (5, NULL, 'PRODUKSI',  'Produksi',             '🏭', 'produksi', 1, 1, 30),
  (6, NULL, 'FINANCE',   'Finance & Accounting', '💰', 'finance',  3, 1, 40);

-- Group + level manajemen
INSERT INTO `sec_group` (`id_group`,`group_code`,`group_name`,`mgmt_level`) VALUES
  (1, 'SALES_STAFF', 'Staf Sales',       1),
  (2, 'SALES_SPV',   'Supervisor Sales', 2),
  (3, 'DIREKSI',     'Direksi',          5),
  (4, 'PRODUKSI',    'Staf Produksi',    1);

-- Izin: Staf Sales -> Inquiry (input, tak boleh approve/export), Proof (input)
INSERT INTO `sec_group_permission` (`group_id`,`module_id`,`can_access`,`can_input`,`can_approve`,`can_export`) VALUES
  (1, 1, 1, 1, 0, 0),   -- Sales staff: Inquiry akses+input
  (1, 3, 1, 1, 0, 0),   -- Sales staff: Proof akses+input
  -- CATATAN: Sales staff TIDAK diberi baris utk HPP(2) -> HPP tak terlihat (default DENY)
  (3, 1, 1, 1, 1, 1),   -- Direksi: Inquiry akses+input+approve+export
  (3, 2, 1, 0, 1, 1),   -- Direksi: HPP akses+approve+export (mgmt_level 5 >= min 4 OK)
  (4, 5, 1, 1, 0, 0);   -- Produksi: hanya modul Produksi (Inquiry TAK terlihat -> poin #4)

-- ============================================================
-- LOGIKA CEK IZIN (untuk implementasi Laravel middleware)
-- ============================================================
-- User boleh AKSES modul X bila SEMUA benar:
--   1. Ada baris sec_group_permission utk (group user, module X) dgn can_access=1
--   2. group.mgmt_level >= module.min_mgmt_level   (report sensitif)
--   3. is_admin=1 -> bypass semua (superadmin)
-- Menu ditampilkan HANYA modul yg lolos cek di atas (default DENY).
-- can_input=0 -> tampil read-only. can_approve/can_export -> tombol khusus.
-- Multi-group: izin efektif = OR dari semua group (paling permisif menang),
--   TAPI mgmt_level = MAX level dari group-group user.
-- ============================================================
