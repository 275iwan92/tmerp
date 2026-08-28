<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migration 2026_08_27_000001 me-rename is_active->activate & is_cancelled->
// stat_cancel di tabel dasar tm2acc, TAPI ada 2 VIEW yang ikut menyebut kolom
// lama di definisinya (SELECT langsung merujuk nama kolom, bukan lewat *) --
// ALTER TABLE RENAME COLUMN tidak menyentuh VIEW, jadi keduanya rusak
// (invalid) sampai definisinya digambar ulang di sini. Ditemukan via audit
// SHOW CREATE VIEW di semua VIEW tm2mst/tm2acc/tm2sdi/tm2pur/tm2inv/tm2sys --
// hanya 2 VIEW tm2acc ini yang kena (tm2mst/tm2inv bersih).
return new class extends Migration
{
    public function up(): void
    {
        $c = DB::connection('tm2acc');

        $c->statement('DROP VIEW IF EXISTS `v1_fin_account_tree`');
        $c->statement("
            CREATE VIEW `v1_fin_account_tree` AS
            SELECT
                a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                a.parent_id, a.is_recon, a.is_postable, a.default_cc_id, a.tax_scheme_code,
                a.activate,
                (CASE WHEN a.parent_id IS NULL THEN 1 WHEN p.parent_id IS NULL THEN 2 ELSE 3 END) AS tree_level,
                cc.cc_code AS default_cc_code
            FROM fin_account a
            LEFT JOIN fin_account p ON p.account_id = a.parent_id
            LEFT JOIN fin_cost_center cc ON cc.cc_id = a.default_cc_id
        ");

        $c->statement('DROP VIEW IF EXISTS `v1_fin_trial_balance`');
        $c->statement("
            CREATE VIEW `v1_fin_trial_balance` AS
            SELECT
                a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                COALESCE(SUM(l.debit), 0) AS total_debit,
                COALESCE(SUM(l.credit), 0) AS total_credit,
                (COALESCE(SUM(l.debit), 0) - COALESCE(SUM(l.credit), 0)) AS balance
            FROM fin_account a
            LEFT JOIN fin_voucher_line l ON l.account_id = a.account_id
            LEFT JOIN fin_voucher v ON v.voucher_id = l.voucher_id AND v.status = 'POSTED' AND v.stat_cancel = 0
            WHERE a.is_postable = 1
            GROUP BY a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance
        ");
    }

    public function down(): void
    {
        $c = DB::connection('tm2acc');

        $c->statement('DROP VIEW IF EXISTS `v1_fin_account_tree`');
        $c->statement("
            CREATE VIEW `v1_fin_account_tree` AS
            SELECT
                a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                a.parent_id, a.is_recon, a.is_postable, a.default_cc_id, a.tax_scheme_code,
                a.is_active,
                (CASE WHEN a.parent_id IS NULL THEN 1 WHEN p.parent_id IS NULL THEN 2 ELSE 3 END) AS tree_level,
                cc.cc_code AS default_cc_code
            FROM fin_account a
            LEFT JOIN fin_account p ON p.account_id = a.parent_id
            LEFT JOIN fin_cost_center cc ON cc.cc_id = a.default_cc_id
        ");

        $c->statement('DROP VIEW IF EXISTS `v1_fin_trial_balance`');
        $c->statement("
            CREATE VIEW `v1_fin_trial_balance` AS
            SELECT
                a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                COALESCE(SUM(l.debit), 0) AS total_debit,
                COALESCE(SUM(l.credit), 0) AS total_credit,
                (COALESCE(SUM(l.debit), 0) - COALESCE(SUM(l.credit), 0)) AS balance
            FROM fin_account a
            LEFT JOIN fin_voucher_line l ON l.account_id = a.account_id
            LEFT JOIN fin_voucher v ON v.voucher_id = l.voucher_id AND v.status = 'POSTED' AND v.is_cancelled = 0
            WHERE a.is_postable = 1
            GROUP BY a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance
        ");
    }
};
