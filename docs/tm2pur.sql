
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
DROP TABLE IF EXISTS `po_import_doc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `po_import_doc` (
  `import_doc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `po_id` bigint unsigned NOT NULL,
  `i_doc_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PIB|INV|PL|BL|COO|INS|NIB|HS|PI_LARTAS|LS|SNI|SPPB|DO_FF|PAY_BM|INCO',
  `i_doc_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
  `is_received` tinyint(1) NOT NULL DEFAULT '0',
  `i_doc_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_doc_date` date DEFAULT NULL,
  `i_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`import_doc_id`),
  UNIQUE KEY `po_import_doc_po_id_i_doc_code_unique` (`po_id`,`i_doc_code`),
  CONSTRAINT `po_import_doc_po_id_foreign` FOREIGN KEY (`po_id`) REFERENCES `po_order` (`po_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `po_import_doc` WRITE;
/*!40000 ALTER TABLE `po_import_doc` DISABLE KEYS */;
/*!40000 ALTER TABLE `po_import_doc` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `po_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `po_line` (
  `po_line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `po_id` bigint unsigned NOT NULL,
  `line_no` smallint unsigned NOT NULL,
  `alloc_id` bigint unsigned DEFAULT NULL COMMENT 'FK 1:1 ke pr_line_alloc; NULL bila PO tanpa PR (kontan/darurat)',
  `item_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2mst.m_raw_material',
  `c_item_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `i_line_class` enum('RAW','INDIRECT','SERVICE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RAW' COMMENT 'diturunkan dari doc_rmprefix1.stock_class; SERVICE kena PPh23',
  `i_qty` decimal(20,4) NOT NULL,
  `i_uom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'satuan beli',
  `c_uom_stock` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_conv_to_stock` decimal(20,6) DEFAULT NULL,
  `i_price` decimal(18,4) NOT NULL COMMENT 'harga satuan dalam i_currency_id header',
  `i_disc_pct` decimal(5,2) DEFAULT NULL,
  `i_disc_amt` decimal(18,4) DEFAULT NULL,
  `c_line_net` decimal(18,4) DEFAULT NULL,
  `i_department_id` bigint unsigned DEFAULT NULL,
  `i_coa_account_id` bigint unsigned DEFAULT NULL,
  `i_co_line_id` bigint unsigned DEFAULT NULL,
  `is_stock` tinyint(1) NOT NULL DEFAULT '1',
  `is_receivable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '[PV-09] 0 = baris SERVICE, ditutup lewat PO bukan PV, tanpa gerak stok',
  `c_tol_pct` decimal(5,2) DEFAULT NULL,
  `c_qty_received` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `c_qty_outstanding` decimal(20,4) DEFAULT NULL,
  `c_line_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OPEN|PARTIALLY_RECEIVED|RECEIVED|CLOSED_BY_TOLERANCE',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`po_line_id`),
  UNIQUE KEY `po_line_po_id_line_no_unique` (`po_id`,`line_no`),
  UNIQUE KEY `uq_po_alloc` (`alloc_id`),
  KEY `po_line_item_id_index` (`item_id`),
  KEY `po_line_i_co_line_id_index` (`i_co_line_id`),
  KEY `po_line_is_receivable_index` (`is_receivable`),
  CONSTRAINT `po_line_alloc_id_foreign` FOREIGN KEY (`alloc_id`) REFERENCES `pr_line_alloc` (`alloc_id`) ON DELETE SET NULL,
  CONSTRAINT `po_line_po_id_foreign` FOREIGN KEY (`po_id`) REFERENCES `po_order` (`po_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `po_line` WRITE;
/*!40000 ALTER TABLE `po_line` DISABLE KEYS */;
INSERT INTO `po_line` VALUES (1,1,1,1,40,'IOH12','Ink OFFSET HN FINNA EMERALD GREEN IVR','RAW',10.0000,'KG','KG',1.000000,999999.0000,0.00,NULL,9999990.0000,2,132,NULL,1,1,NULL,10.0000,0.0000,'RECEIVED','admin','2026-08-20 13:59:05','admin','2026-08-20 19:55:15'),(2,1,2,2,1,'GZ00726','ANIMAL GLUE','RAW',100.0000,'PCS','PCS',1.000000,100000.0000,0.00,NULL,10000000.0000,3,132,NULL,1,1,NULL,100.0000,0.0000,'RECEIVED','admin','2026-08-20 13:59:05','admin','2026-08-20 19:55:15'),(3,2,1,3,305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI','RAW',50.0000,'LBR',NULL,NULL,25000.0000,NULL,NULL,1250000.0000,1,131,NULL,1,1,5.00,54.0000,0.0000,'RECEIVED','test_multipo','2026-08-20 19:59:13','test_multipo','2026-08-20 19:59:13'),(4,3,1,NULL,305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI','RAW',20.0000,'LBR',NULL,NULL,25000.0000,NULL,NULL,500000.0000,1,131,NULL,1,1,5.00,20.0000,0.0000,'RECEIVED','test_2po','2026-08-20 20:00:32','test_2po','2026-08-20 20:00:32'),(5,4,1,NULL,305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI','RAW',30.0000,'LBR',NULL,NULL,25000.0000,NULL,NULL,750000.0000,1,131,NULL,1,1,5.00,30.0000,0.0000,'RECEIVED','test_2po','2026-08-20 20:00:32','test_2po','2026-08-20 20:00:32'),(6,5,1,NULL,305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI','RAW',10.0000,'LBR',NULL,NULL,25000.0000,NULL,NULL,250000.0000,1,131,NULL,1,1,NULL,0.0000,NULL,NULL,'test_full_flow','2026-08-20 20:26:08','test_full_flow','2026-08-20 20:26:08');
/*!40000 ALTER TABLE `po_line` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `po_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `po_order` (
  `po_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PO/YY/##### - digenerate saat SUBMIT',
  `i_doc_date` date NOT NULL,
  `i_supplier_id` bigint unsigned NOT NULL COMMENT 'LOGICAL -> tm2mst.sup_supplier',
  `i_pq_id` bigint unsigned DEFAULT NULL COMMENT 'penawaran acuan - PQ belum dibangun, selalu NULL fase ini',
  `i_po_type` enum('LOCAL','IMPORT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LOCAL',
  `i_procure_type` enum('REPEAT','ONE_TIME','CAPEX','CASH') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'REPEAT',
  `i_currency_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2mst.fin_currency; NULL fase ini (default IDR, PQ belum ada)',
  `i_fx_rate` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `i_quotation_valid_until` date DEFAULT NULL,
  `i_payment_term` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_delivery_date` date DEFAULT NULL,
  `i_ship_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_dpp` decimal(18,4) DEFAULT NULL,
  `c_ppn` decimal(18,4) DEFAULT NULL,
  `c_pph` decimal(18,4) DEFAULT NULL,
  `c_grand_total` decimal(18,4) DEFAULT NULL,
  `c_net_payable` decimal(18,4) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|SUBMITTED|APPROVED|REJECTED|SENT|PARTIALLY_RECEIVED|RECEIVED|CLOSED|CANCELLED',
  `sent_at` datetime DEFAULT NULL,
  `is_auto_generated` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = dari Pembelian Kontan',
  `source_note` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revision_no` int unsigned NOT NULL DEFAULT '0',
  `parent_po_id` bigint unsigned DEFAULT NULL,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `cancel_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `po_order_doc_no_unique` (`doc_no`),
  KEY `po_order_status_index` (`status`),
  KEY `po_order_i_supplier_id_index` (`i_supplier_id`),
  KEY `po_order_i_doc_date_index` (`i_doc_date`),
  KEY `po_order_i_procure_type_index` (`i_procure_type`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `po_order` WRITE;
/*!40000 ALTER TABLE `po_order` DISABLE KEYS */;
INSERT INTO `po_order` VALUES (1,'PO2600001','2026-08-20',1,NULL,'LOCAL','REPEAT',NULL,1.000000,NULL,NULL,NULL,NULL,NULL,19999990.0000,2199998.9000,0.0000,22199988.9000,22199988.9000,'RECEIVED','2026-08-21 02:50:06',0,NULL,0,NULL,0,NULL,'admin','2026-08-20 13:59:05','admin','2026-08-20 19:55:15'),(2,'PO2600002','2026-08-21',1,NULL,'LOCAL','REPEAT',NULL,1.000000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'RECEIVED','2026-08-21 02:59:13',0,NULL,0,NULL,0,NULL,'test_multipo','2026-08-20 19:59:13','test_multipo','2026-08-20 19:59:13'),(3,'PO2600003','2026-08-21',1,NULL,'LOCAL','REPEAT',NULL,1.000000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'RECEIVED','2026-08-21 03:00:32',0,NULL,0,NULL,0,NULL,'test_2po','2026-08-20 20:00:32','test_2po','2026-08-20 20:00:32'),(4,'PO2600004','2026-08-21',1,NULL,'LOCAL','REPEAT',NULL,1.000000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'RECEIVED','2026-08-21 03:00:32',0,NULL,0,NULL,0,NULL,'test_2po','2026-08-20 20:00:32','test_2po','2026-08-20 20:00:32'),(5,'PO2600005','2026-08-21',1,NULL,'LOCAL','REPEAT',NULL,1.000000,NULL,NULL,NULL,NULL,NULL,250000.0000,27500.0000,0.0000,277500.0000,277500.0000,'SENT','2026-08-21 03:26:09',0,NULL,0,NULL,0,NULL,'test_full_flow','2026-08-20 20:26:08','test_full_flow','2026-08-20 20:26:09');
/*!40000 ALTER TABLE `po_order` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pq_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pq_line` (
  `pq_line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pq_id` bigint unsigned NOT NULL,
  `line_no` smallint unsigned NOT NULL,
  `pr_line_id` bigint unsigned DEFAULT NULL COMMENT 'baris PR yang ditawar, opsional',
  `item_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> m_raw_material',
  `i_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `i_qty` decimal(20,4) NOT NULL,
  `i_uom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `i_price_fx` decimal(18,4) NOT NULL COMMENT 'harga dalam mata uang penawaran',
  `c_price_idr` decimal(18,4) DEFAULT NULL,
  `c_subtotal_idr` decimal(18,4) DEFAULT NULL,
  `i_lead_time_day` smallint unsigned DEFAULT NULL,
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pq_line_id`),
  UNIQUE KEY `pq_line_pq_id_line_no_unique` (`pq_id`,`line_no`),
  KEY `pq_line_pr_line_id_index` (`pr_line_id`),
  CONSTRAINT `pq_line_pq_id_foreign` FOREIGN KEY (`pq_id`) REFERENCES `pq_quotation` (`pq_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pq_line` WRITE;
/*!40000 ALTER TABLE `pq_line` DISABLE KEYS */;
/*!40000 ALTER TABLE `pq_line` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pq_quotation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pq_quotation` (
  `pq_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_doc_date` date NOT NULL,
  `i_supplier_id` bigint unsigned NOT NULL COMMENT 'LOGICAL -> tm2mst.sup_supplier (1 PQ = 1 supplier)',
  `i_pr_id` bigint unsigned DEFAULT NULL COMMENT 'PR acuan, opsional -- PQ boleh berdiri sendiri',
  `i_quotation_valid_until` date NOT NULL,
  `i_currency_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2mst.fin_currency',
  `i_fx_rate` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `i_payment_term` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_lead_time_day` smallint unsigned DEFAULT NULL COMMENT '[DELTA mockup] estimasi lead time umum supplier utk PQ ini -- per-baris tetap ada di pq_line utk item spesial',
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_total_idr` decimal(18,4) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CONFIRMED|EXPIRED|CANCELLED',
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pq_id`),
  UNIQUE KEY `pq_quotation_doc_no_unique` (`doc_no`),
  KEY `pq_quotation_i_supplier_id_index` (`i_supplier_id`),
  KEY `pq_quotation_i_pr_id_index` (`i_pr_id`),
  KEY `pq_quotation_i_quotation_valid_until_index` (`i_quotation_valid_until`),
  KEY `pq_quotation_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pq_quotation` WRITE;
/*!40000 ALTER TABLE `pq_quotation` DISABLE KEYS */;
/*!40000 ALTER TABLE `pq_quotation` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pr_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pr_line` (
  `pr_line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pr_id` bigint unsigned NOT NULL,
  `line_no` smallint unsigned NOT NULL,
  `i_item_source` enum('MASTER_ITEM','FREE_TEXT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MASTER_ITEM',
  `item_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2mst.m_raw_material.material_id',
  `c_item_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'snapshot material_code',
  `i_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'snapshot nama item, atau teks bebas bila FREE_TEXT',
  `i_qty` decimal(20,4) NOT NULL,
  `i_uom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `i_est_price` decimal(18,4) DEFAULT NULL,
  `c_est_subtotal` decimal(18,4) DEFAULT NULL,
  `i_department_id` bigint unsigned NOT NULL COMMENT 'LOGICAL -> tm2mst.org_department (boleh beda per baris)',
  `i_coa_account_id` bigint unsigned NOT NULL COMMENT 'LOGICAL -> tm2acc.fin_account (is_postable=1)',
  `i_co_line_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2sdi co_order_item - penelusuran biaya',
  `is_stock` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 utk SERVICE/INDIRECT/FREE_TEXT',
  `i_need_date` date DEFAULT NULL,
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_qty_allocated` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `c_qty_ordered` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `c_line_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OPEN|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pr_line_id`),
  UNIQUE KEY `pr_line_pr_id_line_no_unique` (`pr_id`,`line_no`),
  KEY `pr_line_item_id_index` (`item_id`),
  KEY `pr_line_i_co_line_id_index` (`i_co_line_id`),
  CONSTRAINT `pr_line_pr_id_foreign` FOREIGN KEY (`pr_id`) REFERENCES `pr_requisition` (`pr_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pr_line` WRITE;
/*!40000 ALTER TABLE `pr_line` DISABLE KEYS */;
INSERT INTO `pr_line` VALUES (1,2,1,'MASTER_ITEM',229,'P4454','Paper HVS 58 GR 65 x 100 CM RAINBOW',500.0000,'LBR',25000.0000,12500000.0000,1,131,NULL,1,NULL,NULL,0.0000,0.0000,NULL,'seed_dummy','2026-08-20 10:16:45','seed_dummy','2026-08-20 10:16:45'),(2,2,2,'FREE_TEXT',NULL,NULL,'Sewa mesin genset cadangan (dummy)',1.0000,'Unit',3500000.0000,3500000.0000,1,611,NULL,0,NULL,NULL,0.0000,0.0000,NULL,'seed_dummy','2026-08-20 10:16:45','seed_dummy','2026-08-20 10:16:45'),(3,3,1,'MASTER_ITEM',40,'IOH12','Ink OFFSET HN FINNA EMERALD GREEN IVR',10.0000,'KG',150000.0000,1500000.0000,2,132,NULL,1,NULL,NULL,10.0000,10.0000,'FULLY_ORDERED','seed_dummy','2026-08-20 10:16:45','seed_dummy','2026-08-20 13:59:05'),(4,4,1,'MASTER_ITEM',1,'GZ00726','ANIMAL GLUE',100.0000,'PCS',100000.0000,10000000.0000,3,132,NULL,1,NULL,NULL,100.0000,100.0000,'FULLY_ORDERED','functional_test','2026-08-20 10:41:44','functional_test','2026-08-20 13:59:05'),(5,8,1,'MASTER_ITEM',305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI',50.0000,'LBR',25000.0000,1250000.0000,1,131,NULL,1,NULL,NULL,0.0000,0.0000,NULL,'test_multipo','2026-08-20 19:59:13','test_multipo','2026-08-20 19:59:13');
/*!40000 ALTER TABLE `pr_line` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pr_line_alloc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pr_line_alloc` (
  `alloc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pr_line_id` bigint unsigned NOT NULL,
  `alloc_no` smallint unsigned NOT NULL DEFAULT '1',
  `i_qty` decimal(20,4) NOT NULL COMMENT 'SUM per pr_line TIDAK BOLEH > pr_line.i_qty (validasi aplikasi)',
  `i_supplier_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2mst.sup_supplier',
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_is_converted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 bila sudah menjadi po_line',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`alloc_id`),
  UNIQUE KEY `pr_line_alloc_pr_line_id_alloc_no_unique` (`pr_line_id`,`alloc_no`),
  KEY `pr_line_alloc_c_is_converted_index` (`c_is_converted`),
  CONSTRAINT `pr_line_alloc_pr_line_id_foreign` FOREIGN KEY (`pr_line_id`) REFERENCES `pr_line` (`pr_line_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pr_line_alloc` WRITE;
/*!40000 ALTER TABLE `pr_line_alloc` DISABLE KEYS */;
INSERT INTO `pr_line_alloc` VALUES (1,3,1,10.0000,1,NULL,1,'dummy_mgrops','2026-08-20 11:36:39','admin','2026-08-20 13:59:05'),(2,4,1,100.0000,1,NULL,1,NULL,'2026-08-20 11:39:29','admin','2026-08-20 13:59:05'),(3,5,1,50.0000,NULL,NULL,0,'test_multipo','2026-08-20 19:59:13','test_multipo','2026-08-20 19:59:13');
/*!40000 ALTER TABLE `pr_line_alloc` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pr_requisition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pr_requisition` (
  `pr_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PR/YY/##### - digenerate saat SUBMIT',
  `i_doc_date` date NOT NULL,
  `i_source` enum('MANUAL','MRP') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL',
  `mrp_run_id` bigint unsigned DEFAULT NULL COMMENT 'reserved - MRP belum dibangun',
  `i_department_id` bigint unsigned NOT NULL COMMENT 'LOGICAL -> tm2mst.org_department',
  `i_need_date` date DEFAULT NULL,
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_est_total` decimal(18,4) DEFAULT NULL COMMENT 'SUM(pr_line.c_est_subtotal) - amount_snapshot approval',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|SUBMITTED|APPROVED|REJECTED|REVISION_NEEDED|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED|CANCELLED',
  `revision_no` int unsigned NOT NULL DEFAULT '0',
  `parent_pr_id` bigint unsigned DEFAULT NULL,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `cancel_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pr_id`),
  UNIQUE KEY `pr_requisition_doc_no_unique` (`doc_no`),
  KEY `pr_requisition_status_index` (`status`),
  KEY `pr_requisition_i_doc_date_index` (`i_doc_date`),
  KEY `pr_requisition_i_department_id_index` (`i_department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pr_requisition` WRITE;
/*!40000 ALTER TABLE `pr_requisition` DISABLE KEYS */;
INSERT INTO `pr_requisition` VALUES (2,NULL,'2026-08-20','MANUAL',NULL,1,'2026-08-27','Contoh PR draft (dummy data) -- stok kertas untuk lot produksi berikutnya.',16000000.0000,'DRAFT',0,NULL,0,NULL,'seed_dummy','2026-08-20 10:16:45','seed_dummy','2026-08-20 10:16:45'),(3,'PR2600001','2026-08-19','MANUAL',NULL,2,'2026-08-23','Contoh PR sudah diajukan (dummy data) -- untuk uji layar PR Approval berikutnya.',1500000.0000,'APPROVED',0,NULL,0,NULL,'seed_dummy','2026-08-20 10:16:45','seed_dummy','2026-08-20 11:36:39'),(4,'PR2600002','2026-08-20','MANUAL',NULL,3,NULL,NULL,10000000.0000,'APPROVED',0,NULL,0,NULL,'functional_test','2026-08-20 10:41:44','test_approval','2026-08-20 11:06:25'),(5,'PR2600003','2026-08-20','MANUAL',NULL,1,NULL,NULL,75000000.0000,'REVISION_NEEDED',0,NULL,0,NULL,'functional_test','2026-08-20 10:41:44','test_approval','2026-08-20 11:06:25'),(6,'PR2600004','2026-08-20','MANUAL',NULL,1,NULL,NULL,80000000.0000,'APPROVED',0,NULL,0,NULL,'test_escalation','2026-08-20 11:06:51','test_escalation','2026-08-20 11:06:51'),(7,'PR2600005','2026-08-21','MANUAL',NULL,1,NULL,NULL,NULL,'APPROVED',0,NULL,0,NULL,'test_multipo','2026-08-20 19:58:28','test_multipo','2026-08-20 19:58:28'),(8,'PR2600006','2026-08-21','MANUAL',NULL,1,NULL,NULL,NULL,'APPROVED',0,NULL,0,NULL,'test_multipo','2026-08-20 19:59:13','test_multipo','2026-08-20 19:59:13');
/*!40000 ALTER TABLE `pr_requisition` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `prt_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prt_line` (
  `prt_line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prt_id` bigint unsigned NOT NULL,
  `pv_line_id` bigint unsigned DEFAULT NULL,
  `line_no` smallint unsigned NOT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `c_item_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `i_qty` decimal(20,4) NOT NULL COMMENT 'satuan stok',
  `i_uom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `c_unit_price` decimal(18,4) DEFAULT NULL,
  `c_line_value` decimal(18,4) DEFAULT NULL,
  `is_stock_moved` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 bila qty ini pernah masuk stok -> perlu Stock Out RETURN',
  `i_lot_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement_pv_id` bigint unsigned DEFAULT NULL,
  `replacement_qty` decimal(20,4) DEFAULT NULL,
  `replacement_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PENDING|RECEIVED',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`prt_line_id`),
  UNIQUE KEY `prt_line_prt_id_line_no_unique` (`prt_id`,`line_no`),
  KEY `prt_line_pv_line_id_index` (`pv_line_id`),
  CONSTRAINT `prt_line_prt_id_foreign` FOREIGN KEY (`prt_id`) REFERENCES `prt_return` (`prt_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `prt_line` WRITE;
/*!40000 ALTER TABLE `prt_line` DISABLE KEYS */;
/*!40000 ALTER TABLE `prt_line` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `prt_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prt_return` (
  `prt_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NRB/YY/#####',
  `i_doc_date` date NOT NULL,
  `i_supplier_id` bigint unsigned NOT NULL,
  `pv_id` bigint unsigned DEFAULT NULL COMMENT 'PV asal -- NULL bila retur dari stok (i_source=FROM_STOCK)',
  `po_id` bigint unsigned DEFAULT NULL,
  `i_source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'QC_REJECT' COMMENT 'QC_REJECT|FROM_STOCK',
  `i_disposition` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'REPLACEMENT|CREDIT_NOTE',
  `i_reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `c_credit_value` decimal(18,4) DEFAULT NULL COMMENT 'nilai pengurang tagihan bila CREDIT_NOTE',
  `replacement_pv_id` bigint unsigned DEFAULT NULL COMMENT 'PV barang pengganti -- kasus sederhana 1 dok pengganti; kasus per-baris lihat prt_line.replacement_pv_id',
  `rm_stock_doc_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2inv rm_stock OUT doc_cat=RETURN, NULL sampai stok diposting manual (v1 belum otomatis)',
  `qc_inspector` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comm_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'metode komunikasi ke supplier (Email/Telp/WA/dst)',
  `supplier_pic` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `physical_return_date` date DEFAULT NULL COMMENT 'tanggal barang fisik dikirim balik ke supplier',
  `expedition` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cn_supplier_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'No. Credit Note dari supplier',
  `cn_received_date` date DEFAULT NULL,
  `replacement_due_date` date DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|SUBMITTED|CONFIRMED|COMPLETED|CANCELLED -- VARCHAR polos, SUBMITTED ditambah utk alur approval mockup (bukan di draf DDL awal)',
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`prt_id`),
  UNIQUE KEY `prt_return_doc_no_unique` (`doc_no`),
  KEY `prt_return_pv_id_index` (`pv_id`),
  KEY `prt_return_status_index` (`status`),
  KEY `prt_return_i_supplier_id_index` (`i_supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `prt_return` WRITE;
/*!40000 ALTER TABLE `prt_return` DISABLE KEYS */;
/*!40000 ALTER TABLE `prt_return` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pv_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pv_line` (
  `pv_line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pv_id` bigint unsigned NOT NULL,
  `line_no` smallint unsigned NOT NULL,
  `po_line_id` bigint unsigned NOT NULL COMMENT 'Pengikat sesungguhnya (fisik, sama-DB)',
  `c_po_id` bigint unsigned NOT NULL COMMENT '[PV-01/PV-08] snapshot po_order.po_id -- kunci rekalkulasi per PO',
  `c_po_doc_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '[PV-01] snapshot -- dicetak per baris di nota SPB',
  `c_po_line_no` smallint unsigned NOT NULL COMMENT '[PV-01] snapshot nomor baris PO asal',
  `item_id` bigint unsigned DEFAULT NULL,
  `c_item_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_stock` tinyint(1) NOT NULL DEFAULT '1',
  `i_supplier_do_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '[PV-07] SJ khusus baris ini; NULL = ikut SJ header',
  `i_qty_purchase` decimal(20,4) NOT NULL COMMENT 'satuan BELI -- menutup po_line & dasar tagihan',
  `i_uom_purchase` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `i_qty_stock` decimal(20,4) NOT NULL COMMENT 'satuan STOK aktual -- hasil hitung/timbang',
  `i_uom_stock` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `c_conv_used` decimal(20,6) DEFAULT NULL COMMENT 'i_qty_stock / i_qty_purchase -- jejak konversi nyata',
  `is_conv_manual` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = officer menimpa konversi master (barang curah)',
  `qc_qty_status` enum('PENDING','DONE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `qc_quality_status` enum('PENDING','PASS','PARTIAL','FAIL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `c_qty_accepted` decimal(20,4) DEFAULT NULL COMMENT 'satuan stok -- HANYA INI yang masuk gudang',
  `c_qty_rejected` decimal(20,4) DEFAULT NULL COMMENT 'satuan stok -- kandidat retur',
  `qc_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qc_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qc_at` datetime DEFAULT NULL,
  `c_tol_pct` decimal(5,2) DEFAULT NULL COMMENT 'snapshot po_line.c_tol_pct',
  `c_qty_variance_pct` decimal(8,4) DEFAULT NULL,
  `is_tol_exceeded` tinyint(1) NOT NULL DEFAULT '0',
  `tol_override_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tol_override_at` datetime DEFAULT NULL,
  `tol_override_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_unit_price` decimal(18,4) DEFAULT NULL COMMENT 'snapshot po_line.i_price',
  `c_line_value` decimal(18,4) DEFAULT NULL COMMENT 'c_qty_accepted * c_unit_price',
  `i_lot_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_location_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teks bebas: master lokasi gudang belum ada',
  `i_co_line_id` bigint unsigned DEFAULT NULL COMMENT 'snapshot dari po_line',
  `c_qty_invoiced` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `c_qty_returned` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pv_line_id`),
  UNIQUE KEY `pv_line_pv_id_line_no_unique` (`pv_id`,`line_no`),
  KEY `pv_line_po_line_id_index` (`po_line_id`),
  KEY `pv_line_c_po_id_index` (`c_po_id`),
  KEY `pv_line_qc_quality_status_index` (`qc_quality_status`),
  CONSTRAINT `pv_line_po_line_id_foreign` FOREIGN KEY (`po_line_id`) REFERENCES `po_line` (`po_line_id`) ON DELETE RESTRICT,
  CONSTRAINT `pv_line_pv_id_foreign` FOREIGN KEY (`pv_id`) REFERENCES `pv_receipt` (`pv_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pv_line` WRITE;
/*!40000 ALTER TABLE `pv_line` DISABLE KEYS */;
INSERT INTO `pv_line` VALUES (3,2,1,1,1,'PO2600001',1,40,'IOH12','Ink OFFSET HN FINNA EMERALD GREEN IVR',1,NULL,10.0000,'KG',10.0000,'KG',1.000000,0,'DONE','PASS',10.0000,0.0000,NULL,'test_pv','2026-08-21 02:54:53',NULL,0.0000,0,NULL,NULL,NULL,999999.0000,NULL,NULL,NULL,NULL,0.0000,0.0000,'admin','2026-08-20 19:54:53','admin','2026-08-20 19:54:53'),(4,2,2,2,1,'PO2600001',2,1,'GZ00726','ANIMAL GLUE',1,NULL,100.0000,'PCS',100.0000,'PCS',1.000000,0,'DONE','PASS',100.0000,0.0000,NULL,'test_pv','2026-08-21 02:54:53',NULL,0.0000,0,NULL,NULL,NULL,100000.0000,NULL,NULL,NULL,NULL,0.0000,0.0000,'admin','2026-08-20 19:54:53','admin','2026-08-20 19:54:53'),(5,3,1,1,1,'PO2600001',1,40,'IOH12','Ink OFFSET HN FINNA EMERALD GREEN IVR',1,NULL,10.0000,'KG',10.0000,'KG',1.000000,0,'DONE','PASS',10.0000,0.0000,NULL,'test_pv','2026-08-21 02:55:15',NULL,0.0000,0,NULL,NULL,NULL,999999.0000,NULL,NULL,NULL,NULL,0.0000,0.0000,'admin','2026-08-20 19:55:15','admin','2026-08-20 19:55:15'),(6,3,2,2,1,'PO2600001',2,1,'GZ00726','ANIMAL GLUE',1,NULL,100.0000,'PCS',100.0000,'PCS',1.000000,0,'DONE','PASS',100.0000,0.0000,NULL,'test_pv','2026-08-21 02:55:15',NULL,0.0000,0,NULL,NULL,NULL,100000.0000,NULL,NULL,NULL,NULL,0.0000,0.0000,'admin','2026-08-20 19:55:15','admin','2026-08-20 19:55:15'),(7,4,1,3,2,'PO2600002',1,305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI',1,NULL,54.0000,'LBR',54.0000,'LBR',NULL,0,'DONE','PASS',54.0000,0.0000,NULL,NULL,NULL,5.00,8.0000,1,'test_manager','2026-08-21 02:59:13','Kelebihan kirim diterima',25000.0000,NULL,NULL,NULL,NULL,0.0000,0.0000,'admin','2026-08-20 19:59:13','admin','2026-08-20 19:59:13'),(8,5,1,4,3,'PO2600003',1,305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI',1,NULL,20.0000,'LBR',20.0000,'LBR',NULL,0,'DONE','PASS',20.0000,0.0000,NULL,NULL,NULL,5.00,0.0000,0,NULL,NULL,NULL,25000.0000,NULL,NULL,NULL,NULL,0.0000,0.0000,'admin','2026-08-20 20:00:32','admin','2026-08-20 20:00:32'),(9,5,2,5,4,'PO2600004',1,305,'PYJ31','Paper Ivory 300gr 79 X 109 CM BOHUI',1,NULL,30.0000,'LBR',30.0000,'LBR',NULL,0,'DONE','PASS',30.0000,0.0000,NULL,NULL,NULL,5.00,0.0000,0,NULL,NULL,NULL,25000.0000,NULL,NULL,NULL,NULL,0.0000,0.0000,'admin','2026-08-20 20:00:32','admin','2026-08-20 20:00:32');
/*!40000 ALTER TABLE `pv_line` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pv_receipt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pv_receipt` (
  `pv_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SPB/YY/##### di mockup -- dipakai SPB+YY+##### tanpa slash utk konsisten format nomor dokumen lain di TMERP',
  `i_doc_date` date NOT NULL,
  `i_supplier_id` bigint unsigned NOT NULL COMMENT '[PV-02] KUNCI DOKUMEN -- semua baris wajib dari supplier ini (HARD BLOCK)',
  `i_warehouse_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '[PV-03] KUNCI DOKUMEN -- semua baris wajib ke gudang ini (HARD BLOCK). Teks bebas: m_warehouse belum ada (PROC-WAREHOUSE-MASTER)',
  `i_supplier_do_no` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '[PV-07] SJ utama -- boleh lebih dari satu nomor bila beberapa SJ dalam satu kiriman',
  `i_vehicle_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_po_count` smallint unsigned DEFAULT NULL COMMENT '[PV-01] ringkasan cetak/grid SAJA -- logika wajib baca pv_line',
  `c_po_doc_list` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '[PV-01] daftar no PO dipisah koma -- ringkasan cetak/grid SAJA',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|QC_PENDING|POSTED|CANCELLED',
  `posted_at` datetime DEFAULT NULL,
  `posted_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rm_stock_doc_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL -> tm2inv.rm_stock -- NULL sampai tminv legacy tersedia, lihat PROC_STATE.yaml',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pv_id`),
  UNIQUE KEY `pv_receipt_doc_no_unique` (`doc_no`),
  KEY `pv_receipt_status_index` (`status`),
  KEY `pv_receipt_i_supplier_id_index` (`i_supplier_id`),
  KEY `pv_receipt_i_doc_date_index` (`i_doc_date`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pv_receipt` WRITE;
/*!40000 ALTER TABLE `pv_receipt` DISABLE KEYS */;
INSERT INTO `pv_receipt` VALUES (2,'SPB2600001','2026-08-21',1,'GD-BB',NULL,NULL,NULL,1,'PO2600001','QC_PENDING',NULL,NULL,NULL,'admin','2026-08-20 19:54:53','admin','2026-08-20 19:54:53'),(3,'SPB2600002','2026-08-21',1,'GD-BB',NULL,NULL,NULL,1,'PO2600001','POSTED','2026-08-21 02:55:15','admin',NULL,'admin','2026-08-20 19:55:15','admin','2026-08-20 19:55:15'),(4,'SPB2600003','2026-08-21',1,'GD-BB',NULL,NULL,NULL,1,'PO2600002','POSTED','2026-08-21 02:59:13','admin',NULL,'admin','2026-08-20 19:59:13','admin','2026-08-20 19:59:13'),(5,'SPB2600004','2026-08-21',1,'GD-BB',NULL,NULL,NULL,2,'PO2600003, PO2600004','POSTED','2026-08-21 03:00:32','admin',NULL,'admin','2026-08-20 20:00:32','admin','2026-08-20 20:00:32');
/*!40000 ALTER TABLE `pv_receipt` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

