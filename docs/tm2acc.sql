
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `fin_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_account` (
  `account_id` int unsigned NOT NULL,
  `account_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('ASSET','LIABILITY','EQUITY','REVENUE','COGS','EXPENSE','OTHER_INCOME','OTHER_EXPENSE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `normal_balance` enum('DEBIT','CREDIT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `is_recon` tinyint(1) NOT NULL DEFAULT '0',
  `is_postable` tinyint(1) NOT NULL DEFAULT '1',
  `default_cc_id` int unsigned DEFAULT NULL,
  `tax_scheme_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `fin_account_account_code_unique` (`account_code`),
  KEY `idx_acc_code` (`account_code`),
  KEY `idx_acc_type` (`account_type`),
  KEY `idx_acc_parent` (`parent_id`),
  CONSTRAINT `fk_acc_parent` FOREIGN KEY (`parent_id`) REFERENCES `fin_account` (`account_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_account` WRITE;
/*!40000 ALTER TABLE `fin_account` DISABLE KEYS */;
INSERT INTO `fin_account` VALUES (100,'100000','ASET (ASSETS)','ASSET','DEBIT',NULL,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(110,'110000','ASET LANCAR','ASSET','DEBIT',100,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(111,'111100','Kas Operasional Pabrik','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(112,'111200','Bank BCA - Rekening Utama IDR','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(113,'111300','Bank Mandiri - USD (Ekspor)','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(121,'112100','Piutang Usaha - Customer Lokal','ASSET','DEBIT',110,1,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(122,'112200','Piutang Usaha - Customer Ekspor','ASSET','DEBIT',110,1,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(131,'113100','Persediaan Bahan Baku - Kertas & Board','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(132,'113200','Persediaan Bahan Baku - Tinta & Chemical','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(133,'113300','Persediaan Tooling - Pisau Pond & Deboss','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(134,'113400','Persediaan WIP (Cetak/Finishing)','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(135,'113500','Persediaan Barang Jadi (Packaging)','ASSET','DEBIT',110,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(141,'114100','PPN Masukan (Input VAT)','ASSET','DEBIT',110,0,1,13,'PPN_IN',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(142,'114200','Uang Muka PPh Pasal 22 - Kertas Mill','ASSET','DEBIT',110,0,1,13,'PPH22',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(143,'114300','Uang Muka Pembelian ke Supplier','ASSET','DEBIT',110,1,1,NULL,NULL,1,NULL,'2026-08-19 02:07:31',NULL,'2026-08-19 08:09:46'),(150,'120000','ASET TETAP PABRIK','ASSET','DEBIT',100,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(151,'121100','Mesin Cetak Offset','ASSET','DEBIT',150,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(152,'121200','Mesin Finishing (Die-Cutter/Folder Gluer)','ASSET','DEBIT',150,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(159,'121900','Akumulasi Penyusutan Mesin','ASSET','CREDIT',150,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(200,'200000','KEWAJIBAN (LIABILITIES)','LIABILITY','CREDIT',NULL,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(210,'210000','KEWAJIBAN JANGKA PENDEK','LIABILITY','CREDIT',200,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(211,'211100','Hutang Dagang - Supplier Kertas & Board','LIABILITY','CREDIT',210,1,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(212,'211200','Hutang Dagang - Supplier Tinta & Chemical','LIABILITY','CREDIT',210,1,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(213,'211300','Hutang Jasa Subkon Finishing (Laminasi/UV)','LIABILITY','CREDIT',210,1,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(221,'212100','Hutang PPN Keluaran (Output VAT)','LIABILITY','CREDIT',210,0,1,13,'PPN_OUT',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(222,'212200','Hutang PPh Pasal 23 (Jasa Subkon)','LIABILITY','CREDIT',210,0,1,13,'PPH23',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(223,'212300','Hutang PPh Pasal 22 (Industri Kertas)','LIABILITY','CREDIT',210,0,1,13,'PPH22',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(231,'213100','Beban Akrual / YMH Dibayar','LIABILITY','CREDIT',210,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(232,'213200','Uang Muka Penjualan dari Pelanggan','LIABILITY','CREDIT',210,1,1,NULL,NULL,1,NULL,'2026-08-19 02:07:31',NULL,'2026-08-19 08:09:46'),(300,'300000','EKUITAS (EQUITY)','EQUITY','CREDIT',NULL,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(311,'311000','Modal Disetor','EQUITY','CREDIT',300,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(321,'321000','Laba Ditahan','EQUITY','CREDIT',300,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(322,'322000','Laba Tahun Berjalan','EQUITY','CREDIT',300,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(400,'400000','PENDAPATAN (REVENUE)','REVENUE','CREDIT',NULL,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(411,'411000','Penjualan Packaging - Food Grade Box','REVENUE','CREDIT',400,0,1,NULL,'PPN_OUT',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(412,'412000','Penjualan Packaging - Non-Food/Commercial','REVENUE','CREDIT',400,0,1,NULL,'PPN_OUT',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(413,'413000','Pendapatan Jasa Maklon / Subkon','REVENUE','CREDIT',400,0,1,NULL,'PPN_OUT',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(421,'421000','Diskon Penjualan & Retur','REVENUE','DEBIT',400,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(500,'500000','BEBAN POKOK PENJUALAN (COGS)','COGS','DEBIT',NULL,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(511,'511000','HPP - Bahan Baku Kertas & Board','COGS','DEBIT',500,0,1,1,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(512,'512000','HPP - Tinta, Varnish & Chemical','COGS','DEBIT',500,0,1,1,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(513,'513000','HPP - Jasa Subkon Finishing External','COGS','DEBIT',500,0,1,2,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(514,'514000','HPP - Tenaga Kerja Langsung','COGS','DEBIT',500,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(515,'515000','HPP - Penyusutan Mesin Produksi','COGS','DEBIT',500,0,1,1,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(516,'516000','HPP - Listrik & Daya Pabrik','COGS','DEBIT',500,0,1,1,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(600,'600000','BEBAN OPERASIONAL (EXPENSES)','EXPENSE','DEBIT',NULL,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(611,'611000','Beban Gaji Mgmt, Sales & Admin','EXPENSE','DEBIT',600,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(612,'612000','Beban QC & Lab Warna (Delta E)','EXPENSE','DEBIT',600,0,1,6,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(613,'613000','Beban Amortisasi Pelat CTP & Artwork','EXPENSE','DEBIT',600,0,1,4,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(614,'614000','Beban Pemasaran & Komisi Sales','EXPENSE','DEBIT',600,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(615,'615000','Beban Kerugian Kertas Rusak (Waste)','EXPENSE','DEBIT',600,0,1,1,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-19 08:09:46'),(800,'800000','PENDAPATAN & BEBAN LAIN-LAIN','OTHER_INCOME','CREDIT',NULL,0,0,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(811,'811000','Selisih Kurs Transaksi','OTHER_INCOME','CREDIT',800,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(812,'812000','Beban Administrasi Bank & Bunga','OTHER_EXPENSE','DEBIT',800,0,1,NULL,NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(813,'211900','Hutang Barang Belum Ditagih (GR/IR Clearing)','LIABILITY','CREDIT',NULL,0,1,NULL,NULL,1,'_system','2026-08-21 23:06:54',NULL,'2026-08-21 23:06:54');
/*!40000 ALTER TABLE `fin_account` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_cost_center`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_cost_center` (
  `cc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cc_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cc_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cc_group` enum('PRODUCTION','SALES','PURCHASING','PREPRESS','QC','WAREHOUSE','FINANCE','TAX','GENERAL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `org_dept_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cc_id`),
  UNIQUE KEY `fin_cost_center_cc_code_unique` (`cc_code`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_cost_center` WRITE;
/*!40000 ALTER TABLE `fin_cost_center` DISABLE KEYS */;
INSERT INTO `fin_cost_center` VALUES (1,'CC-PROD-PRINT','Produksi - Cetak Offset','PRODUCTION',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(2,'CC-PROD-FINISH','Produksi - Finishing (Pond/Lem)','PRODUCTION',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(3,'CC-PRODUCTION','Produksi - Umum','PRODUCTION',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(4,'CC-PREPRESS','Prepress / CTP','PREPRESS',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(5,'CC-TOOLING','Tooling / Pisau Pond','PRODUCTION',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(6,'CC-QC-LAB','QC & Lab Warna','QC',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(7,'CC-WH-PAPER','Gudang Kertas & Board','WAREHOUSE',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(8,'CC-WH-INK','Gudang Tinta & Chemical','WAREHOUSE',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(9,'CC-WH-FG','Gudang Barang Jadi','WAREHOUSE',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(10,'CC-SALES','Penjualan','SALES',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(11,'CC-PURCHASING','Pembelian','PURCHASING',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(12,'CC-FINANCE','Keuangan','FINANCE',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(13,'CC-TAX','Perpajakan','TAX',NULL,1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08');
/*!40000 ALTER TABLE `fin_cost_center` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_dp_allocation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_dp_allocation` (
  `allocation_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dp_invoice_id` bigint unsigned NOT NULL COMMENT 'FK fin_invoice — invoice tipe *_DP',
  `final_invoice_id` bigint unsigned NOT NULL COMMENT 'FK fin_invoice — invoice final',
  `allocated_base` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT 'porsi pokok DP yang dipotong',
  `allocated_tax` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT 'porsi PPN DP yang dikreditkan',
  `allocation_date` date NOT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`allocation_id`),
  KEY `idx_dpa_dp` (`dp_invoice_id`),
  KEY `idx_dpa_final` (`final_invoice_id`),
  CONSTRAINT `fk_dpa_dp` FOREIGN KEY (`dp_invoice_id`) REFERENCES `fin_invoice` (`invoice_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_dpa_final` FOREIGN KEY (`final_invoice_id`) REFERENCES `fin_invoice` (`invoice_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_dpa_not_self` CHECK ((`dp_invoice_id` <> `final_invoice_id`))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alokasi DP ke invoice final (M:N); sisa DP = total − Σ(base+tax)';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_dp_allocation` WRITE;
/*!40000 ALTER TABLE `fin_dp_allocation` DISABLE KEYS */;
/*!40000 ALTER TABLE `fin_dp_allocation` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_invoice` (
  `invoice_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_type` enum('CUSTOMER_INVOICE','SUPPLIER_BILL','CUSTOMER_DP','SUPPLIER_DP') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_type` enum('CUSTOMER','SUPPLIER') COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `source_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `co_id` bigint unsigned DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `tax_amount` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `paid_amount` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `voucher_id` bigint unsigned DEFAULT NULL,
  `payment_status` enum('UNPAID','PARTIAL','PAID') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNPAID',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `fin_invoice_invoice_number_unique` (`invoice_number`),
  KEY `fk_inv_voucher` (`voucher_id`),
  KEY `idx_inv_partner` (`partner_type`,`partner_id`),
  KEY `idx_inv_status` (`payment_status`),
  KEY `idx_inv_co` (`co_id`),
  KEY `idx_inv_source` (`source_type`,`source_id`),
  CONSTRAINT `fk_inv_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `fin_voucher` (`voucher_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_invoice` WRITE;
/*!40000 ALTER TABLE `fin_invoice` DISABLE KEYS */;
/*!40000 ALTER TABLE `fin_invoice` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_journal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_journal` (
  `journal_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `journal_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `journal_type` enum('SALES','PURCHASE','CASH_BANK','GENERAL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`journal_id`),
  UNIQUE KEY `fin_journal_journal_code_unique` (`journal_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_journal` WRITE;
/*!40000 ALTER TABLE `fin_journal` DISABLE KEYS */;
INSERT INTO `fin_journal` VALUES (1,'CSH','Jurnal Kas Operasional Pabrik','CASH_BANK',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(2,'BNK','Jurnal Bank Utama & Ekspor','CASH_BANK',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(3,'SAL','Jurnal Penjualan Packaging','SALES',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(4,'PUR','Jurnal Pembelian Material','PURCHASE',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(5,'GEN','Jurnal Umum & Penyesuaian','GENERAL',1,NULL,'2026-08-18 18:02:08',NULL,'2026-08-18 18:02:08'),(6,'USG','Jurnal Pemakaian Bahan Baku','GENERAL',1,'_system','2026-08-21 23:06:54',NULL,NULL);
/*!40000 ALTER TABLE `fin_journal` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_partner_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_partner_account` (
  `map_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `partner_type` enum('CUSTOMER','SUPPLIER') COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `ar_account_id` int unsigned DEFAULT NULL,
  `ap_account_id` int unsigned DEFAULT NULL,
  `payment_term` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NET30',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `uq_partner` (`partner_type`,`partner_id`),
  KEY `fk_pa_ar` (`ar_account_id`),
  KEY `fk_pa_ap` (`ap_account_id`),
  CONSTRAINT `fk_pa_ap` FOREIGN KEY (`ap_account_id`) REFERENCES `fin_account` (`account_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pa_ar` FOREIGN KEY (`ar_account_id`) REFERENCES `fin_account` (`account_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_partner_account` WRITE;
/*!40000 ALTER TABLE `fin_partner_account` DISABLE KEYS */;
/*!40000 ALTER TABLE `fin_partner_account` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_profit_center`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_profit_center` (
  `pc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pc_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'mis. ''PC-PACKAGING'', ''PC-COMMERCIAL''',
  `pc_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pc_id`),
  UNIQUE KEY `fin_profit_center_pc_code_unique` (`pc_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_profit_center` WRITE;
/*!40000 ALTER TABLE `fin_profit_center` DISABLE KEYS */;
INSERT INTO `fin_profit_center` VALUES (1,'PC-PACKAGING','Packaging',1,NULL,'2026-08-20 15:07:02',NULL,'2026-08-20 15:07:02'),(2,'PC-COMMERCIAL','Commercial Print',1,NULL,'2026-08-20 15:07:02',NULL,'2026-08-20 15:07:02');
/*!40000 ALTER TABLE `fin_profit_center` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_voucher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_voucher` (
  `voucher_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `journal_id` bigint unsigned NOT NULL,
  `voucher_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BKM|BKK|BBM|BBK',
  `main_account_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> fin_account, akun kas/bank utama voucher ini',
  `entry_date` date NOT NULL,
  `posting_date` date NOT NULL,
  `doc_type` enum('NEW','RECALC','REVISE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NEW',
  `revision_no` int NOT NULL DEFAULT '0',
  `source_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('DRAFT','POSTED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `cancel_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posted_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `ppn_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NONE|MASUKAN_11|KELUARAN_11',
  `efaktur_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pph_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NONE|PPH23_2|PPH4_2_10|PPH21',
  `bupot_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`voucher_id`),
  UNIQUE KEY `fin_voucher_voucher_number_unique` (`voucher_number`),
  KEY `fk_vch_journal` (`journal_id`),
  KEY `idx_vch_posting` (`posting_date`),
  KEY `idx_vch_source` (`source_type`,`source_id`),
  KEY `idx_vch_status` (`status`),
  KEY `idx_vch_type` (`voucher_type`),
  CONSTRAINT `fk_vch_journal` FOREIGN KEY (`journal_id`) REFERENCES `fin_journal` (`journal_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_voucher` WRITE;
/*!40000 ALTER TABLE `fin_voucher` DISABLE KEYS */;
/*!40000 ALTER TABLE `fin_voucher` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fin_voucher_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_voucher_line` (
  `line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_id` bigint unsigned NOT NULL,
  `account_id` int unsigned NOT NULL,
  `partner_type` enum('CUSTOMER','SUPPLIER') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` bigint unsigned DEFAULT NULL,
  `cc_id` bigint unsigned DEFAULT NULL,
  `co_id` bigint unsigned DEFAULT NULL,
  `debit` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `credit` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `narration` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`line_id`),
  KEY `fk_vl_cc` (`cc_id`),
  KEY `idx_vl_voucher` (`voucher_id`),
  KEY `idx_vl_account` (`account_id`),
  KEY `idx_vl_partner` (`partner_type`,`partner_id`),
  KEY `idx_vl_co` (`co_id`),
  CONSTRAINT `fk_vl_account` FOREIGN KEY (`account_id`) REFERENCES `fin_account` (`account_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_vl_cc` FOREIGN KEY (`cc_id`) REFERENCES `fin_cost_center` (`cc_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_vl_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `fin_voucher` (`voucher_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_dr_cr` CHECK ((((`debit` > 0) and (`credit` = 0)) or ((`credit` > 0) and (`debit` = 0)) or ((`debit` = 0) and (`credit` = 0))))
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fin_voucher_line` WRITE;
/*!40000 ALTER TABLE `fin_voucher_line` DISABLE KEYS */;
/*!40000 ALTER TABLE `fin_voucher_line` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `v1_fin_account_tree`;
/*!50001 DROP VIEW IF EXISTS `v1_fin_account_tree`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v1_fin_account_tree` AS SELECT 
 1 AS `account_id`,
 1 AS `account_code`,
 1 AS `account_name`,
 1 AS `account_type`,
 1 AS `normal_balance`,
 1 AS `parent_id`,
 1 AS `is_recon`,
 1 AS `is_postable`,
 1 AS `default_cc_id`,
 1 AS `tax_scheme_code`,
 1 AS `is_active`,
 1 AS `tree_level`,
 1 AS `default_cc_code`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v1_fin_ap_aging`;
/*!50001 DROP VIEW IF EXISTS `v1_fin_ap_aging`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v1_fin_ap_aging` AS SELECT 
 1 AS `invoice_id`,
 1 AS `invoice_number`,
 1 AS `partner_id`,
 1 AS `source_type`,
 1 AS `source_id`,
 1 AS `invoice_date`,
 1 AS `due_date`,
 1 AS `total_amount`,
 1 AS `paid_amount`,
 1 AS `outstanding`,
 1 AS `days_overdue`,
 1 AS `payment_status`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v1_fin_ar_aging`;
/*!50001 DROP VIEW IF EXISTS `v1_fin_ar_aging`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v1_fin_ar_aging` AS SELECT 
 1 AS `invoice_id`,
 1 AS `invoice_number`,
 1 AS `partner_id`,
 1 AS `co_id`,
 1 AS `invoice_date`,
 1 AS `due_date`,
 1 AS `total_amount`,
 1 AS `paid_amount`,
 1 AS `outstanding`,
 1 AS `days_overdue`,
 1 AS `payment_status`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v1_fin_dp_outstanding`;
/*!50001 DROP VIEW IF EXISTS `v1_fin_dp_outstanding`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v1_fin_dp_outstanding` AS SELECT 
 1 AS `dp_invoice_id`,
 1 AS `invoice_number`,
 1 AS `partner_id`,
 1 AS `co_id`,
 1 AS `dp_base`,
 1 AS `dp_tax`,
 1 AS `dp_total`,
 1 AS `used_base`,
 1 AS `used_tax`,
 1 AS `dp_remaining`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v1_fin_trial_balance`;
/*!50001 DROP VIEW IF EXISTS `v1_fin_trial_balance`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v1_fin_trial_balance` AS SELECT 
 1 AS `account_id`,
 1 AS `account_code`,
 1 AS `account_name`,
 1 AS `account_type`,
 1 AS `normal_balance`,
 1 AS `total_debit`,
 1 AS `total_credit`,
 1 AS `balance`*/;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v1_fin_account_tree`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v1_fin_account_tree` AS select `a`.`account_id` AS `account_id`,`a`.`account_code` AS `account_code`,`a`.`account_name` AS `account_name`,`a`.`account_type` AS `account_type`,`a`.`normal_balance` AS `normal_balance`,`a`.`parent_id` AS `parent_id`,`a`.`is_recon` AS `is_recon`,`a`.`is_postable` AS `is_postable`,`a`.`default_cc_id` AS `default_cc_id`,`a`.`tax_scheme_code` AS `tax_scheme_code`,`a`.`is_active` AS `is_active`,(case when (`a`.`parent_id` is null) then 1 when (`p`.`parent_id` is null) then 2 else 3 end) AS `tree_level`,`cc`.`cc_code` AS `default_cc_code` from ((`fin_account` `a` left join `fin_account` `p` on((`p`.`account_id` = `a`.`parent_id`))) left join `fin_cost_center` `cc` on((`cc`.`cc_id` = `a`.`default_cc_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v1_fin_ap_aging`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v1_fin_ap_aging` AS select `i`.`invoice_id` AS `invoice_id`,`i`.`invoice_number` AS `invoice_number`,`i`.`partner_id` AS `partner_id`,`i`.`source_type` AS `source_type`,`i`.`source_id` AS `source_id`,`i`.`invoice_date` AS `invoice_date`,`i`.`due_date` AS `due_date`,`i`.`total_amount` AS `total_amount`,`i`.`paid_amount` AS `paid_amount`,(`i`.`total_amount` - `i`.`paid_amount`) AS `outstanding`,(to_days(curdate()) - to_days(`i`.`due_date`)) AS `days_overdue`,`i`.`payment_status` AS `payment_status` from `fin_invoice` `i` where ((`i`.`invoice_type` = 'SUPPLIER_BILL') and (`i`.`payment_status` <> 'PAID')) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v1_fin_ar_aging`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v1_fin_ar_aging` AS select `i`.`invoice_id` AS `invoice_id`,`i`.`invoice_number` AS `invoice_number`,`i`.`partner_id` AS `partner_id`,`i`.`co_id` AS `co_id`,`i`.`invoice_date` AS `invoice_date`,`i`.`due_date` AS `due_date`,`i`.`total_amount` AS `total_amount`,`i`.`paid_amount` AS `paid_amount`,(`i`.`total_amount` - `i`.`paid_amount`) AS `outstanding`,(to_days(curdate()) - to_days(`i`.`due_date`)) AS `days_overdue`,`i`.`payment_status` AS `payment_status` from `fin_invoice` `i` where ((`i`.`invoice_type` = 'CUSTOMER_INVOICE') and (`i`.`payment_status` <> 'PAID')) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v1_fin_dp_outstanding`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v1_fin_dp_outstanding` AS select `d`.`invoice_id` AS `dp_invoice_id`,`d`.`invoice_number` AS `invoice_number`,`d`.`partner_id` AS `partner_id`,`d`.`co_id` AS `co_id`,`d`.`subtotal` AS `dp_base`,`d`.`tax_amount` AS `dp_tax`,`d`.`total_amount` AS `dp_total`,coalesce(sum(`a`.`allocated_base`),0) AS `used_base`,coalesce(sum(`a`.`allocated_tax`),0) AS `used_tax`,(`d`.`total_amount` - coalesce(sum((`a`.`allocated_base` + `a`.`allocated_tax`)),0)) AS `dp_remaining` from (`fin_invoice` `d` left join `fin_dp_allocation` `a` on((`a`.`dp_invoice_id` = `d`.`invoice_id`))) where (`d`.`invoice_type` in ('CUSTOMER_DP','SUPPLIER_DP')) group by `d`.`invoice_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v1_fin_trial_balance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v1_fin_trial_balance` AS select `a`.`account_id` AS `account_id`,`a`.`account_code` AS `account_code`,`a`.`account_name` AS `account_name`,`a`.`account_type` AS `account_type`,`a`.`normal_balance` AS `normal_balance`,coalesce(sum(`l`.`debit`),0) AS `total_debit`,coalesce(sum(`l`.`credit`),0) AS `total_credit`,(coalesce(sum(`l`.`debit`),0) - coalesce(sum(`l`.`credit`),0)) AS `balance` from ((`fin_account` `a` left join `fin_voucher_line` `l` on((`l`.`account_id` = `a`.`account_id`))) left join `fin_voucher` `v` on(((`v`.`voucher_id` = `l`.`voucher_id`) and (`v`.`status` = 'POSTED') and (`v`.`is_cancelled` = 0)))) where (`a`.`is_postable` = 1) group by `a`.`account_id`,`a`.`account_code`,`a`.`account_name`,`a`.`account_type`,`a`.`normal_balance` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

