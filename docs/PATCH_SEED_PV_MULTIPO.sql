-- ═══════════════════════════════════════════════════════════════════════════
-- PATCH_SEED_PV_MULTIPO.sql
-- TMERP · tm2pur · Menjadikan SPB/26/00058 sebagai contoh PENERIMAAN MULTI-PO
--
-- Prasyarat berurutan:
--   1) DDL_PROCUREMENT_tm2pur.sql
--   2) SEED_PROC_DUMMY.sql
--   3) PATCH_DDL_PV_MULTIPO.sql   <-- WAJIB sebelum file ini
--
-- Yang dilakukan:
--   (a) Menambah PO/26/00035 — supplier SAMA (PT Cakra Kertas Nusantara)
--   (b) Menambah baris ke-4 pada SPB/26/00058 yang berasal dari PO/26/00035
--       -> satu SPB kini memuat 2 PO, dengan 2 nomor surat jalan berbeda
--   (c) Menyegarkan ringkasan header + snapshot PO di seluruh baris
--   (d) Memperbarui blok simulasi POST agar ikut merekalkulasi PO/26/00035
--
-- Aman diulang.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
USE `tm2pur`;

DELETE FROM pv_line   WHERE pv_line_id = 5804;
DELETE FROM po_line   WHERE po_line_id IN (3501,3502);
DELETE FROM po_order  WHERE po_id = 35;

-- ───────────────────────────────────────────────────────────────────────────
-- (a) PO/26/00035 — supplier sama, gudang sama, mata uang sama
--     (memenuhi PV-02, PV-03, PV-04 sehingga BOLEH digabung dengan PO/26/00031)
--     Sengaja BERBEDA termin pembayaran & tanggal kirim -> memicu WARNING PV-V11
-- ───────────────────────────────────────────────────────────────────────────
INSERT INTO po_order
(po_id, doc_no, i_doc_date, i_supplier_id, i_pq_id,
 c_supplier_name, c_supplier_address, c_supplier_npwp, c_supplier_pic, c_supplier_phone,
 i_po_type, i_procure_type, i_currency_id, c_currency_code, i_fx_rate, i_quotation_valid_until,
 i_payment_term, i_delivery_date, i_ship_to, c_ship_to_name, i_notes,
 c_ppn_rate, c_pph_rate, c_dpp, c_ppn, c_pph, c_grand_total, c_net_payable, c_amount_words,
 status, sent_at, is_auto_generated, source_note, created_by, created_at) VALUES
(35,'PO/26/00035','2026-08-18',1,NULL,
 'PT Cakra Kertas Nusantara','Jl. Margomulyo Industri No. 88, Surabaya 60186','02.876.543.2-604.000','Hendra Wijaya','(031) 7490-2255',
 'LOCAL','REPEAT',1,'IDR',1.000000,NULL,
 '21 hari','2026-08-24',
 'Jl. Rungkut Industri III No. 42, Surabaya 60293','Gudang Bahan Baku',
 'Pesanan susulan - dikirim bersama muatan PO/26/00031',
 11.00,2.00,10400000.0000,1144000.0000,0.0000,11544000.0000,11544000.0000,
 'Sebelas juta lima ratus empat puluh empat ribu rupiah',
 'SENT','2026-08-18 11:40:00',0,NULL,'purchasing.dewi','2026-08-18 10:15:00');

INSERT INTO po_line
(po_line_id, po_id, line_no, alloc_id, c_pr_doc_no, c_pr_line_no, item_id, c_item_code, i_description, i_line_class,
 i_qty, i_uom, c_uom_stock, c_conv_to_stock, i_price, i_disc_pct, i_disc_amt, c_line_net, c_unit_price_net,
 i_department_id, c_department_name, i_coa_account_id, c_coa_code, c_coa_name, i_co_line_id, c_co_no,
 is_stock, is_receivable, c_tol_pct, c_qty_received, c_qty_outstanding, c_line_status, created_by) VALUES
(3501,35,1,NULL,NULL,NULL,1006,'PYA12','Art Paper 120gr 65x100 cm','RAW',
 4000,'LBR','LBR',1.000000,1340.0000,NULL,NULL,5360000.0000,1340.0000,
 3,'Produksi Offset',5110,'5-1101','Bahan Baku',NULL,NULL,
 1,1,5.00,0,4000,'OPEN','purchasing.dewi'),
(3502,35,2,NULL,NULL,NULL,1004,'PYJ25','Kertas Ivory 250gr 79x109 cm','RAW',
 2000,'LBR','LBR',1.000000,2520.0000,NULL,NULL,5040000.0000,2520.0000,
 3,'Produksi Offset',5110,'5-1101','Bahan Baku',NULL,NULL,
 1,1,5.00,0,2000,'OPEN','purchasing.dewi');

