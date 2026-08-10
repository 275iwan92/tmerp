-- ============================================================
-- TIER 3A — DDL geo_ (Master Wilayah) + fin_currency
-- ERP Cetak Offset & Packaging — PT. Telasih Makmur
-- Pendekatan: ALL-NEW ideal. Nama English. Comment lengkap.
-- Skema: tmmst (master netral, shared lintas modul)
-- ============================================================
-- Hierarki recursive ber-id (FK antar-level pakai id, BUKAN teks):
--   geo_country -> geo_region -> geo_city -> geo_district [-> geo_village opsional]
-- 1 ID area = alamat lengkap otomatis via VIEW v_geo_full_address.
-- city_type (KOTA|KABUPATEN) menyelesaikan kasus Malang (nama sama, jenis beda).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. fin_currency  (mata uang — domain finance)
--    Currency berdiri lebih dulu karena di-refer oleh geo_country.
-- ============================================================
DROP TABLE IF EXISTS `fin_currency`;
CREATE TABLE `fin_currency` (
  `id_currency`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `currency_code` VARCHAR(5)   NOT NULL                COMMENT 'Kode ISO 4217, mis. IDR, USD, EUR',
  `currency_name` VARCHAR(50)  NULL DEFAULT NULL       COMMENT 'Nama mata uang, mis. RUPIAH',
  `symbol`        VARCHAR(10)  NULL DEFAULT NULL        COMMENT 'Simbol tampil, mis. Rp, $, €',
  `counted_word`  VARCHAR(50)  NULL DEFAULT NULL        COMMENT 'Sebutan satuan (terbilang), mis. RUPIAH',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1       COMMENT '1=aktif dipilih | 0=nonaktif',
  `created_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_currency`) USING BTREE,
  UNIQUE INDEX `uq_currency_code` (`currency_code` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master mata uang (ISO 4217). Di-refer oleh geo_country.currency_id';
-- CATATAN: kolom `country` & `tax_percentage` dari m_currency lama SENGAJA DIBUANG.
--   - country  -> arah relasi dibalik: geo_country yang menunjuk currency (lihat §2).
--   - tax_percentage -> pindah ke geo_country (pajak = properti negara, bukan mata uang).

-- ============================================================
-- 2. geo_country  (Level 0 — negara)
-- ============================================================
DROP TABLE IF EXISTS `geo_country`;
CREATE TABLE `geo_country` (
  `id_country`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_code`    VARCHAR(5)      NULL DEFAULT NULL      COMMENT 'Kode negara 2-huruf (ISO 3166-1 alpha-2), mis. ID',
  `iso_code`        VARCHAR(5)      NULL DEFAULT NULL      COMMENT 'Kode 3-huruf (ISO 3166-1 alpha-3), mis. IDN',
  `country_name`    VARCHAR(100)    NOT NULL               COMMENT 'Nama negara',
  `capital_name`    VARCHAR(100)    NULL DEFAULT NULL      COMMENT 'Ibu kota',
  `call_country_no` VARCHAR(50)     NULL DEFAULT NULL      COMMENT 'Kode telepon internasional, mis. 62',
  `land_area_km2`   DECIMAL(20,2) UNSIGNED NULL DEFAULT NULL COMMENT 'Luas daratan (km2)',
  `currency_id`     INT UNSIGNED    NULL DEFAULT NULL      COMMENT 'FK -> fin_currency. Mata uang resmi negara (1 negara->1 currency)',
  `tax_percentage`  DECIMAL(10,4)   NULL DEFAULT NULL      COMMENT 'Default tax negara (pindahan dari m_currency lama)',
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1     COMMENT '1=aktif | 0=nonaktif',
  `created_at`      TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`      VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`      VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_country`) USING BTREE,
  UNIQUE INDEX `uq_country_name` (`country_name` ASC) USING BTREE,
  UNIQUE INDEX `uq_country_code` (`country_code` ASC) USING BTREE,
  INDEX `idx_country_currency` (`currency_id` ASC) USING BTREE,
  CONSTRAINT `fk_country_currency` FOREIGN KEY (`currency_id`)
      REFERENCES `fin_currency` (`id_currency`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master negara (Level 0 hierarki geo)';

-- ============================================================
-- 3. geo_region  (Level 1 — provinsi / negara bagian / state)
-- ============================================================
DROP TABLE IF EXISTS `geo_region`;
CREATE TABLE `geo_region` (
  `id_region`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_id`  BIGINT UNSIGNED NOT NULL               COMMENT 'FK -> geo_country',
  `region_code` VARCHAR(20)     NULL DEFAULT NULL       COMMENT 'Kode wilayah (opsional)',
  `region_name` VARCHAR(120)    NOT NULL               COMMENT 'Nama provinsi/state, mis. JAWA TIMUR',
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`  VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`  VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_region`) USING BTREE,
  INDEX `idx_region_country` (`country_id` ASC) USING BTREE,
  UNIQUE INDEX `uq_region_country_name` (`country_id` ASC, `region_name` ASC) USING BTREE,
  CONSTRAINT `fk_region_country` FOREIGN KEY (`country_id`)
      REFERENCES `geo_country` (`id_country`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master provinsi/state (Level 1). FK ber-id ke country';

-- ============================================================
-- 4. geo_city  (Level 2 — kota / kabupaten)
--    city_type = KUNCI kasus Malang: KOTA vs KABUPATEN (nama sama, id beda).
-- ============================================================
DROP TABLE IF EXISTS `geo_city`;
CREATE TABLE `geo_city` (
  `id_city`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` BIGINT UNSIGNED NOT NULL                 COMMENT 'FK -> geo_region',
  `city_code` VARCHAR(20)     NULL DEFAULT NULL         COMMENT 'Kode wilayah (mis. 35.07)',
  `city_type` VARCHAR(20)     NULL DEFAULT NULL         COMMENT 'KOTA|KABUPATEN (Indonesia) | CITY (luar negeri). Pembeda Malang',
  `city_name` VARCHAR(80)     NOT NULL                 COMMENT 'Nama kota/kabupaten, mis. Malang',
  `post_code` VARCHAR(100)    NULL DEFAULT NULL         COMMENT 'Rentang kode pos, mis. 65151 - 65393',
  `is_active` TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100)   NULL DEFAULT NULL,
  `updated_by` VARCHAR(100)   NULL DEFAULT NULL,
  PRIMARY KEY (`id_city`) USING BTREE,
  INDEX `idx_city_region` (`region_id` ASC) USING BTREE,
  -- unik per (region, type, name): "KOTA Malang" & "KABUPATEN Malang" boleh koeksis
  UNIQUE INDEX `uq_city_region_type_name` (`region_id` ASC, `city_type` ASC, `city_name` ASC) USING BTREE,
  CONSTRAINT `fk_city_region` FOREIGN KEY (`region_id`)
      REFERENCES `geo_region` (`id_region`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master kota/kabupaten (Level 2). city_type membedakan KOTA vs KABUPATEN';

-- ============================================================
-- 5. geo_district  (Level 3 — kecamatan)
--    Data TIDAK ada di existing; diisi via import (BPS/Kemendagri), bukan manual.
-- ============================================================
DROP TABLE IF EXISTS `geo_district`;
CREATE TABLE `geo_district` (
  `id_district`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_id`       BIGINT UNSIGNED NOT NULL             COMMENT 'FK -> geo_city',
  `district_code` VARCHAR(20)     NULL DEFAULT NULL     COMMENT 'Kode kecamatan (opsional)',
  `district_name` VARCHAR(100)    NOT NULL             COMMENT 'Nama kecamatan, mis. Klojen',
  `post_code`     VARCHAR(20)     NULL DEFAULT NULL     COMMENT 'Kode pos kecamatan (opsional)',
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
  `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
  PRIMARY KEY (`id_district`) USING BTREE,
  INDEX `idx_district_city` (`city_id` ASC) USING BTREE,
  UNIQUE INDEX `uq_district_city_name` (`city_id` ASC, `district_name` ASC) USING BTREE,
  CONSTRAINT `fk_district_city` FOREIGN KEY (`city_id`)
      REFERENCES `geo_city` (`id_city`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master kecamatan (Level 3). Diisi via import resmi, bukan input manual';

-- ============================================================
-- 6. geo_village  (Level 4 — kelurahan/desa) — OPSIONAL (5-level)
--    Aktifkan hanya bila keputusan pending #1 = "5-level". ~80rb+ baris.
-- ============================================================
-- DROP TABLE IF EXISTS `geo_village`;
-- CREATE TABLE `geo_village` (
--   `id_village`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
--   `district_id`   BIGINT UNSIGNED NOT NULL             COMMENT 'FK -> geo_district',
--   `village_code`  VARCHAR(20)     NULL DEFAULT NULL,
--   `village_name`  VARCHAR(100)    NOT NULL             COMMENT 'Nama kelurahan/desa',
--   `post_code`     VARCHAR(20)     NULL DEFAULT NULL,
--   `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
--   `created_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
--   `updated_at`    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   `created_by`    VARCHAR(100)    NULL DEFAULT NULL,
--   `updated_by`    VARCHAR(100)    NULL DEFAULT NULL,
--   PRIMARY KEY (`id_village`) USING BTREE,
--   INDEX `idx_village_district` (`district_id` ASC) USING BTREE,
--   UNIQUE INDEX `uq_village_district_name` (`district_id` ASC, `village_name` ASC) USING BTREE,
--   CONSTRAINT `fk_village_district` FOREIGN KEY (`district_id`)
--       REFERENCES `geo_district` (`id_district`) ON DELETE RESTRICT ON UPDATE CASCADE
-- ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
--   COMMENT='Master kelurahan/desa (Level 4, opsional 5-level)';

-- ============================================================
-- 7. VIEW v_geo_full_address
--    Dropdown "1x pilih ID = alamat lengkap".
--    Grain = kecamatan; kota tanpa kecamatan tetap tampil (LEFT JOIN).
-- ============================================================
DROP VIEW IF EXISTS `v_geo_full_address`;
CREATE VIEW `v_geo_full_address` AS
SELECT
  d.id_district,
  c.id_city,
  r.id_region,
  ct.id_country,
  CONCAT(
    ct.country_name, ' > ',
    r.region_name, ' > ',
    COALESCE(CONCAT(c.city_type, ' '), ''), c.city_name,
    IF(d.district_name IS NULL, '', CONCAT(' > ', d.district_name))
  ) AS full_label,                 -- "INDONESIA > JAWA TIMUR > KOTA Malang > Klojen"
  ct.country_name,
  r.region_name,
  c.city_type,
  c.city_name,
  d.district_name
FROM `geo_city` c
JOIN `geo_region`  r  ON r.id_region   = c.region_id
JOIN `geo_country` ct ON ct.id_country = r.country_id
LEFT JOIN `geo_district` d ON d.city_id = c.id_city;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CATATAN PEMAKAIAN (untuk DBA / prompt lanjutan)
-- ============================================================
-- - Master (customer/supplier/dll) simpan 1 kolom: city_id ATAU district_id.
--   Dari 1 id itu, JOIN ke v_geo_full_address -> seluruh hierarki tampil.
-- - Dropdown bertingkat: WHERE country_id=? -> region; WHERE region_id=? -> city; dst.
-- - Migrasi dari m_place_area (47.821 baris, hierarki teks) ke struktur ber-id:
--     1) geo_region  <- DISTINCT region_name per country
--     2) geo_city    <- area_name + area_type (bawa area_type -> city_type; ini pembeda Malang)
--     3) geo_district<- IMPORT terpisah (emsifa/api-wilayah-indonesia); TIDAK ada di existing.
-- - Global (luar negeri): dr5hn/countries-states-cities-database (tergantung keputusan pending #2).
-- - PENDING: (1) 4 vs 5 level, (2) cakupan luar negeri, (3) sekali-download vs live-sync.
-- ============================================================
