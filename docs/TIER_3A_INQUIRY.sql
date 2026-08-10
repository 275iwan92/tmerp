-- ============================================================
-- TIER 3A — MODUL INQUIRY (DDL)
-- ERP Cetak Offset & Packaging — PT. Telasih Makmur
-- Skema: tmsdi (Sales-domain transaksi) · Prefix: iqp_ (inquiry product)
-- Basis: TIER 1 v2.1 + TIER 2 v3 (bukan 3B). Pendekatan ALL-NEW.
-- ------------------------------------------------------------
-- Konvensi terkunci:
--   * Nama English, prefix per-domain.
--   * Audit: created_at/by, updated_at/by (semua transaksi).
--   * Approval: approved_by/at. Confirm/cancel: cukup flag + updated_by/at.
--   * State transisi -> doc_state_log (polymorphic), BUKAN kolom per-transisi.
--   * Snapshot: inquiry membekukan struktur produk (mirror children).
--   * Diskon: item di iqp_tier; dokumen kolom-kembar header (NULL default).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- LOOKUP domain-inquiry (nempel ke iqp_, bukan ref_ generik)
-- ============================================================
DROP TABLE IF EXISTS `iqp_type`;
CREATE TABLE `iqp_type` (
  `type_code`  VARCHAR(10)  NOT NULL          COMMENT 'NEW | RECALC | REVISE',
  `type_label` VARCHAR(50)  NOT NULL          COMMENT 'Label UI',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`type_code`) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lookup tipe inquiry. Set once di header, tak berubah';

