-- =============================================================================
-- TMERP · MODUL 06 FINANCE & ACCOUNTING · TIER 3A (DDL + VIEW + SEED)
-- DB: tm2acc · MySQL 8.0+ · money DECIMAL(18,4) · prefix fin_
-- -----------------------------------------------------------------------------
-- KEPUTUSAN TERKUNCI YANG DITERAPKAN:
--   A. GL terintegrasi bertahap: subledger + polymorphic source_type/source_id.
--   B. COGS perpetual (jurnal HPP saat KIRIM dari snapshot Costing V2).
--   C. Cost center = ref terkontrol (fin_cost_center), BUKAN free-text.
--   • business_partners DIHAPUS → partner = ref LOGICAL ke tm2mst.cust_customer
--     / tm2mst.sup_supplier (TANPA FK MySQL lintas-DB). Mapping akun recon per
--     partner = fin_partner_account.
--   • Rate PPN/PPh TIDAK di-hardcode di nama akun → hidup di tm2mst.fin_tax_scheme
--     (dependency logical; nilai diambil saat posting).
--   • Audit baku 4 kolom di semua tabel transaksi.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS tm2acc
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tm2acc;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS v1_fin_ap_aging;
DROP VIEW  IF EXISTS v1_fin_ar_aging;
DROP VIEW  IF EXISTS v1_fin_trial_balance;
DROP VIEW  IF EXISTS v1_fin_account_tree;
DROP TABLE IF EXISTS fin_invoice;
DROP TABLE IF EXISTS fin_voucher_line;
DROP TABLE IF EXISTS fin_voucher;
DROP TABLE IF EXISTS fin_partner_account;
DROP TABLE IF EXISTS fin_cost_center;
DROP TABLE IF EXISTS fin_account;
DROP TABLE IF EXISTS fin_journal;
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. fin_journal — BUKU JURNAL (master kecil)
-- =============================================================================
CREATE TABLE fin_journal (
    journal_id    INT AUTO_INCREMENT PRIMARY KEY,
    journal_code  VARCHAR(10)  NOT NULL UNIQUE,
    journal_name  VARCHAR(100) NOT NULL,
    journal_type  ENUM('SALES','PURCHASE','CASH_BANK','GENERAL') NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_by    VARCHAR(50), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by    VARCHAR(50), updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================================================
-- 2. fin_account — CHART OF ACCOUNTS (master, self-parent)
--    account_id manual (id stabil untuk pohon parent). is_recon → wajib partner.
-- =============================================================================
CREATE TABLE fin_account (
    account_id       INT NOT NULL PRIMARY KEY,
    account_code     VARCHAR(20)  NOT NULL UNIQUE,
    account_name     VARCHAR(150) NOT NULL,
    account_type     ENUM('ASSET','LIABILITY','EQUITY','REVENUE','COGS','EXPENSE','OTHER_INCOME','OTHER_EXPENSE') NOT NULL,
    normal_balance   ENUM('DEBIT','CREDIT') NOT NULL,
    parent_id        INT DEFAULT NULL,
    is_recon         TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = akun rekonsiliasi AR/AP (wajib partner_id di setiap baris)
    is_postable      TINYINT(1) NOT NULL DEFAULT 1,  -- 0 = akun header (tak boleh dijurnal langsung)
    default_cc_id    INT DEFAULT NULL,               -- default cost center (logical → fin_cost_center)
    tax_scheme_code  VARCHAR(20) DEFAULT NULL,        -- logical → tm2mst.fin_tax_scheme (rate diambil saat posting)
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(50), updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_acc_parent FOREIGN KEY (parent_id) REFERENCES fin_account(account_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE INDEX idx_acc_code ON fin_account(account_code);
CREATE INDEX idx_acc_type ON fin_account(account_type);
CREATE INDEX idx_acc_parent ON fin_account(parent_id);

-- =============================================================================
-- 3. fin_cost_center — COST CENTER TERKONTROL (Keputusan C)
--    org_dept_id = ref LOGICAL ke tm2mst.org_department (non-produksi);
--    center produksi (PRINT/FINISH/PREPRESS/TOOLING) berdiri sendiri.
-- =============================================================================
CREATE TABLE fin_cost_center (
    cc_id       INT AUTO_INCREMENT PRIMARY KEY,
    cc_code     VARCHAR(30)  NOT NULL UNIQUE,   -- CC-PROD-PRINT, CC-FINANCE, ...
    cc_name     VARCHAR(100) NOT NULL,
    cc_group    ENUM('PRODUCTION','SALES','PURCHASING','PREPRESS','QC','WAREHOUSE','FINANCE','TAX','GENERAL') NOT NULL,
    org_dept_id INT DEFAULT NULL,               -- logical → tm2mst.org_department
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(50), updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================================================
-- 4. fin_partner_account — MAPPING AKUN RECON PER PARTNER
--    Pengganti business_partners. partner = ref LOGICAL (tanpa FK lintas-DB).
-- =============================================================================
CREATE TABLE fin_partner_account (
    map_id          INT AUTO_INCREMENT PRIMARY KEY,
    partner_type    ENUM('CUSTOMER','SUPPLIER') NOT NULL,
    partner_id      INT NOT NULL,               -- logical → tm2mst.cust_customer / tm2mst.sup_supplier
    ar_account_id   INT DEFAULT NULL,           -- akun piutang default (customer)
    ap_account_id   INT DEFAULT NULL,           -- akun hutang default (supplier)
    payment_term    VARCHAR(50) DEFAULT 'NET30',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(50), updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pa_ar FOREIGN KEY (ar_account_id) REFERENCES fin_account(account_id) ON DELETE RESTRICT,
    CONSTRAINT fk_pa_ap FOREIGN KEY (ap_account_id) REFERENCES fin_account(account_id) ON DELETE RESTRICT,
    UNIQUE KEY uq_partner (partner_type, partner_id)
) ENGINE=InnoDB;

-- =============================================================================
-- 5. fin_voucher — HEADER VOUCHER (journal entry). Snapshot; REVISE=copy-record.
-- =============================================================================
CREATE TABLE fin_voucher (
    voucher_id      INT AUTO_INCREMENT PRIMARY KEY,
    voucher_number  VARCHAR(50) NOT NULL UNIQUE,
    journal_id      INT NOT NULL,
    entry_date      DATE NOT NULL,
    posting_date    DATE NOT NULL,
    doc_type        ENUM('NEW','RECALC','REVISE') NOT NULL DEFAULT 'NEW',
    revision_no     INT NOT NULL DEFAULT 0,
    source_type     VARCHAR(30) DEFAULT NULL,   -- Keputusan A: hook integrasi (CO,DO,PO,RECEIVING,INVOICE,MANUAL)
    source_id       INT DEFAULT NULL,           -- logical → id dokumen sumber
    reference_no    VARCHAR(100) DEFAULT NULL,  -- No WO / Surat Jalan / PO eksternal
    description     TEXT DEFAULT NULL,
    status          ENUM('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    is_cancelled    TINYINT(1) NOT NULL DEFAULT 0,
    cancel_reason   VARCHAR(100) DEFAULT NULL,  -- 'SUPERSEDED' saat REVISE
    posted_by VARCHAR(50), posted_at DATETIME NULL,
    created_by VARCHAR(50), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(50), updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vch_journal FOREIGN KEY (journal_id) REFERENCES fin_journal(journal_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE INDEX idx_vch_posting ON fin_voucher(posting_date);
CREATE INDEX idx_vch_source  ON fin_voucher(source_type, source_id);
CREATE INDEX idx_vch_status  ON fin_voucher(status);

-- =============================================================================
-- 6. fin_voucher_line — BARIS DOUBLE-ENTRY. debit XOR credit (CHECK MySQL 8).
--    partner_id WAJIB jika account.is_recon=1 (di-enforce aplikasi, bukan DB).
-- =============================================================================
CREATE TABLE fin_voucher_line (
    line_id      INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id   INT NOT NULL,
    account_id   INT NOT NULL,
    partner_type ENUM('CUSTOMER','SUPPLIER') DEFAULT NULL,
    partner_id   INT DEFAULT NULL,              -- logical → tm2mst master
    cc_id        INT DEFAULT NULL,              -- FK terkontrol cost center
    co_id        INT DEFAULT NULL,              -- logical → tm2sdi.co_order (revenue PER CO — kasus cross-CO)
    debit        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    credit       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    narration    VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_vl_voucher FOREIGN KEY (voucher_id) REFERENCES fin_voucher(voucher_id) ON DELETE CASCADE,
    CONSTRAINT fk_vl_account FOREIGN KEY (account_id) REFERENCES fin_account(account_id) ON DELETE RESTRICT,
    CONSTRAINT fk_vl_cc      FOREIGN KEY (cc_id)      REFERENCES fin_cost_center(cc_id) ON DELETE RESTRICT,
    CONSTRAINT chk_dr_cr CHECK (
        (debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0) OR (debit = 0 AND credit = 0)
    )
) ENGINE=InnoDB;
CREATE INDEX idx_vl_voucher ON fin_voucher_line(voucher_id);
CREATE INDEX idx_vl_account ON fin_voucher_line(account_id);
CREATE INDEX idx_vl_partner ON fin_voucher_line(partner_type, partner_id);
CREATE INDEX idx_vl_co      ON fin_voucher_line(co_id);

-- =============================================================================
-- 7. fin_invoice — SUBLEDGER AR/AP. Polymorphic source (Keputusan A).
--    Amount = snapshot (frozen saat posting). total = subtotal+tax (snapshot).
-- =============================================================================
CREATE TABLE fin_invoice (
    invoice_id     INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_type   ENUM('CUSTOMER_INVOICE','SUPPLIER_BILL') NOT NULL,
    partner_type   ENUM('CUSTOMER','SUPPLIER') NOT NULL,
    partner_id     INT NOT NULL,                -- logical → tm2mst master
    source_type    VARCHAR(30) DEFAULT NULL,    -- CO / DO / PO / RECEIVING / MANUAL
    source_id      INT DEFAULT NULL,
    co_id          INT DEFAULT NULL,            -- revenue/invoice PER CO (kasus cross-CO)
    invoice_date   DATE NOT NULL,
    due_date       DATE NOT NULL,
    subtotal       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    tax_amount     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    total_amount   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,  -- snapshot = subtotal+tax
    paid_amount    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    voucher_id     INT DEFAULT NULL,            -- jurnal pengakuan
    payment_status ENUM('UNPAID','PARTIAL','PAID') NOT NULL DEFAULT 'UNPAID',
    created_by VARCHAR(50), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(50), updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_voucher FOREIGN KEY (voucher_id) REFERENCES fin_voucher(voucher_id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_inv_partner ON fin_invoice(partner_type, partner_id);
CREATE INDEX idx_inv_status  ON fin_invoice(payment_status);
CREATE INDEX idx_inv_co      ON fin_invoice(co_id);
CREATE INDEX idx_inv_source  ON fin_invoice(source_type, source_id);

-- =============================================================================
-- 8. VIEW v1_ (turunan/computed — TIDAK disimpan)
-- =============================================================================

-- 8a. Pohon COA untuk grid master (level + indent).
CREATE VIEW v1_fin_account_tree AS
SELECT a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
       a.parent_id, a.is_recon, a.is_postable, a.default_cc_id, a.tax_scheme_code, a.is_active,
       CASE WHEN a.parent_id IS NULL THEN 1
            WHEN p.parent_id IS NULL THEN 2 ELSE 3 END AS tree_level,
       cc.cc_code AS default_cc_code
FROM fin_account a
LEFT JOIN fin_account p    ON p.account_id = a.parent_id
LEFT JOIN fin_cost_center cc ON cc.cc_id   = a.default_cc_id;

-- 8b. Neraca saldo (trial balance) — hanya voucher POSTED & tak dibatalkan.
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
GROUP BY a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance;

-- 8c. Umur piutang (per CO). due_date < today = overdue.
CREATE VIEW v1_fin_ar_aging AS
SELECT i.invoice_id, i.invoice_number, i.partner_id, i.co_id, i.invoice_date, i.due_date,
       i.total_amount, i.paid_amount, (i.total_amount - i.paid_amount) AS outstanding,
       DATEDIFF(CURRENT_DATE, i.due_date) AS days_overdue, i.payment_status
FROM fin_invoice i
WHERE i.invoice_type='CUSTOMER_INVOICE' AND i.payment_status <> 'PAID';

-- 8d. Umur hutang.
CREATE VIEW v1_fin_ap_aging AS
SELECT i.invoice_id, i.invoice_number, i.partner_id, i.source_type, i.source_id,
       i.invoice_date, i.due_date, i.total_amount, i.paid_amount,
       (i.total_amount - i.paid_amount) AS outstanding,
       DATEDIFF(CURRENT_DATE, i.due_date) AS days_overdue, i.payment_status
FROM fin_invoice i
WHERE i.invoice_type='SUPPLIER_BILL' AND i.payment_status <> 'PAID';

-- =============================================================================
-- 9. SEED
-- =============================================================================
INSERT INTO fin_journal (journal_code, journal_name, journal_type) VALUES
('CSH','Jurnal Kas Operasional Pabrik','CASH_BANK'),
('BNK','Jurnal Bank Utama & Ekspor','CASH_BANK'),
('SAL','Jurnal Penjualan Packaging','SALES'),
('PUR','Jurnal Pembelian Material','PURCHASE'),
('GEN','Jurnal Umum & Penyesuaian','GENERAL');

INSERT INTO fin_cost_center (cc_code, cc_name, cc_group) VALUES
('CC-PROD-PRINT','Produksi - Cetak Offset','PRODUCTION'),
('CC-PROD-FINISH','Produksi - Finishing (Pond/Lem)','PRODUCTION'),
('CC-PRODUCTION','Produksi - Umum','PRODUCTION'),
('CC-PREPRESS','Prepress / CTP','PREPRESS'),
('CC-TOOLING','Tooling / Pisau Pond','PRODUCTION'),
('CC-QC-LAB','QC & Lab Warna','QC'),
('CC-WH-PAPER','Gudang Kertas & Board','WAREHOUSE'),
('CC-WH-INK','Gudang Tinta & Chemical','WAREHOUSE'),
('CC-WH-FG','Gudang Barang Jadi','WAREHOUSE'),
('CC-SALES','Penjualan','SALES'),
('CC-PURCHASING','Pembelian','PURCHASING'),
('CC-FINANCE','Keuangan','FINANCE'),
('CC-TAX','Perpajakan','TAX');

-- COA (account_id manual; is_postable=0 untuk header). Nama = label; RATE pajak
-- diambil dari tm2mst.fin_tax_scheme via tax_scheme_code, BUKAN dari nama.
INSERT INTO fin_account
(account_id,account_code,account_name,account_type,normal_balance,parent_id,is_recon,is_postable,tax_scheme_code) VALUES
-- ASET
(100,'100000','ASET (ASSETS)','ASSET','DEBIT',NULL,0,0,NULL),
(110,'110000','ASET LANCAR','ASSET','DEBIT',100,0,0,NULL),
(111,'111100','Kas Operasional Pabrik','ASSET','DEBIT',110,0,1,NULL),
(112,'111200','Bank BCA - Rekening Utama IDR','ASSET','DEBIT',110,0,1,NULL),
(113,'111300','Bank Mandiri - USD (Ekspor)','ASSET','DEBIT',110,0,1,NULL),
(121,'112100','Piutang Usaha - Customer Lokal','ASSET','DEBIT',110,1,1,NULL),
(122,'112200','Piutang Usaha - Customer Ekspor','ASSET','DEBIT',110,1,1,NULL),
(131,'113100','Persediaan Bahan Baku - Kertas & Board','ASSET','DEBIT',110,0,1,NULL),
(132,'113200','Persediaan Bahan Baku - Tinta & Chemical','ASSET','DEBIT',110,0,1,NULL),
(133,'113300','Persediaan Tooling - Pisau Pond & Deboss','ASSET','DEBIT',110,0,1,NULL),
(134,'113400','Persediaan WIP (Cetak/Finishing)','ASSET','DEBIT',110,0,1,NULL),
(135,'113500','Persediaan Barang Jadi (Packaging)','ASSET','DEBIT',110,0,1,NULL),
(141,'114100','PPN Masukan (Input VAT)','ASSET','DEBIT',110,0,1,'PPN_IN'),
(142,'114200','Uang Muka PPh Pasal 22 - Kertas Mill','ASSET','DEBIT',110,0,1,'PPH22'),
(150,'120000','ASET TETAP PABRIK','ASSET','DEBIT',100,0,0,NULL),
(151,'121100','Mesin Cetak Offset','ASSET','DEBIT',150,0,1,NULL),
(152,'121200','Mesin Finishing (Die-Cutter/Folder Gluer)','ASSET','DEBIT',150,0,1,NULL),
(159,'121900','Akumulasi Penyusutan Mesin','ASSET','CREDIT',150,0,1,NULL),
-- KEWAJIBAN
(200,'200000','KEWAJIBAN (LIABILITIES)','LIABILITY','CREDIT',NULL,0,0,NULL),
(210,'210000','KEWAJIBAN JANGKA PENDEK','LIABILITY','CREDIT',200,0,0,NULL),
(211,'211100','Hutang Dagang - Supplier Kertas & Board','LIABILITY','CREDIT',210,1,1,NULL),
(212,'211200','Hutang Dagang - Supplier Tinta & Chemical','LIABILITY','CREDIT',210,1,1,NULL),
(213,'211300','Hutang Jasa Subkon Finishing (Laminasi/UV)','LIABILITY','CREDIT',210,1,1,NULL),
(221,'212100','Hutang PPN Keluaran (Output VAT)','LIABILITY','CREDIT',210,0,1,'PPN_OUT'),
(222,'212200','Hutang PPh Pasal 23 (Jasa Subkon)','LIABILITY','CREDIT',210,0,1,'PPH23'),
(223,'212300','Hutang PPh Pasal 22 (Industri Kertas)','LIABILITY','CREDIT',210,0,1,'PPH22'),
(231,'213100','Beban Akrual / YMH Dibayar','LIABILITY','CREDIT',210,0,1,NULL),
-- EKUITAS
(300,'300000','EKUITAS (EQUITY)','EQUITY','CREDIT',NULL,0,0,NULL),
(311,'311000','Modal Disetor','EQUITY','CREDIT',300,0,1,NULL),
(321,'321000','Laba Ditahan','EQUITY','CREDIT',300,0,1,NULL),
(322,'322000','Laba Tahun Berjalan','EQUITY','CREDIT',300,0,1,NULL),
-- PENDAPATAN
(400,'400000','PENDAPATAN (REVENUE)','REVENUE','CREDIT',NULL,0,0,NULL),
(411,'411000','Penjualan Packaging - Food Grade Box','REVENUE','CREDIT',400,0,1,'PPN_OUT'),
(412,'412000','Penjualan Packaging - Non-Food/Commercial','REVENUE','CREDIT',400,0,1,'PPN_OUT'),
(413,'413000','Pendapatan Jasa Maklon / Subkon','REVENUE','CREDIT',400,0,1,'PPN_OUT'),
(421,'421000','Diskon Penjualan & Retur','REVENUE','DEBIT',400,0,1,NULL),
-- HPP / COGS
(500,'500000','BEBAN POKOK PENJUALAN (COGS)','COGS','DEBIT',NULL,0,0,NULL),
(511,'511000','HPP - Bahan Baku Kertas & Board','COGS','DEBIT',500,0,1,NULL),
(512,'512000','HPP - Tinta, Varnish & Chemical','COGS','DEBIT',500,0,1,NULL),
(513,'513000','HPP - Jasa Subkon Finishing External','COGS','DEBIT',500,0,1,NULL),
(514,'514000','HPP - Tenaga Kerja Langsung','COGS','DEBIT',500,0,1,NULL),
(515,'515000','HPP - Penyusutan Mesin Produksi','COGS','DEBIT',500,0,1,NULL),
(516,'516000','HPP - Listrik & Daya Pabrik','COGS','DEBIT',500,0,1,NULL),
-- BEBAN OPERASIONAL
(600,'600000','BEBAN OPERASIONAL (EXPENSES)','EXPENSE','DEBIT',NULL,0,0,NULL),
(611,'611000','Beban Gaji Mgmt, Sales & Admin','EXPENSE','DEBIT',600,0,1,NULL),
(612,'612000','Beban QC & Lab Warna (Delta E)','EXPENSE','DEBIT',600,0,1,NULL),
(613,'613000','Beban Amortisasi Pelat CTP & Artwork','EXPENSE','DEBIT',600,0,1,NULL),
(614,'614000','Beban Pemasaran & Komisi Sales','EXPENSE','DEBIT',600,0,1,NULL),
(615,'615000','Beban Kerugian Kertas Rusak (Waste)','EXPENSE','DEBIT',600,0,1,NULL),
-- LAIN-LAIN
(800,'800000','PENDAPATAN & BEBAN LAIN-LAIN','OTHER_INCOME','CREDIT',NULL,0,0,NULL),
(811,'811000','Selisih Kurs Transaksi','OTHER_INCOME','CREDIT',800,0,1,NULL),
(812,'812000','Beban Administrasi Bank & Bunga','OTHER_EXPENSE','DEBIT',800,0,1,NULL);

-- default cost center untuk beberapa akun (contoh mapping; sisanya di UI)
UPDATE fin_account a JOIN fin_cost_center c ON c.cc_code='CC-PROD-PRINT'
  SET a.default_cc_id=c.cc_id WHERE a.account_code IN ('511000','512000','515000','516000','615000');
UPDATE fin_account a JOIN fin_cost_center c ON c.cc_code='CC-PROD-FINISH'
  SET a.default_cc_id=c.cc_id WHERE a.account_code IN ('513000');
UPDATE fin_account a JOIN fin_cost_center c ON c.cc_code='CC-PREPRESS'
  SET a.default_cc_id=c.cc_id WHERE a.account_code IN ('613000');
UPDATE fin_account a JOIN fin_cost_center c ON c.cc_code='CC-QC-LAB'
  SET a.default_cc_id=c.cc_id WHERE a.account_code IN ('612000');
UPDATE fin_account a JOIN fin_cost_center c ON c.cc_code='CC-TAX'
  SET a.default_cc_id=c.cc_id WHERE a.account_code IN ('114100','114200','212100','212200','212300');

-- =============================================================================
-- DEPENDENCY LOGICAL (tak dibuat di sini — dimiliki modul lain):
--   tm2mst.cust_customer, tm2mst.sup_supplier  → partner_id
--   tm2mst.org_department                       → fin_cost_center.org_dept_id
--   tm2mst.fin_tax_scheme (code: PPN_IN/PPN_OUT/PPH22/PPH23) → rate posting
--   tm2sdi.co_order                             → co_id (revenue per CO)
-- =============================================================================
