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

 Date: 17/09/2026 15:47:06
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for doc_rmprefix1
-- ----------------------------
DROP TABLE IF EXISTS `doc_rmprefix1`;
CREATE TABLE `doc_rmprefix1`  (
  `idprefix` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix1` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  PRIMARY KEY (`idprefix`) USING BTREE,
  UNIQUE INDEX `prefix1`(`prefix1` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master prefix level 1 untuk kategori bahan baku (raw material)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of doc_rmprefix1
-- ----------------------------
INSERT INTO `doc_rmprefix1` VALUES (1, 'Paper', 'P', 'Segala jenis Kertas');
INSERT INTO `doc_rmprefix1` VALUES (2, 'Sheet', 'S', 'Segala jenis Sheet');
INSERT INTO `doc_rmprefix1` VALUES (3, 'Ink', 'I', 'Segala Jenis Tinta');
INSERT INTO `doc_rmprefix1` VALUES (4, 'Glue', 'G', 'Segala Jenis Lem');
INSERT INTO `doc_rmprefix1` VALUES (5, 'Gasoline', 'L', 'Segala Jenis Bahan Bakar');
INSERT INTO `doc_rmprefix1` VALUES (6, 'Solasi', 'A', 'Segala Jenis Solasi');
INSERT INTO `doc_rmprefix1` VALUES (7, 'Plastik', 'K', 'Segala Jenis Plastik');
INSERT INTO `doc_rmprefix1` VALUES (8, 'Chemical Liquid', 'Q', 'Segala jenis Bahan Kimia Cair');
INSERT INTO `doc_rmprefix1` VALUES (9, 'Chemical Powder', 'R', 'Segala jenis Bahan Kimia Bubuk');
INSERT INTO `doc_rmprefix1` VALUES (10, 'CTP Plate', 'T', 'Segala Jenis Plat CTP');
INSERT INTO `doc_rmprefix1` VALUES (11, 'Pack Material', 'C', 'Segala Jenis Kebutuhan Packing');
INSERT INTO `doc_rmprefix1` VALUES (12, 'Stationery / ATK', 'Y', 'Segala Jenis Alat Tulis Kantor');
INSERT INTO `doc_rmprefix1` VALUES (13, 'Spare Part Vehicle', 'V', 'Segala Jenis Komponen Kendaraan');
INSERT INTO `doc_rmprefix1` VALUES (14, 'Spare Part Machine', 'H', 'Segala Jenis Komponen Mesin');
INSERT INTO `doc_rmprefix1` VALUES (15, 'Tools / Equipment', 'E', 'Segala Jenis Tools buat Maintenance');
INSERT INTO `doc_rmprefix1` VALUES (16, 'Laptop/Desktop/Networking', 'Z', 'Segala Jenis Komputer dan Jaringan');
INSERT INTO `doc_rmprefix1` VALUES (17, 'Jasa/ Service', 'J', 'Segala urusan Jasa / service');
INSERT INTO `doc_rmprefix1` VALUES (18, 'Peralatan Kebersihan', 'B', 'Segala jenis kebutuhan kebersihan');

-- ----------------------------
-- Table structure for doc_rmprefix2
-- ----------------------------
DROP TABLE IF EXISTS `doc_rmprefix2`;
CREATE TABLE `doc_rmprefix2`  (
  `idprefix` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix1` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix2` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  PRIMARY KEY (`idprefix`) USING BTREE,
  UNIQUE INDEX `prefix1`(`prefix1` ASC, `prefix2` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 85 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master prefix level 2 untuk kategori bahan baku' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of doc_rmprefix2
-- ----------------------------
INSERT INTO `doc_rmprefix2` VALUES (1, 'Paper', 'Ambri', 'P', 'A', NULL);
INSERT INTO `doc_rmprefix2` VALUES (2, 'Paper', 'Art Papper', 'P', 'R', NULL);
INSERT INTO `doc_rmprefix2` VALUES (3, 'Paper', 'Art carton', 'P', 'C', NULL);
INSERT INTO `doc_rmprefix2` VALUES (4, 'Paper', 'Duplex', 'P', 'D', NULL);
INSERT INTO `doc_rmprefix2` VALUES (5, 'Paper', 'Kraft', 'P', 'K', NULL);
INSERT INTO `doc_rmprefix2` VALUES (6, 'Paper', 'Ivory', 'P', 'Y', NULL);
INSERT INTO `doc_rmprefix2` VALUES (7, 'Paper', 'Samson kraft', 'P', 'S', NULL);
INSERT INTO `doc_rmprefix2` VALUES (8, 'Paper', 'Bleach sakraft', 'P', 'B', NULL);
INSERT INTO `doc_rmprefix2` VALUES (9, 'Paper', 'Stiker bontax', 'P', 'X', NULL);
INSERT INTO `doc_rmprefix2` VALUES (10, 'Paper', 'Stiker bontax transparant', 'P', 'T', NULL);
INSERT INTO `doc_rmprefix2` VALUES (11, 'Paper', 'Stiker vinyl', 'P', 'V', NULL);
INSERT INTO `doc_rmprefix2` VALUES (12, 'Paper', 'Stiker vinyl transparant', 'P', 'N', NULL);
INSERT INTO `doc_rmprefix2` VALUES (13, 'Paper', 'Flex banner', 'P', 'F', NULL);
INSERT INTO `doc_rmprefix2` VALUES (20, 'Solasi', 'Lebar 2.4cm', 'A', 'S', NULL);
INSERT INTO `doc_rmprefix2` VALUES (21, 'Solasi', 'Lebar 4.8cm', 'A', 'L', NULL);
INSERT INTO `doc_rmprefix2` VALUES (22, 'Gasoline', 'Solar', 'L', 'S', NULL);
INSERT INTO `doc_rmprefix2` VALUES (23, 'Gasoline', 'Bio Solar', 'L', 'B', NULL);
INSERT INTO `doc_rmprefix2` VALUES (24, 'Gasoline', 'Pertamax', 'L', 'X', NULL);
INSERT INTO `doc_rmprefix2` VALUES (25, 'Gasoline', 'Premium', 'L', 'P', NULL);
INSERT INTO `doc_rmprefix2` VALUES (26, 'Chemical', 'Wash', 'Q', 'W', NULL);
INSERT INTO `doc_rmprefix2` VALUES (27, 'Chemical', 'Plate Cleaner', 'Q', 'C', NULL);
INSERT INTO `doc_rmprefix2` VALUES (29, 'Ink', 'Versafire', 'I', 'V', NULL);
INSERT INTO `doc_rmprefix2` VALUES (30, 'Ink', 'Times', 'I', 'T', NULL);
INSERT INTO `doc_rmprefix2` VALUES (31, 'Ink', 'Sunthink', 'I', 'S', NULL);
INSERT INTO `doc_rmprefix2` VALUES (32, 'Ink', 'Hanway', 'I', 'H', NULL);
INSERT INTO `doc_rmprefix2` VALUES (33, 'Ink', 'Printer', 'I', 'P', NULL);
INSERT INTO `doc_rmprefix2` VALUES (34, 'Glue', 'Laminasi Youbond', 'G', 'Y', NULL);
INSERT INTO `doc_rmprefix2` VALUES (35, 'Glue', 'Folder', 'G', 'F', NULL);
INSERT INTO `doc_rmprefix2` VALUES (36, 'Glue', 'Plong', 'G', 'P', NULL);
INSERT INTO `doc_rmprefix2` VALUES (37, 'Peralatan Kebersihan', 'Majun', 'B', 'M', NULL);
INSERT INTO `doc_rmprefix2` VALUES (38, 'Stationery / ATK', 'Map ATK', 'Y', 'M', NULL);
INSERT INTO `doc_rmprefix2` VALUES (39, 'Pack Material', 'Karet', 'C', 'T', NULL);
INSERT INTO `doc_rmprefix2` VALUES (40, 'Pack Material', 'Paper Pack', 'C', 'P', NULL);
INSERT INTO `doc_rmprefix2` VALUES (41, 'CTP Plate', 'PLATE AIYINDA', 'T', 'P', '');
INSERT INTO `doc_rmprefix2` VALUES (42, 'Paper', 'HVS', 'P', '4', '');
INSERT INTO `doc_rmprefix2` VALUES (43, 'Sheet', 'SHBF', 'S', 'B', '');
INSERT INTO `doc_rmprefix2` VALUES (44, 'Sheet', 'SHEF', 'S', 'E', '');
INSERT INTO `doc_rmprefix2` VALUES (45, 'Sheet', 'SFEF', 'S', 'F', '');
INSERT INTO `doc_rmprefix2` VALUES (46, 'Sheet', 'BCF', 'S', 'C', '');
INSERT INTO `doc_rmprefix2` VALUES (47, 'Sheet', 'DF', 'S', '4', '');
INSERT INTO `doc_rmprefix2` VALUES (48, 'Chemical Powder', 'DRYUP', 'R', 'D', '');
INSERT INTO `doc_rmprefix2` VALUES (49, 'Chemical Liquid', 'ABC WASH', 'Q', 'A', '');
INSERT INTO `doc_rmprefix2` VALUES (50, 'Chemical Liquid', 'BOTTCHERIN S 3007 K', 'Q', 'B', '');
INSERT INTO `doc_rmprefix2` VALUES (51, 'CTP Plate', 'BLANKET ', 'T', 'S', '');
INSERT INTO `doc_rmprefix2` VALUES (52, 'Plastik', 'R8', 'K', '8', '');
INSERT INTO `doc_rmprefix2` VALUES (53, 'Plastik', 'R5', 'K', '5', '');
INSERT INTO `doc_rmprefix2` VALUES (54, 'Plastik', 'R10', 'K', '0', '');
INSERT INTO `doc_rmprefix2` VALUES (55, 'Plastik', 'R3', 'K', '3', '');
INSERT INTO `doc_rmprefix2` VALUES (56, 'Ink', 'ALSAMSI DARK BLUE', 'I', 'D', '');
INSERT INTO `doc_rmprefix2` VALUES (57, 'Ink', 'SUNTHINK YELLOW', 'I', 'R', '');
INSERT INTO `doc_rmprefix2` VALUES (58, 'Ink', 'SUNTHINK BLACK', 'I', 'B', '');
INSERT INTO `doc_rmprefix2` VALUES (59, 'Ink', 'DGM', 'I', 'G', '');
INSERT INTO `doc_rmprefix2` VALUES (60, 'Ink', 'DGM PASTEL GREEN', 'I', '2', '');
INSERT INTO `doc_rmprefix2` VALUES (61, 'Ink', 'OFFSET', 'I', 'O', '');
INSERT INTO `doc_rmprefix2` VALUES (62, 'CTP Plate', 'BUSA ', 'T', 'B', '');
INSERT INTO `doc_rmprefix2` VALUES (63, 'Stationery / ATK', 'DOUBLE TAPE', 'Y', 'D', '');
INSERT INTO `doc_rmprefix2` VALUES (64, 'Chemical Powder', 'DRIYER', 'R', 'Y', '');
INSERT INTO `doc_rmprefix2` VALUES (65, 'Chemical Liquid', 'BOTTCHERIN FR 1000', 'Q', '2', '');
INSERT INTO `doc_rmprefix2` VALUES (66, 'Chemical Liquid', 'BOTTCHER PRO AR', 'Q', '', '');
INSERT INTO `doc_rmprefix2` VALUES (67, 'Chemical Liquid', 'OFFSET', 'Q', '5', '');
INSERT INTO `doc_rmprefix2` VALUES (68, 'CTP Plate', 'SPONGE', 'T', '5', '');
INSERT INTO `doc_rmprefix2` VALUES (69, 'Plastik', 'R3', 'K', '4', '');
INSERT INTO `doc_rmprefix2` VALUES (70, 'Plastik', 'S2', 'K', 'S', '');
INSERT INTO `doc_rmprefix2` VALUES (71, 'CTP Plate', 'AIYINDA', 'T', 'A', '');
INSERT INTO `doc_rmprefix2` VALUES (72, 'Chemical Liquid', 'UV', 'Q', 'U', '');
INSERT INTO `doc_rmprefix2` VALUES (73, 'Chemical Liquid', 'FOUNTAIN DECO', 'Q', 'F', '');
INSERT INTO `doc_rmprefix2` VALUES (74, 'Chemical Liquid', 'FEBOLUBE', 'Q', 'V', '');
INSERT INTO `doc_rmprefix2` VALUES (75, 'Chemical Liquid', 'GUM SOLUTION', 'Q', 'G', '');
INSERT INTO `doc_rmprefix2` VALUES (76, 'Chemical Liquid', 'CLAENER', 'Q', 'P', '');
INSERT INTO `doc_rmprefix2` VALUES (77, 'Glue', 'OFFSET', 'G', 'O', '');
INSERT INTO `doc_rmprefix2` VALUES (78, 'Stationery / ATK', 'ISI CUTTER', 'Y', 'C', '');
INSERT INTO `doc_rmprefix2` VALUES (79, 'Chemical Liquid', 'WASH RWA', 'Q', 'R', '');
INSERT INTO `doc_rmprefix2` VALUES (80, 'Peralatan Kebersihan', 'SABUN WING', 'B', 'W', '');
INSERT INTO `doc_rmprefix2` VALUES (81, 'Pack Material', 'WRAPPING', 'C', 'W', '');
INSERT INTO `doc_rmprefix2` VALUES (82, 'Pack Material', 'KARET', 'C', 'K', '');
INSERT INTO `doc_rmprefix2` VALUES (83, 'Peralatan Kebersihan', 'MAJUN', 'B', 'J', '');
INSERT INTO `doc_rmprefix2` VALUES (84, 'Chemical Liquid', 'OP VARNISH', 'Q', 'O', '');

-- ----------------------------
-- Table structure for doc_rmprefix3
-- ----------------------------
DROP TABLE IF EXISTS `doc_rmprefix3`;
CREATE TABLE `doc_rmprefix3`  (
  `idprefix` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix1` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix2` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix3` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  PRIMARY KEY (`idprefix`) USING BTREE,
  UNIQUE INDEX `prefix1`(`prefix1` ASC, `prefix2` ASC, `prefix3` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 143 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master prefix level 3 untuk kategori bahan baku' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of doc_rmprefix3
-- ----------------------------
INSERT INTO `doc_rmprefix3` VALUES (1, 'Paper', 'Ambri', '0gr', 'P', 'A', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (2, 'Paper', 'Ambri', '0gr', 'P', 'A', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (3, 'Paper', 'Art Papper', '85gr', 'P', 'R', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (4, 'Paper', 'Art Papper', '120gr', 'P', 'R', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (5, 'Paper', 'Art Papper', '150gr', 'P', 'R', 'C', NULL);
INSERT INTO `doc_rmprefix3` VALUES (6, 'Paper', 'Art carton', '190gr', 'P', 'C', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (7, 'Paper', 'Art carton', '200gr', 'P', 'C', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (8, 'Paper', 'Art carton', '210gr', 'P', 'C', 'C', NULL);
INSERT INTO `doc_rmprefix3` VALUES (9, 'Paper', 'Art carton', '230gr', 'P', 'C', 'D', NULL);
INSERT INTO `doc_rmprefix3` VALUES (10, 'Paper', 'Art carton', '260gr', 'P', 'C', 'E', NULL);
INSERT INTO `doc_rmprefix3` VALUES (11, 'Paper', 'Duplex', '250gr', 'P', 'D', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (12, 'Paper', 'Duplex', '270gr', 'P', 'D', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (13, 'Paper', 'Duplex', '310gr', 'P', 'D', 'C', NULL);
INSERT INTO `doc_rmprefix3` VALUES (14, 'Paper', 'Duplex', '350gr', 'P', 'D', 'D', NULL);
INSERT INTO `doc_rmprefix3` VALUES (15, 'Paper', 'Duplex', '400gr', 'P', 'D', 'E', NULL);
INSERT INTO `doc_rmprefix3` VALUES (16, 'Paper', 'Duplex', '450gr', 'P', 'D', 'F', NULL);
INSERT INTO `doc_rmprefix3` VALUES (17, 'Paper', 'Duplex', '500gr', 'P', 'D', 'G', NULL);
INSERT INTO `doc_rmprefix3` VALUES (18, 'Paper', 'Kraft', '125gr', 'P', 'K', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (19, 'Paper', 'Kraft', '250gr', 'P', 'K', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (20, 'Paper', 'Kraft', '275gr', 'P', 'K', 'C', NULL);
INSERT INTO `doc_rmprefix3` VALUES (21, 'Paper', 'Kraft', '300gr', 'P', 'K', 'D', NULL);
INSERT INTO `doc_rmprefix3` VALUES (22, 'Paper', 'Kraft', '330gr', 'P', 'K', 'E', NULL);
INSERT INTO `doc_rmprefix3` VALUES (23, 'Paper', 'Kraft', '350gr', 'P', 'K', 'F', NULL);
INSERT INTO `doc_rmprefix3` VALUES (24, 'Paper', 'Ivory', '210gr', 'P', 'Y', 'G', NULL);
INSERT INTO `doc_rmprefix3` VALUES (25, 'Paper', 'Ivory', '230gr', 'P', 'Y', 'H', NULL);
INSERT INTO `doc_rmprefix3` VALUES (26, 'Paper', 'Ivory', '250gr', 'P', 'Y', 'I', NULL);
INSERT INTO `doc_rmprefix3` VALUES (27, 'Paper', 'Ivory', '300gr', 'P', 'Y', 'J', NULL);
INSERT INTO `doc_rmprefix3` VALUES (28, 'Paper', 'Ivory', '350gr', 'P', 'Y', 'K', NULL);
INSERT INTO `doc_rmprefix3` VALUES (29, 'Paper', 'Ivory', '400gr', 'P', 'Y', 'L', NULL);
INSERT INTO `doc_rmprefix3` VALUES (30, 'Paper', 'Samson kraft', '70gr', 'P', 'S', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (31, 'Paper', 'Samson kraft', '80gr', 'P', 'S', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (32, 'Paper', 'Samson kraft', '100gr', 'P', 'S', 'C', NULL);
INSERT INTO `doc_rmprefix3` VALUES (33, 'Paper', 'Bleach sakraft', '90gr', 'P', 'B', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (34, 'Paper', 'Bleach sakraft', '100gr', 'P', 'B', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (35, 'Paper', 'Stiker bontax', '0gr', 'P', 'X', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (36, 'Paper', 'Stiker bontax transparant', '0gr', 'P', 'T', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (37, 'Paper', 'Stiker vinyl', '0gr', 'P', 'V', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (38, 'Paper', 'Stiker vinyl transparant', '0gr', 'P', 'N', 'B', NULL);
INSERT INTO `doc_rmprefix3` VALUES (39, 'Paper', 'Flex banner', '0gr', 'P', 'F', 'A', NULL);
INSERT INTO `doc_rmprefix3` VALUES (75, 'CTP Plate', 'PLATE AIYINDA', 'BESAR', 'T', 'P', 'B', '');
INSERT INTO `doc_rmprefix3` VALUES (76, 'Paper', 'Art carton', '310gr', 'P', 'C', 'F', '');
INSERT INTO `doc_rmprefix3` VALUES (77, 'Paper', 'Kraft', '265gr', 'P', 'K', 'N', '');
INSERT INTO `doc_rmprefix3` VALUES (79, 'Paper', 'HVS', '60 GR ', 'P', '4', '1', '');
INSERT INTO `doc_rmprefix3` VALUES (80, 'Paper', 'HVS', '58 GR', 'P', '4', '4', '');
INSERT INTO `doc_rmprefix3` VALUES (81, 'Paper', 'HVS', '85 GR', 'P', '4', '5', '');
INSERT INTO `doc_rmprefix3` VALUES (83, 'Paper', 'HVS', '90 GR', 'P', '4', '3', '');
INSERT INTO `doc_rmprefix3` VALUES (84, 'Paper', 'Ivory', '190 GR ', 'P', 'Y', '1', '');
INSERT INTO `doc_rmprefix3` VALUES (85, 'Paper', 'Ivory', '210 GR', 'P', 'Y', '2', '');
INSERT INTO `doc_rmprefix3` VALUES (86, 'Paper', 'Ivory', '230 GR', 'P', 'Y', '3', '');
INSERT INTO `doc_rmprefix3` VALUES (87, 'Paper', 'Ivory', '250 GR', 'P', 'Y', '4', '');
INSERT INTO `doc_rmprefix3` VALUES (88, 'Paper', 'Ivory', '270 GR', 'P', 'Y', '5', '');
INSERT INTO `doc_rmprefix3` VALUES (89, 'Paper', 'Kraft', '290gr', 'P', 'K', 'M', '');
INSERT INTO `doc_rmprefix3` VALUES (90, 'Paper', 'Ivory', '270 GR ', 'P', 'Y', '6', '');
INSERT INTO `doc_rmprefix3` VALUES (91, 'Paper', 'Ivory', '300 GR ', 'P', 'Y', '7', '');
INSERT INTO `doc_rmprefix3` VALUES (92, 'Paper', 'Kraft', '70gr', 'P', 'K', 'O', '');
INSERT INTO `doc_rmprefix3` VALUES (93, 'Paper', 'Duplex', '300gr', 'P', 'D', 'H', '');
INSERT INTO `doc_rmprefix3` VALUES (94, 'Sheet', 'SHBF', 'K275/M200/K275', 'S', 'B', '1', '');
INSERT INTO `doc_rmprefix3` VALUES (95, 'Sheet', 'SHBF', 'K125/M125/M125', 'S', 'B', '2', '');
INSERT INTO `doc_rmprefix3` VALUES (96, 'Sheet', 'SHBF', 'K150/M150/K200', 'S', 'B', '3', '');
INSERT INTO `doc_rmprefix3` VALUES (97, 'Sheet', 'SHBF', 'M125/K125/K125', 'S', 'B', '4', '');
INSERT INTO `doc_rmprefix3` VALUES (98, 'Sheet', 'SHBF', 'M125/M125/K125', 'S', 'B', '5', '');
INSERT INTO `doc_rmprefix3` VALUES (99, 'Sheet', 'SHBF', 'M125/K125', 'S', 'B', '6', '');
INSERT INTO `doc_rmprefix3` VALUES (100, 'Sheet', 'SHBF', 'M125X3/K125', 'S', 'B', '7', '');
INSERT INTO `doc_rmprefix3` VALUES (101, 'Sheet', 'BCF', 'K125/M125/K125', 'S', 'C', '7', '');
INSERT INTO `doc_rmprefix3` VALUES (102, 'Sheet', 'BCF', 'M125/K125X3/K125', 'S', 'C', '8', '');
INSERT INTO `doc_rmprefix3` VALUES (103, 'Sheet', 'SFEF', 'K1250/M125/K200', 'S', 'F', '9', '');
INSERT INTO `doc_rmprefix3` VALUES (104, 'Sheet', 'SFEF', 'K125/M125X3/K125', 'S', 'F', '1', '');
INSERT INTO `doc_rmprefix3` VALUES (105, 'Sheet', 'DF', 'K125/M125X3/K125', 'S', '4', '4', '');
INSERT INTO `doc_rmprefix3` VALUES (106, 'Sheet', 'BCF', 'K125/M125X3/K125', 'S', 'C', '4', '');
INSERT INTO `doc_rmprefix3` VALUES (107, 'Sheet', 'BCF', 'M125/K125X3/K125', 'S', 'C', '2', '');
INSERT INTO `doc_rmprefix3` VALUES (108, 'Sheet', 'SHBF', 'K150/M125/K200', 'S', 'B', '9', '');
INSERT INTO `doc_rmprefix3` VALUES (109, 'Sheet', 'BCF', 'M125/M125X3/K125', 'S', 'C', '3', '');
INSERT INTO `doc_rmprefix3` VALUES (110, 'Sheet', 'SHEF', 'K150/M150/K200', 'S', 'E', '7', '');
INSERT INTO `doc_rmprefix3` VALUES (111, 'Solasi', 'Lebar 2.4cm', 'KECIL', 'A', 'S', 'K', '');
INSERT INTO `doc_rmprefix3` VALUES (112, 'Solasi', 'Lebar 4.8cm', 'BESAR', 'A', 'L', 'B', '');
INSERT INTO `doc_rmprefix3` VALUES (114, 'Ink', 'OFFSET', 'DGM', 'I', 'O', 'D', '');
INSERT INTO `doc_rmprefix3` VALUES (116, 'Ink', 'OFFSET', 'HN', 'I', 'O', 'H', '');
INSERT INTO `doc_rmprefix3` VALUES (117, 'CTP Plate', 'BLANKET ', 'BESAR', 'T', 'S', 'B', '');
INSERT INTO `doc_rmprefix3` VALUES (118, 'CTP Plate', 'BLANKET ', 'CITO ', 'T', 'S', 'C', '');
INSERT INTO `doc_rmprefix3` VALUES (119, 'Stationery / ATK', 'DOUBLE TAPE', 'BESAR', 'Y', 'D', 'B', '');
INSERT INTO `doc_rmprefix3` VALUES (120, 'Stationery / ATK', 'DOUBLE TAPE', 'KECIL', 'Y', 'D', 'K', '');
INSERT INTO `doc_rmprefix3` VALUES (121, 'Chemical Liquid', 'OFFSET', 'ANTI SCRATCHING AGENT', 'Q', '5', '3', '');
INSERT INTO `doc_rmprefix3` VALUES (122, 'CTP Plate', 'SPONGE', 'BUSA', 'T', '5', 'B', '');
INSERT INTO `doc_rmprefix3` VALUES (123, 'CTP Plate', 'SPONGE', 'EVA', 'T', '5', 'E', '');
INSERT INTO `doc_rmprefix3` VALUES (124, 'CTP Plate', 'SPONGE', 'VISCOVISTA', 'T', '5', 'V', '');
INSERT INTO `doc_rmprefix3` VALUES (125, 'Ink', 'OFFSET', 'RAPIDA', 'I', 'O', 'R', '');
INSERT INTO `doc_rmprefix3` VALUES (126, 'CTP Plate', 'AIYINDA', '103 X 800', 'T', 'A', '5', '');
INSERT INTO `doc_rmprefix3` VALUES (127, 'CTP Plate', 'AIYINDA', '57 X 51', 'T', 'A', '8', '');
INSERT INTO `doc_rmprefix3` VALUES (128, 'Ink', 'OFFSET', 'OSM', 'I', 'O', 'O', '');
INSERT INTO `doc_rmprefix3` VALUES (129, 'Ink', 'OFFSET', 'AMBRI', 'I', 'O', 'A', '');
INSERT INTO `doc_rmprefix3` VALUES (130, 'Chemical Liquid', 'UV', 'VARNISH', 'Q', 'U', 'A', '');
INSERT INTO `doc_rmprefix3` VALUES (131, 'Ink', 'OFFSET', 'HAEMA', 'I', 'O', 'I', '');
INSERT INTO `doc_rmprefix3` VALUES (132, 'Chemical Liquid', 'CLAENER', 'PLATE CLEANER', 'Q', 'P', 'C', '');
INSERT INTO `doc_rmprefix3` VALUES (133, 'Ink', 'OFFSET', 'POPM', 'I', 'O', 'P', '');
INSERT INTO `doc_rmprefix3` VALUES (134, 'Glue', 'OFFSET', 'LAM LEMINASI', 'G', 'O', 'L', '');
INSERT INTO `doc_rmprefix3` VALUES (135, 'Glue', 'OFFSET', 'RAJAWALI KUNING', 'G', 'O', 'R', '');
INSERT INTO `doc_rmprefix3` VALUES (136, 'Glue', 'OFFSET', 'PF', 'G', 'O', 'P', '');
INSERT INTO `doc_rmprefix3` VALUES (137, 'Stationery / ATK', 'ISI CUTTER', 'BESAR', 'Y', 'C', 'B', '');
INSERT INTO `doc_rmprefix3` VALUES (138, 'Stationery / ATK', 'ISI CUTTER', 'KECIL', 'Y', 'C', 'K', '');
INSERT INTO `doc_rmprefix3` VALUES (139, 'Sheet', 'SFEF', 'M125/K125', 'S', 'F', '3', '');
INSERT INTO `doc_rmprefix3` VALUES (140, 'Sheet', 'SFEF', 'WK150/M125', 'S', 'F', 'W', '');
INSERT INTO `doc_rmprefix3` VALUES (141, 'Sheet', 'BCF', 'K125/M125X3/M125', 'S', 'C', 'K', '');
INSERT INTO `doc_rmprefix3` VALUES (142, 'Sheet', 'DF', 'M125X3/K125', 'S', '4', '7', '');

-- ----------------------------
-- Table structure for doc_rmprefix4
-- ----------------------------
DROP TABLE IF EXISTS `doc_rmprefix4`;
CREATE TABLE `doc_rmprefix4`  (
  `idprefix` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category4` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix1` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix2` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix3` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix4` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  PRIMARY KEY (`idprefix`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 220 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master prefix level 4 untuk kategori bahan baku' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of doc_rmprefix4
-- ----------------------------
INSERT INTO `doc_rmprefix4` VALUES (9, 'Paper', 'Art carton', '210gr', '61 X 86 CM', 'P', 'C', 'C', '01', '');
INSERT INTO `doc_rmprefix4` VALUES (10, 'Paper', 'Art carton', '210gr', '65  X 100 CM', 'P', 'C', 'C', '02', '');
INSERT INTO `doc_rmprefix4` VALUES (11, 'Paper', 'Art carton', '210gr', '79 X 109 CM', 'P', 'C', 'C', '03', '');
INSERT INTO `doc_rmprefix4` VALUES (12, 'Paper', 'Art carton', '210gr', '65 X 98 CM', 'P', 'C', 'C', '04', '');
INSERT INTO `doc_rmprefix4` VALUES (13, 'Paper', 'Art carton', '230gr', '79 X 109 CM', 'P', 'C', 'D', '05', '');
INSERT INTO `doc_rmprefix4` VALUES (14, 'Paper', 'Art carton', '260gr', '65  X 100 CM', 'P', 'C', 'E', '06', '');
INSERT INTO `doc_rmprefix4` VALUES (15, 'Paper', 'Art carton', '260gr', '79 X 109 CM', 'P', 'C', 'E', '07', '');
INSERT INTO `doc_rmprefix4` VALUES (16, 'Paper', 'Art carton', '310gr', '79 X 109 CM', 'P', 'C', 'F', '08', '');
INSERT INTO `doc_rmprefix4` VALUES (17, 'Paper', 'Art Papper', '120gr', '65  X 100 CM', 'P', 'R', 'B', '01', '');
INSERT INTO `doc_rmprefix4` VALUES (19, 'Paper', 'Art Papper', '120gr', '79 X 109 CM', 'P', 'R', 'B', '02', '');
INSERT INTO `doc_rmprefix4` VALUES (20, 'Paper', 'Art Papper', '150gr', '65  X 100 CM', 'P', 'R', 'C', '04', '');
INSERT INTO `doc_rmprefix4` VALUES (21, 'Paper', 'Art Papper', '150gr', '65 X 90 CM', 'P', 'R', 'C', '05', '');
INSERT INTO `doc_rmprefix4` VALUES (22, 'Paper', 'Art Papper', '85gr', '65  X 100 CM', 'P', 'R', 'A', '06', '');
INSERT INTO `doc_rmprefix4` VALUES (23, 'Paper', 'Art Papper', '85gr', '79 X 109 CM', 'P', 'R', 'A', '07', '');
INSERT INTO `doc_rmprefix4` VALUES (24, 'Paper', 'Kraft', '275gr', '90 X 109 CM', 'P', 'K', 'C', '01', '');
INSERT INTO `doc_rmprefix4` VALUES (26, 'Paper', 'Kraft', '125gr', '90 X 120 CM', 'P', 'K', 'A', '02', '');
INSERT INTO `doc_rmprefix4` VALUES (27, 'Paper', 'Kraft', '250gr', '101 X 77 CM', 'P', 'K', 'B', '03', '');
INSERT INTO `doc_rmprefix4` VALUES (28, 'Paper', 'Kraft', '250gr', '105 X 97 CM', 'P', 'K', 'B', '04', '');
INSERT INTO `doc_rmprefix4` VALUES (29, 'Paper', 'Kraft', '250gr', '105 X 97,5 CM', 'P', 'K', 'B', '05', '');
INSERT INTO `doc_rmprefix4` VALUES (30, 'Paper', 'Kraft', '250gr', '114 X 85 CM', 'P', 'K', 'B', '06', '');
INSERT INTO `doc_rmprefix4` VALUES (31, 'Paper', 'Kraft', '250gr', '76 X 102 CM', 'P', 'K', 'B', '07', '');
INSERT INTO `doc_rmprefix4` VALUES (32, 'Paper', 'Kraft', '250gr', '79 X 93 CM', 'P', 'K', 'B', '08', '');
INSERT INTO `doc_rmprefix4` VALUES (33, 'Paper', 'Kraft', '250gr', '90 X 23 CM', 'P', 'K', 'B', '09', '');
INSERT INTO `doc_rmprefix4` VALUES (34, 'Paper', 'Kraft', '250gr', '90 X 70 CM', 'P', 'K', 'B', '10', '');
INSERT INTO `doc_rmprefix4` VALUES (35, 'Paper', 'Kraft', '250gr', '95 X 65 CM', 'P', 'K', 'B', '11', '');
INSERT INTO `doc_rmprefix4` VALUES (36, 'Paper', 'Kraft', '250gr', '95 X 66 CM', 'P', 'K', 'B', '12', '');
INSERT INTO `doc_rmprefix4` VALUES (38, 'Paper', 'Kraft', '265gr', '75 X 70 CM', 'P', 'K', 'N', '13', '');
INSERT INTO `doc_rmprefix4` VALUES (39, 'Paper', 'Kraft', '265gr', '79 X 109 CM', 'P', 'K', 'N', '14', '');
INSERT INTO `doc_rmprefix4` VALUES (41, 'Paper', 'Kraft', '275gr', '107 X 89 CM', 'P', 'K', 'C', '15', '');
INSERT INTO `doc_rmprefix4` VALUES (43, 'Paper', 'Kraft', '275gr', '110 X 88,5 CM', 'P', 'K', 'C', '16', '');
INSERT INTO `doc_rmprefix4` VALUES (44, 'Paper', 'HVS', '60 GR ', '65 x 100 CM', 'P', '4', '1', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (45, 'Paper', 'HVS', '58 GR', '65 x 100 CM', 'P', '4', '4', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (46, 'Paper', 'HVS', '85 GR', '65 x 100 CM', 'P', '4', '5', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (47, 'Paper', 'HVS', '90 GR', '65 x 100 CM', 'P', '4', '3', '6', '');
INSERT INTO `doc_rmprefix4` VALUES (48, 'Paper', 'Ivory', '190 GR ', '65 X 75 CM', 'P', 'Y', '1', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (49, 'Paper', 'Ivory', '210 GR', '65 X 110 CM', 'P', 'Y', '2', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (50, 'Paper', 'Ivory', '210 GR', '65 x 100 CM', 'P', 'Y', '2', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (51, 'Paper', 'Ivory', '210 GR', '79 X 109 CM', 'P', 'Y', '2', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (52, 'Paper', 'Ivory', '210 GR', '90 X 120 CM', 'P', 'Y', '2', '6', '');
INSERT INTO `doc_rmprefix4` VALUES (53, 'Paper', 'Kraft', '275gr', '88,5 CM X 109 CM', 'P', 'K', 'C', '17', '');
INSERT INTO `doc_rmprefix4` VALUES (54, 'Paper', 'Kraft', '275gr', '79 X 109 CM', 'P', 'K', 'C', '18', '');
INSERT INTO `doc_rmprefix4` VALUES (55, 'Paper', 'Kraft', '275gr', '79 X 121 CM', 'P', 'K', 'C', '19', '');
INSERT INTO `doc_rmprefix4` VALUES (56, 'Paper', 'Kraft', '275gr', '85 X 112 CM', 'P', 'K', 'C', '20', '');
INSERT INTO `doc_rmprefix4` VALUES (57, 'Paper', 'Kraft', '275gr', '89 X 110 CM', 'P', 'K', 'C', '21', '');
INSERT INTO `doc_rmprefix4` VALUES (58, 'Paper', 'Kraft', '275gr', '79,5 X 121 CM', 'P', 'K', 'C', '22', '');
INSERT INTO `doc_rmprefix4` VALUES (59, 'Paper', 'Kraft', '290gr', '75 X 82 CM', 'P', 'K', 'M', '23', '');
INSERT INTO `doc_rmprefix4` VALUES (60, 'Paper', 'Kraft', '300gr', '79 X 109 CM', 'P', 'K', 'D', '24', '');
INSERT INTO `doc_rmprefix4` VALUES (61, 'Paper', 'Kraft', '300gr', '89 X 110 CM', 'P', 'K', 'D', '25', '');
INSERT INTO `doc_rmprefix4` VALUES (62, 'Paper', 'Kraft', '300gr', '90 X 110 CM', 'P', 'K', 'D', '27', '');
INSERT INTO `doc_rmprefix4` VALUES (63, 'Paper', 'Kraft', '300gr', '90 X 120 CM', 'P', 'K', 'D', '28', '');
INSERT INTO `doc_rmprefix4` VALUES (65, 'Paper', 'Kraft', '350gr', '79 X 109 CM', 'P', 'K', 'F', '26', '');
INSERT INTO `doc_rmprefix4` VALUES (66, 'Paper', 'Ivory', '230 GR', '65 x 100 CM', 'P', 'Y', '3', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (67, 'Paper', 'Ivory', '230gr', '79 X 109 CM', 'P', 'Y', 'H', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (68, 'Paper', 'Ivory', '230 GR', '61,5 X 120 CM', 'P', 'Y', '3', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (69, 'Paper', 'Ivory', '230 GR', '120 X 82 CM', 'P', 'Y', '3', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (71, 'Paper', 'Ivory', '230 GR', '73 X 51 CM', 'P', 'Y', '3', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (72, 'Paper', 'Ivory', '250 GR', '72 X 57 CM', 'P', 'Y', '4', '6', '');
INSERT INTO `doc_rmprefix4` VALUES (73, 'Paper', 'Ivory', '250 GR', '89 X 61 CM ', 'P', 'Y', '4', '7', '');
INSERT INTO `doc_rmprefix4` VALUES (74, 'Paper', 'Ivory', '230 GR', '70 X 95', 'P', 'Y', '3', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (75, 'Paper', 'Ivory', '250 GR', '79 X 109 CM', 'P', 'Y', '4', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (76, 'Paper', 'Ivory', '250 GR', '72 X 57 CM', 'P', 'Y', '4', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (77, 'Paper', 'Ivory', '250 GR', '89 X 61 CM ', 'P', 'Y', '4', '9', '');
INSERT INTO `doc_rmprefix4` VALUES (78, 'Paper', 'Ivory', '250 GR', '79 X 109 CM', 'P', 'Y', '4', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (79, 'Paper', 'Ivory', '270 GR', '65,5 X 96,5 CM ', 'P', 'Y', '5', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (80, 'Paper', 'Ivory', '270 GR', '65,5 X 97 CM', 'P', 'Y', '5', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (81, 'Paper', 'Ivory', '270 GR', '79 X 109 CM', 'P', 'Y', '5', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (82, 'Paper', 'Ivory', '300 GR ', '70 X 70 CM', 'P', 'Y', '7', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (83, 'Paper', 'Ivory', '300gr', '79 X 95 CM', 'P', 'Y', 'J', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (84, 'Paper', 'Ivory', '300gr', '79 X 109 CM', 'P', 'Y', 'J', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (85, 'Paper', 'Ivory', '300gr', '65 x 100 CM', 'P', 'Y', 'J', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (86, 'Paper', 'Ivory', '300gr', '76 X 106 CM', 'P', 'Y', 'J', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (87, 'Paper', 'Ivory', '300gr', '89 X 120 CM', 'P', 'Y', 'J', '6', '');
INSERT INTO `doc_rmprefix4` VALUES (88, 'Paper', 'Ivory', '300gr', '89 X 110 CM', 'P', 'Y', 'J', '8', '');
INSERT INTO `doc_rmprefix4` VALUES (89, 'Paper', 'Ivory', '300gr', '90 X 120 CM', 'P', 'Y', 'J', '9', '');
INSERT INTO `doc_rmprefix4` VALUES (90, 'Paper', 'Kraft', '70gr', '90 X 120 CM', 'P', 'K', 'O', '29', '');
INSERT INTO `doc_rmprefix4` VALUES (93, 'Paper', 'Kraft', '290gr', '79,5 X 121 CM', 'P', 'K', 'M', '30', '');
INSERT INTO `doc_rmprefix4` VALUES (94, 'Paper', 'Duplex', '250gr', '105 X 102 CM', 'P', 'D', 'A', '01', '');
INSERT INTO `doc_rmprefix4` VALUES (95, 'Paper', 'Duplex', '250gr', '105 X 56 CM', 'P', 'D', 'A', '02', '');
INSERT INTO `doc_rmprefix4` VALUES (97, 'Paper', 'Duplex', '250gr', '105 X 79 CM', 'P', 'D', 'A', '03', '');
INSERT INTO `doc_rmprefix4` VALUES (98, 'Paper', 'Duplex', '250gr', '120 X 76 CM', 'P', 'D', 'A', '04', '');
INSERT INTO `doc_rmprefix4` VALUES (99, 'Paper', 'Duplex', '250gr', '49 x 79 CM', 'P', 'D', 'A', '05', '');
INSERT INTO `doc_rmprefix4` VALUES (100, 'Paper', 'Duplex', '250gr', '65 X 100 CM', 'P', 'D', 'A', '06', '');
INSERT INTO `doc_rmprefix4` VALUES (101, 'Paper', 'Duplex', '250gr', '70 X 100 CM', 'P', 'D', 'A', '07', '');
INSERT INTO `doc_rmprefix4` VALUES (102, 'Paper', 'Duplex', '250gr', '79 X 109 CM', 'P', 'D', 'A', '08', '');
INSERT INTO `doc_rmprefix4` VALUES (103, 'Paper', 'Duplex', '250gr', '99 X 121 CM', 'P', 'D', 'A', '09', '');
INSERT INTO `doc_rmprefix4` VALUES (104, 'Paper', 'Duplex', '250gr', '90 X 120 CM', 'P', 'D', 'A', '10', '');
INSERT INTO `doc_rmprefix4` VALUES (105, 'Paper', 'Duplex', '250gr', '89 X 120 CM', 'P', 'D', 'A', '11', '');
INSERT INTO `doc_rmprefix4` VALUES (106, 'Paper', 'Duplex', '250gr', '100 X 114 CM', 'P', 'D', 'A', '12', '');
INSERT INTO `doc_rmprefix4` VALUES (107, 'Paper', 'Duplex', '250gr', '104 X 66 CM', 'P', 'R', 'A', '13', '');
INSERT INTO `doc_rmprefix4` VALUES (108, 'Paper', 'Duplex', '250gr', '114 X 79 CM', 'P', 'D', 'A', '14', '');
INSERT INTO `doc_rmprefix4` VALUES (109, 'Paper', 'Duplex', '250gr', '119 X 92 CM', 'P', 'D', 'A', '15', '');
INSERT INTO `doc_rmprefix4` VALUES (110, 'Paper', 'Duplex', '250gr', '63 X 79 CM', 'P', 'D', 'A', '16', '');
INSERT INTO `doc_rmprefix4` VALUES (111, 'Paper', 'Duplex', '250gr', '67 X 86 CM', 'P', 'D', 'A', '17', '');
INSERT INTO `doc_rmprefix4` VALUES (112, 'Paper', 'Duplex', '250gr', '67 X 93,5 CM', 'P', 'D', 'A', '18', '');
INSERT INTO `doc_rmprefix4` VALUES (113, 'Paper', 'Duplex', '250gr', '70 X 83 CM', 'P', 'D', 'A', '19', '');
INSERT INTO `doc_rmprefix4` VALUES (114, 'Paper', 'Duplex', '250gr', '77 X 125 CM', 'P', 'D', 'A', '20', '');
INSERT INTO `doc_rmprefix4` VALUES (115, 'Paper', 'Duplex', '250gr', '77 X 66,5 CM', 'P', 'D', 'A', '21', '');
INSERT INTO `doc_rmprefix4` VALUES (116, 'Paper', 'Duplex', '250gr', '77 X 94 CM', 'P', 'D', 'A', '22', '');
INSERT INTO `doc_rmprefix4` VALUES (117, 'Paper', 'Duplex', '250gr', '77 X 96 CM', 'P', 'D', 'A', '23', '');
INSERT INTO `doc_rmprefix4` VALUES (118, 'Paper', 'Duplex', '250gr', '79 X 104 CM', 'P', 'D', 'A', '24', '');
INSERT INTO `doc_rmprefix4` VALUES (119, 'Paper', 'Duplex', '250gr', '79 X 125 CM', 'P', 'D', 'A', '25', '');
INSERT INTO `doc_rmprefix4` VALUES (120, 'Paper', 'Duplex', '250gr', '79,5 CM X 121 CM', 'P', 'D', 'A', '26', '');
INSERT INTO `doc_rmprefix4` VALUES (121, 'Paper', 'Duplex', '250gr', '82,5 CM X 70 CM', 'P', 'D', 'A', '27', '');
INSERT INTO `doc_rmprefix4` VALUES (122, 'Paper', 'Duplex', '250gr', '83 X 85 CM', 'P', 'D', 'A', '28', '');
INSERT INTO `doc_rmprefix4` VALUES (123, 'Paper', 'Duplex', '250gr', '85 X 79 CM', 'P', 'D', 'A', '29', '');
INSERT INTO `doc_rmprefix4` VALUES (124, 'Paper', 'Duplex', '250gr', '89 X 124 CM', 'P', 'D', 'A', '30', '');
INSERT INTO `doc_rmprefix4` VALUES (125, 'Paper', 'Duplex', '250gr', '92 X 78 CM', 'P', 'D', 'A', '31', '');
INSERT INTO `doc_rmprefix4` VALUES (126, 'Paper', 'Duplex', '250gr', '95 X 105 CM', 'P', 'D', 'A', '32', '');
INSERT INTO `doc_rmprefix4` VALUES (127, 'Paper', 'Duplex', '250gr', '120 X 76,5 CM', 'P', 'D', 'A', '33', '');
INSERT INTO `doc_rmprefix4` VALUES (128, 'Paper', 'Duplex', '250gr', '68 X 93,5 CM', 'P', 'D', 'A', '34', '');
INSERT INTO `doc_rmprefix4` VALUES (129, 'Paper', 'Duplex', '250gr', '72 X 102 CM', 'P', 'D', 'A', '35', '');
INSERT INTO `doc_rmprefix4` VALUES (130, 'Paper', 'Duplex', '250gr', '59 X 44 CM', 'P', 'D', 'A', '36', '');
INSERT INTO `doc_rmprefix4` VALUES (131, 'Paper', 'Duplex', '270gr', '66 X 96 CM', 'P', 'D', 'B', '01', '');
INSERT INTO `doc_rmprefix4` VALUES (132, 'Paper', 'Duplex', '300gr', '70 X 92 CM', 'P', 'D', 'H', '01', '');
INSERT INTO `doc_rmprefix4` VALUES (133, 'Paper', 'Duplex', '300gr', '79 X 92 CM', 'P', 'D', 'H', '02', '');
INSERT INTO `doc_rmprefix4` VALUES (134, 'Paper', 'Duplex', '310gr', '59,5 X 74,5 CM', 'P', 'D', 'C', '01', '');
INSERT INTO `doc_rmprefix4` VALUES (135, 'Paper', 'Duplex', '310gr', '70 X 100 CM', 'P', 'D', 'C', '02', '');
INSERT INTO `doc_rmprefix4` VALUES (137, 'Paper', 'Duplex', '310gr', '79 X 120 CM', 'P', 'D', 'C', '04', '');
INSERT INTO `doc_rmprefix4` VALUES (138, 'Paper', 'Duplex', '310gr', '80 X 120 CM', 'P', 'D', 'C', '05', '');
INSERT INTO `doc_rmprefix4` VALUES (139, 'Paper', 'Duplex', '310gr', '90 X 120 CM', 'P', 'D', 'C', '06', '');
INSERT INTO `doc_rmprefix4` VALUES (140, 'Paper', 'Duplex', '310gr', '79 X 109 CM', 'P', 'D', 'C', '07', '');
INSERT INTO `doc_rmprefix4` VALUES (141, 'Paper', 'Duplex', '310gr', '114 X 72,5 CM', 'P', 'D', 'C', '08', '');
INSERT INTO `doc_rmprefix4` VALUES (142, 'Sheet', 'BCF', 'M125/M125X3/K125', '475 X 745', 'S', 'C', '3', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (143, 'Sheet', 'BCF', 'M125/M125X3/K125', '520 X 780', 'S', 'C', '3', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (144, 'Sheet', 'BCF', 'M125/M125X3/K125', '580 X 835', 'S', 'C', '3', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (145, 'Sheet', 'BCF', 'M125/M125X3/K125', '580 X 855', 'S', 'C', '3', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (146, 'Sheet', 'SHBF', 'K150/M125/K200', '520 X 780', 'S', 'B', '9', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (147, 'Sheet', 'SHBF', 'K150/M150/K200', '505 X 700', 'S', 'B', '3', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (148, 'Sheet', 'SHBF', 'K150/M150/K200', '560 X 890', 'S', 'B', '3', '7', '');
INSERT INTO `doc_rmprefix4` VALUES (149, 'Sheet', 'SHBF', 'K150/M150/K200', '560 X 970', 'S', 'B', '3', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (150, 'Sheet', 'SHBF', 'M125/M125/K125', '103 X 740 ', 'S', 'B', '5', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (151, 'Sheet', 'SHBF', 'M125/M125/K125', '395 X 700', 'S', 'B', '5', '21', '');
INSERT INTO `doc_rmprefix4` VALUES (152, 'Sheet', 'SHBF', 'M125/M125/K125', '414 X 1005', 'S', 'B', '5', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (153, 'Sheet', 'SHBF', 'M125/M125/K125', '440 x 1005', 'S', 'B', '5', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (154, 'Sheet', 'SHBF', 'M125/M125/K125', '440 x 925', 'S', 'B', '5', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (155, 'Sheet', 'SHBF', 'M125/M125/K125', '460 x 630', 'S', 'B', '5', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (156, 'Sheet', 'SHBF', 'M125/M125/K125', '505 x 70', 'S', 'B', '5', '12', '');
INSERT INTO `doc_rmprefix4` VALUES (157, 'Sheet', 'SHBF', 'M125/M125/K125', '505 x 785 ', 'S', 'B', '5', '13', '');
INSERT INTO `doc_rmprefix4` VALUES (158, 'Ink', 'DGM', '11581', 'ZETLA BROWN', 'I', 'G', '1', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (159, 'Ink', 'OFFSET', 'DGM', '11530', 'I', 'O', 'D', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (160, 'Ink', 'OFFSET', 'DGM', '11581', 'I', 'O', 'D', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (161, 'Ink', 'OFFSET', 'DGM', '11485', 'I', 'O', 'D', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (162, 'Ink', 'OFFSET', 'DGM', '11077', 'I', 'O', 'D', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (163, 'Ink', 'OFFSET', 'DGM', 'S TOM', 'I', 'O', 'D', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (164, 'Ink', 'OFFSET', 'DGM', '11077', 'I', 'O', 'D', '7', '');
INSERT INTO `doc_rmprefix4` VALUES (165, 'Ink', 'OFFSET', 'DGM', '11076', 'I', 'O', 'D', '6', '');
INSERT INTO `doc_rmprefix4` VALUES (166, 'Ink', 'OFFSET', 'HN', 'FINNA', 'I', 'O', 'H', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (167, 'Ink', 'OFFSET', 'HN', 'YAMAHA', 'I', 'O', 'H', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (168, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'I', 'O', 'H', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (169, 'Ink', 'OFFSET', 'HN', 'NYB', 'I', 'O', 'H', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (170, 'Ink', 'OFFSET', 'HN', '548', 'I', 'O', 'H', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (171, 'Ink', 'OFFSET', 'HN', '2303', 'I', 'O', 'H', '7', '');
INSERT INTO `doc_rmprefix4` VALUES (172, 'CTP Plate', 'BLANKET ', 'CITO ', '0.5 X 1.2', 'T', 'S', 'C', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (173, 'CTP Plate', 'BLANKET ', 'CITO ', '1.0 X 3.0', 'T', 'S', 'C', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (174, 'CTP Plate', 'BLANKET ', 'CITO ', '0.8 X 3.0', 'T', 'S', 'C', '3', '');
INSERT INTO `doc_rmprefix4` VALUES (175, 'CTP Plate', 'BLANKET ', 'CITO ', '0.3 X 1.0', 'T', 'S', 'C', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (176, 'CTP Plate', 'BLANKET ', 'CITO ', '0.5 X 1.5', 'T', 'S', 'C', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (177, 'CTP Plate', 'BLANKET ', 'CITO ', '0.6 X 1.7', 'T', 'S', 'C', '6', '');
INSERT INTO `doc_rmprefix4` VALUES (178, 'Ink', 'OFFSET', 'RAPIDA', 'BLACK', 'I', 'O', 'R', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (179, 'Ink', 'OFFSET', 'RAPIDA', 'YELLOW', 'I', 'O', 'R', '8', '');
INSERT INTO `doc_rmprefix4` VALUES (180, 'Ink', 'OFFSET', 'RAPIDA', 'CYAN', 'I', 'O', 'R', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (181, 'Ink', 'OFFSET', 'RAPIDA', 'MAGENTA', 'I', 'O', 'R', '6', '');
INSERT INTO `doc_rmprefix4` VALUES (182, 'Ink', 'OFFSET', 'OSM', '1438', 'I', 'O', 'O', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (183, 'Ink', 'OFFSET', 'OSM', '1332', 'I', 'O', 'O', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (184, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'I', 'O', 'H', '9', '');
INSERT INTO `doc_rmprefix4` VALUES (185, 'Ink', 'OFFSET', 'HAEMA', 'IMPRESS', 'I', 'O', 'I', 'I', '');
INSERT INTO `doc_rmprefix4` VALUES (186, 'Ink', 'OFFSET', 'POPM', '029 ', 'I', 'O', 'P', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (187, 'Glue', 'OFFSET', 'PF', '7300', 'G', 'O', 'P', '73', '');
INSERT INTO `doc_rmprefix4` VALUES (188, 'Glue', 'OFFSET', 'PF', '6259', 'G', 'O', 'P', '62', '');
INSERT INTO `doc_rmprefix4` VALUES (189, 'Glue', 'OFFSET', 'PF', '1493', 'G', 'O', 'P', '93', '');
INSERT INTO `doc_rmprefix4` VALUES (190, 'Sheet', 'SFEF', 'M125/K125', '560 X 405 ', 'S', 'F', '3', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (191, 'Sheet', 'SFEF', 'M125/K125', '560 X 785', 'S', 'F', '3', '13', '');
INSERT INTO `doc_rmprefix4` VALUES (192, 'Sheet', 'SFEF', 'M125/K125', '585 X 915', 'S', 'F', '3', '41', '');
INSERT INTO `doc_rmprefix4` VALUES (193, 'Sheet', 'SFEF', 'M125/K125', '590 X 510', 'S', 'F', '3', '65', '');
INSERT INTO `doc_rmprefix4` VALUES (194, 'Sheet', 'SFEF', 'M125/K125', '765 X 410', 'S', 'F', '3', '12', '');
INSERT INTO `doc_rmprefix4` VALUES (195, 'Sheet', 'SFEF', 'M125/K125', '815 X 410', 'S', 'F', '3', '31', '');
INSERT INTO `doc_rmprefix4` VALUES (196, 'Sheet', 'SFEF', 'M125/K125', '880 X 410', 'S', 'F', '3', '32', '');
INSERT INTO `doc_rmprefix4` VALUES (197, 'Sheet', 'SFEF', 'M125/K125', '880 X 410 ', 'S', 'F', '3', '23', '');
INSERT INTO `doc_rmprefix4` VALUES (198, 'Sheet', 'SFEF', 'M125/K125', '885 X 410', 'S', 'F', '3', '43', '');
INSERT INTO `doc_rmprefix4` VALUES (199, 'Sheet', 'SFEF', 'M125/K125', '900 X 580', 'S', 'F', '3', '44', '');
INSERT INTO `doc_rmprefix4` VALUES (200, 'Sheet', 'SFEF', 'M125/K125', '425 X 550', 'S', 'F', '3', '64', '');
INSERT INTO `doc_rmprefix4` VALUES (201, 'Sheet', 'SFEF', 'M125/K125', '455 X 870', 'S', 'F', '3', '76', '');
INSERT INTO `doc_rmprefix4` VALUES (202, 'Sheet', 'SFEF', 'M125/K125', '510 X 495', 'S', 'F', '3', '48', '');
INSERT INTO `doc_rmprefix4` VALUES (203, 'Sheet', 'SFEF', 'M125/K125', '635 X 685', 'S', 'F', '3', '68', '');
INSERT INTO `doc_rmprefix4` VALUES (204, 'Sheet', 'SFEF', 'WK150/M125', '930 X 375 ', 'S', 'F', 'W', '98', '');
INSERT INTO `doc_rmprefix4` VALUES (205, 'Sheet', 'BCF', 'K125/M125X3/M125', '655 X 850', 'S', 'C', 'K', '65', '');
INSERT INTO `doc_rmprefix4` VALUES (206, 'Sheet', 'BCF', 'K125/M125X3/M125', '470 X 635', 'S', 'C', 'K', '63', '');
INSERT INTO `doc_rmprefix4` VALUES (207, 'Sheet', 'SHBF', 'M125/M125/K125', '520 X 780', 'S', 'B', '5', '52', '');
INSERT INTO `doc_rmprefix4` VALUES (208, 'Sheet', 'SHBF', 'M125/M125/K125', '540 X 900', 'S', 'B', '5', '90', '');
INSERT INTO `doc_rmprefix4` VALUES (209, 'Sheet', 'SHBF', 'M125/M125/K125', '560 X 900', 'S', 'B', '5', '56', '');
INSERT INTO `doc_rmprefix4` VALUES (210, 'Sheet', 'SHBF', 'M125/M125/K125', '590 X 1015', 'S', 'B', '5', '10', '');
INSERT INTO `doc_rmprefix4` VALUES (211, 'Sheet', 'SHBF', 'M125/M125/K125', '590 X 915 ', 'S', 'B', '5', '91', '');
INSERT INTO `doc_rmprefix4` VALUES (212, 'Sheet', 'SHBF', 'M125/M125/K125', '640 X 785', 'S', 'B', '5', '87', '');
INSERT INTO `doc_rmprefix4` VALUES (213, 'Sheet', 'SHBF', 'M125/M125/K125', '815 X 680', 'S', 'B', '5', '68', '');
INSERT INTO `doc_rmprefix4` VALUES (214, 'Sheet', 'SHBF', 'M125/M125/K125', '395 X 700', 'S', 'B', '5', '70', '');
INSERT INTO `doc_rmprefix4` VALUES (215, 'Sheet', 'DF', 'M125X3/K125', '500 X 680', 'S', '4', '7', '1', '');
INSERT INTO `doc_rmprefix4` VALUES (216, 'Sheet', 'DF', 'M125X3/K125', '510 X 650', 'S', '4', '7', '2', '');
INSERT INTO `doc_rmprefix4` VALUES (217, 'Sheet', 'DF', 'M125X3/K125', '665 X 925', 'S', '4', '7', '5', '');
INSERT INTO `doc_rmprefix4` VALUES (218, 'Sheet', 'DF', 'M125X3/K125', '675 X 870', 'S', '4', '7', '4', '');
INSERT INTO `doc_rmprefix4` VALUES (219, 'Sheet', 'DF', 'M125X3/K125', '590 X 740', 'S', '4', '7', '59', '');

-- ----------------------------
-- Table structure for doc_rmprefix5
-- ----------------------------
DROP TABLE IF EXISTS `doc_rmprefix5`;
CREATE TABLE `doc_rmprefix5`  (
  `idprefix` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category4` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category5` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix1` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix2` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix3` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix4` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `prefix5` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  PRIMARY KEY (`idprefix`) USING BTREE,
  UNIQUE INDEX `prefix1`(`prefix1` ASC, `prefix2` ASC, `prefix3` ASC, `prefix4` ASC, `prefix5` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 175 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master prefix level 5 untuk kategori bahan baku' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of doc_rmprefix5
-- ----------------------------
INSERT INTO `doc_rmprefix5` VALUES (9, 'Paper', 'Art carton', '210gr', '61 X 86 CM', 'GOLDEN', 'P', 'C', 'C', '01', '001', '');
INSERT INTO `doc_rmprefix5` VALUES (10, 'Paper', 'Art carton', '210gr', '65  X 100 CM', 'GOLDEN', 'P', 'C', 'C', '02', '002', '');
INSERT INTO `doc_rmprefix5` VALUES (11, 'Paper', 'Art carton', '210gr', '79 X 109 CM', 'GOLDEN', 'P', 'C', 'C', '03', '003', '');
INSERT INTO `doc_rmprefix5` VALUES (12, 'Paper', 'Art carton', '210gr', '65 X 98 CM', 'OJI', 'P', 'C', 'C', '04', '004', '');
INSERT INTO `doc_rmprefix5` VALUES (13, 'Paper', 'Art carton', '230gr', '79 X 109 CM', 'GOLDEN', 'P', 'C', 'D', '05', '005', '');
INSERT INTO `doc_rmprefix5` VALUES (15, 'Paper', 'Art carton', '260gr', '65  X 100 CM', 'GOLDEN', 'P', 'C', 'E', '06', '006', '');
INSERT INTO `doc_rmprefix5` VALUES (16, 'Paper', 'Art carton', '260gr', '79 X 109 CM', 'GOLDEN', 'P', 'C', 'E', '07', '007', '');
INSERT INTO `doc_rmprefix5` VALUES (17, 'Paper', 'Art carton', '310gr', '79 X 109 CM', 'GOLDEN', 'P', 'C', 'F', '08', '008', '');
INSERT INTO `doc_rmprefix5` VALUES (18, 'Paper', 'Art Papper', '120gr', '65  X 100 CM', 'GOLDEN', 'P', 'R', 'B', '01', '001', '');
INSERT INTO `doc_rmprefix5` VALUES (20, 'Paper', 'Art Papper', '150gr', '65  X 100 CM', 'NEVIA', 'P', 'R', 'C', '04', '004', '');
INSERT INTO `doc_rmprefix5` VALUES (21, 'Paper', 'Art Papper', '150gr', '65 X 90 CM', 'NEVIA', 'P', 'R', 'C', '05', '005', '');
INSERT INTO `doc_rmprefix5` VALUES (22, 'Paper', 'Art Papper', '85gr', '65  X 100 CM', 'NEVIA', 'P', 'R', 'A', '06', '006', '');
INSERT INTO `doc_rmprefix5` VALUES (23, 'Paper', 'Art Papper', '85gr', '79 X 109 CM', 'NEVIA', 'P', 'R', 'A', '07', '007', '');
INSERT INTO `doc_rmprefix5` VALUES (24, 'Paper', 'Kraft', '275gr', '90 X 109 CM', '', 'P', 'K', 'C', '01', '001', '');
INSERT INTO `doc_rmprefix5` VALUES (25, 'Paper', 'Kraft', '125gr', '90 X 120 CM', '', 'P', 'K', 'A', '02', '002', '');
INSERT INTO `doc_rmprefix5` VALUES (26, 'Paper', 'Kraft', '250gr', '101 X 77 CM', '', 'P', 'K', 'B', '03', '003', '');
INSERT INTO `doc_rmprefix5` VALUES (27, 'Paper', 'Kraft', '250gr', '105 X 97 CM', '', 'P', 'K', 'B', '04', '004', '');
INSERT INTO `doc_rmprefix5` VALUES (28, 'Paper', 'Kraft', '250gr', '105 X 97,5 CM', '', 'P', 'K', 'B', '05', '005', '');
INSERT INTO `doc_rmprefix5` VALUES (29, 'Paper', 'Kraft', '250gr', '114 X 85 CM', '', 'P', 'K', 'B', '06', '006', '');
INSERT INTO `doc_rmprefix5` VALUES (30, 'Paper', 'Kraft', '250gr', '76 X 102 CM', '', 'P', 'K', 'B', '07', '007', '');
INSERT INTO `doc_rmprefix5` VALUES (31, 'Paper', 'Kraft', '250gr', '79 X 93 CM', '', 'P', 'K', 'B', '08', '008', '');
INSERT INTO `doc_rmprefix5` VALUES (32, 'Paper', 'Kraft', '250gr', '90 X 23 CM', '', 'P', 'K', 'B', '09', '009', '');
INSERT INTO `doc_rmprefix5` VALUES (33, 'Paper', 'Kraft', '250gr', '90 X 70 CM', '', 'P', 'K', 'B', '10', '010', '');
INSERT INTO `doc_rmprefix5` VALUES (34, 'Paper', 'Kraft', '250gr', '95 X 65 CM', '', 'P', 'K', 'B', '11', '011', '');
INSERT INTO `doc_rmprefix5` VALUES (35, 'Paper', 'Kraft', '250gr', '95 X 66 CM', '', 'P', 'K', 'B', '12', '012', '');
INSERT INTO `doc_rmprefix5` VALUES (36, 'Paper', 'Art Papper', '120gr', '65  X 100 CM', 'NEVIA', 'P', 'R', 'B', '01', '002', '');
INSERT INTO `doc_rmprefix5` VALUES (37, 'Paper', 'Art Papper', '120gr', '79 X 109 CM', 'GOLDEN', 'P', 'R', 'B', '02', '003', '');
INSERT INTO `doc_rmprefix5` VALUES (38, 'Paper', 'Kraft', '265gr', '75 X 70 CM', 'XTRU', 'P', 'K', 'N', '13', '013', '');
INSERT INTO `doc_rmprefix5` VALUES (39, 'Paper', 'Kraft', '265gr', '79 X 109 CM', '', 'P', 'K', 'N', '14', '014', '');
INSERT INTO `doc_rmprefix5` VALUES (41, 'Paper', 'Kraft', '275gr', '107 X 89 CM', '', 'P', 'K', 'C', '15', '015', '');
INSERT INTO `doc_rmprefix5` VALUES (43, 'Paper', 'Kraft', '275gr', '110 X 88,5 CM', '', 'P', 'K', 'C', '16', '016', '');
INSERT INTO `doc_rmprefix5` VALUES (44, 'Paper', 'HVS', '60 GR ', '65 x 100 CM', 'PAPERPLUS', 'P', '4', '1', '5', '4', '');
INSERT INTO `doc_rmprefix5` VALUES (45, 'Paper', 'HVS', '58 GR', '65 x 100 CM', 'RAINBOW', 'P', '4', '4', '5', '4', '');
INSERT INTO `doc_rmprefix5` VALUES (46, 'Paper', 'HVS', '85 GR', '65 x 100 CM', 'TANPA MERK', 'P', '4', '5', '5', '7', '');
INSERT INTO `doc_rmprefix5` VALUES (47, 'Paper', 'HVS', '90 GR', '65 x 100 CM', 'TANPA MERK', 'P', '4', '3', '6', '7', '');
INSERT INTO `doc_rmprefix5` VALUES (48, 'Paper', 'Ivory', '190 GR ', '65 X 75 CM', 'BOHUI', 'P', 'Y', '1', '1', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (49, 'Paper', 'Ivory', '210 GR', '65 X 110 CM', 'TANPA MERK', 'P', 'Y', '2', '2', '5', '');
INSERT INTO `doc_rmprefix5` VALUES (50, 'Paper', 'Ivory', '210 GR', '65 x 100 CM', 'BOHUI', 'P', 'Y', '2', '3', '5', '');
INSERT INTO `doc_rmprefix5` VALUES (51, 'Paper', 'Ivory', '210 GR', '79 X 109 CM', 'BOHUI', 'P', 'Y', '2', '5', '5', '');
INSERT INTO `doc_rmprefix5` VALUES (52, 'Paper', 'Ivory', '210 GR', '90 X 120 CM', 'BOHUI', 'P', 'Y', '2', '6', '5', '');
INSERT INTO `doc_rmprefix5` VALUES (53, 'Paper', 'Ivory', '210 GR', '65 x 100 CM', 'IPSUN', 'P', 'Y', '2', '3', '6', '');
INSERT INTO `doc_rmprefix5` VALUES (54, 'Paper', 'Ivory', '210 GR', '65 x 100 CM', 'STARPACK', 'P', 'Y', '2', '3', '7', '');
INSERT INTO `doc_rmprefix5` VALUES (55, 'Paper', 'Kraft', '275gr', '88,5 CM X 109 CM', '', 'P', 'K', 'C', '17', '017', '');
INSERT INTO `doc_rmprefix5` VALUES (56, 'Paper', 'Kraft', '275gr', '79 X 109 CM', '', 'P', 'K', 'C', '18', '018', '');
INSERT INTO `doc_rmprefix5` VALUES (57, 'Paper', 'Kraft', '275gr', '79 X 121 CM', '', 'P', 'K', 'C', '19', '019', '');
INSERT INTO `doc_rmprefix5` VALUES (58, 'Paper', 'Kraft', '275gr', '85 X 112 CM', '', 'P', 'K', 'C', '20', '020', '');
INSERT INTO `doc_rmprefix5` VALUES (59, 'Paper', 'Kraft', '275gr', '89 X 110 CM', '', 'P', 'K', 'C', '21', '021', '');
INSERT INTO `doc_rmprefix5` VALUES (60, 'Paper', 'Kraft', '275gr', '79,5 X 121 CM', 'XTRU', 'P', 'K', 'C', '22', '022', '');
INSERT INTO `doc_rmprefix5` VALUES (61, 'Paper', 'Kraft', '290gr', '75 X 82 CM', 'XTRU', 'P', 'K', 'M', '23', '023', '');
INSERT INTO `doc_rmprefix5` VALUES (62, 'Paper', 'Kraft', '300gr', '79 X 109 CM', '', 'P', 'K', 'D', '24', '024', '');
INSERT INTO `doc_rmprefix5` VALUES (63, 'Paper', 'Kraft', '300gr', '89 X 110 CM', '', 'P', 'K', 'D', '25', '025', '');
INSERT INTO `doc_rmprefix5` VALUES (64, 'Paper', 'Kraft', '300gr', '90 X 110 CM', '', 'P', 'K', 'D', '27', '027', '');
INSERT INTO `doc_rmprefix5` VALUES (65, 'Paper', 'Kraft', '300gr', '90 X 120 CM', '', 'P', 'K', 'D', '28', '028', '');
INSERT INTO `doc_rmprefix5` VALUES (66, 'Paper', 'Kraft', '350gr', '79 X 109 CM', '', 'P', 'K', 'F', '26', '026', '');
INSERT INTO `doc_rmprefix5` VALUES (67, 'Paper', 'Ivory', '230 GR', '61,5 X 120 CM', 'TANPA MERK', 'P', 'Y', '3', '2', '5', '');
INSERT INTO `doc_rmprefix5` VALUES (68, 'Paper', 'Ivory', '230 GR', '70 X 95', 'BOHUI', 'P', 'Y', '3', '4', '5', '');
INSERT INTO `doc_rmprefix5` VALUES (69, 'Paper', 'Ivory', '230 GR', '120 X 82 CM', 'BOHUI', 'P', 'Y', '3', '3', '5', '');
INSERT INTO `doc_rmprefix5` VALUES (70, 'Paper', 'Ivory', '230gr', '79 X 109 CM', 'BOHUI', 'P', 'Y', 'H', '3', '6', '');
INSERT INTO `doc_rmprefix5` VALUES (71, 'Paper', 'Ivory', '250 GR', '79 X 109 CM', 'APRIL', 'P', 'Y', '4', '1', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (72, 'Paper', 'Ivory', '250 GR', '72 X 57 CM', 'BOHUI', 'P', 'Y', '4', '6', '6', '');
INSERT INTO `doc_rmprefix5` VALUES (73, 'Paper', 'Ivory', '250 GR', '79 X 109 CM', 'BOHUI', 'P', 'Y', '4', '1', '6', '');
INSERT INTO `doc_rmprefix5` VALUES (74, 'Paper', 'Ivory', '250 GR', '89 X 61 CM ', 'BOHUI', 'P', 'Y', '4', '7', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (75, 'Paper', 'Ivory', '250 GR', '79 X 109 CM', 'IPSUN', 'P', 'Y', '4', '1', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (76, 'Paper', 'Ivory', '270 GR', '65,5 X 96,5 CM ', 'BOHUI', 'P', 'Y', '5', '1', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (77, 'Paper', 'Ivory', '270 GR', '65,5 X 97 CM', 'BOHUI', 'P', 'Y', '5', '2', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (78, 'Paper', 'Ivory', '270 GR', '79 X 109 CM', 'XTRU', 'P', 'Y', '5', '3', '3', '');
INSERT INTO `doc_rmprefix5` VALUES (79, 'Paper', 'Ivory', '300 GR ', '70 X 70 CM', 'TANPA MERK', 'P', 'Y', '7', '1', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (80, 'Paper', 'Ivory', '300gr', '79 X 95 CM', 'TANPA MERK', 'P', 'Y', 'J', '2', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (81, 'Paper', 'Ivory', '300gr', '79 X 109 CM', 'BOHUI', 'P', 'Y', 'J', '3', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (82, 'Paper', 'Ivory', '300gr', '79 X 109 CM', 'APRIL', 'P', 'Y', 'J', '3', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (83, 'Paper', 'Ivory', '300gr', '65 x 100 CM', 'BOHUI ', 'P', 'Y', 'J', '4', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (84, 'Paper', 'Ivory', '300gr', '76 X 106 CM', 'BOHUI', 'P', 'Y', 'J', '5', '4', '');
INSERT INTO `doc_rmprefix5` VALUES (85, 'Paper', 'Ivory', '300gr', '89 X 110 CM', 'BOHUI', 'P', 'Y', 'J', '8', '4', '');
INSERT INTO `doc_rmprefix5` VALUES (86, 'Paper', 'Ivory', '300gr', '89 X 120 CM', 'BOHUI', 'P', 'Y', 'J', '6', '4', '');
INSERT INTO `doc_rmprefix5` VALUES (87, 'Paper', 'Ivory', '300gr', '90 X 120 CM', 'BOHUI', 'P', 'Y', 'J', '9', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (88, 'Paper', 'Ivory', '300gr', '79 X 109 CM', 'IKA VA', 'P', 'Y', 'J', '3', '11', '');
INSERT INTO `doc_rmprefix5` VALUES (89, 'Paper', 'Ivory', '300gr', '79 X 95 CM', 'IKA VA', 'P', 'Y', 'J', '2', '9', '');
INSERT INTO `doc_rmprefix5` VALUES (90, 'Paper', 'Ivory', '300gr', '79 X 109 CM', 'IPSUN', 'P', 'Y', 'J', '3', '7', '');
INSERT INTO `doc_rmprefix5` VALUES (91, 'Paper', 'Ivory', '300gr', '65 x 100 CM', 'STARPACK', 'P', 'Y', 'J', '4', '12', '');
INSERT INTO `doc_rmprefix5` VALUES (92, 'Paper', 'Ivory', '300gr', '90 X 120 CM', 'STARPACK', 'P', 'Y', 'J', '9', '13', '');
INSERT INTO `doc_rmprefix5` VALUES (93, 'Paper', 'Kraft', '70gr', '90 X 120 CM', '', 'P', 'K', 'O', '29', '029', '');
INSERT INTO `doc_rmprefix5` VALUES (94, 'Paper', 'Kraft', '275gr', '79 X 121 CM', 'XTRU', 'P', 'K', 'C', '19', '30', '');
INSERT INTO `doc_rmprefix5` VALUES (96, 'Paper', 'Kraft', '290gr', '79,5 X 121 CM', 'XTRU', 'P', 'K', 'M', '30', '030', '');
INSERT INTO `doc_rmprefix5` VALUES (97, 'Paper', 'Duplex', '250gr', '105 X 102 CM', 'DAYASA', 'P', 'D', 'A', '01', '001', '');
INSERT INTO `doc_rmprefix5` VALUES (98, 'Paper', 'Duplex', '250gr', '105 X 56 CM', 'DAYASA', 'P', 'D', 'A', '02', '002', '');
INSERT INTO `doc_rmprefix5` VALUES (100, 'Paper', 'Duplex', '250gr', '105 X 79 CM', 'DAYASA', 'P', 'D', 'A', '03', '003', '');
INSERT INTO `doc_rmprefix5` VALUES (101, 'Paper', 'Duplex', '250gr', '120 X 76 CM', 'DAYASA', 'P', 'D', 'A', '04', '004', '');
INSERT INTO `doc_rmprefix5` VALUES (102, 'Paper', 'Duplex', '250gr', '49 x 79 CM', 'DAYASA', 'P', 'D', 'A', '05', '005', '');
INSERT INTO `doc_rmprefix5` VALUES (103, 'Paper', 'Duplex', '250gr', '65 X 100 CM', 'DAYASA', 'P', 'D', 'A', '06', '006', '');
INSERT INTO `doc_rmprefix5` VALUES (104, 'Paper', 'Duplex', '250gr', '70 X 100 CM', 'DAYASA', 'P', 'D', 'A', '07', '007', '');
INSERT INTO `doc_rmprefix5` VALUES (105, 'Paper', 'Duplex', '250gr', '79 X 109 CM', 'DAYASA', 'P', 'D', 'A', '08', '008', '');
INSERT INTO `doc_rmprefix5` VALUES (106, 'Paper', 'Duplex', '250gr', '99 X 121 CM', 'DAYASA', 'P', 'D', 'A', '09', '009', '');
INSERT INTO `doc_rmprefix5` VALUES (107, 'Paper', 'Duplex', '250gr', '79 X 109 CM', 'FAJAR', 'P', 'D', 'A', '08', '010', '');
INSERT INTO `doc_rmprefix5` VALUES (108, 'Paper', 'Duplex', '250gr', '90 X 120 CM', 'FAJAR', 'P', 'D', 'A', '10', '010', '');
INSERT INTO `doc_rmprefix5` VALUES (109, 'Paper', 'Duplex', '250gr', '89 X 120 CM', 'IKA DA', 'P', 'D', 'A', '11', '011', '');
INSERT INTO `doc_rmprefix5` VALUES (110, 'Paper', 'Duplex', '250gr', '100 X 114 CM', 'IKA DP', 'P', 'D', 'A', '12', '012', '');
INSERT INTO `doc_rmprefix5` VALUES (111, 'Paper', 'Duplex', '250gr', '105 X 102 CM', 'IKA DP', 'P', 'D', 'A', '01', '012', '');
INSERT INTO `doc_rmprefix5` VALUES (112, 'Paper', 'Duplex', '250gr', '114 X 79 CM', 'IKA DP', 'P', 'D', 'A', '14', '014', '');
INSERT INTO `doc_rmprefix5` VALUES (114, 'Paper', 'Duplex', '250gr', '119 X 92 CM', 'IKA DP', 'P', 'D', 'A', '15', '015', '');
INSERT INTO `doc_rmprefix5` VALUES (115, 'Paper', 'Duplex', '250gr', '63 X 79 CM', 'IKA DP', 'P', 'D', 'A', '16', '016', '');
INSERT INTO `doc_rmprefix5` VALUES (116, 'Paper', 'Duplex', '250gr', '65 X 100 CM', 'IKA DP', 'P', 'D', 'A', '06', '16', '');
INSERT INTO `doc_rmprefix5` VALUES (117, 'Paper', 'Duplex', '250gr', '67 X 86 CM', 'IKA DP', 'P', 'D', 'A', '17', '017', '');
INSERT INTO `doc_rmprefix5` VALUES (118, 'Paper', 'Duplex', '250gr', '67 X 93,5 CM', 'IKA DP', 'P', 'D', 'A', '18', '018', '');
INSERT INTO `doc_rmprefix5` VALUES (120, 'Paper', 'Duplex', '250gr', '70 X 83 CM', 'IKA DP', 'P', 'D', 'A', '19', '019', '');
INSERT INTO `doc_rmprefix5` VALUES (121, 'Paper', 'Duplex', '250gr', '77 X 125 CM', 'IKA DP', 'P', 'D', 'A', '20', '020', '');
INSERT INTO `doc_rmprefix5` VALUES (122, 'Paper', 'Duplex', '250gr', '77 X 66,5 CM', 'IKA DP', 'P', 'D', 'A', '21', '021', '');
INSERT INTO `doc_rmprefix5` VALUES (123, 'Paper', 'Duplex', '250gr', '77 X 94 CM', 'IKA DP', 'P', 'D', 'A', '22', '022', '');
INSERT INTO `doc_rmprefix5` VALUES (124, 'Paper', 'Duplex', '250gr', '77 X 96 CM', 'IKA DP', 'P', 'D', 'A', '23', '023', '');
INSERT INTO `doc_rmprefix5` VALUES (125, 'Paper', 'Duplex', '250gr', '79 X 104 CM', 'IKA DP', 'P', 'D', 'A', '24', '024', '');
INSERT INTO `doc_rmprefix5` VALUES (126, 'Paper', 'Duplex', '250gr', '79 X 125 CM', 'IKA DP', 'P', 'D', 'A', '25', '025', '');
INSERT INTO `doc_rmprefix5` VALUES (127, 'Paper', 'Duplex', '250gr', '79,5 CM X 121 CM', 'IKA DP', 'P', 'D', 'A', '26', '026', '');
INSERT INTO `doc_rmprefix5` VALUES (128, 'Paper', 'Duplex', '250gr', '82,5 CM X 70 CM', 'IKA DP', 'P', 'D', 'A', '27', '027', '');
INSERT INTO `doc_rmprefix5` VALUES (129, 'Paper', 'Duplex', '250gr', '83 X 85 CM', 'IKA DP', 'P', 'D', 'A', '28', '028', '');
INSERT INTO `doc_rmprefix5` VALUES (130, 'Paper', 'Duplex', '250gr', '85 X 79 CM', 'IKA DP', 'P', 'D', 'A', '29', '029', '');
INSERT INTO `doc_rmprefix5` VALUES (131, 'Paper', 'Duplex', '250gr', '89 X 124 CM', 'IKA DP', 'P', 'D', 'A', '30', '030', '');
INSERT INTO `doc_rmprefix5` VALUES (132, 'Paper', 'Duplex', '250gr', '92 X 78 CM', 'IKA DP', 'P', 'D', 'A', '31', '031', '');
INSERT INTO `doc_rmprefix5` VALUES (133, 'Paper', 'Duplex', '250gr', '95 X 105 CM', 'IKA DP', 'P', 'D', 'A', '32', '032', '');
INSERT INTO `doc_rmprefix5` VALUES (134, 'Paper', 'Duplex', '250gr', '120 X 76,5 CM', 'IKA DP', 'P', 'D', 'A', '33', '033', '');
INSERT INTO `doc_rmprefix5` VALUES (135, 'Paper', 'Duplex', '250gr', '68 X 93,5 CM', 'IKA DP', 'P', 'D', 'A', '34', '034', '');
INSERT INTO `doc_rmprefix5` VALUES (136, 'Paper', 'Duplex', '250gr', '72 X 102 CM', 'KUAN YUAN', 'P', 'D', 'A', '35', '035', '');
INSERT INTO `doc_rmprefix5` VALUES (137, 'Paper', 'Duplex', '250gr', '59 X 44 CM', 'PAKERIN', 'P', 'D', 'A', '36', '036', '');
INSERT INTO `doc_rmprefix5` VALUES (138, 'Paper', 'Duplex', '270gr', '66 X 96 CM', 'MSE', 'P', 'D', 'B', '01', '001', '');
INSERT INTO `doc_rmprefix5` VALUES (139, 'Paper', 'Duplex', '300gr', '70 X 92 CM', 'DAYASA', 'P', 'D', 'H', '01', '001', '');
INSERT INTO `doc_rmprefix5` VALUES (140, 'Paper', 'Duplex', '300gr', '79 X 92 CM', 'DAYASA', 'P', 'D', 'H', '02', '002', '');
INSERT INTO `doc_rmprefix5` VALUES (141, 'Paper', 'Duplex', '310gr', '59,5 X 74,5 CM', 'DAYASA', 'P', 'D', 'C', '01', '001', '');
INSERT INTO `doc_rmprefix5` VALUES (142, 'Paper', 'Duplex', '310gr', '70 X 100 CM', 'DAYASA', 'P', 'D', 'C', '02', '002', '');
INSERT INTO `doc_rmprefix5` VALUES (144, 'Paper', 'Duplex', '310gr', '79 X 120 CM', 'DAYASA', 'P', 'D', 'C', '04', '004', '');
INSERT INTO `doc_rmprefix5` VALUES (145, 'Paper', 'Duplex', '310gr', '80 X 120 CM', 'DAYASA', 'P', 'D', 'C', '05', '005', '');
INSERT INTO `doc_rmprefix5` VALUES (146, 'Paper', 'Duplex', '310gr', '90 X 120 CM', 'DAYASA', 'P', 'D', 'C', '06', '006', '');
INSERT INTO `doc_rmprefix5` VALUES (147, 'Paper', 'Duplex', '310gr', '90 X 120 CM', 'FAJAR', 'P', 'D', 'C', '06', '007', '');
INSERT INTO `doc_rmprefix5` VALUES (148, 'Paper', 'Duplex', '310gr', '70 X 100 CM', 'IKA DA', 'P', 'D', 'C', '02', '007', '');
INSERT INTO `doc_rmprefix5` VALUES (149, 'Paper', 'Duplex', '310gr', '79 X 109 CM', 'IKA DA', 'P', 'D', 'C', '07', '007', '');
INSERT INTO `doc_rmprefix5` VALUES (150, 'Paper', 'Duplex', '310gr', '114 X 72,5 CM', 'IKA DP', 'P', 'D', 'C', '08', '008', '');
INSERT INTO `doc_rmprefix5` VALUES (151, 'Ink', 'OFFSET', 'HN', 'FINNA', 'DARK GREEN IVORY ', 'I', 'O', 'H', '1', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (152, 'Ink', 'OFFSET', 'HN', 'FINNA', 'EMERALD GREEN IVR', 'I', 'O', 'H', '1', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (153, 'Ink', 'OFFSET', 'HN', 'YAMAHA', 'REFLEX BLUE', 'I', 'O', 'H', '2', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (154, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'AOK RED SAR', 'I', 'O', 'H', '3', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (155, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'BLACK SAR', 'I', 'O', 'H', '3', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (156, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'WHITE SAR', 'I', 'O', 'H', '3', '3', '');
INSERT INTO `doc_rmprefix5` VALUES (157, 'Ink', 'OFFSET', 'HN', 'NYB', 'RED HD R', 'I', 'O', 'H', '4', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (158, 'Ink', 'OFFSET', 'HN', '2303', 'LIGHT GREEN', 'I', 'O', 'H', '7', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (159, 'Ink', 'OFFSET', 'HN', '548', 'DARK BLUE ', 'I', 'O', 'H', '5', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (160, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'YELLOW SAR', 'I', 'O', 'H', '3', '6', '');
INSERT INTO `doc_rmprefix5` VALUES (161, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'BLACK BELT', 'I', 'O', 'H', '3', '9', '');
INSERT INTO `doc_rmprefix5` VALUES (162, 'CTP Plate', 'BLANKET ', 'CITO ', '0.3 X 1.0', 'MATRIX ', 'T', 'S', 'C', '4', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (163, 'CTP Plate', 'BLANKET ', 'CITO ', '0.5 X 1.2', 'MATRIX ', 'T', 'S', 'C', '1', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (164, 'CTP Plate', 'BLANKET ', 'CITO ', '0.5 X 1.5', 'MATRIX ', 'T', 'S', 'C', '5', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (165, 'CTP Plate', 'BLANKET ', 'CITO ', '0.6 X 1.7', 'MATRIX ', 'T', 'S', 'C', '6', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (166, 'CTP Plate', 'BLANKET ', 'CITO ', '1.0 X 3.0', 'MATRIX ', 'T', 'S', 'C', '2', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (167, 'Ink', 'OFFSET', 'OSM', '1332', 'GREEN', 'I', 'O', 'O', '2', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (168, 'Ink', 'OFFSET', 'OSM', '1438', 'GREEN', 'I', 'O', 'O', '1', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (169, 'Ink', 'OFFSET', 'HN', 'AMBRI', 'RED NO 2', 'I', 'O', 'H', '3', '8', '');
INSERT INTO `doc_rmprefix5` VALUES (170, 'Ink', 'OFFSET', 'HAEMA', 'IMPRESS', 'CYAN', 'I', 'O', 'I', 'I', '1', '');
INSERT INTO `doc_rmprefix5` VALUES (171, 'Ink', 'OFFSET', 'HAEMA', 'IMPRESS', 'BLACK', 'I', 'O', 'I', 'I', '2', '');
INSERT INTO `doc_rmprefix5` VALUES (172, 'Ink', 'OFFSET', 'HAEMA', 'IMPRESS', 'MAGENTA', 'I', 'O', 'I', 'I', '3', '');
INSERT INTO `doc_rmprefix5` VALUES (173, 'Ink', 'OFFSET', 'HAEMA', 'IMPRESS', 'YELLOW', 'I', 'O', 'I', 'I', '4', '');
INSERT INTO `doc_rmprefix5` VALUES (174, 'Ink', 'OFFSET', 'POPM', '029 ', 'S BROWN', 'I', 'O', 'P', '1', '1', '');

SET FOREIGN_KEY_CHECKS = 1;
