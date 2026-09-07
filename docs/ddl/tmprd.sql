/*
 Navicat Premium Dump SQL

 Source Server         : TM 188
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46-0ubuntu0.24.04.4)
 Source Host           : 103.79.247.146:23306
 Source Schema         : tmprd

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46-0ubuntu0.24.04.4)
 File Encoding         : 65001

 Date: 07/09/2026 08:02:30
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for ga_run_batch
-- ----------------------------
DROP TABLE IF EXISTS `ga_run_batch`;
CREATE TABLE `ga_run_batch`  (
  `ga_batch_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format: SIM-YYYYMMDD-NNN, unik per run',
  `scenario_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SCN-1 / SCN-2A / SCN-2B / SCN-3',
  `run_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Username PPIC yang trigger',
  `run_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fitness_score` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Skor GA terbaik (makin tinggi makin baik)',
  `ga_generations` smallint UNSIGNED NULL DEFAULT NULL COMMENT 'Generasi GA yang dijalankan',
  `total_wo` smallint UNSIGNED NULL DEFAULT NULL COMMENT 'Jumlah WO yang dijadwalkan',
  `total_late_wo` smallint UNSIGNED NULL DEFAULT NULL COMMENT 'Jumlah WO yang masih terlambat',
  `status` enum('DRAFT','CONFIRMED','SUPERSEDED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT=simulasi, CONFIRMED=sudah deploy ke real, SUPERSEDED=diganti run baru',
  `confirmed_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ga_batch_id`) USING BTREE,
  UNIQUE INDEX `uq_batch_code`(`batch_code` ASC) USING BTREE,
  INDEX `idx_scenario_status`(`scenario_id` ASC, `status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header batch simulasi GA Scheduler - 1 baris per run per skenario' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ga_run_batch
-- ----------------------------
INSERT INTO `ga_run_batch` VALUES (1, 'SIM-SCN-1-20260604-032441-753', 'SCN-1', 'ppic', '2026-06-04 08:24:42', 5000.0000, 100, 10, 0, 'SUPERSEDED', NULL, NULL, NULL, '2026-06-04 08:24:42', '2026-06-05 08:16:39');
INSERT INTO `ga_run_batch` VALUES (2, 'SIM-SCN-1-20260605-031639-499', 'SCN-1', 'ppic', '2026-06-05 08:16:39', 5000.0000, 100, 10, 0, 'SUPERSEDED', NULL, NULL, NULL, '2026-06-05 08:16:39', '2026-06-20 08:51:16');
INSERT INTO `ga_run_batch` VALUES (3, 'SIM-SCN-2A-20260608-010422-015', 'SCN-2A', 'ppic', '2026-06-08 06:04:22', 4850.0000, 100, 10, 0, 'SUPERSEDED', NULL, NULL, NULL, '2026-06-08 06:04:22', '2026-06-20 08:53:16');
INSERT INTO `ga_run_batch` VALUES (4, 'SIM-SCN-1-20260620-015116-127', 'SCN-1', 'system', '2026-06-20 08:51:16', 10000.0000, 100, 10, 0, 'DRAFT', NULL, NULL, NULL, '2026-06-20 08:51:16', '2026-06-20 08:51:16');
INSERT INTO `ga_run_batch` VALUES (5, 'SIM-SCN-2A-20260620-015315-619', 'SCN-2A', 'system', '2026-06-20 08:53:16', 9700.0000, 100, 10, 0, 'DRAFT', NULL, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:16');
INSERT INTO `ga_run_batch` VALUES (6, 'SIM-SCN-2B-20260620-015520-445', 'SCN-2B', 'system', '2026-06-20 08:55:21', 9700.0000, 100, 10, 0, 'DRAFT', NULL, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:21');
INSERT INTO `ga_run_batch` VALUES (7, 'SIM-SCN-3-20260620-015651-794', 'SCN-3', 'system', '2026-06-20 08:56:51', 10000.0000, 100, 10, 0, 'DRAFT', NULL, NULL, NULL, '2026-06-20 08:56:51', '2026-06-20 08:56:51');

-- ----------------------------
-- Table structure for ga_schedule
-- ----------------------------
DROP TABLE IF EXISTS `ga_schedule`;
CREATE TABLE `ga_schedule`  (
  `ga_sch_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ga_batch_id` bigint UNSIGNED NOT NULL COMMENT 'FK ke ga_run_batch',
  `wo_id` bigint UNSIGNED NOT NULL COMMENT 'FK ke prd_wo',
  `wopc_id` bigint UNSIGNED NOT NULL COMMENT 'FK ke prd_wo_process (target UPDATE saat confirm)',
  `wo_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Denormalized untuk Gantt Chart',
  `duedate` datetime NULL DEFAULT NULL COMMENT 'Duedate WO, untuk warning di Gantt',
  `is_vvip` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Inherit dari prd_wo.is_vvip',
  `process_seq` tinyint UNSIGNED NOT NULL,
  `process_id` bigint UNSIGNED NULL DEFAULT NULL,
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'NULL jika is_subkon=1',
  `qty_plan` decimal(15, 4) NOT NULL DEFAULT 0.0000,
  `qty_per_hour` decimal(15, 4) NOT NULL DEFAULT 1.0000,
  `duration_min` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Durasi efektif dalam menit',
  `start_plan` datetime NOT NULL,
  `end_plan` datetime NOT NULL,
  `shift_id` bigint UNSIGNED NULL DEFAULT NULL,
  `is_overtime` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=memakai shift lembur',
  `is_sunday` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=jatuh di hari Minggu',
  `is_locked` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=dikunci PPIC via tombol Gantt, GA re-run tidak boleh geser',
  `is_subkon` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `subkon_vendor_id` bigint UNSIGNED NULL DEFAULT NULL,
  `batch_group_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode batch virtual untuk Die Cut identik, misal: VBATCH-14-7',
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ga_sch_id`) USING BTREE,
  INDEX `idx_batch`(`ga_batch_id` ASC) USING BTREE,
  INDEX `idx_wo`(`wo_id` ASC) USING BTREE,
  INDEX `idx_wopc`(`wopc_id` ASC) USING BTREE,
  INDEX `idx_machine_time`(`machine_id` ASC, `start_plan` ASC, `end_plan` ASC) USING BTREE,
  CONSTRAINT `fk_gasch_batch` FOREIGN KEY (`ga_batch_id`) REFERENCES `ga_run_batch` (`ga_batch_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 106 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail jadwal simulasi per proses per WO hasil GA' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ga_schedule
-- ----------------------------
INSERT INTO `ga_schedule` VALUES (1, 1, 37, 104, '15103 0001', '2015-11-16 16:30:00', 0, 1, 0, 12, 50000.0000, 100.0000, 30000, '2015-10-25 20:30:00', '2015-11-15 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:53:31');
INSERT INTO `ga_schedule` VALUES (2, 1, 50, 106, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 2, 1, 1, 1000.0000, 1000.0000, 60, '2026-06-19 15:30:00', '2026-06-19 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (3, 1, 50, 105, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 1, 3, 12, 1000.0000, 500.0000, 120, '2026-06-19 13:30:00', '2026-06-19 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (4, 1, 33, 96, ' 0002', '2026-05-15 16:30:00', 0, 2, 4, 14, 4500000.0000, 120.0000, 2250000, '2022-02-02 03:30:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (5, 1, 33, 95, ' 0002', '2026-05-15 16:30:00', 0, 1, 3, 10, 4500000.0000, 100.0000, 2700000, '2016-12-14 07:18:27', '2022-02-01 07:18:27', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 09:04:43');
INSERT INTO `ga_schedule` VALUES (6, 1, 51, 108, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 2, 4, 14, 500.0000, 300.0000, 100, '2026-06-24 14:50:00', '2026-06-24 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (7, 1, 51, 107, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 1, 3, 12, 500.0000, 500.0000, 60, '2026-06-24 13:50:00', '2026-06-24 14:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (8, 1, 56, 115, '2600411-01', '2026-05-15 16:30:00', 0, 1, 7, 15, 10.0000, 12.0000, 0, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0, 0, 1, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (9, 1, 52, 111, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 3, 4, 14, 800.0000, 400.0000, 120, '2026-06-27 13:30:00', '2026-06-27 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (10, 1, 52, 110, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 2, 1, 1, 800.0000, 800.0000, 60, '2026-06-27 11:00:00', '2026-06-27 12:00:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (11, 1, 52, 109, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 1, 3, 12, 800.0000, 400.0000, 120, '2026-06-27 09:00:00', '2026-06-27 11:00:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (12, 1, 6, 113, '151030001', '2015-11-16 16:30:00', 0, 2, 4, 14, 50000.0000, 120.0000, 25000, '2015-10-29 07:50:00', '2015-11-15 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (13, 1, 6, 112, '151030001', '2015-11-16 16:30:00', 0, 1, 3, 10, 50000.0000, 100.0000, 30000, '2015-10-08 12:50:00', '2015-10-29 07:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (14, 1, 36, 102, '2600412 0001', '2026-05-15 16:30:00', 0, 2, 4, 14, 6500.0000, 120.0000, 3250, '2026-05-12 10:20:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (15, 1, 36, 101, '2600412 0001', '2026-05-15 16:30:00', 0, 1, 3, 10, 6500.0000, 100.0000, 3900, '2026-05-09 17:20:00', '2026-05-12 10:20:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-04 08:24:42', '2026-06-04 08:24:42');
INSERT INTO `ga_schedule` VALUES (16, 2, 37, 104, '15103 0001', '2015-11-16 16:30:00', 0, 1, 0, 0, 50000.0000, 100.0000, 30000, '2015-10-25 20:30:00', '2015-11-15 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (17, 2, 52, 111, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 3, 4, 14, 800.0000, 400.0000, 120, '2026-06-27 13:30:00', '2026-06-27 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (18, 2, 52, 110, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 2, 1, 1, 800.0000, 800.0000, 60, '2026-06-27 11:00:00', '2026-06-27 12:00:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (19, 2, 52, 109, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 1, 3, 12, 800.0000, 400.0000, 120, '2026-06-27 09:00:00', '2026-06-27 11:00:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (20, 2, 51, 108, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 2, 4, 14, 500.0000, 300.0000, 100, '2026-06-24 14:50:00', '2026-06-24 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (21, 2, 51, 107, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 1, 3, 12, 500.0000, 500.0000, 60, '2026-06-24 13:50:00', '2026-06-24 14:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (22, 2, 50, 106, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 2, 1, 1, 1000.0000, 1000.0000, 60, '2026-06-19 15:30:00', '2026-06-19 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (23, 2, 50, 105, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 1, 3, 12, 1000.0000, 500.0000, 120, '2026-06-19 13:30:00', '2026-06-19 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (24, 2, 36, 102, '2600412 0001', '2026-05-15 16:30:00', 0, 2, 4, 14, 6500.0000, 120.0000, 3250, '2026-05-12 10:20:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (25, 2, 36, 101, '2600412 0001', '2026-05-15 16:30:00', 0, 1, 3, 10, 6500.0000, 100.0000, 3900, '2026-05-09 17:20:00', '2026-05-12 10:20:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (26, 2, 33, 96, ' 0002', '2026-05-15 16:30:00', 0, 2, 4, 14, 4500000.0000, 120.0000, 2250000, '2022-02-02 03:30:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (27, 2, 33, 95, ' 0002', '2026-05-15 16:30:00', 0, 1, 3, 10, 4500000.0000, 100.0000, 2700000, '2016-12-15 03:30:00', '2022-02-02 03:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (28, 2, 6, 113, '151030001', '2015-11-16 16:30:00', 0, 2, 4, 14, 50000.0000, 120.0000, 25000, '2015-10-29 07:50:00', '2015-11-15 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (29, 2, 6, 112, '151030001', '2015-11-16 16:30:00', 0, 1, 3, 10, 50000.0000, 100.0000, 30000, '2015-10-08 12:50:00', '2015-10-29 07:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-05 08:16:39', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (30, 2, 56, 115, '2600411-01', '2026-05-15 16:30:00', 0, 1, 7, 15, 10.0000, 12.0000, 0, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0, 0, 1, 0, NULL, NULL, '2026-06-05 08:16:40', '2026-06-05 08:16:40');
INSERT INTO `ga_schedule` VALUES (31, 3, 37, 104, '15103 0001', '2015-11-16 16:30:00', 0, 1, 0, 0, 50000.0000, 100.0000, 30000, '2015-10-25 20:30:00', '2015-11-15 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (32, 3, 36, 102, '2600412 0001', '2026-05-15 16:30:00', 0, 2, 4, 14, 6500.0000, 120.0000, 3250, '2026-05-12 10:20:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (33, 3, 36, 101, '2600412 0001', '2026-05-15 16:30:00', 0, 1, 3, 10, 6500.0000, 100.0000, 3900, '2026-05-09 17:20:00', '2026-05-12 10:20:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (34, 3, 50, 106, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 2, 1, 1, 1000.0000, 1000.0000, 60, '2026-06-19 15:30:00', '2026-06-19 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (35, 3, 50, 105, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 1, 3, 12, 1000.0000, 500.0000, 120, '2026-06-19 13:30:00', '2026-06-19 15:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (36, 3, 33, 96, ' 0002', '2026-05-15 16:30:00', 0, 2, 4, 14, 4500000.0000, 120.0000, 2250000, '2022-02-02 03:30:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (37, 3, 33, 95, ' 0002', '2026-05-15 16:30:00', 0, 1, 3, 10, 4500000.0000, 100.0000, 2700000, '2016-12-15 03:30:00', '2022-02-02 03:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (38, 3, 6, 113, '151030001', '2015-11-16 16:30:00', 0, 2, 4, 14, 50000.0000, 120.0000, 25000, '2015-10-29 07:50:00', '2015-11-15 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (39, 3, 6, 112, '151030001', '2015-11-16 16:30:00', 0, 1, 3, 10, 50000.0000, 100.0000, 30000, '2015-10-08 12:50:00', '2015-10-29 07:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (40, 3, 52, 111, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 3, 4, 14, 800.0000, 400.0000, 120, '2026-06-27 14:30:00', '2026-06-27 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:22', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (41, 3, 52, 110, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 2, 1, 1, 800.0000, 800.0000, 60, '2026-06-27 13:30:00', '2026-06-27 14:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:23', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (42, 3, 52, 109, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 1, 3, 12, 800.0000, 400.0000, 120, '2026-06-27 10:00:00', '2026-06-27 12:00:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:23', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (43, 3, 56, 115, '2600411-01', '2026-05-15 16:30:00', 0, 1, 7, 15, 10.0000, 12.0000, 0, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0, 0, 1, 0, NULL, NULL, '2026-06-08 06:04:23', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (44, 3, 51, 108, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 2, 4, 14, 500.0000, 300.0000, 100, '2026-06-24 14:50:00', '2026-06-24 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:23', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (45, 3, 51, 107, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 1, 3, 12, 500.0000, 500.0000, 60, '2026-06-24 13:50:00', '2026-06-24 14:50:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-08 06:04:23', '2026-06-08 06:04:23');
INSERT INTO `ga_schedule` VALUES (46, 4, 56, 115, '2600411-01', '2026-05-15 16:30:00', 0, 1, 7, 15, 10.0000, 12.0000, 0, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0, 0, 1, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (47, 4, 6, 113, '151030001', '2015-11-16 16:30:00', 0, 2, 4, 14, 50000.0000, 120.0000, 25000, '2015-09-14 14:50:00', '2015-11-14 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (48, 4, 6, 112, '151030001', '2015-11-16 16:30:00', 0, 1, 3, 10, 50000.0000, 100.0000, 30000, '2015-07-01 15:50:00', '2015-09-14 14:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (49, 4, 51, 108, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 2, 4, 14, 500.0000, 300.0000, 100, '2026-06-24 14:50:00', '2026-06-24 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (50, 4, 51, 107, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 1, 3, 12, 500.0000, 500.0000, 60, '2026-06-24 13:50:00', '2026-06-24 14:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (51, 4, 37, 104, '15103 0001', '2015-11-16 16:30:00', 0, 1, 0, NULL, 50000.0000, 100.0000, 30000, '2015-11-15 16:30:00', '2015-11-15 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-MACHINE', '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (52, 4, 36, 102, '2600412 0001', '2026-05-15 16:30:00', 0, 2, 4, 14, 6500.0000, 120.0000, 3250, '2026-05-07 08:20:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (53, 4, 36, 101, '2600412 0001', '2026-05-15 16:30:00', 0, 1, 3, 10, 6500.0000, 100.0000, 3900, '2026-04-27 15:20:00', '2026-05-07 08:20:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (54, 4, 52, 111, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 3, 4, 14, 800.0000, 400.0000, 120, '2026-06-27 13:30:00', '2026-06-27 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (55, 4, 52, 110, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 2, 1, 1, 800.0000, 800.0000, 60, '2026-06-27 11:30:00', '2026-06-27 13:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (56, 4, 52, 109, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 1, 3, 12, 800.0000, 400.0000, 120, '2026-06-27 09:30:00', '2026-06-27 11:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (57, 4, 33, 96, ' 0002', '2026-05-15 16:30:00', 0, 2, 4, 14, 4500000.0000, 120.0000, 2250000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (58, 4, 33, 95, ' 0002', '2026-05-15 16:30:00', 0, 1, 3, 10, 4500000.0000, 100.0000, 2700000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (59, 4, 50, 106, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 2, 1, 1, 1000.0000, 1000.0000, 60, '2026-06-19 15:30:00', '2026-06-19 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (60, 4, 50, 105, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 1, 3, 12, 1000.0000, 500.0000, 120, '2026-06-19 13:30:00', '2026-06-19 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:51:17', '2026-06-20 08:51:17');
INSERT INTO `ga_schedule` VALUES (61, 5, 51, 108, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 2, 4, 14, 500.0000, 300.0000, 100, '2026-06-24 14:50:00', '2026-06-24 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (62, 5, 51, 107, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 1, 3, 12, 500.0000, 500.0000, 60, '2026-06-24 13:50:00', '2026-06-24 14:50:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (63, 5, 6, 113, '151030001', '2015-11-16 16:30:00', 0, 2, 4, 14, 50000.0000, 120.0000, 25000, '2015-10-19 23:04:36', '2015-11-14 23:59:59', 3, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (64, 5, 6, 112, '151030001', '2015-11-16 16:30:00', 0, 1, 3, 10, 50000.0000, 100.0000, 30000, '2015-09-17 13:34:09', '2015-10-19 23:04:36', 3, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (65, 5, 56, 115, '2600411-01', '2026-05-15 16:30:00', 0, 1, 7, 15, 10.0000, 12.0000, 0, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0, 0, 1, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (66, 5, 37, 104, '15103 0001', '2015-11-16 16:30:00', 0, 1, 0, NULL, 50000.0000, 100.0000, 30000, '2015-11-15 16:30:00', '2015-11-15 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-MACHINE', '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (67, 5, 33, 96, ' 0002', '2026-05-15 16:30:00', 0, 2, 4, 14, 4500000.0000, 120.0000, 2250000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (68, 5, 33, 95, ' 0002', '2026-05-15 16:30:00', 0, 1, 3, 10, 4500000.0000, 100.0000, 2700000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (69, 5, 50, 106, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 2, 1, 1, 1000.0000, 1000.0000, 60, '2026-06-19 15:30:00', '2026-06-19 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (70, 5, 50, 105, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 1, 3, 12, 1000.0000, 500.0000, 120, '2026-06-19 13:30:00', '2026-06-19 15:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (71, 5, 36, 102, '2600412 0001', '2026-05-15 16:30:00', 0, 2, 4, 14, 6500.0000, 120.0000, 3250, '2026-05-11 17:04:57', '2026-05-14 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (72, 5, 36, 101, '2600412 0001', '2026-05-15 16:30:00', 0, 1, 3, 10, 6500.0000, 100.0000, 3900, '2026-05-07 08:04:54', '2026-05-11 17:04:57', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (73, 5, 52, 111, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 3, 4, 14, 800.0000, 400.0000, 120, '2026-06-27 14:30:00', '2026-06-27 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (74, 5, 52, 110, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 2, 1, 1, 800.0000, 800.0000, 60, '2026-06-27 13:30:00', '2026-06-27 14:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (75, 5, 52, 109, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 1, 3, 12, 800.0000, 400.0000, 120, '2026-06-27 10:45:00', '2026-06-27 13:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:53:16', '2026-06-20 08:53:17');
INSERT INTO `ga_schedule` VALUES (76, 6, 36, 102, '2600412 0001', '2026-05-15 16:30:00', 0, 2, 4, 14, 6500.0000, 120.0000, 3250, '2026-05-11 17:04:57', '2026-05-14 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (77, 6, 36, 101, '2600412 0001', '2026-05-15 16:30:00', 0, 1, 3, 10, 6500.0000, 100.0000, 3900, '2026-05-07 08:04:54', '2026-05-11 17:04:57', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (78, 6, 51, 108, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 2, 4, 14, 500.0000, 300.0000, 100, '2026-06-24 14:50:00', '2026-06-24 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (79, 6, 51, 107, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 1, 3, 12, 500.0000, 500.0000, 60, '2026-06-24 13:50:00', '2026-06-24 14:50:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (80, 6, 6, 113, '151030001', '2015-11-16 16:30:00', 0, 2, 4, 14, 50000.0000, 120.0000, 25000, '2015-10-19 23:04:36', '2015-11-14 23:59:59', 3, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (81, 6, 6, 112, '151030001', '2015-11-16 16:30:00', 0, 1, 3, 10, 50000.0000, 100.0000, 30000, '2015-09-17 13:34:09', '2015-10-19 23:04:36', 3, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (82, 6, 50, 106, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 2, 1, 1, 1000.0000, 1000.0000, 60, '2026-06-19 15:30:00', '2026-06-19 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (83, 6, 50, 105, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 1, 3, 12, 1000.0000, 500.0000, 120, '2026-06-19 13:30:00', '2026-06-19 15:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (84, 6, 37, 104, '15103 0001', '2015-11-16 16:30:00', 0, 1, 0, NULL, 50000.0000, 100.0000, 30000, '2015-11-15 16:30:00', '2015-11-15 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-MACHINE', '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (85, 6, 33, 96, ' 0002', '2026-05-15 16:30:00', 0, 2, 4, 14, 4500000.0000, 120.0000, 2250000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (86, 6, 33, 95, ' 0002', '2026-05-15 16:30:00', 0, 1, 3, 10, 4500000.0000, 100.0000, 2700000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (87, 6, 56, 115, '2600411-01', '2026-05-15 16:30:00', 0, 1, 7, 15, 10.0000, 12.0000, 0, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0, 0, 1, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (88, 6, 52, 111, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 3, 4, 14, 800.0000, 400.0000, 120, '2026-06-27 14:30:00', '2026-06-27 16:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:21', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (89, 6, 52, 110, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 2, 1, 1, 800.0000, 800.0000, 60, '2026-06-27 13:30:00', '2026-06-27 14:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:22', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (90, 6, 52, 109, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 1, 3, 12, 800.0000, 400.0000, 120, '2026-06-27 10:45:00', '2026-06-27 13:30:00', 2, 1, 0, 0, 0, NULL, NULL, '2026-06-20 08:55:22', '2026-06-20 08:55:22');
INSERT INTO `ga_schedule` VALUES (91, 7, 52, 111, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 3, 4, 14, 800.0000, 400.0000, 120, '2026-06-27 13:30:00', '2026-06-27 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:51', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (92, 7, 52, 110, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 2, 1, 1, 800.0000, 800.0000, 60, '2026-06-27 11:30:00', '2026-06-27 13:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:51', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (93, 7, 52, 109, 'DUMMY-WO-003', '2026-06-28 16:30:00', 0, 1, 3, 12, 800.0000, 400.0000, 120, '2026-06-27 09:30:00', '2026-06-27 11:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (94, 7, 50, 106, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 2, 1, 1, 1000.0000, 1000.0000, 60, '2026-06-19 15:30:00', '2026-06-19 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (95, 7, 50, 105, 'DUMMY-WO-001', '2026-06-20 16:30:00', 0, 1, 3, 12, 1000.0000, 500.0000, 120, '2026-06-19 13:30:00', '2026-06-19 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (96, 7, 36, 102, '2600412 0001', '2026-05-15 16:30:00', 0, 2, 4, 14, 6500.0000, 120.0000, 3250, '2026-05-07 08:20:00', '2026-05-14 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (97, 7, 36, 101, '2600412 0001', '2026-05-15 16:30:00', 0, 1, 3, 10, 6500.0000, 100.0000, 3900, '2026-04-27 15:20:00', '2026-05-07 08:20:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (98, 7, 51, 108, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 2, 4, 14, 500.0000, 300.0000, 100, '2026-06-24 14:50:00', '2026-06-24 16:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (99, 7, 51, 107, 'DUMMY-WO-002', '2026-06-25 16:30:00', 0, 1, 3, 12, 500.0000, 500.0000, 60, '2026-06-24 13:50:00', '2026-06-24 14:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (100, 7, 56, 115, '2600411-01', '2026-05-15 16:30:00', 0, 1, 7, 15, 10.0000, 12.0000, 0, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0, 0, 1, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (101, 7, 6, 113, '151030001', '2015-11-16 16:30:00', 0, 2, 4, 14, 50000.0000, 120.0000, 25000, '2015-09-14 14:50:00', '2015-11-14 15:30:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (102, 7, 6, 112, '151030001', '2015-11-16 16:30:00', 0, 1, 3, 10, 50000.0000, 100.0000, 30000, '2015-07-01 15:50:00', '2015-09-14 14:50:00', 4, 0, 0, 0, 0, NULL, NULL, '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (103, 7, 33, 96, ' 0002', '2026-05-15 16:30:00', 0, 2, 4, 14, 4500000.0000, 120.0000, 2250000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (104, 7, 33, 95, ' 0002', '2026-05-15 16:30:00', 0, 1, 3, 10, 4500000.0000, 100.0000, 2700000, '2026-05-14 16:30:00', '2026-05-14 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-DURATION', '2026-06-20 08:56:52', '2026-06-20 08:56:52');
INSERT INTO `ga_schedule` VALUES (105, 7, 37, 104, '15103 0001', '2015-11-16 16:30:00', 0, 1, 0, NULL, 50000.0000, 100.0000, 30000, '2015-11-15 16:30:00', '2015-11-15 16:30:00', NULL, 0, 0, 0, 0, NULL, 'INVALID-MACHINE', '2026-06-20 08:56:52', '2026-06-20 08:56:52');

-- ----------------------------
-- Table structure for mnt_breakdown
-- ----------------------------
DROP TABLE IF EXISTS `mnt_breakdown`;
CREATE TABLE `mnt_breakdown`  (
  `breakdown_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Primary key unik breakdown',
  `start_break` datetime NULL DEFAULT NULL COMMENT 'Waktu mulai breakdown',
  `sop_break` datetime NULL DEFAULT NULL COMMENT 'Waktu selesai breakdown',
  `break_note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Catatan penyebab atau detail breakdown',
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_machine (mesin yang mengalami breakdown)',
  PRIMARY KEY (`breakdown_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Data breakdown mesin (start & stop breakdown)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of mnt_breakdown
-- ----------------------------

-- ----------------------------
-- Table structure for mps_common_value
-- ----------------------------
DROP TABLE IF EXISTS `mps_common_value`;
CREATE TABLE `mps_common_value`  (
  `common_process_id` bigint UNSIGNED NOT NULL COMMENT 'Primary key unik parameter umum MPS',
  `process_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_process',
  `machine_id` int NULL DEFAULT NULL COMMENT 'Relasi ke m_machine',
  `common_value` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama nilai umum (misal: cfg_ink, die_cut, dll)',
  `field_common` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama field yang digunakan sebagai referensi umum',
  `table_common` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama table sumber referensi umum',
  `create_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu pembuatan data',
  `update_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu update terakhir data',
  `create_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang membuat data',
  `update_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang mengupdate data',
  PRIMARY KEY (`common_process_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Parameter umum untuk proses dan mesin dalam MPS' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of mps_common_value
-- ----------------------------
INSERT INTO `mps_common_value` VALUES (1, 1, 1, 'cfg_ink', 'cfg_ink', 'v1_mst_product', NULL, '2025-11-08 11:47:49', '', '');
INSERT INTO `mps_common_value` VALUES (2, 7, 20, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:11:10', '', '');
INSERT INTO `mps_common_value` VALUES (3, 20, 20, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:12:20', '', '');
INSERT INTO `mps_common_value` VALUES (4, 5, 18, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:12:35', '', '');
INSERT INTO `mps_common_value` VALUES (5, 4, 17, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:17:46', '', '');
INSERT INTO `mps_common_value` VALUES (6, 4, 14, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:18:07', '', '');
INSERT INTO `mps_common_value` VALUES (7, 4, 15, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:18:27', '', '');
INSERT INTO `mps_common_value` VALUES (8, 4, 16, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:18:57', '', '');
INSERT INTO `mps_common_value` VALUES (9, 2, 9, 'die_cut', 'diecut_id', 'prd_wo_process', NULL, '2025-11-08 12:21:37', '', '');

-- ----------------------------
-- Table structure for mps_plan
-- ----------------------------
DROP TABLE IF EXISTS `mps_plan`;
CREATE TABLE `mps_plan`  (
  `plan_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key unik rencana MPS',
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama plan MPS',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Deskripsi atau catatan tambahan plan',
  `is_applied` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag apakah plan sudah diaplikasikan',
  `start_date` datetime NULL DEFAULT NULL COMMENT 'Tanggal mulai rencana produksi',
  `stat_confirm` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Status konfirmasi rencana MPS',
  `stat_cancel` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Status pembatalan rencana MPS',
  `stat_closed` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Status closed / selesai',
  `create_log` timestamp NULL DEFAULT NULL COMMENT 'Tanggal dan waktu pembuatan data',
  `update_log` timestamp NULL DEFAULT NULL COMMENT 'Tanggal dan waktu update terakhir',
  `create_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang membuat data',
  `update_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang mengupdate data',
  PRIMARY KEY (`plan_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 102 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header rencana produksi MPS (Master Production Schedule)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of mps_plan
-- ----------------------------
INSERT INTO `mps_plan` VALUES (1, 'Plan for Product 1', 'Plan description for Product 1', 1, '2025-10-07 15:45:56', 0, 0, 0, '2025-10-07 22:45:56', '2025-12-06 16:27:49', NULL, NULL);
INSERT INTO `mps_plan` VALUES (2, 'Testing Artisan Tube Medium', '', 0, '2025-10-19 16:16:20', 0, 0, 0, '2025-10-18 23:16:54', '2025-10-18 23:16:54', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (3, 'llkjklj', '', 0, '2025-10-19 18:35:02', 0, 0, 0, '2025-10-18 07:00:00', '2025-10-19 01:35:55', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (4, 'Testing Axis N100', '', 0, '2025-10-21 16:37:36', 0, 0, 0, '2025-10-20 07:00:00', '2025-10-20 23:41:06', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (5, 'Testing Axis N100 HJGHJGHJ', '', 0, '2025-10-21 18:37:36', 0, 0, 0, '2025-10-20 23:50:34', '2025-10-20 23:50:34', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (6, 'Test 4 item', '', 1, '2025-10-23 14:28:38', 0, 0, 0, '2025-10-22 07:00:00', '2025-12-06 17:30:37', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (7, 'test again', '', 0, '2025-10-25 14:28:38', 0, 0, 0, '2025-10-23 07:00:00', '2025-10-23 21:24:10', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (8, 'test1', '', 0, '2025-10-22 14:28:38', 0, 0, 0, '2025-11-10 16:59:57', '2025-11-10 16:59:57', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (9, 'test gold star', '', 0, '2025-10-20 14:28:38', 0, 0, 0, '2025-11-10 07:00:00', '2025-11-10 18:23:55', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (10, 'TESTING', '', 0, '2025-11-11 11:49:00', 0, 0, 0, '2025-11-10 07:00:00', '2025-11-10 18:49:55', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (11, 'TESTING 123', '', 0, '2025-11-12 11:49:00', 0, 0, 0, '2025-11-10 19:04:37', '2025-11-10 19:04:37', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (12, 'test', '', 0, '2025-11-23 14:10:50', 0, 0, 0, '2025-11-22 07:00:00', '2025-11-22 21:13:04', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (13, 'testing_01', '', 1, '2025-12-09 16:13:02', 0, 0, 0, '2025-12-06 07:00:00', '2025-12-06 16:14:52', 'trial', 'trial');
INSERT INTO `mps_plan` VALUES (100, 'DUMMY PLAN - Python Scheduler Test', 'Plan dummy untuk pengujian endpoint /schedule. Berisi 3 WO dengan routing lengkap.', 0, '2026-06-01 08:00:00', 0, 0, 0, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `mps_plan` VALUES (101, 'testing 11-05', '', 0, NULL, 0, 0, 0, '2026-05-11 20:59:13', '2026-05-11 20:59:13', 'trial', 'trial');

-- ----------------------------
-- Table structure for mps_plan_details
-- ----------------------------
DROP TABLE IF EXISTS `mps_plan_details`;
CREATE TABLE `mps_plan_details`  (
  `plan_detail_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key detail rencana MPS',
  `plan_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke mps_plan (header plan)',
  `wo_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke prd_wo (Work Order yang direncanakan)',
  `is_locked` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Menandakan detail plan dikunci dan tidak bisa diubah',
  `is_applied` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Flag apakah detail plan sudah diaplikasikan',
  `plan_date` datetime NULL DEFAULT NULL COMMENT 'Tanggal plan per item',
  `created_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu pembuatan data',
  `updated_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu update terakhir',
  `sequence_wo` int NULL DEFAULT NULL COMMENT 'Urutan Work Order dalam plan',
  PRIMARY KEY (`plan_detail_id`) USING BTREE,
  INDEX `plan_product_cos_plan_id_foreign`(`plan_id` ASC) USING BTREE,
  INDEX `plan_product_cos_co_product_id_foreign`(`wo_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 24 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail rencana MPS per Work Order' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of mps_plan_details
-- ----------------------------
INSERT INTO `mps_plan_details` VALUES (1, 1, 1, 0, 0, '2025-10-19 17:16:54', '2025-10-07 22:45:56', '2025-10-07 22:45:56', 1);
INSERT INTO `mps_plan_details` VALUES (2, 1, 2, 0, 0, '2025-10-19 18:16:54', '2025-10-07 22:45:56', '2025-10-07 22:45:56', 2);
INSERT INTO `mps_plan_details` VALUES (3, 1, 3, 0, 0, '2025-10-19 20:16:54', '2025-10-07 22:45:56', '2025-10-07 22:45:56', 3);
INSERT INTO `mps_plan_details` VALUES (4, 2, 2, 1, 0, '2025-10-19 16:16:54', '2025-10-18 23:17:41', '2025-10-18 23:17:41', 1);
INSERT INTO `mps_plan_details` VALUES (5, 2, 3, 0, 0, '2025-11-03 18:51:44', '2025-10-18 23:52:28', '2025-10-23 16:59:59', 2);
INSERT INTO `mps_plan_details` VALUES (6, 3, 3, 0, 0, '2025-10-21 18:35:17', '2025-10-19 01:35:24', '2025-10-19 01:35:51', 1);
INSERT INTO `mps_plan_details` VALUES (7, 4, 3, 0, 1, '2025-10-21 16:38:04', '2025-10-20 23:38:27', '2025-10-20 23:38:27', 1);
INSERT INTO `mps_plan_details` VALUES (8, 5, 3, 1, 0, '2025-10-30 16:50:34', '2025-10-20 23:51:30', '2025-10-20 23:51:30', 1);
INSERT INTO `mps_plan_details` VALUES (9, 6, 6, 1, 0, '2025-11-19 14:28:39', '2025-10-22 21:28:43', '2025-10-23 10:55:43', 1);
INSERT INTO `mps_plan_details` VALUES (10, 6, 7, 1, 0, '2025-11-19 14:28:39', '2025-10-22 21:28:47', '2025-10-23 10:55:43', 2);
INSERT INTO `mps_plan_details` VALUES (11, 6, 8, 1, 0, '2025-11-19 14:28:39', '2025-10-22 21:28:51', '2025-10-23 10:55:43', 3);
INSERT INTO `mps_plan_details` VALUES (12, 6, 9, 1, 0, '2025-11-19 14:28:39', '2025-10-22 21:29:28', '2025-10-23 10:55:43', 4);
INSERT INTO `mps_plan_details` VALUES (13, 7, 7, 0, 0, '2025-10-29 14:23:01', '2025-10-23 21:23:24', '2025-10-23 21:23:24', 1);
INSERT INTO `mps_plan_details` VALUES (14, 9, 10, 0, 1, '2025-11-11 11:22:44', '2025-11-10 18:23:19', '2025-11-10 18:23:52', 1);
INSERT INTO `mps_plan_details` VALUES (15, 10, 10, 0, 0, '2025-12-11 11:49:00', '2025-11-10 18:49:31', '2025-11-11 16:38:23', 1);
INSERT INTO `mps_plan_details` VALUES (16, 11, 6, 0, 0, '2025-11-11 12:04:38', '2025-11-10 19:04:55', '2025-11-10 19:05:25', 1);
INSERT INTO `mps_plan_details` VALUES (17, 12, 4, 0, 0, '2026-10-04 14:10:50', '2025-11-22 21:12:21', '2025-11-22 14:13:33', 1);
INSERT INTO `mps_plan_details` VALUES (18, 12, 4, 0, 0, '2026-10-03 07:13:33', '2025-11-22 21:12:36', '2025-11-22 14:13:33', 2);
INSERT INTO `mps_plan_details` VALUES (19, 13, 6, 1, 1, '2025-12-08 16:13:02', '2025-12-06 23:13:29', '2025-12-06 23:13:29', 1);
INSERT INTO `mps_plan_details` VALUES (20, 6, 2, 0, 0, '2025-12-06 17:30:11', '2025-12-07 00:30:34', '2025-12-07 00:30:34', 5);
INSERT INTO `mps_plan_details` VALUES (21, 100, 50, 0, 0, '2026-06-20 23:59:00', '2026-05-09 17:10:40', '2026-05-09 17:10:40', 1);
INSERT INTO `mps_plan_details` VALUES (22, 100, 51, 0, 0, '2026-06-25 23:59:00', '2026-05-09 17:10:40', '2026-05-09 17:10:40', 2);
INSERT INTO `mps_plan_details` VALUES (23, 100, 52, 0, 0, '2026-06-28 23:59:00', '2026-05-09 17:10:40', '2026-05-09 17:10:40', 3);

-- ----------------------------
-- Table structure for mps_schedules
-- ----------------------------
DROP TABLE IF EXISTS `mps_schedules`;
CREATE TABLE `mps_schedules`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key unik jadwal MPS',
  `wo_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke prd_wo (Work Order)',
  `process_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_process (proses produksi)',
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_machine (mesin)',
  `operation_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke operation/aksi mesin (jika digunakan)',
  `previous_schedule_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'ID jadwal sebelumnya (untuk dependency)',
  `process_dependency_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'ID proses yang menjadi dependency',
  `is_start_process` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag proses awal dalam routing',
  `is_final_process` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag proses akhir dalam routing',
  `quantity` bigint NULL DEFAULT NULL COMMENT 'Qty produksi untuk jadwal ini',
  `plan_speed` bigint NULL DEFAULT NULL COMMENT 'Kecepatan produksi per jam/menit',
  `conversion_value` decimal(8, 2) NULL DEFAULT NULL COMMENT 'Nilai konversi uom',
  `plan_duration` int NULL DEFAULT NULL COMMENT 'Durasi proses (menit)',
  `start_time` datetime NULL DEFAULT NULL COMMENT 'Waktu mulai proses',
  `end_time` datetime NULL DEFAULT NULL COMMENT 'Waktu selesai proses',
  `is_completed` tinyint(1) NULL DEFAULT NULL COMMENT 'Status proses selesai',
  `shift_id` int NULL DEFAULT NULL COMMENT 'Shift kerja',
  `is_overtime` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag lembur',
  `adjusted_start` datetime NULL DEFAULT NULL COMMENT 'Waktu mulai setelah penyesuaian',
  `adjusted_end` datetime NULL DEFAULT NULL COMMENT 'Waktu selesai setelah penyesuaian',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu data dibuat',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu update terakhir data',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 85 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Jadwal proses produksi hasil perhitungan MPS' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of mps_schedules
-- ----------------------------
INSERT INTO `mps_schedules` VALUES (57, 7, 3, 12, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 27, '2025-10-01 07:00:00', '2025-10-01 07:27:00', NULL, 24, 0, '2025-10-01 08:00:00', '2025-10-01 08:27:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (58, 7, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 53, '2025-10-01 07:27:00', '2025-10-01 08:20:00', NULL, 3, 0, '2025-10-01 08:27:00', '2025-10-01 09:20:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (59, 7, 7, 20, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 210, '2025-10-01 08:20:00', '2025-10-01 11:50:00', NULL, 59, 0, '2025-10-01 09:20:00', '2025-10-01 12:50:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (60, 7, 5, 18, 18, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 105, '2025-10-01 11:50:00', '2025-10-01 13:35:00', NULL, 45, 0, '2025-10-01 12:50:00', '2025-10-01 14:35:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (61, 7, 4, 14, 14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 175, '2025-10-01 13:35:00', '2025-10-01 16:30:00', NULL, 31, 0, '2025-10-01 14:35:00', '2025-10-01 17:30:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (62, 7, 10, 21, 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 175, '2025-10-01 16:30:00', '2025-10-01 19:25:00', NULL, 66, 0, '2025-10-01 17:30:00', '2025-10-01 20:25:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (63, 7, 2, 9, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 175, '2025-10-01 19:25:00', '2025-10-01 22:20:00', NULL, 10, 0, '2025-10-01 20:25:00', '2025-10-01 23:20:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (64, 7, 11, 21, 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 300, '2025-10-01 22:20:00', '2025-10-02 03:21:00', NULL, 67, 0, '2025-10-01 23:20:00', '2025-10-02 04:21:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (65, 8, 3, 12, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 75, '2025-10-01 07:27:00', '2025-10-01 08:42:00', NULL, 24, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (66, 8, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 113, '2025-10-01 09:42:00', '2025-10-01 11:35:00', NULL, 3, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (67, 8, 6, 19, 19, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 750, '2025-10-01 11:35:00', '2025-10-02 00:06:00', NULL, 53, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (68, 8, 4, 17, 17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 150, '2025-10-02 00:06:00', '2025-10-02 02:36:00', NULL, 39, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (69, 8, 10, 21, 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 450, '2025-10-02 04:21:00', '2025-10-02 11:51:00', NULL, 67, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (70, 8, 2, 10, 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 180, '2025-10-02 11:51:00', '2025-10-02 14:51:00', NULL, 18, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (71, 9, 3, 12, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 450, '2025-10-01 08:42:00', '2025-10-01 16:12:00', NULL, 24, 0, '2025-10-01 09:42:00', '2025-10-01 17:12:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (72, 9, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 450, '2025-10-01 17:12:00', '2025-10-02 00:43:00', NULL, 4, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (73, 9, 20, 20, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3000, '2025-10-02 00:43:00', '2025-10-04 02:45:00', NULL, 62, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (74, 9, 4, 17, 17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 600, '2025-10-04 02:45:00', '2025-10-04 12:45:00', NULL, 41, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (75, 9, 10, 21, 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1350, '2025-10-04 12:45:00', '2025-10-05 11:16:00', NULL, 70, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (76, 9, 2, 10, 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 540, '2025-10-05 11:16:00', '2025-10-05 20:16:00', NULL, 21, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (77, 6, 3, 12, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 15, '2025-10-01 16:12:00', '2025-10-01 16:27:00', NULL, 24, 0, '2025-10-01 17:12:00', '2025-10-01 17:27:00', '2025-12-06 17:30:37', '2025-12-06 19:42:17');
INSERT INTO `mps_schedules` VALUES (78, 6, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30, '2025-10-02 00:43:00', '2025-10-02 01:13:00', NULL, 4, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (79, 6, 7, 20, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 120, '2025-10-04 02:45:00', '2025-10-04 04:45:00', NULL, 62, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (80, 6, 5, 18, 18, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 60, '2025-10-04 04:45:00', '2025-10-04 05:45:00', NULL, 48, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (81, 6, 4, 14, 14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, '2025-10-04 05:45:00', '2025-10-04 07:25:00', NULL, 34, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (82, 6, 10, 21, 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, '2025-10-05 11:16:00', '2025-10-05 12:56:00', NULL, 70, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (83, 6, 2, 9, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, '2025-10-05 12:56:00', '2025-10-05 14:36:00', NULL, 14, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');
INSERT INTO `mps_schedules` VALUES (84, 6, 11, 21, 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 172, '2025-10-05 14:36:00', '2025-10-05 17:28:00', NULL, 70, 0, '2025-10-01 08:27:00', '2025-10-01 09:42:00', '2025-12-06 17:30:37', '2025-12-06 17:30:37');

-- ----------------------------
-- Table structure for mps_simulate
-- ----------------------------
DROP TABLE IF EXISTS `mps_simulate`;
CREATE TABLE `mps_simulate`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key simulasi jadwal',
  `plan_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke mps_plan_details',
  `process_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_process',
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_machine',
  `operation_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke operation mesin',
  `previous_schedule_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'ID jadwal sebelumnya',
  `process_dependency_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi dependency proses',
  `is_start_process` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag proses awal',
  `is_final_process` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag proses akhir',
  `quantity` bigint NULL DEFAULT NULL COMMENT 'Qty yang diproses',
  `plan_speed` bigint NULL DEFAULT NULL COMMENT 'Kecepatan rencana produksi',
  `conversion_value` decimal(8, 2) NULL DEFAULT NULL COMMENT 'Konversi uom',
  `plan_duration` int NULL DEFAULT NULL COMMENT 'Durasi rencana (menit)',
  `start_time` datetime NULL DEFAULT NULL COMMENT 'Waktu mulai simulasi',
  `end_time` datetime NULL DEFAULT NULL COMMENT 'Waktu selesai simulasi',
  `is_completed` tinyint(1) NULL DEFAULT NULL COMMENT 'Status selesai simulasi',
  `shift_id` int NULL DEFAULT NULL COMMENT 'Shift kerja',
  `is_overtime` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag lembur simulasi',
  `adjusted_start` datetime NULL DEFAULT NULL COMMENT 'Waktu mulai setelah penyesuaian',
  `adjusted_end` datetime NULL DEFAULT NULL COMMENT 'Waktu selesai setelah penyesuaian',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu simulasi dibuat',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu update simulasi',
  `is_manual_override` tinyint(1) NULL DEFAULT NULL COMMENT 'Flag override manual oleh user',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 442 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Data simulasi jadwal MPS (perhitungan alternatif)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of mps_simulate
-- ----------------------------
INSERT INTO `mps_simulate` VALUES (433, 9, 3, 10, 10, NULL, NULL, 0, 0, 0, 0, NULL, 0, '2025-10-01 08:00:00', '2025-10-01 08:00:00', 0, 3, 0, NULL, NULL, '2026-05-18 04:20:49', '2026-05-18 04:20:49', 0);
INSERT INTO `mps_simulate` VALUES (434, 9, 4, 14, 14, NULL, NULL, 0, 0, 0, 0, NULL, 0, '2025-10-01 08:00:00', '2025-10-01 08:00:00', 0, 10, 0, NULL, NULL, '2026-05-18 04:20:49', '2026-05-18 04:20:49', 0);
INSERT INTO `mps_simulate` VALUES (435, 21, 3, 12, 12, NULL, NULL, 0, 0, 0, 0, NULL, 120, '2026-05-22 12:00:00', '2026-05-22 14:00:00', 0, 12, 0, NULL, NULL, '2026-05-22 13:44:47', '2026-05-22 13:44:47', 0);
INSERT INTO `mps_simulate` VALUES (436, 21, 1, 1, 1, NULL, NULL, 0, 0, 0, 0, NULL, 60, '2026-05-22 14:00:00', '2026-05-22 15:00:00', 0, 5, 0, NULL, NULL, '2026-05-22 13:44:47', '2026-05-22 13:44:47', 0);
INSERT INTO `mps_simulate` VALUES (437, 22, 3, 12, 12, NULL, NULL, 0, 0, 0, 0, NULL, 60, '2026-05-22 14:00:00', '2026-05-22 15:00:00', 0, 12, 0, NULL, NULL, '2026-05-22 13:44:47', '2026-05-22 13:44:47', 0);
INSERT INTO `mps_simulate` VALUES (438, 22, 4, 14, 14, NULL, NULL, 0, 0, 0, 0, NULL, 100, '2026-05-22 15:00:00', '2026-05-22 16:40:00', 0, 19, 0, NULL, NULL, '2026-05-22 13:44:48', '2026-05-22 13:44:48', 0);
INSERT INTO `mps_simulate` VALUES (439, 23, 3, 12, 12, NULL, NULL, 0, 0, 0, 0, NULL, 120, '2026-05-22 15:00:00', '2026-05-22 17:00:00', 0, 12, 0, NULL, NULL, '2026-05-22 13:44:48', '2026-05-22 13:44:48', 0);
INSERT INTO `mps_simulate` VALUES (440, 23, 1, 1, 1, NULL, NULL, 0, 0, 0, 0, NULL, 60, '2026-05-22 17:00:00', '2026-05-22 18:00:00', 0, 5, 0, NULL, NULL, '2026-05-22 13:44:48', '2026-05-22 13:44:48', 0);
INSERT INTO `mps_simulate` VALUES (441, 23, 4, 14, 14, NULL, NULL, 0, 0, 0, 0, NULL, 120, '2026-05-22 18:00:00', '2026-05-22 20:00:00', 0, 19, 0, NULL, NULL, '2026-05-22 13:44:48', '2026-05-22 13:44:48', 0);

-- ----------------------------
-- Table structure for prd_output
-- ----------------------------
DROP TABLE IF EXISTS `prd_output`;
CREATE TABLE `prd_output`  (
  `prd_output_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK Auto Number ID for transaction',
  `wopc_id` bigint UNSIGNED NOT NULL COMMENT 'FK dari table  prd_wo_process',
  `wo_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK dari table prd_wo',
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_machine (mesin yang digunakan)',
  `process_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_process (jenis proses)',
  `employee_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'untuk user invidual',
  `group_emp_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'crew dari emp ID =>  FK table tma_so_ass',
  `prd_start` datetime NULL DEFAULT NULL COMMENT 'mulai di kerjakan',
  `prd_pause_start` datetime NULL DEFAULT NULL COMMENT 'pause di mulai',
  `prd_pause_stop` datetime NULL DEFAULT NULL COMMENT 'pase diakhiri',
  `prd_stop` datetime NULL DEFAULT NULL COMMENT 'selesai dikerjakan',
  `qty_good` decimal(15, 2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'qty baik',
  `qty_waste` decimal(15, 2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'qty rusak',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'satuan',
  `scrap_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'kode rusak untuk mengenali',
  `proses_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'PROSES / SELESAI',
  `note_crew` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Notes for crew',
  `prd_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'production_note',
  `update_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp saat update',
  `create_log` timestamp NULL DEFAULT NULL COMMENT 'Tanggal dan waktu pembuatan data',
  `create_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang membuat data',
  `update_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang mengupdate data',
  PRIMARY KEY (`prd_output_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_unicode_ci COMMENT = 'PRODUCTION - PRODUCTION OUTPUT' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prd_output
-- ----------------------------

-- ----------------------------
-- Table structure for prd_output_scrap
-- ----------------------------
DROP TABLE IF EXISTS `prd_output_scrap`;
CREATE TABLE `prd_output_scrap`  (
  `doc_detail_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `prd_output_id` bigint UNSIGNED NOT NULL COMMENT 'Auto Number ID for transaction',
  `scrap_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `qty_scrap` decimal(10, 2) UNSIGNED NULL DEFAULT NULL,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `note_scrap` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  PRIMARY KEY (`doc_detail_id`) USING BTREE,
  INDEX `prd_output_id`(`prd_output_id` ASC) USING BTREE,
  INDEX `scrap_code`(`scrap_code` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prd_output_scrap
-- ----------------------------

-- ----------------------------
-- Table structure for prd_wo
-- ----------------------------
DROP TABLE IF EXISTS `prd_wo`;
CREATE TABLE `prd_wo`  (
  `wo_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key unik Work Order',
  `wo_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nomor Work Order (CO-No + counter)',
  `wo_date` date NULL DEFAULT NULL COMMENT 'Tanggal penerbitan Work Order',
  `co_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke sdi_co (header Sales/Customer Order)',
  `co_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke sdi_co_detail',
  `shipsch_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Link to s_ship_sch',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke produk',
  `parts_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_product_parts',
  `mcard_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_card',
  `customer_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke customer di SO',
  `co_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nomor Customer Order',
  `qty_order` decimal(15, 4) NULL DEFAULT NULL COMMENT 'Qty order dari customer',
  `uom_sales` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Satuan jual',
  `duedate` datetime NULL DEFAULT NULL COMMENT 'Due date produksi',
  `stat_confirm` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Status konfirmasi WO',
  `is_vvip` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Flag VVIP: 1=WO prioritas darurat, penalti keterlambatan x5 di GA',
  `stat_printwo` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Status cetak WO',
  `create_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu pembuatan Work Order',
  `update_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu update terakhir WO',
  `create_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat WO',
  `update_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pengupdate WO',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'READY',
  PRIMARY KEY (`wo_id`) USING BTREE,
  UNIQUE INDEX `wo_no`(`wo_no` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 57 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header Work Order produksi' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prd_wo
-- ----------------------------
INSERT INTO `prd_wo` VALUES (6, '151030001', '2026-04-30', 5, 0, 135, 10756, 4, 69, 21, '', 50000.0000, 'LBR', '2026-11-16 00:00:00', 0, 0, 0, '2026-04-30 23:04:04', '2026-04-30 23:04:04', 'nisa', 'nisa', 'REQ_SIMULATION');
INSERT INTO `prd_wo` VALUES (33, ' 0002', '2026-05-01', 18812, 0, 182, 997, 4, 69, 54, '', 4500000.0000, 'PCS', '2026-05-15 00:00:00', 0, 0, 0, '2026-05-01 20:05:45', '2026-05-01 20:05:45', 'nisa', 'nisa', 'READY');
INSERT INTO `prd_wo` VALUES (36, '2600412 0001', '2026-05-01', 18813, 0, 186, 997, 4, 69, 54, '', 6500.0000, 'PCS', '2026-05-15 00:00:00', 0, 0, 0, '2026-05-01 21:05:43', '2026-05-01 21:05:43', 'trial', 'trial', 'READY');
INSERT INTO `prd_wo` VALUES (37, '15103 0001', '2026-05-06', 5, 0, 135, 10756, 2, 67, 21, '', 50000.0000, 'LBR', '2015-11-16 00:00:00', 0, 0, 0, '2026-05-07 02:05:12', '2026-05-07 02:05:12', 'trial', 'trial', 'READY');
INSERT INTO `prd_wo` VALUES (50, 'DUMMY-WO-001', '2026-06-01', NULL, NULL, NULL, 997, 4, NULL, NULL, 'DUMMY-CO-001', 1000.0000, 'PCS', '2026-06-20 23:59:00', 0, 0, 0, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system', 'REQ_SIMULATION');
INSERT INTO `prd_wo` VALUES (51, 'DUMMY-WO-002', '2026-06-01', NULL, NULL, NULL, 997, 4, NULL, NULL, 'DUMMY-CO-002', 500.0000, 'PCS', '2026-06-25 23:59:00', 0, 0, 0, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system', 'READY');
INSERT INTO `prd_wo` VALUES (52, 'DUMMY-WO-003', '2026-06-02', NULL, NULL, NULL, 997, 4, NULL, NULL, 'DUMMY-CO-003', 800.0000, 'PCS', '2026-06-28 23:59:00', 0, 0, 0, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system', 'READY');
INSERT INTO `prd_wo` VALUES (53, '15103-0001', '2026-05-11', 5, 0, 134, 10759, 0, 0, 21, '', 5000.0000, 'LBR', '2015-11-16 00:00:00', 0, 0, 0, '2026-05-11 21:05:00', '2026-05-11 21:05:00', 'trial', 'trial', 'READY');
INSERT INTO `prd_wo` VALUES (55, '2600412-0001', '2026-05-11', 18813, 0, 185, 2258, 0, 0, 54, '', 8900.0000, 'PCS', '2026-05-15 00:00:00', 0, 0, 0, '2026-05-11 21:05:32', '2026-05-11 21:05:32', '_system', '_system', 'READY');
INSERT INTO `prd_wo` VALUES (56, '2600411-01', '2026-05-30', 18812, 33491, NULL, 997, NULL, NULL, 54, '2600411', 4500000.0000, 'PCS', '2026-05-15 00:00:00', 0, 0, 0, '2026-05-11 21:05:32', '2026-05-11 21:05:32', '_system', '_system', 'READY');

-- ----------------------------
-- Table structure for prd_wo_material
-- ----------------------------
DROP TABLE IF EXISTS `prd_wo_material`;
CREATE TABLE `prd_wo_material`  (
  `worm_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `wo_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke prd_wo',
  `rawmat_id` bigint NULL DEFAULT NULL COMMENT 'Relasi ke raw material',
  `rawmat_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Jenis material (PAPER / SHEET / INK, dll)',
  `qty_usage` decimal(10, 0) UNSIGNED NULL DEFAULT NULL COMMENT 'Qty material yang digunakan',
  `uom_usage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Satuan penggunaan material',
  `qty_tolerance` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Qty toleransi material (waste/spoilage)',
  `qty_consumtion` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Qty material aktual yang dikonsumsi',
  `rawmat_subs_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke rawmat substitusi (jika ada)',
  `create_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu pembuatan data',
  `update_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu update terbaru',
  `create_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang membuat data',
  `update_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang mengupdate data',
  PRIMARY KEY (`worm_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 96 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Material yang digunakan dalam Work Order' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prd_wo_material
-- ----------------------------
INSERT INTO `prd_wo_material` VALUES (10, 1, 84, 'PAPER', 25000, 'PCS', 1000.00, NULL, NULL, '2025-10-18 11:04:01', '2025-10-18 11:04:01', NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (11, 1, 288, 'SHEET', 25000, 'PCS', 250.00, NULL, NULL, '2025-10-18 11:04:01', '2025-10-18 11:04:01', NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (66, 10, 134, 'PAPER', 2500, 'PCS', NULL, NULL, NULL, '2025-11-10 09:59:13', '2025-11-10 09:59:13', NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (67, 13, 134, 'PAPER', 50000, 'PCS', NULL, NULL, NULL, '2025-11-10 11:10:02', '2025-11-10 11:10:02', NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (72, 31, 5760, 'PAPER', 50000, 'PCS', NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (73, 31, 5760, 'PAPER', 50000, 'PCS', NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (83, 33, 5760, 'PAPER', 4500000, 'PCS', 90000.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (84, 33, 3945, 'SHEET', 4500000, 'LBR', 90000.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (86, 35, 5760, 'PAPER', 8900, 'PCS', 178.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (87, 35, 3945, 'SHEET', 8900, 'LBR', 178.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (89, 36, 5760, 'PAPER', 6500, 'PCS', 130.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (90, 36, 3945, 'SHEET', 6500, 'LBR', 130.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (92, 37, NULL, NULL, 50000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (93, 6, 5760, 'PAPER', 50000, 'PCS', 1000.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (94, 6, 3945, 'SHEET', 50000, 'LBR', 1000.00, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_material` VALUES (95, 56, 145, '', 10, 'LBR', 10.00, 0.00, 0, '2026-05-30 16:43:44', '2026-05-30 16:43:44', '', '');

-- ----------------------------
-- Table structure for prd_wo_process
-- ----------------------------
DROP TABLE IF EXISTS `prd_wo_process`;
CREATE TABLE `prd_wo_process`  (
  `wopc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key unik proses WO',
  `wo_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke prd_wo',
  `process_seq` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Urutan proses dalam routing WO',
  `process_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_process (jenis proses)',
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke m_machine (mesin yang digunakan)',
  `diecut_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke diecut (jika ada), biasanya dalam proses cutting',
  `qty_per_hour` decimal(15, 4) UNSIGNED NULL DEFAULT NULL COMMENT 'Kapasitas produksi per jam',
  `uom_capacity` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Satuan kapasitas produksi',
  `qty_plan` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Qty rencana produksi pada proses ini',
  `start_plan` datetime NULL DEFAULT NULL COMMENT 'Waktu mulai rencana proses',
  `end_plan` datetime NULL DEFAULT NULL COMMENT 'Waktu selesai rencana proses',
  `duration_plan` time NULL DEFAULT NULL COMMENT 'Durasi rencana proses',
  `qty_actual` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Qty aktual yang diproduksi',
  `start_actual` datetime NULL DEFAULT NULL COMMENT 'Waktu mulai aktual',
  `end_actual` datetime NULL DEFAULT NULL COMMENT 'Waktu selesai aktual',
  `duration_actual` time NULL DEFAULT NULL COMMENT 'Durasi aktual proses',
  `previous_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi ke proses sebelumnya dalam routing',
  `process_dependency_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Dependency proses lain',
  `is_completed` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Status proses selesai',
  `is_subkon` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=proses ini disubkontrakkan ke pihak ke-3, tidak butuh slot mesin internal',
  `subkon_vendor_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK ke tmmst.m_supplier jika is_subkon=1',
  `subkon_lead_days` tinyint UNSIGNED NULL DEFAULT 3 COMMENT 'Estimasi lead time subkon dalam hari kerja (default 3)',
  `percentage_progress` decimal(5, 2) UNSIGNED NULL DEFAULT NULL COMMENT 'Persentase progress proses',
  `create_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu pembuatan data',
  `update_log` timestamp NULL DEFAULT NULL COMMENT 'Waktu update data terbaru',
  `create_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat',
  `update_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pengupdate',
  PRIMARY KEY (`wopc_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 116 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Routing proses produksi per Work Order' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prd_wo_process
-- ----------------------------
INSERT INTO `prd_wo_process` VALUES (54, 15, 1, 3, 12, NULL, 2000.0000, 'LBR', 12438.00, '2025-12-01 20:16:54', '2025-12-01 21:16:54', NULL, 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2025-12-07 00:33:19', '2025-12-07 00:36:32', '', '');
INSERT INTO `prd_wo_process` VALUES (55, 15, 2, 1, 1, NULL, 5000.0000, 'LBR', 24875.00, '2025-12-02 20:16:54', '2025-12-02 21:16:54', NULL, 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2025-12-07 00:36:24', '2025-12-07 00:36:24', '', '');
INSERT INTO `prd_wo_process` VALUES (81, 31, 1, 3, 10, NULL, 100.0000, 'PCS', NULL, '2025-12-03 21:16:54', '2025-12-04 12:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (82, 31, 2, 4, 14, NULL, 120.0000, 'PCS', NULL, '2025-12-04 20:16:54', '2025-12-04 23:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (95, 33, 1, 3, 10, NULL, 100.0000, 'PCS', NULL, '2025-12-05 20:16:54', '2025-12-06 23:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (96, 33, 2, 4, 14, NULL, 120.0000, 'PCS', NULL, '2025-12-07 20:16:54', '2025-12-08 23:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (98, 35, 1, 3, 10, NULL, 100.0000, 'PCS', NULL, '2025-12-08 20:16:54', '2025-12-09 23:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (99, 35, 2, 4, 14, NULL, 120.0000, 'PCS', NULL, '2025-12-10 20:16:54', '2025-12-11 23:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (101, 36, 1, 3, 10, NULL, 100.0000, 'PCS', NULL, '2025-12-12 20:16:54', '2025-12-13 23:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (102, 36, 2, 4, 14, NULL, 120.0000, 'PCS', NULL, '2025-12-14 20:16:54', '2025-12-15 23:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (104, 37, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-16 20:16:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (105, 50, 1, 3, 12, NULL, 500.0000, 'PCS', 1000.00, '2026-06-01 08:00:00', '2026-06-01 10:00:00', '02:00:00', 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `prd_wo_process` VALUES (106, 50, 2, 1, 1, NULL, 1000.0000, 'PCS', 1000.00, '2026-06-01 10:00:00', '2026-06-01 11:00:00', '01:00:00', 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `prd_wo_process` VALUES (107, 51, 1, 3, 12, NULL, 500.0000, 'PCS', 500.00, '2026-06-02 08:00:00', '2026-06-02 09:00:00', '01:00:00', 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `prd_wo_process` VALUES (108, 51, 2, 4, 14, NULL, 300.0000, 'PCS', 500.00, '2026-06-02 09:00:00', '2026-06-02 10:40:00', '01:40:00', 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `prd_wo_process` VALUES (109, 52, 1, 3, 12, NULL, 400.0000, 'PCS', 800.00, '2026-06-03 08:00:00', '2026-06-03 10:00:00', '02:00:00', 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `prd_wo_process` VALUES (110, 52, 2, 1, 1, NULL, 800.0000, 'PCS', 800.00, '2026-06-03 10:00:00', '2026-06-03 11:00:00', '01:00:00', 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `prd_wo_process` VALUES (111, 52, 3, 4, 14, NULL, 400.0000, 'PCS', 800.00, '2026-06-03 11:00:00', '2026-06-03 13:00:00', '02:00:00', 0.00, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 3, 0.00, '2026-05-09 17:10:40', '2026-05-09 17:10:40', 'system', 'system');
INSERT INTO `prd_wo_process` VALUES (112, 6, 1, 3, 10, NULL, 100.0000, 'PCS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (113, 6, 2, 4, 14, NULL, 120.0000, 'PCS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 3, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `prd_wo_process` VALUES (115, 56, 1, 7, 15, NULL, 12.0000, '1200', 10.00, '2026-05-01 00:00:00', '2026-05-01 00:00:00', NULL, 0.00, NULL, NULL, NULL, 0, 0, 1, 0, NULL, 3, 0.00, '2026-05-30 16:43:47', '2026-05-30 16:43:47', '', '');

-- ----------------------------
-- Table structure for prd_wo_process_simulasi
-- ----------------------------
DROP TABLE IF EXISTS `prd_wo_process_simulasi`;
CREATE TABLE `prd_wo_process_simulasi`  (
  `sim_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `scenario_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contoh: SCN-A, SCN-B',
  `batch_simulasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID Unik per eksekusi, misal: SIM-20260528-01',
  `wo_id` bigint UNSIGNED NOT NULL COMMENT 'Link ke prd_wo',
  `mcardproc_id` bigint UNSIGNED NOT NULL COMMENT 'Link ke blueprint m_card_process',
  `process_seq` tinyint UNSIGNED NOT NULL,
  `process_id` bigint UNSIGNED NOT NULL,
  `machine_id` bigint UNSIGNED NOT NULL,
  `qty_target` decimal(15, 4) UNSIGNED NOT NULL,
  `qty_per_hour` decimal(15, 4) UNSIGNED NOT NULL,
  `duration_hr` int UNSIGNED NOT NULL,
  `start_plan` datetime NOT NULL,
  `end_plan` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sim_id`) USING BTREE,
  INDEX `idx_scenario`(`scenario_id` ASC, `batch_simulasi` ASC) USING BTREE,
  INDEX `idx_wo_machine`(`wo_id` ASC, `machine_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prd_wo_process_simulasi
-- ----------------------------
INSERT INTO `prd_wo_process_simulasi` VALUES (1, 'SCN-A', 'SIM-20260529-043520', 6, 75, 2, 4, 14, 50000.0000, 120.0000, 417, '2015-10-30 07:00:00', '2015-11-16 16:00:00', '2026-05-29 13:24:33');
INSERT INTO `prd_wo_process_simulasi` VALUES (2, 'SCN-A', 'SIM-20260529-043520', 6, 74, 1, 3, 10, 50000.0000, 100.0000, 500, '2015-10-09 11:00:00', '2015-10-30 07:00:00', '2026-05-29 13:24:33');

-- ----------------------------
-- Table structure for rawmat_criteria
-- ----------------------------
DROP TABLE IF EXISTS `rawmat_criteria`;
CREATE TABLE `rawmat_criteria`  (
  `criteria_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `criteria_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `criteria_weight` decimal(5, 2) NULL DEFAULT NULL,
  `attribute_type` enum('benefit','cost') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'benefit',
  `test` tinyint(1) NULL DEFAULT NULL,
  PRIMARY KEY (`criteria_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rawmat_criteria
-- ----------------------------

-- ----------------------------
-- Table structure for rawmat_profile
-- ----------------------------
DROP TABLE IF EXISTS `rawmat_profile`;
CREATE TABLE `rawmat_profile`  (
  `rawmat_id` bigint UNSIGNED NOT NULL,
  `criteria_id` bigint UNSIGNED NOT NULL,
  `value` decimal(10, 2) NULL DEFAULT NULL,
  PRIMARY KEY (`rawmat_id`, `criteria_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rawmat_profile
-- ----------------------------

-- ----------------------------
-- Table structure for rawmat_substitution_recommendation
-- ----------------------------
DROP TABLE IF EXISTS `rawmat_substitution_recommendation`;
CREATE TABLE `rawmat_substitution_recommendation`  (
  `rec_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `main_rawmat_id` bigint UNSIGNED NULL DEFAULT NULL,
  `subs_rawmat_id` bigint UNSIGNED NULL DEFAULT NULL,
  `total_score` decimal(6, 2) NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rec_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rawmat_substitution_recommendation
-- ----------------------------

-- ----------------------------
-- View structure for v1_co_to_wo
-- ----------------------------
DROP VIEW IF EXISTS `v1_co_to_wo`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_co_to_wo` AS select `cod`.`doc_detail_id` AS `doc_detail_id`,`cod`.`doc_id` AS `doc_id`,`co`.`customer_id` AS `customer_id`,`cod`.`product_id` AS `product_id`,`pp`.`mcard_id` AS `mcard_id`,`pp`.`parts_id` AS `parts_id`,`co`.`co_no` AS `co_no`,`cod`.`qty_order` AS `qty_order`,`cod`.`uom_sales` AS `uom_sales`,`cod`.`duedate` AS `duedate` from ((((`tmsdi`.`s_co_detail` `cod` left join `tmsdi`.`s_co` `co` on((`co`.`doc_id` = `cod`.`doc_id`))) left join `tmmst`.`m_product` `p` on((`p`.`product_id` = `cod`.`product_id`))) left join `tmmst`.`m_product_parts` `pp` on((`pp`.`product_id` = `p`.`product_id`))) left join `tmmst`.`m_card` `mc` on((`mc`.`mcard_id` = `pp`.`mcard_id`)));

-- ----------------------------
-- View structure for v1_m_card
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_card`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_card` AS select `mc`.`mcard_id` AS `mcard_id`,`mc`.`mcard_code` AS `mcard_code`,`mc`.`mcard_name` AS `mcard_name`,`mc`.`mcard_date` AS `mcard_date`,`mc`.`mcard_rev` AS `mcard_rev`,`mc`.`is_active` AS `is_active`,`mc`.`image_up` AS `image_up`,`mc`.`out_per_plano` AS `out_per_plano`,`mc`.`printsize_width` AS `printsize_width`,`mc`.`printsize_length` AS `printsize_length`,`mc`.`cfg_color` AS `cfg_color`,`mc`.`cfg_ink` AS `cfg_ink`,`mc`.`layout_attachment` AS `layout_attachment`,`mc`.`reff_parts_code` AS `reff_parts_code`,`mc`.`date_approval_email` AS `date_approval_email`,`mc`.`date_approval_digital` AS `date_approval_digital`,`mc`.`date_approval_sample` AS `date_approval_sample`,`mc`.`approval_by` AS `approval_by`,`mc`.`stat_print_original` AS `stat_print_original`,`mc`.`create_log` AS `create_log`,`mc`.`update_log` AS `update_log`,`mc`.`create_by` AS `create_by`,`mc`.`update_by` AS `update_by` from `tmmst`.`m_card` `mc` where (`mc`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_card_material
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_card_material`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_card_material` AS select `mcm`.`mcardrm_id` AS `mcardrm_id`,`mcm`.`mcard_id` AS `mcard_id`,`mcm`.`rawmat_id` AS `rawmat_id`,`mcm`.`rawmat_type` AS `rawmat_type`,`mcm`.`percentage_tolerance` AS `percentage_tolerance`,`mcm`.`uom_usage` AS `uom_usage`,`mcm`.`size_width` AS `size_width`,`mcm`.`size_length` AS `size_length`,`mcm`.`gram_weight` AS `gram_weight`,`mcm`.`create_log` AS `create_log`,`mcm`.`update_log` AS `update_log`,`mcm`.`create_by` AS `create_by`,`mcm`.`update_by` AS `update_by` from `tmmst`.`m_card_material` `mcm`;

-- ----------------------------
-- View structure for v1_m_card_process
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_card_process`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_card_process` AS select `tmmst`.`m_card_process`.`mcardproc_id` AS `mcardproc_id`,`tmmst`.`m_card_process`.`mcard_id` AS `mcard_id`,`tmmst`.`m_card_process`.`process_seq` AS `process_seq`,`tmmst`.`m_card_process`.`process_id` AS `process_id`,`tmmst`.`m_card_process`.`machine_id` AS `machine_id`,`tmmst`.`m_card_process`.`qty_per_hour` AS `qty_per_hour`,`tmmst`.`m_card_process`.`setup_minutes` AS `setup_minutes`,`tmmst`.`m_card_process`.`uom_capacity` AS `uom_capacity`,`tmmst`.`m_card_process`.`create_log` AS `create_log`,`tmmst`.`m_card_process`.`update_log` AS `update_log`,`tmmst`.`m_card_process`.`create_by` AS `create_by`,`tmmst`.`m_card_process`.`update_by` AS `update_by` from `tmmst`.`m_card_process`;

-- ----------------------------
-- View structure for v1_m_constant
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_constant`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_constant` AS select `tmmst`.`m_constant`.`id` AS `id`,`tmmst`.`m_constant`.`constant_string` AS `constant_string`,`tmmst`.`m_constant`.`constant_number` AS `constant_number`,`tmmst`.`m_constant`.`constant_type` AS `constant_type`,`tmmst`.`m_constant`.`remark` AS `remark`,`tmmst`.`m_constant`.`update_log` AS `update_log`,`tmmst`.`m_constant`.`create_log` AS `create_log` from `tmmst`.`m_constant`;

-- ----------------------------
-- View structure for v1_m_customer
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_customer`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_customer` AS select `C`.`customer_id` AS `customer_id`,`C`.`customer_code` AS `customer_code`,`C`.`customer_name` AS `customer_name`,`C`.`tax_code` AS `tax_code`,`C`.`mr_ms` AS `mr_ms`,`C`.`contact_name` AS `contact_name`,`C`.`default_address` AS `default_address`,`C`.`default_id_area` AS `default_id_area`,`C`.`email` AS `email`,`C`.`phone` AS `phone`,`C`.`website` AS `website`,`C`.`customer_type` AS `customer_type`,`C`.`payment_term` AS `payment_term`,`C`.`currency_code` AS `currency_code`,`C`.`is_active` AS `is_active`,`C`.`create_log` AS `create_log`,`C`.`update_log` AS `update_log`,`C`.`create_by` AS `create_by`,`C`.`update_by` AS `update_by`,`C`.`credit_limit` AS `credit_limit`,`C`.`current_balance` AS `current_balance` from `tmmst`.`m_customer` `C` where (`C`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_emp
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_emp`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_emp` AS select `m`.`employee_id` AS `employee_id`,`m`.`employee_code` AS `employee_code`,`m`.`device_pin` AS `device_pin`,`m`.`full_name` AS `full_name`,`m`.`is_active` AS `is_active`,`d`.`gender` AS `gender`,`d`.`hire_date` AS `hire_date`,`d`.`birth_date` AS `birth_date`,`d`.`resign_date` AS `resign_date`,`d`.`retire_date` AS `retire_date`,`m`.`emp_type_id` AS `emp_type_id`,`d`.`religion` AS `religion`,`d`.`marital_status` AS `marital_status`,`d`.`email` AS `email`,`d`.`phone_number` AS `phone_number`,`d`.`photo_url` AS `photo_url`,`m`.`default_shift_id` AS `default_shift_id`,`m`.`payroll_cycle` AS `payroll_cycle`,`pi`.`payroll_method` AS `payroll_method`,`pi`.`bank_account_number` AS `bank_account_number`,`pi`.`bank_name` AS `bank_name`,`d`.`stat_confirm` AS `stat_confirm`,`d`.`current_department_id` AS `current_department_id`,`d`.`current_section_id` AS `current_section_id`,`d`.`current_position_id` AS `current_position_id`,`m`.`create_log` AS `create_log`,`m`.`update_log` AS `update_log`,`m`.`create_by` AS `create_by`,`m`.`update_by` AS `update_by` from ((`tmmst`.`m_emp` `m` left join `tmhrm`.`emp_detail` `d` on((`d`.`employee_id` = `m`.`employee_id`))) left join `tmpyr`.`emp_payment_info` `pi` on((`pi`.`employee_id` = `m`.`employee_id`))) where (`m`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_emp_dept
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_emp_dept`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_emp_dept` AS select `m`.`employee_id` AS `employee_id`,`m`.`employee_code` AS `employee_code`,`m`.`full_name` AS `full_name`,`m`.`is_active` AS `is_active`,`m`.`payroll_cycle` AS `payroll_cycle`,`pi`.`payroll_method` AS `payroll_method`,`d`.`current_department_id` AS `current_department_id`,`d`.`current_position_id` AS `current_position_id`,`od`.`department_name` AS `department_name`,concat(`m`.`full_name`,' | ',coalesce(`od`.`department_name`,'-')) AS `empdept` from (((`tmmst`.`m_emp` `m` left join `tmhrm`.`emp_detail` `d` on((`d`.`employee_id` = `m`.`employee_id`))) left join `tmpyr`.`emp_payment_info` `pi` on((`pi`.`employee_id` = `m`.`employee_id`))) left join `tmmst`.`org_department` `od` on((`od`.`department_id` = `d`.`current_department_id`))) where (`m`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_machine
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_machine`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_machine` AS select `m`.`machine_id` AS `machine_id`,`m`.`machine_name` AS `machine_name`,`m`.`stat_machine` AS `stat_machine`,`m`.`process_id_default` AS `process_id_default`,`m`.`employee_id` AS `employee_id`,`m`.`login_name` AS `login_name`,`m`.`gantt_color` AS `gantt_color`,`m`.`is_active` AS `is_active`,`m`.`notes` AS `notes`,`m`.`create_by` AS `create_by`,`m`.`update_by` AS `update_by`,`m`.`create_log` AS `create_log`,`m`.`update_log` AS `update_log` from `tmmst`.`m_machine` `m`;

-- ----------------------------
-- View structure for v1_m_process
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_process`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_process` AS select `tmmst`.`m_process`.`process_id` AS `process_id`,`tmmst`.`m_process`.`process_name` AS `process_name`,`tmmst`.`m_process`.`create_log` AS `create_log`,`tmmst`.`m_process`.`update_log` AS `update_log`,`tmmst`.`m_process`.`create_by` AS `create_by`,`tmmst`.`m_process`.`update_by` AS `update_by` from `tmmst`.`m_process`;

-- ----------------------------
-- View structure for v1_m_product
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_product`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_product` AS select `p`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_name` AS `product_name`,`p`.`customer_id` AS `customer_id`,`p`.`description` AS `description`,`p`.`uom_stock` AS `uom_stock`,`p`.`uom_sales` AS `uom_sales`,`p`.`qty_limit_min` AS `qty_limit_min`,`p`.`qty_limit_max` AS `qty_limit_max`,`p`.`cfg_parts_id` AS `cfg_parts_id`,`tmmst`.`p`.`cfg_color` AS `cfg_color`,`tmmst`.`p`.`cfg_ink` AS `cfg_ink`,`tmmst`.`p`.`product_cat_id` AS `product_cat_id`,`tmmst`.`p`.`conversion_to_stock` AS `conversion_to_stock`,`tmmst`.`p`.`min_stock` AS `min_stock`,`tmmst`.`p`.`max_stock` AS `max_stock`,`tmmst`.`p`.`cur_stock` AS `cur_stock`,`tmmst`.`p`.`is_active` AS `is_active`,`tmmst`.`p`.`cm_length` AS `cm_length`,`tmmst`.`p`.`cm_width` AS `cm_width`,`tmmst`.`p`.`cm_height` AS `cm_height`,`tmmst`.`p`.`stat_confirm` AS `stat_confirm`,`tmmst`.`p`.`default_selling_price` AS `default_selling_price`,`tmmst`.`p`.`minimum_selling_price` AS `minimum_selling_price`,`tmmst`.`p`.`create_log` AS `create_log`,`tmmst`.`p`.`update_log` AS `update_log`,`tmmst`.`p`.`create_by` AS `create_by`,`tmmst`.`p`.`update_by` AS `update_by` from `tmmst`.`m_product` `p` where (`tmmst`.`p`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_product_parts
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_product_parts`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_product_parts` AS select `pp`.`parts_id` AS `parts_id`,`pp`.`product_id` AS `product_id`,`pp`.`parts_code` AS `parts_code`,`pp`.`parts_name` AS `parts_name`,`pp`.`qty_per_sku` AS `qty_per_sku`,`pp`.`description` AS `description`,`pp`.`mcard_id` AS `mcard_id`,`pp`.`uom_stock` AS `uom_stock`,`pp`.`cfg_color` AS `cfg_color`,`pp`.`cfg_ink` AS `cfg_ink`,`pp`.`waste_default` AS `waste_default`,`pp`.`spoilage_default` AS `spoilage_default`,`pp`.`min_stock` AS `min_stock`,`pp`.`max_stock` AS `max_stock`,`pp`.`cur_stock` AS `cur_stock`,`pp`.`is_active` AS `is_active`,`pp`.`cm_length` AS `cm_length`,`pp`.`cm_width` AS `cm_width`,`pp`.`cm_height` AS `cm_height`,`pp`.`create_log` AS `create_log`,`pp`.`update_log` AS `update_log`,`pp`.`create_by` AS `create_by`,`pp`.`update_by` AS `update_by` from `tmmst`.`m_product_parts` `pp`;

-- ----------------------------
-- View structure for v1_m_raw_material
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_raw_material`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_raw_material` AS select `rm`.`material_id` AS `material_id`,`rm`.`material_code` AS `material_code`,`rm`.`material_name` AS `material_name`,`rm`.`item_type` AS `item_type`,`rm`.`category1` AS `category1`,`rm`.`category2` AS `category2`,`rm`.`category3` AS `category3`,`rm`.`category4` AS `category4`,`rm`.`category5` AS `category5`,`rm`.`description` AS `description`,`rm`.`brand` AS `brand`,`rm`.`color` AS `color`,`rm`.`gram_weight` AS `gram_weight`,`rm`.`cm_length` AS `cm_length`,`rm`.`cm_width` AS `cm_width`,`rm`.`mm_thickness` AS `mm_thickness`,`rm`.`specification` AS `specification`,`rm`.`uom_stock` AS `uom_stock`,`rm`.`uom_purchase` AS `uom_purchase`,`rm`.`purchasable` AS `purchasable`,`rm`.`uom_usage` AS `uom_usage`,`rm`.`conversion_to_stock` AS `conversion_to_stock`,`rm`.`conversion_to_usage` AS `conversion_to_usage`,`rm`.`free_note` AS `free_note`,`rm`.`min_stock` AS `min_stock`,`rm`.`max_stock` AS `max_stock`,`rm`.`cur_stock` AS `cur_stock`,`rm`.`is_active` AS `is_active`,`rm`.`create_log` AS `create_log`,`rm`.`update_log` AS `update_log`,`rm`.`create_by` AS `create_by`,`rm`.`update_by` AS `update_by` from `tmmst`.`m_raw_material` `rm` where (`rm`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_shift
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_shift`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_shift` AS select `tmmst`.`m_shifts`.`shift_id` AS `shift_id`,`tmmst`.`m_shifts`.`shift_name` AS `shift_name`,`tmmst`.`m_shifts`.`emp_type_id` AS `emp_type_id`,`tmmst`.`m_shifts`.`description` AS `description`,`tmmst`.`m_shifts`.`is_active` AS `is_active`,`tmmst`.`m_shifts`.`create_log` AS `create_log`,`tmmst`.`m_shifts`.`update_log` AS `update_log`,`tmmst`.`m_shifts`.`create_by` AS `create_by`,`tmmst`.`m_shifts`.`update_by` AS `update_by` from `tmmst`.`m_shifts` where (`tmmst`.`m_shifts`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_shift_details
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_shift_details`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_shift_details` AS select `tmmst`.`m_shifts_details`.`shift_detail_id` AS `shift_detail_id`,`tmmst`.`m_shifts_details`.`shift_id` AS `shift_id`,`tmmst`.`m_shifts_details`.`shift_day` AS `shift_day`,`tmmst`.`m_shifts_details`.`start_time` AS `start_time`,`tmmst`.`m_shifts_details`.`break_out` AS `break_out`,`tmmst`.`m_shifts_details`.`break_in` AS `break_in`,`tmmst`.`m_shifts_details`.`end_time` AS `end_time`,`tmmst`.`m_shifts_details`.`description` AS `description`,`tmmst`.`m_shifts_details`.`create_log` AS `create_log`,`tmmst`.`m_shifts_details`.`update_log` AS `update_log`,`tmmst`.`m_shifts_details`.`create_by` AS `create_by`,`tmmst`.`m_shifts_details`.`update_by` AS `update_by` from `tmmst`.`m_shifts_details`;

-- ----------------------------
-- View structure for v1_m_uom
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_uom`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_uom` AS select `tmmst`.`m_uom`.`uom_id` AS `uom_id`,`tmmst`.`m_uom`.`uom_code` AS `uom_code`,`tmmst`.`m_uom`.`uom_name` AS `uom_name`,`tmmst`.`m_uom`.`create_by` AS `create_by`,`tmmst`.`m_uom`.`update_by` AS `update_by`,`tmmst`.`m_uom`.`create_log` AS `create_log`,`tmmst`.`m_uom`.`update_log` AS `update_log` from `tmmst`.`m_uom`;

-- ----------------------------
-- View structure for v1_m_wh_loc
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_wh_loc`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_wh_loc` AS select `l`.`location_id` AS `location_id`,`l`.`warehouse_id` AS `warehouse_id`,`l`.`location_code` AS `location_code`,`l`.`location_name` AS `location_name`,`l`.`row_name` AS `row_name`,`l`.`column_name` AS `column_name`,`l`.`stack_name` AS `stack_name`,`l`.`location_type` AS `location_type`,`l`.`max_capacity_qty` AS `max_capacity_qty`,`l`.`description` AS `description`,`l`.`is_active` AS `is_active`,`l`.`create_log` AS `create_log`,`l`.`update_log` AS `update_log`,`l`.`create_by` AS `create_by`,`l`.`update_by` AS `update_by`,concat(`w`.`warehouse_name`,' - ',`l`.`location_name`) AS `strwh` from (`tmmst`.`m_wh_loc` `l` join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `l`.`warehouse_id`)));

-- ----------------------------
-- View structure for v1_mps_common_field
-- ----------------------------
DROP VIEW IF EXISTS `v1_mps_common_field`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mps_common_field` AS select `information_schema`.`COLUMNS`.`TABLE_SCHEMA` AS `database_name`,`information_schema`.`COLUMNS`.`TABLE_NAME` AS `table_name`,`information_schema`.`COLUMNS`.`COLUMN_NAME` AS `field_name`,`information_schema`.`COLUMNS`.`DATA_TYPE` AS `data_type`,`information_schema`.`COLUMNS`.`CHARACTER_MAXIMUM_LENGTH` AS `max_length`,`information_schema`.`COLUMNS`.`COLUMN_KEY` AS `key_type`,`information_schema`.`COLUMNS`.`IS_NULLABLE` AS `is_nullable`,`information_schema`.`COLUMNS`.`COLUMN_DEFAULT` AS `default_value`,`information_schema`.`COLUMNS`.`EXTRA` AS `extra_info` from `information_schema`.`COLUMNS` where ((`information_schema`.`COLUMNS`.`TABLE_SCHEMA` = 'tmprd') or (`information_schema`.`COLUMNS`.`TABLE_SCHEMA` = 'tmmst'));

-- ----------------------------
-- View structure for v1_mps_common_table
-- ----------------------------
DROP VIEW IF EXISTS `v1_mps_common_table`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mps_common_table` AS select `information_schema`.`TABLES`.`TABLE_NAME` AS `TABLE_NAME` from `information_schema`.`TABLES` where ((`information_schema`.`TABLES`.`TABLE_SCHEMA` = 'tmmst') or (`information_schema`.`TABLES`.`TABLE_SCHEMA` = 'tmprd'));

-- ----------------------------
-- View structure for v1_mps_schedules
-- ----------------------------
DROP VIEW IF EXISTS `v1_mps_schedules`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mps_schedules` AS select `ms`.`id` AS `id`,`ms`.`plan_duration` AS `plan_duration`,`ms`.`start_time` AS `start_time`,`ms`.`end_time` AS `end_time`,`ms`.`process_id` AS `process_id`,`mp`.`process_name` AS `process_name`,`ms`.`machine_id` AS `machine_id`,`mm`.`machine_name` AS `machine_name`,`pw`.`wo_no` AS `wo_no`,`mpp`.`parts_name` AS `parts_name` from ((((`mps_schedules` `ms` left join `tmmst`.`m_machine` `mm` on((`ms`.`machine_id` = `mm`.`machine_id`))) left join `tmmst`.`m_process` `mp` on((`ms`.`process_id` = `mp`.`process_id`))) left join `prd_wo` `pw` on((`ms`.`wo_id` = `pw`.`wo_id`))) left join `tmmst`.`m_product_parts` `mpp` on((`pw`.`parts_id` = `mpp`.`parts_id`)));

-- ----------------------------
-- View structure for v1_mps_simulate
-- ----------------------------
DROP VIEW IF EXISTS `v1_mps_simulate`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mps_simulate` AS select `ms`.`id` AS `id`,`ms`.`plan_detail_id` AS `plan_detail_id`,`ms`.`plan_duration` AS `plan_duration`,`ms`.`start_time` AS `start_time`,`ms`.`end_time` AS `end_time`,`ms`.`process_id` AS `process_id`,`mp`.`process_name` AS `process_name`,`ms`.`machine_id` AS `machine_id`,`mm`.`machine_name` AS `machine_name`,`pw`.`wo_no` AS `wo_no`,`mpp`.`parts_name` AS `parts_name` from (((((`mps_simulate` `ms` left join `tmmst`.`m_machine` `mm` on((`ms`.`machine_id` = `mm`.`machine_id`))) left join `tmmst`.`m_process` `mp` on((`ms`.`process_id` = `mp`.`process_id`))) left join `mps_plan_details` `mpd` on((`ms`.`plan_detail_id` = `mpd`.`plan_detail_id`))) left join `prd_wo` `pw` on((`mpd`.`wo_id` = `pw`.`wo_id`))) left join `tmmst`.`m_product_parts` `mpp` on((`pw`.`parts_id` = `mpp`.`parts_id`)));

-- ----------------------------
-- View structure for v1_mps_wo
-- ----------------------------
DROP VIEW IF EXISTS `v1_mps_wo`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mps_wo` AS select `pw`.`wo_id` AS `wo_id`,`pw`.`wo_no` AS `wo_no`,`pw`.`wo_date` AS `wo_date`,`pw`.`doc_id` AS `doc_id`,`pw`.`doc_detail_id` AS `doc_detail_id`,`pw`.`product_id` AS `product_id`,`pw`.`parts_id` AS `parts_id`,`pw`.`mcard_id` AS `mcard_id`,`pw`.`customer_id` AS `customer_id`,`pw`.`co_no` AS `co_no`,`pw`.`qty_order` AS `qty_order`,`pw`.`uom_sales` AS `uom_sales`,`pw`.`duedate` AS `duedate`,`pwp`.`wopc_id` AS `wopc_id`,`pwp`.`process_seq` AS `process_seq`,`pwp`.`process_id` AS `process_id`,`pwp`.`qty_per_hour` AS `qty_per_hour`,`pwp`.`uom_capacity` AS `uom_capacity`,`pwp`.`qty_plan` AS `qty_plan`,`pwp`.`start_plan` AS `start_plan`,`pwp`.`end_plan` AS `end_plan`,`pwp`.`duration_plan` AS `duration_plan`,`pwp`.`qty_actual` AS `qty_actual`,`pwp`.`start_actual` AS `start_actual`,`pwp`.`end_actual` AS `end_actual`,`pwp`.`duration_actual` AS `duration_actual`,`pwp`.`machine_id` AS `process_machine_id`,`pwp`.`previous_id` AS `previous_id`,`pwp`.`process_dependency_id` AS `process_dependency_id`,`pwp`.`is_completed` AS `process_is_completed`,`pwp`.`percentage_progress` AS `percentage_progress`,`pwm`.`worm__id` AS `worm__id`,`pwm`.`rawmat_id` AS `rawmat_id`,`pwm`.`rawmat_type` AS `rawmat_type`,`pwm`.`qty_usage` AS `qty_usage`,`pwm`.`uom_usage` AS `uom_usage`,`pwm`.`qty_tolerance` AS `qty_tolerance`,`pwm`.`qty_consumtion` AS `qty_consumtion` from ((`prd_wo` `pw` left join `prd_wo_process` `pwp` on((`pw`.`wo_id` = `pwp`.`wo_id`))) left join `prd_wo_material` `pwm` on((`pw`.`wo_id` = `pwm`.`wo_id`)));

-- ----------------------------
-- View structure for v1_mrp
-- ----------------------------
DROP VIEW IF EXISTS `v1_mrp`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mrp` AS select `cod`.`product_id` AS `product_id`,`pp`.`parts_id` AS `parts_id`,`mc`.`mcard_id` AS `mcard_id`,`pp`.`parts_name` AS `parts_name`,`co`.`co_no` AS `co_no`,`mc`.`image_up` AS `image_up`,`mc`.`out_per_plano` AS `out_per_plano`,`pp`.`waste_default` AS `waste_default`,`pp`.`spoilage_default` AS `spoilage_default`,`cod`.`qty_order` AS `qty_order`,if((`mcm`.`rawmat_type` = 'PAPER'),round(((`cod`.`qty_order` / `mc`.`image_up`) / `mc`.`out_per_plano`),2),round((`cod`.`qty_order` / `mc`.`image_up`),2)) AS `qty_usage`,if((`mcm`.`rawmat_type` = 'PAPER'),round(((`pp`.`waste_default` / 100) * `cod`.`qty_order`),2),round(((`pp`.`spoilage_default` / 100) * `cod`.`qty_order`),2)) AS `qty_tolerance`,`mcm`.`rawmat_type` AS `rawmat_type` from (((((`tmsdi`.`s_co_detail` `cod` left join `tmsdi`.`s_co` `co` on((`co`.`doc_id` = `cod`.`doc_id`))) left join `tmmst`.`m_product` `p` on((`cod`.`product_id` = `p`.`product_id`))) left join `tmmst`.`m_product_parts` `pp` on((`p`.`product_id` = `pp`.`product_id`))) left join `tmmst`.`m_card` `mc` on((`mc`.`mcard_id` = `pp`.`mcard_id`))) left join `tmmst`.`m_card_material` `mcm` on((`mcm`.`mcard_id` = `mc`.`mcard_id`)));

-- ----------------------------
-- View structure for v1_mst_machine
-- ----------------------------
DROP VIEW IF EXISTS `v1_mst_machine`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mst_machine` AS select `m`.`machine_id` AS `machine_id`,`m`.`machine_name` AS `machine_name`,`m`.`stat_machine` AS `stat_machine`,`m`.`process_id_default` AS `process_id_default`,`m`.`employee_id` AS `employee_id`,`m`.`login_name` AS `login_name`,`m`.`gantt_color` AS `gantt_color`,`m`.`is_active` AS `is_active`,`m`.`notes` AS `notes`,`m`.`create_by` AS `create_by`,`m`.`update_by` AS `update_by`,`m`.`create_log` AS `create_log`,`m`.`update_log` AS `update_log` from `tmmst`.`m_machine` `m`;

-- ----------------------------
-- View structure for v1_mst_product
-- ----------------------------
DROP VIEW IF EXISTS `v1_mst_product`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_mst_product` AS select `tmmst`.`m_product`.`product_id` AS `product_id`,`tmmst`.`m_product`.`product_code` AS `product_code`,`tmmst`.`m_product`.`product_name` AS `product_name`,`tmmst`.`m_product`.`description` AS `description`,`tmmst`.`m_product`.`uom_stock` AS `uom_stock`,`tmmst`.`m_product`.`uom_sales` AS `uom_sales`,`tmmst`.`m_product`.`qty_limit_min` AS `qty_limit_min`,`tmmst`.`m_product`.`qty_limit_max` AS `qty_limit_max`,`tmmst`.`m_product`.`cfg_parts_id` AS `cfg_parts_id`,`tmmst`.`m_product`.`cfg_color` AS `cfg_color`,`tmmst`.`m_product`.`cfg_ink` AS `cfg_ink`,`tmmst`.`m_product`.`product_cat_id` AS `product_cat_id`,`tmmst`.`m_product`.`conversion_to_stock` AS `conversion_to_stock`,`tmmst`.`m_product`.`min_stock` AS `min_stock`,`tmmst`.`m_product`.`max_stock` AS `max_stock`,`tmmst`.`m_product`.`cur_stock` AS `cur_stock`,`tmmst`.`m_product`.`is_active` AS `is_active`,`tmmst`.`m_product`.`cm_length` AS `cm_length`,`tmmst`.`m_product`.`cm_width` AS `cm_width`,`tmmst`.`m_product`.`cm_height` AS `cm_height`,`tmmst`.`m_product`.`stat_confirm` AS `stat_confirm`,`tmmst`.`m_product`.`default_selling_price` AS `default_selling_price`,`tmmst`.`m_product`.`minimum_selling_price` AS `minimum_selling_price`,`tmmst`.`m_product`.`create_log` AS `create_log`,`tmmst`.`m_product`.`update_log` AS `update_log`,`tmmst`.`m_product`.`create_by` AS `create_by`,`tmmst`.`m_product`.`update_by` AS `update_by` from `tmmst`.`m_product`;

-- ----------------------------
-- View structure for v1_prd_wo_parts
-- ----------------------------
DROP VIEW IF EXISTS `v1_prd_wo_parts`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_prd_wo_parts` AS select `wo`.`wo_id` AS `wo_id`,`wo`.`wo_no` AS `wo_no`,`pp`.`parts_id` AS `parts_id`,`pp`.`parts_name` AS `parts_name`,`wo`.`duedate` AS `duedate` from ((`prd_wo` `wo` left join `tmmst`.`m_product` `p` on((`p`.`product_id` = `wo`.`product_id`))) left join `tmmst`.`m_product_parts` `pp` on((`pp`.`product_id` = `p`.`product_id`)));

-- ----------------------------
-- View structure for v1_s_co
-- ----------------------------
DROP VIEW IF EXISTS `v1_s_co`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_s_co` AS select `c`.`doc_id` AS `doc_id`,`c`.`co_date` AS `co_date`,`c`.`co_no` AS `co_no`,`c`.`customer_id` AS `customer_id`,`c`.`recipient_id` AS `recipient_id`,`c`.`forwarder_id` AS `forwarder_id`,`c`.`shipper_id` AS `shipper_id`,`c`.`sales_by` AS `sales_by`,`c`.`currency_code` AS `currency_code`,`c`.`shipment_id` AS `shipment_id`,`c`.`payment_id` AS `payment_id`,`c`.`payment_bank_account_id` AS `payment_bank_account_id`,`c`.`stat_confirm` AS `stat_confirm`,`c`.`stat_cancel` AS `stat_cancel`,`c`.`stat_closed` AS `stat_closed`,`c`.`quotation_id` AS `quotation_id`,`c`.`forecast_id` AS `forecast_id`,`c`.`order_category` AS `order_category`,`c`.`po_no_customer` AS `po_no_customer`,`c`.`po_date_customer` AS `po_date_customer`,`c`.`po_customer_rcvdate` AS `po_customer_rcvdate`,`c`.`file_attachment_jpg` AS `file_attachment_jpg`,`c`.`file_attachment_pdf` AS `file_attachment_pdf`,`c`.`stat_auth` AS `stat_auth`,`c`.`auth_module` AS `auth_module`,`c`.`stat_invoiced` AS `stat_invoiced`,`c`.`current_approval_level` AS `current_approval_level`,`c`.`current_approval_login` AS `current_approval_login`,`c`.`deliver_to` AS `deliver_to`,`c`.`delivery_addr_area_id` AS `delivery_addr_area_id`,`c`.`doc_status` AS `doc_status`,`c`.`tax_enable` AS `tax_enable`,`c`.`subtotal` AS `subtotal`,`c`.`disc_percent` AS `disc_percent`,`c`.`disc_amount` AS `disc_amount`,`c`.`freight_amount` AS `freight_amount`,`c`.`insurance_amount` AS `insurance_amount`,`c`.`trucking_amount` AS `trucking_amount`,`c`.`others_amount` AS `others_amount`,`c`.`ppn_percent` AS `ppn_percent`,`c`.`ppn_amount` AS `ppn_amount`,`c`.`pph22_percent` AS `pph22_percent`,`c`.`pph22_amount` AS `pph22_amount`,`c`.`pph23_percent` AS `pph23_percent`,`c`.`pph23_amount` AS `pph23_amount`,`c`.`total_discount_amount` AS `total_discount_amount`,`c`.`total_expense_amount` AS `total_expense_amount`,`c`.`total_beforetax_amount` AS `total_beforetax_amount`,`c`.`total_taxes_amount` AS `total_taxes_amount`,`c`.`grand_total` AS `grand_total`,`c`.`counted` AS `counted`,`c`.`order_notes` AS `order_notes`,`c`.`invoice_post_status` AS `invoice_post_status`,`c`.`invoice_post_datetime` AS `invoice_post_datetime`,`c`.`invoice_posted` AS `invoice_posted`,`c`.`invoice_no` AS `invoice_no`,`c`.`create_by` AS `create_by`,`c`.`update_by` AS `update_by`,`c`.`create_log` AS `create_log`,`c`.`update_log` AS `update_log`,`ct`.`customer_name` AS `customer_name` from (`tmsdi`.`s_co` `c` left join `tmmst`.`m_customer` `ct` on((`ct`.`customer_id` = `c`.`customer_id`)));

-- ----------------------------
-- View structure for v1_s_schip_sch
-- ----------------------------
DROP VIEW IF EXISTS `v1_s_schip_sch`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_s_schip_sch` AS select `ss`.`shipsch_id` AS `shipsch_id`,`ss`.`link_parent_id` AS `link_parent_id`,`ss`.`co_id` AS `co_id`,`ss`.`co_detail_id` AS `co_detail_id`,`ss`.`customer_id` AS `customer_id`,`ss`.`schedule_date` AS `schedule_date`,`ss`.`qty_schedule` AS `qty_schedule`,`ss`.`product_id` AS `product_id`,`ss`.`uom_code` AS `uom_code`,`ss`.`qty_split` AS `qty_split`,`ss`.`unit_price` AS `unit_price`,`ss`.`qty_unitprice` AS `qty_unitprice`,`ss`.`doc_status` AS `doc_status`,`ss`.`stat_confirm` AS `stat_confirm`,`ss`.`stat_cancel` AS `stat_cancel`,`ss`.`stat_closed` AS `stat_closed`,`ss`.`split_color` AS `split_color`,`ss`.`create_by` AS `create_by`,`ss`.`update_by` AS `update_by`,`ss`.`create_log` AS `create_log`,`ss`.`update_log` AS `update_log`,`c`.`co_no` AS `co_no`,concat(`c`.`co_no`,' | ',date_format(`ss`.`schedule_date`,'%d-%b-%y'),' | ',`p`.`product_name`) AS `conoprod` from ((((`tmsdi`.`s_ship_sch` `ss` left join `tmsdi`.`s_co` `c` on((`c`.`doc_id` = `ss`.`co_id`))) left join `tmsdi`.`s_co_detail` `cd` on((`cd`.`doc_detail_id` = `ss`.`co_detail_id`))) left join `tmmst`.`m_product` `p` on((`p`.`product_id` = `cd`.`product_id`))) left join `tmmst`.`m_product_parts` `pp` on((`pp`.`product_id` = `p`.`product_id`)));

-- ----------------------------
-- View structure for v1_shiift
-- ----------------------------
DROP VIEW IF EXISTS `v1_shiift`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_shiift` AS select `tmhrm`.`tsa`.`sa_id` AS `id`,`tmmst`.`ms`.`shift_name` AS `name`,dayname(`tmhrm`.`tsa`.`work_date`) AS `day`,`tmmst`.`ms`.`start_time` AS `start_time`,`tmmst`.`ms`.`end_time` AS `end_time`,`tmhrm`.`tsa`.`is_active` AS `is_active`,`tmhrm`.`tsa`.`create_log` AS `created_at`,`tmhrm`.`tsa`.`update_log` AS `updated_at`,`tmhrm`.`tsa`.`employee_id` AS `employee_id`,`tmhrm`.`tsa`.`machine_id` AS `machine_id`,`tmhrm`.`tsa`.`work_date` AS `work_date`,`tmhrm`.`tsa`.`notes` AS `notes` from (`tmhrm`.`tma_shift_assignments` `tsa` left join `tmmst`.`m_shifts` `ms` on((`tmmst`.`ms`.`shift_id` = `tmhrm`.`tsa`.`shift_id`)));

-- ----------------------------
-- View structure for v1_tma_so_ass
-- ----------------------------
DROP VIEW IF EXISTS `v1_tma_so_ass`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_tma_so_ass` AS select `sa`.`sa_id` AS `sa_id`,`sa`.`group_emp_id` AS `group_emp_id`,`sa`.`period_id` AS `period_id`,`sa`.`shift_src_id` AS `shift_src_id`,`sa`.`shift_dst_id` AS `shift_dst_id`,`sa`.`machine_id` AS `machine_id`,`sa`.`assignment_type` AS `assignment_type`,`sa`.`create_by` AS `create_by`,`sa`.`update_by` AS `update_by`,`sa`.`create_log` AS `create_log`,`sa`.`update_log` AS `update_log` from `tmhrm`.`tma_so_ass` `sa`;

-- ----------------------------
-- View structure for v1_tma_so_ass_detail
-- ----------------------------
DROP VIEW IF EXISTS `v1_tma_so_ass_detail`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_tma_so_ass_detail` AS select `sad`.`sa_detail_id` AS `sa_detail_id`,`sad`.`sa_id` AS `sa_id`,`sad`.`employee_id` AS `employee_id`,`sad`.`period_id` AS `period_id`,`sad`.`shift_id` AS `shift_id`,`sad`.`ta_start` AS `ta_start`,`sad`.`ta_end` AS `ta_end`,`sad`.`extend_in` AS `extend_in`,`sad`.`extend_out` AS `extend_out`,`sad`.`emcall_in` AS `emcall_in`,`sad`.`emcall_out` AS `emcall_out`,`sad`.`ta_duration` AS `ta_duration`,`sad`.`machine_id` AS `machine_id`,`sad`.`notes` AS `notes`,`sad`.`create_by` AS `create_by`,`sad`.`update_by` AS `update_by`,`sad`.`create_log` AS `create_log`,`sad`.`update_log` AS `update_log` from `tmhrm`.`tma_so_ass_detail` `sad`;

-- ----------------------------
-- View structure for v_booking_detail
-- ----------------------------
DROP VIEW IF EXISTS `v_booking_detail`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_booking_detail` AS select `wm`.`worm_id` AS `worm_id`,`wm`.`rawmat_id` AS `rawmat_id`,`wo`.`wo_no` AS `wo_no`,`wo`.`wo_date` AS `wo_date`,`wo`.`co_no` AS `co_no`,`co`.`co_date` AS `co_date`,`co`.`customer_id` AS `customer_id`,`wm`.`qty_usage` AS `qty_usage`,`wm`.`qty_consumtion` AS `qty_consumtion`,`wm`.`uom_usage` AS `uom_usage`,`wo`.`status` AS `wo_status` from ((`prd_wo_material` `wm` join `prd_wo` `wo` on((`wm`.`wo_id` = `wo`.`wo_id`))) left join `tmsdi`.`s_co` `co` on((`wo`.`co_id` = `co`.`doc_id`)));

-- ----------------------------
-- View structure for v_booking_master
-- ----------------------------
DROP VIEW IF EXISTS `v_booking_master`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_booking_master` AS select distinct `wm`.`rawmat_id` AS `rawmat_id`,`tminv`.`m`.`material_code` AS `material_code`,`tminv`.`m`.`material_name` AS `material_name` from ((`prd_wo_material` `wm` join `prd_wo` `wo` on((`wm`.`wo_id` = `wo`.`wo_id`))) join `tminv`.`v1_m_raw_material` `m` on((`wm`.`rawmat_id` = `tminv`.`m`.`material_id`))) where (`wo`.`status` = 'READY');

-- ----------------------------
-- Procedure structure for sp_updwo
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_updwo`;
delimiter ;;
CREATE PROCEDURE `sp_updwo`()
BEGIN
	#Routine body goes here...
  
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