-- ───────────────────────────────────────────────────────────────────────────
-- (b) Baris ke-4 SPB/26/00058 — berasal dari PO/26/00035, SJ berbeda
--     Baris ini LOLOS QC penuh, jadi ia BUKAN penghambat POST.
--     Penghambat tetap hanya: baris 1 (QC PENDING) & baris 2 (toleransi).
-- ───────────────────────────────────────────────────────────────────────────
INSERT INTO pv_line
(pv_line_id, pv_id, po_line_id, c_po_id, c_po_doc_no, c_po_line_no, c_po_date, i_supplier_do_no,
 line_no, item_id, c_item_code, i_description, is_stock,
 i_qty_purchase, i_uom_purchase, i_qty_stock, i_uom_stock, c_conv_used, is_conv_manual,
 c_qty_po_outstanding, c_receipt_seq_po,
 qc_qty_status, qc_quality_status, c_qty_accepted, c_qty_rejected, qc_note, qc_by, qc_at,
 c_tol_pct, c_qty_variance_pct, is_tol_exceeded, tol_override_by, tol_override_at, tol_override_reason,
 c_unit_price, c_unit_price_net, c_line_value, c_unit_cost_stock, c_stock_value,
 i_lot_no, i_location_id, c_location_code, i_co_line_id, c_qty_invoiced, c_qty_returned, created_by) VALUES
(5804,58,3501,35,'PO/26/00035',1,'2026-08-18','SJ-CKN/8/1143',
 4,1006,'PYA12','Art Paper 120gr 65x100 cm',1,
 4000,'LBR',4000,'LBR',1.000000,0,
 4000,1,
 'DONE','PASS',4000,0,'Sesuai contoh, kemasan utuh','qc.wati','2026-08-21 10:06:00',
 5.00,0.0000,0,NULL,NULL,NULL,
 1340.0000,1340.0000,5360000.0000,1340.00000,5360000.0000,
 'AP120-2608-C',12,'RAK-B/04',NULL,0,0,'gudang.ari');

-- ───────────────────────────────────────────────────────────────────────────
-- (c) Segarkan snapshot PO di SEMUA baris + ringkasan header SEMUA dokumen
-- ───────────────────────────────────────────────────────────────────────────
UPDATE `pv_line` v
  JOIN `po_line`  pl ON pl.po_line_id = v.po_line_id
  JOIN `po_order` po ON po.po_id      = pl.po_id
   SET v.c_po_id      = po.po_id,
       v.c_po_doc_no  = po.doc_no,
       v.c_po_line_no = pl.line_no,
       v.c_po_date    = po.i_doc_date
 WHERE v.c_po_id IS NULL OR v.c_po_doc_no IS NULL;

-- SJ per baris untuk 3 baris asal PO/26/00031
UPDATE `pv_line` SET i_supplier_do_no = 'SJ-CKN/8/1142' WHERE pv_line_id IN (5801,5802,5803);

-- Header SPB/26/00058: dua PO, dua surat jalan
UPDATE `pv_receipt`
   SET i_supplier_do_no = 'SJ-CKN/8/1142, SJ-CKN/8/1143',
       i_notes = 'Satu muatan berisi 2 PO. Bongkar pukul 09.10, pengawas gudang: Sdr. Ari'
 WHERE pv_id = 58;

-- Ringkasan c_po_count / c_po_doc_list untuk seluruh dokumen
UPDATE `pv_receipt` h
   SET h.c_po_count    = (SELECT COUNT(DISTINCT v.c_po_id) FROM pv_line v WHERE v.pv_id = h.pv_id),
       h.c_po_doc_list = (SELECT GROUP_CONCAT(DISTINCT v.c_po_doc_no ORDER BY v.c_po_doc_no SEPARATOR ', ')
                            FROM pv_line v WHERE v.pv_id = h.pv_id);

-- [PV-09] pastikan baris jasa tertutup dari worklist
UPDATE `po_line` SET is_receivable = 0 WHERE i_line_class = 'SERVICE';


