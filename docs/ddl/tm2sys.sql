/*
 Navicat Premium Dump SQL

 Source Server         : 127.0.0.1
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46)
 Source Host           : localhost:3306
 Source Schema         : tm2sys

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46)
 File Encoding         : 65001

 Date: 04/09/2026 09:58:56
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for cache
-- ----------------------------
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache`  (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`) USING BTREE,
  INDEX `cache_expiration_index`(`expiration` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of cache
-- ----------------------------

-- ----------------------------
-- Table structure for cache_locks
-- ----------------------------
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks`  (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`) USING BTREE,
  INDEX `cache_locks_expiration_index`(`expiration` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of cache_locks
-- ----------------------------

-- ----------------------------
-- Table structure for failed_jobs
-- ----------------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `failed_jobs_uuid_unique`(`uuid` ASC) USING BTREE,
  INDEX `failed_jobs_connection_queue_failed_at_index`(`connection` ASC, `queue` ASC, `failed_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of failed_jobs
-- ----------------------------

-- ----------------------------
-- Table structure for job_batches
-- ----------------------------
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches`  (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `cancelled_at` int NULL DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of job_batches
-- ----------------------------

-- ----------------------------
-- Table structure for jobs
-- ----------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED NULL DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `jobs_queue_index`(`queue` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of jobs
-- ----------------------------

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 244 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of migrations
-- ----------------------------
INSERT INTO `migrations` VALUES (1, '0001_01_01_000000_create_users_table', 1);
INSERT INTO `migrations` VALUES (2, '0001_01_01_000001_create_cache_table', 1);
INSERT INTO `migrations` VALUES (3, '0001_01_01_000002_create_jobs_table', 1);
INSERT INTO `migrations` VALUES (4, '2026_08_09_000002_add_registration_columns_to_sec_user_table', 2);
INSERT INTO `migrations` VALUES (5, '2026_08_09_000003_migrate_sys_app_menu_icons_to_fontawesome', 3);
INSERT INTO `migrations` VALUES (6, '2026_08_09_000004_update_login_label_translations', 4);
INSERT INTO `migrations` VALUES (7, '2026_08_09_000005_create_sys_login_hero_table', 5);
INSERT INTO `migrations` VALUES (8, '2026_08_09_000006_add_revise_scope_columns_to_iqp_inquiry_table', 6);
INSERT INTO `migrations` VALUES (10, '2026_08_15_000001_consolidate_setup_security_and_add_costing_module', 7);
INSERT INTO `migrations` VALUES (11, '2026_08_18_000001_add_default_password_to_sec_user_table', 8);
INSERT INTO `migrations` VALUES (12, '2026_08_18_000002_create_prod_machine_and_shift_tables', 9);
INSERT INTO `migrations` VALUES (13, '2026_08_18_000003_add_master_machine_and_shift_menu_items', 10);
INSERT INTO `migrations` VALUES (14, '2026_08_18_000004_add_legacy_vendor_fields_to_cust_customer_table', 11);
INSERT INTO `migrations` VALUES (15, '2026_08_18_000005_create_sup_supplier_table', 11);
INSERT INTO `migrations` VALUES (16, '2026_08_18_000006_create_cust_and_sup_category_tables', 11);
INSERT INTO `migrations` VALUES (17, '2026_08_18_000007_create_cust_customer_bank_and_sup_supplier_bank_tables', 11);
INSERT INTO `migrations` VALUES (18, '2026_08_18_000008_add_migration_engine_menu_item', 11);
INSERT INTO `migrations` VALUES (19, '2026_08_18_000010_migrate_m_product_type_size_and_ref_param_option_data', 12);
INSERT INTO `migrations` VALUES (20, '2026_08_18_000011_create_fin_payment_term_table', 13);
INSERT INTO `migrations` VALUES (21, '2026_08_18_000012_add_category_fk_to_cust_and_sup', 14);
INSERT INTO `migrations` VALUES (22, '2026_08_18_000013_create_customer_and_supplier_branch_contact_tables', 15);
INSERT INTO `migrations` VALUES (23, '2026_08_19_000001_add_approval_status_to_cust_customer_table', 16);
INSERT INTO `migrations` VALUES (24, '2026_08_19_000002_create_fin_journal_table', 17);
INSERT INTO `migrations` VALUES (25, '2026_08_19_000003_create_fin_account_table', 18);
INSERT INTO `migrations` VALUES (26, '2026_08_19_000004_create_fin_cost_center_table', 18);
INSERT INTO `migrations` VALUES (27, '2026_08_19_000005_create_fin_partner_account_table', 18);
INSERT INTO `migrations` VALUES (28, '2026_08_19_000006_create_fin_voucher_table', 18);
INSERT INTO `migrations` VALUES (29, '2026_08_19_000007_create_fin_voucher_line_table', 18);
INSERT INTO `migrations` VALUES (30, '2026_08_19_000008_create_fin_invoice_table', 18);
INSERT INTO `migrations` VALUES (31, '2026_08_19_000009_create_fin_views', 18);
INSERT INTO `migrations` VALUES (32, '2026_08_19_000010_add_finance_coa_menu_item', 18);
INSERT INTO `migrations` VALUES (33, '2026_08_19_000012_create_org_structure_tables', 19);
INSERT INTO `migrations` VALUES (34, '2026_08_19_000013_rebuild_prod_machine_with_division_fk', 19);
INSERT INTO `migrations` VALUES (35, '2026_08_19_000014_replace_prod_shift_with_emp_shift', 19);
INSERT INTO `migrations` VALUES (36, '2026_08_19_000015_create_emp_employee_and_related_tables', 19);
INSERT INTO `migrations` VALUES (37, '2026_08_19_000016_create_emp_roster_and_selfservice_tables', 19);
INSERT INTO `migrations` VALUES (38, '2026_08_19_000017_add_hrm_module_and_employee_menu', 19);
INSERT INTO `migrations` VALUES (39, '2026_08_19_000018_add_hrm_schedule_menu', 20);
INSERT INTO `migrations` VALUES (40, '2026_08_19_000019_add_v2_fields_to_cust_customer_table', 21);
INSERT INTO `migrations` VALUES (41, '2026_08_19_000020_add_dp_support_to_finance', 22);
INSERT INTO `migrations` VALUES (42, '2026_08_19_000021_add_finance_dp_invoice_menu', 23);
INSERT INTO `migrations` VALUES (43, '2026_08_20_000001_create_m_plano_size_table', 24);
INSERT INTO `migrations` VALUES (44, '2026_08_20_000002_create_iqp_layout_table', 24);
INSERT INTO `migrations` VALUES (45, '2026_08_20_000003_add_part_id_to_iqp_costing_part_table', 24);
INSERT INTO `migrations` VALUES (46, '2026_08_20_000004_add_bank_constants_to_sys_constant_table', 25);
INSERT INTO `migrations` VALUES (47, '2026_08_20_000005_create_quo_quotation_table', 25);
INSERT INTO `migrations` VALUES (48, '2026_08_20_000006_add_module_status_system', 26);
INSERT INTO `migrations` VALUES (49, '2026_08_20_000007_redesign_quotation_multi_inquiry', 27);
INSERT INTO `migrations` VALUES (50, '2026_08_20_000008_create_co_order_tables', 28);
INSERT INTO `migrations` VALUES (51, '2026_08_20_000009_mark_co_order_ready_for_qc', 29);
INSERT INTO `migrations` VALUES (52, '2026_08_20_000010_create_doc_rmprefix_tables', 30);
INSERT INTO `migrations` VALUES (53, '2026_08_20_000011_create_m_raw_material_table', 31);
INSERT INTO `migrations` VALUES (54, '2026_08_20_000012_add_procurement_delta_columns', 32);
INSERT INTO `migrations` VALUES (55, '2026_08_20_000013_seed_procurement_sys_constants', 32);
INSERT INTO `migrations` VALUES (56, '2026_08_20_000014_create_flow_engine_tables', 32);
INSERT INTO `migrations` VALUES (57, '2026_08_20_000015_seed_procurement_flow_config', 32);
INSERT INTO `migrations` VALUES (58, '2026_08_20_000016_create_pr_requisition_tables', 32);
INSERT INTO `migrations` VALUES (59, '2026_08_20_000017_grant_direksi_procurement_access', 33);
INSERT INTO `migrations` VALUES (60, '2026_08_21_000001_create_po_order_tables', 34);
INSERT INTO `migrations` VALUES (61, '2026_08_21_000002_seed_po_flow_config', 34);
INSERT INTO `migrations` VALUES (62, '2026_08_21_000003_add_item_master_delta_to_m_raw_material', 35);
INSERT INTO `migrations` VALUES (63, '2026_08_21_000004_create_fin_profit_center_table', 35);
INSERT INTO `migrations` VALUES (64, '2026_08_21_000005_create_pv_receipt_tables', 36);
INSERT INTO `migrations` VALUES (65, '2026_08_21_000006_create_m_warehouse_tables', 37);
INSERT INTO `migrations` VALUES (66, '2026_08_21_000007_add_legacy_hrm_migration_columns', 38);
INSERT INTO `migrations` VALUES (67, '2026_08_21_000008_align_warehouse_schema_for_inventory', 39);
INSERT INTO `migrations` VALUES (68, '2026_08_21_000009_cleanup_legacy_inv_data', 40);
INSERT INTO `migrations` VALUES (69, '2026_08_21_000010_create_inv_tier3_delta_and_new_tables', 41);
INSERT INTO `migrations` VALUES (70, '2026_08_21_000011_seed_inv_flow_and_menu', 42);
INSERT INTO `migrations` VALUES (71, '2026_08_21_000012_create_inv_views', 43);
INSERT INTO `migrations` VALUES (72, '2026_08_21_000013_seed_prod_master_dummy_data', 44);
INSERT INTO `migrations` VALUES (73, '2026_08_22_000001_confirm_test_inquiries_for_worklist_demo', 45);
INSERT INTO `migrations` VALUES (74, '2026_08_22_000002_seed_quotation_and_co_dummy_data', 46);
INSERT INTO `migrations` VALUES (75, '2026_08_22_000003_split_sales_transaksi_menu_group', 47);
INSERT INTO `migrations` VALUES (76, '2026_08_22_000004_rename_inventory_report_menu_labels', 48);
INSERT INTO `migrations` VALUES (77, '2026_08_22_000005_seed_fg_sfg_dummy_stock_data', 49);
INSERT INTO `migrations` VALUES (78, '2026_08_22_000006_retire_redundant_inventory_report_menus', 50);
INSERT INTO `migrations` VALUES (79, '2026_08_22_000007_split_inventory_menu_by_stock_class', 51);
INSERT INTO `migrations` VALUES (80, '2026_08_22_000008_mark_stock_in_menus_ready', 52);
INSERT INTO `migrations` VALUES (81, '2026_08_22_000009_seed_fg_sfg_stock_in_demo', 53);
INSERT INTO `migrations` VALUES (82, '2026_08_22_000010_seed_employee_dummy_photos', 54);
INSERT INTO `migrations` VALUES (83, '2026_08_22_000011_seed_photo_anik_andy', 55);
INSERT INTO `migrations` VALUES (84, '2026_08_22_000012_add_pr_po_approval_menu_items', 56);
INSERT INTO `migrations` VALUES (85, '2026_08_22_000013_wire_outstanding_reports_menu', 57);
INSERT INTO `migrations` VALUES (86, '2026_08_22_000014_wire_stock_out_return_adjustment_menu', 58);
INSERT INTO `migrations` VALUES (87, '2026_08_22_000015_create_pq_prt_tables', 59);
INSERT INTO `migrations` VALUES (88, '2026_08_22_000016_seed_prt_flow_config', 60);
INSERT INTO `migrations` VALUES (89, '2026_08_22_000017_wire_pq_prt_menu', 61);
INSERT INTO `migrations` VALUES (90, '2026_08_22_000018_make_po_line_department_nullable', 62);
INSERT INTO `migrations` VALUES (91, '2026_08_22_000019_make_po_line_coa_account_nullable', 63);
INSERT INTO `migrations` VALUES (92, '2026_08_22_000020_seed_gr_ir_and_usage_journal', 64);
INSERT INTO `migrations` VALUES (93, '2026_08_22_000021_add_stock_gr_ir_defaults_to_doc_rmprefix1', 65);
INSERT INTO `migrations` VALUES (94, '2026_08_22_000022_add_accounting_columns_to_product_tables', 66);
INSERT INTO `migrations` VALUES (95, '2026_08_22_000023_add_cash_bank_voucher_columns', 67);
INSERT INTO `migrations` VALUES (96, '2026_08_22_000024_wire_cash_bank_menu', 68);
INSERT INTO `migrations` VALUES (97, '2026_08_22_000025_wire_stock_out_sfg_menu', 69);
INSERT INTO `migrations` VALUES (98, '2026_08_22_000026_wire_stock_return_sfg_menu', 70);
INSERT INTO `migrations` VALUES (99, '2026_08_22_000027_add_plan_delivery_columns', 71);
INSERT INTO `migrations` VALUES (100, '2026_08_22_000028_wire_plan_delivery_menu', 72);
INSERT INTO `migrations` VALUES (103, '2026_08_26_000001_add_doc_slug_and_doc_reference', 75);
INSERT INTO `migrations` VALUES (104, '2026_08_26_000002_replace_doc_rmprefix_with_mat_category', 76);
INSERT INTO `migrations` VALUES (105, '2026_08_26_000003_add_master_mat_category_menu_item', 77);
INSERT INTO `migrations` VALUES (106, '2026_08_26_000004_add_matcategory_delta_doc_reference', 78);
INSERT INTO `migrations` VALUES (107, '2026_08_26_000005_fix_mat_category_menu_status', 79);
INSERT INTO `migrations` VALUES (108, '2026_08_26_000007_rename_is_active_to_activate_tm2mst', 80);
INSERT INTO `migrations` VALUES (109, '2026_08_26_000008_add_fieldrename_state_doc_reference', 81);
INSERT INTO `migrations` VALUES (110, '2026_08_26_000006_add_is_confirmed_to_raw_material', 82);
INSERT INTO `migrations` VALUES (111, '2026_08_27_000001_rename_is_active_to_activate_tm2acc', 83);
INSERT INTO `migrations` VALUES (112, '2026_08_27_000002_fix_tm2acc_views_after_rename', 84);
INSERT INTO `migrations` VALUES (113, '2026_08_27_000003_rename_is_active_to_activate_tm2sdi', 85);
INSERT INTO `migrations` VALUES (114, '2026_08_27_000004_rename_is_cancelled_to_stat_cancel_tm2pur', 86);
INSERT INTO `migrations` VALUES (115, '2026_08_27_000005_rename_is_active_to_activate_tm2sys', 87);
INSERT INTO `migrations` VALUES (116, '2026_08_27_000006_add_security_recovery_fields_to_sec_user', 88);
INSERT INTO `migrations` VALUES (117, '2026_08_27_000007_create_sec_recovery_question_table', 89);
INSERT INTO `migrations` VALUES (118, '2026_08_27_000008_add_no_kk_to_emp_employee', 90);
INSERT INTO `migrations` VALUES (119, '2026_08_27_000009_drop_orphaned_m_product_type_legacy_tables', 91);
INSERT INTO `migrations` VALUES (120, '2026_08_27_000011_rename_warehouse_and_plano_size_tables', 92);
INSERT INTO `migrations` VALUES (121, '2026_08_27_000010_merge_raw_material_into_mat_raw', 93);
INSERT INTO `migrations` VALUES (122, '2026_08_27_000012_fix_tm2inv_views_after_m_table_rename', 94);
INSERT INTO `migrations` VALUES (123, '2026_08_27_000013_add_topmanagementreport_doc_reference', 95);
INSERT INTO `migrations` VALUES (124, '2026_08_27_000014_create_sys_grid_preference_table', 96);
INSERT INTO `migrations` VALUES (125, '2026_08_27_000015_add_hrm_correction_requests_menu', 97);
INSERT INTO `migrations` VALUES (126, '2026_08_28_000001_create_prd_tier3_tables', 98);
INSERT INTO `migrations` VALUES (127, '2026_08_28_000002_seed_prd_scheduling_board_dummy', 99);
INSERT INTO `migrations` VALUES (128, '2026_08_28_000003_register_gantt_board_standard_doc', 100);
INSERT INTO `migrations` VALUES (129, '2026_08_28_000004_register_print_nota_standard_doc', 101);
INSERT INTO `migrations` VALUES (130, '2026_08_28_000003_seed_prod_machine_max_speed', 102);
INSERT INTO `migrations` VALUES (131, '2026_08_28_000004_seed_emp_shift_roster_dummy', 103);
INSERT INTO `migrations` VALUES (132, '2026_08_28_000005_seed_pending_queue_task_dummy', 104);
INSERT INTO `migrations` VALUES (133, '2026_08_28_000006_seed_prd_approval_roles', 105);
INSERT INTO `migrations` VALUES (134, '2026_08_28_000007_add_prd_worklist_rule_forecast_menu', 106);
INSERT INTO `migrations` VALUES (135, '2026_08_28_000008_add_subcontract_po_type', 107);
INSERT INTO `migrations` VALUES (136, '2026_08_28_000009_promote_prd_worklist_menu_ready', 108);
INSERT INTO `migrations` VALUES (137, '2026_08_28_000010_create_prd_wo_operation_output', 109);
INSERT INTO `migrations` VALUES (138, '2026_08_28_000011_promote_prd_output_menu_ready', 110);
INSERT INTO `migrations` VALUES (139, '2026_08_28_000012_add_scrap_type_to_production_output', 111);
INSERT INTO `migrations` VALUES (140, '2026_08_28_000013_seed_prd_scrap_type_defaults', 112);
INSERT INTO `migrations` VALUES (141, '2026_08_28_000014_add_prd_scrap_type_menu', 113);
INSERT INTO `migrations` VALUES (142, '2026_08_28_000015_add_wo_generation_delta_doc_reference', 114);
INSERT INTO `migrations` VALUES (143, '2026_08_28_000016_add_part_source_columns_to_prd_wo', 115);
INSERT INTO `migrations` VALUES (144, '2026_08_28_000017_create_prod_part_process_rate_tables', 116);
INSERT INTO `migrations` VALUES (145, '2026_08_28_000018_seed_wo_generation_dummy_chain', 117);
INSERT INTO `migrations` VALUES (146, '2026_08_28_000019_add_prd_wo_generate_menu', 118);
INSERT INTO `migrations` VALUES (147, '2026_08_28_000020_promote_wo_generation_screens_ready', 119);
INSERT INTO `migrations` VALUES (148, '2026_08_28_000010_add_slow_moving_report_menu', 120);
INSERT INTO `migrations` VALUES (149, '2026_08_28_000011_create_dispatch_do_tables', 120);
INSERT INTO `migrations` VALUES (150, '2026_08_28_000012_add_dispatch_module_and_menu', 120);
INSERT INTO `migrations` VALUES (151, '2026_08_28_000021_seed_second_plan_delivery_for_wo_generate_demo', 121);
INSERT INTO `migrations` VALUES (152, '2026_08_28_000022_add_priority_to_prd_wo_and_process_rate_menu', 122);
INSERT INTO `migrations` VALUES (153, '2026_08_28_000023_remove_wo_generate_standalone_menu', 123);
INSERT INTO `migrations` VALUES (154, '2026_08_28_000024_rewrite_prod_plan_menu_labels', 124);
INSERT INTO `migrations` VALUES (155, '2026_08_28_000025_reorganize_production_menu', 125);
INSERT INTO `migrations` VALUES (156, '2026_08_28_000026_hide_master_card_layout_rename_crew', 126);
INSERT INTO `migrations` VALUES (157, '2026_08_28_000027_migrate_process_master_from_tm2old', 127);
INSERT INTO `migrations` VALUES (158, '2026_08_28_000028_promote_prod_mst_process_menu_ready', 128);
INSERT INTO `migrations` VALUES (159, '2026_08_28_000029_create_prod_crew_table', 129);
INSERT INTO `migrations` VALUES (160, '2026_08_28_000030_migrate_machine_crew_from_tm2old', 129);
INSERT INTO `migrations` VALUES (161, '2026_08_28_000031_promote_prod_mst_crew_menu_ready', 130);
INSERT INTO `migrations` VALUES (162, '2026_08_28_000013_hide_prod_mrp_menu', 131);
INSERT INTO `migrations` VALUES (163, '2026_08_28_000014_create_prd_mrp_tables', 131);
INSERT INTO `migrations` VALUES (164, '2026_08_28_000015_add_prod_kanban_menu', 131);
INSERT INTO `migrations` VALUES (165, '2026_08_28_000032_cleanup_mps_kanban_menu', 132);
INSERT INTO `migrations` VALUES (166, '2026_08_28_000033_add_mps_spreadsheet_menu', 133);
INSERT INTO `migrations` VALUES (167, '2026_08_28_000034_move_dispatch_menu_into_sales_delivery', 134);
INSERT INTO `migrations` VALUES (168, '2026_08_28_000026_add_hrm_leave_requests_menu', 135);
INSERT INTO `migrations` VALUES (169, '2026_08_29_000001_seed_co_external_cost_dummy', 136);
INSERT INTO `migrations` VALUES (170, '2026_08_29_000002_split_hrm_recruitment_into_group', 137);
INSERT INTO `migrations` VALUES (171, '2026_08_29_000003_create_recruitment_tm2hrr_schema', 138);
INSERT INTO `migrations` VALUES (172, '2026_08_29_000004_reorganize_hrm_menu_groups', 139);
INSERT INTO `migrations` VALUES (173, '2026_08_29_000006_finalize_recruitment_menu', 140);
INSERT INTO `migrations` VALUES (174, '2026_08_29_000007_add_reports_to_position_id', 141);
INSERT INTO `migrations` VALUES (175, '2026_08_29_000008_add_org_chart_menu', 142);
INSERT INTO `migrations` VALUES (176, '2026_08_29_000009_seed_real_org_structure', 143);
INSERT INTO `migrations` VALUES (177, '2026_08_29_000010_expand_candidate_form_fields', 144);
INSERT INTO `migrations` VALUES (178, '2026_08_29_000011_link_confident_org_chart_occupants', 145);
INSERT INTO `migrations` VALUES (179, '2026_08_29_000012_create_support_ticket_tables', 146);
INSERT INTO `migrations` VALUES (180, '2026_08_29_000013_add_support_tickets_menu', 147);
INSERT INTO `migrations` VALUES (181, '2026_08_30_000001_register_ui_standard_docs', 148);
INSERT INTO `migrations` VALUES (182, '2026_08_30_000002_add_master_currency_menu_item', 149);
INSERT INTO `migrations` VALUES (183, '2026_08_30_000003_register_spreadsheet_grid_standard_doc', 150);
INSERT INTO `migrations` VALUES (184, '2026_08_30_000004_register_recruitment_delta_plan_ab_doc', 151);
INSERT INTO `migrations` VALUES (185, '2026_08_30_000005_register_recruitmentportal_web_tier1_doc', 152);
INSERT INTO `migrations` VALUES (186, '2026_08_30_000006_seed_real_vacancies_from_glints', 153);
INSERT INTO `migrations` VALUES (187, '2026_08_31_000001_fix_sys_support_replies_fk', 154);
INSERT INTO `migrations` VALUES (188, '2026_08_31_000002_add_short_label_tooltip_to_sys_app_menu', 155);
INSERT INTO `migrations` VALUES (189, '2026_08_31_000003_add_mode_to_sys_theme_and_seed_variants', 155);
INSERT INTO `migrations` VALUES (190, '2026_08_31_000004_add_wm_label_scan_masterdata_menu', 156);
INSERT INTO `migrations` VALUES (191, '2026_08_31_000005_translate_wm_menu_to_english', 157);
INSERT INTO `migrations` VALUES (192, '2026_08_31_000006_merge_reports_into_stock_class_groups', 158);
INSERT INTO `migrations` VALUES (193, '2026_08_31_000004_matrix_sys_theme_family_hue', 159);
INSERT INTO `migrations` VALUES (194, '2026_08_31_000005_register_themestandard_doc', 160);
INSERT INTO `migrations` VALUES (195, '2026_09_02_000001_create_sys_constant_category_table', 161);
INSERT INTO `migrations` VALUES (196, '2026_09_02_000002_move_sys_constant_to_tm2sys', 162);
INSERT INTO `migrations` VALUES (197, '2026_09_02_000003_seed_branding_constants', 163);
INSERT INTO `migrations` VALUES (198, '2026_09_02_000004_add_flag_emoji_to_geo_country', 163);
INSERT INTO `migrations` VALUES (199, '2026_09_02_000005_create_fin_exchange_rate_table', 163);
INSERT INTO `migrations` VALUES (200, '2026_09_02_000006_add_locale_columns_to_sys_languages', 163);
INSERT INTO `migrations` VALUES (201, '2026_09_02_000007_add_admin_theme_columns_to_sec_user', 163);
INSERT INTO `migrations` VALUES (202, '2026_09_02_000008_add_configuration_menu_item', 164);
INSERT INTO `migrations` VALUES (203, '2026_09_02_000009_register_configuration_and_report_standard_docs', 165);
INSERT INTO `migrations` VALUES (204, '2026_09_02_000010_register_backlog_doc', 166);
INSERT INTO `migrations` VALUES (205, '2026_09_02_000011_fix_org_company_legal_name', 167);
INSERT INTO `migrations` VALUES (206, '2026_09_02_000012_add_exchange_rate_menu_item', 168);
INSERT INTO `migrations` VALUES (207, '2026_09_02_000013_populate_geo_country_flag_emoji', 169);
INSERT INTO `migrations` VALUES (208, '2026_09_02_000014_register_log_job_done_doc', 170);
INSERT INTO `migrations` VALUES (209, '2026_09_02_000015_create_sys_holiday_table', 171);
INSERT INTO `migrations` VALUES (210, '2026_09_02_000016_add_holiday_menu_item', 172);
INSERT INTO `migrations` VALUES (211, '2026_09_02_000017_rename_sales_mon_label_to_laporan', 173);
INSERT INTO `migrations` VALUES (212, '2026_09_02_000018_build_recruitment_l8_sync_log', 174);
INSERT INTO `migrations` VALUES (213, '2026_09_02_000019_add_fixed_asset_menu_item', 175);
INSERT INTO `migrations` VALUES (214, '2026_09_02_000020_seed_prodev_engine_constants', 176);
INSERT INTO `migrations` VALUES (215, '2026_09_02_000021_add_prodev_technical_columns_to_prod_part', 177);
INSERT INTO `migrations` VALUES (216, '2026_09_02_000022_create_prod_part_process_table', 178);
INSERT INTO `migrations` VALUES (217, '2026_09_02_000023_create_prod_layout_group_tables', 179);
INSERT INTO `migrations` VALUES (218, '2026_09_02_000024_create_prod_part_attachment_table', 179);
INSERT INTO `migrations` VALUES (219, '2026_09_02_000025_create_prod_part_finishing_tables', 180);
INSERT INTO `migrations` VALUES (220, '2026_09_02_000026_create_mat_flap_rule_table', 181);
INSERT INTO `migrations` VALUES (221, '2026_09_02_000027_activate_prod_card_layout_menu', 182);
INSERT INTO `migrations` VALUES (222, '2026_09_02_000028_add_sf_ef_rate_to_iqp_costing_part', 183);
INSERT INTO `migrations` VALUES (223, '2026_09_02_000029_add_lam_flat_sf_ef_output_columns', 184);
INSERT INTO `migrations` VALUES (224, '2026_09_02_000030_mark_remaining_part_types_engine_ready', 185);
INSERT INTO `migrations` VALUES (225, '2026_09_02_000031_add_prodev_data_source_to_iqp_costing_part', 186);
INSERT INTO `migrations` VALUES (226, '2026_09_02_000032_add_win_rate_menu_item', 187);
INSERT INTO `migrations` VALUES (227, '2026_09_02_000033_add_handover_tracking_to_pv_receipt', 188);
INSERT INTO `migrations` VALUES (228, '2026_09_02_000034_build_outstanding_pv_report', 189);
INSERT INTO `migrations` VALUES (229, '2026_09_03_000001_repoint_sales_menu_to_prodev', 190);
INSERT INTO `migrations` VALUES (230, '2026_09_03_000002_seed_uat_role_level_accounts', 191);
INSERT INTO `migrations` VALUES (231, '2026_09_03_000003_rename_config_menu_to_settings', 192);
INSERT INTO `migrations` VALUES (232, '2026_09_03_000004_make_avatar_dropdown_data_driven', 193);
INSERT INTO `migrations` VALUES (233, '2026_09_03_000005_add_kupas_packing_fields_to_iqp_costing_part', 194);
INSERT INTO `migrations` VALUES (234, '2026_09_03_000006_seed_payroll_encryption_key_constant', 195);
INSERT INTO `migrations` VALUES (235, '2026_09_03_000007_seed_default_period_constants', 196);
INSERT INTO `migrations` VALUES (236, '2026_09_03_000008_add_prodev_tools_columns', 197);
INSERT INTO `migrations` VALUES (237, '2026_09_03_000009_add_prodev_part_id_to_iqp_costing_part', 198);
INSERT INTO `migrations` VALUES (238, '2026_09_03_000010_add_prod_part_id_to_iqp_part', 199);
INSERT INTO `migrations` VALUES (239, '2026_09_03_000011_rename_cpdel_and_regroup_delivery_menu', 200);
INSERT INTO `migrations` VALUES (241, '2026_09_03_000012_seed_prodev_inqprod_dummy_workload', 201);
INSERT INTO `migrations` VALUES (242, '2026_09_03_000013_enrich_prodev_inqprod_dummy_engine_data', 202);
INSERT INTO `migrations` VALUES (243, '2026_09_03_000014_seed_prodev_press_geometry_constants', 203);

-- ----------------------------
-- Table structure for password_reset_tokens
-- ----------------------------
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens`  (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of password_reset_tokens
-- ----------------------------

-- ----------------------------
-- Table structure for sec_group
-- ----------------------------
DROP TABLE IF EXISTS `sec_group`;
CREATE TABLE `sec_group`  (
  `id_group` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode, mis. SALES_STAFF, DIREKSI, PRODUKSI',
  `group_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Deskripsi (ex sec_groups.description ScriptCase)',
  `mgmt_level` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=STAFF 2=SUPERVISOR 3=MANAGER 4=GM 5=DIREKSI. Utk report sensitif',
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_group`) USING BTREE,
  UNIQUE INDEX `uq_group_code`(`group_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Grup/role pengguna + level manajemen (1=STAFF .. 5=DIREKSI). Direferensikan sec_user_group & sec_group_permission.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_group
-- ----------------------------
INSERT INTO `sec_group` VALUES (1, 'SALES_STAFF', 'Staf Sales', 1, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);
INSERT INTO `sec_group` VALUES (2, 'SALES_SPV', 'Supervisor Sales', 2, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);
INSERT INTO `sec_group` VALUES (3, 'DIREKSI', 'Direksi', 5, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);
INSERT INTO `sec_group` VALUES (4, 'PRODUKSI', 'Staf Produksi', 1, 1, '2026-08-08 16:40:12', '2026-08-08 16:40:12', NULL, NULL);
INSERT INTO `sec_group` VALUES (5, 'QA', 'Quality Assurance', 1, 1, '2026-08-28 08:41:07', '2026-08-28 08:41:07', 'system', NULL);
INSERT INTO `sec_group` VALUES (6, 'PROD_MGR', 'Manager Produksi', 3, 1, '2026-08-28 08:41:07', '2026-08-28 08:41:07', 'system', NULL);
INSERT INTO `sec_group` VALUES (7, 'OPS_MGR', 'Manager Operasional', 4, 1, '2026-08-28 08:41:07', '2026-08-28 08:41:07', 'system', NULL);
INSERT INTO `sec_group` VALUES (8, 'MGR_SALES', 'Manager Sales', 3, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_group` VALUES (9, 'MGR_PUR', 'Manager Purchasing', 3, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_group` VALUES (10, 'MGR_INV', 'Manager Inventory', 3, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_group` VALUES (11, 'MGR_ACC', 'Manager Finance & Accounting', 3, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);

-- ----------------------------
-- Table structure for sec_group_permission
-- ----------------------------
DROP TABLE IF EXISTS `sec_group_permission`;
CREATE TABLE `sec_group_permission`  (
  `id_permission` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` int UNSIGNED NOT NULL COMMENT 'FK -> sec_group',
  `module_id` int UNSIGNED NOT NULL COMMENT 'FK -> sec_module',
  `can_access` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh buka & lihat (termasuk report yg diizinkan level). 0=modul TAK terlihat',
  `can_input` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh create/edit (readwrite). 0=readonly',
  `can_approve` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh approve (mis. Direksi approve harga)',
  `can_export` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=boleh export data (KRITIKAL: bisa bawa data perusahaan keluar)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_permission`) USING BTREE,
  UNIQUE INDEX `uq_perm_group_module`(`group_id` ASC, `module_id` ASC) USING BTREE,
  INDEX `idx_perm_module`(`module_id` ASC) USING BTREE,
  CONSTRAINT `fk_perm_group` FOREIGN KEY (`group_id`) REFERENCES `sec_group` (`id_group`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perm_module` FOREIGN KEY (`module_id`) REFERENCES `sec_module` (`id_module`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 42 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Izin per GRUP per MODUL (sec_module) — flag can_access/can_input/can_approve/can_export. Default DENY: tak ada baris = grup tak boleh akses modul.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_group_permission
-- ----------------------------
INSERT INTO `sec_group_permission` VALUES (9, 3, 9, 1, 1, 1, 1, '2026-08-15 04:37:49', '2026-08-15 04:37:49', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (13, 3, 13, 1, 1, 1, 1, '2026-08-15 04:38:35', '2026-08-15 04:38:35', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (14, 3, 6, 1, 1, 1, 1, '2026-08-19 01:02:03', '2026-08-19 01:02:03', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (15, 3, 14, 1, 1, 1, 1, '2026-08-19 06:53:40', '2026-08-19 06:53:40', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (16, 3, 2, 1, 1, 1, 1, '2026-08-20 17:09:27', '2026-08-20 17:09:27', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (17, 3, 1, 1, 1, 1, 1, '2026-08-27 02:37:15', '2026-08-28 14:38:02', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (18, 3, 4, 1, 1, 1, 1, '2026-08-27 17:43:32', '2026-08-27 17:43:32', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (19, 4, 4, 1, 1, 1, 1, '2026-08-28 01:41:07', '2026-08-28 01:41:07', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (20, 5, 4, 1, 1, 1, 1, '2026-08-28 01:41:07', '2026-08-28 01:41:07', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (21, 6, 4, 1, 1, 1, 1, '2026-08-28 01:41:07', '2026-08-28 01:41:07', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (22, 7, 4, 1, 1, 1, 1, '2026-08-28 01:41:07', '2026-08-28 01:41:07', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (27, 1, 1, 1, 1, 0, 1, '2026-08-28 14:38:02', '2026-08-28 14:38:02', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (28, 2, 1, 1, 1, 1, 1, '2026-08-28 14:38:02', '2026-08-28 14:38:02', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (29, 7, 1, 1, 1, 1, 1, '2026-08-28 14:38:02', '2026-08-28 14:38:02', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (30, 1, 4, 1, 1, 0, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (31, 2, 4, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (32, 8, 1, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (33, 8, 13, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (34, 8, 4, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (35, 8, 5, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (36, 9, 2, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (37, 9, 5, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (38, 10, 3, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (39, 10, 5, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (40, 11, 6, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);
INSERT INTO `sec_group_permission` VALUES (41, 11, 5, 1, 1, 1, 1, '2026-09-03 00:18:50', '2026-09-03 00:18:50', NULL, NULL);

-- ----------------------------
-- Table structure for sec_login_attempt
-- ----------------------------
DROP TABLE IF EXISTS `sec_login_attempt`;
CREATE TABLE `sec_login_attempt`  (
  `id_attempt` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL COMMENT '1=berhasil | 0=gagal',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'IPv4/IPv6',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_attempt`) USING BTREE,
  INDEX `idx_attempt_login_time`(`login` ASC, `attempted_at` ASC) USING BTREE,
  INDEX `idx_attempt_success`(`success` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 83 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Audit percobaan login (login, success, ip, user_agent). Tabel KANONIK sejak sys_login_attempts dihapus. Diisi SERVER-SIDE tiap percobaan.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_login_attempt
-- ----------------------------
INSERT INTO `sec_login_attempt` VALUES (1, 'admin', 0, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 10:51:07');
INSERT INTO `sec_login_attempt` VALUES (2, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 10:58:44');
INSERT INTO `sec_login_attempt` VALUES (3, 'rina', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 11:16:21');
INSERT INTO `sec_login_attempt` VALUES (4, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 11:30:28');
INSERT INTO `sec_login_attempt` VALUES (5, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-08 18:24:03');
INSERT INTO `sec_login_attempt` VALUES (6, 'admin', 0, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 06:52:09');
INSERT INTO `sec_login_attempt` VALUES (7, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-09 06:52:16');
INSERT INTO `sec_login_attempt` VALUES (8, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.28929.0 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX', '2026-08-13 02:24:10');
INSERT INTO `sec_login_attempt` VALUES (9, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.28929.0 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX', '2026-08-13 03:06:54');
INSERT INTO `sec_login_attempt` VALUES (10, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-13 03:12:32');
INSERT INTO `sec_login_attempt` VALUES (11, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.28929.0 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX', '2026-08-15 04:40:16');
INSERT INTO `sec_login_attempt` VALUES (12, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 05:39:47');
INSERT INTO `sec_login_attempt` VALUES (13, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.30096.5 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX', '2026-08-15 08:11:32');
INSERT INTO `sec_login_attempt` VALUES (14, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 08:42:55');
INSERT INTO `sec_login_attempt` VALUES (15, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-18 03:09:03');
INSERT INTO `sec_login_attempt` VALUES (16, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.30096.5 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX', '2026-08-18 05:11:34');
INSERT INTO `sec_login_attempt` VALUES (17, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-18 05:56:36');
INSERT INTO `sec_login_attempt` VALUES (18, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-18 08:16:09');
INSERT INTO `sec_login_attempt` VALUES (19, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-18 08:30:34');
INSERT INTO `sec_login_attempt` VALUES (20, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.32352.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36 MSIX', '2026-08-18 11:09:08');
INSERT INTO `sec_login_attempt` VALUES (21, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-18 13:39:33');
INSERT INTO `sec_login_attempt` VALUES (22, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.32352.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36 MSIX', '2026-08-18 21:14:06');
INSERT INTO `sec_login_attempt` VALUES (23, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-18 21:16:44');
INSERT INTO `sec_login_attempt` VALUES (24, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-18 21:27:03');
INSERT INTO `sec_login_attempt` VALUES (25, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.32352.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36 MSIX', '2026-08-19 01:06:16');
INSERT INTO `sec_login_attempt` VALUES (26, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.32885.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36 MSIX', '2026-08-19 07:08:23');
INSERT INTO `sec_login_attempt` VALUES (27, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.32885.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36 MSIX', '2026-08-19 15:36:43');
INSERT INTO `sec_login_attempt` VALUES (28, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.32885.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36 MSIX', '2026-08-20 00:29:11');
INSERT INTO `sec_login_attempt` VALUES (29, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-20 02:09:09');
INSERT INTO `sec_login_attempt` VALUES (30, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-20 03:43:25');
INSERT INTO `sec_login_attempt` VALUES (31, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-20 08:15:22');
INSERT INTO `sec_login_attempt` VALUES (32, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 04:37:34');
INSERT INTO `sec_login_attempt` VALUES (33, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 06:47:40');
INSERT INTO `sec_login_attempt` VALUES (34, 'admin', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 14:25:15');
INSERT INTO `sec_login_attempt` VALUES (35, 'admin', 0, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 16:35:35');
INSERT INTO `sec_login_attempt` VALUES (36, 'johiwan@gmail.com', 0, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 16:35:51');
INSERT INTO `sec_login_attempt` VALUES (37, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 16:36:04');
INSERT INTO `sec_login_attempt` VALUES (38, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 20:28:42');
INSERT INTO `sec_login_attempt` VALUES (39, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-22 00:46:47');
INSERT INTO `sec_login_attempt` VALUES (40, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-22 03:45:39');
INSERT INTO `sec_login_attempt` VALUES (41, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 00:23:05');
INSERT INTO `sec_login_attempt` VALUES (42, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-24 03:33:57');
INSERT INTO `sec_login_attempt` VALUES (43, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-24 03:36:01');
INSERT INTO `sec_login_attempt` VALUES (44, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-24 04:17:23');
INSERT INTO `sec_login_attempt` VALUES (45, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-24 06:24:58');
INSERT INTO `sec_login_attempt` VALUES (46, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-26 08:28:16');
INSERT INTO `sec_login_attempt` VALUES (47, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 00:23:39');
INSERT INTO `sec_login_attempt` VALUES (48, 'johiwan', 0, '127.0.0.1', 'Symfony', '2026-08-27 02:18:07');
INSERT INTO `sec_login_attempt` VALUES (49, 'verify_test_134436', 1, '127.0.0.1', 'Symfony', '2026-08-27 02:29:59');
INSERT INTO `sec_login_attempt` VALUES (50, 'verify_test_ab11d291', 1, '127.0.0.1', 'Symfony', '2026-08-27 02:30:41');
INSERT INTO `sec_login_attempt` VALUES (51, 'verify_test_6b718f02', 1, '127.0.0.1', 'Symfony', '2026-08-27 02:31:12');
INSERT INTO `sec_login_attempt` VALUES (52, 'nonexistent_user_xyz_6a8fa26088f1d', 0, '127.0.0.1', 'Symfony', '2026-08-27 02:35:12');
INSERT INTO `sec_login_attempt` VALUES (53, 'verify_test_95faac82', 1, '127.0.0.1', 'Symfony', '2026-08-27 02:35:36');
INSERT INTO `sec_login_attempt` VALUES (54, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 06:52:10');
INSERT INTO `sec_login_attempt` VALUES (55, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 07:13:30');
INSERT INTO `sec_login_attempt` VALUES (56, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 07:14:58');
INSERT INTO `sec_login_attempt` VALUES (57, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 07:17:07');
INSERT INTO `sec_login_attempt` VALUES (58, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 07:49:48');
INSERT INTO `sec_login_attempt` VALUES (59, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 01:44:45');
INSERT INTO `sec_login_attempt` VALUES (60, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 10:52:38');
INSERT INTO `sec_login_attempt` VALUES (61, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 22:41:32');
INSERT INTO `sec_login_attempt` VALUES (62, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 02:05:54');
INSERT INTO `sec_login_attempt` VALUES (63, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 03:12:39');
INSERT INTO `sec_login_attempt` VALUES (64, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 05:50:37');
INSERT INTO `sec_login_attempt` VALUES (65, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 06:51:30');
INSERT INTO `sec_login_attempt` VALUES (66, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 07:39:16');
INSERT INTO `sec_login_attempt` VALUES (67, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 22:46:20');
INSERT INTO `sec_login_attempt` VALUES (68, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 23:49:14');
INSERT INTO `sec_login_attempt` VALUES (69, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 02:03:42');
INSERT INTO `sec_login_attempt` VALUES (70, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 05:36:59');
INSERT INTO `sec_login_attempt` VALUES (71, 'testuser', 0, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.40609.0 Chrome/148.0.7778.280 Safari/537.36 MSIX', '2026-08-30 14:27:57');
INSERT INTO `sec_login_attempt` VALUES (72, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 14:38:13');
INSERT INTO `sec_login_attempt` VALUES (73, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-31 01:26:03');
INSERT INTO `sec_login_attempt` VALUES (74, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-31 02:50:44');
INSERT INTO `sec_login_attempt` VALUES (75, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-01 04:43:19');
INSERT INTO `sec_login_attempt` VALUES (76, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-01 08:02:56');
INSERT INTO `sec_login_attempt` VALUES (77, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 02:36:31');
INSERT INTO `sec_login_attempt` VALUES (78, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 00:03:46');
INSERT INTO `sec_login_attempt` VALUES (79, 'johiwan', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 00:43:52');
INSERT INTO `sec_login_attempt` VALUES (80, 'operator', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 04:29:47');
INSERT INTO `sec_login_attempt` VALUES (81, 'operator', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 07:53:47');
INSERT INTO `sec_login_attempt` VALUES (82, 'operator', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 23:54:17');

-- ----------------------------
-- Table structure for sec_module
-- ----------------------------
DROP TABLE IF EXISTS `sec_module`;
CREATE TABLE `sec_module`  (
  `id_module` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'NULL=modul induk | isi=sub-modul (mis. HPP di bawah INQUIRY)',
  `module_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik, mis. INQUIRY, HPP, MASTER',
  `module_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label menu',
  `icon` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kelas ikon (Font Awesome) / emoji',
  `route_prefix` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Prefix route Laravel, mis. inquiry (utk mapping middleware)',
  `min_mgmt_level` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Level manajemen MINIMUM utk akses (1=STAFF..5=DIREKSI). Report sensitif set tinggi',
  `is_reportable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=modul ini punya report (info tampilan)',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 999,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_module`) USING BTREE,
  UNIQUE INDEX `uq_module_code`(`module_code` ASC) USING BTREE,
  INDEX `idx_module_parent`(`parent_id` ASC) USING BTREE,
  CONSTRAINT `fk_module_parent` FOREIGN KEY (`parent_id`) REFERENCES `sec_module` (`id_module`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Modul bisnis = unit pemberian izin. module_code DIBUAT SAMA dengan kode modul sys_app_menu (SALES/PROC/INV/PROD/MDATA/FINACC/SETUP) agar izin & menu sinkron.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_module
-- ----------------------------
INSERT INTO `sec_module` VALUES (1, NULL, 'SALES', 'Sales', 'fa-solid fa-file-invoice-dollar', 'sales', 1, 1, 10, 1, '2026-08-09 01:56:48', '2026-08-27 02:37:13', NULL, NULL);
INSERT INTO `sec_module` VALUES (2, NULL, 'PROC', 'Procurement', 'fa-solid fa-cart-shopping', 'procurement', 1, 0, 50, 1, '2026-08-09 01:56:48', '2026-08-09 01:57:16', NULL, NULL);
INSERT INTO `sec_module` VALUES (3, NULL, 'INV', 'Inventory', 'fa-solid fa-boxes-stacked', 'inventory', 1, 0, 60, 1, '2026-08-09 01:56:48', '2026-08-09 01:57:16', NULL, NULL);
INSERT INTO `sec_module` VALUES (4, NULL, 'PROD', 'Production', 'fa-solid fa-industry', 'production', 1, 1, 40, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (5, NULL, 'MDATA', 'Master Data', 'fa-solid fa-database', 'master', 2, 0, 50, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (6, NULL, 'FINACC', 'Finance & Accounting', 'fa-solid fa-sack-dollar', 'finance', 3, 1, 60, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (9, NULL, 'SETUP', 'Setup', 'fa-solid fa-gear', 'setup', 5, 0, 99, 1, '2026-08-09 01:56:48', '2026-08-09 01:56:48', NULL, NULL);
INSERT INTO `sec_module` VALUES (13, NULL, 'COSTING', 'Costing (HPP)', 'fa-solid fa-calculator', 'inquiry-costing', 2, 1, 15, 1, '2026-08-15 04:38:35', '2026-08-15 04:38:35', NULL, NULL);
INSERT INTO `sec_module` VALUES (14, NULL, 'HRM', 'Human Resources', 'fa-solid fa-users', 'hrm', 2, 1, 45, 1, '2026-08-19 06:53:40', '2026-08-19 06:53:40', NULL, NULL);

-- ----------------------------
-- Table structure for sec_recovery_question
-- ----------------------------
DROP TABLE IF EXISTS `sec_recovery_question`;
CREATE TABLE `sec_recovery_question`  (
  `id_question` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_question`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_recovery_question
-- ----------------------------
INSERT INTO `sec_recovery_question` VALUES (1, 'Di kota mana Anda lahir?', 1, 10, '2026-08-27 02:57:44', 'claude_code', NULL, NULL);
INSERT INTO `sec_recovery_question` VALUES (2, 'Siapa nama hewan peliharaan pertama Anda?', 1, 20, '2026-08-27 02:57:44', 'claude_code', NULL, NULL);
INSERT INTO `sec_recovery_question` VALUES (3, 'Apa nama SD (Sekolah Dasar) Anda?', 1, 30, '2026-08-27 02:57:44', 'claude_code', NULL, NULL);
INSERT INTO `sec_recovery_question` VALUES (4, 'Apa makanan favorit Anda saat kecil?', 1, 40, '2026-08-27 02:57:44', 'claude_code', NULL, NULL);
INSERT INTO `sec_recovery_question` VALUES (5, 'Apa nama jalan tempat Anda tinggal saat kecil?', 1, 50, '2026-08-27 02:57:44', 'claude_code', NULL, NULL);
INSERT INTO `sec_recovery_question` VALUES (6, 'Apa nama panggilan Anda saat kecil?', 1, 60, '2026-08-27 02:57:44', 'claude_code', NULL, NULL);

-- ----------------------------
-- Table structure for sec_user
-- ----------------------------
DROP TABLE IF EXISTS `sec_user`;
CREATE TABLE `sec_user`  (
  `id_user` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username login',
  `pswd` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Hash password (bcrypt Laravel)',
  `default_password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT 'Ciphertext Laravel Crypt (AES-256, key=APP_KEY). Password default terbaca oleh admin. Permanen, timpa saat reset.',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = user wajib ganti password saat login berikutnya (habis reset / user baru).',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `phone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `birth_date` date NULL DEFAULT NULL COMMENT 'Verifikasi lupa password poin 1. Tertaut employee_id -> disalin dari emp_employee.birth_date saat setup; akun tak tertaut -> isi manual.',
  `mother_name_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Verifikasi lupa password poin 2 (nama ibu kandung) -- HASH bcrypt, bukan plaintext/Crypt (tak pernah perlu ditampilkan balik).',
  `security_question_id` int UNSIGNED NULL DEFAULT NULL COMMENT 'FK -> sec_recovery_question.id_question. NULL = belum setup.',
  `security_answer_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Verifikasi lupa password poin 4 -- HASH bcrypt.',
  `security_setup_at` timestamp NULL DEFAULT NULL COMMENT 'Kapan user menyelesaikan setup 4 pertanyaan keamanan. NULL = belum setup, dipaksa lewat /security/setup.',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ex active varchar(1) -> tinyint',
  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ex priv_admin: superadmin bypass semua izin',
  `activation_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `mfa` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Secret MFA (bila aktif)',
  `mfa_last_updated` timestamp NULL DEFAULT NULL,
  `pswd_last_updated` timestamp NULL DEFAULT NULL,
  `employee_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'FK logis -> emp_employee (tm2mst)',
  `linked_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Jenis entitas tertaut, mis. EMPLOYEE',
  `linked_id` bigint UNSIGNED NULL DEFAULT NULL COMMENT 'ID entitas master (lintas-DB, dijaga app)',
  `id_type` enum('nik','nrp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `reg_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'customer|supplier|internal — hanya diisi dari self-registration',
  `id_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'NIK (customer/supplier) atau NRP (internal) dari self-registration',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `wa_verified` tinyint(1) NOT NULL DEFAULT 0,
  `wa_verified_at` timestamp NULL DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `theme_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'navy',
  `admin_theme_mode` enum('light','pastel','bright','dark','black') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `admin_theme_accent` enum('blue','green','red','purple','amber','cyan','mono') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_user`) USING BTREE,
  UNIQUE INDEX `uq_user_login`(`login` ASC) USING BTREE,
  INDEX `idx_user_employee`(`employee_id` ASC) USING BTREE,
  INDEX `sec_user_security_question_id_foreign`(`security_question_id` ASC) USING BTREE,
  CONSTRAINT `sec_user_security_question_id_foreign` FOREIGN KEY (`security_question_id`) REFERENCES `sec_recovery_question` (`id_question`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 259 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Akun login. login=username unik, pswd=hash. Kolom pendaftaran: id_type/id_number (NIK/NRP), email_verified, wa_verified, approval_status, theme_code. linked_type/linked_id menaut ke customer/supplier. (Catatan: active vs is_active masih dobel — pilih satu; reg_type overlap dgn linked_type.)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_user
-- ----------------------------
INSERT INTO `sec_user` VALUES (1, 'johiwan', '$2y$12$oFd65uXF2Xb9NRDmJzRmEu9LKcxcnbO8BmFvBwCfVOGKbzvk8P0Bm', 'eyJpdiI6Iks2bFBKSlMxSlFVMlBwRjltZEVLSVE9PSIsInZhbHVlIjoiZ1dsUHI0TkVwYzQ0WCtCb20yTTd0Zz09IiwibWFjIjoiN2QzNGYwZGY5ZjVhOTIxYmJkNDZlNzc1ZjJmYWJkM2M1ODU0Yjg1NWI2YTY4YmY3MjIzZGMyMjRhYWUxZWRiMyIsInRhZyI6IiJ9', 0, 'Johannes Iwan', 'budi.santoso@telasih.co.id', '+62 82255999048', '1969-10-01', '$2y$12$UwuzbG8IbakOOw5xtQXuI.fGPo27LKcXguiPB/o6.eLWcHU55zhaC', 1, '$2y$12$Lw7WyAsCJNdkuU9vtGHRWunRLLwgkE4pWZV7Qt9A5LLOZo/H6A1Le', '2026-08-27 07:17:59', 1, 1, NULL, NULL, NULL, '2026-08-21 16:27:47', 1, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'pending', 'dark_blue', 'light', 'blue', '2026-08-08 10:36:57', '2026-09-02 02:52:41', NULL, 'claude_code');
INSERT INTO `sec_user` VALUES (2, 'rina', '$2y$12$TWqA9X9VyKchNwCj3f95yem69aMNK6Ug7SC4Coa4DHjf3n/m5Vxm.', NULL, 0, 'Rina Wijaya', 'rina.wijaya@telasih.co.id', NULL, '1990-01-01', '$2y$12$XMq0GhEAJ1hpAr4uEqU1rOBlHwlkCdOqb3oksPI8wn8Hu0D.1klAS', 1, '$2y$12$c.4o/K7NThEQBYztEt5ufOJ4d3pz2Cuq6802/J7GtmXWtcjoi7wNC', '2026-08-28 12:20:10', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'pending', 'navy', 'light', 'blue', '2026-08-08 10:36:58', '2026-08-28 12:20:10', NULL, NULL);
INSERT INTO `sec_user` VALUES (32, 'nisa', '$2y$12$gdq7aOUHLAFOEa9DDtVofeiIG6If.rGfRForAJuwqiGMCRSW7018e', 'eyJpdiI6IlJlaFVDTFZZRE9EN2hTTEIzUkdZZmc9PSIsInZhbHVlIjoialFyZTlkelZ3K1A0WWFab3l2YzF1QT09IiwibWFjIjoiNDVlZGJjOTQ1OGIwMDBkMGZiMDRmMDQ1MmQ2NzRmMjNlM2U0ZDVmZGJjMjhhMDI5YTAyZWU2MTlkZDdkZGRhOCIsInRhZyI6IiJ9', 1, 'Nisa', 'nisa@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-18 04:09:22', NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-18 03:53:05', '2026-08-18 04:09:22', NULL, NULL);
INSERT INTO `sec_user` VALUES (33, 'arinda', '$2y$12$X771h2Rt2OFdZQgoN95o1uC4TyDYV4OLiEN.0HVCXe33uWjWg21Ry', 'eyJpdiI6Ilk5WkJhRnZDR2d0TzZIR0lOa2ZkaUE9PSIsInZhbHVlIjoiS0g4UkQ0NkszMkJWQTZQY2IrRjMyQT09IiwibWFjIjoiM2ZiMTZkM2NhNzUyNjI4ZTNmMzRiMDM2NjhlOGZmY2UyZDQyODAxYmRiYzYwNzkzZTYxOGQwNjE3MTVmNDY5NyIsInRhZyI6IiJ9', 1, 'Arinda', 'arinda@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-18 04:09:23', NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-18 03:53:06', '2026-08-18 04:09:23', NULL, NULL);
INSERT INTO `sec_user` VALUES (34, 'sania', '$2y$12$fIR.2sR6sgPKqv1PM1CbM.85uptAi2S5GaWBzz/7nIEfi18V8n0vO', 'eyJpdiI6ImhPTjR3RmZsOFpRUUpUVVUyNjlwc3c9PSIsInZhbHVlIjoidnZ0aVQ0UUIvb09TdGlKRzFza2tudz09IiwibWFjIjoiODBlNTU3Y2ExNjY3MzRiMjI4YjI2MDJjZDRmODA2YTExN2JkZTg4Mjk3MjMzMmEwODIwNjNjZjhmNjk2MmZkMSIsInRhZyI6IiJ9', 1, 'Sania', 'sania@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-18 04:09:24', NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-18 03:53:06', '2026-08-18 04:09:24', NULL, NULL);
INSERT INTO `sec_user` VALUES (35, 'tya', '$2y$12$dA5w04F5xxy7zb02EjoNHuYWG0UORj/5ytzTbwA2TeNAbjJsH.dJ6', 'eyJpdiI6IlhmNHpXK3FDOVhkZmJLTWlvS2NxZWc9PSIsInZhbHVlIjoiT0Yzb3hyL1l1R3dkRjBIU0VaenBSQT09IiwibWFjIjoiNWRhYmQ4OTk3YzVkNjBkM2YzNDZhYWRkM2NhYWE3OTc1M2Q3MDZiNjkxN2JiNjcxNDQxMDdjOWMyNzk0ZmUzNCIsInRhZyI6IiJ9', 1, 'Tya', 'tya@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-18 04:09:24', NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-18 03:53:06', '2026-08-18 04:09:24', NULL, NULL);
INSERT INTO `sec_user` VALUES (36, 'eka', '$2y$12$taVXdVee31td/TiUJXPuFeQjFkbIST20G5sFJVy/ZHWUFnPtgCmpq', 'eyJpdiI6ImJrNGE4TkF3N3NaaUlMUHZOS1dkL3c9PSIsInZhbHVlIjoiVUhMUHd0bC9WcXRRdFl1YkNoTGRkZz09IiwibWFjIjoiOWUwYzA5MWQ4ZTJhZTFkMWI1YzYyYjljMGRhYTE2YzU1MTUwZjJiZGU0OTMxYWU2YzEwM2YxNDhkZTk2ZTEyYSIsInRhZyI6IiJ9', 1, 'Eka', 'eka@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-18 04:09:25', NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-18 03:53:06', '2026-08-18 04:09:25', NULL, NULL);
INSERT INTO `sec_user` VALUES (37, 'wiwik', '$2y$12$BaMjOiDa1iNNRLNGfy09AuROAFDhhurEJrGHAD1CyD6p0hQOrvdX.', 'eyJpdiI6IlN1d0NyL0ttay9HUk92YnpkTDZsU1E9PSIsInZhbHVlIjoiTDdZV2tLQlB3Zm5ON1BVcmNkZXlXdz09IiwibWFjIjoiMTQwZjllODAwNmEyNDdjNWRiNGFmODkyZWQ0YzU5YWE0NzlmMThhMzk2MGEyYzdhZjEwZDgyYjBjMjFiY2NmYSIsInRhZyI6IiJ9', 1, 'Wiwik', 'wiwik@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-18 04:09:26', NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-18 03:53:07', '2026-08-18 04:09:26', NULL, NULL);
INSERT INTO `sec_user` VALUES (38, 'dummy_mgrops', '$2y$12$pMewfJNP8FrIgKSLZZtk5uesX4cfTvxKBsdTa7e9121/S58UudTe6', NULL, 0, 'Contoh Manajer Operasional (dummy)', 'dummy.mgrops@telasih.local', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, NULL, 6, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'pending', 'navy', 'light', 'blue', '2026-08-20 18:05:39', '2026-08-20 18:05:39', NULL, NULL);
INSERT INTO `sec_user` VALUES (39, 'mahmud', '$2y$12$uhsWJoBfNlNOvu7hynw02ed19L7CiCq.Liv/rR53RC6xN/bRrIbb.', 'eyJpdiI6IjdVS1UvWWxRemVrSUNBNWp3dUtHdFE9PSIsInZhbHVlIjoibjllY1JCNDZHcVdrQWtlWWRob1FQdz09IiwibWFjIjoiYTIwYTA1ZmU3NGZmZmM2NjcyZjcwZTA5YTc2MTIxYTI4YjhhZTUzMDc4OGY3OGE2MWU3MGE4YzJlNTRlMjI1NCIsInRhZyI6IiJ9', 1, 'MAHMUD SUPAAT', 'mahmud@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:44', 7, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:44', '2026-08-21 05:00:44', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (40, 'heru', '$2y$12$UgU10uLhZf0FKKbOtre6WOFN94MmmeUkNx4vv5Uz2uA0mrMnTuzVW', 'eyJpdiI6IkMwcGRxNEFFM1B3QzFteER2WFQxN0E9PSIsInZhbHVlIjoidEE1cUlJVTdGTmd1N0I5allCYXY1UT09IiwibWFjIjoiNDkyZWYxZDQwZWJjMWQ1ZWViMTU2ZjgxZGQ3NDNhNzYzZjc1YmYxODg2ZTBjNWIzNjY4ZmM1NDUzNzU3YzZmNCIsInRhZyI6IiJ9', 1, 'HERU KURNIAWAN', 'heru@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:44', 8, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:44', '2026-08-21 05:00:44', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (41, 'agus', '$2y$12$MVu2BjKktKCikNG6h9CTqe93INzJVXgbv.hhnP3rlJqxYcJ8irZKC', 'eyJpdiI6Im5FMUtPTVBDNmdhWE9LbXhwSmUzTlE9PSIsInZhbHVlIjoiOFhram54VkZUUTdYcXNIRnJpUGhwZz09IiwibWFjIjoiMzM5OTllMzg4NzM2MjAyYWRhZWE2N2JhNzUyOTEyODQ0NWIwZTVhYmMxMzM5NGRhODIzZTNmZjYyOTc3NDY5OSIsInRhZyI6IiJ9', 1, 'AGUS BUDI SUSANTO', 'agus@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:45', 10, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:45', '2026-08-21 05:00:45', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (42, 'gani', '$2y$12$qWgWQtmne38rSPzFSjXgp.KvCR38yEuc9Owjh6Qhjyng2qynrh9hy', 'eyJpdiI6IlN6d2Zmc0xmVzg4azNCdTdkalVzUlE9PSIsInZhbHVlIjoia1lXSlpwMTJXUEZzWFRIcFM0Z1NBZz09IiwibWFjIjoiYzk0NGU2MTM5ZThlOWQ2YWIzNWQzNTU2MTg3Yjk4NmUyZTEyOTc1NjA3ODFlODI5ZDU2Mjk0ZGZiNjBjYTU3YSIsInRhZyI6IiJ9', 1, 'GANI CAHYONO', 'gani@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:45', 11, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:45', '2026-08-21 05:00:45', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (43, 'muhammad', '$2y$12$3SXf1m20vRclvihksW2BgO7MCjWALDcyywyIUkLQheNf6p7J5npBG', 'eyJpdiI6IjF6RjJ4dDlWZWJFSTlFbEpUY1V6Snc9PSIsInZhbHVlIjoieTRKZm1VYkFaWjh0RDRhRHdSL05udz09IiwibWFjIjoiZTk0MmRkZmRmMzM1M2ZkNjg3YWM5NWIyNTk1MmYxY2FlMDZmYjc1ZWYwYzgzMWY4ZWVjNzhjZjIwODBhNzMyZSIsInRhZyI6IiJ9', 1, 'MUHAMMAD VERY SYAHPUTRA', 'muhammad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:45', 12, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:45', '2026-08-21 05:00:45', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (44, 'guntur', '$2y$12$0tdUJ94Jgd3Zp18VuNPTD.D8VduE.Pw25ZlzsgDVl7Wk6qpYF0MDq', 'eyJpdiI6Ik9KRnhkNGMydm5QbzRJUVB6QkV1SGc9PSIsInZhbHVlIjoiUFlUc1BQVVpqdjcwMGlIRFRaS1RCdz09IiwibWFjIjoiOWJhMDIxYmI3N2Y0YWIwM2E1NTEwMTg0NDYxNDgzOGZhZDY0MjE1NTM5ZjdjNjY3ZWE2ZDQxZTViNDRmMWM2NiIsInRhZyI6IiJ9', 1, 'GUNTUR REZA PAHLEVI', 'guntur@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:45', 13, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:45', '2026-08-21 05:00:45', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (45, 'fendi', '$2y$12$UkLt0ko9pqs9Ax1.1.1iUuoyPOc3wlTOWhGxb332xrUOK9IchrzWS', 'eyJpdiI6IllaR3VCOW5OcGRUSmpJc0xWaTZGU3c9PSIsInZhbHVlIjoiNTNJbWJiTWZEZlYzVENuQk9lS3FhUT09IiwibWFjIjoiNmRlNzE2ZmRmMjZjNzhlYzVhYWI1ZTAyYmZmNGFlZWIyOGRlZDYwZjkyM2YxMTc1MzlkZGQ4MmFkNjZlZTA5YiIsInRhZyI6IiJ9', 1, 'FENDI PRASETYO JAYA WASESA', 'fendi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:46', 14, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:46', '2026-08-21 05:00:46', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (46, 'sofyan', '$2y$12$F6WH06wFW2WLIyNgH5G5W.Vjd7qV.d/0.NkM2jNRYfV2d5wGOIUdK', 'eyJpdiI6ImZDUkRXclpJVGlTMFZSTmNiWFZzK0E9PSIsInZhbHVlIjoicHZGdGM3bmZ5K2dCQkZZQ2d1V084QT09IiwibWFjIjoiNmIyMGU5NDU5Mjk3MWYwODhmZGQyMWFiYzc5NGQ5YjU1ZmM2NTcyOWRjMGY5Njk2ODIxMWRiNzEzNDNmYTkxMCIsInRhZyI6IiJ9', 1, 'SOFYAN HADI ROBY SAPUTRA', 'sofyan@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:46', 15, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:46', '2026-08-21 05:00:46', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (47, 'toni', '$2y$12$i460a3EhQ.OxeYelAo7K8.J5N8RAAnkCWlrmcgPcd4tUt38eE6aku', 'eyJpdiI6Im1mNGFFc2pjSGd2eUFnNWs2RHRCTFE9PSIsInZhbHVlIjoiN1cvMFk2NWdxaUJWQmtRVGtpTHRxQT09IiwibWFjIjoiNDRiYWIwODBjNGU2NjU1YjZiY2U2MmQ1NjQ5YzAwMWMyNjdhOTZhOThjNzY4NmIwOTBjNGRiOTc0NzRkMzViMiIsInRhZyI6IiJ9', 1, 'TONI WANDOYO', 'toni@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:46', 16, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:46', '2026-08-21 05:00:46', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (48, 'ahmad', '$2y$12$UHEqPvb.qKyv1LpKOkC5qu.XDyOpIv7EpXzLsiBUb3WRDazj6nDM.', 'eyJpdiI6IlBlZ3pQMTZzdC9yK1FBcE1RdVMxeVE9PSIsInZhbHVlIjoiaVhjN3dYM200YVVkTldJYmV3b3p3QT09IiwibWFjIjoiMTFmYzg5YTYxMTY1OGYyYzQ3OGViMDE1NDM0ZTc1YzlmZThlM2IyZGRiNTdkZjIwZTFhNThhYmExY2Y2YjkxMyIsInRhZyI6IiJ9', 1, 'AHMAD ALWI NASTAIN', 'ahmad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:46', 17, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:46', '2026-08-21 05:00:46', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (49, 'bobin', '$2y$12$A.rgVpjqsrsdN42DAaibkONX7iCo1Zp.4nwnXP2aiqH6GlEndv7Dy', 'eyJpdiI6IlVLU2tMWkFQNlczUU8yaWs1L21XNUE9PSIsInZhbHVlIjoiWHUzOHE1aTIvMlRlZUhzbHdCUzlWQT09IiwibWFjIjoiZWIyYzBiZjA4MWQ5ZDE3NDM4OTI4YzAzYzU2ZmM0NmFiZWY4YzM5YzM1YjEwYTBmMTNmOTg5YWJlMTQ3NWRhOCIsInRhZyI6IiJ9', 1, 'BOBIN ERFIANTO', 'bobin@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:46', 18, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:46', '2026-08-21 05:00:46', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (50, 'mochamad', '$2y$12$EiAUYUQK5T64MQxWOON9pO.ONPQBQX968SbJ2bA3MgHx82GOkLPWi', 'eyJpdiI6IlhDOXpQTWdzcWhkcjFqNEg1dTkzTkE9PSIsInZhbHVlIjoiQzc3SXIyNmVHdno3c0hjcVNxVm15dz09IiwibWFjIjoiMDg5ZThlMzVlMmZiNDMxY2JlZGE0M2YyZGVmYzVlNjJlN2FhNjczYWZiMzFhMjRkY2FlNDc2ZTA5MGVmYzc3MCIsInRhZyI6IiJ9', 1, 'MOCHAMAD KHOIRUL AMINUDIN', 'mochamad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:47', 19, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:47', '2026-08-21 05:00:47', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (51, 'dimas', '$2y$12$S5JWOxbqE2.6WoVOTs3xo.mVyPZgT5.YGs63lP3aoYCeJ2NCnW5gi', 'eyJpdiI6IlFXZ1FZbkJicmFZWHhJSHdoU3RJMXc9PSIsInZhbHVlIjoiY0NoWDBpbE5QTUlkNTBHai9RNHNiQT09IiwibWFjIjoiMWY2ZjJiNDEyOGQxMDJiMDUxYmY5MWFmNzVjNTA1NzFmM2IyMzM0OTgxOWExMGY0NGIyY2Y3ZmM4YzdjMjZiYSIsInRhZyI6IiJ9', 1, 'DIMAS ANGGA', 'dimas@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:47', 20, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:47', '2026-08-21 05:00:47', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (52, 'arif', '$2y$12$iHaRzG9tOYGsWPk0XOrDUeJXYDE3MBPTGRmtEjM2grLCZpx2dOH.e', 'eyJpdiI6ImJieXVhSHN5V1A1U2RLK3BVYmtrNmc9PSIsInZhbHVlIjoibGJBRHk5V3JDN2E1dGlUVm5WZlpoUT09IiwibWFjIjoiMzQyOGU2N2Q0MTYwMGU1ODI5MDU5OTgwY2Y3ZmVjNTIwY2FjMzhmNTc1OGIyOGQ0MTcyMTdjNDBjNDI5ZDIwOSIsInRhZyI6IiJ9', 1, 'ARIF RAMADHANI', 'arif@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:47', 21, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:47', '2026-08-21 05:00:47', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (53, 'didit', '$2y$12$GPZHRaI0X9D0R3oAtizf.ucmZ3xNuav5olN8ACUl7x9nvTzY5uJae', 'eyJpdiI6ImlGcjNCWnlPeUcvSmZXVDhPdjMvWXc9PSIsInZhbHVlIjoiWnFzSW9RSXJPV0hxRzE4VC9vNmpJUT09IiwibWFjIjoiNzIzMTU3ODI5MmIwODZjM2FhZDQ3Njg1ZDBiNzNlNDk5ZjE5ZTM1ZmQxZjVlNzRjNWE4Njg2ZDgzMTcyMDAwMyIsInRhZyI6IiJ9', 1, 'DIDIT ADITIA KURNIAWAN', 'didit@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:47', 22, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:47', '2026-08-21 05:00:47', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (54, 'ivan', '$2y$12$qX2Z7UtRWhCimXd1fongFefpt1lsxrl3HuMdncKFsBz.h44FsghQi', 'eyJpdiI6IlBsdU5mLzRsNHYwS0QwK3Y1RUVxOGc9PSIsInZhbHVlIjoiTVJXekNOcDFuQ0RiQWFNR29qTnVmZz09IiwibWFjIjoiZWExZjg5YTU5NmNmOGJiOWFmZmYxZDllYTBiNzUyYjcyZmRkNzAwMTRlODVkMWY0OWYyOGMyODQyODAxMDk2YSIsInRhZyI6IiJ9', 1, 'IVAN SAPUTRA CAHYA NUGRAHA', 'ivan@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:47', 23, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:47', '2026-08-21 05:00:47', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (55, 'hendri', '$2y$12$fQBMofheg1KrNYnSlGEteuBmkcaCzg0f6NU3yESXgNMFuJ1Zswdzi', 'eyJpdiI6IkVhd2srWTBVTENmVVFiTUlWNTJSVlE9PSIsInZhbHVlIjoiWWxRanR5bHc4d2k5cTVBanJxUmI4QT09IiwibWFjIjoiNmM4ZTBmMzk4Nzk5MGZjMWIxZjUwNGViMDdjMTI3ZDVlMGMyOGJkOGZmY2YzZTM3MGFkMzdmNDhiOWU5MmRiNCIsInRhZyI6IiJ9', 1, 'HENDRI PRASETYA UTAMA', 'hendri@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:48', 24, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:48', '2026-08-21 05:00:48', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (56, 'm', '$2y$12$DeAiKyqLjPGegiIPmWgd.udqLq6wWe2.7mcXU2KhsKduBmLwQy5mW', 'eyJpdiI6IkFtdFdYR3RzZHV5M2pQWkFQcWIycFE9PSIsInZhbHVlIjoiQTdGditackd6SGk3Z080RS9FaTRsZz09IiwibWFjIjoiNjU4OWQ0ZjdlY2RjNDQwODFlY2NhOGViMjNiZWMyYzFmNWIyNWI4MThkYjFkYWRhZWU4YjMwMjJhNTIwYzU0OCIsInRhZyI6IiJ9', 1, 'M. MUNIR CHOLIS', 'm@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:48', 25, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:48', '2026-08-21 05:00:48', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (57, 'm2', '$2y$12$WVHZnyFD4jQI09HAYDrYZe2zvmXYi3cZzlU2VCAeoC0vw0Z1IX3wC', 'eyJpdiI6InlJZ2lJUzY2UE5GRFNzNlRKT3FjeVE9PSIsInZhbHVlIjoibVhCbjFXWHRHb29lZDc1dTcvbUFJUT09IiwibWFjIjoiZmM4Y2Y2MDA1MTVmMDI5ZjZhYWY2NzIyMTdiZWZiYzhlMjVmZWUzOWIwYTFlYTY5NDk4ZDJmYzFkYjRiNmZmMSIsInRhZyI6IiJ9', 1, 'M. AZKA NOVAN RAMADHAN', 'm2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:48', 26, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:48', '2026-08-21 05:00:48', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (58, 'm3', '$2y$12$9rdP9iPS4OCyhMThuWNmoeQOkxHepiHmJwlYJaagFJgaUTHaHLzk.', 'eyJpdiI6ImEzNDlrYkhyVUtNYThMc2NyRjRxblE9PSIsInZhbHVlIjoiVHhMYUwzSFlmMStzek1qSGZxV2k1UT09IiwibWFjIjoiYjUwNWU3NDE2ZmRiMGU0NzM0MzFhMTUxZDJiYzNhOTI2YWY2ZmE3MzExOTZkNDI0ODY2NWViYmQzMTRjZGJiYiIsInRhZyI6IiJ9', 1, 'M. TEGUH ERLANGGA P', 'm3@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:48', 27, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:48', '2026-08-21 05:00:48', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (59, 'achmad', '$2y$12$T6KTAjwmh4i0kOUAhNEzEeCk.hO/KC5XvJThdwbt6IaOulbDBL44W', 'eyJpdiI6ImNBd28xQ3RxT05wWjBNY3RBaFpLUWc9PSIsInZhbHVlIjoiY0kwRXlRaytpNTA4RXAxbFZXOGJsdz09IiwibWFjIjoiNTdjYzI1MjAwYmFkMjAzMzllZTdkYmM2OTMyMDk1MmU4MGRjNjg0ZWJiNzUwYTY0MzY1ZDc5MzU4ZGMwN2U4YiIsInRhZyI6IiJ9', 1, 'ACHMAD SYARIF', 'achmad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:49', 28, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:49', '2026-08-27 02:18:34', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (60, 'arif2', '$2y$12$MaA6uHg2AITl6XEaf.T/QOI1DOJVjRSSR2ZuHGQSUrnGZ26.2.RMW', 'eyJpdiI6IlhSZ1MvWkR5VzVYQ1dRSXdiM3JEK1E9PSIsInZhbHVlIjoidEdyYVdYME0wUmFZZVNNK2I5L0NPZz09IiwibWFjIjoiN2VlMDc1N2EyNTI3NjJiZThlZTA2YzVhZjlmYWNjMzViYjkxYmNkZTYxMGE4MzEzNTgzMzg4Y2I2MWRhMzBiOCIsInRhZyI6IiJ9', 1, 'ARIF ARIZAL', 'arif2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:49', 29, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:49', '2026-08-21 05:00:49', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (61, 'habib', '$2y$12$TtdPz35Cb7..PXVqgg6mBOwLRSXMPvT2.wXWP2za4anItaIDeWhNK', 'eyJpdiI6ImRob29iL1FONFFxc3FENjdtdlhScnc9PSIsInZhbHVlIjoidlBPY04rTW5HZGlPblVKUlNJeUNrQT09IiwibWFjIjoiMTY4NGYxYTg1NDk5NmE0ZGIzNzMzMjM3MTg3M2RkY2VkYjcwNDE5NmVlMzg4YTg4NzRiMzRhZWJhNTMzMTgwNSIsInRhZyI6IiJ9', 1, 'HABIB AKHDAN AL FALAH', 'habib@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:49', 30, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:49', '2026-08-21 05:00:49', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (62, 'andy', '$2y$12$aewLpIDi4scmEW7HtWqvUu8ab1CiRrRwzII4YMHxLASaXYfC4g8Dm', 'eyJpdiI6IjZ5REUwcitaRHozcHdLRkxFeHdWRVE9PSIsInZhbHVlIjoiOHVPcW1DbWwyUk9XNENWbitxR2NoQT09IiwibWFjIjoiMWJhZjg5ZWRhZThmNzFlNTgzNTYwYzMyNjAyZjFiMWQ1N2FmYjY4ZjVkZDEwMTRiZjM5YzU5YTI0NTExYTk4ZiIsInRhZyI6IiJ9', 1, 'ANDY MAULANA SUPRAPTO', 'andy@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:49', 31, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:49', '2026-08-21 05:00:49', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (63, 'wahyu', '$2y$12$EcESzL9Tx6xJpckicmGTw.oZbCRmxygfqGZbTK5e6C5.fL4PaZ/.y', 'eyJpdiI6IldrMXJkRWhEcHRZV0NTMXlJYUNSYlE9PSIsInZhbHVlIjoibVpLR1E2RFdiaUpPRGJxZ0paVE1FZz09IiwibWFjIjoiOTc4YzI5NzVlOGE1ODYxMmUyN2JiZjYwZTdjNGZiYjk1YTgzYjk5OThjMGNjOGFjNWYyYTVjZjllM2I3YzhmNSIsInRhZyI6IiJ9', 1, 'WAHYU NUR CAHYO', 'wahyu@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:49', 32, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:49', '2026-08-21 05:00:49', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (64, 'wahyu2', '$2y$12$Xche8KeSLPUQUrkv/z8emOapLZLZ0w0IllwmQKL9b6R/pIQciCUNS', 'eyJpdiI6Ik01SCthRXVXVzVKUUxYVXhlalB6UWc9PSIsInZhbHVlIjoiWXQ2azNtckxXSFIwT25WTENiMENnUT09IiwibWFjIjoiNDZmNjBlMTgxYjhjMjgzMjRkNTZhOTM0OWU1YjdlNTViYmVlZjRlNzM1N2Q5Mzk4YmVjOGQ0OWNjNDkwMDU1YSIsInRhZyI6IiJ9', 1, 'WAHYU WIDODO', 'wahyu2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:50', 33, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:50', '2026-08-21 05:00:50', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (65, 'muhamad', '$2y$12$cFlMdU8v2MlXOYjoPJ/I3u3dxGXtt0snhXbGln9CQDKDkJf0pxhsu', 'eyJpdiI6IkZQS3dWYWtqL1lWSXJxb1Z4b1JZUmc9PSIsInZhbHVlIjoiRXNIQXhJN01xdWpDSjNRdUF2NE9Vdz09IiwibWFjIjoiZDExMWViNzYwMTI5Nzc1YjUxZDNlZGIwMmY0OTkwODY4OTg2YmJiYmIyMWNhOWQ1MDBkODg5MWYzMzgwYWU2MSIsInRhZyI6IiJ9', 1, 'MUHAMAD FAHRUL BISMA', 'muhamad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:50', 34, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:50', '2026-08-21 05:00:50', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (66, 'tri', '$2y$12$nO6/wCubnz6AW6O4THXDC.nI2JRpuEYSG0XWJTqsSrghclenIR3he', 'eyJpdiI6Ikc1UHRpOU9YbVpuQlF2dXRvekZucWc9PSIsInZhbHVlIjoiS3k2cm1IUDZxMHpVTGIzNG5xSkFEQT09IiwibWFjIjoiNGFhYzFiMTQ2Njc0MmQ2MDFjYzE5ZGVkYjE2ZGM5YjQ5Njk5Y2ViOGZjMzMzYjQ5NDcxZGVjZDQzNzJmMzM4MiIsInRhZyI6IiJ9', 1, 'TRI DWI ASMA DENI', 'tri@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:50', 35, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:50', '2026-08-21 05:00:50', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (67, 'm4', '$2y$12$HVieQT/Jgz8sxIWn1fueuu6fOLXT5V03/QUSvx.osTjioxBhdCL7y', 'eyJpdiI6ImZqcHU0RDRza0NyS1ZsL1hKczIwRWc9PSIsInZhbHVlIjoiY1JzcE45ZGVLNzlFZ2RWYmlIblVvQT09IiwibWFjIjoiNTE2ZjZkNzA5NmUyOTVkMzVkNTE4NWFjYTNmY2EwZTdjZDhjMThjYTIxMjcxYjVkZTk1NjFhNjdiM2U5ZjQyYSIsInRhZyI6IiJ9', 1, 'M. ABDUL SYAMSUL IMAN', 'm4@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:50', 36, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:50', '2026-08-21 05:00:50', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (68, 'muchamad', '$2y$12$HgBwmggbOczwWSDmHdU6fOdyOdPIHtl2bRjmIZPzzc47AD.DjS/Qi', 'eyJpdiI6Im84VFRGdHowYWxLMFQ1aXo1SVYydXc9PSIsInZhbHVlIjoiZWxhdlR3cEt1WXd3VVp2NTd6d1d4Zz09IiwibWFjIjoiNjM5Zjk4MDFmMzVmYzg3OGQ4ODFlNDVmMDhkZTlhZDIwOWVlYTczMmU2N2FjNDQ1NWJmYWFkZDZjZmFmOGRmNiIsInRhZyI6IiJ9', 1, 'MUCHAMAD TIS\'AH AKBAR', 'muchamad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:51', 37, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:51', '2026-08-21 05:00:51', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (69, 'teguh', '$2y$12$GDYL.W3k2CekxWzsCdsaKeXjHmqyULqOeoBqMEWiIKdILPxFh5wsW', 'eyJpdiI6InJGOWVkRUlEbkRxNWpkdHZSN2FYVEE9PSIsInZhbHVlIjoiaHBYWWpKWW0wdlF2d2hoZThOZFcwZz09IiwibWFjIjoiMmI2MWVkNWZiZjQ0ZTAyMmMzNzE5OTJlZDg5ZmMxOWU0YTJkMWZmNTA3YmNkMjRhZmU0MGUwN2M0OTBhNTliNCIsInRhZyI6IiJ9', 1, 'TEGUH IMAM RUDI ARIF MANDIRA', 'teguh@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:51', 38, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:51', '2026-08-21 05:00:51', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (70, 'aji', '$2y$12$9kukY1mcQuNZ0zWzL2s9guskkZFgVoyw.UcxflW4.eijsEDdIC2qO', 'eyJpdiI6InZ6RUxuYjdzbVgrSjNldXBOdGpUdWc9PSIsInZhbHVlIjoiN2M3RmJEZ1YzK3FEMTZSY0d6cHpqUT09IiwibWFjIjoiOTQ3Yzg0MzFiNTQxZDZhY2YxYTNhMzI3OTRmOWYzNGU0YThhY2Y5YTU5YmMzYjZhOTNkZDUwZGExNzhlNDVkYyIsInRhZyI6IiJ9', 1, 'AJI SETIAWAN', 'aji@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:51', 39, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:51', '2026-08-21 05:00:51', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (71, 'muchamad2', '$2y$12$LH8ysGWMwFJo4Y9wmeSuEOETmk5a0L6L/0YkG98NIMYCBAe8IsQES', 'eyJpdiI6IkhMM0NVT3ZtVDdtanp2a0pBMmVDNWc9PSIsInZhbHVlIjoidFllTVRqY2FDYUpBUFhBUFY2ZHZ1dz09IiwibWFjIjoiOTZiYmI4NzU4Mzg3YWE1OTYxZjk0Y2I0NTE1ZjRhNzE1MGIxMWYxZWJjNTZhMjMzZDBjODZiYzRiYjU0ODg5YSIsInRhZyI6IiJ9', 1, 'MUCHAMAD ZAENURI', 'muchamad2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:51', 40, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:51', '2026-08-21 05:00:51', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (72, 'robby', '$2y$12$z.3pBFEsnHYdO1mTEK3G1ui/OnGl.BxuUur4nN3BF1MU3IUJVHbkK', 'eyJpdiI6IlJJQVhSYUt5NnFTb01ZUFRobHVXdmc9PSIsInZhbHVlIjoiUWZ0TDhUZ1dtZkZRbjc4TkEyOWVzZz09IiwibWFjIjoiZDQwMGRiODIwZDNhNTM0YjJlYzExY2NiYzE0ZDQ4MGRjNjFjMzNiMzFiODU4OTM4ZjZhMDMwNTkyMjFiMTMxNyIsInRhZyI6IiJ9', 1, 'ROBBY NUR MAULANA', 'robby@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:51', 41, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:51', '2026-08-21 05:00:51', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (73, 'aldi', '$2y$12$9yruTUKok9erw2qOSUN1qOyv7n/a.Wv9tWzBIsrllPGoRQZc/NBPO', 'eyJpdiI6Ik5WK3FUQU1laURKYXZRYm9WdEdaYVE9PSIsInZhbHVlIjoiR2xSWE9GT09WQkpFOXNDOUt6YkU3Zz09IiwibWFjIjoiNDhkZjQ1YWM3NGM5ZDRmYTE5OWRhN2Q5M2NlZDgwY2Y1YjM5N2I3M2YzMzcwNWMwYzg3ODkwODRmMTk3NzdlMiIsInRhZyI6IiJ9', 1, 'ALDI ARDIANSYAH', 'aldi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:52', 42, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:52', '2026-08-21 05:00:52', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (74, 'firdianzah', '$2y$12$SysWXLr08D03H2H8DI5o1.g1PVe66M4Xg9CIW3Ma9/6fj1jPwOCtu', 'eyJpdiI6IkZoVStOZmpIdXlTelRnZy93Z0VlZXc9PSIsInZhbHVlIjoiRVUvdUtaTGVHMTZuWUsrbHp6UEQrZz09IiwibWFjIjoiNWYxMDczNTFiN2ZjMjIzZDYwOTEyMThhNzE4OGVhY2I4MWQ1OGU0NDYyMjY2YTIzOTY0MTU3ZTQyZjNhY2VkNCIsInRhZyI6IiJ9', 1, 'FIRDIANZAH', 'firdianzah@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:52', 43, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:52', '2026-08-21 05:00:52', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (75, 'agi', '$2y$12$4eSxRnCSZ3279pyPS9ONeOUxtIjIFxrn4fF6PN8u0X8uY/DPy.Rwa', 'eyJpdiI6IkViM3p5Y1R1R0tTYTB4alRCTmRFTnc9PSIsInZhbHVlIjoibWpGYTJRbGZ0WVplTytxYnRZSkk1dz09IiwibWFjIjoiODg0NzA2YzExN2Y1YmJiY2MzODhhMTZiMWFiN2I1MDQ2YjI0ZTNjMWVlYTdhOTBmZGRjZTcxOGRjNGI2ZmRjMSIsInRhZyI6IiJ9', 1, 'AGI PURNOMO', 'agi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:52', 44, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:52', '2026-08-21 05:00:52', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (76, 'iqbal', '$2y$12$VETABBPbO6b55z9J/K8kYu9vIToAGVNnNyX9m8kwiB/rOO1oP1TpK', 'eyJpdiI6ImM2clAvZjc1aWxoek9wb3J6RFM2OEE9PSIsInZhbHVlIjoiT041QkpGWnRZM0YraENCSE9HblBlUT09IiwibWFjIjoiMGZjYWYxM2Q5MDI0MzA1MGMxMzJhZTJhZjUxYjMxNjY4YzI3YzA5NTU3ZWMwODA4NjU5MjcxNjFmYmVjMjBlZSIsInRhZyI6IiJ9', 1, 'IQBAL A\'LA ASHYIFA', 'iqbal@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:52', 45, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:52', '2026-08-21 05:00:52', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (77, 'rayhan', '$2y$12$3eOPq.5YsyMx9vhW9U85B.d1yisfYltypoI1MckxzTf7EV272rKJq', 'eyJpdiI6ImNjU0d2YklyNEl1OU1ieXFNYzRvemc9PSIsInZhbHVlIjoidzNWM1NwWmNpUkFwYy9ReWMxbGVOQT09IiwibWFjIjoiN2I2MDBjMjRhMjljYjBjNzkyMDI3YjAyZWRlZjFhNmE0NmVmZDEzNTc1YjExYmQ5NTkxNjdiNjgyMTdhZmEzYSIsInRhZyI6IiJ9', 1, 'RAYHAN MAULANA CHRISNA PUTRA', 'rayhan@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:52', 46, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:52', '2026-08-21 05:00:52', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (78, 'sulistiawati', '$2y$12$r0rWjsyXJB4MS97jugbvLOtM/IE1cVP981BN5cV.X6it7pQ48q0ii', 'eyJpdiI6IkNlUGE5OHlHaEN4cGFvR2JvaTZEcWc9PSIsInZhbHVlIjoiK0hJb3E5eC9TUjJ2NWZKYXFzNWpQUT09IiwibWFjIjoiZGVmN2U0ZWY1MTg2ZTAyZmNlYTY5ZjAxNjIwMDAxNTU2MWU3Y2U4YjJkODI2YzA3MzUzYTQ2NmUxODkyNjk4NSIsInRhZyI6IiJ9', 1, 'SULISTIAWATI', 'sulistiawati@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:53', 47, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:53', '2026-08-21 05:00:53', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (79, 'sarirotul', '$2y$12$1fdE/ip4rUwnQnpFwOsocuhSRe8OI70N.YvWb23w8jpYyVdjzN4v.', 'eyJpdiI6IlprbkI4MGJtTUxLM29nb1kwMDJxOXc9PSIsInZhbHVlIjoiTDBaL1RuUS9TZHBBMmdJSm9JbUJ5dz09IiwibWFjIjoiZjk4ZjU1MjAzNDA2OGQ0NmE3MzQ0NzM2MGFjNDkzODNmZjViN2UwZmU3ODJkODM2OTEzNzQwZTc3NTVkYTg0NSIsInRhZyI6IiJ9', 1, 'SARIROTUL LUTFIAH', 'sarirotul@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:53', 48, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:53', '2026-08-21 05:00:53', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (80, 'ika', '$2y$12$ixk0dpOUCL1d0S7jS2EYbOYRBgjTiVOs.Wk.SLKBhV2d34wgEUUL.', 'eyJpdiI6IkkwTGVrSUppZDdLRFVTUlgrWWNRamc9PSIsInZhbHVlIjoia0VVWmdoSzU4MnB5dW9mTU5lODRiUT09IiwibWFjIjoiNTk5ZGM3MjFhNzNhZmM1YjIzYjU3NjgzMWI5NzU3NDQ2ZTcyYzU3OWQ1YzRjYzI3NmY2NWFmOThlMWRkNzNmMiIsInRhZyI6IiJ9', 1, 'IKA MUNAWAROH', 'ika@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:53', 49, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:53', '2026-08-21 05:00:53', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (81, 'dina', '$2y$12$pNc/WvIyMR6zXajUHKSO7evZeyJ6K/vtr7r35eWzpSqnk7OZ95dUi', 'eyJpdiI6IlgxVmxhdDBoRHBNUWFUU1IyNVU5ZHc9PSIsInZhbHVlIjoiMUlUL3dJT3l1NDdWWnMrVE43TzlrZz09IiwibWFjIjoiZDU0NWVmM2E3NmM5MTQ2NDZjMTgxMmY1Zjk5OWZjMjI0ZDM3NzViNWQxOWNjN2ZkMTdmNmU1ODg4ODFhZDA3MCIsInRhZyI6IiJ9', 1, 'DINA RATNASARI', 'dina@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:53', 50, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:53', '2026-08-21 05:00:53', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (82, 'kus', '$2y$12$6INvpeUwcYlhdm67VYHKje1ZotftWURpwk6iFklf2dtf0YUhZ0sX6', 'eyJpdiI6IkNDNjZqYTcraFhWVElKOW9jdmJnVmc9PSIsInZhbHVlIjoiam1HaExpUFNCTzN1ZWJPZnJ4Uk1kUT09IiwibWFjIjoiMmMxYWQ5NTk1ZmZlNGE1OWI0OWFhZjA5ZDRlZDhhOTczMDhhM2E3YTk4Mzk3ZWY4OTExMzY1ZTMxMTUyYjNjYSIsInRhZyI6IiJ9', 1, 'KUS SULISTIANI', 'kus@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:54', 51, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:54', '2026-08-21 05:00:54', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (83, 'tutiari', '$2y$12$Rrbal2cLdox1Qpg2C5tcTOsC.vzbMy9SoZkSdvO0JDljiUa2ioNma', 'eyJpdiI6IkhWbzF0djJZQStuQXFydFQyUkpJQmc9PSIsInZhbHVlIjoiMFNjUVlXektIdk53UXJwb3RnVDRNZz09IiwibWFjIjoiOGIzN2Q2NTZiN2RkMDE4MzRlNTIyMzVlNjg5ODE1Y2NhODg5NmU2NDYwMTY4NzIxMmI3MWI4NjUyODI1Y2Y4NSIsInRhZyI6IiJ9', 1, 'TUTIARI', 'tutiari@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:54', 52, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:54', '2026-08-21 05:00:54', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (84, 'linda', '$2y$12$1EUME6MOf5ibe5JClPN8RuYvpV5XGkoQyPNbtAFOEuq5AQUYLPViu', 'eyJpdiI6IkZEQTJTclRySG1wcmFWYWtvQkpwbmc9PSIsInZhbHVlIjoiK3dQdVJpWkN6YU9POCt6eTA0UDkxQT09IiwibWFjIjoiZGJjNjc5OGMyYzdhYmFhNTdhMmNhNjJhOTZmOTQ2YTM3NzcwODRiYzEzMGIyYWYxNmNlYjk3ZDA1M2I1NDg2MCIsInRhZyI6IiJ9', 1, 'LINDA WULANDARI', 'linda@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:54', 53, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:54', '2026-08-21 05:00:54', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (85, 'yulisda', '$2y$12$o/v3mBQheqONFu0FkDM..uXwd8x6.I/CYeWNK1JRUWABpR0tg/wYa', 'eyJpdiI6Iklnb0ZIdEN3cUxwd1RPbktmWGlKK2c9PSIsInZhbHVlIjoidTZtL01sMlRlc0tVaGxGY2NqNWNOQT09IiwibWFjIjoiNmViZTdhMTFjZGQ2MDZkNmJiNzFhMDlmMTAyMTNjOTI0N2MxNTk1ZTA1YjgwYmNhZjg0ZDJjOTZlM2I0N2VhYSIsInRhZyI6IiJ9', 1, 'YULISDA DIAN', 'yulisda@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:54', 54, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:54', '2026-08-21 05:00:54', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (86, 'murni', '$2y$12$lE7NTjR0XDXgbVUdTrIkH.S2Vje9K.wsXGwwLqeiBlnwts2kcNNcS', 'eyJpdiI6IlhmQlJqOWgxcWx4eU54blJwWWJIQWc9PSIsInZhbHVlIjoiVFNNNUZaZGo5RzFmM21xQmMzVVVyUT09IiwibWFjIjoiNmEwNzVjN2FmMmU5ODgxNWUyNGI3MDdlZTUzYjY0MzFjYzcyYTY1YjRiMDQ2MjBkZGViNTgyMDY5NTFjM2NmMCIsInRhZyI6IiJ9', 1, 'MURNI', 'murni@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:54', 55, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:54', '2026-08-21 05:00:54', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (87, 'sri', '$2y$12$UOkWCPNW4xduZWcSygFIyuuWyng4G0DAykTUk2x0LJpFkp/.2kgpa', 'eyJpdiI6InN0VHFrKzN0bGxHcTlKTlAycVhhbWc9PSIsInZhbHVlIjoiS2JTQjJlOVk5RDJqeTZESTZEMnhlQT09IiwibWFjIjoiMGVjYjUwZTM5NzIyNjM0NTMyMGRiZmZiYzljMTZiNDVjOGNjNjhiOWYxNDNlNmJmYTg1NTUwNzYxNjJlODcwNiIsInRhZyI6IiJ9', 1, 'SRI WAHYUNI', 'sri@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:55', 56, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:55', '2026-08-21 05:00:55', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (88, 'defy', '$2y$12$0SpEYE3NcZuPA1sYkFuLruscOr2fjYQDATdfdsWKVIA0kOCUIvoMa', 'eyJpdiI6IkluOWRsTVJ4bEVCcDFScmFsTkYvMVE9PSIsInZhbHVlIjoiVTEyczZac0tqYUNrNkFlWFlvaXBwUT09IiwibWFjIjoiYzVmYzNiMTEyMzE3NGMxOWI3ZDM1NDg4ZTI2YWU3ZjU3NmVkYjk3OTk3MTFjMmIyOTk5M2U5ZTZmMTI4YWUxMCIsInRhZyI6IiJ9', 1, 'DEFY WIJIASTUTIK', 'defy@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:55', 57, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:55', '2026-08-21 05:00:55', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (89, 'jumaati', '$2y$12$UsHZBQGZ8U3cve6j1sgpgu/ChJAbVCZ7Y6qAGrVRDxeIypm2rIzAi', 'eyJpdiI6ImU5bXJCZ1pIWHVKZFBRZGZ2c3p6R2c9PSIsInZhbHVlIjoiMjZleTVFOUQzWXRHdVFiZkVkS1AwQT09IiwibWFjIjoiODEzODVhOWU0OGE2MDEyY2JlMTk5MmEyNTNjN2EzZWNiN2U3YzYzZDczZGM1MTkwZjZjY2JhZTM1ZWQ3NmZjMCIsInRhZyI6IiJ9', 1, 'JUMAATI', 'jumaati@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:55', 58, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:55', '2026-08-21 05:00:55', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (90, 'sukarti', '$2y$12$u4H5/bpYnXxsDXKFYo.pW.pOWPgZN9v.oaaqrKnlRpOjMo1VpJ17m', 'eyJpdiI6IlFrRFVXdUJtdE1UdXNKT1pKaitYS1E9PSIsInZhbHVlIjoiRkVKdWJ6RWZDS0hmSGlPdGxpTHBUdz09IiwibWFjIjoiMmY1YTViOTJmZDkyNWFjZDNlNmQ3OGEzYWQ1MGE5YjRjNzE1YTJlMDk3YTY5OGVlYTE0Yzg1MTU1OWY5NzNmZiIsInRhZyI6IiJ9', 1, 'SUKARTI', 'sukarti@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:55', 59, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:55', '2026-08-21 05:00:55', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (91, 'andik', '$2y$12$CDkbTwpBbp9VilMzoqtiUexZcCLvjWS3HOL5PQPJICPyg9kiXmfwu', 'eyJpdiI6ImltSTcwOFQ3cTM5ck5oNjZSZitVUWc9PSIsInZhbHVlIjoiclNCRDVtekhKeGdaZnBTUzg5aEM5dz09IiwibWFjIjoiM2JhNDEzNDBjZDEyOGU2ZTk0Y2JmMzUxNjQ4M2E2ZTYzMWE1NDY5YmJmZDcyY2FlZTAyNTAyZjk2NDVlOGNlNCIsInRhZyI6IiJ9', 1, 'ANDIK PURWANTO', 'andik@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:56', 60, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:56', '2026-08-21 05:00:56', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (92, 'muchamad3', '$2y$12$ZUNWnK/msm6n6Zy1hBKvS.XIjEj.ur3G1qPp9PZI427/L.oL7qsei', 'eyJpdiI6Ild0dkFYUVI0YUdGSGdpUlZLRWRONGc9PSIsInZhbHVlIjoiTlFyNy9MWkc4dy9ZekM4ZGYzV0o3dz09IiwibWFjIjoiMjZiZTIzNWViYjY2NDEyZDE2ZTRkODE2MDlmY2QxOTI1OTI1OTQzZmQ1ODExZDdmODBiNjZjOTI1M2Q5MDk1NiIsInRhZyI6IiJ9', 1, 'MUCHAMAD LUKMAN FARID', 'muchamad3@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:56', 61, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:56', '2026-08-21 05:00:56', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (93, 'johan', '$2y$12$CjpbCpGOiwCRV1eXjNH4ZelmHisNUDVIi5FZTqmVFqM.stGPKHvGq', 'eyJpdiI6ImxQN3RvbzJ4VjluaHhFUkE3eDRENkE9PSIsInZhbHVlIjoiTjRtVUtwWEFQZnNHUXkrM2t2M0Qzdz09IiwibWFjIjoiMWM4NjQ5NjlkNWFiNDU5ZGM4MmNiN2JjZDc3ZjUxMmY0NzYyYjhmZDliODgxOTdlN2JiZGE4MGQ5MzkyOWQ5NSIsInRhZyI6IiJ9', 1, 'JOHAN AZZURA FEBRIANSYAH', 'johan@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:56', 62, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:56', '2026-08-21 05:00:56', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (94, 'jhohan', '$2y$12$3KRBaK5HefTfX8LhA9f.DeNLbTahuf7ALKrzL.h.4tSk6OY/mq8JG', 'eyJpdiI6IjUxb2hHR1FHOGd4NFkxZllybnpISkE9PSIsInZhbHVlIjoiV3VaUUgzV2RmZFZ0WVBFTlVDVEdldz09IiwibWFjIjoiYmQ0MTUzNWFhMjBhOGE4MWY2YjVjMmI2YjcxMzliYTY4MTU4OTc0Mjg4MzQ1Nzc0ZTc1YTI4MGI4NTM0MWI3ZiIsInRhZyI6IiJ9', 1, 'JHOHAN DWI PRASETYO', 'jhohan@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:56', 63, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:56', '2026-08-21 05:00:56', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (95, 'ika2', '$2y$12$RQGp53roGeqJ8Y3mhY.8BuMHvd2ZVLDaztby6pn8Y.bsjwkCDNFeq', 'eyJpdiI6Im9TMzlvMmlYeE5rY3lMUVFNa2ZHN3c9PSIsInZhbHVlIjoiOEdVZ1k5KzE2ZnBvRnpjMEFpSEdwQT09IiwibWFjIjoiOTYxNDA3YzNkOGJkZmY5N2Q2YzUxNTA5YjE2YzdlODIzMmMxYWE2MWY5MTMzNDMzNzc1YzUzZjI1ODdlYWZiMyIsInRhZyI6IiJ9', 1, 'IKA FADILLAH AKBAR', 'ika2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:56', 64, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:56', '2026-08-21 05:00:56', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (96, 'muchammad', '$2y$12$CTM9S.MBMz0srX1vk2yeaOM0dFuxul38.HXRg6oC1L9kT5v36jcNi', 'eyJpdiI6Im41V2R4UzVZMUU2b1V6MlNIbWNES1E9PSIsInZhbHVlIjoidXdxdE52L2U4QXNjTmNLbTJHSGJZQT09IiwibWFjIjoiYTZkYWRlODMyYTcwMDcwYTAyMDk5N2I1YmI3N2VhYjQ0NmFjYTIwMWY0M2UwMDc1NGQ5NGNiYmFkMzkxYmRkMCIsInRhZyI6IiJ9', 1, 'MUCHAMMAD SYARIFUDIN', 'muchammad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:57', 65, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:57', '2026-08-21 05:00:57', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (97, 'isarotul', '$2y$12$il.Vmzxer4reodrYFg0Lf.VuSZQyfJJy94EYtUAyPRlZUwebVilxS', 'eyJpdiI6IkRXNjhMN04xeDlFUHFVUGM3aTViSUE9PSIsInZhbHVlIjoiVmJzOXF0WHg4N1hpOU9kOTlLalQzZz09IiwibWFjIjoiOWM4ZTAzOTc4MmUxMTAxNzliZTIwNzgxZTZiMTViMzUzZDhhN2U1YTk1Nzc2NmUyZTgxYzE4NjI0ZjNkNGU4OCIsInRhZyI6IiJ9', 1, 'ISAROTUL UMA', 'isarotul@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:57', 66, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:57', '2026-08-21 05:00:57', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (98, 'risma', '$2y$12$9xXPTu8wlwgxMr0Skewzb.DArwICkiBiRZ8f2ZKQKRxbu/U26Wnw6', 'eyJpdiI6IngrLy9pVkhPQnEzYUFJZk5PZ0JkT3c9PSIsInZhbHVlIjoiY0htWUFYTWVGMmdhM0l4VW1YaHVEdz09IiwibWFjIjoiYmVkNzVlZTdiNDQ0OWIwYmEwZTZjNjA1NGVmNDc4YjlmODU0NDcyNGI1ZGEzNDllY2NlOGQzZDZlNjY1NmFmYiIsInRhZyI6IiJ9', 1, 'RISMA NOVITA PUTRI', 'risma@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:57', 67, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:57', '2026-08-21 05:00:57', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (99, 'maura', '$2y$12$Gg3ZQs5r40.GOGnwRb9RTe9rCeBWI7VqCBeW8kgIMmGoGRWrD4fCm', 'eyJpdiI6Ill1K1o0UTBscWtReVg1S25NbGZ1VUE9PSIsInZhbHVlIjoiRlR1MnBxNG9YTjZNUldEWUVFWEVzdz09IiwibWFjIjoiZGI3MGM2YjkwM2M4ZjhjODJhMzJjNzE3ZmZkZDBhMTNkNGExOGQ1MDczNjZkMDVjMTY3MjAwMzBjZjdjZmEyNyIsInRhZyI6IiJ9', 1, 'MAURA SESIL PRISILIA', 'maura@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:57', 68, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:57', '2026-08-21 05:00:57', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (100, 'desi', '$2y$12$YAU6.36YTIAGyr4aZ6DuxOmUoLKl2mgMwhhdOGP8tJGc0sWZkTMj.', 'eyJpdiI6IkpIZVFjbVFjd2dOd0E1azJSbDR2aEE9PSIsInZhbHVlIjoiTi9aTmZselBlZzk5L05qd3Q4bWhCQT09IiwibWFjIjoiM2Q1ZTA3NjBiMjc1YWUwN2FkZTVkMjhkMGE4ZGE2ODRjYWU1NTY2OWJkYjc5ZWQyZWE0MWZiZmQ2NWVlMDQxYSIsInRhZyI6IiJ9', 1, 'DESI DWI INDAH SAFITRI', 'desi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:58', 69, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:58', '2026-08-21 05:00:58', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (101, 'suyati', '$2y$12$D7oacdieSGJrkqKaQgCwfuBGhKFNCRgeLDKDwCUoIvoBXLkODrMKu', 'eyJpdiI6InNPOWdieTlMSFFZNWRYRzdOT3A1YUE9PSIsInZhbHVlIjoic0JxdW81Nno1OXZwTjlyWko2WDVrUT09IiwibWFjIjoiZmFlNmQzMTljZjNlOWZhNTM5OWMzNDJkODQ0NTZmYjZjMTFiYzYwZDMyMDU1NGM0NmYyMjliNWEwOTJjNTMyNyIsInRhZyI6IiJ9', 1, 'SUYATI', 'suyati@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:58', 70, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:58', '2026-08-21 05:00:58', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (102, 'evi', '$2y$12$Xq5w7chJ.GNq4Im35tELreV.a8HDjGRFLGSIWDi55C0CVdBxnQ8mW', 'eyJpdiI6IisxUVJ5UldzOGdJZnkwSnpWNVJXeWc9PSIsInZhbHVlIjoiLy81RXRoVW9hT0lDT0Rla3I5dXlpZz09IiwibWFjIjoiN2NkYzc2NzUzYzhmYjRlMGFlODhkNTMwNjE2YWM3ZWQ3NjQwNGU3YWJhZDM3MjQ2NWRkZDQ5ZmRmNDU3YTQ1YSIsInRhZyI6IiJ9', 1, 'EVI KUMALASARI', 'evi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:58', 71, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:58', '2026-08-21 05:00:58', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (103, 'ilma', '$2y$12$/yGh3ML/TlFBKzzGtp852erfH1HLWBXsNc8TSCSmX0eagbWBV92Um', 'eyJpdiI6IjdwcG9kb3hWRm43Vk1MQjVkYmdvZlE9PSIsInZhbHVlIjoiTEVkSlJQQ2JwUkdxaWdqRlI0aUJuQT09IiwibWFjIjoiNzhlN2UxN2Y1NTBiODliOWQzZjMzYzQ2ZGEwMmEyNjY2MTA0OTgxNjVjNDRmNTRmMzg3MTI2M2JjYThhYjc0ZiIsInRhZyI6IiJ9', 1, 'ILMA NURMASHITA', 'ilma@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:58', 72, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:58', '2026-08-21 05:00:58', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (104, 'sania2', '$2y$12$KJrZ6WfZfkMiN/N3quzj/eJSdKr0kAGd.kucX/83uUXRS5EYkj/Fy', 'eyJpdiI6Inh5bDN5Nld3VnN5bXBkMVgxSk9UMkE9PSIsInZhbHVlIjoidURjTlJwODlpb1V0eUpFUGJRa3k4dz09IiwibWFjIjoiOTlmMTczODE4MTdlMjhkNTQyNjRlZGFmZWZkODE4ZGU3YmMzOGVmNWEzN2QwOGRkMmVhNzdlOGY4YmU4OTcxZSIsInRhZyI6IiJ9', 1, 'SANIA INDAR PARAWANGSA', 'sania2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:59', 73, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:59', '2026-08-21 05:00:59', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (105, 'maria', '$2y$12$lHCgTgB//xn54o01BAK/oukKXKnEnluAu3QT060uN5I.Z.M9XzC72', 'eyJpdiI6Ik9xNHFyZStMRGJwblBIVG1FOGhFOUE9PSIsInZhbHVlIjoiZ1F3NU00dGtSVTYwMHQ5WGhCRUVRdz09IiwibWFjIjoiZjdlN2I3M2Y0ZDJjY2MwYmZiMTU1YWNmMWZhZTYyZDc0Zjg5NzQ4ZmRhZjM1MTgwMWJjMjRkOTkxN2Q0NTdiMyIsInRhZyI6IiJ9', 1, 'MARIA ULFA', 'maria@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:59', 74, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:59', '2026-08-21 05:00:59', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (106, 'nur', '$2y$12$BozV4aBhaZ7EzDz7f0c4s.Plr.lSH0GMsUZYSeAUfeGMyoiF1wFES', 'eyJpdiI6IjVuL3lBSFJWL0E0UXBQeUtOa1pyd3c9PSIsInZhbHVlIjoiR1FsdmIvWXl1V1RFTFlmV3h6bCt1Zz09IiwibWFjIjoiMWZjNDgyZTNhNjY4ZGM2OTdhY2U4NGY1NzY4NDE4YTFkMTI0NjdkMjcwOGFhNTNiNDU1ZThlNDI0MzU1Yjk4NSIsInRhZyI6IiJ9', 1, 'NUR AZIZAH', 'nur@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:59', 75, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:59', '2026-08-21 05:00:59', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (107, 'rohmat', '$2y$12$fCgrVUCvIhqgEjl9UsWa1OZIUCf7Gyzz4HdMPQ/9n7oh2SJ/VOQAm', 'eyJpdiI6IkZXbkhLWHJHUnNCWUNxWjRUWld5Zmc9PSIsInZhbHVlIjoiSWRqQkNQaS9panVHYjV2dnpDem9JZz09IiwibWFjIjoiM2RjY2Y4ODFhNjI0OWFjZGM3ZjU0MmJkZTdkMzFkYWI2MmNhZjBlNDNjZWNlMzA2YzM0ZWUyZjBmNmIyYTk3MCIsInRhZyI6IiJ9', 1, 'ROHMAT', 'rohmat@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:59', 78, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:59', '2026-08-21 05:00:59', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (108, 'panca', '$2y$12$kHREPnd6jQ8gzu8386qDNOkk5BHACWbAMoT4WGAU1pQcswxAw2DS2', 'eyJpdiI6Inhmd1pSUXFCNEN6THFTeHV0SkYzWUE9PSIsInZhbHVlIjoiZzNzdS8wZ3lITkhKaWNtOEJEanErZz09IiwibWFjIjoiOWExMjkxNWRjNWRiYzdlMzA5ZTRhM2RkZDI1ODgxMGMyYTc3MmNiODVmYzI3ZmM3M2I3MDJkMzg5ZDQwYjBjZCIsInRhZyI6IiJ9', 1, 'PANCA INDRA LAKSMANA PUTRA', 'panca@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:00:59', 79, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:00:59', '2026-08-21 05:00:59', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (109, 'regi', '$2y$12$dDnsJN9mayjZNv3Zwb0zkucn/uNPqMXg8J8dNiwtWh3Comd0OGyCS', 'eyJpdiI6InU2bUFiNlBFL0V5UnMrZFRlN2tIOUE9PSIsInZhbHVlIjoiTzNSVWN1aExCbmw1S1ZKcCsvUlU0UT09IiwibWFjIjoiNTJiYmFlNWZjNmI3ZDU2NTBhZTk3YzlmNjkzOWVlZTE5NGJiY2YxMjlmOGVmMjEwNGQzNGU4YjRmM2ZjYTgxYSIsInRhZyI6IiJ9', 1, 'REGI SEPTA WAHYU WIDAYANA', 'regi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:00', 80, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:00', '2026-08-21 05:01:00', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (110, 'subandi', '$2y$12$XbvBvqZ.eEshu0//u6s9Cu0nZH2fU14DgWjfoYdW2BKejUu24z.AC', 'eyJpdiI6ImRCamZqM2NoSEN0dzdQT0tFZWdkMmc9PSIsInZhbHVlIjoiQkZxOFZobkgvTjdMNE1sU0N4Q3FlUT09IiwibWFjIjoiNGVhYThmN2EwYmMwMGVlNGZiYmIwYTFlNTk5MTMxNDEyMjVlMDIzZWI2NzdhMTgyMDQ4YzhiMDJmMGZhZWNiYiIsInRhZyI6IiJ9', 1, 'SUBANDI', 'subandi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:00', 81, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:00', '2026-08-21 05:01:00', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (111, 'trio', '$2y$12$WXHz7WufsikznMpGm/A0A.GvGah3.w.SZnphWn6440hiiWE8OXMOa', 'eyJpdiI6ImFiQytpNTl0VHdYTXVCaHlqVXR6aGc9PSIsInZhbHVlIjoiRHJxMzdQQy9JUkN6UEZQU1VoUURmdz09IiwibWFjIjoiY2E3YTRlNWMzMzc3OGYxNGQ2NWZlYmNjOWVjNzM1ZDQ4ODVjNjExYmZhNGYzZDZjOWQ3YmQ5MTE0NjVjNjg4OCIsInRhZyI6IiJ9', 1, 'TRIO NERMA', 'trio@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:00', 82, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:00', '2026-08-21 05:01:00', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (112, 'mohammad', '$2y$12$U4JVchANmLN9zOqOQRcIIuFeMhk0Doj6zNtUXDPUFBts2UL8Zot/C', 'eyJpdiI6InRBUGNOYmIrRzBVRktoeVBsaUhtdFE9PSIsInZhbHVlIjoiQ1JEcjNQTGw5eGM1T1JleFN1bUpQdz09IiwibWFjIjoiOTI4NmZjNzM5NTIyZDE2NDlhMjQxNjYxYTY5NTE0YmUxYTIwODEzMjU0YjZlMTA5ZjQwNmI0ODRhMWZiM2U1MSIsInRhZyI6IiJ9', 1, 'MOHAMMAD RAMADHAN ADI SAPUTRA', 'mohammad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:00', 83, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:00', '2026-08-21 05:01:00', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (113, 'ahmad2', '$2y$12$euFfvnzkg79DYbPPWC2cw./xIeqzo3kTaYccErC3O2Jushw68NInO', 'eyJpdiI6IlhHeEFXWkNSOXRGRys1NzVKSXU5ZlE9PSIsInZhbHVlIjoiSWd4N1B6RDdiOUM2cFpJOVczZ1ZZZz09IiwibWFjIjoiNmMzMjdkOWRjYWI1ZTM4MWU4NTBhM2JlNTgyYmFkMDQ1OGEzMThlZGEzZTMzYmVlZWE5Y2E1MmE4MjhiN2QzZiIsInRhZyI6IiJ9', 1, 'AHMAD DWI MAULANA', 'ahmad2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:01', 84, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:01', '2026-08-21 05:01:01', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (114, 'agung', '$2y$12$mNZyBPSXwkA8EuJF2GN9xuf3ziCUU8jiy55CsikWCD6zQJCMHTLbi', 'eyJpdiI6InF1YmU2a0h6MVhVbVhtMVJXeWxTMEE9PSIsInZhbHVlIjoiMnNSMGVBODVNOXZpTC8yQ0w1TGVLZz09IiwibWFjIjoiN2Q4Y2JlNjJmYWNkNDEzNzQzZmNlYWI5ZjQzZDkyZTVmZGJjMzBhM2IyMDFiN2MyODFhODNlNTg1OTJmNjhiMSIsInRhZyI6IiJ9', 1, 'AGUNG DWI JAYANA', 'agung@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:01', 85, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:01', '2026-08-21 05:01:01', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (115, 'salmah', '$2y$12$dSKrZxwbw6TOB1O5IlBCCuVrNCbHMTD1xG10R9sisHdhDYrSIQfye', 'eyJpdiI6ImhZekM2UHB1emM5aUIzTm1oeDlxbFE9PSIsInZhbHVlIjoiZHRhanVxUG9CWFB0aTNIT0VvWmN3QT09IiwibWFjIjoiN2ZmODZmOGQ3MzYzYTljZThiNjU4MzNkNGZjYzY3NzA2NzRjM2JlMTZlYTNhOWUyMGYxNzFkMGUzNWU2MmQyZiIsInRhZyI6IiJ9', 1, 'SALMAH', 'salmah@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:01', 86, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:01', '2026-08-21 05:01:01', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (116, 'andreas', '$2y$12$5bYEKZ0E562ffksUcHWijuZjz2VJ9KzIhnDaQc.oEnJwvd0XXsFn.', 'eyJpdiI6IkdQQm9ySFluaWRvejNNR3MxMjlUeFE9PSIsInZhbHVlIjoiMFRaRU4xSlNEMGtseE5yN1Zodk0rZz09IiwibWFjIjoiZDY3NTcyYTVjOGRjZmU0OGE1ODY1MGQwYzYzYTExZjMwMjI2NzBmM2M1NTBlYjczM2EyYWUzYTc3OTIxMzIxZSIsInRhZyI6IiJ9', 1, 'ANDREAS HERLAMBANG', 'andreas@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:01', 87, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:01', '2026-08-21 05:01:01', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (117, 'imam', '$2y$12$l2tD3PeyQQMl.tpjskpCbugE8TJVxXb6X/vrrCnT1LYG8tZ.lWoD6', 'eyJpdiI6InlCYzlKbTZPL0Nxb1VWUHhLaGozTFE9PSIsInZhbHVlIjoiZmM0dkN5V3RNNkVtZUNnT1VWVndWZz09IiwibWFjIjoiMWJlMWI5ZGI0NGJmNDYyZmZkNjFlNmY3NjU4MjU1MTQyYmFlYzIwZmI5MmJkYjZkYWZhNzAyNmNmOWYxMTdlNSIsInRhZyI6IiJ9', 1, 'IMAM SUKOLEK', 'imam@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:01', 88, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:01', '2026-08-21 05:01:01', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (118, 'lukman', '$2y$12$xlK/LCYAS5QB5PycB4FndOt7ama85zuizJVRtEzKuh/Nv1xUW6bhC', 'eyJpdiI6ImF6Zml1YS9GTlZNYWJtV0JodGlzSHc9PSIsInZhbHVlIjoiK0tGb0FCejZFV2l1RHJSV29Bc3BBQT09IiwibWFjIjoiMGJmZGNiMTAxYzJiNDJlNDkwNDM5NGJiOWRhYTUzNWQ4NThhYWI0YjQyZmY2OTdiYTQ1YWZkY2UzMmJiNWM5MyIsInRhZyI6IiJ9', 1, 'LUKMAN FIRMANSYAH', 'lukman@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:02', 89, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:02', '2026-08-21 05:01:02', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (119, 'm5', '$2y$12$uTMvUyIVrkUPFD1PNWV.duyH.k8T2jvS0SjRh8.cj7JeU5HVSyQJG', 'eyJpdiI6ImthMUxRL3N6SElOME9VK3VPbVppdGc9PSIsInZhbHVlIjoiK2VtU0l6OC83ejJCM2QwVDVwUWRvUT09IiwibWFjIjoiY2U0NDJkZThlODVjNzk5YjZkMmUxMzcwMzI4ZWFkNGRhZTRmNzYzMjVkZGRkMjVkNDFmZWI0YWIyYTE1YzM2NSIsInRhZyI6IiJ9', 1, 'M. SE\'IYA ARDIANTO', 'm5@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:02', 90, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:02', '2026-08-21 05:01:02', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (120, 'arif3', '$2y$12$aMQ4A7wB0BECnOzLzIxGsuedHTCfRMvddGtqk5hvqXWyEFKEYA.be', 'eyJpdiI6Ikp2ZGFMTkd4SnNzaWpoSjRRbkVLVFE9PSIsInZhbHVlIjoiaW4vSFc2cTEzVHdzUzRid3huYlVwQT09IiwibWFjIjoiYTY0MWE1ZjI1YjQ2NjRkNDVmZWVhOTUxYzkzMzY3NmMxM2E3MmRkOTgzODM0YjQ5YmY1YjJmMTNlODAzYzQ3YyIsInRhZyI6IiJ9', 1, 'ARIF MUHARTO', 'arif3@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:02', 91, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:02', '2026-08-21 05:01:02', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (121, 'ardha', '$2y$12$JDFVrYx3lht5HADTeoVUWOhnH.f.yWVGYB1Cvym0PeTiTiZ54wgUG', 'eyJpdiI6IjRiakxUejFuait3WW43MjdUbVZ3RWc9PSIsInZhbHVlIjoicldRMUxHOE45U0I2cHNKZk03OXBsdz09IiwibWFjIjoiMTk3MWVhYWQyNDhkMTA4M2UzZDcyODEwNGM3NGJlNWIxNDg5ODJhNWI2ZmY4YTE2MzhlZGQ0ODIyMTI3NWNhYyIsInRhZyI6IiJ9', 1, 'ARDHA FEBRIANSYAH', 'ardha@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:02', 92, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:02', '2026-08-21 05:01:02', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (122, 'muhamad2', '$2y$12$AbdDalXrDDRXHLe5M7c.suq4CBv.rUWunBnyt/LL7sXllJIj2lYrW', 'eyJpdiI6IkZMQk9FVjI3cnJqbU91bWZ5ZXJKekE9PSIsInZhbHVlIjoibmg3TEd6KzlwRHgzdEkzcG9LajNOUT09IiwibWFjIjoiNWQ4YzU1OTg2NTRlZDQzN2VhNTBlZDNiMTZkNmQ0ZWI4ZGRjOWU1ZmM3YzI2YWIwOTFkNmNlZjgzYjg5ZmNkYiIsInRhZyI6IiJ9', 1, 'MUHAMAD BANDUNG NUGROHO', 'muhamad2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:03', 93, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:03', '2026-08-21 05:01:03', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (123, 'mochammad', '$2y$12$P3KBtZLteUtisECGqI.Fceiwp2Igvj5lM7a7hRez4kVFinq8AT9mS', 'eyJpdiI6InptRFFiTG1uNU9MSnRPYTNJMUt0MFE9PSIsInZhbHVlIjoiMGtRYnlHZ3M4WUp2eG5YbElrTHdMUT09IiwibWFjIjoiZTdjMTZjMzdhNmFiMmViMDI3YmU5YWNiYTBjMWE5YjIxMWIwOWI3NDFmMDFhY2Y4OTc0MWM2YzJmMTQwODFjOSIsInRhZyI6IiJ9', 1, 'MOCHAMMAD VIRGA REXZY', 'mochammad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:03', 94, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:03', '2026-08-21 05:01:03', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (124, 'rizki', '$2y$12$evV5d2ED2a/4Ew2IsoZ7t.z8YwoDdqzC3tWguJUqSTaNkbKzWXf2C', 'eyJpdiI6IlBtSDh6Y2QycjhlU0RqWXZsQVF6NkE9PSIsInZhbHVlIjoiSWpkWWloMFQxd0J1NlJYazRxeW42UT09IiwibWFjIjoiNWQ3MTE3MTlmMzQyOTlhMjllMjA1M2FmOGZjMTZlNWMwZWQ0MjE4Zjk1MTU4Mzk3NzFjYzAyYWEyYTE4Zjk3MCIsInRhZyI6IiJ9', 1, 'RIZKI NUR HIDAYAT', 'rizki@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:03', 95, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:03', '2026-08-21 05:01:03', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (125, 'mochammad2', '$2y$12$5HzCHkN//ewfMUYLLKfit.1HWuj7AVlA0U.9NSnveRih04t/1YbDq', 'eyJpdiI6IjdyWDNFUHBZODR3K0hnelZnaHdScEE9PSIsInZhbHVlIjoiSDhnOHlDYk0ySzRDMkppRWZwWE03QT09IiwibWFjIjoiMjUwOWM1OGMxMjU5YTAyM2M4MTJkMDc4YTRiNjZmZmIxYzcxNTBkZTJkOWE3YzdmMTlkMGZjNTcyZjJkOWRlNSIsInRhZyI6IiJ9', 1, 'MOCHAMMAD ADE WARDANA', 'mochammad2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:03', 96, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:03', '2026-08-21 05:01:03', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (126, 'm6', '$2y$12$piAioYknZY1ibUUbYjPBMOr6W5FsFvy5mqtVQtVwecZX9MtquH9S2', 'eyJpdiI6IllQNUZrN1c0aXJlcmxEZ3NCUnN2b2c9PSIsInZhbHVlIjoiLytDU1N4d2hIR21OVFdPMTlnczhJZz09IiwibWFjIjoiNGNhZTBjNWNiYTYzZjIwZmJlN2MzOWQ5ZGQ5OWJlNzc5YmQ3ZDMzZTcxNWQ5ZjhhYjkwMGQyMjk0OWE1NmI1MyIsInRhZyI6IiJ9', 1, 'M. IQBAL AGO', 'm6@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:04', 97, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:04', '2026-08-21 05:01:04', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (127, 'supriati', '$2y$12$7.H0JXHp0nooKNvXmvTHxONSuUcmBLbRQMFYpAFFze1cm.zUMmWc6', 'eyJpdiI6Img0TXpnVGJKWSszcnNZUmN0YnV4Unc9PSIsInZhbHVlIjoiWU9JV0kwTHVoOVhvUE9xcXRmK1hBUT09IiwibWFjIjoiMzRlYmM2NjUxNTY5MmZhMzNhZmNiMzdiOTE1ZGQ1ODdlNmNmOGRjNGU1YzU2MzA0ZTVmOTJmYzViZmIyN2NhYSIsInRhZyI6IiJ9', 1, 'SUPRIATI', 'supriati@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:04', 98, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:04', '2026-08-21 05:01:04', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (128, 'sandi', '$2y$12$CYrjWO2lGZhc0Ukco6IJIOCsE2HhNjthU4ZLcz4JBiWvvbGu2D1JK', 'eyJpdiI6ImM0ZmRvNElac3Z1T25wMitXMzVlWFE9PSIsInZhbHVlIjoiNzNQeTZtdlkrbU1KR2htMitMMVBSZz09IiwibWFjIjoiMzQ2ZWZhMDk4ZmY5NWZmZTdkNjlhZGNmNzhhNDBhMWVlNTdmMWY2ODY0OWQ1YjYwNjhmYjkwZjliNGM1YjAzNSIsInRhZyI6IiJ9', 1, 'SANDI ARIANTO', 'sandi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:04', 99, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:04', '2026-08-21 05:01:04', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (129, 'm7', '$2y$12$fQ0yJuq22TJlzOtC5K8G7.8MaAJiB0PtbZhCcXA9pjzT9tGYf5gAS', 'eyJpdiI6IkxjVmd2cXRSckZrbEVia2QvSjBFb0E9PSIsInZhbHVlIjoiY0hUZlpYM0RvcCtBNU8vVmVqUEVvUT09IiwibWFjIjoiOTc0ZjlmMjk5ODNmYWFhNDI4MzViZDZjZDBkNDQ0Yzc5ZGEzYjgwY2E2MWJjOWQzNTkyODVjZmFjNGYyM2YwMiIsInRhZyI6IiJ9', 1, 'M. YOGA PRATAMA', 'm7@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:04', 100, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:04', '2026-08-21 05:01:04', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (130, 'm8', '$2y$12$eXSPVlI1MEIwzJUbmMPHPOa5R8v1xq2DstJqpIqhqu49OHSUIfU6u', 'eyJpdiI6IlF6eXlUdmlNeUovL3BXL0VJQWNIK1E9PSIsInZhbHVlIjoiSzJWcVppTG1LdUtEbW1hUktWU3hmQT09IiwibWFjIjoiNTc0YmNkNDA2Y2JiYmIzMjUwOTQzZGI1ZWExZTc0MGY2ZDNmOGM1ZDU0NzNhNDEzOTc2NDZkZDJjMTVhMDhhOCIsInRhZyI6IiJ9', 1, 'M. GHOVRIELLAH', 'm8@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:04', 101, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:04', '2026-08-21 05:01:04', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (131, 'm9', '$2y$12$9mGOXpe67.MDg1TMDyWxaOXFEdqLhVPk59CRE.SnctgR9KbFqEpWq', 'eyJpdiI6IlovVDcvd2V5RS8ySHQxdGMydjI3c3c9PSIsInZhbHVlIjoicW9PNlJkczVTRzluajRVdndaSGtwUT09IiwibWFjIjoiOWVkMGRmYWMzY2UyNTRiZjM4MDZiODRhZGZmOTIwNjRhZmFlOWMwOGEyOTY4MDMyOTc0ZTc1MDQyNzEyYzJiNiIsInRhZyI6IiJ9', 1, 'M. RIVAL', 'm9@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:05', 102, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:05', '2026-08-21 05:01:05', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (132, 'akhmad', '$2y$12$/Ar1aa/tNQepCwCXezWSUe0LbRFGHzL4RDpKjKHYBo0tPBjdAfTaW', 'eyJpdiI6IlJOd2ZkVDJqNXJPa1UxWkpCcTdWMHc9PSIsInZhbHVlIjoiL2dlUk9HVkQ3UkdNN2pEd3JMcmhmZz09IiwibWFjIjoiYzFmYjU1MWQzMWFmNjk4M2MzZjRjMmE0MGY3ZmVlZmI0NjVjZDljYjk1MmFlODgzMTY5NmY4ZTU2YTcyNjRhMyIsInRhZyI6IiJ9', 1, 'AKHMAD REHAN ABDILLAH', 'akhmad@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:05', 103, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:05', '2026-08-21 05:01:05', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (133, 'm10', '$2y$12$itJZbBNLpV/I8ic9tQZpkukYo/SvBtQyzTnNjOpfnnSUHtqAL8GrK', 'eyJpdiI6IndSMktqemVqNC95dHgyMnZEWEJPSXc9PSIsInZhbHVlIjoiaDRDQXpTOTFPL1hmVW0xMm53amFQZz09IiwibWFjIjoiZDVhYjc1MjFkNTZkY2U2YWFkMjg1OWQ0MTEwYjYwNWQ0YTllZWVjODNlOTRmYjFhYjAyNzg4OWQ4YjRmNmQ1OSIsInRhZyI6IiJ9', 1, 'M. REFAN MAULANA', 'm10@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:05', 104, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:05', '2026-08-21 05:01:05', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (134, 'ade', '$2y$12$6rZnyYb9CLG6jJB9VtKIZuG8awknBXWe5egF8gOspDuNSsBRIvRGi', 'eyJpdiI6IkM2T3pJc3VlL1RzaVNORFp0cGxobEE9PSIsInZhbHVlIjoiU1FZR21IMXlLcDY5dkkrWlE2Z0Jzdz09IiwibWFjIjoiOWExYTRiNTBlZjJkMjA0MzI1OWEzYjYwYzg5YzNjMjI4NjU2ZTA5YzAyMjkzYTdiMmE4OGUzMDM3ZTBlNTI3NiIsInRhZyI6IiJ9', 1, 'ADE MAHENDRA', 'ade@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:05', 105, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:05', '2026-08-21 05:01:05', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (135, 'raden', '$2y$12$siPwqulE1atfs0o1rbTNDetdg6lhjp13Aeowyex40X6zm9GTvAFv6', 'eyJpdiI6IkI4b0hOZkxxdUF4TTNCaUczRlQvU0E9PSIsInZhbHVlIjoiM3BXa3JkZENFWC83OThmd29LclNiQT09IiwibWFjIjoiZGJhMjAxODA5ZTZjMTEwMDU0MGY1OWNiYjA5M2NiMWI3NWNiODc4YjY5ODJlNzRlMTNjZDgzNWNkOTc3MGEyZiIsInRhZyI6IiJ9', 1, 'RADEN DICKY', 'raden@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:06', 106, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:06', '2026-08-21 05:01:06', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (136, 'ukkasyah', '$2y$12$4ZIdvfEHCuqQldkfeLjL5.PxY7PpeMn9y11BL29DNBtdEWUCq0Dp.', 'eyJpdiI6IkdranZVZk1VWG9TemlyUmlpd1FKRVE9PSIsInZhbHVlIjoiSlJuNU4zd1JpZXlwaVJ3cnc5WE9JZz09IiwibWFjIjoiMmI1M2I0YjM5NGM3ZWI3MzY5ZWJhM2UxYTQxMzNmMTg1N2EzYzk1MWYzZjU4ZjcyMzU1YWQzYTdhMTg4ZDNlOCIsInRhZyI6IiJ9', 1, 'UKKASYAH MASHUDI', 'ukkasyah@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:06', 107, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:06', '2026-08-21 05:01:06', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (137, 'vicky', '$2y$12$fZUUgNeTN0Cl1uTKG.pOYOcm0xbacPVukDr0KA/WSSmlBquhWtiFm', 'eyJpdiI6Ik1CVUxsQlBnWEFNMVJ4amlkZ211UHc9PSIsInZhbHVlIjoicjNqRVN5VzVlM1pwN01MUnJJeEFTUT09IiwibWFjIjoiNTkzM2Q4YmU2ZDQxNzg3Nzc3Y2M5Yjk3MDk4MzgxZWRlOTcyYWI3YTMyZjQ2NmVkNWQ5YTMwMDlmNmIyMDkwMyIsInRhZyI6IiJ9', 1, 'VICKY ADI', 'vicky@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:06', 108, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:06', '2026-08-21 05:01:06', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (138, 'dwi', '$2y$12$Dfi942/Vhro5NTuosAAMNeThyM1DVt4j3DNu6FuAc/95RNBB9lfNS', 'eyJpdiI6InFBNlhHWE00LytaZ0Q5d1lxNUNRMlE9PSIsInZhbHVlIjoielAwYUFSVmV5NGgvbWs3aVJKUDFidz09IiwibWFjIjoiZDVkZTA2ZWJkZTc4M2VlMjcwM2UyNmZkZmRkODk2OTM3YjU2N2I2Mjc3NGI2MTJmYjdhYTg0M2QzMGQyN2RlNSIsInRhZyI6IiJ9', 1, 'DWI SETIAWAN', 'dwi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:06', 109, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:06', '2026-08-21 05:01:06', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (139, 'adi', '$2y$12$YKpDrWj5K6X/5LknAjfpNeY84FUd1i/aufjL/04Rv/PMoDgil97cO', 'eyJpdiI6Im9RN1B6OVZCbWN4UklVTnU1S1N4VXc9PSIsInZhbHVlIjoialZZbzhiYTRXQXNLRkVteWVNUUx6dz09IiwibWFjIjoiZWM4MzBlYjI4NmZiMTg3ZDIyZTA4MmUyNDUzNGU1NDBlNGE3OGM2Yzc1YjkxMmYwMmVmNzdlYzA4ZDdkZGUzYiIsInRhZyI6IiJ9', 1, 'ADI PRATOMO', 'adi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:06', 113, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:06', '2026-08-21 05:01:06', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (140, 'dinul', '$2y$12$DYEqkleThVFqG5L1DhOWl.zcEiGH/lTYOMBSH2ZoN91M/FJklERZW', 'eyJpdiI6IkVaSnowN1F4ZG5RbVpCODRBQnAveVE9PSIsInZhbHVlIjoidG1RQTEvL3hIeldZQVl4akF0TXp1dz09IiwibWFjIjoiMWQyMzkyM2RmMGRkMjQ4MDAxZWVjZDhkMDhhYjhlYzU5MzdlYjhjZDYyNjU1MzFlNjQ5NTQ2MmRkZTZhMGJlYSIsInRhZyI6IiJ9', 1, 'DINUL ROCHMAN', 'dinul@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:07', 114, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:07', '2026-08-21 05:01:07', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (141, 'maulana', '$2y$12$WykGGXq1EWktzN5On0DbfuPhvk3D2wWY71NtU3KzXxg/GYikN4vOC', 'eyJpdiI6Ilh3bERvKzFRSFJuOHhWMkhmMys3WlE9PSIsInZhbHVlIjoiRFFlV09BTVZPdm5JQUhobTFCMWdSQT09IiwibWFjIjoiMzU1ZWI2NzRkNjcxMWU0MjhkZWI5MjYyYmRkZmYwNDBmZjY2Y2VlNjg3YTVjZTlkZWFmMjZkZWM4YTNkMGE5MCIsInRhZyI6IiJ9', 1, 'MAULANA MALIK IBNU AZIZ', 'maulana@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:07', 115, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:07', '2026-08-21 05:01:07', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (142, 'agus2', '$2y$12$0xQFqqajmh8k2v/ZMX60wesk.ENzwdcLcmUZUoPhl8gLPfCHWQnMS', 'eyJpdiI6IlpjMmtjT0hOY1Flemt5TTVuN083Z0E9PSIsInZhbHVlIjoiVlFxZVgvazAySUR6dlc5ak95YWd6Zz09IiwibWFjIjoiMWEyNDA1ODY1OWQyNjIyMTY2OWYyYWY0MjBmNTQ5YzI5NGE2M2YyMzM3MzJhYzJjZTFhYzFiYTIyNWQ1ODkxZiIsInRhZyI6IiJ9', 1, 'AGUS MUJIONO', 'agus2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:07', 116, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:07', '2026-08-21 05:01:07', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (143, 'wiwik2', '$2y$12$R/OmLd/2rRcSAzXSdGvZduoqd4wnmUv6ftQi4jp4knNB9ROlVrZ3.', 'eyJpdiI6InBSbCtWbFd2d1pvem1VRDVMdDNiRkE9PSIsInZhbHVlIjoiMHhMSlc3TjRydk9Fam9oTlhZUUZvdz09IiwibWFjIjoiOGVjMWIzMmQxZDBkYjUzNmUxZDBhMzY5NGFmNzVlMTY0NzQwN2M2ZmRmNjM3MzUxMjJiNzUxMjgxN2Y1YzRiNCIsInRhZyI6IiJ9', 1, 'WIWIK ARDIATNO', 'wiwik2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:07', 117, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:07', '2026-08-21 05:01:07', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (144, 'tri2', '$2y$12$GGSsG2xSqKTYNfd1nYK6iO6waXdQzrQWmL8dGVRqKM8DynVLdCurm', 'eyJpdiI6ImpCNS8waHdkZDRVZmMxY0tPQkxpT0E9PSIsInZhbHVlIjoibnpoMGoyL2gzOUxFNzNRam50NDRwdz09IiwibWFjIjoiMDliYTNhNmRhYWYyNGRiYzAxNDZkZjVkMmYyY2JjYjM5OTAyYmFiNTJjYTFmOGE0NGRlYmQ0NzdmOTM3YzE1NSIsInRhZyI6IiJ9', 1, 'TRI YUDHA IRIANTO', 'tri2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:08', 118, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:08', '2026-08-21 05:01:08', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (145, 'windayanti', '$2y$12$ew0kR8u4hq3.Foo7iA51yuRRqfAJVJp6EgibOa/soEhjGJqDQbpcW', 'eyJpdiI6IkE0MEtTSTlVUVlzV09EU2l1bXBTMUE9PSIsInZhbHVlIjoieGM2SnRnVjRVa01IR0c0U08rV215QT09IiwibWFjIjoiZWZkYWU1ZDI3OWFiMTA0ZGEzOTk3ZTgxZDgwZTRhZDllNzFjMmQ1MDRjZWQ5ZTIzMWZlYzRiZDkyNDVlZjhkNyIsInRhZyI6IiJ9', 1, 'WINDAYANTI', 'windayanti@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:08', 119, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:08', '2026-08-21 05:01:08', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (146, 'tri3', '$2y$12$hkJhhxIMmJTeVVnC.TwNOOuO6w3MGPC5PFRrMcMnIcyWkIdOFNexy', 'eyJpdiI6IjYxVTRid2FkbjZiK2xIS1ptbE5YOGc9PSIsInZhbHVlIjoiOE5DdVljVGhZZkVXVHhJVHg0WGRPdz09IiwibWFjIjoiMjIxMDYyYjUwZjJkOTlmOGZhOTJkMmI4NDFkNjQ0MWZiNWZlYmZiOGExMGQwMjgzMGI0NTI0MjdjN2ZjMDg3NSIsInRhZyI6IiJ9', 1, 'TRI SASONGKO', 'tri3@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:08', 120, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:08', '2026-08-21 05:01:08', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (147, 'yuna', '$2y$12$o0/RR8.bXW1a3ylktNdI7OgfSaGQa22YQ15UT95xU5vXpFe6D5kSe', 'eyJpdiI6IkhzN2NXREtVNEJLemJObG1tUlNnM1E9PSIsInZhbHVlIjoiU2tDM1FpN0V0UTN5SkhLdzk1bStsQT09IiwibWFjIjoiMDc3MTQ3YTY4NjE3ZmIwZWUwM2Y2MzhlZThjYmJiNTMwNjg4MDRhYmJlNjFkNjkzMDhkZDZlYzNiZmJmNmFlOCIsInRhZyI6IiJ9', 1, 'YUNA PUJI ASTUTI', 'yuna@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:08', 122, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:08', '2026-08-21 05:01:08', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (148, 'dedi', '$2y$12$DJNA391KB0skHW3y0CQb3ueGw/ZU1nRsWEJcaxrpgMAx7apwMdWuO', 'eyJpdiI6Ilp1eWNIRS9JZ0FpT2pRN3c2eUFhMXc9PSIsInZhbHVlIjoiSjh1cW5QWG1ZdUpPQWcrblFDWEdsdz09IiwibWFjIjoiYzg5YWZlYWFkMTA4ZTI1YWFiOGZjZDk5YzU0OWQ0YzA0ODliNzAzMWRiMjU3OWY0MTQxNTcwODM2MTgyY2NmNiIsInRhZyI6IiJ9', 1, 'DEDI', 'dedi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:08', 123, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:08', '2026-08-21 05:01:08', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (149, 'suhendrik', '$2y$12$uOjMHJkxrN55D2ZGQuccXOQbcQ8TNa57FiR2BFB2skQp1vY2Xc4lq', 'eyJpdiI6IktWM1U5dGFuelVvaWJmZkNaYTQvNkE9PSIsInZhbHVlIjoiMUFET2drcHRzU1ZwYlZBQjFZeUYzZz09IiwibWFjIjoiYjMyYTY2MTE2NWE2MDA3ZTU4MGJiN2IzY2MwZjgwNjZjNWFlMjI1NWY5YmE3MWUwYTIzZjg1YTY3ZGM4N2M1YyIsInRhZyI6IiJ9', 1, 'SUHENDRIK', 'suhendrik@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:09', 124, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:09', '2026-08-21 05:01:09', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (150, 'umi', '$2y$12$fQ4xapPQXpg/2H.uUwuJIeRA9BmxdEBE39To3/8Pce/cHXrhO9Lfa', 'eyJpdiI6Im5tSHU4dzJvWitTemJoZGU5eVczNGc9PSIsInZhbHVlIjoia2RhVjNPZHVTL2pHZ2RrZzA0d09KQT09IiwibWFjIjoiMzE2YmEyMDI4MDA1NDkwNDY5NGNkNjhmNGFmMWMzNjg5MjZkNjgyYmNmNDg4OWIwM2I4Nzk3MWJkMTI5MTZlMCIsInRhZyI6IiJ9', 1, 'UMI NAFIAH', 'umi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:09', 125, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:09', '2026-08-21 05:01:09', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (151, 'gunawan', '$2y$12$2ox5jvRT/mfeVMVmVY0mQeaWjeIiJJhN9ov.c1Sfv7im0Zqdj4XW.', 'eyJpdiI6ImVvRjNhTGFBTDRDeVpreXJJdE9GdHc9PSIsInZhbHVlIjoiWnJsRkZCdi9oUEZYcHYwaFR3c2V4QT09IiwibWFjIjoiNTRjN2E0ZTc3N2U0MGM5ODg0NDdlMWVmNGMzY2NjOTFkZTc4OTJkYjU3OTBlM2Q4NzdlYWJkZDI3NmM2YjE1YiIsInRhZyI6IiJ9', 1, 'GUNAWAN HENJO ALBERTO', 'gunawan@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:09', 126, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:09', '2026-08-21 05:01:09', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (152, 'eka2', '$2y$12$AUYuS6dJW.JBbaN.bxTpo.5qghMQP3m30q83PoF4Tdmq..TLVTY4.', 'eyJpdiI6Ikg5aEYyVU1FNWM1K0dsWDNyTFkrdHc9PSIsInZhbHVlIjoiazhqWTVpZ2JsS0lja1lzaDdCSkNaZz09IiwibWFjIjoiYmE4YmJhMjM0NzRhNzA1MGNmZGM4YzA4ZTg4MzJmMzFhOWViMDI2MDNkMjU5YjFhMjA4MzYzZTMyZDhmYzVlMCIsInRhZyI6IiJ9', 1, 'EKA YENI RAHAYU', 'eka2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:09', 127, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:09', '2026-08-21 05:01:09', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (153, 'deni', '$2y$12$SjVtSOF3q5cdhUrmBsZ49uVj7a1lIjWZDbGAriwgh5/tMugFcyNGW', 'eyJpdiI6IkxRSXUveU1GU0M0b0xKVEpncDR3UUE9PSIsInZhbHVlIjoiZFlHa3BXRG1temhDOHZRU2FFQVhHUT09IiwibWFjIjoiNjViMzU4Y2FiMWI1ZDU1YTM3OTg3ZjcwZGZkYzAyZDUxOGQyMTBiYjdkMTUzMTNkZTNhNDIzM2U3ZmVjOTg0ZiIsInRhZyI6IiJ9', 1, 'DENI IRA NOVITA', 'deni@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:09', 128, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:09', '2026-08-21 05:01:09', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (154, 'ahmad3', '$2y$12$aIKesu5SCmDyah3mblgkW.cE3zLHSZCigRoomXWUoa13c/qqiBG9m', 'eyJpdiI6InR3UmJSb2o5MVNySGM0VHdIdFl5eEE9PSIsInZhbHVlIjoiaXdJaTN0b0lNcDg1TDRGTTlHK05pdz09IiwibWFjIjoiYzc2MGY3YjdmNzJlM2ZjNjIwYTA1N2UwY2M2ZTVhMTc1MzMzNWI1ZDFkMTg4ZTk1YzZkM2YxZTIwMzk5ZjNjNyIsInRhZyI6IiJ9', 1, 'AHMAD FAUZI', 'ahmad3@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:10', 129, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:10', '2026-08-21 05:01:10', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (155, 'april', '$2y$12$nGf3S4Ma0FlwjXFPZKESiOXPXhad37T8zzwxiu/LvQPMysHWfiW4W', 'eyJpdiI6InZRRUhQRkRPcWZaWGo2NWVLSURoT1E9PSIsInZhbHVlIjoiYlZZa3hmVHFxQk5WTHpXS1pJOE5TUT09IiwibWFjIjoiNGE3NmNkMWQ3ZTU1NDVkMTZmZmI2M2Y5ZGJlYjg4Zjg0N2IyMzg1ZTg4MzNjZjdkMmZjYmI0ZTdjMjBlYjMzNSIsInRhZyI6IiJ9', 1, 'APRIL', 'april@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:10', 130, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:10', '2026-08-21 05:01:10', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (156, 'saskia', '$2y$12$waU9WbKLasT1ghL2u9R3Nefi1WS/Kb/spBAmL2byh846z/fK8ZmHa', 'eyJpdiI6Ik8vRlRUMi9aQi9WMjJjNHp1VGkxcmc9PSIsInZhbHVlIjoibkRNVXZCck1pVTBJUjMvRlJMUWNqdz09IiwibWFjIjoiNzFhNDdmMGFkZWMzNzE1MWFkMTYxZmE2ZmIwZTU0NzNiYzM5MTExOTVhNzAzZGM4NWVjYmRiYjdjNTAyNzczNCIsInRhZyI6IiJ9', 1, 'SASKIA NURIN ANTIKA', 'saskia@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:10', 131, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:10', '2026-08-21 05:01:10', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (157, 'andi', '$2y$12$EZ5NxZjQzHBMldv8XqS21uUAFMHoTAJv7GEL6vrXkoE1FXN1z5inu', 'eyJpdiI6IkhHRkozVGNlaHpIVTlTZlQwSkdocHc9PSIsInZhbHVlIjoiVU15S2liRjYvK2I1Q1JYSnJ4VXlPdz09IiwibWFjIjoiMmEwMWZkOWE5ZGE1ZTlmZWE0ZmE2ZjlhYzdjNTYyMjc2MzNjNDYzODU0ZDU4ZWUzZDk2NzhlMDg0MDRlNjhlOCIsInRhZyI6IiJ9', 1, 'ANDI PARIS BACHTIAR', 'andi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:10', 132, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:10', '2026-08-21 05:01:10', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (158, 'eni', '$2y$12$ucCVGnOEch/Jv72Wp3xbqegN4vvIHOEXX/TsehRJiXjnDrGVzADNi', 'eyJpdiI6InNzMlVhZ292cDhQZmxNc3N0b09KVUE9PSIsInZhbHVlIjoiTDBUZFg5S3JZY21lU2FLWUFHVXhpUT09IiwibWFjIjoiMzNlZWQ1YTRkY2Q1ODk1NmM0YmE0MWMwMjg4NDNlYjc3NGI3ODVmNmI1NjU3NGZlNzNkYWE3ZWNiNWExNTY5ZSIsInRhZyI6IiJ9', 1, 'ENI KURNIATI HANINGTYAS', 'eni@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:11', 133, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:11', '2026-08-21 05:01:11', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (159, 'arif4', '$2y$12$zfeTgQTQe1QdoSMceIXf0Ox340bHuu09cZeqqycUOCKKAKcQ1Q..e', 'eyJpdiI6InV5Nkx3WVVqOXNtQzhuV2thT1VJM3c9PSIsInZhbHVlIjoiWTdkaFYxb3IyWXZ6clEwb0VwQVI3QT09IiwibWFjIjoiNzJjYWM4M2UyMjU5NzEzZjA5NDQ5ZGJiNmE1NmVmYzcyZDNlNmM5OGE3Y2YwM2UzZjk1M2MwOWFjNTZkMWU5YiIsInRhZyI6IiJ9', 1, 'ARIF AGUS', 'arif4@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:11', 134, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:11', '2026-08-21 05:01:11', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (160, 'hendrian', '$2y$12$l0GkTar/ELNAEXuMbgcuFOmYtjYX0NphntHg8Yh08Q4WQc75rHX/i', 'eyJpdiI6IlJxMHptUG4wVWFNWmJQbzM3U2pSWnc9PSIsInZhbHVlIjoiVkVoc0pHcE9WaDY1ODNXeEZMZW5VQT09IiwibWFjIjoiZGFiZDhhNDllZDdhNDBjNjU4NWZjN2IwOGEzYWQ4ZWVhNzU5ZjFjNWQwOTc0ZGQ3YjRmMjFlMjU5NDM4ZDE4ZiIsInRhZyI6IiJ9', 1, 'HENDRIAN', 'hendrian@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:11', 135, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:11', '2026-08-21 05:01:11', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (161, 'arinda2', '$2y$12$CmPUYjHCB89DBUw.7SVU7eRI48mMESBlJAvDOKneCocl1yVxkYkKa', 'eyJpdiI6InJyRTg4MmtLVnJ1YjZySlBsenZBT0E9PSIsInZhbHVlIjoiOWFHUUxnN3lteEJPZEdBRDUzelRhQT09IiwibWFjIjoiYmMyNjk4ZTRiYzE4MzZiNmFmYzJlNDNlODlmYTkzYmQxOGRkNWNhMDJlYjU0NDNiNmE2OThkODRhZjU0MDQyOSIsInRhZyI6IiJ9', 1, 'ARINDA NINDY PUTRI SILVYA', 'arinda2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:11', 136, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:11', '2026-08-21 05:01:11', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (162, 'rizta', '$2y$12$AZOLPBJeAGQkAlIsGf9/b.CxOAXgBTFeUzN/yxXMEss3TsIHx3qSC', 'eyJpdiI6IjdlVHBMNTllV3pMRzBYNnNOQ09BV1E9PSIsInZhbHVlIjoiQjk4SUVQcUFpNW41cFBxSUpnb1Vsdz09IiwibWFjIjoiZWU2YTIwNzQ2MDlhMjRhOTdjODk5MGE4NmQ0NDFiNTEyM2IwN2FiNGU5NDIyYzJjNjVmYzYzNDM4YTYxMTE0MiIsInRhZyI6IiJ9', 1, 'RIZTA HARIF SHABRINA', 'rizta@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:11', 137, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:11', '2026-08-21 05:01:11', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (163, 'muhammad2', '$2y$12$ZMIaE87YTvrQ6vBdfv5Mqu8xv7d4OupdB99B7Oid0MwVSnfqt5kga', 'eyJpdiI6IlZrTWJOQTREWEJTWnJRRnRXNWIraVE9PSIsInZhbHVlIjoiYXhlc0kwOU1RMHBaWXNibWdxVXZvQT09IiwibWFjIjoiYWE2NGM3YTQ4MjJkOWQ1NmFhN2IxNTM2MWI2YjVhMTkzMjI1YTFmYWFlMjZmY2EzMzI4OTZlZjIwMWIwYWEzOCIsInRhZyI6IiJ9', 1, 'MUHAMMAD RAFLY', 'muhammad2@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:12', 138, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:12', '2026-08-21 05:01:12', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (164, 'anis', '$2y$12$rxyfi9w.idIotPnRYHQ4ZuLzIZlAkKMLeUSMf/INk4Sbyn3rVRtti', 'eyJpdiI6ImtlQm00Y0h4KzNJUGdIVUMxTlMxa2c9PSIsInZhbHVlIjoiU0tKSTVwbUQwNjBwU0dRNklDOHhXZz09IiwibWFjIjoiZDFjZWRhY2RkMTc3N2FiNjQ3MTg0MThhNGJkODI1OTBiOWY2NGNkZTNlZmNhNTE5NTFiYzkwNGM2NzBhY2MzMSIsInRhZyI6IiJ9', 1, 'ANIS SYAFAAH', 'anis@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:12', 139, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:12', '2026-08-21 05:01:12', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (165, 'mia', '$2y$12$psLQuj5OT2Bt4x2MJBvGnuf3gIap3sgb9H1Lxrv3IZZFJDLmS8Z2m', 'eyJpdiI6InNScURZTFJET01PMkNueFdJLy9CTHc9PSIsInZhbHVlIjoidHVqRUhpeTFmclo0MTlrdG45WVg4UT09IiwibWFjIjoiMWZkYzQ1ODhhNzU5ZWYwODEzZmY3ZmMxNDYwMWE3YTA1OWM2NjE3MmQ2YjdlNGQ0NzU5YWJjYzJmZDgyODkxMSIsInRhZyI6IiJ9', 1, 'MIA FIRIYAL NINDITA', 'mia@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:12', 140, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:12', '2026-08-21 05:01:12', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (166, 'nurdiana', '$2y$12$r4tKNexCeNh7.5avOnm.RO9ZlvR/rqszITA.pmjDxRaZ7WgPE.v7K', 'eyJpdiI6IjRLMmNlMFpmODJmUGJYdUY4ckdUYXc9PSIsInZhbHVlIjoiYXR6ZHk5N29NMG1vTEkvMHRIbDhFQT09IiwibWFjIjoiOWZkYmU5NTlkNmRiMzZhMjM1ZWU5MTljMTIzNDM3MDI3YTYxODM0MTk5ZWM2OWQxZjhlOWNiYzFlZDFhNjY0YiIsInRhZyI6IiJ9', 1, 'NURDIANA LEDIA WATI', 'nurdiana@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:12', 141, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:12', '2026-08-21 05:01:12', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (167, 'budi', '$2y$12$yR1yUZPKGJm3k6eJrX5V9.rMKTTQzYjvg6NtFVxPuceQBtByv6q/C', 'eyJpdiI6IjFFVkMwQm1jVDllWjlSRVFOWjFGemc9PSIsInZhbHVlIjoiSGl6VU5QdVFENUxLM003eHQzM2RaUT09IiwibWFjIjoiNDU2NTk5M2I0YTZhYWU2OTBiMzAwM2ZmYTU3ODhhNjkzZWEzZjU4YzZjMWVhMGRjMjRhNzBjM2U0ZDNmN2Q4MiIsInRhZyI6IiJ9', 1, 'BUDI AMIN', 'budi@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:13', 142, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:13', '2026-08-21 05:01:13', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (168, 'suro', '$2y$12$haqWQhysw0lMLlbX/xhlheZMRXtuOO4cGoAMMWuX8X4QJSXesUxDK', 'eyJpdiI6IlROUEFEWnd6cGEyRXBlRTRkdVJFK0E9PSIsInZhbHVlIjoiRHpTdkMwbTNJdnE2VFlIN2JkS1pUQT09IiwibWFjIjoiNTU2N2Q0ZTIxNWE1YjVmNTVmYjBhYTE2N2Q0Y2IwMTJjMmY4N2VlNzMzZDBkMGRlZjMzNDQ2N2QwYmEzODY0OCIsInRhZyI6IiJ9', 1, 'SURO NUR WIDIANTO', 'suro@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:13', 143, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:13', '2026-08-21 05:01:13', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (169, 'annisa', '$2y$12$kh/7EEzBewJQEh/sgfuEguaihrggkPqi3Mf6Aedx2wFmEDRoY2MU.', 'eyJpdiI6InpKK0RJN0FYMXVVSnpNVTlLV25PRkE9PSIsInZhbHVlIjoiMWs1TVYyUk1JWHVQbVA5WFUzcGY4Zz09IiwibWFjIjoiOTA0ZWQxMzk2MmZiNDU1NWVkOGNiNTFmMzY1Yjk4Nzg0NTJlMzQxMTIwMGNmMTllYjgxOTZhZDM3MDA0MWNiMyIsInRhZyI6IiJ9', 1, 'ANNISA KUSUMANINGTYAS', 'annisa@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:13', 144, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:13', '2026-08-21 05:01:13', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (170, 'indira', '$2y$12$HObfNA7ujbNEhGBj/356yupyobxRY1oBFUuBRd.qTMHoA5ZIB9Mma', 'eyJpdiI6IkxscTJvUnRaMWRQQUg2d05XbDIxT1E9PSIsInZhbHVlIjoiUThVcjRWUGNrdFdDbFJIVklUdUNVZz09IiwibWFjIjoiNWJhOTY4ZjM3ZmE5MzEwMmI0YTQxYWNjMjY2M2JhYmY1ZTZkY2E0NzA1MmI0YzA5MDZiNjgxMzBhOWUyNjc3MyIsInRhZyI6IiJ9', 1, 'INDIRA DYAH PRAMESTI', 'indira@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:13', 145, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:13', '2026-08-21 05:01:13', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (171, 'rony', '$2y$12$ROkRYK98Ilom.a8uYxC3gOQcr.mjPVUHNEkBBn/oED.DIFQoLoQkW', 'eyJpdiI6IkNla1lxbTFxbmhJV0NRMzhxa3ZVQ2c9PSIsInZhbHVlIjoiSlBRZnFRcmprdHBWaEp0QlB5ZEVWdz09IiwibWFjIjoiNDIxOWI3MThjN2Y4NDdkN2I4OThlOTY2YzFlYjFkNWI3YTc1OWQ1MjllMTdmZWU2M2FmZGZjNTMxMTdlNDYzNCIsInRhZyI6IiJ9', 1, 'RONY SAPUTRA', 'rony@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:13', 146, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:13', '2026-08-21 05:01:13', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (172, 'ringga', '$2y$12$AEgj6xYCrEuHZosMqe8Th.E1AesbViTGc64eku3CdkYzwnn837NzS', 'eyJpdiI6IjFOL3N3SkxmY2tzQ25OVGJqUHJZSUE9PSIsInZhbHVlIjoib0dVN0lDTno3Qk5PdFJ3Y0lBSzFpdz09IiwibWFjIjoiOGM1NDUwYjAxM2VmZjFjZjg5YjkxZjZhNDcxZDI4ZDNiNjU3NDVkYzI0ODAyOWEwY2JhNDE5MTYxMzU4NGEzNyIsInRhZyI6IiJ9', 1, 'RINGGA GITA LESMANA', 'ringga@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:14', 147, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:14', '2026-08-21 05:01:14', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (173, 'widita', '$2y$12$eh.VowIFS8ETyJtmyS4t8eXZas.Q0o3JTjHHF.bZ2tpYxMWIa4A02', 'eyJpdiI6IlJlOFJRUzIwN0xJT05POXR4NEIzUXc9PSIsInZhbHVlIjoiblRvR3czMFZETXJEdzlXWG8wL2ViUT09IiwibWFjIjoiYzY2ZTEyZGM0YjdmYTU0YTg5OTRiZGEwNDliOWYzNTA2ZjQzOWJjYjU5NjI1YTk1MDY5N2VlMTY4YjkzMjY3NyIsInRhZyI6IiJ9', 1, 'WIDITA KUSUMANINGSIH', 'widita@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:14', 148, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:14', '2026-08-21 05:01:14', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (174, 'didin', '$2y$12$a0hLeHqDZ81th9SmxV/B9.JBmpjMaPiiZt8LUlIpUpgxka4.lu5Wi', 'eyJpdiI6InpFMU9mS2VPY1FnVXlKZHh0VU8zQnc9PSIsInZhbHVlIjoiOVkzQXJIV2NHeGJqMzVhUTdkWmhCQT09IiwibWFjIjoiNjRmZDQ1ZmVhN2I4ZjMzNmVkMDBjMTRkM2I5NDg5MzEwOTA2YmViYzI3YzU4ZjU4MzRiMTdmZjhjZGY2MTBmYSIsInRhZyI6IiJ9', 1, 'DIDIN ARIF KURNIAWAN', 'didin@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:14', 150, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:14', '2026-08-21 05:01:14', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (175, 'sania3', '$2y$12$MoCLxCyhjsj5lrVaTjCsiOzg.nmBlrZUoKcDAf24kMoU/ZJLnGBfy', 'eyJpdiI6IjJScjBOU0hicW1FTVlHSEJSaHNpeGc9PSIsInZhbHVlIjoiYlZQVGxKSDI3dlFTQlNWczFKYmFuZz09IiwibWFjIjoiNGFjZWQ1OTAzNTJkZTgxNTA2Y2NkNDYxYmVhZmFlOTI3ODY1YmU0MzY0YjFjNDYwYTZjOTEyNmMxZmE3Y2E2ZSIsInRhZyI6IiJ9', 1, 'SANIA MAURA', 'sania3@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-21 05:01:14', 151, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-21 05:01:14', '2026-08-21 05:01:14', 'tinker-test', 'tinker-test');
INSERT INTO `sec_user` VALUES (213, 'admin', '$2y$12$VP09FPiTjboumCBZHBfxx.Q342xON0Sc7qtiEqSI2vXhWryUTejR2', NULL, 0, 'Budi Santoso', 'budi.santoso@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'pending', 'navy', 'light', 'blue', '2026-08-28 12:03:06', '2026-08-28 12:03:37', NULL, NULL);
INSERT INTO `sec_user` VALUES (231, 'kandidat', '$2y$12$N6WkYWrIPp09w6me88PHGOZQzkyI4i60jlU6qhE6vWitm/txhlA7a', 'eyJpdiI6IjNDdDZidDQzRlpjSkY3S2hEZlBDeEE9PSIsInZhbHVlIjoidmlwaFprRHRtclhxMGpzVUltQXRWdz09IiwibWFjIjoiZDY3YWYwNWIxYTg4Y2NhNjQ2NDI5NzNmOTllYzczYmUwMGVmN2FmODY5MjliMWQwMDM1MTE2OTkxYjMyYjU1NSIsInRhZyI6IiJ9', 1, 'Kandidat E2E Test', 'kandidat@telasih.co.id', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, NULL, '2026-08-29 02:55:03', 497, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-08-29 02:55:03', '2026-08-29 02:55:03', 'johiwan', 'johiwan');
INSERT INTO `sec_user` VALUES (232, 'operator', '$2y$12$ioLsTndIBNvHH8KgzYD4iu/mJkp7nENhkv3e/NW3rVDd9NegSMoZ2', NULL, 0, 'UAT Operator (Level 1)', 'operator@uat.telasih.co.id', '+628000000001', '1990-01-01', '$2y$12$mPlz514q2d8C8yvikQZkFOpeSLKC89Q2vc.pRbuwH3h4FOG7xESS2', 1, '$2y$12$rW0PubbX5j99RzYl2CZbzeq38FGxloRh3qvqMztuImB8JAHSyYQne', '2026-09-03 00:18:50', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-09-03 00:18:50', '2026-09-04 00:31:24', 'claude_code', NULL);
INSERT INTO `sec_user` VALUES (233, 'supervs', '$2y$12$ioLsTndIBNvHH8KgzYD4iu/mJkp7nENhkv3e/NW3rVDd9NegSMoZ2', NULL, 0, 'UAT Supervisor (Level 2)', 'supervs@uat.telasih.co.id', '+628000000002', '1990-01-01', '$2y$12$mPlz514q2d8C8yvikQZkFOpeSLKC89Q2vc.pRbuwH3h4FOG7xESS2', 1, '$2y$12$rW0PubbX5j99RzYl2CZbzeq38FGxloRh3qvqMztuImB8JAHSyYQne', '2026-09-03 00:18:50', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_user` VALUES (234, 'mgr-sal', '$2y$12$ioLsTndIBNvHH8KgzYD4iu/mJkp7nENhkv3e/NW3rVDd9NegSMoZ2', NULL, 0, 'UAT Manager Sales (Level 3)', 'mgr-sal@uat.telasih.co.id', '+628000000003', '1990-01-01', '$2y$12$mPlz514q2d8C8yvikQZkFOpeSLKC89Q2vc.pRbuwH3h4FOG7xESS2', 1, '$2y$12$rW0PubbX5j99RzYl2CZbzeq38FGxloRh3qvqMztuImB8JAHSyYQne', '2026-09-03 00:18:50', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_user` VALUES (235, 'mgr-pur', '$2y$12$ioLsTndIBNvHH8KgzYD4iu/mJkp7nENhkv3e/NW3rVDd9NegSMoZ2', NULL, 0, 'UAT Manager Purchasing (Level 3)', 'mgr-pur@uat.telasih.co.id', '+628000000004', '1990-01-01', '$2y$12$mPlz514q2d8C8yvikQZkFOpeSLKC89Q2vc.pRbuwH3h4FOG7xESS2', 1, '$2y$12$rW0PubbX5j99RzYl2CZbzeq38FGxloRh3qvqMztuImB8JAHSyYQne', '2026-09-03 00:18:50', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_user` VALUES (236, 'mgr-inv', '$2y$12$ioLsTndIBNvHH8KgzYD4iu/mJkp7nENhkv3e/NW3rVDd9NegSMoZ2', NULL, 0, 'UAT Manager Inventory (Level 3)', 'mgr-inv@uat.telasih.co.id', '+628000000005', '1990-01-01', '$2y$12$mPlz514q2d8C8yvikQZkFOpeSLKC89Q2vc.pRbuwH3h4FOG7xESS2', 1, '$2y$12$rW0PubbX5j99RzYl2CZbzeq38FGxloRh3qvqMztuImB8JAHSyYQne', '2026-09-03 00:18:50', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_user` VALUES (237, 'mgr-acc', '$2y$12$ioLsTndIBNvHH8KgzYD4iu/mJkp7nENhkv3e/NW3rVDd9NegSMoZ2', NULL, 0, 'UAT Manager Finance & Accounting (Level 3)', 'mgr-acc@uat.telasih.co.id', '+628000000006', '1990-01-01', '$2y$12$mPlz514q2d8C8yvikQZkFOpeSLKC89Q2vc.pRbuwH3h4FOG7xESS2', 1, '$2y$12$rW0PubbX5j99RzYl2CZbzeq38FGxloRh3qvqMztuImB8JAHSyYQne', '2026-09-03 00:18:50', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);
INSERT INTO `sec_user` VALUES (238, 'direksi', '$2y$12$ioLsTndIBNvHH8KgzYD4iu/mJkp7nENhkv3e/NW3rVDd9NegSMoZ2', NULL, 0, 'UAT Direksi (Level 4)', 'direksi@uat.telasih.co.id', '+628000000007', '1990-01-01', '$2y$12$mPlz514q2d8C8yvikQZkFOpeSLKC89Q2vc.pRbuwH3h4FOG7xESS2', 1, '$2y$12$rW0PubbX5j99RzYl2CZbzeq38FGxloRh3qvqMztuImB8JAHSyYQne', '2026-09-03 00:18:50', 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 0, NULL, 0, NULL, 'approved', 'navy', 'light', 'blue', '2026-09-03 00:18:50', '2026-09-03 00:18:50', 'claude_code', NULL);

-- ----------------------------
-- Table structure for sec_user_group
-- ----------------------------
DROP TABLE IF EXISTS `sec_user_group`;
CREATE TABLE `sec_user_group`  (
  `id_user_group` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL COMMENT 'FK -> sec_user',
  `group_id` int UNSIGNED NOT NULL COMMENT 'FK -> sec_group',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user_group`) USING BTREE,
  UNIQUE INDEX `uq_user_group`(`user_id` ASC, `group_id` ASC) USING BTREE,
  INDEX `idx_ug_group`(`group_id` ASC) USING BTREE,
  CONSTRAINT `fk_ug_group` FOREIGN KEY (`group_id`) REFERENCES `sec_group` (`id_group`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ug_user` FOREIGN KEY (`user_id`) REFERENCES `sec_user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Keanggotaan user dalam grup (relasi M:N). Izin efektif user = GABUNGAN izin seluruh grupnya.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sec_user_group
-- ----------------------------
INSERT INTO `sec_user_group` VALUES (1, 2, 1, '2026-08-08 17:36:58');
INSERT INTO `sec_user_group` VALUES (2, 1, 3, '2026-08-08 17:36:58');
INSERT INTO `sec_user_group` VALUES (4, 213, 3, '2026-08-28 19:03:06');
INSERT INTO `sec_user_group` VALUES (6, 232, 1, '2026-09-03 00:18:50');
INSERT INTO `sec_user_group` VALUES (7, 233, 2, '2026-09-03 00:18:50');
INSERT INTO `sec_user_group` VALUES (8, 234, 8, '2026-09-03 00:18:50');
INSERT INTO `sec_user_group` VALUES (9, 235, 9, '2026-09-03 00:18:50');
INSERT INTO `sec_user_group` VALUES (10, 236, 10, '2026-09-03 00:18:50');
INSERT INTO `sec_user_group` VALUES (11, 237, 11, '2026-09-03 00:18:50');
INSERT INTO `sec_user_group` VALUES (12, 238, 3, '2026-09-03 00:18:50');

-- ----------------------------
-- Table structure for sessions
-- ----------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions`  (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED NULL DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `sessions_user_id_index`(`user_id` ASC) USING BTREE,
  INDEX `sessions_last_activity_index`(`last_activity` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sessions
-- ----------------------------
INSERT INTO `sessions` VALUES ('qGaIBWHBxT84A63mLmHr6bSxdeJjOxjwQIuxWwoe', 232, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJkdzZ2R216VGx3a1lhQ0NKYWhwU1R6dFpKc0hTcDV0YTZpVHRqZW02IiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL3RtZXJwLnRlc3RcL2lucXVpcnkiLCJyb3V0ZSI6ImlucXVpcnkuaHViIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIzMn0=', 1788486175);

-- ----------------------------
-- Table structure for sys_app_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_app_menu`;
CREATE TABLE `sys_app_menu`  (
  `id` int NOT NULL,
  `parent_id` int NULL DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_label` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `tooltip` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `trans_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `menu_type` enum('module','group','item') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'item',
  `object_type` enum('form','grid','report','dashboard','link') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `target` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `target_kind` enum('app','php','html','url') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'app',
  `sort_order` int NOT NULL DEFAULT 0,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `required_object` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status_code` enum('NOT_BUILT','READY_FOR_QC','LOCKED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NOT_BUILT' COMMENT 'Kesiapan build (menu_type=item saja) -- lihat module_status_system di GLOBAL_RULES.yaml',
  `status_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `status_updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `status_updated_at` datetime NULL DEFAULT NULL,
  `doc_slug` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Basis nama file docs/*.md /.yaml terkait, TANPA _tierN & ekstensi. File aktual = {doc_slug}_tier{n}.md, {doc_slug}_state.yaml, dst. Bisa sama antar >1 code (1 dok membahas >1 submodule).',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `code`(`code` ASC) USING BTREE,
  INDEX `idx_parent`(`parent_id` ASC, `sort_order` ASC) USING BTREE,
  INDEX `idx_sys_app_menu_status`(`status_code` ASC) USING BTREE,
  CONSTRAINT `sys_app_menu_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `sys_app_menu` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menu dinamis (mega menu / sidebar / command palette) — LAYER TAMPILAN. Hierarki module->group->item; target+target_kind=eksekusi; required_object -> sec_module.module_code (kaitan hak akses).' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_app_menu
-- ----------------------------
INSERT INTO `sys_app_menu` VALUES (1, NULL, 'SALES', 'Sales', 'SD', 'Sales & Distribution', NULL, 'fa-solid fa-file-invoice-dollar', 'module', NULL, NULL, 'app', 10, 1, 'SALES', '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, 'sales');
INSERT INTO `sys_app_menu` VALUES (2, NULL, 'PROC', 'Procurement', 'MM', 'Procurement (Materials Management)', NULL, 'fa-solid fa-cart-shopping', 'module', NULL, NULL, 'app', 20, 1, 'PROC', '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, 'procurement');
INSERT INTO `sys_app_menu` VALUES (3, NULL, 'INV', 'Inventory', 'WM', 'Inventory (Warehouse Management)', NULL, 'fa-solid fa-boxes-stacked', 'module', NULL, NULL, 'app', 30, 1, 'INV', '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, 'inventory');
INSERT INTO `sys_app_menu` VALUES (4, NULL, 'PROD', 'Production', 'PP', 'Production (Production Planning)', NULL, 'fa-solid fa-industry', 'module', NULL, NULL, 'app', 40, 1, 'PROD', '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (5, NULL, 'MDATA', 'Master Data', 'MD', 'Master Data', NULL, 'fa-solid fa-database', 'module', NULL, NULL, 'app', 65, 1, 'MDATA', '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (6, NULL, 'FINACC', 'Finance & Accounting', 'FA', 'Finance & Accounting', NULL, 'fa-solid fa-sack-dollar', 'module', NULL, NULL, 'app', 60, 1, 'FINACC', '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, 'financeaccounting');
INSERT INTO `sys_app_menu` VALUES (9, NULL, 'SETUP', 'Setup', NULL, NULL, NULL, 'fa-solid fa-gear', 'module', NULL, NULL, 'app', 99, 1, 'SETUP', '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (110, 1, 'SALES_TRX', 'Transaksi', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 10, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (111, 938, 'SALES_INQ', 'Inquiry Product', NULL, NULL, NULL, NULL, 'item', 'form', 'form_inquiry.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', 'inquiryproduct');
INSERT INTO `sys_app_menu` VALUES (112, 938, 'SALES_COST', 'Pro-Cost', NULL, NULL, NULL, NULL, 'item', 'form', 'form_costing.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', 'Worklist grid inquiry/costing-list.blade.php live 2026-08-22 (module:COSTING), dblclick buka inquiry.costing existing', 'claude_code', '2026-08-21 17:01:30', 'productcosting');
INSERT INTO `sys_app_menu` VALUES (113, 938, 'SALES_QUOT', 'Quotation', NULL, NULL, NULL, NULL, 'item', 'form', 'form_quotation.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (114, 939, 'SALES_SO', 'Customer Order', NULL, NULL, NULL, NULL, 'item', 'form', 'form_customer_order.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 08:21:09', NULL);
INSERT INTO `sys_app_menu` VALUES (115, 939, 'SALES_CPDEL', 'Customer Plan Delivery', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (116, 940, 'SALES_DO', 'Delivery Order', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 14:38:02', NULL);
INSERT INTO `sys_app_menu` VALUES (180, 1, 'SALES_MON', 'Laporan', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 80, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (181, 180, 'SALES_GRID_SO', 'Daftar Sales Order', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_so.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (182, 180, 'SALES_MON_ORD', 'Monitoring Order', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_monitoring_order.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (183, 180, 'SALES_RPT', 'Laporan Penjualan', NULL, NULL, NULL, NULL, 'item', 'report', 'rpt_sales.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (210, 2, 'PROC_TRX', 'Transaksi', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (211, 210, 'PROC_PR', 'Purchase Requisition (PR)', NULL, NULL, NULL, NULL, 'item', 'form', 'form_pr.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (212, 210, 'PROC_PQ', 'Purchase Quotation', NULL, NULL, NULL, NULL, 'item', 'form', 'form_purchase_quotation.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (213, 210, 'PROC_PO', 'Purchase Order', NULL, NULL, NULL, NULL, 'item', 'form', 'form_po.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (214, 210, 'PROC_RCV', 'Purchase Receive', NULL, NULL, NULL, NULL, 'item', 'form', 'form_purchase_receive.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, 'purchasereceive');
INSERT INTO `sys_app_menu` VALUES (215, 210, 'PROC_RET', 'Purchase Return', NULL, NULL, NULL, NULL, 'item', 'form', 'form_purchase_return.php', 'app', 50, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (280, 2, 'PROC_MON', 'Laporan', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 80, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (281, 280, 'PROC_MON_PR', 'Outstanding PR', NULL, NULL, NULL, NULL, 'item', 'grid', '', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (282, 280, 'PROC_MON_PO', 'Outstanding PO', NULL, NULL, NULL, NULL, 'item', 'grid', '', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (310, 3, 'INV_TRX', 'Transaksi', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 10, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (311, 310, 'INV_MOVE', 'Mutasi / Transfer Stok', NULL, NULL, NULL, NULL, 'item', 'form', 'form_stock_move.php', 'app', 10, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (312, 310, 'INV_ADJ', 'Penyesuaian Stok (Opname)', NULL, NULL, NULL, NULL, 'item', 'form', 'form_stock_adj.php', 'app', 20, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (380, 3, 'INV_RPT', 'Laporan', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 80, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (381, 380, 'INV_RAW', 'Master Stok', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_stock_raw.php', 'app', 10, 0, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (382, 380, 'INV_FG', 'Stok Barang Jadi', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_stock_fg.php', 'app', 20, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (383, 380, 'INV_CARD', 'Kartu Stok', NULL, NULL, NULL, NULL, 'item', 'report', 'rpt_stock_card.php', 'app', 30, 0, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (384, 380, 'INV_HIST', 'Riwayat Mutasi', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_stock_history.php', 'app', 40, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (410, 4, 'PROD_MASTER', 'Master', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (411, 410, 'PROD_CARD', 'Master Card', NULL, NULL, NULL, NULL, 'item', 'form', 'form_master_card.php', 'app', 10, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (412, 410, 'PROD_LAYOUT', 'Layout', NULL, NULL, NULL, NULL, 'item', 'form', 'form_layout.php', 'app', 20, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (420, 4, 'PROD_PLAN', 'Planning', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (421, 420, 'PROD_MPS', 'MPS - Gantt Chart', NULL, NULL, NULL, NULL, 'item', 'form', 'form_mps.php', 'app', 11, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-27 17:43:32', NULL);
INSERT INTO `sys_app_menu` VALUES (422, 420, 'PROD_MRP', 'MRP (Material Requirement Planning)', NULL, NULL, NULL, NULL, 'item', 'form', 'form_mrp.php', 'app', 20, 0, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (430, 4, 'PROD_SUPPORT', 'Support', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (431, 430, 'PROD_MTN_SCH', 'Schedule Maintenance', NULL, NULL, NULL, NULL, 'item', 'form', 'form_maintenance_schedule.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (432, 430, 'PROD_MTN_PREV', 'Preventive Maintenance', NULL, NULL, NULL, NULL, 'item', 'form', 'form_maintenance_preventive.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (440, 4, 'PROD_OUT', 'Output', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 40, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (441, 440, 'PROD_OUTPUT', 'Production Output', NULL, NULL, NULL, NULL, 'item', 'form', 'form_production_output.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 03:10:08', NULL);
INSERT INTO `sys_app_menu` VALUES (442, 420, 'PROD_MON_WO', 'MPS - Kanban Board', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_monitoring_wo.php', 'app', 12, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 04:30:25', NULL);
INSERT INTO `sys_app_menu` VALUES (511, 5, 'MD_CUST', 'Master Customer', NULL, NULL, NULL, NULL, 'item', 'form', 'form_customer.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', 'customer');
INSERT INTO `sys_app_menu` VALUES (512, 5, 'MD_SUPP', 'Master Supplier', NULL, NULL, NULL, NULL, 'item', 'form', 'form_supplier.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (513, 5, 'MD_PROD', 'Master Produk', NULL, NULL, NULL, NULL, 'item', 'form', 'form_product.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', 'Grid+form Livewire master.produk live 2026-08-21', 'claude_code', '2026-08-21 16:28:39', 'masterproduct');
INSERT INTO `sys_app_menu` VALUES (514, 5, 'MD_RAW', 'Master Bahan Baku', NULL, NULL, NULL, NULL, 'item', 'form', 'form_material.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (515, 5, 'MD_UOM', 'Master Satuan (UoM)', NULL, NULL, NULL, NULL, 'item', 'form', 'form_uom.php', 'app', 50, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (516, 5, 'MD_WH', 'Master Gudang', NULL, NULL, NULL, NULL, 'item', 'form', 'form_warehouse.php', 'app', 60, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (610, 6, 'FIN', 'Finance', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (611, 610, 'FIN_CASH', 'Kasir (Kas/Bank)', NULL, NULL, NULL, NULL, 'item', 'form', 'form_cashier.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (612, 610, 'FIN_AR', 'Piutang (AR)', NULL, NULL, NULL, NULL, 'item', 'form', 'form_ar.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (613, 610, 'FIN_AP', 'Hutang (AP)', NULL, NULL, NULL, NULL, 'item', 'form', 'form_ap.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (620, 6, 'ACC', 'Accounting', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (621, 620, 'ACC_JRN', 'Jurnal', NULL, NULL, NULL, NULL, 'item', 'form', 'form_journal.php', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (622, 620, 'ACC_GL', 'Buku Besar', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_gl.php', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (623, 620, 'ACC_BS', 'Neraca', NULL, NULL, NULL, NULL, 'item', 'report', 'rpt_balance_sheet.php', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (624, 620, 'ACC_PL', 'Laba Rugi', NULL, NULL, NULL, NULL, 'item', 'report', 'rpt_income_stmt.php', 'app', 40, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (911, 9, 'SET_USER', 'Pengguna', NULL, NULL, NULL, NULL, 'item', 'grid', 'security.php?tab=users', 'app', 10, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (912, 9, 'SET_GROUP', 'Grup', NULL, NULL, NULL, NULL, 'item', 'grid', 'security.php?tab=groups', 'app', 20, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (913, 9, 'SET_PERM', 'Hak Akses Grup', NULL, NULL, NULL, NULL, 'item', 'grid', 'security.php?tab=perms', 'app', 30, 1, NULL, '2026-08-08 17:23:31', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (914, 9, 'SET_LANG', 'Bahasa', NULL, NULL, NULL, NULL, 'item', 'form', 'security.php?tab=lang', 'app', 40, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (915, 9, 'SET_THEME', 'Tema', NULL, NULL, NULL, NULL, 'item', 'form', 'security.php?tab=theme', 'app', 50, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (916, 9, 'SET_MENU', 'Susunan Menu', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_app_menu.php', 'app', 60, 1, NULL, '2026-08-08 17:23:31', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (917, 410, 'MD_MACHINE', 'Master Mesin', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 40, 1, NULL, '2026-08-18 04:51:25', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (918, 5, 'MD_SHIFT', 'Master Shift', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 80, 1, NULL, '2026-08-18 04:51:25', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (919, 9, 'SET_MIGRATION', 'Migrasi Data Legacy', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 40, 1, NULL, '2026-08-18 06:53:45', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (920, 620, 'ACC_COA', 'Master COA', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 5, 1, NULL, '2026-08-19 01:02:03', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (921, NULL, 'HRM', 'Human Resources', 'HR', 'Human Resources', NULL, NULL, 'module', NULL, NULL, 'app', 45, 1, 'HRM', '2026-08-19 06:53:40', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (922, 921, 'HRM_MASTER', 'Employee', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-19 06:53:40', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (923, 922, 'HRM_EMPLOYEE', 'Master Employee', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 10, 1, NULL, '2026-08-19 06:53:40', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', 'masteremployee');
INSERT INTO `sys_app_menu` VALUES (924, 921, 'HRM_SCHEDULE', 'Time Attendance', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 20, 1, NULL, '2026-08-19 07:19:13', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (925, 924, 'HRM_ROSTER', 'Shift Roster', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 10, 1, NULL, '2026-08-19 07:19:13', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (926, 610, 'FIN_DP', 'Uang Muka (DP)', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 15, 1, NULL, '2026-08-19 15:13:32', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', 'downpayment');
INSERT INTO `sys_app_menu` VALUES (930, 938, 'SALES_IMPO', 'Pro-Dev', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 20, 1, NULL, '2026-08-20 02:04:22', 'READY_FOR_QC', 'Worklist grid inquiry/imposition-list.blade.php live 2026-08-22, dblclick buka inquiry.imposition existing', 'claude_code', '2026-08-21 17:01:30', NULL);
INSERT INTO `sys_app_menu` VALUES (931, 938, 'SALES_NOTA', 'Nota Inquiry', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 50, 0, NULL, '2026-08-20 02:04:22', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-20 02:04:22', NULL);
INSERT INTO `sys_app_menu` VALUES (932, 310, 'INV_IN', 'Stock In', NULL, NULL, NULL, NULL, 'item', NULL, NULL, 'app', 5, 0, NULL, '2026-08-21 14:09:08', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (933, 310, 'INV_OUT', 'Stock Out', NULL, NULL, NULL, NULL, 'item', NULL, NULL, 'app', 6, 0, NULL, '2026-08-21 14:09:08', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (934, 310, 'INV_RTP', 'Return from Production', NULL, NULL, NULL, NULL, 'item', NULL, NULL, 'app', 7, 0, NULL, '2026-08-21 14:09:08', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (935, 310, 'INV_ADJ2', 'Stock Adjustment & Opname', NULL, NULL, NULL, NULL, 'item', NULL, NULL, 'app', 8, 0, NULL, '2026-08-21 14:09:08', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (936, 310, 'INV_APRV', 'Persetujuan Penyesuaian', NULL, NULL, NULL, NULL, 'item', NULL, NULL, 'app', 9, 0, NULL, '2026-08-21 14:09:08', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (937, 380, 'INV_PERIOD', 'Periode Stok', NULL, NULL, NULL, NULL, 'item', NULL, NULL, 'app', 50, 0, NULL, '2026-08-21 14:09:08', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (938, 1, 'SALES_INQGRP', 'Inquiry & Quotation', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 20, 1, NULL, '2026-08-21 20:49:47', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 20:49:47', NULL);
INSERT INTO `sys_app_menu` VALUES (939, 1, 'SALES_ORDGRP', 'Sales Order', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 30, 1, NULL, '2026-08-21 20:49:47', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 20:49:47', NULL);
INSERT INTO `sys_app_menu` VALUES (940, 1, 'SALES_DLVGRP', 'Delivery', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 40, 1, NULL, '2026-08-21 20:49:47', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 20:49:47', NULL);
INSERT INTO `sys_app_menu` VALUES (941, 3, 'INV_RPT_RM', 'Reports — Raw Material', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 40, 0, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (942, 950, 'INV_RAW_RM', 'Stock Balance', NULL, NULL, NULL, 'fa-solid fa-list-check', 'item', 'grid', NULL, 'app', 60, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (943, 950, 'INV_CARD_RM', 'Stock Card', NULL, NULL, NULL, 'fa-solid fa-chart-column', 'item', 'report', NULL, 'app', 70, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (944, 3, 'INV_RPT_FG', 'Reports — Finished Goods', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 50, 0, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (945, 956, 'INV_RAW_FG', 'Stock Balance', NULL, NULL, NULL, 'fa-solid fa-list-check', 'item', 'grid', NULL, 'app', 60, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (946, 956, 'INV_CARD_FG', 'Stock Card', NULL, NULL, NULL, 'fa-solid fa-chart-column', 'item', 'report', NULL, 'app', 70, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (947, 3, 'INV_RPT_SFG', 'Reports — Semi Finished Goods', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 60, 0, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (948, 962, 'INV_RAW_SFG', 'Stock Balance', NULL, NULL, NULL, 'fa-solid fa-list-check', 'item', 'grid', NULL, 'app', 60, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (949, 962, 'INV_CARD_SFG', 'Stock Card', NULL, NULL, NULL, 'fa-solid fa-chart-column', 'item', 'report', NULL, 'app', 70, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (950, 3, 'INV_TRX_RM', 'Raw Material', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 10, 1, NULL, '2026-08-21 21:36:26', 'NOT_BUILT', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (951, 950, 'INV_RM_ITEM1', 'Stock In', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 10, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', 'Stock In manual live 2026-08-22 (stock-in.blade.php + StockPostingService)', 'claude_code', '2026-08-21 21:55:46', NULL);
INSERT INTO `sys_app_menu` VALUES (952, 950, 'INV_RM_ITEM2', 'Stock Out', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 20, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (953, 950, 'INV_RM_ITEM3', 'Return from Production', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 30, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (954, 950, 'INV_RM_ITEM4', 'Stock Adjustment & Physical Count', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 40, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (955, 950, 'INV_RM_ITEM5', 'Adjustment Approval', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 50, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (956, 3, 'INV_TRX_FG', 'Finished Goods', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 20, 1, NULL, '2026-08-21 21:36:26', 'NOT_BUILT', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (957, 956, 'INV_FG_ITEM1', 'Stock In', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 10, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', 'Stock In manual live 2026-08-22 (stock-in.blade.php + StockPostingService)', 'claude_code', '2026-08-21 21:55:46', NULL);
INSERT INTO `sys_app_menu` VALUES (958, 956, 'INV_FG_ITEM2', 'Stock Out', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 20, 1, NULL, '2026-08-21 21:36:26', 'NOT_BUILT', 'Di luar cakupan modul Stock Out/Return v1 -- INV_RULES.yaml (fg_out_bukan_milik_modul / SFG tak disebut spec Tier 4). Hanya kelas RM yang berlaku.', 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (959, 956, 'INV_FG_ITEM3', 'Return from Production', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 30, 1, NULL, '2026-08-21 21:36:26', 'NOT_BUILT', 'Di luar cakupan modul Stock Out/Return v1 -- INV_RULES.yaml (fg_out_bukan_milik_modul / SFG tak disebut spec Tier 4). Hanya kelas RM yang berlaku.', 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (960, 956, 'INV_FG_ITEM4', 'Stock Adjustment & Physical Count', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 40, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (961, 956, 'INV_FG_ITEM5', 'Adjustment Approval', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 50, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (962, 3, 'INV_TRX_SFG', 'Semi Finished Goods', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 30, 1, NULL, '2026-08-21 21:36:26', 'NOT_BUILT', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (963, 962, 'INV_SFG_ITEM1', 'Stock In', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 10, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', 'Stock In manual live 2026-08-22 (stock-in.blade.php + StockPostingService)', 'claude_code', '2026-08-21 21:55:46', NULL);
INSERT INTO `sys_app_menu` VALUES (964, 962, 'INV_SFG_ITEM2', 'Stock Out', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 20, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', 'Di luar cakupan modul Stock Out/Return v1 -- INV_RULES.yaml (fg_out_bukan_milik_modul / SFG tak disebut spec Tier 4). Hanya kelas RM yang berlaku.', 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (965, 962, 'INV_SFG_ITEM3', 'Return from Production', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 30, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', 'Di luar cakupan modul Stock Out/Return v1 -- INV_RULES.yaml (fg_out_bukan_milik_modul / SFG tak disebut spec Tier 4). Hanya kelas RM yang berlaku.', 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (966, 962, 'INV_SFG_ITEM4', 'Stock Adjustment & Physical Count', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 40, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (967, 962, 'INV_SFG_ITEM5', 'Adjustment Approval', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 50, 1, NULL, '2026-08-21 21:36:26', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-21 21:36:26', NULL);
INSERT INTO `sys_app_menu` VALUES (968, 210, 'PROC_PR_APPR', 'PR Approval Center', NULL, NULL, NULL, NULL, 'item', 'grid', '', 'app', 15, 1, NULL, '2026-08-22 01:06:32', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (969, 210, 'PROC_PO_APPR', 'PO Approval Center', NULL, NULL, NULL, NULL, 'item', 'grid', '', 'app', 35, 1, NULL, '2026-08-22 01:06:32', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (970, 280, 'PROC_MON_PV', 'Outstanding PV', NULL, NULL, NULL, NULL, 'item', 'grid', '', 'app', 30, 1, NULL, '2026-08-22 03:59:50', 'READY_FOR_QC', 'Live 2026-09-02: flag is_handed_over per-PV (opsi paling sederhana dari 3 yg diajukan dulu).', 'claude_code', '2026-09-02 14:06:07', NULL);
INSERT INTO `sys_app_menu` VALUES (971, 210, 'PROC_RET_APPR', 'Persetujuan Purchase Return', NULL, NULL, NULL, NULL, 'item', 'grid', '', 'app', 55, 1, NULL, '2026-08-22 05:14:11', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (973, 924, 'HRM_ATTENDANCE', 'Presensi', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 20, 1, NULL, '2026-08-24 08:03:10', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (974, 921, 'HRM_PAYROLL', 'Payroll', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 15, 1, NULL, '2026-08-26 07:11:37', 'NOT_BUILT', NULL, NULL, NULL, 'attendancepayroll');
INSERT INTO `sys_app_menu` VALUES (975, 921, 'HRM_RECRUITMENT', 'Recruitment', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 50, 1, NULL, '2026-08-26 07:11:37', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (976, 922, 'HRM_SELFSERVICE', 'Self-Service Karyawan', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 30, 1, NULL, '2026-08-26 07:11:37', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-26 07:11:37', 'selfservice');
INSERT INTO `sys_app_menu` VALUES (977, 5, 'MD_MATCAT', 'Master Kategori Bahan Baku', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 45, 1, NULL, '2026-08-26 08:18:48', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-26 08:41:15', NULL);
INSERT INTO `sys_app_menu` VALUES (978, 922, 'HRM_CORRECTION', 'Koreksi Data Karyawan', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 20, 1, NULL, '2026-08-27 07:02:05', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-27 07:02:05', NULL);
INSERT INTO `sys_app_menu` VALUES (979, 420, 'PROD_WO_LIST', 'Work Order', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 10, 1, NULL, '2026-08-28 01:44:20', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 01:58:11', NULL);
INSERT INTO `sys_app_menu` VALUES (980, 420, 'PROD_RULE_LIB', 'Rule Library', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 16, 1, NULL, '2026-08-28 01:44:20', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 01:58:11', NULL);
INSERT INTO `sys_app_menu` VALUES (981, 420, 'PROD_MTS_FORECAST', 'Forecast MTS', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 17, 0, NULL, '2026-08-28 01:44:20', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 01:58:11', NULL);
INSERT INTO `sys_app_menu` VALUES (982, 440, 'PROD_SCRAP_TYPE', 'Kelola Tipe Scrap', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 30, 1, NULL, '2026-08-28 03:45:47', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 03:45:47', NULL);
INSERT INTO `sys_app_menu` VALUES (984, 950, 'INV_SLOW_RM', 'Slow Moving Item', NULL, NULL, NULL, 'fa-solid fa-chart-pie', 'item', 'report', NULL, 'app', 80, 1, NULL, '2026-08-28 04:36:24', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 04:36:24', NULL);
INSERT INTO `sys_app_menu` VALUES (985, 956, 'INV_SLOW_FG', 'Slow Moving Item', NULL, NULL, NULL, 'fa-solid fa-chart-pie', 'item', 'report', NULL, 'app', 80, 1, NULL, '2026-08-28 04:36:24', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 04:36:24', NULL);
INSERT INTO `sys_app_menu` VALUES (986, 962, 'INV_SLOW_SFG', 'Slow Moving Item', NULL, NULL, NULL, 'fa-solid fa-chart-pie', 'item', 'report', NULL, 'app', 80, 1, NULL, '2026-08-28 04:36:24', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 04:36:24', NULL);
INSERT INTO `sys_app_menu` VALUES (989, 420, 'PROD_PROCESS_RATE', 'Target Rate Proses x Mesin/Pegawai', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 16, 1, NULL, '2026-08-28 07:18:55', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 07:18:55', NULL);
INSERT INTO `sys_app_menu` VALUES (990, 410, 'PROD_MST_PROCESS', 'Master Proses', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 30, 1, NULL, '2026-08-28 08:52:16', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 11:13:06', NULL);
INSERT INTO `sys_app_menu` VALUES (991, 410, 'PROD_MST_CREW', 'Master Crew', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 50, 1, NULL, '2026-08-28 08:52:16', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 11:22:00', NULL);
INSERT INTO `sys_app_menu` VALUES (992, 430, 'PROD_SHIFT_ROSTER', 'Jadwal Shift', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 10, 1, NULL, '2026-08-28 08:52:16', 'READY_FOR_QC', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (993, 420, 'PROD_MPS_SHEET', 'MPS - Spreadsheet', NULL, NULL, NULL, NULL, 'item', 'grid', 'grid_mps_spreadsheet.php', 'app', 13, 1, NULL, '2026-08-28 13:56:02', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 13:56:02', NULL);
INSERT INTO `sys_app_menu` VALUES (994, 924, 'HRM_LEAVE_APPROVAL', 'Approval Cuti/Sakit/Izin', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 30, 1, NULL, '2026-08-28 16:05:35', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-28 16:05:35', NULL);
INSERT INTO `sys_app_menu` VALUES (995, 975, 'HRM_RCR_REQUISITION', 'Permohonan Tenaga Kerja', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 10, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (996, 975, 'HRM_RCR_VACANCY', 'Posting Lowongan & Monitoring Channel', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 20, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (997, 975, 'HRM_RCR_TALENTPOOL', 'Talent Pool', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 30, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (998, 975, 'HRM_RCR_PIPELINE', 'Pipeline Board', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 40, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (999, 975, 'HRM_RCR_INTERVIEW', 'Jadwal Interview', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 50, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (1000, 975, 'HRM_RCR_ASSESSMENT', 'Input Hasil Psikotes/Interview', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 60, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (1001, 975, 'HRM_RCR_OFFER', 'Offering', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 70, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (1002, 975, 'HRM_RCR_SYNCLOG', 'Rekap Sync Log', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 80, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-09-02 05:23:56', NULL);
INSERT INTO `sys_app_menu` VALUES (1003, 975, 'HRM_RCR_PROVISION', 'Provisioning ke ERP', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 90, 1, NULL, '2026-08-29 02:09:39', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (1004, 974, 'HRM_PAYROLL_CALC', 'Perhitungan Gaji', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 10, 1, NULL, '2026-08-29 02:39:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-29 02:39:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1005, 924, 'HRM_OVERTIME', 'Lembur', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 40, 1, NULL, '2026-08-29 02:39:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-29 02:39:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1006, 921, 'HRM_ORG', 'Organization', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 5, 1, NULL, '2026-08-29 02:39:04', 'NOT_BUILT', NULL, NULL, NULL, NULL);
INSERT INTO `sys_app_menu` VALUES (1007, 1006, 'HRM_ORG_STRUCTURE', 'Master Posisi', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 10, 1, NULL, '2026-08-29 02:39:04', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 06:09:24', NULL);
INSERT INTO `sys_app_menu` VALUES (1008, 975, 'HRM_RCR_PORTAL', 'Portal Candidate', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 100, 1, NULL, '2026-08-29 02:52:22', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 02:52:22', NULL);
INSERT INTO `sys_app_menu` VALUES (1009, 1006, 'HRM_ORG_CHART', 'Struktur Organisasi', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 20, 1, NULL, '2026-08-29 06:09:24', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 06:09:24', NULL);
INSERT INTO `sys_app_menu` VALUES (1010, 9, 'SET_TICKETS', 'Support Tickets', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 70, 1, NULL, '2026-08-29 07:29:01', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-29 07:29:01', NULL);
INSERT INTO `sys_app_menu` VALUES (1011, 5, 'MD_CURRENCY', 'Master Currency', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 90, 1, NULL, '2026-08-30 01:14:55', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-30 01:14:55', NULL);
INSERT INTO `sys_app_menu` VALUES (1012, 3, 'INV_LBL', 'Label & QR Code', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 33, 1, NULL, '2026-08-31 04:57:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1013, 1012, 'INV_LBL_ITEM1', 'Print Pallet Label (QR)', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 10, 1, NULL, '2026-08-31 04:57:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1014, 1012, 'INV_LBL_ITEM2', 'Print Item Label (QR)', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 20, 1, NULL, '2026-08-31 04:57:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1015, 1012, 'INV_LBL_ITEM3', 'Label Print History', NULL, NULL, NULL, NULL, 'item', 'report', NULL, 'app', 30, 1, NULL, '2026-08-31 04:57:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1016, 3, 'INV_SCAN', 'Scan Center', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 36, 1, NULL, '2026-08-31 04:57:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1017, 1016, 'INV_SCAN_ITEM1', 'Scan Transaction (Mobile Camera)', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 10, 1, NULL, '2026-08-31 04:57:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1018, 1016, 'INV_SCAN_ITEM2', 'Track via QR/Barcode', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 20, 1, NULL, '2026-08-31 04:57:04', 'NOT_BUILT', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1019, 3, 'INV_REF', 'Related Master Data', NULL, NULL, NULL, NULL, 'group', NULL, NULL, 'app', 38, 1, NULL, '2026-08-31 04:57:04', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1020, 1019, 'INV_REF_RAW', 'Raw Material Master', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 10, 1, NULL, '2026-08-31 04:57:04', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1021, 1019, 'INV_REF_MATCAT', 'Material Category', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 20, 1, NULL, '2026-08-31 04:57:04', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1022, 1019, 'INV_REF_PROD', 'Product Master', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 30, 1, NULL, '2026-08-31 04:57:04', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1023, 1019, 'INV_REF_WH', 'Warehouse & Location', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 40, 1, NULL, '2026-08-31 04:57:04', 'READY_FOR_QC', NULL, 'claude_code', '2026-08-31 04:57:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1024, 9, 'SET_CONFIG', 'Settings', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 60, 1, NULL, '2026-09-02 01:28:58', 'READY_FOR_QC', NULL, 'claude_code', '2026-09-02 01:28:58', 'configurationstandard');
INSERT INTO `sys_app_menu` VALUES (1025, 5, 'MD_EXCHRATE', 'Master Kurs', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 91, 1, NULL, '2026-09-02 02:41:50', 'READY_FOR_QC', NULL, 'claude_code', '2026-09-02 02:41:50', NULL);
INSERT INTO `sys_app_menu` VALUES (1026, 5, 'MD_HOLIDAY', 'Master Hari Libur', NULL, NULL, NULL, NULL, 'item', 'form', NULL, 'app', 92, 1, NULL, '2026-09-02 03:41:59', 'READY_FOR_QC', NULL, 'claude_code', '2026-09-02 03:41:59', NULL);
INSERT INTO `sys_app_menu` VALUES (1027, 620, 'ACC_FIXEDASSET', 'Aset Tetap', NULL, NULL, NULL, NULL, 'item', 'grid', NULL, 'app', 7, 1, NULL, '2026-09-02 05:28:07', 'NOT_BUILT', NULL, 'claude_code', '2026-09-02 05:28:07', NULL);
INSERT INTO `sys_app_menu` VALUES (1028, 180, 'SALES_WINRATE', 'Win-Rate Sales', NULL, NULL, NULL, NULL, 'item', 'report', NULL, 'app', 15, 1, NULL, '2026-09-02 14:02:04', 'READY_FOR_QC', NULL, 'claude_code', '2026-09-02 14:02:04', NULL);
INSERT INTO `sys_app_menu` VALUES (1029, 940, 'SALES_DN', 'Delivery Notes', NULL, NULL, NULL, 'fa-solid fa-file-lines', 'item', 'grid', NULL, 'app', 20, 1, NULL, '2026-09-03 05:45:33', 'READY_FOR_QC', 'Reuse grid Delivery Order (dispatch.do.index) -- 1 tabel 2 cetakan (DO + Surat Jalan), belum ada layar terpisah.', 'claude_code', '2026-09-03 05:45:33', NULL);

-- ----------------------------
-- Table structure for sys_app_menu_color
-- ----------------------------
DROP TABLE IF EXISTS `sys_app_menu_color`;
CREATE TABLE `sys_app_menu_color`  (
  `status_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_hex` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_clickable` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`status_code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_app_menu_color
-- ----------------------------
INSERT INTO `sys_app_menu_color` VALUES ('LOCKED', '#16a34a', 'OK', 1, NULL, NULL);
INSERT INTO `sys_app_menu_color` VALUES ('NOT_BUILT', '#9ca3af', 'Belum Tersedia', 0, NULL, NULL);
INSERT INTO `sys_app_menu_color` VALUES ('READY_FOR_QC', '#f59e0b', 'Siap di-QC', 1, NULL, NULL);

-- ----------------------------
-- Table structure for sys_chat_conversations
-- ----------------------------
DROP TABLE IF EXISTS `sys_chat_conversations`;
CREATE TABLE `sys_chat_conversations`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `is_group` tinyint(1) NULL DEFAULT 0,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Header percakapan chat antar pengguna. is_group=0 (1:1) / 1 (grup); title=nama grup bila grup.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_chat_conversations
-- ----------------------------

-- ----------------------------
-- Table structure for sys_chat_messages
-- ----------------------------
DROP TABLE IF EXISTS `sys_chat_messages`;
CREATE TABLE `sys_chat_messages`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint NOT NULL,
  `sender` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_conv_time`(`conversation_id` ASC, `sent_at` ASC) USING BTREE,
  CONSTRAINT `sys_chat_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `sys_chat_conversations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Pesan chat. conversation_id -> sys_chat_conversations; sender = sec_user.login.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_chat_messages
-- ----------------------------

-- ----------------------------
-- Table structure for sys_chat_participants
-- ----------------------------
DROP TABLE IF EXISTS `sys_chat_participants`;
CREATE TABLE `sys_chat_participants`  (
  `conversation_id` bigint NOT NULL,
  `user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`conversation_id`, `user_id`) USING BTREE,
  CONSTRAINT `sys_chat_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `sys_chat_conversations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Peserta tiap percakapan (PK gabungan conversation_id + user_id); last_read_at utk menghitung pesan belum dibaca. user_id = sec_user.login.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_chat_participants
-- ----------------------------

-- ----------------------------
-- Table structure for sys_constant
-- ----------------------------
DROP TABLE IF EXISTS `sys_constant`;
CREATE TABLE `sys_constant`  (
  `id_constant` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `constant_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `constant_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `constant_number` decimal(20, 6) NULL DEFAULT NULL,
  `constant_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_constant`) USING BTREE,
  UNIQUE INDEX `sys_constant_constant_key_unique`(`constant_key` ASC) USING BTREE,
  INDEX `sys_constant_category_code_foreign`(`category_code` ASC) USING BTREE,
  CONSTRAINT `sys_constant_category_code_foreign` FOREIGN KEY (`category_code`) REFERENCES `sys_constant_category` (`code`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 45 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_constant
-- ----------------------------
INSERT INTO `sys_constant` VALUES (1, 'PLANO_REF_AREA', 'PRODUCTION', 'COSTING', 8611.000000, NULL, 'Luas plano referensi (cm2)', 1, '2026-08-13 12:12:59', '2026-08-13 12:12:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (2, 'RIM_SHEETS', 'PRODUCTION', 'COSTING', 500.000000, NULL, 'Jumlah lembar per rim', 1, '2026-08-13 12:12:59', '2026-08-13 12:12:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (3, 'FILM_RATE_DEFAULT', 'PRODUCTION', 'COSTING', 55.000000, NULL, 'Rate film default bila film_rate_id kosong', 1, '2026-08-13 12:12:59', '2026-08-13 12:12:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (4, 'FILM_FACTOR_DEFAULT', 'PRODUCTION', 'COSTING', 0.250000, NULL, 'Faktor film default (ff)', 1, '2026-08-13 12:12:59', '2026-08-13 12:12:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (5, 'PLATE_PER_COLOR', 'PRODUCTION', 'COSTING', 90000.000000, NULL, 'Rate plate per warna', 1, '2026-08-13 12:12:59', '2026-08-13 12:12:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (6, 'SHEET_MACHINE_WIDTH_CM', 'PRODUCTION', 'COSTING', 180.000000, NULL, 'Lebar mesin sheet (cm), dipakai hitung isi_trim', 1, '2026-08-13 14:20:31', '2026-08-13 14:20:31', NULL, NULL);
INSERT INTO `sys_constant` VALUES (7, 'SHEET_TRIM_GAP_CM', 'PRODUCTION', 'COSTING', 2.000000, NULL, 'Gap trim (cm)', 1, '2026-08-13 14:20:31', '2026-08-13 14:20:31', NULL, NULL);
INSERT INTO `sys_constant` VALUES (8, 'BANK_NAME', 'FINANCE', 'PAYMENT', NULL, NULL, 'Nama bank tujuan transfer di dokumen Quotation', 1, '2026-08-20 01:25:17', '2026-08-20 01:25:17', NULL, NULL);
INSERT INTO `sys_constant` VALUES (9, 'BANK_ACCOUNT_NAME', 'FINANCE', 'PAYMENT', NULL, NULL, 'Nama pemegang rekening di dokumen Quotation', 1, '2026-08-20 01:25:17', '2026-08-20 01:25:17', NULL, NULL);
INSERT INTO `sys_constant` VALUES (10, 'BANK_ACCOUNT_NO', 'FINANCE', 'PAYMENT', NULL, NULL, 'Nomor rekening di dokumen Quotation', 1, '2026-08-20 01:25:17', '2026-08-20 01:25:17', NULL, NULL);
INSERT INTO `sys_constant` VALUES (11, 'PPN', 'FINANCE', 'TAX', 11.000000, NULL, 'Tarif PPN (%)', 1, '2026-08-20 17:05:56', '2026-08-20 17:05:56', NULL, NULL);
INSERT INTO `sys_constant` VALUES (12, 'PPH23_JASA', 'FINANCE', 'TAX', 2.000000, NULL, 'Tarif PPh 23 atas jasa (%)', 1, '2026-08-20 17:05:56', '2026-08-20 17:05:56', NULL, NULL);
INSERT INTO `sys_constant` VALUES (13, 'PPH22_IMPOR_API', 'FINANCE', 'TAX', 2.500000, NULL, 'PPh 22 impor ber-API (%) - belum dipakai, po_import_doc INACTIVE', 1, '2026-08-20 17:05:56', '2026-08-20 17:05:56', NULL, NULL);
INSERT INTO `sys_constant` VALUES (14, 'RECEIVE_TOLERANCE_PCT', 'PROCUREMENT', 'PROCUREMENT', 5.000000, NULL, 'Toleransi terima default bila m_raw_material.tol_purchase kosong', 1, '2026-08-20 17:05:56', '2026-08-20 17:05:56', NULL, NULL);
INSERT INTO `sys_constant` VALUES (15, 'INV_ADJ_LIMIT_L1', 'INVENTORY', 'INV', 5000000.000000, NULL, 'Batas nilai Adjustment Kepala Gudang', 1, '2026-08-21 14:00:48', '2026-08-21 14:00:48', NULL, NULL);
INSERT INTO `sys_constant` VALUES (16, 'INV_ADJ_LIMIT_L2', 'INVENTORY', 'INV', 25000000.000000, NULL, 'Batas nilai Adjustment Manajer Operasional', 1, '2026-08-21 14:00:48', '2026-08-21 14:00:48', NULL, NULL);
INSERT INTO `sys_constant` VALUES (17, 'INV_ALLOW_NEGATIVE', 'INVENTORY', 'INV', 0.000000, NULL, '0 = posting OUT ditolak bila saldo jadi negatif', 1, '2026-08-21 14:00:48', '2026-08-21 14:00:48', NULL, NULL);
INSERT INTO `sys_constant` VALUES (18, 'INV_BACKDATE_DAYS', 'INVENTORY', 'INV', 7.000000, NULL, 'Maksimum hari mundur doc_date dalam periode OPEN', 1, '2026-08-21 14:00:48', '2026-08-21 14:00:48', NULL, NULL);
INSERT INTO `sys_constant` VALUES (19, 'INV_DEFAULT_COSTING', 'INVENTORY', 'INV', NULL, 'AVG', 'Metode harga perolehan default bila item belum diatur', 1, '2026-08-21 14:00:48', '2026-08-21 14:01:46', NULL, NULL);
INSERT INTO `sys_constant` VALUES (20, 'INV_REASON_ADJ', 'INVENTORY', 'INV', NULL, 'OPNAME_SCHEDULED,DAMAGED,SHRINKAGE,WRONG_ENTRY,LOST,FOUND,MIGRATION', 'Alasan Adjustment/Opname', 1, '2026-08-21 14:00:48', '2026-08-21 14:01:46', NULL, NULL);
INSERT INTO `sys_constant` VALUES (21, 'INV_REASON_RTP', 'INVENTORY', 'INV', NULL, 'SURPLUS,WO_CANCELLED,WRONG_ITEM,DEFECT_FOUND,SPEC_CHANGED', 'Alasan Return from Production', 1, '2026-08-21 14:00:48', '2026-08-21 14:01:46', NULL, NULL);
INSERT INTO `sys_constant` VALUES (22, 'INV_PROCESS_CODE', 'INVENTORY', 'INV', NULL, 'BELAH,CETAK,COATING,LAMINASI,PLONG,LEM,KUPAS,PACKING', 'Urutan proses produksi', 1, '2026-08-21 14:00:48', '2026-08-21 14:01:46', NULL, NULL);
INSERT INTO `sys_constant` VALUES (23, 'APP_NAME', 'BRANDING', 'BRANDING', NULL, 'ERP TM', 'Nama aplikasi (topbar, judul login, brand Filament) -- BUKAN nama company', 1, '2026-09-02 01:21:59', '2026-09-02 01:21:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (24, 'APP_TAGLINE', 'BRANDING', 'BRANDING', NULL, 'packaging solution', 'Tagline di bawah nama aplikasi', 1, '2026-09-02 01:21:59', '2026-09-02 01:21:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (25, 'APP_LOGO_PATH', 'BRANDING', 'BRANDING', NULL, NULL, 'Path storage logo aplikasi (kosong = pakai badge teks)', 1, '2026-09-02 01:21:59', '2026-09-02 01:21:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (26, 'APP_FONT_FAMILY', 'BRANDING', 'BRANDING', NULL, 'system-ui', 'Kunci preset font aplikasi (lihat FontPreset di Configuration)', 1, '2026-09-02 01:21:59', '2026-09-02 01:21:59', NULL, NULL);
INSERT INTO `sys_constant` VALUES (27, 'PRODEV_UPLOAD_MAX_MB', 'PRODUCTION', 'PRODEV', 25.000000, NULL, 'Batas maks ukuran file upload JPEG/PDF/AI di ProDev (MB). Mulai 25MB (user 2026-09-02), naikkan kalau file dieline AI kompleks kepotong.', 1, '2026-09-02 08:17:47', '2026-09-02 08:17:47', 'claude_code', NULL);
INSERT INTO `sys_constant` VALUES (28, 'PRODEV_INTERLOCK_SAVING_MIN_PCT', 'PRODUCTION', 'PRODEV', 12.000000, NULL, 'Estimasi hemat MIN Interlocking Nesting (%). Dari riset industri, BUKAN angka resmi Esko (dicek 2026-09-02, tak ketemu di docs.esko.com). Adjust stlh trial nyata.', 1, '2026-09-02 08:17:47', '2026-09-02 08:17:47', 'claude_code', NULL);
INSERT INTO `sys_constant` VALUES (29, 'PRODEV_INTERLOCK_SAVING_MAX_PCT', 'PRODUCTION', 'PRODEV', 22.000000, NULL, 'Estimasi hemat MAX Interlocking Nesting (%) -- pasangan PRODEV_INTERLOCK_SAVING_MIN_PCT, sumber & status sama.', 1, '2026-09-02 08:17:47', '2026-09-02 08:17:47', 'claude_code', NULL);
INSERT INTO `sys_constant` VALUES (33, 'PRODEV_FLAP_MIN_MM', 'PRODUCTION', 'PRODEV', 20.000000, NULL, 'Lidah (flap) minimum default, mm (2cm). SEMENTARA flat -- user 2026-09-02: seharusnya formula dari panjang/lebar tutup (blm diimplementasi), pakai sampai mat_flap_rule siap.', 1, '2026-09-02 08:18:10', '2026-09-02 08:18:10', 'claude_code', NULL);
INSERT INTO `sys_constant` VALUES (34, 'VERBA_SANCTA', 'PAYROLL', 'ENCRYPTION', 0.000000, 'telasihmakmur', 'Passphrase sumber kunci enkripsi at-rest basic_salary/net_salary (tm2pyr, BELUM dibangun) -- JANGAN diubah/dihapus sembarangan setelah modul Payroll live (butuh migrasi dekrip-enkrip ulang). Detail lengkap: docs/payroll_encryption_decision.md', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (35, 'FISCAL_YEAR_START_MONTH', 'FINANCE', 'PERIODE', 1.000000, NULL, 'Bulan mulai tahun buku accounting (1 = Januari)', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (36, 'FISCAL_YEAR_START_DAY', 'FINANCE', 'PERIODE', 1.000000, NULL, 'Tanggal mulai tahun buku accounting', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (37, 'FISCAL_YEAR_END_MONTH', 'FINANCE', 'PERIODE', 12.000000, NULL, 'Bulan akhir tahun buku accounting (12 = Desember)', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (38, 'FISCAL_YEAR_END_DAY', 'FINANCE', 'PERIODE', 31.000000, NULL, 'Tanggal akhir tahun buku accounting', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (39, 'STOCK_OPNAME_DAY_OF_MONTH', 'INVENTORY', 'PERIODE', 1.000000, NULL, 'Tanggal pelaksanaan stock opname tiap bulan (1 = tanggal 1)', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (40, 'PAYROLL_MONTHLY_CUTOFF_DAY', 'PAYROLL', 'PERIODE', 0.000000, 'END_OF_MONTH', 'Cutoff gajian bulanan = hari TERAKHIR tiap bulan (28-31, dinamis per bulan -- bukan angka tetap, kode harus hitung via Carbon endOfMonth(), bukan baca constant_number)', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (41, 'PAYROLL_WEEKLY_CUTOFF_DOW', 'PAYROLL', 'PERIODE', 5.000000, NULL, 'Hari cutoff gajian mingguan = Jumat (5, sama di Carbon dayOfWeek maupun dayOfWeekIso)', 1, NULL, NULL, NULL, NULL);
INSERT INTO `sys_constant` VALUES (42, 'PRODEV_GAP_MM', 'PRODUCTION', 'PRODEV', 3.000000, NULL, 'Jarak antar-unit dalam 1 plano (mm) -- dipakai ImpositionEngine::run()/sweepFlap() dst sbg parameter gap. Sebelumnya hardcode 3.0 di 4 tempat prodev.blade.php.', 1, '2026-09-03 08:25:58', '2026-09-03 08:25:58', 'claude_code', NULL);
INSERT INTO `sys_constant` VALUES (43, 'PRODEV_GRIPPER_MM', 'PRODUCTION', 'PRODEV', 12.000000, NULL, 'Gripper margin mesin cetak sheetfed (mm, area cengkeram tepi plano yg tidak bisa dicetak). Sebelumnya hardcode 12.0.', 1, '2026-09-03 08:25:58', '2026-09-03 08:25:58', 'claude_code', NULL);
INSERT INTO `sys_constant` VALUES (44, 'PRODEV_MARGIN_MM', 'PRODUCTION', 'PRODEV', 8.000000, NULL, 'Margin tepi plano di luar gripper (mm). Sebelumnya hardcode 8.0.', 1, '2026-09-03 08:25:58', '2026-09-03 08:25:58', 'claude_code', NULL);

-- ----------------------------
-- Table structure for sys_constant_category
-- ----------------------------
DROP TABLE IF EXISTS `sys_constant_category`;
CREATE TABLE `sys_constant_category`  (
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT 100,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_constant_category
-- ----------------------------
INSERT INTO `sys_constant_category` VALUES ('BRANDING', 'Identitas Aplikasi', 'Nama app, tagline, logo, font', 10, 1, '2026-09-02 01:21:17', '2026-09-02 01:21:17');
INSERT INTO `sys_constant_category` VALUES ('FINANCE', 'Keuangan', 'PPN, PPh, info bank/transfer', 20, 1, '2026-09-02 01:21:17', '2026-09-02 01:21:17');
INSERT INTO `sys_constant_category` VALUES ('INVENTORY', 'Inventory', 'Batas adjustment, alasan stok, dst', 50, 1, '2026-09-02 01:21:17', '2026-09-02 01:21:17');
INSERT INTO `sys_constant_category` VALUES ('PAYROLL', 'Payroll & Enkripsi', 'Parameter modul Payroll (belum dibangun) -- termasuk kunci enkripsi at-rest', 60, 1, NULL, NULL);
INSERT INTO `sys_constant_category` VALUES ('PROCUREMENT', 'Pengadaan', 'Toleransi terima, dst', 40, 1, '2026-09-02 01:21:17', '2026-09-02 01:21:17');
INSERT INTO `sys_constant_category` VALUES ('PRODUCTION', 'Produksi & Costing', 'Parameter costing (rim, plano, film, dst)', 30, 1, '2026-09-02 01:21:17', '2026-09-02 01:21:17');

-- ----------------------------
-- Table structure for sys_doc_reference
-- ----------------------------
DROP TABLE IF EXISTS `sys_doc_reference`;
CREATE TABLE `sys_doc_reference`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama file fisik lengkap di /docs, mis. globalrules.yaml',
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'GLOBAL_RULE / STANDARD / ARCHITECTURE / ENGINE / REFERENCE / SECURITY',
  `related_menu_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Referensi lunak ke sys_app_menu.code kalau ada kaitan tak langsung (BUKAN FK -- boleh NULL)',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `sort_order` smallint NOT NULL DEFAULT 999,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 46 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_doc_reference
-- ----------------------------
INSERT INTO `sys_doc_reference` VALUES (1, 'globalrules.yaml', 'Aturan Universal Semua Modul', 'GLOBAL_RULE', NULL, NULL, 10, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (2, 'globalstarthere.md', 'Pintu Masuk Dokumentasi (Start Here)', 'GLOBAL_RULE', NULL, NULL, 20, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (3, 'globalarchitecture_tier0.md', 'Rancangan Global Arsitektur (Tier 0)', 'ARCHITECTURE', NULL, NULL, 30, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (4, 'griduistandard.md', 'Standar Tampilan Grid (Master & Worklist)', 'STANDARD', NULL, NULL, 40, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (5, 'sidebarmenustandard.md', 'Standar Struktur Menu Sidebar', 'STANDARD', NULL, NULL, 50, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (6, 'prefixlist.md', 'Daftar Prefix Tabel (Naming Convention)', 'STANDARD', NULL, NULL, 60, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (7, 'queue.md', 'Antrean Kerja Claude Code', 'GLOBAL_RULE', NULL, NULL, 70, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (8, 'checklistmockuperp.md', 'Master Checklist Mockup ERP', 'GLOBAL_RULE', NULL, NULL, 80, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (9, 'flowengine_tier1.md', 'Flow Engine — Business Concept & Scope (Tier 1)', 'ENGINE', NULL, NULL, 90, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (10, 'flowengine_tier2.md', 'Flow Engine — Business Logic & Rules (Tier 2)', 'ENGINE', NULL, NULL, 91, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (11, 'flowengine_tier3.md', 'Flow Engine — Database & Data Process Flow (Tier 3)', 'ENGINE', NULL, NULL, 92, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (12, 'flowengine_tier4.md', 'Flow Engine — UI/UX & Interaction Design (Tier 4)', 'ENGINE', NULL, NULL, 93, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (13, 'openingapp_tier1.md', 'Opening App — System Flow & Process Matrix (Tier 1)', 'ENGINE', 'SETUP', NULL, 100, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (14, 'openingapp_tier2.md', 'Opening App — Business Logic & Rules (Tier 2)', 'ENGINE', 'SETUP', NULL, 101, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (15, 'openingapp_tier3.md', 'Opening App — DDL, Relasi & Seed (Tier 3)', 'ENGINE', 'SETUP', NULL, 102, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (16, 'openingapp_tier4.md', 'Opening App — Mockup & UI/UX Spec (Tier 4)', 'ENGINE', 'SETUP', NULL, 103, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (17, 'openingapp_instruksi_layout.md', 'Instruksi Layout Opening App', 'ENGINE', 'SETUP', NULL, 104, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (18, 'security_brief.md', 'Brief Modul Security: Login → Main Menu', 'SECURITY', 'SETUP', NULL, 110, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (19, 'security_defaultpassword_spec.md', 'Spec Default Password & Must Change Password', 'SECURITY', 'SETUP', NULL, 111, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (20, 'geomaster_ringkasan.md', 'Ringkasan Domain Master Wilayah (geo_)', 'REFERENCE', 'MDATA', NULL, 120, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (21, 'productionprocess_lampiran_a.md', 'Urutan Proses Standar Offset & Packaging', 'REFERENCE', 'PROD', NULL, 130, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (22, 'itemmaster_delta.md', 'Delta Item Master — Field Fisik & Linkage Akuntansi', 'REFERENCE', 'MD_RAW', NULL, 140, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (23, 'purchasereceive_delta_multipo.md', 'Delta Purchase Receive — Multi-PO per SPB', 'REFERENCE', 'PROC_RCV', NULL, 150, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (24, 'customer_fix_nav_address.md', 'Fix Master Customer — Navigasi Tab & Alamat Wilayah', 'REFERENCE', 'MD_CUST', NULL, 160, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (25, 'inquiryproduct_delta_revise.md', 'Delta Revise Scope & Relasi CO Snapshot', 'REFERENCE', 'SALES_INQ', NULL, 170, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (26, 'inquiryproduct_flow.md', 'Siklus Inquiry → PO → Master Product', 'REFERENCE', 'SALES_INQ', NULL, 171, 1, '2026-08-26 07:11:37');
INSERT INTO `sys_doc_reference` VALUES (27, 'matcategory_delta.md', 'Delta Master Kategori Bahan Baku — Pengganti doc_rmprefix1..5', 'REFERENCE', 'MD_MATCAT', NULL, 141, 1, '2026-08-26 08:25:05');
INSERT INTO `sys_doc_reference` VALUES (28, 'fieldrename_state.yaml', 'Progres Rename is_active/is_confirmed (DEC-FIELDNAME-STATUS)', 'GLOBAL_RULE', NULL, NULL, 61, 1, '2026-08-27 00:54:14');
INSERT INTO `sys_doc_reference` VALUES (29, 'topmanagementreport_tier1.md', 'Laporan Top Management — Konsep & Cakupan (Tier 1)', 'ENGINE', NULL, NULL, 95, 1, '2026-08-27 06:23:50');
INSERT INTO `sys_doc_reference` VALUES (30, 'ganttboardstandard.md', 'Standar Komponen Gantt Board (Reusable)', 'STANDARD', NULL, 'Kontrak Row/Block VM, props <x-gantt.board>, dan Alpine store gantt utk semua layar Gantt (mesin/waktu) ke depan.', 45, 1, '2026-08-27 23:52:15');
INSERT INTO `sys_doc_reference` VALUES (31, 'printnotastandard.md', 'Standar Dokumen Cetak Nota (Reusable)', 'STANDARD', NULL, 'Komponen print.document/sheet/header/parties/section/signatures/footer + kontrak tema warna utk semua dokumen cetak nota (kop+parties+signature) ke depan.', 46, 1, '2026-08-28 00:20:37');
INSERT INTO `sys_doc_reference` VALUES (32, 'production_scheduling_wo_generation_delta.md', 'Delta WO Generation dari Plan Delivery + Layar Work Order', 'ENGINE', 'PROD_WO_LIST', NULL, 172, 1, '2026-08-28 04:10:31');
INSERT INTO `sys_doc_reference` VALUES (33, 'FORM_UI_STANDARD.md', 'Standar Tampilan Form/Entry (header, tombol Back)', 'STANDARD', NULL, 'Header navy + tombol Back pill untuk semua layar form/entry (CO Entry, PR/PO/PV, Master Warehouse, dst). Sudah lama dipakai, baru terdaftar di sini.', 41, 1, '2026-08-30 00:04:49');
INSERT INTO `sys_doc_reference` VALUES (34, 'kanbanboardstandard.md', 'Standar Kanban Board (Pola Reusable)', 'STANDARD', NULL, 'Pola drag-drop kartu antar kolom status/kategori (x-data draggedId, dragover.prevent, validasi server ketat) diekstrak dari Pipeline Board + Monitoring Dashboard.', 47, 1, '2026-08-30 00:04:49');
INSERT INTO `sys_doc_reference` VALUES (35, 'admindashboardstandard.md', 'Standar Admin Dashboard (Panel Filament /admin)', 'STANDARD', NULL, 'Pemetaan Module->Cluster/Group->navigationGroup/Item->Resource, auth bridge login/pswd, ->topNavigation(), dan cara aman bikin Resource baru tanpa crash generator.', 48, 1, '2026-08-30 00:04:49');
INSERT INTO `sys_doc_reference` VALUES (36, 'uistandardindex.md', 'Index Standar UI/UX (Pintu Masuk)', 'STANDARD', NULL, 'Peta semua dokumen *standard.md UI/UX (grid/form/nota/gantt/kanban/admin dashboard) + kapan masing-masing wajib dibaca.', 39, 1, '2026-08-30 00:04:49');
INSERT INTO `sys_doc_reference` VALUES (37, 'spreadsheetgridstandard.md', 'Standar Grid Spreadsheet-Editable (Adopsi AutoFill)', 'STANDARD', NULL, 'Klik sel jadi editable, drag-fill copy nilai ke bawah, \"+ Baris\" langsung dari tabel, simpan batch. Versi ringan buatan sendiri (Alpine+Livewire) dari perilaku AutoFill DataTables.net -- HANYA master kecil/scalar (Currency, UOM).', 49, 1, '2026-08-30 01:23:46');
INSERT INTO `sys_doc_reference` VALUES (38, 'recruitment_delta_plan_ab_local.md', 'Delta Portal Kandidat Plan A/B (implementasi lokal)', 'REFERENCE', NULL, 'Delta atas recruitment_tier3.md §1-3: skema crp_submission diperluas, interface adapter ditambah sisi-portal, Firebase pakai Local Emulator.', 173, 1, '2026-08-30 06:26:14');
INSERT INTO `sys_doc_reference` VALUES (39, 'recruitmentportal_web_tier1.md', 'Portal Rekrutmen Profesional (Web)', 'STANDARD', 'HRM_RCR_PORTAL', 'Portal kandidat publik (Laravel+Firebase), akun real, di /hrm/recruitment/portal -- menggantikan simulasi KTP-only lama.', 174, 1, '2026-08-30 07:20:20');
INSERT INTO `sys_doc_reference` VALUES (40, 'themestandard.md', 'Standar Tema (Light/Dark/Pastel/Cerah x Corak Warna)', 'STANDARD', NULL, 'Matrix 4 mode x 3 corak tersimpan di tm2sys.sys_theme (family/hue), dipilih user lewat grid picker di dropdown avatar. Cakupan: shell lama saja, belum halaman iframe/Filament admin.', 50, 1, '2026-08-31 05:23:33');
INSERT INTO `sys_doc_reference` VALUES (41, 'configurationstandard.md', 'Standar Configuration (Konstanta Sistem, Branding, Bahasa)', 'STANDARD', 'SET_CONFIG', 'Layar Configuration shell lama (di atas grup Security) -- struktur sys_constant/sys_constant_category, identitas aplikasi (APP_NAME/logo/font), bahasa & terjemahan (sys_languages/sys_translations), fin_exchange_rate, geo_country.flag_emoji.', 52, 1, '2026-09-02 01:47:20');
INSERT INTO `sys_doc_reference` VALUES (42, 'reportstandard.md', 'Standar Report 4 Level (Kerangka, Belum Diimplementasi)', 'STANDARD', NULL, 'Kerangka konsep report Data Entry/Supervisor/Manager/Top Management + graphics report -- BELUM ada layar report sungguhan dibangun, scoping menyusul terpisah.', 53, 1, '2026-09-02 01:47:20');
INSERT INTO `sys_doc_reference` VALUES (43, 'BACKLOG.md', 'Backlog Lintas-Modul (Fase 2 Report + Semua Item Tertunda)', 'TRACKING', NULL, 'Gabungan semua pekerjaan direncanakan tapi belum dikerjakan -- Fase 2 Report 4-level + item lama tiap modul. Verifikasi ulang ke kode sebelum eksekusi.', 5, 1, '2026-09-02 01:57:51');
INSERT INTO `sys_doc_reference` VALUES (44, 'LOG_JOB_DONE.md', 'Log Riwayat Pekerjaan Selesai', 'TRACKING', NULL, 'Arsip item BACKLOG.md yang sudah selesai dikerjakan, dipisah supaya backlog tetap ramping. Urutan terbaru di atas.', 6, 1, '2026-09-02 03:30:38');
INSERT INTO `sys_doc_reference` VALUES (45, 'payroll_encryption_decision.md', 'Payroll -- Keputusan Desain Enkripsi', 'SECURITY', NULL, 'Scope kolom, lokasi kunci (VERBA_SANCTA), algoritma, dampak ke laporan -- dibaca sebelum mulai bangun modul Payroll', 999, 1, NULL);

-- ----------------------------
-- Table structure for sys_grid_preference
-- ----------------------------
DROP TABLE IF EXISTS `sys_grid_preference`;
CREATE TABLE `sys_grid_preference`  (
  `id_grid_preference` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_login` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grid_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible_columns` json NOT NULL,
  `sort_column` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `sort_direction` enum('asc','desc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `group_by_column` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_grid_preference`) USING BTREE,
  UNIQUE INDEX `sys_grid_preference_user_login_grid_key_unique`(`user_login` ASC, `grid_key` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_grid_preference
-- ----------------------------
INSERT INTO `sys_grid_preference` VALUES (10, 'johiwan', 'sales.co-list', '[\"quotation.quotation_no\", \"co_date\", \"customer_po_no\"]', 'co_date', 'asc', NULL, '2026-08-27 10:52:22');
INSERT INTO `sys_grid_preference` VALUES (11, 'johiwan', 'master.raw-material', '[\"item_type\", \"stat_confirm\", \"category1\", \"cur_stock\", \"avg_unitprice\"]', 'stat_confirm', 'asc', NULL, '2026-08-30 02:58:32');

-- ----------------------------
-- Table structure for sys_languages
-- ----------------------------
DROP TABLE IF EXISTS `sys_languages`;
CREATE TABLE `sys_languages`  (
  `code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `decimal_separator` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ',',
  `thousand_separator` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '.',
  `is_default` tinyint(1) NULL DEFAULT 0,
  PRIMARY KEY (`code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Daftar bahasa aktif untuk i18n. PK=code (id/en); is_default=bahasa awal. Tabel induk dari sys_translations.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_languages
-- ----------------------------
INSERT INTO `sys_languages` VALUES ('en', 'English', '.', ',', 1);
INSERT INTO `sys_languages` VALUES ('id', 'Bahasa Indonesia', ',', '.', 0);

-- ----------------------------
-- Table structure for sys_login_hero
-- ----------------------------
DROP TABLE IF EXISTS `sys_login_hero`;
CREATE TABLE `sys_login_hero`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_login_hero
-- ----------------------------
INSERT INTO `sys_login_hero` VALUES (1, '/images/login-hero/hero-01.png', 'Mesin cetak offset modern', 10, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (2, '/images/login-hero/hero-02.png', 'Digital printing presisi tinggi', 20, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (3, '/images/login-hero/hero-03.png', 'Line produksi corrugated box', 30, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (4, '/images/login-hero/hero-04.png', 'Quality control kemasan', 40, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (5, '/images/login-hero/hero-05.png', 'Finishing & packing produk jadi', 50, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (6, '/images/login-hero/hero-06.png', 'Warehouse & logistik pengiriman', 60, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (7, '/images/login-hero/hero-07.png', 'Proses laminasi & coating', 70, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (8, '/images/login-hero/hero-08.png', 'Tim produksi Telasih Makmur', 80, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (9, '/images/login-hero/hero-09.png', 'Cetak warna presisi tinggi', 90, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (10, '/images/login-hero/hero-10.png', 'Packaging solution end-to-end', 100, 1, '2026-08-20 13:04:11');
INSERT INTO `sys_login_hero` VALUES (11, '/images/login-hero/hero-11.png', 'Quality control kemasan', 110, 1, '2026-08-20 13:04:11');

-- ----------------------------
-- Table structure for sys_notifications
-- ----------------------------
DROP TABLE IF EXISTS `sys_notifications`;
CREATE TABLE `sys_notifications`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `type` enum('info','success','warning','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'info',
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_read` tinyint(1) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_unread`(`user_id` ASC, `is_read` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 136 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Notifikasi (lonceng/user menu). user_id=login penerima, NULL=broadcast semua user; link=tujuan saat diklik; is_read=status baca.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_notifications
-- ----------------------------
INSERT INTO `sys_notifications` VALUES (1, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a78251267b00) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-09 06:58:26');
INSERT INTO `sys_notifications` VALUES (2, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7958e83c2fc) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-10 04:51:52');
INSERT INTO `sys_notifications` VALUES (3, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7a036968d55) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-10 16:59:21');
INSERT INTO `sys_notifications` VALUES (4, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7a0392b5e2f) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-10 17:00:02');
INSERT INTO `sys_notifications` VALUES (5, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d2a115469b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 02:21:06');
INSERT INTO `sys_notifications` VALUES (6, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d374c2dae7) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 03:17:33');
INSERT INTO `sys_notifications` VALUES (7, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d39dbbf216) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 03:28:27');
INSERT INTO `sys_notifications` VALUES (8, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d46b766efb) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 04:23:19');
INSERT INTO `sys_notifications` VALUES (9, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d497bdd3d1) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 04:35:08');
INSERT INTO `sys_notifications` VALUES (10, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d5c2b6f1f7) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 05:54:51');
INSERT INTO `sys_notifications` VALUES (11, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d606fa261e) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 06:13:03');
INSERT INTO `sys_notifications` VALUES (12, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d6bead10c9) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 07:02:02');
INSERT INTO `sys_notifications` VALUES (13, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d72cc37d78) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 07:31:24');
INSERT INTO `sys_notifications` VALUES (14, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d74352d264) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 07:37:25');
INSERT INTO `sys_notifications` VALUES (15, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a7d7fb7cc1a2) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-13 08:26:31');
INSERT INTO `sys_notifications` VALUES (16, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a80210c29625) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-15 08:19:26');
INSERT INTO `sys_notifications` VALUES (17, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a802eb3ecf67) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-15 09:17:40');
INSERT INTO `sys_notifications` VALUES (18, 'admin', 'PR2600002 menunggu approval Anda', 'Purchase Requisition senilai Rp 10.000.000 diajukan Budi Santoso, menunggu persetujuan Manajer Operasional.', 'warning', '/purchasing/pr-approval', 0, '2026-08-20 21:20:42');
INSERT INTO `sys_notifications` VALUES (19, 'admin', 'PO2600001 berhasil diterbitkan', 'Purchase Order ke KAWI CTP  senilai Rp 12.765.000 sudah masuk antrian approval.', 'success', '/purchasing/po', 0, '2026-08-20 21:20:42');
INSERT INTO `sys_notifications` VALUES (20, NULL, 'Pemeliharaan sistem terjadwal', 'Server akan restart singkat Minggu dini hari 02:00-02:15 WIB untuk pembaruan rutin.', 'info', NULL, 1, '2026-08-20 21:20:42');
INSERT INTO `sys_notifications` VALUES (21, 'admin', 'Quotation QUO2600001 sudah 30 hari sejak terbit', 'Masa berlaku penawaran mendekati batas -- pertimbangkan follow-up ke customer.', 'warning', '/quotation', 1, '2026-08-20 21:20:42');
INSERT INTO `sys_notifications` VALUES (22, 'admin', 'Gagal sinkronisasi data kurs harian', 'fin_currency_rate tidak terupdate otomatis hari ini -- input manual bila diperlukan.', 'error', NULL, 1, '2026-08-20 21:20:42');
INSERT INTO `sys_notifications` VALUES (23, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a8fa0867e260) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-27 02:27:19');
INSERT INTO `sys_notifications` VALUES (26, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a90e263bbbdf) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 01:20:36');
INSERT INTO `sys_notifications` VALUES (27, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a90ead726171) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 01:56:39');
INSERT INTO `sys_notifications` VALUES (28, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a90eb15eed82) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 01:57:42');
INSERT INTO `sys_notifications` VALUES (29, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a90fbad3f570) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 03:08:29');
INSERT INTO `sys_notifications` VALUES (30, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a90fc17d81e6) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 03:10:15');
INSERT INTO `sys_notifications` VALUES (31, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a910495580ae) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 03:46:29');
INSERT INTO `sys_notifications` VALUES (32, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a910ef9ed5cc) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 04:30:50');
INSERT INTO `sys_notifications` VALUES (33, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a910f2f91144) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 04:31:43');
INSERT INTO `sys_notifications` VALUES (34, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91104e8682d) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 04:36:30');
INSERT INTO `sys_notifications` VALUES (35, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91213123891) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 05:48:33');
INSERT INTO `sys_notifications` VALUES (36, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91215cb9b2e) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 05:49:16');
INSERT INTO `sys_notifications` VALUES (37, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9138720566b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 07:27:46');
INSERT INTO `sys_notifications` VALUES (38, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9138c46bdfd) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 07:29:08');
INSERT INTO `sys_notifications` VALUES (39, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a913c9d761a6) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 07:45:33');
INSERT INTO `sys_notifications` VALUES (40, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a913f3a1315d) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 07:56:42');
INSERT INTO `sys_notifications` VALUES (41, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9141a080375) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 08:06:56');
INSERT INTO `sys_notifications` VALUES (42, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91473015d68) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 08:30:40');
INSERT INTO `sys_notifications` VALUES (43, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9148fee72d2) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 08:38:23');
INSERT INTO `sys_notifications` VALUES (44, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a914927ed8b9) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 08:39:04');
INSERT INTO `sys_notifications` VALUES (45, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9168ae5ad8e) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 10:53:34');
INSERT INTO `sys_notifications` VALUES (46, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a916f9d6b694) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 11:23:09');
INSERT INTO `sys_notifications` VALUES (47, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a917d360eed2) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 12:21:10');
INSERT INTO `sys_notifications` VALUES (48, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a917d360eed2) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 12:21:10');
INSERT INTO `sys_notifications` VALUES (49, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9184143f538) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 12:50:28');
INSERT INTO `sys_notifications` VALUES (50, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9184143f538) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 12:50:28');
INSERT INTO `sys_notifications` VALUES (51, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9184b7d1b3b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 12:53:11');
INSERT INTO `sys_notifications` VALUES (52, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9184b7d1b3b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 12:53:11');
INSERT INTO `sys_notifications` VALUES (53, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a918e83c256f) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 13:35:00');
INSERT INTO `sys_notifications` VALUES (54, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a918e83c256f) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 13:35:00');
INSERT INTO `sys_notifications` VALUES (55, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91942e46eae) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 13:59:10');
INSERT INTO `sys_notifications` VALUES (56, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91942e46eae) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 13:59:10');
INSERT INTO `sys_notifications` VALUES (57, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9194c7c9a7b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:01:43');
INSERT INTO `sys_notifications` VALUES (58, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9194c7c9a7b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:01:43');
INSERT INTO `sys_notifications` VALUES (59, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91952f130a1) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:03:27');
INSERT INTO `sys_notifications` VALUES (60, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91952f130a1) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:03:27');
INSERT INTO `sys_notifications` VALUES (61, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a919735b6308) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:12:05');
INSERT INTO `sys_notifications` VALUES (62, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a919735b6308) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:12:05');
INSERT INTO `sys_notifications` VALUES (63, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91992faaf8a) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:20:31');
INSERT INTO `sys_notifications` VALUES (64, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91992faaf8a) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:20:31');
INSERT INTO `sys_notifications` VALUES (65, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9199c2331ec) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:22:58');
INSERT INTO `sys_notifications` VALUES (66, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9199c2331ec) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:22:58');
INSERT INTO `sys_notifications` VALUES (67, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a919aa1246e8) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:26:41');
INSERT INTO `sys_notifications` VALUES (68, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a919aa1246e8) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:26:41');
INSERT INTO `sys_notifications` VALUES (69, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a919d511816c) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:38:09');
INSERT INTO `sys_notifications` VALUES (70, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a919d511816c) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 14:38:09');
INSERT INTO `sys_notifications` VALUES (71, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91b165e61fd) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 16:03:50');
INSERT INTO `sys_notifications` VALUES (72, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a91b165e61fd) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 16:03:50');
INSERT INTO `sys_notifications` VALUES (73, 'johiwan', 'Pengajuan disetujui', 'Pengajuan CUTI tanggal 05 Sep 2026 s/d 07 Sep 2026 disetujui HRD.', 'success', NULL, 0, '2026-08-28 16:10:33');
INSERT INTO `sys_notifications` VALUES (74, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920eab7fdff) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:41:48');
INSERT INTO `sys_notifications` VALUES (75, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920eab7fdff) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:41:48');
INSERT INTO `sys_notifications` VALUES (76, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920f5cb21b4) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:44:44');
INSERT INTO `sys_notifications` VALUES (77, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920f5cb21b4) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:44:44');
INSERT INTO `sys_notifications` VALUES (78, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920faf59471) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:46:07');
INSERT INTO `sys_notifications` VALUES (79, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920faf59471) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:46:07');
INSERT INTO `sys_notifications` VALUES (80, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920fd04d9fd) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:46:40');
INSERT INTO `sys_notifications` VALUES (81, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a920fd04d9fd) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-28 22:46:40');
INSERT INTO `sys_notifications` VALUES (82, 'annisa', 'Jadwal Interview Baru', 'Anda dijadwalkan interview kandidat Kandidat Interview Test pada 01 Sep 2026 02:55.', 'info', NULL, 0, '2026-08-29 02:55:48');
INSERT INTO `sys_notifications` VALUES (83, 'annisa', 'Jadwal Interview Baru', 'Anda dijadwalkan interview kandidat Kandidat Kalender pada 10 Sep 2026 14:00.', 'info', NULL, 0, '2026-08-29 05:57:42');
INSERT INTO `sys_notifications` VALUES (84, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93770fed6ba) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:19:29');
INSERT INTO `sys_notifications` VALUES (85, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93770fed6ba) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:19:29');
INSERT INTO `sys_notifications` VALUES (86, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a937784c10b4) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:21:24');
INSERT INTO `sys_notifications` VALUES (87, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a937784c10b4) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:21:24');
INSERT INTO `sys_notifications` VALUES (88, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93785edf414) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:25:03');
INSERT INTO `sys_notifications` VALUES (89, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93785edf414) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:25:03');
INSERT INTO `sys_notifications` VALUES (90, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93791e834b1) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:28:14');
INSERT INTO `sys_notifications` VALUES (91, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93791e834b1) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 00:28:14');
INSERT INTO `sys_notifications` VALUES (92, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93bfe6ec57e) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 05:30:15');
INSERT INTO `sys_notifications` VALUES (93, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93bfe6ec57e) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 05:30:15');
INSERT INTO `sys_notifications` VALUES (94, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93cdebf102a) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 06:30:04');
INSERT INTO `sys_notifications` VALUES (95, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93cdebf102a) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 06:30:04');
INSERT INTO `sys_notifications` VALUES (96, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93d688dbdef) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:06:49');
INSERT INTO `sys_notifications` VALUES (97, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93d688dbdef) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:06:49');
INSERT INTO `sys_notifications` VALUES (98, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93d812c15ed) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:13:22');
INSERT INTO `sys_notifications` VALUES (99, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93d812c15ed) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:13:22');
INSERT INTO `sys_notifications` VALUES (100, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93d9486306b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:18:32');
INSERT INTO `sys_notifications` VALUES (101, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93d9486306b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:18:32');
INSERT INTO `sys_notifications` VALUES (102, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93e0ed5910c) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:51:09');
INSERT INTO `sys_notifications` VALUES (103, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93e0ed5910c) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 07:51:09');
INSERT INTO `sys_notifications` VALUES (104, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93e35bc9f1a) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 08:01:31');
INSERT INTO `sys_notifications` VALUES (105, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a93e35bc9f1a) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 08:01:31');
INSERT INTO `sys_notifications` VALUES (106, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a943ee60469b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 14:32:07');
INSERT INTO `sys_notifications` VALUES (107, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a943ee60469b) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 14:32:07');
INSERT INTO `sys_notifications` VALUES (108, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9442708f5b1) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 14:47:12');
INSERT INTO `sys_notifications` VALUES (109, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9442708f5b1) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 14:47:12');
INSERT INTO `sys_notifications` VALUES (110, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9445b49b5f7) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 15:01:08');
INSERT INTO `sys_notifications` VALUES (111, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9445b49b5f7) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-30 15:01:08');
INSERT INTO `sys_notifications` VALUES (112, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94dc4b7889c) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 01:43:40');
INSERT INTO `sys_notifications` VALUES (113, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94dc4b7889c) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 01:43:40');
INSERT INTO `sys_notifications` VALUES (114, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94ddd2bb65d) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 01:50:10');
INSERT INTO `sys_notifications` VALUES (115, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94ddd2bb65d) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 01:50:10');
INSERT INTO `sys_notifications` VALUES (116, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94e45216396) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 02:17:54');
INSERT INTO `sys_notifications` VALUES (117, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94e45216396) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 02:17:54');
INSERT INTO `sys_notifications` VALUES (118, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94ed8705944) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 02:57:11');
INSERT INTO `sys_notifications` VALUES (119, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a94ed8705944) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-08-31 02:57:11');
INSERT INTO `sys_notifications` VALUES (120, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d36d8fb3e) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 01:54:54');
INSERT INTO `sys_notifications` VALUES (121, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d36d8fb3e) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 01:54:54');
INSERT INTO `sys_notifications` VALUES (122, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d3f171193) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 01:57:05');
INSERT INTO `sys_notifications` VALUES (123, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d3f171193) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 01:57:05');
INSERT INTO `sys_notifications` VALUES (124, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d42841dc6) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 01:58:00');
INSERT INTO `sys_notifications` VALUES (125, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d42841dc6) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 01:58:00');
INSERT INTO `sys_notifications` VALUES (126, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d6f0d7378) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 02:09:53');
INSERT INTO `sys_notifications` VALUES (127, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98d6f0d7378) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 02:09:53');
INSERT INTO `sys_notifications` VALUES (128, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98f8568d07d) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 04:32:22');
INSERT INTO `sys_notifications` VALUES (129, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a98f8568d07d) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 04:32:22');
INSERT INTO `sys_notifications` VALUES (130, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a99046f833a4) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 05:23:59');
INSERT INTO `sys_notifications` VALUES (131, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a99046f833a4) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 05:23:59');
INSERT INTO `sys_notifications` VALUES (132, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a990ff7e87e3) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 06:13:12');
INSERT INTO `sys_notifications` VALUES (133, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a990ff7e87e3) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 06:13:12');
INSERT INTO `sys_notifications` VALUES (134, 'johiwan', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9917aef17bb) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 06:46:07');
INSERT INTO `sys_notifications` VALUES (135, 'admin', 'Pendaftaran baru menunggu approval', 'Register Smoke Test (test.register.6a9917aef17bb) mendaftar sebagai customer.', 'info', 'http://localhost/security?tab=users', 0, '2026-09-03 06:46:07');

-- ----------------------------
-- Table structure for sys_quotes
-- ----------------------------
DROP TABLE IF EXISTS `sys_quotes`;
CREATE TABLE `sys_quotes`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `quote_text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Kata-kata penyemangat di halaman utama & login. Ambil acak: WHERE is_active=1 ORDER BY RAND() LIMIT 1. is_active=nonaktif tanpa hapus.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_quotes
-- ----------------------------
INSERT INTO `sys_quotes` VALUES (1, 'Cara memulai adalah berhenti bicara dan mulai melakukan.', 'Walt Disney', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (2, 'Kesuksesan adalah hasil dari persiapan, kerja keras, dan belajar dari kegagalan.', 'Colin Powell', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (3, 'Disiplin adalah jembatan antara tujuan dan pencapaian.', 'Jim Rohn', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (4, 'Kerja keras mengalahkan bakat ketika bakat tidak bekerja keras.', 'Tim Notke', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (5, 'Sedikit demi sedikit, lama-lama menjadi bukit.', 'Peribahasa', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (6, 'Fokus pada progres, bukan kesempurnaan.', 'Anonim', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (7, 'Jangan menunggu waktu yang tepat. Mulailah, dan jadikan waktumu tepat.', 'Anonim', 1, '2026-08-08 17:24:02');
INSERT INTO `sys_quotes` VALUES (8, 'Hari ini harus lebih baik daripada kemarin.', 'Anonim', 1, '2026-08-08 17:24:02');

-- ----------------------------
-- Table structure for sys_setting
-- ----------------------------
DROP TABLE IF EXISTS `sys_setting`;
CREATE TABLE `sys_setting`  (
  `setting_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`setting_key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Setting umum aplikasi (key-value): active_theme, default_language, app_version, dll. Dipakai sebagai fallback level-aplikasi (mis. tema default bila user belum memilih).' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_setting
-- ----------------------------
INSERT INTO `sys_setting` VALUES ('app_version', '1.0');
INSERT INTO `sys_setting` VALUES ('default_language', 'id');
INSERT INTO `sys_setting` VALUES ('notify_channel', 'manual');

-- ----------------------------
-- Table structure for sys_support_replies
-- ----------------------------
DROP TABLE IF EXISTS `sys_support_replies`;
CREATE TABLE `sys_support_replies`  (
  `id_reply` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint UNSIGNED NOT NULL,
  `author` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sec_user.login/name pembalas -- staff atau candidate sendiri',
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reply`) USING BTREE,
  INDEX `idx_reply_ticket`(`ticket_id` ASC) USING BTREE,
  CONSTRAINT `sys_support_replies_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `sys_support_ticket` (`id_ticket`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_support_replies
-- ----------------------------

-- ----------------------------
-- Table structure for sys_support_ticket
-- ----------------------------
DROP TABLE IF EXISTS `sys_support_ticket`;
CREATE TABLE `sys_support_ticket`  (
  `id_ticket` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format SPT{YY}{MM}{NNNN}, digenerate app',
  `created_by_user_id` bigint UNSIGNED NOT NULL,
  `screen_context` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Label tab aktif saat submit (auto)',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN|IN_PROGRESS|DONE|CLOSED -- selaras qc_feedback.md',
  `resolution_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `resolved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `resolved_at` datetime NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_ticket`) USING BTREE,
  UNIQUE INDEX `sys_support_ticket_ticket_no_unique`(`ticket_no` ASC) USING BTREE,
  INDEX `sys_support_ticket_created_by_user_id_foreign`(`created_by_user_id` ASC) USING BTREE,
  INDEX `idx_ticket_status`(`status` ASC) USING BTREE,
  CONSTRAINT `sys_support_ticket_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `sec_user` (`id_user`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_support_ticket
-- ----------------------------
INSERT INTO `sys_support_ticket` VALUES (1, 'SPT26080001', 1, 'Pipeline Board — test', 'Tombol Lanjutkan tidak muncul di kolom Offering', 'DONE', 'Sudah diperbaiki, tombol dipindah ke posisi benar.', 'johiwan', '2026-08-29 07:30:41', '2026-08-29 07:30:41', '2026-08-29 07:30:41');
INSERT INTO `sys_support_ticket` VALUES (2, 'SPT26080002', 1, 'Pipeline Board — test', 'Tombol Lanjutkan tidak muncul di kolom Offering', 'DONE', 'Sudah diperbaiki, tombol dipindah ke posisi benar.', 'johiwan', '2026-08-29 07:31:57', '2026-08-29 07:31:57', '2026-08-29 07:31:57');

-- ----------------------------
-- Table structure for sys_support_ticket_attachment
-- ----------------------------
DROP TABLE IF EXISTS `sys_support_ticket_attachment`;
CREATE TABLE `sys_support_ticket_attachment`  (
  `id_attachment` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint UNSIGNED NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` datetime NOT NULL,
  PRIMARY KEY (`id_attachment`) USING BTREE,
  INDEX `sys_support_ticket_attachment_ticket_id_foreign`(`ticket_id` ASC) USING BTREE,
  CONSTRAINT `sys_support_ticket_attachment_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `sys_support_ticket` (`id_ticket`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_support_ticket_attachment
-- ----------------------------

-- ----------------------------
-- Table structure for sys_theme
-- ----------------------------
DROP TABLE IF EXISTS `sys_theme`;
CREATE TABLE `sys_theme`  (
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `primary_color` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sidebar_color` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `mode` enum('light','dark') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `family` enum('pastel','cerah','dark','hitam') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Baris matrix tema. NULL = tema \"Klasik\" lama, tak ikut grid picker utama.',
  `hue` enum('blue','green','red') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kolom matrix tema (corak). NULL = tema \"Klasik\" lama.',
  `content_bg_color` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Override warna latar area konten. NULL = pakai default per mode (light/dark).',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `activate` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Preset tema tampilan (code, primary_color, sidebar_color, is_default). Tema aktif per-user disimpan di sec_user.theme_code; default aplikasi di sys_setting.active_theme.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_theme
-- ----------------------------
INSERT INTO `sys_theme` VALUES ('cerah_red', 'Cerah Merah', '#e5484d', '#3a1414', 'light', 'cerah', 'red', NULL, 0, 1, 150);
INSERT INTO `sys_theme` VALUES ('dark_blue', 'Dark Biru', '#4c85ff', '#101b30', 'dark', 'dark', 'blue', '#12182c', 0, 1, 210);
INSERT INTO `sys_theme` VALUES ('dark_green', 'Dark Hijau', '#34d399', '#0b1f16', 'dark', 'dark', 'green', '#0f1c17', 0, 1, 220);
INSERT INTO `sys_theme` VALUES ('dark_red', 'Dark Merah', '#ff6b6b', '#2a1416', 'dark', 'dark', 'red', '#1f1518', 0, 1, 230);
INSERT INTO `sys_theme` VALUES ('emerald', 'Cerah Hijau (Emerald)', '#16a34a', '#123a26', 'light', 'cerah', 'green', NULL, 0, 1, 50);
INSERT INTO `sys_theme` VALUES ('hitam_blue', 'Hitam Biru', '#5b9dff', '#0a0a0c', 'dark', 'hitam', 'blue', '#060608', 0, 1, 310);
INSERT INTO `sys_theme` VALUES ('hitam_green', 'Hitam Hijau', '#2ee6a6', '#0a0a0c', 'dark', 'hitam', 'green', '#060608', 0, 1, 320);
INSERT INTO `sys_theme` VALUES ('hitam_red', 'Hitam Merah', '#ff5c5c', '#0a0a0c', 'dark', 'hitam', 'red', '#060608', 0, 1, 330);
INSERT INTO `sys_theme` VALUES ('indigo', 'Indigo', '#3b3a8f', '#26265a', 'light', NULL, NULL, NULL, 0, 1, 60);
INSERT INTO `sys_theme` VALUES ('navy', 'Cerah Biru (Navy)', '#2f6fed', '#1f2742', 'light', 'cerah', 'blue', NULL, 1, 1, 10);
INSERT INTO `sys_theme` VALUES ('pastel_blue', 'Pastel Biru', '#6aa3e8', '#1f2742', 'light', 'pastel', 'blue', '#f2f6fc', 0, 1, 110);
INSERT INTO `sys_theme` VALUES ('pastel_green', 'Pastel Hijau', '#7fae8f', '#1c2e26', 'light', 'pastel', 'green', '#f2f7f3', 0, 1, 120);
INSERT INTO `sys_theme` VALUES ('pastel_red', 'Pastel Merah', '#d98fa0', '#2f2028', 'light', 'pastel', 'red', '#faf3f5', 0, 1, 130);
INSERT INTO `sys_theme` VALUES ('plum', 'Plum', '#5b3a67', '#3a2743', 'light', NULL, NULL, NULL, 0, 1, 40);
INSERT INTO `sys_theme` VALUES ('slate', 'Slate', '#3f4753', '#2b2f3a', 'light', NULL, NULL, NULL, 0, 1, 30);
INSERT INTO `sys_theme` VALUES ('teal', 'Teal', '#1f6f6b', '#173a38', 'light', NULL, NULL, NULL, 0, 1, 20);

-- ----------------------------
-- Table structure for sys_translations
-- ----------------------------
DROP TABLE IF EXISTS `sys_translations`;
CREATE TABLE `sys_translations`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `lang_code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trans_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trans_value` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_lang_key`(`lang_code` ASC, `trans_key` ASC) USING BTREE,
  CONSTRAINT `sys_translations_ibfk_1` FOREIGN KEY (`lang_code`) REFERENCES `sys_languages` (`code`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 33 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Teks UI per bahasa. lang_code -> sys_languages.code; trans_key=kunci teks (unik per bahasa). Tambah bahasa = tambah baris per trans_key.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_translations
-- ----------------------------
INSERT INTO `sys_translations` VALUES (1, 'id', 'brand_tagline', 'Sistem ERP terintegrasi.');
INSERT INTO `sys_translations` VALUES (2, 'en', 'brand_tagline', 'Integrated ERP system.');
INSERT INTO `sys_translations` VALUES (3, 'id', 'login_heading', 'Masuk');
INSERT INTO `sys_translations` VALUES (4, 'en', 'login_heading', 'Sign in');
INSERT INTO `sys_translations` VALUES (5, 'id', 'login_sub', 'Gunakan akun terdaftar untuk masuk.');
INSERT INTO `sys_translations` VALUES (6, 'en', 'login_sub', 'Use your registered account to sign in.');
INSERT INTO `sys_translations` VALUES (7, 'id', 'label_username', 'Username atau email');
INSERT INTO `sys_translations` VALUES (8, 'en', 'label_username', 'Username or email');
INSERT INTO `sys_translations` VALUES (9, 'id', 'label_password', 'Password');
INSERT INTO `sys_translations` VALUES (10, 'en', 'label_password', 'Password');
INSERT INTO `sys_translations` VALUES (11, 'id', 'remember', 'Ingat saya');
INSERT INTO `sys_translations` VALUES (12, 'en', 'remember', 'Remember me');
INSERT INTO `sys_translations` VALUES (13, 'id', 'forgot', 'Lupa password?');
INSERT INTO `sys_translations` VALUES (14, 'en', 'forgot', 'Forgot password?');
INSERT INTO `sys_translations` VALUES (15, 'id', 'btn_login', 'Masuk');
INSERT INTO `sys_translations` VALUES (16, 'en', 'btn_login', 'Sign in');
INSERT INTO `sys_translations` VALUES (17, 'id', 'err_empty', 'Isi username dan password.');
INSERT INTO `sys_translations` VALUES (18, 'en', 'err_empty', 'Enter your username and password.');
INSERT INTO `sys_translations` VALUES (19, 'id', 'err_wrong', 'Username atau password salah.');
INSERT INTO `sys_translations` VALUES (20, 'en', 'err_wrong', 'Incorrect username or password.');
INSERT INTO `sys_translations` VALUES (21, 'id', 'fp_heading', 'Lupa password');
INSERT INTO `sys_translations` VALUES (22, 'en', 'fp_heading', 'Forgot password');
INSERT INTO `sys_translations` VALUES (23, 'id', 'fp_sub', 'Masukkan email akun Anda. Kami kirim tautan untuk atur ulang password.');
INSERT INTO `sys_translations` VALUES (24, 'en', 'fp_sub', 'Enter your account email. We will send a reset link.');
INSERT INTO `sys_translations` VALUES (25, 'id', 'label_email', 'Email');
INSERT INTO `sys_translations` VALUES (26, 'en', 'label_email', 'Email');
INSERT INTO `sys_translations` VALUES (27, 'id', 'btn_send', 'Kirim tautan reset');
INSERT INTO `sys_translations` VALUES (28, 'en', 'btn_send', 'Send reset link');
INSERT INTO `sys_translations` VALUES (29, 'id', 'back_login', 'Kembali ke halaman masuk');
INSERT INTO `sys_translations` VALUES (30, 'en', 'back_login', 'Back to sign in');
INSERT INTO `sys_translations` VALUES (31, 'id', 'fp_done', 'Jika email terdaftar, tautan reset sudah dikirim.');
INSERT INTO `sys_translations` VALUES (32, 'en', 'fp_done', 'If the email is registered, a reset link has been sent.');

-- ----------------------------
-- Table structure for sys_user_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_user_menu`;
CREATE TABLE `sys_user_menu`  (
  `id_umenu` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
  `section` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Judul kelompok di dropdown (mis. \"Pesan & info\", \"Akun\"). Kosong/NULL = tanpa header (mis. tombol Keluar).',
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode unik stabil item, dipakai kode aplikasi (mis. UM_CHAT).',
  `label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Teks yang tampil di menu.',
  `icon` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Kelas ikon Font Awesome (mis. fa-solid fa-comment-dots).',
  `action_type` enum('drawer','modal','menu','url','lang','logout') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'url' COMMENT 'Jenis aksi saat diklik: drawer=buka panel geser | modal=buka pop-up | menu=buka objek di sys_app_menu | url=arahkan ke halaman | lang=ganti bahasa | logout=keluar.',
  `action_target` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sasaran aksi sesuai action_type: id drawer (chatDrawer) | id modal (mEdit) | code sys_app_menu (SET_USER) | URL/route (support.php) | login.html utk logout. NULL utk lang.',
  `badge_source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Sumber angka badge di item: \"chat\" (sys_chat_*), \"notif\" (sys_notifications), atau NULL bila tak berbadge.',
  `is_danger` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=tampil merah/menonjol (mis. Keluar aplikasi).',
  `admin_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=hanya tampil untuk admin/berwenang (mis. Pengaturan/Setup). Tetap wajib dicek izin di server.',
  `visibility` enum('all','employee_linked','non_employee_linked','setup_non_admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT 999 COMMENT 'Urutan tampil (kecil di atas).',
  `activate` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=tampil, 0=disembunyikan tanpa dihapus.',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_umenu`) USING BTREE,
  UNIQUE INDEX `uq_umenu_code`(`code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Menu profil (dropdown avatar): chat, notifikasi, AI, edit profil, ubah password, setup, bahasa, keluar. Item & urutan diatur dari tabel ini.' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_user_menu
-- ----------------------------
INSERT INTO `sys_user_menu` VALUES (1, 'Pesan & Info', 'UM_CHAT', 'Chat', 'fa-solid fa-comment-dots', 'drawer', 'chatOpen', 'chat', 0, 0, 'all', 10, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');
INSERT INTO `sys_user_menu` VALUES (2, 'Pesan & Info', 'UM_NOTIF', 'Notifikasi', 'fa-solid fa-bell', 'drawer', 'notifOpen', 'notif', 0, 0, 'all', 20, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');
INSERT INTO `sys_user_menu` VALUES (3, 'Pesan & Info', 'UM_AI', 'Asisten AI', 'fa-solid fa-wand-magic-sparkles', 'drawer', 'aiOpen', NULL, 0, 0, 'all', 30, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');
INSERT INTO `sys_user_menu` VALUES (4, 'Akun', 'UM_MYSECURITY', 'My Security', 'fa-solid fa-shield-halved', 'url', 'self-service.index', NULL, 0, 0, 'employee_linked', 40, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');
INSERT INTO `sys_user_menu` VALUES (5, 'Akun', 'UM_EDITPROFIL', 'Edit profil', 'fa-solid fa-user-pen', 'modal', 'profileOpen', NULL, 0, 0, 'non_employee_linked', 50, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');
INSERT INTO `sys_user_menu` VALUES (6, 'Akun', 'UM_PASSWORD', 'Ubah password', 'fa-solid fa-key', 'modal', 'passwordOpen', NULL, 0, 0, 'non_employee_linked', 60, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');
INSERT INTO `sys_user_menu` VALUES (7, 'Akun', 'UM_SETUP', 'Pengaturan (Setup)', 'fa-solid fa-gear', 'menu', 'SET_USER', NULL, 0, 0, 'setup_non_admin', 70, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');
INSERT INTO `sys_user_menu` VALUES (8, 'Akun', 'UM_HELP', 'Bantuan & Support', 'fa-solid fa-circle-question', 'url', 'help.index', NULL, 0, 0, 'all', 80, 1, '2026-09-03 09:47:51', '2026-09-03 09:47:51');

SET FOREIGN_KEY_CHECKS = 1;
