---
name: db_acc
description: Skema database Finance & Accounting (COA, voucher jurnal, invoice AR/AP, DP allocation, cost/profit center) modul tm2acc
---

# Database: tm2acc (koneksi Eloquent: `acc`)

**Sumber DDL**: `docs/ddl/tm2acc.sql`
**Jumlah tabel**: 8

## Daftar Tabel

### `fin_account`
Chart of Accounts (COA), self-referencing hierarki.
- PK: `account_id` (int unsigned, PK manual app-layer -- bukan AUTO_INCREMENT, sesuai memory `project_finance_module_status.md`)
- Kolom: `account_code` (unik), `account_name`, `account_type` (enum ASSET/LIABILITY/EQUITY/REVENUE/COGS/EXPENSE/OTHER_INCOME/OTHER_EXPENSE), `normal_balance` (enum DEBIT/CREDIT), `parent_id` (self-FK), `is_recon`, `is_postable`, `default_cc_id`, `tax_scheme_code`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `parent_id -> fin_account` (self, RESTRICT)
- Kemungkinan FK logis lintas-DB: -

### `fin_cost_center`
Master cost center (pusat biaya), dikelompokkan per fungsi (produksi/sales/dst) & bisa terhubung ke departemen org.
- PK: `cc_id`
- Kolom: `cc_code` (unik), `cc_name`, `cc_group` (enum PRODUCTION/SALES/PURCHASING/PREPRESS/QC/WAREHOUSE/FINANCE/TAX/GENERAL), `org_dept_id`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: -
- Kemungkinan FK logis lintas-DB: `org_dept_id` -> tm2mst.org_department

### `fin_profit_center`
Master profit center (mis. PC-PACKAGING, PC-COMMERCIAL).
- PK: `pc_id`
- Kolom: `pc_code` (unik), `pc_name`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: -
- Kemungkinan FK logis lintas-DB: -

### `fin_partner_account`
Pemetaan partner (customer/supplier) ke akun AR/AP masing-masing + payment term default.
- PK: `map_id`
- Kolom: `partner_type` (enum CUSTOMER/SUPPLIER), `partner_id` (LOGICAL -> tm2mst.cust_customer atau sup_supplier tergantung partner_type), `ar_account_id`, `ap_account_id`, `payment_term` (default NET30), `activate`, `created_at/by`, `updated_at/by`
- FK internal: `ap_account_id -> fin_account`, `ar_account_id -> fin_account` (keduanya RESTRICT), unique(`partner_type`,`partner_id`)
- Kemungkinan FK logis lintas-DB: `partner_id` -> tm2mst.cust_customer / tm2mst.sup_supplier (polymorphic by partner_type)

### `fin_journal`
Master jenis jurnal (Sales/Purchase/Cash-Bank/General).
- PK: `journal_id`
- Kolom: `journal_code` (unik), `journal_name`, `journal_type` (enum SALES/PURCHASE/CASH_BANK/GENERAL), `activate`, `created_at/by`, `updated_at/by`
- FK internal: -
- Kemungkinan FK logis lintas-DB: -

### `fin_voucher`
Header voucher jurnal (BKM/BKK/BBM/BBK, dan jenis lain) -- dokumen transaksi utama modul finance.
- PK: `voucher_id`
- Kolom: `voucher_number` (unik), `journal_id`, `voucher_type` (varchar10: BKM|BKK|BBM|BBK), `main_account_id` (LOGICAL -> fin_account, akun kas/bank utama), `entry_date`, `posting_date`, `doc_type` (enum NEW/RECALC/REVISE, default NEW), `revision_no`, `source_type`, `source_id`, `reference_no`, `partner_name`, `description` (text), `status` (enum DRAFT/POSTED/CANCELLED), `stat_cancel`, `cancel_reason`, `posted_by`, `posted_at`, `created_at/by`, `updated_at/by`, `ppn_type` (NONE|MASUKAN_11|KELUARAN_11), `efaktur_no`, `pph_type` (NONE|PPH23_2|PPH4_2_10|PPH21), `bupot_no`
- FK internal: `journal_id -> fin_journal` (RESTRICT)
- Kemungkinan FK logis lintas-DB: `source_type`+`source_id` polymorphic ke dokumen sumber lintas modul (mis. tm2pur.pv_receipt, tm2sdi.co_order)

