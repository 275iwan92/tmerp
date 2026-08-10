-- ============================================================
-- TIER 3A — MASTER untuk MODUL INQUIRY (subset)
-- ERP Cetak Offset & Packaging — PT. Telasih Makmur
-- Skema: tm2mst (master netral) · Basis: tmmst existing, dikonversi ke 3A
-- ------------------------------------------------------------
-- SCOPE: HANYA master yang di-refer modul inquiry (keputusan (b)).
--   Sisanya (emp_/org_/sup_/inv_) menyusul saat modulnya digarap.
-- Konversi naming: create_log->created_at, update_log->updated_at,
--   create_by->created_by, update_by->updated_by, stat_confirm->is_confirmed.
-- Kolom bisnis existing DIPERTAHANKAN (stock, credit_limit, dll).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. uom_unit  (ex m_uom)
-- ============================================================
DROP TABLE IF EXISTS `uom_unit`;
CREATE TABLE `uom_unit` (
  `id_uom`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uom_code`   VARCHAR(50)  NOT NULL,
  `uom_name`   VARCHAR(200) NULL DEFAULT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) NULL DEFAULT NULL,
  `updated_by` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_uom`) USING BTREE,
  UNIQUE INDEX `uq_uom_code` (`uom_code` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master satuan';

-- 2. uom_conversion  (ex m_uom_conversion)
DROP TABLE IF EXISTS `uom_conversion`;
CREATE TABLE `uom_conversion` (
  `id_conversion`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uom_src`          VARCHAR(50)  NOT NULL COMMENT 'Satuan asal',
  `uom_dst`          VARCHAR(50)  NOT NULL COMMENT 'Satuan tujuan',
  `conversion_value` DECIMAL(20,6) UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) NULL DEFAULT NULL,
  `updated_by` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_conversion`) USING BTREE,
  INDEX `idx_conv_src` (`uom_src` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Konversi antar satuan';

-- ============================================================
-- 3. cust_customer  (ex m_customer)
-- ============================================================
DROP TABLE IF EXISTS `cust_customer`;
CREATE TABLE `cust_customer` (
  `id_customer`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code`   VARCHAR(50)  NULL DEFAULT NULL,
  `customer_name`   VARCHAR(200) NULL DEFAULT NULL,
  `tax_code`        VARCHAR(50)  NULL DEFAULT NULL COMMENT 'NPWP',
  `mr_ms`           VARCHAR(5)   NULL DEFAULT NULL COMMENT 'Bapak/Ibu',
  `contact_name`    VARCHAR(200) NULL DEFAULT NULL,
  `default_address` TEXT         NULL,
  `default_area_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> geo_city/geo_district (id wilayah)',
  `email`           VARCHAR(150) NULL DEFAULT NULL,
  `phone`           VARCHAR(50)  NULL DEFAULT NULL,
  `website`         VARCHAR(150) NULL DEFAULT NULL,
  `customer_type`   VARCHAR(10)  NULL DEFAULT 'local' COMMENT 'local | import',
  `payment_term`    VARCHAR(50)  NULL DEFAULT NULL COMMENT 'FK -> fin_payment_term (menyusul)',
  `currency_code`   VARCHAR(10)  NULL DEFAULT 'IDR' COMMENT 'FK -> fin_currency.currency_code',
  `credit_limit`    DECIMAL(18,2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'Batas piutang',
  `current_balance` DECIMAL(18,2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'Saldo piutang berjalan',
  `is_prospect`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=prospek (calon dari inkuiri) | 0=resmi',
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`      VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`      VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_customer`) USING BTREE,
  UNIQUE INDEX `uq_customer_code` (`customer_code` ASC) USING BTREE,
  INDEX `idx_cust_area` (`default_area_id` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master customer (+prospek)';

-- ============================================================
-- 4. prod_category  (ex m_product_cat)
-- ============================================================
DROP TABLE IF EXISTS `prod_category`;
CREATE TABLE `prod_category` (
  `id_category`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(200) NULL DEFAULT NULL,
  `description`   TEXT         NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_category`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategori produk';

-- ============================================================
-- 5. prod_type  (ex m_product_type) — dieline DSL & dimensi
--    PK = kode (varchar), sesuai existing.
-- ============================================================
DROP TABLE IF EXISTS `prod_type`;
CREATE TABLE `prod_type` (
  `type_code`         VARCHAR(30)  NOT NULL COMMENT 'PK kode tipe produk',
  `type_name`         VARCHAR(100) NULL DEFAULT NULL,
  `category`          VARCHAR(30)  NULL DEFAULT NULL COMMENT 'PACKAGING|COMMERCIAL_PRINT|DISPLAY|MISC',
  `sub_category`      VARCHAR(30)  NULL DEFAULT NULL COMMENT 'TUCK_BOX|TRAY|CYLINDER|FLAT_PRINT...',
  `box_style`        VARCHAR(30)  NULL DEFAULT NULL COMMENT 'REVERSE_TUCK|STRAIGHT_TUCK|SLEEVE...',
  `photo_path`        VARCHAR(255) NULL DEFAULT NULL,
  `svg_generator`     VARCHAR(30)  NULL DEFAULT NULL COMMENT 'Kunci generator SVG dieline',
  `dieline_sample`    VARCHAR(255) NULL DEFAULT NULL COMMENT 'SVG statis fallback',
  `dieline_spec`      JSON         NULL COMMENT 'DSL deklaratif dieline {vars/elements/flat}',
  `ecma_code`         VARCHAR(30)  NULL DEFAULT NULL,
  `part_type_default` VARCHAR(20)  NULL DEFAULT NULL,
  `material_filter`   VARCHAR(255) NULL DEFAULT NULL,
  `has_multi_part`    TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 999,
  `spec_version`      SMALLINT     NOT NULL DEFAULT 1,
  `notes`             VARCHAR(255) NULL DEFAULT NULL,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`        VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`        VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`type_code`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipe produk + dieline DSL (sumber dieline_spec ke inquiry)';

-- ============================================================
-- 6. mat_raw  (ex m_raw_material) — bahan baku
-- ============================================================
DROP TABLE IF EXISTS `mat_raw`;
CREATE TABLE `mat_raw` (
  `id_material`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_code`   VARCHAR(60)  NULL DEFAULT NULL,
  `material_name`   VARCHAR(200) NULL DEFAULT NULL,
  `item_type`       VARCHAR(15)  NULL DEFAULT 'MATERIAL' COMMENT 'MATERIAL|SERVICE|FREE_SAMPLE',
  `category1`       VARCHAR(100) NULL DEFAULT NULL,
  `category2`       VARCHAR(100) NULL DEFAULT NULL,
  `category3`       VARCHAR(100) NULL DEFAULT NULL,
  `description`     TEXT         NULL,
  `brand`           VARCHAR(100) NULL DEFAULT NULL,
  `color`           VARCHAR(50)  NULL DEFAULT NULL,
  `gram_weight`     INT UNSIGNED NULL DEFAULT 0 COMMENT 'Gramatur',
  `cm_length`       DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_width`        DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `mm_thickness`    DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `specification`   VARCHAR(255) NULL DEFAULT NULL,
  `uom_stock`       VARCHAR(50)  NULL DEFAULT NULL,
  `uom_purchase`    VARCHAR(50)  NULL DEFAULT NULL,
  `uom_usage`       VARCHAR(50)  NULL DEFAULT NULL,
  `conversion_to_stock` DECIMAL(20,6) UNSIGNED NULL DEFAULT 1.000000,
  `conversion_to_usage` DECIMAL(20,6) UNSIGNED NULL DEFAULT NULL,
  `purchasable`     TINYINT(1)   NOT NULL DEFAULT 1,
  `avg_unitprice`   DECIMAL(30,5) NULL DEFAULT NULL COMMENT 'Harga perolehan rata-rata',
  `last_unitprice`  DECIMAL(30,5) NULL DEFAULT NULL,
  `min_stock`       DECIMAL(20,4) UNSIGNED NULL DEFAULT 0.0000,
  `max_stock`       DECIMAL(20,4) UNSIGNED NULL DEFAULT NULL,
  `cur_stock`       DECIMAL(20,4) NULL DEFAULT NULL,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`      VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`      VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_material`) USING BTREE,
  UNIQUE INDEX `uq_material_code` (`material_code` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master bahan baku (di-refer costing & part_material)';

-- ============================================================
-- 7. prod_process  (ex m_process)
-- ============================================================
DROP TABLE IF EXISTS `prod_process`;
CREATE TABLE `prod_process` (
  `id_process`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_name` VARCHAR(200) NULL DEFAULT NULL,
  `gantt_color`  VARCHAR(10)  NULL DEFAULT NULL COMMENT 'Warna di gantt chart',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_process`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master proses produksi';

-- ============================================================
-- 8. prod_product  (ex m_product) — HEADER produk baku
--    Sumber snapshot untuk inquiry RECALC/REVISE.
-- ============================================================
DROP TABLE IF EXISTS `prod_product`;
CREATE TABLE `prod_product` (
  `id_product`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_code`    VARCHAR(100) NOT NULL,
  `product_name`    VARCHAR(200) NULL DEFAULT NULL,
  `customer_id`     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> cust_customer',
  `category_id`     BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_category',
  `description`     TEXT         NULL,
  `uom_stock`       VARCHAR(50)  NULL DEFAULT NULL,
  `uom_sales`       VARCHAR(50)  NULL DEFAULT NULL,
  `conversion_to_stock` DECIMAL(20,6) UNSIGNED NULL DEFAULT 1.000000,
  `qty_limit_min`   DECIMAL(12,2) UNSIGNED NULL DEFAULT 0.00,
  `qty_limit_max`   DECIMAL(12,2) UNSIGNED NULL DEFAULT 0.00,
  `cm_length`       DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_width`        DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_height`       DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `min_stock`       DECIMAL(20,4) UNSIGNED NULL DEFAULT 0.0000,
  `max_stock`       DECIMAL(20,4) UNSIGNED NULL DEFAULT NULL,
  `cur_stock`       DECIMAL(20,4) NULL DEFAULT 0.0000,
  `default_selling_price` DECIMAL(10,2) UNSIGNED NULL DEFAULT 0.00,
  `minimum_selling_price` DECIMAL(10,2) UNSIGNED NULL DEFAULT 0.00,
  `is_confirmed`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'ex stat_confirm',
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`      VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`      VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_product`) USING BTREE,
  UNIQUE INDEX `uq_product_code` (`product_code` ASC) USING BTREE,
  INDEX `idx_prod_customer` (`customer_id` ASC) USING BTREE,
  INDEX `idx_prod_category` (`category_id` ASC) USING BTREE,
  CONSTRAINT `fk_prod_customer` FOREIGN KEY (`customer_id`)
      REFERENCES `cust_customer` (`id_customer`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`)
      REFERENCES `prod_category` (`id_category`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Header produk baku. Sumber snapshot RECALC/REVISE';

-- ============================================================
-- 9. prod_part  (ex m_product_parts)
-- ============================================================
DROP TABLE IF EXISTS `prod_part`;
CREATE TABLE `prod_part` (
  `id_part`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    BIGINT UNSIGNED NOT NULL COMMENT 'FK -> prod_product',
  `part_code`     VARCHAR(50)  NOT NULL,
  `part_name`     VARCHAR(200) NOT NULL,
  `part_type`     VARCHAR(20)  NULL DEFAULT NULL COMMENT 'DC|DC+SF|DC+SHEET|KRAFT|SHEET',
  `qty_per_sku`   DECIMAL(10,4) UNSIGNED NOT NULL DEFAULT 1.0000,
  `description`   TEXT         NULL,
  `uom_stock`     VARCHAR(50)  NULL DEFAULT NULL,
  `cfg_color`     VARCHAR(5)   NULL DEFAULT NULL COMMENT '4/4, 4/0, dll',
  `cfg_ink`       VARCHAR(200) NULL DEFAULT NULL,
  `waste_default`    DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Waste %',
  `spoilage_default` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Spoilage %',
  -- imposisi & dieline baku (ex m_card)
  `lc_per_plano`  DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Out per plano baku',
  `image_per_lc`  DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'UP baku',
  `printsize_p`   DECIMAL(8,2) NULL DEFAULT NULL,
  `printsize_l`   DECIMAL(8,2) NULL DEFAULT NULL,
  `dieline_ref`   VARCHAR(80)  NULL DEFAULT NULL,
  `ecma_code`     VARCHAR(30)  NULL DEFAULT NULL,
  `cm_length`     DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_width`      DECIMAL(12,4) UNSIGNED NULL DEFAULT 0.0000,
  `cm_height`     DECIMAL(12,4) NULL DEFAULT NULL,
  `min_stock`     DECIMAL(20,4) UNSIGNED NULL DEFAULT 0.0000,
  `max_stock`     DECIMAL(20,4) UNSIGNED NULL DEFAULT NULL,
  `cur_stock`     DECIMAL(20,4) NULL DEFAULT NULL,
  `last_design_id`  BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis iqp inquiry design terakhir jadi baku',
  `last_costing_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis iqp_costing terakhir jadi baku',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_part`) USING BTREE,
  INDEX `idx_part_product` (`product_id` ASC) USING BTREE,
  CONSTRAINT `fk_part_product` FOREIGN KEY (`product_id`)
      REFERENCES `prod_product` (`id_product`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Part/komponen produk baku';

-- ============================================================
-- 10. prod_part_material  (ex m_product_parts_material) — layer bahan
-- ============================================================
DROP TABLE IF EXISTS `prod_part_material`;
CREATE TABLE `prod_part_material` (
  `id_part_material` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK -> prod_part',
  `layer_seq`   INT          NOT NULL DEFAULT 1 COMMENT 'Urutan lapisan',
  `material_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> mat_raw',
  `rawmat_type` VARCHAR(20)  NULL DEFAULT NULL COMMENT 'utama|sekunder|SF|flute',
  `gram_weight` DECIMAL(8,2) NULL DEFAULT NULL,
  `flute_type`  VARCHAR(10)  NULL DEFAULT NULL COMMENT 'B/F, E/F...',
  `size_p`      DECIMAL(8,2) NULL DEFAULT NULL,
  `size_l`      DECIMAL(8,2) NULL DEFAULT NULL,
  `is_primary`  TINYINT(1)   NOT NULL DEFAULT 1,
  `note`        VARCHAR(255) NULL DEFAULT NULL,
  `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`  VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`  VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_material`) USING BTREE,
  INDEX `idx_pmat_part` (`part_id` ASC) USING BTREE,
  INDEX `idx_pmat_material` (`material_id` ASC) USING BTREE,
  CONSTRAINT `fk_pmat_part` FOREIGN KEY (`part_id`)
      REFERENCES `prod_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pmat_material` FOREIGN KEY (`material_id`)
      REFERENCES `mat_raw` (`id_material`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Layer bahan per part baku (multi-layer)';

-- ============================================================
-- 11. prod_part_process  (ex m_product_parts_process) — routing branch/flow
-- ============================================================
DROP TABLE IF EXISTS `prod_part_process`;
CREATE TABLE `prod_part_process` (
  `id_part_process` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`      BIGINT UNSIGNED NOT NULL COMMENT 'FK -> prod_part',
  `branch_code`  VARCHAR(20)  NULL DEFAULT 'MAIN' COMMENT 'MAIN|L|R|BODY|LID',
  `step_seq`     INT          NOT NULL COMMENT 'Urutan dalam branch',
  `flow_type`    VARCHAR(10)  NULL DEFAULT 'LINEAR' COMMENT 'LINEAR|SPLIT|MERGE',
  `process_id`   BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_process',
  `process_name` VARCHAR(80)  NULL DEFAULT NULL COMMENT 'Snapshot nama',
  `machine_id`   BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis mesin (menyusul)',
  `layer_ref`    BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> prod_part_material (layer diproses)',
  `qty_factor`   DECIMAL(5,3) NOT NULL DEFAULT 1.000 COMMENT 'Porsi qty (0.5=belah plano)',
  `note`         VARCHAR(255) NULL DEFAULT NULL,
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_process`) USING BTREE,
  INDEX `idx_pproc_part` (`part_id` ASC) USING BTREE,
  INDEX `idx_pproc_process` (`process_id` ASC) USING BTREE,
  CONSTRAINT `fk_pproc_part` FOREIGN KEY (`part_id`)
      REFERENCES `prod_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pproc_process` FOREIGN KEY (`process_id`)
      REFERENCES `prod_process` (`id_process`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Routing per part baku (branch/flow SPLIT/MERGE)';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CATATAN
-- ============================================================
-- Mapping nama (old -> 3A):
--   m_uom->uom_unit · m_uom_conversion->uom_conversion · m_customer->cust_customer
--   m_product_cat->prod_category · m_product_type->prod_type · m_raw_material->mat_raw
--   m_process->prod_process · m_product->prod_product · m_product_parts->prod_part
--   m_product_parts_material->prod_part_material · m_product_parts_process->prod_part_process
-- PK diseragamkan: id_<entitas> (kecuali prod_type & iqp_type = kode varchar).
-- FK antar-master sudah dipasang fisik. FK ke geo_/fin_ (currency,area) = logis dulu
--   (beda-DB atau menyusul); ditegakkan saat integrasi.
-- BELUM disertakan (menyusul saat modul terkait / bila inquiry butuh lebih dalam):
--   rate costing (mat_coating_rate/film/sheet — pola versioned), m_tooling_asset->prod_tooling_asset,
--   m_machine, m_pantone_color, sys_constant, prod_cfg_* (template konfigurasi).
--   Ditambahkan saat costing engine & tooling digarap.
-- ============================================================
