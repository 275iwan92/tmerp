/*
 Navicat Premium Dump SQL

 Source Server         : 127.0.0.1
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46)
 Source Host           : localhost:3306
 Source Schema         : tm2mst

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46)
 File Encoding         : 65001

 Date: 08/08/2026 12:30:38
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for cust_customer
-- ----------------------------
DROP TABLE IF EXISTS `cust_customer`;
CREATE TABLE `cust_customer`  (
  `id_customer` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `customer_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `tax_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'NPWP',
  `mr_ms` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Bapak/Ibu',
  `contact_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `default_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `default_area_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> geo_city/geo_district (id wilayah)',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `website` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `customer_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'local' COMMENT 'local | import',
  `payment_term` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'FK -> fin_payment_term (menyusul)',
  `currency_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'IDR' COMMENT 'FK -> fin_currency.currency_code',
  `credit_limit` decimal(18, 2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'Batas piutang',
  `current_balance` decimal(18, 2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'Saldo piutang berjalan',
  `is_prospect` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=prospek (calon dari inkuiri) | 0=resmi',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_customer`) USING BTREE,
  UNIQUE INDEX `uq_customer_code`(`customer_code` ASC) USING BTREE,
  INDEX `idx_cust_area`(`default_area_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master customer (+prospek)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of cust_customer
-- ----------------------------

-- ----------------------------
-- Table structure for fin_currency
-- ----------------------------
DROP TABLE IF EXISTS `fin_currency`;
CREATE TABLE `fin_currency`  (
  `id_currency` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `currency_code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode ISO 4217, mis. IDR, USD, EUR',
  `currency_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama mata uang, mis. RUPIAH',
  `symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Simbol tampil, mis. Rp, $, €',
  `counted_word` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sebutan satuan (terbilang), mis. RUPIAH',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=aktif dipilih | 0=nonaktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_currency`) USING BTREE,
  UNIQUE INDEX `uq_currency_code`(`currency_code` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master mata uang (ISO 4217). Di-refer oleh geo_country.currency_id' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of fin_currency
-- ----------------------------

-- ----------------------------
-- Table structure for geo_city
-- ----------------------------
DROP TABLE IF EXISTS `geo_city`;
CREATE TABLE `geo_city`  (
  `id_city` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> geo_region',
  `city_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode wilayah (mis. 35.07)',
  `city_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'KOTA|KABUPATEN (Indonesia) | CITY (luar negeri). Pembeda Malang',
  `city_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama kota/kabupaten, mis. Malang',
  `post_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Rentang kode pos, mis. 65151 - 65393',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_city`) USING BTREE,
  UNIQUE INDEX `uq_city_region_type_name`(`region_id` ASC, `city_type` ASC, `city_name` ASC) USING BTREE,
  INDEX `idx_city_region`(`region_id` ASC) USING BTREE,
  CONSTRAINT `fk_city_region` FOREIGN KEY (`region_id`) REFERENCES `geo_region` (`id_region`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master kota/kabupaten (Level 2). city_type membedakan KOTA vs KABUPATEN' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of geo_city
-- ----------------------------

-- ----------------------------
-- Table structure for geo_country
-- ----------------------------
DROP TABLE IF EXISTS `geo_country`;
CREATE TABLE `geo_country`  (
  `id_country` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode negara 2-huruf (ISO 3166-1 alpha-2), mis. ID',
  `iso_code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode 3-huruf (ISO 3166-1 alpha-3), mis. IDN',
  `country_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama negara',
  `capital_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Ibu kota',
  `call_country_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode telepon internasional, mis. 62',
  `land_area_km2` decimal(20, 2) UNSIGNED NULL DEFAULT NULL COMMENT 'Luas daratan (km2)',
  `currency_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> fin_currency. Mata uang resmi negara (1 negara->1 currency)',
  `tax_percentage` decimal(10, 4) NULL DEFAULT NULL COMMENT 'Default tax negara (pindahan dari m_currency lama)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=aktif | 0=nonaktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_country`) USING BTREE,
  UNIQUE INDEX `uq_country_name`(`country_name` ASC) USING BTREE,
  UNIQUE INDEX `uq_country_code`(`country_code` ASC) USING BTREE,
  INDEX `idx_country_currency`(`currency_id` ASC) USING BTREE,
  CONSTRAINT `fk_country_currency` FOREIGN KEY (`currency_id`) REFERENCES `fin_currency` (`id_currency`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master negara (Level 0 hierarki geo)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of geo_country
-- ----------------------------

-- ----------------------------
-- Table structure for geo_district
-- ----------------------------
DROP TABLE IF EXISTS `geo_district`;
CREATE TABLE `geo_district`  (
  `id_district` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> geo_city',
  `district_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode kecamatan (opsional)',
  `district_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama kecamatan, mis. Klojen',
  `post_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode pos kecamatan (opsional)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_district`) USING BTREE,
  UNIQUE INDEX `uq_district_city_name`(`city_id` ASC, `district_name` ASC) USING BTREE,
  INDEX `idx_district_city`(`city_id` ASC) USING BTREE,
  CONSTRAINT `fk_district_city` FOREIGN KEY (`city_id`) REFERENCES `geo_city` (`id_city`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master kecamatan (Level 3). Diisi via import resmi, bukan input manual' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of geo_district
-- ----------------------------

-- ----------------------------
-- Table structure for geo_region
-- ----------------------------
DROP TABLE IF EXISTS `geo_region`;
CREATE TABLE `geo_region`  (
  `id_region` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> geo_country',
  `region_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode wilayah (opsional)',
  `region_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama provinsi/state, mis. JAWA TIMUR',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_region`) USING BTREE,
  UNIQUE INDEX `uq_region_country_name`(`country_id` ASC, `region_name` ASC) USING BTREE,
  INDEX `idx_region_country`(`country_id` ASC) USING BTREE,
  CONSTRAINT `fk_region_country` FOREIGN KEY (`country_id`) REFERENCES `geo_country` (`id_country`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master provinsi/state (Level 1). FK ber-id ke country' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of geo_region
-- ----------------------------

-- ----------------------------
-- Table structure for m_product_type
-- ----------------------------
DROP TABLE IF EXISTS `m_product_type`;
CREATE TABLE `m_product_type`  (
  `product_type_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode tipe produk. PK.',
  `product_type_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama tampil (ID).',
  `category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PACKAGING' COMMENT 'Kategori (static SC): PACKAGING / COMMERCIAL_PRINT / DISPLAY / MISC.',
  `sub_category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sub-kategori: TUCK_BOX, TRAY, CYLINDER, POLYGONAL, FLAT_PRINT, dll.',
  `box_style` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'REVERSE_TUCK, STRAIGHT_TUCK, SLEEVE, AUTO_LOCK…',
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Foto preview 3D (ref. Templatemaker). Contoh: /img/producttype/CARD_BOX.jpg',
  `svg_generator` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kunci generator SVG dieline (mis. TUCK_END, SLEEVE, PILLOW). NULL = belum ada preview dinamis.',
  `dieline_sample` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'File SVG statis fallback bila svg_generator NULL.',
  `ecma_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode ECMA default.',
  `part_type_default` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Default m_part_type.part_type_code.',
  `has_multi_part` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = umumnya >1 part (Body+Lid, dsb).',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 999 COMMENT 'Urutan tampil di galeri.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif.',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Keterangan.',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `create_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `update_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `dieline_spec` json NULL COMMENT 'DSL deklaratif dieline (vars/elements/flat)',
  `material_filter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Filter opsional dropdown material (mis. category1=KERTAS). NULL = semua MATERIAL.',
  `spec_version` smallint NOT NULL DEFAULT 1,
  PRIMARY KEY (`product_type_code`) USING BTREE,
  INDEX `idx_m_ptype_cat`(`category` ASC, `sort_order` ASC) USING BTREE,
  INDEX `idx_m_ptype_active`(`is_active` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master tipe produk (galeri thumbnail). Menentukan kolom dimensi mana yang tampil.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of m_product_type
-- ----------------------------
INSERT INTO `m_product_type` VALUES ('AMPLOP_KOP', 'Amplop & Kop Surat', 'PACKAGING', 'FLAT_PRINT', 'REVERSE_TUCK,', 'Cuplikan layar 2026-07-17 160408(1).png', 'FLAT', '', '', 'SHEET', 1, 90, 1, 'Stationery kantor.', '', '2026-07-16 00:00:00', '', '2026-07-17 00:00:00', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('BAG', 'Paper Bag', 'PACKAGING', 'TUCK_BOX', 'REVERSE_TUCK,', 'Cuplikan layar 2026-07-17 162039.png', '', '', '', 'KRAFT', 0, 60, 0, 'Tas kertas, bisa dilipat rata.', '', '2026-07-16 00:00:00', '', '2026-07-17 00:00:00', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('BOX_WITH_LID', 'Box with Lid', 'PACKAGING', 'TRAY', NULL, '/img/producttype/BOX_WITH_LID.jpg', 'TRAY', NULL, NULL, 'DC', 1, 21, 1, 'Base tray + lid terpisah. Shoe box.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('BROSUR', 'Brosur / Leaflet', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/BROSUR.jpg', 'FLAT_FOLD', NULL, NULL, 'SHEET', 0, 82, 1, 'Lembaran lipat. Half/tri/Z/gate fold.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT_FOLD\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"n\": \"fold_count\", \"t\": \"foldlines\", \"w\": \"A\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('BUKU', 'Buku / Katalog', 'COMMERCIAL_PRINT', 'BOOK', NULL, '/img/producttype/BUKU.jpg', 'FLAT', NULL, NULL, 'SHEET', 1, 85, 1, 'Cover + isi. Saddle/perfect binding.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('CAKE_SLICE_BOX', 'Cake Slice Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/CAKE_SLICE_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 55, 0, 'Box segitiga potongan kue. Tanpa lem.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('CARD_BOX', 'Card Box (Tuck End)', 'PACKAGING', 'TUCK_BOX', NULL, '/img/producttype/CARD_BOX.jpg', 'TUCK_END', NULL, 'A60.20.00.03', 'DC', 0, 10, 1, 'Dus tuck end standar. Kosmetik, obat, playing card.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:09:41', '{\"gen\": \"TUCK_END\", \"flat\": {\"h\": \"max(TT,DF)+C+max(BT,DF)+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+max(BT,DF)\", \"W\": \"G+2*A+2*B\", \"bR\": \"max(BT,DF)\", \"ga\": \"min(8, C/10, G)\", \"tR\": \"max(TT,DF)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"style\": \"REVERSE_TUCK\", \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x2\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"BT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('COFFIN', 'Coffin Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/COFFIN.jpg', NULL, NULL, NULL, 'DC', 1, 56, 0, 'Box peti. Halloween/novelty.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('CONE_MITERED', 'Cone (Mitered)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/CONE_MITERED.jpg', NULL, NULL, NULL, 'DC', 0, 34, 0, 'Kerucut dipotong 2 bidang miring.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('CONE_TRUNCATED', 'Cone (Truncated)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/CONE_TRUNCATED.jpg', NULL, NULL, NULL, 'DC', 0, 33, 0, 'Kerucut terpotong. Top diameter 0 = kerucut penuh.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('COUNTER_DISPLAY', 'Counter Display', 'DISPLAY', 'POP', NULL, '/img/producttype/COUNTER_DISPLAY.jpg', 'TRAY', NULL, NULL, 'DC+SF', 0, 70, 1, 'Tray sisi miring untuk display.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('CRASH_LOCK', 'Auto / Crash Lock Bottom', 'PACKAGING', 'TUCK_BOX', 'AUTO_LOCK', '/img/producttype/CRASH_LOCK.jpg', 'CRASH_LOCK', NULL, 'A71.51.10.00', 'DC', 0, 13, 1, 'Bottom auto-lock, cepat saat packing.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"CRASH_LOCK\", \"flat\": {\"h\": \"max(TT,DF)+C+B*0.75+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+B*0.75\", \"W\": \"G+2*A+2*B\", \"ga\": \"min(8, C/10, G)\", \"lk\": \"B*0.5\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('CURVED_BOX', 'Curved Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/CURVED_BOX.jpg', NULL, NULL, NULL, 'DC', 0, 50, 0, 'Sisi melengkung. Parfum, perhiasan.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('CUSTOM', 'Custom / Lainnya', 'MISC', 'CUSTOM', NULL, '/img/producttype/CUSTOM.jpg', NULL, NULL, NULL, 'DC', 0, 999, 1, 'Tipe tidak terdaftar. Isi manual, jelaskan di spec_notes.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-16 14:29:38', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('CYLINDER', 'Cylinder (Mitered)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/CYLINDER.jpg', 'CYLINDER', NULL, NULL, 'DC', 0, 31, 1, 'Tube/pipa, opsional dipotong miring.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"CYLINDER\", \"flat\": {\"h\": \"C+2*P\", \"w\": \"PI*D+2*P\"}, \"vars\": {\"H\": \"max(C,D)\", \"W\": \"PI*D+max(20,D*0.3)+D\", \"r\": \"D/2\", \"bh\": \"C\", \"cx\": \"PI*D+max(20,D*0.3)+D/2\", \"cy\": \"C/2\", \"cir\": \"PI*D\", \"gap\": \"max(20, D*0.3)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"bh\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"cir\", \"x\": \"0\", \"y\": \"0\"}, {\"k\": \"CUT\", \"r\": \"r\", \"t\": \"circle\", \"cx\": \"cx\", \"cy\": \"cy\"}, {\"s\": \"tutup\", \"t\": \"text\", \"x\": \"cx\", \"y\": \"cy+r+16\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('EGG_SHAPED_BOX', 'Egg Shaped Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/EGG_SHAPED_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 52, 0, 'Box telur + tutup. Easter.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('ELLIPTICAL_BOX', 'Elliptical Box', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/ELLIPTICAL_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 32, 0, 'Box oval + tutup.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('ENVELOPE', 'Envelope', 'PACKAGING', 'BAG', NULL, '/img/producttype/ENVELOPE.jpg', NULL, NULL, NULL, 'SHEET', 0, 61, 0, 'Amplop surat klasik.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('EXPLODING_BOX', 'Exploding Box', 'PACKAGING', 'TRAY', NULL, '/img/producttype/EXPLODING_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 26, 0, 'Tray + lid, sisi terbuka saat lid dilepas.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('FLOOR_DISPLAY', 'Floor Display', 'DISPLAY', 'POP', NULL, '/img/producttype/FLOOR_DISPLAY.jpg', NULL, NULL, NULL, 'DC+SF', 1, 71, 0, 'Display lantai bertingkat.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('FLYER', 'Flyer', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/FLYER.jpg', 'FLAT', NULL, NULL, 'SHEET', 0, 83, 1, 'Lembaran tanpa lipat.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('GEM_STONE_BOX', 'Gem Stone Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/GEM_STONE_BOX.jpg', NULL, NULL, NULL, 'DC', 0, 44, 0, 'Dua piramida poligon disatukan.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('GIFT_BOX', 'Gift Box', 'PACKAGING', 'TUCK_BOX', NULL, 'GIFT_BOX.jpg', 'TRAY', NULL, NULL, 'DC', 0, 15, 1, 'Box hadiah. Net template lebih hemat bahan.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-22 19:04:50', '{\"flat\": {\"h\": \"H + 2*P\", \"w\": \"W + 2*P\"}, \"vars\": {\"H\": \"max(6, top_tuck_flap) + C + (C + A*0.9)\", \"W\": \"G + 2*A + 2*B\", \"SH\": \"max(3, A*0.14)\", \"TH\": \"max(6, thumb_hole_width)\", \"TT\": \"max(6, top_tuck_flap)\", \"x1\": \"G\", \"x2\": \"G + A\", \"x3\": \"G + A + B\", \"x4\": \"G + 2*A + B\", \"x5\": \"G + 2*A + 2*B\", \"yB\": \"max(6, top_tuck_flap) + C\", \"yK\": \"max(6, top_tuck_flap) + C + (C + A*0.9)\", \"yT\": \"max(6, top_tuck_flap)\", \"KAKI\": \"C + A*0.9\", \"mid3\": \"G + A + B/2\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"g\": \"G*0.6\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1 + SH\", \"b\": \"x2 - SH\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1 + SH\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x2 - SH\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x3 + SH\", \"b\": \"x4 - SH\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3 + SH\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x4 - SH\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x2\", \"b\": \"mid3 - TH/2\", \"h\": \"TT*0.75\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"mid3 + TH/2\", \"b\": \"x3\", \"h\": \"TT*0.75\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"r\": \"TH/2\", \"t\": \"circle\", \"cx\": \"mid3\", \"cy\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"TT*0.75\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yB\", \"y2\": \"yK - TH/2\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yB\", \"y2\": \"yK - TH/2\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"TH/2\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yK - TH/2\", \"dir\": \"down\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x3\", \"y1\": \"yB + C\", \"y2\": \"yB + C\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x3\", \"y1\": \"yK - A*0.28\", \"y2\": \"yK - A*0.28\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"TT*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"TT*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('HANG_TAG', 'Hang Tag', 'DISPLAY', 'POP', NULL, '/img/producttype/HANG_TAG.jpg', 'FLAT', NULL, NULL, 'DC', 0, 73, 1, 'Label gantung produk.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('HEART_SHAPED_BOX', 'Heart Shaped Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/HEART_SHAPED_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 51, 0, 'Box hati + tutup. Valentine.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('KALENDER_DINDING', 'Kalender Dinding', 'COMMERCIAL_PRINT', 'CALENDAR', NULL, '/img/producttype/KALENDER_DINDING.jpg', 'FLAT_CAL', NULL, NULL, 'SHEET', 1, 80, 1, 'Kalender gantung. Jilid spiral/wire-o + eyelet.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT_CAL\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\", \"nh\": \"max(5, A/50)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}, {\"k\": \"HOLE\", \"n\": \"nh\", \"r\": \"1.8\", \"t\": \"holes\", \"w\": \"A\", \"y\": \"7\"}, {\"k\": \"HOLE\", \"r\": \"3\", \"t\": \"circle\", \"cx\": \"A/2\", \"cy\": \"4\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('KALENDER_DUDUK', 'Kalender Duduk (Meja)', 'COMMERCIAL_PRINT', 'CALENDAR', NULL, '/img/producttype/KALENDER_DUDUK.jpg', 'FLAT', NULL, NULL, 'DC', 1, 81, 1, 'Kalender meja + kaki penyangga.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('KARTU_NAMA', 'Kartu Nama', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/KARTU_NAMA.jpg', 'FLAT', NULL, NULL, 'DC', 0, 89, 1, 'Kartu nama.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('LABEL_STIKER', 'Label / Stiker', 'COMMERCIAL_PRINT', 'LABEL', NULL, '/img/producttype/LABEL_STIKER.jpg', 'FLAT', NULL, NULL, 'SHEET', 0, 88, 1, 'Stiker die-cut / roll.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('MAILER', 'Mailer', 'PACKAGING', 'TUCK_BOX', NULL, '/img/producttype/MAILER.jpg', 'TRAY', NULL, NULL, 'DC+SF', 0, 17, 1, 'Amplop tebal. E-commerce.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('MAJALAH', 'Majalah', 'COMMERCIAL_PRINT', 'BOOK', NULL, '/img/producttype/MAJALAH.jpg', NULL, NULL, NULL, 'SHEET', 1, 86, 0, 'Cover + isi. Saddle stitch.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('MATCH_BOX', 'Match Box (Tray & Sleeve)', 'PACKAGING', 'TUCK_BOX', NULL, '/img/producttype/MATCH_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 16, 0, 'Tray + sleeve. 2 part.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('MILK_CARTON', 'Milk Carton', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/MILK_CARTON.jpg', NULL, NULL, NULL, 'DC', 0, 36, 0, 'Tube dengan bukaan gusset.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('MULTI_SHEET_BOX', 'Multi Sheet Box', 'PACKAGING', 'TRAY', NULL, '/img/producttype/MULTI_SHEET_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 24, 0, 'Box besar dari 6 lembar terpisah.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('NESTABLE_TRAY', 'Nestable Tray', 'PACKAGING', 'TRAY', NULL, '/img/producttype/NESTABLE_TRAY.jpg', NULL, NULL, NULL, 'DC', 0, 22, 0, 'Tray dengan draft angle, bisa ditumpuk.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('NOTA_INVOICE', 'Nota / Invoice (NCR)', 'COMMERCIAL_PRINT', 'BOOK', NULL, '/img/producttype/NOTA.jpg', NULL, NULL, NULL, 'SHEET', 1, 87, 0, 'Nota rangkap NCR.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('PARALLELEPIPED', 'Parallelepiped', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/PARALLELEPIPED.jpg', NULL, NULL, NULL, 'DC', 0, 46, 0, 'Box skew 6 jajaran genjang. Bisa jadi dadu.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('PASSEPARTOUT', 'Passepartout', 'PACKAGING', 'TRAY', NULL, '/img/producttype/PASSEPARTOUT.jpg', NULL, NULL, NULL, 'DC', 0, 25, 0, 'Tray dinding ganda tanpa lem. Photo frame.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('PILLOW_PACK', 'Pillow Pack', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/PILLOW_PACK.jpg', 'PILLOW', NULL, NULL, 'DC', 0, 57, 0, 'Bantal, cap melengkung. Voucher, perhiasan.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('PLATONIC_SOLIDS', 'Platonic Solids', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/PLATONIC_SOLIDS.jpg', NULL, NULL, NULL, 'DC', 0, 45, 0, 'Tetra/Hexa/Octa/Dodeca/Icosahedron.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('POLYGONAL_BOX', 'Polygonal Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/POLYGONAL_BOX.jpg', NULL, NULL, NULL, 'DC', 0, 40, 0, 'Box prismatik, tutup terintegrasi.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('POLYGONAL_BOX_LID', 'Polygonal Box with Lid', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/POLYGONAL_BOX_LID.jpg', NULL, NULL, NULL, 'DC', 1, 41, 0, 'Box prismatik + tutup terpisah.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('POLYGONAL_PYRAMID', 'Polygonal Pyramid', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/POLYGONAL_PYRAMID.jpg', NULL, NULL, NULL, 'DC', 0, 42, 0, 'Piramida alas poligon.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('POSTER', 'Poster', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/POSTER.jpg', 'FLAT', NULL, NULL, 'SHEET', 0, 84, 1, 'Cetak besar 1 lembar.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('PYRAMID_BOX', 'Pyramid Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/PYRAMID_BOX.jpg', NULL, NULL, NULL, 'DC', 0, 43, 0, 'Box piramida, alas jadi tutup.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('RHOMBUS_BOX', 'Rhombus Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/RHOMBUS_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 48, 0, 'Box belah ketupat + tutup.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('ROUND_BOX', 'Round Box (Hat Box)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/ROUND_BOX.jpg', 'CYLINDER', NULL, NULL, 'DC', 1, 30, 1, 'Box silinder + tutup. Cookie box.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"CYLINDER\", \"flat\": {\"h\": \"C+LH+2*P\", \"w\": \"PI*D+2*P\"}, \"vars\": {\"H\": \"max(C+LH,D)\", \"W\": \"PI*D+max(20,D*0.3)+D\", \"r\": \"D/2\", \"bh\": \"C+LH\", \"cx\": \"PI*D+max(20,D*0.3)+D/2\", \"cy\": \"(C+LH)/2\", \"cir\": \"PI*D\", \"gap\": \"max(20, D*0.3)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"bh\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"cir\", \"x\": \"0\", \"y\": \"0\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"0\", \"x2\": \"cir\", \"y1\": \"C\", \"y2\": \"C\"}, {\"k\": \"CUT\", \"r\": \"r\", \"t\": \"circle\", \"cx\": \"cx\", \"cy\": \"cy\"}, {\"s\": \"tutup\", \"t\": \"text\", \"x\": \"cx\", \"y\": \"cy+r+16\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('RTE_BOX', 'Reverse Tuck End (RTE)', 'PACKAGING', 'TUCK_BOX', 'REVERSE_TUCK', '/img/producttype/RTE_BOX.jpg', 'TUCK_END', NULL, 'A60.20.00.03', 'DC', 0, 11, 1, 'Tuck atas-bawah arah berlawanan. Paling ekonomis.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:09:41', '{\"gen\": \"TUCK_END\", \"flat\": {\"h\": \"max(TT,DF)+C+max(BT,DF)+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+max(BT,DF)\", \"W\": \"G+2*A+2*B\", \"bR\": \"max(BT,DF)\", \"ga\": \"min(8, C/10, G)\", \"tR\": \"max(TT,DF)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"style\": \"REVERSE_TUCK\", \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x2\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"BT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('SHALLOW_BOX', 'Shallow Box', 'PACKAGING', 'TRAY', NULL, '/img/producttype/SHALLOW_BOX.jpg', 'TRAY', NULL, NULL, 'DC', 1, 20, 1, 'Box tinggi rendah. Tart, pizza.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('SLEEVE', 'Sleeve', 'PACKAGING', 'TUCK_BOX', 'SLEEVE', '/img/producttype/SLEEVE.jpg', 'SLEEVE', NULL, 'A20.00.00.00', 'DC', 0, 14, 1, 'Selongsong tanpa tutup. Sering jadi part tambahan.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:59:31', '{\"gen\": \"SLEEVE\", \"flat\": {\"h\": \"C+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"C\", \"W\": \"G+2*A+2*B\", \"ga\": \"min(8, C/10, G)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"C\", \"yT\": \"0\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('SOMA_CUBE', 'Soma Cube', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/SOMA_CUBE.jpg', NULL, NULL, NULL, 'DC', 1, 49, 0, '7 polikubus. Puzzle.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('SPHERE', 'Sphere', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/SPHERE.jpg', NULL, NULL, NULL, 'DC', 0, 35, 0, 'Bola dari segmen berulang.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('STAR_BOX', 'Star Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/STAR_BOX.jpg', NULL, NULL, NULL, 'DC', 0, 53, 0, 'Box bintang.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('STAR_SHAPE', 'Star Shape', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/STAR_SHAPE.jpg', NULL, NULL, NULL, 'DC', 0, 54, 0, 'Bintang tebal, jumlah sudut bebas.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('STE_BOX', 'Straight Tuck End (STE)', 'PACKAGING', 'TUCK_BOX', 'STRAIGHT_TUCK', '/img/producttype/STE_BOX.jpg', 'TUCK_END', NULL, 'A60.10.00.03', 'DC', 0, 12, 1, 'Tuck atas-bawah searah. Tampilan lebih rapi.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:09:41', '{\"gen\": \"TUCK_END\", \"flat\": {\"h\": \"max(TT,DF)+C+max(BT,DF)+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+max(BT,DF)\", \"W\": \"G+2*A+2*B\", \"ga\": \"min(8, C/10, G)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"style\": \"STRAIGHT_TUCK\", \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"BT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 1);
INSERT INTO `m_product_type` VALUES ('TRAPEZOID_BOX', 'Trapezoid Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/TRAPEZOID_BOX.jpg', NULL, NULL, NULL, 'DC', 1, 47, 0, 'Box trapesium. Praline mewah.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('TRAY_INSERT', 'Tray Insert', 'PACKAGING', 'TRAY', NULL, '/img/producttype/TRAY_INSERT.jpg', NULL, NULL, NULL, 'DC', 0, 23, 0, 'Insert pengunci item di tengah box.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);
INSERT INTO `m_product_type` VALUES ('WOBBLER', 'Wobbler', 'DISPLAY', 'POP', NULL, '/img/producttype/WOBBLER.jpg', NULL, NULL, NULL, 'DC', 0, 72, 0, 'Display goyang di rak.', NULL, '2026-07-16 14:29:38', NULL, '2026-07-17 09:57:34', NULL, NULL, 1);

-- ----------------------------
-- Table structure for m_product_type_param
-- ----------------------------
DROP TABLE IF EXISTS `m_product_type_param`;
CREATE TABLE `m_product_type_param`  (
  `param_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK.',
  `product_type_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FK ke m_product_type.product_type_code.',
  `column_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama kolom fisik di i_inquiry_part yang di-show.',
  `param_label` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label override (kosongkan = pakai label default field SC).',
  `unit` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'mm' COMMENT 'Satuan: mm, deg, pcs, %, x.',
  `param_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NUM' COMMENT 'NUM = numeric input; ENUM = dropdown dari option_json',
  `option_json` json NULL COMMENT 'Utk param_type=ENUM: [{\"c\":\"KLEM_SENG\",\"l\":\"Klem Seng\"}]',
  `is_required` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = wajib diisi saat tipe ini dipilih.',
  `default_value` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Nilai default saat tipe dipilih.',
  `default_str` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Default utk param_type=ENUM (default_value hanya utk NUM)',
  `min_value` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Batas bawah validasi.',
  `max_value` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Batas atas validasi.',
  `seq` smallint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Urutan tampil.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif.',
  `create_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`param_id`) USING BTREE,
  UNIQUE INDEX `uq_ptype_param`(`product_type_code` ASC, `column_name` ASC) USING BTREE,
  INDEX `idx_ptype_param_seq`(`product_type_code` ASC, `seq` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 303 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Metadata show/hide kolom dimensi per tipe produk. Dibaca Ajax onChange product_type.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of m_product_type_param
-- ----------------------------
INSERT INTO `m_product_type_param` VALUES (1, 'CARD_BOX', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (2, 'CARD_BOX', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (3, 'CARD_BOX', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (4, 'CARD_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (5, 'CARD_BOX', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (6, 'CARD_BOX', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (7, 'CARD_BOX', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (8, 'CARD_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (9, 'RTE_BOX', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (10, 'RTE_BOX', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (11, 'RTE_BOX', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (12, 'RTE_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (13, 'RTE_BOX', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (14, 'RTE_BOX', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (15, 'RTE_BOX', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (16, 'RTE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (17, 'STE_BOX', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (18, 'STE_BOX', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (19, 'STE_BOX', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (20, 'STE_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (21, 'STE_BOX', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (22, 'STE_BOX', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (23, 'STE_BOX', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (24, 'STE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (25, 'CRASH_LOCK', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (26, 'CRASH_LOCK', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (27, 'CRASH_LOCK', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (28, 'CRASH_LOCK', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (29, 'CRASH_LOCK', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (30, 'CRASH_LOCK', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (31, 'CRASH_LOCK', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (32, 'SLEEVE', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (33, 'SLEEVE', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (34, 'SLEEVE', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (35, 'SLEEVE', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (36, 'SLEEVE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (37, 'GIFT_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (38, 'GIFT_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (39, 'GIFT_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (40, 'GIFT_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (41, 'GIFT_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (42, 'MATCH_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (43, 'MATCH_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (44, 'MATCH_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (45, 'MATCH_BOX', 'material_thickness', 'Tebal Material', 'mm', 'NUM', NULL, 1, 0.50, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (46, 'MATCH_BOX', 'clearance', 'Clearance', 'mm', 'NUM', NULL, 0, 2.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (47, 'MATCH_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (48, 'MAILER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 154.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (49, 'MAILER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 110.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (50, 'MAILER', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (51, 'MAILER', 'overlap', 'Overlap', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (52, 'MAILER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (53, 'SHALLOW_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (54, 'SHALLOW_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (55, 'SHALLOW_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (56, 'SHALLOW_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (57, 'BOX_WITH_LID', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 170.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (58, 'BOX_WITH_LID', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 110.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (59, 'BOX_WITH_LID', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (60, 'BOX_WITH_LID', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (61, 'BOX_WITH_LID', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (62, 'NESTABLE_TRAY', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 148.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (63, 'NESTABLE_TRAY', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 105.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (64, 'NESTABLE_TRAY', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 74.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (65, 'NESTABLE_TRAY', 'angle', 'Draft Angle', 'deg', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (66, 'NESTABLE_TRAY', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (67, 'TRAY_INSERT', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (68, 'TRAY_INSERT', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 160.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (69, 'TRAY_INSERT', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (70, 'TRAY_INSERT', 'item_length', 'Panjang Item', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (71, 'TRAY_INSERT', 'item_width', 'Lebar Item', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (72, 'TRAY_INSERT', 'item_height', 'Tinggi Item', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (73, 'TRAY_INSERT', 'material_thickness', 'Tebal Material', 'mm', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (74, 'TRAY_INSERT', 'thumb_hole_width', 'Lebar Lubang Jempol', 'mm', 'NUM', NULL, 0, 20.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (75, 'TRAY_INSERT', 'thumb_hole_length', 'Panjang Lubang Jempol', 'mm', 'NUM', NULL, 0, 50.00, NULL, NULL, NULL, 9, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (76, 'TRAY_INSERT', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 10, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (77, 'MULTI_SHEET_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (78, 'MULTI_SHEET_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (79, 'MULTI_SHEET_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 200.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (80, 'MULTI_SHEET_BOX', 'material_thickness', 'Tebal Material', 'mm', 'NUM', NULL, 1, 0.50, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (81, 'MULTI_SHEET_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (82, 'PASSEPARTOUT', 'picture_length', 'Panjang Gambar', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (83, 'PASSEPARTOUT', 'picture_width', 'Lebar Gambar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (84, 'PASSEPARTOUT', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (85, 'PASSEPARTOUT', 'frame_width', 'Lebar Bingkai', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (86, 'PASSEPARTOUT', 'frame_depth', 'Kedalaman Bingkai', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (87, 'PASSEPARTOUT', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (88, 'EXPLODING_BOX', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (89, 'EXPLODING_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (90, 'EXPLODING_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (91, 'EXPLODING_BOX', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (92, 'EXPLODING_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (93, 'ROUND_BOX', 'diameter', 'Diameter', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (94, 'ROUND_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (95, 'ROUND_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (96, 'ROUND_BOX', 'extra_offset_caps', 'Offset Ekstra Cap', 'mm', 'NUM', NULL, 0, 0.50, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (97, 'ROUND_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (98, 'CYLINDER', 'diameter', 'Diameter', 'mm', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (99, 'CYLINDER', 'product_height', 'Panjang', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (100, 'CYLINDER', 'top_miter_angle', 'Sudut Miter Atas', 'deg', 'NUM', NULL, 0, 45.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (101, 'CYLINDER', 'bottom_miter_angle', 'Sudut Miter Bawah', 'deg', 'NUM', NULL, 0, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (102, 'CYLINDER', 'twist', 'Twist', 'deg', 'NUM', NULL, 0, 60.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (103, 'CYLINDER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (104, 'ELLIPTICAL_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (105, 'ELLIPTICAL_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (106, 'ELLIPTICAL_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (107, 'ELLIPTICAL_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (108, 'ELLIPTICAL_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (109, 'CONE_TRUNCATED', 'top_diameter', 'Diameter Atas', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (110, 'CONE_TRUNCATED', 'bottom_diameter', 'Diameter Bawah', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (111, 'CONE_TRUNCATED', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 120.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (112, 'CONE_TRUNCATED', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (113, 'CONE_MITERED', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (114, 'CONE_MITERED', 'top_size', 'Top', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (115, 'CONE_MITERED', 'top_angle', 'Sudut Atas', 'deg', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (116, 'CONE_MITERED', 'top_miter_angle', 'Sudut Miter Atas', 'deg', 'NUM', NULL, 0, -10.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (117, 'CONE_MITERED', 'bottom_miter_angle', 'Sudut Miter Bawah', 'deg', 'NUM', NULL, 0, 10.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (118, 'CONE_MITERED', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (119, 'SPHERE', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (120, 'SPHERE', 'segments', 'Jumlah Segmen', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (121, 'SPHERE', 'rings', 'Jumlah Ring', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (122, 'SPHERE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (123, 'MILK_CARTON', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (124, 'MILK_CARTON', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (125, 'MILK_CARTON', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (126, 'MILK_CARTON', 'roof_height', 'Tinggi Atap', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (127, 'MILK_CARTON', 'top_flap', 'Flap Atas', 'mm', 'NUM', NULL, 1, 18.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (128, 'MILK_CARTON', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (129, 'MILK_CARTON', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (130, 'POLYGONAL_BOX', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (131, 'POLYGONAL_BOX', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (132, 'POLYGONAL_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (133, 'POLYGONAL_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (134, 'POLYGONAL_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (135, 'POLYGONAL_BOX_LID', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (136, 'POLYGONAL_BOX_LID', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 7.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (137, 'POLYGONAL_BOX_LID', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (138, 'POLYGONAL_BOX_LID', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (139, 'POLYGONAL_BOX_LID', 'clearance_pct', 'Clearance', '%', 'NUM', NULL, 0, 5.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (140, 'POLYGONAL_BOX_LID', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (141, 'POLYGONAL_PYRAMID', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (142, 'POLYGONAL_PYRAMID', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (143, 'POLYGONAL_PYRAMID', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 7.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (144, 'POLYGONAL_PYRAMID', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (145, 'PYRAMID_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 115.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (146, 'PYRAMID_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 115.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (147, 'PYRAMID_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 73.50, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (148, 'PYRAMID_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (149, 'GEM_STONE_BOX', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (150, 'GEM_STONE_BOX', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (151, 'GEM_STONE_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (152, 'GEM_STONE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (153, 'PLATONIC_SOLIDS', 'midsphere_diameter', 'Diameter Midsphere', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (154, 'PLATONIC_SOLIDS', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (155, 'PARALLELEPIPED', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (156, 'PARALLELEPIPED', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (157, 'PARALLELEPIPED', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (158, 'PARALLELEPIPED', 'angle_alpha', 'Sudut α', 'deg', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (159, 'PARALLELEPIPED', 'angle_beta', 'Sudut β', 'deg', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (160, 'PARALLELEPIPED', 'angle_gamma', 'Sudut γ', 'deg', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (161, 'PARALLELEPIPED', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (162, 'TRAPEZOID_BOX', 'top_length', 'Panjang Atas', 'mm', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (163, 'TRAPEZOID_BOX', 'bottom_length', 'Panjang Bawah', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (164, 'TRAPEZOID_BOX', 'top_width', 'Lebar Atas', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (165, 'TRAPEZOID_BOX', 'bottom_width', 'Lebar Bawah', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (166, 'TRAPEZOID_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (167, 'TRAPEZOID_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (168, 'RHOMBUS_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (169, 'RHOMBUS_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (170, 'RHOMBUS_BOX', 'angle', 'Sudut', 'deg', 'NUM', NULL, 1, 120.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (171, 'RHOMBUS_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (172, 'RHOMBUS_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (173, 'SOMA_CUBE', 'unit_cell_length', 'Panjang Unit Sel', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (174, 'SOMA_CUBE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (175, 'CURVED_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (176, 'CURVED_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (177, 'CURVED_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (178, 'CURVED_BOX', 'curve_size', 'Besar Lengkung', 'mm', 'NUM', NULL, 1, -5.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (179, 'CURVED_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (180, 'HEART_SHAPED_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (181, 'HEART_SHAPED_BOX', 'shape_pct', 'Bentuk', '%', 'NUM', NULL, 1, 55.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (182, 'HEART_SHAPED_BOX', 'tilt', 'Tilt', 'deg', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (183, 'HEART_SHAPED_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (184, 'HEART_SHAPED_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (185, 'HEART_SHAPED_BOX', 'extra_offset_caps', 'Offset Ekstra Cap', 'mm', 'NUM', NULL, 0, 1.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (186, 'HEART_SHAPED_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (187, 'EGG_SHAPED_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (188, 'EGG_SHAPED_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (189, 'EGG_SHAPED_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (190, 'EGG_SHAPED_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (191, 'EGG_SHAPED_BOX', 'sharpness', 'Ketajaman', 'x', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (192, 'EGG_SHAPED_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (193, 'STAR_BOX', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (194, 'STAR_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (195, 'STAR_BOX', 'number_of_points', 'Jumlah Sudut', 'x', 'NUM', NULL, 1, 5.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (196, 'STAR_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (197, 'STAR_SHAPE', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 200.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (198, 'STAR_SHAPE', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 76.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (199, 'STAR_SHAPE', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 152.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (200, 'STAR_SHAPE', 'number_of_points', 'Jumlah Sudut', 'x', 'NUM', NULL, 1, 5.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (201, 'STAR_SHAPE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (202, 'CAKE_SLICE_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (203, 'CAKE_SLICE_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (204, 'CAKE_SLICE_BOX', 'angle', 'Sudut', 'deg', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (205, 'CAKE_SLICE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (206, 'COFFIN', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 168.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (207, 'COFFIN', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 56.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (208, 'COFFIN', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 46.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (209, 'COFFIN', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (210, 'COFFIN', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (211, 'PILLOW_PACK', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (212, 'PILLOW_PACK', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (213, 'PILLOW_PACK', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (214, 'PILLOW_PACK', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (215, 'PILLOW_PACK', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (216, 'BAG', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (217, 'BAG', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (218, 'BAG', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (219, 'BAG', 'fold', 'Lipatan', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (220, 'BAG', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (221, 'BAG', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (222, 'ENVELOPE', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (223, 'ENVELOPE', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (224, 'ENVELOPE', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (225, 'ENVELOPE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (226, 'COUNTER_DISPLAY', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (227, 'COUNTER_DISPLAY', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (228, 'COUNTER_DISPLAY', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (229, 'COUNTER_DISPLAY', 'front', 'Tinggi Depan', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (230, 'COUNTER_DISPLAY', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (231, 'COUNTER_DISPLAY', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (232, 'FLOOR_DISPLAY', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 400.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (233, 'FLOOR_DISPLAY', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 300.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (234, 'FLOOR_DISPLAY', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 1500.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (235, 'FLOOR_DISPLAY', 'front', 'Tinggi Depan', 'mm', 'NUM', NULL, 0, 100.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (236, 'FLOOR_DISPLAY', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (237, 'WOBBLER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (238, 'WOBBLER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (239, 'WOBBLER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (240, 'HANG_TAG', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (241, 'HANG_TAG', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (242, 'HANG_TAG', 'thumb_hole_width', 'Diameter Lubang', 'mm', 'NUM', NULL, 0, 5.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (243, 'HANG_TAG', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (244, 'KALENDER_DINDING', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 330.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (245, 'KALENDER_DINDING', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 480.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (246, 'KALENDER_DINDING', 'page_count', 'Jumlah Lembar', 'x', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (247, 'KALENDER_DINDING', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `m_product_type_param` VALUES (248, 'KALENDER_DINDING', 'hanger_type', 'Tipe Gantungan', NULL, 'ENUM', '[{\"c\": \"EYELET\", \"l\": \"Eyelet (Mata Ayam)\"}, {\"c\": \"HOOK\", \"l\": \"Hook (Gantungan Kait)\"}, {\"c\": \"DIECUT\", \"l\": \"Die-cut (Lubang Potong)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Gantungan\"}]', 1, NULL, 'EYELET', NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `m_product_type_param` VALUES (249, 'KALENDER_DINDING', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (250, 'KALENDER_DUDUK', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (251, 'KALENDER_DUDUK', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (252, 'KALENDER_DUDUK', 'product_height', 'Tinggi Kaki', 'mm', 'NUM', NULL, 0, 100.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (253, 'KALENDER_DUDUK', 'page_count', 'Jumlah Lembar', 'x', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (254, 'KALENDER_DUDUK', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `m_product_type_param` VALUES (255, 'KALENDER_DUDUK', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (256, 'BROSUR', 'product_length', 'Panjang (buka)', 'mm', 'NUM', NULL, 1, 297.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (257, 'BROSUR', 'product_width', 'Lebar (buka)', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (258, 'BROSUR', 'fold_count', 'Jumlah Lipatan', 'x', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (259, 'BROSUR', 'fold_type', 'Tipe Lipat', NULL, 'ENUM', '[{\"c\": \"HALF\", \"l\": \"Lipat 2 (Half / Bi-fold)\"}, {\"c\": \"TRI\", \"l\": \"Lipat 3 (Tri-fold)\"}, {\"c\": \"Z\", \"l\": \"Lipat Z (Zig-zag)\"}, {\"c\": \"GATE\", \"l\": \"Gate Fold\"}, {\"c\": \"ROLL\", \"l\": \"Roll Fold\"}]', 1, NULL, 'TRI', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `m_product_type_param` VALUES (260, 'BROSUR', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (261, 'FLYER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (262, 'FLYER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 148.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (263, 'FLYER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (264, 'POSTER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 594.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (265, 'POSTER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 420.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (266, 'POSTER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (267, 'BUKU', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (268, 'BUKU', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 148.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (269, 'BUKU', 'page_count', 'Jumlah Halaman', 'x', 'NUM', NULL, 1, 64.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (270, 'BUKU', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `m_product_type_param` VALUES (271, 'BUKU', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (272, 'MAJALAH', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 275.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (273, 'MAJALAH', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (274, 'MAJALAH', 'page_count', 'Jumlah Halaman', 'x', 'NUM', NULL, 1, 48.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (275, 'MAJALAH', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `m_product_type_param` VALUES (276, 'MAJALAH', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (277, 'NOTA_INVOICE', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (278, 'NOTA_INVOICE', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (279, 'NOTA_INVOICE', 'page_count', 'Jumlah Rangkap', 'x', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (280, 'NOTA_INVOICE', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `m_product_type_param` VALUES (281, 'NOTA_INVOICE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (282, 'LABEL_STIKER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (283, 'LABEL_STIKER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (284, 'LABEL_STIKER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (285, 'KARTU_NAMA', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (286, 'KARTU_NAMA', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 55.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (287, 'KARTU_NAMA', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (288, 'AMPLOP_KOP', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 220.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (289, 'AMPLOP_KOP', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 110.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (290, 'AMPLOP_KOP', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (291, 'AMPLOP_KOP', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (292, 'CUSTOM', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (293, 'CUSTOM', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (294, 'CUSTOM', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (295, 'CUSTOM', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (296, 'CUSTOM', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (297, 'CUSTOM', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (298, 'CUSTOM', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (299, 'CUSTOM', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 0, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `m_product_type_param` VALUES (300, 'GIFT_BOX', 'top_tuck_flap', 'Tuck Flap (mm)', 'mm', 'NUM', NULL, 0, 15.00, NULL, 5.00, 80.00, 6, 1, '2026-07-22 19:04:49', '2026-07-22 19:04:49');
INSERT INTO `m_product_type_param` VALUES (301, 'GIFT_BOX', 'thumb_hole_width', 'Lubang Jempol (mm)', 'mm', 'NUM', NULL, 0, 15.00, NULL, 0.00, 60.00, 7, 1, '2026-07-22 19:04:49', '2026-07-22 19:04:49');

-- ----------------------------
-- Table structure for m_product_type_size
-- ----------------------------
DROP TABLE IF EXISTS `m_product_type_size`;
CREATE TABLE `m_product_type_size`  (
  `size_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK.',
  `product_type_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FK ke m_prd_type.product_type_code.',
  `size_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serum 30ml (40x40x110)',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 999,
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1,
  `create_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`size_id`) USING BTREE,
  UNIQUE INDEX `uq_ptype_size`(`product_type_code` ASC, `size_name` ASC) USING BTREE,
  INDEX `idx_ptype_size_seq`(`product_type_code` ASC, `is_active` ASC, `sort_order` ASC) USING BTREE,
  CONSTRAINT `fk_prd_size_type` FOREIGN KEY (`product_type_code`) REFERENCES `m_product_type` (`product_type_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 84 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Preset ukuran pasaran per tipe produk. Pilih -> auto-fill dimensi.' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of m_product_type_size
-- ----------------------------
INSERT INTO `m_product_type_size` VALUES (1, 'CARD_BOX', 'Serum 30ml (40x40x110)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (2, 'CARD_BOX', 'Skincare 50ml (50x50x150)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (3, 'CARD_BOX', 'Kosmetik sedang (60x60x100)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (4, 'CARD_BOX', 'Dus obat strip (105x32x22)', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (5, 'CARD_BOX', 'Slim/masker (95x20x150)', 50, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (6, 'CARD_BOX', 'Kotak teh (70x70x120)', 60, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (7, 'RTE_BOX', 'Serum 30ml (40x40x110)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (8, 'RTE_BOX', 'Skincare 50ml (50x50x150)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (9, 'RTE_BOX', 'Kosmetik sedang (60x60x100)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (10, 'RTE_BOX', 'Dus obat strip (105x32x22)', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (11, 'RTE_BOX', 'Slim/masker (95x20x150)', 50, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (12, 'RTE_BOX', 'Kotak teh (70x70x120)', 60, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (13, 'STE_BOX', 'Serum 30ml (40x40x110)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (14, 'STE_BOX', 'Skincare 50ml (50x50x150)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (15, 'STE_BOX', 'Kosmetik sedang (60x60x100)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (16, 'STE_BOX', 'Dus obat strip (105x32x22)', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (17, 'STE_BOX', 'Slim/masker (95x20x150)', 50, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (18, 'STE_BOX', 'Kotak teh (70x70x120)', 60, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (19, 'CRASH_LOCK', 'Food box kecil (120x80x180)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (20, 'CRASH_LOCK', 'Food box besar (150x100x220)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (21, 'CRASH_LOCK', 'Kotak nasi (200x120x60)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (22, 'SLEEVE', 'Sleeve kecil (90x50x160)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (23, 'SLEEVE', 'Sleeve sedang (110x60x180)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (24, 'GIFT_BOX', 'Kubus 100 (100x100x100)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (25, 'GIFT_BOX', 'Hampers (150x150x80)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (26, 'GIFT_BOX', 'Parcel besar (200x150x100)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (27, 'MAILER', 'E-comm S (200x140x70)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (28, 'MAILER', 'E-comm A5 (235x180x50)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (29, 'MAILER', 'E-comm A4 (330x250x50)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (30, 'BOX_WITH_LID', 'Shoe box (300x180x110)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (31, 'BOX_WITH_LID', 'Parcel tray (250x200x80)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (32, 'SHALLOW_BOX', 'Dus kue (250x250x70)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (33, 'SHALLOW_BOX', 'Dus pizza (300x300x40)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (34, 'ROUND_BOX', 'Cookie D100 (D100x80)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (35, 'ROUND_BOX', 'Hat box D200 (D200x120)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (36, 'ROUND_BOX', 'Kaleng D80 (D80x60)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (37, 'CYLINDER', 'Tube poster D63x430', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (38, 'CYLINDER', 'Tube kecil D45x90', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (39, 'CYLINDER', 'Tube parfum D40x130', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (40, 'HANG_TAG', 'Tag 80x40', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (41, 'HANG_TAG', 'Tag 50x90', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (42, 'HANG_TAG', 'Tag 60x100', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (43, 'COUNTER_DISPLAY', 'Display kecil (200x150x120)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (44, 'COUNTER_DISPLAY', 'Display sedang (300x200x180)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (45, 'KALENDER_DINDING', '31x48 (A3+, ekonomis)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (46, 'KALENDER_DINDING', '31x54', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (47, 'KALENDER_DINDING', '38x53 (kantor)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (48, 'KALENDER_DINDING', '26x38 (kecil)', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (49, 'KALENDER_DINDING', 'A3 (29,7x42)', 50, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (50, 'KALENDER_DINDING', '44x64 (besar/promo)', 60, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (51, 'KALENDER_DINDING', 'B2 70x50', 70, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (52, 'KALENDER_DINDING', 'Poster A2 1 lembar', 80, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (53, 'KALENDER_DUDUK', 'A5 15x21 (populer)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (54, 'KALENDER_DUDUK', '15x20', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (55, 'KALENDER_DUDUK', '17x23', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (56, 'BROSUR', 'A4 lipat 3 (tri-fold)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (57, 'BROSUR', 'A4 lipat 2 (bi-fold)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (58, 'BROSUR', 'A4 lipat Z', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (59, 'BROSUR', 'A3 lipat 2 (jadi A4)', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (60, 'BROSUR', 'A3 lipat 3', 50, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (61, 'FLYER', 'A5 (14,8x21)', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (62, 'FLYER', 'A6 (10,5x14,8)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (63, 'FLYER', 'DL (9,9x21)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (64, 'FLYER', 'A4 (21x29,7)', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (65, 'POSTER', 'A3 (29,7x42) - poster', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:17:10');
INSERT INTO `m_product_type_size` VALUES (66, 'POSTER', 'A2 (42x59,4)', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (67, 'POSTER', 'A1 (59,4x84,1)', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (68, 'POSTER', '50x70', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (69, 'LABEL_STIKER', 'Kotak 5x5', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (70, 'LABEL_STIKER', 'Kotak 7x7', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (71, 'LABEL_STIKER', 'Kotak 10x10', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (72, 'LABEL_STIKER', 'Label 9x5', 40, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (73, 'LABEL_STIKER', 'Label 10x5', 50, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (74, 'LABEL_STIKER', 'Label 9x6 (botol)', 60, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (75, 'LABEL_STIKER', 'Label 12x8 (pouch)', 70, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (76, 'LABEL_STIKER', 'Bulat D5 (kotak 50x50)', 80, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (77, 'KARTU_NAMA', 'Standar 90x55', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (78, 'KARTU_NAMA', '90x54', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (79, 'BUKU', 'A5 64 hal', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (80, 'BUKU', 'A4 64 hal', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (81, 'BUKU', 'B5 80 hal', 30, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (82, 'AMPLOP_KOP', 'Amplop DL 220x110', 10, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');
INSERT INTO `m_product_type_size` VALUES (83, 'AMPLOP_KOP', 'Kop A4 210x297', 20, 1, '2026-07-17 09:12:40', '2026-07-17 09:12:40');

-- ----------------------------
-- Table structure for m_product_type_size_val
-- ----------------------------
DROP TABLE IF EXISTS `m_product_type_size_val`;
CREATE TABLE `m_product_type_size_val`  (
  `size_val_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK.',
  `size_id` bigint UNSIGNED NOT NULL COMMENT 'FK ke m_prd_type_size.size_id.',
  `column_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Selaras m_prd_type_param.column_name.',
  `value_num` decimal(12, 3) NULL DEFAULT NULL COMMENT 'Nilai utk param_type=NUM.',
  `value_str` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nilai utk param_type=ENUM.',
  PRIMARY KEY (`size_val_id`) USING BTREE,
  UNIQUE INDEX `uq_size_col`(`size_id` ASC, `column_name` ASC) USING BTREE,
  CONSTRAINT `fk_prd_sizeval_size` FOREIGN KEY (`size_id`) REFERENCES `m_product_type_size` (`size_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 511 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail nilai preset ukuran (EAV: column_name -> value).' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of m_product_type_size_val
-- ----------------------------
INSERT INTO `m_product_type_size_val` VALUES (256, 13, 'product_length', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (257, 7, 'product_length', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (258, 1, 'product_length', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (259, 13, 'product_width', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (260, 7, 'product_width', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (261, 1, 'product_width', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (262, 13, 'product_height', 110.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (263, 7, 'product_height', 110.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (264, 1, 'product_height', 110.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (265, 14, 'product_length', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (266, 8, 'product_length', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (267, 2, 'product_length', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (268, 14, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (269, 8, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (270, 2, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (271, 14, 'product_height', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (272, 8, 'product_height', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (273, 2, 'product_height', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (274, 15, 'product_length', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (275, 9, 'product_length', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (276, 3, 'product_length', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (277, 15, 'product_width', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (278, 9, 'product_width', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (279, 3, 'product_width', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (280, 15, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (281, 9, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (282, 3, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (283, 16, 'product_length', 105.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (284, 10, 'product_length', 105.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (285, 4, 'product_length', 105.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (286, 16, 'product_width', 32.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (287, 10, 'product_width', 32.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (288, 4, 'product_width', 32.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (289, 16, 'product_height', 22.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (290, 10, 'product_height', 22.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (291, 4, 'product_height', 22.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (292, 17, 'product_length', 95.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (293, 11, 'product_length', 95.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (294, 5, 'product_length', 95.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (295, 17, 'product_width', 20.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (296, 11, 'product_width', 20.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (297, 5, 'product_width', 20.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (298, 17, 'product_height', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (299, 11, 'product_height', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (300, 5, 'product_height', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (301, 18, 'product_length', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (302, 12, 'product_length', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (303, 6, 'product_length', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (304, 18, 'product_width', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (305, 12, 'product_width', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (306, 6, 'product_width', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (307, 18, 'product_height', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (308, 12, 'product_height', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (309, 6, 'product_height', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (310, 19, 'product_length', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (311, 19, 'product_width', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (312, 19, 'product_height', 180.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (313, 20, 'product_length', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (314, 20, 'product_width', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (315, 20, 'product_height', 220.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (316, 21, 'product_length', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (317, 21, 'product_width', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (318, 21, 'product_height', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (319, 22, 'product_length', 90.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (320, 22, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (321, 22, 'product_height', 160.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (322, 23, 'product_length', 110.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (323, 23, 'product_width', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (324, 23, 'product_height', 180.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (325, 24, 'product_length', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (326, 24, 'product_width', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (327, 24, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (328, 25, 'product_length', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (329, 25, 'product_width', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (330, 25, 'product_height', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (331, 26, 'product_length', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (332, 26, 'product_width', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (333, 26, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (334, 27, 'product_length', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (335, 27, 'product_width', 140.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (336, 27, 'product_height', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (337, 28, 'product_length', 235.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (338, 28, 'product_width', 180.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (339, 28, 'product_height', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (340, 29, 'product_length', 330.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (341, 29, 'product_width', 250.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (342, 29, 'product_height', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (343, 30, 'product_length', 300.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (344, 30, 'product_width', 180.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (345, 30, 'product_height', 110.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (346, 30, 'lid_height', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (347, 31, 'product_length', 250.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (348, 31, 'product_width', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (349, 31, 'product_height', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (350, 31, 'lid_height', 35.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (351, 32, 'product_length', 250.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (352, 32, 'product_width', 250.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (353, 32, 'product_height', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (354, 33, 'product_length', 300.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (355, 33, 'product_width', 300.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (356, 33, 'product_height', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (357, 34, 'diameter', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (358, 34, 'product_height', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (359, 34, 'lid_height', 30.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (360, 35, 'diameter', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (361, 35, 'product_height', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (362, 35, 'lid_height', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (363, 36, 'diameter', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (364, 36, 'product_height', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (365, 36, 'lid_height', 25.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (366, 37, 'diameter', 63.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (367, 37, 'product_height', 430.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (368, 38, 'diameter', 45.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (369, 38, 'product_height', 90.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (370, 39, 'diameter', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (371, 39, 'product_height', 130.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (372, 40, 'product_length', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (373, 40, 'product_width', 40.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (374, 41, 'product_length', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (375, 41, 'product_width', 90.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (376, 42, 'product_length', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (377, 42, 'product_width', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (378, 43, 'product_length', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (379, 43, 'product_width', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (380, 43, 'product_height', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (381, 44, 'product_length', 300.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (382, 44, 'product_width', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (383, 44, 'product_height', 180.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (384, 45, 'product_length', 310.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (385, 45, 'product_width', 480.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (386, 45, 'page_count', 7.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (387, 45, 'binding_type', NULL, 'KLEM_SENG');
INSERT INTO `m_product_type_size_val` VALUES (388, 45, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (389, 46, 'product_length', 310.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (390, 46, 'product_width', 540.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (391, 46, 'page_count', 7.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (392, 46, 'binding_type', NULL, 'KLEM_SENG');
INSERT INTO `m_product_type_size_val` VALUES (393, 46, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (394, 47, 'product_length', 380.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (395, 47, 'product_width', 530.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (396, 47, 'page_count', 7.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (397, 47, 'binding_type', NULL, 'WIRE_O');
INSERT INTO `m_product_type_size_val` VALUES (398, 47, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (399, 48, 'product_length', 260.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (400, 48, 'product_width', 380.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (401, 48, 'page_count', 7.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (402, 48, 'binding_type', NULL, 'KLEM_SENG');
INSERT INTO `m_product_type_size_val` VALUES (403, 48, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (404, 49, 'product_length', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (405, 49, 'product_width', 420.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (406, 49, 'page_count', 7.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (407, 49, 'binding_type', NULL, 'KLEM_SENG');
INSERT INTO `m_product_type_size_val` VALUES (408, 49, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (409, 50, 'product_length', 440.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (410, 50, 'product_width', 640.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (411, 50, 'page_count', 7.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (412, 50, 'binding_type', NULL, 'WIRE_O');
INSERT INTO `m_product_type_size_val` VALUES (413, 50, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (414, 51, 'product_length', 700.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (415, 51, 'product_width', 500.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (416, 51, 'page_count', 7.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (417, 51, 'binding_type', NULL, 'WIRE_O');
INSERT INTO `m_product_type_size_val` VALUES (418, 51, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (419, 52, 'product_length', 420.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (420, 52, 'product_width', 594.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (421, 52, 'page_count', 1.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (422, 52, 'binding_type', NULL, 'NONE');
INSERT INTO `m_product_type_size_val` VALUES (423, 52, 'hanger_type', NULL, 'EYELET');
INSERT INTO `m_product_type_size_val` VALUES (424, 53, 'product_length', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (425, 53, 'product_width', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (426, 53, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (427, 53, 'page_count', 13.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (428, 53, 'binding_type', NULL, 'SPIRAL');
INSERT INTO `m_product_type_size_val` VALUES (429, 54, 'product_length', 150.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (430, 54, 'product_width', 200.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (431, 54, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (432, 54, 'page_count', 13.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (433, 54, 'binding_type', NULL, 'SPIRAL');
INSERT INTO `m_product_type_size_val` VALUES (434, 55, 'product_length', 170.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (435, 55, 'product_width', 230.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (436, 55, 'product_height', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (437, 55, 'page_count', 13.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (438, 55, 'binding_type', NULL, 'SPIRAL');
INSERT INTO `m_product_type_size_val` VALUES (439, 56, 'product_length', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (440, 56, 'product_width', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (441, 56, 'fold_count', 2.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (442, 56, 'fold_type', NULL, 'TRI');
INSERT INTO `m_product_type_size_val` VALUES (443, 57, 'product_length', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (444, 57, 'product_width', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (445, 57, 'fold_count', 1.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (446, 57, 'fold_type', NULL, 'HALF');
INSERT INTO `m_product_type_size_val` VALUES (447, 58, 'product_length', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (448, 58, 'product_width', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (449, 58, 'fold_count', 2.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (450, 58, 'fold_type', NULL, 'Z');
INSERT INTO `m_product_type_size_val` VALUES (451, 59, 'product_length', 420.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (452, 59, 'product_width', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (453, 59, 'fold_count', 1.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (454, 59, 'fold_type', NULL, 'HALF');
INSERT INTO `m_product_type_size_val` VALUES (455, 60, 'product_length', 420.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (456, 60, 'product_width', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (457, 60, 'fold_count', 2.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (458, 60, 'fold_type', NULL, 'TRI');
INSERT INTO `m_product_type_size_val` VALUES (459, 61, 'product_length', 148.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (460, 61, 'product_width', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (461, 62, 'product_length', 105.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (462, 62, 'product_width', 148.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (463, 63, 'product_length', 99.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (464, 63, 'product_width', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (465, 64, 'product_length', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (466, 64, 'product_width', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (467, 65, 'product_length', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (468, 65, 'product_width', 420.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (469, 66, 'product_length', 420.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (470, 66, 'product_width', 594.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (471, 67, 'product_length', 594.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (472, 67, 'product_width', 841.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (473, 68, 'product_length', 500.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (474, 68, 'product_width', 700.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (475, 69, 'product_length', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (476, 69, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (477, 70, 'product_length', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (478, 70, 'product_width', 70.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (479, 71, 'product_length', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (480, 71, 'product_width', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (481, 72, 'product_length', 90.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (482, 72, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (483, 73, 'product_length', 100.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (484, 73, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (485, 74, 'product_length', 90.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (486, 74, 'product_width', 60.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (487, 75, 'product_length', 120.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (488, 75, 'product_width', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (489, 76, 'product_length', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (490, 76, 'product_width', 50.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (491, 77, 'product_length', 90.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (492, 77, 'product_width', 55.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (493, 78, 'product_length', 90.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (494, 78, 'product_width', 54.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (495, 79, 'product_length', 148.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (496, 79, 'product_width', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (497, 79, 'page_count', 64.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (498, 79, 'binding_type', NULL, 'PERFECT');
INSERT INTO `m_product_type_size_val` VALUES (499, 80, 'product_length', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (500, 80, 'product_width', 297.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (501, 80, 'page_count', 64.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (502, 80, 'binding_type', NULL, 'SADDLE');
INSERT INTO `m_product_type_size_val` VALUES (503, 81, 'product_length', 176.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (504, 81, 'product_width', 250.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (505, 81, 'page_count', 80.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (506, 81, 'binding_type', NULL, 'PERFECT');
INSERT INTO `m_product_type_size_val` VALUES (507, 82, 'product_length', 220.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (508, 82, 'product_width', 110.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (509, 83, 'product_length', 210.000, NULL);
INSERT INTO `m_product_type_size_val` VALUES (510, 83, 'product_width', 297.000, NULL);

-- ----------------------------
-- Table structure for m_ref_param_option
-- ----------------------------
DROP TABLE IF EXISTS `m_ref_param_option`;
CREATE TABLE `m_ref_param_option`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `column_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'fold_type, binding_type, hanger_type…',
  `option_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'KLEM_SENG',
  `option_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'Klem Seng',
  `sort_order` smallint NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_col_code`(`column_name` ASC, `option_code` ASC) USING BTREE,
  INDEX `ix_col_active`(`column_name` ASC, `is_active` ASC, `sort_order` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 27 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of m_ref_param_option
-- ----------------------------
INSERT INTO `m_ref_param_option` VALUES (1, 'material_role', 'PAPER', 'Paper', 10, 1);
INSERT INTO `m_ref_param_option` VALUES (2, 'material_role', 'SF', 'SF (Single Face)', 20, 1);
INSERT INTO `m_ref_param_option` VALUES (3, 'material_role', 'SHEET', 'Sheet', 30, 1);
INSERT INTO `m_ref_param_option` VALUES (4, 'material_role', 'LINER', 'Liner', 40, 1);
INSERT INTO `m_ref_param_option` VALUES (5, 'material_role', 'KRAFT', 'Kraft', 50, 1);
INSERT INTO `m_ref_param_option` VALUES (6, 'material_role', 'OTHER', 'Lainnya', 99, 1);
INSERT INTO `m_ref_param_option` VALUES (7, 'color_notation', '4/4', '4/4 (full 2 sisi)', 10, 1);
INSERT INTO `m_ref_param_option` VALUES (8, 'color_notation', '4/1', '4/1', 20, 1);
INSERT INTO `m_ref_param_option` VALUES (9, 'color_notation', '4/0', '4/0 (1 sisi full)', 30, 1);
INSERT INTO `m_ref_param_option` VALUES (10, 'color_notation', '2/0', '2/0', 40, 1);
INSERT INTO `m_ref_param_option` VALUES (11, 'color_notation', '1/1', '1/1', 50, 1);
INSERT INTO `m_ref_param_option` VALUES (12, 'color_notation', '1/0', '1/0 (1 warna 1 sisi)', 60, 1);
INSERT INTO `m_ref_param_option` VALUES (13, 'coating_side', 'SIDE_A', 'Depan (Side A)', 10, 1);
INSERT INTO `m_ref_param_option` VALUES (14, 'coating_side', 'SIDE_B', 'Belakang (Side B)', 20, 1);
INSERT INTO `m_ref_param_option` VALUES (15, 'coating_side', 'BOTH', 'Dua Sisi', 30, 1);
INSERT INTO `m_ref_param_option` VALUES (16, 'coating_coverage', 'FULL', 'Full (seluruh area)', 10, 1);
INSERT INTO `m_ref_param_option` VALUES (17, 'coating_coverage', 'SPOT', 'Spot (titik)', 20, 1);
INSERT INTO `m_ref_param_option` VALUES (18, 'coating_coverage', 'AREA', 'Area (sebagian)', 30, 1);
INSERT INTO `m_ref_param_option` VALUES (19, 'flute_type', 'B', 'B Flute', 10, 1);
INSERT INTO `m_ref_param_option` VALUES (20, 'flute_type', 'E', 'E Flute', 20, 1);
INSERT INTO `m_ref_param_option` VALUES (21, 'flute_type', 'F', 'F Flute', 30, 1);
INSERT INTO `m_ref_param_option` VALUES (22, 'flute_type', 'BE', 'BE (Double)', 40, 1);
INSERT INTO `m_ref_param_option` VALUES (23, 'ppn_exempt_reason', 'BONDED', 'Kawasan Berikat', 10, 1);
INSERT INTO `m_ref_param_option` VALUES (24, 'ppn_exempt_reason', 'EXPORT', 'Ekspor', 20, 1);
INSERT INTO `m_ref_param_option` VALUES (25, 'ppn_exempt_reason', 'GOVT', 'Fasilitas Pemerintah', 30, 1);
INSERT INTO `m_ref_param_option` VALUES (26, 'ppn_exempt_reason', 'OTHER', 'Lainnya', 99, 1);

-- ----------------------------
-- Table structure for mat_raw
-- ----------------------------
DROP TABLE IF EXISTS `mat_raw`;
CREATE TABLE `mat_raw`  (
  `id_material` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `material_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `item_type` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'MATERIAL' COMMENT 'MATERIAL|SERVICE|FREE_SAMPLE',
  `category1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `brand` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `gram_weight` int UNSIGNED NULL DEFAULT 0 COMMENT 'Gramatur',
  `cm_length` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_width` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `mm_thickness` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `specification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `uom_stock` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `uom_purchase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `uom_usage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `conversion_to_stock` decimal(20, 6) UNSIGNED NULL DEFAULT 1.000000,
  `conversion_to_usage` decimal(20, 6) UNSIGNED NULL DEFAULT NULL,
  `purchasable` tinyint(1) NOT NULL DEFAULT 1,
  `avg_unitprice` decimal(30, 5) NULL DEFAULT NULL COMMENT 'Harga perolehan rata-rata',
  `last_unitprice` decimal(30, 5) NULL DEFAULT NULL,
  `min_stock` decimal(20, 4) UNSIGNED NULL DEFAULT 0.0000,
  `max_stock` decimal(20, 4) UNSIGNED NULL DEFAULT NULL,
  `cur_stock` decimal(20, 4) NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_material`) USING BTREE,
  UNIQUE INDEX `uq_material_code`(`material_code` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master bahan baku (di-refer costing & part_material)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of mat_raw
-- ----------------------------

-- ----------------------------
-- Table structure for prod_category
-- ----------------------------
DROP TABLE IF EXISTS `prod_category`;
CREATE TABLE `prod_category`  (
  `id_category` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_category`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Kategori produk' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of prod_category
-- ----------------------------

-- ----------------------------
-- Table structure for prod_dieline_template
-- ----------------------------
DROP TABLE IF EXISTS `prod_dieline_template`;
CREATE TABLE `prod_dieline_template`  (
  `id_dieline` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `dieline_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik pola, mis. RTE-TUCK-01',
  `dieline_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama pola, mis. Reverse Tuck End',
  `box_style` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'REVERSE_TUCK|STRAIGHT_TUCK|SLEEVE|TRAY|...',
  `ecma_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kode standar ECMA (bila ada)',
  `svg_generator` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kunci engine render (default TMDieline)',
  `dieline_spec` json NOT NULL COMMENT 'DSL: {canvas, vars, flat{w,h}, elements[]}. Sumber gambar SVG',
  `preview_sample` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Path SVG/PNG statis fallback (opsional)',
  `spec_version` smallint NOT NULL DEFAULT 1 COMMENT 'Versi skema DSL (kompatibilitas engine)',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_dieline`) USING BTREE,
  UNIQUE INDEX `uq_dieline_code`(`dieline_code` ASC) USING BTREE,
  INDEX `idx_dieline_style`(`box_style` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Library pola dieline (F19). Tambah pola baru = INSERT dieline_spec, engine generik' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prod_dieline_template
-- ----------------------------

-- ----------------------------
-- Table structure for prod_part
-- ----------------------------
DROP TABLE IF EXISTS `prod_part`;
CREATE TABLE `prod_part`  (
  `id_part` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> prod_product',
  `part_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `part_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `part_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'DC|DC+SF|DC+SHEET|KRAFT|SHEET',
  `qty_per_sku` decimal(10, 4) UNSIGNED NOT NULL DEFAULT 1.0000,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `uom_stock` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `cfg_color` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '4/4, 4/0, dll',
  `cfg_ink` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `waste_default` decimal(5, 2) NULL DEFAULT NULL COMMENT 'Waste %',
  `spoilage_default` decimal(5, 2) NULL DEFAULT NULL COMMENT 'Spoilage %',
  `lc_per_plano` decimal(6, 2) NULL DEFAULT NULL COMMENT 'Out per plano baku',
  `image_per_lc` decimal(5, 2) NULL DEFAULT NULL COMMENT 'UP baku',
  `printsize_p` decimal(8, 2) NULL DEFAULT NULL,
  `printsize_l` decimal(8, 2) NULL DEFAULT NULL,
  `dieline_ref` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ecma_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `cm_length` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_width` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_height` decimal(12, 4) NULL DEFAULT NULL,
  `min_stock` decimal(20, 4) UNSIGNED NULL DEFAULT 0.0000,
  `max_stock` decimal(20, 4) UNSIGNED NULL DEFAULT NULL,
  `cur_stock` decimal(20, 4) NULL DEFAULT NULL,
  `last_design_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis iqp inquiry design terakhir jadi baku',
  `last_costing_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis iqp_costing terakhir jadi baku',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_part`) USING BTREE,
  INDEX `idx_part_product`(`product_id` ASC) USING BTREE,
  CONSTRAINT `fk_part_product` FOREIGN KEY (`product_id`) REFERENCES `prod_product` (`id_product`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Part/komponen produk baku' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of prod_part
-- ----------------------------

-- ----------------------------
-- Table structure for prod_part_material
-- ----------------------------
DROP TABLE IF EXISTS `prod_part_material`;
CREATE TABLE `prod_part_material`  (
  `id_part_material` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> prod_part',
  `layer_seq` int NOT NULL DEFAULT 1 COMMENT 'Urutan lapisan',
  `material_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> mat_raw',
  `rawmat_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'utama|sekunder|SF|flute',
  `gram_weight` decimal(8, 2) NULL DEFAULT NULL,
  `flute_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'B/F, E/F...',
  `size_p` decimal(8, 2) NULL DEFAULT NULL,
  `size_l` decimal(8, 2) NULL DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_material`) USING BTREE,
  INDEX `idx_pmat_part`(`part_id` ASC) USING BTREE,
  INDEX `idx_pmat_material`(`material_id` ASC) USING BTREE,
  CONSTRAINT `fk_pmat_material` FOREIGN KEY (`material_id`) REFERENCES `mat_raw` (`id_material`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pmat_part` FOREIGN KEY (`part_id`) REFERENCES `prod_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Layer bahan per part baku (multi-layer)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of prod_part_material
-- ----------------------------

-- ----------------------------
-- Table structure for prod_part_process
-- ----------------------------
DROP TABLE IF EXISTS `prod_part_process`;
CREATE TABLE `prod_part_process`  (
  `id_part_process` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> prod_part',
  `branch_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'MAIN' COMMENT 'MAIN|L|R|BODY|LID',
  `step_seq` int NOT NULL COMMENT 'Urutan dalam branch',
  `flow_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'LINEAR' COMMENT 'LINEAR|SPLIT|MERGE',
  `process_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_process',
  `process_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Snapshot nama',
  `machine_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis mesin (menyusul)',
  `layer_ref` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_part_material (layer diproses)',
  `qty_factor` decimal(5, 3) NOT NULL DEFAULT 1.000 COMMENT 'Porsi qty (0.5=belah plano)',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_process`) USING BTREE,
  INDEX `idx_pproc_part`(`part_id` ASC) USING BTREE,
  INDEX `idx_pproc_process`(`process_id` ASC) USING BTREE,
  CONSTRAINT `fk_pproc_part` FOREIGN KEY (`part_id`) REFERENCES `prod_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pproc_process` FOREIGN KEY (`process_id`) REFERENCES `prod_process` (`id_process`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Routing per part baku (branch/flow SPLIT/MERGE)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of prod_part_process
-- ----------------------------

-- ----------------------------
-- Table structure for prod_process
-- ----------------------------
DROP TABLE IF EXISTS `prod_process`;
CREATE TABLE `prod_process`  (
  `id_process` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `gantt_color` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Warna di gantt chart',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_process`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master proses produksi' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of prod_process
-- ----------------------------

-- ----------------------------
-- Table structure for prod_product
-- ----------------------------
DROP TABLE IF EXISTS `prod_product`;
CREATE TABLE `prod_product`  (
  `id_product` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `customer_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> cust_customer',
  `category_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_category',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `uom_stock` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `uom_sales` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `conversion_to_stock` decimal(20, 6) UNSIGNED NULL DEFAULT 1.000000,
  `qty_limit_min` decimal(12, 2) UNSIGNED NULL DEFAULT 0.00,
  `qty_limit_max` decimal(12, 2) UNSIGNED NULL DEFAULT 0.00,
  `cm_length` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_width` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_height` decimal(12, 4) UNSIGNED NULL DEFAULT 0.0000,
  `min_stock` decimal(20, 4) UNSIGNED NULL DEFAULT 0.0000,
  `max_stock` decimal(20, 4) UNSIGNED NULL DEFAULT NULL,
  `cur_stock` decimal(20, 4) NULL DEFAULT 0.0000,
  `default_selling_price` decimal(10, 2) UNSIGNED NULL DEFAULT 0.00,
  `minimum_selling_price` decimal(10, 2) UNSIGNED NULL DEFAULT 0.00,
  `is_confirmed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ex stat_confirm',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_product`) USING BTREE,
  UNIQUE INDEX `uq_product_code`(`product_code` ASC) USING BTREE,
  INDEX `idx_prod_customer`(`customer_id` ASC) USING BTREE,
  INDEX `idx_prod_category`(`category_id` ASC) USING BTREE,
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `prod_category` (`id_category`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_customer` FOREIGN KEY (`customer_id`) REFERENCES `cust_customer` (`id_customer`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header produk baku. Sumber snapshot RECALC/REVISE' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of prod_product
-- ----------------------------

-- ----------------------------
-- Table structure for prod_type
-- ----------------------------
DROP TABLE IF EXISTS `prod_type`;
CREATE TABLE `prod_type`  (
  `type_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PK kode tipe produk',
  `type_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'PACKAGING|COMMERCIAL_PRINT|DISPLAY|MISC',
  `sub_category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'TUCK_BOX|TRAY|CYLINDER|FLAT_PRINT...',
  `box_style` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'REVERSE_TUCK|STRAIGHT_TUCK|SLEEVE...',
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `svg_generator` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kunci generator SVG dieline',
  `dieline_sample` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'SVG statis fallback',
  `dieline_spec` json NULL COMMENT 'DSL deklaratif dieline {vars/elements/flat}',
  `dieline_template_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_dieline_template (bila pakai pola dari library, bukan spec inline)',
  `ecma_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `part_type_default` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `material_filter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `has_multi_part` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 999,
  `spec_version` smallint NOT NULL DEFAULT 1,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`type_code`) USING BTREE,
  INDEX `idx_ptype_dieline_tpl`(`dieline_template_id` ASC) USING BTREE,
  CONSTRAINT `fk_ptype_dieline_tpl` FOREIGN KEY (`dieline_template_id`) REFERENCES `prod_dieline_template` (`id_dieline`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tipe produk + dieline DSL (sumber dieline_spec ke inquiry)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of prod_type
-- ----------------------------
INSERT INTO `prod_type` VALUES ('AMPLOP_KOP', 'Amplop & Kop Surat', 'PACKAGING', 'FLAT_PRINT', 'REVERSE_TUCK,', 'Cuplikan layar 2026-07-17 160408(1).png', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'SHEET', NULL, 1, 90, 1, 'Stationery kantor.', 1, '2026-07-16 00:00:00', '2026-07-17 00:00:00', NULL, NULL);
INSERT INTO `prod_type` VALUES ('BAG', 'Paper Bag', 'PACKAGING', 'TUCK_BOX', 'REVERSE_TUCK,', 'Cuplikan layar 2026-07-17 162039.png', NULL, NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'KRAFT', NULL, 0, 60, 1, 'Tas kertas, bisa dilipat rata.', 0, '2026-07-16 00:00:00', '2026-07-17 00:00:00', NULL, NULL);
INSERT INTO `prod_type` VALUES ('BOX_WITH_LID', 'Box with Lid', 'PACKAGING', 'TRAY', NULL, '/img/producttype/BOX_WITH_LID.jpg', 'TRAY', NULL, '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, NULL, 'DC', NULL, 1, 21, 1, 'Base tray + lid terpisah. Shoe box.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('BROSUR', 'Brosur / Leaflet', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/BROSUR.jpg', 'FLAT_FOLD', NULL, '{\"gen\": \"FLAT_FOLD\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"n\": \"fold_count\", \"t\": \"foldlines\", \"w\": \"A\"}]}', NULL, NULL, 'SHEET', NULL, 0, 82, 1, 'Lembaran lipat. Half/tri/Z/gate fold.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('BUKU', 'Buku / Katalog', 'COMMERCIAL_PRINT', 'BOOK', NULL, '/img/producttype/BUKU.jpg', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'SHEET', NULL, 1, 85, 1, 'Cover + isi. Saddle/perfect binding.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CAKE_SLICE_BOX', 'Cake Slice Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/CAKE_SLICE_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 55, 1, 'Box segitiga potongan kue. Tanpa lem.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CARD_BOX', 'Card Box (Tuck End)', 'FOLDING_CARTON', 'Straight Tuck', 'STE', NULL, 'TUCK_END', NULL, '{\"gen\": \"TUCK_END\", \"flat\": {\"h\": \"max(TT,DF)+C+max(BT,DF)+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+max(BT,DF)\", \"W\": \"G+2*A+2*B\", \"bR\": \"max(BT,DF)\", \"ga\": \"min(8, C/10, G)\", \"tR\": \"max(TT,DF)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"style\": \"REVERSE_TUCK\", \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x2\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"BT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 'A20.20.01.01', 'DC', NULL, 0, 1, 1, 'Box lipat tuck atas-bawah. Dieline live dari dimensi P×L×T.', 1, '2026-08-08 04:09:45', '2026-07-17 09:09:41', NULL, NULL);
INSERT INTO `prod_type` VALUES ('COFFIN', 'Coffin Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/COFFIN.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 56, 1, 'Box peti. Halloween/novelty.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CONE_MITERED', 'Cone (Mitered)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/CONE_MITERED.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 34, 1, 'Kerucut dipotong 2 bidang miring.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CONE_TRUNCATED', 'Cone (Truncated)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/CONE_TRUNCATED.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 33, 1, 'Kerucut terpotong. Top diameter 0 = kerucut penuh.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('COUNTER_DISPLAY', 'Counter Display', 'DISPLAY', 'POP', NULL, '/img/producttype/COUNTER_DISPLAY.jpg', 'TRAY', NULL, '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, NULL, 'DC+SF', NULL, 0, 70, 1, 'Tray sisi miring untuk display.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CRASH_LOCK', 'Auto / Crash Lock Bottom', 'PACKAGING', 'TUCK_BOX', 'AUTO_LOCK', '/img/producttype/CRASH_LOCK.jpg', 'CRASH_LOCK', NULL, '{\"gen\": \"CRASH_LOCK\", \"flat\": {\"h\": \"max(TT,DF)+C+B*0.75+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+B*0.75\", \"W\": \"G+2*A+2*B\", \"ga\": \"min(8, C/10, G)\", \"lk\": \"B*0.5\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"lk\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 'A71.51.10.00', 'DC', NULL, 0, 13, 1, 'Bottom auto-lock, cepat saat packing.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CURVED_BOX', 'Curved Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/CURVED_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 50, 1, 'Sisi melengkung. Parfum, perhiasan.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CUSTOM', 'Custom / Lainnya', 'MISC', 'CUSTOM', NULL, '/img/producttype/CUSTOM.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 999, 1, 'Tipe tidak terdaftar. Isi manual, jelaskan di spec_notes.', 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38', NULL, NULL);
INSERT INTO `prod_type` VALUES ('CYLINDER', 'Cylinder (Mitered)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/CYLINDER.jpg', 'CYLINDER', NULL, '{\"gen\": \"CYLINDER\", \"flat\": {\"h\": \"C+2*P\", \"w\": \"PI*D+2*P\"}, \"vars\": {\"H\": \"max(C,D)\", \"W\": \"PI*D+max(20,D*0.3)+D\", \"r\": \"D/2\", \"bh\": \"C\", \"cx\": \"PI*D+max(20,D*0.3)+D/2\", \"cy\": \"C/2\", \"cir\": \"PI*D\", \"gap\": \"max(20, D*0.3)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"bh\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"cir\", \"x\": \"0\", \"y\": \"0\"}, {\"k\": \"CUT\", \"r\": \"r\", \"t\": \"circle\", \"cx\": \"cx\", \"cy\": \"cy\"}, {\"s\": \"tutup\", \"t\": \"text\", \"x\": \"cx\", \"y\": \"cy+r+16\"}]}', NULL, NULL, 'DC', NULL, 0, 31, 1, 'Tube/pipa, opsional dipotong miring.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('EGG_SHAPED_BOX', 'Egg Shaped Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/EGG_SHAPED_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 52, 1, 'Box telur + tutup. Easter.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('ELLIPTICAL_BOX', 'Elliptical Box', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/ELLIPTICAL_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 32, 1, 'Box oval + tutup.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('ENVELOPE', 'Envelope', 'PACKAGING', 'BAG', NULL, '/img/producttype/ENVELOPE.jpg', NULL, NULL, NULL, NULL, NULL, 'SHEET', NULL, 0, 61, 1, 'Amplop surat klasik.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('EXPLODING_BOX', 'Exploding Box', 'PACKAGING', 'TRAY', NULL, '/img/producttype/EXPLODING_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 26, 1, 'Tray + lid, sisi terbuka saat lid dilepas.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('FLOOR_DISPLAY', 'Floor Display', 'DISPLAY', 'POP', NULL, '/img/producttype/FLOOR_DISPLAY.jpg', NULL, NULL, NULL, NULL, NULL, 'DC+SF', NULL, 1, 71, 1, 'Display lantai bertingkat.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('FLYER', 'Flyer', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/FLYER.jpg', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'SHEET', NULL, 0, 83, 1, 'Lembaran tanpa lipat.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('GEM_STONE_BOX', 'Gem Stone Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/GEM_STONE_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 44, 1, 'Dua piramida poligon disatukan.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('GIFT_BOX', 'Gift Box', 'PACKAGING', 'TUCK_BOX', NULL, 'GIFT_BOX.jpg', 'TRAY', NULL, '{\"flat\": {\"h\": \"H + 2*P\", \"w\": \"W + 2*P\"}, \"vars\": {\"H\": \"max(6, top_tuck_flap) + C + (C + A*0.9)\", \"W\": \"G + 2*A + 2*B\", \"SH\": \"max(3, A*0.14)\", \"TH\": \"max(6, thumb_hole_width)\", \"TT\": \"max(6, top_tuck_flap)\", \"x1\": \"G\", \"x2\": \"G + A\", \"x3\": \"G + A + B\", \"x4\": \"G + 2*A + B\", \"x5\": \"G + 2*A + 2*B\", \"yB\": \"max(6, top_tuck_flap) + C\", \"yK\": \"max(6, top_tuck_flap) + C + (C + A*0.9)\", \"yT\": \"max(6, top_tuck_flap)\", \"KAKI\": \"C + A*0.9\", \"mid3\": \"G + A + B/2\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"g\": \"G*0.6\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1 + SH\", \"b\": \"x2 - SH\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1 + SH\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x2 - SH\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x3 + SH\", \"b\": \"x4 - SH\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3 + SH\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x4 - SH\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x2\", \"b\": \"mid3 - TH/2\", \"h\": \"TT*0.75\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"mid3 + TH/2\", \"b\": \"x3\", \"h\": \"TT*0.75\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"r\": \"TH/2\", \"t\": \"circle\", \"cx\": \"mid3\", \"cy\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"TT*0.75\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yB\", \"y2\": \"yK - TH/2\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yB\", \"y2\": \"yK - TH/2\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"TH/2\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yK - TH/2\", \"dir\": \"down\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x3\", \"y1\": \"yB + C\", \"y2\": \"yB + C\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x3\", \"y1\": \"yK - A*0.28\", \"y2\": \"yK - A*0.28\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"TT*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"TT*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}]}', NULL, NULL, 'DC', NULL, 0, 15, 1, 'Box hadiah. Net template lebih hemat bahan.', 1, '2026-07-16 14:29:38', '2026-07-22 19:04:50', NULL, NULL);
INSERT INTO `prod_type` VALUES ('HANG_TAG', 'Hang Tag', 'DISPLAY', 'POP', NULL, '/img/producttype/HANG_TAG.jpg', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'DC', NULL, 0, 73, 1, 'Label gantung produk.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('HEART_SHAPED_BOX', 'Heart Shaped Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/HEART_SHAPED_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 51, 1, 'Box hati + tutup. Valentine.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('KALENDER_DINDING', 'Kalender Dinding', 'COMMERCIAL_PRINT', 'CALENDAR', NULL, '/img/producttype/KALENDER_DINDING.jpg', 'FLAT_CAL', NULL, '{\"gen\": \"FLAT_CAL\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\", \"nh\": \"max(5, A/50)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}, {\"k\": \"HOLE\", \"n\": \"nh\", \"r\": \"1.8\", \"t\": \"holes\", \"w\": \"A\", \"y\": \"7\"}, {\"k\": \"HOLE\", \"r\": \"3\", \"t\": \"circle\", \"cx\": \"A/2\", \"cy\": \"4\"}]}', NULL, NULL, 'SHEET', NULL, 1, 80, 1, 'Kalender gantung. Jilid spiral/wire-o + eyelet.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('KALENDER_DUDUK', 'Kalender Duduk (Meja)', 'COMMERCIAL_PRINT', 'CALENDAR', NULL, '/img/producttype/KALENDER_DUDUK.jpg', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'DC', NULL, 1, 81, 1, 'Kalender meja + kaki penyangga.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('KARTU_NAMA', 'Kartu Nama', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/KARTU_NAMA.jpg', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'DC', NULL, 0, 89, 1, 'Kartu nama.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('LABEL_STIKER', 'Label / Stiker', 'COMMERCIAL_PRINT', 'LABEL', NULL, '/img/producttype/LABEL_STIKER.jpg', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'SHEET', NULL, 0, 88, 1, 'Stiker die-cut / roll.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('MAILER', 'Mailer', 'PACKAGING', 'TUCK_BOX', NULL, '/img/producttype/MAILER.jpg', 'TRAY', NULL, '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, NULL, 'DC+SF', NULL, 0, 17, 1, 'Amplop tebal. E-commerce.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('MAJALAH', 'Majalah', 'COMMERCIAL_PRINT', 'BOOK', NULL, '/img/producttype/MAJALAH.jpg', NULL, NULL, NULL, NULL, NULL, 'SHEET', NULL, 1, 86, 1, 'Cover + isi. Saddle stitch.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('MATCH_BOX', 'Match Box (Tray & Sleeve)', 'PACKAGING', 'TUCK_BOX', NULL, '/img/producttype/MATCH_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 16, 1, 'Tray + sleeve. 2 part.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('MILK_CARTON', 'Milk Carton', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/MILK_CARTON.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 36, 1, 'Tube dengan bukaan gusset.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('MULTI_SHEET_BOX', 'Multi Sheet Box', 'PACKAGING', 'TRAY', NULL, '/img/producttype/MULTI_SHEET_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 24, 1, 'Box besar dari 6 lembar terpisah.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('NESTABLE_TRAY', 'Nestable Tray', 'PACKAGING', 'TRAY', NULL, '/img/producttype/NESTABLE_TRAY.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 22, 1, 'Tray dengan draft angle, bisa ditumpuk.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('NOTA_INVOICE', 'Nota / Invoice (NCR)', 'COMMERCIAL_PRINT', 'BOOK', NULL, '/img/producttype/NOTA.jpg', NULL, NULL, NULL, NULL, NULL, 'SHEET', NULL, 1, 87, 1, 'Nota rangkap NCR.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('PARALLELEPIPED', 'Parallelepiped', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/PARALLELEPIPED.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 46, 1, 'Box skew 6 jajaran genjang. Bisa jadi dadu.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('PASSEPARTOUT', 'Passepartout', 'PACKAGING', 'TRAY', NULL, '/img/producttype/PASSEPARTOUT.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 25, 1, 'Tray dinding ganda tanpa lem. Photo frame.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('PILLOW_PACK', 'Pillow Pack', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/PILLOW_PACK.jpg', 'PILLOW', NULL, NULL, NULL, NULL, 'DC', NULL, 0, 57, 1, 'Bantal, cap melengkung. Voucher, perhiasan.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('PLATONIC_SOLIDS', 'Platonic Solids', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/PLATONIC_SOLIDS.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 45, 1, 'Tetra/Hexa/Octa/Dodeca/Icosahedron.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('POLYGONAL_BOX', 'Polygonal Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/POLYGONAL_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 40, 1, 'Box prismatik, tutup terintegrasi.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('POLYGONAL_BOX_LID', 'Polygonal Box with Lid', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/POLYGONAL_BOX_LID.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 41, 1, 'Box prismatik + tutup terpisah.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('POLYGONAL_PYRAMID', 'Polygonal Pyramid', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/POLYGONAL_PYRAMID.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 42, 1, 'Piramida alas poligon.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('POSTER', 'Poster', 'COMMERCIAL_PRINT', 'FLAT_PRINT', NULL, '/img/producttype/POSTER.jpg', 'FLAT', NULL, '{\"gen\": \"FLAT\", \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}]}', NULL, NULL, 'SHEET', NULL, 0, 84, 1, 'Cetak besar 1 lembar.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('PYRAMID_BOX', 'Pyramid Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/PYRAMID_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 43, 1, 'Box piramida, alas jadi tutup.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('RHOMBUS_BOX', 'Rhombus Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/RHOMBUS_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 48, 1, 'Box belah ketupat + tutup.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('ROUND_BOX', 'Round Box (Hat Box)', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/ROUND_BOX.jpg', 'CYLINDER', NULL, '{\"gen\": \"CYLINDER\", \"flat\": {\"h\": \"C+LH+2*P\", \"w\": \"PI*D+2*P\"}, \"vars\": {\"H\": \"max(C+LH,D)\", \"W\": \"PI*D+max(20,D*0.3)+D\", \"r\": \"D/2\", \"bh\": \"C+LH\", \"cx\": \"PI*D+max(20,D*0.3)+D/2\", \"cy\": \"(C+LH)/2\", \"cir\": \"PI*D\", \"gap\": \"max(20, D*0.3)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"bh\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"cir\", \"x\": \"0\", \"y\": \"0\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"0\", \"x2\": \"cir\", \"y1\": \"C\", \"y2\": \"C\"}, {\"k\": \"CUT\", \"r\": \"r\", \"t\": \"circle\", \"cx\": \"cx\", \"cy\": \"cy\"}, {\"s\": \"tutup\", \"t\": \"text\", \"x\": \"cx\", \"y\": \"cy+r+16\"}]}', NULL, NULL, 'DC', NULL, 1, 30, 1, 'Box silinder + tutup. Cookie box.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('RTE_BOX', 'Reverse Tuck End (RTE)', 'PACKAGING', 'TUCK_BOX', 'REVERSE_TUCK', '/img/producttype/RTE_BOX.jpg', 'TUCK_END', NULL, '{\"gen\": \"TUCK_END\", \"flat\": {\"h\": \"max(TT,DF)+C+max(BT,DF)+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+max(BT,DF)\", \"W\": \"G+2*A+2*B\", \"bR\": \"max(BT,DF)\", \"ga\": \"min(8, C/10, G)\", \"tR\": \"max(TT,DF)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"style\": \"REVERSE_TUCK\", \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x2\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"BT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 'A60.20.00.03', 'DC', NULL, 0, 11, 1, 'Tuck atas-bawah arah berlawanan. Paling ekonomis.', 1, '2026-07-16 14:29:38', '2026-07-17 09:09:41', NULL, NULL);
INSERT INTO `prod_type` VALUES ('SHALLOW_BOX', 'Shallow Box', 'PACKAGING', 'TRAY', NULL, '/img/producttype/SHALLOW_BOX.jpg', 'TRAY', NULL, '{\"gen\": \"TRAY\", \"flat\": {\"h\": \"B+2*C+2*P\", \"w\": \"A+2*C+2*P\"}, \"vars\": {\"H\": \"B+2*C\", \"W\": \"A+2*C\", \"ox\": \"C\", \"oy\": \"C\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CREASE\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"0\"}, {\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"ox\", \"y\": \"oy+B\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"0\", \"y\": \"oy\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"C\", \"x\": \"ox+A\", \"y\": \"oy\"}]}', NULL, NULL, 'DC', NULL, 1, 20, 1, 'Box tinggi rendah. Tart, pizza.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('SLEEVE', 'Sleeve', 'PACKAGING', 'TUCK_BOX', 'SLEEVE', '/img/producttype/SLEEVE.jpg', 'SLEEVE', NULL, '{\"gen\": \"SLEEVE\", \"flat\": {\"h\": \"C+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"C\", \"W\": \"G+2*A+2*B\", \"ga\": \"min(8, C/10, G)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"C\", \"yT\": \"0\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 'A20.00.00.00', 'DC', NULL, 0, 14, 1, 'Selongsong tanpa tutup. Sering jadi part tambahan.', 1, '2026-07-16 14:29:38', '2026-07-17 09:59:31', NULL, NULL);
INSERT INTO `prod_type` VALUES ('SOMA_CUBE', 'Soma Cube', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/SOMA_CUBE.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 49, 1, '7 polikubus. Puzzle.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('SPHERE', 'Sphere', 'PACKAGING', 'CYLINDER', NULL, '/img/producttype/SPHERE.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 35, 1, 'Bola dari segmen berulang.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('STAR_BOX', 'Star Box', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/STAR_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 53, 1, 'Box bintang.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('STAR_SHAPE', 'Star Shape', 'PACKAGING', 'SHAPED', NULL, '/img/producttype/STAR_SHAPE.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 54, 1, 'Bintang tebal, jumlah sudut bebas.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('STE_BOX', 'Straight Tuck End (STE)', 'PACKAGING', 'TUCK_BOX', 'STRAIGHT_TUCK', '/img/producttype/STE_BOX.jpg', 'TUCK_END', NULL, '{\"gen\": \"TUCK_END\", \"flat\": {\"h\": \"max(TT,DF)+C+max(BT,DF)+2*P\", \"w\": \"G+2*A+2*B+2*P\"}, \"vars\": {\"H\": \"max(TT,DF)+C+max(BT,DF)\", \"W\": \"G+2*A+2*B\", \"ga\": \"min(8, C/10, G)\", \"x0\": \"0\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*A+2*B\", \"yB\": \"max(TT,DF)+C\", \"yT\": \"max(TT,DF)\"}, \"style\": \"STRAIGHT_TUCK\", \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x1\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x5\", \"x2\": \"x5\", \"y1\": \"yT\", \"y2\": \"yB\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yT\", \"y2\": \"yT\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yT\", \"dir\": \"up\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"BT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yB\", \"dir\": \"down\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"k\": \"CUT\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x4\", \"y1\": \"yB\", \"y2\": \"yB\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yB\", \"dir\": \"down\"}, {\"g\": \"ga\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"x0\", \"x1\": \"x1\", \"yB\": \"yB\", \"yT\": \"yT\"}]}', NULL, 'A60.10.00.03', 'DC', NULL, 0, 12, 1, 'Tuck atas-bawah searah. Tampilan lebih rapi.', 1, '2026-07-16 14:29:38', '2026-07-17 09:09:41', NULL, NULL);
INSERT INTO `prod_type` VALUES ('TRAPEZOID_BOX', 'Trapezoid Box', 'PACKAGING', 'POLYGONAL', NULL, '/img/producttype/TRAPEZOID_BOX.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 1, 47, 1, 'Box trapesium. Praline mewah.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('TRAY_INSERT', 'Tray Insert', 'PACKAGING', 'TRAY', NULL, '/img/producttype/TRAY_INSERT.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 23, 1, 'Insert pengunci item di tengah box.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);
INSERT INTO `prod_type` VALUES ('WOBBLER', 'Wobbler', 'DISPLAY', 'POP', NULL, '/img/producttype/WOBBLER.jpg', NULL, NULL, NULL, NULL, NULL, 'DC', NULL, 0, 72, 1, 'Display goyang di rak.', 0, '2026-07-16 14:29:38', '2026-07-17 09:57:34', NULL, NULL);

-- ----------------------------
-- Table structure for prod_type_param
-- ----------------------------
DROP TABLE IF EXISTS `prod_type_param`;
CREATE TABLE `prod_type_param`  (
  `id_type_param` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `param_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ex column_name — variabel DSL',
  `param_label` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `unit` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'mm, deg, pcs, %, x',
  `param_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'NUM' COMMENT 'NUM | ENUM',
  `option_json` json NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `default_value` decimal(10, 2) NULL DEFAULT NULL,
  `default_str` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `min_value` decimal(10, 2) NULL DEFAULT NULL,
  `max_value` decimal(10, 2) NULL DEFAULT NULL,
  `seq` smallint UNSIGNED NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_type_param`) USING BTREE,
  UNIQUE INDEX `uq_tparam_type_key`(`type_code` ASC, `param_key` ASC) USING BTREE,
  INDEX `idx_tparam_type`(`type_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 512 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Parameter dimensi per tipe (NUM/ENUM)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prod_type_param
-- ----------------------------
INSERT INTO `prod_type_param` VALUES (1, 'CARD_BOX', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (2, 'CARD_BOX', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (3, 'CARD_BOX', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (4, 'CARD_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (5, 'CARD_BOX', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (6, 'CARD_BOX', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (7, 'CARD_BOX', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (8, 'CARD_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (9, 'RTE_BOX', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (10, 'RTE_BOX', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (11, 'RTE_BOX', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (12, 'RTE_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (13, 'RTE_BOX', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (14, 'RTE_BOX', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (15, 'RTE_BOX', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (16, 'RTE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (17, 'STE_BOX', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (18, 'STE_BOX', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (19, 'STE_BOX', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (20, 'STE_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (21, 'STE_BOX', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (22, 'STE_BOX', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (23, 'STE_BOX', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (24, 'STE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (25, 'CRASH_LOCK', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (26, 'CRASH_LOCK', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (27, 'CRASH_LOCK', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (28, 'CRASH_LOCK', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (29, 'CRASH_LOCK', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (30, 'CRASH_LOCK', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 1, 12.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (31, 'CRASH_LOCK', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (32, 'SLEEVE', 'product_length', 'Panjang (A)', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (33, 'SLEEVE', 'product_width', 'Lebar (B)', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (34, 'SLEEVE', 'product_height', 'Tinggi (C)', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (35, 'SLEEVE', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (36, 'SLEEVE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (37, 'GIFT_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (38, 'GIFT_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (39, 'GIFT_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (40, 'GIFT_BOX', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (41, 'GIFT_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (42, 'MATCH_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (43, 'MATCH_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (44, 'MATCH_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (45, 'MATCH_BOX', 'material_thickness', 'Tebal Material', 'mm', 'NUM', NULL, 1, 0.50, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (46, 'MATCH_BOX', 'clearance', 'Clearance', 'mm', 'NUM', NULL, 0, 2.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (47, 'MATCH_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (48, 'MAILER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 154.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (49, 'MAILER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 110.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (50, 'MAILER', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (51, 'MAILER', 'overlap', 'Overlap', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (52, 'MAILER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (53, 'SHALLOW_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (54, 'SHALLOW_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (55, 'SHALLOW_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (56, 'SHALLOW_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (57, 'BOX_WITH_LID', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 170.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (58, 'BOX_WITH_LID', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 110.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (59, 'BOX_WITH_LID', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (60, 'BOX_WITH_LID', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (61, 'BOX_WITH_LID', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (62, 'NESTABLE_TRAY', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 148.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (63, 'NESTABLE_TRAY', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 105.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (64, 'NESTABLE_TRAY', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 74.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (65, 'NESTABLE_TRAY', 'angle', 'Draft Angle', 'deg', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (66, 'NESTABLE_TRAY', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (67, 'TRAY_INSERT', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (68, 'TRAY_INSERT', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 160.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (69, 'TRAY_INSERT', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (70, 'TRAY_INSERT', 'item_length', 'Panjang Item', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (71, 'TRAY_INSERT', 'item_width', 'Lebar Item', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (72, 'TRAY_INSERT', 'item_height', 'Tinggi Item', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (73, 'TRAY_INSERT', 'material_thickness', 'Tebal Material', 'mm', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (74, 'TRAY_INSERT', 'thumb_hole_width', 'Lebar Lubang Jempol', 'mm', 'NUM', NULL, 0, 20.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (75, 'TRAY_INSERT', 'thumb_hole_length', 'Panjang Lubang Jempol', 'mm', 'NUM', NULL, 0, 50.00, NULL, NULL, NULL, 9, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (76, 'TRAY_INSERT', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 10, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (77, 'MULTI_SHEET_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (78, 'MULTI_SHEET_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (79, 'MULTI_SHEET_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 200.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (80, 'MULTI_SHEET_BOX', 'material_thickness', 'Tebal Material', 'mm', 'NUM', NULL, 1, 0.50, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (81, 'MULTI_SHEET_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (82, 'PASSEPARTOUT', 'picture_length', 'Panjang Gambar', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (83, 'PASSEPARTOUT', 'picture_width', 'Lebar Gambar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (84, 'PASSEPARTOUT', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (85, 'PASSEPARTOUT', 'frame_width', 'Lebar Bingkai', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (86, 'PASSEPARTOUT', 'frame_depth', 'Kedalaman Bingkai', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (87, 'PASSEPARTOUT', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (88, 'EXPLODING_BOX', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (89, 'EXPLODING_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (90, 'EXPLODING_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (91, 'EXPLODING_BOX', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (92, 'EXPLODING_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (93, 'ROUND_BOX', 'diameter', 'Diameter', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (94, 'ROUND_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (95, 'ROUND_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (96, 'ROUND_BOX', 'extra_offset_caps', 'Offset Ekstra Cap', 'mm', 'NUM', NULL, 0, 0.50, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (97, 'ROUND_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (98, 'CYLINDER', 'diameter', 'Diameter', 'mm', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (99, 'CYLINDER', 'product_height', 'Panjang', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (100, 'CYLINDER', 'top_miter_angle', 'Sudut Miter Atas', 'deg', 'NUM', NULL, 0, 45.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (101, 'CYLINDER', 'bottom_miter_angle', 'Sudut Miter Bawah', 'deg', 'NUM', NULL, 0, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (102, 'CYLINDER', 'twist', 'Twist', 'deg', 'NUM', NULL, 0, 60.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (103, 'CYLINDER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (104, 'ELLIPTICAL_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (105, 'ELLIPTICAL_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (106, 'ELLIPTICAL_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (107, 'ELLIPTICAL_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (108, 'ELLIPTICAL_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (109, 'CONE_TRUNCATED', 'top_diameter', 'Diameter Atas', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (110, 'CONE_TRUNCATED', 'bottom_diameter', 'Diameter Bawah', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (111, 'CONE_TRUNCATED', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 120.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (112, 'CONE_TRUNCATED', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (113, 'CONE_MITERED', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (114, 'CONE_MITERED', 'top_size', 'Top', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (115, 'CONE_MITERED', 'top_angle', 'Sudut Atas', 'deg', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (116, 'CONE_MITERED', 'top_miter_angle', 'Sudut Miter Atas', 'deg', 'NUM', NULL, 0, -10.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (117, 'CONE_MITERED', 'bottom_miter_angle', 'Sudut Miter Bawah', 'deg', 'NUM', NULL, 0, 10.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (118, 'CONE_MITERED', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (119, 'SPHERE', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (120, 'SPHERE', 'segments', 'Jumlah Segmen', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (121, 'SPHERE', 'rings', 'Jumlah Ring', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (122, 'SPHERE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (123, 'MILK_CARTON', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (124, 'MILK_CARTON', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (125, 'MILK_CARTON', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (126, 'MILK_CARTON', 'roof_height', 'Tinggi Atap', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (127, 'MILK_CARTON', 'top_flap', 'Flap Atas', 'mm', 'NUM', NULL, 1, 18.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (128, 'MILK_CARTON', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (129, 'MILK_CARTON', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (130, 'POLYGONAL_BOX', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (131, 'POLYGONAL_BOX', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (132, 'POLYGONAL_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (133, 'POLYGONAL_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (134, 'POLYGONAL_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (135, 'POLYGONAL_BOX_LID', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (136, 'POLYGONAL_BOX_LID', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 7.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (137, 'POLYGONAL_BOX_LID', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (138, 'POLYGONAL_BOX_LID', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (139, 'POLYGONAL_BOX_LID', 'clearance_pct', 'Clearance', '%', 'NUM', NULL, 0, 5.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (140, 'POLYGONAL_BOX_LID', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (141, 'POLYGONAL_PYRAMID', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (142, 'POLYGONAL_PYRAMID', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (143, 'POLYGONAL_PYRAMID', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 7.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (144, 'POLYGONAL_PYRAMID', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (145, 'PYRAMID_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 115.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (146, 'PYRAMID_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 115.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (147, 'PYRAMID_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 73.50, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (148, 'PYRAMID_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (149, 'GEM_STONE_BOX', 'number_of_sides', 'Jumlah Sisi', 'x', 'NUM', NULL, 1, 6.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (150, 'GEM_STONE_BOX', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (151, 'GEM_STONE_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (152, 'GEM_STONE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (153, 'PLATONIC_SOLIDS', 'midsphere_diameter', 'Diameter Midsphere', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (154, 'PLATONIC_SOLIDS', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (155, 'PARALLELEPIPED', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (156, 'PARALLELEPIPED', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (157, 'PARALLELEPIPED', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (158, 'PARALLELEPIPED', 'angle_alpha', 'Sudut α', 'deg', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (159, 'PARALLELEPIPED', 'angle_beta', 'Sudut β', 'deg', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (160, 'PARALLELEPIPED', 'angle_gamma', 'Sudut γ', 'deg', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (161, 'PARALLELEPIPED', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (162, 'TRAPEZOID_BOX', 'top_length', 'Panjang Atas', 'mm', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (163, 'TRAPEZOID_BOX', 'bottom_length', 'Panjang Bawah', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (164, 'TRAPEZOID_BOX', 'top_width', 'Lebar Atas', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (165, 'TRAPEZOID_BOX', 'bottom_width', 'Lebar Bawah', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (166, 'TRAPEZOID_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (167, 'TRAPEZOID_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (168, 'RHOMBUS_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (169, 'RHOMBUS_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (170, 'RHOMBUS_BOX', 'angle', 'Sudut', 'deg', 'NUM', NULL, 1, 120.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (171, 'RHOMBUS_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (172, 'RHOMBUS_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (173, 'SOMA_CUBE', 'unit_cell_length', 'Panjang Unit Sel', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (174, 'SOMA_CUBE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (175, 'CURVED_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (176, 'CURVED_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (177, 'CURVED_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (178, 'CURVED_BOX', 'curve_size', 'Besar Lengkung', 'mm', 'NUM', NULL, 1, -5.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (179, 'CURVED_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (180, 'HEART_SHAPED_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (181, 'HEART_SHAPED_BOX', 'shape_pct', 'Bentuk', '%', 'NUM', NULL, 1, 55.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (182, 'HEART_SHAPED_BOX', 'tilt', 'Tilt', 'deg', 'NUM', NULL, 1, 45.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (183, 'HEART_SHAPED_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (184, 'HEART_SHAPED_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (185, 'HEART_SHAPED_BOX', 'extra_offset_caps', 'Offset Ekstra Cap', 'mm', 'NUM', NULL, 0, 1.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (186, 'HEART_SHAPED_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (187, 'EGG_SHAPED_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (188, 'EGG_SHAPED_BOX', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (189, 'EGG_SHAPED_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (190, 'EGG_SHAPED_BOX', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (191, 'EGG_SHAPED_BOX', 'sharpness', 'Ketajaman', 'x', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (192, 'EGG_SHAPED_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (193, 'STAR_BOX', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (194, 'STAR_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (195, 'STAR_BOX', 'number_of_points', 'Jumlah Sudut', 'x', 'NUM', NULL, 1, 5.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (196, 'STAR_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (197, 'STAR_SHAPE', 'outer_diameter', 'Diameter Luar', 'mm', 'NUM', NULL, 1, 200.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (198, 'STAR_SHAPE', 'inner_diameter', 'Diameter Dalam', 'mm', 'NUM', NULL, 1, 76.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (199, 'STAR_SHAPE', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 152.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (200, 'STAR_SHAPE', 'number_of_points', 'Jumlah Sudut', 'x', 'NUM', NULL, 1, 5.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (201, 'STAR_SHAPE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (202, 'CAKE_SLICE_BOX', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (203, 'CAKE_SLICE_BOX', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (204, 'CAKE_SLICE_BOX', 'angle', 'Sudut', 'deg', 'NUM', NULL, 1, 30.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (205, 'CAKE_SLICE_BOX', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (206, 'COFFIN', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 168.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (207, 'COFFIN', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 56.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (208, 'COFFIN', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 46.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (209, 'COFFIN', 'lid_height', 'Tinggi Tutup', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (210, 'COFFIN', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (211, 'PILLOW_PACK', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (212, 'PILLOW_PACK', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 70.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (213, 'PILLOW_PACK', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 15.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (214, 'PILLOW_PACK', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (215, 'PILLOW_PACK', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (216, 'BAG', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (217, 'BAG', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (218, 'BAG', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (219, 'BAG', 'fold', 'Lipatan', 'mm', 'NUM', NULL, 1, 20.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (220, 'BAG', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (221, 'BAG', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (222, 'ENVELOPE', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (223, 'ENVELOPE', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (224, 'ENVELOPE', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (225, 'ENVELOPE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (226, 'COUNTER_DISPLAY', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (227, 'COUNTER_DISPLAY', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 60.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (228, 'COUNTER_DISPLAY', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (229, 'COUNTER_DISPLAY', 'front', 'Tinggi Depan', 'mm', 'NUM', NULL, 1, 25.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (230, 'COUNTER_DISPLAY', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (231, 'COUNTER_DISPLAY', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (232, 'FLOOR_DISPLAY', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 400.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (233, 'FLOOR_DISPLAY', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 300.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (234, 'FLOOR_DISPLAY', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 1, 1500.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (235, 'FLOOR_DISPLAY', 'front', 'Tinggi Depan', 'mm', 'NUM', NULL, 0, 100.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (236, 'FLOOR_DISPLAY', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (237, 'WOBBLER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 100.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (238, 'WOBBLER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (239, 'WOBBLER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (240, 'HANG_TAG', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 80.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (241, 'HANG_TAG', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 40.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (242, 'HANG_TAG', 'thumb_hole_width', 'Diameter Lubang', 'mm', 'NUM', NULL, 0, 5.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (243, 'HANG_TAG', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (244, 'KALENDER_DINDING', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 330.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (245, 'KALENDER_DINDING', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 480.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (246, 'KALENDER_DINDING', 'page_count', 'Jumlah Lembar', 'x', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (247, 'KALENDER_DINDING', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `prod_type_param` VALUES (248, 'KALENDER_DINDING', 'hanger_type', 'Tipe Gantungan', NULL, 'ENUM', '[{\"c\": \"EYELET\", \"l\": \"Eyelet (Mata Ayam)\"}, {\"c\": \"HOOK\", \"l\": \"Hook (Gantungan Kait)\"}, {\"c\": \"DIECUT\", \"l\": \"Die-cut (Lubang Potong)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Gantungan\"}]', 1, NULL, 'EYELET', NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `prod_type_param` VALUES (249, 'KALENDER_DINDING', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (250, 'KALENDER_DUDUK', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (251, 'KALENDER_DUDUK', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 150.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (252, 'KALENDER_DUDUK', 'product_height', 'Tinggi Kaki', 'mm', 'NUM', NULL, 0, 100.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (253, 'KALENDER_DUDUK', 'page_count', 'Jumlah Lembar', 'x', 'NUM', NULL, 1, 13.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (254, 'KALENDER_DUDUK', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `prod_type_param` VALUES (255, 'KALENDER_DUDUK', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (256, 'BROSUR', 'product_length', 'Panjang (buka)', 'mm', 'NUM', NULL, 1, 297.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (257, 'BROSUR', 'product_width', 'Lebar (buka)', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (258, 'BROSUR', 'fold_count', 'Jumlah Lipatan', 'x', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (259, 'BROSUR', 'fold_type', 'Tipe Lipat', NULL, 'ENUM', '[{\"c\": \"HALF\", \"l\": \"Lipat 2 (Half / Bi-fold)\"}, {\"c\": \"TRI\", \"l\": \"Lipat 3 (Tri-fold)\"}, {\"c\": \"Z\", \"l\": \"Lipat Z (Zig-zag)\"}, {\"c\": \"GATE\", \"l\": \"Gate Fold\"}, {\"c\": \"ROLL\", \"l\": \"Roll Fold\"}]', 1, NULL, 'TRI', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `prod_type_param` VALUES (260, 'BROSUR', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (261, 'FLYER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (262, 'FLYER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 148.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (263, 'FLYER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (264, 'POSTER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 594.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (265, 'POSTER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 420.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (266, 'POSTER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (267, 'BUKU', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (268, 'BUKU', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 148.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (269, 'BUKU', 'page_count', 'Jumlah Halaman', 'x', 'NUM', NULL, 1, 64.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (270, 'BUKU', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `prod_type_param` VALUES (271, 'BUKU', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (272, 'MAJALAH', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 275.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (273, 'MAJALAH', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (274, 'MAJALAH', 'page_count', 'Jumlah Halaman', 'x', 'NUM', NULL, 1, 48.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (275, 'MAJALAH', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `prod_type_param` VALUES (276, 'MAJALAH', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (277, 'NOTA_INVOICE', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 210.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (278, 'NOTA_INVOICE', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 140.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (279, 'NOTA_INVOICE', 'page_count', 'Jumlah Rangkap', 'x', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (280, 'NOTA_INVOICE', 'binding_type', 'Tipe Jilid', NULL, 'ENUM', '[{\"c\": \"SPIRAL\", \"l\": \"Spiral\"}, {\"c\": \"WIRE_O\", \"l\": \"Wire-O\"}, {\"c\": \"KLEM_SENG\", \"l\": \"Klem Seng\"}, {\"c\": \"SADDLE\", \"l\": \"Saddle Stitch (Jahit Kawat)\"}, {\"c\": \"PERFECT\", \"l\": \"Perfect Binding (Lem Punggung)\"}, {\"c\": \"NONE\", \"l\": \"Tanpa Jilid\"}]', 1, NULL, 'KLEM_SENG', NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-17 09:53:33');
INSERT INTO `prod_type_param` VALUES (281, 'NOTA_INVOICE', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (282, 'LABEL_STIKER', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (283, 'LABEL_STIKER', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 50.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (284, 'LABEL_STIKER', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (285, 'KARTU_NAMA', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 90.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (286, 'KARTU_NAMA', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 55.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (287, 'KARTU_NAMA', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 2.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (288, 'AMPLOP_KOP', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 1, 220.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (289, 'AMPLOP_KOP', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 1, 110.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (290, 'AMPLOP_KOP', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 1, 10.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (291, 'AMPLOP_KOP', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 1, 3.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (292, 'CUSTOM', 'product_length', 'Panjang', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 1, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (293, 'CUSTOM', 'product_width', 'Lebar', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 2, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (294, 'CUSTOM', 'product_height', 'Tinggi', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 3, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (295, 'CUSTOM', 'glue_flap', 'Lidah Lem', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 4, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (296, 'CUSTOM', 'top_tuck_flap', 'Top Tuck', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 5, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (297, 'CUSTOM', 'dust_flap', 'Dust Flap', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 6, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (298, 'CUSTOM', 'bottom_tuck_flap', 'Bottom Tuck', 'mm', 'NUM', NULL, 0, 0.00, NULL, NULL, NULL, 7, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (299, 'CUSTOM', 'overflow_margin', 'Overflow / Bleed', 'mm', 'NUM', NULL, 0, 3.00, NULL, NULL, NULL, 8, 1, '2026-07-16 14:29:38', '2026-07-16 14:29:38');
INSERT INTO `prod_type_param` VALUES (300, 'GIFT_BOX', 'top_tuck_flap', 'Tuck Flap (mm)', 'mm', 'NUM', NULL, 0, 15.00, NULL, 5.00, 80.00, 6, 1, '2026-07-22 19:04:49', '2026-07-22 19:04:49');
INSERT INTO `prod_type_param` VALUES (301, 'GIFT_BOX', 'thumb_hole_width', 'Lubang Jempol (mm)', 'mm', 'NUM', NULL, 0, 15.00, NULL, 0.00, 60.00, 7, 1, '2026-07-22 19:04:49', '2026-07-22 19:04:49');

-- ----------------------------
-- Table structure for prod_type_size
-- ----------------------------
DROP TABLE IF EXISTS `prod_type_size`;
CREATE TABLE `prod_type_size`  (
  `id_type_size` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FK -> prod_type',
  `size_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode ukuran pasaran',
  `size_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Label, mis. \"Small 8x5x2 cm\"',
  `sort_order` smallint NOT NULL DEFAULT 999,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_type_size`) USING BTREE,
  INDEX `idx_tsize_type`(`type_code` ASC) USING BTREE,
  CONSTRAINT `fk_tsize_type` FOREIGN KEY (`type_code`) REFERENCES `prod_type` (`type_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Ukuran pasaran per tipe (dropdown auto-fill dimensi)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prod_type_size
-- ----------------------------

-- ----------------------------
-- Table structure for prod_type_size_val
-- ----------------------------
DROP TABLE IF EXISTS `prod_type_size_val`;
CREATE TABLE `prod_type_size_val`  (
  `id_size_val` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_size_id` bigint UNSIGNED NOT NULL,
  `param_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_num` decimal(12, 3) NULL DEFAULT NULL COMMENT 'Nilai NUM',
  `value_str` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nilai ENUM',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_size_val`) USING BTREE,
  UNIQUE INDEX `uq_sizeval_size_param`(`type_size_id` ASC, `param_key` ASC) USING BTREE,
  INDEX `idx_sizeval_size`(`type_size_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 256 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Nilai parameter per ukuran (NUM/ENUM)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prod_type_size_val
-- ----------------------------
INSERT INTO `prod_type_size_val` VALUES (1, 13, 'product_length', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (2, 7, 'product_length', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (3, 1, 'product_length', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (4, 13, 'product_width', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (5, 7, 'product_width', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (6, 1, 'product_width', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (7, 13, 'product_height', 110.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (8, 7, 'product_height', 110.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (9, 1, 'product_height', 110.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (10, 14, 'product_length', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (11, 8, 'product_length', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (12, 2, 'product_length', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (13, 14, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (14, 8, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (15, 2, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (16, 14, 'product_height', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (17, 8, 'product_height', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (18, 2, 'product_height', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (19, 15, 'product_length', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (20, 9, 'product_length', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (21, 3, 'product_length', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (22, 15, 'product_width', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (23, 9, 'product_width', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (24, 3, 'product_width', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (25, 15, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (26, 9, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (27, 3, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (28, 16, 'product_length', 105.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (29, 10, 'product_length', 105.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (30, 4, 'product_length', 105.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (31, 16, 'product_width', 32.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (32, 10, 'product_width', 32.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (33, 4, 'product_width', 32.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (34, 16, 'product_height', 22.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (35, 10, 'product_height', 22.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (36, 4, 'product_height', 22.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (37, 17, 'product_length', 95.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (38, 11, 'product_length', 95.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (39, 5, 'product_length', 95.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (40, 17, 'product_width', 20.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (41, 11, 'product_width', 20.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (42, 5, 'product_width', 20.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (43, 17, 'product_height', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (44, 11, 'product_height', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (45, 5, 'product_height', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (46, 18, 'product_length', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (47, 12, 'product_length', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (48, 6, 'product_length', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (49, 18, 'product_width', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (50, 12, 'product_width', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (51, 6, 'product_width', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (52, 18, 'product_height', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (53, 12, 'product_height', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (54, 6, 'product_height', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (55, 19, 'product_length', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (56, 19, 'product_width', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (57, 19, 'product_height', 180.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (58, 20, 'product_length', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (59, 20, 'product_width', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (60, 20, 'product_height', 220.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (61, 21, 'product_length', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (62, 21, 'product_width', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (63, 21, 'product_height', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (64, 22, 'product_length', 90.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (65, 22, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (66, 22, 'product_height', 160.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (67, 23, 'product_length', 110.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (68, 23, 'product_width', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (69, 23, 'product_height', 180.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (70, 24, 'product_length', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (71, 24, 'product_width', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (72, 24, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (73, 25, 'product_length', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (74, 25, 'product_width', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (75, 25, 'product_height', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (76, 26, 'product_length', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (77, 26, 'product_width', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (78, 26, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (79, 27, 'product_length', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (80, 27, 'product_width', 140.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (81, 27, 'product_height', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (82, 28, 'product_length', 235.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (83, 28, 'product_width', 180.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (84, 28, 'product_height', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (85, 29, 'product_length', 330.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (86, 29, 'product_width', 250.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (87, 29, 'product_height', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (88, 30, 'product_length', 300.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (89, 30, 'product_width', 180.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (90, 30, 'product_height', 110.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (91, 30, 'lid_height', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (92, 31, 'product_length', 250.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (93, 31, 'product_width', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (94, 31, 'product_height', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (95, 31, 'lid_height', 35.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (96, 32, 'product_length', 250.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (97, 32, 'product_width', 250.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (98, 32, 'product_height', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (99, 33, 'product_length', 300.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (100, 33, 'product_width', 300.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (101, 33, 'product_height', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (102, 34, 'diameter', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (103, 34, 'product_height', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (104, 34, 'lid_height', 30.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (105, 35, 'diameter', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (106, 35, 'product_height', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (107, 35, 'lid_height', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (108, 36, 'diameter', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (109, 36, 'product_height', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (110, 36, 'lid_height', 25.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (111, 37, 'diameter', 63.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (112, 37, 'product_height', 430.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (113, 38, 'diameter', 45.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (114, 38, 'product_height', 90.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (115, 39, 'diameter', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (116, 39, 'product_height', 130.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (117, 40, 'product_length', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (118, 40, 'product_width', 40.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (119, 41, 'product_length', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (120, 41, 'product_width', 90.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (121, 42, 'product_length', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (122, 42, 'product_width', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (123, 43, 'product_length', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (124, 43, 'product_width', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (125, 43, 'product_height', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (126, 44, 'product_length', 300.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (127, 44, 'product_width', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (128, 44, 'product_height', 180.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (129, 45, 'product_length', 310.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (130, 45, 'product_width', 480.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (131, 45, 'page_count', 7.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (132, 45, 'binding_type', NULL, 'KLEM_SENG', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (133, 45, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (134, 46, 'product_length', 310.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (135, 46, 'product_width', 540.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (136, 46, 'page_count', 7.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (137, 46, 'binding_type', NULL, 'KLEM_SENG', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (138, 46, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (139, 47, 'product_length', 380.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (140, 47, 'product_width', 530.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (141, 47, 'page_count', 7.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (142, 47, 'binding_type', NULL, 'WIRE_O', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (143, 47, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (144, 48, 'product_length', 260.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (145, 48, 'product_width', 380.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (146, 48, 'page_count', 7.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (147, 48, 'binding_type', NULL, 'KLEM_SENG', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (148, 48, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (149, 49, 'product_length', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (150, 49, 'product_width', 420.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (151, 49, 'page_count', 7.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (152, 49, 'binding_type', NULL, 'KLEM_SENG', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (153, 49, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (154, 50, 'product_length', 440.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (155, 50, 'product_width', 640.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (156, 50, 'page_count', 7.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (157, 50, 'binding_type', NULL, 'WIRE_O', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (158, 50, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (159, 51, 'product_length', 700.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (160, 51, 'product_width', 500.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (161, 51, 'page_count', 7.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (162, 51, 'binding_type', NULL, 'WIRE_O', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (163, 51, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (164, 52, 'product_length', 420.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (165, 52, 'product_width', 594.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (166, 52, 'page_count', 1.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (167, 52, 'binding_type', NULL, 'NONE', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (168, 52, 'hanger_type', NULL, 'EYELET', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (169, 53, 'product_length', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (170, 53, 'product_width', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (171, 53, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (172, 53, 'page_count', 13.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (173, 53, 'binding_type', NULL, 'SPIRAL', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (174, 54, 'product_length', 150.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (175, 54, 'product_width', 200.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (176, 54, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (177, 54, 'page_count', 13.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (178, 54, 'binding_type', NULL, 'SPIRAL', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (179, 55, 'product_length', 170.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (180, 55, 'product_width', 230.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (181, 55, 'product_height', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (182, 55, 'page_count', 13.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (183, 55, 'binding_type', NULL, 'SPIRAL', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (184, 56, 'product_length', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (185, 56, 'product_width', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (186, 56, 'fold_count', 2.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (187, 56, 'fold_type', NULL, 'TRI', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (188, 57, 'product_length', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (189, 57, 'product_width', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (190, 57, 'fold_count', 1.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (191, 57, 'fold_type', NULL, 'HALF', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (192, 58, 'product_length', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (193, 58, 'product_width', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (194, 58, 'fold_count', 2.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (195, 58, 'fold_type', NULL, 'Z', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (196, 59, 'product_length', 420.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (197, 59, 'product_width', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (198, 59, 'fold_count', 1.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (199, 59, 'fold_type', NULL, 'HALF', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (200, 60, 'product_length', 420.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (201, 60, 'product_width', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (202, 60, 'fold_count', 2.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (203, 60, 'fold_type', NULL, 'TRI', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (204, 61, 'product_length', 148.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (205, 61, 'product_width', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (206, 62, 'product_length', 105.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (207, 62, 'product_width', 148.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (208, 63, 'product_length', 99.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (209, 63, 'product_width', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (210, 64, 'product_length', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (211, 64, 'product_width', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (212, 65, 'product_length', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (213, 65, 'product_width', 420.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (214, 66, 'product_length', 420.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (215, 66, 'product_width', 594.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (216, 67, 'product_length', 594.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (217, 67, 'product_width', 841.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (218, 68, 'product_length', 500.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (219, 68, 'product_width', 700.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (220, 69, 'product_length', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (221, 69, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (222, 70, 'product_length', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (223, 70, 'product_width', 70.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (224, 71, 'product_length', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (225, 71, 'product_width', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (226, 72, 'product_length', 90.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (227, 72, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (228, 73, 'product_length', 100.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (229, 73, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (230, 74, 'product_length', 90.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (231, 74, 'product_width', 60.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (232, 75, 'product_length', 120.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (233, 75, 'product_width', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (234, 76, 'product_length', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (235, 76, 'product_width', 50.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (236, 77, 'product_length', 90.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (237, 77, 'product_width', 55.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (238, 78, 'product_length', 90.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (239, 78, 'product_width', 54.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (240, 79, 'product_length', 148.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (241, 79, 'product_width', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (242, 79, 'page_count', 64.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (243, 79, 'binding_type', NULL, 'PERFECT', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (244, 80, 'product_length', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (245, 80, 'product_width', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (246, 80, 'page_count', 64.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (247, 80, 'binding_type', NULL, 'SADDLE', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (248, 81, 'product_length', 176.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (249, 81, 'product_width', 250.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (250, 81, 'page_count', 80.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (251, 81, 'binding_type', NULL, 'PERFECT', '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (252, 82, 'product_length', 220.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (253, 82, 'product_width', 110.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (254, 83, 'product_length', 210.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');
INSERT INTO `prod_type_size_val` VALUES (255, 83, 'product_width', 297.000, NULL, '2026-08-08 12:30:20', '2026-08-08 12:30:20');

-- ----------------------------
-- Table structure for ref_param_option
-- ----------------------------
DROP TABLE IF EXISTS `ref_param_option`;
CREATE TABLE `ref_param_option`  (
  `id_option` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `param_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ex column_name: fold_type, binding_type...',
  `option_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `sort_order` smallint NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_option`) USING BTREE,
  INDEX `idx_refopt_param`(`param_key` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Opsi ENUM parameter (global, lintas tipe)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ref_param_option
-- ----------------------------

-- ----------------------------
-- Table structure for uom_conversion
-- ----------------------------
DROP TABLE IF EXISTS `uom_conversion`;
CREATE TABLE `uom_conversion`  (
  `id_conversion` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `uom_src` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Satuan asal',
  `uom_dst` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Satuan tujuan',
  `conversion_value` decimal(20, 6) UNSIGNED NOT NULL DEFAULT 0.000000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_conversion`) USING BTREE,
  INDEX `idx_conv_src`(`uom_src` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Konversi antar satuan' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of uom_conversion
-- ----------------------------

-- ----------------------------
-- Table structure for uom_unit
-- ----------------------------
DROP TABLE IF EXISTS `uom_unit`;
CREATE TABLE `uom_unit`  (
  `id_uom` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uom_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_uom`) USING BTREE,
  UNIQUE INDEX `uq_uom_code`(`uom_code` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master satuan' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of uom_unit
-- ----------------------------

-- ----------------------------
-- View structure for v_geo_full_address
-- ----------------------------
DROP VIEW IF EXISTS `v_geo_full_address`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_geo_full_address` AS select `d`.`id_district` AS `id_district`,`c`.`id_city` AS `id_city`,`r`.`id_region` AS `id_region`,`ct`.`id_country` AS `id_country`,concat(`ct`.`country_name`,' > ',`r`.`region_name`,' > ',coalesce(concat(`c`.`city_type`,' '),''),`c`.`city_name`,if((`d`.`district_name` is null),'',concat(' > ',`d`.`district_name`))) AS `full_label`,`ct`.`country_name` AS `country_name`,`r`.`region_name` AS `region_name`,`c`.`city_type` AS `city_type`,`c`.`city_name` AS `city_name`,`d`.`district_name` AS `district_name` from (((`geo_city` `c` join `geo_region` `r` on((`r`.`id_region` = `c`.`region_id`))) join `geo_country` `ct` on((`ct`.`id_country` = `r`.`country_id`))) left join `geo_district` `d` on((`d`.`city_id` = `c`.`id_city`)));

SET FOREIGN_KEY_CHECKS = 1;