-- ============================================================
-- 1. iqp_inquiry  (HEADER)
--    Identitas + komersial + pajak + diskon dokumen + state + lineage.
-- ============================================================
DROP TABLE IF EXISTS `iqp_inquiry`;
CREATE TABLE `iqp_inquiry` (
  `id_inquiry`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_no`        VARCHAR(20)     NOT NULL              COMMENT 'IQ[YY][NNNNN], auto, immutable',

  -- ---- identitas dasar (Tier1 §5.1) ----
  `inquiry_date`      DATE            NOT NULL,
  `source`            VARCHAR(50)     NULL DEFAULT NULL     COMMENT 'Sumber permintaan (email/WA/telp/dll)',
  `pic_sales`         VARCHAR(100)    NULL DEFAULT NULL     COMMENT 'PIC Sales penanggung jawab',
  `customer_id`       BIGINT UNSIGNED NULL DEFAULT NULL     COMMENT 'FK -> cust_customer (NULL bila prospek)',
  `is_prospect`       TINYINT(1)      NOT NULL DEFAULT 0    COMMENT '1=calon (belum jadi customer resmi)',
  `prospect_name`     VARCHAR(150)    NULL DEFAULT NULL     COMMENT 'Nama calon bila belum ada customer_id',
  `deadline_quotation` DATE           NULL DEFAULT NULL     COMMENT 'Batas terbit quotation (min +14 HK)',
  `product_draft_name` VARCHAR(150)   NOT NULL              COMMENT 'Nama produk draft (tanpa karakter khusus)',
  `sales_note`        TEXT            NULL,

  -- ---- tipe & lineage (Tier1 §5.11, Tier2 §9) ----
  `inquiry_type`      VARCHAR(10)     NOT NULL DEFAULT 'NEW' COMMENT 'FK -> iqp_type. NEW|RECALC|REVISE. Set once',
  `parent_product_id` BIGINT UNSIGNED NULL DEFAULT NULL     COMMENT 'FK -> prod_product asal (RECALC/REVISE); NULL untuk NEW',
  `revision_no`       INT UNSIGNED    NOT NULL DEFAULT 0    COMMENT 'Naik tiap versi baru',

  -- ---- komersial: sifat pekerjaan (Tier1 §5.2) ----
  `material_source`   VARCHAR(10)     NOT NULL DEFAULT 'OWN' COMMENT 'OWN | CUSTOMER. Penentu sifat CO',
  `job_nature`        VARCHAR(10)     NOT NULL DEFAULT 'PRODUCT' COMMENT 'PRODUCT | JASA (turunan material_source)',
  `cust_material_spec` TEXT           NULL                  COMMENT 'Spec bahan customer (wajib bila material_source=CUSTOMER)',
  `cust_material_eta` DATE            NULL DEFAULT NULL     COMMENT 'ETA kirim bahan customer',

  -- ---- pajak (Tier1 §5.5) ----
  `tax_scheme`        VARCHAR(10)     NOT NULL DEFAULT 'PPN' COMMENT 'PPN | NON_PPN (non-PPN dianggap include)',
  `pph_applicable`    TINYINT(1)      NOT NULL DEFAULT 0    COMMENT '1=kena PPh23 (hanya jasa)',
  `ppn_percent`       DECIMAL(6,4)    NOT NULL DEFAULT 11.0000,
  `pph_percent`       DECIMAL(6,4)    NOT NULL DEFAULT 2.0000,

  -- ---- diskon DOKUMEN (kolom-kembar dgn co_order; NULL default, jarang) ----
  `discount_doc_type`   VARCHAR(10)   NULL DEFAULT NULL     COMMENT 'PERCENT | AMOUNT (diskon amount dokumen)',
  `discount_doc_value`  DECIMAL(20,4) NULL DEFAULT NULL,
  `discount_qty_value`  DECIMAL(20,4) NULL DEFAULT NULL     COMMENT 'Diskon qty occasional',
  `discount_qty_min_ton` DECIMAL(20,4) NULL DEFAULT NULL    COMMENT 'Ambang tonase pengaktif diskon qty',

  -- ---- prove snapshot (Tier1 §5.8) ----
  `prove_final_version` INT UNSIGNED  NULL DEFAULT NULL     COMMENT 'Nomor versi prove final (snapshot cepat; detail di iqp_prove)',
  `prove_approved_date` DATE          NULL DEFAULT NULL     COMMENT 'Tgl approval prove FISIK = mulai due-date clock',
  `due_date`          DATE            NULL DEFAULT NULL     COMMENT '+14 HK dari prove_approved_date',
  `design_number`     VARCHAR(30)     NULL DEFAULT NULL     COMMENT 'Identitas artwork/proof, mis. DS003479',

  -- ---- jejak balik dokumen hilir (Tier1 §3.4) ----
  `quotation_id`      BIGINT UNSIGNED NULL DEFAULT NULL     COMMENT 'FK -> quo_quotation (many-to-one)',
  `co_id`             BIGINT UNSIGNED NULL DEFAULT NULL     COMMENT 'FK -> co_order (jejak balik via quotation)',

  -- ---- STATE (Tier2 §11.2) ----
  `sub_state`         VARCHAR(25)     NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|WAITING_COST|SUBMITTED|PRICE_CALCULATED|PRICE_APPROVED|QUOTED|PROVING|PROVE_APPROVED|CONVERTED|CANCELLED|LOST',
  `is_confirmed`      TINYINT(1)      NOT NULL DEFAULT 0    COMMENT 'Sales kunci (stat_confirm lama)',
  `is_cancelled`      TINYINT(1)      NOT NULL DEFAULT 0    COMMENT 'Hanya bila is_confirmed=1',
  `cancel_reason`     VARCHAR(255)    NULL DEFAULT NULL,
  `is_approved`       TINYINT(1)      NOT NULL DEFAULT 0    COMMENT 'Approval Direksi = publish harga',

  -- ---- audit (baseline transaksi) ----
  `approved_by`       VARCHAR(100)    NULL DEFAULT NULL     COMMENT 'Direksi yg approve',
  `approved_at`       TIMESTAMP       NULL DEFAULT NULL,
  `created_at`        TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`        VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`        VARCHAR(100)    NULL DEFAULT NULL,

  PRIMARY KEY (`id_inquiry`) USING BTREE,
  UNIQUE INDEX `uq_inquiry_no` (`inquiry_no` ASC) USING BTREE,
  INDEX `idx_inq_customer` (`customer_id` ASC) USING BTREE,
  INDEX `idx_inq_parent` (`parent_product_id` ASC) USING BTREE,
  INDEX `idx_inq_substate` (`sub_state` ASC) USING BTREE,
  INDEX `idx_inq_quotation` (`quotation_id` ASC) USING BTREE,
  CONSTRAINT `fk_inq_type` FOREIGN KEY (`inquiry_type`)
      REFERENCES `iqp_type` (`type_code`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Header inquiry product (MTO). Sumber kebenaran spec/harga/pajak/state';
-- FK ke cust_customer/prod_product/quo_quotation/co_order: ditambah saat master & modul terkait 3A final.

-- ============================================================
-- 2. iqp_tier  (tier qty berjenjang; tepat 1 UTAMA) — F2
--    Diskon ITEM ada di sini (per tier).
-- ============================================================
DROP TABLE IF EXISTS `iqp_tier`;
CREATE TABLE `iqp_tier` (
  `id_tier`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id`   BIGINT UNSIGNED NOT NULL             COMMENT 'FK -> iqp_inquiry',
  `tier_seq`     INT UNSIGNED    NOT NULL             COMMENT 'Urutan tampil tier',
  `qty`          DECIMAL(20,4)   NOT NULL             COMMENT 'Kuantitas tier (pcs)',
  `is_primary`   TINYINT(1)      NOT NULL DEFAULT 0   COMMENT '1=tier UTAMA (WAJIB tepat 1 per inquiry)',
  -- diskon item (per tier) — Tier1 §5.6
  `discount_item_type`  VARCHAR(10)   NULL DEFAULT NULL COMMENT 'PERCENT | AMOUNT (NULL=tanpa diskon)',
  `discount_item_value` DECIMAL(20,4) NULL DEFAULT NULL,
  -- harga hasil publish (diisi setelah costing PUBLISHED; buta sebelum itu)
  `price_excl`   DECIMAL(20,4)   NULL DEFAULT NULL    COMMENT 'Harga/pc excl PPN (snapshot dari costing)',
  `price_incl`   DECIMAL(20,4)   NULL DEFAULT NULL    COMMENT 'Harga/pc incl PPN',
  -- proteksi 1 UTAMA: generated col berisi inquiry_id hanya saat is_primary=1 (NULL diabaikan unique)
  `primary_key_guard` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`is_primary`=1,`inquiry_id`,NULL)) STORED,
  `created_at`   TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_tier`) USING BTREE,
  INDEX `idx_tier_inquiry` (`inquiry_id` ASC) USING BTREE,
  UNIQUE INDEX `uq_one_primary_per_inquiry` (`primary_key_guard`) USING BTREE,
  -- FK RESTRICT (bukan CASCADE): inquiry_id dipakai generated col primary_key_guard;
  -- MySQL melarang CASCADE/SET NULL pada kolom basis generated column. Cascade via aplikasi.
  CONSTRAINT `fk_tier_inquiry` FOREIGN KEY (`inquiry_id`)
      REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tier qty inquiry. 1 UTAMA dipaksa via unique index. Diskon item per tier';

-- ============================================================
-- 3. iqp_part  (part mirror — Body/Lid/Partisi) — F18, Tier1 §5.11
--    Snapshot dari m_product_parts saat inquiry dibuat.
-- ============================================================
DROP TABLE IF EXISTS `iqp_part`;
CREATE TABLE `iqp_part` (
  `id_part`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id`    BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_inquiry',
  `part_seq`      INT UNSIGNED    NOT NULL,
  `part_code`     VARCHAR(30)     NULL DEFAULT NULL,
  `part_name`     VARCHAR(100)    NOT NULL            COMMENT 'mis. Body / Lid / Partisi',
  `part_type`     VARCHAR(30)     NULL DEFAULT NULL   COMMENT 'Tipe part (acuan routing standar)',
  `qty_per_sku`   DECIMAL(20,4)   NOT NULL DEFAULT 1  COMMENT 'Jumlah part per 1 SKU jadi',
  `cfg_color`     VARCHAR(30)     NULL DEFAULT NULL   COMMENT 'Konfigurasi warna, mis. 4/4, 4/0',
  `cfg_ink`       VARCHAR(50)     NULL DEFAULT NULL,
  -- dieline snapshot (DSL) — Tier1 §5.3
  `dieline_spec`  JSON            NULL                COMMENT 'Snapshot dieline_spec DSL {canvas,flat,vars,elements}',
  `flat_w`        DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'Bentangan W (dari flat DSL) -> costing',
  `flat_h`        DECIMAL(20,4)   NULL DEFAULT NULL,
  `flat_area`     DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'Luas bentangan -> costing',
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_part`) USING BTREE,
  INDEX `idx_part_inquiry` (`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_part_inquiry` FOREIGN KEY (`inquiry_id`)
      REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Part mirror inquiry (snapshot m_product_parts). Tiap part punya dieline & bahan';

-- ============================================================
-- 4. iqp_part_material  (layer bahan per part) — Tier1 §5.11
-- ============================================================
DROP TABLE IF EXISTS `iqp_part_material`;
CREATE TABLE `iqp_part_material` (
  `id_part_material` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`       BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_part',
  `layer_seq`     INT UNSIGNED    NOT NULL            COMMENT 'Urutan layer (1 part bisa banyak lapis: duplex+SF)',
  `rawmat_id`     BIGINT UNSIGNED NULL DEFAULT NULL   COMMENT 'FK -> mat_raw (snapshot id)',
  `rawmat_name`   VARCHAR(120)    NULL DEFAULT NULL   COMMENT 'Snapshot nama bahan (jaga bila master berubah)',
  `gram_weight`   DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'Gramatur',
  `flute_type`    VARCHAR(20)     NULL DEFAULT NULL   COMMENT 'Tipe flute (corrugated)',
  `size_p`        DECIMAL(20,4)   NULL DEFAULT NULL,
  `size_l`        DECIMAL(20,4)   NULL DEFAULT NULL,
  `is_primary`    TINYINT(1)      NOT NULL DEFAULT 0  COMMENT '1=bahan utama layer',
  `material_source` VARCHAR(10)   NULL DEFAULT 'OWN'  COMMENT 'OWN|CUSTOMER per-layer (siapkan sejak Tier3; blocker B1)',
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_material`) USING BTREE,
  INDEX `idx_pmat_part` (`part_id` ASC) USING BTREE,
  CONSTRAINT `fk_pmat_part` FOREIGN KEY (`part_id`)
      REFERENCES `iqp_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Layer bahan per part (snapshot). Mendukung multi-layer (duplex+SF)';

