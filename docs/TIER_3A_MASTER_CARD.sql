-- ============================================================
-- TIER 3A — MASTER CARD → MASTER PRODUCT
-- Skema: tm2mst · Prefix: prod_ · Target: MySQL 8.0
-- STATUS: TERUJI jalan di MariaDB 10.11 (sintaks kompatibel MySQL 8.0)
-- Konsep: Master Card = VIEW hasil rakit dari tabel ternormalisasi,
--         BUKAN tabel tunggal. 1 kartu = 1 product (+ N part).
-- ============================================================
USE tm2mst;

-- ------------------------------------------------------------
-- DELTA #1 — prod_product : identitas kartu
--   MySQL 8.0 TIDAK mendukung ADD COLUMN IF NOT EXISTS → tanpa IF NOT EXISTS
-- ------------------------------------------------------------
ALTER TABLE `prod_product`
  ADD COLUMN `design_no`   VARCHAR(50) NULL COMMENT 'No. Desain (DSxxxxxx) pada Master Card' AFTER `product_code`,
  ADD COLUMN `card_status` VARCHAR(15) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|APPROVED — status Master Card' AFTER `is_confirmed`,
  ADD INDEX `idx_prod_design` (`design_no`);

-- ------------------------------------------------------------
-- DELTA #2 — prod_part : field yang tercetak di kartu
-- ------------------------------------------------------------
ALTER TABLE `prod_part`
  ADD COLUMN `joint_type`   VARCHAR(30)  NULL COMMENT 'Bentuk Sambungan, mis. Double Join'          AFTER `dieline_ref`,
  ADD COLUMN `finishing`    VARCHAR(60)  NULL COMMENT 'Jenis Finishing, mis. Laminating Glossy',
  ADD COLUMN `up_per_print` VARCHAR(20)  NULL COMMENT 'Isi dalam Satu Cetakan (UP / SET)',
  ADD COLUMN `pack_qty`     INT UNSIGNED NULL COMMENT 'Pack qty (pcs/pack)';

