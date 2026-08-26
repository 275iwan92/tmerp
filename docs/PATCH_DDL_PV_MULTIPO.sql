-- ═══════════════════════════════════════════════════════════════════════════
-- PATCH_DDL_PV_MULTIPO.sql
-- TMERP · tm2pur · Menerapkan purchasereceive_delta_multipo.md (PV-01 s/d PV-11)
--
-- Efek: satu SPB boleh menampung banyak PO dari SATU supplier.
--       Referensi PO PINDAH dari header (pv_receipt.po_id) ke baris (pv_line).
--
-- Prasyarat: DDL_PROCUREMENT_tm2pur.sql sudah dijalankan.
-- Urutan   : jalankan file ini SEBELUM PATCH_SEED_PV_MULTIPO.sql.
-- Aman diulang? TIDAK sepenuhnya (MySQL 8 tidak punya ADD COLUMN IF NOT EXISTS).
--       Bila diulang, error "Duplicate column name" boleh diabaikan.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
USE `tm2pur`;

-- ───────────────────────────────────────────────────────────────────────────
-- 1. pv_line — terima referensi PO dari header
--    (po_line_id sudah ada sejak awal; yang ditambah adalah snapshot cetak)
-- ───────────────────────────────────────────────────────────────────────────
ALTER TABLE `pv_line`
  ADD COLUMN `c_po_id` BIGINT UNSIGNED NULL AFTER `po_line_id`
    COMMENT '[PV-01] snapshot po_order.po_id — kunci rekalkulasi status PO per dokumen',
  ADD COLUMN `c_po_doc_no` VARCHAR(30) NULL AFTER `c_po_id`
    COMMENT '[PV-01] snapshot nomor PO — dicetak per baris di nota SPB',
  ADD COLUMN `c_po_line_no` SMALLINT UNSIGNED NULL AFTER `c_po_doc_no`
    COMMENT '[PV-01] snapshot nomor baris PO asal',
  ADD COLUMN `c_po_date` DATE NULL AFTER `c_po_line_no`
    COMMENT '[PV-01] snapshot tanggal PO',
  ADD COLUMN `i_supplier_do_no` VARCHAR(50) NULL AFTER `c_po_date`
    COMMENT '[PV-07] surat jalan khusus baris ini; NULL = ikut SJ header',
  ADD COLUMN `c_receipt_seq_po` SMALLINT UNSIGNED NULL AFTER `c_qty_po_outstanding`
    COMMENT '[PV-06] penerimaan ke-berapa terhadap PO tersebut (bukan terhadap dokumen SPB)',
  ADD INDEX `idx_pvl_po` (`c_po_id`);

-- ───────────────────────────────────────────────────────────────────────────
-- 2. pv_receipt — header menjadi SUPPLIER-DRIVEN
--    po_id dibuang. Yang tersisa hanya ringkasan tercetak (bukan logika).
-- ───────────────────────────────────────────────────────────────────────────
ALTER TABLE `pv_receipt`
  ADD COLUMN `c_po_count` SMALLINT UNSIGNED NULL AFTER `doc_no`
    COMMENT '[PV-01] jumlah PO berbeda dalam dokumen ini (ringkasan grid)',
  ADD COLUMN `c_po_doc_list` VARCHAR(255) NULL AFTER `c_po_count`
    COMMENT '[PV-01] daftar nomor PO dipisah koma — HANYA untuk grid & kop nota. Logika WAJIB baca pv_line.',
  MODIFY COLUMN `i_supplier_do_no` VARCHAR(150) NULL
    COMMENT '[PV-07] SJ utama supplier (boleh memuat beberapa nomor bila satu kiriman satu SJ gabungan)',
  MODIFY COLUMN `i_supplier_id` BIGINT UNSIGNED NOT NULL
    COMMENT '[PV-02] KUNCI DOKUMEN — seluruh baris wajib berasal dari supplier ini (HARD BLOCK)';

-- Pindahkan data lama header -> baris SEBELUM kolom header dibuang
UPDATE `pv_line` v
  JOIN `pv_receipt` h ON h.pv_id = v.pv_id
  JOIN `po_line`   pl ON pl.po_line_id = v.po_line_id
  JOIN `po_order`  po ON po.po_id = pl.po_id
   SET v.c_po_id      = po.po_id,
       v.c_po_doc_no  = po.doc_no,
       v.c_po_line_no = pl.line_no,
       v.c_po_date    = po.i_doc_date,
       v.c_receipt_seq_po = COALESCE(h.c_receipt_seq, 1);

