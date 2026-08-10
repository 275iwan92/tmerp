/*
 Navicat Premium Dump SQL

 Source Server         : 127.0.0.1
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46)
 Source Host           : localhost:3306
 Source Schema         : tm2sys

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46)
 File Encoding         : 65001

 Date: 09/08/2026 02:14:19
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for cache
-- ----------------------------
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache`  (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`) USING BTREE,
  INDEX `cache_expiration_index`(`expiration` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of cache
-- ----------------------------

-- ----------------------------
-- Table structure for cache_locks
-- ----------------------------
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks`  (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`) USING BTREE,
  INDEX `cache_locks_expiration_index`(`expiration` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of cache_locks
-- ----------------------------

-- ----------------------------
-- Table structure for failed_jobs
-- ----------------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `failed_jobs_uuid_unique`(`uuid` ASC) USING BTREE,
  INDEX `failed_jobs_connection_queue_failed_at_index`(`connection` ASC, `queue` ASC, `failed_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of failed_jobs
-- ----------------------------

-- ----------------------------
-- Table structure for job_batches
-- ----------------------------
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches`  (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `cancelled_at` int NULL DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of job_batches
-- ----------------------------

-- ----------------------------
-- Table structure for jobs
-- ----------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED NULL DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `jobs_queue_index`(`queue` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of jobs
-- ----------------------------

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of migrations
-- ----------------------------
INSERT INTO `migrations` VALUES (1, '0001_01_01_000000_create_users_table', 1);
INSERT INTO `migrations` VALUES (2, '0001_01_01_000001_create_cache_table', 1);
INSERT INTO `migrations` VALUES (3, '0001_01_01_000002_create_jobs_table', 1);
INSERT INTO `migrations` VALUES (4, '2026_08_09_000002_add_registration_columns_to_sec_user_table', 2);

-- ----------------------------
-- Table structure for password_reset_tokens
-- ----------------------------
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens`  (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of password_reset_tokens
-- ----------------------------

-- ----------------------------
-- Table structure for sec_group
-- ----------------------------
DROP TABLE IF EXISTS `sec_group`;
CREATE TABLE `sec_group`  (
  `id_group` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode, mis. SALES_STAFF, DIREKSI, PRODUKSI',
  `group_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Deskripsi (ex sec_groups.description ScriptCase)',
  `mgmt_level` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=STAFF 2=SUPERVISOR 3=MANAGER 4=GM 5=DIREKSI. Utk report sensitif',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_group`) USING BTREE,
  UNIQUE INDEX `uq_group_code`(`group_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Grup/role pengguna + level manajemen (1=STAFF .. 5=DIREKSI). Direferensikan sec_user_group & sec_group_permission.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_group
-- ----------------------------
INSERT INTO `sec_group` VALUES (1, 'SALES_STAFF', 'Staf Sales', 1, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);
INSERT INTO `sec_group` VALUES (2, 'SALES_SPV', 'Supervisor Sales', 2, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);
INSERT INTO `sec_group` VALUES (3, 'DIREKSI', 'Direksi', 5, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);
INSERT INTO `sec_group` VALUES (4, 'PRODUKSI', 'Staf Produksi', 1, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);

-- ----------------------------
-- Table structure for sec_group_permission
-- ----------------------------
DROP TABLE IF EXISTS `sec_group_permission`;
CREATE TABLE `sec_group_permission`  (
  `id_permission` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` int UNSIGNED NOT NULL COMMENT 'FK -> sec_group',
  `module_id` int UNSIGNED NOT NULL COMMENT 'FK -> sec_module',
  `can_access` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh buka & lihat (termasuk report yg diizinkan level). 0=modul TAK terlihat',
  `can_input` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh create/edit (readwrite). 0=readonly',
  `can_approve` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh approve (mis. Direksi approve harga)',
  `can_export` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh export data (KRITIKAL: bisa bawa data perusahaan keluar)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_permission`) USING BTREE,
  UNIQUE INDEX `uq_perm_group_module`(`group_id` ASC, `module_id` ASC) USING BTREE,
  INDEX `idx_perm_module`(`module_id` ASC) USING BTREE,
  CONSTRAINT `fk_perm_group` FOREIGN KEY (`group_id`) REFERENCES `sec_group` (`id_group`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perm_module` FOREIGN KEY (`module_id`) REFERENCES `sec_module` (`id_module`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Izin per GRUP per MODUL (sec_module) — flag can_access/can_input/can_approve/can_export. Default DENY: tak ada baris = grup tak boleh akses modul.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_group_permission
-- ----------------------------

-- ----------------------------
-- Table structure for sec_login_attempt
-- ----------------------------
DROP TABLE IF EXISTS `sec_login_attempt`;
CREATE TABLE `sec_login_attempt`  (
  `id_attempt` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL COMMENT '1=berhasil | 0=gagal',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'IPv4/IPv6',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_attempt`) USING BTREE,
  INDEX `idx_attempt_login_time`(`login` ASC, `attempted_at` ASC) USING BTREE,
  INDEX `idx_attempt_success`(`success` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Audit percobaan login (login, success, ip, user_agent). Tabel KANONIK sejak sys_login_attempts dihapus. Diisi SERVER-SIDE tiap percobaan.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_login_attempt
-- ----------------------------
INSERT INTO `sec_login_attempt` VALUES (1, 'admin', 0, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 10:51:07');
INSERT INTO `sec_login_attempt` VALUES (2, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 10:58:44');
INSERT INTO `sec_login_attempt` VALUES (3, 'rina', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 11:16:21');
INSERT INTO `sec_login_attempt` VALUES (4, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 11:30:28');
INSERT INTO `sec_login_attempt` VALUES (5, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 18:24:03');

-- ----------------------------
-- Table structure for sec_module
-- ----------------------------
DROP TABLE IF EXISTS `sec_module`;
CREATE TABLE `sec_module`  (
  `id_module` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'NULL=modul induk | isi=sub-modul (mis. HPP di bawah INQUIRY)',
  `module_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik, mis. INQUIRY, HPP, MASTER',
  `module_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label menu',
  `icon` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kelas ikon (Font Awesome) / emoji',
  `route_prefix` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Prefix route Laravel, mis. inquiry (utk mapping middleware)',
  `min_mgmt_level` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Level manajemen MINIMUM utk akses (1=STAFF..5=DIREKSI). Report sensitif set tinggi',
  `is_reportable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=modul ini punya report (info tampilan)',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 999,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_module`) USING BTREE,
  UNIQUE INDEX `uq_module_code`(`module_code` ASC) USING BTREE,
  INDEX `idx_module_parent`(`parent_id` ASC) USING BTREE,
  CONSTRAINT `fk_module_parent` FOREIGN KEY (`parent_id`) REFERENCES `sec_module` (`id_module`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Modul bisnis = unit pemberian izin. module_code DIBUAT SAMA dengan kode modul sys_app_menu (SALES/PROC/INV/PROD/MDATA/FINACC/SETUP) agar izin & menu sinkron.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_module
-- ----------------------------
INSERT INTO `sec_module` VALUES (1, NULL, 'SALES', 'Sales', 'fa-solid fa-file-invoice-dollar', 'sales', 1, 1, 10, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (2, NULL, 'PROC', 'Procurement', 'fa-solid fa-cart-shopping', 'procurement', 2, 1, 20, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (3, NULL, 'INV', 'Inventory', 'fa-solid fa-boxes-stacked', 'inventory', 1, 1, 30, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (4, NULL, 'PROD', 'Production', 'fa-solid fa-industry', 'production', 1, 1, 40, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (5, NULL, 'MDATA', 'Master Data', 'fa-solid fa-database', 'master', 2, 0, 50, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (6, NULL, 'FINACC', 'Finance & Accounting', 'fa-solid fa-sack-dollar', 'finance', 3, 1, 60, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (9, NULL, 'SETUP', 'Setup', 'fa-solid fa-gear', 'setup', 5, 0, 99, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);

-- ----------------------------
-- Table structure for sec_user
-- ----------------------------
DROP TABLE IF EXISTS `sec_user`;
CREATE TABLE `sec_user`  (
  `id_user` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username login',
  `pswd` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Hash password (bcrypt Laravel)',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `phone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ex active varchar(1) -> tinyint',
  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ex priv_admin: superadmin bypass semua izin',
  `activation_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `mfa` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Secret MFA (bila aktif)',
  `mfa_last_updated` timestamp NULL DEFAULT NULL,
  `pswd_last_updated` timestamp NULL DEFAULT NULL,
  `employee_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis -> emp_employee (tm2mst)',
  `linked_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Jenis entitas tertaut, mis. EMPLOYEE',
  `linked_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'ID entitas master (lintas-DB, dijaga app)',
  `id_type` enum('nik','nrp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `reg_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'customer|supplier|internal — hanya diisi dari self-registration',
  `id_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'NIK (customer/supplier) atau NRP (internal) dari self-registration',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `wa_verified` tinyint(1) NOT NULL DEFAULT 0,
  `wa_verified_at` timestamp NULL DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `theme_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'navy',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_user`) USING BTREE,
  UNIQUE INDEX `uq_user_login`(`login` ASC) USING BTREE,
  INDEX `idx_user_employee`(`employee_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Akun login. login=username unik, pswd=hash. Kolom pendaftaran: id_type/id_number (NIK/NRP), email_verified, wa_verified, approval_status, theme_code. linked_type/linked_id menaut ke customer/supplier. (Catatan: active vs is_active masih dobel — pilih satu; reg_type overlap dgn linked_type.)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_user
-- ----------------------------
INSERT INTO `sec_user` VALUES (1, 'admin', '$2y$12$e9O.vkMXaGUGjIpbGpidaOxpYRayGR6dqNp9mp/YWqkKtdpbYGDE2', 'Budi Santoso', 'budi.santoso@telasih.co.id', '', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'pending', 'navy', '2026-08-08 10:36:57', '2026-08-08 11:13:50', NULL, NULL);
INSERT INTO `sec_user` VALUES (2, 'rina', '$2y$12$djMB4yromEkygFF3WzMaLuW9FNhVCdlGzbyy1Qfep0C.VaOUyTNuW', 'Rina Wijaya', 'rina.wijaya@telasih.co.id', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'pending', 'navy', '2026-08-08 10:36:58', '2026-08-08 10:36:58', NULL, NULL);

-- ----------------------------
-- Table structure for sec_user_group
-- ----------------------------
DROP TABLE IF EXISTS `sec_user_group`;
CREATE TABLE `sec_user_group`  (
  `id_user_group` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> sec_user',
  `group_id` int UNSIGNED NOT NULL COMMENT 'FK -> sec_group',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user_group`) USING BTREE,
  UNIQUE INDEX `uq_user_group`(`user_id` ASC, `group_id` ASC) USING BTREE,
  INDEX `idx_ug_group`(`group_id` ASC) USING BTREE,
  CONSTRAINT `fk_ug_group` FOREIGN KEY (`group_id`) REFERENCES `sec_group` (`id_group`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ug_user` FOREIGN KEY (`user_id`) REFERENCES `sec_user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Keanggotaan user dalam grup (relasi M:N). Izin efektif user = GABUNGAN izin seluruh grupnya.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_user_group
-- ----------------------------
INSERT INTO `sec_user_group` VALUES (1, 2, 1, '2026-08-08 17:36:58');
INSERT INTO `sec_user_group` VALUES (2, 1, 3, '2026-08-08 17:36:58');

-- ----------------------------
-- Table structure for sessions
-- ----------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions`  (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED NULL DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `sessions_user_id_index`(`user_id` ASC) USING BTREE,
  INDEX `sessions_last_activity_index`(`last_activity` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sessions
-- ----------------------------
INSERT INTO `sessions` VALUES ('2q6h8b9eOEqlppRq6g9F7HYF6WJx1cilcQ134m26', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Microsoft Windows 10.0.26200; en-US) PowerShell/7.6.3', 'eyJfdG9rZW4iOiJEeHhnUUVTWDVxeGRySzhUOGc2d3hwYXEzYUpJQ0lnNzNQRk1Hb1luIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL3RtZXJwLnRlc3RcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sInVybCI6eyJpbnRlbmRlZCI6Imh0dHA6XC9cL3RtZXJwLnRlc3RcL2lucXVpcnlcL25ldz9tb2RlPU5FVyJ9fQ==', 1786207690);
INSERT INTO `sessions` VALUES ('avcWHQlwDaXIdVB9eQauJ0NTQ02wCWowgzQ5wghP', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', 'eyJfdG9rZW4iOiJnWVdCR2RmZDhVR2V1TGNIQVNlVGlwRVdvNmZac2xrV3RLTTFaczBQIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL3RtZXJwLnRlc3RcL2lucXVpcnlcL25ldz9tb2RlPU5FVyIsInJvdXRlIjoiaW5xdWlyeS5mb3JtIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1786181465);
INSERT INTO `sessions` VALUES ('BhFUu8W5W0FLacxJpCJdBjhpRuQMMZTJfJEyabxH', NULL, '127.0.0.1', 'curl/8.21.0', 'eyJfdG9rZW4iOiJSaXBhSW1SbHM1aXltVTg2MmR3WXFCZFdoMGZwNVlOcjVUOWpydXFwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL3RtZXJwLnRlc3RcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1786205550);
INSERT INTO `sessions` VALUES ('QnFfyBWeGVr1L7vD5qonTZmJV5rXNnB8CtXAEA3e', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJpNlhoTWJDTUVHNXBLNWV3R21meEU3WXQ0T3c5OUMzVUdrdXltSEV4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL3RtZXJwLnRlc3RcL3NlY3VyaXR5P3RhYj11c2VycyIsInJvdXRlIjoic2VjdXJpdHkuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MX0=', 1786213641);
INSERT INTO `sessions` VALUES ('sRp34fIfGus2Gv29Icwyr4EWi6mqMxuWhcXzAmsI', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJlcTJnbTllVnlBWWh6bGtrVXpKU1JsbTFMaEY3ZkVjdEE4M3lxN09VIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvdG1lcnAudGVzdFwvc2VjdXJpdHkiLCJyb3V0ZSI6InNlY3VyaXR5LmluZGV4In0sInVybCI6W10sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==', 1786188740);

-- ----------------------------
-- Table structure for sys_app_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_app_menu`;
CREATE TABLE `sys_app_menu`  (
  `id` int NOT NULL,
  `parent_id` int NULL DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trans_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `menu_type` enum('module','group','item') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'item',
  `object_type` enum('form','grid','report','dashboard','link') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `target` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `target_kind` enum('app','php','html','url') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'app',
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `required_object` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `code`(`code` ASC) USING BTREE,
  INDEX `idx_parent`(`parent_id` ASC, `sort_order` ASC) USING BTREE,
  CONSTRAINT `sys_app_menu_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `sys_app_menu` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menu dinamis (mega menu / sidebar / command palette) — LAYER TAMPILAN. Hierarki module->group->item; target+target_kind=eksekusi; required_object -> sec_module.module_code (kaitan hak akses).' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_app_menu
-- ----------------------------
INSERT INTO `sys_app_menu` VALUES (1, NULL, 'SALES', 'Sales', NULL, 'fa-solid fa-file-invoice-dollar', 'module', NULL, NULL, 'app', 10, 1, 'SALES', '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (2, NULL, 'PROC', 'Procurement', NULL, 'fa-solid fa-cart-shopping', 'module', NULL, NULL, 'app', 20, 1, 'PROC', '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (3, NULL, 'INV', 'Inventory', NULL, 'fa-solid fa-boxes-stacked', 'module', NULL, NULL, 'app', 30, 1, 'INV', '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (4, NULL, 'PROD', 'Production', NULL, 'fa-solid fa-industry', 'module', NULL, NULL, 'app', 40, 1, 'PROD', '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (5, NULL, 'MDATA', 'Master Data', NULL, 'fa-solid fa-database', 'module', NULL, NULL, 'app', 50, 1, 'MDATA', '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (6, NULL, 'FINACC', 'Finance & Accounting', NULL, 'fa-solid fa-sack-dollar', 'module', NULL, NULL, 'app', 60, 1, 'FINACC', '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (9, NULL, 'SETUP', 'Setup', NULL, 'fa-solid fa-gear', 'module', NULL, NULL, 'app', 99, 1, 'SETUP', '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (110, 1, 'SALES_TRX', 'Transaksi', NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (111, 110, 'SALES_INQ', 'Inquiry Product', NULL, NULL, 'item', 'form', 'form_inquiry.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (112, 110, 'SALES_COST', 'Product Costing', NULL, NULL, 'item', 'form', 'form_costing.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (113, 110, 'SALES_QUOT', 'Quotation', NULL, NULL, 'item', 'form', 'form_quotation.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (114, 110, 'SALES_SO', 'Customer Order', NULL, NULL, 'item', 'form', 'form_customer_order.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (115, 110, 'SALES_PLAN', 'Plan Delivery', NULL, NULL, 'item', 'form', 'form_plan_delivery.php', 'app', 50, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (116, 110, 'SALES_DO', 'Delivery Order', NULL, NULL, 'item', 'form', 'form_delivery_order.php', 'app', 60, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (180, 1, 'SALES_MON', 'Monitoring & Laporan', NULL, NULL, 'group', NULL, NULL, 'app', 80, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (181, 180, 'SALES_GRID_SO', 'Daftar Sales Order', NULL, NULL, 'item', 'grid', 'grid_so.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (182, 180, 'SALES_MON_ORD', 'Monitoring Order', NULL, NULL, 'item', 'grid', 'grid_monitoring_order.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (183, 180, 'SALES_RPT', 'Laporan Penjualan', NULL, NULL, 'item', 'report', 'rpt_sales.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (210, 2, 'PROC_TRX', 'Transaksi', NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (211, 210, 'PROC_PR', 'Purchase Requisition (PR)', NULL, NULL, 'item', 'form', 'form_pr.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (212, 210, 'PROC_PQ', 'Purchase Quotation', NULL, NULL, 'item', 'form', 'form_purchase_quotation.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (213, 210, 'PROC_PO', 'Purchase Order', NULL, NULL, 'item', 'form', 'form_po.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (214, 210, 'PROC_RCV', 'Purchase Receive', NULL, NULL, 'item', 'form', 'form_purchase_receive.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (215, 210, 'PROC_RET', 'Purchase Return', NULL, NULL, 'item', 'form', 'form_purchase_return.php', 'app', 50, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (280, 2, 'PROC_MON', 'Monitoring', NULL, NULL, 'group', NULL, NULL, 'app', 80, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (281, 280, 'PROC_MON_PR', 'Monitoring PR', NULL, NULL, 'item', 'grid', 'grid_monitoring_pr.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (282, 280, 'PROC_MON_PO', 'Monitoring PO', NULL, NULL, 'item', 'grid', 'grid_monitoring_po.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (310, 3, 'INV_TRX', 'Transaksi', NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (311, 310, 'INV_MOVE', 'Mutasi / Transfer Stok', NULL, NULL, 'item', 'form', 'form_stock_move.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (312, 310, 'INV_ADJ', 'Penyesuaian Stok (Opname)', NULL, NULL, 'item', 'form', 'form_stock_adj.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (380, 3, 'INV_RPT', 'Stok & Laporan', NULL, NULL, 'group', NULL, NULL, 'app', 80, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (381, 380, 'INV_RAW', 'Stok Bahan Baku', NULL, NULL, 'item', 'grid', 'grid_stock_raw.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (382, 380, 'INV_FG', 'Stok Barang Jadi', NULL, NULL, 'item', 'grid', 'grid_stock_fg.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (383, 380, 'INV_CARD', 'Kartu Stok', NULL, NULL, 'item', 'report', 'rpt_stock_card.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (384, 380, 'INV_HIST', 'Riwayat Mutasi', NULL, NULL, 'item', 'grid', 'grid_stock_history.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (410, 4, 'PROD_MASTER', 'Master', NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (411, 410, 'PROD_CARD', 'Master Card', NULL, NULL, 'item', 'form', 'form_master_card.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (412, 410, 'PROD_LAYOUT', 'Layout', NULL, NULL, 'item', 'form', 'form_layout.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (420, 4, 'PROD_PLAN', 'Planning', NULL, NULL, 'group', NULL, NULL, 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (421, 420, 'PROD_MPS', 'MPS (Master Production Schedule)', NULL, NULL, 'item', 'form', 'form_mps.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (422, 420, 'PROD_MRP', 'MRP (Material Requirement Planning)', NULL, NULL, 'item', 'form', 'form_mrp.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (430, 4, 'PROD_MTN', 'Maintenance', NULL, NULL, 'group', NULL, NULL, 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (431, 430, 'PROD_MTN_SCH', 'Schedule Maintenance', NULL, NULL, 'item', 'form', 'form_maintenance_schedule.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (432, 430, 'PROD_MTN_PREV', 'Preventive Maintenance', NULL, NULL, 'item', 'form', 'form_maintenance_preventive.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (440, 4, 'PROD_OUT', 'Output', NULL, NULL, 'group', NULL, NULL, 'app', 40, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (441, 440, 'PROD_OUTPUT', 'Production Output', NULL, NULL, 'item', 'form', 'form_production_output.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (442, 440, 'PROD_MON_WO', 'Monitoring Work Order', NULL, NULL, 'item', 'grid', 'grid_monitoring_wo.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (511, 5, 'MD_CUST', 'Master Customer', NULL, NULL, 'item', 'form', 'form_customer.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (512, 5, 'MD_SUPP', 'Master Supplier', NULL, NULL, 'item', 'form', 'form_supplier.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (513, 5, 'MD_PROD', 'Master Produk', NULL, NULL, 'item', 'form', 'form_product.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (514, 5, 'MD_RAW', 'Master Bahan Baku', NULL, NULL, 'item', 'form', 'form_material.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (515, 5, 'MD_UOM', 'Master Satuan (UoM)', NULL, NULL, 'item', 'form', 'form_uom.php', 'app', 50, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (516, 5, 'MD_WH', 'Master Gudang', NULL, NULL, 'item', 'form', 'form_warehouse.php', 'app', 60, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (610, 6, 'FIN', 'Finance', NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (611, 610, 'FIN_CASH', 'Kasir (Kas/Bank)', NULL, NULL, 'item', 'form', 'form_cashier.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (612, 610, 'FIN_AR', 'Piutang (AR)', NULL, NULL, 'item', 'form', 'form_ar.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (613, 610, 'FIN_AP', 'Hutang (AP)', NULL, NULL, 'item', 'form', 'form_ap.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (620, 6, 'ACC', 'Accounting', NULL, NULL, 'group', NULL, NULL, 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (621, 620, 'ACC_JRN', 'Jurnal', NULL, NULL, 'item', 'form', 'form_journal.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (622, 620, 'ACC_GL', 'Buku Besar', NULL, NULL, 'item', 'grid', 'grid_gl.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (623, 620, 'ACC_BS', 'Neraca', NULL, NULL, 'item', 'report', 'rpt_balance_sheet.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (624, 620, 'ACC_PL', 'Laba Rugi', NULL, NULL, 'item', 'report', 'rpt_income_stmt.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (911, 9, 'SET_USER', 'Pengguna', NULL, NULL, 'item', 'grid', 'security.php?tab=users', 'app', 10, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (912, 9, 'SET_GROUP', 'Grup', NULL, NULL, 'item', 'grid', 'security.php?tab=groups', 'app', 20, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (913, 9, 'SET_PERM', 'Hak Akses Grup', NULL, NULL, 'item', 'grid', 'security.php?tab=perms', 'app', 30, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (914, 9, 'SET_LANG', 'Bahasa', NULL, NULL, 'item', 'form', 'security.php?tab=lang', 'app', 40, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (915, 9, 'SET_THEME', 'Tema', NULL, NULL, 'item', 'form', 'security.php?tab=theme', 'app', 50, 1, NULL, '2026-08-08 17:23:31');
INSERT INTO `sys_app_menu` VALUES (916, 9, 'SET_MENU', 'Susunan Menu', NULL, NULL, 'item', 'grid', 'grid_app_menu.php', 'app', 60, 1, NULL, '2026-08-08 17:23:31');

-- ----------------------------
-- Table structure for sys_chat_conversations
-- ----------------------------
DROP TABLE IF EXISTS `sys_chat_conversations`;
CREATE TABLE `sys_chat_conversations`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `is_group` tinyint(1) NULL DEFAULT 0,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header percakapan chat antar pengguna. is_group=0 (1:1) / 1 (grup); title=nama grup bila grup.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_chat_conversations
-- ----------------------------

-- ----------------------------
-- Table structure for sys_chat_messages
-- ----------------------------
DROP TABLE IF EXISTS `sys_chat_messages`;
CREATE TABLE `sys_chat_messages`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint NOT NULL,
  `sender` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_conv_time`(`conversation_id` ASC, `sent_at` ASC) USING BTREE,
  CONSTRAINT `sys_chat_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `sys_chat_conversations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Pesan chat. conversation_id -> sys_chat_conversations; sender = sec_user.login.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_chat_messages
-- ----------------------------

-- ----------------------------
-- Table structure for sys_chat_participants
-- ----------------------------
DROP TABLE IF EXISTS `sys_chat_participants`;
CREATE TABLE `sys_chat_participants`  (
  `conversation_id` bigint NOT NULL,
  `user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`conversation_id`, `user_id`) USING BTREE,
  CONSTRAINT `sys_chat_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `sys_chat_conversations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Peserta tiap percakapan (PK gabungan conversation_id + user_id); last_read_at utk menghitung pesan belum dibaca. user_id = sec_user.login.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_chat_participants
-- ----------------------------

-- ----------------------------
-- Table structure for sys_languages
-- ----------------------------
DROP TABLE IF EXISTS `sys_languages`;
CREATE TABLE `sys_languages`  (
  `code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NULL DEFAULT 0,
  PRIMARY KEY (`code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Daftar bahasa aktif untuk i18n. PK=code (id/en); is_default=bahasa awal. Tabel induk dari sys_translations.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_languages
-- ----------------------------
INSERT INTO `sys_languages` VALUES ('en', 'English', 0);
INSERT INTO `sys_languages` VALUES ('id', 'Bahasa Indonesia', 1);

-- ----------------------------
-- Table structure for sys_notifications
-- ----------------------------
DROP TABLE IF EXISTS `sys_notifications`;
CREATE TABLE `sys_notifications`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `type` enum('info','success','warning','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'info',
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_read` tinyint(1) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_unread`(`user_id` ASC, `is_read` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Notifikasi (lonceng/user menu). user_id=login penerima, NULL=broadcast semua user; link=tujuan saat diklik; is_read=status baca.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_notifications
-- ----------------------------

-- ----------------------------
-- Table structure for sys_quotes
-- ----------------------------
DROP TABLE IF EXISTS `sys_quotes`;
CREATE TABLE `sys_quotes`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `quote_text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Kata-kata penyemangat di halaman utama & login. Ambil acak: WHERE is_active=1 ORDER BY RAND() LIMIT 1. is_active=nonaktif tanpa hapus.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_quotes
-- ----------------------------
INSERT INTO `sys_quotes` VALUES (1, 'Cara memulai adalah berhenti bicara dan mulai melakukan.', 'Walt Disney', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (2, 'Kesuksesan adalah hasil dari persiapan, kerja keras, dan belajar dari kegagalan.', 'Colin Powell', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (3, 'Disiplin adalah jembatan antara tujuan dan pencapaian.', 'Jim Rohn', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (4, 'Kerja keras mengalahkan bakat ketika bakat tidak bekerja keras.', 'Tim Notke', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (5, 'Sedikit demi sedikit, lama-lama menjadi bukit.', 'Peribahasa', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (6, 'Fokus pada progres, bukan kesempurnaan.', 'Anonim', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (7, 'Jangan menunggu waktu yang tepat. Mulailah, dan jadikan waktumu tepat.', 'Anonim', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (8, 'Hari ini harus lebih baik daripada kemarin.', 'Anonim', 1, '2026-08-08 17:24:02');

-- ----------------------------
-- Table structure for sys_setting
-- ----------------------------
DROP TABLE IF EXISTS `sys_setting`;
CREATE TABLE `sys_setting`  (
  `setting_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`setting_key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Setting umum aplikasi (key-value): active_theme, default_language, app_version, dll. Dipakai sebagai fallback level-aplikasi (mis. tema default bila user belum memilih).' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_setting
-- ----------------------------
INSERT INTO `sys_setting` VALUES ('active_theme', 'navy');
INSERT INTO `sys_setting` VALUES ('app_version', '1.0');
INSERT INTO `sys_setting` VALUES ('default_language', 'id');

-- ----------------------------
-- Table structure for sys_support_replies
-- ----------------------------
DROP TABLE IF EXISTS `sys_support_replies`;
CREATE TABLE `sys_support_replies`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint NOT NULL,
  `author` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `ticket_id`(`ticket_id` ASC) USING BTREE,
  CONSTRAINT `sys_support_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `sys_support_tickets` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Thread balasan tiket support. ticket_id -> sys_support_tickets; author = sec_user.login.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_support_replies
-- ----------------------------

-- ----------------------------
-- Table structure for sys_support_tickets
-- ----------------------------
DROP TABLE IF EXISTS `sys_support_tickets`;
CREATE TABLE `sys_support_tickets`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `ticket_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `reporter` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('bug','feature_request','question') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'medium',
  `subject` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `app_version` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `browser_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `attachment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `status` enum('new','reviewing','in_progress','done','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'new',
  `assigned_to` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `ticket_no`(`ticket_no` ASC) USING BTREE,
  INDEX `idx_status`(`status` ASC) USING BTREE,
  INDEX `idx_reporter`(`reporter` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tiket dukungan dari user ke tim pembuat aplikasi. type=bug/feature_request/question; status new..done/rejected; menu_code/app_version/browser_info = konteks otomatis utk reproduksi.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_support_tickets
-- ----------------------------

-- ----------------------------
-- Table structure for sys_theme
-- ----------------------------
DROP TABLE IF EXISTS `sys_theme`;
CREATE TABLE `sys_theme`  (
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `primary_color` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sidebar_color` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Preset tema tampilan (code, primary_color, sidebar_color, is_default). Tema aktif per-user disimpan di sec_user.theme_code; default aplikasi di sys_setting.active_theme.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_theme
-- ----------------------------
INSERT INTO `sys_theme` VALUES ('emerald', 'Emerald', '#1f7a4d', '#123a26', 0, 1, 50);
INSERT INTO `sys_theme` VALUES ('indigo', 'Indigo', '#3b3a8f', '#26265a', 0, 1, 60);
INSERT INTO `sys_theme` VALUES ('navy', 'Navy (default)', '#2f6fed', '#1f2742', 1, 1, 10);
INSERT INTO `sys_theme` VALUES ('plum', 'Plum', '#5b3a67', '#3a2743', 0, 1, 40);
INSERT INTO `sys_theme` VALUES ('slate', 'Slate', '#3f4753', '#2b2f3a', 0, 1, 30);
INSERT INTO `sys_theme` VALUES ('teal', 'Teal', '#1f6f6b', '#173a38', 0, 1, 20);

-- ----------------------------
-- Table structure for sys_translations
-- ----------------------------
DROP TABLE IF EXISTS `sys_translations`;
CREATE TABLE `sys_translations`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `lang_code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trans_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trans_value` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_lang_key`(`lang_code` ASC, `trans_key` ASC) USING BTREE,
  CONSTRAINT `sys_translations_ibfk_1` FOREIGN KEY (`lang_code`) REFERENCES `sys_languages` (`code`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 33 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Teks UI per bahasa. lang_code -> sys_languages.code; trans_key=kunci teks (unik per bahasa). Tambah bahasa = tambah baris per trans_key.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_translations
-- ----------------------------
INSERT INTO `sys_translations` VALUES (1, 'id', 'brand_tagline', 'Sistem ERP terintegrasi.');
INSERT INTO `sys_translations` VALUES (2, 'en', 'brand_tagline', 'Integrated ERP system.');
INSERT INTO `sys_translations` VALUES (3, 'id', 'login_heading', 'Masuk');
INSERT INTO `sys_translations` VALUES (4, 'en', 'login_heading', 'Sign in');
INSERT INTO `sys_translations` VALUES (5, 'id', 'login_sub', 'Gunakan akun terdaftar untuk masuk.');
INSERT INTO `sys_translations` VALUES (6, 'en', 'login_sub', 'Use your registered account to sign in.');
INSERT INTO `sys_translations` VALUES (7, 'id', 'label_username', 'Username atau email');
INSERT INTO `sys_translations` VALUES (8, 'en', 'label_username', 'Username or email');
INSERT INTO `sys_translations` VALUES (9, 'id', 'label_password', 'Password');
INSERT INTO `sys_translations` VALUES (10, 'en', 'label_password', 'Password');
INSERT INTO `sys_translations` VALUES (11, 'id', 'remember', 'Ingat saya');
INSERT INTO `sys_translations` VALUES (12, 'en', 'remember', 'Remember me');
INSERT INTO `sys_translations` VALUES (13, 'id', 'forgot', 'Lupa password?');
INSERT INTO `sys_translations` VALUES (14, 'en', 'forgot', 'Forgot password?');
INSERT INTO `sys_translations` VALUES (15, 'id', 'btn_login', 'Masuk');
INSERT INTO `sys_translations` VALUES (16, 'en', 'btn_login', 'Sign in');
INSERT INTO `sys_translations` VALUES (17, 'id', 'err_empty', 'Isi username dan password.');
INSERT INTO `sys_translations` VALUES (18, 'en', 'err_empty', 'Enter your username and password.');
INSERT INTO `sys_translations` VALUES (19, 'id', 'err_wrong', 'Username atau password salah.');
INSERT INTO `sys_translations` VALUES (20, 'en', 'err_wrong', 'Incorrect username or password.');
INSERT INTO `sys_translations` VALUES (21, 'id', 'fp_heading', 'Lupa password');
INSERT INTO `sys_translations` VALUES (22, 'en', 'fp_heading', 'Forgot password');
INSERT INTO `sys_translations` VALUES (23, 'id', 'fp_sub', 'Masukkan email akun Anda. Kami kirim tautan untuk atur ulang password.');
INSERT INTO `sys_translations` VALUES (24, 'en', 'fp_sub', 'Enter your account email. We will send a reset link.');
INSERT INTO `sys_translations` VALUES (25, 'id', 'label_email', 'Email');
INSERT INTO `sys_translations` VALUES (26, 'en', 'label_email', 'Email');
INSERT INTO `sys_translations` VALUES (27, 'id', 'btn_send', 'Kirim tautan reset');
INSERT INTO `sys_translations` VALUES (28, 'en', 'btn_send', 'Send reset link');
INSERT INTO `sys_translations` VALUES (29, 'id', 'back_login', 'Kembali ke halaman masuk');
INSERT INTO `sys_translations` VALUES (30, 'en', 'back_login', 'Back to sign in');
INSERT INTO `sys_translations` VALUES (31, 'id', 'fp_done', 'Jika email terdaftar, tautan reset sudah dikirim.');
INSERT INTO `sys_translations` VALUES (32, 'en', 'fp_done', 'If the email is registered, a reset link has been sent.');

-- ----------------------------
-- Table structure for sys_user_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_user_menu`;
CREATE TABLE `sys_user_menu`  (
  `id_umenu` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
  `section` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Judul kelompok di dropdown (mis. \"Pesan & info\", \"Akun\"). Kosong/NULL = tanpa header (mis. tombol Keluar).',
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik stabil item, dipakai kode aplikasi (mis. UM_CHAT).',
  `label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks yang tampil di menu.',
  `icon` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kelas ikon Font Awesome (mis. fa-solid fa-comment-dots).',
  `action_type` enum('drawer','modal','menu','url','lang','logout') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'url' COMMENT 'Jenis aksi saat diklik: drawer=buka panel geser | modal=buka pop-up | menu=buka objek di sys_app_menu | url=arahkan ke halaman | lang=ganti bahasa | logout=keluar.',
  `action_target` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sasaran aksi sesuai action_type: id drawer (chatDrawer) | id modal (mEdit) | code sys_app_menu (SET_USER) | URL/route (support.php) | login.html utk logout. NULL utk lang.',
  `badge_source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sumber angka badge di item: \"chat\" (sys_chat_*), \"notif\" (sys_notifications), atau NULL bila tak berbadge.',
  `is_danger` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=tampil merah/menonjol (mis. Keluar aplikasi).',
  `admin_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=hanya tampil untuk admin/berwenang (mis. Pengaturan/Setup). Tetap wajib dicek izin di server.',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 999 COMMENT 'Urutan tampil (kecil di atas).',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=tampil, 0=disembunyikan tanpa dihapus.',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_umenu`) USING BTREE,
  UNIQUE INDEX `uq_umenu_code`(`code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menu profil (dropdown avatar): chat, notifikasi, AI, edit profil, ubah password, setup, bahasa, keluar. Item & urutan diatur dari tabel ini.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_user_menu
-- ----------------------------
INSERT INTO `sys_user_menu` VALUES (1, 'Pesan & info', 'UM_CHAT', 'Chat', 'fa-solid fa-comment-dots', 'drawer', 'chatDrawer', 'chat', 0, 0, 10, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (2, 'Pesan & info', 'UM_NOTIF', 'Notifikasi', 'fa-solid fa-bell', 'drawer', 'notifDrawer', 'notif', 0, 0, 20, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (3, 'Pesan & info', 'UM_AI', 'Asisten AI', 'fa-solid fa-wand-magic-sparkles', 'drawer', 'aiDrawer', NULL, 0, 0, 30, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (4, 'Akun', 'UM_EDIT', 'Edit profil', 'fa-solid fa-user-pen', 'modal', 'mEdit', NULL, 0, 0, 40, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (5, 'Akun', 'UM_PASS', 'Ubah password', 'fa-solid fa-key', 'modal', 'mPass', NULL, 0, 0, 50, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (6, 'Akun', 'UM_SETUP', 'Pengaturan (Setup)', 'fa-solid fa-gear', 'menu', 'SET_USER', NULL, 0, 1, 60, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (7, 'Akun', 'UM_ACTIVITY', 'Aktivitas saya', 'fa-solid fa-clock-rotate-left', 'url', 'aktivitas.php', NULL, 0, 0, 70, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (8, 'Akun', 'UM_HELP', 'Bantuan & dukungan', 'fa-solid fa-circle-question', 'url', 'support.php', NULL, 0, 0, 80, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (9, 'Akun', 'UM_LANG', 'Bahasa', 'fa-solid fa-globe', 'lang', NULL, NULL, 0, 0, 90, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');
INSERT INTO `sys_user_menu` VALUES (10, NULL, 'UM_LOGOUT', 'Keluar aplikasi', 'fa-solid fa-right-from-bracket', 'logout', 'login.html', NULL, 1, 0, 100, 1, '2026-08-09 02:13:53', '2026-08-09 02:13:53');

SET FOREIGN_KEY_CHECKS = 1;
