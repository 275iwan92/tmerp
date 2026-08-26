
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
DROP TABLE IF EXISTS `fg_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fg_stock` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK header movement FG',
  `doc_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'No dokumen',
  `doc_date` datetime NOT NULL,
  `doc_type` tinyint DEFAULT NULL COMMENT 'IN=1 (dari produksi), OUT=-1 (ke customer)',
  `doc_cat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BB | STOCK | ADJ | RETURN | OPNAME',
  `ref_doc_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'MANUAL',
  `ref_doc_id` bigint unsigned DEFAULT NULL,
  `ref_doc_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL COMMENT 'gudang',
  `issued_to_dept` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `process_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product (SKU induk dokumen ini)',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'catatan\r\n',
  `stat_confirm` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=draft, 1=confirmed → trigger otomatis isi fg_stockcard',
  `stat_cancel` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'flag cancel',
  `stat_closed` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'flag close',
  `post_date` date DEFAULT NULL COMMENT 'post date oleh pihak accoutning saat di jurnal',
  `period_id` bigint unsigned DEFAULT NULL,
  `flow_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `posted_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `opn_start` datetime DEFAULT NULL COMMENT 'opname start',
  `opn_stop` datetime DEFAULT NULL COMMENT 'opname stop',
  `opn_officer` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nama petugas yang melakukan opname',
  `opn_witness` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_scope` tinyint unsigned NOT NULL DEFAULT '0',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  UNIQUE KEY `uq_fg_stock_docno` (`doc_no`),
  KEY `idx_fg_s_product` (`product_id`) USING BTREE,
  KEY `idx_fg_s_date` (`doc_date`) USING BTREE,
  KEY `idx_fg_stock_flow` (`flow_status`,`doc_cat`)
) ENGINE=InnoDB AUTO_INCREMENT=170 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Header movement stok FG — dikonfirm → trigger isi fg_stockcard';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fg_stock` WRITE;
/*!40000 ALTER TABLE `fg_stock` DISABLE KEYS */;
INSERT INTO `fg_stock` VALUES (30,'26DN00001','2026-03-10 00:00:00',1,'STOCK','MANUAL',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-04-30 06:50:51','2026-04-30 06:50:51'),(40,'26DN00001-DUP2','2026-03-10 00:00:00',1,'STOCK','MANUAL',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-04-30 06:51:46','2026-08-21 07:00:45'),(42,'10260004','2026-03-10 00:00:00',1,'STOCK','MANUAL',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-04-30 06:52:39','2026-04-30 06:52:39'),(99,'10260002','2026-03-10 00:00:00',1,'STOCK','MANUAL',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-04-30 07:02:14','2026-04-30 07:02:14'),(166,'10260006','2026-04-30 00:00:00',1,'STOCK','MANUAL',NULL,NULL,5,NULL,NULL,NULL,NULL,12572,'abc',1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-04-30 07:31:36','2026-04-30 07:36:38'),(168,'FG-IN-260001','2026-08-18 21:17:03',1,'IN','MANUAL',NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,'Data uji -- stok awal barang jadi (dummy, bukan dari alur produksi sungguhan).',1,0,0,NULL,NULL,'CONFIRMED',NULL,NULL,NULL,NULL,NULL,NULL,0,'system','system','2026-08-18 14:17:03','2026-08-18 14:17:03'),(169,'IN/26/00001','2026-08-20 00:00:00',1,'STOCK','MANUAL',NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,'Data uji Stock In FG -- via StockPostingService, bukan insert manual.',1,0,0,NULL,NULL,'POSTED','system','2026-08-21 21:57:13',NULL,NULL,NULL,NULL,0,'system','system','2026-08-21 14:57:13','2026-08-21 14:57:13');
/*!40000 ALTER TABLE `fg_stock` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fg_stock_d`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fg_stock_d` (
  `doc_detail_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK detail',
  `doc_id` bigint unsigned NOT NULL COMMENT 'FK → fg_stock',
  `seq_no` int unsigned NOT NULL DEFAULT '0',
  `product_id` bigint unsigned NOT NULL COMMENT 'FK → tmmst.m_product',
  `parts_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts. NULL untuk produk single-part',
  `qty` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'qty in atau qty out atau qty adj tergantung tipe',
  `qty_returned` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `qty_system` decimal(20,4) DEFAULT NULL,
  `qty_physical` decimal(20,4) DEFAULT NULL,
  `action_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_condition` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_detail_id` bigint unsigned DEFAULT NULL,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'satuan',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nomor lot sesuai fisik',
  `unit_price` decimal(20,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT 'harga ini tidak di tampilkan tapi menjadi dasar perhitungan fifo/lifo/avg',
  `qty_unitprice` decimal(20,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT 'menjadi dasar pelaporan nilai barang',
  `reference_id` bigint unsigned DEFAULT NULL COMMENT 'FK dokumen sumber (wo_id, delivery_id, dll)',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'catatan atau referensi yang harus di sertakan',
  `location_id` bigint unsigned NOT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'create oleh siapa',
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'update oleh siapa',
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'create kapan',
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'update kapan',
  PRIMARY KEY (`doc_detail_id`) USING BTREE,
  KEY `idx_fg_d_doc` (`doc_id`) USING BTREE,
  KEY `idx_fg_d_product` (`product_id`) USING BTREE,
  KEY `idx_fg_d_parts` (`parts_id`) USING BTREE,
  KEY `idx_fg_stock_d_source` (`source_detail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Detail movement stok FG per produk / komponen';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fg_stock_d` WRITE;
/*!40000 ALTER TABLE `fg_stock_d` DISABLE KEYS */;
INSERT INTO `fg_stock_d` VALUES (2,168,0,1,NULL,500.0000,0.0000,NULL,NULL,NULL,NULL,NULL,'PCS','LOT-FG-0001',0.0000,0.0000,NULL,NULL,3,'system','system','2026-08-18 14:17:03','2026-08-18 14:17:03'),(3,168,1,2,NULL,200.0000,0.0000,NULL,NULL,NULL,NULL,NULL,'PCS','LOT-FG-0002',0.0000,0.0000,NULL,NULL,3,'system','system','2026-08-18 14:17:03','2026-08-18 14:17:03'),(4,169,1,1,NULL,150.0000,0.0000,NULL,NULL,NULL,NULL,NULL,'PCS','LOT-FGIN-0001',0.0000,0.0000,NULL,NULL,3,'system','system','2026-08-21 14:57:13','2026-08-21 14:57:13');
/*!40000 ALTER TABLE `fg_stock_d` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fg_stockcard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fg_stockcard` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `previous_doc_id` bigint unsigned DEFAULT NULL,
  `doc_date` datetime NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product',
  `parts_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts. NULL untuk single-part',
  `doc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BB / IN / OUT / ADJ',
  `location_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK → fg_stock_d.doc_detail_id',
  `qty_bb` decimal(20,4) DEFAULT NULL,
  `qty_in` decimal(20,4) DEFAULT NULL,
  `qty_out` decimal(20,4) DEFAULT NULL,
  `qty_adj` decimal(20,4) DEFAULT NULL,
  `qty_eb` decimal(20,4) DEFAULT NULL,
  `post_date` date DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reference_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_bb` decimal(20,4) DEFAULT NULL,
  `value_in` decimal(20,4) DEFAULT NULL,
  `value_out` decimal(20,4) DEFAULT NULL,
  `value_adj` decimal(20,4) DEFAULT NULL,
  `value_eb` decimal(20,4) DEFAULT NULL,
  `avg_unitprice` decimal(30,5) DEFAULT NULL,
  `period_id` bigint unsigned DEFAULT NULL,
  `c_sku_key` varchar(64) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(ifnull(`product_id`,0),_utf8mb4'-',ifnull(`parts_id`,0))) STORED COMMENT 'Kunci saldo FG: produk+komponen, NULL dinormalkan ke 0',
  PRIMARY KEY (`doc_id`) USING BTREE,
  UNIQUE KEY `uq_fg_stockcard_ref` (`reference_name`,`reference_detail_id`),
  KEY `idx_fg_sc_product` (`product_id`) USING BTREE,
  KEY `idx_fg_sc_parts` (`parts_id`) USING BTREE,
  KEY `idx_fg_sc_date` (`doc_date`) USING BTREE,
  KEY `idx_fg_sc_sku` (`c_sku_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Kartu stok FG Master stok FG per periode— diisi otomatis oleh trigger saat fg_stock confirm';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fg_stockcard` WRITE;
/*!40000 ALTER TABLE `fg_stockcard` DISABLE KEYS */;
INSERT INTO `fg_stockcard` (`doc_id`, `previous_doc_id`, `doc_date`, `product_id`, `parts_id`, `doc_type`, `location_id`, `warehouse_id`, `lot_no`, `qty`, `uom_code`, `reference_detail_id`, `qty_bb`, `qty_in`, `qty_out`, `qty_adj`, `qty_eb`, `post_date`, `create_by`, `update_by`, `create_log`, `update_log`, `reference_name`, `value_bb`, `value_in`, `value_out`, `value_adj`, `value_eb`, `avg_unitprice`, `period_id`) VALUES (1,NULL,'2026-08-18 21:17:03',1,NULL,'IN',3,2,'LOT-FG-0001',500.0000,'PCS',2,0.0000,500.0000,0.0000,0.0000,500.0000,NULL,'system','system','2026-08-18 14:17:03','2026-08-18 14:17:03','tm2inv.fg_stock_d',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,NULL,'2026-08-18 21:17:03',2,NULL,'IN',3,2,'LOT-FG-0002',200.0000,'PCS',3,0.0000,200.0000,0.0000,0.0000,200.0000,NULL,'system','system','2026-08-18 14:17:03','2026-08-18 14:17:03','tm2inv.fg_stock_d',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,1,'2026-08-20 00:00:00',1,NULL,'IN',3,2,'LOT-FGIN-0001',150.0000,'PCS',4,500.0000,150.0000,0.0000,0.0000,650.0000,NULL,'system','system','2026-08-21 14:57:13','2026-08-21 14:57:13','tm2inv.fg_stock_d',NULL,NULL,NULL,NULL,0.0000,0.00000,NULL);
/*!40000 ALTER TABLE `fg_stockcard` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `fg_stockmst`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fg_stockmst` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_id` bigint unsigned DEFAULT NULL COMMENT 'FK → i_period',
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product',
  `parts_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts. NULL untuk single-part',
  `location_id` bigint unsigned DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_bb` decimal(20,4) DEFAULT NULL,
  `qty_in` decimal(20,4) DEFAULT NULL,
  `qty_out` decimal(20,4) DEFAULT NULL,
  `qty_adj` decimal(20,4) DEFAULT NULL,
  `qty_eb` decimal(20,4) DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  KEY `idx_fg_mst_period` (`period_id`) USING BTREE,
  KEY `idx_fg_mst_product` (`product_id`) USING BTREE,
  KEY `idx_fg_mst_parts` (`parts_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='BELUM TENTU TERPAKAI Master stok FG per periode';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `fg_stockmst` WRITE;
/*!40000 ALTER TABLE `fg_stockmst` DISABLE KEYS */;
/*!40000 ALTER TABLE `fg_stockmst` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `inv_layer_consumption`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_layer_consumption` (
  `consumption_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `i_layer_id` bigint unsigned NOT NULL,
  `i_doc_detail_id` bigint unsigned NOT NULL,
  `i_reference_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `i_qty` decimal(20,4) NOT NULL,
  `i_unit_cost` decimal(30,5) NOT NULL COMMENT 'snapshot harga layer saat dikonsumsi',
  `c_value` decimal(20,4) NOT NULL COMMENT 'i_qty * i_unit_cost',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`consumption_id`),
  KEY `idx_cons_layer` (`i_layer_id`),
  KEY `idx_cons_doc` (`i_reference_name`,`i_doc_detail_id`),
  CONSTRAINT `inv_layer_consumption_i_layer_id_foreign` FOREIGN KEY (`i_layer_id`) REFERENCES `inv_stock_layer` (`layer_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `inv_layer_consumption` WRITE;
/*!40000 ALTER TABLE `inv_layer_consumption` DISABLE KEYS */;
/*!40000 ALTER TABLE `inv_layer_consumption` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `inv_period`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_period` (
  `period_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `i_period_code` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YYYYMM',
  `i_date_start` date NOT NULL,
  `i_date_end` date NOT NULL,
  `i_status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN|CLOSING|CLOSED',
  `c_closed_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_closed_at` datetime DEFAULT NULL,
  `i_notes` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`period_id`),
  UNIQUE KEY `inv_period_i_period_code_unique` (`i_period_code`),
  KEY `idx_inv_period_range` (`i_date_start`,`i_date_end`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `inv_period` WRITE;
/*!40000 ALTER TABLE `inv_period` DISABLE KEYS */;
INSERT INTO `inv_period` VALUES (1,'202608','2026-08-01','2026-08-31','OPEN',NULL,NULL,NULL,'system','2026-08-21 07:00:48','system','2026-08-21 07:00:48');
/*!40000 ALTER TABLE `inv_period` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `inv_stock_layer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_stock_layer` (
  `layer_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `i_stock_class` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'RM|SFG|FG',
  `i_item_id` bigint unsigned NOT NULL,
  `i_location_id` bigint unsigned NOT NULL,
  `i_warehouse_id` bigint unsigned DEFAULT NULL,
  `i_lot_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_qty_in` decimal(20,4) NOT NULL,
  `c_qty_remaining` decimal(20,4) NOT NULL COMMENT 'dikurangi saat OUT sesuai FIFO/LIFO',
  `i_unit_cost` decimal(30,5) NOT NULL,
  `i_uom_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `i_received_at` datetime NOT NULL COMMENT 'dasar urutan FIFO/LIFO',
  `i_reference_id` bigint unsigned DEFAULT NULL,
  `i_reference_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_is_exhausted` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`layer_id`),
  KEY `idx_layer_pick` (`i_stock_class`,`i_item_id`,`i_location_id`,`c_is_exhausted`,`i_received_at`),
  KEY `idx_layer_ref` (`i_reference_name`,`i_reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `inv_stock_layer` WRITE;
/*!40000 ALTER TABLE `inv_stock_layer` DISABLE KEYS */;
/*!40000 ALTER TABLE `inv_stock_layer` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rm_booking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rm_booking` (
  `booking_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK header booking RM',
  `booking_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor dokumen, format 26BK00001',
  `booking_date` datetime DEFAULT NULL COMMENT 'Tanggal booking',
  `booking_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL' COMMENT 'MANUAL = input user | WO = generate dari prd_wo_material',
  `warehouse_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmmst.m_warehouse',
  `wo_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmprd.prd_wo (opsional)',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stat_confirm` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=draft (Simpan Draft) | 1=terkunci (Simpan & Kunci) -> reservasi aktif',
  `stat_cancel` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=normal | 1=dibatalkan',
  `stat_closed` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=open | 1=selesai/terpenuhi seluruhnya',
  `confirm_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirm_date` datetime DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`) USING BTREE,
  UNIQUE KEY `uq_rm_bk_no` (`booking_no`) USING BTREE,
  KEY `idx_rm_bk_date` (`booking_date`) USING BTREE,
  KEY `idx_rm_bk_stat` (`stat_confirm`,`stat_cancel`,`stat_closed`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Header booking/reservasi bahan baku — belum menyentuh ledger stok';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rm_booking` WRITE;
/*!40000 ALTER TABLE `rm_booking` DISABLE KEYS */;
/*!40000 ALTER TABLE `rm_booking` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rm_booking_d`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rm_booking_d` (
  `booking_detail_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK detail booking',
  `booking_id` bigint unsigned NOT NULL COMMENT 'FK -> rm_booking',
  `seq_no` int unsigned NOT NULL DEFAULT '0' COMMENT 'Urutan tampil baris',
  `co_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmsdi.s_co.doc_id (denormalisasi utk filter)',
  `co_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmsdi.s_co_detail.doc_detail_id',
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmmst.m_product (snapshot dari CO detail)',
  `material_id` bigint unsigned NOT NULL COMMENT 'FK -> tmmst.m_raw_material',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'FK -> tmmst.m_uom',
  `location_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmmst.m_wh_loc; NULL = belum ditentukan',
  `qty_need` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Total kebutuhan baris ini',
  `qty_book` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Dipesan dari stok tersedia (reservasi)',
  `qty_purchase` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Kekurangan -> dibeli',
  `qty_replacement` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Ditutup oleh material pengganti',
  `qty_issued` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Realisasi keluar; diisi dari rm_stock_d saat issue',
  `material_replace_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmmst.m_raw_material',
  `uom_replace` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replace_ratio` decimal(20,6) NOT NULL DEFAULT '1.000000' COMMENT 'Konversi 1 unit material utama = n unit pengganti',
  `replace_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `substitution_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmprd.rawmat_substitution_recommendation (jika dari rekomendasi MCDM)',
  `pr_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> tmpur.p_requisition_d, terisi bila qty_purchase sudah di-PR',
  `note_detail` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stat_cancel` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=normal | 1=baris dibatalkan',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_detail_id`) USING BTREE,
  KEY `idx_rm_bkd_bk` (`booking_id`) USING BTREE,
  KEY `idx_rm_bkd_mat` (`material_id`,`location_id`) USING BTREE,
  KEY `idx_rm_bkd_co` (`co_detail_id`) USING BTREE,
  KEY `idx_rm_bkd_rep` (`material_replace_id`) USING BTREE,
  CONSTRAINT `fk_rm_bkd_bk` FOREIGN KEY (`booking_id`) REFERENCES `rm_booking` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Detail kebutuhan material per baris CO';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rm_booking_d` WRITE;
/*!40000 ALTER TABLE `rm_booking_d` DISABLE KEYS */;
/*!40000 ALTER TABLE `rm_booking_d` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rm_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rm_stock` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK header movement Raw Material',
  `doc_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor dokumen (SPK / nomor manual)',
  `doc_date` datetime NOT NULL,
  `doc_type` tinyint DEFAULT NULL COMMENT 'IN=1, OUT=-1',
  `doc_cat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BB=beginning balance | STOCK=normal | ADJ=penyesuaian | RETURN=retur | OPNAME=stok opname',
  `ref_doc_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'MANUAL' COMMENT 'PV|WO|OUT|MANUAL',
  `ref_doc_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK dokumen sumber',
  `ref_doc_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2prd.prd_wo',
  `wo_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor Work Order (CO-No + counter)',
  `warehouse_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_warehouse',
  `issued_to_dept` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `process_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'wajib utk AD/OP/RETURN; daftar di sys_constant INV_REASON_*',
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Keterangan tambahan',
  `stat_confirm` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=draft, 1=confirmed → trigger otomatis isi rm_stockcard, confirmation saat release',
  `stat_confirm_plan` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'confirmation for plan',
  `stat_cancel` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=normal, 1=cancelled',
  `stat_closed` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=open, 1=closed/posted',
  `post_date` date DEFAULT NULL COMMENT 'Tanggal posting ke accounting',
  `period_id` bigint unsigned DEFAULT NULL,
  `flow_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|SUBMITTED|APPROVED_L1..Ln|REVISION_NEEDED|POSTED|CANCELLED',
  `posted_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `opn_start` datetime DEFAULT NULL COMMENT 'opname start',
  `opn_stop` datetime DEFAULT NULL COMMENT 'opname stop',
  `opn_officer` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nama petugas yang melakukan opname',
  `opn_witness` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_scope` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Opname: 1 = seluruh item cakupan wajib masuk daftar',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `stat_auth` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'DRAFT' COMMENT 'DRAFT, WAITING, AP_1, RJ_1',
  `auth_module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Isi dengan OP atau AD',
  `current_approval_level` int unsigned DEFAULT '0' COMMENT '0=Draft, 1=Menunggu Appv Lvl 1',
  `current_approval_login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Login name Manajer yg harus approve',
  `doc_status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Keterangan text user friendly',
  PRIMARY KEY (`doc_id`) USING BTREE,
  UNIQUE KEY `uq_rm_s_docno` (`doc_no`),
  KEY `idx_rm_s_date` (`doc_date`) USING BTREE,
  KEY `idx_rm_s_flow` (`flow_status`,`doc_cat`),
  KEY `idx_rm_s_ref` (`ref_doc_type`,`ref_doc_id`),
  KEY `idx_rm_s_period` (`period_id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Header movement stok RM — dikonfirm → trigger isi rm_stockcard';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rm_stock` WRITE;
/*!40000 ALTER TABLE `rm_stock` DISABLE KEYS */;
INSERT INTO `rm_stock` VALUES (5,'26SR00002','2026-04-24 00:00:00',-1,'AD','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',1,1,1,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','trial','2026-04-23 17:00:00','2026-07-09 03:38:04','DRAFT',NULL,0,NULL,NULL),(6,'26SR00003','2026-04-24 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,2,NULL,NULL,NULL,NULL,'',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','admin','2026-04-23 17:00:00','2026-07-07 07:04:21','DRAFT',NULL,0,NULL,NULL),(7,'26SPB00001','2026-04-30 13:09:37',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00001',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-04-30 06:09:37','2026-08-21 06:55:53','DRAFT',NULL,0,NULL,NULL),(8,'26SPB00007','2026-06-04 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00007',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-04 08:55:29','2026-06-04 08:55:29','DRAFT',NULL,0,NULL,NULL),(9,'26SPB00006','2026-06-04 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00006',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-04 08:55:49','2026-06-04 08:55:49','DRAFT',NULL,0,NULL,NULL),(10,'26SPB00007-DUP2','2026-06-04 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00007',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-04 08:55:53','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(11,'26SPB00007-DUP3','2026-06-04 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00007',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-04 08:57:51','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(12,'26SPB00008','2026-06-05 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00008',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-05 01:07:04','2026-06-05 01:07:04','DRAFT',NULL,0,NULL,NULL),(13,'26SPB00009','2026-06-05 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,1,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00009',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-05 01:21:18','2026-06-05 01:21:18','DRAFT',NULL,0,NULL,NULL),(14,'26SPB00006-DUP2','2026-06-17 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00006',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-17 01:31:13','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(15,'26SPB00005','2026-06-15 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00005',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-17 01:41:56','2026-06-17 01:41:56','DRAFT',NULL,0,NULL,NULL),(24,'PRC1600000054','2016-01-01 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'Auto dari p_receive: PRC1600000054',1,0,0,0,'2016-01-01',NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'_migrate','_migrate','2026-06-25 07:14:51','2026-06-25 07:14:51','DRAFT',NULL,0,NULL,NULL),(25,'26SPB00009-DUP2','2026-06-25 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00009',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-06-25 07:18:28','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(26,'PRC1600000676','2016-01-04 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'Auto dari p_receive: PRC1600000676',1,0,0,0,'2016-01-04',NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'_migrate','_migrate','2026-06-25 07:53:12','2026-06-25 07:53:12','DRAFT',NULL,0,NULL,NULL),(27,'26SPB00013','2026-07-02 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00013',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 13:51:33','2026-07-02 13:51:33','DRAFT',NULL,0,NULL,NULL),(28,'26SPB00020','2026-07-03 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00020',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-03 01:39:46','2026-07-03 01:39:46','DRAFT',NULL,0,NULL,NULL),(29,'26SPB00019','2026-07-03 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00019',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-03 01:45:09','2026-07-03 01:45:09','DRAFT',NULL,0,NULL,NULL),(31,'26BB00005','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'coba beginnging balance',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-08-21 06:55:53','DRAFT',NULL,0,NULL,NULL),(32,'26BB00005-DUP2','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,1,NULL,NULL,NULL,NULL,'coba beginning balance',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-08-21 06:55:53','DRAFT',NULL,0,NULL,NULL),(33,'26BB00005-DUP3','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'coba beginning balance lagi',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(34,'26BB00005-DUP4','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,1,NULL,NULL,NULL,NULL,'kenapa doc_no nya sama? ',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(35,'26BB00005-DUP5','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(36,'26BB00005-DUP6','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'doc_no masih belum sesuai',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(37,'26BB00006','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'akhirnya doc no nya increment',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-07-09 02:18:14','DRAFT',NULL,0,NULL,NULL),(41,'26BB00007','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-07-09 02:18:14','DRAFT',NULL,0,NULL,NULL),(43,'26BB00008','2026-07-03 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-02 17:00:00','2026-07-09 02:18:15','DRAFT',NULL,0,NULL,NULL),(46,'26SR00004','2026-07-03 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Notes',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','_system','2026-07-02 17:00:00','2026-07-07 07:04:21','DRAFT',NULL,0,NULL,NULL),(47,'26SR00005','2026-07-03 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','_system','2026-07-02 17:00:00','2026-07-08 02:09:26','DRAFT',NULL,0,NULL,NULL),(48,'26SR00006','2026-07-03 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-03 14:24:29','2026-07-03 14:24:29','DRAFT',NULL,0,NULL,NULL),(49,'26SR00007','2026-07-03 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-03 14:26:41','2026-07-03 14:26:41','DRAFT',NULL,0,NULL,NULL),(50,'26SR00008','2026-07-04 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-04 03:08:48','2026-07-04 03:08:48','DRAFT',NULL,0,NULL,NULL),(51,'26SR00009','2026-07-04 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-04 03:09:23','2026-07-04 03:09:23','DRAFT',NULL,0,NULL,NULL),(53,'26BB00009','2026-07-04 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,1,NULL,NULL,NULL,NULL,'TEST_INV_MAR26_TC01',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','_system','2026-07-03 17:00:00','2026-07-09 02:18:15','DRAFT',NULL,0,NULL,NULL),(54,'26SR00010','2026-07-06 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-06 02:26:13','2026-07-06 02:26:13','DRAFT',NULL,0,NULL,NULL),(55,'26SR00011','2026-07-06 00:00:00',-1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'notes',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','_system','2026-07-05 17:00:00','2026-07-05 17:00:00','DRAFT',NULL,0,NULL,NULL),(57,'26BB00010','2026-07-06 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,1,NULL,NULL,NULL,NULL,'TEST_INV_MAR26_TC01',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-05 17:00:00','2026-07-09 02:18:15','DRAFT',NULL,0,NULL,NULL),(59,'26SR00012','2026-07-06 00:00:00',-1,'AD','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-05 17:00:00','2026-07-09 03:38:04','DRAFT',NULL,0,NULL,NULL),(60,'26SR00013','2026-07-06 00:00:00',-1,'AD','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-05 17:00:00','2026-07-09 03:38:04','DRAFT',NULL,0,NULL,NULL),(62,'26BB00011','2026-07-06 00:00:00',1,'BB','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-05 17:00:00','2026-07-09 02:18:15','DRAFT',NULL,0,NULL,NULL),(65,'26OP00014','2026-07-08 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'coba stock opname 1',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-08 14:33:00','2026-07-08 15:33:00','Lucy',NULL,0,'','_system','2026-07-07 17:00:00','2026-07-09 03:38:05','DRAFT',NULL,0,NULL,NULL),(66,'26IN00001','2026-07-03 00:00:00',1,'STOCK','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00021',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-09 03:07:28','2026-07-09 03:07:28','DRAFT',NULL,0,NULL,NULL),(67,'26IN00002','2026-07-02 00:00:00',1,'IN','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'Auto dari p_receive: 26SPB00014',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-09 03:10:44','2026-07-09 03:10:44','DRAFT',NULL,0,NULL,NULL),(68,'26AD00014','2026-07-09 00:00:00',-1,'AD','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'coba stock adjustment',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','_system','2026-07-08 17:00:00','2026-07-08 17:00:00','DRAFT',NULL,0,NULL,NULL),(69,'26OP00015','2026-07-09 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Coba Opname Baru',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-09 11:24:00','2026-07-09 10:24:00','Lucy',NULL,0,'','_system','2026-07-08 17:00:00','2026-07-08 17:00:00','DRAFT',NULL,0,NULL,NULL),(71,'26OU00014','2026-07-09 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-09 08:39:57','2026-07-09 08:39:57','DRAFT',NULL,0,NULL,NULL),(72,'26OP00016','2026-07-10 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Notes',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-10 16:16:00','2026-07-10 16:16:00','Lucy',NULL,0,'','_system','2026-07-09 17:00:00','2026-07-09 17:00:00','DRAFT',NULL,0,NULL,NULL),(73,'26OP00017','2026-07-10 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-10 16:21:00','2026-07-10 16:21:00','Lucy',NULL,0,'','_system','2026-07-09 17:00:00','2026-07-09 17:00:00','',NULL,0,'','Ready approve by _system'),(74,'26OP00018','2026-07-10 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Note',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-10 19:41:00','2026-07-10 19:41:00','Lucy',NULL,0,'','_system','2026-07-09 17:00:00','2026-07-09 17:00:00','',NULL,0,'','Ready approve by _system'),(75,'26OP00019','2026-07-10 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-10 21:54:00','2026-07-10 21:54:00','Lucy',NULL,0,'','_system','2026-07-09 17:00:00','2026-07-09 17:00:00','','',0,'',''),(76,'26OP00020','2026-07-11 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'mencoba approval',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-11 06:34:00','2026-07-11 07:34:00','Lucy',NULL,0,'','_system','2026-07-10 17:00:00','2026-07-11 02:01:03','APR_','OP',1,'','Approve by arinda'),(77,'26OP00021','2026-07-11 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'qwertyuiop',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-11 09:10:00','2026-07-11 09:10:00','Lucy',NULL,0,'','_system','2026-07-10 17:00:00','2026-07-11 02:12:18','APR_','OP',1,'','Approve by arinda'),(78,'26OP00022','2026-07-11 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'qwdfbn',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-11 09:17:00','2026-07-11 09:17:00','Comel',NULL,0,'','_system','2026-07-10 17:00:00','2026-07-11 02:20:55','APR_','OP',1,'','Approve by arinda'),(79,'26OP00023','2026-07-11 00:00:00',-1,'OP','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'qwerty',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,'2026-07-11 09:26:00','2026-07-11 09:26:00','Lucy',NULL,0,'','_system','2026-07-10 17:00:00','2026-07-11 02:27:45','APR_1','OP',2,'','Approve by arinda'),(80,'26AD00014-DUP2','2026-07-11 00:00:00',-1,'AD','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'qwerty',1,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','_system','2026-07-10 17:00:00','2026-08-21 06:49:53','APR_1','AD',2,'','Approve by nisa'),(81,'26OU00014-DUP2','2026-07-13 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'Notes',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-13 04:04:18','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(82,'26OU00014-DUP3','2026-07-14 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-14 03:41:34','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(83,'26OU00014-DUP4','2026-07-14 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-14 03:42:20','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(84,'26OU00014-DUP5','2026-07-14 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,NULL,0,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,NULL,NULL,0,'','','2026-07-14 06:32:39','2026-08-21 06:49:53','DRAFT',NULL,0,NULL,NULL),(91,'26OU00015','2026-07-15 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','_system','2026-07-14 17:00:00','2026-07-14 17:00:00','','',0,'','CONFIRMED By _system'),(92,'26OU00016','2026-07-16 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,NULL,5,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','_system','2026-07-15 17:00:00','2026-07-16 02:20:40','','',0,'','DRAFT'),(95,'26OU00017','2026-07-16 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,'',5,NULL,NULL,NULL,NULL,'',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','_system','2026-07-15 17:00:00','2026-07-17 01:47:56','','',0,'','DRAFT'),(96,'26OU00018','2026-07-17 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,'',1,NULL,NULL,NULL,NULL,'',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','admin','2026-07-16 17:00:00','2026-07-17 01:29:51','','',0,'','DRAFT'),(97,'26OU00019','2026-07-17 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,'',5,NULL,NULL,NULL,NULL,'',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','admin','2026-07-16 17:00:00','2026-07-17 06:21:05','','',0,'','DRAFT'),(98,'26OU00020','2026-07-17 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,'',5,NULL,NULL,NULL,NULL,'',1,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','_system','2026-07-16 17:00:00','2026-07-17 06:25:49','','',0,'','DRAFT'),(99,'26OU00021','2026-07-17 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,'',4,NULL,NULL,NULL,NULL,'',0,0,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','','2026-07-17 06:36:25','2026-07-17 06:36:25','','',0,'','DRAFT'),(100,'26OU00022','2026-07-18 00:00:00',-1,'OU','MANUAL',NULL,NULL,0,'',5,NULL,NULL,NULL,NULL,'',0,1,0,0,NULL,NULL,'DRAFT',NULL,NULL,NULL,NULL,'',NULL,0,'','_system','2026-07-17 17:00:00','2026-07-17 17:00:00','','',0,'','CONFIRMED By _system'),(101,'IN/26/00001','2026-08-21 00:00:00',1,'STOCK','MANUAL',NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,'Smoke test StockPostingService',1,0,0,0,NULL,NULL,'POSTED','smoke-test','2026-08-21 21:56:10',NULL,NULL,NULL,NULL,0,'smoke-test','smoke-test','2026-08-21 14:56:10','2026-08-21 14:56:10','DRAFT',NULL,0,NULL,NULL),(102,'IN/26/00002','2026-08-21 00:00:00',1,'STOCK','MANUAL',NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,'Smoke test NULL cur_stock fix',1,0,0,0,NULL,NULL,'POSTED','smoke-test','2026-08-21 21:58:50',NULL,NULL,NULL,NULL,0,'smoke-test','smoke-test','2026-08-21 14:58:50','2026-08-21 14:58:50','DRAFT',NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `rm_stock` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rm_stock_d`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rm_stock_d` (
  `doc_detail_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK detail movement RM',
  `doc_id` bigint unsigned NOT NULL COMMENT 'FK → rm_stock',
  `seq_no` int unsigned NOT NULL DEFAULT '0',
  `material_id` bigint unsigned NOT NULL COMMENT 'FK → tmmst.m_raw_material',
  `worm_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2prd.prd_wo_material',
  `qty` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Qty IN atau OUT (selalu positif)',
  `qty_returned` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_required` decimal(20,4) DEFAULT '0.0000' COMMENT 'Qty kebutuhan',
  `qty_available` decimal(20,4) DEFAULT NULL COMMENT 'Qty stock yang availabel saat ini',
  `qty_booked` decimal(20,4) DEFAULT NULL COMMENT 'Qty booking',
  `qty_system` decimal(20,4) DEFAULT NULL COMMENT 'snapshot saldo saat baris ditarik, TIDAK dibaca ulang saat posting',
  `qty_physical` decimal(20,4) DEFAULT NULL,
  `action_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ISSUE / SUBSTITUTE / PURCHASE',
  `return_condition` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GOOD|SCRAP -- hanya doc_cat=RETURN',
  `source_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK self -> rm_stock_d.doc_detail_id baris OUT yang diretur',
  `parent_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK self → doc_detail_id baris kebutuhan induk (untuk SUBSTITUTE/PURCHASE)',
  `co_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK CO detail dari tmsdi jika terkait order customer',
  `unit_price` decimal(20,4) unsigned NOT NULL DEFAULT '0.0000',
  `qty_unitprice` decimal(20,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT 'qty × unit_price',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL COMMENT 'FK dokumen sumber (p_receive_d, wo_id, dll)',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama tabel referensi',
  `location_id` bigint unsigned NOT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_detail_id`) USING BTREE,
  KEY `idx_rm_d_doc` (`doc_id`) USING BTREE,
  KEY `idx_rm_d_material` (`material_id`) USING BTREE,
  KEY `idx_rm_d_parent` (`parent_detail_id`) USING BTREE,
  KEY `idx_rm_d_source` (`source_detail_id`),
  KEY `idx_rm_d_action` (`action_type`),
  CONSTRAINT `fk_rm_d_parent` FOREIGN KEY (`parent_detail_id`) REFERENCES `rm_stock_d` (`doc_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Detail movement stok RM per bahan baku';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rm_stock_d` WRITE;
/*!40000 ALTER TABLE `rm_stock_d` DISABLE KEYS */;
INSERT INTO `rm_stock_d` VALUES (2,3,0,283,0,6000.0000,0.0000,'',0.0067,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'3',0,'',3,'','','2026-04-24 06:57:26','2026-04-24 06:57:26'),(5,6,0,1,0,60.0000,0.0000,'LBR',60.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'btn_confirm belum jalan',8,'','','2026-04-24 07:49:53','2026-08-21 06:49:53'),(14,24,0,5735,0,4.0000,0.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,76835.0000,307340.0000,NULL,5896,'tmpur.p_receive_d',8,'_migrate','_migrate','2026-06-25 07:14:51','2026-08-21 06:49:53'),(15,24,0,5736,0,10.0000,0.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,86031.0000,860310.0000,NULL,13194,'tmpur.p_receive_d',8,'_migrate','_migrate','2026-06-25 07:14:51','2026-08-21 06:49:53'),(16,24,0,5738,0,12.0000,0.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,80949.0000,971388.0000,NULL,15609,'tmpur.p_receive_d',8,'_migrate','_migrate','2026-06-25 07:14:51','2026-08-21 06:49:53'),(17,24,0,5737,0,10.0000,0.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,75350.0000,753500.0000,NULL,15610,'tmpur.p_receive_d',8,'_migrate','_migrate','2026-06-25 07:14:51','2026-08-21 06:49:53'),(21,25,0,3404,0,2999.0000,0.0000,'1 ',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,NULL,23259,'tmpur.p_receive_d',1,'','','2026-06-25 07:18:28','2026-06-25 07:18:28'),(22,26,0,5706,0,1.0000,0.0000,'LBR',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2120000.0000,2120000.0000,NULL,9973,'tmpur.p_receive_d',8,'_migrate','_migrate','2026-06-25 07:53:12','2026-08-21 06:49:53'),(23,27,0,9,0,100.0000,0.0000,'22',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,NULL,23293,'tmpur.p_receive_d',1,'','','2026-07-02 13:51:33','2026-07-02 13:51:33'),(24,28,0,7,0,10.0000,0.0000,'43',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,NULL,23296,'tmpur.p_receive_d',1,'','','2026-07-03 01:39:46','2026-07-03 01:39:46'),(25,29,0,7,0,10.0000,0.0000,'31',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,'12345',23298,'tmpur.p_receive_d',1,'','','2026-07-03 01:45:09','2026-07-03 01:45:09'),(27,31,0,1,0,1000.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No',0,'',1,'','','2026-07-03 03:01:09','2026-07-03 03:01:27'),(28,32,0,1,0,1000.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 1',0,'',1,'','','2026-07-03 03:17:36','2026-07-03 03:17:36'),(29,33,0,1,0,1000.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 2',0,'',1,'','','2026-07-03 03:28:23','2026-07-03 03:28:23'),(30,34,0,1491,0,1000.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 4',0,'',3,'','','2026-07-03 03:30:15','2026-07-03 03:30:15'),(31,37,0,3058,0,1000.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 5',0,'',1,'','','2026-07-03 04:09:15','2026-07-03 04:09:15'),(32,41,0,1,0,0.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-03 07:37:53','2026-07-03 07:37:53'),(33,53,0,843,0,1300.0000,0.0000,'LBR',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'MAR26-A5-PA1',0,'',1,'','','2026-07-04 06:52:54','2026-07-04 06:52:54'),(34,46,0,3058,0,1000.0000,0.0000,'PCS',1000.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'12345',0,'coba btn_confirm',1,'','','2026-07-06 02:41:28','2026-07-07 06:51:37'),(35,57,0,5533,0,1300.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'MAR26-A5-PA1',0,'',1,'','','2026-07-06 03:04:44','2026-07-06 03:04:44'),(36,55,0,2325,0,1000.0000,0.0000,'PCS',12345.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'12345',0,'',1,'','','2026-07-06 03:11:16','2026-07-06 04:06:42'),(37,59,0,2750,0,0.0000,0.0000,'PCS',12.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'123',0,'',3,'','','2026-07-06 06:12:10','2026-07-06 06:12:10'),(38,60,0,2510,0,0.0000,0.0000,'PCS',12.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'123',0,'',3,'','','2026-07-06 06:12:45','2026-07-06 06:12:45'),(40,62,0,2325,0,123.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'123',0,'',8,'','','2026-07-06 06:25:46','2026-08-21 06:49:53'),(41,47,0,606,0,10.0000,0.0000,'PCS',10.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No',0,'coba confirm_action',1,'','','2026-07-08 01:53:26','2026-07-08 01:54:19'),(42,65,0,1,0,100.0000,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,100000.0000,0.0000,'',0,'',1,'','','2026-07-08 04:53:15','2026-07-08 04:53:15'),(43,66,0,7,0,10.0000,0.0000,'1',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,'',23297,'tmpur.p_receive_d',1,'','','2026-07-09 03:07:28','2026-07-09 03:07:28'),(44,67,0,32,0,10.0000,0.0000,'1',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,'',23264,'tmpur.p_receive_d',1,'','','2026-07-09 03:10:44','2026-07-09 03:10:44'),(45,69,0,1,0,-10.0000,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,100000.0000,0.0000,'',0,'',1,'','','2026-07-09 04:26:42','2026-07-09 04:27:34'),(46,68,0,1,0,-10.0000,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-09 15:49:18','2026-07-09 15:49:18'),(47,72,0,1,0,-10.0000,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 09:16:48','2026-07-10 09:16:48'),(48,73,0,1,0,0.0000,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 09:22:03','2026-07-10 09:22:03'),(49,74,0,1,0,90.0000,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 13:20:10','2026-07-10 13:20:10'),(50,75,0,1,0,0.0000,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 14:55:55','2026-07-10 14:55:55'),(51,76,0,604,0,0.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',8,'','','2026-07-11 00:38:37','2026-08-21 06:49:53'),(52,77,0,604,0,0.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',8,'','','2026-07-11 02:11:28','2026-08-21 06:49:53'),(53,78,0,604,0,0.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',8,'','','2026-07-11 02:18:14','2026-08-21 06:49:53'),(54,79,0,604,0,0.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',8,'','','2026-07-11 02:26:55','2026-08-21 06:49:53'),(55,80,0,604,0,0.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',8,'','','2026-07-11 04:12:52','2026-08-21 06:49:53'),(57,91,0,1,0,50.0000,0.0000,'PCS',50.0000,180.0000,50.0000,NULL,NULL,'SUBTITUTE',NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-16 00:22:10','2026-07-18 01:31:27'),(58,92,0,3404,0,0.0000,0.0000,'PCS',3000.0000,2999.0000,2999.0000,NULL,NULL,'SUBTITUTE',NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-16 01:12:50','2026-07-16 01:12:50'),(62,95,0,1,0,130.0000,0.0000,'PCS',200.0000,130.0000,130.0000,NULL,NULL,'ISSUE',NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-16 04:34:08','2026-07-16 06:20:51'),(63,95,0,5533,0,60.0000,0.0000,'PCS',60.0000,3900.0000,60.0000,NULL,NULL,'SUBTITUTE',NULL,NULL,62,0,0.0000,0.0000,'',0,'',1,'','','2026-07-16 04:37:27','2026-07-16 06:37:30'),(64,95,0,843,0,10.0000,0.0000,'PCS',10.0000,1300.0000,10.0000,NULL,NULL,'SUBTITUTE',NULL,NULL,62,0,0.0000,0.0000,'',0,'',1,'','','2026-07-16 05:31:46','2026-07-16 06:37:37'),(65,96,0,283,0,10.0000,0.0000,'LBR',10.0000,6000.0000,10.0000,NULL,NULL,'ISSUE',NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',3,'','','2026-07-17 01:29:08','2026-07-17 01:29:51'),(66,97,0,3404,0,99.0000,0.0000,'PCS',99.0000,2999.0000,99.0000,NULL,NULL,'ISSUE',NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-17 02:04:35','2026-07-17 02:06:53'),(67,98,0,9,0,10.0000,0.0000,'PCS',10.0000,100.0000,10.0000,NULL,NULL,'ISSUE',NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-17 06:23:03','2026-07-17 06:24:08'),(68,100,0,9,0,10.0000,0.0000,'PCS',10.0000,90.0000,10.0000,NULL,NULL,'ISSUE',NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-18 01:34:59','2026-07-18 01:35:24'),(69,101,1,1,NULL,100.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5000.0000,0.0000,'SMOKE-LOT',NULL,NULL,8,'smoke-test','smoke-test','2026-08-21 14:56:10','2026-08-21 14:56:10'),(70,102,1,2,NULL,25.0000,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1000.0000,0.0000,'SMOKE-NULL',NULL,NULL,8,'smoke-test','smoke-test','2026-08-21 14:58:50','2026-08-21 14:58:50');
/*!40000 ALTER TABLE `rm_stock_d` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rm_stock_d_bak`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rm_stock_d_bak` (
  `doc_detail_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'PK detail movement RM',
  `doc_id` bigint unsigned NOT NULL COMMENT 'FK → rm_stock',
  `material_id` bigint unsigned NOT NULL COMMENT 'FK → tmmst.m_raw_material',
  `worm_id` int unsigned NOT NULL COMMENT 'FK -> tmprd.prd_wo_material \r\nwo rm detail',
  `qty` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Qty IN atau OUT (selalu positif)',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_required` decimal(20,4) DEFAULT '0.0000' COMMENT 'Qty kebutuhan',
  `qty_available` decimal(20,4) DEFAULT NULL COMMENT 'Qty stock yang availabel saat ini',
  `qty_booked` decimal(20,4) DEFAULT NULL COMMENT 'Qty booking',
  `qty_replacement` decimal(20,4) DEFAULT NULL COMMENT 'Qty pengganti',
  `material_replacement_id` bigint unsigned DEFAULT NULL COMMENT 'Material pengganti',
  `action_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ISSUE / SUBSTITUTE / PURCHASE',
  `parent_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK self → doc_detail_id baris kebutuhan induk (untuk SUBSTITUTE/PURCHASE)',
  `co_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK CO detail dari tmsdi jika terkait order customer',
  `unit_price` decimal(20,4) unsigned NOT NULL DEFAULT '0.0000',
  `qty_unitprice` decimal(20,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT 'qty × unit_price',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL COMMENT 'FK dokumen sumber (p_receive_d, wo_id, dll)',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama tabel referensi',
  `location_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_wh_loc',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rm_stock_d_bak` WRITE;
/*!40000 ALTER TABLE `rm_stock_d_bak` DISABLE KEYS */;
INSERT INTO `rm_stock_d_bak` VALUES (2,3,283,0,6000.0000,'',0.0067,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'3',0,'',3,'','','2026-04-24 06:57:26','2026-04-24 06:57:26'),(5,6,1,0,60.0000,'LBR',60.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'btn_confirm belum jalan',0,'','','2026-04-24 07:49:53','2026-07-07 04:46:03'),(14,24,5735,0,4.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,76835.0000,307340.0000,NULL,5896,'tmpur.p_receive_d',0,'_migrate','_migrate','2026-06-25 07:14:51','2026-06-25 07:14:51'),(15,24,5736,0,10.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,86031.0000,860310.0000,NULL,13194,'tmpur.p_receive_d',NULL,'_migrate','_migrate','2026-06-25 07:14:51','2026-06-25 07:14:51'),(16,24,5738,0,12.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,80949.0000,971388.0000,NULL,15609,'tmpur.p_receive_d',NULL,'_migrate','_migrate','2026-06-25 07:14:51','2026-06-25 07:14:51'),(17,24,5737,0,10.0000,'KG',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,75350.0000,753500.0000,NULL,15610,'tmpur.p_receive_d',NULL,'_migrate','_migrate','2026-06-25 07:14:51','2026-06-25 07:14:51'),(21,25,3404,0,2999.0000,'1 ',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,NULL,23259,'tmpur.p_receive_d',1,'','','2026-06-25 07:18:28','2026-06-25 07:18:28'),(22,26,5706,0,1.0000,'LBR',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2120000.0000,2120000.0000,NULL,9973,'tmpur.p_receive_d',NULL,'_migrate','_migrate','2026-06-25 07:53:12','2026-06-25 07:53:12'),(23,27,9,0,100.0000,'22',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,NULL,23293,'tmpur.p_receive_d',1,'','','2026-07-02 13:51:33','2026-07-02 13:51:33'),(24,28,7,0,10.0000,'43',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,NULL,23296,'tmpur.p_receive_d',1,'','','2026-07-03 01:39:46','2026-07-03 01:39:46'),(25,29,7,0,10.0000,'31',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,'12345',23298,'tmpur.p_receive_d',1,'','','2026-07-03 01:45:09','2026-07-03 01:45:09'),(27,31,1,0,1000.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No',0,'',1,'','','2026-07-03 03:01:09','2026-07-03 03:01:27'),(28,32,1,0,1000.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 1',0,'',1,'','','2026-07-03 03:17:36','2026-07-03 03:17:36'),(29,33,1,0,1000.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 2',0,'',1,'','','2026-07-03 03:28:23','2026-07-03 03:28:23'),(30,34,1491,0,1000.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 4',0,'',3,'','','2026-07-03 03:30:15','2026-07-03 03:30:15'),(31,37,3058,0,1000.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No 5',0,'',1,'','','2026-07-03 04:09:15','2026-07-03 04:09:15'),(32,41,1,0,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-03 07:37:53','2026-07-03 07:37:53'),(33,53,843,0,1300.0000,'LBR',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'MAR26-A5-PA1',0,'',1,'','','2026-07-04 06:52:54','2026-07-04 06:52:54'),(34,46,3058,0,1000.0000,'PCS',1000.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'12345',0,'coba btn_confirm',1,'','','2026-07-06 02:41:28','2026-07-07 06:51:37'),(35,57,5533,0,1300.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'MAR26-A5-PA1',0,'',1,'','','2026-07-06 03:04:44','2026-07-06 03:04:44'),(36,55,2325,0,1000.0000,'PCS',12345.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'12345',0,'',1,'','','2026-07-06 03:11:16','2026-07-06 04:06:42'),(37,59,2750,0,0.0000,'PCS',12.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'123',0,'',3,'','','2026-07-06 06:12:10','2026-07-06 06:12:10'),(38,60,2510,0,0.0000,'PCS',12.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'123',0,'',3,'','','2026-07-06 06:12:45','2026-07-06 06:12:45'),(40,62,2325,0,123.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'123',0,'',0,'','','2026-07-06 06:25:46','2026-07-06 06:25:46'),(41,47,606,0,10.0000,'PCS',10.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'Lot No',0,'coba confirm_action',1,'','','2026-07-08 01:53:26','2026-07-08 01:54:19'),(42,65,1,0,100.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,100000.0000,0.0000,'',0,'',1,'','','2026-07-08 04:53:15','2026-07-08 04:53:15'),(43,66,7,0,10.0000,'1',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,'',23297,'tmpur.p_receive_d',1,'','','2026-07-09 03:07:28','2026-07-09 03:07:28'),(44,67,32,0,10.0000,'1',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,'',23264,'tmpur.p_receive_d',1,'','','2026-07-09 03:10:44','2026-07-09 03:10:44'),(45,69,1,0,-10.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,100000.0000,0.0000,'',0,'',1,'','','2026-07-09 04:26:42','2026-07-09 04:27:34'),(46,68,1,0,-10.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-09 15:49:18','2026-07-09 15:49:18'),(47,72,1,0,-10.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 09:16:48','2026-07-10 09:16:48'),(48,73,1,0,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 09:22:03','2026-07-10 09:22:03'),(49,74,1,0,90.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 13:20:10','2026-07-10 13:20:10'),(50,75,1,0,0.0000,'PCS',100.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-10 14:55:55','2026-07-10 14:55:55'),(51,76,604,0,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',0,'','','2026-07-11 00:38:37','2026-07-11 00:38:37'),(52,77,604,0,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',0,'','','2026-07-11 02:11:28','2026-07-11 02:11:28'),(53,78,604,0,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',0,'','','2026-07-11 02:18:14','2026-07-11 02:18:14'),(54,79,604,0,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',0,'','','2026-07-11 02:26:55','2026-07-11 02:26:55'),(55,80,604,0,0.0000,'PCS',0.0000,NULL,NULL,NULL,NULL,NULL,NULL,0,0.0000,0.0000,'',0,'',0,'','','2026-07-11 04:12:52','2026-07-11 04:12:52'),(57,91,1,0,0.0000,'PCS',50.0000,180.0000,50.0000,50.0000,283,'SUBTITUTE',NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-16 00:22:10','2026-07-16 00:32:02'),(58,92,3404,0,0.0000,'PCS',3000.0000,2999.0000,2999.0000,1.0000,1679,'SUBTITUTE',NULL,0,0.0000,0.0000,'',0,'',1,'','','2026-07-16 01:12:50','2026-07-16 01:12:50');
/*!40000 ALTER TABLE `rm_stock_d_bak` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rm_stockcard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rm_stockcard` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK kartu stok RM',
  `previous_doc_id` bigint unsigned DEFAULT NULL COMMENT 'ID record sebelumnya — chain untuk audit trail',
  `doc_date` datetime NOT NULL,
  `material_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_raw_material',
  `doc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BB / IN / OUT / ADJ',
  `location_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL COMMENT 'Duplikasi untuk laporan cepat',
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Qty signed: + masuk, - keluar',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK → rm_stock_d.doc_detail_id atau p_receive_d.doc_detail_id',
  `reference_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '''tm2inv.rm_stock_d'' | ''tm2pur.pv_line''',
  `qty_bb` decimal(20,4) DEFAULT NULL COMMENT 'Saldo sebelum movement',
  `qty_in` decimal(20,4) DEFAULT NULL COMMENT 'Qty masuk pada record ini',
  `qty_out` decimal(20,4) DEFAULT NULL COMMENT 'Qty keluar pada record ini',
  `qty_adj` decimal(20,4) DEFAULT NULL COMMENT 'Qty penyesuaian pada record ini',
  `qty_eb` decimal(20,4) DEFAULT NULL COMMENT 'Saldo setelah movement',
  `post_date` date DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `value_bb` decimal(20,4) DEFAULT NULL,
  `value_in` decimal(20,4) DEFAULT NULL,
  `value_out` decimal(20,4) DEFAULT NULL,
  `value_adj` decimal(20,4) DEFAULT NULL,
  `value_eb` decimal(20,4) DEFAULT NULL,
  `avg_unitprice` decimal(30,5) DEFAULT NULL,
  `period_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`doc_id`) USING BTREE,
  UNIQUE KEY `uq_rm_sc_ref` (`reference_name`,`reference_detail_id`),
  KEY `idx_rm_sc_material` (`material_id`) USING BTREE,
  KEY `idx_rm_sc_date` (`doc_date`) USING BTREE,
  KEY `idx_rm_sc_saldo` (`material_id`,`location_id`,`doc_date`,`doc_id`),
  KEY `idx_rm_sc_period` (`period_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Kartu stok RM Master stok RM per periode  — diisi otomatis oleh trigger saat rm_stock confirm';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rm_stockcard` WRITE;
/*!40000 ALTER TABLE `rm_stockcard` DISABLE KEYS */;
INSERT INTO `rm_stockcard` VALUES (1,NULL,'2026-04-24 16:12:30',283,'IN',3,1,'3',6000.0000,'',2,'tm2inv.rm_stock_d',0.0000,6000.0000,0.0000,0.0000,6000.0000,NULL,'',NULL,'2026-04-24 06:58:12','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(10,NULL,'2016-01-01 00:00:00',5735,'IN',8,0,NULL,4.0000,'KG',14,'tm2inv.rm_stock_d',0.0000,4.0000,0.0000,0.0000,4.0000,NULL,'_migrate',NULL,'2026-06-25 07:14:51','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(11,NULL,'2016-01-01 00:00:00',5736,'IN',8,0,NULL,10.0000,'KG',15,'tm2inv.rm_stock_d',0.0000,10.0000,0.0000,0.0000,10.0000,NULL,'_migrate',NULL,'2026-06-25 07:14:51','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(12,NULL,'2016-01-01 00:00:00',5738,'IN',8,0,NULL,12.0000,'KG',16,'tm2inv.rm_stock_d',0.0000,12.0000,0.0000,0.0000,12.0000,NULL,'_migrate',NULL,'2026-06-25 07:14:51','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(13,NULL,'2016-01-01 00:00:00',5737,'IN',8,0,NULL,10.0000,'KG',17,'tm2inv.rm_stock_d',0.0000,10.0000,0.0000,0.0000,10.0000,NULL,'_migrate',NULL,'2026-06-25 07:14:51','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(14,NULL,'2026-06-25 00:00:00',3404,'IN',1,5,NULL,2999.0000,'1 ',21,'tm2inv.rm_stock_d',0.0000,2999.0000,0.0000,0.0000,2999.0000,NULL,'',NULL,'2026-06-25 07:18:28','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(15,NULL,'2016-01-04 00:00:00',5706,'IN',8,0,NULL,1.0000,'LBR',22,'tm2inv.rm_stock_d',0.0000,1.0000,0.0000,0.0000,1.0000,NULL,'_migrate',NULL,'2026-06-25 07:53:12','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(16,NULL,'2026-07-02 00:00:00',9,'IN',1,0,NULL,100.0000,'22',23,'tm2inv.rm_stock_d',0.0000,100.0000,0.0000,0.0000,100.0000,NULL,'',NULL,'2026-07-02 13:51:33','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(17,NULL,'2026-07-03 00:00:00',7,'IN',1,5,NULL,10.0000,'43',24,'tm2inv.rm_stock_d',0.0000,10.0000,0.0000,0.0000,10.0000,NULL,'',NULL,'2026-07-03 01:39:46','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(18,17,'2026-07-03 00:00:00',7,'IN',1,5,'12345',10.0000,'31',25,'tm2inv.rm_stock_d',10.0000,10.0000,0.0000,0.0000,20.0000,NULL,'',NULL,'2026-07-03 01:45:09','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(19,NULL,'2026-07-03 00:00:00',3058,'IN',1,5,'Lot No 5',1000.0000,'PCS',31,'tm2inv.rm_stock_d',0.0000,1000.0000,0.0000,0.0000,1000.0000,NULL,'',NULL,'2026-07-03 06:17:15','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(20,NULL,'2026-07-04 00:00:00',843,'BB',1,1,'MAR26-A5-PA1',1300.0000,'LBR',33,'tm2inv.rm_stock_d',0.0000,1300.0000,0.0000,0.0000,1300.0000,NULL,'',NULL,'2026-07-06 01:26:43','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(21,NULL,'2026-07-06 00:00:00',5533,'BB',1,1,'MAR26-A5-PA1',1300.0000,'PCS',35,'tm2inv.rm_stock_d',0.0000,1300.0000,0.0000,0.0000,1300.0000,NULL,'',NULL,'2026-07-06 03:58:45','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(26,NULL,'2026-07-06 00:00:00',2325,'IN',8,5,'123',123.0000,'PCS',40,'tm2inv.rm_stock_d',0.0000,123.0000,0.0000,0.0000,123.0000,NULL,'',NULL,'2026-07-06 06:25:57','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(27,NULL,'2026-07-06 13:33:53',1,'IN',1,5,'Lot No',1000.0000,'PCS',27,'tm2inv.rm_stock_d',0.0000,1000.0000,0.0000,0.0000,1000.0000,NULL,'',NULL,'2026-07-06 06:33:53','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(28,NULL,'2026-04-24 00:00:00',1,'OUT',8,2,'',-60.0000,'LBR',5,'tm2inv.rm_stock_d',0.0000,0.0000,60.0000,0.0000,-60.0000,NULL,'',NULL,'2026-07-07 07:04:21','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(29,NULL,'2026-07-03 00:00:00',3058,'OUT',1,5,'12345',-1000.0000,'PCS',34,'tm2inv.rm_stock_d',0.0000,0.0000,1000.0000,0.0000,-1000.0000,NULL,'',NULL,'2026-07-07 07:04:21','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(30,NULL,'2026-07-03 00:00:00',606,'OUT',1,5,'Lot No',-10.0000,'PCS',41,'tm2inv.rm_stock_d',0.0000,0.0000,10.0000,0.0000,-10.0000,NULL,'',NULL,'2026-07-08 02:09:26','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(31,NULL,'2026-07-08 00:00:00',1,'ADJ',1,5,'',100.0000,'PCS',42,'tm2inv.rm_stock_d',0.0000,0.0000,0.0000,100.0000,100.0000,NULL,'',NULL,'2026-07-08 08:46:13','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(32,18,'2026-07-03 00:00:00',7,'IN',1,5,'',10.0000,'1',43,'tm2inv.rm_stock_d',20.0000,10.0000,0.0000,0.0000,30.0000,NULL,'',NULL,'2026-07-09 03:07:28','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(33,NULL,'2026-07-02 00:00:00',32,'IN',1,0,'',10.0000,'1',44,'tm2inv.rm_stock_d',0.0000,10.0000,0.0000,0.0000,10.0000,NULL,'',NULL,'2026-07-09 03:10:44','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(34,31,'2026-07-09 00:00:00',1,'AD',1,5,'',-10.0000,'PCS',45,'tm2inv.rm_stock_d',100.0000,0.0000,0.0000,-10.0000,90.0000,NULL,'',NULL,'2026-07-10 01:31:11','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(35,31,'2026-07-09 00:00:00',1,'AD',1,5,'',-10.0000,'PCS',46,'tm2inv.rm_stock_d',100.0000,0.0000,0.0000,-10.0000,90.0000,NULL,'',NULL,'2026-07-10 01:32:21','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(36,35,'2026-07-10 00:00:00',1,'AD',1,5,'',-10.0000,'PCS',47,'tm2inv.rm_stock_d',90.0000,0.0000,0.0000,-10.0000,80.0000,NULL,'',NULL,'2026-07-10 09:16:57','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(37,36,'2026-07-10 00:00:00',1,'AD',1,5,'',90.0000,'PCS',49,'tm2inv.rm_stock_d',80.0000,0.0000,0.0000,90.0000,170.0000,NULL,'',NULL,'2026-07-10 13:20:26','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(39,38,'2026-07-16 00:00:00',1,'OUT',1,5,'',-130.0000,'PCS',62,'tm2inv.rm_stock_d',180.0000,0.0000,130.0000,0.0000,50.0000,NULL,'',NULL,'2026-07-17 01:18:11','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(40,25,'2026-07-16 00:00:00',5533,'OUT',1,5,'',-60.0000,'PCS',63,'tm2inv.rm_stock_d',3900.0000,0.0000,60.0000,0.0000,3840.0000,NULL,'',NULL,'2026-07-17 01:18:11','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(41,20,'2026-07-16 00:00:00',843,'OUT',1,5,'',-10.0000,'PCS',64,'tm2inv.rm_stock_d',1300.0000,0.0000,10.0000,0.0000,1290.0000,NULL,'',NULL,'2026-07-17 01:18:11','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(45,14,'2026-07-17 00:00:00',3404,'OUT',1,5,'',-99.0000,'PCS',66,'tm2inv.rm_stock_d',2999.0000,0.0000,99.0000,0.0000,2900.0000,NULL,'',NULL,'2026-07-17 06:21:05','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(46,16,'2026-07-17 00:00:00',9,'OUT',1,5,'',-10.0000,'PCS',67,'tm2inv.rm_stock_d',100.0000,0.0000,10.0000,0.0000,90.0000,NULL,'',NULL,'2026-07-17 06:25:49','2026-08-21 07:11:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(47,28,'2026-08-21 00:00:00',1,'IN',8,1,'SMOKE-LOT',100.0000,'PCS',69,'tm2inv.rm_stock_d',-60.0000,100.0000,0.0000,0.0000,40.0000,NULL,'smoke-test','smoke-test','2026-08-21 14:56:10','2026-08-21 14:56:10',NULL,NULL,NULL,NULL,500000.0000,12500.00000,NULL),(48,NULL,'2026-08-21 00:00:00',2,'IN',8,1,'SMOKE-NULL',25.0000,'PCS',70,'tm2inv.rm_stock_d',0.0000,25.0000,0.0000,0.0000,25.0000,NULL,'smoke-test','smoke-test','2026-08-21 14:58:50','2026-08-21 14:58:50',NULL,NULL,NULL,NULL,25000.0000,1000.00000,NULL);
/*!40000 ALTER TABLE `rm_stockcard` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sfg_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sfg_stock` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'PK header movement SFG',
  `doc_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor dokumen — bisa nomor WO/SPK dari produksi',
  `doc_date` datetime NOT NULL,
  `doc_type` tinyint DEFAULT NULL COMMENT 'IN=1 (dari produksi), OUT=-1 (ke FG atau write-off)',
  `doc_cat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BB | STOCK | ADJ | OPNAME | CANCEL (WO dibatalkan)',
  `ref_doc_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'MANUAL',
  `ref_doc_id` bigint unsigned DEFAULT NULL,
  `ref_doc_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product (SKU induk, untuk filter laporan)',
  `wo_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmprd.prd_wo (Work Order sumber)',
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `issued_to_dept` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `process_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stat_confirm` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0=draft, 1=confirmed → trigger otomatis isi sfg_stockcard',
  `stat_cancel` tinyint unsigned NOT NULL DEFAULT '0',
  `stat_closed` tinyint unsigned NOT NULL DEFAULT '0',
  `post_date` date DEFAULT NULL,
  `period_id` bigint unsigned DEFAULT NULL,
  `flow_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `posted_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `opn_start` datetime DEFAULT NULL COMMENT 'opname start',
  `opn_stop` datetime DEFAULT NULL COMMENT 'opname stop',
  `opn_officer` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nama petugas yang melakukan opname',
  `opn_witness` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_scope` tinyint unsigned NOT NULL DEFAULT '0',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_id`) USING BTREE,
  UNIQUE KEY `uq_sfg_stock_docno` (`doc_no`),
  KEY `idx_sfg_s_product` (`product_id`) USING BTREE,
  KEY `idx_sfg_s_wo` (`wo_id`) USING BTREE,
  KEY `idx_sfg_s_date` (`doc_date`) USING BTREE,
  KEY `idx_sfg_stock_flow` (`flow_status`,`doc_cat`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Header movement stok SFG/WIP — dikonfirm → trigger isi sfg_stockcard';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sfg_stock` WRITE;
/*!40000 ALTER TABLE `sfg_stock` DISABLE KEYS */;
INSERT INTO `sfg_stock` VALUES (1,'SFG-IN-260001','2026-08-20 21:17:03',1,'IN','MANUAL',NULL,NULL,1,NULL,3,NULL,NULL,NULL,NULL,'Data uji -- barang setengah jadi kembali dari subcon (dummy).',1,0,0,NULL,NULL,'CONFIRMED',NULL,NULL,NULL,NULL,NULL,NULL,0,'system','system','2026-08-20 14:17:03','2026-08-20 14:17:03'),(2,'IN/26/00001','2026-08-20 00:00:00',1,'STOCK','MANUAL',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,NULL,'Data uji Stock In SFG -- via StockPostingService, bukan insert manual.',1,0,0,NULL,NULL,'POSTED','system','2026-08-21 21:57:13',NULL,NULL,NULL,NULL,0,'system','system','2026-08-21 14:57:13','2026-08-21 14:57:13');
/*!40000 ALTER TABLE `sfg_stock` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sfg_stock_d`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sfg_stock_d` (
  `doc_detail_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` bigint unsigned NOT NULL COMMENT 'FK → sfg_stock',
  `seq_no` int unsigned NOT NULL DEFAULT '0',
  `parts_id` bigint unsigned NOT NULL COMMENT 'FK → tmmst.m_product_parts (badan / tutup / komponen lain)',
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'Denormalisasi dari m_product_parts.product_id — query cepat',
  `qty` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `qty_returned` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `qty_system` decimal(20,4) DEFAULT NULL,
  `qty_physical` decimal(20,4) DEFAULT NULL,
  `action_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_condition` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_detail_id` bigint unsigned DEFAULT NULL,
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `qty_unitprice` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `reference_id` bigint unsigned DEFAULT NULL COMMENT 'FK dokumen sumber: wo_id, delivery_id, dll',
  `reference_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` bigint unsigned NOT NULL,
  `cancel_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alasan pembatalan — diisi jika doc_cat = CANCEL',
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_detail_id`) USING BTREE,
  KEY `idx_sfg_d_doc` (`doc_id`) USING BTREE,
  KEY `idx_sfg_d_parts` (`parts_id`) USING BTREE,
  KEY `idx_sfg_d_product` (`product_id`) USING BTREE,
  KEY `idx_sfg_stock_d_source` (`source_detail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Detail movement stok SFG per komponen produk';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sfg_stock_d` WRITE;
/*!40000 ALTER TABLE `sfg_stock_d` DISABLE KEYS */;
INSERT INTO `sfg_stock_d` VALUES (1,1,0,2,1,300.0000,0.0000,NULL,NULL,NULL,NULL,NULL,'PCS','LOT-SFG-0001',0.0000,0.0000,NULL,NULL,4,NULL,'system','system','2026-08-20 14:17:03','2026-08-20 14:17:03'),(2,2,1,2,NULL,80.0000,0.0000,NULL,NULL,NULL,NULL,NULL,'PCS','LOT-SFGIN-0001',0.0000,0.0000,NULL,NULL,4,NULL,'system','system','2026-08-21 14:57:13','2026-08-21 14:57:13');
/*!40000 ALTER TABLE `sfg_stock_d` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sfg_stockcard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sfg_stockcard` (
  `doc_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `previous_doc_id` bigint unsigned DEFAULT NULL,
  `doc_date` datetime NOT NULL,
  `parts_id` bigint unsigned DEFAULT NULL COMMENT 'FK → tmmst.m_product_parts',
  `product_id` bigint unsigned DEFAULT NULL COMMENT 'Denormalisasi untuk query cepat',
  `doc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BB / IN / OUT / ADJ / CANCEL',
  `location_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `lot_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `uom_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_detail_id` bigint unsigned DEFAULT NULL COMMENT 'FK → sfg_stock_d.doc_detail_id',
  `qty_bb` decimal(20,4) DEFAULT NULL,
  `qty_in` decimal(20,4) DEFAULT NULL,
  `qty_out` decimal(20,4) DEFAULT NULL,
  `qty_adj` decimal(20,4) DEFAULT NULL,
  `qty_eb` decimal(20,4) DEFAULT NULL,
  `post_date` date DEFAULT NULL,
  `create_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `create_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_log` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reference_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_bb` decimal(20,4) DEFAULT NULL,
  `value_in` decimal(20,4) DEFAULT NULL,
  `value_out` decimal(20,4) DEFAULT NULL,
  `value_adj` decimal(20,4) DEFAULT NULL,
  `value_eb` decimal(20,4) DEFAULT NULL,
  `avg_unitprice` decimal(30,5) DEFAULT NULL,
  `period_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`doc_id`) USING BTREE,
  UNIQUE KEY `uq_sfg_stockcard_ref` (`reference_name`,`reference_detail_id`),
  KEY `idx_sfg_sc_parts` (`parts_id`) USING BTREE,
  KEY `idx_sfg_sc_product` (`product_id`) USING BTREE,
  KEY `idx_sfg_sc_date` (`doc_date`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Kartu stok SFG Master stok SFG per periode — diisi otomatis oleh trigger saat sfg_stock confirm';
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sfg_stockcard` WRITE;
/*!40000 ALTER TABLE `sfg_stockcard` DISABLE KEYS */;
INSERT INTO `sfg_stockcard` VALUES (1,NULL,'2026-08-20 21:17:03',2,1,'IN',4,3,'LOT-SFG-0001',300.0000,'PCS',1,0.0000,300.0000,0.0000,0.0000,300.0000,NULL,'system','system','2026-08-20 14:17:03','2026-08-20 14:17:03','tm2inv.sfg_stock_d',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,NULL,'2026-08-20 00:00:00',2,NULL,'IN',4,3,'LOT-SFGIN-0001',80.0000,'PCS',2,0.0000,80.0000,0.0000,0.0000,80.0000,NULL,'system','system','2026-08-21 14:57:13','2026-08-21 14:57:13','tm2inv.sfg_stock_d',NULL,NULL,NULL,NULL,0.0000,0.00000,NULL);
/*!40000 ALTER TABLE `sfg_stockcard` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `v_fg_onhand`;
/*!50001 DROP VIEW IF EXISTS `v_fg_onhand`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_fg_onhand` AS SELECT 
 1 AS `product_id`,
 1 AS `parts_id`,
 1 AS `location_id`,
 1 AS `warehouse_id`,
 1 AS `warehouse_name`,
 1 AS `location_name`,
 1 AS `location_code`,
 1 AS `is_usable`,
 1 AS `uom_code`,
 1 AS `qty_onhand`,
 1 AS `value_onhand`,
 1 AS `avg_unitprice`,
 1 AS `last_movement`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_inv_movement`;
/*!50001 DROP VIEW IF EXISTS `v_inv_movement`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_inv_movement` AS SELECT 
 1 AS `stock_class`,
 1 AS `doc_id`,
 1 AS `doc_date`,
 1 AS `doc_type`,
 1 AS `item_id`,
 1 AS `item_code`,
 1 AS `item_name`,
 1 AS `parts_id`,
 1 AS `warehouse_id`,
 1 AS `warehouse_name`,
 1 AS `location_id`,
 1 AS `location_name`,
 1 AS `is_usable`,
 1 AS `lot_no`,
 1 AS `uom_code`,
 1 AS `doc_no`,
 1 AS `doc_cat`,
 1 AS `ref_doc_no`,
 1 AS `qty_bb`,
 1 AS `qty_in`,
 1 AS `qty_out`,
 1 AS `qty_adj`,
 1 AS `qty_eb`,
 1 AS `value_eb`,
 1 AS `avg_unitprice`,
 1 AS `period_id`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_inv_wo_reconcile`;
/*!50001 DROP VIEW IF EXISTS `v_inv_wo_reconcile`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_inv_wo_reconcile` AS SELECT 
 1 AS `wo_id`,
 1 AS `wo_no`,
 1 AS `material_id`,
 1 AS `material_code`,
 1 AS `material_name`,
 1 AS `uom_code`,
 1 AS `qty_issued`,
 1 AS `qty_return_good`,
 1 AS `qty_return_scrap`,
 1 AS `qty_net_used`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_rm_available`;
/*!50001 DROP VIEW IF EXISTS `v_rm_available`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_rm_available` AS SELECT 
 1 AS `material_id`,
 1 AS `location_id`,
 1 AS `warehouse_id`,
 1 AS `uom_code`,
 1 AS `is_usable`,
 1 AS `qty_onhand`,
 1 AS `qty_booked`,
 1 AS `qty_available`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_rm_onhand`;
/*!50001 DROP VIEW IF EXISTS `v_rm_onhand`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_rm_onhand` AS SELECT 
 1 AS `material_id`,
 1 AS `location_id`,
 1 AS `warehouse_id`,
 1 AS `warehouse_name`,
 1 AS `location_name`,
 1 AS `location_code`,
 1 AS `is_usable`,
 1 AS `uom_code`,
 1 AS `qty_onhand`,
 1 AS `value_onhand`,
 1 AS `avg_unitprice`,
 1 AS `last_movement`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_rm_out_returnable`;
/*!50001 DROP VIEW IF EXISTS `v_rm_out_returnable`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_rm_out_returnable` AS SELECT 
 1 AS `doc_id`,
 1 AS `doc_no`,
 1 AS `doc_date`,
 1 AS `wo_id`,
 1 AS `ref_doc_no`,
 1 AS `doc_detail_id`,
 1 AS `material_id`,
 1 AS `material_code`,
 1 AS `material_name`,
 1 AS `lot_no`,
 1 AS `location_id`,
 1 AS `location_name`,
 1 AS `uom_code`,
 1 AS `action_type`,
 1 AS `qty_issued`,
 1 AS `qty_returned`,
 1 AS `qty_returnable`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_sfg_onhand`;
/*!50001 DROP VIEW IF EXISTS `v_sfg_onhand`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_sfg_onhand` AS SELECT 
 1 AS `parts_id`,
 1 AS `location_id`,
 1 AS `warehouse_id`,
 1 AS `warehouse_name`,
 1 AS `location_name`,
 1 AS `location_code`,
 1 AS `is_usable`,
 1 AS `uom_code`,
 1 AS `qty_onhand`,
 1 AS `value_onhand`,
 1 AS `avg_unitprice`,
 1 AS `last_movement`*/;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v_fg_onhand`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_fg_onhand` AS select `sc`.`product_id` AS `product_id`,`sc`.`parts_id` AS `parts_id`,`sc`.`location_id` AS `location_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`l`.`location_name` AS `location_name`,`l`.`location_code` AS `location_code`,`l`.`is_usable` AS `is_usable`,`sc`.`uom_code` AS `uom_code`,`sc`.`qty_eb` AS `qty_onhand`,`sc`.`value_eb` AS `value_onhand`,`sc`.`avg_unitprice` AS `avg_unitprice`,`sc`.`doc_date` AS `last_movement` from (((`fg_stockcard` `sc` join (select `fg_stockcard`.`c_sku_key` AS `c_sku_key`,`fg_stockcard`.`location_id` AS `location_id`,max(concat(date_format(`fg_stockcard`.`doc_date`,'%Y%m%d%H%i%s'),lpad(`fg_stockcard`.`doc_id`,12,'0'))) AS `mx` from `fg_stockcard` group by `fg_stockcard`.`c_sku_key`,`fg_stockcard`.`location_id`) `x` on(((`x`.`c_sku_key` = `sc`.`c_sku_key`) and (`x`.`location_id` = `sc`.`location_id`) and (`x`.`mx` = concat(date_format(`sc`.`doc_date`,'%Y%m%d%H%i%s'),lpad(`sc`.`doc_id`,12,'0')))))) left join `tm2mst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tm2mst`.`m_warehouse` `w` on((`w`.`id_warehouse` = `sc`.`warehouse_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_inv_movement`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_inv_movement` AS select 'RM' AS `stock_class`,`sc`.`doc_id` AS `doc_id`,`sc`.`doc_date` AS `doc_date`,`sc`.`doc_type` AS `doc_type`,`sc`.`material_id` AS `item_id`,`m`.`material_code` AS `item_code`,`m`.`material_name` AS `item_name`,NULL AS `parts_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`sc`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`l`.`is_usable` AS `is_usable`,`sc`.`lot_no` AS `lot_no`,`sc`.`uom_code` AS `uom_code`,`h`.`doc_no` AS `doc_no`,`h`.`doc_cat` AS `doc_cat`,`h`.`ref_doc_no` AS `ref_doc_no`,`sc`.`qty_bb` AS `qty_bb`,`sc`.`qty_in` AS `qty_in`,`sc`.`qty_out` AS `qty_out`,`sc`.`qty_adj` AS `qty_adj`,`sc`.`qty_eb` AS `qty_eb`,`sc`.`value_eb` AS `value_eb`,`sc`.`avg_unitprice` AS `avg_unitprice`,`sc`.`period_id` AS `period_id` from (((((`rm_stockcard` `sc` left join `rm_stock_d` `d` on(((`d`.`doc_detail_id` = `sc`.`reference_detail_id`) and (`sc`.`reference_name` = 'tm2inv.rm_stock_d')))) left join `rm_stock` `h` on((`h`.`doc_id` = `d`.`doc_id`))) left join `tm2mst`.`m_raw_material` `m` on((`m`.`material_id` = `sc`.`material_id`))) left join `tm2mst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tm2mst`.`m_warehouse` `w` on((`w`.`id_warehouse` = `sc`.`warehouse_id`))) union all select 'SFG' AS `SFG`,`sc`.`doc_id` AS `doc_id`,`sc`.`doc_date` AS `doc_date`,`sc`.`doc_type` AS `doc_type`,`sc`.`parts_id` AS `parts_id`,`pp`.`part_code` AS `part_code`,`pp`.`part_name` AS `part_name`,`sc`.`parts_id` AS `parts_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`sc`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`l`.`is_usable` AS `is_usable`,`sc`.`lot_no` AS `lot_no`,`sc`.`uom_code` AS `uom_code`,`h`.`doc_no` AS `doc_no`,`h`.`doc_cat` AS `doc_cat`,`h`.`ref_doc_no` AS `ref_doc_no`,`sc`.`qty_bb` AS `qty_bb`,`sc`.`qty_in` AS `qty_in`,`sc`.`qty_out` AS `qty_out`,`sc`.`qty_adj` AS `qty_adj`,`sc`.`qty_eb` AS `qty_eb`,`sc`.`value_eb` AS `value_eb`,`sc`.`avg_unitprice` AS `avg_unitprice`,`sc`.`period_id` AS `period_id` from (((((`sfg_stockcard` `sc` left join `sfg_stock_d` `d` on(((`d`.`doc_detail_id` = `sc`.`reference_detail_id`) and (`sc`.`reference_name` = 'tm2inv.sfg_stock_d')))) left join `sfg_stock` `h` on((`h`.`doc_id` = `d`.`doc_id`))) left join `tm2mst`.`prod_part` `pp` on((`pp`.`id_part` = `sc`.`parts_id`))) left join `tm2mst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tm2mst`.`m_warehouse` `w` on((`w`.`id_warehouse` = `sc`.`warehouse_id`))) union all select 'FG' AS `FG`,`sc`.`doc_id` AS `doc_id`,`sc`.`doc_date` AS `doc_date`,`sc`.`doc_type` AS `doc_type`,`sc`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_name` AS `product_name`,`sc`.`parts_id` AS `parts_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`sc`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`l`.`is_usable` AS `is_usable`,`sc`.`lot_no` AS `lot_no`,`sc`.`uom_code` AS `uom_code`,`h`.`doc_no` AS `doc_no`,`h`.`doc_cat` AS `doc_cat`,`h`.`ref_doc_no` AS `ref_doc_no`,`sc`.`qty_bb` AS `qty_bb`,`sc`.`qty_in` AS `qty_in`,`sc`.`qty_out` AS `qty_out`,`sc`.`qty_adj` AS `qty_adj`,`sc`.`qty_eb` AS `qty_eb`,`sc`.`value_eb` AS `value_eb`,`sc`.`avg_unitprice` AS `avg_unitprice`,`sc`.`period_id` AS `period_id` from (((((`fg_stockcard` `sc` left join `fg_stock_d` `d` on(((`d`.`doc_detail_id` = `sc`.`reference_detail_id`) and (`sc`.`reference_name` = 'tm2inv.fg_stock_d')))) left join `fg_stock` `h` on((`h`.`doc_id` = `d`.`doc_id`))) left join `tm2mst`.`prod_product` `p` on((`p`.`id_product` = `sc`.`product_id`))) left join `tm2mst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tm2mst`.`m_warehouse` `w` on((`w`.`id_warehouse` = `sc`.`warehouse_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_inv_wo_reconcile`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_inv_wo_reconcile` AS select `h`.`wo_id` AS `wo_id`,`h`.`ref_doc_no` AS `wo_no`,`d`.`material_id` AS `material_id`,`m`.`material_code` AS `material_code`,`m`.`material_name` AS `material_name`,`d`.`uom_code` AS `uom_code`,sum((case when ((`h`.`doc_cat` = 'STOCK') and (`h`.`doc_type` = -(1))) then `d`.`qty` else 0 end)) AS `qty_issued`,sum((case when ((`h`.`doc_cat` = 'RETURN') and (`d`.`return_condition` = 'GOOD')) then `d`.`qty` else 0 end)) AS `qty_return_good`,sum((case when ((`h`.`doc_cat` = 'RETURN') and (`d`.`return_condition` = 'SCRAP')) then `d`.`qty` else 0 end)) AS `qty_return_scrap`,(sum((case when ((`h`.`doc_cat` = 'STOCK') and (`h`.`doc_type` = -(1))) then `d`.`qty` else 0 end)) - sum((case when (`h`.`doc_cat` = 'RETURN') then `d`.`qty` else 0 end))) AS `qty_net_used` from ((`rm_stock` `h` join `rm_stock_d` `d` on((`d`.`doc_id` = `h`.`doc_id`))) left join `tm2mst`.`m_raw_material` `m` on((`m`.`material_id` = `d`.`material_id`))) where ((`h`.`flow_status` = 'POSTED') and (`h`.`wo_id` is not null) and (ifnull(`d`.`action_type`,'ISSUE') <> 'PURCHASE')) group by `h`.`wo_id`,`h`.`ref_doc_no`,`d`.`material_id`,`m`.`material_code`,`m`.`material_name`,`d`.`uom_code` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_rm_available`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rm_available` AS select `o`.`material_id` AS `material_id`,`o`.`location_id` AS `location_id`,`o`.`warehouse_id` AS `warehouse_id`,`o`.`uom_code` AS `uom_code`,`o`.`is_usable` AS `is_usable`,`o`.`qty_onhand` AS `qty_onhand`,ifnull(`b`.`qty_booked_open`,0) AS `qty_booked`,(case when (`o`.`is_usable` = 0) then 0 else (`o`.`qty_onhand` - ifnull(`b`.`qty_booked_open`,0)) end) AS `qty_available` from (`v_rm_onhand` `o` left join (select `d`.`material_id` AS `material_id`,`d`.`location_id` AS `location_id`,sum((`d`.`qty_book` - `d`.`qty_issued`)) AS `qty_booked_open` from (`rm_booking_d` `d` join `rm_booking` `h` on((`h`.`booking_id` = `d`.`booking_id`))) where ((`h`.`stat_confirm` = 1) and (`h`.`stat_cancel` = 0) and (`h`.`stat_closed` = 0) and (`d`.`stat_cancel` = 0) and (`d`.`qty_book` > `d`.`qty_issued`)) group by `d`.`material_id`,`d`.`location_id`) `b` on(((`b`.`material_id` = `o`.`material_id`) and (`b`.`location_id` <=> `o`.`location_id`)))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_rm_onhand`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rm_onhand` AS select `sc`.`material_id` AS `material_id`,`sc`.`location_id` AS `location_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`l`.`location_name` AS `location_name`,`l`.`location_code` AS `location_code`,`l`.`is_usable` AS `is_usable`,`sc`.`uom_code` AS `uom_code`,`sc`.`qty_eb` AS `qty_onhand`,`sc`.`value_eb` AS `value_onhand`,`sc`.`avg_unitprice` AS `avg_unitprice`,`sc`.`doc_date` AS `last_movement` from (((`rm_stockcard` `sc` join (select `rm_stockcard`.`material_id` AS `material_id`,`rm_stockcard`.`location_id` AS `location_id`,max(concat(date_format(`rm_stockcard`.`doc_date`,'%Y%m%d%H%i%s'),lpad(`rm_stockcard`.`doc_id`,12,'0'))) AS `mx` from `rm_stockcard` group by `rm_stockcard`.`material_id`,`rm_stockcard`.`location_id`) `x` on(((`x`.`material_id` = `sc`.`material_id`) and (`x`.`location_id` = `sc`.`location_id`) and (`x`.`mx` = concat(date_format(`sc`.`doc_date`,'%Y%m%d%H%i%s'),lpad(`sc`.`doc_id`,12,'0')))))) left join `tm2mst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tm2mst`.`m_warehouse` `w` on((`w`.`id_warehouse` = `sc`.`warehouse_id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_rm_out_returnable`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rm_out_returnable` AS select `h`.`doc_id` AS `doc_id`,`h`.`doc_no` AS `doc_no`,`h`.`doc_date` AS `doc_date`,`h`.`wo_id` AS `wo_id`,`h`.`ref_doc_no` AS `ref_doc_no`,`d`.`doc_detail_id` AS `doc_detail_id`,`d`.`material_id` AS `material_id`,`m`.`material_code` AS `material_code`,`m`.`material_name` AS `material_name`,`d`.`lot_no` AS `lot_no`,`d`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`d`.`uom_code` AS `uom_code`,`d`.`action_type` AS `action_type`,`d`.`qty` AS `qty_issued`,`d`.`qty_returned` AS `qty_returned`,(`d`.`qty` - `d`.`qty_returned`) AS `qty_returnable` from (((`rm_stock` `h` join `rm_stock_d` `d` on((`d`.`doc_id` = `h`.`doc_id`))) left join `tm2mst`.`m_raw_material` `m` on((`m`.`material_id` = `d`.`material_id`))) left join `tm2mst`.`m_wh_loc` `l` on((`l`.`location_id` = `d`.`location_id`))) where ((`h`.`doc_type` = -(1)) and (`h`.`doc_cat` = 'STOCK') and (`h`.`flow_status` = 'POSTED') and (`d`.`action_type` in ('ISSUE','SUBSTITUTE')) and ((`d`.`qty` - `d`.`qty_returned`) > 0)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_sfg_onhand`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`tm2user`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sfg_onhand` AS select `sc`.`parts_id` AS `parts_id`,`sc`.`location_id` AS `location_id`,`sc`.`warehouse_id` AS `warehouse_id`,`w`.`warehouse_name` AS `warehouse_name`,`l`.`location_name` AS `location_name`,`l`.`location_code` AS `location_code`,`l`.`is_usable` AS `is_usable`,`sc`.`uom_code` AS `uom_code`,`sc`.`qty_eb` AS `qty_onhand`,`sc`.`value_eb` AS `value_onhand`,`sc`.`avg_unitprice` AS `avg_unitprice`,`sc`.`doc_date` AS `last_movement` from (((`sfg_stockcard` `sc` join (select `sfg_stockcard`.`parts_id` AS `parts_id`,`sfg_stockcard`.`location_id` AS `location_id`,max(concat(date_format(`sfg_stockcard`.`doc_date`,'%Y%m%d%H%i%s'),lpad(`sfg_stockcard`.`doc_id`,12,'0'))) AS `mx` from `sfg_stockcard` group by `sfg_stockcard`.`parts_id`,`sfg_stockcard`.`location_id`) `x` on(((`x`.`parts_id` = `sc`.`parts_id`) and (`x`.`location_id` = `sc`.`location_id`) and (`x`.`mx` = concat(date_format(`sc`.`doc_date`,'%Y%m%d%H%i%s'),lpad(`sc`.`doc_id`,12,'0')))))) left join `tm2mst`.`m_wh_loc` `l` on((`l`.`location_id` = `sc`.`location_id`))) left join `tm2mst`.`m_warehouse` `w` on((`w`.`id_warehouse` = `sc`.`warehouse_id`))) */;
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

