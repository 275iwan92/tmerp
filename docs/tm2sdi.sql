/*
 Navicat Premium Dump SQL

 Source Server         : 127.0.0.1
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46)
 Source Host           : localhost:3306
 Source Schema         : tm2sdi

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46)
 File Encoding         : 65001

 Date: 08/08/2026 12:30:53
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for iqp_costing
-- ----------------------------
DROP TABLE IF EXISTS `iqp_costing`;
CREATE TABLE `iqp_costing`  (
  `id_costing` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_inquiry',
  `doc_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CALCULATED|PENDING_APPROVAL|PUBLISHED|FAILED',
  `rounding_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Mode pembulatan',
  `ppn_mode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPN' COMMENT 'PPN|NON_PPN (snapshot dari inquiry)',
  `published_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=harga terbuka ke Sisi B (Sales)',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Approval Direksi = event publish',
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_costing`) USING BTREE,
  UNIQUE INDEX `uq_costing_inquiry`(`inquiry_id` ASC) USING BTREE,
  INDEX `idx_cost_status`(`doc_status` ASC) USING BTREE,
  CONSTRAINT `fk_cost_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header costing HPP (MTO). RAHASIA (Sisi A). Publish via approve Direksi' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_costing
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_costing_detail
-- ----------------------------
DROP TABLE IF EXISTS `iqp_costing_detail`;
CREATE TABLE `iqp_costing_detail`  (
  `id_costing_detail` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `costing_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_costing',
  `tier_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_tier (parameter per tier)',
  `part_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> iqp_part (NULL bila level tier/dokumen)',
  `line_kind` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'BUCKET|LADDER. BUCKET=komponen biaya; LADDER=parameter margin/pajak',
  `bucket_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'MATERIAL|PROCESS|TOOLING|COATING|HANDLING|SPOILAGE (bila line_kind=BUCKET)',
  `ref_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK ke master sumber (mat_/prod_ rate) — id snapshot',
  `ref_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Snapshot nama komponen',
  `qty` decimal(20, 4) NULL DEFAULT NULL,
  `rate_snapshot` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Rate BEKU saat hitung (master boleh berubah, ini tetap)',
  `amount` decimal(20, 4) NULL DEFAULT NULL COMMENT 'qty x rate_snapshot (subtotal komponen)',
  `tolerance_pct` decimal(10, 4) NULL DEFAULT NULL COMMENT 'Toleransi %',
  `margin_pct` decimal(10, 4) NULL DEFAULT NULL COMMENT 'Margin % (boleh beda per tier)',
  `hpp_per_pc` decimal(20, 4) NULL DEFAULT NULL COMMENT 'HPP/pc hasil Σ bucket',
  `after_margin` decimal(20, 4) NULL DEFAULT NULL COMMENT 'excl PPN setelah margin',
  `net_excl` decimal(20, 4) NULL DEFAULT NULL COMMENT 'setelah diskon item',
  `price_incl` decimal(20, 4) NULL DEFAULT NULL COMMENT 'incl PPN (display)',
  `calc_trace` json NULL COMMENT 'Jejak perhitungan (audit calc-trace)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_costing_detail`) USING BTREE,
  INDEX `idx_cdet_costing`(`costing_id` ASC) USING BTREE,
  INDEX `idx_cdet_tier`(`tier_id` ASC) USING BTREE,
  INDEX `idx_cdet_kind`(`line_kind` ASC, `bucket_type` ASC) USING BTREE,
  INDEX `fk_cdet_part`(`part_id` ASC) USING BTREE,
  CONSTRAINT `fk_cdet_costing` FOREIGN KEY (`costing_id`) REFERENCES `iqp_costing` (`id_costing`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cdet_part` FOREIGN KEY (`part_id`) REFERENCES `iqp_part` (`id_part`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cdet_tier` FOREIGN KEY (`tier_id`) REFERENCES `iqp_tier` (`id_tier`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail costing SATU tabel: BUCKET (komponen biaya) + LADDER (param margin/pajak) per tier. Rate di-snapshot' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_costing_detail
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_external_cost
-- ----------------------------
DROP TABLE IF EXISTS `iqp_external_cost`;
CREATE TABLE `iqp_external_cost`  (
  `id_external_cost` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_inquiry',
  `cost_kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'TRUCKING|EKSPEDISI|JASA_LUAR|LAINNYA',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `amount` decimal(20, 4) NOT NULL DEFAULT 0.0000,
  `tax_treatment` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TAXABLE' COMMENT 'TAXABLE(masuk DPP)|REIMBURSEMENT (blocker B9)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_external_cost`) USING BTREE,
  INDEX `idx_ext_inquiry`(`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_ext_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Biaya eksternal (trucking/ekspedisi/jasa luar). Default taxable (masuk DPP)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_external_cost
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_inquiry
-- ----------------------------
DROP TABLE IF EXISTS `iqp_inquiry`;
CREATE TABLE `iqp_inquiry`  (
  `id_inquiry` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IQ[YY][NNNNN], auto, immutable',
  `inquiry_date` date NOT NULL,
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sumber permintaan (email/WA/telp/dll)',
  `pic_sales` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'PIC Sales penanggung jawab',
  `customer_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> cust_customer (NULL bila prospek)',
  `is_prospect` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=calon (belum jadi customer resmi)',
  `prospect_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama calon bila belum ada customer_id',
  `deadline_quotation` date NULL DEFAULT NULL COMMENT 'Batas terbit quotation (min +14 HK)',
  `product_draft_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama produk draft (tanpa karakter khusus)',
  `sales_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `inquiry_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NEW' COMMENT 'FK -> iqp_type. NEW|RECALC|REVISE. Set once',
  `parent_product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_product asal (RECALC/REVISE); NULL untuk NEW',
  `revision_no` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Naik tiap versi baru',
  `material_source` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OWN' COMMENT 'OWN | CUSTOMER. Penentu sifat CO',
  `job_nature` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRODUCT' COMMENT 'PRODUCT | JASA (turunan material_source)',
  `cust_material_spec` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Spec bahan customer (wajib bila material_source=CUSTOMER)',
  `cust_material_eta` date NULL DEFAULT NULL COMMENT 'ETA kirim bahan customer',
  `tax_scheme` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPN' COMMENT 'PPN | NON_PPN (non-PPN dianggap include)',
  `pph_applicable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=kena PPh23 (hanya jasa)',
  `ppn_percent` decimal(6, 4) NOT NULL DEFAULT 11.0000,
  `pph_percent` decimal(6, 4) NOT NULL DEFAULT 2.0000,
  `discount_doc_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'PERCENT | AMOUNT (diskon amount dokumen)',
  `discount_doc_value` decimal(20, 4) NULL DEFAULT NULL,
  `discount_qty_value` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Diskon qty occasional',
  `discount_qty_min_ton` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Ambang tonase pengaktif diskon qty',
  `prove_final_version` int UNSIGNED NULL DEFAULT NULL COMMENT 'Nomor versi prove final (snapshot cepat; detail di iqp_prove)',
  `prove_approved_date` date NULL DEFAULT NULL COMMENT 'Tgl approval prove FISIK = mulai due-date clock',
  `due_date` date NULL DEFAULT NULL COMMENT '+14 HK dari prove_approved_date',
  `design_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Identitas artwork/proof, mis. DS003479',
  `quotation_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> quo_quotation (many-to-one)',
  `co_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> co_order (jejak balik via quotation)',
  `sub_state` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|WAITING_COST|SUBMITTED|PRICE_CALCULATED|PRICE_APPROVED|QUOTED|PROVING|PROVE_APPROVED|CONVERTED|CANCELLED|LOST',
  `is_confirmed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Sales kunci (stat_confirm lama)',
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Hanya bila is_confirmed=1',
  `cancel_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Approval Direksi = publish harga',
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Direksi yg approve',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_inquiry`) USING BTREE,
  UNIQUE INDEX `uq_inquiry_no`(`inquiry_no` ASC) USING BTREE,
  INDEX `idx_inq_customer`(`customer_id` ASC) USING BTREE,
  INDEX `idx_inq_parent`(`parent_product_id` ASC) USING BTREE,
  INDEX `idx_inq_substate`(`sub_state` ASC) USING BTREE,
  INDEX `idx_inq_quotation`(`quotation_id` ASC) USING BTREE,
  INDEX `fk_inq_type`(`inquiry_type` ASC) USING BTREE,
  CONSTRAINT `fk_inq_type` FOREIGN KEY (`inquiry_type`) REFERENCES `iqp_type` (`type_code`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header inquiry product (MTO). Sumber kebenaran spec/harga/pajak/state' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_inquiry
-- ----------------------------
INSERT INTO `iqp_inquiry` VALUES (5, 'IQ2600001', '2026-08-08', 'EMAIL', NULL, NULL, 1, 'CV Test Dieline', NULL, 'Box Test Dieline', NULL, 'NEW', NULL, 0, 'OWN', 'PRODUCT', NULL, NULL, 'PPN', 0, 11.0000, 2.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'DRAFT', 0, 0, NULL, 0, NULL, NULL, '2026-08-08 04:42:28', '2026-08-08 04:42:28', 'web', 'web');

-- ----------------------------
-- Table structure for iqp_part
-- ----------------------------
DROP TABLE IF EXISTS `iqp_part`;
CREATE TABLE `iqp_part`  (
  `id_part` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_inquiry',
  `part_seq` int UNSIGNED NOT NULL,
  `part_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `part_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'mis. Body / Lid / Partisi',
  `part_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Tipe part (acuan routing standar)',
  `qty_per_sku` decimal(20, 4) NOT NULL DEFAULT 1.0000 COMMENT 'Jumlah part per 1 SKU jadi',
  `cfg_color` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Konfigurasi warna, mis. 4/4, 4/0',
  `cfg_ink` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `dieline_spec` json NULL COMMENT 'Snapshot dieline_spec DSL {canvas,flat,vars,elements}',
  `flat_w` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Bentangan W (dari flat DSL) -> costing',
  `flat_h` decimal(20, 4) NULL DEFAULT NULL,
  `flat_area` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Luas bentangan -> costing',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_part`) USING BTREE,
  INDEX `idx_part_inquiry`(`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_part_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Part mirror inquiry (snapshot m_product_parts). Tiap part punya dieline & bahan' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_part
-- ----------------------------
INSERT INTO `iqp_part` VALUES (1, 5, 1, NULL, 'Body', 'DC', 1.0000, NULL, NULL, '{\"dims\": {\"dust_flap\": \"40.0000\", \"glue_flap\": \"15.0000\", \"product_width\": \"50.0000\", \"top_tuck_flap\": \"35.0000\", \"product_height\": \"120.0000\", \"product_length\": \"200\"}, \"flat\": {\"h\": \"H\", \"w\": \"W\"}, \"vars\": {\"H\": \"C+2*TT\", \"W\": \"2*(A+B)+G\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*(A+B)\", \"yBot\": \"TT+C\", \"yTop\": \"TT\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"x5-x1\", \"x\": \"x1\", \"y\": \"yTop\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yTop\", \"y2\": \"yBot\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yTop\", \"y2\": \"yBot\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yTop\", \"y2\": \"yBot\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yTop\", \"y2\": \"yTop\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yBot\", \"y2\": \"yBot\"}, {\"g\": \"8\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"0\", \"x1\": \"G\", \"yB\": \"yBot\", \"yT\": \"yTop\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yBot\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yBot\", \"dir\": \"down\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yBot\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yBot\", \"dir\": \"down\"}], \"type_code\": \"CARD_BOX\"}', 515.0000, 190.0000, 97850.0000, '2026-08-08 04:42:28', '2026-08-08 04:42:28', 'web', 'web');

-- ----------------------------
-- Table structure for iqp_part_material
-- ----------------------------
DROP TABLE IF EXISTS `iqp_part_material`;
CREATE TABLE `iqp_part_material`  (
  `id_part_material` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_part',
  `layer_seq` int UNSIGNED NOT NULL COMMENT 'Urutan layer (1 part bisa banyak lapis: duplex+SF)',
  `rawmat_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> mat_raw (snapshot id)',
  `rawmat_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Snapshot nama bahan (jaga bila master berubah)',
  `gram_weight` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Gramatur',
  `flute_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Tipe flute (corrugated)',
  `size_p` decimal(20, 4) NULL DEFAULT NULL,
  `size_l` decimal(20, 4) NULL DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=bahan utama layer',
  `material_source` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'OWN' COMMENT 'OWN|CUSTOMER per-layer (siapkan sejak Tier3; blocker B1)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_material`) USING BTREE,
  INDEX `idx_pmat_part`(`part_id` ASC) USING BTREE,
  CONSTRAINT `fk_pmat_part` FOREIGN KEY (`part_id`) REFERENCES `iqp_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Layer bahan per part (snapshot). Mendukung multi-layer (duplex+SF)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_part_material
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_part_process
-- ----------------------------
DROP TABLE IF EXISTS `iqp_part_process`;
CREATE TABLE `iqp_part_process`  (
  `id_part_process` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_part',
  `branch_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MAIN' COMMENT 'MAIN|L|R|BODY|LID (branch produksi)',
  `step_seq` int UNSIGNED NOT NULL COMMENT 'Urutan step dalam branch',
  `flow_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LINEAR' COMMENT 'LINEAR|SPLIT|MERGE',
  `process_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_process (snapshot id)',
  `process_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Snapshot nama proses',
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> mesin (snapshot id)',
  `qty_factor` decimal(20, 4) NOT NULL DEFAULT 1.0000 COMMENT 'Faktor qty (mis. SPLIT belah plano)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_process`) USING BTREE,
  INDEX `idx_pproc_part`(`part_id` ASC) USING BTREE,
  CONSTRAINT `fk_pproc_part` FOREIGN KEY (`part_id`) REFERENCES `iqp_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Proses per part, ber-branch & flow (SPLIT/MERGE). Snapshot routing' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_part_process
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_prove
-- ----------------------------
DROP TABLE IF EXISTS `iqp_prove`;
CREATE TABLE `iqp_prove`  (
  `id_prove` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_inquiry (UNIQUE = 1:1)',
  `design_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'mis. DS003479',
  `final_version` int UNSIGNED NULL DEFAULT NULL COMMENT 'Versi final terpilih',
  `pdf_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Path PDF artwork siap cetak final',
  `physical_approved_date` date NULL DEFAULT NULL COMMENT 'Tgl approval fisik (tanda tangan basah)',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Entry oleh officer; approval fisik oleh customer',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_prove`) USING BTREE,
  UNIQUE INDEX `uq_prove_inquiry`(`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_prove_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Prove final per inquiry (1:1). Repeat order = inquiry baru = prove baru' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_prove
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_prove_log
-- ----------------------------
DROP TABLE IF EXISTS `iqp_prove_log`;
CREATE TABLE `iqp_prove_log`  (
  `id_prove_log` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `prove_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_prove',
  `version_no` int UNSIGNED NOT NULL COMMENT 'v1, v2, v3...',
  `separation` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Separasi CMYK+PMS',
  `pdf_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sebab revisi/molor (follow-up wajib)',
  `logged_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `logged_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_prove_log`) USING BTREE,
  INDEX `idx_plog_prove`(`prove_id` ASC) USING BTREE,
  CONSTRAINT `fk_plog_prove` FOREIGN KEY (`prove_id`) REFERENCES `iqp_prove` (`id_prove`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Log versi prove (v1/v2/v3) di bawah prove header' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_prove_log
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_tier
-- ----------------------------
DROP TABLE IF EXISTS `iqp_tier`;
CREATE TABLE `iqp_tier`  (
  `id_tier` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_inquiry',
  `tier_seq` int UNSIGNED NOT NULL COMMENT 'Urutan tampil tier',
  `qty` decimal(20, 4) NOT NULL COMMENT 'Kuantitas tier (pcs)',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=tier UTAMA (WAJIB tepat 1 per inquiry)',
  `discount_item_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'PERCENT | AMOUNT (NULL=tanpa diskon)',
  `discount_item_value` decimal(20, 4) NULL DEFAULT NULL,
  `price_excl` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Harga/pc excl PPN (snapshot dari costing)',
  `price_incl` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Harga/pc incl PPN',
  `primary_key_guard` bigint UNSIGNED GENERATED ALWAYS AS (if((`is_primary` = 1),`inquiry_id`,NULL)) STORED NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_tier`) USING BTREE,
  UNIQUE INDEX `uq_one_primary_per_inquiry`(`primary_key_guard` ASC) USING BTREE,
  INDEX `idx_tier_inquiry`(`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_tier_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tier qty inquiry. 1 UTAMA dipaksa via unique index. Diskon item per tier' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_tier
-- ----------------------------
INSERT INTO `iqp_tier` VALUES (4, 5, 1, 5000.0000, 1, NULL, NULL, NULL, NULL, DEFAULT, '2026-08-08 04:42:28', '2026-08-08 04:42:28', 'web', 'web');

-- ----------------------------
-- Table structure for iqp_tooling
-- ----------------------------
DROP TABLE IF EXISTS `iqp_tooling`;
CREATE TABLE `iqp_tooling`  (
  `id_tooling` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> iqp_inquiry',
  `tooling_kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PLATE|PISAU|MATRAS|FILM',
  `tooling_asset_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> m_tooling_asset (bila reuse existing)',
  `tooling_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ADA|ADA_WARNING|AUS|TIDAK_ADA',
  `is_new_charge` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=tooling baru -> biaya sekali (line terpisah)',
  `cost` decimal(20, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Biaya tooling baru (0 bila repeat/ADA)',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_tooling`) USING BTREE,
  INDEX `idx_tool_inquiry`(`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_tool_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Line tooling. AUS/TIDAK_ADA -> biaya baru terpisah, tak disembunyikan di harga/pc' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_tooling
-- ----------------------------

-- ----------------------------
-- Table structure for iqp_type
-- ----------------------------
DROP TABLE IF EXISTS `iqp_type`;
CREATE TABLE `iqp_type`  (
  `type_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NEW | RECALC | REVISE',
  `type_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label UI',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`type_code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Lookup tipe inquiry. Set once di header, tak berubah' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of iqp_type
-- ----------------------------
INSERT INTO `iqp_type` VALUES ('NEW', 'Produk Baru', 1);
INSERT INTO `iqp_type` VALUES ('RECALC', 'Hitung Ulang Harga', 1);
INSERT INTO `iqp_type` VALUES ('REVISE', 'Ubah Spesifikasi', 1);

SET FOREIGN_KEY_CHECKS = 1;
