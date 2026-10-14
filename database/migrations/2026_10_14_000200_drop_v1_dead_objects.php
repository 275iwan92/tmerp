<?php

/*
 * REMARK
 * Tujuan       : Membuang objek database sisa versi 1 yang terbukti tak terpakai (4 view + tabel prd_mts_forecast), tanpa dualisme.
 * Sumber hulu  : Audit 2026-10-14: 0 rujukan kode, 0 FK, tidak dipakai view lain, 0 baris; layar v1 MTS dan Trial Balance sudah dibuang (50e71827).
 * Input        : -
 * Output       : Objek di bawah dibuang bila ada (aman dijalankan ulang). down() membuatnya kembali dari definisi yang tertanam.
 * Dipakai oleh : -
 * Status       : ddt* dijalankan sesi; tm2* oleh Jo. Cadangan definisi: folder tmerp_backup/2026-10-14_v1_tables di profil pengguna.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** [connection, object, T|V, CREATE statement (schema names written as [[connection]])] */
    private function objects(): array
    {
        return [
            ['tm2acc', 'v1_fin_trial_balance', 'V', <<<'SQL'
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v1_fin_trial_balance` AS select `a`.`account_id` AS `account_id`,`a`.`account_code` AS `account_code`,`a`.`account_name` AS `account_name`,`a`.`account_type` AS `account_type`,`a`.`normal_balance` AS `normal_balance`,coalesce(sum(`l`.`debit`),0) AS `total_debit`,coalesce(sum(`l`.`credit`),0) AS `total_credit`,(coalesce(sum(`l`.`debit`),0) - coalesce(sum(`l`.`credit`),0)) AS `balance` from ((`fin_account` `a` left join `fin_voucher_line` `l` on((`l`.`account_id` = `a`.`account_id`))) left join `fin_voucher` `v` on(((`v`.`voucher_id` = `l`.`voucher_id`) and (`v`.`status` = 'POSTED') and (`v`.`stat_cancel` = 0)))) where (`a`.`is_postable` = 1) group by `a`.`account_id`,`a`.`account_code`,`a`.`account_name`,`a`.`account_type`,`a`.`normal_balance`
SQL],
            ['tm2prd', 'v_prd_customer_interruption_profile', 'V', <<<'SQL'
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prd_customer_interruption_profile` AS select `prd_decision_feedback_log`.`context_customer_id` AS `context_customer_id`,count(0) AS `total_interruption`,`prd_decision_feedback_log`.`option_chosen` AS `option_chosen`,count(0) AS `count_per_option` from `prd_decision_feedback_log` where ((`prd_decision_feedback_log`.`event_type` = 'INTERRUPTION') and (`prd_decision_feedback_log`.`context_customer_id` is not null)) group by `prd_decision_feedback_log`.`context_customer_id`,`prd_decision_feedback_log`.`option_chosen`
SQL],
            ['tm2prd', 'v_prd_exception_dashboard', 'V', <<<'SQL'
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prd_exception_dashboard` AS select `o`.`operation_id` AS `operation_id`,`o`.`wo_id` AS `wo_id`,`o`.`process_name` AS `process_name`,`o`.`machine_id` AS `machine_id`,`o`.`target_start` AS `target_start`,`o`.`target_finish` AS `target_finish`,`o`.`critical_ratio` AS `critical_ratio`,`o`.`task_status` AS `task_status`,(case when ((`o`.`task_status` = 'PENDING') and (`o`.`target_start` < now())) then 1 else 0 end) AS `is_unscheduled_risk`,(case when (`o`.`critical_ratio` < 1) then 1 else 0 end) AS `is_at_risk` from `prd_wo_operation` `o` where (`o`.`task_status` not in ('DONE','QC_PASSED'))
SQL],
            ['tm2inv', 'v_rm_out_returnable', 'V', <<<'SQL'
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_rm_out_returnable` AS select `h`.`doc_id` AS `doc_id`,`h`.`doc_no` AS `doc_no`,`h`.`doc_date` AS `doc_date`,`h`.`wo_id` AS `wo_id`,`h`.`ref_doc_no` AS `ref_doc_no`,`d`.`doc_detail_id` AS `doc_detail_id`,`d`.`material_id` AS `material_id`,`m`.`material_code` AS `material_code`,`m`.`material_name` AS `material_name`,`d`.`lot_no` AS `lot_no`,`d`.`location_id` AS `location_id`,`l`.`location_name` AS `location_name`,`d`.`uom_code` AS `uom_code`,`d`.`action_type` AS `action_type`,`d`.`qty` AS `qty_issued`,`d`.`qty_returned` AS `qty_returned`,(`d`.`qty` - `d`.`qty_returned`) AS `qty_returnable` from (((`rm_stock` `h` join `rm_stock_d` `d` on((`d`.`doc_id` = `h`.`doc_id`))) left join `[[tm2mst]]`.`mat_raw` `m` on((`m`.`material_id` = `d`.`material_id`))) left join `[[tm2mst]]`.`wh_loc` `l` on((`l`.`location_id` = `d`.`location_id`))) where ((`h`.`doc_type` = -(1)) and (`h`.`doc_cat` = 'STOCK') and (`h`.`flow_status` = 'POSTED') and (`d`.`action_type` in ('ISSUE','SUBSTITUTE')) and ((`d`.`qty` - `d`.`qty_returned`) > 0))
SQL],
            ['tm2prd', 'prd_mts_forecast', 'T', <<<'SQL'
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
  `data_origin` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LIVE' COMMENT 'LIVE | DUMMY | LEGACY',
  `data_ready` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = memenuhi syarat wajib data saat ini',
  `data_origin_manual` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = asal data ditentukan manual, jangan diubah aturan',
  PRIMARY KEY (`forecast_id`),
  KEY `idx_prd_forecast_product_period` (`product_id`,`period_start`),
  KEY `prd_mts_forecast_data_origin_index` (`data_origin`)
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL],
        ];
    }

    public function up(): void
    {
        foreach ($this->objects() as [$conn, $name, $type]) {
            DB::connection($conn)->statement('DROP '.($type === 'V' ? 'VIEW' : 'TABLE').' IF EXISTS `'.$name.'`');
        }
    }

    public function down(): void
    {
        foreach ($this->objects() as [$conn, $name, $type, $sql]) {
            $c = DB::connection($conn);
            if ($c->select('select 1 from information_schema.TABLES where TABLE_SCHEMA = ? and TABLE_NAME = ?', [$c->getDatabaseName(), $name])) {
                continue;
            }
            $sql = preg_replace_callback('/\[\[(tm2[a-z]+)\]\]/', fn ($m) => config('database.connections.'.$m[1].'.database'), $sql);
            $c->statement($sql);
        }
    }
};
