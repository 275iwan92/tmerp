-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: tm2prd
-- ------------------------------------------------------
-- Server version	8.0.46

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

--
-- Table structure for table `prd_breakdown_event`
--

DROP TABLE IF EXISTS `prd_breakdown_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_breakdown_event` (
  `event_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operation_id` bigint unsigned NOT NULL,
  `machine_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tm2mst.prod_machine',
  `reported_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reported_at` datetime NOT NULL,
  `downtime_estimate_minutes` int unsigned DEFAULT NULL,
  `reroute_decision` enum('MACHINE_ALT','SUBCONTRACT') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_machine_id` bigint unsigned DEFAULT NULL COMMENT 'Bila reroute_decision=MACHINE_ALT',
  `subcontract_vendor_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2mst.sup_supplier, bila SUBCONTRACT',
  `subcontract_po_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2pur.po_header (po_type=SUBCONTRACT)',
  `subcontract_due_out` datetime DEFAULT NULL,
  `subcontract_due_return` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `prd_breakdown_event_operation_id_foreign` (`operation_id`),
  KEY `idx_prd_breakdown_machine` (`machine_id`),
  CONSTRAINT `prd_breakdown_event_operation_id_foreign` FOREIGN KEY (`operation_id`) REFERENCES `prd_wo_operation` (`operation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_breakdown_event`
--

LOCK TABLES `prd_breakdown_event` WRITE;
/*!40000 ALTER TABLE `prd_breakdown_event` DISABLE KEYS */;
INSERT INTO `prd_breakdown_event` VALUES (1,5,5,'system','2026-08-28 10:30:00',180,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-27 10:43:32');
/*!40000 ALTER TABLE `prd_breakdown_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prd_crew_assignment`
--

DROP TABLE IF EXISTS `prd_crew_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_crew_assignment` (
  `assignment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operation_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tm2mst.emp_employee',
  `crew_role` enum('OPERATOR_UTAMA','CO_OPERATOR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uk_prd_crew_op_employee` (`operation_id`,`employee_id`),
  KEY `idx_prd_crew_employee` (`employee_id`),
  CONSTRAINT `prd_crew_assignment_operation_id_foreign` FOREIGN KEY (`operation_id`) REFERENCES `prd_wo_operation` (`operation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_crew_assignment`
--

LOCK TABLES `prd_crew_assignment` WRITE;
/*!40000 ALTER TABLE `prd_crew_assignment` DISABLE KEYS */;
INSERT INTO `prd_crew_assignment` VALUES (1,1,1,'OPERATOR_UTAMA','system','2026-08-27 17:43:32'),(2,1,363,'CO_OPERATOR','system','2026-08-27 17:43:32'),(3,3,279,'OPERATOR_UTAMA','system','2026-08-27 17:43:32'),(4,6,159,'OPERATOR_UTAMA','system','2026-08-27 17:43:32'),(5,7,137,'OPERATOR_UTAMA','system','2026-08-27 17:43:32');
/*!40000 ALTER TABLE `prd_crew_assignment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prd_decision_feedback_log`
--

DROP TABLE IF EXISTS `prd_decision_feedback_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_decision_feedback_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_type` enum('INTERRUPTION','SIMULATION_ADJUST') COLLATE utf8mb4_unicode_ci NOT NULL,
  `wo_id` bigint unsigned DEFAULT NULL,
  `context_customer_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2mst.cust_customer',
  `context_machine_id` bigint unsigned DEFAULT NULL,
  `options_presented` json DEFAULT NULL COMMENT 'Snapshot opsi yang ditawarkan sistem',
  `option_chosen` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chosen_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chosen_at` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `prd_decision_feedback_log_wo_id_foreign` (`wo_id`),
  KEY `idx_prd_feedback_customer` (`context_customer_id`),
  CONSTRAINT `prd_decision_feedback_log_wo_id_foreign` FOREIGN KEY (`wo_id`) REFERENCES `prd_wo` (`wo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_decision_feedback_log`
--

LOCK TABLES `prd_decision_feedback_log` WRITE;
/*!40000 ALTER TABLE `prd_decision_feedback_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `prd_decision_feedback_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prd_mts_forecast`
--

DROP TABLE IF EXISTS `prd_mts_forecast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_mts_forecast` (
  `forecast_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tm2mst.prod_product',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `forecast_qty` decimal(18,4) NOT NULL,
  `actual_qty` decimal(18,4) DEFAULT NULL COMMENT 'Terisi setelah periode berjalan',
  `accuracy_pct` decimal(6,4) DEFAULT NULL COMMENT '1-ABS(forecast-actual)/forecast; target 85-90%',
  `input_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Direksi/Top Management (Tier1 §5A.8)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`forecast_id`),
  KEY `idx_prd_forecast_product_period` (`product_id`,`period_start`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_mts_forecast`
--

LOCK TABLES `prd_mts_forecast` WRITE;
/*!40000 ALTER TABLE `prd_mts_forecast` DISABLE KEYS */;
/*!40000 ALTER TABLE `prd_mts_forecast` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prd_scheduling_rule`
--

DROP TABLE IF EXISTS `prd_scheduling_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_scheduling_rule` (
  `rule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rule_type` enum('BATCHING','SEQUENCING') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label mudah dibaca, mis. "Gabung dieline sama"',
  `applies_to_machine_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2mst.prod_machine -- NULL = semua mesin sejenis applies_to_process',
  `applies_to_process` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama proses, mis. CETAK, PLONG',
  `condition_field` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama atribut task/produk yang dibandingkan',
  `condition_operator` enum('EQUALS','SORT_ASC','SORT_DESC') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_status` enum('DRAFT','ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Wajib diisi utk rule_status=ACTIVE',
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`rule_id`),
  KEY `idx_prd_rule_machine` (`applies_to_machine_id`,`rule_status`),
  KEY `idx_prd_rule_type` (`rule_type`,`rule_status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_scheduling_rule`
--

LOCK TABLES `prd_scheduling_rule` WRITE;
/*!40000 ALTER TABLE `prd_scheduling_rule` DISABLE KEYS */;
/*!40000 ALTER TABLE `prd_scheduling_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prd_wo`
--

DROP TABLE IF EXISTS `prd_wo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo` (
  `wo_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor dokumen, sequence per tahun',
  `wo_type` enum('MTO','MTS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('CO_CONFIRMED','MTS_FORECAST') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_co_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2sdi.co_order, wajib bila MTO',
  `source_forecast_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK prd_mts_forecast, wajib bila MTS',
  `product_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tm2mst.prod_product',
  `parts_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2mst.prod_part, NULL bila single-part',
  `qty` decimal(18,4) NOT NULL,
  `due_date` datetime NOT NULL COMMENT 'Dari CO (MTO) atau replenishment cycle (MTS)',
  `material_needed_by` datetime DEFAULT NULL COMMENT 'Hasil backward schedule -- Tier2 §4.1',
  `wo_status` enum('DRAFT','SIMULATED','SCHEDULED','IN_PROGRESS','ON_HOLD','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `schedule_layer` enum('SIMULATED','COMMITTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SIMULATED',
  `is_hot_job` tinyint(1) NOT NULL DEFAULT '0',
  `hot_job_source` enum('DIREKSI','CUSTOMER','INTERNAL_REWORK') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hot_job_customer_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2mst.cust_customer, isi bila hot_job_source=CUSTOMER',
  `confirmed_at` datetime DEFAULT NULL COMMENT 'FIFO tie-break Composite Priority Score (Tier2 §4.2 Lapis 4)',
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`wo_id`),
  UNIQUE KEY `uk_prd_wo_no` (`wo_no`),
  KEY `idx_prd_wo_status` (`wo_status`,`schedule_layer`),
  KEY `idx_prd_wo_source_co` (`source_co_id`),
  KEY `idx_prd_wo_due` (`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_wo`
--

LOCK TABLES `prd_wo` WRITE;
/*!40000 ALTER TABLE `prd_wo` DISABLE KEYS */;
INSERT INTO `prd_wo` VALUES (1,'WO2600231','MTO','CO_CONFIRMED',2,NULL,1,NULL,800.0000,'2026-09-05 17:00:00',NULL,'IN_PROGRESS','COMMITTED',0,NULL,NULL,NULL,NULL,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(2,'WO2600245','MTO','CO_CONFIRMED',2,NULL,1,NULL,500.0000,'2026-09-05 17:00:00',NULL,'SCHEDULED','COMMITTED',1,'DIREKSI',NULL,NULL,NULL,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(3,'WO2600228','MTO','CO_CONFIRMED',2,NULL,4,NULL,1200.0000,'2026-09-05 17:00:00',NULL,'IN_PROGRESS','COMMITTED',0,NULL,NULL,NULL,NULL,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(4,'WO2600230','MTO','CO_CONFIRMED',2,NULL,1,NULL,600.0000,'2026-09-05 17:00:00',NULL,'ON_HOLD','COMMITTED',0,NULL,NULL,NULL,NULL,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(5,'WO2600225','MTO','CO_CONFIRMED',2,NULL,4,NULL,900.0000,'2026-09-05 17:00:00',NULL,'IN_PROGRESS','COMMITTED',0,NULL,NULL,NULL,NULL,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(6,'WO2600227','MTO','CO_CONFIRMED',2,NULL,4,NULL,700.0000,'2026-09-05 17:00:00',NULL,'COMPLETED','COMMITTED',0,NULL,NULL,NULL,NULL,'system','2026-08-27 10:43:32',NULL,'2026-08-27 18:52:20'),(7,'WO2600250','MTO','CO_CONFIRMED',NULL,NULL,1,NULL,400.0000,'2026-09-05 17:00:00',NULL,'SIMULATED','SIMULATED',0,NULL,NULL,NULL,NULL,'system','2026-08-27 10:43:32',NULL,'2026-08-27 18:57:52'),(8,'WO2600260','MTO','CO_CONFIRMED',NULL,NULL,1,NULL,350.0000,'2026-09-03 17:00:00',NULL,'SCHEDULED','COMMITTED',0,NULL,NULL,NULL,NULL,'system','2026-08-27 18:16:25',NULL,'2026-08-27 18:16:25');
/*!40000 ALTER TABLE `prd_wo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prd_wo_material`
--

DROP TABLE IF EXISTS `prd_wo_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo_material` (
  `detail_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_id` bigint unsigned NOT NULL,
  `seq_no` int unsigned NOT NULL DEFAULT '0',
  `material_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tm2mst.mat_raw -- kebutuhan ASLI (sebelum substitusi)',
  `qty_required` decimal(18,4) NOT NULL COMMENT 'Kontrak Inventory: Stock Out tarik dari sini',
  `qty_standar` decimal(18,4) NOT NULL COMMENT 'Kebutuhan teoritis dari BOM',
  `is_substituted` tinyint(1) NOT NULL DEFAULT '0',
  `substitute_material_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2mst.mat_raw, hasil §5A.10 Tier1',
  `substitute_reason` enum('DEAD_STOCK','SLOW_MOVING','OTHER') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `substitute_approved_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Wajib minimal Manager Operasional (Tier1 §9.4)',
  `substitute_approved_at` datetime DEFAULT NULL,
  `cutting_yield_waste_qty` decimal(18,4) DEFAULT NULL COMMENT 'Sisa potong, masuk rm_stockcard via Inventory',
  `material_needed_by` datetime DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`detail_id`),
  KEY `prd_wo_material_wo_id_foreign` (`wo_id`),
  KEY `idx_prd_wo_material_item` (`material_id`),
  CONSTRAINT `prd_wo_material_wo_id_foreign` FOREIGN KEY (`wo_id`) REFERENCES `prd_wo` (`wo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_wo_material`
--

LOCK TABLES `prd_wo_material` WRITE;
/*!40000 ALTER TABLE `prd_wo_material` DISABLE KEYS */;
/*!40000 ALTER TABLE `prd_wo_material` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prd_wo_operation`
--

DROP TABLE IF EXISTS `prd_wo_operation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prd_wo_operation` (
  `operation_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_id` bigint unsigned NOT NULL,
  `seq_no` int unsigned NOT NULL COMMENT 'Urutan routing, dari tm2mst.prod_parts_process',
  `process_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_type` enum('MACHINE','MANUAL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `machine_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2mst.prod_machine, wajib bila resource_type=MACHINE',
  `qty` decimal(18,4) NOT NULL,
  `target_start` datetime DEFAULT NULL COMMENT 'Hasil Backward Schedule (Tier2 §4.1)',
  `target_finish` datetime DEFAULT NULL,
  `actual_start` datetime DEFAULT NULL,
  `actual_finish` datetime DEFAULT NULL,
  `task_status` enum('PENDING','ASSIGNED','RUNNING','DONE','BREAKDOWN','SUBCONTRACT','QC_HOLD','QC_PASSED','QC_REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `qc_gate_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Titik kritis routing (Tier1 F5)',
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_hot_job` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Salinan dari WO, bisa beda kalau hot job cuma sebagian task',
  `batched_with_operation_id` bigint unsigned DEFAULT NULL COMMENT 'Self-FK -- Batching Rule (Tier1 §5A.7)',
  `rate_band_value` decimal(18,4) DEFAULT NULL COMMENT 'Snapshot rate dipakai saat backward schedule (audit)',
  `critical_ratio` decimal(8,4) DEFAULT NULL COMMENT 'Computed, refresh tiap re-run PDR (Tier2 §4.2)',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`operation_id`),
  KEY `idx_prd_wo_op_wo` (`wo_id`,`seq_no`),
  KEY `idx_prd_wo_op_machine` (`machine_id`,`target_start`,`target_finish`),
  KEY `idx_prd_wo_op_status` (`task_status`),
  KEY `fk_prd_wo_op_batch` (`batched_with_operation_id`),
  CONSTRAINT `fk_prd_wo_op_batch` FOREIGN KEY (`batched_with_operation_id`) REFERENCES `prd_wo_operation` (`operation_id`),
  CONSTRAINT `prd_wo_operation_wo_id_foreign` FOREIGN KEY (`wo_id`) REFERENCES `prd_wo` (`wo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prd_wo_operation`
--

LOCK TABLES `prd_wo_operation` WRITE;
/*!40000 ALTER TABLE `prd_wo_operation` DISABLE KEYS */;
INSERT INTO `prd_wo_operation` VALUES (1,1,1,'Cetak','MACHINE',1,800.0000,'2026-08-28 08:00:00','2026-08-28 12:00:00',NULL,NULL,'RUNNING',0,NULL,0,NULL,NULL,2.4000,'system','2026-08-27 10:43:32','johiwan','2026-08-27 18:57:52'),(2,2,1,'Cetak','MACHINE',1,500.0000,'2026-08-28 14:00:00','2026-08-28 17:00:00',NULL,NULL,'ASSIGNED',0,NULL,1,NULL,NULL,1.6000,'system','2026-08-27 10:43:32','johiwan','2026-08-27 18:57:51'),(3,3,1,'Laminasi','MACHINE',4,1200.0000,'2026-08-28 11:15:00','2026-08-28 14:15:00',NULL,NULL,'RUNNING',0,NULL,0,NULL,NULL,1.9000,'system','2026-08-27 10:43:32','johiwan','2026-08-27 18:57:51'),(4,4,1,'Plong','MACHINE',5,300.0000,'2026-08-28 08:00:00','2026-08-28 10:00:00',NULL,NULL,'QC_PASSED',0,NULL,0,NULL,NULL,2.0000,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(5,4,2,'Plong','MACHINE',5,300.0000,'2026-08-28 10:00:00','2026-08-28 13:00:00',NULL,NULL,'BREAKDOWN',0,NULL,0,NULL,NULL,0.6000,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(6,5,1,'Packing','MANUAL',NULL,900.0000,'2026-08-28 10:00:00','2026-08-28 13:00:00',NULL,NULL,'RUNNING',0,NULL,0,NULL,NULL,1.8000,'system','2026-08-27 10:43:32',NULL,'2026-08-27 10:43:32'),(7,6,1,'Packing','MANUAL',NULL,700.0000,'2026-08-28 12:00:00','2026-08-28 16:00:00',NULL,NULL,'QC_HOLD',1,NULL,0,NULL,NULL,0.8000,'system','2026-08-27 10:43:32',NULL,'2026-08-27 18:57:52'),(8,7,1,'Cetak','MACHINE',1,400.0000,NULL,NULL,NULL,NULL,'PENDING',0,NULL,0,NULL,NULL,NULL,'system','2026-08-27 10:43:32','johiwan','2026-08-27 18:57:52'),(9,8,1,'Cetak','MACHINE',1,350.0000,'2026-08-28 17:30:00','2026-08-28 19:00:00',NULL,NULL,'PENDING',0,NULL,0,NULL,NULL,3.2000,'system','2026-08-27 18:16:25','johiwan','2026-08-27 18:57:52');
/*!40000 ALTER TABLE `prd_wo_operation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_prd_customer_interruption_profile`
--

DROP TABLE IF EXISTS `v_prd_customer_interruption_profile`;
/*!50001 DROP VIEW IF EXISTS `v_prd_customer_interruption_profile`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_prd_customer_interruption_profile` AS SELECT 
 1 AS `context_customer_id`,
 1 AS `total_interruption`,
 1 AS `option_chosen`,
 1 AS `count_per_option`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_prd_exception_dashboard`
--

DROP TABLE IF EXISTS `v_prd_exception_dashboard`;
/*!50001 DROP VIEW IF EXISTS `v_prd_exception_dashboard`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_prd_exception_dashboard` AS SELECT 
 1 AS `operation_id`,
 1 AS `wo_id`,
 1 AS `process_name`,
 1 AS `machine_id`,
 1 AS `target_start`,
 1 AS `target_finish`,
 1 AS `critical_ratio`,
 1 AS `task_status`,
 1 AS `is_unscheduled_risk`,
 1 AS `is_at_risk`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'tm2prd'
--

--
-- Dumping routines for database 'tm2prd'
--

--
-- Final view structure for view `v_prd_customer_interruption_profile`
--

/*!50001 DROP VIEW IF EXISTS `v_prd_customer_interruption_profile`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_prd_customer_interruption_profile` AS select `prd_decision_feedback_log`.`context_customer_id` AS `context_customer_id`,count(0) AS `total_interruption`,`prd_decision_feedback_log`.`option_chosen` AS `option_chosen`,count(0) AS `count_per_option` from `prd_decision_feedback_log` where ((`prd_decision_feedback_log`.`event_type` = 'INTERRUPTION') and (`prd_decision_feedback_log`.`context_customer_id` is not null)) group by `prd_decision_feedback_log`.`context_customer_id`,`prd_decision_feedback_log`.`option_chosen` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_prd_exception_dashboard`
--

/*!50001 DROP VIEW IF EXISTS `v_prd_exception_dashboard`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_prd_exception_dashboard` AS select `o`.`operation_id` AS `operation_id`,`o`.`wo_id` AS `wo_id`,`o`.`process_name` AS `process_name`,`o`.`machine_id` AS `machine_id`,`o`.`target_start` AS `target_start`,`o`.`target_finish` AS `target_finish`,`o`.`critical_ratio` AS `critical_ratio`,`o`.`task_status` AS `task_status`,(case when ((`o`.`task_status` = 'PENDING') and (`o`.`target_start` < now())) then 1 else 0 end) AS `is_unscheduled_risk`,(case when (`o`.`critical_ratio` < 1) then 1 else 0 end) AS `is_at_risk` from `prd_wo_operation` `o` where (`o`.`task_status` not in ('DONE','QC_PASSED')) */;
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

-- Dump completed on 2026-08-28  9:23:16