-- Isi ringkasan header dari baris
UPDATE `pv_receipt` h
   SET h.c_po_count    = (SELECT COUNT(DISTINCT v.c_po_id)  FROM pv_line v WHERE v.pv_id = h.pv_id),
       h.c_po_doc_list = (SELECT GROUP_CONCAT(DISTINCT v.c_po_doc_no ORDER BY v.c_po_doc_no SEPARATOR ', ')
                            FROM pv_line v WHERE v.pv_id = h.pv_id);

-- Baru buang kolom header
ALTER TABLE `pv_receipt`
  DROP INDEX `idx_pv_po`,
  DROP COLUMN `po_id`,
  DROP COLUMN `c_po_doc_no`,
  DROP COLUMN `c_po_date`;

-- ───────────────────────────────────────────────────────────────────────────
-- 3. po_line — [PV-09] baris jasa tidak pernah masuk PV
-- ───────────────────────────────────────────────────────────────────────────
ALTER TABLE `po_line`
  ADD COLUMN `is_receivable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_stock`
    COMMENT '[PV-09] 1 = ditutup lewat SPB. 0 = baris SERVICE, ditutup lewat tombol "Konfirmasi Penyelesaian Jasa" di layar PO (hak PO.can_approve), tanpa pergerakan stok.',
  ADD INDEX `idx_pol_receivable` (`is_receivable`);

UPDATE `po_line` SET `is_receivable` = 0 WHERE `i_line_class` = 'SERVICE';

-- ───────────────────────────────────────────────────────────────────────────
-- 4. prt_return — [§5] PO diambil dari baris PV, jadi header boleh kosong
-- ───────────────────────────────────────────────────────────────────────────
ALTER TABLE `prt_return`
  MODIFY COLUMN `po_id` BIGINT UNSIGNED NULL
    COMMENT 'Nullable sejak PV multi-PO — ambil dari pv_line.c_po_id baris yang diretur';

-- ───────────────────────────────────────────────────────────────────────────
-- 5. VIEW — tulis ulang yang terdampak
-- ───────────────────────────────────────────────────────────────────────────

-- [PV-09] worklist tidak boleh memunculkan baris jasa
CREATE OR REPLACE VIEW v1_po_outstanding AS
SELECT h.po_id, h.doc_no, h.i_doc_date, h.i_supplier_id, h.c_supplier_name, h.status,
       h.i_delivery_date, h.i_payment_term, h.c_currency_code, h.c_ship_to_name,
       l.po_line_id, l.line_no, l.c_item_code, l.i_description, l.i_qty, l.i_uom,
       l.c_uom_stock, l.c_conv_to_stock, l.c_qty_received,
       (l.i_qty - l.c_qty_received) AS qty_outstanding,
       l.c_tol_pct, l.is_stock, l.is_receivable, l.i_co_line_id, l.c_co_no
FROM po_order h JOIN po_line l ON l.po_id = h.po_id
WHERE h.status IN ('SENT','PARTIALLY_RECEIVED')
  AND h.is_cancelled = 0
  AND l.is_receivable = 1
  AND l.i_qty > l.c_qty_received;

-- [PV-01] daftar supplier untuk Langkah 1 (modal pilih supplier)
CREATE OR REPLACE VIEW v1_pv_supplier_worklist AS
SELECT i_supplier_id, c_supplier_name,
       COUNT(DISTINCT po_id) AS open_po_count,
       COUNT(*)              AS open_line_count,
       MIN(i_doc_date)       AS oldest_po_date,
       MIN(i_delivery_date)  AS earliest_delivery_date
FROM v1_po_outstanding
GROUP BY i_supplier_id, c_supplier_name;

-- [§5] peta SPB <-> PO: dasar penagihan lintas PO & audit
CREATE OR REPLACE VIEW v1_pv_po_link AS
SELECT h.pv_id, h.doc_no AS pv_doc_no, h.i_doc_date AS pv_date, h.status AS pv_status,
       h.i_supplier_id, h.c_supplier_name,
       v.c_po_id AS po_id, v.c_po_doc_no AS po_doc_no,
       COUNT(*)                       AS line_count,
       SUM(v.i_qty_purchase)          AS qty_purchase_total,
       SUM(COALESCE(v.c_line_value,0))  AS invoice_value,
       SUM(COALESCE(v.c_stock_value,0)) AS stock_value
