/*
 Navicat Premium Dump SQL

 Source Server         : TM 176
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46-0ubuntu0.22.04.4)
 Source Host           : 103.79.247.146:33306
 Source Schema         : tmhpp

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46-0ubuntu0.22.04.4)
 File Encoding         : 65001

 Date: 03/09/2026 14:12:44
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for hpp
-- ----------------------------
DROP TABLE IF EXISTS `hpp`;
CREATE TABLE `hpp`  (
  `hpp_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key transaksi HPP',
  `doc_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nomor dokumen kalkulasi',
  `doc_date` date NULL DEFAULT (curdate()) COMMENT 'Tanggal kalkulasi',
  `doc_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'DRAFT' COMMENT 'Status dokumen HPP',
  `i_calc_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'NEW' COMMENT 'Tipe kalkulasi HPP. FK lookup ke m_hpp_calc_type.calc_type_code: NEW=Produk Baru, RECALC=Hitung Ulang, REVISE=Revisi Produk. VARCHAR bukan ENUM agar bisa dikelola via lookup table tanpa ALTER.',
  `revision_no` int NULL DEFAULT 0 COMMENT 'Nomor revisi kalkulasi',
  `customer_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Relasi customer existing',
  `product_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Logical FK ke tmmst.m_product via v1_m_product. NULL untuk produk baru (NEW). Wajib diisi untuk RECALC dan REVISE. Trigger auto-fill: product_name, customer_id, dimensi (cm→mm).',
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama customer atau calon customer',
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama product/custom product',
  `cfg_parts_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Jenis pars yang akan di hitung. Untuk menentukan jumlah total parts',
  `qty_order_pcs` decimal(18, 2) NULL DEFAULT 0.00 COMMENT 'Qty order dalam pcs',
  `is_ppn` tinyint(1) NULL DEFAULT 1 COMMENT 'Include PPN atau tidak',
  `ppn_percent` decimal(5, 2) NULL DEFAULT 11.00 COMMENT 'Persentase PPN',
  `margin_percent` decimal(8, 2) NULL DEFAULT 0.00 COMMENT 'Margin keuntungan',
  `tolerance_percent` decimal(8, 2) NULL DEFAULT 0.00 COMMENT 'Toleransi produksi',
  `rounding_mode` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'NEAREST' COMMENT 'Mode rounding harga',
  `currency_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'IDR' COMMENT 'Currency transaksi',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan kalkulasi',
  `i_dim_panjang_mm` decimal(8, 2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'Panjang produk jadi dalam mm (P di Master Card). Auto-fill dari m_product.cm_length×10 saat pilih product_id. Diturunkan ke hpp_parts.i_dim_panjang_mm saat part dibuat. Basis formula c_ukuran_cetak_panjang_cm.',
  `i_dim_lebar_mm` decimal(8, 2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'Lebar produk jadi dalam mm (L di Master Card). Auto-fill dari m_product.cm_width×10. Diturunkan ke hpp_parts.',
  `i_dim_tinggi_mm` decimal(8, 2) UNSIGNED NULL DEFAULT 0.00 COMMENT 'Tinggi produk jadi dalam mm (T di Master Card). Auto-fill dari m_product.cm_height×10. Diturunkan ke hpp_parts. Dipakai pada c_ukuran_cetak_lebar_cm = (L+T+bleed+gutter)/10.',
  `i_lidah_lem_mm` decimal(8, 2) NULL DEFAULT 0.00 COMMENT 'Lebar lidah lem (mm). Utk SHEET_DIGITAL: masuk rumus P sheet. Auto-fill ke hpp_parts.',
  `total_material_cost` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `total_process_cost` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `total_finishing_cost` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `total_hpp` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `total_after_margin` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `total_after_ppn` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `price_per_pcs` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `create_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu pembuatan',
  `update_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu update dokumen',
  `create_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat',
  `update_by` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir update',
  `stat_confirm` tinyint UNSIGNED NULL DEFAULT 0 COMMENT '0=open, 1=confirmed (read-only). setelah confirm semua data menjadi read only. jika ingin membatalkan maka ke stat_cancel. jika ingin mengubah di lakukan revisi, copy paste record ini lalu bedakan revisi nya',
  `stat_cancel` tinyint UNSIGNED NULL DEFAULT 0 COMMENT '0=open, 1=cancelled. digunakan saat sudah di confirm ingin di batalkan',
  `stat_closed` tinyint UNSIGNED NULL DEFAULT 0 COMMENT '0=open, 1=closed. saat ini berarti data sudah tidak bisa di cancel. jadi setalh di closed itu sudah berubah menjadi Customer Order.',
  `stat_failed` tinyint UNSIGNED NULL DEFAULT 0 COMMENT '0=belum 1 =failed. perhitungan harga yang sekaligus menjadi dasar sales quotation, gagal jadi order',
  PRIMARY KEY (`hpp_id`) USING BTREE,
  INDEX `idx_doc_no`(`doc_no` ASC) USING BTREE,
  INDEX `idx_doc_date`(`doc_date` ASC) USING BTREE,
  INDEX `idx_customer_id`(`customer_id` ASC) USING BTREE,
  INDEX `idx_product_type_id`(`cfg_parts_id` ASC) USING BTREE,
  INDEX `idx_doc_status`(`doc_status` ASC) USING BTREE,
  INDEX `idx_product_id`(`product_id` ASC) USING BTREE,
  INDEX `idx_calc_type`(`i_calc_type` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header HPP. Satu baris = satu dokumen HPP (request kalkulasi dari sales).' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of hpp
-- ----------------------------
INSERT INTO `hpp` VALUES (1, '26HPP0000001', '2026-05-20', 'DRAFT', 'NEW', 0, 0, 0, 'aaa', 'aaa', 0, 1000.00, 0, 11.00, 15.00, 6.00, 'NEAREST', 'IDR', '', 0.00, 0.00, 0.00, 0.00, 1608105.9000, 3645500.0000, 1069312.5000, 6322918.4000, 7885.0500, 7567.6000, 6702.2935, '2026-06-26 00:00:00', '2026-06-26 00:00:00', '_system', '_system', 0, 1, 0, 0);
INSERT INTO `hpp` VALUES (2, '26HPP0000002', '2026-05-21', 'DRAFT', 'NEW', 0, 0, 0, 'UAT TEST - NS40 DC', 'NS40 DC 200X134X247MM', 0, 1000.00, 1, 11.00, 20.00, 0.00, 'NEAREST', 'IDR', '', 200.00, 134.00, 247.00, 0.00, 1608105.9000, 3645500.0000, 1069312.5000, 6322918.4000, 0.0000, 0.0000, 0.0000, '2026-06-23 10:28:04', '2026-06-23 10:28:04', '_system', '_system', 0, 0, 0, 0);
INSERT INTO `hpp` VALUES (3, '26HPP0000003', '2026-06-03', 'DRAFT', 'NEW', 0, 0, 0, '', 'product1', 5, 550.00, 1, 10.00, 15.00, 3.00, 'NEAREST', 'IDR', '', 40.00, 40.00, 10.00, 0.00, 1209.0000, 2070.0000, 654.5400, 3933.5455, 4766.5300, 45908.5400, 4051.5519, '2026-06-04 00:00:00', '2026-06-26 00:00:00', '_system', '_system', 0, 0, 0, 0);
INSERT INTO `hpp` VALUES (4, '26HPP0000004', '2026-06-03', 'DRAFT', 'NEW', 0, 0, 0, '', 'product', 5, 720.00, 1, 11.00, 12.50, 1.00, 'NEAREST', 'IDR', '', 40.00, 40.00, 10.00, 0.00, 3260.3100, 850.0000, 0.0000, 4110.3147, 4744.9500, 4887.1000, 4151.8330, '2026-06-23 00:00:00', '2026-06-26 00:00:00', '_system', '_system', 0, 0, 0, 0);
INSERT INTO `hpp` VALUES (5, '26HPP0000005', '2026-06-27', 'DRAFT', 'RECALC', 0, 54, 6143, '', '', 1, 3000.00, 0, 10.00, 15.00, 6.00, 'NEAREST', 'IDR', '', 0.00, 0.00, 0.00, 0.00, 343.2000, 853.0200, 251.9800, 1331.4034, 1660.3300, 1597.6400, 1411.2876, '2026-06-29 00:00:00', '2026-06-29 00:00:00', '_system', '_system', 0, 0, 0, 0);
INSERT INTO `hpp` VALUES (6, '26HPP0000006', '2026-06-27', 'DRAFT', 'RECALC', 0, 0, 0, '', '', 1, 3000.00, 1, 10.00, 15.00, 6.00, 'NEAREST', 'IDR', '', 0.00, 0.00, 0.00, 0.00, 343.2000, 736.2100, 251.9800, 1331.4034, 1660.3300, 1757.4100, 1411.2876, '2026-06-27 00:00:00', '2026-06-27 00:00:00', '_system', '_system', 0, 0, 0, 0);
INSERT INTO `hpp` VALUES (7, '26HPP0000007', '2026-07-07', 'DRAFT', 'RECALC', 0, 54, 6636, 'RAJA BAKERY', 'SJJ IB GOLD STAR GM5Z-3B CV (12V7AH) ', 0, 5000.00, 1, 11.00, 15.00, 5.00, 'NEAREST', 'IDR', '', 135.00, 108.00, 140.00, 0.00, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '2026-07-07 00:00:00', '2026-07-07 00:00:00', '_system', '_system', 0, 0, 0, 0);
INSERT INTO `hpp` VALUES (8, '26HPP0000008', '2026-07-11', 'DRAFT', 'RECALC', 0, 10, 26, 'PAK IFUNG', 'DOS WARUNG NASIONAL (DS000465)', 0, 10000.00, 1, 11.00, 12.50, 3.00, 'NEAREST', 'IDR', '', 200.00, 200.00, 79.00, 0.00, 1123.1200, 155.0000, 67.9700, 1346.0966, 1584.5400, 1629.3200, 1386.4795, '2026-07-11 00:00:00', '2026-07-11 00:00:00', 'wiwik', 'wiwik', 0, 0, 0, 0);
INSERT INTO `hpp` VALUES (9, '26HPP0000009', '2026-07-18', 'DRAFT', 'NEW', 0, 138, 0, 'CV SAMUDRA KIAT JAYA', '', 1, 1800000.00, 0, 11.00, 15.00, 11.00, 'NEAREST', 'IDR', '', 427.00, 220.00, 172.00, 35.00, 23949.5800, 1150.0000, 0.0000, 25099.5832, 33178.5600, 30300.5200, 28201.7789, '2026-07-18 00:00:00', '2026-07-18 00:00:00', 'wiwik', 'wiwik', 0, 0, 0, 0);

-- ----------------------------
-- Table structure for hpp_parts
-- ----------------------------
DROP TABLE IF EXISTS `hpp_parts`;
CREATE TABLE `hpp_parts`  (
  `hpp_parts_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key unique per part kalkulasi HPP. Auto increment.',
  `hpp_id` bigint UNSIGNED NOT NULL COMMENT 'Logical FK ke hpp.hpp_id. Tidak menggunakan physical FK — relasi dikelola Scriptcase.',
  `no_urut` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Urutan part dalam satu HPP. Contoh: 1=Inner Box, 2=Outer Box, 3=Label.',
  `qty_part` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Qty efektif untuk kalkulasi part ini. Dihitung di frm_hpp dari qty_order_pcs.',
  `part_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama part packaging. Contoh: Inner Box DC, Outer Box SHEET, Label.',
  `part_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DC' COMMENT 'Jenis kalkulasi part. Nilai: DC | DC+SF | DC+SHEET | KRAFT | SHEET. Menentukan blok formula yang dijalankan engine.',
  `i_dim_panjang_mm` decimal(8, 2) NULL DEFAULT 0.00,
  `i_dim_lebar_mm` decimal(8, 2) NULL DEFAULT 0.00,
  `i_dim_tinggi_mm` decimal(8, 2) NULL DEFAULT 0.00,
  `i_lidah_lem_mm` decimal(8, 2) NULL DEFAULT 0.00,
  `pakai_laminasi` tinyint(1) NOT NULL DEFAULT 0,
  `mcard_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'Logical FK ke tmmst.m_card per part. NULL untuk produk baru. Diisi setelah HPP CONFIRMED (generate m_card) atau saat auto-fill dari m_product_parts.mcard_id via product_id. Satu produk multi-komponen bisa punya mcard_id berbeda tiap part.',
  `gramatur_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'Logical FK ke m_paper_grammage.id_cons_grammage. Sumber harga dan DC% duplex/kraft.',
  `i_dc_persen_override` decimal(5, 2) NULL DEFAULT NULL COMMENT 'Override DC% per part dalam nilai aktual. NULL = ambil dari m_paper_grammage.duplex_dc_percentage/100. Contoh: 5.30 → dc_factor = 1+5.30 = 6.30.',
  `i_panjang_plano_cm` decimal(10, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Panjang plano fisik (cm) termasuk gripper. Dipakai: c_paper_cost_pc. Gripper tidak masuk area cetak.',
  `i_lebar_plano_cm` decimal(10, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Lebar plano fisik (cm) termasuk gripper. Dipakai: c_paper_cost_pc. Gripper tidak masuk area cetak.',
  `i_cut_per_plano` decimal(8, 4) NOT NULL DEFAULT 1.0000 COMMENT 'Jumlah potongan per plano. Dipakai pada formula c_paper_cost_pc sebagai pembagi. Default 1.',
  `i_isi_per_lb` decimal(8, 4) NOT NULL DEFAULT 1.0000 COMMENT 'Jumlah PCS dalam 1 lembar bahan (LB). Dipakai sebagai pembagi pada formula c_paper_cost_pc dan c_tooling_cost_pc. Default 1.',
  `i_jumlah_warna` tinyint UNSIGNED NOT NULL DEFAULT 4 COMMENT 'Input jumlah warna cetak. Dipakai pada formula c_tooling_cost_pc: plate_per_color * i_num_colors / qty / pcs_per_lb.',
  `sheet_rate_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'Logical FK ke m_sheet_rate. Filter type=SHEET untuk DC+SHEET, filter type=SINGLE FACE untuk DC+SF. Sumber index_substance (Rp/m2).',
  `supplier_sheet_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Supplier corrugated dipilih user. FK ke m_sheet_supplier. WAJIB diisi jika part_type DC+SHEET, DC+SF, atau SHEET.',
  `c_ongkos_lam_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT ' hasil J38 (saat ini ongkos lam belum punya\nfield hasil per-PC; c_i_ongkos_lam I38 sudah ada utk total order).',
  `c_sheet_compare_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Readonly. Ringkasan perbandingan harga semua supplier. Di-set oleh method compare_sheet_suppliers(). Format: teks multi-baris untuk tampil di form.',
  `i_sheet_panjang_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Input panjang piece SF atau SH dalam cm. Dipakai pada formula i_lam_sheet_pc auto-calc untuk DC+SHEET dan DC+SF.',
  `i_sheet_lebar_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Input lebar piece SF dalam cm. Dipakai khusus DC+SF pada formula i_lam_sheet_pc = index * i_sheet_panjang_cm * i_sheet_lebar_cm.',
  `i_lebar_trim_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Input lebar trim dalam cm. Dipakai pada formula DC+SHEET: i_lam_sheet_pc = index * i_lebar_trim_cm * i_sheet_panjang_cm / i_isi_per_trim * (1 - i_discount_rm).',
  `i_isi_per_trim` smallint UNSIGNED NULL DEFAULT 1 COMMENT 'Input jumlah piece per trim (ISI/TRIM). Dipakai pada formula DC+SHEET sebagai pembagi. Minimal 1.',
  `i_discount_rm` decimal(5, 4) NULL DEFAULT 0.0000 COMMENT 'Input discount atau surcharge harga sheet. Nilai positif = diskon, negatif = surcharge. Range -0.9900 s/d 0.9900. Dipakai pada formula DC+SHEET: * (1 - i_discount_rm).',
  `i_ongkos_cetak_rim` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos cetak per RIM (1 RIM = 500 lembar cetak). Dipakai pada formula c_process_cost_pc: (c_jumlah_cetak_lc / 500) * i_ongkos_cetak_rim / qty_order.',
  `i_ongkos_plong_lb` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input rate ongkos plong per LB (lembar bahan) dalam Rp. Dipakai pada formula c_process_cost_pc: c_jumlah_bahan_lb * i_plong_rate_rp_lb / qty_order.',
  `i_ongkos_lem_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Rate ongkos lem/laminasi FLAT per PCS (Rp). Meski label Excel \"per LC\", rate diinput sudah per PC final. Formula: c_lem_cost_pc = i_ongkos_lem_pc.',
  `i_ongkos_hotprint_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos hotprint / foil stamping per PCS dalam Rp.',
  `i_ongkos_kupas_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos kupas / stripping per PCS dalam Rp.',
  `i_ongkos_qc_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos QC + packing per PCS dalam Rp.',
  `i_ongkos_kirim_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos kirim / delivery per PCS dalam Rp.',
  `i_ongkos_print_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos print per PCS dalam Rp. Khusus part_type SHEET (cetak fleksografi / digital).',
  `c_print_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'SHEET: print per PC = i_ongkos_print_pc x 2 (Excel C20 bolak-balik).',
  `i_ongkos_diecut_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos die-cut / slotting per PCS dalam Rp. Khusus part_type SHEET.',
  `i_ongkos_slitter_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos slitter (potong memanjang) per PCS dalam Rp. Khusus part_type SHEET.',
  `i_ongkos_slotter_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos slotter (alur lipat) per PCS dalam Rp. Khusus part_type SHEET.',
  `join_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Logical FK ke m_join_type.code. Jenis penyambungan box corrugated. Contoh: STITCHING_SINGLE, GLUE_DOUBLE. Khusus part_type SHEET.',
  `packing_type_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'Logical FK ke m_packing_type.id_packing. Jenis packing: NONE/WRAP/SHRINK/WRAP_SHRINK.',
  `i_ongkos_packing_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Rate ongkos packing per PCS (Rp). Default dari m_packing_type.default_rate, overridable per order.',
  `i_ongkos_join_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos proses join / stitching / gluing per PCS dalam Rp. Nilai diambil dari m_join_type atau input manual. Khusus part_type SHEET.',
  `i_ongkos_partisi_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos partisi / sekat per PCS dalam Rp. Khusus part_type SHEET.',
  `i_ongkos_layer_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos layer / double wall per PCS dalam Rp. Khusus part_type SHEET.',
  `i_ongkos_pisau_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'SHEET: ongkos pisau diecut per PC (Excel OB_A1 C29). Bukan tooling DC.',
  `i_ongkos_laminasi_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Input ongkos proses laminasi (ongkos mesin/tenaga laminasi) per PCS dalam Rp. Berbeda dari i_lam_biasa_pc yang merupakan biaya material film laminasi.',
  `i_ongkos_lam_rp_cm2_sh` decimal(8, 4) NULL DEFAULT NULL COMMENT 'Rate ongkos proses laminasi SHEET per cm² (Rp/cm²). Formula: kontribusi_sh = flag_sh * uk_p * uk_l * rate / i_isi_per_lb. NS40=0.15, FS_GTZ=0.095, KRAFT=0.075. NULL = tidak auto-calc, pakai i_ongkos_laminasi_pc manual.',
  `i_ongkos_lam_rp_cm2_sf` decimal(8, 4) NULL DEFAULT NULL COMMENT 'Rate ongkos proses laminasi SF per cm² (Rp/cm²). Formula: kontribusi_sf = flag_sf * uk_p * uk_l * rate / i_isi_per_lb. NS40=0.10, FS_GTZ=0.085, KRAFT=0.065. NULL = tidak dihitung.',
  `i_lam_sheet_flat_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'J36 Laminasi Sheet, Rp/PC final. NS40=1360, NKC135=1600 (flat); FS_GTZ=1852/isi. User input nilai akhir per-PC.',
  `sf_ef_rate_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'J37: Logical FK ke m_sheet_rate.doc_id (lookup via v1_sf_ef_rate, flute E/F). Sumber harga SF EF. NULL = tidak pakai SF EF.',
  `i_sf_ef_rate_override` decimal(18, 4) NULL DEFAULT NULL COMMENT 'J37: override harga SF EF per order. NULL = ambil m_sheet_rate.index_substance (via v1_sf_ef_rate.rate). Harga berubah tiap file.',
  `i_lam_pakai_ukuran_sf` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'J38: 0 = ongkos laminasi pakai ukuran cetak (B22/C22, default). 1 = pakai ukuran SF/SH (B23/C23) — kasus NKC135.',
  `film_rate_id` tinyint UNSIGNED NULL DEFAULT NULL COMMENT 'Logical FK ke m_film_rate.code. Nilai: 55 = Normal, 60 = Full Cetak + WB Laminasi. Sumber film_rate untuk formula c_tooling_cost_pc.',
  `i_film_faktor` decimal(8, 4) NOT NULL DEFAULT 0.2500 COMMENT 'Input film factor per part. Default 0.25 dari konstanta FILM_FACTOR_DEFAULT. Bisa di-override per part. Dipakai pada formula c_tooling_cost_pc: i_film_factor * c_area_cetak_cm2 * film_rate / qty_order / i_pcs_per_lb.',
  `i_pisau_faktor` decimal(8, 4) NOT NULL DEFAULT 0.2500 COMMENT 'Input knife amortization factor per part. Default 0.25 dari konstanta KNIFE_FACTOR_DEFAULT. Dipakai pada formula c_tooling_cost_pc: i_knife_factor * i_knife_rate_rp / qty_order / i_pcs_per_lb.',
  `i_pisau_rate_rp` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Rate biaya pisau/cetakan per order dalam Rp. Di-set otomatis via Scriptcase onLoad dan Ajax onChange part_type: DC=DC+SF=DC+SHEET=900000, KRAFT=1500000, SHEET=0. User tidak input manual.',
  `i_ongkos_plate_warna` decimal(18, 4) NOT NULL DEFAULT 90000.0000 COMMENT 'Biaya plate CTP per warna dalam Rp. Default 90000 dari konstanta PLATE_PER_COLOR. Bisa di-override per part. Dipakai pada formula c_tooling_cost_pc: i_plate_rp_per_color * i_num_colors / qty_order / i_pcs_per_lb.',
  `c_uk_cetak_panjang_cm` decimal(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ukuran cetak panjang cm. Input langsung user dari Master Card (Excel B22).',
  `c_uk_cetak_lebar_cm` decimal(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ukuran cetak lebar cm. Input langsung user dari Master Card (Excel C22).',
  `c_area_cetak_cm2` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Luas area cetak (cm²). Formula: c_uk_cetak_panjang_cm * c_uk_cetak_lebar_cm. Tidak termasuk gripper. Dipakai: coating, film, plate, pisau.',
  `c_jumlah_cetak_lc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Jumlah lembar cetak (LC). Formula: qty_part / i_cut_per_plano. Excel B21.',
  `c_jumlah_bahan_lb` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Jumlah lembar bahan (LB). Saat ini sama dengan c_jumlah_cetak_lc (hpp_parts tidak punya field waste). Excel B21.',
  `c_paper_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Biaya material kertas/PC. DC/Ivory: (plano_p*plano_l/8611)*price_list*(1+dc_pct)/(500*cut*isi_lb). KRAFT: (plano_p*plano_l/10000)*price_kg*(gsm/1000)/(cut*isi_lb). SHEET: dari c_paper_cost_pc dihitung via sheet_rate.',
  `c_sheet_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'SHEET: harga kertas sheet per PC (Excel C19). Pengganti paper utk SHEET.',
  `c_i_paper` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total harga kertas order. Formula: c_paper_cost_pc * qty_part * i_isi_per_lb.',
  `c_i_sheet_cost` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: harga sheet total order = c_sheet_cost_pc x qty.',
  `c_process_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Total biaya proses/PC. Formula: cetak + plong + lem + hotprint + kupas + qc + kirim + laminasi + ongkos_lam.',
  `c_tooling_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Total biaya tooling/PC. Formula: c_film_cost_pc + c_plate_cost_pc + c_knife_cost_pc.',
  `c_total_coating_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Total biaya coating/PC. Formula: SUM(hpp_parts_coating.cost_per_pc). Excel: SUM(J32:J35). MASUK ke c_total_part_cost_pc.',
  `c_total_coating_lc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Total biaya coating/LC. Formula: SUM(hpp_parts_coating.cost_per_lc) = SUM(uk_p*uk_l*index).',
  `c_sf_ef_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'J37 hasil: c_uk_cetak_p*c_uk_cetak_l/10000 * harga_sf / i_isi_per_lb * i_sf_ef_flag.',
  `c_i_sf_ef` decimal(18, 4) NULL DEFAULT NULL COMMENT 'J37 x qty: total SF EF per order = c_sf_ef_cost_pc * qty_part.',
  `c_i_total_coating` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total biaya coating order. Formula: c_total_coating_pc * qty_part * i_isi_per_lb.',
  `c_total_part_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'HPP/PC part. Formula: c_paper_cost_pc + c_process_cost_pc + c_total_coating_pc + c_lam_sheet_cost_pc + c_tooling_cost_pc. Excel J43.',
  `c_cetak_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Biaya cetak/PC. Formula: i_ongkos_cetak_rim / 500 / i_cut_per_plano / i_isi_per_lb. Excel J24.',
  `c_i_cetak` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos cetak order. Formula: c_cetak_cost_pc * qty_part * i_isi_per_lb. Excel I24.',
  `c_plong_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Biaya plong/PC. Formula: i_ongkos_plong_lb / i_isi_per_lb. Tidak dibagi cut — plong terjadi per LB setelah plano dipotong. Excel J25.',
  `c_i_plong` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos plong order. Formula: c_plong_cost_pc * qty_part * i_isi_per_lb. Excel I25.',
  `c_lem_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Biaya lem/PC. Formula: i_ongkos_lem_pc (flat, tidak ada pembagi). Excel J26.',
  `c_i_lem` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos lem order. Formula: i_ongkos_lem_pc * qty_part * i_isi_per_lb. Excel I26.',
  `c_i_hotprint` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos hotprint order. Formula: i_ongkos_hotprint_pc * qty_part * i_isi_per_lb. Excel I28.',
  `c_i_print` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: print total order = c_print_cost_pc x qty.',
  `c_i_diecut` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: diecut total order = i_ongkos_diecut_pc x qty.',
  `c_i_slitter` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: slitter total order = i_ongkos_slitter_pc x qty.',
  `c_i_slotter` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: slotter total order = i_ongkos_slotter_pc x qty.',
  `c_i_join` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: join total order = i_ongkos_join_pc x qty.',
  `c_i_partisi` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: partisi total order = i_ongkos_partisi_pc x qty.',
  `c_i_layer` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: layer total order = i_ongkos_layer_pc x qty.',
  `c_i_pisau_sheet` decimal(18, 2) NULL DEFAULT NULL COMMENT 'SHEET: pisau diecut total order = i_ongkos_pisau_pc x qty.',
  `c_i_kupas` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos kupas order. Formula: i_ongkos_kupas_pc * qty_part * i_isi_per_lb. Excel I29.',
  `c_i_qc` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos QC order. Formula: i_ongkos_qc_pc * qty_part * i_isi_per_lb. Excel I30.',
  `c_i_kirim` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos kirim order. Formula: i_ongkos_kirim_pc * qty_part * i_isi_per_lb. Excel I31.',
  `c_i_packing` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos packing order. Formula: i_ongkos_packing_pc * qty_part * i_isi_per_lb.',
  `c_i_ongkos_lam` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total ongkos proses laminasi order. Formula: i_ongkos_laminasi_pc * qty_part * i_isi_per_lb. Excel I38.',
  `c_i_lam_sheet_flat` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `c_film_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Biaya film/PC. Formula: i_film_faktor * c_area_cetak_cm2 * film_rate / qty_part / i_isi_per_lb. film_rate dari m_film_rate (55 normal, 60 full cetak+WB). Excel J40.',
  `c_i_film` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total biaya film order. Formula: c_film_cost_pc * qty_part * i_isi_per_lb. Excel I40.',
  `c_plate_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Biaya plate/PC. Formula: i_ongkos_plate_warna * i_jumlah_warna / qty_part / i_isi_per_lb. Excel J41.',
  `c_i_plate` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total biaya plate order. Formula: c_plate_cost_pc * qty_part * i_isi_per_lb. Excel I41.',
  `c_knife_cost_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Biaya pisau/PC. Formula: i_pisau_faktor * i_pisau_rate_rp / qty_part / i_isi_per_lb. Excel J42.',
  `c_i_knife` decimal(18, 2) NULL DEFAULT NULL COMMENT 'Total biaya pisau order. Formula: c_knife_cost_pc * qty_part * i_isi_per_lb. Excel I42.',
  `calc_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan user',
  `description_calc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan ringkas runtime kalkulasi: warning, asumsi khusus, atau flag debug. Detail lengkap per formula tersimpan di log_hpp_calc_breakdown.',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User Scriptcase yang membuat record part ini.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp otomatis saat record part dibuat.',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User Scriptcase terakhir yang mengubah record part ini.',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp otomatis saat record part terakhir diubah.',
  PRIMARY KEY (`hpp_parts_id`) USING BTREE,
  INDEX `idx_hpp_id`(`hpp_id` ASC) USING BTREE,
  INDEX `idx_part_type`(`part_type` ASC) USING BTREE,
  INDEX `idx_grammage_id`(`gramatur_id` ASC) USING BTREE,
  INDEX `idx_sheet_rate`(`sheet_rate_id` ASC) USING BTREE,
  INDEX `idx_result_cost`(`c_total_part_cost_pc` ASC) USING BTREE,
  INDEX `idx_hpp_parts_mcard`(`mcard_id` ASC) USING BTREE,
  INDEX `idx_supplier_sheet`(`supplier_sheet_code` ASC) USING BTREE,
  INDEX `idx_packing_type`(`packing_type_id` ASC) USING BTREE,
  INDEX `idx_sf_ef_rate`(`sf_ef_rate_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 54 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Detail per part dalam satu HPP. Satu HPP terdiri 1-N part (Inner, Outer, Label).' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of hpp_parts
-- ----------------------------
INSERT INTO `hpp_parts` VALUES (1, 2, 1, 1000.00, 'NS40 DC 200X134X247MM', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 1, 0, 11, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', 1790.5000, '', 70.0000, 39.5000, 160.0000, 4, 0.0000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 2, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.0000, 0.0000, 1360.0000, 0, 0.0000, 0, 60, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 2870.0000, 500.0000, 500.0000, 1608.1059, 0.0000, 1608105.91, 0.00, 3645.5000, 595.7625, 473.5500, 473.5500, 0.0000, NULL, 473550.00, 6322.9184, 900.0000, 900000.00, 250.0000, 250000.00, 375.0000, 375000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 30000.00, 100000.00, 200000.00, 0.00, 1790500.00, 1360000.0000, 10.7625, 10762.50, 360.0000, 360000.00, 225.0000, 225000.00, '', 'v1024/06/2026 11:45:18 DC+SHEET paper=1608.11 proc=3645.5 coat=473.55 lam=1790.5 tool=595.76 sheet=0 TOTAL=6322.92 flag=PAPER_OK COAT_OK SFEF_SKIP TOOL_OK ', '_system', '2026-05-21 00:00:00', '', '2026-06-24 11:45:18');
INSERT INTO `hpp_parts` VALUES (3, 1, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 1, 0, 11, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', 1790.5000, '', 70.0000, 39.5000, 160.0000, 4, 0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.0000, 0.0000, 1360.0000, 0, 0.0000, 0, 60, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 2870.0000, 500.0000, 500.0000, 1608.1059, 0.0000, 1608105.91, 0.00, 3645.5000, 595.7625, 473.5500, 473.5500, 0.0000, NULL, 473550.00, 6322.9184, 900.0000, 900000.00, 250.0000, 250000.00, 375.0000, 375000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 30000.00, 100000.00, 200000.00, 0.00, 1790500.00, 1360000.0000, 10.7625, 10762.50, 360.0000, 360000.00, 225.0000, 225000.00, 'v2.1|12/06/2026 07:41||qty=1,000|lc=500|cut=2|paper=1,608.11|proc=1,855.00|coat=473.55|lam(J36+J37+J38)=1,360.00+0.00+430.50|tool=595.76|TOTAL=6,322.92', 'v1026/06/2026 15:30:46 DC+SHEET paper=1608.11 proc=3645.5 coat=473.55 lam=1790.5 tool=595.76 sheet=0 TOTAL=6322.92 flag=PAPER_OK COAT_OK SFEF_SKIP TOOL_OK ', '', '2026-05-26 00:00:00', '_system', '2026-06-26 15:30:46');
INSERT INTO `hpp_parts` VALUES (17, 0, 1, 0.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, NULL, NULL, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 2870.0000, 500.0000, 500.0000, 1592.7418, NULL, NULL, NULL, 2286.5000, 594.8656, NULL, NULL, NULL, NULL, NULL, 4574.5575, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 9.8656, NULL, 360.0000, NULL, 225.0000, NULL, 'v5.0 | 02/06/2026 14:45 |  | dc_factor=6.30 | up=2 | area=2,870.00cm2 | lc=500 | paper=1,592.74 | process=2,286.50 | coating=100.45 | tooling=594.87 | TOTAL=4,574.56', NULL, '', '2026-06-02 21:45:36', '', '2026-06-02 21:45:36');
INSERT INTO `hpp_parts` VALUES (22, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, 0.0000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, NULL, NULL, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 2870.0000, 500.0000, 500.0000, 1592.7418, NULL, NULL, NULL, 2286.5000, 594.8656, NULL, NULL, NULL, NULL, NULL, 4574.5575, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 9.8656, NULL, 360.0000, NULL, 225.0000, NULL, 'v5.0 | 02/06/2026 21:17 |  | dc_factor=6.30 | up=2 | area=2,870.00cm2 | lc=500 | paper=1,592.74 | process=2,286.50 | coating=100.45 | tooling=594.87 | TOTAL=4,574.56', NULL, '', '2026-06-02 07:00:00', '', '2026-06-22 19:25:56');
INSERT INTO `hpp_parts` VALUES (23, 0, 1, 3000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, NULL, NULL, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 2870.0000, 1500.0000, 1500.0000, 1592.7418, NULL, NULL, NULL, 2286.5000, 198.2885, NULL, NULL, NULL, NULL, NULL, 4177.9804, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 3.2885, NULL, 120.0000, NULL, 75.0000, NULL, 'v5.0 | 03/06/2026 20:23 |  | dc_factor=6.30 | up=2 | area=2,870.00cm2 | lc=1,500 | paper=1,592.74 | process=2,286.50 | coating=100.45 | tooling=198.29 | TOTAL=4,177.98', NULL, '_system', '2026-06-04 03:23:16', '_system', '2026-06-23 15:48:07');
INSERT INTO `hpp_parts` VALUES (24, 0, 1, 3000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, NULL, NULL, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 2870.0000, 1500.0000, 1500.0000, 1592.7418, NULL, NULL, NULL, 2286.5000, 198.2885, NULL, NULL, NULL, NULL, NULL, 4177.9804, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 3.2885, NULL, 120.0000, NULL, 75.0000, NULL, 'v5.0 | 03/06/2026 20:24 |  | dc_factor=6.30 | up=2 | area=2,870.00cm2 | lc=1,500 | paper=1,592.74 | process=2,286.50 | coating=100.45 | tooling=198.29 | TOTAL=4,177.98', NULL, '_system', '2026-06-04 03:24:09', '_system', '2026-06-23 15:48:14');
INSERT INTO `hpp_parts` VALUES (25, 0, 1, 3000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, NULL, NULL, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 2870.0000, 1500.0000, 1500.0000, 1592.7418, NULL, NULL, NULL, 2286.5000, 198.2885, NULL, NULL, NULL, NULL, NULL, 4177.9804, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 3.2885, NULL, 120.0000, NULL, 75.0000, NULL, 'v5.0 | 03/06/2026 20:25 |  | dc_factor=6.30 | up=2 | area=2,870.00cm2 | lc=1,500 | paper=1,592.74 | process=2,286.50 | coating=100.45 | tooling=198.29 | TOTAL=4,177.98', NULL, '_system', '2026-06-04 03:25:05', '_system', '2026-06-26 14:04:13');
INSERT INTO `hpp_parts` VALUES (29, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.1500, 0.1000, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 0.0000, 500.0000, 500.0000, 1592.7418, NULL, NULL, NULL, 4117.3300, 594.8656, NULL, NULL, NULL, NULL, NULL, 6405.3875, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 9.8656, NULL, 360.0000, NULL, 225.0000, NULL, 'v1.1|05/06/2026 13:21||qty=1,000|lc=500|up=2|paper=1,592.74|proc=4,117.33|coat=100.45|tool=594.87|TOTAL=6,405.39', NULL, '', '2026-06-05 20:21:37', '', '2026-06-05 20:21:37');
INSERT INTO `hpp_parts` VALUES (31, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.1500, 0.1000, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 0.0000, 500.0000, 500.0000, 1592.7418, NULL, NULL, NULL, 4119.0500, 594.8656, NULL, NULL, NULL, NULL, NULL, 6306.6575, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 9.8656, NULL, 360.0000, NULL, 225.0000, NULL, 'v1.1-EXCEL_MATCH|05/06/2026 14:48||qty=1,000|lc=500|up=2|paper=1,592.74|proc=4,119.05|coat_info_only=100.45|tool=594.87|TOTAL=6,306.66', NULL, '', '2026-06-05 21:48:23', '', '2026-06-05 21:48:23');
INSERT INTO `hpp_parts` VALUES (32, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.1500, 0.1000, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 0.0000, 500.0000, 500.0000, 1592.7418, NULL, NULL, NULL, 4119.0500, 594.8656, NULL, NULL, NULL, NULL, NULL, 6306.6575, 900.0000, NULL, 250.0000, NULL, 375.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.0000, 9.8656, NULL, 360.0000, NULL, 225.0000, NULL, 'v1.1-EXCEL_MATCH|05/06/2026 16:53||qty=1,000|lc=500|up=2|paper=1,592.74|proc=4,119.05|coat_info_only=100.45|tool=594.87|TOTAL=6,306.66', NULL, '_system', '2026-06-05 23:53:35', '_system', '2026-06-22 19:25:56');
INSERT INTO `hpp_parts` VALUES (34, 0, 1, 2000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.1500, 0.1000, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 0.0000, 1000.0000, 1000.0000, 1592.7418, NULL, 3185483.68, NULL, 4117.3300, 297.4328, NULL, NULL, NULL, NULL, NULL, 6057.7297, 900.0000, 1800000.00, 250.0000, 500000.00, 375.0000, 750000.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 60000.00, 200000.00, 400000.00, NULL, 861000.00, 0.0000, 4.9328, 9865.63, 180.0000, 360000.00, 112.5000, 225000.00, 'v1.1|08/06/2026 09:13||qty=2,000|lc=1,000|up=2|paper=1,592.74|proc=4,117.33|coat=50.23|tool=297.43|TOTAL=6,057.73', NULL, '', '2026-06-08 16:13:50', '', '2026-06-08 16:13:50');
INSERT INTO `hpp_parts` VALUES (35, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, '', 70.0000, 39.5000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.1500, 0.1000, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 0.0000, 500.0000, 500.0000, 1592.7418, NULL, 1592741.84, NULL, 4117.3300, 594.8656, NULL, NULL, NULL, NULL, NULL, 6355.1625, 900.0000, 900000.00, 250.0000, 250000.00, 375.0000, 375000.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30000.00, 100000.00, 200000.00, NULL, 430500.00, 0.0000, 9.8656, 9865.63, 360.0000, 360000.00, 225.0000, 225000.00, 'v1.1|08/06/2026 09:32||qty=1,000|lc=500|up=2|paper=1,592.74|proc=4,117.33|coat=50.23|tool=594.87|TOTAL=6,355.16', NULL, '', '2026-06-08 16:32:42', '', '2026-06-23 09:57:13');
INSERT INTO `hpp_parts` VALUES (38, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 4, 2, 'UJK', NULL, 'Lengkapi sheet_rate, lebar_trim, dan panjang sheet untuk perbandingan.', 70.0000, 39.5000, 0.0000, 0, -0.0500, 0.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', NULL, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.0000, 0.0000, 0.0000, NULL, NULL, 0, 55, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 0.0000, 1000.0000, 1000.0000, 1592.7418, NULL, 1592741.84, NULL, 2745.5000, 594.8656, NULL, NULL, NULL, NULL, NULL, 4933.1075, 0.0000, 0.00, 250.0000, 250000.00, 375.0000, 375000.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30000.00, 100000.00, 200000.00, NULL, 430500.00, 0.0000, 9.8656, 9865.63, 360.0000, 360000.00, 225.0000, 225000.00, 'v1.1|10/06/2026 12:15||qty=1,000|lc=1,000|up=1|paper=1,592.74|proc=2,745.50|coat=0.00|tool=594.87|TOTAL=4,933.11', NULL, '', '2026-06-10 12:10:06', '', '2026-06-10 12:15:28');
INSERT INTO `hpp_parts` VALUES (39, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 11, 5.30, 70000.0000, 100000.0000, 2.0000, 1.0000, 4, 2, 'UJK', 0.0000, 'Lengkapi sheet_rate, lebar_trim, dan panjang sheet untuk perbandingan.', 70.0000, 0.0000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.1500, 0.1000, 1360.0000, 103, 0.0000, 0, 60, 0.2500, 0.2500, 900000.0000, 90000.0000, 0.0000, 41.0000, 0.0000, 500.0000, 500.0000, 1608105911.0440, NULL, 1608105911044.00, NULL, 3215.0000, 585.0000, 0.0000, 0.0000, 0.0000, NULL, 0.00, 1608109711.0440, 900.0000, 900000.00, 250.0000, 250000.00, 375.0000, 375000.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30000.00, 100000.00, 200000.00, 0.00, 1360000.00, 0.0000, 0.0000, 0.00, 360.0000, 360000.00, 225.0000, 225000.00, '', 'v3.4|15/06/2026 14:15||qty=1,000|lc=500|cut=2|isi=1|paper=1,608,105,911.04|proc=3,215.00|coat=0.00|lam(J36+J37+J38)=1,360.00+0.00+0.00|tool=585.00|TOTAL=1,608,109,711.04', '', '2026-06-15 14:15:19', '', '2026-06-22 19:25:56');
INSERT INTO `hpp_parts` VALUES (40, 0, 1, 1000.00, 'NS40', 'DC+SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 11, 5.30, 70000.0000, 100000.0000, 2.0000, 1.0000, 4, 2, 'UJK', 430.5000, 'Lengkapi sheet_rate, lebar_trim, dan panjang sheet untuk perbandingan.', 70.0000, 0.0000, 160.0000, 4, -0.1000, 900000.0000, 250.0000, 375.0000, 0.0000, 30.0000, 100.0000, 200.0000, 0.0000, NULL, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 0.1500, 0.1000, 1360.0000, 103, 0.0000, 0, 60, 0.2500, 0.2500, 900000.0000, 90000.0000, 70.0000, 41.0000, 0.0000, 500.0000, 500.0000, 1608105911.0440, NULL, 1608105911044.00, NULL, 3645.5000, 585.0000, 0.0000, 0.0000, 0.0000, NULL, 0.00, 1608110141.5440, 900.0000, 900000.00, 250.0000, 250000.00, 375.0000, 375000.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30000.00, 100000.00, 200000.00, 0.00, 1790500.00, 0.0000, 0.0000, 0.00, 360.0000, 360000.00, 225.0000, 225000.00, '', 'v3.4|15/06/2026 14:18||qty=1,000|lc=500|cut=2|isi=1|paper=1,608,105,911.04|proc=3,645.50|coat=0.00|lam(J36+J37+J38)=1,360.00+0.00+430.50|tool=585.00|TOTAL=1,608,110,141.54', '', '2026-06-15 14:18:43', '', '2026-06-23 15:48:17');
INSERT INTO `hpp_parts` VALUES (42, 4, 0, 720.00, 'OB_A1', 'SHEET', 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 0.0000, 0.0000, 1.0000, 1.0000, 0, 16, 'UJK', 0.0000, '', 126.9000, 50.2000, 155.0000, 3, 0.0350, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 100.0000, 100.0000, 150.0000, 300.0000, 350.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, 0.0000, 0, 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 3260.3147, 0.00, 2347426.58, 850.0000, 0.0000, 0.0000, 0.0000, 0.0000, NULL, 0.00, 4110.3147, 0.0000, 0.00, 0.0000, 0.00, 0.0000, 0.00, 0.00, 216000.00, 252000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 72000.00, 72000.00, 0.00, 0.00, 0.0000, 0.0000, 0.00, 0.0000, 0.00, 0.0000, 0.00, '', 'v1023/06/2026 15:46:01 SHEET paper=0 proc=850 coat=0 lam=0 tool=0 sheet=3260.31 TOTAL=4110.31 flag=SHEET_OK COAT_SKIP LAM_SKIP TOOL_SKIP ', '', '2026-06-23 00:00:00', '', '2026-06-23 15:48:20');
INSERT INTO `hpp_parts` VALUES (43, 3, 0, 550.00, 'KALENDER', 'DC', 0.00, 0.00, 0.00, 0.00, 0, 0, 3, 0.00, 65.0000, 100.0000, 1.0000, 1.0000, 4, 0, '', 0.0000, '', 0.0000, 0.0000, 0.0000, 1, 0.0000, 750000.0000, 150.0000, 320.0000, 0.0000, 0.0000, 50.0000, 50.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, 0.0000, 0, 0, 0.0000, 0.0000, 0.0000, 90000.0000, 65.0000, 100.0000, 6500.0000, 0.0000, 0.0000, 1209.0000, 0.0000, 664950.00, 0.00, 2070.0000, 654.5455, 0.0000, 0.0000, 0.0000, NULL, 0.00, 3933.5455, 1500.0000, 825000.00, 150.0000, 82500.00, 320.0000, 176000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27500.00, 27500.00, 0.00, 0.00, 0.0000, 0.0000, 0.00, 654.5455, 360000.00, 0.0000, 0.00, '', 'v1024/06/2026 13:44:46 DC paper=1209 proc=2070 coat=0 lam=0 tool=654.55 sheet=0 TOTAL=3933.55 flag=PAPER_AP_OK COAT_OK LAM_SKIP TOOL_OK ', '_system', '2026-06-24 00:00:00', '', '2026-06-26 14:04:17');
INSERT INTO `hpp_parts` VALUES (44, 0, 0, 3000.00, 'FS GTZ-5S', 'DC+SF', 0.00, 0.00, 0.00, 0.00, 0, 0, 8, 5.30, 89.0000, 125.0000, 3.0000, 4.0000, 4, 104, '', 0.0000, 'Lengkapi sheet_rate, lebar_trim, dan panjang sheet untuk perbandingan.', 88.0000, 41.0000, 0.0000, 0, 0.0000, 1500000.0000, 250.0000, 60.0000, 0.0000, 20.0000, 20.0000, 20.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 104, 2600.0000, 0, 55, 0.0500, 0.0500, 900000.0000, 90000.0000, 89.0000, 42.0000, 3738.0000, 0.0000, 0.0000, 345.9202, 0.0000, 1037760.57, 0.00, 432.5000, 33.7928, 116.8125, 467.2500, 0.0000, NULL, 350437.50, 929.0255, 250.0000, 750000.00, 62.5000, 187500.00, 60.0000, 180000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 60000.00, 60000.00, 60000.00, 0.00, 0.00, 0.0000, 0.0428, 128.49, 30.0000, 90000.00, 3.7500, 11250.00, '', 'v1027/06/2026 11:20:28 DC+SF paper=345.92 proc=432.5 coat=116.81 lam=0 tool=33.79 sheet=0 TOTAL=929.03 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ', '_system', '2026-06-27 00:00:00', '_system', '2026-06-27 11:55:00');
INSERT INTO `hpp_parts` VALUES (45, 5, 0, 3000.00, 'FS GTZ-5S', 'DC+SF', 0.00, 0.00, 0.00, 0.00, 1, 0, 20, 0.00, 89.0000, 125.0000, 3.0000, 4.0000, 4, 2, 'UJK', 60.7425, 'Lengkapi sheet_rate, lebar_trim, dan panjang sheet untuk perbandingan.', 88.5000, 41.0000, 170.0000, 4, -0.0500, 1500000.0000, 250.0000, 60.0000, 0.0000, 20.0000, 20.0000, 20.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 2, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0650, 0.0000, 104, 2600.0000, 0, 55, 0.0500, 0.0500, 900000.0000, 90000.0000, 89.0000, 42.0000, 3738.0000, 1000.0000, 1000.0000, 343.2071, 0.0000, 1029621.27, 0.00, 853.0250, 135.1713, 116.8125, 467.2500, 242.9700, 728910.0000, 350437.50, 1331.4034, 250.0000, 750000.00, 62.5000, 187500.00, 60.0000, 180000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 60000.00, 60000.00, 60000.00, 0.00, 182227.50, 0.0000, 0.1713, 513.98, 120.0000, 360000.00, 15.0000, 45000.00, '', 'v14 04/07/2026 14:00:05 DC+SF paper=343.21 proc=853.03 coat=116.81 lam=60.74 sfef=242.97 tool=135.17 sheet=0 TOTAL=1331.4 flag=PAPER_OK COAT_OK SFEF_OVR LAM_OK TOOL_OK ', '_system', '2026-06-27 00:00:00', '_system', '2026-07-04 14:00:05');
INSERT INTO `hpp_parts` VALUES (46, 6, 0, 3000.00, 'abcr', 'DC+SF', 0.00, 0.00, 0.00, 0.00, 1, 0, 20, 0.00, 89.0000, 125.0000, 3.0000, 4.0000, 4, 2, 'UJK', 303.7125, 'Lengkapi sheet_rate, lebar_trim, dan panjang sheet untuk perbandingan.', 88.5000, 41.0000, 170.0000, 4, -0.0500, 1500000.0000, 250.0000, 60.0000, 0.0000, 20.0000, 20.0000, 20.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0750, 0.0650, 0.0000, 104, 2600.0000, 0, 55, 0.0500, 0.0500, 900000.0000, 90000.0000, 89.0000, 42.0000, 3738.0000, 0.0000, 0.0000, 343.2071, 0.0000, 1029621.27, 0.00, 736.2125, 135.1713, 116.8125, 467.2500, 242.9700, 728910.0000, 350437.50, 1331.4034, 250.0000, 750000.00, 62.5000, 187500.00, 60.0000, 180000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 60000.00, 60000.00, 60000.00, 0.00, 911137.50, 0.0000, 0.1713, 513.98, 120.0000, 360000.00, 15.0000, 45000.00, '', 'v14 03/07/2026 11:05:13 DC+SF paper=343.21 proc=736.21 coat=116.81 lam=303.71 sfef=242.97 tool=135.17 sheet=0 TOTAL=1331.4 flag=PAPER_OK COAT_OK SFEF_OVR LAM_OK TOOL_OK ', '_system', '2026-06-27 00:00:00', '_system', '2026-07-03 11:05:13');
INSERT INTO `hpp_parts` VALUES (49, 0, 0, 1000.00, '', 'DC+SF', 0.00, 0.00, 0.00, 0.00, 1, 0, 0, 5.30, 89.0000, 125.0000, 3.0000, 4.0000, 4, 0, 'UJK', 547.1050, '', 88.5000, 41.0000, 0.0000, 0, 0.0000, 1500000.0000, 250.0000, 60.0000, 0.0000, 20.0000, 20.0000, 20.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 205.5900, 0.0900, 0.0800, 463.0000, 0, 0.0000, 0, 55, 0.0500, 0.0500, 900000.0000, 90000.0000, 89.0000, 42.0000, 3738.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.00, 0.00, 979.6050, 405.5140, 0.0000, 0.0000, 0.0000, NULL, 0.00, 1385.1190, 250.0000, 250000.00, 62.5000, 62500.00, 60.0000, 60000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 20000.00, 20000.00, 0.00, 547105.00, 463000.0000, 0.5140, 513.98, 360.0000, 360000.00, 45.0000, 45000.00, '', 'v1301/07/2026 14:56:33 DC+SF paper=0 proc=979.61 coat=0 lam=547.11 tool=405.51 sheet=0 TOTAL=1385.12 flag=PAPER_GAGAL COAT_SKIP SFEF_SKIP LAM_OK TOOL_OK ', '_system', '2026-07-01 14:56:33', '_system', '2026-07-01 14:56:33');
INSERT INTO `hpp_parts` VALUES (51, 7, 1, 5000.00, 'GM5Z-3B CV', 'DC+SF', 0.00, 0.00, 0.00, 0.00, 1, 0, 8, 5.30, 120.0000, 104.0000, 4.0000, 2.0000, 4, 104, 'UJK', 97.7925, 'UJK: Rp 1,075.72 (surcharge 10.0%, RM=1,880.6)\nRCA: Rp 723.66 (disc 26.0%, RM=1,880.6)', 59.0000, 51.0000, 0.0000, 0, 0.0000, 500000.0000, 75.0000, 30.0000, 0.0000, 10.0000, 10.0000, 10.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0650, 0.0000, 104, 2600.0000, 1, 60, 0.0500, 0.0500, 600000.0000, 90000.0000, 60.0000, 52.0000, 3120.0000, 0.0000, 0.0000, 582.0787, 0.0000, 2910393.68, 0.00, 827.2925, 78.0936, 101.4000, 202.8000, 405.6000, 2028000.0000, 507000.00, 1487.4648, 125.0000, 625000.00, 37.5000, 187500.00, 30.0000, 150000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 50000.00, 50000.00, 50000.00, 0.00, 488962.50, 0.0000, 0.0936, 468.00, 72.0000, 360000.00, 6.0000, 30000.00, '', 'v14 07/07/2026 08:15:19 DC+SF paper=582.08 proc=827.29 coat=101.4 lam=97.79 sfef=405.6 tool=78.09 sheet=0 TOTAL=1487.46 flag=PAPER_OK COAT_OK SFEF_OVR LAM_OK TOOL_OK ', '_system', '2026-07-07 00:00:00', '_system', '2026-07-07 08:15:19');
INSERT INTO `hpp_parts` VALUES (52, 8, 1, 10000.00, 'DUS', 'DC', 0.00, 0.00, 0.00, 0.00, 0, 0, 11, 5.60, 70.0000, 100.0000, 1.0000, 3.0000, 4, 0, 'UJK', 0.0000, '', 0.0000, 0.0000, 0.0000, 1, 0.0000, 135000.0000, 75.0000, 0.0000, 0.0000, 10.0000, 10.0000, 10.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, '', 2, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, 0.0000, 0, 45, 0.5000, 0.2500, 1000000.0000, 90000.0000, 62.0000, 100.0000, 6200.0000, 3333.3333, 3333.3333, 1123.1216, 0.0000, 11231215.89, 0.00, 145.0000, 67.9750, 0.0000, 0.0000, 0.0000, 0.0000, 0.00, 1336.0966, 90.0000, 900000.00, 25.0000, 250000.00, 0.0000, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100000.00, 100000.00, 100000.00, 0.00, 0.00, 0.0000, 6.9750, 69750.00, 36.0000, 360000.00, 25.0000, 250000.00, '', 'v14 11/07/2026 15:59:44 DC paper=1123.12 proc=145 coat=0 lam=0 sfef=0 tool=67.98 sheet=0 TOTAL=1336.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ', 'wiwik', '2026-07-11 00:00:00', 'wiwik', '2026-07-11 15:59:44');
INSERT INTO `hpp_parts` VALUES (53, 9, 0, 1800000.00, '', 'SHEET', 427.00, 220.00, 172.00, 35.00, 0, 0, 0, 0.00, 0.0000, 0.0000, 1.0000, 1.0000, 0, 10, 'UJK', 0.0000, '', 126.9000, 50.2000, 155.0000, 3, -5.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 100.0000, 100.0000, 300.0000, 600.0000, 350.0000, 0.0000, 0.0000, '', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, 0.0000, 0, 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 23949.5832, 0.00, 43109249760.00, 1150.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.00, 25099.5832, 0.0000, 0.00, 0.0000, 0.00, 0.0000, 0.00, 0.00, 1080000000.00, 630000000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 180000000.00, 180000000.00, 0.00, 0.00, 0.0000, 0.0000, 0.00, 0.0000, 0.00, 0.0000, 0.00, '', 'v14 18/07/2026 14:31:05 SHEET paper=0 proc=1150 coat=0 lam=0 sfef=0 tool=0 sheet=23949.58 TOTAL=25099.58 flag=SHEET_OK COAT_SKIP LAM_SKIP TOOL_SKIP ', 'wiwik', '2026-07-18 14:31:05', 'wiwik', '2026-07-18 14:31:05');

-- ----------------------------
-- Table structure for hpp_parts_coating
-- ----------------------------
DROP TABLE IF EXISTS `hpp_parts_coating`;
CREATE TABLE `hpp_parts_coating`  (
  `hpp_parts_id` bigint UNSIGNED NOT NULL COMMENT 'Logical Foreign Key\r\n',
  `parts_coating_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary Key untuk coating per part. Auto increment.',
  `coating_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Pilihan coating nya',
  `index_coating` decimal(20, 4) UNSIGNED NULL DEFAULT NULL COMMENT 'disimpan dari apa yang di pilih, harus disimpan karena bisa berubah dari masa ke masa',
  `cost_per_lc` decimal(20, 6) NULL DEFAULT NULL COMMENT 'Harga biaya ongkos per lembar cetak.\r\nHarga coating per lembar cetak = panjang uk cetak x lebar uk cetak x index coating',
  `cost_per_pc` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Biaya ongkos per pcs.\r\nHarga coating per pc = panjang uk cetak x lebar uk cetak x index coating dibagi (Isi dalam 1 LB Cetak)\r\n',
  `notes_coating` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User Scriptcase yang membuat record part ini.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp otomatis saat record part dibuat.',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User Scriptcase terakhir yang mengubah record part ini.',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp otomatis saat record part terakhir diubah.',
  PRIMARY KEY (`parts_coating_id`) USING BTREE,
  INDEX `idx_hpp_parts_id`(`hpp_parts_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 51 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Child coating per part. 1-N layer. c_total_coating_pc di hpp_parts = SUM cost_per_pc table ini.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of hpp_parts_coating
-- ----------------------------
INSERT INTO `hpp_parts_coating` VALUES (3, 31, 'Laminating Biasa', 0.1650, 473.550000, 473.5500, '', '', '2026-06-10 11:15:15', '', '2026-06-10 11:15:15');
INSERT INTO `hpp_parts_coating` VALUES (23, 35, 'Laminating Biasa', 0.1650, 473.550000, 473.5500, '', '', '2026-06-15 14:16:09', '', '2026-06-15 14:16:09');
INSERT INTO `hpp_parts_coating` VALUES (1, 42, 'Laminating Biasa', 0.1650, 473.550000, 473.5500, '', '', '2026-06-20 11:48:43', '', '2026-07-02 10:19:24');
INSERT INTO `hpp_parts_coating` VALUES (45, 43, 'Laminating Biasa', 0.1250, 467.250000, 116.8125, '', '_system', '2026-06-27 10:43:00', '_system', '2026-06-27 11:03:15');
INSERT INTO `hpp_parts_coating` VALUES (44, 44, 'Laminating Biasa', 0.1250, 467.250000, 116.8125, '', '_system', '2026-06-27 11:20:17', '_system', '2026-06-27 11:20:17');
INSERT INTO `hpp_parts_coating` VALUES (46, 48, 'Laminating Biasa', 0.1250, 467.250000, 116.8125, '', '_system', '2026-07-03 09:10:59', '_system', '2026-07-03 09:31:08');
INSERT INTO `hpp_parts_coating` VALUES (51, 50, 'UV', 0.0650, 202.800000, 101.4000, 'Auto-insert default DC+SF/DC+SHEET', NULL, '2026-07-07 08:09:10', '_system', '2026-07-07 08:11:19');

-- ----------------------------
-- Table structure for m_coating_rate
-- ----------------------------
DROP TABLE IF EXISTS `m_coating_rate`;
CREATE TABLE `m_coating_rate`  (
  `id_coating` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `coating_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Tipe coating: VARNISH, WATERBASE, UV, UV_SPOT, LAM_BIASA, LAM_DOFF, LAM_3D, OTHER.',
  `description` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Keterangan lengkap jenis coating untuk tampilan di form',
  `index_coating` decimal(20, 4) UNSIGNED NULL DEFAULT NULL COMMENT 'Index/rate coating. cost_per_lc = uk_p * uk_l * index_coating. cost_per_pc = cost_per_lc / i_isi_per_lb.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Status aktif: 1 = aktif, 0 = nonaktif',
  `notes` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan tambahan tipe coating ini.',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat master.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp saat master dibuat.',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir edit master.',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp terakhir update master.',
  PRIMARY KEY (`id_coating`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master coating type & index. Formula: cost = uk_p * uk_l * index_coating.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_coating_rate
-- ----------------------------
INSERT INTO `m_coating_rate` VALUES (1, 'Varnish', 'Varnish / Oil Based', 0.0350, 1, 'index Rp/cm². cost_pc = p×l×0.035/isi/cut', 'system', '2026-05-13 12:47:28', 'system', '2026-06-06 14:23:43');
INSERT INTO `m_coating_rate` VALUES (2, 'Water Base', 'Water Based Coating', 0.0350, 1, 'index Rp/cm². cost_pc = p×l×0.035/isi/cut', 'system', '2026-05-13 12:47:28', 'system', '2026-06-06 14:23:43');
INSERT INTO `m_coating_rate` VALUES (3, 'UV', 'Ultra Violet Coating', 0.0200, 1, 'index Rp/cm². Verified dari Excel NKC135. cost_pc = p×l×0.020/isi/cut', 'system', '2026-05-13 12:47:28', 'system', '2026-06-06 14:23:43');
INSERT INTO `m_coating_rate` VALUES (4, 'UV-Spot', 'UV Spot / Selective UV', 0.0250, 1, 'index Rp/cm². UV parsial (spot). cost_pc = p×l×0.025/isi/cut. Estimasi, sesuaikan dengan supplier.', 'system', '2026-06-06 14:23:43', 'system', '2026-06-08 11:03:43');
INSERT INTO `m_coating_rate` VALUES (5, 'Laminating Biasa', 'Jenis normal biasa', 0.1650, 1, 'index Rp/cm² — cost_pc = p×l×index/isi/cut', 'system', '2026-06-06 13:49:45', 'system', '2026-06-06 14:23:43');
INSERT INTO `m_coating_rate` VALUES (6, 'Laminating Glossy', 'Laminating Glossy / Biasa', 0.1650, 1, 'index Rp/cm². Verified dari Excel NS40 J33 formula: B33×p×l×0.165/isi. cost_pc = p×l×0.165/isi/cut.', 'system', '2026-06-06 14:23:43', 'system', '2026-06-08 11:03:43');
INSERT INTO `m_coating_rate` VALUES (7, 'Laminating Doff', 'Laminating Doff / Matte', 0.2100, 1, 'index Rp/cm². Verified dari Excel J34 formula. cost_pc = p×l×0.21/isi/cut.', 'system', '2026-06-06 14:23:43', 'system', '2026-06-08 11:03:43');
INSERT INTO `m_coating_rate` VALUES (8, 'Laminating 3D', 'Laminating 3 Dimensi / Hologram', 0.2100, 1, 'index Rp/cm². Verified dari Excel J35 formula (sama dengan Doff). cost_pc = p×l×0.21/isi/cut.', 'system', '2026-06-06 14:23:43', 'system', '2026-06-08 11:03:43');
INSERT INTO `m_coating_rate` VALUES (9, 'Other', 'Jenis lain — index input manual', 0.0000, 1, 'index diisi manual di form. cost_pc = p×l×index/isi/cut', 'system', '2026-05-13 12:47:28', 'system', '2026-06-08 11:03:07');
INSERT INTO `m_coating_rate` VALUES (10, 'No Coating', 'Tidak Aada coating', 0.0000, 1, 'index Rp/cm² — cost_pc = p×l×index/isi/cut', 'system', '2026-06-06 13:52:50', 'system', '2026-06-08 11:03:09');

-- ----------------------------
-- Table structure for m_film_rate
-- ----------------------------
DROP TABLE IF EXISTS `m_film_rate`;
CREATE TABLE `m_film_rate`  (
  `code` tinyint UNSIGNED NOT NULL COMMENT 'PK = nilai film rate (55, 60, 45, 30). Code IS the rate value.',
  `label` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label tampilan di dropdown form.',
  `sort_order` tinyint UNSIGNED NULL DEFAULT 1 COMMENT 'Urutan tampil di dropdown.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Flag active lookup film rate.',
  PRIMARY KEY (`code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master film rate. 55=Normal, 60=Full Cetak+WB, 45=Ivory, 30=Bag bawah.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_film_rate
-- ----------------------------
INSERT INTO `m_film_rate` VALUES (30, '30 — Bag Bawah / Tutup Tipis', 4, 1);
INSERT INTO `m_film_rate` VALUES (45, '45 — Ivory / Kertas Biasa', 3, 1);
INSERT INTO `m_film_rate` VALUES (55, '55 — Normal', 1, 1);
INSERT INTO `m_film_rate` VALUES (60, '60 — Full Cetak + WB Lam', 2, 1);

-- ----------------------------
-- Table structure for m_flute_allowance
-- ----------------------------
DROP TABLE IF EXISTS `m_flute_allowance`;
CREATE TABLE `m_flute_allowance`  (
  `flute_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Jenis flute: B/F, C/F, BC/F. PK.',
  `leb_panjang_mm` decimal(6, 2) NOT NULL DEFAULT 0.00 COMMENT 'K: Lebihan Panjang (mm).',
  `leb_lebar_mm` decimal(6, 2) NOT NULL DEFAULT 0.00 COMMENT 'L: Lebihan Lebar (mm).',
  `leb_tinggi_mm` decimal(6, 2) NOT NULL DEFAULT 0.00 COMMENT 'M: Lebihan Tinggi (mm).',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=aktif.',
  PRIMARY KEY (`flute_type`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Konstanta lebihan flute utk derivasi geometri sheet (SHEET_DIGITAL).' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of m_flute_allowance
-- ----------------------------

-- ----------------------------
-- Table structure for m_hpp_calc_type
-- ----------------------------
DROP TABLE IF EXISTS `m_hpp_calc_type`;
CREATE TABLE `m_hpp_calc_type`  (
  `calc_type_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode: NEW, RECALC, REVISE.',
  `calc_type_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label dropdown UI (Bahasa Indonesia)',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Penjelasan alur bisnis untuk tipe ini',
  `requires_product_id` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = product_id wajib diisi di hpp header',
  `requires_mcard_id` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = mcard_id wajib diisi di hpp header',
  `allow_spec_change` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = user boleh ubah spec di hpp_parts',
  `update_mcard_on_confirm` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = update m_card_material/process saat HPP CONFIRMED',
  `sort_order` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Urutan tampil di dropdown.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif.',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat record.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp dibuat.',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir update.',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp terakhir update.',
  PRIMARY KEY (`calc_type_code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master jenis kalkulasi. NEW=Order baru, RECALC=Ulang, REVISE=Revisi.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_hpp_calc_type
-- ----------------------------
INSERT INTO `m_hpp_calc_type` VALUES ('NEW', 'Produk Baru', 'Produk pertama kali dihitung. Belum ada Master Card di sistem. HPP dibuat manual dari nol. product_id dan mcard_id boleh kosong. Setelah HPP CONFIRMED dan harga disetujui customer, sistem generate draft m_card baru (BOM+BOP). mcard_id disimpan ke hpp_parts.', 0, 0, 1, 0, 1, 1, 'system', '2026-05-13 17:16:23', 'system', '2026-05-13 17:16:23');
INSERT INTO `m_hpp_calc_type` VALUES ('RECALC', 'Hitung Ulang', 'Produk sudah ada di sistem dan sudah punya Master Card. Harga bahan baku atau ongkos berubah, tetapi spesifikasi produk tidak berubah. product_id dan mcard_id WAJIB diisi. hpp_parts auto-fill spec dari m_card existing. User hanya update field harga (grammage_id, i_ongkos_*). Setelah CONFIRMED, m_card TIDAK berubah — hanya hpp.price_per_pcs yang diupdate.', 1, 1, 0, 0, 2, 1, 'system', '2026-05-13 17:16:23', 'system', '2026-05-13 17:16:23');
INSERT INTO `m_hpp_calc_type` VALUES ('REVISE', 'Revisi Produk', 'Produk sudah ada di sistem, tetapi spesifikasi atau desain berubah (ukuran, material, warna, dll). product_id WAJIB. mcard_id diisi (versi lama sebagai referensi). User bebas ubah semua spec di hpp_parts. Setelah CONFIRMED, m_card_material dan m_card_process diupdate sesuai spec baru, dan mcard_rev di tmmst.m_card naik.', 1, 0, 1, 1, 3, 1, 'system', '2026-05-13 17:16:23', 'system', '2026-05-13 17:16:23');

-- ----------------------------
-- Table structure for m_hpp_constants
-- ----------------------------
DROP TABLE IF EXISTS `m_hpp_constants`;
CREATE TABLE `m_hpp_constants`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `constant_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Key unik untuk lookup di PHP, contoh: KNIFE_RATE_DC',
  `constant_group` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Grup logika: DUPLEX | TOOLING | FILM | PROCESS | OB',
  `constant_number` decimal(20, 4) NULL DEFAULT NULL COMMENT 'Nilai numerik konstanta',
  `constant_string` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nilai string (jika diperlukan)',
  `uom` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Satuan: Rp/order, cm2, lembar, dst',
  `remark` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Penjelasan konteks & cara pakai konstanta ini',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat master konstanta.',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir update.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp dibuat.',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp terakhir update.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_constant_key`(`constant_key` ASC) USING BTREE,
  INDEX `idx_constant_group`(`constant_group` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 18 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master konstanta sistem. PLANO_REF_AREA=8611, RIM_SHEETS=500, dll.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_hpp_constants
-- ----------------------------
INSERT INTO `m_hpp_constants` VALUES (1, 'PLANO_REF_AREA', 'DUPLEX', 8611.0000, NULL, 'cm²', '79cm × 109cm = plano standar referensi duplex. Dipakai sebagai pembagi normalisasi harga.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (2, 'RIM_SHEETS', 'DUPLEX', 500.0000, NULL, 'lembar', 'Jumlah lembar per rim cetak. Pembagi untuk konversi ongkos_cetak_rim → per lembar.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (3, 'FILM_RATE_DEFAULT', 'FILM', 55.0000, NULL, NULL, 'Konstanta film rate untuk DC normal (cetak 1-4 warna, tanpa WB laminasi penuh).', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (4, 'FILM_RATE_FULLPRINT', 'FILM', 60.0000, NULL, NULL, 'Konstanta film rate untuk DC cetak penuh + WB laminasi. Pilih di form jika kondisi ini terpenuhi.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (5, 'FILM_FACTOR_DEFAULT', 'FILM', 0.2500, NULL, NULL, 'Film factor default (0.25). Bisa di-override per part jika spesifikasi berbeda.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (6, 'KNIFE_RATE_DC', 'TOOLING', 900000.0000, NULL, 'Rp/order', 'Biaya pisau/cetakan per order untuk tipe DC (Duplex Carton). Diamortisasi per qty.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (7, 'KNIFE_RATE_KRAFT', 'TOOLING', 1500000.0000, NULL, 'Rp/order', 'Biaya pisau per order untuk tipe KRAFT. Lebih mahal karena pisau kraft lebih besar/tebal.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (8, 'KNIFE_RATE_OB', 'TOOLING', 0.0000, NULL, 'Rp/order', 'OB/SHEET tidak menggunakan pisau die-cut → 0. Diset otomatis saat part_type = SHEET.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (9, 'KNIFE_FACTOR_DEFAULT', 'TOOLING', 0.2500, NULL, NULL, 'Knife factor default untuk DC dan KRAFT. OB wajib 0. Bisa di-override per part.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (10, 'PLATE_PER_COLOR', 'TOOLING', 90000.0000, NULL, 'Rp/warna', 'Biaya plate cetak per warna. Dipakai: plate_per_color × num_colors ÷ qty ÷ pcs.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (11, 'PCS_PER_LB_DEFAULT', 'PROCESS', 1.0000, NULL, 'PC/LB', 'Default isi per LB jika user tidak isi. Minimal 1. Dipakai untuk konversi input_plong_rp_lb → Rp/PC.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (12, 'CUT_PER_PLANO_DEFAULT', 'PROCESS', 1.0000, NULL, 'cut', 'Default potongan per plano jika user tidak isi. Minimal 1.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (13, 'OB_DISCOUNT_RM_MIN', 'OB', -0.9900, NULL, NULL, 'Batas minimum discount_rm yang diizinkan (-0.99 = surcharge maks 99%). Guard validasi.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (14, 'OB_DISCOUNT_RM_MAX', 'OB', 0.9900, NULL, NULL, 'Batas maksimum discount_rm yang diizinkan (0.99 = diskon maks 99%). Guard validasi.', 1, NULL, NULL, '2026-05-11 14:40:02', '2026-05-11 14:40:02');
INSERT INTO `m_hpp_constants` VALUES (15, 'KALENDER_ONLY', 'PROCESS', NULL, 'KALENDER', NULL, 'Part type khusus kalender dinding. Muncul blok KLEP (Mika KLEP + Ongkos KLEP). Hanya aktif jika part_name mengandung kata ini atau part_type = KALENDER.', 1, NULL, NULL, '2026-06-04 21:47:10', '2026-06-04 21:47:10');
INSERT INTO `m_hpp_constants` VALUES (16, 'KLEP_DEFAULT_MIKA_PC', 'PROCESS', 170.0000, NULL, 'Rp/PC', 'Default biaya Mika KLEP per PC untuk kalender dinding (dari KALENDER_MAJOIN_46X64). User bisa override.', 1, NULL, NULL, '2026-06-04 21:47:10', '2026-06-04 21:47:10');
INSERT INTO `m_hpp_constants` VALUES (17, 'KLEP_DEFAULT_ONGKOS_PC', 'PROCESS', 100.0000, NULL, 'Rp/PC', 'Default ongkos pasang KLEP per PC untuk kalender dinding (dari KALENDER_MAJOIN_46X64). User bisa override.', 1, NULL, NULL, '2026-06-04 21:47:10', '2026-06-04 21:47:10');

-- ----------------------------
-- Table structure for m_join_type
-- ----------------------------
DROP TABLE IF EXISTS `m_join_type`;
CREATE TABLE `m_join_type`  (
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PK kode jenis join: STITCHING, GLUE, COMBI.',
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label tampilan di dropdown.',
  `default_rate` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Default ongkos join Rp/PC. Overridable per order via hpp_parts.i_ongkos_join_pc.',
  `sort_order` tinyint UNSIGNED NULL DEFAULT 1 COMMENT 'Urutan tampil di dropdown.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Flag active lookup join corrugated.',
  PRIMARY KEY (`code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master jenis sambungan box corrugated. Khusus part_type SHEET.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_join_type
-- ----------------------------
INSERT INTO `m_join_type` VALUES ('GLUE_DOUBLE', 'Glue Double — lem 2 lapis', 500.0000, 4, 1);
INSERT INTO `m_join_type` VALUES ('GLUE_SINGLE', 'Glue Single — lem 1 lapis', 250.0000, 3, 1);
INSERT INTO `m_join_type` VALUES ('STITCHING_DOUBLE', 'Stitching Double — kawat 2 lapis', 750.0000, 2, 1);
INSERT INTO `m_join_type` VALUES ('STITCHING_SINGLE', 'Stitching Single — kawat 1 lapis', 375.0000, 1, 1);

-- ----------------------------
-- Table structure for m_packing_type
-- ----------------------------
DROP TABLE IF EXISTS `m_packing_type`;
CREATE TABLE `m_packing_type`  (
  `id_packing` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode: NONE | WRAP | SHRINK | WRAP_SHRINK.',
  `description` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Keterangan jenis packing untuk tampilan form.',
  `uom` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'PC' COMMENT 'Satuan rate. Default PC.',
  `default_rate` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Default rate Rp per UOM. Overridable per order via hpp_parts.i_ongkos_packing_pc.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = aktif, 0 = nonaktif.',
  `sort_order` tinyint UNSIGNED NULL DEFAULT 1 COMMENT 'Urutan tampil dropdown.',
  `notes` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan.',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_packing`) USING BTREE,
  UNIQUE INDEX `uk_packing_code`(`code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master jenis packing. Rate overridable per order.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_packing_type
-- ----------------------------
INSERT INTO `m_packing_type` VALUES (1, 'NONE', 'Tanpa packing tambahan', 'PC', 0.0000, 1, 1, 'Default, tidak ada biaya packing.', 'system', '2026-06-10 12:12:06', NULL, '2026-06-10 12:12:06');
INSERT INTO `m_packing_type` VALUES (2, 'WRAP', 'Wrapping (plastik / OPP)', 'PC', 0.0000, 1, 2, 'Rate diisi per order sesuai negosiasi.', 'system', '2026-06-10 12:12:06', NULL, '2026-06-10 12:12:06');
INSERT INTO `m_packing_type` VALUES (3, 'SHRINK', 'Shrink film', 'PC', 0.0000, 1, 3, 'Rate diisi per order.', 'system', '2026-06-10 12:12:06', NULL, '2026-06-10 12:12:06');
INSERT INTO `m_packing_type` VALUES (4, 'WRAP_SHRINK', 'Wrap + Shrink kombinasi', 'PC', 0.0000, 1, 4, 'Rate diisi per order.', 'system', '2026-06-10 12:12:06', NULL, '2026-06-10 12:12:06');

-- ----------------------------
-- Table structure for m_paper_grammage
-- ----------------------------
DROP TABLE IF EXISTS `m_paper_grammage`;
CREATE TABLE `m_paper_grammage`  (
  `id_cons_grammage` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `paper_type` enum('IVORY','ART','DUPLEX','HVS','KRAFT','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DUPLEX' COMMENT 'Tipe kertas: DUPLEX, IVORY, ART, KRAFT, HVS, OTHER.',
  `grammage` int NULL DEFAULT NULL COMMENT 'Gramatur kertas dalam gsm (g/m2).',
  `ivory_ap_price_per_kg` decimal(15, 2) NULL DEFAULT NULL COMMENT 'Harga Ivory / Art Paper per kg — diisi jika paper_type = IVORY atau ART',
  `duplex_price_list` int UNSIGNED NULL DEFAULT NULL COMMENT 'Harga per RIM untuk DUPLEX (Rp integer). Excel E_row.',
  `duplex_dc_percentage` int UNSIGNED NULL DEFAULT NULL COMMENT 'DC% disimpan x100. Contoh 530 = 5.30%. Excel D10.',
  `effective_date` date NOT NULL DEFAULT '2000-01-01' COMMENT 'Tanggal harga mulai berlaku. Gunakan tanggal HPP untuk lookup harga aktif.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Status aktif: 1 = aktif ditampilkan, 0 = nonaktif / dihapus sementara',
  PRIMARY KEY (`id_cons_grammage`) USING BTREE,
  INDEX `idx_grammage_type`(`paper_type` ASC, `is_active` ASC) USING BTREE,
  INDEX `idx_grammage_effective`(`paper_type` ASC, `grammage` ASC, `effective_date` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 29 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master gramatur kertas. Harga per rim (duplex) atau per kg (ivory/kraft).' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_paper_grammage
-- ----------------------------
INSERT INTO `m_paper_grammage` VALUES (1, 'IVORY', 85, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (2, 'IVORY', 100, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (3, 'IVORY', 120, 15500.00, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (4, 'IVORY', 150, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (5, 'IVORY', 200, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (6, 'IVORY', 210, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (7, 'IVORY', 230, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (8, 'DUPLEX', 250, NULL, 255000, 530, '2026-03-01', 1);
INSERT INTO `m_paper_grammage` VALUES (9, 'DUPLEX', 270, NULL, 267000, 530, '2026-03-01', 1);
INSERT INTO `m_paper_grammage` VALUES (10, 'DUPLEX', 310, NULL, 290000, 530, '2026-03-01', 1);
INSERT INTO `m_paper_grammage` VALUES (11, 'DUPLEX', 350, NULL, 314000, 530, '2026-03-01', 1);
INSERT INTO `m_paper_grammage` VALUES (12, 'DUPLEX', 400, NULL, 353000, 530, '2026-03-01', 1);
INSERT INTO `m_paper_grammage` VALUES (13, 'DUPLEX', 450, NULL, 395000, 530, '2026-03-01', 1);
INSERT INTO `m_paper_grammage` VALUES (14, 'DUPLEX', 500, NULL, 439000, 530, '2026-03-01', 1);
INSERT INTO `m_paper_grammage` VALUES (15, 'KRAFT', 85, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (16, 'KRAFT', 100, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (17, 'KRAFT', 120, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (18, 'KRAFT', 150, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (19, 'KRAFT', 200, NULL, NULL, NULL, '2000-01-01', 1);
INSERT INTO `m_paper_grammage` VALUES (20, 'DUPLEX', 250, NULL, 253000, 530, '2026-04-01', 1);
INSERT INTO `m_paper_grammage` VALUES (21, 'DUPLEX', 270, NULL, 267000, 530, '2026-04-01', 1);
INSERT INTO `m_paper_grammage` VALUES (22, 'DUPLEX', 310, NULL, 287000, 530, '2026-04-01', 1);
INSERT INTO `m_paper_grammage` VALUES (23, 'DUPLEX', 350, NULL, 311000, 530, '2026-04-01', 1);
INSERT INTO `m_paper_grammage` VALUES (24, 'DUPLEX', 400, NULL, 350000, 530, '2026-04-01', 1);
INSERT INTO `m_paper_grammage` VALUES (25, 'DUPLEX', 450, NULL, 395000, 530, '2026-04-01', 1);
INSERT INTO `m_paper_grammage` VALUES (26, 'DUPLEX', 500, NULL, 435000, 530, '2026-04-01', 1);
INSERT INTO `m_paper_grammage` VALUES (27, 'KRAFT', 250, 6700.00, NULL, NULL, '2025-08-01', 1);
INSERT INTO `m_paper_grammage` VALUES (28, 'KRAFT', 250, 8350.00, NULL, NULL, '2026-04-01', 1);

-- ----------------------------
-- Table structure for m_part_template
-- ----------------------------
DROP TABLE IF EXISTS `m_part_template`;
CREATE TABLE `m_part_template`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `template_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode trigger template. Dicocokkan dengan kata pertama part_name yang diketik user.',
  `part_name_hint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hint nama part lengkap. Contoh: NS40 DC 200X134X247MM Bag Atas.',
  `part_no` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Default no_urut part. Contoh: 1=badan, 2=tutup.',
  `part_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Label tampilan template di dropdown.',
  `part_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipe part: DC, DC+SF, DC+SHEET, KRAFT, SHEET.',
  `gramatur_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'Default FK ke m_paper_grammage.',
  `i_dc_persen_override` decimal(5, 2) NULL DEFAULT NULL COMMENT 'Default override DC%. NULL = ambil dari m_paper_grammage.',
  `i_panjang_plano_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Default panjang plano (cm).',
  `i_lebar_plano_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Default lebar plano (cm).',
  `i_cut_per_plano` decimal(8, 4) NULL DEFAULT 1.0000 COMMENT 'Default jumlah potongan per plano (B19 Excel).',
  `i_isi_per_lb` decimal(8, 4) NULL DEFAULT 1.0000 COMMENT 'Default isi per lembar bahan (B20 Excel).',
  `c_uk_cetak_panjang_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Default ukuran cetak panjang (cm).',
  `c_uk_cetak_lebar_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Default ukuran cetak lebar (cm).',
  `i_jumlah_warna` tinyint NULL DEFAULT 4 COMMENT 'Default jumlah warna cetak (plate count).',
  `film_rate_id` int NULL DEFAULT 55 COMMENT 'Default FK ke m_film_rate (55/60/45/30).',
  `i_film_faktor` decimal(8, 4) NULL DEFAULT NULL COMMENT 'Default film amortization factor. 0.25 order baru.',
  `i_ongkos_plate_warna` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Default biaya plate per warna (Rp). 90000.',
  `i_pisau_faktor` decimal(8, 4) NULL DEFAULT NULL COMMENT 'Default knife amortization factor. 0.25.',
  `i_pisau_rate_rp` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Default rate pisau per order (Rp).',
  `i_ongkos_cetak_rim` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Default ongkos cetak per RIM (Rp). Excel B24.',
  `i_ongkos_plong_lb` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Default rate plong per LB (Rp). Excel B25.',
  `i_ongkos_lem_pc` decimal(18, 4) NULL DEFAULT NULL COMMENT 'Default rate lem FLAT per PC (Rp). Excel B26.',
  `i_ongkos_kupas_pc` decimal(18, 4) NULL DEFAULT 0.0000 COMMENT 'Default ongkos kupas per PC (Rp).',
  `i_ongkos_qc_pc` decimal(18, 4) NULL DEFAULT 0.0000 COMMENT 'Default ongkos QC per PC (Rp).',
  `i_ongkos_kirim_pc` decimal(18, 4) NULL DEFAULT 0.0000 COMMENT 'Default ongkos kirim per PC (Rp).',
  `i_ongkos_hotprint_pc` decimal(18, 4) NULL DEFAULT 0.0000 COMMENT 'Default ongkos hotprint per PC (Rp).',
  `i_ongkos_print_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ongkos print IC (SHEET type)',
  `i_ongkos_diecut_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ongkos diecut corrugated',
  `i_ongkos_slitter_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ongkos slitter',
  `i_ongkos_slotter_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ongkos slotter',
  `i_ongkos_join_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ongkos join (stitching/glue)',
  `i_ongkos_partisi_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ongkos partisi',
  `i_ongkos_layer_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Ongkos layer',
  `i_ongkos_pisau_pc` decimal(18, 4) NOT NULL DEFAULT 0.0000 COMMENT 'SHEET: default ongkos pisau diecut per PC (Excel OB_A1 C29).',
  `i_ongkos_laminasi_pc` decimal(18, 4) NULL DEFAULT 0.0000 COMMENT 'Default ongkos proses laminasi per PC (Rp).',
  `i_lam_sheet_flat_pc` decimal(18, 4) NOT NULL DEFAULT 1360.0000,
  `i_ongkos_lam_rp_cm2_sh` decimal(8, 4) NULL DEFAULT NULL COMMENT 'Default rate ongkos laminasi sheet per cm² untuk template ini. Di-copy ke hpp_parts saat load template.',
  `i_ongkos_lam_rp_cm2_sf` decimal(8, 4) NULL DEFAULT NULL COMMENT 'Default rate ongkos laminasi SF per cm² untuk template ini. Di-copy ke hpp_parts saat load template.',
  `sheet_rate_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'Default FK ke m_sheet_rate.',
  `i_sheet_panjang_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Default panjang piece SF/SH (cm).',
  `i_sheet_lebar_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Default lebar piece SF (cm).',
  `i_lebar_trim_cm` decimal(12, 4) NULL DEFAULT NULL COMMENT 'Default lebar trim (cm).',
  `i_isi_per_trim` smallint NULL DEFAULT 1 COMMENT 'Default isi per trim (ISI/TRIM).',
  `i_discount_rm` decimal(5, 4) NULL DEFAULT 0.0000 COMMENT 'Default discount/surcharge sheet. Negatif = surcharge.',
  `supplier_sheet_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Default FK ke m_sheet_supplier.',
  `is_active` tinyint NULL DEFAULT 1 COMMENT '1=aktif tampil di dropdown.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp dibuat.',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang membuat record',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang terakhir update',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp terakhir update',
  `qty_order_uat` int UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Qty default untuk UAT/testing per produk',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_template_code`(`template_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master template default values per part. Auto-fill hpp_parts.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_part_template
-- ----------------------------
INSERT INTO `m_part_template` VALUES (1, 'NS40', 'NS40 DC 200X134X247MM', 1, 'Bag Atas', 'DC+SHEET', 23, 5.30, 70.0000, 100.0000, 2.0000, 1.0000, 70.0000, 41.0000, 4, 55, 0.2500, 90000.0000, 0.2500, 900000.0000, 900000.0000, 250.0000, 375.0000, 30.0000, 100.0000, 200.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 430.5000, 1360.0000, 0.1500, 0.1000, 2, 70.0000, 39.5000, 160.0000, 4, -0.1000, 'UJK', 1, '2026-05-18 22:04:38', '_test', '_test', '2026-07-01 14:52:51', 1000);
INSERT INTO `m_part_template` VALUES (2, 'NKC135-BADAN', 'NKC135 DC 509X186X255MM - Bag Badan', 1, 'Bag Atas', 'DC+SHEET', 24, 5.00, 79.0000, 48.0000, 1.0000, 1.0000, 75.0000, 46.5000, 4, 55, 0.2500, 90000.0000, 0.2500, 900000.0000, 400000.0000, 200.0000, 650.0000, 400.0000, 100.0000, 300.0000, 700.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 505.0500, 1360.0000, 0.0000, 0.0000, 2, 74.0000, 45.5000, 140.0000, 3, -0.0500, 'UJK', 1, '2026-05-20 07:00:00', '_test', '_test', '2026-07-01 14:53:08', 1000);
INSERT INTO `m_part_template` VALUES (3, 'NKC135-TUTUP', 'NKC135 DC 509X186X255MM - Bag Tutup', 2, 'Bag Bawah', 'DC+SHEET', 24, 5.00, 79.0000, 61.0000, 1.0000, 1.0000, 75.0000, 59.0000, 4, 55, 0.2500, 90000.0000, 0.2500, 900000.0000, 400000.0000, 200.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 309.7500, 1360.0000, 0.0000, 0.0000, 2, 74.0000, 0.0000, 182.0000, 3, -0.0500, 'UJK', 1, '2026-05-20 07:00:00', '_test', '_test', '2026-07-01 14:53:09', 1000);
INSERT INTO `m_part_template` VALUES (4, 'OB_A1', 'OB A1 240X260X280MM', 1, 'Outer Box', 'SHEET', 0, 0.00, 0.0000, 0.0000, 1.0000, 1.0000, 0.0000, 0.0000, 0, 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 100.0000, 100.0000, 0.0000, 300.0000, 350.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 16, 126.9000, 50.2000, 155.0000, 3, 0.0350, 'UJK', 1, '2026-05-20 07:00:00', '_test', '_test', '2026-07-01 14:52:52', 720);
INSERT INTO `m_part_template` VALUES (5, 'KALENDER', 'KALENDER MAJOIN 46X64', 1, 'Bag Atas', 'DC', 8, 0.00, 65.0000, 100.0000, 1.0000, 1.0000, 65.0000, 100.0000, 4, 0, 0.0000, 90000.0000, 0.0000, 0.0000, 750000.0000, 150.0000, 0.0000, 0.0000, 50.0000, 50.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0, 0.0000, 0.0000, 0.0000, 1, 0.0000, 'UJK', 1, '2026-06-24 09:27:15', '_test', '_test', '2026-07-01 14:53:10', 550);
INSERT INTO `m_part_template` VALUES (6, 'FS GTZ-5S', 'FS GTZ-5S MF TRIAL', 1, 'Bag Atas', 'DC+SF', 20, 0.00, 89.0000, 125.0000, 3.0000, 4.0000, 89.0000, 42.0000, 4, 55, 0.0500, 90000.0000, 0.0500, 900000.0000, 1500000.0000, 250.0000, 60.0000, 20.0000, 20.0000, 20.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0750, 0.0650, 2, 88.5000, 41.0000, 170.0000, 4, -0.0500, 'UJK', 1, '2026-06-27 10:41:37', '_test', '_test', '2026-07-03 10:44:30', 3000);

-- ----------------------------
-- Table structure for m_part_type
-- ----------------------------
DROP TABLE IF EXISTS `m_part_type`;
CREATE TABLE `m_part_type`  (
  `part_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PK kode part: DC, DC+SF, DC+SHEET, KRAFT, SHEET.',
  `part_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label tampilan part type.',
  `urutan` tinyint UNSIGNED NULL DEFAULT 1 COMMENT 'Urutan tampil di dropdown.',
  `pisau_rate_rp` decimal(15, 2) NULL DEFAULT NULL COMMENT 'Default rate pisau per order. DC=900000, KRAFT=1500000, SHEET=0.',
  `pakai_laminasi` tinyint(1) NOT NULL DEFAULT 0,
  `default_template_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'FK ke m_part_template.id. Template default saat user pilih tipe part langsung (tanpa pilih part_name).',
  `is_active` tinyint UNSIGNED NULL DEFAULT 1 COMMENT '1=aktif.',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan tambahan jenis part.',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User pembuat master.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp saat master dibuat.',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir edit.',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp terakhir update.',
  PRIMARY KEY (`part_code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master tipe part. DC, DC+SF, DC+SHEET, KRAFT, SHEET.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_part_type
-- ----------------------------
INSERT INTO `m_part_type` VALUES ('DC', 'DC — Duplex Carton', 3, 900000.00, 0, 5, 1, 'Pisau default 900rb. Override per order via hpp_parts.i_pisau_rate_rp. Masa depan bisa variasi per size/vendor.', NULL, '2026-05-13 12:51:31', NULL, '2026-06-27 13:23:31');
INSERT INTO `m_part_type` VALUES ('DC+SF', 'DC + Single Face', 1, 900000.00, 1, 6, 1, 'Pisau default 900rb. Override per order via hpp_parts.i_pisau_rate_rp. Masa depan bisa variasi per size/vendor.', NULL, '2026-05-13 12:51:31', NULL, '2026-06-27 13:23:31');
INSERT INTO `m_part_type` VALUES ('DC+SHEET', 'DC + Laminasi Sheet', 2, 900000.00, 1, 1, 1, 'Pisau default 900rb. Override per order via hpp_parts.i_pisau_rate_rp. Masa depan bisa variasi per size/vendor.', NULL, '2026-05-13 12:51:31', NULL, '2026-06-27 13:23:31');
INSERT INTO `m_part_type` VALUES ('KRAFT', 'KRAFT — Kraft Box', 4, 1500000.00, 0, NULL, 1, 'Pisau default 1.5jt. Override per order via hpp_parts.i_pisau_rate_rp. Lebih mahal karena ukuran/ketebalan kraft lebih besar.', NULL, '2026-05-13 12:51:31', NULL, '2026-06-04 21:47:10');
INSERT INTO `m_part_type` VALUES ('SHEET', 'SHEET — Outer Box/OB', 5, 0.00, 0, 4, 1, 'SHEET/OB tidak pakai pisau die-cut. i_pisau_rate_rp = 0.', NULL, '2026-05-13 12:51:31', NULL, '2026-06-27 13:23:31');
INSERT INTO `m_part_type` VALUES ('SHEET_DIGITAL', 'SHEET DIGITAL — OB Digital', 6, 0.00, 0, 4, 1, 'Clone SHEET. Rumus = SHEET (v17 via $is_sheet). Isolasi utk cetak digital & bahan beda (future). Pisau die-cut = 0.', '_system', '2026-07-13 14:06:07', NULL, '2026-07-13 14:06:07');

-- ----------------------------
-- Table structure for m_routing_template
-- ----------------------------
DROP TABLE IF EXISTS `m_routing_template`;
CREATE TABLE `m_routing_template`  (
  `id_routing` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `seq` smallint UNSIGNED NOT NULL COMMENT 'Urutan proses. Pre-process diberi seq besar/khusus; lantai 1..N. (CATATAN: kolom UNSIGNED — seed pre-process dikonversi positif, lihat is_preprocess).',
  `proses_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama proses produksi.',
  `is_preprocess` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = pre-process/tooling (Film, Pisau, Plate). 0 = proses lantai.',
  `applies_to` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'ALL' COMMENT 'Part type berlaku: ALL atau CSV dari DC,DC+SF,DC+SHEET,KRAFT,SHEET.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = aktif.',
  `notes` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Catatan.',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_routing`) USING BTREE,
  INDEX `idx_seq`(`seq` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master routing produksi (Output #3). Sequence FIX dari Diagram_urutan.jpg.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_routing_template
-- ----------------------------
INSERT INTO `m_routing_template` VALUES (1, 1, 'Film', 1, 'ALL', 1, 'Pre-process: dasar pembuatan pisau & plate.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (2, 2, 'Pisau / Matras', 1, 'ALL', 1, 'Pre-process: dipakai di PLONG.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (3, 3, 'Plate', 1, 'ALL', 1, 'Pre-process: per warna/sisi, dipakai di CETAK.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (4, 11, 'Belah Plano', 0, 'DC,DC+SF,DC+SHEET,KRAFT', 1, 'Potong plano jadi lembar cetak.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (5, 12, 'Cetak', 0, 'DC,DC+SF,DC+SHEET,KRAFT', 1, 'Pakai plate.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (6, 13, 'Coating', 0, 'ALL', 1, 'WB / UV / spot. Multi-layer & kombinasi.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (7, 14, 'Laminasi', 0, 'DC+SF,DC+SHEET', 1, 'Rekat DC + sheet/SF.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (8, 15, 'Plong (Diecut)', 0, 'DC,DC+SF,DC+SHEET,KRAFT', 1, 'Pakai pisau/matras.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (9, 16, 'Kupas', 0, 'DC,DC+SF,DC+SHEET,KRAFT', 1, 'Stripping.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (10, 17, 'Lem', 0, 'DC,DC+SF,DC+SHEET,KRAFT', 1, 'Samping / bottom-lock / 4-corner / 6-corner.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (11, 18, 'QC', 0, 'ALL', 1, 'Quality control.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (12, 19, 'Packing', 0, 'ALL', 1, 'NONE/WRAP/SHRINK/WRAP_SHRINK.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');
INSERT INTO `m_routing_template` VALUES (13, 20, 'Kirim', 0, 'ALL', 1, 'Delivery.', 'system', '2026-06-10 12:16:44', NULL, '2026-06-10 12:16:44');

-- ----------------------------
-- Table structure for m_sheet_discount_tier
-- ----------------------------
DROP TABLE IF EXISTS `m_sheet_discount_tier`;
CREATE TABLE `m_sheet_discount_tier`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `supplier_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'FK ke m_sheet_supplier.supplier_code.',
  `part_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'FK m_part_type.part_code. NULL=berlaku semua tipe (perilaku lama). Diisi utk tier khusus 1 tipe (mis. SHEET_DIGITAL).',
  `sheet_rate_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK ke m_sheet_rate.doc_id. NULL = berlaku semua substance.',
  `rm_from` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Running meter minimum inklusif.',
  `rm_to` decimal(10, 2) NULL DEFAULT NULL COMMENT 'Running meter maksimum inklusif. NULL = tidak terbatas.',
  `discount_pct` decimal(6, 4) NOT NULL COMMENT 'Diskon: positif=disc, negatif=surcharge. Contoh: -0.10=surcharge 10%.',
  `effective_date` date NOT NULL DEFAULT '2000-01-01' COMMENT 'Tanggal mulai berlaku tier ini.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif.',
  `notes` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Catatan tier discount.',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_supplier_rate`(`supplier_code` ASC, `sheet_rate_id` ASC, `is_active` ASC) USING BTREE,
  INDEX `idx_supplier_rm`(`supplier_code` ASC, `rm_from` ASC, `rm_to` ASC) USING BTREE,
  INDEX `idx_parttype_rm`(`part_type` ASC, `supplier_code` ASC, `sheet_rate_id` ASC, `rm_from` ASC, `rm_to` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master tier discount sheet corrugated per volume order.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_sheet_discount_tier
-- ----------------------------
INSERT INTO `m_sheet_discount_tier` VALUES (1, 'UJK', NULL, 2, 0.00, NULL, -0.1000, '2026-01-01', 0, 'K125 flat -10% (Mar2026, deprecated)');
INSERT INTO `m_sheet_discount_tier` VALUES (2, 'UJK', NULL, 2, 0.00, NULL, -0.0500, '2026-04-01', 1, 'K125 flat -5% (Apr2026, aktif)');
INSERT INTO `m_sheet_discount_tier` VALUES (3, 'UJK', NULL, 16, 0.00, NULL, -0.1500, '2026-01-01', 0, 'WK150 flat -15% (Apr2026, deprecated)');
INSERT INTO `m_sheet_discount_tier` VALUES (4, 'UJK', NULL, 16, 0.00, NULL, -0.0650, '2026-11-01', 1, 'WK150 flat -6.5% (Nov2026, aktif)');
INSERT INTO `m_sheet_discount_tier` VALUES (5, 'UJK', NULL, NULL, 0.00, NULL, -0.1000, '2026-01-01', 1, 'UJK default fallback flat -10%');
INSERT INTO `m_sheet_discount_tier` VALUES (6, 'RCA', NULL, 2, 0.00, 299.99, 0.2000, '2026-01-01', 1, 'K125, <300RM disc 20%');
INSERT INTO `m_sheet_discount_tier` VALUES (7, 'RCA', NULL, 2, 300.00, 499.99, 0.2000, '2026-01-01', 1, 'K125, 300-499RM disc 20%');
INSERT INTO `m_sheet_discount_tier` VALUES (8, 'RCA', NULL, 2, 500.00, 999.99, 0.2500, '2026-01-01', 1, 'K125, 500-999RM disc 25%');
INSERT INTO `m_sheet_discount_tier` VALUES (9, 'RCA', NULL, 2, 1000.00, 9999999.00, 0.2600, '2026-01-01', 1, 'K125, >=1000RM disc 26%');
INSERT INTO `m_sheet_discount_tier` VALUES (10, 'RCA', NULL, 16, 0.00, NULL, 0.2250, '2026-01-01', 1, 'WK150, flat disc 22.5%');
INSERT INTO `m_sheet_discount_tier` VALUES (11, 'RCA', NULL, NULL, 0.00, 299.99, 0.2000, '2026-01-01', 1, 'RCA default, <300RM 20%');
INSERT INTO `m_sheet_discount_tier` VALUES (12, 'RCA', NULL, NULL, 300.00, 499.99, 0.2000, '2026-01-01', 1, 'RCA default, 300-499RM 20%');
INSERT INTO `m_sheet_discount_tier` VALUES (13, 'RCA', NULL, NULL, 500.00, 999.99, 0.2500, '2026-01-01', 1, 'RCA default, 500-999RM 25%');
INSERT INTO `m_sheet_discount_tier` VALUES (14, 'RCA', NULL, NULL, 1000.00, 9999999.00, 0.2600, '2026-01-01', 1, 'RCA default, >=1000RM 26%');

-- ----------------------------
-- Table structure for m_sheet_rate
-- ----------------------------
DROP TABLE IF EXISTS `m_sheet_rate`;
CREATE TABLE `m_sheet_rate`  (
  `doc_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `material_substance` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Substansi sheet: K125/M125/M125, WK150/M125/M125, dll.',
  `flute_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Tipe flute: B/F, C/F, BC/F, E/F.',
  `index_substance` decimal(10, 0) UNSIGNED NULL DEFAULT NULL COMMENT 'Harga sheet per m2 dalam Rp. Excel index_rp_m2.',
  `price_per_rim` decimal(15, 2) NULL DEFAULT NULL COMMENT 'Harga material per rim (saat ini index_substance, perlu konfirmasi satuan)',
  `mat_subtype` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sub-tipe material (Single Face / Sheet Corrugated).',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Status aktif: 1 = aktif, 0 = nonaktif / tidak dipakai',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User yang membuat record',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp pembuatan record',
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User terakhir update.',
  `updated_log` datetime NULL DEFAULT NULL COMMENT 'Waktu terakhir baris master diupdate',
  PRIMARY KEY (`doc_id`) USING BTREE,
  UNIQUE INDEX `substance_name`(`material_substance` ASC, `flute_type` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 108 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master harga sheet corrugated per m2 per substansi per supplier.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_sheet_rate
-- ----------------------------
INSERT INTO `m_sheet_rate` VALUES (1, 'M125/M125/M125', 'B/F', 4283, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:19');
INSERT INTO `m_sheet_rate` VALUES (2, 'K125/M125/M125', 'B/F', 4410, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:29');
INSERT INTO `m_sheet_rate` VALUES (3, 'K125/M125/K125', 'B/F', 4537, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:29');
INSERT INTO `m_sheet_rate` VALUES (4, 'K150/M125/M125', 'B/F', 4631, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:29');
INSERT INTO `m_sheet_rate` VALUES (5, 'K150/M125/K125', 'B/F', 4758, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:29');
INSERT INTO `m_sheet_rate` VALUES (6, 'K150/M125/K150', 'B/F', 4979, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:29');
INSERT INTO `m_sheet_rate` VALUES (7, 'K200/M125/M125', 'B/F', 5268, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (8, 'K200/M125/K125', 'B/F', 5400, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (9, 'K200/M125/K150', 'B/F', 5629, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (10, 'K200/M125/K200', 'B/F', 6088, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (11, 'K275/M125/M125', 'B/F', 6528, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (12, 'K275/M125/K125', 'B/F', 6666, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (13, 'K275/M125/K150', 'B/F', 6903, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (14, 'K275/M125/K200', 'B/F', 7379, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (15, 'K275/M125/K275', 'B/F', 8746, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (16, 'WK150/M125/M125', 'B/F', 5153, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (17, 'WK150/M125/K125', 'B/F', 5285, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (18, 'WK150/M125/K150', 'B/F', 5515, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:30');
INSERT INTO `m_sheet_rate` VALUES (19, 'WK150/M125/K200', 'B/F', 5973, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (20, 'WK150/M125/K275', 'B/F', 7260, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (21, 'WK150/M125/WK150', 'B/F', 6293, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (22, 'WK200/M125/M125', 'B/F', 5726, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (23, 'WK200/M125/K125', 'B/F', 5859, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (24, 'WK200/M125/K150', 'B/F', 6088, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (25, 'WK200/M125/K200', 'B/F', 6547, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (26, 'WK200/M125/K275', 'B/F', 7855, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (27, 'WK200/M125/WK150', 'B/F', 6908, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (28, 'WK200/M125/WK200', 'B/F', 7524, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (29, 'K150/M150/K150', 'B/F', 5279, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:31');
INSERT INTO `m_sheet_rate` VALUES (30, 'K200/M150/K150', 'B/F', 5929, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (31, 'K200/M150/K200', 'B/F', 6388, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (32, 'WK150/M150/K150', 'B/F', 5815, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (33, 'K275/M150/K275', 'B/F', 9046, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (34, 'S FACE 125 M / 125 M', 'B/F', 2850, NULL, 'SINGLE FACE', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (35, 'S FACE 125 M / 125 K', 'B/F', 2950, NULL, 'SINGLE FACE', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (36, 'S FACE 125 M / 150 K', 'B/F', 3200, NULL, 'SINGLE FACE', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (37, 'S FACE 125 M / 150 WK', 'B/F', 3450, NULL, 'SINGLE FACE', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (38, 'S FACE 150 M / 150 K', 'B/F', 3550, NULL, 'SINGLE FACE', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (39, 'M125/M125/M125', 'C/F', 4380, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (40, 'K125/M125/M125', 'C/F', 4508, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (41, 'K125/M125/K125', 'C/F', 4635, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:32');
INSERT INTO `m_sheet_rate` VALUES (42, 'K150/M125/M125', 'C/F', 4729, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (43, 'K150/M125/K125', 'C/F', 4856, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (44, 'K150/M125/K150', 'C/F', 5077, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (45, 'K200/M125/M125', 'C/F', 5369, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (46, 'K200/M125/K125', 'C/F', 5501, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (47, 'K200/M125/K150', 'C/F', 5731, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (48, 'K200/M125/K200', 'C/F', 6189, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (49, 'K275/M125/M125', 'C/F', 6634, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (50, 'K275/M125/K125', 'C/F', 6771, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (51, 'K275/M125/K150', 'C/F', 7009, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (52, 'K275/M125/K200', 'C/F', 7484, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:33');
INSERT INTO `m_sheet_rate` VALUES (53, 'K275/M125/K275', 'C/F', 8855, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:34');
INSERT INTO `m_sheet_rate` VALUES (54, 'WK150/M125/M125', 'C/F', 5254, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:34');
INSERT INTO `m_sheet_rate` VALUES (55, 'WK150/M125/K125', 'C/F', 5387, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:34');
INSERT INTO `m_sheet_rate` VALUES (56, 'WK150/M125/K150', 'C/F', 5616, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:34');
INSERT INTO `m_sheet_rate` VALUES (57, 'WK150/M125/K200', 'C/F', 6075, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (58, 'WK150/M125/K275', 'C/F', 7365, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (59, 'WK150/M125/WK150', 'C/F', 6402, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (60, 'WK200/M125/M125', 'C/F', 5828, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (61, 'WK200/M125/K125', 'C/F', 5960, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (62, 'WK200/M125/K150', 'C/F', 6189, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (63, 'WK200/M125/K200', 'C/F', 6648, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (64, 'WK200/M125/K275', 'C/F', 7960, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (65, 'WK200/M125/WK150', 'C/F', 7017, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (66, 'WK200/M125/WK200', 'C/F', 7633, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (67, 'K150/M150/K150', 'C/F', 5377, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (68, 'K200/M150/K200', 'C/F', 6489, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:35');
INSERT INTO `m_sheet_rate` VALUES (69, 'WK150/M150/K150', 'C/F', 5916, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (70, 'K275/M150/K275', 'C/F', 9155, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (71, 'M125/M125/M125', 'BC/F', 7252, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (72, 'K125/M125/M125', 'BC/F', 7379, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (73, 'K125/M125/K125', 'BC/F', 7507, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (74, 'K150/M125/M125', 'BC/F', 7600, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (75, 'K150/M125/K125', 'BC/F', 7728, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (76, 'K150/M125/K150', 'BC/F', 7948, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (77, 'K200/M125/M125', 'BC/F', 8351, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (78, 'K200/M125/K125', 'BC/F', 8483, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (79, 'K200/M125/K150', 'BC/F', 8713, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (80, 'K200/M125/K200', 'BC/F', 9171, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:36');
INSERT INTO `m_sheet_rate` VALUES (81, 'K275/M125/M125', 'BC/F', 9726, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (82, 'K275/M125/K125', 'BC/F', 9863, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (83, 'K275/M125/K150', 'BC/F', 10101, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (84, 'K275/M125/K200', 'BC/F', 10577, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (85, 'K275/M125/K275', 'BC/F', 12058, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (86, 'WK150/M125/M125', 'BC/F', 8236, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (87, 'WK150/M125/K125', 'BC/F', 8369, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (88, 'WK150/M125/K150', 'BC/F', 8598, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (89, 'WK150/M125/K200', 'BC/F', 9057, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (90, 'WK150/M125/K275', 'BC/F', 10458, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (91, 'WK150/M125/WK150', 'BC/F', 9605, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (92, 'WK200/M125/M125', 'BC/F', 8810, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (93, 'WK200/M125/K125', 'BC/F', 8942, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:37');
INSERT INTO `m_sheet_rate` VALUES (94, 'WK200/M125/K150', 'BC/F', 9171, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (95, 'WK200/M125/K200', 'BC/F', 9630, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (96, 'WK200/M125/K275', 'BC/F', 11053, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (97, 'WK200/M125/WK150', 'BC/F', 10220, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (98, 'WK200/M125/WK200', 'BC/F', 10836, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (99, 'K150/M150/K150', 'BC/F', 8548, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (100, 'K200/M150/K200', 'BC/F', 9771, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (101, 'WK150/M150/K150', 'BC/F', 9198, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (102, 'K275/M150/K275', 'BC/F', 12658, NULL, 'SHEET', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (103, 'SF M 125 / M 125', 'E/F', 2500, NULL, 'SF', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (104, 'SF M 125 / K 125', 'E/F', 2600, NULL, 'SF', 1, NULL, '2026-06-05 13:18:48', 'system_', '2026-05-05 15:12:38');
INSERT INTO `m_sheet_rate` VALUES (105, 'SF M 125 / K 150', 'E/F', 0, NULL, 'SF', 1, 'system_v6g', '2026-06-11 13:34:45', NULL, NULL);
INSERT INTO `m_sheet_rate` VALUES (106, 'SF M 125 / WK 150', 'E/F', 0, NULL, 'SF', 1, 'system_v6g', '2026-06-11 13:34:45', NULL, NULL);
INSERT INTO `m_sheet_rate` VALUES (107, 'SF BF M 125 / M 125', 'E/F', 0, NULL, 'SF', 1, 'system_v6g', '2026-06-11 13:34:45', NULL, NULL);

-- ----------------------------
-- Table structure for m_sheet_supplier
-- ----------------------------
DROP TABLE IF EXISTS `m_sheet_supplier`;
CREATE TABLE `m_sheet_supplier`  (
  `supplier_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode supplier. PK.',
  `supplier_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama lengkap supplier.',
  `machine_width_cm` decimal(8, 2) NOT NULL COMMENT 'Lebar mesin corrugator (cm). Basis hitung isi_per_trim.',
  `trim_allowance_cm` decimal(5, 2) NOT NULL DEFAULT 2.00 COMMENT 'Total gap trim per run (cm). UJK=2, RCA=5.',
  `sort_order` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Urutan tampil di dropdown.',
  `is_active` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif.',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Catatan supplier.',
  `created_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp dibuat.',
  `updated_log` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp terakhir update.',
  PRIMARY KEY (`supplier_code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Master supplier sheet corrugated. Lebar mesin & gap waste.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of m_sheet_supplier
-- ----------------------------
INSERT INTO `m_sheet_supplier` VALUES ('RCA', 'RCA', 215.00, 5.00, 2, 1, 'Mesin ~215cm. Disc: tiered by running meter.', '2026-05-16 09:30:40', '2026-05-16 09:30:40');
INSERT INTO `m_sheet_supplier` VALUES ('UJK', 'UJK', 170.00, 2.00, 1, 1, 'Mesin ~170cm. Disc: flat surcharge per substance.', '2026-05-16 09:30:40', '2026-05-16 09:30:40');

-- ----------------------------
-- Table structure for map_hpp
-- ----------------------------
DROP TABLE IF EXISTS `map_hpp`;
CREATE TABLE `map_hpp`  (
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `nama_excel` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Label di Excel costing. NULL jika tidak ada padanan.',
  `nama_field` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama kolom di tabel hpp.',
  `tipe_input` enum('RW','RO','FM','AUTO','SYS') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'RW=user input | RO=tampil saja | FM=hasil formula | AUTO=set sistem | SYS=tidak tampil.',
  `nilai_default` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nilai default saat record baru.',
  `tipe_data` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipe data MySQL.',
  `nama_block` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama block di form Scriptcase.',
  `visible_cond` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kondisi tampil. ALL=selalu | RECALC,REVISE=hanya jika calc_type tsb.',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Urutan tampil dalam block.',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Keterangan + formula jika FM.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_nama_field`(`nama_field` ASC) USING BTREE,
  INDEX `idx_block`(`nama_block` ASC) USING BTREE,
  INDEX `idx_tipe`(`tipe_input` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 35 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Dokumentasi pemetaan field hpp ke layout form_hpp.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of map_hpp
-- ----------------------------
INSERT INTO `map_hpp` VALUES (1, 'No Dokumen', 'doc_no', 'AUTO', NULL, 'VARCHAR(50)', 'Blk_Header', 'ALL', 1, 'AUTO — Format: HPP-YYYYMM-XXXX. Di-generate di onBeforeInsert dari sequence MAX(hpp_id)+1. Tidak boleh diedit user.');
INSERT INTO `map_hpp` VALUES (2, 'Tanggal', 'doc_date', 'RW', 'CURDATE()', 'DATE', 'Blk_Header', 'ALL', 2, 'Tanggal kalkulasi. Default hari ini. User bisa ubah untuk backdate.');
INSERT INTO `map_hpp` VALUES (3, 'Status', 'doc_status', 'AUTO', 'DRAFT', 'VARCHAR(30)', 'Blk_Header', 'ALL', 3, 'AUTO — Status dokumen HPP. DRAFT saat dibuat. Berubah ke CONFIRMED/CANCELLED via tombol action. Tidak diedit langsung di form.');
INSERT INTO `map_hpp` VALUES (4, 'Tipe Kalkulasi', 'i_calc_type', 'RW', 'NEW', 'VARCHAR(20)', 'Blk_Header', 'ALL', 4, 'Pilihan: NEW=Produk Baru | RECALC=Hitung Ulang | REVISE=Revisi Produk. FK lookup ke m_hpp_calc_type. Menentukan apakah product_id wajib diisi.');
INSERT INTO `map_hpp` VALUES (5, 'No Revisi', 'revision_no', 'AUTO', '0', 'INT', 'Blk_Header', 'ALL', 5, 'AUTO — Nomor revisi. Default 0 untuk dokumen baru. Naik otomatis saat REVISE di-confirm.');
INSERT INTO `map_hpp` VALUES (6, 'Customer', 'customer_id', 'RW', NULL, 'BIGINT UNSIGNED', 'Blk_Customer', 'ALL', 1, 'FK ke v1_m_customer. Dropdown existing customer. Bisa NULL untuk calon customer baru (isi customer_name manual). Ajax onChange: auto-fill customer_name.');
INSERT INTO `map_hpp` VALUES (7, 'Nama Customer', 'customer_name', 'RW', NULL, 'VARCHAR(255)', 'Blk_Customer', 'ALL', 2, 'Nama customer atau calon customer. Auto-fill dari m_customer.customer_name saat customer_id dipilih. Bisa diedit manual untuk calon customer baru.');
INSERT INTO `map_hpp` VALUES (8, 'Produk Existing', 'product_id', 'RW', NULL, 'BIGINT UNSIGNED', 'Blk_Product', 'RECALC,REVISE', 1, 'FK ke v1_m_product. WAJIB untuk RECALC dan REVISE. NULL untuk NEW. Ajax onChange: auto-fill product_name, customer_id, dimensi (cm→mm).');
INSERT INTO `map_hpp` VALUES (9, 'Nama Produk', 'product_name', 'RW', NULL, 'VARCHAR(255)', 'Blk_Product', 'ALL', 2, 'Nama produk atau custom product. Auto-fill dari m_product.product_name saat product_id dipilih. Diisi manual untuk NEW.');
INSERT INTO `map_hpp` VALUES (10, 'Konfigurasi Part', 'cfg_parts_id', 'RW', NULL, 'BIGINT UNSIGNED', 'Blk_Product', 'ALL', 3, 'FK ke v1_m_product_cfg_parts. Menentukan jumlah total parts yang akan dihitung. Dropdown: 1 Part, 2 Parts, dst.');
INSERT INTO `map_hpp` VALUES (11, 'Panjang (mm)', 'i_dim_panjang_mm', 'RW', '0', 'DECIMAL(8,2)', 'Blk_Dimensi', 'ALL', 1, 'Panjang produk jadi dalam mm (P di Master Card). Auto-fill dari m_product.cm_length×10 saat product_id dipilih. Diturunkan ke hpp_parts.i_dim_panjang_mm saat part dibuat.');
INSERT INTO `map_hpp` VALUES (12, 'Lebar (mm)', 'i_dim_lebar_mm', 'RW', '0', 'DECIMAL(8,2)', 'Blk_Dimensi', 'ALL', 2, 'Lebar produk jadi dalam mm (L di Master Card). Auto-fill dari m_product.cm_width×10.');
INSERT INTO `map_hpp` VALUES (13, 'Tinggi (mm)', 'i_dim_tinggi_mm', 'RW', '0', 'DECIMAL(8,2)', 'Blk_Dimensi', 'ALL', 3, 'Tinggi produk jadi dalam mm (T di Master Card). Auto-fill dari m_product.cm_height×10.');
INSERT INTO `map_hpp` VALUES (14, 'Qty Order (PCS)', 'qty_order_pcs', 'RW', '0', 'DECIMAL(18,2)', 'Blk_Parameter', 'ALL', 1, 'Qty order dalam PCS. WAJIB diisi. Basis semua formula kalkulasi di hpp_parts. Validate: > 0.');
INSERT INTO `map_hpp` VALUES (15, 'Include PPN', 'is_ppn', 'RW', '1', 'TINYINT(1)', 'Blk_Parameter', 'ALL', 2, 'Flag include PPN: 1=Ya, 0=Tidak. Checkbox. Menentukan apakah ppn_percent dipakai dalam total_after_ppn.');
INSERT INTO `map_hpp` VALUES (16, 'PPN (%)', 'ppn_percent', 'RW', '11', 'DECIMAL(5,2)', 'Blk_Parameter', 'ALL', 3, 'Persentase PPN. Default 11%. Aktif hanya jika is_ppn=1. Formula: total_after_ppn = total_after_margin × (1 + ppn_percent/100).');
INSERT INTO `map_hpp` VALUES (17, 'Margin (%)', 'margin_percent', 'RW', '0', 'DECIMAL(8,2)', 'Blk_Parameter', 'ALL', 4, 'Margin keuntungan dalam persen. Formula: total_after_margin = total_hpp × (1 + margin_percent/100).');
INSERT INTO `map_hpp` VALUES (18, 'Toleransi (%)', 'tolerance_percent', 'RW', '0', 'DECIMAL(8,2)', 'Blk_Parameter', 'ALL', 5, 'Toleransi produksi dalam persen. Ditambahkan ke total_hpp sebelum margin. Formula: total_hpp_tol = total_hpp × (1 + tolerance_percent/100).');
INSERT INTO `map_hpp` VALUES (19, 'Rounding', 'rounding_mode', 'RW', 'NEAREST', 'VARCHAR(30)', 'Blk_Parameter', 'ALL', 6, 'Mode pembulatan harga akhir. Pilihan: NEAREST=pembulatan terdekat | UP=selalu naik | DOWN=selalu turun. Diterapkan pada price_per_pcs.');
INSERT INTO `map_hpp` VALUES (20, 'Currency', 'currency_code', 'RW', 'IDR', 'VARCHAR(10)', 'Blk_Parameter', 'ALL', 7, 'Kode currency transaksi. Default IDR. FK ke v1_m_currency.');
INSERT INTO `map_hpp` VALUES (21, 'Catatan', 'notes', 'RW', NULL, 'TEXT', 'Blk_Notes', 'ALL', 1, 'Catatan bebas kalkulasi. Opsional.');
INSERT INTO `map_hpp` VALUES (22, 'Total Material', 'total_material_cost', 'FM', '0', 'DECIMAL(18,2)', 'Blk_Hasil', 'ALL', 1, 'FM — SUM(c_paper_cost_pc × qty_order_pcs) dari semua hpp_parts. Di-update setelah calc_cogs dijalankan di semua part.');
INSERT INTO `map_hpp` VALUES (23, 'Total Proses', 'total_process_cost', 'FM', '0', 'DECIMAL(18,2)', 'Blk_Hasil', 'ALL', 2, 'FM — SUM(c_process_cost_pc × qty_order_pcs) dari semua hpp_parts.');
INSERT INTO `map_hpp` VALUES (24, 'Total Finishing', 'total_finishing_cost', 'FM', '0', 'DECIMAL(18,2)', 'Blk_Hasil', 'ALL', 3, 'FM — SUM(c_coating_cost_pc × qty_order_pcs) dari semua hpp_parts.');
INSERT INTO `map_hpp` VALUES (25, 'Total HPP', 'total_hpp', 'FM', '0', 'DECIMAL(18,2)', 'Blk_Hasil', 'ALL', 4, 'FM — total_material_cost + total_process_cost + total_finishing_cost + total_tooling (dari parts).');
INSERT INTO `map_hpp` VALUES (26, 'Setelah Margin', 'total_after_margin', 'FM', '0', 'DECIMAL(18,2)', 'Blk_Hasil', 'ALL', 5, 'FM — total_hpp × (1 + tolerance_percent/100) × (1 + margin_percent/100).');
INSERT INTO `map_hpp` VALUES (27, 'Setelah PPN', 'total_after_ppn', 'FM', '0', 'DECIMAL(18,2)', 'Blk_Hasil', 'ALL', 6, 'FM — total_after_margin × (1 + ppn_percent/100) jika is_ppn=1, else = total_after_margin.');
INSERT INTO `map_hpp` VALUES (28, 'Harga/PCS', 'price_per_pcs', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil', 'ALL', 7, 'FM — total_after_ppn / qty_order_pcs, dibulatkan sesuai rounding_mode.');
INSERT INTO `map_hpp` VALUES (29, NULL, 'hpp_id', 'SYS', NULL, 'BIGINT UNSIGNED', 'Blk_Info', 'ALL', 1, 'PK auto increment. Primary key transaksi HPP.');
INSERT INTO `map_hpp` VALUES (30, NULL, 'create_log', 'RO', 'CURRENT_TIMESTAMP', 'TIMESTAMP', 'Blk_Info', 'ALL', 2, 'Timestamp otomatis saat record dibuat.');
INSERT INTO `map_hpp` VALUES (31, NULL, 'update_log', 'RO', 'CURRENT_TIMESTAMP ON UPDATE', 'TIMESTAMP', 'Blk_Info', 'ALL', 3, 'Timestamp otomatis saat record terakhir diubah.');
INSERT INTO `map_hpp` VALUES (32, NULL, 'create_by', 'RO', NULL, 'VARCHAR(200)', 'Blk_Info', 'ALL', 4, 'User Scriptcase yang membuat record. Di-set dari [usr_login] di onBeforeInsert.');
INSERT INTO `map_hpp` VALUES (33, NULL, 'update_by', 'RO', NULL, 'VARCHAR(200)', 'Blk_Info', 'ALL', 5, 'User Scriptcase terakhir yang mengubah record. Di-set dari [usr_login] di onBeforeUpdate.');
INSERT INTO `map_hpp` VALUES (34, 'Detail Parts', 'emb_hpp_parts', 'SYS', NULL, 'EMBED', 'Blk_Parts', 'ALL', 1, 'SYS — Block ini menampilkan embedded application frm_hpp_parts. Di Scriptcase: klik Blk_Parts > Applications > tambah frm_hpp_parts. Passing global: set [g_hpp_id] = {hpp_id} di onLoad sebelum block di-render. Block hanya tampil saat sc_mode = upd (record sudah tersimpan, hpp_id sudah ada).');

-- ----------------------------
-- Table structure for map_hpp_parts
-- ----------------------------
DROP TABLE IF EXISTS `map_hpp_parts`;
CREATE TABLE `map_hpp_parts`  (
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK auto increment.',
  `excel_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Label baris di Excel costing. NULL jika tidak ada padanan langsung.',
  `cell_addr_input` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Alamat cell INPUT di Excel. Contoh: B24 = rate cetak/rim yang diketik user. - = tidak ada padanan.',
  `cell_addr_result` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Alamat cell RESULT/FORMULA di Excel. Contoh: J24 = hasil cetak/PC. - = tidak ada padanan atau field sistem.',
  `nama_field` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama kolom di tabel hpp_parts (setelah rename v2).',
  `input_type` enum('RW','RO','FM','AUTO','SYS') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'RW=user input | RO=tampil saja tidak bisa edit | FM=hasil formula tombol Hitung | AUTO=di-set sistem (onLoad/Ajax) | SYS=field sistem tidak tampil di form.',
  `nilai_default` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nilai default saat record baru dibuat.',
  `tipe_data` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipe data MySQL: INT, TINYINT, DECIMAL(x,y), VARCHAR(n), TIMESTAMP, dll.',
  `nama_block` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Nama block di form Scriptcase.',
  `visible_type` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Part type yang menampilkan field ini. Contoh: DC,DC+SF | ALL | SHEET.',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Urutan tampil dalam block.',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Keterangan lengkap. Untuk FM wajib ada rumus formulanya.',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_nama_field`(`nama_field` ASC) USING BTREE,
  INDEX `idx_block`(`nama_block` ASC) USING BTREE,
  INDEX `idx_tipe`(`input_type` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 126 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Dokumentasi pemetaan field hpp_parts ke layout form_hpp_parts.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of map_hpp_parts
-- ----------------------------
INSERT INTO `map_hpp_parts` VALUES (1, 'No Urut Part', '-', '-', 'no_urut', 'AUTO', '1', 'TINYINT UNSIGNED', 'Blk_Identitas', 'ALL', 50, 'Urutan part dalam satu HPP. Di-set otomatis di onBeforeInsert. Default 1 sementara saat onLoad.');
INSERT INTO `map_hpp_parts` VALUES (2, 'Nama Part', '-', '-', 'part_name', 'RW', NULL, 'VARCHAR(100)', 'Blk_Identitas', 'ALL', 20, 'Nama deskriptif part. Contoh: Inner Box, Outer Box. Trigger template auto-fill via onChange.');
INSERT INTO `map_hpp_parts` VALUES (3, 'Tipe Part', '-', '-', 'part_type', 'RW', 'DC', 'VARCHAR(20)', 'Blk_Identitas', 'ALL', 30, 'Jenis kalkulasi: DC | DC+SF | DC+SHEET | KRAFT | SHEET. Menentukan blok field yang tampil.');
INSERT INTO `map_hpp_parts` VALUES (4, 'Master Card ID', '-', '-', 'mcard_id', 'RW', NULL, 'BIGINT UNSIGNED', 'Blk_Identitas', 'ALL', 40, 'Logical FK ke tmmst.m_card. Optional. NULL untuk produk baru.');
INSERT INTO `map_hpp_parts` VALUES (5, 'Jumlah Order (qty part)', 'B21', '-', 'qty_part', 'AUTO', '0', 'DECIMAL(12,2)', 'Blk_Identitas', 'ALL', 10, 'Qty efektif untuk kalkulasi part ini. Di-set AUTO dari hpp.qty_order_pcs di onLoad/onBeforeInsert. Dipakai sebagai pembagi utama pada semua formula biaya per PC.');
INSERT INTO `map_hpp_parts` VALUES (11, 'Gramatur / Bahan', 'F-G(1-16)', 'J3', 'gramatur_id', 'RW', NULL, 'INT UNSIGNED', 'Blk_Material_Base', 'DC,DC+SF,DC+SHEET,KRAFT', 10, 'FK ke m_paper_grammage. Sumber harga duplex/kraft dan DC%. Tampil sebagai dropdown v1_grammage_latest.');
INSERT INTO `map_hpp_parts` VALUES (12, 'Panjang Plano (cm)', 'B18', '-', 'i_panjang_plano_cm', 'RW', '0', 'DECIMAL(12,4)', 'Blk_Material_Base', 'DC,DC+SF,DC+SHEET,KRAFT', 20, 'Panjang plano mesin cetak dalam cm. Contoh: 70. Dipakai pada i_up_cetak dan c_paper_cost_pc.');
INSERT INTO `map_hpp_parts` VALUES (13, 'Lebar Plano (cm)', 'C18', '-', 'i_lebar_plano_cm', 'RW', '0', 'DECIMAL(12,4)', 'Blk_Material_Base', 'DC,DC+SF,DC+SHEET,KRAFT', 30, 'Lebar plano mesin cetak dalam cm. Contoh: 100.');
INSERT INTO `map_hpp_parts` VALUES (14, '1 Plano dipotong jadi', 'B19', '-', 'i_cut_per_plano', 'RW', '1', 'DECIMAL(8,4)', 'Blk_Material_Base', 'DC,DC+SF,DC+SHEET,KRAFT', 40, 'Jumlah potongan per plano. Default 1. Pembagi pada c_paper_cost_pc.');
INSERT INTO `map_hpp_parts` VALUES (15, 'Isi dalam 1 LB Cetak', 'B20', '-', 'i_isi_per_lb', 'RW', '1', 'DECIMAL(8,4)', 'Blk_Material_Base', 'DC,DC+SF,DC+SHEET,KRAFT', 50, 'Jumlah PCS dalam 1 Lembar Bahan. Default 1. Pembagi pada c_paper_cost_pc dan c_tooling_cost_pc.');
INSERT INTO `map_hpp_parts` VALUES (18, '% DC (override)', 'D10', '-', 'i_dc_persen_override', 'RW', NULL, 'DECIMAL(5,2)', 'Blk_Material_Base', 'DC,DC+SF,DC+SHEET', 60, 'Override DC% per part. NULL/0 = fallback ke master grammage. Contoh: 5.30 → dc_factor = 1+5.30 = 6.30.');
INSERT INTO `map_hpp_parts` VALUES (19, 'Supplier Sheet', '-', '-', 'supplier_sheet_code', 'RW', NULL, 'VARCHAR(20)', 'Blk_Sheet_Common', 'DC+SF,DC+SHEET,SHEET', 10, 'Supplier corrugated. FK ke m_sheet_supplier. Wajib diisi untuk DC+SHEET, DC+SF, SHEET. Filter harga compare_sheet_suppliers().');
INSERT INTO `map_hpp_parts` VALUES (20, 'Substansi / Tipe SF', 'A37', 'J37', 'sheet_rate_id', 'RW', NULL, 'INT UNSIGNED', 'Blk_Sheet_Common', 'DC+SF,DC+SHEET,SHEET', 20, 'FK ke m_sheet_rate.doc_id. DC+SF→SINGLE FACE | DC+SHEET→SHEET(B/F,C/F) | SHEET→SHEET. Sumber index_substance Rp/m².');
INSERT INTO `map_hpp_parts` VALUES (21, 'Panjang Piece SF/SH (cm)', 'B23', '-', 'i_sheet_panjang_cm', 'RW', NULL, 'DECIMAL(12,4)', 'Blk_Sheet_Common', 'DC+SF,DC+SHEET,SHEET', 30, 'Panjang piece SF atau Sheet Laminasi cm. DC+SF: lam_sheet=index×p×l. DC+SHEET: lam_sheet=index×lebar_trim×p/isi×(1-disc).');
INSERT INTO `map_hpp_parts` VALUES (22, 'Info Harga Supplier', '-', '-', 'c_sheet_compare_info', 'RO', NULL, 'TEXT', 'Blk_Sheet_Common', 'DC+SF,DC+SHEET,SHEET', 40, 'READONLY. Ringkasan perbandingan harga supplier per substansi. Di-set otomatis compare_sheet_suppliers().');
INSERT INTO `map_hpp_parts` VALUES (23, 'Lebar Piece SF (cm)', 'C23', '-', 'i_sheet_lebar_cm', 'RW', NULL, 'DECIMAL(12,4)', 'Blk_SF_Lebar', 'DC+SF', 10, 'Lebar piece Single Face cm. Hanya DC+SF. lam_sheet_pc = index × (panjang/100) × (lebar/100).');
INSERT INTO `map_hpp_parts` VALUES (24, 'Lebar Trim (cm)', '-', '-', 'i_lebar_trim_cm', 'RW', NULL, 'DECIMAL(12,4)', 'Blk_Sheet_Trim', 'DC+SHEET,SHEET', 10, 'Lebar trim sheet cm. Contoh: 160mm=1.60cm. lam_sheet_pc = index×(lebar_trim/100)×(panjang/100)/isi×(1-disc).');
INSERT INTO `map_hpp_parts` VALUES (25, 'ISI/TRIM', '-', '-', 'i_isi_per_trim', 'RW', '1', 'SMALLINT UNSIGNED', 'Blk_Sheet_Trim', 'DC+SHEET,SHEET', 20, 'Jumlah piece per trim. Minimal 1. Pembagi pada formula lam_sheet_pc dan c_paper_cost_pc SHEET.');
INSERT INTO `map_hpp_parts` VALUES (26, 'Discount / Surcharge', '-', '-', 'i_discount_rm', 'RW', '0', 'DECIMAL(5,4)', 'Blk_Sheet_Trim', 'DC+SHEET,SHEET', 30, 'Discount (positif) atau surcharge (negatif). Range: -0.99 s/d 0.99. Contoh: -0.10 = surcharge 10%.');
INSERT INTO `map_hpp_parts` VALUES (27, 'Ukuran Cetak P (cm)', 'B22', '-', 'c_uk_cetak_panjang_cm', 'FM', '0', 'DECIMAL(12,4)', 'Blk_Ukuran_Cetak', 'DC,DC+SF,DC+SHEET', 10, 'FM — ((P+L)×2 + kuping + bleed + jarak)/10. Jika dim=0: user input langsung dari Master Card (Mode B).');
INSERT INTO `map_hpp_parts` VALUES (28, 'Ukuran Cetak L (cm)', 'C22', '-', 'c_uk_cetak_lebar_cm', 'FM', '0', 'DECIMAL(12,4)', 'Blk_Ukuran_Cetak', 'DC,DC+SF,DC+SHEET', 20, 'FM — (L + T + bleed + jarak)/10. Jika dim=0: user input langsung (Mode B).');
INSERT INTO `map_hpp_parts` VALUES (29, 'Luas Cetak (cm²)', '-', '-', 'c_area_cetak_cm2', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Ukuran_Cetak', 'DC,DC+SF,DC+SHEET', 30, 'FM — c_uk_cetak_panjang_cm × c_uk_cetak_lebar_cm. Dipakai pada coating_cost dan film_cost.');
INSERT INTO `map_hpp_parts` VALUES (32, 'Jumlah Cetak (LC)', 'B21', '-', 'c_jumlah_cetak_lc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Material', 'DC,DC+SF,DC+SHEET,KRAFT', 30, 'FM — qty_order / i_up_cetak. Jumlah lembar cetak untuk memenuhi order.');
INSERT INTO `map_hpp_parts` VALUES (33, 'Jumlah Bahan (LB)', '-', '-', 'c_jumlah_bahan_lb', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Material', 'DC,DC+SF,DC+SHEET,KRAFT', 40, 'FM — c_jumlah_cetak_lc × (1 + waste + spoilage). Lembar bahan aktual termasuk waste.');
INSERT INTO `map_hpp_parts` VALUES (34, 'Ongkos Cetak/RIM', 'B24', 'J24', 'i_ongkos_cetak_rim', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Cetak', 'DC,DC+SF,DC+SHEET', 20, 'Ongkos cetak per RIM (500 LC). c_cetak = (lc/500) × rate/qty. KRAFT boleh 0 jika tidak cetak.');
INSERT INTO `map_hpp_parts` VALUES (35, 'Ongkos Hot Print/PC', 'B28', 'J28', 'i_ongkos_hotprint_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Cetak', 'DC,DC+SF,DC+SHEET', 30, 'Ongkos hot print/foil stamping per PCS Rp. Dijumlah langsung ke c_process_cost_pc.');
INSERT INTO `map_hpp_parts` VALUES (36, 'Ongkos Plong/LB', 'B25', 'J25', 'i_ongkos_plong_lb', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Plong', 'DC,DC+SF,DC+SHEET,KRAFT', 10, 'Ongkos plong per LB Rp. c_plong = i_ongkos_plong_lb / i_isi_per_lb. TIDAK pakai cut_per_plano. Contoh NS40: 250/1=250.00.');
INSERT INTO `map_hpp_parts` VALUES (37, 'Ongkos LEM/PC', 'B26', 'J26', 'i_ongkos_lem_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Plong', 'DC,DC+SF,DC+SHEET,KRAFT', 20, 'Ongkos lem FLAT RATE per PC dalam Rp. Input langsung = nilai per PC. Label Excel \"LEM/LC\" menyesatkan — formula sebenarnya flat, bukan per lembar cetak. Rename dari i_ongkos_lem_lc.');
INSERT INTO `map_hpp_parts` VALUES (44, 'Ongkos Laminasi (fallback manual)', 'B38', 'J38', 'i_ongkos_laminasi_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 110, 'Ongkos jasa mesin/tenaga laminasi per PCS Rp. Beda dari i_lam_biasa_pc (material).');
INSERT INTO `map_hpp_parts` VALUES (45, 'Film Rate (55/60)', '-', '-', 'film_rate_id', 'RW', 'NULL', 'TINYINT UNSIGNED', 'Blk_Film_Plate', 'DC,DC+SF,DC+SHEET', 10, 'FK ke m_film_rate. 55=Normal print | 60=Full cetak+WB lam | 45=Ivory/Kalender | 30=Bag Bawah.');
INSERT INTO `map_hpp_parts` VALUES (46, 'Film (faktor)', 'B40', 'J40', 'i_film_faktor', 'RW', '0.25', 'DECIMAL(8,4)', 'Blk_Film_Plate', 'DC,DC+SF,DC+SHEET', 20, 'Faktor amortisasi film. Default 0.25. c_film = faktor² × area × film_rate / qty. DIKUADRATKAN sesuai Excel.');
INSERT INTO `map_hpp_parts` VALUES (47, 'Jumlah Warna', 'B17', '-', 'i_jumlah_warna', 'RW', '4', 'TINYINT UNSIGNED', 'Blk_Film_Plate', 'DC,DC+SF,DC+SHEET', 30, 'Jumlah warna cetak. Default 4 (CMYK). plate_cost = ongkos_plate × warna / qty / isi_lb.');
INSERT INTO `map_hpp_parts` VALUES (48, 'Plate (Rp/warna)', 'B41', 'J41', 'i_ongkos_plate_warna', 'RW', '90000', 'DECIMAL(18,4)', 'Blk_Film_Plate', 'DC,DC+SF,DC+SHEET', 40, 'Biaya plate CTP per warna Rp. Default 90.000. plate_cost = ongkos × warna / qty / isi_lb. Contoh NS40: 90000×4/1000/1=360.');
INSERT INTO `map_hpp_parts` VALUES (49, 'Pisau (faktor)', 'B42', 'J42', 'i_pisau_faktor', 'RW', '0.25', 'DECIMAL(8,4)', 'Blk_Pisau', 'DC,DC+SF,DC+SHEET,KRAFT', 10, 'Faktor amortisasi pisau. Default 0.25. knife_cost = faktor × rate / qty / isi_lb.');
INSERT INTO `map_hpp_parts` VALUES (50, 'Pisau (rate Rp/order)', 'I42', 'J42', 'i_pisau_rate_rp', 'AUTO', '0', 'DECIMAL(18,4)', 'Blk_Pisau', 'DC,DC+SF,DC+SHEET,KRAFT', 20, 'Rate biaya pisau per order Rp. AUTO dari part_type: DC/DC+SF/DC+SHEET=900.000, KRAFT=1.500.000, SHEET=0. User BOLEH override per order. Tetap prefix i_ karena bisa di-edit. Dari m_hpp_constants atau m_part_type.pisau_rate_rp.');
INSERT INTO `map_hpp_parts` VALUES (51, 'Ongkos Kupas/PC', 'B29', 'J29', 'i_ongkos_kupas_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Ongkos_Umum', 'DC,DC+SF,DC+SHEET,KRAFT,SHEET', 10, 'Ongkos kupas/stripping per PCS Rp. Berlaku semua tipe.');
INSERT INTO `map_hpp_parts` VALUES (52, 'Ongkos QC/PC', 'B30', 'J30', 'i_ongkos_qc_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Ongkos_Umum', 'DC,DC+SF,DC+SHEET,KRAFT,SHEET', 20, 'Ongkos QC+packing per PCS Rp. Berlaku semua tipe.');
INSERT INTO `map_hpp_parts` VALUES (53, 'Ongkos Kirim/PC', 'B31', 'J31', 'i_ongkos_kirim_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Ongkos_Umum', 'DC,DC+SF,DC+SHEET,KRAFT,SHEET', 30, 'Ongkos kirim/delivery per PCS Rp. Berlaku semua tipe.');
INSERT INTO `map_hpp_parts` VALUES (54, 'Ongkos Print', 'C20', 'D20', 'i_ongkos_print_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Sheet', 'SHEET', 10, 'Ongkos cetak flexografi/digital per PCS. Khusus SHEET corrugated.');
INSERT INTO `map_hpp_parts` VALUES (55, 'Ongkos Slitter', 'C22', 'D22', 'i_ongkos_slitter_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Sheet', 'SHEET', 30, 'Ongkos slitter (potong memanjang) per PCS. Bersama slotter = \"Ongkos Sliter+Sloter\" di Excel.');
INSERT INTO `map_hpp_parts` VALUES (56, 'Ongkos Slotter', 'C23', 'D23', 'i_ongkos_slotter_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Sheet', 'SHEET', 40, 'Ongkos slotter (buat alur lipat/slot) per PCS.');
INSERT INTO `map_hpp_parts` VALUES (57, 'Ongkos Die Cut', 'C21', 'D21', 'i_ongkos_diecut_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Sheet', 'SHEET', 20, 'Ongkos die-cut per PCS. Extra field DB — konfirmasi apakah perlu atau DROP.');
INSERT INTO `map_hpp_parts` VALUES (58, 'Ongkos Join', 'C24', 'D24', 'i_ongkos_join_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Sheet', 'SHEET', 60, 'Ongkos join/stitching/gluing per PCS Rp. Nilai dari m_join_type atau input manual.');
INSERT INTO `map_hpp_parts` VALUES (59, 'Jenis Join', 'B24', '-', 'join_type', 'RW', NULL, 'VARCHAR(50)', 'Blk_Proses_Sheet', 'SHEET', 50, 'Jenis penyambungan box corrugated. FK ke m_join_type.code. Contoh: STITCHING_SINGLE, GLUE_DOUBLE.');
INSERT INTO `map_hpp_parts` VALUES (60, 'Partisi', 'C25', 'D25', 'i_ongkos_partisi_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Sheet', 'SHEET', 70, 'Ongkos partisi/sekat per PCS Rp.');
INSERT INTO `map_hpp_parts` VALUES (61, 'Layer', 'C26', 'D26', 'i_ongkos_layer_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Sheet', 'SHEET', 80, 'Ongkos layer/double wall per PCS Rp.');
INSERT INTO `map_hpp_parts` VALUES (62, 'Harga Kertas/PC', '-', 'J3', 'c_paper_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Material', 'ALL', 10, 'FM — DC/DC+SF/DC+SHEET: duplex_price×dc_factor×(plano_p×plano_l)/8611/500/cut/isi. KRAFT: harga_kg×(grm/1000)×(plano_cm²/10000)/(cut×isi). SHEET: index×(lebar_trim/100)×(panjang/100)/isi_trim×(1-disc).');
INSERT INTO `map_hpp_parts` VALUES (63, 'Cetak/PC', '-', 'J24', 'c_cetak_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Proses', 'DC,DC+SF,DC+SHEET,KRAFT', 10, 'FM — ongkos_cetak_rim/500/cut/isi. Contoh NS40: 900000/500/2/1=900.00.');
INSERT INTO `map_hpp_parts` VALUES (64, 'Plong/PC', '-', 'J25', 'c_plong_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Proses', 'DC,DC+SF,DC+SHEET,KRAFT', 20, 'FM — ongkos_plong_lb/isi_per_lb. TIDAK pakai cut. Contoh NS40: 250/1=250.00.');
INSERT INTO `map_hpp_parts` VALUES (65, 'Lem/PC', '-', 'J26', 'c_lem_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Proses', 'DC,DC+SF,DC+SHEET,KRAFT', 30, 'FM — flat rate = i_ongkos_lem_pc langsung per PC. Contoh NS40: 375.00.');
INSERT INTO `map_hpp_parts` VALUES (66, 'Ongkos Proses/PC', '-', 'J39', 'c_process_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Proses', 'ALL', 150, 'FM — SUBTOTAL biaya proses/PC. DC family+KRAFT: cetak+plong+lem+hotprint+kupas+qc+kirim+vb+lam_biasa+lam_dof+lam_3d+lam_sheet+ongkos_lam. SHEET: print+slitter+slotter+diecut+join+partisi+layer+kupas+qc+kirim.');
INSERT INTO `map_hpp_parts` VALUES (68, 'Film', '-', 'J40', 'c_film_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Tooling', 'DC,DC+SF,DC+SHEET,KRAFT', 10, 'FM — faktor² × area_cetak_cm2 × film_rate_code / qty_order. film_rate dari m_film_rate (55/60/45/30).');
INSERT INTO `map_hpp_parts` VALUES (69, 'Plate', '-', 'J41', 'c_plate_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Tooling', 'DC,DC+SF,DC+SHEET,KRAFT', 30, 'FM — ongkos_plate × jumlah_warna / qty / isi_lb. Contoh NS40: 90000×4/1000/1=360.00.');
INSERT INTO `map_hpp_parts` VALUES (70, 'Pisau', '-', 'J42', 'c_knife_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Tooling', 'DC,DC+SF,DC+SHEET,KRAFT', 50, 'FM — pisau_faktor × pisau_rate / qty / isi_lb. Contoh NS40: 0.25×900000/1000/1=225.00.');
INSERT INTO `map_hpp_parts` VALUES (71, 'Tooling/PC', '-', 'J43', 'c_tooling_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Tooling', 'DC,DC+SF,DC+SHEET,KRAFT', 70, 'FM — SUBTOTAL tooling = c_film + c_plate + c_knife. SHEET: c_tooling_cost_pc = 0.');
INSERT INTO `map_hpp_parts` VALUES (72, 'Harga Pokok (HPP)/PC', '-', 'J43', 'c_total_part_cost_pc', 'FM', '0', 'DECIMAL(18,4)', 'Blk_Hasil_Kalkulasi', 'ALL', 10, 'FM — paper + process + coating + tooling per PC.');
INSERT INTO `map_hpp_parts` VALUES (73, 'Catatan Kalkulasi', '-', '-', 'calc_notes', 'FM', NULL, 'TEXT', 'Blk_Catatan_Hasil', 'ALL', 10, 'FM — Catatan runtime: versi, timestamp, dc_factor, c_up, warning, summary. Di-set calc_cogs() Step 17.');
INSERT INTO `map_hpp_parts` VALUES (74, NULL, '-', '-', 'hpp_parts_id', 'SYS', NULL, 'BIGINT UNSIGNED', 'Blk_Info_Debg', NULL, 10, 'PK auto increment. Primary key unik per part.');
INSERT INTO `map_hpp_parts` VALUES (75, NULL, '-', '-', 'hpp_id', 'SYS', NULL, 'BIGINT UNSIGNED', 'Blk_Info_Debg', NULL, 20, 'Logical FK ke hpp.hpp_id. Di-set dari parent header via [g_hpp_id].');
INSERT INTO `map_hpp_parts` VALUES (76, NULL, '-', '-', 'created_log', 'SYS', 'CURRENT_TIMESTAMP', 'TIMESTAMP', 'Blk_Info_Debg', NULL, 30, 'Timestamp otomatis saat record dibuat.');
INSERT INTO `map_hpp_parts` VALUES (77, NULL, '-', '-', 'updated_log', 'SYS', 'CURRENT_TIMESTAMP ON UPDATE', 'TIMESTAMP', 'Blk_Info_Debg', NULL, 50, 'Timestamp otomatis saat record terakhir diubah. ON UPDATE CURRENT_TIMESTAMP.');
INSERT INTO `map_hpp_parts` VALUES (78, NULL, '-', '-', 'created_by', 'SYS', NULL, 'VARCHAR(100)', 'Blk_Info_Debg', NULL, 40, 'User Scriptcase yang membuat record. Di-set dari [usr_login] di onLoad.');
INSERT INTO `map_hpp_parts` VALUES (79, NULL, '-', '-', 'updated_by', 'SYS', NULL, 'VARCHAR(100)', 'Blk_Info_Debg', NULL, 60, 'User Scriptcase terakhir ubah record. Di-set dari [usr_login].');
INSERT INTO `map_hpp_parts` VALUES (81, 'Faktor Lam Sheet (Rp/cm²)', '-', '-', 'i_ongkos_lam_rp_cm2_sh', 'RW', NULL, 'DECIMAL(8,4)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 80, 'Rate ongkos proses laminasi SHEET per cm² (Rp/cm²). Formula: ongkos_sh = flag_sh × uk_p × uk_l × rate / i_isi_per_lb. NS40=0.15, FS_GTZ=0.095, KRAFT=0.075. NULL = i_ongkos_laminasi_pc input manual.');
INSERT INTO `map_hpp_parts` VALUES (82, 'Faktor Lam SF (Rp/cm²)', '-', '-', 'i_ongkos_lam_rp_cm2_sf', 'RW', NULL, 'DECIMAL(8,4)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 90, 'Rate ongkos proses laminasi SF per cm² (Rp/cm²). Formula: ongkos_sf = flag_sf × uk_p × uk_l × rate / i_isi_per_lb. NS40=0.10, FS_GTZ=0.085, KRAFT=0.065. NULL = tidak dihitung.');
INSERT INTO `map_hpp_parts` VALUES (83, 'Harga Kertas (Total Order)', 'I13', 'I13', 'c_i_paper', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Material', 'ALL', 20, 'FM — Total harga kertas untuk seluruh order. Formula: c_paper_cost_pc * qty_part * i_isi_per_lb. Excel I13 (baris gramatur aktif).');
INSERT INTO `map_hpp_parts` VALUES (84, 'Ongkos Cetak (Total)', 'I24', 'I24', 'c_i_cetak', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Proses', 'DC,DC+SF,DC+SHEET,KRAFT', 11, 'FM — Total ongkos cetak order. Formula: c_cetak_cost_pc * qty_part * i_isi_per_lb. Excel I24.');
INSERT INTO `map_hpp_parts` VALUES (85, 'Ongkos Plong (Total)', 'I25', 'I25', 'c_i_plong', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Proses', 'DC,DC+SF,DC+SHEET,KRAFT', 21, 'FM — Total ongkos plong order. Formula: c_plong_cost_pc * qty_part * i_isi_per_lb. Excel I25.');
INSERT INTO `map_hpp_parts` VALUES (86, 'Ongkos LEM (Total)', 'I26', 'I26', 'c_i_lem', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Proses', 'DC,DC+SF,DC+SHEET,KRAFT', 31, 'FM — Total ongkos lem order. Formula: i_ongkos_lem_pc * qty_part * i_isi_per_lb. Excel I26.');
INSERT INTO `map_hpp_parts` VALUES (88, 'Ongkos Hot Print (Total)', 'I28', 'I28', 'c_i_hotprint', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Proses', 'DC,DC+SF,DC+SHEET', 50, 'FM — Total ongkos hotprint order. Formula: i_ongkos_hotprint_pc * qty_part * i_isi_per_lb. Excel I28.');
INSERT INTO `map_hpp_parts` VALUES (89, 'Ongkos Kupas (Total)', 'I29', 'I29', 'c_i_kupas', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Proses', 'ALL', 60, 'FM — Total ongkos kupas order. Formula: i_ongkos_kupas_pc * qty_part * i_isi_per_lb. Excel I29.');
INSERT INTO `map_hpp_parts` VALUES (90, 'Ongkos QC (Total)', 'I30', 'I30', 'c_i_qc', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Proses', 'ALL', 70, 'FM — Total ongkos QC order. Formula: i_ongkos_qc_pc * qty_part * i_isi_per_lb. Excel I30.');
INSERT INTO `map_hpp_parts` VALUES (91, 'Ongkos Kirim (Total)', 'I31', 'I31', 'c_i_kirim', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Proses', 'ALL', 80, 'FM — Total ongkos kirim order. Formula: i_ongkos_kirim_pc * qty_part * i_isi_per_lb. Excel I31.');
INSERT INTO `map_hpp_parts` VALUES (98, 'Ongkos Laminasi (Total Order)', 'I38', 'I38', 'c_i_ongkos_lam', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 120, 'FM — Total ongkos proses laminasi order. Formula: i_ongkos_laminasi_pc * qty_part * i_isi_per_lb. Excel I38.');
INSERT INTO `map_hpp_parts` VALUES (99, 'Film (Total Order)', 'I40', 'I40', 'c_i_film', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Tooling', 'DC,DC+SF,DC+SHEET,KRAFT', 20, 'FM — Total film order. Formula: c_film_cost_pc * qty_part * i_isi_per_lb. Excel I40.');
INSERT INTO `map_hpp_parts` VALUES (100, 'Plate (Total Order)', 'I41', 'I41', 'c_i_plate', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Tooling', 'DC,DC+SF,DC+SHEET,KRAFT', 40, 'FM — Total plate order. Formula: c_plate_cost_pc * qty_part * i_isi_per_lb. Excel I41.');
INSERT INTO `map_hpp_parts` VALUES (101, 'Pisau (Total Order)', 'I42', 'I42', 'c_i_knife', 'FM', NULL, 'DECIMAL(18,2)', 'Blk_Hasil_Tooling', 'DC,DC+SF,DC+SHEET,KRAFT', 60, 'FM — Total pisau order. Formula: c_knife_cost_pc * qty_part * i_isi_per_lb. Excel I42.');
INSERT INTO `map_hpp_parts` VALUES (112, 'Pakai Laminasi Sheet?', 'B36', '-', 'i_lam_sheet_flag', 'RW', '1', 'TINYINT', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 10, 'B36: 1=pakai laminasi sheet, 0=tidak. Pengali J36 & komponen sheet di J38.');
INSERT INTO `map_hpp_parts` VALUES (113, 'Laminasi Sheet', 'J36', 'J36', 'i_lam_sheet_flat_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 20, 'J36 input nilai AKHIR Rp/PC. NS40=1360, NKC135=1600, FS_GTZ=463 (=1852/isi). User bagi sendiri jika harga per-LB.');
INSERT INTO `map_hpp_parts` VALUES (114, 'Pakai SF EF?', 'B37', '-', 'i_sf_ef_flag', 'RW', '0', 'TINYINT', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 30, 'B37: 1=pakai SF EF, 0=tidak. Pengali J37 & komponen SF di J38.');
INSERT INTO `map_hpp_parts` VALUES (115, 'Jenis SF EF', 'C37', '-', 'sf_ef_rate_id', 'RW', NULL, 'INT', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 40, 'C37 VLOOKUP harga SF EF -> lookup v1_sf_ef_rate (m_sheet_rate flute E/F). value=id_sf, label=display_label.');
INSERT INTO `map_hpp_parts` VALUES (116, 'Harga SF EF (override)', '-', '-', 'i_sf_ef_rate_override', 'RW', NULL, 'DECIMAL(18,4)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 50, 'Override harga SF EF per order. Kosong = pakai default v1_sf_ef_rate.rate.');
INSERT INTO `map_hpp_parts` VALUES (117, 'SF EF /PC', 'J37', 'J37', 'c_sf_ef_cost_pc', 'FM', NULL, 'DECIMAL(18,4)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 60, 'FM J37 = c_uk_cetak_panjang_cm*c_uk_cetak_lebar_cm/10000 * harga_sf / i_isi_per_lb * i_sf_ef_flag.');
INSERT INTO `map_hpp_parts` VALUES (118, 'Ukuran Ongkos Laminasi', '-', '-', 'i_lam_pakai_ukuran_sf', 'RW', '0', 'TINYINT', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 70, 'J38: 0=ukuran cetak (B22/C22), 1=ukuran SF (B23/C23). Kasus NKC135=1.');
INSERT INTO `map_hpp_parts` VALUES (119, 'Ongkos Laminasi /PC', 'J38', 'J38', 'c_ongkos_lam_pc', 'FM', NULL, 'DECIMAL(18,4)', 'Blk_Laminasi_Sheet', 'DC+SF,DC+SHEET', 100, 'FM J38 = i_lam_sheet_flag*uk*faktor_sh/isi + i_sf_ef_flag*uk*faktor_sf/isi. uk=SF jika i_lam_pakai_ukuran_sf=1, else cetak.');
INSERT INTO `map_hpp_parts` VALUES (120, 'Coating /PC', 'J32', 'J32', 'c_total_coating_pc', 'FM', NULL, 'DECIMAL(18,4)', 'Blk_Hasil_Coating', 'DC,DC+SF,DC+SHEET', 10, 'Total coating per PC = SUM(hpp_parts_coating.cost_pc).');
INSERT INTO `map_hpp_parts` VALUES (121, 'Coating /LC', '-', '-', 'c_total_coating_lc', 'FM', NULL, 'DECIMAL(18,4)', 'Blk_Hasil_Coating', 'DC,DC+SF,DC+SHEET', 20, 'Total coating per LC = SUM(hpp_parts_coating.cost_lc).');
INSERT INTO `map_hpp_parts` VALUES (122, 'Coating (Total Order)', 'I32', 'I32', 'c_i_total_coating', 'FM', NULL, 'DECIMAL(18,4)', 'Blk_Hasil_Coating', 'DC,DC+SF,DC+SHEET', 30, 'Total coating order = c_total_coating_pc * qty * isi_per_lb.');
INSERT INTO `map_hpp_parts` VALUES (123, 'Jenis Packing', '-', '-', 'packing_type_id', 'RW', NULL, 'INT', 'Blk_Proses_Packing', 'DC,DC+SF,DC+SHEET,KRAFT,SHEET', 10, 'FK m_packing_type. Jenis packing produk.');
INSERT INTO `map_hpp_parts` VALUES (124, 'Ongkos Packing/PC', '-', '-', 'i_ongkos_packing_pc', 'RW', '0', 'DECIMAL(18,4)', 'Blk_Proses_Packing', 'DC,DC+SF,DC+SHEET,KRAFT,SHEET', 20, 'Ongkos packing per PC (Rp).');
INSERT INTO `map_hpp_parts` VALUES (125, 'Packing (Total Order)', '-', '-', 'c_i_packing', 'FM', NULL, 'DECIMAL(18,4)', 'Blk_Proses_Packing', 'DC,DC+SF,DC+SHEET,KRAFT,SHEET', 30, 'Total packing order = i_ongkos_packing_pc * qty.');

-- ----------------------------
-- Table structure for notif_inbox
-- ----------------------------
DROP TABLE IF EXISTS `notif_inbox`;
CREATE TABLE `notif_inbox`  (
  `inbox_id` int NOT NULL AUTO_INCREMENT,
  `notif_id` int NOT NULL,
  `login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_dtsent` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notif_ontop` int NOT NULL DEFAULT 0,
  `notif_isread` int NOT NULL DEFAULT 0,
  `notif_dtread` datetime NULL DEFAULT NULL,
  `notif_tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `notif_important` int NULL DEFAULT NULL,
  PRIMARY KEY (`inbox_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of notif_inbox
-- ----------------------------
INSERT INTO `notif_inbox` VALUES (2, 1, 'herru', '2025-03-19 15:20:10', 0, 0, NULL, '0', NULL);
INSERT INTO `notif_inbox` VALUES (3, 1, 'johiwan', '2025-03-19 15:20:10', 0, 0, NULL, '0', NULL);

-- ----------------------------
-- Table structure for notif_notifications
-- ----------------------------
DROP TABLE IF EXISTS `notif_notifications`;
CREATE TABLE `notif_notifications`  (
  `notif_id` int NOT NULL AUTO_INCREMENT,
  `notif_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_dtcreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notif_ontop` int NOT NULL DEFAULT 0,
  `notif_dtexpire` datetime NULL DEFAULT NULL,
  `notif_categ` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `notif_login_sender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notif_type` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `notif_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`notif_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of notif_notifications
-- ----------------------------
INSERT INTO `notif_notifications` VALUES (1, 'tes', '<p>vhgfhgfhb</p>', '2025-03-19 15:20:10', 0, NULL, 'DEFAULT', 'admin', '', '', NULL);

-- ----------------------------
-- Table structure for notif_pref
-- ----------------------------
DROP TABLE IF EXISTS `notif_pref`;
CREATE TABLE `notif_pref`  (
  `login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receive_email` int NOT NULL DEFAULT 0,
  `receive_sms` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`login`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of notif_pref
-- ----------------------------
INSERT INTO `notif_pref` VALUES ('admin', 0, 0);
INSERT INTO `notif_pref` VALUES ('arinda', 0, 0);
INSERT INTO `notif_pref` VALUES ('diana', 0, 0);
INSERT INTO `notif_pref` VALUES ('eni', 0, 0);
INSERT INTO `notif_pref` VALUES ('hendra', 0, 0);
INSERT INTO `notif_pref` VALUES ('johiwan', 0, 0);
INSERT INTO `notif_pref` VALUES ('nabila', 0, 0);
INSERT INTO `notif_pref` VALUES ('nadya', 0, 0);
INSERT INTO `notif_pref` VALUES ('nisa', 0, 0);
INSERT INTO `notif_pref` VALUES ('novita', 0, 0);
INSERT INTO `notif_pref` VALUES ('paris', 0, 0);
INSERT INTO `notif_pref` VALUES ('sania', 0, 0);
INSERT INTO `notif_pref` VALUES ('shabrina', 0, 0);
INSERT INTO `notif_pref` VALUES ('wiwik', 0, 0);
INSERT INTO `notif_pref` VALUES ('yuna', 0, 0);

-- ----------------------------
-- Table structure for notif_profiles
-- ----------------------------
DROP TABLE IF EXISTS `notif_profiles`;
CREATE TABLE `notif_profiles`  (
  `profile_id` int NOT NULL AUTO_INCREMENT,
  `profile_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `profile_users` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `profile_groups` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `profile_public` int NOT NULL DEFAULT 0,
  `profile_owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`profile_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of notif_profiles
-- ----------------------------

-- ----------------------------
-- Table structure for notif_tags
-- ----------------------------
DROP TABLE IF EXISTS `notif_tags`;
CREATE TABLE `notif_tags`  (
  `tag_id` int NOT NULL AUTO_INCREMENT,
  `tag_title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag_color` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `tag_active` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`tag_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of notif_tags
-- ----------------------------

-- ----------------------------
-- Table structure for notif_user_tags
-- ----------------------------
DROP TABLE IF EXISTS `notif_user_tags`;
CREATE TABLE `notif_user_tags`  (
  `user_tags_id` int NOT NULL AUTO_INCREMENT,
  `login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_sender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`user_tags_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of notif_user_tags
-- ----------------------------

-- ----------------------------
-- Table structure for notification_logs
-- ----------------------------
DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NULL DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `is_read` tinyint NULL DEFAULT 0,
  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of notification_logs
-- ----------------------------
INSERT INTO `notification_logs` VALUES (1, 1, 'Notif masuk!', 1, '2026-04-13 22:26:25');
INSERT INTO `notification_logs` VALUES (2, 1, 'Notif masuk 2!', 1, '2026-04-13 22:35:53');
INSERT INTO `notification_logs` VALUES (3, 1, 'Notif masuk 3!', 1, '2026-04-13 22:36:45');
INSERT INTO `notification_logs` VALUES (4, 1, 'Notif masuk 4!', 1, '2026-04-13 22:37:44');
INSERT INTO `notification_logs` VALUES (5, 1, 'Notif masuk 5!', 1, '2026-04-13 23:56:25');
INSERT INTO `notification_logs` VALUES (6, 1, 'Notif masuk 6!', 1, '2026-04-13 23:58:27');
INSERT INTO `notification_logs` VALUES (7, 1, 'Notif masuk 7!', 1, '2026-04-14 00:01:35');

-- ----------------------------
-- Table structure for sc_log
-- ----------------------------
DROP TABLE IF EXISTS `sc_log`;
CREATE TABLE `sc_log`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `inserted_date` datetime NULL DEFAULT NULL,
  `username` varchar(90) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `application` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `creator` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2299 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sc_log
-- ----------------------------
INSERT INTO `sc_log` VALUES (1, '2026-07-11 10:02:36', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2, '2026-07-11 10:02:40', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (3, '2026-07-11 10:02:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (4, '2026-07-11 10:02:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (5, '2026-07-11 10:02:45', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (6, '2026-07-11 10:02:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (7, '2026-07-11 10:02:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (8, '2026-07-11 10:03:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (9, '2026-07-11 10:03:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (10, '2026-07-11 10:03:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (11, '2026-07-11 10:03:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (12, '2026-07-11 10:03:16', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (13, '2026-07-11 10:03:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (14, '2026-07-11 10:03:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (15, '2026-07-11 10:03:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (16, '2026-07-11 10:03:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (17, '2026-07-11 10:03:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (18, '2026-07-11 10:03:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (19, '2026-07-11 10:03:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (20, '2026-07-11 10:03:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (21, '2026-07-11 10:04:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (22, '2026-07-11 10:04:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (23, '2026-07-11 10:04:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (24, '2026-07-11 10:04:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (25, '2026-07-11 10:04:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (26, '2026-07-11 10:04:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (27, '2026-07-11 10:04:29', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'insert', '--> keys <-- hpp_id : 8||--> fields <-- doc_no (new)  : 26HPP0000008||doc_no (label)  : Doc No||doc_date (new)  : 2026-07-11||doc_date (label)  : Doc Date||doc_status (new)  : DRAFT||doc_status (label)  : Status||i_calc_type (new)  : RECALC||i_calc_type (label)  : Tipe Perhitungan ||revision_no (new)  : 0||revision_no (label)  : No Rev.||customer_id (new)  : 10||customer_id (label)  : Pilih Customer (*)||product_id (new)  : 26||product_id (label)  : Pilih produk yang sudah ada||customer_name (new)  : PAK IFUNG||customer_name (label)  : Nama Calon Customer / Lead||product_name (new)  : DOS WARUNG NASIONAL (DS000465)||product_name (label)  : Product Name||cfg_parts_id (new)  : 0||cfg_parts_id (label)  : Cfg Parts||qty_order_pcs (new)  : 10000.00||qty_order_pcs (label)  : Qty Order Pcs||is_ppn (new)  : 1||is_ppn (label)  : Pilih PPn ||ppn_percent (new)  : 11.00||ppn_percent (label)  : Ppn Percent||margin_percent (new)  : 125.00||margin_percent (label)  : Margin Percent||tolerance_percent (new)  : 3.00||tolerance_percent (label)  : Tolerance Percent||rounding_mode (new)  : NEAREST||rounding_mode (label)  : Rounding Mode||currency_code (new)  : IDR||currency_code (label)  : Currency||notes (new)  : ||notes (label)  : Notes||i_dim_panjang_mm (new)  : 200.00||i_dim_panjang_mm (label)  : Panjang (mm)||i_dim_lebar_mm (new)  : 200.00||i_dim_lebar_mm (label)  : Lebar (mm)||i_dim_tinggi_mm (new)  : 79.00||i_dim_tinggi_mm (label)  : Tinggi (mm)||total_material_cost (new)  : 0||total_material_cost (label)  : Total Material Cost||total_process_cost (new)  : 0||total_process_cost (label)  : Total Process Cost||total_finishing_cost (new)  : 0||total_finishing_cost (label)  : Total Tooling Cost||total_hpp (new)  : 0||total_hpp (label)  : Total Hpp||total_after_margin (new)  : 0||total_after_margin (label)  : Total After Margin||total_after_ppn (new)  : 0||total_after_ppn (label)  : Total After Ppn||price_per_pcs (new)  : 0||price_per_pcs (label)  : Price Per Pcs||create_log (new)  : 2026-07-11 00:00:00||create_log (label)  : Create Log||update_log (new)  : 2026-07-11 00:00:00||update_log (label)  : Update Log||create_by (new)  : wiwik||create_by (label)  : Create By||update_by (new)  : wiwik||update_by (label)  : Update By||stat_confirm (new)  : 0||stat_confirm (label)  : Stat Confirm||stat_cancel (new)  : 0||stat_cancel (label)  : Stat Cancel||stat_closed (new)  : 0||stat_closed (label)  : Stat Closed||stat_failed (new)  : 0||stat_failed (label)  : Stat Failed||deta (new)  : ||deta (label)  : ');
INSERT INTO `sc_log` VALUES (28, '2026-07-11 10:04:29', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (29, '2026-07-11 10:04:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (30, '2026-07-11 10:04:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (31, '2026-07-11 10:04:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (32, '2026-07-11 10:04:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (33, '2026-07-11 10:04:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (34, '2026-07-11 10:04:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (35, '2026-07-11 10:05:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (36, '2026-07-11 10:05:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (37, '2026-07-11 10:05:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (38, '2026-07-11 10:05:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (39, '2026-07-11 10:05:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (40, '2026-07-11 10:05:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (41, '2026-07-11 10:05:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (42, '2026-07-11 10:05:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (43, '2026-07-11 10:05:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (44, '2026-07-11 10:05:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (45, '2026-07-11 10:05:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (46, '2026-07-11 10:05:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (47, '2026-07-11 10:06:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (48, '2026-07-11 10:06:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (49, '2026-07-11 10:06:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (50, '2026-07-11 10:06:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (51, '2026-07-11 10:06:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (52, '2026-07-11 10:06:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (53, '2026-07-11 10:06:24', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'insert', '--> keys <-- hpp_parts_id : 52||--> fields <-- hpp_id (new)  : 8||hpp_id (label)  : {lang_hpp_parts_fld_hpp_id}||no_urut (new)  : 1||no_urut (label)  : No Urut||qty_part (new)  : 10000.00||qty_part (label)  : Qty Part||part_name (new)  : DUS||part_name (label)  : part_name||part_type (new)  : DC||part_type (label)  : {lang_hpp_parts_fld_part_type}||pakai_laminasi (new)  : 0||pakai_laminasi (label)  : Pakai Laminasi||mcard_id (new)  : 0||mcard_id (label)  : {lang_hpp_parts_fld_mcard_id}||gramatur_id (new)  : 11||gramatur_id (label)  : Gramatur Id||i_dc_persen_override (new)  : 5.60||i_dc_persen_override (label)  : I Dc Persen Override||i_panjang_plano_cm (new)  : 70||i_panjang_plano_cm (label)  : I Panjang Plano Cm||i_lebar_plano_cm (new)  : 100||i_lebar_plano_cm (label)  : I Lebar Plano Cm||i_cut_per_plano (new)  : 1||i_cut_per_plano (label)  : {lang_hpp_parts_fld_i_cut_per_plano}||i_isi_per_lb (new)  : 3||i_isi_per_lb (label)  : I Isi Per Lb||i_jumlah_warna (new)  : 4||i_jumlah_warna (label)  : I Jumlah Warna||sheet_rate_id (new)  : 0||sheet_rate_id (label)  : {lang_hpp_parts_fld_sheet_rate_id}||supplier_sheet_code (new)  : UJK||supplier_sheet_code (label)  : Supplier Sheet Code||c_ongkos_lam_pc (new)  : 0||c_ongkos_lam_pc (label)  : C Ongkos Lam Pc||c_sheet_compare_info (new)  : ||c_sheet_compare_info (label)  : C Sheet Compare Info||i_sheet_panjang_cm (new)  : 0.0000||i_sheet_panjang_cm (label)  : {lang_hpp_parts_fld_i_sheet_panjang_cm}||i_sheet_lebar_cm (new)  : 0.0000||i_sheet_lebar_cm (label)  : {lang_hpp_parts_fld_i_sheet_lebar_cm}||i_lebar_trim_cm (new)  : 0.0000||i_lebar_trim_cm (label)  : {lang_hpp_parts_fld_i_lebar_trim_cm}||i_isi_per_trim (new)  : 1||i_isi_per_trim (label)  : {lang_hpp_parts_fld_i_isi_per_trim}||i_discount_rm (new)  : 0.0000||i_discount_rm (label)  : Discrount RM||i_ongkos_cetak_rim (new)  : 150000.00||i_ongkos_cetak_rim (label)  : Ongkos Cetak Rim||i_ongkos_plong_lb (new)  : 75.00||i_ongkos_plong_lb (label)  : Ongkos Plong||i_ongkos_lem_pc (new)  : 0.00||i_ongkos_lem_pc (label)  : Ongkos Lem||i_ongkos_hotprint_pc (new)  : 0.00||i_ongkos_hotprint_pc (label)  : Ongkos hotprint||i_ongkos_kupas_pc (new)  : 10.00||i_ongkos_kupas_pc (label)  : {lang_hpp_parts_fld_i_ongkos_kupas_pc}||i_ongkos_qc_pc (new)  : 10.00||i_ongkos_qc_pc (label)  : {lang_hpp_parts_fld_i_ongkos_qc_pc}||i_ongkos_kirim_pc (new)  : 10.00||i_ongkos_kirim_pc (label)  : {lang_hpp_parts_fld_i_ongkos_kirim_pc}||i_ongkos_print_pc (new)  : 0.00||i_ongkos_print_pc (label)  : {lang_hpp_parts_fld_i_ongkos_print_pc}||c_print_cost_pc (new)  : 0||c_print_cost_pc (label)  : C Print Cost Pc||i_ongkos_diecut_pc (new)  : 0.00||i_ongkos_diecut_pc (label)  : {lang_hpp_parts_fld_i_ongkos_diecut_pc}||i_ongkos_slitter_pc (new)  : 0.00||i_ongkos_slitter_pc (label)  : {lang_hpp_parts_fld_i_ongkos_slitter_pc}||i_ongkos_slotter_pc (new)  : 0.00||i_ongkos_slotter_pc (label)  : {lang_hpp_parts_fld_i_ongkos_slotter_pc}||join_type (new)  : ||join_type (label)  : {lang_hpp_parts_fld_join_type}||packing_type_id (new)  : 2||packing_type_id (label)  : Packing Type Id||i_ongkos_packing_pc (new)  : 0.00||i_ongkos_packing_pc (label)  : I Ongkos Packing Pc||i_ongkos_join_pc (new)  : 0.00||i_ongkos_join_pc (label)  : {lang_hpp_parts_fld_i_ongkos_join_pc}||i_ongkos_partisi_pc (new)  : 0.00||i_ongkos_partisi_pc (label)  : {lang_hpp_parts_fld_i_ongkos_partisi_pc}||i_ongkos_layer_pc (new)  : 0.00||i_ongkos_layer_pc (label)  : {lang_hpp_parts_fld_i_ongkos_layer_pc}||i_ongkos_pisau_pc (new)  : 0.0000||i_ongkos_pisau_pc (label)  : I Ongkos Pisau Pc||i_ongkos_laminasi_pc (new)  : 0.0000||i_ongkos_laminasi_pc (label)  : {lang_hpp_parts_fld_i_ongkos_laminasi_pc}||i_ongkos_lam_rp_cm2_sh (new)  : 0.0000||i_ongkos_lam_rp_cm2_sh (label)  : I Ongkos Lam Rp Cm 2 Sh||i_ongkos_lam_rp_cm2_sf (new)  : 0.0000||i_ongkos_lam_rp_cm2_sf (label)  : I Ongkos Lam Rp Cm 2 Sf||i_lam_sheet_flat_pc (new)  : 0.00||i_lam_sheet_flat_pc (label)  : I Lam Sheet Flat Pc||sf_ef_rate_id (new)  : 0||sf_ef_rate_id (label)  : Sf Ef Rate Id||i_sf_ef_rate_override (new)  : 0||i_sf_ef_rate_override (label)  : I Sf Ef Rate Override||i_lam_pakai_ukuran_sf (new)  : 0||i_lam_pakai_ukuran_sf (label)  : I Lam Pakai Ukuran Sf||film_rate_id (new)  : 0||film_rate_id (label)  : {lang_hpp_parts_fld_film_rate_id}||i_film_faktor (new)  : 0.5000||i_film_faktor (label)  : I Film Faktor||i_pisau_faktor (new)  : 025.0000||i_pisau_faktor (label)  : I Pisau Faktor||i_pisau_rate_rp (new)  : 1000000.0000||i_pisau_rate_rp (label)  : I Pisau Rate Rp||i_ongkos_plate_warna (new)  : 90000.0000||i_ongkos_plate_warna (label)  : I Ongkos Plate Warna||c_uk_cetak_panjang_cm (new)  : 62||c_uk_cetak_panjang_cm (label)  : C Uk Cetak Panjang Cm||c_uk_cetak_lebar_cm (new)  : 100||c_uk_cetak_lebar_cm (label)  : C Uk Cetak Lebar Cm||c_area_cetak_cm2 (new)  : 6200||c_area_cetak_cm2 (label)  : Area Cetak||c_jumlah_cetak_lc (new)  : 3333.3333||c_jumlah_cetak_lc (label)  : Jumlah Cetak||c_jumlah_bahan_lb (new)  : 3333.3333||c_jumlah_bahan_lb (label)  : Jumlah bahan||c_paper_cost_pc (new)  : 1123.1215886657||c_paper_cost_pc (label)  : Ongkos Kertas Pcs||c_sheet_cost_pc (new)  : 0||c_sheet_cost_pc (label)  : C Sheet Cost Pc||c_i_paper (new)  : 11231215.89||c_i_paper (label)  : C I Paper||c_i_sheet_cost (new)  : 0||c_i_sheet_cost (label)  : C I Sheet Cost||c_process_cost_pc (new)  : 155||c_process_cost_pc (label)  : Ongkos Proses||c_tooling_cost_pc (new)  : 2544.525||c_tooling_cost_pc (label)  : Ongkos Tooling||c_total_coating_pc (new)  : 0||c_total_coating_pc (label)  : C Total Coating Pc||c_total_coating_lc (new)  : 0||c_total_coating_lc (label)  : C Total Coating Lc||c_sf_ef_cost_pc (new)  : 0||c_sf_ef_cost_pc (label)  : C Sf Ef Cost Pc||c_i_sf_ef (new)  : 0||c_i_sf_ef (label)  : C I Sf Ef||c_i_total_coating (new)  : 0||c_i_total_coating (label)  : C I Total Coating||c_total_part_cost_pc (new)  : 3822.6466||c_total_part_cost_pc (label)  : c_total_part_cost_pc||c_cetak_cost_pc (new)  : 100||c_cetak_cost_pc (label)  : C Cetak Cost Pc||c_i_cetak (new)  : 1000000||c_i_cetak (label)  : C I Cetak||c_plong_cost_pc (new)  : 25||c_plong_cost_pc (label)  : C Plong Cost Pc||c_i_plong (new)  : 250000||c_i_plong (label)  : C I Plong||c_lem_cost_pc (new)  : 0.00||c_lem_cost_pc (label)  : C Lem Cost Pc||c_i_lem (new)  : 0||c_i_lem (label)  : C I Lem||c_i_hotprint (new)  : 0||c_i_hotprint (label)  : C I Hotprint||c_i_print (new)  : 0||c_i_print (label)  : C I Print||c_i_diecut (new)  : 0||c_i_diecut (label)  : C I Diecut||c_i_slitter (new)  : 0||c_i_slitter (label)  : C I Slitter||c_i_slotter (new)  : 0||c_i_slotter (label)  : C I Slotter||c_i_join (new)  : 0||c_i_join (label)  : C I Join||c_i_partisi (new)  : 0||c_i_partisi (label)  : C I Partisi||c_i_layer (new)  : 0||c_i_layer (label)  : C I Layer||c_i_pisau_sheet (new)  : 0||c_i_pisau_sheet (label)  : C I Pisau Sheet||c_i_kupas (new)  : 100000||c_i_kupas (label)  : C I Kupas||c_i_qc (new)  : 100000||c_i_qc (label)  : C I Qc||c_i_kirim (new)  : 100000||c_i_kirim (label)  : C I Kirim||c_i_packing (new)  : 0||c_i_packing (label)  : C I Packing||c_i_ongkos_lam (new)  : 0||c_i_ongkos_lam (label)  : C I Ongkos Lam||c_i_lam_sheet_flat (new)  : 0||c_i_lam_sheet_flat (label)  : C I Lam Sheet Flat||c_film_cost_pc (new)  : 8.525||c_film_cost_pc (label)  : C Film Cost Pc||c_i_film (new)  : 85250||c_i_film (label)  : C I Film||c_plate_cost_pc (new)  : 36||c_plate_cost_pc (label)  : C Plate Cost Pc||c_i_plate (new)  : 360000||c_i_plate (label)  : C I Plate||c_knife_cost_pc (new)  : 2500||c_knife_cost_pc (label)  : C Knife Cost Pc||c_i_knife (new)  : 25000000||c_i_knife (label)  : C I Knife||calc_notes (new)  : ||calc_notes (label)  : {lang_hpp_parts_fld_calc_notes}||description_calc (new)  : v14 11/07/2026 10:06:24 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=2544.53 sheet=0 TOTAL=3822.65 flag=PAPER_OK COAT_SKIP LAM_SKIP TOOL_OK ||description_calc (label)  : Description Calc||created_by (new)  : wiwik||created_by (label)  : {lang_hpp_parts_fld_created_by}||created_log (new)  : 2026-07-11 10:06:24||created_log (label)  : {lang_hpp_parts_fld_created_log}||updated_by (new)  : wiwik||updated_by (label)  : {lang_hpp_parts_fld_updated_by}||updated_log (new)  : 2026-07-11 10:06:24||updated_log (label)  : {lang_hpp_parts_fld_updated_log}||deta_coat (new)  : ||deta_coat (label)  : Coating');
INSERT INTO `sc_log` VALUES (54, '2026-07-11 10:06:24', 'wiwik', 'form_hpp_parts_coating', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (55, '2026-07-11 10:06:28', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_parts_id : 52||--> fields <-- c_paper_cost_pc (old)  : 1123.1216||c_paper_cost_pc (new)  : 1123.1215886657||c_paper_cost_pc (label)  : Ongkos Kertas Pcs||description_calc (old)  : v14 11/07/2026 10:06:24 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=2544.53 sheet=0 TOTAL=3822.65 flag=PAPER_OK COAT_SKIP LAM_SKIP TOOL_OK ||description_calc (new)  : v14 11/07/2026 10:06:28 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=2544.53 sheet=0 TOTAL=3822.65 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (label)  : Description Calc||created_log (old)  : 2026-07-11 10:06:24||created_log (new)  : 2026-07-11 00:00:00||created_log (label)  : {lang_hpp_parts_fld_created_log}||updated_log (old)  : 2026-07-11 10:06:24||updated_log (new)  : 2026-07-11 10:06:28||updated_log (label)  : {lang_hpp_parts_fld_updated_log}');
INSERT INTO `sc_log` VALUES (56, '2026-07-11 10:06:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (57, '2026-07-11 10:06:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (58, '2026-07-11 10:06:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (59, '2026-07-11 10:06:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (60, '2026-07-11 10:06:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (61, '2026-07-11 10:06:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (62, '2026-07-11 10:07:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (63, '2026-07-11 10:07:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (64, '2026-07-11 10:07:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (65, '2026-07-11 10:07:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (66, '2026-07-11 10:07:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (67, '2026-07-11 10:07:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (68, '2026-07-11 10:07:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (69, '2026-07-11 10:07:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (70, '2026-07-11 10:07:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (71, '2026-07-11 10:07:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (72, '2026-07-11 10:07:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (73, '2026-07-11 10:07:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (74, '2026-07-11 10:08:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (75, '2026-07-11 10:08:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (76, '2026-07-11 10:08:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (77, '2026-07-11 10:08:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (78, '2026-07-11 10:08:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (79, '2026-07-11 10:08:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (80, '2026-07-11 10:08:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (81, '2026-07-11 10:08:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (82, '2026-07-11 10:08:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (83, '2026-07-11 10:08:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (84, '2026-07-11 10:08:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (85, '2026-07-11 10:08:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (86, '2026-07-11 10:09:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (87, '2026-07-11 10:09:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (88, '2026-07-11 10:09:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (89, '2026-07-11 10:09:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (90, '2026-07-11 10:09:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (91, '2026-07-11 10:09:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (92, '2026-07-11 10:09:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (93, '2026-07-11 10:09:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (94, '2026-07-11 10:09:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (95, '2026-07-11 10:09:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (96, '2026-07-11 10:09:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (97, '2026-07-11 10:09:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (98, '2026-07-11 10:10:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (99, '2026-07-11 10:10:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (100, '2026-07-11 10:10:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (101, '2026-07-11 10:10:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (102, '2026-07-11 10:10:14', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_parts_id : 52||--> fields <-- film_rate_id (old)  : 0||film_rate_id (new)  : 45||film_rate_id (label)  : {lang_hpp_parts_fld_film_rate_id}||c_paper_cost_pc (old)  : 1123.1216||c_paper_cost_pc (new)  : 1123.1215886657||c_paper_cost_pc (label)  : Ongkos Kertas Pcs||c_tooling_cost_pc (old)  : 2544.5250||c_tooling_cost_pc (new)  : 2542.975||c_tooling_cost_pc (label)  : Ongkos Tooling||c_total_part_cost_pc (old)  : 3822.6466||c_total_part_cost_pc (new)  : 3821.0966||c_total_part_cost_pc (label)  : c_total_part_cost_pc||c_film_cost_pc (old)  : 8.5250||c_film_cost_pc (new)  : 6.975||c_film_cost_pc (label)  : C Film Cost Pc||c_i_film (old)  : 85250.00||c_i_film (new)  : 69750||c_i_film (label)  : C I Film||description_calc (old)  : v14 11/07/2026 10:06:28 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=2544.53 sheet=0 TOTAL=3822.65 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (new)  : v14 11/07/2026 10:10:14 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=2542.98 sheet=0 TOTAL=3821.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (label)  : Description Calc||updated_log (old)  : 2026-07-11 10:06:28||updated_log (new)  : 2026-07-11 10:10:14||updated_log (label)  : {lang_hpp_parts_fld_updated_log}');
INSERT INTO `sc_log` VALUES (103, '2026-07-11 10:10:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (104, '2026-07-11 10:10:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (105, '2026-07-11 10:10:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (106, '2026-07-11 10:10:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (107, '2026-07-11 10:10:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (108, '2026-07-11 10:10:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (109, '2026-07-11 10:10:51', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (110, '2026-07-11 10:10:51', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (111, '2026-07-11 10:11:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (112, '2026-07-11 10:11:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (113, '2026-07-11 10:11:06', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_parts_id : 52||--> fields <-- i_pisau_faktor (old)  : 25.0000||i_pisau_faktor (new)  : 0.2500||i_pisau_faktor (label)  : I Pisau Faktor||c_paper_cost_pc (old)  : 1123.1216||c_paper_cost_pc (new)  : 1123.1215886657||c_paper_cost_pc (label)  : Ongkos Kertas Pcs||c_tooling_cost_pc (old)  : 2542.9750||c_tooling_cost_pc (new)  : 67.975||c_tooling_cost_pc (label)  : Ongkos Tooling||c_total_part_cost_pc (old)  : 3821.0966||c_total_part_cost_pc (new)  : 1346.0966||c_total_part_cost_pc (label)  : c_total_part_cost_pc||c_knife_cost_pc (old)  : 2500.0000||c_knife_cost_pc (new)  : 25||c_knife_cost_pc (label)  : C Knife Cost Pc||c_i_knife (old)  : 25000000.00||c_i_knife (new)  : 250000||c_i_knife (label)  : C I Knife||description_calc (old)  : v14 11/07/2026 10:10:14 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=2542.98 sheet=0 TOTAL=3821.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (new)  : v14 11/07/2026 10:11:06 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=67.98 sheet=0 TOTAL=1346.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (label)  : Description Calc||updated_log (old)  : 2026-07-11 10:10:14||updated_log (new)  : 2026-07-11 10:11:06||updated_log (label)  : {lang_hpp_parts_fld_updated_log}');
INSERT INTO `sc_log` VALUES (114, '2026-07-11 10:11:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (115, '2026-07-11 10:11:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (116, '2026-07-11 10:11:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (117, '2026-07-11 10:11:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (118, '2026-07-11 10:11:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (119, '2026-07-11 10:11:32', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (120, '2026-07-11 10:11:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (121, '2026-07-11 10:11:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (122, '2026-07-11 10:11:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (123, '2026-07-11 10:11:51', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (124, '2026-07-11 10:12:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (125, '2026-07-11 10:12:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (126, '2026-07-11 10:12:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (127, '2026-07-11 10:12:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (128, '2026-07-11 10:12:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (129, '2026-07-11 10:12:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (130, '2026-07-11 10:13:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (131, '2026-07-11 10:13:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (132, '2026-07-11 10:13:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (133, '2026-07-11 10:13:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (134, '2026-07-11 10:13:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (135, '2026-07-11 10:13:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (136, '2026-07-11 10:13:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (137, '2026-07-11 10:13:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (138, '2026-07-11 10:13:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (139, '2026-07-11 10:13:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (140, '2026-07-11 10:14:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (141, '2026-07-11 10:14:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (142, '2026-07-11 10:14:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (143, '2026-07-11 10:14:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (144, '2026-07-11 10:14:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (145, '2026-07-11 10:14:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (146, '2026-07-11 10:14:28', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_parts_id : 52||--> fields <-- c_paper_cost_pc (old)  : 1123.1216||c_paper_cost_pc (new)  : 1123.1215886657||c_paper_cost_pc (label)  : Ongkos Kertas Pcs||description_calc (old)  : v14 11/07/2026 10:11:06 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=67.98 sheet=0 TOTAL=1346.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (new)  : v14 11/07/2026 10:14:28 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=67.98 sheet=0 TOTAL=1346.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (label)  : Description Calc||updated_log (old)  : 2026-07-11 10:11:06||updated_log (new)  : 2026-07-11 10:14:28||updated_log (label)  : {lang_hpp_parts_fld_updated_log}');
INSERT INTO `sc_log` VALUES (147, '2026-07-11 10:14:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (148, '2026-07-11 10:14:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (149, '2026-07-11 10:14:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (150, '2026-07-11 10:14:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (151, '2026-07-11 10:14:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (152, '2026-07-11 10:14:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (153, '2026-07-11 10:14:52', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_id : 8||--> fields <-- margin_percent (old)  : 125.00||margin_percent (new)  : 12.50||margin_percent (label)  : Margin Percent||total_material_cost (old)  : 0.0000||total_material_cost (new)  : 1123.12||total_material_cost (label)  : Total Material Cost||total_process_cost (old)  : 0.0000||total_process_cost (new)  : 155.00||total_process_cost (label)  : Total Process Cost||total_finishing_cost (old)  : 0.0000||total_finishing_cost (new)  : 67.97||total_finishing_cost (label)  : Total Tooling Cost||total_hpp (old)  : 0.0000||total_hpp (new)  : 1346.0966||total_hpp (label)  : Total Hpp||total_after_margin (old)  : 0.0000||total_after_margin (new)  : 1584.54||total_after_margin (label)  : Total After Margin||total_after_ppn (old)  : 0.0000||total_after_ppn (new)  : 1629.32||total_after_ppn (label)  : Total After Ppn||price_per_pcs (old)  : 0.0000||price_per_pcs (new)  : 1386.4795||price_per_pcs (label)  : Price Per Pcs');
INSERT INTO `sc_log` VALUES (154, '2026-07-11 10:15:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (155, '2026-07-11 10:15:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (156, '2026-07-11 10:15:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (157, '2026-07-11 10:15:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (158, '2026-07-11 10:15:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (159, '2026-07-11 10:15:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (160, '2026-07-11 10:15:31', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (161, '2026-07-11 10:15:31', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (162, '2026-07-11 10:15:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (163, '2026-07-11 10:15:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (164, '2026-07-11 10:15:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (165, '2026-07-11 10:15:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (166, '2026-07-11 10:16:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (167, '2026-07-11 10:16:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (168, '2026-07-11 10:16:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (169, '2026-07-11 10:16:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (170, '2026-07-11 10:16:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (171, '2026-07-11 10:16:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (172, '2026-07-11 10:16:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (173, '2026-07-11 10:16:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (174, '2026-07-11 10:16:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (175, '2026-07-11 10:16:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (176, '2026-07-11 10:16:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (177, '2026-07-11 10:16:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (178, '2026-07-11 10:17:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (179, '2026-07-11 10:17:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (180, '2026-07-11 10:17:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (181, '2026-07-11 10:17:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (182, '2026-07-11 10:17:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (183, '2026-07-11 10:17:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (184, '2026-07-11 10:17:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (185, '2026-07-11 10:17:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (186, '2026-07-11 10:17:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (187, '2026-07-11 10:17:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (188, '2026-07-11 10:17:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (189, '2026-07-11 10:17:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (190, '2026-07-11 10:18:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (191, '2026-07-11 10:18:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (192, '2026-07-11 10:19:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (193, '2026-07-11 10:19:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (194, '2026-07-11 10:20:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (195, '2026-07-11 10:20:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (196, '2026-07-11 10:21:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (197, '2026-07-11 10:21:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (198, '2026-07-11 10:22:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (199, '2026-07-11 10:22:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (200, '2026-07-11 10:23:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (201, '2026-07-11 10:23:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (202, '2026-07-11 10:24:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (203, '2026-07-11 10:24:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (204, '2026-07-11 10:25:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (205, '2026-07-11 10:25:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (206, '2026-07-11 10:26:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (207, '2026-07-11 10:26:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (208, '2026-07-11 10:27:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (209, '2026-07-11 10:27:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (210, '2026-07-11 10:28:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (211, '2026-07-11 10:28:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (212, '2026-07-11 10:29:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (213, '2026-07-11 10:29:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (214, '2026-07-11 10:30:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (215, '2026-07-11 10:30:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (216, '2026-07-11 10:31:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (217, '2026-07-11 10:31:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (218, '2026-07-11 10:32:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (219, '2026-07-11 10:32:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (220, '2026-07-11 10:33:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (221, '2026-07-11 10:33:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (222, '2026-07-11 10:34:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (223, '2026-07-11 10:34:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (224, '2026-07-11 10:35:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (225, '2026-07-11 10:35:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (226, '2026-07-11 10:36:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (227, '2026-07-11 10:36:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (228, '2026-07-11 10:37:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (229, '2026-07-11 10:37:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (230, '2026-07-11 10:38:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (231, '2026-07-11 10:38:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (232, '2026-07-11 10:38:24', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (233, '2026-07-11 10:38:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (234, '2026-07-11 10:38:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (235, '2026-07-11 10:38:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (236, '2026-07-11 10:38:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (237, '2026-07-11 10:38:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (238, '2026-07-11 10:38:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (239, '2026-07-11 10:38:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (240, '2026-07-11 10:39:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (241, '2026-07-11 10:39:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (242, '2026-07-11 10:39:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (243, '2026-07-11 10:39:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (244, '2026-07-11 10:39:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (245, '2026-07-11 10:39:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (246, '2026-07-11 10:39:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (247, '2026-07-11 10:39:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (248, '2026-07-11 10:39:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (249, '2026-07-11 10:39:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (250, '2026-07-11 10:39:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (251, '2026-07-11 10:39:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (252, '2026-07-11 10:40:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (253, '2026-07-11 10:40:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (254, '2026-07-11 10:40:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (255, '2026-07-11 10:40:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (256, '2026-07-11 10:41:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (257, '2026-07-11 10:41:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (258, '2026-07-11 10:42:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (259, '2026-07-11 10:42:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (260, '2026-07-11 10:43:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (261, '2026-07-11 10:43:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (262, '2026-07-11 10:43:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (263, '2026-07-11 10:43:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (264, '2026-07-11 10:43:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (265, '2026-07-11 10:43:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (266, '2026-07-11 10:44:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (267, '2026-07-11 10:44:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (268, '2026-07-11 10:44:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (269, '2026-07-11 10:44:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (270, '2026-07-11 10:44:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (271, '2026-07-11 10:44:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (272, '2026-07-11 10:44:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (273, '2026-07-11 10:44:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (274, '2026-07-11 10:44:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (275, '2026-07-11 10:44:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (276, '2026-07-11 10:44:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (277, '2026-07-11 10:44:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (278, '2026-07-11 10:45:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (279, '2026-07-11 10:45:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (280, '2026-07-11 10:45:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (281, '2026-07-11 10:45:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (282, '2026-07-11 10:45:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (283, '2026-07-11 10:45:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (284, '2026-07-11 10:45:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (285, '2026-07-11 10:45:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (286, '2026-07-11 10:45:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (287, '2026-07-11 10:45:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (288, '2026-07-11 10:45:51', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (289, '2026-07-11 10:45:51', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (290, '2026-07-11 10:46:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (291, '2026-07-11 10:46:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (292, '2026-07-11 10:46:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (293, '2026-07-11 10:46:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (294, '2026-07-11 10:46:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (295, '2026-07-11 10:46:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (296, '2026-07-11 10:46:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (297, '2026-07-11 10:46:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (298, '2026-07-11 10:46:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (299, '2026-07-11 10:46:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (300, '2026-07-11 10:46:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (301, '2026-07-11 10:46:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (302, '2026-07-11 10:47:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (303, '2026-07-11 10:47:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (304, '2026-07-11 10:47:02', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (305, '2026-07-11 10:47:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (306, '2026-07-11 10:47:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (307, '2026-07-11 10:47:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (308, '2026-07-11 10:47:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (309, '2026-07-11 10:47:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (310, '2026-07-11 10:47:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (311, '2026-07-11 10:47:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (312, '2026-07-11 10:47:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (313, '2026-07-11 10:47:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (314, '2026-07-11 10:47:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (315, '2026-07-11 10:48:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (316, '2026-07-11 10:48:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (317, '2026-07-11 10:48:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (318, '2026-07-11 10:48:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (319, '2026-07-11 10:49:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (320, '2026-07-11 10:49:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (321, '2026-07-11 10:50:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (322, '2026-07-11 10:50:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (323, '2026-07-11 10:51:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (324, '2026-07-11 10:51:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (325, '2026-07-11 10:52:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (326, '2026-07-11 10:52:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (327, '2026-07-11 10:53:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (328, '2026-07-11 10:53:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (329, '2026-07-11 10:54:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (330, '2026-07-11 10:54:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (331, '2026-07-11 10:55:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (332, '2026-07-11 10:55:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (333, '2026-07-11 10:56:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (334, '2026-07-11 10:56:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (335, '2026-07-11 10:57:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (336, '2026-07-11 10:57:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (337, '2026-07-11 10:58:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (338, '2026-07-11 10:58:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (339, '2026-07-11 10:59:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (340, '2026-07-11 10:59:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (341, '2026-07-11 11:00:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (342, '2026-07-11 11:00:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (343, '2026-07-11 11:01:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (344, '2026-07-11 11:01:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (345, '2026-07-11 11:02:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (346, '2026-07-11 11:02:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (347, '2026-07-11 11:03:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (348, '2026-07-11 11:03:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (349, '2026-07-11 11:04:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (350, '2026-07-11 11:04:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (351, '2026-07-11 11:05:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (352, '2026-07-11 11:05:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (353, '2026-07-11 11:06:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (354, '2026-07-11 11:06:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (355, '2026-07-11 11:07:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (356, '2026-07-11 11:07:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (357, '2026-07-11 11:08:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (358, '2026-07-11 11:08:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (359, '2026-07-11 11:09:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (360, '2026-07-11 11:09:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (361, '2026-07-11 11:10:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (362, '2026-07-11 11:10:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (363, '2026-07-11 11:11:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (364, '2026-07-11 11:11:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (365, '2026-07-11 11:12:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (366, '2026-07-11 11:12:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (367, '2026-07-11 11:13:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (368, '2026-07-11 11:13:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (369, '2026-07-11 11:14:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (370, '2026-07-11 11:14:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (371, '2026-07-11 11:15:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (372, '2026-07-11 11:15:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (373, '2026-07-11 11:16:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (374, '2026-07-11 11:16:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (375, '2026-07-11 11:17:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (376, '2026-07-11 11:17:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (377, '2026-07-11 11:18:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (378, '2026-07-11 11:18:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (379, '2026-07-11 11:19:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (380, '2026-07-11 11:19:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (381, '2026-07-11 11:20:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (382, '2026-07-11 11:20:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (383, '2026-07-11 11:21:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (384, '2026-07-11 11:21:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (385, '2026-07-11 11:22:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (386, '2026-07-11 11:22:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (387, '2026-07-11 11:23:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (388, '2026-07-11 11:23:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (389, '2026-07-11 11:24:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (390, '2026-07-11 11:24:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (391, '2026-07-11 11:25:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (392, '2026-07-11 11:25:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (393, '2026-07-11 11:26:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (394, '2026-07-11 11:26:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (395, '2026-07-11 11:27:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (396, '2026-07-11 11:27:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (397, '2026-07-11 11:28:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (398, '2026-07-11 11:28:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (399, '2026-07-11 11:29:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (400, '2026-07-11 11:29:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (401, '2026-07-11 11:30:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (402, '2026-07-11 11:30:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (403, '2026-07-11 11:31:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (404, '2026-07-11 11:31:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (405, '2026-07-11 11:32:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (406, '2026-07-11 11:32:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (407, '2026-07-11 11:33:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (408, '2026-07-11 11:33:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (409, '2026-07-11 11:34:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (410, '2026-07-11 11:34:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (411, '2026-07-11 11:35:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (412, '2026-07-11 11:35:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (413, '2026-07-11 11:36:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (414, '2026-07-11 11:36:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (415, '2026-07-11 11:37:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (416, '2026-07-11 11:37:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (417, '2026-07-11 11:38:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (418, '2026-07-11 11:38:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (419, '2026-07-11 11:39:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (420, '2026-07-11 11:39:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (421, '2026-07-11 11:40:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (422, '2026-07-11 11:40:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (423, '2026-07-11 11:41:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (424, '2026-07-11 11:41:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (425, '2026-07-11 11:42:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (426, '2026-07-11 11:42:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (427, '2026-07-11 11:43:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (428, '2026-07-11 11:43:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (429, '2026-07-11 11:44:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (430, '2026-07-11 11:44:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (431, '2026-07-11 11:45:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (432, '2026-07-11 11:45:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (433, '2026-07-11 11:46:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (434, '2026-07-11 11:46:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (435, '2026-07-11 11:47:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (436, '2026-07-11 11:47:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (437, '2026-07-11 11:48:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (438, '2026-07-11 11:48:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (439, '2026-07-11 11:49:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (440, '2026-07-11 11:49:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (441, '2026-07-11 15:55:01', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (442, '2026-07-11 15:55:09', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (443, '2026-07-11 15:55:34', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (444, '2026-07-11 15:58:22', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (445, '2026-07-11 15:58:31', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (446, '2026-07-11 15:58:31', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (447, '2026-07-11 15:58:32', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (448, '2026-07-11 15:58:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (449, '2026-07-11 15:58:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (450, '2026-07-11 15:58:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (451, '2026-07-11 15:58:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (452, '2026-07-11 15:58:48', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (453, '2026-07-11 15:58:51', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (454, '2026-07-11 15:58:51', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (455, '2026-07-11 15:58:51', 'wiwik', 'form_hpp_parts_coating', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (456, '2026-07-11 15:58:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (457, '2026-07-11 15:58:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (458, '2026-07-11 15:59:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (459, '2026-07-11 15:59:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (460, '2026-07-11 15:59:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (461, '2026-07-11 15:59:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (462, '2026-07-11 15:59:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (463, '2026-07-11 15:59:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (464, '2026-07-11 15:59:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (465, '2026-07-11 15:59:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (466, '2026-07-11 15:59:44', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_parts_id : 52||--> fields <-- i_ongkos_cetak_rim (old)  : 150000.0000||i_ongkos_cetak_rim (new)  : 135000.00||i_ongkos_cetak_rim (label)  : Ongkos Cetak Rim||c_paper_cost_pc (old)  : 1123.1216||c_paper_cost_pc (new)  : 1123.1215886657||c_paper_cost_pc (label)  : Ongkos Kertas Pcs||c_process_cost_pc (old)  : 155.0000||c_process_cost_pc (new)  : 145||c_process_cost_pc (label)  : Ongkos Proses||c_total_part_cost_pc (old)  : 1346.0966||c_total_part_cost_pc (new)  : 1336.0966||c_total_part_cost_pc (label)  : c_total_part_cost_pc||c_cetak_cost_pc (old)  : 100.0000||c_cetak_cost_pc (new)  : 90||c_cetak_cost_pc (label)  : C Cetak Cost Pc||c_i_cetak (old)  : 1000000.00||c_i_cetak (new)  : 900000||c_i_cetak (label)  : C I Cetak||description_calc (old)  : v14 11/07/2026 10:14:28 DC paper=1123.12 proc=155 coat=0 lam=0 sfef=0 tool=67.98 sheet=0 TOTAL=1346.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (new)  : v14 11/07/2026 15:59:44 DC paper=1123.12 proc=145 coat=0 lam=0 sfef=0 tool=67.98 sheet=0 TOTAL=1336.1 flag=PAPER_OK COAT_OK LAM_SKIP TOOL_OK ||description_calc (label)  : Description Calc||updated_log (old)  : 2026-07-11 10:14:28||updated_log (new)  : 2026-07-11 15:59:44||updated_log (label)  : {lang_hpp_parts_fld_updated_log}');
INSERT INTO `sc_log` VALUES (467, '2026-07-11 15:59:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (468, '2026-07-11 15:59:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (469, '2026-07-11 15:59:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (470, '2026-07-11 15:59:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (471, '2026-07-11 16:00:06', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (472, '2026-07-11 16:00:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (473, '2026-07-11 16:00:32', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (474, '2026-07-11 16:00:34', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (475, '2026-07-11 16:00:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (476, '2026-07-11 16:00:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (477, '2026-07-11 16:00:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (478, '2026-07-11 16:00:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (479, '2026-07-11 16:00:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (480, '2026-07-11 16:00:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (481, '2026-07-11 16:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (482, '2026-07-11 16:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (483, '2026-07-11 16:01:08', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (484, '2026-07-11 16:01:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (485, '2026-07-11 16:02:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (486, '2026-07-11 16:02:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (487, '2026-07-11 16:04:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (488, '2026-07-11 16:04:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (489, '2026-07-11 16:05:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (490, '2026-07-11 16:05:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (491, '2026-07-11 16:06:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (492, '2026-07-11 16:06:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (493, '2026-07-11 16:07:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (494, '2026-07-11 16:07:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (495, '2026-07-11 16:08:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (496, '2026-07-11 16:08:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (497, '2026-07-11 16:09:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (498, '2026-07-11 16:09:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (499, '2026-07-11 16:10:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (500, '2026-07-11 16:10:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (501, '2026-07-11 16:11:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (502, '2026-07-11 16:11:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (503, '2026-07-11 16:12:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (504, '2026-07-11 16:12:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (505, '2026-07-11 16:13:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (506, '2026-07-11 16:13:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (507, '2026-07-11 16:14:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (508, '2026-07-11 16:14:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (509, '2026-07-11 16:15:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (510, '2026-07-11 16:15:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (511, '2026-07-11 16:16:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (512, '2026-07-11 16:16:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (513, '2026-07-11 16:17:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (514, '2026-07-11 16:17:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (515, '2026-07-11 16:18:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (516, '2026-07-11 16:18:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (517, '2026-07-11 16:19:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (518, '2026-07-11 16:19:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (519, '2026-07-11 16:19:54', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (520, '2026-07-11 16:19:54', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (521, '2026-07-11 16:19:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (522, '2026-07-11 16:19:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (523, '2026-07-11 16:20:06', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (524, '2026-07-11 16:20:06', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (525, '2026-07-11 16:20:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (526, '2026-07-11 16:20:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (527, '2026-07-11 16:20:20', 'wiwik', 'grid_m_join_type', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (528, '2026-07-11 16:20:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (529, '2026-07-11 16:20:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (530, '2026-07-11 16:20:31', 'wiwik', 'grid_m_packing_type', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (531, '2026-07-11 16:20:34', 'wiwik', 'grid_m_part_type', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (532, '2026-07-11 16:20:35', 'wiwik', 'grid_m_paper_grammage', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (533, '2026-07-11 16:20:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (534, '2026-07-11 16:20:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (535, '2026-07-11 16:20:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (536, '2026-07-11 16:20:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (537, '2026-07-11 16:20:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (538, '2026-07-11 16:20:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (539, '2026-07-11 16:21:06', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (540, '2026-07-11 16:21:06', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (541, '2026-07-11 16:21:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (542, '2026-07-11 16:21:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (543, '2026-07-11 16:21:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (544, '2026-07-11 16:21:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (545, '2026-07-11 16:21:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (546, '2026-07-11 16:21:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (547, '2026-07-11 16:21:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (548, '2026-07-11 16:21:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (549, '2026-07-11 16:22:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (550, '2026-07-11 16:22:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (551, '2026-07-11 16:23:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (552, '2026-07-11 16:23:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (553, '2026-07-11 16:24:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (554, '2026-07-11 16:24:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (555, '2026-07-11 16:25:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (556, '2026-07-11 16:25:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (557, '2026-07-11 16:28:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (558, '2026-07-11 16:28:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (559, '2026-07-11 16:28:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (560, '2026-07-11 16:28:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (561, '2026-07-11 16:29:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (562, '2026-07-11 16:29:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (563, '2026-07-11 16:30:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (564, '2026-07-11 16:30:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (565, '2026-07-11 16:31:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (566, '2026-07-11 16:31:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (567, '2026-07-11 16:32:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (568, '2026-07-11 16:32:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (569, '2026-07-11 16:33:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (570, '2026-07-11 16:33:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (571, '2026-07-11 16:34:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (572, '2026-07-11 16:34:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (573, '2026-07-11 16:35:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (574, '2026-07-11 16:35:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (575, '2026-07-11 16:36:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (576, '2026-07-11 16:36:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (577, '2026-07-11 16:37:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (578, '2026-07-11 16:37:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (579, '2026-07-11 16:38:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (580, '2026-07-11 16:38:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (581, '2026-07-11 16:39:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (582, '2026-07-11 16:39:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (583, '2026-07-11 16:40:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (584, '2026-07-11 16:40:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (585, '2026-07-11 16:41:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (586, '2026-07-11 16:41:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (587, '2026-07-11 16:42:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (588, '2026-07-11 16:42:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (589, '2026-07-11 16:43:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (590, '2026-07-11 16:43:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (591, '2026-07-11 16:44:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (592, '2026-07-11 16:44:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (593, '2026-07-11 16:45:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (594, '2026-07-11 16:45:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (595, '2026-07-11 16:46:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (596, '2026-07-11 16:46:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (597, '2026-07-11 16:47:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (598, '2026-07-11 16:47:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (599, '2026-07-11 16:48:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (600, '2026-07-11 16:48:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (601, '2026-07-11 16:49:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (602, '2026-07-11 16:49:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (603, '2026-07-11 16:50:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (604, '2026-07-11 16:50:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (605, '2026-07-11 16:51:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (606, '2026-07-11 16:51:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (607, '2026-07-11 16:52:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (608, '2026-07-11 16:52:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (609, '2026-07-11 16:53:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (610, '2026-07-11 16:53:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (611, '2026-07-11 16:59:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (612, '2026-07-11 16:59:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (613, '2026-07-11 17:00:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (614, '2026-07-11 17:00:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (615, '2026-07-11 17:00:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (616, '2026-07-11 17:00:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (617, '2026-07-11 17:00:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (618, '2026-07-11 17:00:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (619, '2026-07-11 17:00:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (620, '2026-07-11 17:00:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (621, '2026-07-13 10:21:14', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (622, '2026-07-13 10:25:41', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (623, '2026-07-13 10:31:32', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (624, '2026-07-13 10:31:32', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (625, '2026-07-13 10:31:35', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (626, '2026-07-13 10:31:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (627, '2026-07-13 10:31:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (628, '2026-07-13 10:31:39', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (629, '2026-07-13 10:31:42', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (630, '2026-07-13 10:31:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (631, '2026-07-13 10:31:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (632, '2026-07-13 10:31:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (633, '2026-07-13 10:31:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (634, '2026-07-13 10:32:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (635, '2026-07-13 10:32:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (636, '2026-07-13 10:32:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (637, '2026-07-13 10:32:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (638, '2026-07-13 10:32:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (639, '2026-07-13 10:32:26', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (640, '2026-07-13 10:32:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (641, '2026-07-13 10:32:36', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (642, '2026-07-13 10:32:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (643, '2026-07-13 10:32:46', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (644, '2026-07-13 10:32:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (645, '2026-07-13 10:32:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (646, '2026-07-13 10:33:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (647, '2026-07-13 10:33:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (648, '2026-07-13 10:34:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (649, '2026-07-13 10:34:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (650, '2026-07-13 10:35:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (651, '2026-07-13 10:35:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (652, '2026-07-13 10:36:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (653, '2026-07-13 10:36:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (654, '2026-07-13 10:37:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (655, '2026-07-13 10:37:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (656, '2026-07-13 10:38:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (657, '2026-07-13 10:38:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (658, '2026-07-13 10:39:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (659, '2026-07-13 10:39:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (660, '2026-07-13 10:40:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (661, '2026-07-13 10:40:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (662, '2026-07-13 10:41:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (663, '2026-07-13 10:41:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (664, '2026-07-13 10:42:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (665, '2026-07-13 10:42:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (666, '2026-07-13 10:43:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (667, '2026-07-13 10:43:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (668, '2026-07-13 10:44:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (669, '2026-07-13 10:44:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (670, '2026-07-13 10:45:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (671, '2026-07-13 10:45:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (672, '2026-07-13 10:46:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (673, '2026-07-13 10:46:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (674, '2026-07-13 10:47:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (675, '2026-07-13 10:47:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (676, '2026-07-13 10:48:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (677, '2026-07-13 10:48:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (678, '2026-07-13 10:49:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (679, '2026-07-13 10:49:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (680, '2026-07-13 10:50:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (681, '2026-07-13 10:50:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (682, '2026-07-13 10:51:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (683, '2026-07-13 10:51:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (684, '2026-07-13 10:52:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (685, '2026-07-13 10:52:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (686, '2026-07-13 10:53:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (687, '2026-07-13 10:53:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (688, '2026-07-13 10:54:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (689, '2026-07-13 10:54:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (690, '2026-07-13 10:55:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (691, '2026-07-13 10:55:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (692, '2026-07-13 10:56:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (693, '2026-07-13 10:56:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (694, '2026-07-13 10:57:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (695, '2026-07-13 10:57:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (696, '2026-07-13 10:58:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (697, '2026-07-13 10:58:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (698, '2026-07-13 10:59:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (699, '2026-07-13 10:59:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (700, '2026-07-13 11:00:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (701, '2026-07-13 11:00:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (702, '2026-07-13 11:01:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (703, '2026-07-13 11:01:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (704, '2026-07-13 11:02:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (705, '2026-07-13 11:02:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (706, '2026-07-13 11:03:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (707, '2026-07-13 11:03:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (708, '2026-07-13 11:04:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (709, '2026-07-13 11:04:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (710, '2026-07-13 11:05:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (711, '2026-07-13 11:05:14', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (712, '2026-07-13 11:06:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (713, '2026-07-13 11:06:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (714, '2026-07-13 11:07:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (715, '2026-07-13 11:07:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (716, '2026-07-13 11:08:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (717, '2026-07-13 11:08:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (718, '2026-07-13 11:09:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (719, '2026-07-13 11:09:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (720, '2026-07-13 11:10:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (721, '2026-07-13 11:10:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (722, '2026-07-13 11:11:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (723, '2026-07-13 11:11:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (724, '2026-07-13 11:12:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (725, '2026-07-13 11:12:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (726, '2026-07-13 11:13:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (727, '2026-07-13 11:13:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (728, '2026-07-13 11:14:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (729, '2026-07-13 11:14:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (730, '2026-07-13 11:15:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (731, '2026-07-13 11:15:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (732, '2026-07-13 11:16:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (733, '2026-07-13 11:16:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (734, '2026-07-13 11:17:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (735, '2026-07-13 11:17:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (736, '2026-07-13 11:18:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (737, '2026-07-13 11:18:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (738, '2026-07-13 11:19:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (739, '2026-07-13 11:19:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (740, '2026-07-13 11:20:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (741, '2026-07-13 11:20:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (742, '2026-07-13 11:21:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (743, '2026-07-13 11:21:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (744, '2026-07-13 11:22:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (745, '2026-07-13 11:22:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (746, '2026-07-13 11:23:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (747, '2026-07-13 11:23:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (748, '2026-07-13 11:24:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (749, '2026-07-13 11:24:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (750, '2026-07-13 11:25:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (751, '2026-07-13 11:25:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (752, '2026-07-13 11:26:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (753, '2026-07-13 11:26:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (754, '2026-07-13 11:28:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (755, '2026-07-13 11:28:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (756, '2026-07-13 11:29:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (757, '2026-07-13 11:29:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (758, '2026-07-13 11:30:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (759, '2026-07-13 11:30:21', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (760, '2026-07-13 11:31:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (761, '2026-07-13 11:31:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (762, '2026-07-13 11:32:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (763, '2026-07-13 11:32:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (764, '2026-07-13 11:33:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (765, '2026-07-13 11:33:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (766, '2026-07-13 11:34:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (767, '2026-07-13 11:34:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (768, '2026-07-13 11:35:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (769, '2026-07-13 11:35:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (770, '2026-07-13 11:36:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (771, '2026-07-13 11:36:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (772, '2026-07-13 11:37:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (773, '2026-07-13 11:37:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (774, '2026-07-13 11:38:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (775, '2026-07-13 11:38:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (776, '2026-07-13 11:39:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (777, '2026-07-13 11:39:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (778, '2026-07-13 11:40:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (779, '2026-07-13 11:40:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (780, '2026-07-13 11:41:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (781, '2026-07-13 11:41:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (782, '2026-07-13 11:42:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (783, '2026-07-13 11:42:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (784, '2026-07-13 11:43:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (785, '2026-07-13 11:43:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (786, '2026-07-13 11:44:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (787, '2026-07-13 11:44:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (788, '2026-07-13 11:45:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (789, '2026-07-13 11:45:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (790, '2026-07-13 11:46:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (791, '2026-07-13 11:46:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (792, '2026-07-13 11:47:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (793, '2026-07-13 11:47:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (794, '2026-07-13 11:48:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (795, '2026-07-13 11:48:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (796, '2026-07-13 11:49:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (797, '2026-07-13 11:49:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (798, '2026-07-13 11:50:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (799, '2026-07-13 11:50:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (800, '2026-07-13 11:51:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (801, '2026-07-13 11:51:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (802, '2026-07-13 11:52:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (803, '2026-07-13 11:52:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (804, '2026-07-13 11:53:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (805, '2026-07-13 11:53:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (806, '2026-07-13 11:54:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (807, '2026-07-13 11:54:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (808, '2026-07-13 11:55:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (809, '2026-07-13 11:55:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (810, '2026-07-13 11:56:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (811, '2026-07-13 11:56:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (812, '2026-07-13 11:57:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (813, '2026-07-13 11:57:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (814, '2026-07-13 11:58:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (815, '2026-07-13 11:58:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (816, '2026-07-13 11:59:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (817, '2026-07-13 11:59:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (818, '2026-07-13 12:00:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (819, '2026-07-13 12:00:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (820, '2026-07-13 12:01:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (821, '2026-07-13 12:01:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (822, '2026-07-13 12:02:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (823, '2026-07-13 12:02:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (824, '2026-07-13 12:03:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (825, '2026-07-13 12:03:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (826, '2026-07-13 12:04:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (827, '2026-07-13 12:04:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (828, '2026-07-13 12:05:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (829, '2026-07-13 12:05:11', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (830, '2026-07-13 13:03:13', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (831, '2026-07-13 13:03:15', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (832, '2026-07-13 13:04:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (833, '2026-07-13 13:04:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (834, '2026-07-13 13:05:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (835, '2026-07-13 13:05:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (836, '2026-07-13 13:06:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (837, '2026-07-13 13:06:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (838, '2026-07-13 13:07:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (839, '2026-07-13 13:07:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (840, '2026-07-13 13:08:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (841, '2026-07-13 13:08:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (842, '2026-07-13 13:09:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (843, '2026-07-13 13:09:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (844, '2026-07-13 13:10:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (845, '2026-07-13 13:10:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (846, '2026-07-13 13:11:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (847, '2026-07-13 13:11:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (848, '2026-07-13 13:12:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (849, '2026-07-13 13:12:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (850, '2026-07-13 13:13:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (851, '2026-07-13 13:13:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (852, '2026-07-13 13:14:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (853, '2026-07-13 13:14:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (854, '2026-07-13 13:15:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (855, '2026-07-13 13:15:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (856, '2026-07-13 13:16:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (857, '2026-07-13 13:16:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (858, '2026-07-13 13:17:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (859, '2026-07-13 13:17:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (860, '2026-07-13 13:18:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (861, '2026-07-13 13:18:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (862, '2026-07-13 13:19:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (863, '2026-07-13 13:19:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (864, '2026-07-13 13:20:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (865, '2026-07-13 13:20:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (866, '2026-07-13 13:21:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (867, '2026-07-13 13:21:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (868, '2026-07-13 13:22:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (869, '2026-07-13 13:22:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (870, '2026-07-13 13:23:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (871, '2026-07-13 13:23:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (872, '2026-07-13 13:24:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (873, '2026-07-13 13:24:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (874, '2026-07-13 13:25:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (875, '2026-07-13 13:25:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (876, '2026-07-13 13:26:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (877, '2026-07-13 13:26:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (878, '2026-07-13 13:28:04', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (879, '2026-07-13 13:28:05', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (880, '2026-07-13 13:28:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (881, '2026-07-13 13:28:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (882, '2026-07-13 13:29:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (883, '2026-07-13 13:29:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (884, '2026-07-13 13:30:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (885, '2026-07-13 13:30:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (886, '2026-07-13 13:31:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (887, '2026-07-13 13:31:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (888, '2026-07-13 13:32:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (889, '2026-07-13 13:32:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (890, '2026-07-13 13:33:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (891, '2026-07-13 13:33:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (892, '2026-07-13 13:34:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (893, '2026-07-13 13:34:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (894, '2026-07-13 13:35:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (895, '2026-07-13 13:35:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (896, '2026-07-13 13:36:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (897, '2026-07-13 13:36:14', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (898, '2026-07-13 13:37:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (899, '2026-07-13 13:37:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (900, '2026-07-13 13:38:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (901, '2026-07-13 13:38:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (902, '2026-07-13 13:39:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (903, '2026-07-13 13:39:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (904, '2026-07-13 13:40:13', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (905, '2026-07-13 13:40:15', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (906, '2026-07-13 13:41:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (907, '2026-07-13 13:41:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (908, '2026-07-13 13:42:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (909, '2026-07-13 13:42:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (910, '2026-07-13 13:43:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (911, '2026-07-13 13:43:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (912, '2026-07-13 13:44:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (913, '2026-07-13 13:44:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (914, '2026-07-13 13:45:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (915, '2026-07-13 13:45:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (916, '2026-07-13 13:46:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (917, '2026-07-13 13:46:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (918, '2026-07-13 13:47:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (919, '2026-07-13 13:47:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (920, '2026-07-13 13:48:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (921, '2026-07-13 13:48:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (922, '2026-07-13 13:48:52', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (923, '2026-07-13 13:48:52', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (924, '2026-07-13 13:48:55', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (925, '2026-07-13 13:48:55', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (926, '2026-07-13 13:49:05', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (927, '2026-07-13 13:49:05', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (928, '2026-07-13 13:49:15', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (929, '2026-07-13 13:49:15', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (930, '2026-07-13 13:49:25', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (931, '2026-07-13 13:49:25', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (932, '2026-07-13 13:49:35', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (933, '2026-07-13 13:49:35', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (934, '2026-07-13 13:49:45', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (935, '2026-07-13 13:49:45', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (936, '2026-07-13 13:50:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (937, '2026-07-13 13:50:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (938, '2026-07-13 13:51:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (939, '2026-07-13 13:51:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (940, '2026-07-13 13:52:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (941, '2026-07-13 13:52:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (942, '2026-07-13 13:53:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (943, '2026-07-13 13:53:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (944, '2026-07-13 13:54:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (945, '2026-07-13 13:54:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (946, '2026-07-13 13:55:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (947, '2026-07-13 13:55:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (948, '2026-07-13 13:56:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (949, '2026-07-13 13:56:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (950, '2026-07-13 13:57:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (951, '2026-07-13 13:57:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (952, '2026-07-13 13:58:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (953, '2026-07-13 13:58:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (954, '2026-07-13 13:59:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (955, '2026-07-13 13:59:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (956, '2026-07-13 14:00:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (957, '2026-07-13 14:00:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (958, '2026-07-13 14:01:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (959, '2026-07-13 14:01:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (960, '2026-07-13 14:02:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (961, '2026-07-13 14:02:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (962, '2026-07-13 14:03:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (963, '2026-07-13 14:03:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (964, '2026-07-13 14:04:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (965, '2026-07-13 14:04:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (966, '2026-07-13 14:05:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (967, '2026-07-13 14:05:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (968, '2026-07-13 14:06:15', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (969, '2026-07-13 14:06:17', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (970, '2026-07-13 14:07:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (971, '2026-07-13 14:07:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (972, '2026-07-13 14:08:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (973, '2026-07-13 14:08:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (974, '2026-07-13 14:09:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (975, '2026-07-13 14:09:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (976, '2026-07-13 14:10:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (977, '2026-07-13 14:10:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (978, '2026-07-13 14:11:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (979, '2026-07-13 14:11:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (980, '2026-07-13 14:12:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (981, '2026-07-13 14:12:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (982, '2026-07-13 14:13:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (983, '2026-07-13 14:13:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (984, '2026-07-13 14:14:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (985, '2026-07-13 14:14:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (986, '2026-07-13 14:16:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (987, '2026-07-13 14:16:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (988, '2026-07-13 14:17:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (989, '2026-07-13 14:17:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (990, '2026-07-13 14:18:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (991, '2026-07-13 14:18:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (992, '2026-07-13 14:19:21', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (993, '2026-07-13 14:19:23', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (994, '2026-07-13 14:20:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (995, '2026-07-13 14:20:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (996, '2026-07-13 14:21:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (997, '2026-07-13 14:21:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (998, '2026-07-13 14:22:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (999, '2026-07-13 14:22:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1000, '2026-07-13 14:23:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1001, '2026-07-13 14:23:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1002, '2026-07-13 14:24:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1003, '2026-07-13 14:24:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1004, '2026-07-13 14:25:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1005, '2026-07-13 14:25:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1006, '2026-07-13 14:26:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1007, '2026-07-13 14:26:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1008, '2026-07-13 14:27:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1009, '2026-07-13 14:27:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1010, '2026-07-13 14:28:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1011, '2026-07-13 14:28:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1012, '2026-07-13 14:29:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1013, '2026-07-13 14:29:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1014, '2026-07-13 14:30:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1015, '2026-07-13 14:30:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1016, '2026-07-13 14:31:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1017, '2026-07-13 14:31:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1018, '2026-07-13 14:32:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1019, '2026-07-13 14:32:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1020, '2026-07-13 14:33:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1021, '2026-07-13 14:33:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1022, '2026-07-13 14:34:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1023, '2026-07-13 14:34:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1024, '2026-07-13 14:35:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1025, '2026-07-13 14:35:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1026, '2026-07-13 14:36:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1027, '2026-07-13 14:36:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1028, '2026-07-13 14:37:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1029, '2026-07-13 14:37:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1030, '2026-07-13 14:38:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1031, '2026-07-13 14:38:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1032, '2026-07-13 14:39:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1033, '2026-07-13 14:39:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1034, '2026-07-13 14:40:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1035, '2026-07-13 14:40:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1036, '2026-07-13 14:41:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1037, '2026-07-13 14:41:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1038, '2026-07-13 14:42:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1039, '2026-07-13 14:42:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1040, '2026-07-13 14:43:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1041, '2026-07-13 14:43:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1042, '2026-07-13 14:44:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1043, '2026-07-13 14:44:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1044, '2026-07-13 14:45:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1045, '2026-07-13 14:45:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1046, '2026-07-13 14:46:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1047, '2026-07-13 14:46:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1048, '2026-07-13 14:47:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1049, '2026-07-13 14:47:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1050, '2026-07-13 14:48:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1051, '2026-07-13 14:48:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1052, '2026-07-13 14:49:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1053, '2026-07-13 14:49:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1054, '2026-07-13 14:50:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1055, '2026-07-13 14:50:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1056, '2026-07-13 14:51:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1057, '2026-07-13 14:51:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1058, '2026-07-13 14:52:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1059, '2026-07-13 14:52:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1060, '2026-07-13 14:53:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1061, '2026-07-13 14:53:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1062, '2026-07-13 14:54:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1063, '2026-07-13 14:54:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1064, '2026-07-13 14:55:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1065, '2026-07-13 14:55:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1066, '2026-07-13 14:56:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1067, '2026-07-13 14:56:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1068, '2026-07-13 14:57:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1069, '2026-07-13 14:57:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1070, '2026-07-13 14:58:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1071, '2026-07-13 14:58:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1072, '2026-07-13 14:59:13', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1073, '2026-07-13 14:59:13', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1074, '2026-07-13 15:00:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1075, '2026-07-13 15:00:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1076, '2026-07-13 15:01:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1077, '2026-07-13 15:01:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1078, '2026-07-13 15:02:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1079, '2026-07-13 15:02:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1080, '2026-07-13 15:03:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1081, '2026-07-13 15:03:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1082, '2026-07-13 15:04:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1083, '2026-07-13 15:04:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1084, '2026-07-13 15:05:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1085, '2026-07-13 15:05:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1086, '2026-07-13 15:06:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1087, '2026-07-13 15:06:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1088, '2026-07-13 15:07:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1089, '2026-07-13 15:07:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1090, '2026-07-13 15:08:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1091, '2026-07-13 15:08:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1092, '2026-07-13 15:09:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1093, '2026-07-13 15:09:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1094, '2026-07-13 15:10:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1095, '2026-07-13 15:10:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1096, '2026-07-13 15:11:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1097, '2026-07-13 15:11:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1098, '2026-07-13 15:12:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1099, '2026-07-13 15:12:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1100, '2026-07-13 15:13:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1101, '2026-07-13 15:13:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1102, '2026-07-13 15:14:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1103, '2026-07-13 15:14:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1104, '2026-07-13 15:15:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1105, '2026-07-13 15:15:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1106, '2026-07-13 15:16:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1107, '2026-07-13 15:16:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1108, '2026-07-13 15:17:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1109, '2026-07-13 15:17:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1110, '2026-07-13 15:18:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1111, '2026-07-13 15:18:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1112, '2026-07-13 15:19:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1113, '2026-07-13 15:19:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1114, '2026-07-13 15:20:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1115, '2026-07-13 15:20:12', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1116, '2026-07-13 15:21:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1117, '2026-07-13 15:21:11', '', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1118, '2026-07-15 16:44:04', '', 'sec_Login', 'Scriptcase', '192.168.1.98', 'access', '');
INSERT INTO `sc_log` VALUES (1119, '2026-07-15 16:44:38', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1120, '2026-07-15 17:44:22', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1121, '2026-07-15 19:34:04', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1122, '2026-07-15 19:41:52', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1123, '2026-07-15 20:54:36', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1124, '2026-07-15 20:54:57', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1125, '2026-07-15 20:54:58', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1126, '2026-07-15 20:54:58', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1127, '2026-07-15 20:54:59', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1128, '2026-07-15 20:54:59', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1129, '2026-07-15 20:54:59', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1130, '2026-07-15 20:55:00', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1131, '2026-07-15 20:55:30', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1132, '2026-07-15 20:55:41', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (1133, '2026-07-15 20:55:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1134, '2026-07-15 20:55:42', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1135, '2026-07-15 20:55:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1136, '2026-07-15 20:55:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1137, '2026-07-15 20:55:55', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1138, '2026-07-15 20:55:59', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (1139, '2026-07-15 20:55:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1140, '2026-07-15 20:55:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1141, '2026-07-15 20:56:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1142, '2026-07-15 20:56:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1143, '2026-07-15 20:56:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1144, '2026-07-15 20:56:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1145, '2026-07-15 20:56:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1146, '2026-07-15 20:56:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1147, '2026-07-15 20:56:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1148, '2026-07-15 20:56:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1149, '2026-07-15 20:56:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1150, '2026-07-15 20:56:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1151, '2026-07-15 20:57:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1152, '2026-07-15 20:57:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1153, '2026-07-15 20:57:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1154, '2026-07-15 20:57:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1155, '2026-07-15 20:57:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1156, '2026-07-15 20:57:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1157, '2026-07-15 20:57:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1158, '2026-07-15 20:57:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1159, '2026-07-15 20:58:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1160, '2026-07-15 20:58:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1161, '2026-07-15 20:59:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1162, '2026-07-15 20:59:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1163, '2026-07-15 21:38:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1164, '2026-07-15 21:38:41', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1165, '2026-07-15 21:38:48', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1166, '2026-07-15 21:42:49', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1167, '2026-07-15 21:42:52', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (1168, '2026-07-15 21:42:52', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1169, '2026-07-15 21:42:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1170, '2026-07-15 21:42:55', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1171, '2026-07-15 21:42:58', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1172, '2026-07-15 21:43:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1173, '2026-07-15 21:43:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1174, '2026-07-15 21:43:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1175, '2026-07-15 21:43:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1176, '2026-07-15 21:43:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1177, '2026-07-15 21:43:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1178, '2026-07-15 21:43:34', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1179, '2026-07-15 21:43:34', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1180, '2026-07-15 21:43:44', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1181, '2026-07-15 21:43:44', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1182, '2026-07-15 21:43:54', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1183, '2026-07-15 21:43:54', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1184, '2026-07-15 21:44:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1185, '2026-07-15 21:44:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1186, '2026-07-15 21:45:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1187, '2026-07-15 21:45:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1188, '2026-07-15 21:46:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1189, '2026-07-15 21:46:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1190, '2026-07-15 21:47:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1191, '2026-07-15 21:47:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1192, '2026-07-15 21:48:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1193, '2026-07-15 21:48:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1194, '2026-07-15 21:49:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1195, '2026-07-15 21:49:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1196, '2026-07-15 21:50:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1197, '2026-07-15 21:50:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1198, '2026-07-15 21:51:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1199, '2026-07-15 21:51:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1200, '2026-07-15 21:52:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1201, '2026-07-15 21:52:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1202, '2026-07-15 21:53:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1203, '2026-07-15 21:53:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1204, '2026-07-15 21:53:46', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1205, '2026-07-15 21:53:48', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1206, '2026-07-15 21:53:48', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1207, '2026-07-15 21:53:48', 'wiwik', 'form_hpp_parts_coating', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1208, '2026-07-15 21:53:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1209, '2026-07-15 21:53:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1210, '2026-07-15 21:54:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1211, '2026-07-15 21:54:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1212, '2026-07-15 21:54:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1213, '2026-07-15 21:54:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1214, '2026-07-15 21:54:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1215, '2026-07-15 21:54:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1216, '2026-07-15 21:54:33', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1217, '2026-07-15 21:54:33', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1218, '2026-07-15 21:54:36', 'wiwik', 'grid_m_join_type', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1219, '2026-07-15 21:54:38', 'wiwik', 'grid_m_packing_type', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1220, '2026-07-15 21:54:40', 'wiwik', 'grid_m_paper_grammage', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1221, '2026-07-15 21:54:41', 'wiwik', 'grid_m_part_type', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1222, '2026-07-15 21:54:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1223, '2026-07-15 21:54:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1224, '2026-07-15 21:54:43', 'wiwik', 'grid_m_routing_template', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1225, '2026-07-15 21:54:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1226, '2026-07-15 21:54:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1227, '2026-07-15 21:54:53', 'wiwik', 'grid_m_sheet_discount_tier', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1228, '2026-07-15 21:54:56', 'wiwik', 'grid_m_sheet_rate', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1229, '2026-07-15 21:54:59', 'wiwik', 'grid_m_sheet_supplier', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1230, '2026-07-15 21:55:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1231, '2026-07-15 21:55:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1232, '2026-07-15 21:55:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1233, '2026-07-15 21:55:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1234, '2026-07-15 21:55:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1235, '2026-07-15 21:55:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1236, '2026-07-15 21:55:34', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1237, '2026-07-15 21:55:34', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1238, '2026-07-15 21:55:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1239, '2026-07-15 21:55:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1240, '2026-07-15 21:55:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1241, '2026-07-15 21:55:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1242, '2026-07-15 21:56:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1243, '2026-07-15 21:56:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1244, '2026-07-15 21:56:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1245, '2026-07-15 21:56:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1246, '2026-07-15 21:56:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1247, '2026-07-15 21:56:23', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1248, '2026-07-15 21:56:33', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1249, '2026-07-15 21:56:33', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1250, '2026-07-15 21:56:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1251, '2026-07-15 21:56:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1252, '2026-07-15 21:56:46', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1253, '2026-07-15 21:56:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1254, '2026-07-15 21:56:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1255, '2026-07-15 21:56:54', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1256, '2026-07-15 21:56:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1257, '2026-07-15 21:56:57', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1258, '2026-07-15 21:57:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1259, '2026-07-15 21:57:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1260, '2026-07-15 21:57:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1261, '2026-07-15 21:57:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1262, '2026-07-15 21:57:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1263, '2026-07-15 21:57:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1264, '2026-07-15 21:57:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1265, '2026-07-15 21:57:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1266, '2026-07-15 21:57:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1267, '2026-07-15 21:57:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1268, '2026-07-15 21:57:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1269, '2026-07-15 21:57:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1270, '2026-07-15 21:58:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1271, '2026-07-15 21:58:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1272, '2026-07-15 21:58:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1273, '2026-07-15 21:58:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1274, '2026-07-15 21:58:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1275, '2026-07-15 21:58:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1276, '2026-07-15 21:58:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1277, '2026-07-15 21:58:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1278, '2026-07-15 21:58:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1279, '2026-07-15 21:58:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1280, '2026-07-15 21:58:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1281, '2026-07-15 21:58:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1282, '2026-07-15 21:59:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1283, '2026-07-15 21:59:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1284, '2026-07-15 21:59:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1285, '2026-07-15 21:59:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1286, '2026-07-15 21:59:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1287, '2026-07-15 21:59:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1288, '2026-07-15 21:59:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1289, '2026-07-15 21:59:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1290, '2026-07-15 21:59:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1291, '2026-07-15 21:59:45', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1292, '2026-07-15 21:59:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1293, '2026-07-15 21:59:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1294, '2026-07-15 22:00:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1295, '2026-07-15 22:00:05', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1296, '2026-07-15 22:00:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1297, '2026-07-15 22:00:15', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1298, '2026-07-15 22:00:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1299, '2026-07-15 22:00:25', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1300, '2026-07-15 22:00:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1301, '2026-07-15 22:00:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1302, '2026-07-15 22:00:43', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1303, '2026-07-16 13:44:06', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1304, '2026-07-16 13:44:09', 'wiwik', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (1305, '2026-07-16 13:44:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1306, '2026-07-16 13:44:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1307, '2026-07-16 13:44:13', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1308, '2026-07-16 13:44:17', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1309, '2026-07-16 13:44:18', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1310, '2026-07-16 13:44:18', 'wiwik', 'form_hpp_parts_coating', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1311, '2026-07-16 13:44:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1312, '2026-07-16 13:44:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1313, '2026-07-16 13:44:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1314, '2026-07-16 13:44:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1315, '2026-07-16 13:44:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1316, '2026-07-16 13:44:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1317, '2026-07-16 13:44:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1318, '2026-07-16 13:44:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1319, '2026-07-16 13:44:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1320, '2026-07-16 13:44:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1321, '2026-07-16 13:45:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1322, '2026-07-16 13:45:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1323, '2026-07-16 13:45:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1324, '2026-07-16 13:45:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1325, '2026-07-16 13:45:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1326, '2026-07-16 13:45:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1327, '2026-07-16 13:45:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1328, '2026-07-16 13:45:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1329, '2026-07-16 13:45:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1330, '2026-07-16 13:45:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1331, '2026-07-16 13:45:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1332, '2026-07-16 13:45:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1333, '2026-07-16 13:46:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1334, '2026-07-16 13:46:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1335, '2026-07-16 13:46:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1336, '2026-07-16 13:46:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1337, '2026-07-16 13:46:24', 'wiwik', 'grid_m_paper_grammage', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1338, '2026-07-16 13:46:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1339, '2026-07-16 13:46:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1340, '2026-07-16 13:46:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1341, '2026-07-16 13:46:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1342, '2026-07-16 13:46:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1343, '2026-07-16 13:46:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1344, '2026-07-16 13:46:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1345, '2026-07-16 13:46:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1346, '2026-07-16 13:47:03', 'wiwik', 'grid_m_packing_type', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1347, '2026-07-16 13:47:08', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1348, '2026-07-16 13:47:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1349, '2026-07-16 13:47:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1350, '2026-07-16 13:47:11', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1351, '2026-07-16 13:47:11', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1352, '2026-07-16 13:47:11', 'wiwik', 'form_hpp_parts_coating', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1353, '2026-07-16 13:47:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1354, '2026-07-16 13:47:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1355, '2026-07-16 13:47:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1356, '2026-07-16 13:47:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1357, '2026-07-16 13:47:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1358, '2026-07-16 13:47:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1359, '2026-07-16 13:47:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1360, '2026-07-16 13:47:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1361, '2026-07-16 13:47:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1362, '2026-07-16 13:47:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1363, '2026-07-16 13:48:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1364, '2026-07-16 13:48:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1365, '2026-07-16 13:48:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1366, '2026-07-16 13:48:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1367, '2026-07-16 13:48:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1368, '2026-07-16 13:48:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1369, '2026-07-16 13:48:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1370, '2026-07-16 13:48:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1371, '2026-07-16 13:48:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1372, '2026-07-16 13:48:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1373, '2026-07-16 13:48:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1374, '2026-07-16 13:48:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1375, '2026-07-16 13:49:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1376, '2026-07-16 13:49:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1377, '2026-07-16 13:49:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1378, '2026-07-16 13:49:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1379, '2026-07-16 13:49:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1380, '2026-07-16 13:49:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1381, '2026-07-16 13:49:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1382, '2026-07-16 13:49:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1383, '2026-07-16 13:49:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1384, '2026-07-16 13:49:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1385, '2026-07-16 13:49:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1386, '2026-07-16 13:49:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1387, '2026-07-16 13:50:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1388, '2026-07-16 13:50:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1389, '2026-07-16 13:50:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1390, '2026-07-16 13:50:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1391, '2026-07-16 13:50:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1392, '2026-07-16 13:50:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1393, '2026-07-16 13:50:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1394, '2026-07-16 13:50:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1395, '2026-07-16 13:50:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1396, '2026-07-16 13:50:49', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1397, '2026-07-16 13:50:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1398, '2026-07-16 13:50:59', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1399, '2026-07-16 13:51:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1400, '2026-07-16 13:51:09', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1401, '2026-07-16 13:51:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1402, '2026-07-16 13:51:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1403, '2026-07-16 13:51:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1404, '2026-07-16 13:51:29', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1405, '2026-07-16 13:51:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1406, '2026-07-16 13:51:39', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1407, '2026-07-16 13:51:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1408, '2026-07-16 13:51:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1409, '2026-07-16 13:52:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1410, '2026-07-16 13:52:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1411, '2026-07-16 13:52:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1412, '2026-07-16 13:52:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1413, '2026-07-16 13:52:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1414, '2026-07-16 13:52:20', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1415, '2026-07-16 13:52:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1416, '2026-07-16 13:52:30', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1417, '2026-07-16 13:52:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1418, '2026-07-16 13:52:40', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1419, '2026-07-16 13:53:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1420, '2026-07-16 13:53:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1421, '2026-07-16 13:54:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1422, '2026-07-16 13:54:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1423, '2026-07-16 13:55:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1424, '2026-07-16 13:55:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1425, '2026-07-16 13:56:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1426, '2026-07-16 13:56:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1427, '2026-07-16 13:57:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1428, '2026-07-16 13:57:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1429, '2026-07-16 13:58:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1430, '2026-07-16 13:58:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1431, '2026-07-16 13:59:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1432, '2026-07-16 13:59:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1433, '2026-07-16 14:00:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1434, '2026-07-16 14:00:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1435, '2026-07-16 14:01:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1436, '2026-07-16 14:01:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1437, '2026-07-16 14:02:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1438, '2026-07-16 14:02:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1439, '2026-07-16 14:03:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1440, '2026-07-16 14:03:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1441, '2026-07-16 14:04:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1442, '2026-07-16 14:04:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1443, '2026-07-16 14:05:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1444, '2026-07-16 14:05:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1445, '2026-07-16 14:06:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1446, '2026-07-16 14:06:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1447, '2026-07-16 14:07:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1448, '2026-07-16 14:07:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1449, '2026-07-16 14:08:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1450, '2026-07-16 14:08:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1451, '2026-07-16 14:09:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1452, '2026-07-16 14:09:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1453, '2026-07-16 14:10:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1454, '2026-07-16 14:10:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1455, '2026-07-16 14:11:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1456, '2026-07-16 14:11:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1457, '2026-07-16 14:12:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1458, '2026-07-16 14:12:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1459, '2026-07-16 14:13:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1460, '2026-07-16 14:13:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1461, '2026-07-16 14:14:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1462, '2026-07-16 14:14:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1463, '2026-07-16 14:15:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1464, '2026-07-16 14:15:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1465, '2026-07-16 14:16:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1466, '2026-07-16 14:16:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1467, '2026-07-16 14:17:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1468, '2026-07-16 14:17:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1469, '2026-07-16 14:18:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1470, '2026-07-16 14:18:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1471, '2026-07-16 14:19:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1472, '2026-07-16 14:19:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1473, '2026-07-16 14:20:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1474, '2026-07-16 14:20:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1475, '2026-07-16 14:21:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1476, '2026-07-16 14:21:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1477, '2026-07-16 14:22:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1478, '2026-07-16 14:22:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1479, '2026-07-16 14:23:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1480, '2026-07-16 14:23:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1481, '2026-07-16 14:24:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1482, '2026-07-16 14:24:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1483, '2026-07-16 14:25:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1484, '2026-07-16 14:25:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1485, '2026-07-16 14:26:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1486, '2026-07-16 14:26:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1487, '2026-07-16 14:27:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1488, '2026-07-16 14:27:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1489, '2026-07-16 14:28:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1490, '2026-07-16 14:28:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1491, '2026-07-16 14:29:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1492, '2026-07-16 14:29:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1493, '2026-07-16 14:30:04', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1494, '2026-07-16 14:30:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1495, '2026-07-18 13:11:01', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1496, '2026-07-18 13:11:05', 'wiwik', 'sec_logged', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1497, '2026-07-18 13:11:07', 'wiwik', 'sec_logged', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (1498, '2026-07-18 13:11:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1499, '2026-07-18 13:11:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1500, '2026-07-18 13:11:11', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1501, '2026-07-18 13:11:15', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1502, '2026-07-18 13:11:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1503, '2026-07-18 13:11:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1504, '2026-07-18 13:11:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1505, '2026-07-18 13:11:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1506, '2026-07-18 13:11:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1507, '2026-07-18 13:11:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1508, '2026-07-18 13:11:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1509, '2026-07-18 13:11:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1510, '2026-07-18 13:11:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1511, '2026-07-18 13:11:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1512, '2026-07-18 13:12:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1513, '2026-07-18 13:12:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1514, '2026-07-18 13:12:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1515, '2026-07-18 13:12:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1516, '2026-07-18 13:12:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1517, '2026-07-18 13:12:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1518, '2026-07-18 13:12:30', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'insert', '--> keys <-- hpp_id : 9||--> fields <-- doc_no (new)  : 26HPP0000009||doc_no (label)  : Doc No||doc_date (new)  : 2026-07-18||doc_date (label)  : Doc Date||doc_status (new)  : DRAFT||doc_status (label)  : Status||i_calc_type (new)  : NEW||i_calc_type (label)  : Tipe Perhitungan ||revision_no (new)  : 0||revision_no (label)  : No Rev.||customer_id (new)  : 0||customer_id (label)  : Pilih Customer (*)||product_id (new)  : 0||product_id (label)  : Pilih produk yang sudah ada||customer_name (new)  : ||customer_name (label)  : Nama Calon Customer / Lead||product_name (new)  : ||product_name (label)  : Product Name||cfg_parts_id (new)  : 1||cfg_parts_id (label)  : Cfg Parts||qty_order_pcs (new)  : 1000.00||qty_order_pcs (label)  : Qty Order Pcs||is_ppn (new)  : 1||is_ppn (label)  : Pilih PPn ||ppn_percent (new)  : 11.00||ppn_percent (label)  : Ppn Percent||margin_percent (new)  : 15.00||margin_percent (label)  : Margin Percent||tolerance_percent (new)  : 11.00||tolerance_percent (label)  : Tolerance Percent||rounding_mode (new)  : NEAREST||rounding_mode (label)  : Rounding Mode||currency_code (new)  : IDR||currency_code (label)  : Currency||notes (new)  : ||notes (label)  : Notes||i_dim_panjang_mm (new)  : 0||i_dim_panjang_mm (label)  : Panjang (mm)||i_dim_lebar_mm (new)  : 0||i_dim_lebar_mm (label)  : Lebar (mm)||i_dim_tinggi_mm (new)  : 0||i_dim_tinggi_mm (label)  : Tinggi (mm)||i_lidah_lem_mm (new)  : 0||i_lidah_lem_mm (label)  : Lidah Lem (mm)||total_material_cost (new)  : 0||total_material_cost (label)  : Total Material Cost||total_process_cost (new)  : 0||total_process_cost (label)  : Total Process Cost||total_finishing_cost (new)  : 0||total_finishing_cost (label)  : Total Tooling Cost||total_hpp (new)  : 0||total_hpp (label)  : Total Hpp||total_after_margin (new)  : 0||total_after_margin (label)  : Total After Margin||total_after_ppn (new)  : 0||total_after_ppn (label)  : Total After Ppn||price_per_pcs (new)  : 0||price_per_pcs (label)  : Price Per Pcs||create_log (new)  : 2026-07-18 00:00:00||create_log (label)  : Create Log||update_log (new)  : 2026-07-18 00:00:00||update_log (label)  : Update Log||create_by (new)  : wiwik||create_by (label)  : Create By||update_by (new)  : wiwik||update_by (label)  : Update By||stat_confirm (new)  : 0||stat_confirm (label)  : Stat Confirm||stat_cancel (new)  : 0||stat_cancel (label)  : Stat Cancel||stat_closed (new)  : 0||stat_closed (label)  : Stat Closed||stat_failed (new)  : 0||stat_failed (label)  : Stat Failed||deta (new)  : ||deta (label)  : ');
INSERT INTO `sc_log` VALUES (1519, '2026-07-18 13:12:31', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1520, '2026-07-18 13:12:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1521, '2026-07-18 13:12:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1522, '2026-07-18 13:12:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1523, '2026-07-18 13:12:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1524, '2026-07-18 13:12:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1525, '2026-07-18 13:12:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1526, '2026-07-18 13:13:08', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1527, '2026-07-18 13:13:08', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1528, '2026-07-18 13:13:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1529, '2026-07-18 13:13:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1530, '2026-07-18 13:13:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1531, '2026-07-18 13:13:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1532, '2026-07-18 13:13:38', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1533, '2026-07-18 13:13:38', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1534, '2026-07-18 13:13:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1535, '2026-07-18 13:13:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1536, '2026-07-18 13:14:54', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1537, '2026-07-18 13:14:54', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1538, '2026-07-18 13:15:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1539, '2026-07-18 13:15:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1540, '2026-07-18 13:16:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1541, '2026-07-18 13:16:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1542, '2026-07-18 13:17:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1543, '2026-07-18 13:17:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1544, '2026-07-18 13:18:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1545, '2026-07-18 13:18:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1546, '2026-07-18 13:19:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1547, '2026-07-18 13:19:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1548, '2026-07-18 13:20:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1549, '2026-07-18 13:20:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1550, '2026-07-18 13:21:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1551, '2026-07-18 13:21:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1552, '2026-07-18 13:22:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1553, '2026-07-18 13:22:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1554, '2026-07-18 13:23:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1555, '2026-07-18 13:23:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1556, '2026-07-18 13:24:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1557, '2026-07-18 13:24:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1558, '2026-07-18 13:25:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1559, '2026-07-18 13:25:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1560, '2026-07-18 13:26:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1561, '2026-07-18 13:26:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1562, '2026-07-18 13:27:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1563, '2026-07-18 13:27:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1564, '2026-07-18 13:28:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1565, '2026-07-18 13:28:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1566, '2026-07-18 13:29:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1567, '2026-07-18 13:29:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1568, '2026-07-18 13:30:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1569, '2026-07-18 13:30:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1570, '2026-07-18 13:31:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1571, '2026-07-18 13:31:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1572, '2026-07-18 13:32:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1573, '2026-07-18 13:32:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1574, '2026-07-18 13:33:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1575, '2026-07-18 13:33:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1576, '2026-07-18 13:34:22', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1577, '2026-07-18 13:34:22', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1578, '2026-07-18 13:34:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1579, '2026-07-18 13:34:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1580, '2026-07-18 13:34:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1581, '2026-07-18 13:34:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1582, '2026-07-18 13:34:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1583, '2026-07-18 13:34:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1584, '2026-07-18 13:34:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1585, '2026-07-18 13:34:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1586, '2026-07-18 13:35:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1587, '2026-07-18 13:35:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1588, '2026-07-18 13:35:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1589, '2026-07-18 13:35:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1590, '2026-07-18 13:35:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1591, '2026-07-18 13:35:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1592, '2026-07-18 13:35:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1593, '2026-07-18 13:35:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1594, '2026-07-18 13:35:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1595, '2026-07-18 13:35:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1596, '2026-07-18 13:35:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1597, '2026-07-18 13:35:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1598, '2026-07-18 13:36:08', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1599, '2026-07-18 13:36:08', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1600, '2026-07-18 13:36:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1601, '2026-07-18 13:36:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1602, '2026-07-18 13:36:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1603, '2026-07-18 13:36:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1604, '2026-07-18 13:36:38', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1605, '2026-07-18 13:36:38', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1606, '2026-07-18 13:36:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1607, '2026-07-18 13:36:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1608, '2026-07-18 13:36:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1609, '2026-07-18 13:36:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1610, '2026-07-18 13:37:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1611, '2026-07-18 13:37:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1612, '2026-07-18 13:38:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1613, '2026-07-18 13:38:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1614, '2026-07-18 13:39:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1615, '2026-07-18 13:39:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1616, '2026-07-18 13:40:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1617, '2026-07-18 13:40:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1618, '2026-07-18 13:41:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1619, '2026-07-18 13:41:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1620, '2026-07-18 13:42:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1621, '2026-07-18 13:42:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1622, '2026-07-18 13:43:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1623, '2026-07-18 13:43:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1624, '2026-07-18 13:44:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1625, '2026-07-18 13:44:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1626, '2026-07-18 13:45:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1627, '2026-07-18 13:45:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1628, '2026-07-18 13:46:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1629, '2026-07-18 13:46:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1630, '2026-07-18 13:47:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1631, '2026-07-18 13:47:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1632, '2026-07-18 13:48:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1633, '2026-07-18 13:48:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1634, '2026-07-18 13:49:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1635, '2026-07-18 13:49:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1636, '2026-07-18 13:50:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1637, '2026-07-18 13:50:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1638, '2026-07-18 13:51:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1639, '2026-07-18 13:51:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1640, '2026-07-18 13:52:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1641, '2026-07-18 13:52:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1642, '2026-07-18 13:53:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1643, '2026-07-18 13:53:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1644, '2026-07-18 13:54:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1645, '2026-07-18 13:54:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1646, '2026-07-18 13:55:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1647, '2026-07-18 13:55:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1648, '2026-07-18 13:56:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1649, '2026-07-18 13:56:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1650, '2026-07-18 13:57:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1651, '2026-07-18 13:57:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1652, '2026-07-18 13:58:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1653, '2026-07-18 13:58:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1654, '2026-07-18 13:59:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1655, '2026-07-18 13:59:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1656, '2026-07-18 14:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1657, '2026-07-18 14:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1658, '2026-07-18 14:01:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1659, '2026-07-18 14:01:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1660, '2026-07-18 14:02:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1661, '2026-07-18 14:02:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1662, '2026-07-18 14:03:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1663, '2026-07-18 14:03:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1664, '2026-07-18 14:07:24', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1665, '2026-07-18 14:07:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1666, '2026-07-18 14:07:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1667, '2026-07-18 14:07:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1668, '2026-07-18 14:08:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1669, '2026-07-18 14:08:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1670, '2026-07-18 14:09:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1671, '2026-07-18 14:09:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1672, '2026-07-18 14:09:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1673, '2026-07-18 14:09:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1674, '2026-07-18 14:09:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1675, '2026-07-18 14:09:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1676, '2026-07-18 14:09:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1677, '2026-07-18 14:09:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1678, '2026-07-18 14:09:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1679, '2026-07-18 14:09:50', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1680, '2026-07-18 14:09:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1681, '2026-07-18 14:10:13', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1682, '2026-07-18 14:10:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1683, '2026-07-18 14:10:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1684, '2026-07-18 14:10:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1685, '2026-07-18 14:10:22', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1686, '2026-07-18 14:10:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1687, '2026-07-18 14:10:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1688, '2026-07-18 14:10:38', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1689, '2026-07-18 14:10:38', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1690, '2026-07-18 14:10:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1691, '2026-07-18 14:10:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1692, '2026-07-18 14:10:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1693, '2026-07-18 14:10:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1694, '2026-07-18 14:11:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1695, '2026-07-18 14:11:08', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1696, '2026-07-18 14:11:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1697, '2026-07-18 14:11:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1698, '2026-07-18 14:11:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1699, '2026-07-18 14:11:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1700, '2026-07-18 14:11:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1701, '2026-07-18 14:11:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1702, '2026-07-18 14:12:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1703, '2026-07-18 14:12:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1704, '2026-07-18 14:13:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1705, '2026-07-18 14:13:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1706, '2026-07-18 14:14:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1707, '2026-07-18 14:14:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1708, '2026-07-18 14:15:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1709, '2026-07-18 14:15:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1710, '2026-07-18 14:16:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1711, '2026-07-18 14:16:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1712, '2026-07-18 14:17:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1713, '2026-07-18 14:17:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1714, '2026-07-18 14:18:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1715, '2026-07-18 14:18:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1716, '2026-07-18 14:19:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1717, '2026-07-18 14:19:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1718, '2026-07-18 14:20:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1719, '2026-07-18 14:20:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1720, '2026-07-18 14:21:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1721, '2026-07-18 14:21:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1722, '2026-07-18 14:24:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1723, '2026-07-18 14:24:12', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1724, '2026-07-18 14:24:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1725, '2026-07-18 14:24:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1726, '2026-07-18 14:25:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1727, '2026-07-18 14:25:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1728, '2026-07-18 14:26:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1729, '2026-07-18 14:26:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1730, '2026-07-18 14:27:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1731, '2026-07-18 14:27:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1732, '2026-07-18 14:28:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1733, '2026-07-18 14:28:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1734, '2026-07-18 14:29:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1735, '2026-07-18 14:29:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1736, '2026-07-18 14:29:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1737, '2026-07-18 14:29:38', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1738, '2026-07-18 14:29:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1739, '2026-07-18 14:29:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1740, '2026-07-18 14:29:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1741, '2026-07-18 14:29:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1742, '2026-07-18 14:30:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1743, '2026-07-18 14:30:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1744, '2026-07-18 14:30:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1745, '2026-07-18 14:30:18', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1746, '2026-07-18 14:30:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1747, '2026-07-18 14:30:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1748, '2026-07-18 14:30:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1749, '2026-07-18 14:30:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1750, '2026-07-18 14:30:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1751, '2026-07-18 14:30:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1752, '2026-07-18 14:30:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1753, '2026-07-18 14:30:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1754, '2026-07-18 14:31:05', 'wiwik', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'insert', '--> keys <-- hpp_parts_id : 53||--> fields <-- hpp_id (new)  : 9||hpp_id (label)  : {lang_hpp_parts_fld_hpp_id}||no_urut (new)  : 0||no_urut (label)  : No Urut||qty_part (new)  : 1800000.00||qty_part (label)  : Qty Part||part_name (new)  : ||part_name (label)  : part_name||part_type (new)  : SHEET||part_type (label)  : {lang_hpp_parts_fld_part_type}||i_dim_panjang_mm (new)  : 427.00||i_dim_panjang_mm (label)  : Dimensi Panjang (mm)||i_dim_lebar_mm (new)  : 220.00||i_dim_lebar_mm (label)  : Dimensi Lebar (mm)||i_dim_tinggi_mm (new)  : 172.00||i_dim_tinggi_mm (label)  : Dim Tinggi (mm)||i_lidah_lem_mm (new)  : 35.00||i_lidah_lem_mm (label)  : Lidah Lem (mm)||pakai_laminasi (new)  : 0||pakai_laminasi (label)  : Pakai Laminasi||mcard_id (new)  : 0||mcard_id (label)  : {lang_hpp_parts_fld_mcard_id}||gramatur_id (new)  : 0||gramatur_id (label)  : Gramatur Id||i_dc_persen_override (new)  : 0.00||i_dc_persen_override (label)  : I Dc Persen Override||i_panjang_plano_cm (new)  : 0||i_panjang_plano_cm (label)  : I Panjang Plano Cm||i_lebar_plano_cm (new)  : 0||i_lebar_plano_cm (label)  : I Lebar Plano Cm||i_cut_per_plano (new)  : 1||i_cut_per_plano (label)  : {lang_hpp_parts_fld_i_cut_per_plano}||i_isi_per_lb (new)  : 1||i_isi_per_lb (label)  : I Isi Per Lb||i_jumlah_warna (new)  : 0||i_jumlah_warna (label)  : I Jumlah Warna||sheet_rate_id (new)  : 10||sheet_rate_id (label)  : {lang_hpp_parts_fld_sheet_rate_id}||supplier_sheet_code (new)  : UJK||supplier_sheet_code (label)  : Supplier Sheet Code||c_ongkos_lam_pc (new)  : 0||c_ongkos_lam_pc (label)  : C Ongkos Lam Pc||c_sheet_compare_info (new)  : ||c_sheet_compare_info (label)  : C Sheet Compare Info||i_sheet_panjang_cm (new)  : 126.9000||i_sheet_panjang_cm (label)  : {lang_hpp_parts_fld_i_sheet_panjang_cm}||i_sheet_lebar_cm (new)  : 50.2000||i_sheet_lebar_cm (label)  : {lang_hpp_parts_fld_i_sheet_lebar_cm}||i_lebar_trim_cm (new)  : 155.0000||i_lebar_trim_cm (label)  : {lang_hpp_parts_fld_i_lebar_trim_cm}||i_isi_per_trim (new)  : 3||i_isi_per_trim (label)  : {lang_hpp_parts_fld_i_isi_per_trim}||i_discount_rm (new)  : -5.0000||i_discount_rm (label)  : Discrount RM||i_ongkos_cetak_rim (new)  : 0.00||i_ongkos_cetak_rim (label)  : Ongkos Cetak Rim||i_ongkos_plong_lb (new)  : 0.00||i_ongkos_plong_lb (label)  : Ongkos Plong||i_ongkos_lem_pc (new)  : 0.00||i_ongkos_lem_pc (label)  : Ongkos Lem||i_ongkos_hotprint_pc (new)  : 0.00||i_ongkos_hotprint_pc (label)  : Ongkos hotprint||i_ongkos_kupas_pc (new)  : 0.00||i_ongkos_kupas_pc (label)  : {lang_hpp_parts_fld_i_ongkos_kupas_pc}||i_ongkos_qc_pc (new)  : 100.00||i_ongkos_qc_pc (label)  : {lang_hpp_parts_fld_i_ongkos_qc_pc}||i_ongkos_kirim_pc (new)  : 100.00||i_ongkos_kirim_pc (label)  : {lang_hpp_parts_fld_i_ongkos_kirim_pc}||i_ongkos_print_pc (new)  : 300.00||i_ongkos_print_pc (label)  : {lang_hpp_parts_fld_i_ongkos_print_pc}||c_print_cost_pc (new)  : 600||c_print_cost_pc (label)  : C Print Cost Pc||i_ongkos_diecut_pc (new)  : 350.00||i_ongkos_diecut_pc (label)  : {lang_hpp_parts_fld_i_ongkos_diecut_pc}||i_ongkos_slitter_pc (new)  : 0.00||i_ongkos_slitter_pc (label)  : {lang_hpp_parts_fld_i_ongkos_slitter_pc}||i_ongkos_slotter_pc (new)  : 0.00||i_ongkos_slotter_pc (label)  : {lang_hpp_parts_fld_i_ongkos_slotter_pc}||join_type (new)  : ||join_type (label)  : {lang_hpp_parts_fld_join_type}||packing_type_id (new)  : 0||packing_type_id (label)  : Packing Type Id||i_ongkos_packing_pc (new)  : 0||i_ongkos_packing_pc (label)  : I Ongkos Packing Pc||i_ongkos_join_pc (new)  : 0.00||i_ongkos_join_pc (label)  : {lang_hpp_parts_fld_i_ongkos_join_pc}||i_ongkos_partisi_pc (new)  : 0.00||i_ongkos_partisi_pc (label)  : {lang_hpp_parts_fld_i_ongkos_partisi_pc}||i_ongkos_layer_pc (new)  : 0.00||i_ongkos_layer_pc (label)  : {lang_hpp_parts_fld_i_ongkos_layer_pc}||i_ongkos_pisau_pc (new)  : 0.0000||i_ongkos_pisau_pc (label)  : I Ongkos Pisau Pc||i_ongkos_laminasi_pc (new)  : 0.0000||i_ongkos_laminasi_pc (label)  : {lang_hpp_parts_fld_i_ongkos_laminasi_pc}||i_ongkos_lam_rp_cm2_sh (new)  : 0.0000||i_ongkos_lam_rp_cm2_sh (label)  : I Ongkos Lam Rp Cm 2 Sh||i_ongkos_lam_rp_cm2_sf (new)  : 0.0000||i_ongkos_lam_rp_cm2_sf (label)  : I Ongkos Lam Rp Cm 2 Sf||i_lam_sheet_flat_pc (new)  : 0.00||i_lam_sheet_flat_pc (label)  : I Lam Sheet Flat Pc||sf_ef_rate_id (new)  : 0||sf_ef_rate_id (label)  : Sf Ef Rate Id||i_sf_ef_rate_override (new)  : 0||i_sf_ef_rate_override (label)  : I Sf Ef Rate Override||i_lam_pakai_ukuran_sf (new)  : 0||i_lam_pakai_ukuran_sf (label)  : I Lam Pakai Ukuran Sf||film_rate_id (new)  : 0||film_rate_id (label)  : {lang_hpp_parts_fld_film_rate_id}||i_film_faktor (new)  : 0.0000||i_film_faktor (label)  : I Film Faktor||i_pisau_faktor (new)  : 0.0000||i_pisau_faktor (label)  : I Pisau Faktor||i_pisau_rate_rp (new)  : 0.0000||i_pisau_rate_rp (label)  : I Pisau Rate Rp||i_ongkos_plate_warna (new)  : 0.0000||i_ongkos_plate_warna (label)  : I Ongkos Plate Warna||c_uk_cetak_panjang_cm (new)  : 0||c_uk_cetak_panjang_cm (label)  : C Uk Cetak Panjang Cm||c_uk_cetak_lebar_cm (new)  : 0||c_uk_cetak_lebar_cm (label)  : C Uk Cetak Lebar Cm||c_area_cetak_cm2 (new)  : 0||c_area_cetak_cm2 (label)  : Area Cetak||c_jumlah_cetak_lc (new)  : 0||c_jumlah_cetak_lc (label)  : Jumlah Cetak||c_jumlah_bahan_lb (new)  : 0||c_jumlah_bahan_lb (label)  : Jumlah bahan||c_paper_cost_pc (new)  : 0||c_paper_cost_pc (label)  : Ongkos Kertas Pcs||c_sheet_cost_pc (new)  : 23949.5832||c_sheet_cost_pc (label)  : C Sheet Cost Pc||c_i_paper (new)  : 0||c_i_paper (label)  : C I Paper||c_i_sheet_cost (new)  : 43109249760||c_i_sheet_cost (label)  : C I Sheet Cost||c_process_cost_pc (new)  : 1150||c_process_cost_pc (label)  : Ongkos Proses||c_tooling_cost_pc (new)  : 0||c_tooling_cost_pc (label)  : Ongkos Tooling||c_total_coating_pc (new)  : 0||c_total_coating_pc (label)  : C Total Coating Pc||c_total_coating_lc (new)  : 0||c_total_coating_lc (label)  : C Total Coating Lc||c_sf_ef_cost_pc (new)  : 0||c_sf_ef_cost_pc (label)  : C Sf Ef Cost Pc||c_i_sf_ef (new)  : 0||c_i_sf_ef (label)  : C I Sf Ef||c_i_total_coating (new)  : 0||c_i_total_coating (label)  : C I Total Coating||c_total_part_cost_pc (new)  : 25099.5832||c_total_part_cost_pc (label)  : c_total_part_cost_pc||c_cetak_cost_pc (new)  : 0||c_cetak_cost_pc (label)  : C Cetak Cost Pc||c_i_cetak (new)  : 0||c_i_cetak (label)  : C I Cetak||c_plong_cost_pc (new)  : 0||c_plong_cost_pc (label)  : C Plong Cost Pc||c_i_plong (new)  : 0||c_i_plong (label)  : C I Plong||c_lem_cost_pc (new)  : 0||c_lem_cost_pc (label)  : C Lem Cost Pc||c_i_lem (new)  : 0||c_i_lem (label)  : C I Lem||c_i_hotprint (new)  : 0||c_i_hotprint (label)  : C I Hotprint||c_i_print (new)  : 1080000000||c_i_print (label)  : C I Print||c_i_diecut (new)  : 630000000||c_i_diecut (label)  : C I Diecut||c_i_slitter (new)  : 0||c_i_slitter (label)  : C I Slitter||c_i_slotter (new)  : 0||c_i_slotter (label)  : C I Slotter||c_i_join (new)  : 0||c_i_join (label)  : C I Join||c_i_partisi (new)  : 0||c_i_partisi (label)  : C I Partisi||c_i_layer (new)  : 0||c_i_layer (label)  : C I Layer||c_i_pisau_sheet (new)  : 0||c_i_pisau_sheet (label)  : C I Pisau Sheet||c_i_kupas (new)  : 0||c_i_kupas (label)  : C I Kupas||c_i_qc (new)  : 180000000||c_i_qc (label)  : C I Qc||c_i_kirim (new)  : 180000000||c_i_kirim (label)  : C I Kirim||c_i_packing (new)  : 0||c_i_packing (label)  : C I Packing||c_i_ongkos_lam (new)  : 0||c_i_ongkos_lam (label)  : C I Ongkos Lam||c_i_lam_sheet_flat (new)  : 0||c_i_lam_sheet_flat (label)  : C I Lam Sheet Flat||c_film_cost_pc (new)  : 0||c_film_cost_pc (label)  : C Film Cost Pc||c_i_film (new)  : 0||c_i_film (label)  : C I Film||c_plate_cost_pc (new)  : 0||c_plate_cost_pc (label)  : C Plate Cost Pc||c_i_plate (new)  : 0||c_i_plate (label)  : C I Plate||c_knife_cost_pc (new)  : 0||c_knife_cost_pc (label)  : C Knife Cost Pc||c_i_knife (new)  : 0||c_i_knife (label)  : C I Knife||calc_notes (new)  : ||calc_notes (label)  : {lang_hpp_parts_fld_calc_notes}||description_calc (new)  : v14 18/07/2026 14:31:05 SHEET paper=0 proc=1150 coat=0 lam=0 sfef=0 tool=0 sheet=23949.58 TOTAL=25099.58 flag=SHEET_OK COAT_SKIP LAM_SKIP TOOL_SKIP ||description_calc (label)  : Description Calc||created_by (new)  : wiwik||created_by (label)  : {lang_hpp_parts_fld_created_by}||created_log (new)  : 2026-07-18 14:31:05||created_log (label)  : {lang_hpp_parts_fld_created_log}||updated_by (new)  : wiwik||updated_by (label)  : {lang_hpp_parts_fld_updated_by}||updated_log (new)  : 2026-07-18 14:31:05||updated_log (label)  : {lang_hpp_parts_fld_updated_log}||deta_coat (new)  : ||deta_coat (label)  : Coating');
INSERT INTO `sc_log` VALUES (1755, '2026-07-18 14:31:05', 'wiwik', 'form_hpp_parts_coating', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1756, '2026-07-18 14:31:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1757, '2026-07-18 14:31:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1758, '2026-07-18 14:31:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1759, '2026-07-18 14:31:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1760, '2026-07-18 14:31:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1761, '2026-07-18 14:31:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1762, '2026-07-18 14:31:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1763, '2026-07-18 14:31:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1764, '2026-07-18 14:31:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1765, '2026-07-18 14:31:48', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1766, '2026-07-18 14:31:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1767, '2026-07-18 14:31:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1768, '2026-07-18 14:32:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1769, '2026-07-18 14:32:08', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1770, '2026-07-18 14:32:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1771, '2026-07-18 14:32:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1772, '2026-07-18 14:32:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1773, '2026-07-18 14:32:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1774, '2026-07-18 14:32:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1775, '2026-07-18 14:32:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1776, '2026-07-18 14:33:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1777, '2026-07-18 14:33:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1778, '2026-07-18 14:34:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1779, '2026-07-18 14:34:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1780, '2026-07-18 14:35:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1781, '2026-07-18 14:35:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1782, '2026-07-18 14:36:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1783, '2026-07-18 14:36:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1784, '2026-07-18 14:37:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1785, '2026-07-18 14:37:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1786, '2026-07-18 14:38:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1787, '2026-07-18 14:38:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1788, '2026-07-18 14:39:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1789, '2026-07-18 14:39:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1790, '2026-07-18 14:40:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1791, '2026-07-18 14:40:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1792, '2026-07-18 14:41:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1793, '2026-07-18 14:41:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1794, '2026-07-18 14:42:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1795, '2026-07-18 14:42:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1796, '2026-07-18 14:43:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1797, '2026-07-18 14:43:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1798, '2026-07-18 14:44:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1799, '2026-07-18 14:44:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1800, '2026-07-18 14:45:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1801, '2026-07-18 14:45:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1802, '2026-07-18 14:46:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1803, '2026-07-18 14:46:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1804, '2026-07-18 14:47:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1805, '2026-07-18 14:47:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1806, '2026-07-18 14:48:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1807, '2026-07-18 14:48:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1808, '2026-07-18 14:49:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1809, '2026-07-18 14:49:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1810, '2026-07-18 14:50:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1811, '2026-07-18 14:50:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1812, '2026-07-18 14:51:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1813, '2026-07-18 14:51:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1814, '2026-07-18 14:52:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1815, '2026-07-18 14:52:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1816, '2026-07-18 14:53:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1817, '2026-07-18 14:53:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1818, '2026-07-18 14:54:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1819, '2026-07-18 14:54:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1820, '2026-07-18 14:55:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1821, '2026-07-18 14:55:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1822, '2026-07-18 14:56:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1823, '2026-07-18 14:56:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1824, '2026-07-18 14:57:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1825, '2026-07-18 14:57:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1826, '2026-07-18 14:58:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1827, '2026-07-18 14:58:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1828, '2026-07-18 14:59:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1829, '2026-07-18 14:59:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1830, '2026-07-18 15:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1831, '2026-07-18 15:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1832, '2026-07-18 15:01:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1833, '2026-07-18 15:01:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1834, '2026-07-18 15:01:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1835, '2026-07-18 15:01:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1836, '2026-07-18 15:01:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1837, '2026-07-18 15:01:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1838, '2026-07-18 15:01:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1839, '2026-07-18 15:01:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1840, '2026-07-18 15:01:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1841, '2026-07-18 15:01:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1842, '2026-07-18 15:01:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1843, '2026-07-18 15:01:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1844, '2026-07-18 15:01:53', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_id : 9||--> fields <-- is_ppn (old)  : 1||is_ppn (new)  : 0||is_ppn (label)  : Pilih PPn ||i_dim_panjang_mm (old)  : 0.00||i_dim_panjang_mm (new)  : 427.00||i_dim_panjang_mm (label)  : Panjang (mm)||i_dim_lebar_mm (old)  : 0.00||i_dim_lebar_mm (new)  : 220.00||i_dim_lebar_mm (label)  : Lebar (mm)||i_dim_tinggi_mm (old)  : 0.00||i_dim_tinggi_mm (new)  : 172.00||i_dim_tinggi_mm (label)  : Tinggi (mm)||i_lidah_lem_mm (old)  : 0.00||i_lidah_lem_mm (new)  : 35.00||i_lidah_lem_mm (label)  : Lidah Lem (mm)');
INSERT INTO `sc_log` VALUES (1845, '2026-07-18 15:01:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1846, '2026-07-18 15:01:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1847, '2026-07-18 15:02:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1848, '2026-07-18 15:02:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1849, '2026-07-18 15:02:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1850, '2026-07-18 15:02:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1851, '2026-07-18 15:02:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1852, '2026-07-18 15:02:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1853, '2026-07-18 15:02:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1854, '2026-07-18 15:02:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1855, '2026-07-18 15:02:38', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_id : 9||--> fields <-- qty_order_pcs (old)  : 1000.00||qty_order_pcs (new)  : 1800000.00||qty_order_pcs (label)  : Qty Order Pcs||total_material_cost (old)  : 0.0000||total_material_cost (new)  : 23949.58||total_material_cost (label)  : Total Material Cost||total_process_cost (old)  : 0.0000||total_process_cost (new)  : 1150.00||total_process_cost (label)  : Total Process Cost||total_hpp (old)  : 0.0000||total_hpp (new)  : 25099.5832||total_hpp (label)  : Total Hpp||total_after_margin (old)  : 0.0000||total_after_margin (new)  : 33178.56||total_after_margin (label)  : Total After Margin||total_after_ppn (old)  : 0.0000||total_after_ppn (new)  : 30300.52||total_after_ppn (label)  : Total After Ppn||price_per_pcs (old)  : 0.0000||price_per_pcs (new)  : 28201.7789||price_per_pcs (label)  : Price Per Pcs');
INSERT INTO `sc_log` VALUES (1856, '2026-07-18 15:02:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1857, '2026-07-18 15:02:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1858, '2026-07-18 15:02:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1859, '2026-07-18 15:02:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1860, '2026-07-18 15:03:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1861, '2026-07-18 15:03:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1862, '2026-07-18 15:03:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1863, '2026-07-18 15:03:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1864, '2026-07-18 15:03:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1865, '2026-07-18 15:03:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1866, '2026-07-18 15:03:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1867, '2026-07-18 15:03:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1868, '2026-07-18 15:03:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1869, '2026-07-18 15:03:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1870, '2026-07-18 15:03:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1871, '2026-07-18 15:03:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1872, '2026-07-18 15:04:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1873, '2026-07-18 15:04:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1874, '2026-07-18 15:04:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1875, '2026-07-18 15:04:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1876, '2026-07-18 15:04:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1877, '2026-07-18 15:04:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1878, '2026-07-18 15:04:33', 'wiwik', 'form_hpp', 'Scriptcase', '192.168.1.254', 'update', '--> keys <-- hpp_id : 9||--> fields <-- customer_id (old)  : 0||customer_id (new)  : 138||customer_id (label)  : Pilih Customer (*)||customer_name (old)  : ||customer_name (new)  : CV SAMUDRA KIAT JAYA||customer_name (label)  : Nama Calon Customer / Lead');
INSERT INTO `sc_log` VALUES (1879, '2026-07-18 15:04:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1880, '2026-07-18 15:04:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1881, '2026-07-18 15:04:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1882, '2026-07-18 15:04:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1883, '2026-07-18 15:04:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1884, '2026-07-18 15:04:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1885, '2026-07-18 15:05:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1886, '2026-07-18 15:05:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1887, '2026-07-18 15:05:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1888, '2026-07-18 15:05:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1889, '2026-07-18 15:05:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1890, '2026-07-18 15:05:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1891, '2026-07-18 15:05:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1892, '2026-07-18 15:05:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1893, '2026-07-18 15:05:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1894, '2026-07-18 15:05:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1895, '2026-07-18 15:06:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1896, '2026-07-18 15:06:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1897, '2026-07-18 15:07:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1898, '2026-07-18 15:07:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1899, '2026-07-18 15:08:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1900, '2026-07-18 15:08:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1901, '2026-07-18 15:09:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1902, '2026-07-18 15:09:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1903, '2026-07-18 15:10:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1904, '2026-07-18 15:10:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1905, '2026-07-18 15:11:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1906, '2026-07-18 15:11:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1907, '2026-07-18 15:12:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1908, '2026-07-18 15:12:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1909, '2026-07-18 15:13:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1910, '2026-07-18 15:13:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1911, '2026-07-18 15:14:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1912, '2026-07-18 15:14:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1913, '2026-07-18 15:15:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1914, '2026-07-18 15:15:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1915, '2026-07-18 15:16:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1916, '2026-07-18 15:16:35', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1917, '2026-07-18 15:16:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1918, '2026-07-18 15:16:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1919, '2026-07-18 15:16:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1920, '2026-07-18 15:16:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1921, '2026-07-18 15:16:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1922, '2026-07-18 15:16:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1923, '2026-07-18 15:17:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1924, '2026-07-18 15:17:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1925, '2026-07-18 15:17:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1926, '2026-07-18 15:17:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1927, '2026-07-18 15:17:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1928, '2026-07-18 15:17:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1929, '2026-07-18 15:17:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1930, '2026-07-18 15:17:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1931, '2026-07-18 15:18:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1932, '2026-07-18 15:18:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1933, '2026-07-18 15:19:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1934, '2026-07-18 15:19:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1935, '2026-07-18 15:20:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1936, '2026-07-18 15:20:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1937, '2026-07-18 15:21:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1938, '2026-07-18 15:21:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1939, '2026-07-18 15:22:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1940, '2026-07-18 15:22:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1941, '2026-07-18 15:23:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1942, '2026-07-18 15:23:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1943, '2026-07-18 15:24:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1944, '2026-07-18 15:24:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1945, '2026-07-18 15:25:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1946, '2026-07-18 15:25:43', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1947, '2026-07-18 15:25:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1948, '2026-07-18 15:25:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1949, '2026-07-18 15:25:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1950, '2026-07-18 15:25:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1951, '2026-07-18 15:26:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1952, '2026-07-18 15:26:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1953, '2026-07-18 15:26:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1954, '2026-07-18 15:26:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1955, '2026-07-18 15:26:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1956, '2026-07-18 15:26:28', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1957, '2026-07-18 15:26:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1958, '2026-07-18 15:26:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1959, '2026-07-18 15:26:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1960, '2026-07-18 15:26:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1961, '2026-07-18 15:27:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1962, '2026-07-18 15:27:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1963, '2026-07-18 15:28:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1964, '2026-07-18 15:28:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1965, '2026-07-18 15:29:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1966, '2026-07-18 15:29:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1967, '2026-07-18 15:30:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1968, '2026-07-18 15:30:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1969, '2026-07-18 15:31:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1970, '2026-07-18 15:31:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1971, '2026-07-18 15:32:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1972, '2026-07-18 15:32:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1973, '2026-07-18 15:33:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1974, '2026-07-18 15:33:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1975, '2026-07-18 15:34:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1976, '2026-07-18 15:34:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1977, '2026-07-18 15:35:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1978, '2026-07-18 15:35:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1979, '2026-07-18 15:36:06', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1980, '2026-07-18 15:36:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1981, '2026-07-18 15:36:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1982, '2026-07-18 15:36:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1983, '2026-07-18 15:36:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1984, '2026-07-18 15:36:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1985, '2026-07-18 15:36:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1986, '2026-07-18 15:36:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1987, '2026-07-18 15:36:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1988, '2026-07-18 15:36:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1989, '2026-07-18 15:36:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1990, '2026-07-18 15:36:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1991, '2026-07-18 15:37:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1992, '2026-07-18 15:37:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1993, '2026-07-18 15:37:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1994, '2026-07-18 15:37:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1995, '2026-07-18 15:37:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1996, '2026-07-18 15:37:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1997, '2026-07-18 15:37:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1998, '2026-07-18 15:37:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (1999, '2026-07-18 15:37:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2000, '2026-07-18 15:37:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2001, '2026-07-18 15:37:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2002, '2026-07-18 15:37:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2003, '2026-07-18 15:38:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2004, '2026-07-18 15:38:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2005, '2026-07-18 15:38:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2006, '2026-07-18 15:38:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2007, '2026-07-18 15:38:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2008, '2026-07-18 15:38:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2009, '2026-07-18 15:38:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2010, '2026-07-18 15:38:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2011, '2026-07-18 15:39:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2012, '2026-07-18 15:39:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2013, '2026-07-18 15:40:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2014, '2026-07-18 15:40:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2015, '2026-07-18 15:41:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2016, '2026-07-18 15:41:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2017, '2026-07-18 15:42:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2018, '2026-07-18 15:42:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2019, '2026-07-18 15:43:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2020, '2026-07-18 15:43:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2021, '2026-07-18 15:44:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2022, '2026-07-18 15:44:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2023, '2026-07-18 15:45:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2024, '2026-07-18 15:45:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2025, '2026-07-18 15:46:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2026, '2026-07-18 15:46:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2027, '2026-07-18 15:47:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2028, '2026-07-18 15:47:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2029, '2026-07-18 15:48:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2030, '2026-07-18 15:48:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2031, '2026-07-18 15:49:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2032, '2026-07-18 15:49:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2033, '2026-07-18 15:50:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2034, '2026-07-18 15:50:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2035, '2026-07-18 15:51:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2036, '2026-07-18 15:51:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2037, '2026-07-18 15:52:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2038, '2026-07-18 15:52:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2039, '2026-07-18 15:53:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2040, '2026-07-18 15:53:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2041, '2026-07-18 15:54:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2042, '2026-07-18 15:54:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2043, '2026-07-18 15:55:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2044, '2026-07-18 15:55:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2045, '2026-07-18 15:56:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2046, '2026-07-18 15:56:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2047, '2026-07-18 15:57:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2048, '2026-07-18 15:57:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2049, '2026-07-18 15:58:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2050, '2026-07-18 15:58:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2051, '2026-07-18 15:59:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2052, '2026-07-18 15:59:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2053, '2026-07-18 16:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2054, '2026-07-18 16:00:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2055, '2026-07-18 16:01:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2056, '2026-07-18 16:01:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2057, '2026-07-18 16:02:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2058, '2026-07-18 16:02:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2059, '2026-07-18 16:03:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2060, '2026-07-18 16:03:16', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2061, '2026-07-18 16:03:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2062, '2026-07-18 16:03:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2063, '2026-07-18 16:03:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2064, '2026-07-18 16:03:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2065, '2026-07-18 16:03:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2066, '2026-07-18 16:03:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2067, '2026-07-18 16:03:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2068, '2026-07-18 16:03:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2069, '2026-07-18 16:03:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2070, '2026-07-18 16:03:58', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2071, '2026-07-18 16:04:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2072, '2026-07-18 16:04:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2073, '2026-07-18 16:04:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2074, '2026-07-18 16:04:19', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2075, '2026-07-18 16:04:23', 'wiwik', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2076, '2026-07-18 16:04:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2077, '2026-07-18 16:04:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2078, '2026-07-18 16:04:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2079, '2026-07-18 16:04:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2080, '2026-07-18 16:04:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2081, '2026-07-18 16:04:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2082, '2026-07-18 16:04:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2083, '2026-07-18 16:04:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2084, '2026-07-18 16:05:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2085, '2026-07-18 16:05:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2086, '2026-07-18 16:05:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2087, '2026-07-18 16:05:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2088, '2026-07-18 16:05:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2089, '2026-07-18 16:05:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2090, '2026-07-18 16:05:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2091, '2026-07-18 16:05:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2092, '2026-07-18 16:05:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2093, '2026-07-18 16:05:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2094, '2026-07-18 16:05:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2095, '2026-07-18 16:05:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2096, '2026-07-18 16:06:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2097, '2026-07-18 16:06:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2098, '2026-07-18 16:06:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2099, '2026-07-18 16:06:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2100, '2026-07-18 16:06:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2101, '2026-07-18 16:06:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2102, '2026-07-18 16:07:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2103, '2026-07-18 16:07:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2104, '2026-07-18 16:08:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2105, '2026-07-18 16:08:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2106, '2026-07-18 16:09:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2107, '2026-07-18 16:09:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2108, '2026-07-18 16:10:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2109, '2026-07-18 16:10:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2110, '2026-07-18 16:11:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2111, '2026-07-18 16:11:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2112, '2026-07-18 16:12:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2113, '2026-07-18 16:12:10', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2114, '2026-07-18 16:12:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2115, '2026-07-18 16:12:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2116, '2026-07-18 16:12:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2117, '2026-07-18 16:12:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2118, '2026-07-18 16:12:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2119, '2026-07-18 16:12:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2120, '2026-07-18 16:12:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2121, '2026-07-18 16:12:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2122, '2026-07-18 16:12:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2123, '2026-07-18 16:12:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2124, '2026-07-18 16:13:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2125, '2026-07-18 16:13:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2126, '2026-07-18 16:13:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2127, '2026-07-18 16:13:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2128, '2026-07-18 16:14:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2129, '2026-07-18 16:14:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2130, '2026-07-18 16:15:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2131, '2026-07-18 16:15:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2132, '2026-07-18 16:16:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2133, '2026-07-18 16:16:53', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2134, '2026-07-18 16:16:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2135, '2026-07-18 16:16:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2136, '2026-07-18 16:17:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2137, '2026-07-18 16:17:07', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2138, '2026-07-18 16:17:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2139, '2026-07-18 16:17:17', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2140, '2026-07-18 16:17:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2141, '2026-07-18 16:17:27', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2142, '2026-07-18 16:17:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2143, '2026-07-18 16:17:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2144, '2026-07-18 16:17:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2145, '2026-07-18 16:17:47', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2146, '2026-07-18 16:17:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2147, '2026-07-18 16:17:57', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2148, '2026-07-18 16:18:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2149, '2026-07-18 16:18:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2150, '2026-07-18 16:19:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2151, '2026-07-18 16:19:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2152, '2026-07-18 16:20:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2153, '2026-07-18 16:20:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2154, '2026-07-18 16:21:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2155, '2026-07-18 16:21:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2156, '2026-07-18 16:22:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2157, '2026-07-18 16:22:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2158, '2026-07-18 16:23:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2159, '2026-07-18 16:23:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2160, '2026-07-18 16:24:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2161, '2026-07-18 16:24:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2162, '2026-07-18 16:25:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2163, '2026-07-18 16:25:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2164, '2026-07-18 16:26:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2165, '2026-07-18 16:26:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2166, '2026-07-18 16:27:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2167, '2026-07-18 16:27:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2168, '2026-07-18 16:28:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2169, '2026-07-18 16:28:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2170, '2026-07-18 16:29:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2171, '2026-07-18 16:29:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2172, '2026-07-18 16:30:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2173, '2026-07-18 16:30:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2174, '2026-07-18 16:31:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2175, '2026-07-18 16:31:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2176, '2026-07-18 16:32:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2177, '2026-07-18 16:32:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2178, '2026-07-18 16:33:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2179, '2026-07-18 16:33:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2180, '2026-07-18 16:34:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2181, '2026-07-18 16:34:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2182, '2026-07-18 16:35:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2183, '2026-07-18 16:35:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2184, '2026-07-18 16:36:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2185, '2026-07-18 16:36:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2186, '2026-07-18 16:37:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2187, '2026-07-18 16:37:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2188, '2026-07-18 16:38:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2189, '2026-07-18 16:38:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2190, '2026-07-18 16:39:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2191, '2026-07-18 16:39:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2192, '2026-07-18 16:40:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2193, '2026-07-18 16:40:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2194, '2026-07-18 16:41:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2195, '2026-07-18 16:41:56', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2196, '2026-07-18 16:42:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2197, '2026-07-18 16:42:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2198, '2026-07-18 16:43:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2199, '2026-07-18 16:43:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2200, '2026-07-18 16:44:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2201, '2026-07-18 16:44:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2202, '2026-07-18 16:45:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2203, '2026-07-18 16:45:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2204, '2026-07-18 16:46:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2205, '2026-07-18 16:46:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2206, '2026-07-18 16:47:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2207, '2026-07-18 16:47:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2208, '2026-07-18 16:48:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2209, '2026-07-18 16:48:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2210, '2026-07-18 16:49:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2211, '2026-07-18 16:49:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2212, '2026-07-18 16:50:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2213, '2026-07-18 16:50:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2214, '2026-07-18 16:51:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2215, '2026-07-18 16:51:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2216, '2026-07-18 16:52:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2217, '2026-07-18 16:52:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2218, '2026-07-18 16:53:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2219, '2026-07-18 16:53:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2220, '2026-07-18 16:54:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2221, '2026-07-18 16:54:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2222, '2026-07-18 16:55:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2223, '2026-07-18 16:55:55', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2224, '2026-07-18 16:57:00', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2225, '2026-07-18 16:57:03', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2226, '2026-07-18 16:58:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2227, '2026-07-18 16:58:37', 'wiwik', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2228, '2026-09-01 14:04:35', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2229, '2026-09-01 14:04:43', '', 'sec_Login', 'User', '192.168.1.254', 'login Fail', 'Someone tried to login with the usernameadmin');
INSERT INTO `sc_log` VALUES (2230, '2026-09-01 14:04:55', '', 'sec_Login', 'User', '192.168.1.254', 'login Fail', 'Someone tried to login with the usernameadmin');
INSERT INTO `sc_log` VALUES (2231, '2026-09-01 14:05:48', 'admin', 'sec_Login', 'User', '192.168.1.254', 'login', 'Successfully logged into the system!');
INSERT INTO `sc_log` VALUES (2232, '2026-09-01 14:05:48', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2233, '2026-09-01 14:05:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2234, '2026-09-01 14:05:51', 'admin', 'grid_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2235, '2026-09-01 14:05:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2236, '2026-09-01 14:05:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2237, '2026-09-01 14:06:01', 'admin', 'form_hpp', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2238, '2026-09-01 14:06:01', 'admin', 'form_hpp_parts', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2239, '2026-09-01 14:06:02', 'admin', 'form_hpp_parts_coating', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2240, '2026-09-01 14:06:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2241, '2026-09-01 14:06:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2242, '2026-09-01 14:06:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2243, '2026-09-01 14:06:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2244, '2026-09-01 14:06:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2245, '2026-09-01 14:06:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2246, '2026-09-01 14:06:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2247, '2026-09-01 14:06:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2248, '2026-09-01 14:06:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2249, '2026-09-01 14:06:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2250, '2026-09-01 14:06:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2251, '2026-09-01 14:06:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2252, '2026-09-01 14:07:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2253, '2026-09-01 14:07:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2254, '2026-09-01 14:07:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2255, '2026-09-01 14:07:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2256, '2026-09-01 14:07:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2257, '2026-09-01 14:07:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2258, '2026-09-01 14:07:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2259, '2026-09-01 14:07:40', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2260, '2026-09-01 14:07:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2261, '2026-09-01 14:07:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2262, '2026-09-01 14:07:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2263, '2026-09-01 14:07:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2264, '2026-09-01 14:08:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2265, '2026-09-01 14:08:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2266, '2026-09-01 14:08:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2267, '2026-09-01 14:08:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2268, '2026-09-01 14:08:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2269, '2026-09-01 14:08:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2270, '2026-09-01 14:08:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2271, '2026-09-01 14:08:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2272, '2026-09-01 14:08:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2273, '2026-09-01 14:08:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2274, '2026-09-01 14:08:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2275, '2026-09-01 14:08:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2276, '2026-09-01 14:09:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2277, '2026-09-01 14:09:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2278, '2026-09-01 14:09:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2279, '2026-09-01 14:09:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2280, '2026-09-01 14:09:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2281, '2026-09-01 14:09:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2282, '2026-09-01 14:09:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2283, '2026-09-01 14:09:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2284, '2026-09-01 14:09:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2285, '2026-09-01 14:09:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2286, '2026-09-01 14:09:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2287, '2026-09-01 14:09:59', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2288, '2026-09-01 14:10:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2289, '2026-09-01 14:10:09', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2290, '2026-09-01 14:10:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2291, '2026-09-01 14:10:19', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2292, '2026-09-01 14:10:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2293, '2026-09-01 14:10:29', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2294, '2026-09-01 14:10:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2295, '2026-09-01 14:10:39', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2296, '2026-09-01 14:10:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2297, '2026-09-01 14:10:49', 'admin', 'menumain', 'Scriptcase', '192.168.1.254', 'access', '');
INSERT INTO `sc_log` VALUES (2298, '2026-09-01 14:10:51', '', 'sec_Login', 'Scriptcase', '192.168.1.254', 'access', '');

-- ----------------------------
-- Table structure for sec_apps
-- ----------------------------
DROP TABLE IF EXISTS `sec_apps`;
CREATE TABLE `sec_apps`  (
  `app_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`app_name`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_apps
-- ----------------------------
INSERT INTO `sec_apps` VALUES ('form_hpp', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_hpp_parts', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_hpp_parts_coating', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_hpp_parts_inline', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_coating_rate', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_film_rate', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_hpp_calc_type', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_hpp_constants', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_join_type', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_packing_type', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_paper_grammage', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_part_template', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_part_type', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_routing_template', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_sheet_discount_tier', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_sheet_rate', 'form', '');
INSERT INTO `sec_apps` VALUES ('form_m_sheet_supplier', 'form', '');
INSERT INTO `sec_apps` VALUES ('grid_hpp', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_coating_rate', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_film_rate', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_hpp_calc_type', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_hpp_constants', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_join_type', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_packing_type', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_paper_grammage', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_part_template', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_part_type', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_routing_template', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_sheet_discount_tier', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_sheet_rate', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_m_sheet_supplier', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_grammage_latest', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_card', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_card_material', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_card_process', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_constant', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_currency', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_customer', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_diecut', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_machine', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_process', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_product', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_product_cfg_parts', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_product_parts', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_raw_material', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_uom', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_vendor', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_warehouse', 'cons', '');
INSERT INTO `sec_apps` VALUES ('grid_v1_m_wh_loc', 'cons', '');
INSERT INTO `sec_apps` VALUES ('menumain', 'menu2', 'Menu Main');
INSERT INTO `sec_apps` VALUES ('menumain_ori', 'Menu2', '');
INSERT INTO `sec_apps` VALUES ('notif_form_notif_profiles', 'form', 'NOTIFICATIONS MODULE :: Create and edit Profiles');
INSERT INTO `sec_apps` VALUES ('notif_form_notiftable_user_tags', 'form', 'NOTIFICATIONS MODULE :: Create/Edit tags saved to users');
INSERT INTO `sec_apps` VALUES ('notif_form_sec_notif_inbox_tags', 'form', 'NOTIFICATIONS MODULE :: Associate notification with tag');
INSERT INTO `sec_apps` VALUES ('notif_form_sec_notif_pref', 'form', 'NOTIFICATIONS MODULE :: Edit user preferences');
INSERT INTO `sec_apps` VALUES ('notif_form_sec_notif_tags', 'form', 'NOTIFICATIONS MODULE :: Create and edit Tags');
INSERT INTO `sec_apps` VALUES ('notif_form_sec_notifications', 'form', 'NOTIFICATIONS MODULE :: Create message');
INSERT INTO `sec_apps` VALUES ('notif_grid_notif_user_tags', 'cons', 'NOTIFICATIONS MODULE :: Tags saved to users');
INSERT INTO `sec_apps` VALUES ('notif_grid_sec_notif_inbox', 'cons', 'NOTIFICATIONS MODULE :: View message inbox');
INSERT INTO `sec_apps` VALUES ('notif_grid_sec_notif_outbox', 'cons', 'NOTIFICATIONS MODULE :: View sent messages');
INSERT INTO `sec_apps` VALUES ('notif_grid_sec_notif_outbox_sub', 'cons', 'NOTIFICATIONS MODULE :: View details of sent messages');
INSERT INTO `sec_apps` VALUES ('notif_grid_sec_notif_read', 'cons', 'NOTIFICATIONS MODULE :: Read message');
INSERT INTO `sec_apps` VALUES ('sec_add_2fa', 'contr', 'Add 2FA to user');
INSERT INTO `sec_apps` VALUES ('sec_change_pswd', 'contr', 'Change password');
INSERT INTO `sec_apps` VALUES ('sec_control_2fa', 'cons', 'Control 2FA');
INSERT INTO `sec_apps` VALUES ('sec_form_add_users', 'form', 'Add users');
INSERT INTO `sec_apps` VALUES ('sec_form_edit_users', 'form', 'Edit users');
INSERT INTO `sec_apps` VALUES ('sec_form_sec_apps', 'form', 'SECURITY MODULE :: Add apps');
INSERT INTO `sec_apps` VALUES ('sec_form_sec_groups', 'form', 'SECURITY MODULE :: Show Groups');
INSERT INTO `sec_apps` VALUES ('sec_form_sec_groups_apps', 'form', 'SECURITY MODULE :: Groups/Apps');
INSERT INTO `sec_apps` VALUES ('sec_grid_sec_apps', 'cons', 'SECURITY MODULE :: Show apps');
INSERT INTO `sec_apps` VALUES ('sec_grid_sec_groups', 'cons', 'SECURITY MODULE :: Show Groups');
INSERT INTO `sec_apps` VALUES ('sec_grid_sec_users', 'cons', 'SECURITY MODULE :: Show users');
INSERT INTO `sec_apps` VALUES ('sec_grid_sec_users_groups', 'cons', 'SECURITY MODULE :: Show users by groups');
INSERT INTO `sec_apps` VALUES ('sec_logged', 'contr', 'SECURITY MODULE :: Logged application');
INSERT INTO `sec_apps` VALUES ('sec_logged_users', 'cons', 'List logged users');
INSERT INTO `sec_apps` VALUES ('sec_Login', 'contr', 'Login - Initial Application');
INSERT INTO `sec_apps` VALUES ('sec_my_info', 'form', 'Edit Profile');
INSERT INTO `sec_apps` VALUES ('sec_retrieve_pswd', 'contr', 'Retrieve Password');
INSERT INTO `sec_apps` VALUES ('sec_search_sec_groups', 'filter', 'Search Groups');
INSERT INTO `sec_apps` VALUES ('sec_settings', 'contr', 'Settings');
INSERT INTO `sec_apps` VALUES ('sec_sync_apps', 'contr', 'Resync Applications');

-- ----------------------------
-- Table structure for sec_groups
-- ----------------------------
DROP TABLE IF EXISTS `sec_groups`;
CREATE TABLE `sec_groups`  (
  `group_id` int NOT NULL AUTO_INCREMENT,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`group_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_groups
-- ----------------------------
INSERT INTO `sec_groups` VALUES (1, 'Administrator');

-- ----------------------------
-- Table structure for sec_groups_apps
-- ----------------------------
DROP TABLE IF EXISTS `sec_groups_apps`;
CREATE TABLE `sec_groups_apps`  (
  `group_id` int NOT NULL,
  `app_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priv_access` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `priv_insert` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `priv_delete` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `priv_update` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `priv_export` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `priv_print` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`group_id`, `app_name`) USING BTREE,
  INDEX `sec_groups_apps_ibfk_2`(`app_name` ASC) USING BTREE,
  CONSTRAINT `sec_groups_apps_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `sec_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sec_groups_apps_ibfk_2` FOREIGN KEY (`app_name`) REFERENCES `sec_apps` (`app_name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_groups_apps
-- ----------------------------
INSERT INTO `sec_groups_apps` VALUES (1, 'form_hpp', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_hpp_parts', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_hpp_parts_coating', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_hpp_parts_inline', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_coating_rate', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_film_rate', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_hpp_calc_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_hpp_constants', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_join_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_packing_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_paper_grammage', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_part_template', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_part_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_routing_template', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_sheet_discount_tier', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_sheet_rate', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'form_m_sheet_supplier', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_hpp', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_coating_rate', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_film_rate', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_hpp_calc_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_hpp_constants', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_join_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_packing_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_paper_grammage', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_part_template', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_part_type', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_routing_template', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_sheet_discount_tier', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_sheet_rate', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_m_sheet_supplier', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_grammage_latest', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_card', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_card_material', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_card_process', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_constant', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_currency', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_customer', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_diecut', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_machine', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_process', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_product', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_product_cfg_parts', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_product_parts', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_raw_material', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_uom', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_vendor', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_warehouse', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'grid_v1_m_wh_loc', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'menumain_ori', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_form_notif_profiles', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_form_notiftable_user_tags', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_form_sec_notif_inbox_tags', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_form_sec_notif_pref', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_form_sec_notif_tags', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_form_sec_notifications', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_grid_notif_user_tags', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_grid_sec_notif_inbox', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_grid_sec_notif_outbox', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_grid_sec_notif_outbox_sub', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'notif_grid_sec_notif_read', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_add_2fa', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_change_pswd', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_control_2fa', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_form_add_users', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_form_edit_users', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_form_sec_apps', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_form_sec_groups', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_form_sec_groups_apps', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_grid_sec_apps', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_grid_sec_groups', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_grid_sec_users', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_grid_sec_users_groups', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_logged', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_logged_users', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_Login', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_my_info', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_retrieve_pswd', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_search_sec_groups', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_settings', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO `sec_groups_apps` VALUES (1, 'sec_sync_apps', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');

-- ----------------------------
-- Table structure for sec_logged
-- ----------------------------
DROP TABLE IF EXISTS `sec_logged`;
CREATE TABLE `sec_logged`  (
  `login` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_login` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `sc_session` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_logged
-- ----------------------------
INSERT INTO `sec_logged` VALUES ('wiwik', '1784355067.3065', 'v1mgdeuisrk073oauv3rkghi80', '192.168.1.254');
INSERT INTO `sec_logged` VALUES ('\'admin\'', '1788246283.1843', '_SC_FAIL_SC_', '192.168.1.254');
INSERT INTO `sec_logged` VALUES ('\'admin\'', '1788246295.0353', '_SC_FAIL_SC_', '192.168.1.254');

-- ----------------------------
-- Table structure for sec_settings
-- ----------------------------
DROP TABLE IF EXISTS `sec_settings`;
CREATE TABLE `sec_settings`  (
  `set_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `set_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`set_name`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_settings
-- ----------------------------
INSERT INTO `sec_settings` VALUES ('auth_sn_fb', 'N');
INSERT INTO `sec_settings` VALUES ('auth_sn_fb_app_id', '');
INSERT INTO `sec_settings` VALUES ('auth_sn_fb_secret', '');
INSERT INTO `sec_settings` VALUES ('auth_sn_google', 'N');
INSERT INTO `sec_settings` VALUES ('auth_sn_google_client_id', '');
INSERT INTO `sec_settings` VALUES ('auth_sn_google_secret', '');
INSERT INTO `sec_settings` VALUES ('auth_sn_position', 'below');
INSERT INTO `sec_settings` VALUES ('auth_sn_x', 'N');
INSERT INTO `sec_settings` VALUES ('auth_sn_x_key', '');
INSERT INTO `sec_settings` VALUES ('auth_sn_x_secret', '');
INSERT INTO `sec_settings` VALUES ('brute_force', 'Y');
INSERT INTO `sec_settings` VALUES ('brute_force_attempts', '10');
INSERT INTO `sec_settings` VALUES ('brute_force_time_block', '10');
INSERT INTO `sec_settings` VALUES ('captcha', 'N');
INSERT INTO `sec_settings` VALUES ('cookie_expiration_time', '30');
INSERT INTO `sec_settings` VALUES ('enable_2fa', 'N');
INSERT INTO `sec_settings` VALUES ('enable_2fa_api', 'sys__NM__email_api');
INSERT INTO `sec_settings` VALUES ('enable_2fa_api_type', 'email');
INSERT INTO `sec_settings` VALUES ('enable_2fa_expiration_time', '300');
INSERT INTO `sec_settings` VALUES ('enable_2fa_mode', 'individual');
INSERT INTO `sec_settings` VALUES ('group_administrator', '1');
INSERT INTO `sec_settings` VALUES ('group_default', '');
INSERT INTO `sec_settings` VALUES ('language', 'N');
INSERT INTO `sec_settings` VALUES ('login_mode', 'both');
INSERT INTO `sec_settings` VALUES ('mfa_last_updated', '30');
INSERT INTO `sec_settings` VALUES ('new_users', 'N');
INSERT INTO `sec_settings` VALUES ('password_min', '5');
INSERT INTO `sec_settings` VALUES ('password_strength', 'lowercase_letter');
INSERT INTO `sec_settings` VALUES ('pswd_last_updated', '365');
INSERT INTO `sec_settings` VALUES ('recover_pswd', 'send_link');
INSERT INTO `sec_settings` VALUES ('remember_me', 'Y');
INSERT INTO `sec_settings` VALUES ('req_email_act', 'N');
INSERT INTO `sec_settings` VALUES ('retrieve_password', 'Y');
INSERT INTO `sec_settings` VALUES ('send_email_adm', 'N');
INSERT INTO `sec_settings` VALUES ('session_expire', 'R');
INSERT INTO `sec_settings` VALUES ('smtp_api', 'custom');
INSERT INTO `sec_settings` VALUES ('smtp_from_email', '275iwan92@gmail.com');
INSERT INTO `sec_settings` VALUES ('smtp_from_name', 'Johannes Iwan');
INSERT INTO `sec_settings` VALUES ('smtp_pass', '');
INSERT INTO `sec_settings` VALUES ('smtp_port', '587');
INSERT INTO `sec_settings` VALUES ('smtp_security', 'ssl');
INSERT INTO `sec_settings` VALUES ('smtp_server', 'smtp.gmail.com');
INSERT INTO `sec_settings` VALUES ('smtp_user', '275iwan92#gmail.com');
INSERT INTO `sec_settings` VALUES ('theme', 'Sc9_SoftCloud_sm');

-- ----------------------------
-- Table structure for sec_users
-- ----------------------------
DROP TABLE IF EXISTS `sec_users`;
CREATE TABLE `sec_users`  (
  `login` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pswd` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `active` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `activation_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `priv_admin` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `mfa` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `picture` longblob NULL,
  `role` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `phone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `pswd_last_updated` timestamp NULL DEFAULT NULL,
  `mfa_last_updated` timestamp NULL DEFAULT NULL,
  `employee_id` bigint UNSIGNED NULL DEFAULT NULL,
  `default_pswd` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`login`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_users
-- ----------------------------
INSERT INTO `sec_users` VALUES ('admin', '356982644ec11e59fc6f34bae7a986e5', 'Admin', 'admin@admin.com', 'Y', '', 'Y', NULL, NULL, NULL, NULL, '2026-04-17 22:25:33', NULL, NULL, 'administrator76');
INSERT INTO `sec_users` VALUES ('hendra', '7c5e42a1496508b1c18b087b6cb6451e', 'Carrij Soehendra', 'carrijsoehendra@gmail.com', 'Y', '', '', '', '', 'Direksi', '+628123523936', '2026-07-09 14:14:32', NULL, NULL, 'telasih0611');
INSERT INTO `sec_users` VALUES ('wiwik', '178e19874b2fe6b7552271684dc6cec1', 'Wiwik', 'wiwik@telasihmakmur.com', 'Y', '', '', '', '', 'Officer', '+62 838-5118-1264', '2026-07-10 14:27:48', NULL, 0, 'wiwik112016');

-- ----------------------------
-- Table structure for sec_users_groups
-- ----------------------------
DROP TABLE IF EXISTS `sec_users_groups`;
CREATE TABLE `sec_users_groups`  (
  `login` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_id` int NOT NULL,
  PRIMARY KEY (`login`, `group_id`) USING BTREE,
  INDEX `sec_users_groups_ibfk_2`(`group_id` ASC) USING BTREE,
  CONSTRAINT `sec_users_groups_ibfk_1` FOREIGN KEY (`login`) REFERENCES `sec_users` (`login`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sec_users_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `sec_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_users_groups
-- ----------------------------
INSERT INTO `sec_users_groups` VALUES ('admin', 1);
INSERT INTO `sec_users_groups` VALUES ('hendra', 1);
INSERT INTO `sec_users_groups` VALUES ('wiwik', 1);

-- ----------------------------
-- View structure for v1_flute_allowance
-- ----------------------------
DROP VIEW IF EXISTS `v1_flute_allowance`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_flute_allowance` AS select `m_flute_allowance`.`flute_type` AS `flute_type`,`m_flute_allowance`.`leb_panjang_mm` AS `leb_panjang_mm`,`m_flute_allowance`.`leb_lebar_mm` AS `leb_lebar_mm`,`m_flute_allowance`.`leb_tinggi_mm` AS `leb_tinggi_mm`,((`m_flute_allowance`.`leb_panjang_mm` + `m_flute_allowance`.`leb_lebar_mm`) * 2) AS `total_p_mm`,((`m_flute_allowance`.`leb_lebar_mm` + `m_flute_allowance`.`leb_tinggi_mm`) + 3) AS `total_l_mm`,`m_flute_allowance`.`is_active` AS `is_active` from `m_flute_allowance` where (`m_flute_allowance`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_grammage_all
-- ----------------------------
DROP VIEW IF EXISTS `v1_grammage_all`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_grammage_all` AS select `mg`.`id_cons_grammage` AS `id_cons_grammage`,`mg`.`paper_type` AS `paper_type`,`mg`.`grammage` AS `grammage`,`mg`.`duplex_price_list` AS `duplex_price_list`,`mg`.`duplex_dc_percentage` AS `duplex_dc_percentage`,`mg`.`ivory_ap_price_per_kg` AS `ivory_ap_price_per_kg`,`mg`.`effective_date` AS `effective_date`,(case when (`mg`.`paper_type` = 'DUPLEX') then concat(`mg`.`paper_type`,' ',`mg`.`grammage`,' gsm — Rp ',format(coalesce(`mg`.`duplex_price_list`,0),0),'/rim (',date_format(`mg`.`effective_date`,'%d/%m/%Y'),')') when (`mg`.`ivory_ap_price_per_kg` is not null) then concat(`mg`.`paper_type`,' ',`mg`.`grammage`,' gsm — Rp ',format(`mg`.`ivory_ap_price_per_kg`,0),'/kg (',date_format(`mg`.`effective_date`,'%d/%m/%Y'),')') else concat(`mg`.`paper_type`,' ',`mg`.`grammage`,' gsm — (no price)') end) AS `display_label` from `m_paper_grammage` `mg` where (`mg`.`is_active` = 1) order by `mg`.`paper_type`,`mg`.`grammage`,`mg`.`effective_date` desc;

-- ----------------------------
-- View structure for v1_grammage_latest
-- ----------------------------
DROP VIEW IF EXISTS `v1_grammage_latest`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_grammage_latest` AS select `mg`.`id_cons_grammage` AS `id_cons_grammage`,`mg`.`paper_type` AS `paper_type`,`mg`.`grammage` AS `grammage`,`mg`.`duplex_price_list` AS `duplex_price_list`,`mg`.`duplex_dc_percentage` AS `duplex_dc_percentage`,`mg`.`ivory_ap_price_per_kg` AS `ivory_ap_price_per_kg`,`mg`.`effective_date` AS `effective_date`,(case when (`mg`.`paper_type` = 'DUPLEX') then concat(`mg`.`paper_type`,' ',`mg`.`grammage`,' gsm — Rp ',format(coalesce(`mg`.`duplex_price_list`,0),0),'/rim (',date_format(`mg`.`effective_date`,'%d/%m/%Y'),')') when (`mg`.`ivory_ap_price_per_kg` is not null) then concat(`mg`.`paper_type`,' ',`mg`.`grammage`,' gsm — Rp ',format(`mg`.`ivory_ap_price_per_kg`,0),'/kg (',date_format(`mg`.`effective_date`,'%d/%m/%Y'),')') else concat(`mg`.`paper_type`,' ',`mg`.`grammage`,' gsm — (no price)') end) AS `display_label` from (`m_paper_grammage` `mg` join (select `m_paper_grammage`.`paper_type` AS `paper_type`,`m_paper_grammage`.`grammage` AS `grammage`,max(`m_paper_grammage`.`effective_date`) AS `max_date` from `m_paper_grammage` where (`m_paper_grammage`.`is_active` = 1) group by `m_paper_grammage`.`paper_type`,`m_paper_grammage`.`grammage`) `latest` on(((`mg`.`paper_type` = `latest`.`paper_type`) and (`mg`.`grammage` = `latest`.`grammage`) and (`mg`.`effective_date` = `latest`.`max_date`)))) where (`mg`.`is_active` = 1) order by `mg`.`paper_type`,`mg`.`grammage`;

-- ----------------------------
-- View structure for v1_m_card
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_card`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_card` AS select `mc`.`mcard_id` AS `mcard_id`,`mc`.`mcard_code` AS `mcard_code`,`mc`.`mcard_name` AS `mcard_name`,`mc`.`mcard_date` AS `mcard_date`,`mc`.`mcard_rev` AS `mcard_rev`,`mc`.`is_active` AS `is_active`,`mc`.`image_up` AS `image_up`,`mc`.`out_per_plano` AS `out_per_plano`,`mc`.`printsize_width` AS `printsize_width`,`mc`.`printsize_length` AS `printsize_length`,`mc`.`cfg_color` AS `cfg_color`,`mc`.`cfg_ink` AS `cfg_ink`,`mc`.`layout_attachment` AS `layout_attachment`,`mc`.`reff_parts_code` AS `reff_parts_code`,`mc`.`date_approval_email` AS `date_approval_email`,`mc`.`date_approval_digital` AS `date_approval_digital`,`mc`.`date_approval_sample` AS `date_approval_sample`,`mc`.`approval_by` AS `approval_by`,`mc`.`stat_print_original` AS `stat_print_original`,`mc`.`create_log` AS `create_log`,`mc`.`update_log` AS `update_log`,`mc`.`create_by` AS `create_by`,`mc`.`update_by` AS `update_by` from `tmmst`.`m_card` `mc` where (`mc`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_card_material
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_card_material`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_card_material` AS select `mcm`.`mcardrm_id` AS `mcardrm_id`,`mcm`.`mcard_id` AS `mcard_id`,`mcm`.`rawmat_id` AS `rawmat_id`,`mcm`.`rawmat_type` AS `rawmat_type`,`mcm`.`percentage_tolerance` AS `percentage_tolerance`,`mcm`.`uom_usage` AS `uom_usage`,`mcm`.`size_width` AS `size_width`,`mcm`.`size_length` AS `size_length`,`mcm`.`gram_weight` AS `gram_weight`,`mcm`.`create_log` AS `create_log`,`mcm`.`update_log` AS `update_log`,`mcm`.`create_by` AS `create_by`,`mcm`.`update_by` AS `update_by` from `tmmst`.`m_card_material` `mcm`;

-- ----------------------------
-- View structure for v1_m_card_process
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_card_process`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_card_process` AS select `mcp`.`mcardproc_id` AS `mcardproc_id`,`mcp`.`mcard_id` AS `mcard_id`,`mcp`.`process_seq` AS `process_seq`,`mcp`.`process_id` AS `process_id`,`mcp`.`machine_id` AS `machine_id`,`mcp`.`qty_per_hour` AS `qty_per_hour`,`mcp`.`setup_minutes` AS `setup_minutes`,`mcp`.`uom_capacity` AS `uom_capacity`,`mcp`.`create_log` AS `create_log`,`mcp`.`update_log` AS `update_log`,`mcp`.`create_by` AS `create_by`,`mcp`.`update_by` AS `update_by` from `tmmst`.`m_card_process` `mcp`;

-- ----------------------------
-- View structure for v1_m_constant
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_constant`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_constant` AS select `tmmst`.`m_constant`.`id` AS `id`,`tmmst`.`m_constant`.`constant_string` AS `constant_string`,`tmmst`.`m_constant`.`constant_number` AS `constant_number`,`tmmst`.`m_constant`.`constant_type` AS `constant_type`,`tmmst`.`m_constant`.`remark` AS `remark`,`tmmst`.`m_constant`.`update_log` AS `update_log`,`tmmst`.`m_constant`.`create_log` AS `create_log` from `tmmst`.`m_constant`;

-- ----------------------------
-- View structure for v1_m_currency
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_currency`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_currency` AS select `tmmst`.`m_currency`.`id` AS `id`,`tmmst`.`m_currency`.`currency_code` AS `currency_code`,`tmmst`.`m_currency`.`currency_name` AS `currency_name`,`tmmst`.`m_currency`.`country` AS `country`,`tmmst`.`m_currency`.`symbol` AS `symbol`,`tmmst`.`m_currency`.`counted_word` AS `counted_word`,`tmmst`.`m_currency`.`create_log` AS `create_log`,`tmmst`.`m_currency`.`update_log` AS `update_log`,`tmmst`.`m_currency`.`create_by` AS `create_by`,`tmmst`.`m_currency`.`update_by` AS `update_by` from `tmmst`.`m_currency`;

-- ----------------------------
-- View structure for v1_m_customer
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_customer`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_customer` AS select `C`.`customer_id` AS `customer_id`,`C`.`customer_code` AS `customer_code`,`C`.`customer_name` AS `customer_name`,`C`.`tax_code` AS `tax_code`,`C`.`mr_ms` AS `mr_ms`,`C`.`contact_name` AS `contact_name`,`C`.`default_address` AS `default_address`,`C`.`default_id_area` AS `default_id_area`,`C`.`email` AS `email`,`C`.`phone` AS `phone`,`C`.`website` AS `website`,`C`.`customer_type` AS `customer_type`,`C`.`payment_term` AS `payment_term`,`C`.`currency_code` AS `currency_code`,`C`.`is_active` AS `is_active`,`C`.`create_log` AS `create_log`,`C`.`update_log` AS `update_log`,`C`.`create_by` AS `create_by`,`C`.`update_by` AS `update_by`,`C`.`credit_limit` AS `credit_limit`,`C`.`current_balance` AS `current_balance` from `tmmst`.`m_customer` `C` where (`C`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_diecut
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_diecut`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_diecut` AS select `d`.`diecut_id` AS `diecut_id`,`d`.`mcard_id` AS `mcard_id`,`d`.`diecut_name` AS `diecut_name`,`d`.`doc_rev` AS `doc_rev`,`d`.`doc_date` AS `doc_date`,`d`.`is_active` AS `is_active`,`d`.`vendor_id_default` AS `vendor_id_default`,`d`.`cm_perimeter` AS `cm_perimeter`,`d`.`cm_length` AS `cm_length`,`d`.`cm_width` AS `cm_width`,`d`.`img_attachment` AS `img_attachment`,`d`.`description` AS `description` from `tmmst`.`m_diecut` `d` where (`d`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_machine
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_machine`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_machine` AS select `m`.`machine_id` AS `machine_id`,`m`.`machine_name` AS `machine_name`,`m`.`stat_machine` AS `stat_machine`,`m`.`process_id_default` AS `process_id_default`,`m`.`gantt_color` AS `gantt_color`,`m`.`is_active` AS `is_active`,`m`.`notes` AS `notes` from `tmmst`.`m_machine` `m` where (`m`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_process
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_process`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_process` AS select `p`.`process_id` AS `process_id`,`p`.`process_name` AS `process_name`,`p`.`gantt_color` AS `gantt_color`,`p`.`create_log` AS `create_log`,`p`.`update_log` AS `update_log` from `tmmst`.`m_process` `p`;

-- ----------------------------
-- View structure for v1_m_product
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_product`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_product` AS select `p`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`product_name` AS `product_name`,`p`.`customer_id` AS `customer_id`,`p`.`description` AS `description`,`p`.`uom_stock` AS `uom_stock`,`p`.`uom_sales` AS `uom_sales`,`p`.`qty_limit_min` AS `qty_limit_min`,`p`.`qty_limit_max` AS `qty_limit_max`,`p`.`cfg_parts_id` AS `cfg_parts_id`,`p`.`cfg_color` AS `cfg_color`,`p`.`cfg_ink` AS `cfg_ink`,`p`.`product_cat_id` AS `product_cat_id`,`p`.`conversion_to_stock` AS `conversion_to_stock`,`p`.`min_stock` AS `min_stock`,`p`.`max_stock` AS `max_stock`,`p`.`cur_stock` AS `cur_stock`,`p`.`is_active` AS `is_active`,`p`.`cm_length` AS `cm_length`,`p`.`cm_width` AS `cm_width`,`p`.`cm_height` AS `cm_height`,`p`.`stat_confirm` AS `stat_confirm`,`p`.`default_selling_price` AS `default_selling_price`,`p`.`minimum_selling_price` AS `minimum_selling_price`,`p`.`create_log` AS `create_log`,`p`.`update_log` AS `update_log`,`p`.`create_by` AS `create_by`,`p`.`update_by` AS `update_by` from `tmmst`.`m_product` `p` where (`p`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_product_cfg_color
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_product_cfg_color`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_product_cfg_color` AS select `tmmst`.`m_product_cfg_color`.`doc_id` AS `doc_id`,`tmmst`.`m_product_cfg_color`.`cfg_color` AS `cfg_color`,`tmmst`.`m_product_cfg_color`.`create_log` AS `create_log`,`tmmst`.`m_product_cfg_color`.`update_log` AS `update_log`,`tmmst`.`m_product_cfg_color`.`create_by` AS `create_by`,`tmmst`.`m_product_cfg_color`.`update_by` AS `update_by` from `tmmst`.`m_product_cfg_color`;

-- ----------------------------
-- View structure for v1_m_product_cfg_parts
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_product_cfg_parts`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_product_cfg_parts` AS select `cf`.`doc_id` AS `doc_id`,`cf`.`cfg_parts` AS `cfg_parts`,`cf`.`total_parts` AS `total_parts`,`cf`.`is_active` AS `is_active`,`cf`.`create_log` AS `create_log`,`cf`.`update_log` AS `update_log`,`cf`.`create_by` AS `create_by`,`cf`.`update_by` AS `update_by` from `tmmst`.`m_product_cfg_parts` `cf` where (`cf`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_product_parts
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_product_parts`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_product_parts` AS select `pp`.`parts_id` AS `parts_id`,`pp`.`product_id` AS `product_id`,`pp`.`parts_code` AS `parts_code`,`pp`.`parts_name` AS `parts_name`,`pp`.`qty_per_sku` AS `qty_per_sku`,`pp`.`description` AS `description`,`pp`.`mcard_id` AS `mcard_id`,`pp`.`uom_stock` AS `uom_stock`,`pp`.`cfg_color` AS `cfg_color`,`pp`.`cfg_ink` AS `cfg_ink`,`pp`.`waste_default` AS `waste_default`,`pp`.`spoilage_default` AS `spoilage_default`,`pp`.`min_stock` AS `min_stock`,`pp`.`max_stock` AS `max_stock`,`pp`.`cur_stock` AS `cur_stock`,`pp`.`is_active` AS `is_active`,`pp`.`cm_length` AS `cm_length`,`pp`.`cm_width` AS `cm_width`,`pp`.`cm_height` AS `cm_height`,`pp`.`create_log` AS `create_log`,`pp`.`update_log` AS `update_log`,`pp`.`create_by` AS `create_by`,`pp`.`update_by` AS `update_by` from `tmmst`.`m_product_parts` `pp`;

-- ----------------------------
-- View structure for v1_m_raw_material
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_raw_material`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_raw_material` AS select `rm`.`material_id` AS `material_id`,`rm`.`material_code` AS `material_code`,`rm`.`material_name` AS `material_name`,`rm`.`item_type` AS `item_type`,`rm`.`category1` AS `category1`,`rm`.`category2` AS `category2`,`rm`.`category3` AS `category3`,`rm`.`category4` AS `category4`,`rm`.`category5` AS `category5`,`rm`.`description` AS `description`,`rm`.`brand` AS `brand`,`rm`.`color` AS `color`,`rm`.`gram_weight` AS `gram_weight`,`rm`.`cm_length` AS `cm_length`,`rm`.`cm_width` AS `cm_width`,`rm`.`mm_thickness` AS `mm_thickness`,`rm`.`specification` AS `specification`,`rm`.`uom_stock` AS `uom_stock`,`rm`.`uom_purchase` AS `uom_purchase`,`rm`.`purchasable` AS `purchasable`,`rm`.`uom_usage` AS `uom_usage`,`rm`.`conversion_to_stock` AS `conversion_to_stock`,`rm`.`conversion_to_usage` AS `conversion_to_usage`,`rm`.`free_note` AS `free_note`,`rm`.`min_stock` AS `min_stock`,`rm`.`max_stock` AS `max_stock`,`rm`.`cur_stock` AS `cur_stock`,`rm`.`is_active` AS `is_active`,`rm`.`create_log` AS `create_log`,`rm`.`update_log` AS `update_log`,`rm`.`create_by` AS `create_by`,`rm`.`update_by` AS `update_by` from `tmmst`.`m_raw_material` `rm` where (`rm`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_uom
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_uom`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_uom` AS select `tmmst`.`m_uom`.`uom_id` AS `uom_id`,`tmmst`.`m_uom`.`uom_code` AS `uom_code`,`tmmst`.`m_uom`.`uom_name` AS `uom_name`,`tmmst`.`m_uom`.`create_by` AS `create_by`,`tmmst`.`m_uom`.`update_by` AS `update_by`,`tmmst`.`m_uom`.`create_log` AS `create_log`,`tmmst`.`m_uom`.`update_log` AS `update_log` from `tmmst`.`m_uom`;

-- ----------------------------
-- View structure for v1_m_vendor
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_vendor`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_vendor` AS select `tmmst`.`m_vendor`.`vendor_id` AS `vendor_id`,`tmmst`.`m_vendor`.`vendor_code` AS `vendor_code`,`tmmst`.`m_vendor`.`vendor_name` AS `vendor_name`,`tmmst`.`m_vendor`.`mr_ms` AS `mr_ms`,`tmmst`.`m_vendor`.`contact_name` AS `contact_name`,`tmmst`.`m_vendor`.`default_address` AS `default_address`,`tmmst`.`m_vendor`.`tax_code` AS `tax_code`,`tmmst`.`m_vendor`.`default_id_area` AS `default_id_area`,`tmmst`.`m_vendor`.`email` AS `email`,`tmmst`.`m_vendor`.`phone` AS `phone`,`tmmst`.`m_vendor`.`website` AS `website`,`tmmst`.`m_vendor`.`vendor_type` AS `vendor_type`,`tmmst`.`m_vendor`.`payment_term` AS `payment_term`,`tmmst`.`m_vendor`.`currency_code` AS `currency_code`,`tmmst`.`m_vendor`.`is_active` AS `is_active`,`tmmst`.`m_vendor`.`rating` AS `rating`,`tmmst`.`m_vendor`.`create_log` AS `create_log`,`tmmst`.`m_vendor`.`update_log` AS `update_log`,`tmmst`.`m_vendor`.`create_by` AS `create_by`,`tmmst`.`m_vendor`.`update_by` AS `update_by` from `tmmst`.`m_vendor` where (`tmmst`.`m_vendor`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_warehouse
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_warehouse`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_warehouse` AS select `tmmst`.`m_warehouse`.`warehouse_id` AS `warehouse_id`,`tmmst`.`m_warehouse`.`warehouse_code` AS `warehouse_code`,`tmmst`.`m_warehouse`.`warehouse_name` AS `warehouse_name`,`tmmst`.`m_warehouse`.`warehouse_type` AS `warehouse_type`,`tmmst`.`m_warehouse`.`is_active` AS `is_active`,`tmmst`.`m_warehouse`.`description` AS `description`,`tmmst`.`m_warehouse`.`create_log` AS `create_log`,`tmmst`.`m_warehouse`.`update_log` AS `update_log`,`tmmst`.`m_warehouse`.`create_by` AS `create_by`,`tmmst`.`m_warehouse`.`update_by` AS `update_by` from `tmmst`.`m_warehouse` where (`tmmst`.`m_warehouse`.`is_active` = 1);

-- ----------------------------
-- View structure for v1_m_wh_loc
-- ----------------------------
DROP VIEW IF EXISTS `v1_m_wh_loc`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_m_wh_loc` AS select `l`.`location_id` AS `location_id`,`l`.`warehouse_id` AS `warehouse_id`,`l`.`location_code` AS `location_code`,`l`.`location_name` AS `location_name`,`l`.`row_name` AS `row_name`,`l`.`column_name` AS `column_name`,`l`.`stack_name` AS `stack_name`,`l`.`location_type` AS `location_type`,`l`.`max_capacity_qty` AS `max_capacity_qty`,`l`.`description` AS `description`,`l`.`is_active` AS `is_active`,`l`.`create_log` AS `create_log`,`l`.`update_log` AS `update_log`,`l`.`create_by` AS `create_by`,`l`.`update_by` AS `update_by`,concat(`w`.`warehouse_name`,' - ',`l`.`location_name`) AS `strwh` from (`tmmst`.`m_wh_loc` `l` join `tmmst`.`m_warehouse` `w` on((`w`.`warehouse_id` = `l`.`warehouse_id`)));

-- ----------------------------
-- View structure for v1_sf_ef_rate
-- ----------------------------
DROP VIEW IF EXISTS `v1_sf_ef_rate`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_sf_ef_rate` AS select `m_sheet_rate`.`doc_id` AS `id_sf`,`m_sheet_rate`.`material_substance` AS `description`,`m_sheet_rate`.`index_substance` AS `rate`,concat(`m_sheet_rate`.`material_substance`,' — Rp ',format(`m_sheet_rate`.`index_substance`,0)) AS `display_label`,`m_sheet_rate`.`is_active` AS `is_active`,`m_sheet_rate`.`doc_id` AS `sort_order` from `m_sheet_rate` where ((`m_sheet_rate`.`flute_type` = 'E/F') and (`m_sheet_rate`.`mat_subtype` = 'SF') and (`m_sheet_rate`.`is_active` = 1)) order by `m_sheet_rate`.`material_substance`;

-- ----------------------------
-- View structure for v1_sheet_digital_tier
-- ----------------------------
DROP VIEW IF EXISTS `v1_sheet_digital_tier`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_sheet_digital_tier` AS select `m_sheet_discount_tier`.`id` AS `id`,`m_sheet_discount_tier`.`supplier_code` AS `supplier_code`,`m_sheet_discount_tier`.`sheet_rate_id` AS `sheet_rate_id`,`m_sheet_discount_tier`.`rm_from` AS `rm_from`,`m_sheet_discount_tier`.`rm_to` AS `rm_to`,`m_sheet_discount_tier`.`discount_pct` AS `discount_pct`,`m_sheet_discount_tier`.`effective_date` AS `effective_date`,`m_sheet_discount_tier`.`notes` AS `notes` from `m_sheet_discount_tier` where ((`m_sheet_discount_tier`.`part_type` = 'SHEET_DIGITAL') and (`m_sheet_discount_tier`.`is_active` = 1));

-- ----------------------------
-- View structure for v1_sheet_rate
-- ----------------------------
DROP VIEW IF EXISTS `v1_sheet_rate`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v1_sheet_rate` AS select `m_sheet_rate`.`doc_id` AS `doc_id`,`m_sheet_rate`.`flute_type` AS `flute_type`,`m_sheet_rate`.`material_substance` AS `material_substance`,`m_sheet_rate`.`index_substance` AS `index_substance`,`m_sheet_rate`.`mat_subtype` AS `mat_subtype`,`m_sheet_rate`.`is_active` AS `is_active`,concat(`m_sheet_rate`.`flute_type`,' - ',`m_sheet_rate`.`material_substance`,' - Rp ',format(`m_sheet_rate`.`index_substance`,0),'/m²') AS `label` from `m_sheet_rate` where (`m_sheet_rate`.`is_active` = 1) order by `m_sheet_rate`.`flute_type`,`m_sheet_rate`.`material_substance`;

-- ----------------------------
-- View structure for v_paper_price_by_date
-- ----------------------------
DROP VIEW IF EXISTS `v_paper_price_by_date`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_paper_price_by_date` AS select `g`.`id_cons_grammage` AS `id_cons_grammage`,`g`.`paper_type` AS `paper_type`,`g`.`grammage` AS `grammage`,`g`.`ivory_ap_price_per_kg` AS `ivory_ap_price_per_kg`,`g`.`duplex_price_list` AS `duplex_price_list`,`g`.`duplex_dc_percentage` AS `duplex_dc_percentage`,`g`.`effective_date` AS `effective_date`,`g`.`is_active` AS `is_active` from `m_paper_grammage` `g` where (`g`.`is_active` = 1);

SET FOREIGN_KEY_CHECKS = 1;
