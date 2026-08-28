-- ============================================================
-- SETUP DATABASE LOKAL — ERP PT. Telasih Makmur (Versi Laravel)
-- Membuat 10 database tm2* + 2 user (backupadmin, tm2user)
-- Jalankan sekali di MySQL lokal. Aman diulang (IF NOT EXISTS).
-- ============================================================

-- ------------------------------------------------------------
-- 1. BUAT 10 DATABASE (utf8mb4, InnoDB default)
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `tm2sys` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Security & Notification
CREATE DATABASE IF NOT EXISTS `tm2mst` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Master / Lookup
CREATE DATABASE IF NOT EXISTS `tm2sdi` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Sales & Distribution
CREATE DATABASE IF NOT EXISTS `tm2pur` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Procurement
CREATE DATABASE IF NOT EXISTS `tm2inv` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Inventory
CREATE DATABASE IF NOT EXISTS `tm2prd` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Production
CREATE DATABASE IF NOT EXISTS `tm2acc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Finance & Accounting (scope DIBUKA 2026-08-26, lihat DEC-SCOPE-OPEN)
CREATE DATABASE IF NOT EXISTS `tm2hrm` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- HR Master (scope DIBUKA 2026-08-26)
CREATE DATABASE IF NOT EXISTS `tm2hrr` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- HR Recruitment (scope DIBUKA 2026-08-26)
CREATE DATABASE IF NOT EXISTS `tm2pyr` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- Payroll (scope DIBUKA 2026-08-26)

-- ------------------------------------------------------------
-- 2. USER: backupadmin — administrator cadangan (full akses)
--    Setara root untuk keperluan darurat/backup.
--    GANTI '__PASSWORD__' dengan password asli sebelum jalan.
-- ------------------------------------------------------------
CREATE USER IF NOT EXISTS 'backupadmin'@'localhost' IDENTIFIED BY '__PASSWORD__';
CREATE USER IF NOT EXISTS 'backupadmin'@'%'         IDENTIFIED BY '__PASSWORD__';
GRANT ALL PRIVILEGES ON *.* TO 'backupadmin'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'backupadmin'@'%'         WITH GRANT OPTION;

-- ------------------------------------------------------------
-- 3. USER: tm2user — user aplikasi Laravel (akses 10 DB tm2*)
--    Hanya DML+DDL pada database tm2*, TIDAK punya akses global.
--    Prinsip least-privilege: user app tidak boleh super-admin.
-- ------------------------------------------------------------
CREATE USER IF NOT EXISTS 'tm2user'@'localhost' IDENTIFIED BY '__PASSWORD__';
CREATE USER IF NOT EXISTS 'tm2user'@'%'         IDENTIFIED BY '__PASSWORD__';

-- Grant per-database (bukan *.* — lebih aman)
GRANT ALL PRIVILEGES ON `tm2sys`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2mst`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2sdi`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2pur`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2inv`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2prd`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2acc`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2hrm`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2hrr`.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON `tm2pyr`.* TO 'tm2user'@'localhost';

GRANT ALL PRIVILEGES ON `tm2sys`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2mst`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2sdi`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2pur`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2inv`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2prd`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2acc`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2hrm`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2hrr`.* TO 'tm2user'@'%';
GRANT ALL PRIVILEGES ON `tm2pyr`.* TO 'tm2user'@'%';

FLUSH PRIVILEGES;

-- ------------------------------------------------------------
-- 4. VERIFIKASI (jalankan manual untuk cek)
-- ------------------------------------------------------------
-- SHOW DATABASES LIKE 'tm2%';
-- SELECT user, host FROM mysql.user WHERE user IN ('backupadmin','tm2user');
-- SHOW GRANTS FOR 'tm2user'@'localhost';
