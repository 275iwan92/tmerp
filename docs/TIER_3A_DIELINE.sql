-- ============================================================
-- TIER 3A — DIELINE / SVG (Master) — tambahan modul inquiry
-- ERP Cetak Offset & Packaging — PT. Telasih Makmur
-- Skema: tm2mst · Prefix: prod_ (dieline milik domain produk)
-- ------------------------------------------------------------
-- KONSEP (dari mockup TMDieline): dieline TIDAK disimpan sebagai SVG jadi,
-- melainkan sebagai DSL JSON {canvas, vars, flat, elements[]} yang di-render
-- runtime oleh engine TMDieline. Elements = array bentuk (line/rect/circle/
-- tuck/dust/glue/bleed/foldlines/holes) dgn koordinat berbasis ekspresi.
--
-- Ini menjawab: "menambah pola SVG ke depan" = INSERT dieline_spec baru,
-- tanpa ubah kode. Engine generik, data yang bertambah.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. prod_dieline_template  (F19) — LIBRARY pola dieline reusable
--    Pusat semua "bentuk" dieline. Bisa ditambah kapan saja.
-- ============================================================
DROP TABLE IF EXISTS `prod_dieline_template`;
CREATE TABLE `prod_dieline_template` (
  `id_dieline`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dieline_code`     VARCHAR(40)  NOT NULL              COMMENT 'Kode unik pola, mis. RTE-TUCK-01',
  `dieline_name`     VARCHAR(120) NOT NULL              COMMENT 'Nama pola, mis. Reverse Tuck End',
  `box_style`        VARCHAR(40)  NULL DEFAULT NULL     COMMENT 'REVERSE_TUCK|STRAIGHT_TUCK|SLEEVE|TRAY|...',
  `ecma_code`        VARCHAR(30)  NULL DEFAULT NULL     COMMENT 'Kode standar ECMA (bila ada)',
  `svg_generator`    VARCHAR(30)  NULL DEFAULT NULL     COMMENT 'Kunci engine render (default TMDieline)',
  `dieline_spec`     JSON         NOT NULL              COMMENT 'DSL: {canvas, vars, flat{w,h}, elements[]}. Sumber gambar SVG',
  `preview_sample`   VARCHAR(255) NULL DEFAULT NULL     COMMENT 'Path SVG/PNG statis fallback (opsional)',
  `spec_version`     SMALLINT     NOT NULL DEFAULT 1    COMMENT 'Versi skema DSL (kompatibilitas engine)',
  `notes`            VARCHAR(255) NULL DEFAULT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`       VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`       VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_dieline`) USING BTREE,
  UNIQUE INDEX `uq_dieline_code` (`dieline_code` ASC) USING BTREE,
  INDEX `idx_dieline_style` (`box_style` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Library pola dieline (F19). Tambah pola baru = INSERT dieline_spec, engine generik';

-- ============================================================
-- 2. prod_type_param  — parameter dimensi per tipe produk
--    (dari mockup: m_product_type_param — P,L,T,glue,tuck,dust...)
--    Variabel yang dipakai ekspresi di dieline_spec.
--    v2 (aktual di MySQL, menggantikan versi awal di bawah ini):
--    uom->unit, sort_order->seq, + param_type/option_json/is_required/
--    default_str (dukungan param ENUM, mis. tipe jilid/gantungan kalender).
--    CATATAN: versi live TIDAK punya FK ke prod_type maupun created_by/
--    updated_by (integritas & audit jadi tanggung jawab aplikasi).
-- ============================================================
DROP TABLE IF EXISTS `prod_type_param`;
CREATE TABLE `prod_type_param` (
  `id_type_param`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_code`      VARCHAR(30)  NOT NULL               COMMENT 'ref -> prod_type (tanpa FK fisik)',
  `param_key`      VARCHAR(40)  NOT NULL               COMMENT 'ex column_name — variabel DSL, mis. product_length, glue_flap',
  `param_label`    VARCHAR(60)  NULL DEFAULT NULL      COMMENT 'Label UI',
  `unit`           VARCHAR(10)  NULL DEFAULT NULL      COMMENT 'mm, deg, pcs, %, x',
  `param_type`     VARCHAR(10)  NULL DEFAULT 'NUM'     COMMENT 'NUM | ENUM',
  `option_json`    JSON         NULL DEFAULT NULL      COMMENT 'Pilihan ENUM: [{c,l}, ...]',
  `is_required`    TINYINT(1)   NOT NULL DEFAULT 0,
  `default_value`  DECIMAL(10,2) NULL DEFAULT NULL     COMMENT 'Nilai default (param_type=NUM)',
  `default_str`    VARCHAR(60)  NULL DEFAULT NULL      COMMENT 'Nilai default (param_type=ENUM)',
  `min_value`      DECIMAL(10,2) NULL DEFAULT NULL,
  `max_value`      DECIMAL(10,2) NULL DEFAULT NULL,
  `seq`            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_type_param`) USING BTREE,
  INDEX `idx_tparam_type` (`type_code` ASC) USING BTREE,
  UNIQUE INDEX `uq_tparam_type_key` (`type_code` ASC, `param_key` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parameter dimensi per tipe (NUM/ENUM)';

-- ============================================================
-- 3. prod_type_size  — ukuran pasaran (auto-fill dimensi)
--    (dari mockup: m_product_type_size)
-- ============================================================
DROP TABLE IF EXISTS `prod_type_size`;
CREATE TABLE `prod_type_size` (
  `id_type_size` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_code`    VARCHAR(30)  NOT NULL                 COMMENT 'FK -> prod_type',
  `size_code`    VARCHAR(40)  NOT NULL                 COMMENT 'Kode ukuran pasaran',
  `size_label`   VARCHAR(100) NULL DEFAULT NULL        COMMENT 'Label, mis. "Small 8x5x2 cm"',
  `sort_order`   SMALLINT     NOT NULL DEFAULT 999,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_type_size`) USING BTREE,
  INDEX `idx_tsize_type` (`type_code` ASC) USING BTREE,
  CONSTRAINT `fk_tsize_type` FOREIGN KEY (`type_code`)
      REFERENCES `prod_type` (`type_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ukuran pasaran per tipe (dropdown auto-fill dimensi)';

-- ============================================================
-- 4. prod_type_size_val  — nilai tiap parameter untuk ukuran pasaran
--    (dari mockup: m_product_type_size_val)
--    Menghubungkan size -> nilai konkret tiap param (P=80, L=50, T=20...).
--    v2 (aktual di MySQL): param_value -> value_num (NUM) + value_str (ENUM),
--    mengikuti param_type di prod_type_param. Tanpa FK fisik / created_by/
--    updated_by, sama seperti prod_type_param v2.
-- ============================================================
DROP TABLE IF EXISTS `prod_type_size_val`;
CREATE TABLE `prod_type_size_val` (
  `id_size_val`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_size_id` BIGINT UNSIGNED NOT NULL              COMMENT 'ref -> prod_type_size (tanpa FK fisik)',
  `param_key`    VARCHAR(40)  NOT NULL                 COMMENT 'Param yang diisi (cocok prod_type_param.param_key)',
  `value_num`    DECIMAL(12,3) NULL DEFAULT NULL        COMMENT 'Nilai NUM',
  `value_str`    VARCHAR(60)  NULL DEFAULT NULL         COMMENT 'Nilai ENUM',
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_size_val`) USING BTREE,
  INDEX `idx_sizeval_size` (`type_size_id` ASC) USING BTREE,
  UNIQUE INDEX `uq_sizeval_size_param` (`type_size_id` ASC, `param_key` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Nilai parameter per ukuran (NUM/ENUM)';

-- ============================================================
-- 5. Kaitkan prod_type -> template dieline (opsional)
--    prod_type sudah punya dieline_spec sendiri; kolom ini utk refer LIBRARY.
-- ============================================================
ALTER TABLE `prod_type`
  ADD COLUMN `dieline_template_id` BIGINT UNSIGNED NULL DEFAULT NULL
      COMMENT 'FK -> prod_dieline_template (bila pakai pola dari library, bukan spec inline)'
      AFTER `dieline_spec`,
  ADD INDEX `idx_ptype_dieline_tpl` (`dieline_template_id`),
  ADD CONSTRAINT `fk_ptype_dieline_tpl` FOREIGN KEY (`dieline_template_id`)
      REFERENCES `prod_dieline_template` (`id_dieline`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CATATAN
-- ============================================================
-- ALUR DATA dieline (dari mockup):
--   prod_type (punya dieline_spec ATAU refer prod_dieline_template)
--     + prod_type_param (variabel: P,L,T,glue,tuck,dust)
--     + prod_type_size / _size_val (ukuran pasaran -> auto-fill nilai param)
--   -> TMDieline evaluasi ekspresi elements[] pakai nilai param
--   -> render SVG runtime (client-side)
--   -> iqp_part.dieline_spec = SNAPSHOT hasil (beku saat inquiry)
--
-- MENAMBAH POLA BARU KE DEPAN:
--   INSERT ke prod_dieline_template (dieline_spec DSL baru). Tak perlu ubah kode.
--   CATATAN penting: engine TMDieline harus sudah mendukung tiap 'type' element
--   di spec (line/rect/circle/tuck/dust/glue/bleed/foldlines/holes). Bentuk yang
--   pakai element di luar itu perlu tambah handler di engine, bukan cuma data.
--
-- DARI m_card LAMA: m_card TIDAK menyimpan dieline_spec (hanya dimensi +
--   layout_attachment). Tak bisa auto-import jadi pola; perlu dibuatkan DSL-nya.
-- ============================================================
