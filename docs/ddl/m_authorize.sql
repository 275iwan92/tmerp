/*
 Navicat Premium Dump SQL

 Source Server         : TM 188
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46-0ubuntu0.24.04.4)
 Source Host           : 103.79.247.146:23306
 Source Schema         : tmmst

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46-0ubuntu0.24.04.4)
 File Encoding         : 65001

 Date: 30/09/2026 18:18:02
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for m_authorize
-- ----------------------------
DROP TABLE IF EXISTS `m_authorize`;
CREATE TABLE `m_authorize`  (
  `auth_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'authorize ID',
  `auth_module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'nama module misal PR atau PO atau CO',
  `auth_total_level` tinyint UNSIGNED NULL DEFAULT 0 COMMENT 'total level approval, 0 = tidak ada approval, 1 = level nya hanya atasan langsung',
  `notes` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Keterangan',
  `is_active` tinyint UNSIGNED NULL DEFAULT 1 COMMENT 'active atau tidak',
  `create_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu pembuatan',
  `update_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu update dokumen',
  `create_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat',
  `update_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir update',
  PRIMARY KEY (`auth_id`) USING BTREE,
  UNIQUE INDEX `auth_module`(`auth_module` ASC, `is_active` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master konfigurasi mekanisme approval per module' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_authorize
-- ----------------------------
INSERT INTO `m_authorize` VALUES (1, 'PR', 1, 'Purchase Request', 0, '2026-07-01 14:01:33', '2026-07-01 14:01:33', '_system', '');
INSERT INTO `m_authorize` VALUES (2, 'DN', 1, 'Delivery Note', 0, '2026-04-22 13:45:32', '2026-04-22 13:45:32', '_system', '_system');
INSERT INTO `m_authorize` VALUES (3, 'PO', 2, 'Purchase Order', 1, '2026-02-16 13:00:34', '2026-02-16 13:00:34', '_system', '_system');
INSERT INTO `m_authorize` VALUES (4, 'PV', 1, 'Purchase Receive', 0, '2026-04-22 13:45:23', '2026-04-22 13:45:23', '_system', '_system');
INSERT INTO `m_authorize` VALUES (5, 'CO', 1, 'Customer Order', 1, '2026-04-22 07:00:00', '2026-04-22 07:00:00', '_system', 'nisa');
INSERT INTO `m_authorize` VALUES (6, 'HR', 1, 'Human Resource', 1, '2026-02-16 13:11:29', '2026-02-16 13:11:29', '_system', '_system');
INSERT INTO `m_authorize` VALUES (7, 'DO', 0, 'Delivery Order', 0, '2026-04-21 14:12:26', '2026-04-21 14:12:26', 'TEST', 'TEST');
INSERT INTO `m_authorize` VALUES (8, 'OP', 1, 'Stock Opname', 1, '2026-07-10 10:50:26', '2026-07-10 10:50:26', 'TEST', 'TEST');
INSERT INTO `m_authorize` VALUES (9, 'AD', 1, 'Stock Adjustment', 1, '2026-07-10 10:51:15', '2026-07-10 10:51:15', 'TEST', 'TEST');

-- ----------------------------
-- Table structure for m_authorize_detail
-- ----------------------------
DROP TABLE IF EXISTS `m_authorize_detail`;
CREATE TABLE `m_authorize_detail`  (
  `auth_detail_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `auth_id` bigint UNSIGNED NULL DEFAULT NULL,
  `auth_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'login name yang authorize',
  `auth_level` int UNSIGNED NULL DEFAULT NULL COMMENT 'level authorize',
  `auth_label_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama lengkap yang akan tercetak di dokumen',
  `auth_label_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama jabatan yang akan tercetak di dokumen',
  `auth_label_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Label authorize yang akan tercetak di dokumen misal Menyetujui, Mengetahui, Membuat',
  `create_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu pembuatan',
  `update_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu update dokumen',
  `create_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat',
  `update_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir update',
  PRIMARY KEY (`auth_detail_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 58 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail user/level yang diizinkan approve pada m_authorize' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_authorize_detail
-- ----------------------------
INSERT INTO `m_authorize_detail` VALUES (1, 1, 'wiwik', 1, 'Wiwik', 'Purchasing', 'Staff', '2026-01-07 13:36:13', '2026-01-07 13:36:13', '_system', '_system');
INSERT INTO `m_authorize_detail` VALUES (5, 3, 'wiwik', 1, 'Wiwik', 'Purchasing', 'Staff', '2026-02-16 12:50:25', '2026-02-16 12:50:25', NULL, NULL);
INSERT INTO `m_authorize_detail` VALUES (6, 3, 'nisa', 2, 'Annisa Kusumaning', 'Operational Manager', 'Manager', '2026-02-16 12:50:44', '2026-02-16 12:50:44', NULL, NULL);
INSERT INTO `m_authorize_detail` VALUES (7, 2, 'yuna', 1, 'Yuna Puji Astuti', 'Marketing Coordinator', 'Staff', '2026-02-16 12:52:01', '2026-02-16 12:52:01', NULL, NULL);
INSERT INTO `m_authorize_detail` VALUES (8, 2, 'nisa', 2, 'Annisa Kusumaning', 'Operational Manager', 'Manager', '2026-02-16 12:52:15', '2026-02-16 12:52:15', NULL, NULL);
INSERT INTO `m_authorize_detail` VALUES (9, 6, 'sania', 1, 'Sania', 'Hurman Resource', 'Staff', '2026-02-16 12:53:26', '2026-02-16 12:53:26', NULL, NULL);
INSERT INTO `m_authorize_detail` VALUES (10, 6, 'nisa', 2, 'Annisa Kusumaning', 'Operational Manager', 'Manager', '2026-02-16 12:53:50', '2026-02-16 12:53:50', NULL, NULL);
INSERT INTO `m_authorize_detail` VALUES (55, 5, 'nisa', 1, 'Annisa', '', '', NULL, NULL, '', '');
INSERT INTO `m_authorize_detail` VALUES (56, 8, 'arinda', 1, 'Arinda', '', '', '2026-07-10 10:54:17', '2026-07-10 10:54:17', 'TEST', 'TEST');
INSERT INTO `m_authorize_detail` VALUES (57, 9, 'nisa', 1, 'Annisa Kusumaning', 'Operational Manager', 'Manager', '2026-07-10 10:54:17', '2026-07-10 10:54:17', 'TEST', 'TEST');

SET FOREIGN_KEY_CHECKS = 1;