FROM pv_receipt h JOIN pv_line v ON v.pv_id = h.pv_id
GROUP BY h.pv_id, h.doc_no, h.i_doc_date, h.status, h.i_supplier_id, h.c_supplier_name,
         v.c_po_id, v.c_po_doc_no;

-- Nota SPB — header (TETAP TANPA KOLOM NILAI APA PUN, aturan Sisi B)
CREATE OR REPLACE VIEW vw_nota_pv AS
SELECT h.pv_id, h.doc_no, h.i_doc_date AS doc_date,
       h.c_po_count AS po_count, h.c_po_doc_list AS po_doc_list,
       h.c_supplier_name AS supplier_name, h.c_supplier_address AS supplier_address,
       h.c_warehouse_code AS warehouse_code, h.c_warehouse_name AS warehouse_name,
       h.i_supplier_do_no AS supplier_do_no, h.i_vehicle_no AS vehicle_no,
       h.c_receipt_seq AS receipt_seq, h.status, h.posted_at, h.posted_by, h.i_notes AS notes,
       (SELECT COUNT(*) FROM pv_line v WHERE v.pv_id = h.pv_id) AS line_count,
       (SELECT COUNT(*) FROM pv_line v WHERE v.pv_id = h.pv_id AND v.i_supplier_do_no IS NOT NULL) AS line_do_count,
       CASE WHEN h.status = 'POSTED'     THEN 'TERPOSTING'
            WHEN h.status = 'CANCELLED'  THEN 'DIBATALKAN'
            WHEN h.status = 'QC_PENDING' THEN 'MENUNGGU QC'
            ELSE 'DRAF' END AS stamp_label
FROM pv_receipt h;

-- Nota SPB — baris (ditambah kolom asal PO)
CREATE OR REPLACE VIEW vw_nota_pv_line AS
SELECT v.pv_id, v.line_no,
       v.c_po_doc_no AS po_doc_no, v.c_po_line_no AS po_line_no, v.c_po_date AS po_date,
       v.i_supplier_do_no AS line_do_no,
       v.c_item_code AS item_code, v.i_description AS description,
       v.i_qty_purchase AS qty_purchase, v.i_uom_purchase AS uom_purchase,
       v.i_qty_stock AS qty_stock, v.i_uom_stock AS uom_stock,
       v.c_conv_used AS conv_used, v.is_conv_manual,
       v.c_qty_accepted AS qty_accepted, v.c_qty_rejected AS qty_rejected,
       v.qc_quality_status, v.qc_note, v.i_lot_no AS lot_no, v.c_location_code AS location_code,
       v.c_tol_pct AS tol_pct, v.c_qty_variance_pct AS variance_pct, v.is_tol_exceeded,
       v.tol_override_by, v.tol_override_at, v.tol_override_reason
FROM pv_line v ORDER BY v.pv_id, v.c_po_doc_no, v.line_no;

-- ═══════════════════════════════════════════════════════════════════════════
-- 6. VERIFIKASI
-- ═══════════════════════════════════════════════════════════════════════════
-- (a) Header tidak boleh lagi punya po_id:
--     SHOW COLUMNS FROM pv_receipt LIKE 'po_id';            -- harus kosong
-- (b) Semua baris wajib punya snapshot PO:
--     SELECT pv_line_id FROM pv_line WHERE c_po_id IS NULL;  -- harus kosong
-- (c) [PV-02] Tidak boleh ada baris dari supplier lain:
--     SELECT h.doc_no, v.c_po_doc_no FROM pv_receipt h
--       JOIN pv_line v ON v.pv_id=h.pv_id
--       JOIN po_order p ON p.po_id=v.c_po_id
--      WHERE p.i_supplier_id <> h.i_supplier_id;             -- harus kosong
-- (d) [PV-09] Tidak boleh ada baris jasa yang masuk PV:
--     SELECT v.pv_line_id FROM pv_line v JOIN po_line l ON l.po_line_id=v.po_line_id
--      WHERE l.is_receivable=0;                              -- harus kosong
-- (e) Ringkasan header konsisten dengan baris:
--     SELECT h.doc_no, h.c_po_count,
--            (SELECT COUNT(DISTINCT v.c_po_id) FROM pv_line v WHERE v.pv_id=h.pv_id) AS real_count
--       FROM pv_receipt h HAVING h.c_po_count <> real_count; -- harus kosong
-- ═══════════════════════════════════════════════════════════════════════════
