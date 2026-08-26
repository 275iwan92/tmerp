-- ═══════════════════════════════════════════════════════════════════════════
-- PATCH_DDL_DOC_SLUG.sql — tm2sys
-- Versi SQL manual dari migration
-- database/migrations/2026_08_26_000001_add_doc_slug_and_doc_reference.php
-- (DEC-NAMING 2026-08-26, lihat CLAUDE.md §6 & docs/globalstarthere.md).
-- Jalankan ini kalau tidak lewat `php artisan migrate`. Aman diulang --
-- semua langkah cek keberadaan dulu sebelum insert/alter.
-- ═══════════════════════════════════════════════════════════════════════════

USE tm2sys;

-- ------------------------------------------------------------
-- 1. Kolom doc_slug di sys_app_menu
--    Basis nama file docs/*.md /.yaml per submodule (sumber kebenaran).
--    File aktual = {doc_slug}_tier{n}.md, {doc_slug}_state.yaml, dst.
-- ------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'tm2sys' AND TABLE_NAME = 'sys_app_menu' AND COLUMN_NAME = 'doc_slug'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE sys_app_menu ADD COLUMN doc_slug VARCHAR(80) NULL
     COMMENT ''Basis nama file docs/*.md/.yaml terkait, TANPA _tierN & ekstensi. File aktual = {doc_slug}_tier{n}.md, {doc_slug}_state.yaml, dst. Bisa sama antar >1 code (1 dok membahas >1 submodule).''',
  'SELECT ''doc_slug sudah ada, skip'' AS info'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2. Tabel baru sys_doc_reference
--    Dokumen yang BUKAN 1:1 milik satu menu item (aturan global, standar UI,
--    engine lintas-modul, referensi teknis) -- 1 baris = 1 file fisik /docs.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sys_doc_reference (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_name          VARCHAR(120) NOT NULL COMMENT 'Nama file fisik lengkap di /docs, mis. globalrules.yaml',
  title              VARCHAR(150) NOT NULL,
  category           VARCHAR(40)  NOT NULL COMMENT 'GLOBAL_RULE / STANDARD / ARCHITECTURE / ENGINE / REFERENCE / SECURITY',
  related_menu_code  VARCHAR(30)  NULL COMMENT 'Referensi lunak ke sys_app_menu.code kalau ada kaitan tak langsung (BUKAN FK -- boleh NULL)',
  description        VARCHAR(255) NULL,
  sort_order         SMALLINT     NOT NULL DEFAULT 999,
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Daftar file docs/* yang bukan milik 1 sys_app_menu item -- 1 baris = 1 file fisik';

-- ------------------------------------------------------------
-- 3. Kode HR baru (HRM belum lengkap -- scope sudah dibuka user,
--    DEC-SCOPE-OPEN 2026-08-26). id BUKAN auto_increment di tabel ini
--    (PK manual, alokasi MAX(id)+1 -- konsisten pola migration lain).
-- ------------------------------------------------------------
SET @hrm_id := (SELECT id FROM sys_app_menu WHERE code = 'HRM' LIMIT 1);

-- HRM_ATTENDANCE
SET @next_id := (SELECT IFNULL(MAX(id), 0) + 1 FROM sys_app_menu);
INSERT INTO sys_app_menu
  (id, parent_id, code, label, menu_type, object_type, target_kind, sort_order, is_active, status_code, created_at)
SELECT @next_id, @hrm_id, 'HRM_ATTENDANCE', 'Presensi', 'item', 'form', 'app', 30, 1, 'NOT_BUILT', NOW()
WHERE @hrm_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sys_app_menu WHERE code = 'HRM_ATTENDANCE');

-- HRM_PAYROLL
SET @next_id := (SELECT IFNULL(MAX(id), 0) + 1 FROM sys_app_menu);
INSERT INTO sys_app_menu
  (id, parent_id, code, label, menu_type, object_type, target_kind, sort_order, is_active, status_code, created_at)
SELECT @next_id, @hrm_id, 'HRM_PAYROLL', 'Payroll', 'item', 'form', 'app', 40, 1, 'NOT_BUILT', NOW()
WHERE @hrm_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sys_app_menu WHERE code = 'HRM_PAYROLL');

-- HRM_RECRUITMENT
SET @next_id := (SELECT IFNULL(MAX(id), 0) + 1 FROM sys_app_menu);
INSERT INTO sys_app_menu
  (id, parent_id, code, label, menu_type, object_type, target_kind, sort_order, is_active, status_code, created_at)
SELECT @next_id, @hrm_id, 'HRM_RECRUITMENT', 'Recruitment', 'item', 'form', 'app', 50, 1, 'NOT_BUILT', NOW()
WHERE @hrm_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sys_app_menu WHERE code = 'HRM_RECRUITMENT');

-- HRM_SELFSERVICE (portal karyawan SUDAH live -> route self-service.index,
-- lihat main-menu.blade.php::itemRoutes() -- status langsung READY_FOR_QC)
SET @next_id := (SELECT IFNULL(MAX(id), 0) + 1 FROM sys_app_menu);
INSERT INTO sys_app_menu
  (id, parent_id, code, label, menu_type, object_type, target_kind, sort_order, is_active, status_code, status_updated_by, status_updated_at, created_at)
SELECT @next_id, @hrm_id, 'HRM_SELFSERVICE', 'Self-Service Karyawan', 'item', 'form', 'app', 60, 1, 'READY_FOR_QC', 'claude_code', NOW(), NOW()
WHERE @hrm_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sys_app_menu WHERE code = 'HRM_SELFSERVICE');

-- ------------------------------------------------------------
-- 4. doc_slug: code dengan 1 keluarga dokumen jelas
-- ------------------------------------------------------------
UPDATE sys_app_menu SET doc_slug = 'sales'             WHERE code = 'SALES';
UPDATE sys_app_menu SET doc_slug = 'inquiryproduct'     WHERE code = 'SALES_INQ';
UPDATE sys_app_menu SET doc_slug = 'productcosting'     WHERE code = 'SALES_COST';
UPDATE sys_app_menu SET doc_slug = 'masterproduct'      WHERE code = 'MD_PROD';
UPDATE sys_app_menu SET doc_slug = 'customer'           WHERE code = 'MD_CUST';
UPDATE sys_app_menu SET doc_slug = 'procurement'        WHERE code = 'PROC';
UPDATE sys_app_menu SET doc_slug = 'purchasereceive'    WHERE code = 'PROC_RCV';
UPDATE sys_app_menu SET doc_slug = 'inventory'          WHERE code = 'INV';
UPDATE sys_app_menu SET doc_slug = 'financeaccounting'  WHERE code = 'FINACC';
UPDATE sys_app_menu SET doc_slug = 'downpayment'        WHERE code = 'FIN_DP';
UPDATE sys_app_menu SET doc_slug = 'masteremployee'     WHERE code = 'HRM_EMPLOYEE';
UPDATE sys_app_menu SET doc_slug = 'attendancepayroll'  WHERE code = 'HRM_ATTENDANCE';
UPDATE sys_app_menu SET doc_slug = 'attendancepayroll'  WHERE code = 'HRM_PAYROLL';
UPDATE sys_app_menu SET doc_slug = 'selfservice'        WHERE code = 'HRM_SELFSERVICE';

-- ------------------------------------------------------------
-- 5. Dokumen non-menu ("aturan main" / lintas-modul)
-- ------------------------------------------------------------
INSERT INTO sys_doc_reference (file_name, title, category, related_menu_code, sort_order, is_active, created_at)
SELECT * FROM (SELECT
  'globalrules.yaml' AS file_name, 'Aturan Universal Semua Modul' AS title, 'GLOBAL_RULE' AS category, NULL AS related_menu_code, 10 AS sort_order, 1 AS is_active, NOW() AS created_at
  UNION ALL SELECT 'globalstarthere.md', 'Pintu Masuk Dokumentasi (Start Here)', 'GLOBAL_RULE', NULL, 20, 1, NOW()
  UNION ALL SELECT 'globalarchitecture_tier0.md', 'Rancangan Global Arsitektur (Tier 0)', 'ARCHITECTURE', NULL, 30, 1, NOW()
  UNION ALL SELECT 'griduistandard.md', 'Standar Tampilan Grid (Master & Worklist)', 'STANDARD', NULL, 40, 1, NOW()
  UNION ALL SELECT 'sidebarmenustandard.md', 'Standar Struktur Menu Sidebar', 'STANDARD', NULL, 50, 1, NOW()
  UNION ALL SELECT 'prefixlist.md', 'Daftar Prefix Tabel (Naming Convention)', 'STANDARD', NULL, 60, 1, NOW()
  UNION ALL SELECT 'queue.md', 'Antrean Kerja Claude Code', 'GLOBAL_RULE', NULL, 70, 1, NOW()
  UNION ALL SELECT 'checklistmockuperp.md', 'Master Checklist Mockup ERP', 'GLOBAL_RULE', NULL, 80, 1, NOW()
  UNION ALL SELECT 'flowengine_tier1.md', 'Flow Engine — Business Concept & Scope (Tier 1)', 'ENGINE', NULL, 90, 1, NOW()
  UNION ALL SELECT 'flowengine_tier2.md', 'Flow Engine — Business Logic & Rules (Tier 2)', 'ENGINE', NULL, 91, 1, NOW()
  UNION ALL SELECT 'flowengine_tier3.md', 'Flow Engine — Database & Data Process Flow (Tier 3)', 'ENGINE', NULL, 92, 1, NOW()
  UNION ALL SELECT 'flowengine_tier4.md', 'Flow Engine — UI/UX & Interaction Design (Tier 4)', 'ENGINE', NULL, 93, 1, NOW()
  UNION ALL SELECT 'openingapp_tier1.md', 'Opening App — System Flow & Process Matrix (Tier 1)', 'ENGINE', 'SETUP', 100, 1, NOW()
  UNION ALL SELECT 'openingapp_tier2.md', 'Opening App — Business Logic & Rules (Tier 2)', 'ENGINE', 'SETUP', 101, 1, NOW()
  UNION ALL SELECT 'openingapp_tier3.md', 'Opening App — DDL, Relasi & Seed (Tier 3)', 'ENGINE', 'SETUP', 102, 1, NOW()
  UNION ALL SELECT 'openingapp_tier4.md', 'Opening App — Mockup & UI/UX Spec (Tier 4)', 'ENGINE', 'SETUP', 103, 1, NOW()
  UNION ALL SELECT 'openingapp_instruksi_layout.md', 'Instruksi Layout Opening App', 'ENGINE', 'SETUP', 104, 1, NOW()
  UNION ALL SELECT 'security_brief.md', 'Brief Modul Security: Login → Main Menu', 'SECURITY', 'SETUP', 110, 1, NOW()
  UNION ALL SELECT 'security_defaultpassword_spec.md', 'Spec Default Password & Must Change Password', 'SECURITY', 'SETUP', 111, 1, NOW()
  UNION ALL SELECT 'geomaster_ringkasan.md', 'Ringkasan Domain Master Wilayah (geo_)', 'REFERENCE', 'MDATA', 120, 1, NOW()
  UNION ALL SELECT 'productionprocess_lampiran_a.md', 'Urutan Proses Standar Offset & Packaging', 'REFERENCE', 'PROD', 130, 1, NOW()
  UNION ALL SELECT 'itemmaster_delta.md', 'Delta Item Master — Field Fisik & Linkage Akuntansi', 'REFERENCE', 'MD_RAW', 140, 1, NOW()
  UNION ALL SELECT 'purchasereceive_delta_multipo.md', 'Delta Purchase Receive — Multi-PO per SPB', 'REFERENCE', 'PROC_RCV', 150, 1, NOW()
  UNION ALL SELECT 'customer_fix_nav_address.md', 'Fix Master Customer — Navigasi Tab & Alamat Wilayah', 'REFERENCE', 'MD_CUST', 160, 1, NOW()
  UNION ALL SELECT 'inquiryproduct_delta_revise.md', 'Delta Revise Scope & Relasi CO Snapshot', 'REFERENCE', 'SALES_INQ', 170, 1, NOW()
  UNION ALL SELECT 'inquiryproduct_flow.md', 'Siklus Inquiry → PO → Master Product', 'REFERENCE', 'SALES_INQ', 171, 1, NOW()
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM sys_doc_reference r WHERE r.file_name = seed.file_name
);

-- ------------------------------------------------------------
-- Verifikasi cepat setelah dijalankan:
-- SELECT code, label, doc_slug FROM sys_app_menu WHERE doc_slug IS NOT NULL ORDER BY code;
-- SELECT * FROM sys_doc_reference ORDER BY sort_order;
-- ------------------------------------------------------------