-- ------------------------------------------------------------
-- DELTA #3 — prod_part_ink : warna per PASS per UNIT (CS/KS)
--   Menjawab lembar: "Warna Pertama CS / Warna Kedua KS" tiap unit.
--   Basis hitung jumlah plate & pass utk costing.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `prod_part_ink`;
CREATE TABLE `prod_part_ink` (
  `id_part_ink` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`     BIGINT UNSIGNED NOT NULL,
  `pass_seq`    TINYINT UNSIGNED NOT NULL       COMMENT '1=Warna Pertama(CS), 2=Warna Kedua(KS)',
  `pass_label`  VARCHAR(5)  NULL                COMMENT 'CS | KS',
  `unit_no`     TINYINT UNSIGNED NOT NULL       COMMENT 'Nomor unit pada mesin (1..n)',
  `ink_name`    VARCHAR(40) NOT NULL            COMMENT 'CYAN|MAGENTA|YELLOW|BLACK|OLIVE|GREEN|PANTONE ...',
  `plate_ref`   VARCHAR(60) NULL,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  VARCHAR(100) NULL,
  PRIMARY KEY (`id_part_ink`),
  UNIQUE KEY `uq_ink_slot` (`part_id`,`pass_seq`,`unit_no`),
  CONSTRAINT `fk_ppi_part` FOREIGN KEY (`part_id`) REFERENCES `prod_part`(`id_part`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Warna per unit per pass (CS/KS). Sumber jumlah plate & pass costing';

-- ------------------------------------------------------------
-- DELTA #4 — prod_part_artwork : versi artwork + ACC proof
--   Upload = INSERT versi baru (tak overwrite). 1 versi aktif per part.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `prod_part_artwork`;
CREATE TABLE `prod_part_artwork` (
  `id_artwork`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`        BIGINT UNSIGNED NOT NULL,
  `version_no`     INT UNSIGNED NOT NULL          COMMENT 'v1, v2, v3 ...',
  `artwork_path`   VARCHAR(255) NULL              COMMENT 'File desain (PDF/AI/PNG)',
  `artwork_thumb`  VARCHAR(255) NULL,
  `proof_required` TINYINT(1) NOT NULL DEFAULT 1  COMMENT 'ACC Proof (digital+cetak) wajib dilampirkan',
  `is_approved`    TINYINT(1) NOT NULL DEFAULT 0,
  `approved_by`    VARCHAR(100) NULL,
  `approved_at`    TIMESTAMP NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1  COMMENT 'Tepat 1 versi aktif per part',
  `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`     VARCHAR(100) NULL,
  PRIMARY KEY (`id_artwork`),
  UNIQUE KEY `uq_art_ver` (`part_id`,`version_no`),
  CONSTRAINT `fk_pa_part` FOREIGN KEY (`part_id`) REFERENCES `prod_part`(`id_part`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Versi artwork+proof per part. Part polos boleh 0 baris';

-- ------------------------------------------------------------
-- DELTA #5 — prod_mastercard_signoff : blok "Mengetahui"
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `prod_mastercard_signoff`;
CREATE TABLE `prod_mastercard_signoff` (
  `id_signoff`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `role_name`   VARCHAR(30) NOT NULL   COMMENT 'DESAINER|MARKETING|PPIC|QA|PLANT_MANAGER',
  `signed_by`   VARCHAR(100) NULL,
  `signed_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id_signoff`),
  UNIQUE KEY `uq_signrole` (`product_id`,`role_name`),
  CONSTRAINT `fk_ms_prod` FOREIGN KEY (`product_id`) REFERENCES `prod_product`(`id_product`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tanda tangan Master Card (5 role). Approval kartu = seluruh role signed';

-- ------------------------------------------------------------
-- VIEW — rekonstruksi header Master Card lintas tabel
--   Cross-DB join → selalu via VIEW (prinsip arsitektur tm2*)
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW `vw_master_card` AS
SELECT
  p.`id_product`,
  p.`product_code`  AS kode_item,
  p.`design_no`     AS no_desain,
  p.`product_name`  AS nama_barang,
  DATE(p.`created_at`) AS tanggal,
  c.`customer_name` AS nama_pelanggan,
  p.`card_status`,
  CONCAT(p.`cm_length`,' x ',p.`cm_width`,' x ',p.`cm_height`,' cm') AS dimensi,
  pt.`dieline_ref`  AS kode_pisau,
  pt.`joint_type`   AS bentuk_sambungan,
  pt.`finishing`    AS jenis_finishing,
  pt.`up_per_print` AS isi_satu_cetakan,
  CONCAT(pt.`printsize_p`,' x ',pt.`printsize_l`,' cm') AS ukuran_cetak,
  pt.`pack_qty`,
  (SELECT GROUP_CONCAT(CONCAT('U',unit_no,':',ink_name) ORDER BY unit_no SEPARATOR ' ')
     FROM prod_part_ink WHERE part_id=pt.id_part AND pass_seq=1) AS warna_cs,
  (SELECT GROUP_CONCAT(CONCAT('U',unit_no,':',ink_name) ORDER BY unit_no SEPARATOR ' ')
     FROM prod_part_ink WHERE part_id=pt.id_part AND pass_seq=2) AS warna_ks,
  (SELECT COUNT(*) FROM prod_part_material WHERE part_id=pt.id_part) AS jml_layer,
  (SELECT GROUP_CONCAT(process_name ORDER BY step_seq SEPARATOR ' > ')
     FROM prod_part_process WHERE part_id=pt.id_part) AS urutan_pekerjaan,
  (SELECT COUNT(*) FROM prod_mastercard_signoff WHERE product_id=p.id_product AND signed_at IS NOT NULL) AS signed_count
FROM `prod_product` p
JOIN `prod_part` pt ON pt.product_id=p.id_product
LEFT JOIN `cust_customer` c ON c.id_customer=p.customer_id;

-- ============================================================
-- CATATAN INTEGRITAS (enforce di Laravel + trigger opsional):
--  1. is_active prod_part_artwork: tepat 1 per part (unique partial / trigger).
--  2. card_status='APPROVED' hanya bila 5 signoff signed & artwork approved.
--  3. prod_part_ink pass_seq=2 opsional (single-pass tak wajib).
--  4. Multi-part: kartu = header product + loop N part (view di atas 1 baris/part).
-- ============================================================
