-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: tm2sdi
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
-- Table structure for table `co_order`
--

DROP TABLE IF EXISTS `co_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_order` (
  `id_order` bigint unsigned NOT NULL AUTO_INCREMENT,
  `co_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `co_date` date NOT NULL,
  `quotation_id` bigint unsigned NOT NULL COMMENT '1 CO = 1 Quotation (keputusan user 2026-08-20)',
  `customer_po_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_area_id` int unsigned DEFAULT NULL COMMENT 'FK logis -> tm2mst geo_village/district/city, lintas-DB',
  `ship_area_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'VILLAGE|DISTRICT|CITY',
  `ship_address_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_address_detail` text COLLATE utf8mb4_unicode_ci COMMENT 'Jalan/no/RT-RW manual, terpisah dari wilayah terstruktur',
  `ship_postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_doc_pct` decimal(5,2) DEFAULT NULL COMMENT 'Snapshot dari iqp_inquiry.discount_doc_value saat CO dibuat, locked',
  `discount_qty_pct` decimal(5,2) DEFAULT NULL COMMENT 'Editable di level CO, default dari iqp_inquiry.discount_qty_value',
  `doc_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CONFIRMED',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_order`),
  UNIQUE KEY `co_order_co_no_unique` (`co_no`),
  UNIQUE KEY `co_order_quotation_id_unique` (`quotation_id`),
  CONSTRAINT `co_order_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quo_quotation` (`id_quotation`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_order`
--

LOCK TABLES `co_order` WRITE;
/*!40000 ALTER TABLE `co_order` DISABLE KEYS */;
INSERT INTO `co_order` VALUES (2,'CO2600001','2026-08-19',5,'PO-DUMMY-001',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'CONFIRMED','Data uji -- dibuat manual, bukan dari alur Costing sungguhan.','2026-08-19 13:39:48','2026-08-19 13:39:48','system','system');
/*!40000 ALTER TABLE `co_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_order_delivery`
--

DROP TABLE IF EXISTS `co_order_delivery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_order_delivery` (
  `id_delivery` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL COMMENT 'FK -> co_order_item, harus PRODUCT',
  `shipment_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FULL' COMMENT 'FULL|PARTIAL',
  `ship_date` date DEFAULT NULL,
  `ship_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANNED' COMMENT 'PLANNED|SHIPPED -- SHIPPED dikunci setelah DO terbit (fase Dispatch, belum ada)',
  `split_from_id` bigint unsigned DEFAULT NULL COMMENT 'Self-FK -- baris asal kalau ini hasil Split, utk kolom "Asal"',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_delivery`),
  KEY `co_order_delivery_order_id_foreign` (`order_id`),
  KEY `co_order_delivery_item_id_foreign` (`item_id`),
  KEY `co_order_delivery_split_from_id_foreign` (`split_from_id`),
  CONSTRAINT `co_order_delivery_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `co_order_item` (`id_item`) ON DELETE CASCADE,
  CONSTRAINT `co_order_delivery_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `co_order` (`id_order`) ON DELETE CASCADE,
  CONSTRAINT `co_order_delivery_split_from_id_foreign` FOREIGN KEY (`split_from_id`) REFERENCES `co_order_delivery` (`id_delivery`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_order_delivery`
--

LOCK TABLES `co_order_delivery` WRITE;
/*!40000 ALTER TABLE `co_order_delivery` DISABLE KEYS */;
/*!40000 ALTER TABLE `co_order_delivery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_order_external_cost`
--

DROP TABLE IF EXISTS `co_order_external_cost`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_order_external_cost` (
  `id_cost` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Trucking|Expedition|Lainnya',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tax_treatment` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TAXABLE' COMMENT 'TAXABLE|REIMBURSEMENT',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_cost`),
  KEY `co_order_external_cost_order_id_foreign` (`order_id`),
  CONSTRAINT `co_order_external_cost_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `co_order` (`id_order`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_order_external_cost`
--

LOCK TABLES `co_order_external_cost` WRITE;
/*!40000 ALTER TABLE `co_order_external_cost` DISABLE KEYS */;
/*!40000 ALTER TABLE `co_order_external_cost` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `co_order_item`
--

DROP TABLE IF EXISTS `co_order_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `co_order_item` (
  `id_item` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `line_seq` smallint unsigned NOT NULL DEFAULT '1',
  `quotation_item_id` bigint unsigned DEFAULT NULL COMMENT 'FK logis -> quo_quotation_item, NULL kalau ditambah manual di CO',
  `line_kind` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PRODUCT|SERVICE',
  `inquiry_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> iqp_inquiry, traceability, NULL utk baris manual/service',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `spec_note` text COLLATE utf8mb4_unicode_ci,
  `qty` decimal(14,2) NOT NULL DEFAULT '1.00',
  `qty_unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `discount_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_item`),
  KEY `co_order_item_order_id_foreign` (`order_id`),
  KEY `co_order_item_inquiry_id_foreign` (`inquiry_id`),
  CONSTRAINT `co_order_item_inquiry_id_foreign` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE SET NULL,
  CONSTRAINT `co_order_item_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `co_order` (`id_order`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_order_item`
--

LOCK TABLES `co_order_item` WRITE;
/*!40000 ALTER TABLE `co_order_item` DISABLE KEYS */;
INSERT INTO `co_order_item` VALUES (3,2,1,6,'PRODUCT',NULL,'RSC Box Corrugated 5-Ply Ekspor (contoh manual)',NULL,1000.00,'Pcs',12500.0000,0.00,12500000.00,NULL,'2026-08-19 13:39:48','2026-08-19 13:39:48','system','system');
/*!40000 ALTER TABLE `co_order_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_costing`
--

DROP TABLE IF EXISTS `iqp_costing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_costing` (
  `id_costing` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doc_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Format HPP{YY}{NNNNN}, immutable',
  `doc_date` date DEFAULT (curdate()),
  `inquiry_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_inquiry',
  `calc_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NEW' COMMENT 'NEW|RECALC|REVISE, immutable',
  `i_dim_panjang_mm` decimal(10,2) DEFAULT '0.00' COMMENT 'P produk jadi (mm)',
  `i_dim_lebar_mm` decimal(10,2) DEFAULT '0.00' COMMENT 'L produk jadi (mm)',
  `i_dim_tinggi_mm` decimal(10,2) DEFAULT '0.00' COMMENT 'T produk jadi (mm)',
  `i_lidah_lem_mm` decimal(10,2) DEFAULT '0.00' COMMENT 'Lidah lem (mm)',
  `total_material_cost_pc` decimal(18,4) DEFAULT NULL COMMENT 'Roll-up: sigma bahan/pc (engine)',
  `total_process_cost_pc` decimal(18,4) DEFAULT NULL COMMENT 'Roll-up: sigma proses/pc (engine)',
  `total_finishing_cost_pc` decimal(18,4) DEFAULT NULL COMMENT 'Roll-up: sigma coating+laminasi/pc (engine)',
  `c_tooling_cost_order` decimal(18,4) DEFAULT NULL COMMENT 'Sigma tooling ORDER-level (engine), dibagi qty di tier',
  `doc_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CALCULATED|PENDING_APPROVAL|PUBLISHED|FAILED',
  `rounding_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mode pembulatan',
  `ppn_mode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPN' COMMENT 'PPN|NON_PPN (snapshot dari inquiry)',
  `ppn_pct` decimal(5,2) NOT NULL DEFAULT '11.00',
  `currency_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IDR',
  `calc_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Catatan bebas user, jangan di-overwrite engine',
  `published_flag` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=harga terbuka ke Sisi B (Sales)',
  `is_approved` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Approval Direksi = event publish',
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_costing`) USING BTREE,
  UNIQUE KEY `uq_costing_inquiry` (`inquiry_id`) USING BTREE,
  UNIQUE KEY `uq_costing_doc` (`doc_no`),
  KEY `idx_cost_status` (`doc_status`) USING BTREE,
  CONSTRAINT `fk_cost_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Header costing HPP (MTO). RAHASIA (Sisi A). Publish via approve Direksi';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_costing`
--

LOCK TABLES `iqp_costing` WRITE;
/*!40000 ALTER TABLE `iqp_costing` DISABLE KEYS */;
INSERT INTO `iqp_costing` VALUES (7,NULL,'2026-08-13',6,'NEW',336.00,486.00,0.00,0.00,0.0000,0.0000,0.0000,0.0000,'CALCULATED',NULL,'PPN',11.00,'IDR',NULL,0,0,NULL,NULL,'2026-08-12 23:27:52','2026-08-23 17:24:20','admin','admin'),(24,NULL,'2026-08-15',5,'NEW',515.00,190.00,0.00,0.00,NULL,NULL,NULL,NULL,'DRAFT',NULL,'PPN',11.00,'IDR',NULL,0,0,NULL,NULL,'2026-08-14 22:13:47','2026-08-19 23:07:11','admin','admin');
/*!40000 ALTER TABLE `iqp_costing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_costing_part`
--

DROP TABLE IF EXISTS `iqp_costing_part`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_costing_part` (
  `id_costing_part` bigint unsigned NOT NULL AUTO_INCREMENT,
  `costing_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_costing',
  `part_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> iqp_part, opsional (freeform kalau NULL)',
  `no_urut` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Urutan part (1=Bag atas, 2=Bag bawah...)',
  `part_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `part_type_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DC' COMMENT 'FK logical -> prod_part_type. Baru DC didukung engine',
  `qty_per_set` decimal(10,2) NOT NULL DEFAULT '1.00',
  `i_dim_panjang_mm` decimal(10,2) DEFAULT NULL,
  `i_dim_lebar_mm` decimal(10,2) DEFAULT NULL,
  `i_dim_tinggi_mm` decimal(10,2) DEFAULT NULL,
  `grammage_id` bigint unsigned DEFAULT NULL COMMENT 'FK logical -> mat_grammage',
  `sheet_rate_id` bigint unsigned DEFAULT NULL COMMENT 'FK logical -> mat_sheet_rate',
  `supplier_sheet_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'UJK' COMMENT 'Kode supplier sheet, dipakai lookup discount tier',
  `flute_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'B/F|C/F|BC/F, sumber allowance geometri',
  `i_lidah_lem_mm` decimal(10,2) DEFAULT '0.00' COMMENT 'Untuk derivasi geometri sheet',
  `i_isi_per_lb_kerja` decimal(8,4) NOT NULL DEFAULT '1.0000' COMMENT 'Isi dlm 1 lembar kerja (pembagi rm, guard >0)',
  `i_ongkos_print_pc` decimal(18,4) DEFAULT '0.0000' COMMENT 'Print sheet (x2 di engine)',
  `i_ongkos_diecut_pc` decimal(18,4) DEFAULT '0.0000',
  `i_ongkos_slitter_pc` decimal(18,4) DEFAULT '0.0000',
  `i_ongkos_slotter_pc` decimal(18,4) DEFAULT '0.0000',
  `i_ongkos_join_pc` decimal(18,4) DEFAULT '0.0000',
  `i_ongkos_partisi_pc` decimal(18,4) DEFAULT '0.0000',
  `i_ongkos_layer_pc` decimal(18,4) DEFAULT '0.0000',
  `i_ongkos_qc_pack_pc` decimal(18,4) DEFAULT '0.0000',
  `i_ongkos_kirim_pc` decimal(18,4) DEFAULT '0.0000',
  `c_panjang_sheet_cm` decimal(10,4) DEFAULT NULL COMMENT 'Derived: (((P+L)x2)+total_p_mm+lidah_lem)/10',
  `c_lebar_sheet_cm` decimal(10,4) DEFAULT NULL COMMENT 'Derived: ((L+T)+total_l_mm)/10',
  `c_isi_trim` decimal(10,4) DEFAULT NULL COMMENT 'floor((180-2)/lebar_sheet_cm)',
  `c_lebar_trim_cm` decimal(10,4) DEFAULT NULL COMMENT 'ceil((lebar*isi_trim+2)/5)*5',
  `c_running_meter` decimal(14,4) DEFAULT NULL,
  `c_discount_rm` decimal(8,4) DEFAULT NULL COMMENT 'Lookup tier, negatif=surcharge',
  `c_sheet_cost_pc` decimal(18,4) DEFAULT NULL COMMENT '(lebar_trim x panjang_sheet /10000) x index_substance /isi_trim x (1-discount_rm)',
  `c_print_cost_pc` decimal(18,4) DEFAULT NULL COMMENT 'i_ongkos_print_pc x 2',
  `i_dc_persen_override` decimal(6,4) DEFAULT NULL COMMENT 'Override DC%. NULL/0 = ambil dari grammage',
  `i_panjang_plano_cm` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `i_lebar_plano_cm` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `i_cut_per_plano` decimal(8,4) NOT NULL DEFAULT '1.0000' COMMENT 'Pembagi paper cost (guard >0)',
  `i_isi_per_lb` decimal(8,4) NOT NULL DEFAULT '1.0000' COMMENT 'Isi pcs/lembar (guard >0)',
  `i_jumlah_warna` tinyint unsigned NOT NULL DEFAULT '4' COMMENT 'Plate count',
  `i_ongkos_cetak_rim` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `i_ongkos_plong_lb` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `i_ongkos_lem_pc` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `i_film_faktor` decimal(8,4) DEFAULT NULL COMMENT 'ff (dikuadratkan di engine). NULL = pakai FILM_FACTOR_DEFAULT',
  `i_film_rate` decimal(18,4) DEFAULT NULL COMMENT 'Rate film (nilai langsung). NULL = pakai FILM_RATE_DEFAULT',
  `i_pisau_faktor` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `i_pisau_rate_rp` decimal(18,4) DEFAULT NULL COMMENT 'NULL = ambil default dari prod_part_type',
  `i_ongkos_plate_warna` decimal(18,4) DEFAULT NULL COMMENT 'NULL = pakai PLATE_PER_COLOR',
  `c_area_cetak_cm2` decimal(14,4) DEFAULT NULL,
  `c_jumlah_cetak_lc` decimal(14,4) DEFAULT NULL,
  `c_jumlah_bahan_lb` decimal(14,4) DEFAULT NULL,
  `c_paper_cost_pc` decimal(18,4) DEFAULT NULL,
  `c_cetak_cost_pc` decimal(18,4) DEFAULT NULL,
  `c_plong_cost_pc` decimal(18,4) DEFAULT NULL,
  `c_lem_cost_pc` decimal(18,4) DEFAULT NULL,
  `c_total_coating_pc` decimal(18,4) DEFAULT NULL,
  `c_film_cost_order` decimal(18,4) DEFAULT NULL,
  `c_plate_cost_order` decimal(18,4) DEFAULT NULL,
  `c_knife_cost_order` decimal(18,4) DEFAULT NULL,
  `c_tooling_cost_order` decimal(18,4) DEFAULT NULL COMMENT 'film+plate+knife order-level',
  `c_process_cost_pc` decimal(18,4) DEFAULT NULL COMMENT 'proses+coating /pc',
  `c_total_part_cost_pc` decimal(18,4) DEFAULT NULL COMMENT 'bahan+proses+coating /pc (TANPA tooling)',
  `description_calc` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Jejak engine: versi+ts. Ditulis sistem',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_costing_part`),
  KEY `idx_cpart_costing` (`costing_id`),
  KEY `iqp_costing_part_part_id_foreign` (`part_id`),
  CONSTRAINT `fk_cpart_costing` FOREIGN KEY (`costing_id`) REFERENCES `iqp_costing` (`id_costing`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `iqp_costing_part_part_id_foreign` FOREIGN KEY (`part_id`) REFERENCES `iqp_part` (`id_part`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail costing per part (baru DC). c_*=engine only. docs/SALES_TIER_COSTING.md ┬º1.2 (dipangkas ke DC)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_costing_part`
--

LOCK TABLES `iqp_costing_part` WRITE;
/*!40000 ALTER TABLE `iqp_costing_part` DISABLE KEYS */;
INSERT INTO `iqp_costing_part` VALUES (31,24,1,1,'Body','DC',1.00,NULL,NULL,NULL,NULL,NULL,'UJK',NULL,0.00,1.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,79.0000,109.0000,8.0000,1.0000,4,0.0000,0.0000,0.0000,NULL,NULL,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'admin','2026-08-19 17:37:17','admin','2026-08-19 17:37:17'),(32,7,NULL,1,'','DC',1.00,NULL,NULL,NULL,1,NULL,'UJK',NULL,0.00,1.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,1.0000,1.0000,4,0.0000,0.0000,0.0000,NULL,NULL,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'johiwan','2026-08-23 21:49:54','johiwan','2026-08-23 21:50:03'),(33,7,NULL,2,'','SHEET_DIGITAL',1.00,NULL,NULL,NULL,NULL,NULL,'UJK',NULL,0.00,1.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,1.0000,1.0000,4,0.0000,0.0000,0.0000,NULL,NULL,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'johiwan','2026-08-23 21:49:57','johiwan','2026-08-23 21:49:57'),(34,24,NULL,2,'','DC',1.00,NULL,NULL,NULL,NULL,NULL,'UJK',NULL,0.00,1.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,1.0000,1.0000,4,0.0000,0.0000,0.0000,NULL,NULL,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'johiwan','2026-08-27 09:21:38','johiwan','2026-08-27 09:21:38'),(35,24,NULL,3,'','SHEET_DIGITAL',1.00,NULL,NULL,NULL,NULL,NULL,'UJK',NULL,0.00,1.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.0000,0.0000,1.0000,1.0000,4,0.0000,0.0000,0.0000,NULL,NULL,0.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'johiwan','2026-08-27 09:21:38','johiwan','2026-08-27 09:21:38');
/*!40000 ALTER TABLE `iqp_costing_part` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_costing_part_coating`
--

DROP TABLE IF EXISTS `iqp_costing_part_coating`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_costing_part_coating` (
  `id_coating` bigint unsigned NOT NULL AUTO_INCREMENT,
  `costing_part_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_costing_part',
  `seq` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Urutan layer (berurutan)',
  `coating_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'WB|UV|varnish|laminating',
  `index_coating` decimal(20,4) DEFAULT NULL COMMENT 'SNAPSHOT rate saat hitung (audit)',
  `c_cost_per_lc` decimal(20,6) DEFAULT NULL COMMENT 'area x index_coating',
  `c_cost_per_pc` decimal(20,4) DEFAULT NULL COMMENT 'cost_per_lc / isi (TIDAK /cut)',
  `notes_coating` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_coating`),
  KEY `idx_coating_cpart` (`costing_part_id`),
  CONSTRAINT `fk_coating_cpart` FOREIGN KEY (`costing_part_id`) REFERENCES `iqp_costing_part` (`id_costing_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Coating per part, multi-layer berurutan. Index di-snapshot saat hitung';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_costing_part_coating`
--

LOCK TABLES `iqp_costing_part_coating` WRITE;
/*!40000 ALTER TABLE `iqp_costing_part_coating` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_costing_part_coating` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_costing_price_tier`
--

DROP TABLE IF EXISTS `iqp_costing_price_tier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_costing_price_tier` (
  `id_price_tier` bigint unsigned NOT NULL AUTO_INCREMENT,
  `costing_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_costing',
  `tier_id` bigint unsigned DEFAULT NULL COMMENT 'FK logical -> iqp_tier',
  `qty_pcs` decimal(18,2) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Tepat 1 UTAMA (acuan quotation)',
  `tolerance_pct` decimal(6,2) NOT NULL DEFAULT '0.00',
  `margin_pct` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Mark-up on price: /(1-m)',
  `rounding_mode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NEAREST' COMMENT 'NEAREST|UP|DOWN',
  `rounding_step` decimal(12,2) NOT NULL DEFAULT '1.00',
  `c_tooling_pc` decimal(18,4) DEFAULT NULL COMMENT 'c_tooling_cost_order / qty_pcs',
  `c_hpp_pc` decimal(18,4) DEFAULT NULL COMMENT 'total_hpp_pc + tooling_pc',
  `c_after_tolerance_pc` decimal(18,4) DEFAULT NULL,
  `c_after_round_pc` decimal(18,4) DEFAULT NULL,
  `c_after_margin_pc` decimal(18,4) DEFAULT NULL COMMENT '= price_per_pcs excl PPN',
  `c_after_ppn_pc` decimal(18,4) DEFAULT NULL COMMENT 'incl PPN',
  `price_per_pcs` decimal(18,4) DEFAULT NULL COMMENT 'Harga jual acuan = c_after_margin_pc',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_price_tier`),
  KEY `idx_ptier_costing` (`costing_id`),
  CONSTRAINT `fk_ptier_costing` FOREIGN KEY (`costing_id`) REFERENCES `iqp_costing` (`id_costing`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ladder harga per tier qty. Tooling diamortisasi di sini; parameter di-snapshot saat publish';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_costing_price_tier`
--

LOCK TABLES `iqp_costing_price_tier` WRITE;
/*!40000 ALTER TABLE `iqp_costing_price_tier` DISABLE KEYS */;
INSERT INTO `iqp_costing_price_tier` VALUES (7,7,5,1000.00,1,0.00,0.00,'NEAREST',1.00,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,0.0000,'admin','2026-08-12 23:27:52','admin','2026-08-23 17:24:20'),(24,24,4,5000.00,1,0.00,0.00,'NEAREST',1.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'admin','2026-08-14 22:13:47','admin','2026-08-19 23:07:11');
/*!40000 ALTER TABLE `iqp_costing_price_tier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_costing_state_log`
--

DROP TABLE IF EXISTS `iqp_costing_state_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_costing_state_log` (
  `id_state_log` bigint unsigned NOT NULL AUTO_INCREMENT,
  `costing_id` bigint unsigned NOT NULL,
  `from_state` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_state` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `changed_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_state_log`),
  KEY `idx_cslog_costing` (`costing_id`),
  CONSTRAINT `fk_cslog_costing` FOREIGN KEY (`costing_id`) REFERENCES `iqp_costing` (`id_costing`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jejak transisi doc_status costing (audit pipeline)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_costing_state_log`
--

LOCK TABLES `iqp_costing_state_log` WRITE;
/*!40000 ALTER TABLE `iqp_costing_state_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_costing_state_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_external_cost`
--

DROP TABLE IF EXISTS `iqp_external_cost`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_external_cost` (
  `id_external_cost` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_inquiry',
  `cost_kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'TRUCKING|EKSPEDISI|JASA_LUAR|LAINNYA',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `tax_treatment` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TAXABLE' COMMENT 'TAXABLE(masuk DPP)|REIMBURSEMENT (blocker B9)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_external_cost`) USING BTREE,
  KEY `idx_ext_inquiry` (`inquiry_id`) USING BTREE,
  CONSTRAINT `fk_ext_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Biaya eksternal (trucking/ekspedisi/jasa luar). Default taxable (masuk DPP)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_external_cost`
--

LOCK TABLES `iqp_external_cost` WRITE;
/*!40000 ALTER TABLE `iqp_external_cost` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_external_cost` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_inquiry`
--

DROP TABLE IF EXISTS `iqp_inquiry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_inquiry` (
  `id_inquiry` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IQ[YY][NNNNN], auto, immutable',
  `inquiry_date` date NOT NULL,
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sumber permintaan (email/WA/telp/dll)',
  `pic_sales` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PIC Sales penanggung jawab',
  `customer_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> cust_customer (NULL bila prospek)',
  `is_prospect` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=calon (belum jadi customer resmi)',
  `prospect_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama calon bila belum ada customer_id',
  `deadline_quotation` date DEFAULT NULL COMMENT 'Batas terbit quotation (min +14 HK)',
  `product_draft_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama produk draft (tanpa karakter khusus)',
  `sales_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `inquiry_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NEW' COMMENT 'FK -> iqp_type. NEW|RECALC|REVISE. Set once',
  `parent_product_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> prod_product asal (RECALC/REVISE); NULL untuk NEW',
  `revision_no` int unsigned NOT NULL DEFAULT '0' COMMENT 'Naik tiap versi baru',
  `revise_scope` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CSV subset dari MATERIAL,DIMENSION,ARTWORK,COLOR,PROCESS — NULL untuk NEW/RECALC',
  `revise_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alasan revisi (mis. tambah logo Halal)',
  `material_source` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OWN' COMMENT 'OWN | CUSTOMER. Penentu sifat CO',
  `job_nature` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRODUCT' COMMENT 'PRODUCT | JASA (turunan material_source)',
  `cust_material_spec` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Spec bahan customer (wajib bila material_source=CUSTOMER)',
  `cust_material_eta` date DEFAULT NULL COMMENT 'ETA kirim bahan customer',
  `tax_scheme` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PPN' COMMENT 'PPN | NON_PPN (non-PPN dianggap include)',
  `pph_applicable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=kena PPh23 (hanya jasa)',
  `ppn_percent` decimal(6,4) NOT NULL DEFAULT '11.0000',
  `pph_percent` decimal(6,4) NOT NULL DEFAULT '2.0000',
  `discount_doc_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PERCENT | AMOUNT (diskon amount dokumen)',
  `discount_doc_value` decimal(20,4) DEFAULT NULL,
  `discount_qty_value` decimal(20,4) DEFAULT NULL COMMENT 'Diskon qty occasional',
  `discount_qty_min_ton` decimal(20,4) DEFAULT NULL COMMENT 'Ambang tonase pengaktif diskon qty',
  `prove_final_version` int unsigned DEFAULT NULL COMMENT 'Nomor versi prove final (snapshot cepat; detail di iqp_prove)',
  `prove_approved_date` date DEFAULT NULL COMMENT 'Tgl approval prove FISIK = mulai due-date clock',
  `due_date` date DEFAULT NULL COMMENT '+14 HK dari prove_approved_date',
  `design_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Identitas artwork/proof, mis. DS003479',
  `co_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> co_order (jejak balik via quotation)',
  `sub_state` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|WAITING_COST|SUBMITTED|PRICE_CALCULATED|PRICE_APPROVED|QUOTED|PROVING|PROVE_APPROVED|CONVERTED|CANCELLED|LOST',
  `stat_confirm` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Sales kunci (stat_confirm lama)',
  `stat_cancel` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Hanya bila is_confirmed=1',
  `cancel_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Approval Direksi = publish harga',
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Direksi yg approve',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_inquiry`) USING BTREE,
  UNIQUE KEY `uq_inquiry_no` (`inquiry_no`) USING BTREE,
  KEY `idx_inq_customer` (`customer_id`) USING BTREE,
  KEY `idx_inq_parent` (`parent_product_id`) USING BTREE,
  KEY `idx_inq_substate` (`sub_state`) USING BTREE,
  KEY `fk_inq_type` (`inquiry_type`) USING BTREE,
  KEY `iqp_inquiry_co_id_foreign` (`co_id`),
  CONSTRAINT `fk_inq_type` FOREIGN KEY (`inquiry_type`) REFERENCES `iqp_type` (`type_code`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `iqp_inquiry_co_id_foreign` FOREIGN KEY (`co_id`) REFERENCES `co_order` (`id_order`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Header inquiry product (MTO). Sumber kebenaran spec/harga/pajak/state';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_inquiry`
--

LOCK TABLES `iqp_inquiry` WRITE;
/*!40000 ALTER TABLE `iqp_inquiry` DISABLE KEYS */;
INSERT INTO `iqp_inquiry` VALUES (5,'IQ2600001','2026-08-08','EMAIL',NULL,NULL,1,'CV Test Dieline',NULL,'Box Test Dieline',NULL,'NEW',NULL,0,NULL,NULL,'OWN','PRODUCT',NULL,NULL,'PPN',0,11.0000,2.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'SUBMITTED',1,0,NULL,0,NULL,NULL,'2026-08-07 21:42:28','2026-08-21 10:03:37','web','system'),(6,'IQ2600002','2026-08-08','EMAIL',NULL,NULL,1,'CV Test Kalender',NULL,'Kalender Test',NULL,'NEW',NULL,0,NULL,NULL,'OWN','PRODUCT',NULL,NULL,'PPN',0,11.0000,2.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'SUBMITTED',1,0,NULL,0,NULL,NULL,'2026-08-07 22:58:54','2026-08-21 10:03:37','web','system'),(82,'IQ2600003','2026-08-20','Referral','Dummy Sales',14,0,NULL,NULL,'Contoh Badge RECALC (dummy)','Dummy data khusus cek warna badge nota tipe RECALC.','RECALC',NULL,0,NULL,NULL,'OWN','PRODUCT',NULL,NULL,'PPN',0,11.0000,2.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'DRAFT',0,0,NULL,0,NULL,NULL,'2026-08-20 14:46:10','2026-08-20 14:46:10','dummy_seed','dummy_seed'),(83,'IQ2600004','2026-08-20','Referral','Dummy Sales',14,0,NULL,NULL,'Contoh Badge REVISE (dummy)','Dummy data khusus cek warna badge nota tipe REVISE.','REVISE',NULL,1,NULL,NULL,'OWN','PRODUCT',NULL,NULL,'PPN',0,11.0000,2.0000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'DRAFT',0,0,NULL,0,NULL,NULL,'2026-08-20 14:46:10','2026-08-20 14:46:10','dummy_seed','dummy_seed');
/*!40000 ALTER TABLE `iqp_inquiry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_layout`
--

DROP TABLE IF EXISTS `iqp_layout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_layout` (
  `id_layout` bigint unsigned NOT NULL AUTO_INCREMENT,
  `part_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_part, 1 layout per part',
  `flat_w_mm` decimal(10,2) NOT NULL,
  `flat_h_mm` decimal(10,2) NOT NULL,
  `gap_mm` decimal(6,2) NOT NULL DEFAULT '3.00',
  `plano_size_id` int unsigned DEFAULT NULL COMMENT 'FK logis -> tm2mst.m_plano_size, terakhir dipilih',
  `plano_w_mm` decimal(10,2) NOT NULL,
  `plano_h_mm` decimal(10,2) NOT NULL,
  `gripper_mm` decimal(6,2) NOT NULL DEFAULT '12.00',
  `margin_mm` decimal(6,2) NOT NULL DEFAULT '8.00',
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'TEGAK|PUTAR|CAMPUR',
  `up_count` smallint unsigned NOT NULL DEFAULT '0',
  `efficiency_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `waste_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `layout_detail` json DEFAULT NULL COMMENT 'Grid/mix detail utk render ulang visual',
  `stat_confirm` tinyint(1) NOT NULL DEFAULT '0',
  `confirmed_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_layout`),
  UNIQUE KEY `iqp_layout_part_id_unique` (`part_id`),
  CONSTRAINT `iqp_layout_part_id_foreign` FOREIGN KEY (`part_id`) REFERENCES `iqp_part` (`id_part`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_layout`
--

LOCK TABLES `iqp_layout` WRITE;
/*!40000 ALTER TABLE `iqp_layout` DISABLE KEYS */;
INSERT INTO `iqp_layout` VALUES (1,1,515.00,190.00,3.00,4,790.00,1090.00,12.00,8.00,'PUTAR',8,95.23,4.77,'{\"cells\": [{\"h\": 515, \"n\": 1, \"w\": 190, \"x\": 8, \"y\": 20, \"rot\": true}, {\"h\": 515, \"n\": 2, \"w\": 190, \"x\": 201, \"y\": 20, \"rot\": true}, {\"h\": 515, \"n\": 3, \"w\": 190, \"x\": 394, \"y\": 20, \"rot\": true}, {\"h\": 515, \"n\": 4, \"w\": 190, \"x\": 587, \"y\": 20, \"rot\": true}, {\"h\": 515, \"n\": 5, \"w\": 190, \"x\": 8, \"y\": 538, \"rot\": true}, {\"h\": 515, \"n\": 6, \"w\": 190, \"x\": 201, \"y\": 538, \"rot\": true}, {\"h\": 515, \"n\": 7, \"w\": 190, \"x\": 394, \"y\": 538, \"rot\": true}, {\"h\": 515, \"n\": 8, \"w\": 190, \"x\": 587, \"y\": 538, \"rot\": true}], \"layout\": {\"ih\": 515, \"iw\": 190, \"rot\": true, \"cols\": 4, \"rows\": 2, \"type\": \"grid\"}}',0,NULL,NULL,'2026-08-19 17:29:48','2026-08-19 17:38:14','admin','admin'),(2,2,336.00,486.00,3.00,1,650.00,1000.00,12.00,8.00,'PUTAR',2,53.00,47.00,'{\"cells\": [{\"h\": 336, \"n\": 1, \"w\": 486, \"x\": 8, \"y\": 20, \"rot\": true}, {\"h\": 336, \"n\": 2, \"w\": 486, \"x\": 8, \"y\": 359, \"rot\": true}], \"layout\": {\"ih\": 336, \"iw\": 486, \"rot\": true, \"cols\": 1, \"rows\": 2, \"type\": \"grid\"}}',0,NULL,NULL,'2026-08-21 13:49:53','2026-08-21 13:49:53','johiwan','johiwan');
/*!40000 ALTER TABLE `iqp_layout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_part`
--

DROP TABLE IF EXISTS `iqp_part`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_part` (
  `id_part` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_inquiry',
  `part_seq` int unsigned NOT NULL,
  `part_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `part_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'mis. Body / Lid / Partisi',
  `part_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tipe part (acuan routing standar)',
  `qty_per_sku` decimal(20,4) NOT NULL DEFAULT '1.0000' COMMENT 'Jumlah part per 1 SKU jadi',
  `cfg_color` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Konfigurasi warna, mis. 4/4, 4/0',
  `cfg_ink` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dieline_spec` json DEFAULT NULL COMMENT 'Snapshot dieline_spec DSL {canvas,flat,vars,elements}',
  `flat_w` decimal(20,4) DEFAULT NULL COMMENT 'Bentangan W (dari flat DSL) -> costing',
  `flat_h` decimal(20,4) DEFAULT NULL,
  `flat_area` decimal(20,4) DEFAULT NULL COMMENT 'Luas bentangan -> costing',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_part`) USING BTREE,
  KEY `idx_part_inquiry` (`inquiry_id`) USING BTREE,
  CONSTRAINT `fk_part_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Part mirror inquiry (snapshot m_product_parts). Tiap part punya dieline & bahan';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_part`
--

LOCK TABLES `iqp_part` WRITE;
/*!40000 ALTER TABLE `iqp_part` DISABLE KEYS */;
INSERT INTO `iqp_part` VALUES (1,5,1,NULL,'Body','DC',1.0000,NULL,NULL,'{\"dims\": {\"dust_flap\": \"40.0000\", \"glue_flap\": \"15.0000\", \"product_width\": \"50.0000\", \"top_tuck_flap\": \"35.0000\", \"product_height\": \"120.0000\", \"product_length\": \"200\"}, \"flat\": {\"h\": \"H\", \"w\": \"W\"}, \"vars\": {\"H\": \"C+2*TT\", \"W\": \"2*(A+B)+G\", \"x1\": \"G\", \"x2\": \"G+A\", \"x3\": \"G+A+B\", \"x4\": \"G+2*A+B\", \"x5\": \"G+2*(A+B)\", \"yBot\": \"TT+C\", \"yTop\": \"TT\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"h\": \"C\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"x5-x1\", \"x\": \"x1\", \"y\": \"yTop\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x2\", \"x2\": \"x2\", \"y1\": \"yTop\", \"y2\": \"yBot\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x3\", \"x2\": \"x3\", \"y1\": \"yTop\", \"y2\": \"yBot\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x4\", \"x2\": \"x4\", \"y1\": \"yTop\", \"y2\": \"yBot\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yTop\", \"y2\": \"yTop\"}, {\"k\": \"CREASE\", \"t\": \"line\", \"x1\": \"x1\", \"x2\": \"x5\", \"y1\": \"yBot\", \"y2\": \"yBot\"}, {\"g\": \"8\", \"k\": \"CUT\", \"t\": \"glue\", \"x0\": \"0\", \"x1\": \"G\", \"yB\": \"yBot\", \"yT\": \"yTop\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x1\", \"b\": \"x2\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yBot\", \"dir\": \"down\"}, {\"a\": \"x3\", \"b\": \"x4\", \"h\": \"TT\", \"k\": \"CUT\", \"t\": \"tuck\", \"y\": \"yBot\", \"dir\": \"down\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yTop\", \"dir\": \"up\"}, {\"a\": \"x2\", \"b\": \"x3\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yBot\", \"dir\": \"down\"}, {\"a\": \"x4\", \"b\": \"x5\", \"h\": \"DF*0.7\", \"k\": \"CUT\", \"t\": \"dust\", \"y\": \"yBot\", \"dir\": \"down\"}], \"type_code\": \"CARD_BOX\"}',515.0000,190.0000,97850.0000,'2026-08-07 21:42:28','2026-08-07 21:42:28','web','web'),(2,6,1,NULL,'Part 1','DC',1.0000,NULL,NULL,'{\"gen\": \"FLAT_CAL\", \"dims\": {\"page_count\": \"13.00\", \"hanger_type\": \"EYELET\", \"binding_type\": \"SPIRAL\", \"product_width\": \"480.00\", \"product_length\": \"330.00\", \"overflow_margin\": \"3.00\"}, \"flat\": {\"h\": \"B+2*P\", \"w\": \"A+2*P\"}, \"vars\": {\"H\": \"B\", \"W\": \"A\", \"nh\": \"max(5, A/50)\"}, \"canvas\": {\"h\": \"H\", \"w\": \"W\"}, \"elements\": [{\"k\": \"BLEED\", \"p\": \"P\", \"t\": \"bleed\"}, {\"h\": \"B\", \"k\": \"CUT\", \"t\": \"rect\", \"w\": \"A\", \"x\": \"0\", \"y\": \"0\"}, {\"k\": \"HOLE\", \"n\": \"nh\", \"r\": \"1.8\", \"t\": \"holes\", \"w\": \"A\", \"y\": \"7\"}, {\"k\": \"HOLE\", \"r\": \"3\", \"t\": \"circle\", \"cx\": \"A/2\", \"cy\": \"4\"}], \"type_code\": \"KALENDER_DINDING\"}',336.0000,486.0000,163296.0000,'2026-08-07 22:58:54','2026-08-07 22:58:54','web','web');
/*!40000 ALTER TABLE `iqp_part` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_part_material`
--

DROP TABLE IF EXISTS `iqp_part_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_part_material` (
  `id_part_material` bigint unsigned NOT NULL AUTO_INCREMENT,
  `part_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_part',
  `layer_seq` int unsigned NOT NULL COMMENT 'Urutan layer (1 part bisa banyak lapis: duplex+SF)',
  `rawmat_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> mat_raw (snapshot id)',
  `rawmat_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Snapshot nama bahan (jaga bila master berubah)',
  `gram_weight` decimal(20,4) DEFAULT NULL COMMENT 'Gramatur',
  `flute_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tipe flute (corrugated)',
  `size_p` decimal(20,4) DEFAULT NULL,
  `size_l` decimal(20,4) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=bahan utama layer',
  `material_source` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'OWN' COMMENT 'OWN|CUSTOMER per-layer (siapkan sejak Tier3; blocker B1)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_part_material`) USING BTREE,
  KEY `idx_pmat_part` (`part_id`) USING BTREE,
  CONSTRAINT `fk_pmat_part` FOREIGN KEY (`part_id`) REFERENCES `iqp_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Layer bahan per part (snapshot). Mendukung multi-layer (duplex+SF)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_part_material`
--

LOCK TABLES `iqp_part_material` WRITE;
/*!40000 ALTER TABLE `iqp_part_material` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_part_material` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_part_process`
--

DROP TABLE IF EXISTS `iqp_part_process`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_part_process` (
  `id_part_process` bigint unsigned NOT NULL AUTO_INCREMENT,
  `part_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_part',
  `branch_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MAIN' COMMENT 'MAIN|L|R|BODY|LID (branch produksi)',
  `step_seq` int unsigned NOT NULL COMMENT 'Urutan step dalam branch',
  `flow_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LINEAR' COMMENT 'LINEAR|SPLIT|MERGE',
  `process_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> prod_process (snapshot id)',
  `process_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Snapshot nama proses',
  `machine_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> mesin (snapshot id)',
  `qty_factor` decimal(20,4) NOT NULL DEFAULT '1.0000' COMMENT 'Faktor qty (mis. SPLIT belah plano)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_part_process`) USING BTREE,
  KEY `idx_pproc_part` (`part_id`) USING BTREE,
  CONSTRAINT `fk_pproc_part` FOREIGN KEY (`part_id`) REFERENCES `iqp_part` (`id_part`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Proses per part, ber-branch & flow (SPLIT/MERGE). Snapshot routing';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_part_process`
--

LOCK TABLES `iqp_part_process` WRITE;
/*!40000 ALTER TABLE `iqp_part_process` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_part_process` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_prove`
--

DROP TABLE IF EXISTS `iqp_prove`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_prove` (
  `id_prove` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_inquiry (UNIQUE = 1:1)',
  `design_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'mis. DS003479',
  `final_version` int unsigned DEFAULT NULL COMMENT 'Versi final terpilih',
  `pdf_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path PDF artwork siap cetak final',
  `physical_approved_date` date DEFAULT NULL COMMENT 'Tgl approval fisik (tanda tangan basah)',
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Entry oleh officer; approval fisik oleh customer',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_prove`) USING BTREE,
  UNIQUE KEY `uq_prove_inquiry` (`inquiry_id`) USING BTREE,
  CONSTRAINT `fk_prove_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Prove final per inquiry (1:1). Repeat order = inquiry baru = prove baru';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_prove`
--

LOCK TABLES `iqp_prove` WRITE;
/*!40000 ALTER TABLE `iqp_prove` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_prove` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_prove_log`
--

DROP TABLE IF EXISTS `iqp_prove_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_prove_log` (
  `id_prove_log` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prove_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_prove',
  `version_no` int unsigned NOT NULL COMMENT 'v1, v2, v3...',
  `separation` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Separasi CMYK+PMS',
  `pdf_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sebab revisi/molor (follow-up wajib)',
  `logged_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `logged_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_prove_log`) USING BTREE,
  KEY `idx_plog_prove` (`prove_id`) USING BTREE,
  CONSTRAINT `fk_plog_prove` FOREIGN KEY (`prove_id`) REFERENCES `iqp_prove` (`id_prove`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Log versi prove (v1/v2/v3) di bawah prove header';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_prove_log`
--

LOCK TABLES `iqp_prove_log` WRITE;
/*!40000 ALTER TABLE `iqp_prove_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_prove_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_tier`
--

DROP TABLE IF EXISTS `iqp_tier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_tier` (
  `id_tier` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_inquiry',
  `tier_seq` int unsigned NOT NULL COMMENT 'Urutan tampil tier',
  `qty` decimal(20,4) NOT NULL COMMENT 'Kuantitas tier (pcs)',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=tier UTAMA (WAJIB tepat 1 per inquiry)',
  `discount_item_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PERCENT | AMOUNT (NULL=tanpa diskon)',
  `discount_item_value` decimal(20,4) DEFAULT NULL,
  `price_excl` decimal(20,4) DEFAULT NULL COMMENT 'Harga/pc excl PPN (snapshot dari costing)',
  `price_incl` decimal(20,4) DEFAULT NULL COMMENT 'Harga/pc incl PPN',
  `primary_key_guard` bigint unsigned GENERATED ALWAYS AS (if((`is_primary` = 1),`inquiry_id`,NULL)) STORED,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_tier`) USING BTREE,
  UNIQUE KEY `uq_one_primary_per_inquiry` (`primary_key_guard`) USING BTREE,
  KEY `idx_tier_inquiry` (`inquiry_id`) USING BTREE,
  CONSTRAINT `fk_tier_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Tier qty inquiry. 1 UTAMA dipaksa via unique index. Diskon item per tier';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_tier`
--

LOCK TABLES `iqp_tier` WRITE;
/*!40000 ALTER TABLE `iqp_tier` DISABLE KEYS */;
INSERT INTO `iqp_tier` (`id_tier`, `inquiry_id`, `tier_seq`, `qty`, `is_primary`, `discount_item_type`, `discount_item_value`, `price_excl`, `price_incl`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES (4,5,1,5000.0000,1,NULL,NULL,NULL,NULL,'2026-08-07 21:42:28','2026-08-07 21:42:28','web','web'),(5,6,1,1000.0000,1,NULL,NULL,NULL,NULL,'2026-08-07 22:58:54','2026-08-07 22:58:54','web','web');
/*!40000 ALTER TABLE `iqp_tier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_tooling`
--

DROP TABLE IF EXISTS `iqp_tooling`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_tooling` (
  `id_tooling` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint unsigned NOT NULL COMMENT 'FK -> iqp_inquiry',
  `tooling_kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PLATE|PISAU|MATRAS|FILM',
  `tooling_asset_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> m_tooling_asset (bila reuse existing)',
  `tooling_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ADA|ADA_WARNING|AUS|TIDAK_ADA',
  `is_new_charge` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=tooling baru -> biaya sekali (line terpisah)',
  `cost` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT 'Biaya tooling baru (0 bila repeat/ADA)',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_tooling`) USING BTREE,
  KEY `idx_tool_inquiry` (`inquiry_id`) USING BTREE,
  CONSTRAINT `fk_tool_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Line tooling. AUS/TIDAK_ADA -> biaya baru terpisah, tak disembunyikan di harga/pc';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_tooling`
--

LOCK TABLES `iqp_tooling` WRITE;
/*!40000 ALTER TABLE `iqp_tooling` DISABLE KEYS */;
/*!40000 ALTER TABLE `iqp_tooling` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iqp_type`
--

DROP TABLE IF EXISTS `iqp_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iqp_type` (
  `type_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'NEW | RECALC | REVISE',
  `type_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label UI',
  `activate` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`type_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Lookup tipe inquiry. Set once di header, tak berubah';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iqp_type`
--

LOCK TABLES `iqp_type` WRITE;
/*!40000 ALTER TABLE `iqp_type` DISABLE KEYS */;
INSERT INTO `iqp_type` VALUES ('NEW','Produk Baru',1),('RECALC','Hitung Ulang Harga',1),('REVISE','Ubah Spesifikasi',1);
/*!40000 ALTER TABLE `iqp_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quo_quotation`
--

DROP TABLE IF EXISTS `quo_quotation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quo_quotation` (
  `id_quotation` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL COMMENT 'FK logis -> tm2mst.cust_customer, lintas-DB',
  `revision_no` smallint unsigned NOT NULL DEFAULT '0',
  `issued_date` date NOT NULL,
  `valid_until` date NOT NULL,
  `payment_term` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_scheme` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EXCL' COMMENT 'EXCL|INCL PPN',
  `currency_code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IDR',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `doc_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|PUBLISHED',
  `issued_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_quotation`),
  UNIQUE KEY `quo_quotation_quotation_no_unique` (`quotation_no`),
  KEY `idx_quo_quotation_customer` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quo_quotation`
--

LOCK TABLES `quo_quotation` WRITE;
/*!40000 ALTER TABLE `quo_quotation` DISABLE KEYS */;
INSERT INTO `quo_quotation` VALUES (4,'QUO2600001',14,0,'2026-08-21','2026-09-20',NULL,'EXCL','IDR',NULL,'DRAFT','system','2026-08-21 13:39:48','2026-08-21 13:39:48','system','system'),(5,'QUO2600002',14,0,'2026-08-16','2026-09-15',NULL,'EXCL','IDR',NULL,'PUBLISHED','system','2026-08-16 13:39:48','2026-08-16 13:39:48','system','system');
/*!40000 ALTER TABLE `quo_quotation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quo_quotation_item`
--

DROP TABLE IF EXISTS `quo_quotation_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quo_quotation_item` (
  `id_item` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint unsigned NOT NULL,
  `line_seq` smallint unsigned NOT NULL DEFAULT '1',
  `source_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'INQUIRY_TIER|INQUIRY_TOOLING|FREEFORM',
  `inquiry_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> iqp_inquiry, NULL utk FREEFORM',
  `tier_id` bigint unsigned DEFAULT NULL COMMENT 'FK logis -> iqp_tier (snapshot, tak dipaksa fisik), hanya INQUIRY_TIER',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `spec_note` text COLLATE utf8mb4_unicode_ci,
  `qty` decimal(14,2) NOT NULL DEFAULT '1.00',
  `qty_unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pcs',
  `unit_price` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `discount_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_item`),
  KEY `quo_quotation_item_quotation_id_foreign` (`quotation_id`),
  KEY `quo_quotation_item_inquiry_id_foreign` (`inquiry_id`),
  CONSTRAINT `quo_quotation_item_inquiry_id_foreign` FOREIGN KEY (`inquiry_id`) REFERENCES `iqp_inquiry` (`id_inquiry`) ON DELETE SET NULL,
  CONSTRAINT `quo_quotation_item_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quo_quotation` (`id_quotation`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quo_quotation_item`
--

LOCK TABLES `quo_quotation_item` WRITE;
/*!40000 ALTER TABLE `quo_quotation_item` DISABLE KEYS */;
INSERT INTO `quo_quotation_item` VALUES (5,4,1,'MANUAL',NULL,NULL,'Folding Box Duplex 350gr Kosmetik (contoh manual)',NULL,5000.00,'Pcs',850.0000,0.00,4250000.00,'2026-08-21 13:39:48','2026-08-21 13:39:48','system','system'),(6,5,1,'MANUAL',NULL,NULL,'RSC Box Corrugated 5-Ply Ekspor (contoh manual)',NULL,1000.00,'Pcs',12500.0000,0.00,12500000.00,'2026-08-16 13:39:48','2026-08-16 13:39:48','system','system');
/*!40000 ALTER TABLE `quo_quotation_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'tm2sdi'
--

--
-- Dumping routines for database 'tm2sdi'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-28  9:23:14