### `fin_voucher_line`
Baris jurnal double-entry per voucher, dgn CHECK constraint memastikan hanya salah satu debit/credit terisi.
- PK: `line_id`
- Kolom: `voucher_id`, `account_id`, `partner_type` (enum CUSTOMER/SUPPLIER), `partner_id`, `cc_id`, `co_id` (LOGICAL -> tm2sdi.co_order, penelusuran biaya per CO), `debit`, `credit`, `narration`
- FK internal: `account_id -> fin_account` (RESTRICT), `cc_id -> fin_cost_center` (RESTRICT), `voucher_id -> fin_voucher` (CASCADE), CHECK `chk_dr_cr`: (debit>0 AND credit=0) OR (credit>0 AND debit=0) OR (debit=0 AND credit=0)
- Kemungkinan FK logis lintas-DB: `partner_id` -> tm2mst.cust_customer/sup_supplier, `co_id` -> tm2sdi.co_order

### `fin_invoice`
Header invoice AR/AP (customer invoice, supplier bill, DP customer/supplier) -- termasuk tracking status pembayaran.
- PK: `invoice_id`
- Kolom: `invoice_number` (unik), `invoice_type` (enum CUSTOMER_INVOICE/SUPPLIER_BILL/CUSTOMER_DP/SUPPLIER_DP), `partner_type` (enum CUSTOMER/SUPPLIER), `partner_id`, `source_type`, `source_id`, `co_id` (LOGICAL -> tm2sdi.co_order), `invoice_date`, `due_date`, `subtotal`, `tax_amount`, `total_amount`, `paid_amount`, `voucher_id`, `payment_status` (enum UNPAID/PARTIAL/PAID), `created_at/by`, `updated_at/by`
- FK internal: `voucher_id -> fin_voucher` (SET NULL)
- Kemungkinan FK logis lintas-DB: `partner_id` -> tm2mst.cust_customer/sup_supplier, `co_id` -> tm2sdi.co_order, `source_type`+`source_id` polymorphic

### `fin_dp_allocation`
Alokasi Down Payment (DP) ke invoice final -- relasi M:N antar invoice, sisa DP = total - SUM(base+tax).
- PK: `allocation_id`
- Kolom: `dp_invoice_id` (FK fin_invoice, invoice tipe *_DP), `final_invoice_id` (FK fin_invoice, invoice final), `allocated_base` (porsi pokok DP dipotong), `allocated_tax` (porsi PPN DP dikreditkan), `allocation_date`, `created_at/by`, `updated_at/by`
- FK internal: `dp_invoice_id -> fin_invoice` (RESTRICT), `final_invoice_id -> fin_invoice` (RESTRICT), CHECK `chk_dpa_not_self`: dp_invoice_id <> final_invoice_id
- Kemungkinan FK logis lintas-DB: -

## Catatan
- `fin_account.account_id` adalah `int unsigned NOT NULL` **tanpa** AUTO_INCREMENT -- PK di-generate manual di application layer (Laravel), sesuai memory `project_finance_module_status.md` ("account_id manual PK app-layer"). Ini pola LOCKED, jangan diubah jadi auto_increment tanpa keputusan user.
- Modul finance sudah pakai konvensi kolom penuh sesuai CLAUDE.md §3 terbaru: `activate` (bukan is_active) di semua tabel master, `stat_cancel`+`cancel_reason` di `fin_voucher`, `created_at/by`+`updated_at/by` konsisten di semua tabel.
- Ada 2 pasangan CHECK constraint eksplisit di skema ini (`chk_dr_cr` di `fin_voucher_line`, `chk_dpa_not_self` di `fin_dp_allocation`) -- jarang dipakai di modul lain, menandakan integritas double-entry & DP-allocation dijaga di level DB juga (bukan cuma Laravel validation).
- `fin_voucher.doc_type` (NEW/RECALC/REVISE) mengikuti pola 3-mode InqProd yang sama disebut CLAUDE.md §4 (BARU/REVISI/REKALKULASI) tapi diterapkan di konteks voucher, bukan Inquiry Product -- kemungkinan pola umum "dokumen bisa direvisi/dikalkulasi ulang" yang dipakai lintas modul.
- Sesuai memory `project_dp_invoice_status.md`: Event 1/2/4 DP Invoice live+tested, `fin_dp_allocation` pre-existed di skema ini, `co_order` SO reference masih manual teks (bukan FK) di beberapa tempat.
- Field pajak Indonesia eksplisit di `fin_voucher` (`ppn_type`, `efaktur_no`, `pph_type`, `bupot_no`) -- menunjukkan modul ini sudah mendukung PPN 11% & PPh withholding lokal, bukan generic voucher.