-- ============================================================
-- 5. iqp_part_process  (proses ber-branch & flow) — Tier1 §5.11, §5.3
--    Menangkap BELAH PLANO (SPLIT) & laminasi menyatu (MERGE).
-- ============================================================
DROP TABLE IF EXISTS `iqp_part_process`;
CREATE TABLE `iqp_part_process` (
  `id_part_process` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`       BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_part',
  `branch_code`   VARCHAR(20)     NOT NULL DEFAULT 'MAIN' COMMENT 'MAIN|L|R|BODY|LID (branch produksi)',
  `step_seq`      INT UNSIGNED    NOT NULL            COMMENT 'Urutan step dalam branch',
  `flow_type`     VARCHAR(10)     NOT NULL DEFAULT 'LINEAR' COMMENT 'LINEAR|SPLIT|MERGE',
  `process_id`    BIGINT UNSIGNED NULL DEFAULT NULL   COMMENT 'FK -> prod_process (snapshot id)',
  `process_name`  VARCHAR(120)    NULL DEFAULT NULL   COMMENT 'Snapshot nama proses',
  `machine_id`    BIGINT UNSIGNED NULL DEFAULT NULL   COMMENT 'FK -> mesin (snapshot id)',
  `qty_factor`    DECIMAL(20,4)   NOT NULL DEFAULT 1  COMMENT 'Faktor qty (mis. SPLIT belah plano)',
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_part_process`) USING BTREE,
  INDEX `idx_pproc_part` (`part_id` ASC) USING BTREE,
  CONSTRAINT `fk_pproc_part` FOREIGN KEY (`part_id`)
      REFERENCES `iqp_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Proses per part, ber-branch & flow (SPLIT/MERGE). Snapshot routing';

-- ============================================================
-- 6. iqp_tooling  (line tooling: plat/pisau/film) — F6, Tier1 §5.4
-- ============================================================
DROP TABLE IF EXISTS `iqp_tooling`;
CREATE TABLE `iqp_tooling` (
  `id_tooling`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id`    BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_inquiry',
  `tooling_kind`  VARCHAR(20)     NOT NULL            COMMENT 'PLATE|PISAU|MATRAS|FILM',
  `tooling_asset_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> m_tooling_asset (bila reuse existing)',
  `tooling_status` VARCHAR(20)    NOT NULL            COMMENT 'ADA|ADA_WARNING|AUS|TIDAK_ADA',
  `is_new_charge` TINYINT(1)      NOT NULL DEFAULT 0  COMMENT '1=tooling baru -> biaya sekali (line terpisah)',
  `cost`          DECIMAL(20,4)   NOT NULL DEFAULT 0  COMMENT 'Biaya tooling baru (0 bila repeat/ADA)',
  `remark`        VARCHAR(255)    NULL DEFAULT NULL,
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_tooling`) USING BTREE,
  INDEX `idx_tool_inquiry` (`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_tool_inquiry` FOREIGN KEY (`inquiry_id`)
      REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Line tooling. AUS/TIDAK_ADA -> biaya baru terpisah, tak disembunyikan di harga/pc';

-- ============================================================
-- 7. iqp_external_cost  (biaya pihak ketiga) — F13, Tier1 §5.7
-- ============================================================
DROP TABLE IF EXISTS `iqp_external_cost`;
CREATE TABLE `iqp_external_cost` (
  `id_external_cost` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id`    BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_inquiry',
  `cost_kind`     VARCHAR(20)     NOT NULL            COMMENT 'TRUCKING|EKSPEDISI|JASA_LUAR|LAINNYA',
  `description`   VARCHAR(255)    NULL DEFAULT NULL,
  `amount`        DECIMAL(20,4)   NOT NULL DEFAULT 0,
  `tax_treatment` VARCHAR(15)     NOT NULL DEFAULT 'TAXABLE' COMMENT 'TAXABLE(masuk DPP)|REIMBURSEMENT (blocker B9)',
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_external_cost`) USING BTREE,
  INDEX `idx_ext_inquiry` (`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_ext_inquiry` FOREIGN KEY (`inquiry_id`)
      REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Biaya eksternal (trucking/ekspedisi/jasa luar). Default taxable (masuk DPP)';

-- ============================================================
-- 8. iqp_prove  (prove header 1:1) + 9. iqp_prove_log (versi) — F8, Tier1 §5.8
-- ============================================================
DROP TABLE IF EXISTS `iqp_prove`;
CREATE TABLE `iqp_prove` (
  `id_prove`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id`    BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_inquiry (UNIQUE = 1:1)',
  `design_number` VARCHAR(30)     NULL DEFAULT NULL   COMMENT 'mis. DS003479',
  `final_version` INT UNSIGNED    NULL DEFAULT NULL   COMMENT 'Versi final terpilih',
  `pdf_file`      VARCHAR(255)    NULL DEFAULT NULL   COMMENT 'Path PDF artwork siap cetak final',
  `physical_approved_date` DATE   NULL DEFAULT NULL   COMMENT 'Tgl approval fisik (tanda tangan basah)',
  `is_approved`   TINYINT(1)      NOT NULL DEFAULT 0,
  `approved_by`   VARCHAR(100)    NULL DEFAULT NULL   COMMENT 'Entry oleh officer; approval fisik oleh customer',
  `approved_at`   TIMESTAMP       NULL DEFAULT NULL,
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_prove`) USING BTREE,
  UNIQUE INDEX `uq_prove_inquiry` (`inquiry_id` ASC) USING BTREE,
  CONSTRAINT `fk_prove_inquiry` FOREIGN KEY (`inquiry_id`)
      REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Prove final per inquiry (1:1). Repeat order = inquiry baru = prove baru';

DROP TABLE IF EXISTS `iqp_prove_log`;
CREATE TABLE `iqp_prove_log` (
  `id_prove_log`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prove_id`      BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_prove',
  `version_no`    INT UNSIGNED    NOT NULL            COMMENT 'v1, v2, v3...',
  `separation`    VARCHAR(20)     NULL DEFAULT NULL   COMMENT 'Separasi CMYK+PMS',
  `pdf_file`      VARCHAR(255)    NULL DEFAULT NULL,
  `note`          VARCHAR(255)    NULL DEFAULT NULL   COMMENT 'Sebab revisi/molor (follow-up wajib)',
  `logged_at`     TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `logged_by`     VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_prove_log`) USING BTREE,
  INDEX `idx_plog_prove` (`prove_id` ASC) USING BTREE,
  CONSTRAINT `fk_plog_prove` FOREIGN KEY (`prove_id`)
      REFERENCES `iqp_prove` (`id_prove`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log versi prove (v1/v2/v3) di bawah prove header';

-- ============================================================
-- 10. iqp_costing  (HEADER HPP, MTO) — Tier2 §3.3
--     1 costing per inquiry; parameter per-tier ada di detail.
-- ============================================================
DROP TABLE IF EXISTS `iqp_costing`;
CREATE TABLE `iqp_costing` (
  `id_costing`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id`    BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_inquiry',
  `doc_status`    VARCHAR(20)     NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CALCULATED|PENDING_APPROVAL|PUBLISHED|FAILED',
  `rounding_mode` VARCHAR(20)     NULL DEFAULT NULL   COMMENT 'Mode pembulatan',
  `ppn_mode`      VARCHAR(10)     NOT NULL DEFAULT 'PPN' COMMENT 'PPN|NON_PPN (snapshot dari inquiry)',
  -- publish gate (Tier2 §3.4)
  `published_flag` TINYINT(1)     NOT NULL DEFAULT 0  COMMENT '1=harga terbuka ke Sisi B (Sales)',
  `is_approved`   TINYINT(1)      NOT NULL DEFAULT 0  COMMENT 'Approval Direksi = event publish',
  `approved_by`   VARCHAR(100)    NULL DEFAULT NULL,
  `approved_at`   TIMESTAMP       NULL DEFAULT NULL,
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_costing`) USING BTREE,
  UNIQUE INDEX `uq_costing_inquiry` (`inquiry_id` ASC) USING BTREE,
  INDEX `idx_cost_status` (`doc_status` ASC) USING BTREE,
  CONSTRAINT `fk_cost_inquiry` FOREIGN KEY (`inquiry_id`)
      REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Header costing HPP (MTO). RAHASIA (Sisi A). Publish via approve Direksi';

-- ============================================================
-- 11. iqp_costing_detail  (SATU tabel serbaguna) — Tier2 §3.3
--     Bucket per tier: MATERIAL|PROCESS|TOOLING|COATING + parameter ladder.
--     Snapshot rate WAJIB (audit) — nilai beku saat PUBLISHED.
-- ============================================================
DROP TABLE IF EXISTS `iqp_costing_detail`;
CREATE TABLE `iqp_costing_detail` (
  `id_costing_detail` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `costing_id`    BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_costing',
  `tier_id`       BIGINT UNSIGNED NOT NULL            COMMENT 'FK -> iqp_tier (parameter per tier)',
  `part_id`       BIGINT UNSIGNED NULL DEFAULT NULL   COMMENT 'FK -> iqp_part (NULL bila level tier/dokumen)',

  -- klasifikasi baris serbaguna
  `line_kind`     VARCHAR(15)     NOT NULL            COMMENT 'BUCKET|LADDER. BUCKET=komponen biaya; LADDER=parameter margin/pajak',
  `bucket_type`   VARCHAR(15)     NULL DEFAULT NULL   COMMENT 'MATERIAL|PROCESS|TOOLING|COATING|HANDLING|SPOILAGE (bila line_kind=BUCKET)',

  -- referensi & snapshot (nilai beku audit)
  `ref_id`        BIGINT UNSIGNED NULL DEFAULT NULL   COMMENT 'FK ke master sumber (mat_/prod_ rate) — id snapshot',
  `ref_name`      VARCHAR(120)    NULL DEFAULT NULL   COMMENT 'Snapshot nama komponen',
  `qty`           DECIMAL(20,4)   NULL DEFAULT NULL,
  `rate_snapshot` DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'Rate BEKU saat hitung (master boleh berubah, ini tetap)',
  `amount`        DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'qty x rate_snapshot (subtotal komponen)',

  -- ladder parameter (bila line_kind=LADDER; 1 baris/tier menampung param)
  `tolerance_pct` DECIMAL(10,4)   NULL DEFAULT NULL   COMMENT 'Toleransi %',
  `margin_pct`    DECIMAL(10,4)   NULL DEFAULT NULL   COMMENT 'Margin % (boleh beda per tier)',
  `hpp_per_pc`    DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'HPP/pc hasil Σ bucket',
  `after_margin`  DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'excl PPN setelah margin',
  `net_excl`      DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'setelah diskon item',
  `price_incl`    DECIMAL(20,4)   NULL DEFAULT NULL   COMMENT 'incl PPN (display)',
  `calc_trace`    JSON            NULL                COMMENT 'Jejak perhitungan (audit calc-trace)',

  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_costing_detail`) USING BTREE,
  INDEX `idx_cdet_costing` (`costing_id` ASC) USING BTREE,
  INDEX `idx_cdet_tier` (`tier_id` ASC) USING BTREE,
  INDEX `idx_cdet_kind` (`line_kind` ASC, `bucket_type` ASC) USING BTREE,
  CONSTRAINT `fk_cdet_costing` FOREIGN KEY (`costing_id`)
      REFERENCES `iqp_costing` (`id_costing`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cdet_tier` FOREIGN KEY (`tier_id`)
      REFERENCES `iqp_tier` (`id_tier`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cdet_part` FOREIGN KEY (`part_id`)
      REFERENCES `iqp_part` (`id_part`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detail costing SATU tabel: BUCKET (komponen biaya) + LADDER (param margin/pajak) per tier. Rate di-snapshot';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CATATAN DESAIN (untuk prompt lanjutan / Tier 3 review)
-- ============================================================
-- SNAPSHOT: iqp_part/_material/_process = mirror beku dari m_product* saat NEW/RECALC/REVISE.
--   Master berubah -> inquiry lama TIDAK ikut berubah. rate_snapshot di costing_detail idem.
-- STATE: transisi sub_state dicatat ke doc_state_log (polymorphic: doc_type='INQUIRY').
--   Flag is_confirmed/is_cancelled/is_approved = backbone teknis (Tier2 §11.2).
-- DISKON: item -> iqp_tier; dokumen (amount+qty) -> header iqp_inquiry (kolom kembar co_order).
--   Semua NULL default (jarang dipakai). Convert ke CO = copy kolom (tanpa mapping).
-- COSTING_DETAIL: 1 tabel via line_kind (BUCKET|LADDER) + bucket_type. Fleksibel, gaya lookup.
-- FK PENDING (ditambah saat master/modul lain 3A final):
--   customer_id->cust_customer, parent_product_id->prod_product, rawmat_id->mat_raw,
--   process_id->prod_process, quotation_id->quo_quotation, co_id->co_order,
--   tooling_asset_id->m_tooling_asset.
-- BLOCKER TERKAIT (Tier1 §6.2): B1 material_source per-layer (sudah disiapkan kolomnya),
--   B2 handling fee basis, B9 taxable/reimbursement (kolom tax_treatment disiapkan).
-- DI LUAR MODUL INI: quo_quotation, co_order, stock_costing (MTS) = domain/dokumen terpisah.
-- ============================================================