-- ═══════════════════════════════════════════════════════════════════════════
-- (d) [OPSIONAL] SIMULASI POST SPB/26/00058 — versi MULTI-PO
--     Menggantikan blok §7 di SEED_PROC_DUMMY.sql (yang hanya mengurus 1 PO).
--     Buka komentar untuk menjalankan.
-- ═══════════════════════════════════════════════════════════════════════════
/*
START TRANSACTION;

-- 1. Baris 1: QC kualitas PASS
UPDATE pv_line SET qc_qty_status='DONE', qc_quality_status='PASS',
       c_qty_accepted=5000, c_qty_rejected=0, qc_note='Warna & gramatur sesuai contoh',
       qc_by='qc.wati', qc_at='2026-08-21 10:18:00',
       c_stock_value = 5000 * c_unit_cost_stock
 WHERE pv_line_id=5801;

-- 2. Baris 2: override toleransi (+7,2% vs batas 5%)
UPDATE pv_line SET tol_override_by='Manajer Operasional', tol_override_at='2026-08-21 10:24:00',
       tol_override_reason='Kelebihan kirim diterima, harga per lembar tetap sesuai PO - konfirmasi supplier via WA 21/08.'
 WHERE pv_line_id=5802;

-- 3. Header POSTED
UPDATE pv_receipt SET status='POSTED', posted_at='2026-08-21 10:31:00', posted_by='gudang.ari',
       rm_stock_doc_id=9005 WHERE pv_id=58;

-- 4. [PV-08] Rekalkulasi po_line untuk SETIAP PO yang tersentuh (31 DAN 35)
UPDATE po_line l
  JOIN (SELECT po_line_id, SUM(i_qty_purchase) AS qty
          FROM pv_line v
          JOIN pv_receipt h ON h.pv_id = v.pv_id
         WHERE h.status = 'POSTED'
         GROUP BY po_line_id) s ON s.po_line_id = l.po_line_id
   SET l.c_qty_received    = s.qty,
       l.c_qty_outstanding = GREATEST(l.i_qty - s.qty, 0),
       l.c_line_status     = CASE
         WHEN s.qty >= l.i_qty THEN 'RECEIVED'
         WHEN l.c_tol_pct IS NOT NULL
              AND ((l.i_qty - s.qty) / l.i_qty * 100) <= l.c_tol_pct THEN 'CLOSED_BY_TOLERANCE'
         ELSE 'PARTIALLY_RECEIVED' END
 WHERE l.po_id IN (31,35);

-- 5. Status header PO — dihitung dari barisnya, per PO
--    PO/26/00031: baris 1 sisa 3.000 -> PARTIALLY_RECEIVED
--                 (baris 4 = jasa, is_receivable=0, TIDAK menghalangi status RECEIVED)
--    PO/26/00035: baris 1 lunas, baris 2 belum dikirim -> PARTIALLY_RECEIVED
UPDATE po_order h
   SET h.status = CASE
     WHEN (SELECT COUNT(*) FROM po_line l
            WHERE l.po_id=h.po_id AND l.is_receivable=1
              AND l.c_line_status NOT IN ('RECEIVED','CLOSED_BY_TOLERANCE')) = 0 THEN 'RECEIVED'
     WHEN (SELECT COALESCE(SUM(l.c_qty_received),0) FROM po_line l
            WHERE l.po_id=h.po_id AND l.is_receivable=1) > 0 THEN 'PARTIALLY_RECEIVED'
     ELSE h.status END
 WHERE h.po_id IN (31,35);

COMMIT;

-- 6. Yang seharusnya ikut terjadi di tm2inv (tabel belum ada):
--    SATU rm_stock (doc_no='SPB/26/00058', doc_type=1, doc_cat='STOCK', warehouse_id=1)
--    dengan EMPAT rm_stock_d — perhatikan barisnya berasal dari DUA PO berbeda,
--    dan itu tidak menjadi masalah karena reference_id menunjuk pv_line_id:
--      1001 PYJ31  5.000 LBR @ 2.842,00      = 14.210.000   (PO/26/00031 baris 1)
--      1002 PYA21  5.240 LBR @ 1.475,00      =  7.729.000   (PO/26/00031 baris 2)
--      1003 CFS41    792 LTR @ 6.313,13131   =  5.000.000   (PO/26/00031 baris 3)
--      1006 PYA12  4.000 LBR @ 1.340,00      =  5.360.000   (PO/26/00035 baris 1)
*/


-- ═══════════════════════════════════════════════════════════════════════════
-- VERIFIKASI MULTI-PO
-- ═══════════════════════════════════════════════════════════════════════════
-- (1) SPB/26/00058 harus memuat 2 PO:
--     SELECT doc_no, c_po_count, c_po_doc_list, i_supplier_do_no
--       FROM pv_receipt WHERE pv_id=58;
--
-- (2) Peta SPB <-> PO (dasar penagihan lintas PO):
--     SELECT * FROM v1_pv_po_link WHERE pv_id=58;
--
-- (3) Langkah 1 layar entry — daftar supplier dengan PO terbuka:
--     SELECT * FROM v1_pv_supplier_worklist ORDER BY open_line_count DESC;
--
-- (4) Langkah 2 layar entry — worklist baris terbuka milik PT Cakra (supplier 1).
--     Baris jasa PO/26/00031 #4 TIDAK BOLEH muncul:
--     SELECT doc_no, line_no, c_item_code, i_description, i_qty, c_qty_received,
--            qty_outstanding, i_uom, i_delivery_date, i_payment_term
--       FROM v1_po_outstanding WHERE i_supplier_id=1 ORDER BY doc_no, line_no;
--
-- (5) [PV-V11] Deteksi termin berbeda antar-PO dalam satu SPB (harus muncul 1 baris
--     untuk SPB/26/00058: '30 hari setelah faktur diterima' vs '21 hari'):
--     SELECT h.doc_no, COUNT(DISTINCT p.i_payment_term) AS variasi_termin
--       FROM pv_receipt h JOIN pv_line v ON v.pv_id=h.pv_id
--       JOIN po_order p ON p.po_id=v.c_po_id
--      GROUP BY h.doc_no HAVING variasi_termin > 1;
--
-- (6) [PV-03] Semua baris satu SPB harus satu gudang (harus kosong):
--     SELECT h.doc_no FROM pv_receipt h JOIN pv_line v ON v.pv_id=h.pv_id
--       JOIN po_line l ON l.po_line_id=v.po_line_id
--      GROUP BY h.doc_no HAVING COUNT(DISTINCT h.i_warehouse_id) > 1;
-- ═══════════════════════════════════════════════════════════════════════════
