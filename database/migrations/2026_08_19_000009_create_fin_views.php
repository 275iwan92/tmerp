<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// TIER_3A_FINANCE.sql §8 — VIEW turunan (tak disimpan). v1_fin_account_tree
// = sumber grid Master COA (tree_level + default_cc_code, FIN_TIER_4.md §2).
// Sisanya dipakai laporan modul Voucher/AR-AP yang menyusul.
return new class extends Migration
{
    public function up(): void
    {
        $acc = DB::connection('tm2acc');

        $acc->statement(<<<'SQL'
            CREATE VIEW v1_fin_account_tree AS
            SELECT a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                   a.parent_id, a.is_recon, a.is_postable, a.default_cc_id, a.tax_scheme_code, a.is_active,
                   CASE WHEN a.parent_id IS NULL THEN 1
                        WHEN p.parent_id IS NULL THEN 2 ELSE 3 END AS tree_level,
                   cc.cc_code AS default_cc_code
            FROM fin_account a
            LEFT JOIN fin_account p    ON p.account_id = a.parent_id
            LEFT JOIN fin_cost_center cc ON cc.cc_id   = a.default_cc_id
            SQL);

        $acc->statement(<<<'SQL'
            CREATE VIEW v1_fin_trial_balance AS
            SELECT a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
                   COALESCE(SUM(l.debit),0)  AS total_debit,
                   COALESCE(SUM(l.credit),0) AS total_credit,
                   COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0) AS balance
            FROM fin_account a
            LEFT JOIN fin_voucher_line l ON l.account_id = a.account_id
            LEFT JOIN fin_voucher v ON v.voucher_id = l.voucher_id
               AND v.status='POSTED' AND v.is_cancelled=0
            WHERE a.is_postable = 1
            GROUP BY a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance
            SQL);

        $acc->statement(<<<'SQL'
            CREATE VIEW v1_fin_ar_aging AS
            SELECT i.invoice_id, i.invoice_number, i.partner_id, i.co_id, i.invoice_date, i.due_date,
                   i.total_amount, i.paid_amount, (i.total_amount - i.paid_amount) AS outstanding,
                   DATEDIFF(CURRENT_DATE, i.due_date) AS days_overdue, i.payment_status
            FROM fin_invoice i
            WHERE i.invoice_type='CUSTOMER_INVOICE' AND i.payment_status <> 'PAID'
            SQL);

        $acc->statement(<<<'SQL'
            CREATE VIEW v1_fin_ap_aging AS
            SELECT i.invoice_id, i.invoice_number, i.partner_id, i.source_type, i.source_id,
                   i.invoice_date, i.due_date, i.total_amount, i.paid_amount,
                   (i.total_amount - i.paid_amount) AS outstanding,
                   DATEDIFF(CURRENT_DATE, i.due_date) AS days_overdue, i.payment_status
            FROM fin_invoice i
            WHERE i.invoice_type='SUPPLIER_BILL' AND i.payment_status <> 'PAID'
            SQL);
    }

    public function down(): void
    {
        $acc = DB::connection('tm2acc');

        $acc->statement('DROP VIEW IF EXISTS v1_fin_ap_aging');
        $acc->statement('DROP VIEW IF EXISTS v1_fin_ar_aging');
        $acc->statement('DROP VIEW IF EXISTS v1_fin_trial_balance');
        $acc->statement('DROP VIEW IF EXISTS v1_fin_account_tree');
    }
};
