/*
 Navicat Premium Dump SQL

 Source Server         : TM 188 erptmo
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46-0ubuntu0.24.04.3)
 Source Host           : 103.79.247.146:23306
 Source Schema         : tminv

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46-0ubuntu0.24.04.3)
 File Encoding         : 65001

 Date: 22/07/2026 20:18:54
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fg_stock
-- ----------------------------
DROP TABLE IF EXISTS `fg_stock`;
CREATE TABLE `fg_stock`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK header movement FG',
  `doc_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'No dokumen',
  `doc_date` datetime NULL DEFAULT NULL COMMENT 'tgl dokumen',
  `doc_type` tinyint NULL DEFAULT NULL COMMENT 'IN=1 (dari produksi), OUT=-1 (ke customer)',
  `doc_cat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'BB | STOCK | ADJ | RETURN | OPNAME',
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'gudang',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product (SKU induk dokumen ini)',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'catatan\r\n',
  `stat_confirm` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=draft, 1=confirmed → trigger otomatis isi fg_stockcard',
  `stat_cancel` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'flag cancel',
  `stat_closed` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'flag close',
  `post_date` date NULL DEFAULT NULL COMMENT 'post date oleh pihak accoutning saat di jurnal',
  `opn_start` datetime NULL DEFAULT NULL COMMENT 'opname start',
  `opn_stop` datetime NULL DEFAULT NULL COMMENT 'opname stop',
  `opn_officer` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'nama petugas yang melakukan opname',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  INDEX `idx_fg_s_product`(`product_id` ASC) USING BTREE,
  INDEX `idx_fg_s_date`(`doc_date` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 167 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header movement stok FG — dikonfirm → trigger isi fg_stockcard' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of fg_stock
-- ----------------------------
INSERT INTO `fg_stock` VALUES (30, '26DN00001', '2026-03-10 00:00:00', 1, 'STOCK', 5, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-30 13:50:51', '2026-04-30 13:50:51');
INSERT INTO `fg_stock` VALUES (40, '26DN00001', '2026-03-10 00:00:00', 1, 'STOCK', 5, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-30 13:51:46', '2026-04-30 13:51:46');
INSERT INTO `fg_stock` VALUES (42, '10260004', '2026-03-10 00:00:00', 1, 'STOCK', 5, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-30 13:52:39', '2026-04-30 13:52:39');
INSERT INTO `fg_stock` VALUES (99, '10260002', '2026-03-10 00:00:00', 1, 'STOCK', 5, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-30 14:02:14', '2026-04-30 14:02:14');
INSERT INTO `fg_stock` VALUES (166, '10260006', '2026-04-30 00:00:00', 1, 'STOCK', 5, 12572, 'abc', 1, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-30 14:31:36', '2026-04-30 14:36:38');

-- ----------------------------
-- Table structure for fg_stock_d
-- ----------------------------
DROP TABLE IF EXISTS `fg_stock_d`;
CREATE TABLE `fg_stock_d`  (
  `doc_detail_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK detail',
  `doc_id` bigint UNSIGNED NOT NULL COMMENT 'FK → fg_stock',
  `product_id` bigint UNSIGNED NOT NULL COMMENT 'FK → tmmst.m_product',
  `parts_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts. NULL untuk produk single-part',
  `qty` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'qty in atau qty out atau qty adj tergantung tipe',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'satuan',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'nomor lot sesuai fisik',
  `unit_price` decimal(20, 4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT 'harga ini tidak di tampilkan tapi menjadi dasar perhitungan fifo/lifo/avg',
  `qty_unitprice` decimal(20, 4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT 'menjadi dasar pelaporan nilai barang',
  `reference_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK dokumen sumber (wo_id, delivery_id, dll)',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'catatan atau referensi yang harus di sertakan',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'gudang dan lokasi detailnya. Link to tmmst.m_wh_loc',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'create oleh siapa',
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'update oleh siapa',
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'create kapan',
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'update kapan',
  PRIMARY KEY (`doc_detail_id`) USING BTREE,
  INDEX `idx_fg_d_doc`(`doc_id` ASC) USING BTREE,
  INDEX `idx_fg_d_product`(`product_id` ASC) USING BTREE,
  INDEX `idx_fg_d_parts`(`parts_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail movement stok FG per produk / komponen' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of fg_stock_d
-- ----------------------------

-- ----------------------------
-- Table structure for fg_stockcard
-- ----------------------------
DROP TABLE IF EXISTS `fg_stockcard`;
CREATE TABLE `fg_stockcard`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `previous_doc_id` bigint UNSIGNED NULL DEFAULT NULL,
  `doc_date` datetime NULL DEFAULT NULL,
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product',
  `parts_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts. NULL untuk single-part',
  `doc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'BB / IN / OUT / ADJ',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `qty` decimal(20, 4) NOT NULL DEFAULT 0.0000,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `reference_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → fg_stock_d.doc_detail_id',
  `qty_bb` decimal(20, 4) NULL DEFAULT NULL,
  `qty_in` decimal(20, 4) NULL DEFAULT NULL,
  `qty_out` decimal(20, 4) NULL DEFAULT NULL,
  `qty_adj` decimal(20, 4) NULL DEFAULT NULL,
  `qty_eb` decimal(20, 4) NULL DEFAULT NULL,
  `post_date` date NULL DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  INDEX `idx_fg_sc_product`(`product_id` ASC) USING BTREE,
  INDEX `idx_fg_sc_parts`(`parts_id` ASC) USING BTREE,
  INDEX `idx_fg_sc_date`(`doc_date` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Kartu stok FG Master stok FG per periode— diisi otomatis oleh trigger saat fg_stock confirm' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of fg_stockcard
-- ----------------------------

-- ----------------------------
-- Table structure for fg_stockmst
-- ----------------------------
DROP TABLE IF EXISTS `fg_stockmst`;
CREATE TABLE `fg_stockmst`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → i_period',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product',
  `parts_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts. NULL untuk single-part',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `qty_bb` decimal(20, 4) NULL DEFAULT NULL,
  `qty_in` decimal(20, 4) NULL DEFAULT NULL,
  `qty_out` decimal(20, 4) NULL DEFAULT NULL,
  `qty_adj` decimal(20, 4) NULL DEFAULT NULL,
  `qty_eb` decimal(20, 4) NULL DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  INDEX `idx_fg_mst_period`(`period_id` ASC) USING BTREE,
  INDEX `idx_fg_mst_product`(`product_id` ASC) USING BTREE,
  INDEX `idx_fg_mst_parts`(`parts_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'BELUM TENTU TERPAKAI Master stok FG per periode' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of fg_stockmst
-- ----------------------------

-- ----------------------------
-- Table structure for rm_booking
-- ----------------------------
DROP TABLE IF EXISTS `rm_booking`;
CREATE TABLE `rm_booking`  (
  `booking_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK header booking RM',
  `booking_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nomor dokumen, format 26BK00001',
  `booking_date` datetime NULL DEFAULT NULL COMMENT 'Tanggal booking',
  `booking_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL' COMMENT 'MANUAL = input user | WO = generate dari prd_wo_material',
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmmst.m_warehouse',
  `wo_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmprd.prd_wo (opsional)',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `stat_confirm` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=draft (Simpan Draft) | 1=terkunci (Simpan & Kunci) -> reservasi aktif',
  `stat_cancel` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=normal | 1=dibatalkan',
  `stat_closed` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=open | 1=selesai/terpenuhi seluruhnya',
  `confirm_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `confirm_date` datetime NULL DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`) USING BTREE,
  UNIQUE INDEX `uq_rm_bk_no`(`booking_no` ASC) USING BTREE,
  INDEX `idx_rm_bk_date`(`booking_date` ASC) USING BTREE,
  INDEX `idx_rm_bk_stat`(`stat_confirm` ASC, `stat_cancel` ASC, `stat_closed` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header booking/reservasi bahan baku — belum menyentuh ledger stok' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of rm_booking
-- ----------------------------

-- ----------------------------
-- Table structure for rm_booking_d
-- ----------------------------
DROP TABLE IF EXISTS `rm_booking_d`;
CREATE TABLE `rm_booking_d`  (
  `booking_detail_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK detail booking',
  `booking_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> rm_booking',
  `seq_no` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Urutan tampil baris',
  `co_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmsdi.s_co.doc_id (denormalisasi utk filter)',
  `co_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmsdi.s_co_detail.doc_detail_id',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmmst.m_product (snapshot dari CO detail)',
  `material_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> tmmst.m_raw_material',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'FK -> tmmst.m_uom',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmmst.m_wh_loc; NULL = belum ditentukan',
  `qty_need` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Total kebutuhan baris ini',
  `qty_book` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Dipesan dari stok tersedia (reservasi)',
  `qty_purchase` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Kekurangan -> dibeli',
  `qty_replacement` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ditutup oleh material pengganti',
  `qty_issued` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Realisasi keluar; diisi dari rm_stock_d saat issue',
  `material_replace_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmmst.m_raw_material',
  `uom_replace` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `replace_ratio` decimal(20, 6) NOT NULL DEFAULT 1.000000 COMMENT 'Konversi 1 unit material utama = n unit pengganti',
  `replace_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `substitution_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmprd.rawmat_substitution_recommendation (jika dari rekomendasi MCDM)',
  `pr_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> tmpur.p_requisition_d, terisi bila qty_purchase sudah di-PR',
  `note_detail` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `stat_cancel` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=normal | 1=baris dibatalkan',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_detail_id`) USING BTREE,
  INDEX `idx_rm_bkd_bk`(`booking_id` ASC) USING BTREE,
  INDEX `idx_rm_bkd_mat`(`material_id` ASC, `location_id` ASC) USING BTREE,
  INDEX `idx_rm_bkd_co`(`co_detail_id` ASC) USING BTREE,
  INDEX `idx_rm_bkd_rep`(`material_replace_id` ASC) USING BTREE,
  CONSTRAINT `fk_rm_bkd_bk` FOREIGN KEY (`booking_id`) REFERENCES `rm_booking` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail kebutuhan material per baris CO' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of rm_booking_d
-- ----------------------------

-- ----------------------------
-- Table structure for rm_stock
-- ----------------------------
DROP TABLE IF EXISTS `rm_stock`;
CREATE TABLE `rm_stock`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK header movement Raw Material',
  `doc_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nomor dokumen (SPK / nomor manual)',
  `doc_date` datetime NULL DEFAULT NULL COMMENT 'Tanggal & jam movement',
  `doc_type` tinyint NULL DEFAULT NULL COMMENT 'IN=1, OUT=-1',
  `doc_cat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'BB=beginning balance | STOCK=normal | ADJ=penyesuaian | RETURN=retur | OPNAME=stok opname',
  `wo_id` int UNSIGNED NOT NULL COMMENT 'Primary key unik Work Order',
  `wo_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nomor Work Order (CO-No + counter)',
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_warehouse',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Keterangan tambahan',
  `stat_confirm` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=draft, 1=confirmed → trigger otomatis isi rm_stockcard, confirmation saat release',
  `stat_confirm_plan` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'confirmation for plan',
  `stat_cancel` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=normal, 1=cancelled',
  `stat_closed` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=open, 1=closed/posted',
  `post_date` date NULL DEFAULT NULL COMMENT 'Tanggal posting ke accounting',
  `opn_start` datetime NULL DEFAULT NULL COMMENT 'opname start',
  `opn_stop` datetime NULL DEFAULT NULL COMMENT 'opname stop',
  `opn_officer` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'nama petugas yang melakukan opname',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `stat_auth` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'DRAFT' COMMENT 'DRAFT, WAITING, AP_1, RJ_1',
  `auth_module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Isi dengan OP atau AD',
  `current_approval_level` int UNSIGNED NULL DEFAULT 0 COMMENT '0=Draft, 1=Menunggu Appv Lvl 1',
  `current_approval_login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Login name Manajer yg harus approve',
  `doc_status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Keterangan text user friendly',
  PRIMARY KEY (`doc_id`) USING BTREE,
  INDEX `idx_rm_s_date`(`doc_date` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 101 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header movement stok RM — dikonfirm → trigger isi rm_stockcard' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rm_stock
-- ----------------------------
INSERT INTO `rm_stock` VALUES (5, '26SR00002', '2026-04-24 00:00:00', -1, 'AD', 0, NULL, 5, '', 1, 1, 1, 0, NULL, NULL, NULL, NULL, '', 'trial', '2026-04-24 00:00:00', '2026-07-09 10:38:04', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (6, '26SR00003', '2026-04-24 00:00:00', -1, 'STOCK', 0, NULL, 2, '', 1, 1, 0, 0, NULL, NULL, NULL, NULL, '', 'admin', '2026-04-24 00:00:00', '2026-07-07 14:04:21', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (7, '26SPB00001', NULL, 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00001', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-04-30 13:09:37', '2026-04-30 13:09:37', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (8, '26SPB00007', '2026-06-04 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00007', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-04 15:55:29', '2026-06-04 15:55:29', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (9, '26SPB00006', '2026-06-04 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00006', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-04 15:55:49', '2026-06-04 15:55:49', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (10, '26SPB00007', '2026-06-04 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00007', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-04 15:55:53', '2026-06-04 15:55:53', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (11, '26SPB00007', '2026-06-04 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00007', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-04 15:57:51', '2026-06-04 15:57:51', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (12, '26SPB00008', '2026-06-05 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00008', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-05 08:07:04', '2026-06-05 08:07:04', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (13, '26SPB00009', '2026-06-05 00:00:00', 1, 'STOCK', 0, NULL, 1, 'Auto dari p_receive: 26SPB00009', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-05 08:21:18', '2026-06-05 08:21:18', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (14, '26SPB00006', '2026-06-17 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00006', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-17 08:31:13', '2026-06-17 08:31:13', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (15, '26SPB00005', '2026-06-15 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00005', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-17 08:41:56', '2026-06-17 08:41:56', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (24, 'PRC1600000054', '2016-01-01 00:00:00', 1, 'STOCK', 0, NULL, 0, 'Auto dari p_receive: PRC1600000054', 1, 0, 0, 0, '2016-01-01', NULL, NULL, NULL, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (25, '26SPB00009', '2026-06-25 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00009', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-06-25 14:18:28', '2026-06-25 14:18:28', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (26, 'PRC1600000676', '2016-01-04 00:00:00', 1, 'STOCK', 0, NULL, 0, 'Auto dari p_receive: PRC1600000676', 1, 0, 0, 0, '2016-01-04', NULL, NULL, NULL, '_migrate', '_migrate', '2026-06-25 14:53:12', '2026-06-25 14:53:12', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (27, '26SPB00013', '2026-07-02 00:00:00', 1, 'STOCK', 0, NULL, 0, 'Auto dari p_receive: 26SPB00013', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-02 20:51:33', '2026-07-02 20:51:33', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (28, '26SPB00020', '2026-07-03 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00020', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 08:39:46', '2026-07-03 08:39:46', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (29, '26SPB00019', '2026-07-03 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00019', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 08:45:09', '2026-07-03 08:45:09', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (31, '26BB00005', NULL, 1, 'BB', 0, NULL, 5, 'coba beginnging balance', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (32, '26BB00005', NULL, 1, 'BB', 0, NULL, 1, 'coba beginning balance', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (33, '26BB00005', '2026-07-03 00:00:00', 1, 'BB', 0, NULL, 5, 'coba beginning balance lagi', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (34, '26BB00005', '2026-07-03 00:00:00', 1, 'BB', 0, NULL, 1, 'kenapa doc_no nya sama? ', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (35, '26BB00005', '2026-07-03 00:00:00', 1, 'BB', 0, NULL, 0, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (36, '26BB00005', '2026-07-03 00:00:00', 1, 'BB', 0, NULL, 5, 'doc_no masih belum sesuai', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (37, '26BB00006', '2026-07-03 00:00:00', 1, 'BB', 0, NULL, 5, 'akhirnya doc no nya increment', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (41, '26BB00007', '2026-07-03 00:00:00', 1, 'BB', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:14', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (43, '26BB00008', '2026-07-03 00:00:00', 1, 'BB', 0, NULL, 0, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 00:00:00', '2026-07-09 09:18:15', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (46, '26SR00004', '2026-07-03 00:00:00', -1, 'STOCK', 0, NULL, 5, 'Notes', 1, 1, 0, 0, NULL, NULL, NULL, NULL, '', '_system', '2026-07-03 00:00:00', '2026-07-07 14:04:21', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (47, '26SR00005', '2026-07-03 00:00:00', -1, 'STOCK', 0, NULL, 5, '', 1, 1, 0, 0, NULL, NULL, NULL, NULL, '', '_system', '2026-07-03 00:00:00', '2026-07-08 09:09:26', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (48, '26SR00006', '2026-07-03 00:00:00', -1, 'STOCK', 0, NULL, 0, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 21:24:29', '2026-07-03 21:24:29', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (49, '26SR00007', '2026-07-03 00:00:00', -1, 'STOCK', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-03 21:26:41', '2026-07-03 21:26:41', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (50, '26SR00008', '2026-07-04 00:00:00', -1, 'STOCK', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-04 10:08:48', '2026-07-04 10:08:48', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (51, '26SR00009', '2026-07-04 00:00:00', -1, 'STOCK', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-04 10:09:23', '2026-07-04 10:09:23', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (53, '26BB00009', '2026-07-04 00:00:00', 1, 'BB', 0, NULL, 1, 'TEST_INV_MAR26_TC01', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '_system', '2026-07-04 00:00:00', '2026-07-09 09:18:15', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (54, '26SR00010', '2026-07-06 00:00:00', -1, 'STOCK', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-06 09:26:13', '2026-07-06 09:26:13', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (55, '26SR00011', '2026-07-06 00:00:00', -1, 'STOCK', 0, NULL, 5, 'notes', 1, 1, 0, 0, NULL, NULL, NULL, NULL, '', '_system', '2026-07-06 00:00:00', '2026-07-06 00:00:00', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (57, '26BB00010', '2026-07-06 00:00:00', 1, 'BB', 0, NULL, 1, 'TEST_INV_MAR26_TC01', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-06 00:00:00', '2026-07-09 09:18:15', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (59, '26SR00012', '2026-07-06 00:00:00', -1, 'AD', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-06 00:00:00', '2026-07-09 10:38:04', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (60, '26SR00013', '2026-07-06 00:00:00', -1, 'AD', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-06 00:00:00', '2026-07-09 10:38:04', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (62, '26BB00011', '2026-07-06 00:00:00', 1, 'BB', 0, NULL, 5, '', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-06 00:00:00', '2026-07-09 09:18:15', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (65, '26OP00014', '2026-07-08 00:00:00', -1, 'OP', 0, NULL, 5, 'coba stock opname 1', 1, 0, 0, 0, NULL, '2026-07-08 14:33:00', '2026-07-08 15:33:00', 'Lucy', '', '_system', '2026-07-08 00:00:00', '2026-07-09 10:38:05', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (66, '26IN00001', '2026-07-03 00:00:00', 1, 'STOCK', 0, NULL, 5, 'Auto dari p_receive: 26SPB00021', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-09 10:07:28', '2026-07-09 10:07:28', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (67, '26IN00002', '2026-07-02 00:00:00', 1, 'IN', 0, NULL, 0, 'Auto dari p_receive: 26SPB00014', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-09 10:10:44', '2026-07-09 10:10:44', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (68, '26AD00014', '2026-07-09 00:00:00', -1, 'AD', 0, NULL, 5, 'coba stock adjustment', 1, 0, 0, 0, NULL, NULL, NULL, NULL, '', '_system', '2026-07-09 00:00:00', '2026-07-09 00:00:00', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (69, '26OP00015', '2026-07-09 00:00:00', -1, 'OP', 0, NULL, 5, 'Coba Opname Baru', 1, 0, 0, 0, NULL, '2026-07-09 11:24:00', '2026-07-09 10:24:00', 'Lucy', '', '_system', '2026-07-09 00:00:00', '2026-07-09 00:00:00', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (71, '26OU00014', '2026-07-09 00:00:00', -1, 'OU', 0, NULL, 0, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-09 15:39:57', '2026-07-09 15:39:57', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (72, '26OP00016', '2026-07-10 00:00:00', -1, 'OP', 0, NULL, 5, 'Notes', 1, 0, 0, 0, NULL, '2026-07-10 16:16:00', '2026-07-10 16:16:00', 'Lucy', '', '_system', '2026-07-10 00:00:00', '2026-07-10 00:00:00', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (73, '26OP00017', '2026-07-10 00:00:00', -1, 'OP', 0, NULL, 5, '', 1, 0, 0, 0, NULL, '2026-07-10 16:21:00', '2026-07-10 16:21:00', 'Lucy', '', '_system', '2026-07-10 00:00:00', '2026-07-10 00:00:00', '', NULL, 0, '', 'Ready approve by _system');
INSERT INTO `rm_stock` VALUES (74, '26OP00018', '2026-07-10 00:00:00', -1, 'OP', 0, NULL, 5, 'Note', 1, 0, 0, 0, NULL, '2026-07-10 19:41:00', '2026-07-10 19:41:00', 'Lucy', '', '_system', '2026-07-10 00:00:00', '2026-07-10 00:00:00', '', NULL, 0, '', 'Ready approve by _system');
INSERT INTO `rm_stock` VALUES (75, '26OP00019', '2026-07-10 00:00:00', -1, 'OP', 0, NULL, 5, '', 1, 0, 0, 0, NULL, '2026-07-10 21:54:00', '2026-07-10 21:54:00', 'Lucy', '', '_system', '2026-07-10 00:00:00', '2026-07-10 00:00:00', '', '', 0, '', '');
INSERT INTO `rm_stock` VALUES (76, '26OP00020', '2026-07-11 00:00:00', -1, 'OP', 0, NULL, 5, 'mencoba approval', 1, 0, 0, 0, NULL, '2026-07-11 06:34:00', '2026-07-11 07:34:00', 'Lucy', '', '_system', '2026-07-11 00:00:00', '2026-07-11 09:01:03', 'APR_', 'OP', 1, '', 'Approve by arinda');
INSERT INTO `rm_stock` VALUES (77, '26OP00021', '2026-07-11 00:00:00', -1, 'OP', 0, NULL, 5, 'qwertyuiop', 1, 0, 0, 0, NULL, '2026-07-11 09:10:00', '2026-07-11 09:10:00', 'Lucy', '', '_system', '2026-07-11 00:00:00', '2026-07-11 09:12:18', 'APR_', 'OP', 1, '', 'Approve by arinda');
INSERT INTO `rm_stock` VALUES (78, '26OP00022', '2026-07-11 00:00:00', -1, 'OP', 0, NULL, 5, 'qwdfbn', 1, 0, 0, 0, NULL, '2026-07-11 09:17:00', '2026-07-11 09:17:00', 'Comel', '', '_system', '2026-07-11 00:00:00', '2026-07-11 09:20:55', 'APR_', 'OP', 1, '', 'Approve by arinda');
INSERT INTO `rm_stock` VALUES (79, '26OP00023', '2026-07-11 00:00:00', -1, 'OP', 0, NULL, 5, 'qwerty', 1, 0, 0, 0, NULL, '2026-07-11 09:26:00', '2026-07-11 09:26:00', 'Lucy', '', '_system', '2026-07-11 00:00:00', '2026-07-11 09:27:45', 'APR_1', 'OP', 2, '', 'Approve by arinda');
INSERT INTO `rm_stock` VALUES (80, '26AD00014', '2026-07-11 00:00:00', -1, 'AD', 0, NULL, 5, 'qwerty', 1, 0, 0, 0, NULL, NULL, NULL, '', '', '_system', '2026-07-11 00:00:00', '2026-07-11 11:33:17', 'APR_1', 'AD', 2, '', 'Approve by nisa');
INSERT INTO `rm_stock` VALUES (81, '26OU00014', '2026-07-13 00:00:00', -1, 'OU', 0, NULL, 5, 'Notes', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-13 11:04:18', '2026-07-13 11:04:18', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (82, '26OU00014', '2026-07-14 00:00:00', -1, 'OU', 0, NULL, 0, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-14 10:41:34', '2026-07-14 10:41:34', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (83, '26OU00014', '2026-07-14 00:00:00', -1, 'OU', 0, NULL, 0, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-14 10:42:20', '2026-07-14 10:42:20', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (84, '26OU00014', '2026-07-14 00:00:00', -1, 'OU', 0, NULL, 0, '', 0, 0, 0, 0, NULL, NULL, NULL, NULL, '', '', '2026-07-14 13:32:39', '2026-07-14 13:32:39', 'DRAFT', NULL, 0, NULL, NULL);
INSERT INTO `rm_stock` VALUES (91, '26OU00015', '2026-07-15 00:00:00', -1, 'OU', 0, NULL, 5, '', 0, 1, 0, 0, NULL, NULL, NULL, '', '', '_system', '2026-07-15 00:00:00', '2026-07-15 00:00:00', '', '', 0, '', 'CONFIRMED By _system');
INSERT INTO `rm_stock` VALUES (92, '26OU00016', '2026-07-16 00:00:00', -1, 'OU', 0, NULL, 5, '', 0, 0, 0, 0, NULL, NULL, NULL, '', '', '_system', '2026-07-16 00:00:00', '2026-07-16 09:20:40', '', '', 0, '', 'DRAFT');
INSERT INTO `rm_stock` VALUES (95, '26OU00017', '2026-07-16 00:00:00', -1, 'OU', 0, '', 5, '', 1, 1, 0, 0, NULL, NULL, NULL, '', '', '_system', '2026-07-16 00:00:00', '2026-07-17 08:47:56', '', '', 0, '', 'DRAFT');
INSERT INTO `rm_stock` VALUES (96, '26OU00018', '2026-07-17 00:00:00', -1, 'OU', 0, '', 1, '', 1, 1, 0, 0, NULL, NULL, NULL, '', '', 'admin', '2026-07-17 00:00:00', '2026-07-17 08:29:51', '', '', 0, '', 'DRAFT');
INSERT INTO `rm_stock` VALUES (97, '26OU00019', '2026-07-17 00:00:00', -1, 'OU', 0, '', 5, '', 1, 1, 0, 0, NULL, NULL, NULL, '', '', 'admin', '2026-07-17 00:00:00', '2026-07-17 13:21:05', '', '', 0, '', 'DRAFT');
INSERT INTO `rm_stock` VALUES (98, '26OU00020', '2026-07-17 00:00:00', -1, 'OU', 0, '', 5, '', 1, 1, 0, 0, NULL, NULL, NULL, '', '', '_system', '2026-07-17 00:00:00', '2026-07-17 13:25:49', '', '', 0, '', 'DRAFT');
INSERT INTO `rm_stock` VALUES (99, '26OU00021', '2026-07-17 00:00:00', -1, 'OU', 0, '', 4, '', 0, 0, 0, 0, NULL, NULL, NULL, '', '', '', '2026-07-17 13:36:25', '2026-07-17 13:36:25', '', '', 0, '', 'DRAFT');
INSERT INTO `rm_stock` VALUES (100, '26OU00022', '2026-07-18 00:00:00', -1, 'OU', 0, '', 5, '', 0, 1, 0, 0, NULL, NULL, NULL, '', '', '_system', '2026-07-18 00:00:00', '2026-07-18 00:00:00', '', '', 0, '', 'CONFIRMED By _system');

-- ----------------------------
-- Table structure for rm_stock_d
-- ----------------------------
DROP TABLE IF EXISTS `rm_stock_d`;
CREATE TABLE `rm_stock_d`  (
  `doc_detail_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK detail movement RM',
  `doc_id` bigint UNSIGNED NOT NULL COMMENT 'FK → rm_stock',
  `material_id` bigint UNSIGNED NOT NULL COMMENT 'FK → tmmst.m_raw_material',
  `worm_id` int UNSIGNED NOT NULL COMMENT 'FK -> tmprd.prd_wo_material \r\nwo rm detail',
  `qty` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Qty IN atau OUT (selalu positif)',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `qty_required` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT 'Qty kebutuhan',
  `qty_available` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty stock yang availabel saat ini',
  `qty_booked` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty booking',
  `action_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'ISSUE / SUBSTITUTE / PURCHASE',
  `parent_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK self → doc_detail_id baris kebutuhan induk (untuk SUBSTITUTE/PURCHASE)',
  `co_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK CO detail dari tmsdi jika terkait order customer',
  `unit_price` decimal(20, 4) UNSIGNED NOT NULL DEFAULT 0.0000,
  `qty_unitprice` decimal(20, 4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT 'qty × unit_price',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `reference_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK dokumen sumber (p_receive_d, wo_id, dll)',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama tabel referensi',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_wh_loc',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_detail_id`) USING BTREE,
  INDEX `idx_rm_d_doc`(`doc_id` ASC) USING BTREE,
  INDEX `idx_rm_d_material`(`material_id` ASC) USING BTREE,
  INDEX `idx_rm_d_parent`(`parent_detail_id` ASC) USING BTREE,
  CONSTRAINT `fk_rm_d_parent` FOREIGN KEY (`parent_detail_id`) REFERENCES `rm_stock_d` (`doc_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 69 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail movement stok RM per bahan baku' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rm_stock_d
-- ----------------------------
INSERT INTO `rm_stock_d` VALUES (2, 3, 283, 0, 6000.0000, '', 0.0067, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '3', 0, '', 3, '', '', '2026-04-24 13:57:26', '2026-04-24 13:57:26');
INSERT INTO `rm_stock_d` VALUES (5, 6, 1, 0, 60.0000, 'LBR', 60.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, 'btn_confirm belum jalan', 0, '', '', '2026-04-24 14:49:53', '2026-07-07 11:46:03');
INSERT INTO `rm_stock_d` VALUES (14, 24, 5735, 0, 4.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, 76835.0000, 307340.0000, NULL, 5896, 'tmpur.p_receive_d', 0, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d` VALUES (15, 24, 5736, 0, 10.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, 86031.0000, 860310.0000, NULL, 13194, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d` VALUES (16, 24, 5738, 0, 12.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, 80949.0000, 971388.0000, NULL, 15609, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d` VALUES (17, 24, 5737, 0, 10.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, 75350.0000, 753500.0000, NULL, 15610, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d` VALUES (21, 25, 3404, 0, 2999.0000, '1 ', 0.0000, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, NULL, 23259, 'tmpur.p_receive_d', 1, '', '', '2026-06-25 14:18:28', '2026-06-25 14:18:28');
INSERT INTO `rm_stock_d` VALUES (22, 26, 5706, 0, 1.0000, 'LBR', 0.0000, NULL, NULL, NULL, NULL, NULL, 2120000.0000, 2120000.0000, NULL, 9973, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:53:12', '2026-06-25 14:53:12');
INSERT INTO `rm_stock_d` VALUES (23, 27, 9, 0, 100.0000, '22', 0.0000, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, NULL, 23293, 'tmpur.p_receive_d', 1, '', '', '2026-07-02 20:51:33', '2026-07-02 20:51:33');
INSERT INTO `rm_stock_d` VALUES (24, 28, 7, 0, 10.0000, '43', 0.0000, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, NULL, 23296, 'tmpur.p_receive_d', 1, '', '', '2026-07-03 08:39:46', '2026-07-03 08:39:46');
INSERT INTO `rm_stock_d` VALUES (25, 29, 7, 0, 10.0000, '31', 0.0000, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, '12345', 23298, 'tmpur.p_receive_d', 1, '', '', '2026-07-03 08:45:09', '2026-07-03 08:45:09');
INSERT INTO `rm_stock_d` VALUES (27, 31, 1, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No', 0, '', 1, '', '', '2026-07-03 10:01:09', '2026-07-03 10:01:27');
INSERT INTO `rm_stock_d` VALUES (28, 32, 1, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 1', 0, '', 1, '', '', '2026-07-03 10:17:36', '2026-07-03 10:17:36');
INSERT INTO `rm_stock_d` VALUES (29, 33, 1, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 2', 0, '', 1, '', '', '2026-07-03 10:28:23', '2026-07-03 10:28:23');
INSERT INTO `rm_stock_d` VALUES (30, 34, 1491, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 4', 0, '', 3, '', '', '2026-07-03 10:30:15', '2026-07-03 10:30:15');
INSERT INTO `rm_stock_d` VALUES (31, 37, 3058, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 5', 0, '', 1, '', '', '2026-07-03 11:09:15', '2026-07-03 11:09:15');
INSERT INTO `rm_stock_d` VALUES (32, 41, 1, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-03 14:37:53', '2026-07-03 14:37:53');
INSERT INTO `rm_stock_d` VALUES (33, 53, 843, 0, 1300.0000, 'LBR', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'MAR26-A5-PA1', 0, '', 1, '', '', '2026-07-04 13:52:54', '2026-07-04 13:52:54');
INSERT INTO `rm_stock_d` VALUES (34, 46, 3058, 0, 1000.0000, 'PCS', 1000.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '12345', 0, 'coba btn_confirm', 1, '', '', '2026-07-06 09:41:28', '2026-07-07 13:51:37');
INSERT INTO `rm_stock_d` VALUES (35, 57, 5533, 0, 1300.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'MAR26-A5-PA1', 0, '', 1, '', '', '2026-07-06 10:04:44', '2026-07-06 10:04:44');
INSERT INTO `rm_stock_d` VALUES (36, 55, 2325, 0, 1000.0000, 'PCS', 12345.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '12345', 0, '', 1, '', '', '2026-07-06 10:11:16', '2026-07-06 11:06:42');
INSERT INTO `rm_stock_d` VALUES (37, 59, 2750, 0, 0.0000, 'PCS', 12.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '123', 0, '', 3, '', '', '2026-07-06 13:12:10', '2026-07-06 13:12:10');
INSERT INTO `rm_stock_d` VALUES (38, 60, 2510, 0, 0.0000, 'PCS', 12.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '123', 0, '', 3, '', '', '2026-07-06 13:12:45', '2026-07-06 13:12:45');
INSERT INTO `rm_stock_d` VALUES (40, 62, 2325, 0, 123.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '123', 0, '', 0, '', '', '2026-07-06 13:25:46', '2026-07-06 13:25:46');
INSERT INTO `rm_stock_d` VALUES (41, 47, 606, 0, 10.0000, 'PCS', 10.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No', 0, 'coba confirm_action', 1, '', '', '2026-07-08 08:53:26', '2026-07-08 08:54:19');
INSERT INTO `rm_stock_d` VALUES (42, 65, 1, 0, 100.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, 0, 100000.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-08 11:53:15', '2026-07-08 11:53:15');
INSERT INTO `rm_stock_d` VALUES (43, 66, 7, 0, 10.0000, '1', 0.0000, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, '', 23297, 'tmpur.p_receive_d', 1, '', '', '2026-07-09 10:07:28', '2026-07-09 10:07:28');
INSERT INTO `rm_stock_d` VALUES (44, 67, 32, 0, 10.0000, '1', 0.0000, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, '', 23264, 'tmpur.p_receive_d', 1, '', '', '2026-07-09 10:10:44', '2026-07-09 10:10:44');
INSERT INTO `rm_stock_d` VALUES (45, 69, 1, 0, -10.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, 0, 100000.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-09 11:26:42', '2026-07-09 11:27:34');
INSERT INTO `rm_stock_d` VALUES (46, 68, 1, 0, -10.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-09 22:49:18', '2026-07-09 22:49:18');
INSERT INTO `rm_stock_d` VALUES (47, 72, 1, 0, -10.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 16:16:48', '2026-07-10 16:16:48');
INSERT INTO `rm_stock_d` VALUES (48, 73, 1, 0, 0.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 16:22:03', '2026-07-10 16:22:03');
INSERT INTO `rm_stock_d` VALUES (49, 74, 1, 0, 90.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 20:20:10', '2026-07-10 20:20:10');
INSERT INTO `rm_stock_d` VALUES (50, 75, 1, 0, 0.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 21:55:55', '2026-07-10 21:55:55');
INSERT INTO `rm_stock_d` VALUES (51, 76, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 07:38:37', '2026-07-11 07:38:37');
INSERT INTO `rm_stock_d` VALUES (52, 77, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 09:11:28', '2026-07-11 09:11:28');
INSERT INTO `rm_stock_d` VALUES (53, 78, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 09:18:14', '2026-07-11 09:18:14');
INSERT INTO `rm_stock_d` VALUES (54, 79, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 09:26:55', '2026-07-11 09:26:55');
INSERT INTO `rm_stock_d` VALUES (55, 80, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 11:12:52', '2026-07-11 11:12:52');
INSERT INTO `rm_stock_d` VALUES (57, 91, 1, 0, 50.0000, 'PCS', 50.0000, 180.0000, 50.0000, 'SUBTITUTE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-16 07:22:10', '2026-07-18 08:31:27');
INSERT INTO `rm_stock_d` VALUES (58, 92, 3404, 0, 0.0000, 'PCS', 3000.0000, 2999.0000, 2999.0000, 'SUBTITUTE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-16 08:12:50', '2026-07-16 08:12:50');
INSERT INTO `rm_stock_d` VALUES (62, 95, 1, 0, 130.0000, 'PCS', 200.0000, 130.0000, 130.0000, 'ISSUE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-16 11:34:08', '2026-07-16 13:20:51');
INSERT INTO `rm_stock_d` VALUES (63, 95, 5533, 0, 60.0000, 'PCS', 60.0000, 3900.0000, 60.0000, 'SUBTITUTE', 62, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-16 11:37:27', '2026-07-16 13:37:30');
INSERT INTO `rm_stock_d` VALUES (64, 95, 843, 0, 10.0000, 'PCS', 10.0000, 1300.0000, 10.0000, 'SUBTITUTE', 62, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-16 12:31:46', '2026-07-16 13:37:37');
INSERT INTO `rm_stock_d` VALUES (65, 96, 283, 0, 10.0000, 'LBR', 10.0000, 6000.0000, 10.0000, 'ISSUE', NULL, 0, 0.0000, 0.0000, '', 0, '', 3, '', '', '2026-07-17 08:29:08', '2026-07-17 08:29:51');
INSERT INTO `rm_stock_d` VALUES (66, 97, 3404, 0, 99.0000, 'PCS', 99.0000, 2999.0000, 99.0000, 'ISSUE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-17 09:04:35', '2026-07-17 09:06:53');
INSERT INTO `rm_stock_d` VALUES (67, 98, 9, 0, 10.0000, 'PCS', 10.0000, 100.0000, 10.0000, 'ISSUE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-17 13:23:03', '2026-07-17 13:24:08');
INSERT INTO `rm_stock_d` VALUES (68, 100, 9, 0, 10.0000, 'PCS', 10.0000, 90.0000, 10.0000, 'ISSUE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-18 08:34:59', '2026-07-18 08:35:24');

-- ----------------------------
-- Table structure for rm_stock_d_bak
-- ----------------------------
DROP TABLE IF EXISTS `rm_stock_d_bak`;
CREATE TABLE `rm_stock_d_bak`  (
  `doc_detail_id` bigint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'PK detail movement RM',
  `doc_id` bigint UNSIGNED NOT NULL COMMENT 'FK → rm_stock',
  `material_id` bigint UNSIGNED NOT NULL COMMENT 'FK → tmmst.m_raw_material',
  `worm_id` int UNSIGNED NOT NULL COMMENT 'FK -> tmprd.prd_wo_material \r\nwo rm detail',
  `qty` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Qty IN atau OUT (selalu positif)',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `qty_required` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT 'Qty kebutuhan',
  `qty_available` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty stock yang availabel saat ini',
  `qty_booked` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty booking',
  `qty_replacement` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty pengganti',
  `material_replacement_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Material pengganti',
  `action_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'ISSUE / SUBSTITUTE / PURCHASE',
  `parent_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK self → doc_detail_id baris kebutuhan induk (untuk SUBSTITUTE/PURCHASE)',
  `co_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK CO detail dari tmsdi jika terkait order customer',
  `unit_price` decimal(20, 4) UNSIGNED NOT NULL DEFAULT 0.0000,
  `qty_unitprice` decimal(20, 4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT 'qty × unit_price',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `reference_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK dokumen sumber (p_receive_d, wo_id, dll)',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama tabel referensi',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_wh_loc',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rm_stock_d_bak
-- ----------------------------
INSERT INTO `rm_stock_d_bak` VALUES (2, 3, 283, 0, 6000.0000, '', 0.0067, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '3', 0, '', 3, '', '', '2026-04-24 13:57:26', '2026-04-24 13:57:26');
INSERT INTO `rm_stock_d_bak` VALUES (5, 6, 1, 0, 60.0000, 'LBR', 60.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, 'btn_confirm belum jalan', 0, '', '', '2026-04-24 14:49:53', '2026-07-07 11:46:03');
INSERT INTO `rm_stock_d_bak` VALUES (14, 24, 5735, 0, 4.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 76835.0000, 307340.0000, NULL, 5896, 'tmpur.p_receive_d', 0, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d_bak` VALUES (15, 24, 5736, 0, 10.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 86031.0000, 860310.0000, NULL, 13194, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d_bak` VALUES (16, 24, 5738, 0, 12.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 80949.0000, 971388.0000, NULL, 15609, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d_bak` VALUES (17, 24, 5737, 0, 10.0000, 'KG', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 75350.0000, 753500.0000, NULL, 15610, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stock_d_bak` VALUES (21, 25, 3404, 0, 2999.0000, '1 ', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, NULL, 23259, 'tmpur.p_receive_d', 1, '', '', '2026-06-25 14:18:28', '2026-06-25 14:18:28');
INSERT INTO `rm_stock_d_bak` VALUES (22, 26, 5706, 0, 1.0000, 'LBR', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2120000.0000, 2120000.0000, NULL, 9973, 'tmpur.p_receive_d', NULL, '_migrate', '_migrate', '2026-06-25 14:53:12', '2026-06-25 14:53:12');
INSERT INTO `rm_stock_d_bak` VALUES (23, 27, 9, 0, 100.0000, '22', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, NULL, 23293, 'tmpur.p_receive_d', 1, '', '', '2026-07-02 20:51:33', '2026-07-02 20:51:33');
INSERT INTO `rm_stock_d_bak` VALUES (24, 28, 7, 0, 10.0000, '43', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, NULL, 23296, 'tmpur.p_receive_d', 1, '', '', '2026-07-03 08:39:46', '2026-07-03 08:39:46');
INSERT INTO `rm_stock_d_bak` VALUES (25, 29, 7, 0, 10.0000, '31', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, '12345', 23298, 'tmpur.p_receive_d', 1, '', '', '2026-07-03 08:45:09', '2026-07-03 08:45:09');
INSERT INTO `rm_stock_d_bak` VALUES (27, 31, 1, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No', 0, '', 1, '', '', '2026-07-03 10:01:09', '2026-07-03 10:01:27');
INSERT INTO `rm_stock_d_bak` VALUES (28, 32, 1, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 1', 0, '', 1, '', '', '2026-07-03 10:17:36', '2026-07-03 10:17:36');
INSERT INTO `rm_stock_d_bak` VALUES (29, 33, 1, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 2', 0, '', 1, '', '', '2026-07-03 10:28:23', '2026-07-03 10:28:23');
INSERT INTO `rm_stock_d_bak` VALUES (30, 34, 1491, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 4', 0, '', 3, '', '', '2026-07-03 10:30:15', '2026-07-03 10:30:15');
INSERT INTO `rm_stock_d_bak` VALUES (31, 37, 3058, 0, 1000.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No 5', 0, '', 1, '', '', '2026-07-03 11:09:15', '2026-07-03 11:09:15');
INSERT INTO `rm_stock_d_bak` VALUES (32, 41, 1, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-03 14:37:53', '2026-07-03 14:37:53');
INSERT INTO `rm_stock_d_bak` VALUES (33, 53, 843, 0, 1300.0000, 'LBR', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'MAR26-A5-PA1', 0, '', 1, '', '', '2026-07-04 13:52:54', '2026-07-04 13:52:54');
INSERT INTO `rm_stock_d_bak` VALUES (34, 46, 3058, 0, 1000.0000, 'PCS', 1000.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '12345', 0, 'coba btn_confirm', 1, '', '', '2026-07-06 09:41:28', '2026-07-07 13:51:37');
INSERT INTO `rm_stock_d_bak` VALUES (35, 57, 5533, 0, 1300.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'MAR26-A5-PA1', 0, '', 1, '', '', '2026-07-06 10:04:44', '2026-07-06 10:04:44');
INSERT INTO `rm_stock_d_bak` VALUES (36, 55, 2325, 0, 1000.0000, 'PCS', 12345.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '12345', 0, '', 1, '', '', '2026-07-06 10:11:16', '2026-07-06 11:06:42');
INSERT INTO `rm_stock_d_bak` VALUES (37, 59, 2750, 0, 0.0000, 'PCS', 12.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '123', 0, '', 3, '', '', '2026-07-06 13:12:10', '2026-07-06 13:12:10');
INSERT INTO `rm_stock_d_bak` VALUES (38, 60, 2510, 0, 0.0000, 'PCS', 12.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '123', 0, '', 3, '', '', '2026-07-06 13:12:45', '2026-07-06 13:12:45');
INSERT INTO `rm_stock_d_bak` VALUES (40, 62, 2325, 0, 123.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '123', 0, '', 0, '', '', '2026-07-06 13:25:46', '2026-07-06 13:25:46');
INSERT INTO `rm_stock_d_bak` VALUES (41, 47, 606, 0, 10.0000, 'PCS', 10.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, 'Lot No', 0, 'coba confirm_action', 1, '', '', '2026-07-08 08:53:26', '2026-07-08 08:54:19');
INSERT INTO `rm_stock_d_bak` VALUES (42, 65, 1, 0, 100.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 100000.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-08 11:53:15', '2026-07-08 11:53:15');
INSERT INTO `rm_stock_d_bak` VALUES (43, 66, 7, 0, 10.0000, '1', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, '', 23297, 'tmpur.p_receive_d', 1, '', '', '2026-07-09 10:07:28', '2026-07-09 10:07:28');
INSERT INTO `rm_stock_d_bak` VALUES (44, 67, 32, 0, 10.0000, '1', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 0.0000, '', 23264, 'tmpur.p_receive_d', 1, '', '', '2026-07-09 10:10:44', '2026-07-09 10:10:44');
INSERT INTO `rm_stock_d_bak` VALUES (45, 69, 1, 0, -10.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 100000.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-09 11:26:42', '2026-07-09 11:27:34');
INSERT INTO `rm_stock_d_bak` VALUES (46, 68, 1, 0, -10.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-09 22:49:18', '2026-07-09 22:49:18');
INSERT INTO `rm_stock_d_bak` VALUES (47, 72, 1, 0, -10.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 16:16:48', '2026-07-10 16:16:48');
INSERT INTO `rm_stock_d_bak` VALUES (48, 73, 1, 0, 0.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 16:22:03', '2026-07-10 16:22:03');
INSERT INTO `rm_stock_d_bak` VALUES (49, 74, 1, 0, 90.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 20:20:10', '2026-07-10 20:20:10');
INSERT INTO `rm_stock_d_bak` VALUES (50, 75, 1, 0, 0.0000, 'PCS', 100.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-10 21:55:55', '2026-07-10 21:55:55');
INSERT INTO `rm_stock_d_bak` VALUES (51, 76, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 07:38:37', '2026-07-11 07:38:37');
INSERT INTO `rm_stock_d_bak` VALUES (52, 77, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 09:11:28', '2026-07-11 09:11:28');
INSERT INTO `rm_stock_d_bak` VALUES (53, 78, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 09:18:14', '2026-07-11 09:18:14');
INSERT INTO `rm_stock_d_bak` VALUES (54, 79, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 09:26:55', '2026-07-11 09:26:55');
INSERT INTO `rm_stock_d_bak` VALUES (55, 80, 604, 0, 0.0000, 'PCS', 0.0000, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.0000, 0.0000, '', 0, '', 0, '', '', '2026-07-11 11:12:52', '2026-07-11 11:12:52');
INSERT INTO `rm_stock_d_bak` VALUES (57, 91, 1, 0, 0.0000, 'PCS', 50.0000, 180.0000, 50.0000, 50.0000, 283, 'SUBTITUTE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-16 07:22:10', '2026-07-16 07:32:02');
INSERT INTO `rm_stock_d_bak` VALUES (58, 92, 3404, 0, 0.0000, 'PCS', 3000.0000, 2999.0000, 2999.0000, 1.0000, 1679, 'SUBTITUTE', NULL, 0, 0.0000, 0.0000, '', 0, '', 1, '', '', '2026-07-16 08:12:50', '2026-07-16 08:12:50');

-- ----------------------------
-- Table structure for rm_stockcard
-- ----------------------------
DROP TABLE IF EXISTS `rm_stockcard`;
CREATE TABLE `rm_stockcard`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK kartu stok RM',
  `previous_doc_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'ID record sebelumnya — chain untuk audit trail',
  `doc_date` datetime NULL DEFAULT NULL,
  `material_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_raw_material',
  `doc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'BB / IN / OUT / ADJ',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Duplikasi untuk laporan cepat',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `qty` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Qty signed: + masuk, - keluar',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `reference_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → rm_stock_d.doc_detail_id atau p_receive_d.doc_detail_id',
  `qty_bb` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Saldo sebelum movement',
  `qty_in` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty masuk pada record ini',
  `qty_out` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty keluar pada record ini',
  `qty_adj` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Qty penyesuaian pada record ini',
  `qty_eb` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Saldo setelah movement',
  `post_date` date NULL DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  INDEX `idx_rm_sc_material`(`material_id` ASC) USING BTREE,
  INDEX `idx_rm_sc_date`(`doc_date` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 47 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Kartu stok RM Master stok RM per periode  — diisi otomatis oleh trigger saat rm_stock confirm' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of rm_stockcard
-- ----------------------------
INSERT INTO `rm_stockcard` VALUES (1, NULL, '2026-04-24 16:12:30', 283, 'IN', 3, 1, '3', 6000.0000, '', 2, 0.0000, 6000.0000, 0.0000, 0.0000, 6000.0000, NULL, '', NULL, '2026-04-24 13:58:12', '2026-04-24 16:12:38');
INSERT INTO `rm_stockcard` VALUES (10, NULL, '2016-01-01 00:00:00', 5735, 'IN', 0, 0, NULL, 4.0000, 'KG', 14, 0.0000, 4.0000, 0.0000, 0.0000, 4.0000, NULL, '_migrate', NULL, '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stockcard` VALUES (11, NULL, '2016-01-01 00:00:00', 5736, 'IN', NULL, 0, NULL, 10.0000, 'KG', 15, 0.0000, 10.0000, 0.0000, 0.0000, 10.0000, NULL, '_migrate', NULL, '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stockcard` VALUES (12, NULL, '2016-01-01 00:00:00', 5738, 'IN', NULL, 0, NULL, 12.0000, 'KG', 16, 0.0000, 12.0000, 0.0000, 0.0000, 12.0000, NULL, '_migrate', NULL, '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stockcard` VALUES (13, NULL, '2016-01-01 00:00:00', 5737, 'IN', NULL, 0, NULL, 10.0000, 'KG', 17, 0.0000, 10.0000, 0.0000, 0.0000, 10.0000, NULL, '_migrate', NULL, '2026-06-25 14:14:51', '2026-06-25 14:14:51');
INSERT INTO `rm_stockcard` VALUES (14, NULL, '2026-06-25 00:00:00', 3404, 'IN', 1, 5, NULL, 2999.0000, '1 ', 21, 0.0000, 2999.0000, 0.0000, 0.0000, 2999.0000, NULL, '', NULL, '2026-06-25 14:18:28', '2026-06-25 14:18:28');
INSERT INTO `rm_stockcard` VALUES (15, NULL, '2016-01-04 00:00:00', 5706, 'IN', NULL, 0, NULL, 1.0000, 'LBR', 22, 0.0000, 1.0000, 0.0000, 0.0000, 1.0000, NULL, '_migrate', NULL, '2026-06-25 14:53:12', '2026-06-25 14:53:12');
INSERT INTO `rm_stockcard` VALUES (16, NULL, '2026-07-02 00:00:00', 9, 'IN', 1, 0, NULL, 100.0000, '22', 23, 0.0000, 100.0000, 0.0000, 0.0000, 100.0000, NULL, '', NULL, '2026-07-02 20:51:33', '2026-07-02 20:51:33');
INSERT INTO `rm_stockcard` VALUES (17, NULL, '2026-07-03 00:00:00', 7, 'IN', 1, 5, NULL, 10.0000, '43', 24, 0.0000, 10.0000, 0.0000, 0.0000, 10.0000, NULL, '', NULL, '2026-07-03 08:39:46', '2026-07-03 08:39:46');
INSERT INTO `rm_stockcard` VALUES (18, 17, '2026-07-03 00:00:00', 7, 'IN', 1, 5, '12345', 10.0000, '31', 25, 10.0000, 10.0000, 0.0000, 0.0000, 20.0000, NULL, '', NULL, '2026-07-03 08:45:09', '2026-07-03 08:45:09');
INSERT INTO `rm_stockcard` VALUES (19, NULL, '2026-07-03 00:00:00', 3058, 'IN', 1, 5, 'Lot No 5', 1000.0000, 'PCS', 31, 0.0000, 1000.0000, 0.0000, 0.0000, 1000.0000, NULL, '', NULL, '2026-07-03 13:17:15', '2026-07-03 13:17:15');
INSERT INTO `rm_stockcard` VALUES (20, NULL, '2026-07-04 00:00:00', 843, 'BB', 1, 1, 'MAR26-A5-PA1', 1300.0000, 'LBR', 33, 0.0000, 1300.0000, 0.0000, 0.0000, 1300.0000, NULL, '', NULL, '2026-07-06 08:26:43', '2026-07-06 08:26:43');
INSERT INTO `rm_stockcard` VALUES (21, NULL, '2026-07-06 00:00:00', 5533, 'BB', 1, 1, 'MAR26-A5-PA1', 1300.0000, 'PCS', 35, 0.0000, 1300.0000, 0.0000, 0.0000, 1300.0000, NULL, '', NULL, '2026-07-06 10:58:45', '2026-07-06 10:58:45');
INSERT INTO `rm_stockcard` VALUES (24, 21, '2026-07-06 00:00:00', 5533, 'IN', 1, 1, 'MAR26-A5-PA1', 1300.0000, 'PCS', 35, 1300.0000, 1300.0000, 0.0000, 0.0000, 2600.0000, NULL, '', NULL, '2026-07-06 11:03:21', '2026-07-06 11:03:21');
INSERT INTO `rm_stockcard` VALUES (25, 24, '2026-07-06 00:00:00', 5533, 'IN', 1, 1, 'MAR26-A5-PA1', 1300.0000, 'PCS', 35, 2600.0000, 1300.0000, 0.0000, 0.0000, 3900.0000, NULL, '', NULL, '2026-07-06 11:04:06', '2026-07-06 11:04:06');
INSERT INTO `rm_stockcard` VALUES (26, NULL, '2026-07-06 00:00:00', 2325, 'IN', 0, 5, '123', 123.0000, 'PCS', 40, 0.0000, 123.0000, 0.0000, 0.0000, 123.0000, NULL, '', NULL, '2026-07-06 13:25:57', '2026-07-06 13:25:57');
INSERT INTO `rm_stockcard` VALUES (27, NULL, NULL, 1, 'IN', 1, 5, 'Lot No', 1000.0000, 'PCS', 27, 0.0000, 1000.0000, 0.0000, 0.0000, 1000.0000, NULL, '', NULL, '2026-07-06 13:33:53', '2026-07-06 13:33:53');
INSERT INTO `rm_stockcard` VALUES (28, NULL, '2026-04-24 00:00:00', 1, 'OUT', 0, 2, '', -60.0000, 'LBR', 5, 0.0000, 0.0000, 60.0000, 0.0000, -60.0000, NULL, '', NULL, '2026-07-07 14:04:21', '2026-07-07 14:04:21');
INSERT INTO `rm_stockcard` VALUES (29, NULL, '2026-07-03 00:00:00', 3058, 'OUT', 1, 5, '12345', -1000.0000, 'PCS', 34, 0.0000, 0.0000, 1000.0000, 0.0000, -1000.0000, NULL, '', NULL, '2026-07-07 14:04:21', '2026-07-07 14:04:21');
INSERT INTO `rm_stockcard` VALUES (30, NULL, '2026-07-03 00:00:00', 606, 'OUT', 1, 5, 'Lot No', -10.0000, 'PCS', 41, 0.0000, 0.0000, 10.0000, 0.0000, -10.0000, NULL, '', NULL, '2026-07-08 09:09:26', '2026-07-08 09:09:26');
INSERT INTO `rm_stockcard` VALUES (31, NULL, '2026-07-08 00:00:00', 1, 'ADJ', 1, 5, '', 100.0000, 'PCS', 42, 0.0000, 0.0000, 0.0000, 100.0000, 100.0000, NULL, '', NULL, '2026-07-08 15:46:13', '2026-07-08 15:46:13');
INSERT INTO `rm_stockcard` VALUES (32, 18, '2026-07-03 00:00:00', 7, 'IN', 1, 5, '', 10.0000, '1', 43, 20.0000, 10.0000, 0.0000, 0.0000, 30.0000, NULL, '', NULL, '2026-07-09 10:07:28', '2026-07-09 10:07:28');
INSERT INTO `rm_stockcard` VALUES (33, NULL, '2026-07-02 00:00:00', 32, 'IN', 1, 0, '', 10.0000, '1', 44, 0.0000, 10.0000, 0.0000, 0.0000, 10.0000, NULL, '', NULL, '2026-07-09 10:10:44', '2026-07-09 10:10:44');
INSERT INTO `rm_stockcard` VALUES (34, 31, '2026-07-09 00:00:00', 1, 'AD', 1, 5, '', -10.0000, 'PCS', 45, 100.0000, 0.0000, 0.0000, -10.0000, 90.0000, NULL, '', NULL, '2026-07-10 08:31:11', '2026-07-10 08:31:11');
INSERT INTO `rm_stockcard` VALUES (35, 31, '2026-07-09 00:00:00', 1, 'AD', 1, 5, '', -10.0000, 'PCS', 46, 100.0000, 0.0000, 0.0000, -10.0000, 90.0000, NULL, '', NULL, '2026-07-10 08:32:21', '2026-07-10 08:32:21');
INSERT INTO `rm_stockcard` VALUES (36, 35, '2026-07-10 00:00:00', 1, 'AD', 1, 5, '', -10.0000, 'PCS', 47, 90.0000, 0.0000, 0.0000, -10.0000, 80.0000, NULL, '', NULL, '2026-07-10 16:16:57', '2026-07-10 16:16:57');
INSERT INTO `rm_stockcard` VALUES (37, 36, '2026-07-10 00:00:00', 1, 'AD', 1, 5, '', 90.0000, 'PCS', 49, 80.0000, 0.0000, 0.0000, 90.0000, 170.0000, NULL, '', NULL, '2026-07-10 20:20:26', '2026-07-10 20:20:26');
INSERT INTO `rm_stockcard` VALUES (38, 35, '2026-07-10 00:00:00', 1, 'AD', 1, 5, '', 90.0000, 'PCS', 49, 90.0000, 0.0000, 0.0000, 90.0000, 180.0000, NULL, '', NULL, '2026-07-10 20:20:30', '2026-07-10 20:20:30');
INSERT INTO `rm_stockcard` VALUES (39, 38, '2026-07-16 00:00:00', 1, 'OUT', 1, 5, '', -130.0000, 'PCS', 62, 180.0000, 0.0000, 130.0000, 0.0000, 50.0000, NULL, '', NULL, '2026-07-17 08:18:11', '2026-07-17 08:18:11');
INSERT INTO `rm_stockcard` VALUES (40, 25, '2026-07-16 00:00:00', 5533, 'OUT', 1, 5, '', -60.0000, 'PCS', 63, 3900.0000, 0.0000, 60.0000, 0.0000, 3840.0000, NULL, '', NULL, '2026-07-17 08:18:11', '2026-07-17 08:18:11');
INSERT INTO `rm_stockcard` VALUES (41, 20, '2026-07-16 00:00:00', 843, 'OUT', 1, 5, '', -10.0000, 'PCS', 64, 1300.0000, 0.0000, 10.0000, 0.0000, 1290.0000, NULL, '', NULL, '2026-07-17 08:18:11', '2026-07-17 08:18:11');
INSERT INTO `rm_stockcard` VALUES (42, 39, '2026-07-16 00:00:00', 1, 'OUT', 1, 5, '', -130.0000, 'PCS', 62, 50.0000, 0.0000, 130.0000, 0.0000, -80.0000, NULL, '', NULL, '2026-07-17 08:47:56', '2026-07-17 08:47:56');
INSERT INTO `rm_stockcard` VALUES (43, 40, '2026-07-16 00:00:00', 5533, 'OUT', 1, 5, '', -60.0000, 'PCS', 63, 3840.0000, 0.0000, 60.0000, 0.0000, 3780.0000, NULL, '', NULL, '2026-07-17 08:47:56', '2026-07-17 08:47:56');
INSERT INTO `rm_stockcard` VALUES (44, 41, '2026-07-16 00:00:00', 843, 'OUT', 1, 5, '', -10.0000, 'PCS', 64, 1290.0000, 0.0000, 10.0000, 0.0000, 1280.0000, NULL, '', NULL, '2026-07-17 08:47:56', '2026-07-17 08:47:56');
INSERT INTO `rm_stockcard` VALUES (45, 14, '2026-07-17 00:00:00', 3404, 'OUT', 1, 5, '', -99.0000, 'PCS', 66, 2999.0000, 0.0000, 99.0000, 0.0000, 2900.0000, NULL, '', NULL, '2026-07-17 13:21:05', '2026-07-17 13:21:05');
INSERT INTO `rm_stockcard` VALUES (46, 16, '2026-07-17 00:00:00', 9, 'OUT', 1, 5, '', -10.0000, 'PCS', 67, 100.0000, 0.0000, 10.0000, 0.0000, 90.0000, NULL, '', NULL, '2026-07-17 13:25:49', '2026-07-17 13:25:49');

-- ----------------------------
-- Table structure for sfg_stock
-- ----------------------------
DROP TABLE IF EXISTS `sfg_stock`;
CREATE TABLE `sfg_stock`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK header movement SFG',
  `doc_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nomor dokumen — bisa nomor WO/SPK dari produksi',
  `doc_date` datetime NULL DEFAULT NULL,
  `doc_type` tinyint NULL DEFAULT NULL COMMENT 'IN=1 (dari produksi), OUT=-1 (ke FG atau write-off)',
  `doc_cat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'BB | STOCK | ADJ | OPNAME | CANCEL (WO dibatalkan)',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product (SKU induk, untuk filter laporan)',
  `wo_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmprd.prd_wo (Work Order sumber)',
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `stat_confirm` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=draft, 1=confirmed → trigger otomatis isi sfg_stockcard',
  `stat_cancel` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `stat_closed` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `post_date` date NULL DEFAULT NULL,
  `opn_start` datetime NULL DEFAULT NULL COMMENT 'opname start',
  `opn_stop` datetime NULL DEFAULT NULL COMMENT 'opname stop',
  `opn_officer` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'nama petugas yang melakukan opname',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  INDEX `idx_sfg_s_product`(`product_id` ASC) USING BTREE,
  INDEX `idx_sfg_s_wo`(`wo_id` ASC) USING BTREE,
  INDEX `idx_sfg_s_date`(`doc_date` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header movement stok SFG/WIP — dikonfirm → trigger isi sfg_stockcard' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sfg_stock
-- ----------------------------

-- ----------------------------
-- Table structure for sfg_stock_d
-- ----------------------------
DROP TABLE IF EXISTS `sfg_stock_d`;
CREATE TABLE `sfg_stock_d`  (
  `doc_detail_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_id` bigint UNSIGNED NOT NULL COMMENT 'FK → sfg_stock',
  `parts_id` bigint UNSIGNED NOT NULL COMMENT 'FK → tmmst.m_product_parts (badan / tutup / komponen lain)',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Denormalisasi dari m_product_parts.product_id — query cepat',
  `qty` decimal(20, 4) NOT NULL DEFAULT 0.0000,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `unit_price` decimal(20, 4) NOT NULL DEFAULT 0.0000,
  `qty_unitprice` decimal(20, 4) NOT NULL DEFAULT 0.0000,
  `reference_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK dokumen sumber: wo_id, delivery_id, dll',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `location_id` bigint UNSIGNED NULL DEFAULT NULL,
  `cancel_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Alasan pembatalan — diisi jika doc_cat = CANCEL',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_detail_id`) USING BTREE,
  INDEX `idx_sfg_d_doc`(`doc_id` ASC) USING BTREE,
  INDEX `idx_sfg_d_parts`(`parts_id` ASC) USING BTREE,
  INDEX `idx_sfg_d_product`(`product_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail movement stok SFG per komponen produk' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sfg_stock_d
-- ----------------------------

-- ----------------------------
-- Table structure for sfg_stockcard
-- ----------------------------
DROP TABLE IF EXISTS `sfg_stockcard`;
CREATE TABLE `sfg_stockcard`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `previous_doc_id` bigint UNSIGNED NULL DEFAULT NULL,
  `doc_date` datetime NULL DEFAULT NULL,
  `parts_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Denormalisasi untuk query cepat',
  `doc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'BB / IN / OUT / ADJ / CANCEL',
  `location_id` bigint UNSIGNED NULL DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NULL DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `qty` decimal(20, 4) NOT NULL DEFAULT 0.0000,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `reference_detail_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK → sfg_stock_d.doc_detail_id',
  `qty_bb` decimal(20, 4) NULL DEFAULT NULL,
  `qty_in` decimal(20, 4) NULL DEFAULT NULL,
  `qty_out` decimal(20, 4) NULL DEFAULT NULL,
  `qty_adj` decimal(20, 4) NULL DEFAULT NULL,
  `qty_eb` decimal(20, 4) NULL DEFAULT NULL,
  `post_date` date NULL DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  INDEX `idx_sfg_sc_parts`(`parts_id` ASC) USING BTREE,
  INDEX `idx_sfg_sc_product`(`product_id` ASC) USING BTREE,
  INDEX `idx_sfg_sc_date`(`doc_date` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Kartu stok SFG Master stok SFG per periode — diisi otomatis oleh trigger saat sfg_stock confirm' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sfg_stockcard
-- ----------------------------

-- ----------------------------
-- View structure for v1_m_approval
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_approval`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_approval` AS select `a`.`auth_id` AS `auth_id`,`a`.`auth_module` AS `auth_module`,`a`.`auth_total_level` AS `auth_total_level`,`a`.`notes` AS `notes`,`a`.`is_active` AS `is_active`,`a`.`create_log` AS `create_log`,`a`.`update_log` AS `update_log`,`a`.`create_by` AS `create_by`,`a`.`update_by` AS `update_by`,`ad`.`auth_detail_id` AS `auth_detail_id`,`ad`.`auth_name` AS `auth_name`,`ad`.`auth_level` AS `auth_level`,`ad`.`auth_label_name` AS `auth_label_name`,`ad`.`auth_label_position` AS `auth_label_position`,`ad`.`auth_label_level` AS `auth_label_level` from (`tmmst`.`m_authorize` `a` join `tmmst`.`m_authorize_detail` `ad` on((`ad`.`auth_id` = `a`.`auth_id`)));

-- ----------------------------
-- View structure for v1_m_constant
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_constant`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_constant` AS select `tmmst`.`m_constant`.`id` AS `id`,`tmmst`.`m_constant`.`constant_string` AS `constant_string`,`tmmst`.`m_constant`.`constant_number` AS `constant_number`,`tmmst`.`m_constant`.`constant_type` AS `constant_type`,`tmmst`.`m_constant`.`remark` AS `remark`,`tmmst`.`m_constant`.`update_log` AS `update_log`,`tmmst`.`m_constant`.`create_log` AS `create_log` from `tmmst`.`m_constant`;

-- ----------------------------
-- View structure for v1_m_currency
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_currency`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_currency` AS select `c`.`id` AS `id`,`c`.`currency_code` AS `currency_code`,`c`.`currency_name` AS `currency_name`,`c`.`tax_percentage` AS `tax_percentage`,`c`.`country` AS `country`,`c`.`symbol` AS `symbol`,`c`.`counted_word` AS `counted_word`,`c`.`create_log` AS `create_log`,`c`.`update_log` AS `update_log`,`c`.`create_by` AS `create_by`,`c`.`update_by` AS `update_by` from `tmmst`.`m_currency` `c`;

-- ----------------------------
-- View structure for v1_m_print_cardmat
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_print_cardmat`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_print_cardmat` AS select `sc`.`doc_id` AS `doc_id`,`sc`.`doc_date` AS `doc_date`,`m`.`material_name` AS `material_name`,`w`.`warehouse_name` AS `warehouse_name`,`l`.`location_name` AS `location_name`,`sc`.`lot_no` AS `lot_no`,`sc`.`qty` AS `qty`,`sc`.`uom_code` AS `uom_code`,`sc`.`qty_bb` AS `qty_bb`,`sc`.`qty_in` AS `qty_in`,`sc`.`qty_out` AS `qty_out`,`sc`.`qty_adj` AS `qty_adj`,`sc`.`qty_eb` AS `qty_eb` from (((`rm_stockcard` `sc` left join `tmmst`.`m_raw_material` `m` on((`m`.`material_id` = `sc`.`material_id`))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `sc`.`warehouse_id`)));

-- ----------------------------
-- View structure for v1_m_print_mst_mat
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_print_mst_mat`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_print_mst_mat` AS select `mst`.`doc_id` AS `doc_id`,`mt`.`material_name` AS `material_name`,`l`.`location_name` AS `location_name`,`w`.`warehouse_name` AS `warehouse_name`,`mst`.`uom_code` AS `uom_code`,`mst`.`lot_no` AS `lot_no`,`mst`.`qty_bb` AS `qty_bb`,`mst`.`qty_in` AS `qty_in`,`mst`.`qty_out` AS `qty_out`,`mst`.`qty_adj` AS `qty_adj`,`mst`.`qty_eb` AS `qty_eb` from (((`rm_stockmst` `mst` left join `tmmst`.`m_raw_material` `mt` on((`mt`.`material_id` = `mst`.`material_id`))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `mst`.`location_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `mst`.`warehouse_id`)));

-- ----------------------------
-- View structure for v1_m_print_sr_plan
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_print_sr_plan`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_print_sr_plan` AS select `s`.`doc_id` AS `doc_id`,`s`.`doc_no` AS `doc_no`,`s`.`doc_cat` AS `doc_cat`,`s`.`doc_date` AS `doc_date`,`s`.`wo_no` AS `wo_no`,`s`.`notes` AS `notes`,`sd`.`doc_detail_id` AS `doc_detail_id`,`sd`.`parent_detail_id` AS `parent_detail_id`,`sd`.`qty` AS `qty`,`sd`.`uom_code` AS `uom_code`,`sd`.`lot_no` AS `lot_no`,`sd`.`qty_required` AS `qty_required`,`sd`.`qty_available` AS `qty_available`,`sd`.`qty_booked` AS `qty_booked`,`sd`.`action_type` AS `action_type`,(case `sd`.`action_type` when 'ISSUE' then 'Ambil' when 'SUBSTITUTE' then 'Ambil (pengganti)' when 'PURCHASE' then 'Perlu dibeli' else `sd`.`action_type` end) AS `action_label`,`w`.`warehouse_name` AS `warehouse_name`,`m`.`material_name` AS `material_name`,`l`.`location_name` AS `location_name`,`pm`.`material_name` AS `parent_material_name`,coalesce(`sd`.`parent_detail_id`,`sd`.`doc_detail_id`) AS `grp_key`,(`sd`.`parent_detail_id` is not null) AS `is_child` from ((((((`rm_stock` `s` left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `s`.`warehouse_id`))) left join `rm_stock_d` `sd` on((`sd`.`doc_id` = `s`.`doc_id`))) left join `tmmst`.`m_raw_material` `m` on((`m`.`material_id` = `sd`.`material_id`))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sd`.`location_id`))) left join `rm_stock_d` `pd` on((`pd`.`doc_detail_id` = `sd`.`parent_detail_id`))) left join `tmmst`.`m_raw_material` `pm` on((`pm`.`material_id` = `pd`.`material_id`))) order by `s`.`doc_id`,coalesce(`sd`.`parent_detail_id`,`sd`.`doc_detail_id`),(`sd`.`parent_detail_id` is not null),`sd`.`doc_detail_id`;

-- ----------------------------
-- View structure for v1_m_product
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_product`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_product` AS select `tmmst`.`m_product`.`product_id` AS `product_id`,`tmmst`.`m_product`.`product_code` AS `product_code`,`tmmst`.`m_product`.`product_name` AS `product_name`,`tmmst`.`m_product`.`customer_id` AS `customer_id`,`tmmst`.`m_product`.`description` AS `description`,`tmmst`.`m_product`.`uom_stock` AS `uom_stock`,`tmmst`.`m_product`.`uom_sales` AS `uom_sales`,`tmmst`.`m_product`.`qty_limit_min` AS `qty_limit_min`,`tmmst`.`m_product`.`qty_limit_max` AS `qty_limit_max`,`tmmst`.`m_product`.`cfg_parts_id` AS `cfg_parts_id`,`tmmst`.`m_product`.`cfg_color` AS `cfg_color`,`tmmst`.`m_product`.`cfg_ink` AS `cfg_ink`,`tmmst`.`m_product`.`product_cat_id` AS `product_cat_id`,`tmmst`.`m_product`.`conversion_to_stock` AS `conversion_to_stock`,`tmmst`.`m_product`.`min_stock` AS `min_stock`,`tmmst`.`m_product`.`max_stock` AS `max_stock`,`tmmst`.`m_product`.`cur_stock` AS `cur_stock`,`tmmst`.`m_product`.`is_active` AS `is_active`,`tmmst`.`m_product`.`cm_length` AS `cm_length`,`tmmst`.`m_product`.`cm_width` AS `cm_width`,`tmmst`.`m_product`.`cm_height` AS `cm_height`,`tmmst`.`m_product`.`stat_confirm` AS `stat_confirm`,`tmmst`.`m_product`.`default_selling_price` AS `default_selling_price`,`tmmst`.`m_product`.`minimum_selling_price` AS `minimum_selling_price`,`tmmst`.`m_product`.`create_log` AS `create_log`,`tmmst`.`m_product`.`update_log` AS `update_log`,`tmmst`.`m_product`.`create_by` AS `create_by`,`tmmst`.`m_product`.`update_by` AS `update_by` from `tmmst`.`m_product` where (`tmmst`.`m_product`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_raw_material
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_raw_material`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_raw_material` AS select `tmmst`.`m_raw_material`.`material_id` AS `material_id`,`tmmst`.`m_raw_material`.`material_code` AS `material_code`,`tmmst`.`m_raw_material`.`material_name` AS `material_name`,`tmmst`.`m_raw_material`.`item_type` AS `item_type`,`tmmst`.`m_raw_material`.`category1` AS `category1`,`tmmst`.`m_raw_material`.`category2` AS `category2`,`tmmst`.`m_raw_material`.`category3` AS `category3`,`tmmst`.`m_raw_material`.`category4` AS `category4`,`tmmst`.`m_raw_material`.`category5` AS `category5`,`tmmst`.`m_raw_material`.`description` AS `description`,`tmmst`.`m_raw_material`.`brand` AS `brand`,`tmmst`.`m_raw_material`.`color` AS `color`,`tmmst`.`m_raw_material`.`gram_weight` AS `gram_weight`,`tmmst`.`m_raw_material`.`cm_length` AS `cm_length`,`tmmst`.`m_raw_material`.`cm_width` AS `cm_width`,`tmmst`.`m_raw_material`.`mm_thickness` AS `mm_thickness`,`tmmst`.`m_raw_material`.`specification` AS `specification`,`tmmst`.`m_raw_material`.`uom_stock` AS `uom_stock`,`tmmst`.`m_raw_material`.`uom_purchase` AS `uom_purchase`,`tmmst`.`m_raw_material`.`purchasable` AS `purchasable`,`tmmst`.`m_raw_material`.`uom_usage` AS `uom_usage`,`tmmst`.`m_raw_material`.`conversion_to_stock` AS `conversion_to_stock`,`tmmst`.`m_raw_material`.`conversion_to_usage` AS `conversion_to_usage`,`tmmst`.`m_raw_material`.`free_note` AS `free_note`,`tmmst`.`m_raw_material`.`min_stock` AS `min_stock`,`tmmst`.`m_raw_material`.`max_stock` AS `max_stock`,`tmmst`.`m_raw_material`.`cur_stock` AS `cur_stock`,`tmmst`.`m_raw_material`.`is_active` AS `is_active`,`tmmst`.`m_raw_material`.`avg_unitprice` AS `avg_unitprice`,`tmmst`.`m_raw_material`.`last_unitprice` AS `last_unitprice`,`tmmst`.`m_raw_material`.`def_location_id` AS `def_location_id`,`tmmst`.`m_raw_material`.`create_log` AS `create_log`,`tmmst`.`m_raw_material`.`update_log` AS `update_log`,`tmmst`.`m_raw_material`.`create_by` AS `create_by`,`tmmst`.`m_raw_material`.`update_by` AS `update_by`,`tmmst`.`m_raw_material`.`old_item_code` AS `old_item_code`,`tmmst`.`m_raw_material`.`old_item_name` AS `old_item_name` from `tmmst`.`m_raw_material` where (`tmmst`.`m_raw_material`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_uom
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_uom`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_uom` AS select `tmmst`.`m_uom`.`uom_id` AS `uom_id`,`tmmst`.`m_uom`.`uom_code` AS `uom_code`,`tmmst`.`m_uom`.`uom_name` AS `uom_name`,`tmmst`.`m_uom`.`create_by` AS `create_by`,`tmmst`.`m_uom`.`update_by` AS `update_by`,`tmmst`.`m_uom`.`create_log` AS `create_log`,`tmmst`.`m_uom`.`update_log` AS `update_log` from `tmmst`.`m_uom`;

-- ----------------------------
-- View structure for v1_m_uom_conversion
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_uom_conversion`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_uom_conversion` AS select `uc`.`conversion_id` AS `conversion_id`,`uc`.`uom_src` AS `uom_src`,`uc`.`uom_dst` AS `uom_dst`,`uc`.`conversion_value` AS `conversion_value`,`uc`.`create_log` AS `create_log`,`uc`.`update_log` AS `update_log`,`uc`.`create_by` AS `create_by`,`uc`.`update_by` AS `update_by` from `tmmst`.`m_uom_conversion` `uc`;

-- ----------------------------
-- View structure for v1_m_vendor
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_vendor`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_vendor` AS select `tmmst`.`m_vendor`.`vendor_id` AS `vendor_id`,`tmmst`.`m_vendor`.`vendor_code` AS `vendor_code`,`tmmst`.`m_vendor`.`vendor_name` AS `vendor_name`,`tmmst`.`m_vendor`.`mr_ms` AS `mr_ms`,`tmmst`.`m_vendor`.`contact_name` AS `contact_name`,`tmmst`.`m_vendor`.`default_address` AS `default_address`,`tmmst`.`m_vendor`.`tax_code` AS `tax_code`,`tmmst`.`m_vendor`.`default_id_area` AS `default_id_area`,`tmmst`.`m_vendor`.`email` AS `email`,`tmmst`.`m_vendor`.`phone` AS `phone`,`tmmst`.`m_vendor`.`website` AS `website`,`tmmst`.`m_vendor`.`vendor_type` AS `vendor_type`,`tmmst`.`m_vendor`.`payment_term` AS `payment_term`,`tmmst`.`m_vendor`.`currency_code` AS `currency_code`,`tmmst`.`m_vendor`.`is_active` AS `is_active`,`tmmst`.`m_vendor`.`rating` AS `rating`,`tmmst`.`m_vendor`.`create_log` AS `create_log`,`tmmst`.`m_vendor`.`update_log` AS `update_log`,`tmmst`.`m_vendor`.`create_by` AS `create_by`,`tmmst`.`m_vendor`.`update_by` AS `update_by` from `tmmst`.`m_vendor` where (`tmmst`.`m_vendor`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_warehouse
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_warehouse`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_warehouse` AS select `tmmst`.`m_warehouse`.`warehouse_id` AS `warehouse_id`,`tmmst`.`m_warehouse`.`warehouse_code` AS `warehouse_code`,`tmmst`.`m_warehouse`.`warehouse_name` AS `warehouse_name`,`tmmst`.`m_warehouse`.`warehouse_type` AS `warehouse_type`,`tmmst`.`m_warehouse`.`is_active` AS `is_active`,`tmmst`.`m_warehouse`.`description` AS `description`,`tmmst`.`m_warehouse`.`create_log` AS `create_log`,`tmmst`.`m_warehouse`.`update_log` AS `update_log`,`tmmst`.`m_warehouse`.`create_by` AS `create_by`,`tmmst`.`m_warehouse`.`update_by` AS `update_by` from `tmmst`.`m_warehouse`;

-- ----------------------------
-- View structure for v1_m_wh_loc
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_wh_loc`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_wh_loc` AS select `l`.`location_id` AS `location_id`,`l`.`warehouse_id` AS `warehouse_id`,`l`.`location_code` AS `location_code`,`l`.`location_name` AS `location_name`,`l`.`row_name` AS `row_name`,`l`.`column_name` AS `column_name`,`l`.`stack_name` AS `stack_name`,`l`.`location_type` AS `location_type`,`l`.`max_capacity_qty` AS `max_capacity_qty`,`l`.`description` AS `description`,`l`.`is_active` AS `is_active`,`l`.`create_log` AS `create_log`,`l`.`update_log` AS `update_log`,`l`.`create_by` AS `create_by`,`l`.`update_by` AS `update_by`,concat(`w`.`warehouse_name`,' - ',`l`.`location_name`) AS `strwh` from (`tmmst`.`m_wh_loc` `l` join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `l`.`warehouse_id`)));

-- ----------------------------
-- View structure for v1_s_co
-- ----------------------------
DROP VIEW IF EXISTS `v1_s_co`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_s_co` AS select `tmsdi`.`s_co`.`doc_id` AS `doc_id`,`tmsdi`.`s_co`.`co_date` AS `co_date`,`tmsdi`.`s_co`.`co_no` AS `co_no`,`tmsdi`.`s_co`.`customer_id` AS `customer_id`,`tmsdi`.`s_co`.`recipient_id` AS `recipient_id`,`tmsdi`.`s_co`.`forwarder_id` AS `forwarder_id`,`tmsdi`.`s_co`.`shipper_id` AS `shipper_id`,`tmsdi`.`s_co`.`sales_by` AS `sales_by`,`tmsdi`.`s_co`.`currency_code` AS `currency_code`,`tmsdi`.`s_co`.`shipment_id` AS `shipment_id`,`tmsdi`.`s_co`.`payment_id` AS `payment_id`,`tmsdi`.`s_co`.`payment_bank_account_id` AS `payment_bank_account_id`,`tmsdi`.`s_co`.`stat_confirm` AS `stat_confirm`,`tmsdi`.`s_co`.`stat_cancel` AS `stat_cancel`,`tmsdi`.`s_co`.`stat_closed` AS `stat_closed`,`tmsdi`.`s_co`.`quotation_id` AS `quotation_id`,`tmsdi`.`s_co`.`forecast_id` AS `forecast_id`,`tmsdi`.`s_co`.`order_category` AS `order_category`,`tmsdi`.`s_co`.`po_no_customer` AS `po_no_customer`,`tmsdi`.`s_co`.`po_date_customer` AS `po_date_customer`,`tmsdi`.`s_co`.`po_customer_rcvdate` AS `po_customer_rcvdate`,`tmsdi`.`s_co`.`file_attachment_jpg` AS `file_attachment_jpg`,`tmsdi`.`s_co`.`file_attachment_pdf` AS `file_attachment_pdf`,`tmsdi`.`s_co`.`stat_auth` AS `stat_auth`,`tmsdi`.`s_co`.`auth_module` AS `auth_module`,`tmsdi`.`s_co`.`stat_invoiced` AS `stat_invoiced`,`tmsdi`.`s_co`.`current_approval_level` AS `current_approval_level`,`tmsdi`.`s_co`.`current_approval_login` AS `current_approval_login`,`tmsdi`.`s_co`.`deliver_to` AS `deliver_to`,`tmsdi`.`s_co`.`delivery_addr_area_id` AS `delivery_addr_area_id`,`tmsdi`.`s_co`.`doc_status` AS `doc_status`,`tmsdi`.`s_co`.`tax_enable` AS `tax_enable`,`tmsdi`.`s_co`.`subtotal` AS `subtotal`,`tmsdi`.`s_co`.`disc_percent` AS `disc_percent`,`tmsdi`.`s_co`.`disc_amount` AS `disc_amount`,`tmsdi`.`s_co`.`freight_amount` AS `freight_amount`,`tmsdi`.`s_co`.`insurance_amount` AS `insurance_amount`,`tmsdi`.`s_co`.`trucking_amount` AS `trucking_amount`,`tmsdi`.`s_co`.`others_amount` AS `others_amount`,`tmsdi`.`s_co`.`ppn_percent` AS `ppn_percent`,`tmsdi`.`s_co`.`ppn_amount` AS `ppn_amount`,`tmsdi`.`s_co`.`pph22_percent` AS `pph22_percent`,`tmsdi`.`s_co`.`pph22_amount` AS `pph22_amount`,`tmsdi`.`s_co`.`pph23_percent` AS `pph23_percent`,`tmsdi`.`s_co`.`pph23_amount` AS `pph23_amount`,`tmsdi`.`s_co`.`total_discount_amount` AS `total_discount_amount`,`tmsdi`.`s_co`.`total_expense_amount` AS `total_expense_amount`,`tmsdi`.`s_co`.`total_beforetax_amount` AS `total_beforetax_amount`,`tmsdi`.`s_co`.`total_taxes_amount` AS `total_taxes_amount`,`tmsdi`.`s_co`.`grand_total` AS `grand_total`,`tmsdi`.`s_co`.`counted` AS `counted`,`tmsdi`.`s_co`.`order_notes` AS `order_notes`,`tmsdi`.`s_co`.`invoice_post_status` AS `invoice_post_status`,`tmsdi`.`s_co`.`invoice_post_datetime` AS `invoice_post_datetime`,`tmsdi`.`s_co`.`invoice_posted` AS `invoice_posted`,`tmsdi`.`s_co`.`invoice_no` AS `invoice_no`,`tmsdi`.`s_co`.`create_by` AS `create_by`,`tmsdi`.`s_co`.`update_by` AS `update_by`,`tmsdi`.`s_co`.`create_log` AS `create_log`,`tmsdi`.`s_co`.`update_log` AS `update_log` from `tmsdi`.`s_co` where ((`tmsdi`.`s_co`.`stat_confirm` = 1) and (`tmsdi`.`s_co`.`stat_cancel` = 0));

-- ----------------------------
-- View structure for v1_s_co_all
-- ----------------------------
DROP VIEW IF EXISTS `v1_s_co_all`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_s_co_all` AS select `sd`.`doc_detail_id` AS `doc_detail_id`,`sd`.`doc_id` AS `doc_id`,`sd`.`product_id` AS `product_id`,`sd`.`qty_order` AS `qty_order`,`sd`.`qty_remain` AS `qty_remain`,`sd`.`qty_minim` AS `qty_minim`,`sd`.`qty_limit_min` AS `qty_limit_min`,`sd`.`qty_limit_max` AS `qty_limit_max`,`sd`.`uom_sales` AS `uom_sales`,`sd`.`unit_price` AS `unit_price`,`sd`.`qty_unitprice` AS `qty_unitprice`,`sd`.`duedate` AS `duedate`,`sd`.`order_type` AS `order_type`,`sd`.`note_detail` AS `note_detail`,`sd`.`quotation_detail_id` AS `quotation_detail_id`,`sd`.`forecast_detail_id` AS `forecast_detail_id`,`sd`.`stat_choose` AS `stat_choose`,`sd`.`id_concat` AS `id_concat`,`sd`.`ship_planned` AS `ship_planned`,`sd`.`create_by` AS `create_by`,`sd`.`update_by` AS `update_by`,`sd`.`create_log` AS `create_log`,`sd`.`update_log` AS `update_log`,`s`.`co_no` AS `co_no`,`s`.`co_date` AS `co_date`,`s`.`customer_id` AS `customer_id`,`c`.`customer_name` AS `customer_name` from ((`tmsdi`.`s_co_detail` `sd` left join `tmsdi`.`s_co` `s` on((`s`.`doc_id` = `sd`.`doc_id`))) left join `tmmst`.`m_customer` `c` on((`c`.`customer_id` = `s`.`customer_id`)));

-- ----------------------------
-- View structure for v_fg_onhand
-- ----------------------------
DROP VIEW IF EXISTS `v_fg_onhand`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_fg_onhand` AS select `sc`.`product_id` AS `product_id`,`sc`.`parts_id` AS `parts_id`,`sc`.`location_id` AS `location_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`l`.`location_name` AS `location_name`,`l`.`location_code` AS `location_code`,`sc`.`uom_code` AS `uom_code`,`sc`.`qty_eb` AS `qty_onhand`,`sc`.`doc_date` AS `last_movement` from (((`fg_stockcard` `sc` join (select coalesce(`fg_stockcard`.`product_id`,0) AS `p`,coalesce(`fg_stockcard`.`parts_id`,0) AS `pt`,`fg_stockcard`.`location_id` AS `location_id`,max(`fg_stockcard`.`doc_id`) AS `mx` from `fg_stockcard` group by coalesce(`fg_stockcard`.`product_id`,0),coalesce(`fg_stockcard`.`parts_id`,0),`fg_stockcard`.`location_id`) `x` on(((coalesce(`sc`.`product_id`,0) = `x`.`p`) and (coalesce(`sc`.`parts_id`,0) = `x`.`pt`) and (`sc`.`location_id` = `x`.`location_id`) and (`sc`.`doc_id` = `x`.`mx`)))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `sc`.`warehouse_id`)));

-- ----------------------------
-- View structure for v_fg_sku_stock
-- ----------------------------
DROP VIEW IF EXISTS `v_fg_sku_stock`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_fg_sku_stock` AS select `p`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_name` AS `product_name`,`p`.`uom_stock` AS `uom_stock`,count(distinct `pp`.`parts_id`) AS `total_parts`,min(floor((coalesce(`loc_agg`.`qty_onhand`,0) / `pp`.`qty_per_sku`))) AS `qty_sku_available`,group_concat(concat(`pp`.`parts_name`,': ',coalesce(`loc_agg`.`qty_onhand`,0)) order by `pp`.`parts_id` ASC separator ' | ') AS `parts_breakdown` from ((`tmmst`.`m_product` `p` join `tmmst`.`m_product_parts` `pp` on(((`pp`.`product_id` = `p`.`product_id`) and (`pp`.`is_active` = 1)))) left join (select `v_fg_onhand`.`parts_id` AS `parts_id`,sum(`v_fg_onhand`.`qty_onhand`) AS `qty_onhand` from `v_fg_onhand` where (`v_fg_onhand`.`parts_id` is not null) group by `v_fg_onhand`.`parts_id`) `loc_agg` on((`loc_agg`.`parts_id` = `pp`.`parts_id`))) where (`p`.`is_active` = 1) group by `p`.`product_id`,`p`.`product_code`,`p`.`product_name`,`p`.`uom_stock`;

-- ----------------------------
-- View structure for v_fg_stockcard_detail
-- ----------------------------
DROP VIEW IF EXISTS `v_fg_stockcard_detail`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_fg_stockcard_detail` AS select `sc`.`doc_id` AS `doc_id`,`sc`.`doc_date` AS `doc_date`,`sc`.`doc_type` AS `doc_type`,`sc`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_name` AS `product_name`,`sc`.`parts_id` AS `parts_id`,`pp`.`parts_code` AS `parts_code`,`pp`.`parts_name` AS `parts_name`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`sc`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`sc`.`lot_no` AS `lot_no`,`sc`.`uom_code` AS `uom_code`,`fs`.`doc_no` AS `doc_no`,`fs`.`doc_date` AS `source_date`,`sc`.`qty_bb` AS `qty_bb`,`sc`.`qty_in` AS `qty_in`,`sc`.`qty_out` AS `qty_out`,`sc`.`qty_adj` AS `qty_adj`,`sc`.`qty_eb` AS `qty_eb` from ((((((`fg_stockcard` `sc` left join `tmmst`.`m_product` `p` on((`p`.`product_id` = `sc`.`product_id`))) left join `tmmst`.`m_product_parts` `pp` on((`pp`.`parts_id` = `sc`.`parts_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `sc`.`warehouse_id`))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `fg_stock_d` `fd` on((`fd`.`doc_detail_id` = `sc`.`reference_detail_id`))) left join `fg_stock` `fs` on((`fs`.`doc_id` = `fd`.`doc_id`)));

-- ----------------------------
-- View structure for v_rm_available
-- ----------------------------
DROP VIEW IF EXISTS `v_rm_available`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_rm_available` AS select `o`.`material_id` AS `material_id`,`o`.`location_id` AS `location_id`,`o`.`warehouse_id` AS `warehouse_id`,`o`.`uom_code` AS `uom_code`,`o`.`qty_onhand` AS `qty_onhand`,ifnull(`b`.`qty_booked_open`,0) AS `qty_booked`,(`o`.`qty_onhand` - ifnull(`b`.`qty_booked_open`,0)) AS `qty_available` from (`v_rm_onhand` `o` left join (select `d`.`material_id` AS `material_id`,`d`.`location_id` AS `location_id`,sum((`d`.`qty_book` - `d`.`qty_issued`)) AS `qty_booked_open` from (`rm_booking_d` `d` join `rm_booking` `h` on((`h`.`booking_id` = `d`.`booking_id`))) where ((`h`.`stat_confirm` = 1) and (`h`.`stat_cancel` = 0) and (`h`.`stat_closed` = 0) and (`d`.`stat_cancel` = 0) and (`d`.`qty_book` > `d`.`qty_issued`)) group by `d`.`material_id`,`d`.`location_id`) `b` on(((`b`.`material_id` = `o`.`material_id`) and (`b`.`location_id` <=> `o`.`location_id`))));

-- ----------------------------
-- View structure for v_rm_booking_d
-- ----------------------------
DROP VIEW IF EXISTS `v_rm_booking_d`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_rm_booking_d` AS select `d`.`booking_detail_id` AS `booking_detail_id`,`d`.`booking_id` AS `booking_id`,`d`.`seq_no` AS `seq_no`,`d`.`co_id` AS `co_id`,`d`.`co_detail_id` AS `co_detail_id`,`d`.`product_id` AS `product_id`,`d`.`material_id` AS `material_id`,`d`.`uom_code` AS `uom_code`,`d`.`location_id` AS `location_id`,`d`.`qty_need` AS `qty_need`,`d`.`qty_book` AS `qty_book`,`d`.`qty_purchase` AS `qty_purchase`,`d`.`qty_replacement` AS `qty_replacement`,`d`.`qty_issued` AS `qty_issued`,`d`.`material_replace_id` AS `material_replace_id`,`d`.`uom_replace` AS `uom_replace`,`d`.`replace_ratio` AS `replace_ratio`,`d`.`replace_reason` AS `replace_reason`,`d`.`substitution_id` AS `substitution_id`,`d`.`pr_detail_id` AS `pr_detail_id`,`d`.`note_detail` AS `note_detail`,`d`.`stat_cancel` AS `stat_cancel`,`d`.`create_by` AS `create_by`,`d`.`update_by` AS `update_by`,`d`.`create_log` AS `create_log`,`d`.`update_log` AS `update_log`,`h`.`booking_no` AS `booking_no`,`h`.`booking_date` AS `booking_date`,`h`.`warehouse_id` AS `warehouse_id`,`h`.`stat_confirm` AS `hdr_confirm`,`h`.`stat_cancel` AS `hdr_cancel`,`co`.`co_no` AS `co_no`,`co`.`co_date` AS `co_date`,`co`.`customer_id` AS `customer_id`,`cs`.`customer_name` AS `customer_name`,`p`.`product_code` AS `product_code`,`p`.`product_name` AS `product_name`,`m`.`material_code` AS `material_code`,`m`.`material_name` AS `material_name`,`r`.`material_code` AS `replace_code`,`r`.`material_name` AS `replace_name`,`l`.`location_code` AS `location_code`,`l`.`location_name` AS `location_name`,`av`.`qty_available` AS `qty_available`,(`d`.`qty_book` - `d`.`qty_issued`) AS `qty_outstanding` from ((((((((`rm_booking_d` `d` join `rm_booking` `h` on((`h`.`booking_id` = `d`.`booking_id`))) left join `tmsdi`.`s_co` `co` on((`co`.`doc_id` = `d`.`co_id`))) left join `tmmst`.`m_customer` `cs` on((`cs`.`customer_id` = `co`.`customer_id`))) left join `tmmst`.`m_product` `p` on((`p`.`product_id` = `d`.`product_id`))) left join `tmmst`.`m_raw_material` `m` on((`m`.`material_id` = `d`.`material_id`))) left join `tmmst`.`m_raw_material` `r` on((`r`.`material_id` = `d`.`material_replace_id`))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `d`.`location_id`))) left join `v_rm_available` `av` on(((`av`.`material_id` = `d`.`material_id`) and (`av`.`location_id` <=> `d`.`location_id`))));

-- ----------------------------
-- View structure for v_rm_onhand
-- ----------------------------
DROP VIEW IF EXISTS `v_rm_onhand`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_rm_onhand` AS select `sc`.`material_id` AS `material_id`,`sc`.`location_id` AS `location_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`l`.`location_name` AS `location_name`,`l`.`location_code` AS `location_code`,`sc`.`uom_code` AS `uom_code`,`sc`.`qty_eb` AS `qty_onhand`,`sc`.`doc_date` AS `last_movement` from (((`rm_stockcard` `sc` join (select `rm_stockcard`.`material_id` AS `material_id`,`rm_stockcard`.`location_id` AS `location_id`,max(`rm_stockcard`.`doc_id`) AS `mx` from `rm_stockcard` group by `rm_stockcard`.`material_id`,`rm_stockcard`.`location_id`) `x` on(((`x`.`material_id` = `sc`.`material_id`) and (`x`.`location_id` = `sc`.`location_id`) and (`x`.`mx` = `sc`.`doc_id`)))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `sc`.`warehouse_id`)));

-- ----------------------------
-- View structure for v_rm_stockcard_detail
-- ----------------------------
DROP VIEW IF EXISTS `v_rm_stockcard_detail`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_rm_stockcard_detail` AS select `sc`.`doc_id` AS `doc_id`,`sc`.`doc_date` AS `doc_date`,`sc`.`doc_type` AS `doc_type`,`sc`.`material_id` AS `material_id`,`m`.`material_code` AS `material_code`,`m`.`material_name` AS `material_name`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`sc`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`sc`.`lot_no` AS `lot_no`,`sc`.`uom_code` AS `uom_code`,coalesce(`pr`.`doc_no`,`rs`.`doc_no`) AS `doc_no`,coalesce(`pr`.`doc_date`,`rs`.`doc_date`) AS `source_date`,`sc`.`qty_bb` AS `qty_bb`,`sc`.`qty_in` AS `qty_in`,`sc`.`qty_out` AS `qty_out`,`sc`.`qty_adj` AS `qty_adj`,`sc`.`qty_eb` AS `qty_eb` from (((((((`rm_stockcard` `sc` left join `tmmst`.`m_raw_material` `m` on((`m`.`material_id` = `sc`.`material_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `sc`.`warehouse_id`))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `rm_stock_d` `rsd` on((`rsd`.`doc_detail_id` = `sc`.`reference_detail_id`))) left join `rm_stock` `rs` on((`rs`.`doc_id` = `rsd`.`doc_id`))) left join `tmpur`.`p_receive_d` `prd` on(((`prd`.`doc_detail_id` = `sc`.`reference_detail_id`) and (`rs`.`doc_id` is null)))) left join `tmpur`.`p_receive` `pr` on((`pr`.`doc_id` = `prd`.`doc_id`)));

-- ----------------------------
-- View structure for v_sfg_onhand
-- ----------------------------
DROP VIEW IF EXISTS `v_sfg_onhand`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_sfg_onhand` AS select `sc`.`parts_id` AS `parts_id`,`sc`.`product_id` AS `product_id`,`sc`.`location_id` AS `location_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`l`.`location_name` AS `location_name`,`l`.`location_code` AS `location_code`,`sc`.`uom_code` AS `uom_code`,`sc`.`qty_eb` AS `qty_onhand`,`sc`.`doc_date` AS `last_movement` from (((`sfg_stockcard` `sc` join (select `sfg_stockcard`.`parts_id` AS `parts_id`,`sfg_stockcard`.`location_id` AS `location_id`,max(`sfg_stockcard`.`doc_id`) AS `mx` from `sfg_stockcard` group by `sfg_stockcard`.`parts_id`,`sfg_stockcard`.`location_id`) `x` on(((`x`.`parts_id` = `sc`.`parts_id`) and (`x`.`location_id` = `sc`.`location_id`) and (`x`.`mx` = `sc`.`doc_id`)))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `sc`.`warehouse_id`)));

-- ----------------------------
-- View structure for v_sfg_stockcard_detail
-- ----------------------------
DROP VIEW IF EXISTS `v_sfg_stockcard_detail`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_sfg_stockcard_detail` AS select `sc`.`doc_id` AS `doc_id`,`sc`.`doc_date` AS `doc_date`,`sc`.`doc_type` AS `doc_type`,`sc`.`parts_id` AS `parts_id`,`pp`.`parts_code` AS `parts_code`,`pp`.`parts_name` AS `parts_name`,`sc`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_name` AS `product_name`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`sc`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`sc`.`lot_no` AS `lot_no`,`sc`.`uom_code` AS `uom_code`,`ss`.`doc_no` AS `doc_no`,`ss`.`doc_date` AS `source_date`,`sc`.`qty_bb` AS `qty_bb`,`sc`.`qty_in` AS `qty_in`,`sc`.`qty_out` AS `qty_out`,`sc`.`qty_adj` AS `qty_adj`,`sc`.`qty_eb` AS `qty_eb` from ((((((`sfg_stockcard` `sc` left join `tmmst`.`m_product_parts` `pp` on((`pp`.`parts_id` = `sc`.`parts_id`))) left join `tmmst`.`m_product` `p` on((`p`.`product_id` = `sc`.`product_id`))) left join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `sc`.`warehouse_id`))) left join `tmmst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `sfg_stock_d` `sd` on((`sd`.`doc_detail_id` = `sc`.`reference_detail_id`))) left join `sfg_stock` `ss` on((`ss`.`doc_id` = `sd`.`doc_id`)));

-- ----------------------------
-- Procedure structure for sp_fg_master_stock
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_fg_master_stock`;
delimiter ;;
CREATE PROCEDURE `sp_fg_master_stock`(IN p_date_start DATE, IN p_date_end DATE,
  IN p_warehouse_id BIGINT)
  COMMENT 'Master stok FG per product/parts+lokasi, per periode'
BEGIN
  WITH saldo_awal_cte AS (
    SELECT sc1.product_id, sc1.parts_id, sc1.location_id, sc1.qty_eb AS saldo_awal
    FROM tminv.fg_stockcard sc1
    WHERE sc1.doc_date = (SELECT MAX(sc2.doc_date) FROM tminv.fg_stockcard sc2
        WHERE COALESCE(sc2.product_id,0)=COALESCE(sc1.product_id,0)
          AND COALESCE(sc2.parts_id,0)=COALESCE(sc1.parts_id,0)
          AND COALESCE(sc2.location_id,0)=COALESCE(sc1.location_id,0)
          AND sc2.doc_date < p_date_start)
      AND sc1.doc_id = (SELECT MAX(sc3.doc_id) FROM tminv.fg_stockcard sc3
        WHERE COALESCE(sc3.product_id,0)=COALESCE(sc1.product_id,0)
          AND COALESCE(sc3.parts_id,0)=COALESCE(sc1.parts_id,0)
          AND COALESCE(sc3.location_id,0)=COALESCE(sc1.location_id,0)
          AND sc3.doc_date=sc1.doc_date)
  )
  SELECT
    ROW_NUMBER() OVER (ORDER BY p.product_code, pp.parts_code, l.location_code) AS no_urut,
    sc.product_id, p.product_code, p.product_name,
    sc.parts_id, pp.parts_code, pp.parts_name,
    sc.warehouse_id, w.warehouse_name, sc.location_id, l.location_name,
    sc.uom_code AS satuan,
    IFNULL(sa.saldo_awal,0) AS saldo_awal,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_in  END),0) AS total_masuk,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_out END),0) AS total_keluar,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_adj END),0) AS total_penyesuaian,
    IFNULL(sa.saldo_awal,0) +
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end
                    THEN sc.qty_in - sc.qty_out + sc.qty_adj END),0) AS saldo_akhir
  FROM tminv.fg_stockcard sc
  LEFT JOIN tmmst.m_product       p  ON p.product_id  = sc.product_id
  LEFT JOIN tmmst.m_product_parts pp ON pp.parts_id   = sc.parts_id
  LEFT JOIN tmmst.m_warehouse     w  ON w.warehouse_id = sc.warehouse_id
  LEFT JOIN tmmst.m_wh_loc        l  ON l.location_id  = sc.location_id
  LEFT JOIN saldo_awal_cte sa
    ON COALESCE(sa.product_id,0)=COALESCE(sc.product_id,0)
   AND COALESCE(sa.parts_id,0)=COALESCE(sc.parts_id,0)
   AND COALESCE(sa.location_id,0)=COALESCE(sc.location_id,0)
  WHERE sc.doc_date <= p_date_end
    AND (p_warehouse_id IS NULL OR sc.warehouse_id = p_warehouse_id)
  GROUP BY sc.product_id, sc.parts_id, sc.warehouse_id, sc.location_id, sc.uom_code,
           p.product_code, p.product_name, pp.parts_code, pp.parts_name,
           w.warehouse_name, l.location_name, sa.saldo_awal
  ORDER BY p.product_code, pp.parts_code, l.location_code;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_fg_rebuild_stockcard
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_fg_rebuild_stockcard`;
delimiter ;;
CREATE PROCEDURE `sp_fg_rebuild_stockcard`(IN p_product_id   BIGINT,
  IN p_parts_id     BIGINT,
  IN p_warehouse_id BIGINT)
  COMMENT 'Rebuild saldo fg_stockcard — reset per product/parts/location'
BEGIN
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_doc_id BIGINT; DECLARE v_product BIGINT; DECLARE v_parts BIGINT; DECLARE v_location BIGINT;
  DECLARE v_qty_in DECIMAL(20,4); DECLARE v_qty_out DECIMAL(20,4); DECLARE v_qty_adj DECIMAL(20,4);
  DECLARE v_prev_product BIGINT DEFAULT NULL;
  DECLARE v_prev_parts BIGINT DEFAULT NULL;
  DECLARE v_prev_location BIGINT DEFAULT NULL;
  DECLARE v_prev_doc BIGINT DEFAULT NULL;
  DECLARE v_running_balance DECIMAL(20,4) DEFAULT 0;
  DECLARE cur1 CURSOR FOR
    SELECT doc_id, COALESCE(product_id,0), COALESCE(parts_id,0),
           location_id, qty_in, qty_out, qty_adj
    FROM tminv.fg_stockcard
    WHERE (p_product_id   IS NULL OR product_id   = p_product_id)
      AND (p_parts_id     IS NULL OR parts_id     = p_parts_id)
      AND (p_warehouse_id IS NULL OR warehouse_id = p_warehouse_id)
    ORDER BY COALESCE(product_id,0), COALESCE(parts_id,0),
             location_id, doc_date, doc_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  OPEN cur1;
  rebuild_loop: LOOP
    FETCH cur1 INTO v_doc_id, v_product, v_parts, v_location, v_qty_in, v_qty_out, v_qty_adj;
    IF v_done THEN LEAVE rebuild_loop; END IF;
    IF v_product <> COALESCE(v_prev_product,-1)
    OR v_parts   <> COALESCE(v_prev_parts,-1)
    OR COALESCE(v_location,-1) <> COALESCE(v_prev_location,-1) THEN
      SET v_running_balance = 0; SET v_prev_doc = NULL;
    END IF;
    UPDATE tminv.fg_stockcard
       SET previous_doc_id = v_prev_doc,
           qty_bb = v_running_balance,
           qty_eb = v_running_balance + v_qty_in - v_qty_out + v_qty_adj
     WHERE doc_id = v_doc_id;
    SET v_running_balance = v_running_balance + v_qty_in - v_qty_out + v_qty_adj;
    SET v_prev_doc = v_doc_id; SET v_prev_product = v_product;
    SET v_prev_parts = v_parts; SET v_prev_location = v_location;
  END LOOP;
  CLOSE cur1;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_fg_stock_card
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_fg_stock_card`;
delimiter ;;
CREATE PROCEDURE `sp_fg_stock_card`(IN p_product_id BIGINT,
  IN p_parts_id   BIGINT,
  IN p_date_start DATE,
  IN p_date_end   DATE)
  COMMENT 'Kartu stok FG per produk/komponen dan periode'
BEGIN
  SELECT
    sc.doc_id, sc.doc_date, sc.doc_type,
    sc.product_id, p.product_code, p.product_name,
    sc.parts_id, pp.parts_name,
    sc.warehouse_id, w.warehouse_name,
    sc.location_id,  l.location_name,
    sc.lot_no, sc.uom_code, fs.doc_no,
    sc.qty_bb AS saldo_awal,
    sc.qty_in AS masuk, sc.qty_out AS keluar,
    sc.qty_adj AS penyesuaian, sc.qty_eb AS saldo_akhir
  FROM tminv.fg_stockcard sc
  LEFT JOIN tmmst.m_product       p  ON p.product_id   = sc.product_id
  LEFT JOIN tmmst.m_product_parts pp ON pp.parts_id    = sc.parts_id
  LEFT JOIN tmmst.m_warehouse     w  ON w.warehouse_id = sc.warehouse_id
  LEFT JOIN tmmst.m_wh_loc        l  ON l.location_id  = sc.location_id
  LEFT JOIN tminv.fg_stock_d      fd ON fd.doc_detail_id = sc.reference_detail_id
  LEFT JOIN tminv.fg_stock        fs ON fs.doc_id        = fd.doc_id
  WHERE (p_product_id IS NULL OR sc.product_id = p_product_id)
    AND (p_parts_id   IS NULL OR sc.parts_id   = p_parts_id)
    AND DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end
  ORDER BY p.product_code, pp.parts_name, sc.doc_date, sc.doc_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_rm_available_stock
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_rm_available_stock`;
delimiter ;;
CREATE PROCEDURE `sp_rm_available_stock`(IN p_doc_id BIGINT)
  COMMENT 'Cek stok RM tersedia per lokasi pada tanggal dokumen rm_stock'
BEGIN
  SELECT
    d.material_id, m.material_name,
    s.warehouse_id, w.warehouse_name,
    d.location_id, l.location_name,
    d.qty AS qty_diminta, d.uom_code,
    IFNULL((
      SELECT sc.qty_eb FROM tminv.rm_stockcard sc
       WHERE sc.material_id = d.material_id
         AND sc.location_id = d.location_id
         AND sc.doc_date   <= s.doc_date
       ORDER BY sc.doc_date DESC, sc.doc_id DESC LIMIT 1
    ), 0) AS stok_tersedia
  FROM tminv.rm_stock_d    d
  JOIN tminv.rm_stock       s ON s.doc_id       = d.doc_id
  JOIN tmmst.m_raw_material m ON m.material_id  = d.material_id
  JOIN tmmst.m_warehouse    w ON w.warehouse_id = s.warehouse_id
  LEFT JOIN tmmst.m_wh_loc  l ON l.location_id  = d.location_id
  WHERE d.doc_id = p_doc_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_rm_daily_out
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_rm_daily_out`;
delimiter ;;
CREATE PROCEDURE `sp_rm_daily_out`(IN p_date DATE)
  COMMENT 'Pengeluaran RM per hari dikelompok per nomor SPK'
BEGIN
  SELECT
    s.doc_no AS no_spk, s.doc_date,
    d.material_id, m.material_code, m.material_name,
    d.qty, d.uom_code,
    w.warehouse_name, l.location_name AS lokasi_pengambilan
  FROM tminv.rm_stock   s
  JOIN tminv.rm_stock_d d  ON d.doc_id       = s.doc_id
  JOIN tmmst.m_raw_material m ON m.material_id = d.material_id
  JOIN tmmst.m_warehouse    w ON w.warehouse_id = s.warehouse_id
  LEFT JOIN tmmst.m_wh_loc  l ON l.location_id  = d.location_id
  WHERE s.doc_type=-1 AND DATE(s.doc_date)=p_date
    AND s.stat_confirm=1 AND s.stat_cancel=0
  ORDER BY s.doc_no, m.material_code;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_rm_master_stock
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_rm_master_stock`;
delimiter ;;
CREATE PROCEDURE `sp_rm_master_stock`(IN p_date_start DATE, IN p_date_end DATE,
  IN p_warehouse_id BIGINT)
  COMMENT 'Master stok RM per item+lokasi, per periode'
BEGIN
  WITH saldo_awal_cte AS (
    SELECT sc1.material_id, sc1.location_id, sc1.qty_eb AS saldo_awal
    FROM tminv.rm_stockcard sc1
    WHERE sc1.doc_date = (SELECT MAX(sc2.doc_date) FROM tminv.rm_stockcard sc2
        WHERE sc2.material_id=sc1.material_id AND COALESCE(sc2.location_id,0)=COALESCE(sc1.location_id,0)
          AND sc2.doc_date < p_date_start)
      AND sc1.doc_id = (SELECT MAX(sc3.doc_id) FROM tminv.rm_stockcard sc3
        WHERE sc3.material_id=sc1.material_id AND COALESCE(sc3.location_id,0)=COALESCE(sc1.location_id,0)
          AND sc3.doc_date=sc1.doc_date)
  )
  SELECT
    ROW_NUMBER() OVER (ORDER BY m.material_code, l.location_code) AS no_urut,
    sc.material_id, m.material_code, m.material_name,
    sc.warehouse_id, w.warehouse_name, sc.location_id, l.location_name,
    sc.uom_code AS satuan,
    IFNULL(sa.saldo_awal,0) AS saldo_awal,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_in  END),0) AS total_masuk,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_out END),0) AS total_keluar,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_adj END),0) AS total_penyesuaian,
    IFNULL(sa.saldo_awal,0) +
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end
                    THEN sc.qty_in - sc.qty_out + sc.qty_adj END),0) AS saldo_akhir
  FROM tminv.rm_stockcard sc
  JOIN tmmst.m_raw_material m ON m.material_id = sc.material_id
  LEFT JOIN tmmst.m_warehouse w ON w.warehouse_id = sc.warehouse_id
  LEFT JOIN tmmst.m_wh_loc    l ON l.location_id  = sc.location_id
  LEFT JOIN saldo_awal_cte sa ON sa.material_id=sc.material_id
                              AND COALESCE(sa.location_id,0)=COALESCE(sc.location_id,0)
  WHERE sc.doc_date <= p_date_end
    AND (p_warehouse_id IS NULL OR sc.warehouse_id = p_warehouse_id)
  GROUP BY sc.material_id, sc.warehouse_id, sc.location_id, sc.uom_code,
           m.material_code, m.material_name, w.warehouse_name, l.location_name, sa.saldo_awal
  ORDER BY m.material_code, l.location_code;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_rm_rebuild_stockcard
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_rm_rebuild_stockcard`;
delimiter ;;
CREATE PROCEDURE `sp_rm_rebuild_stockcard`(IN p_material_id  BIGINT,
  IN p_warehouse_id BIGINT,
  IN p_location_id  BIGINT)
  COMMENT 'Rebuild saldo rm_stockcard — reset per material/location'
BEGIN
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_doc_id BIGINT; DECLARE v_material BIGINT; DECLARE v_location BIGINT;
  DECLARE v_qty_in DECIMAL(20,4); DECLARE v_qty_out DECIMAL(20,4); DECLARE v_qty_adj DECIMAL(20,4);
  DECLARE v_prev_material BIGINT DEFAULT NULL;
  DECLARE v_prev_location BIGINT DEFAULT NULL;
  DECLARE v_prev_doc BIGINT DEFAULT NULL;
  DECLARE v_running_balance DECIMAL(20,4) DEFAULT 0;
  DECLARE cur1 CURSOR FOR
    SELECT doc_id, material_id, location_id, qty_in, qty_out, qty_adj
    FROM tminv.rm_stockcard
    WHERE (p_material_id  IS NULL OR material_id  = p_material_id)
      AND (p_warehouse_id IS NULL OR warehouse_id = p_warehouse_id)
      AND (p_location_id  IS NULL OR location_id  = p_location_id)
    ORDER BY material_id, location_id, doc_date, doc_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  OPEN cur1;
  rebuild_loop: LOOP
    FETCH cur1 INTO v_doc_id, v_material, v_location, v_qty_in, v_qty_out, v_qty_adj;
    IF v_done THEN LEAVE rebuild_loop; END IF;
    IF v_material <> COALESCE(v_prev_material,-1)
    OR COALESCE(v_location,-1) <> COALESCE(v_prev_location,-1) THEN
      SET v_running_balance = 0; SET v_prev_doc = NULL;
    END IF;
    UPDATE tminv.rm_stockcard
       SET previous_doc_id = v_prev_doc,
           qty_bb = v_running_balance,
           qty_eb = v_running_balance + v_qty_in - v_qty_out + v_qty_adj
     WHERE doc_id = v_doc_id;
    SET v_running_balance = v_running_balance + v_qty_in - v_qty_out + v_qty_adj;
    SET v_prev_doc = v_doc_id; SET v_prev_material = v_material; SET v_prev_location = v_location;
  END LOOP;
  CLOSE cur1;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_rm_stock_card
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_rm_stock_card`;
delimiter ;;
CREATE PROCEDURE `sp_rm_stock_card`(IN p_material_id  BIGINT,   -- NULL = semua material
  IN p_date_start   DATE,
  IN p_date_end     DATE)
  COMMENT 'Kartu stok RM per item dan periode'
BEGIN
  SELECT
    sc.doc_id, sc.doc_date, sc.doc_type,
    sc.material_id, m.material_code, m.material_name,
    sc.warehouse_id, w.warehouse_name,
    sc.location_id,  l.location_name,
    sc.lot_no, sc.uom_code,
    COALESCE(pr.doc_no, rs.doc_no) AS doc_no,
    sc.qty_bb   AS saldo_awal,
    sc.qty_in   AS masuk,
    sc.qty_out  AS keluar,
    sc.qty_adj  AS penyesuaian,
    sc.qty_eb   AS saldo_akhir
  FROM tminv.rm_stockcard sc
  LEFT JOIN tmmst.m_raw_material m   ON m.material_id    = sc.material_id
  LEFT JOIN tmmst.m_warehouse    w   ON w.warehouse_id   = sc.warehouse_id
  LEFT JOIN tmmst.m_wh_loc       l   ON l.location_id    = sc.location_id
  LEFT JOIN tminv.rm_stock_d     rsd ON rsd.doc_detail_id = sc.reference_detail_id
  LEFT JOIN tminv.rm_stock       rs  ON rs.doc_id         = rsd.doc_id
  LEFT JOIN tmpur.p_receive_d    prd ON prd.doc_detail_id = sc.reference_detail_id
                                    AND rs.doc_id IS NULL
  LEFT JOIN tmpur.p_receive      pr  ON pr.doc_id         = prd.doc_id
  WHERE (p_material_id IS NULL OR sc.material_id = p_material_id)
    AND DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end
  ORDER BY m.material_code, sc.doc_date, sc.doc_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_sfg_master_stock
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_sfg_master_stock`;
delimiter ;;
CREATE PROCEDURE `sp_sfg_master_stock`(IN p_date_start DATE, IN p_date_end DATE,
  IN p_warehouse_id BIGINT)
  COMMENT 'Master stok SFG per parts+lokasi, per periode'
BEGIN
  WITH saldo_awal_cte AS (
    SELECT sc1.parts_id, sc1.location_id, sc1.qty_eb AS saldo_awal
    FROM tminv.sfg_stockcard sc1
    WHERE sc1.doc_date = (SELECT MAX(sc2.doc_date) FROM tminv.sfg_stockcard sc2
        WHERE sc2.parts_id=sc1.parts_id AND COALESCE(sc2.location_id,0)=COALESCE(sc1.location_id,0)
          AND sc2.doc_date < p_date_start)
      AND sc1.doc_id = (SELECT MAX(sc3.doc_id) FROM tminv.sfg_stockcard sc3
        WHERE sc3.parts_id=sc1.parts_id AND COALESCE(sc3.location_id,0)=COALESCE(sc1.location_id,0)
          AND sc3.doc_date=sc1.doc_date)
  )
  SELECT
    ROW_NUMBER() OVER (ORDER BY pp.parts_code, l.location_code) AS no_urut,
    sc.parts_id, pp.parts_code, pp.parts_name, p.product_name AS produk_induk,
    sc.warehouse_id, w.warehouse_name, sc.location_id, l.location_name,
    sc.uom_code AS satuan,
    IFNULL(sa.saldo_awal,0) AS saldo_awal,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_in  END),0) AS total_masuk,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_out END),0) AS total_keluar,
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end THEN sc.qty_adj END),0) AS total_penyesuaian,
    IFNULL(sa.saldo_awal,0) +
    IFNULL(SUM(CASE WHEN DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end
                    THEN sc.qty_in - sc.qty_out + sc.qty_adj END),0) AS saldo_akhir
  FROM tminv.sfg_stockcard sc
  JOIN tmmst.m_product_parts pp ON pp.parts_id  = sc.parts_id
  JOIN tmmst.m_product       p  ON p.product_id = pp.product_id
  LEFT JOIN tmmst.m_warehouse w ON w.warehouse_id = sc.warehouse_id
  LEFT JOIN tmmst.m_wh_loc    l ON l.location_id  = sc.location_id
  LEFT JOIN saldo_awal_cte sa ON sa.parts_id=sc.parts_id
                              AND COALESCE(sa.location_id,0)=COALESCE(sc.location_id,0)
  WHERE sc.doc_date <= p_date_end
    AND (p_warehouse_id IS NULL OR sc.warehouse_id = p_warehouse_id)
  GROUP BY sc.parts_id, sc.warehouse_id, sc.location_id, sc.uom_code,
           pp.parts_code, pp.parts_name, p.product_name, w.warehouse_name, l.location_name, sa.saldo_awal
  ORDER BY pp.parts_code, l.location_code;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_sfg_rebuild_stockcard
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_sfg_rebuild_stockcard`;
delimiter ;;
CREATE PROCEDURE `sp_sfg_rebuild_stockcard`(IN p_parts_id     BIGINT,
  IN p_warehouse_id BIGINT)
  COMMENT 'Rebuild saldo sfg_stockcard — reset per parts/location'
BEGIN
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_doc_id BIGINT; DECLARE v_parts BIGINT; DECLARE v_location BIGINT;
  DECLARE v_qty_in DECIMAL(20,4); DECLARE v_qty_out DECIMAL(20,4); DECLARE v_qty_adj DECIMAL(20,4);
  DECLARE v_prev_parts BIGINT DEFAULT NULL;
  DECLARE v_prev_location BIGINT DEFAULT NULL;
  DECLARE v_prev_doc BIGINT DEFAULT NULL;
  DECLARE v_running_balance DECIMAL(20,4) DEFAULT 0;
  DECLARE cur1 CURSOR FOR
    SELECT doc_id, parts_id, location_id, qty_in, qty_out, qty_adj
    FROM tminv.sfg_stockcard
    WHERE (p_parts_id     IS NULL OR parts_id     = p_parts_id)
      AND (p_warehouse_id IS NULL OR warehouse_id = p_warehouse_id)
    ORDER BY parts_id, location_id, doc_date, doc_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  OPEN cur1;
  rebuild_loop: LOOP
    FETCH cur1 INTO v_doc_id, v_parts, v_location, v_qty_in, v_qty_out, v_qty_adj;
    IF v_done THEN LEAVE rebuild_loop; END IF;
    IF v_parts <> COALESCE(v_prev_parts,-1)
    OR COALESCE(v_location,-1) <> COALESCE(v_prev_location,-1) THEN
      SET v_running_balance = 0; SET v_prev_doc = NULL;
    END IF;
    UPDATE tminv.sfg_stockcard
       SET previous_doc_id = v_prev_doc,
           qty_bb = v_running_balance,
           qty_eb = v_running_balance + v_qty_in - v_qty_out + v_qty_adj
     WHERE doc_id = v_doc_id;
    SET v_running_balance = v_running_balance + v_qty_in - v_qty_out + v_qty_adj;
    SET v_prev_doc = v_doc_id; SET v_prev_parts = v_parts; SET v_prev_location = v_location;
  END LOOP;
  CLOSE cur1;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_sfg_stock_card
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_sfg_stock_card`;
delimiter ;;
CREATE PROCEDURE `sp_sfg_stock_card`(IN p_parts_id   BIGINT,
  IN p_date_start DATE,
  IN p_date_end   DATE)
  COMMENT 'Kartu stok SFG per komponen dan periode'
BEGIN
  SELECT
    sc.doc_id, sc.doc_date, sc.doc_type,
    sc.parts_id, pp.parts_code, pp.parts_name,
    sc.product_id, p.product_name,
    sc.warehouse_id, w.warehouse_name,
    sc.location_id,  l.location_name,
    sc.lot_no, sc.uom_code, ss.doc_no,
    sc.qty_bb AS saldo_awal,
    sc.qty_in AS masuk, sc.qty_out AS keluar,
    sc.qty_adj AS penyesuaian, sc.qty_eb AS saldo_akhir
  FROM tminv.sfg_stockcard sc
  LEFT JOIN tmmst.m_product_parts pp ON pp.parts_id    = sc.parts_id
  LEFT JOIN tmmst.m_product       p  ON p.product_id   = sc.product_id
  LEFT JOIN tmmst.m_warehouse     w  ON w.warehouse_id = sc.warehouse_id
  LEFT JOIN tmmst.m_wh_loc        l  ON l.location_id  = sc.location_id
  LEFT JOIN tminv.sfg_stock_d     sd ON sd.doc_detail_id = sc.reference_detail_id
  LEFT JOIN tminv.sfg_stock       ss ON ss.doc_id        = sd.doc_id
  WHERE (p_parts_id IS NULL OR sc.parts_id = p_parts_id)
    AND DATE(sc.doc_date) BETWEEN p_date_start AND p_date_end
  ORDER BY pp.parts_code, sc.doc_date, sc.doc_id;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table fg_stock
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_fg_stock_confirm`;
delimiter ;;
CREATE TRIGGER `trg_fg_stock_confirm` AFTER UPDATE ON `fg_stock` FOR EACH ROW BEGIN
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_product BIGINT; DECLARE v_parts BIGINT; DECLARE v_qty DECIMAL(20,4);
  DECLARE v_detail BIGINT; DECLARE v_location BIGINT; DECLARE v_lot VARCHAR(100);
  DECLARE v_uom VARCHAR(50); DECLARE v_prev_doc BIGINT;
  DECLARE v_bb DECIMAL(20,4); DECLARE v_eb DECIMAL(20,4); DECLARE v_qty_final DECIMAL(20,4);
  DECLARE v_cat VARCHAR(50);
  DECLARE cur1 CURSOR FOR
    SELECT product_id, parts_id, qty, doc_detail_id, location_id, lot_no, uom_code
    FROM tminv.fg_stock_d WHERE doc_id = NEW.doc_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  IF OLD.stat_confirm = 0 AND NEW.stat_confirm = 1 AND NEW.stat_cancel = 0 THEN
    SET v_cat = CASE
                  WHEN NEW.doc_cat IN ('ADJUST','ADJ')   THEN 'ADJ'
                  WHEN NEW.doc_cat IN ('OPN','OPNAME')    THEN 'ADJ'
                  ELSE NEW.doc_cat END;
    OPEN cur1;
    fg_loop: LOOP
      SET v_done = FALSE;
      FETCH cur1 INTO v_product, v_parts, v_qty, v_detail, v_location, v_lot, v_uom;
      IF v_done THEN LEAVE fg_loop; END IF;
      IF v_qty = 0 AND v_cat <> 'BB' THEN ITERATE fg_loop; END IF;

      IF v_cat = 'ADJ' THEN SET v_qty_final = v_qty;
      ELSEIF v_cat = 'RETURN' THEN SET v_qty_final = ABS(v_qty);
      ELSE SET v_qty_final = IF(NEW.doc_type = -1, -ABS(v_qty), ABS(v_qty)); END IF;

      SET v_prev_doc = NULL; SET v_bb = 0;
      SELECT doc_id, qty_eb INTO v_prev_doc, v_bb
        FROM tminv.fg_stockcard
       WHERE location_id = v_location
         AND COALESCE(parts_id,0)   = COALESCE(v_parts,0)
         AND COALESCE(product_id,0) = COALESCE(v_product,0)
         AND (doc_date < NEW.doc_date
              OR (doc_date = NEW.doc_date AND doc_id < LAST_INSERT_ID()))
       ORDER BY doc_date DESC, doc_id DESC LIMIT 1;
      SET v_bb = IFNULL(v_bb, 0);
      SET v_eb = v_bb + v_qty_final;

      INSERT INTO tminv.fg_stockcard (
        previous_doc_id, doc_date, product_id, parts_id, doc_type,
        location_id, warehouse_id, lot_no, qty, uom_code, reference_detail_id,
        qty_bb, qty_in, qty_out, qty_adj, qty_eb, create_by
      ) VALUES (
        v_prev_doc, NEW.doc_date, v_product, v_parts,
        CASE WHEN v_cat='BB' THEN 'BB'
             WHEN v_cat='RETURN' THEN 'IN'
             WHEN v_cat='ADJ' THEN 'ADJ'
             WHEN NEW.doc_type=1 THEN 'IN' ELSE 'OUT' END,
        v_location, NEW.warehouse_id, v_lot, v_qty_final, v_uom, v_detail,
        v_bb,
        IF(v_cat<>'ADJ' AND (NEW.doc_type=1 OR v_cat='RETURN'), ABS(v_qty), 0),
        IF(v_cat<>'ADJ' AND NEW.doc_type=-1, ABS(v_qty), 0),
        IF(v_cat='ADJ', v_qty, 0),
        v_eb, NEW.create_by
      );
    END LOOP;
    CLOSE cur1;
  END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table rm_stock
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_rm_stock_confirm`;
delimiter ;;
CREATE TRIGGER `trg_rm_stock_confirm` AFTER UPDATE ON `rm_stock` FOR EACH ROW BEGIN
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_material BIGINT; DECLARE v_qty DECIMAL(20,4); DECLARE v_detail BIGINT;
  DECLARE v_location BIGINT; DECLARE v_lot VARCHAR(100); DECLARE v_uom VARCHAR(50);
  DECLARE v_action VARCHAR(20);
  DECLARE v_prev_doc BIGINT; DECLARE v_bb DECIMAL(20,4); DECLARE v_eb DECIMAL(20,4);
  DECLARE v_qty_final DECIMAL(20,4); DECLARE v_cat VARCHAR(50);

  DECLARE cur1 CURSOR FOR
    SELECT material_id, qty, doc_detail_id, location_id, lot_no, uom_code, action_type
    FROM tminv.rm_stock_d WHERE doc_id = NEW.doc_id;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  IF IFNULL(OLD.stat_confirm, 0) = 0 AND NEW.stat_confirm = 1 AND NEW.stat_cancel = 0 THEN

    SET v_cat = CASE
                  WHEN NEW.doc_cat IN ('ADJUST','ADJ','AD') THEN 'AD'
                  WHEN NEW.doc_cat IN ('OPN','OPNAME','OP')  THEN 'AD'
                  ELSE NEW.doc_cat END;

    OPEN cur1;
    rm_loop: LOOP
      SET v_done = FALSE;
      FETCH cur1 INTO v_material, v_qty, v_detail, v_location, v_lot, v_uom, v_action;
      IF v_done THEN LEAVE rm_loop; END IF;

      IF v_action = 'PURCHASE' THEN ITERATE rm_loop; END IF;
      IF v_qty = 0 AND v_cat <> 'BB' THEN ITERATE rm_loop; END IF;

      IF v_cat = 'AD' THEN
          SET v_qty_final = v_qty;
      ELSEIF v_cat = 'RETURN' THEN
          SET v_qty_final = ABS(v_qty);
      ELSE
          SET v_qty_final = IF(NEW.doc_type = -1, -ABS(v_qty), ABS(v_qty));
      END IF;

      SET v_prev_doc = NULL;
      SET v_bb = 0;

      BEGIN
        DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END;
        SELECT doc_id, qty_eb INTO v_prev_doc, v_bb
          FROM tminv.rm_stockcard
         WHERE material_id = v_material AND location_id = v_location
           AND (doc_date < NEW.doc_date
                OR (doc_date = NEW.doc_date AND doc_id < NEW.doc_id))
         ORDER BY doc_date DESC, doc_id DESC LIMIT 1;
      END;

      SET v_bb = IFNULL(v_bb, 0);
      SET v_eb = v_bb + v_qty_final;

      INSERT INTO tminv.rm_stockcard (
        previous_doc_id, doc_date, material_id, doc_type,
        location_id, warehouse_id, lot_no, qty, uom_code, reference_detail_id,
        qty_bb, qty_in, qty_out, qty_adj, qty_eb, create_by
      ) VALUES (
        v_prev_doc, NEW.doc_date, v_material,
        CASE WHEN v_cat='BB' THEN 'BB'
             WHEN v_cat='RETURN' THEN 'IN'
             WHEN v_cat='AD' THEN 'AD'
             WHEN NEW.doc_type=1 THEN 'IN' ELSE 'OUT' END,
        v_location, NEW.warehouse_id, v_lot, v_qty_final, v_uom, v_detail,
        v_bb,
        IF(v_cat<>'AD' AND (NEW.doc_type=1 OR v_cat='RETURN'), ABS(v_qty), 0),
        IF(v_cat<>'AD' AND NEW.doc_type=-1, ABS(v_qty), 0),
        IF(v_cat='AD', v_qty, 0),
        v_eb, NEW.create_by
      );
    END LOOP;
    CLOSE cur1;
  END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sfg_stock
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sfg_stock_confirm`;
delimiter ;;
CREATE TRIGGER `trg_sfg_stock_confirm` AFTER UPDATE ON `sfg_stock` FOR EACH ROW BEGIN
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_parts BIGINT; DECLARE v_product BIGINT; DECLARE v_qty DECIMAL(20,4);
  DECLARE v_detail BIGINT; DECLARE v_location BIGINT; DECLARE v_lot VARCHAR(100);
  DECLARE v_uom VARCHAR(50); DECLARE v_prev_doc BIGINT;
  DECLARE v_bb DECIMAL(20,4); DECLARE v_eb DECIMAL(20,4); DECLARE v_qty_final DECIMAL(20,4);
  DECLARE v_cat VARCHAR(50);
  DECLARE cur1 CURSOR FOR
    SELECT parts_id, product_id, qty, doc_detail_id, location_id, lot_no, uom_code
    FROM tminv.sfg_stock_d WHERE doc_id = NEW.doc_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

  IF OLD.stat_confirm = 0 AND NEW.stat_confirm = 1 AND NEW.stat_cancel = 0 THEN
    SET v_cat = CASE
                  WHEN NEW.doc_cat IN ('ADJUST','ADJ')   THEN 'ADJ'
                  WHEN NEW.doc_cat IN ('OPN','OPNAME')    THEN 'ADJ'
                  ELSE NEW.doc_cat END;
    OPEN cur1;
    sfg_loop: LOOP
      SET v_done = FALSE;
      FETCH cur1 INTO v_parts, v_product, v_qty, v_detail, v_location, v_lot, v_uom;
      IF v_done THEN LEAVE sfg_loop; END IF;
      IF v_qty = 0 AND v_cat <> 'BB' THEN ITERATE sfg_loop; END IF;

      IF v_cat = 'ADJ' THEN SET v_qty_final = v_qty;
      ELSEIF v_cat = 'CANCEL' THEN SET v_qty_final = -ABS(v_qty);
      ELSE SET v_qty_final = IF(NEW.doc_type = -1, -ABS(v_qty), ABS(v_qty)); END IF;

      SET v_prev_doc = NULL; SET v_bb = 0;
      SELECT doc_id, qty_eb INTO v_prev_doc, v_bb
        FROM tminv.sfg_stockcard
       WHERE parts_id = v_parts AND location_id = v_location
         AND (doc_date < NEW.doc_date
              OR (doc_date = NEW.doc_date AND doc_id < LAST_INSERT_ID()))
       ORDER BY doc_date DESC, doc_id DESC LIMIT 1;
      SET v_bb = IFNULL(v_bb, 0);
      SET v_eb = v_bb + v_qty_final;

      INSERT INTO tminv.sfg_stockcard (
        previous_doc_id, doc_date, parts_id, product_id, doc_type,
        location_id, warehouse_id, lot_no, qty, uom_code, reference_detail_id,
        qty_bb, qty_in, qty_out, qty_adj, qty_eb, create_by
      ) VALUES (
        v_prev_doc, NEW.doc_date, v_parts, v_product,
        CASE WHEN v_cat='BB' THEN 'BB'
             WHEN v_cat='ADJ' THEN 'ADJ'
             WHEN v_cat='CANCEL' THEN 'CANCEL'
             WHEN NEW.doc_type=1 THEN 'IN' ELSE 'OUT' END,
        v_location, NEW.warehouse_id, v_lot, v_qty_final, v_uom, v_detail,
        v_bb,
        IF(v_cat NOT IN ('ADJ','CANCEL') AND NEW.doc_type=1, ABS(v_qty), 0),
        IF(v_cat<>'ADJ' AND NEW.doc_type=-1, ABS(v_qty),
           IF(v_cat='CANCEL', ABS(v_qty), 0)),
        IF(v_cat='ADJ', v_qty, 0),
        v_eb, NEW.create_by
      );
    END LOOP;
    CLOSE cur1;
  END IF;
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
