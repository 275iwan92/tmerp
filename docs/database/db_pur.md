---
name: db_pur
description: Skema database Procurement (Purchase Requisition/Quotation/Order/Receipt/Return) modul tm2pur
---

# Database: tm2pur (koneksi Eloquent: `pur`)

**Sumber DDL**: `docs/ddl/tm2pur.sql`
**Jumlah tabel**: 8

## Daftar Tabel

### `pr_requisition`
Header Purchase Requisition (PR) -- permintaan pembelian dari departemen, sebelum jadi PO.
- PK: `pr_id`
- Kolom: `doc_no` (varchar30, unik, format PR/YY/#####), `i_doc_date`, `i_source` (enum MANUAL/MRP/SUBCONTRACT), `mrp_run_id` (reserved, MRP belum dibangun), `i_department_id` (LOGICAL -> tm2mst.org_department), `i_need_date`, `i_notes`, `c_est_total` (snapshot SUM baris), `status` (varchar20: DRAFT|SUBMITTED|APPROVED|REJECTED|REVISION_NEEDED|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED|CANCELLED), `revision_no`, `parent_pr_id`, `stat_cancel`, `cancel_reason`, `created_at/by`, `updated_at/by`
- FK internal: -
- Kemungkinan FK logis lintas-DB: `i_department_id` -> tm2mst.org_department

### `pr_line`
Baris item PR, sumber MASTER_ITEM (dari `m_raw_material`) atau FREE_TEXT.
- PK: `pr_line_id`
- Kolom: `pr_id`, `line_no`, `i_item_source` (enum MASTER_ITEM/FREE_TEXT), `item_id` (LOGICAL -> tm2mst.m_raw_material), `c_item_code` (snapshot), `i_description` (snapshot/teks bebas), `i_qty`, `i_uom`, `i_est_price`, `c_est_subtotal`, `i_department_id` (LOGICAL -> tm2mst.org_department, boleh beda per baris), `i_coa_account_id` (LOGICAL -> tm2acc.fin_account), `i_co_line_id` (LOGICAL -> tm2sdi.co_order_item, penelusuran biaya), `is_stock`, `i_need_date`, `i_notes`, `c_qty_allocated`, `c_qty_ordered`, `c_line_status` (OPEN|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED), audit kolom
- FK internal: `pr_id -> pr_requisition` (CASCADE)
- Kemungkinan FK logis lintas-DB: `item_id` -> tm2mst.m_raw_material, `i_coa_account_id` -> tm2acc.fin_account, `i_co_line_id` -> tm2sdi.co_order_item

### `pr_line_alloc`
Alokasi baris PR ke supplier (1 baris PR bisa dipecah ke beberapa supplier); jadi pengikat ke `po_line` via `alloc_id`.
- PK: `alloc_id`
- Kolom: `pr_line_id`, `alloc_no`, `i_qty` (SUM per pr_line tidak boleh > pr_line.i_qty, validasi app), `i_supplier_id` (LOGICAL -> tm2mst.sup_supplier), `i_notes`, `c_is_converted` (1 bila sudah jadi po_line), audit kolom
- FK internal: `pr_line_id -> pr_line` (CASCADE)
- Kemungkinan FK logis lintas-DB: `i_supplier_id` -> tm2mst.sup_supplier

### `pq_quotation`
Header Purchase Quotation (penawaran harga dari supplier), opsional acuan PR.
- PK: `pq_id`
- Kolom: `doc_no` (unik), `i_doc_date`, `i_supplier_id` (LOGICAL -> tm2mst.sup_supplier, 1 PQ = 1 supplier), `i_pr_id` (opsional, PQ boleh berdiri sendiri -- FK logis internal ke pr_requisition tapi TIDAK dideklarasikan FK fisik), `i_quotation_valid_until`, `i_currency_id` (LOGICAL -> tm2mst.fin_currency), `i_fx_rate`, `i_payment_term`, `i_lead_time_day`, `i_notes`, `c_total_idr`, `status` (DRAFT|CONFIRMED|EXPIRED|CANCELLED), `cancel_reason`, audit kolom
- FK internal: - (tidak ada FK fisik ke pr_requisition meski disebut relasi logis)
- Kemungkinan FK logis lintas-DB: `i_supplier_id` -> tm2mst.sup_supplier, `i_currency_id` -> tm2mst.fin_currency

### `pq_line`
Baris item PQ.
- PK: `pq_line_id`
- Kolom: `pq_id`, `line_no`, `pr_line_id` (opsional, baris PR yang ditawar), `item_id` (LOGICAL -> tm2mst.m_raw_material), `i_description`, `i_qty`, `i_uom`, `i_price_fx` (harga dalam mata uang penawaran), `c_price_idr`, `c_subtotal_idr`, `i_lead_time_day`, `i_notes`, audit kolom
- FK internal: `pq_id -> pq_quotation` (CASCADE)
- Kemungkinan FK logis lintas-DB: `item_id` -> tm2mst.m_raw_material

### `po_order`
Header Purchase Order.
- PK: `po_id`
- Kolom: `doc_no` (PO/YY/#####, digenerate saat SUBMIT), `i_doc_date`, `i_supplier_id` (LOGICAL -> tm2mst.sup_supplier), `i_pq_id` (acuan penawaran, selalu NULL fase ini -- PQ belum dipakai penuh), `i_po_type` (enum LOCAL/IMPORT/SUBCONTRACT), `i_procure_type` (enum REPEAT/ONE_TIME/CAPEX/CASH), `i_currency_id` (LOGICAL -> tm2mst.fin_currency, NULL fase ini), `i_fx_rate`, `i_quotation_valid_until`, `i_payment_term`, `i_delivery_date`, `i_ship_to`, `i_notes`, `c_dpp`, `c_ppn`, `c_pph`, `c_grand_total`, `c_net_payable`, `status` (varchar20: DRAFT|SUBMITTED|APPROVED|REJECTED|SENT|PARTIALLY_RECEIVED|RECEIVED|CLOSED|CANCELLED), `sent_at`, `is_auto_generated` (1 = dari Pembelian Kontan), `source_note`, `revision_no`, `parent_po_id`, `stat_cancel`, `cancel_reason`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: `i_supplier_id` -> tm2mst.sup_supplier, `i_currency_id` -> tm2mst.fin_currency

### `po_line`
Baris item PO.
- PK: `po_line_id`
- Kolom: `po_id`, `line_no`, `alloc_id` (FK 1:1 ke pr_line_alloc; NULL bila PO tanpa PR/kontan-darurat), `item_id` (LOGICAL -> tm2mst.m_raw_material), `c_item_code`, `i_description`, `i_line_class` (enum RAW/INDIRECT/SERVICE, diturunkan dari stock_class; SERVICE kena PPh23), `i_qty`, `i_uom`, `c_uom_stock`, `c_conv_to_stock`, `i_price`, `i_disc_pct`, `i_disc_amt`, `c_line_net`, `i_department_id`, `i_coa_account_id`, `i_co_line_id`, `is_stock`, `is_receivable` (0 = baris SERVICE, ditutup lewat PO bukan PV), `c_tol_pct`, `c_qty_received`, `c_qty_outstanding`, `c_line_status` (OPEN|PARTIALLY_RECEIVED|RECEIVED|CLOSED_BY_TOLERANCE), audit kolom
- FK internal: `alloc_id -> pr_line_alloc` (SET NULL, unique 1:1), `po_id -> po_order` (CASCADE)
- Kemungkinan FK logis lintas-DB: `item_id` -> tm2mst.m_raw_material, `i_co_line_id` -> tm2sdi.co_order_item, `i_coa_account_id` -> tm2acc.fin_account

### `po_import_doc`
Dokumen impor pendukung 1 PO tipe IMPORT (PIB, Invoice, Packing List, Bill of Lading, dst).
- PK: `import_doc_id`
- Kolom: `po_id`, `i_doc_code` (varchar20: PIB|INV|PL|BL|COO|INS|NIB|HS|PI_LARTAS|LS|SNI|SPPB|DO_FF|PAY_BM|INCO), `i_doc_name`, `is_mandatory`, `is_received`, `i_doc_no`, `i_doc_date`, `i_file_path`, `i_notes`, audit kolom
- FK internal: `po_id -> po_order` (CASCADE), unique(`po_id`,`i_doc_code`)
- Kemungkinan FK logis lintas-DB: -

### `pv_receipt`
Header Penerimaan Barang (Surat Penerimaan Barang/SPB) atas satu atau lebih PO ke satu supplier+gudang.
- PK: `pv_id`
- Kolom: `doc_no` (SPB/YY/##### style, tanpa slash), `i_doc_date`, `i_supplier_id` (KUNCI DOKUMEN, HARD BLOCK semua baris wajib dari supplier ini), `i_warehouse_code` (KUNCI DOKUMEN, HARD BLOCK; teks bebas -- master gudang belum diikat FK), `i_supplier_do_no`, `i_vehicle_no`, `i_notes`, `c_po_count` (ringkasan cetak saja), `c_po_doc_list` (ringkasan cetak saja, daftar no PO dipisah koma), `status` (DRAFT|QC_PENDING|POSTED|CANCELLED), `posted_at`, `posted_by`, `rm_stock_doc_id` (LOGICAL -> tm2inv.rm_stock, NULL sampai posting stok), audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: `i_supplier_id` -> tm2mst.sup_supplier, `rm_stock_doc_id` -> tm2inv.rm_stock

### `pv_line`
Baris penerimaan barang, mengikat fisik ke `po_line` (1 DB sama) + snapshot data PO utk cetak & QC.
- PK: `pv_line_id`
- Kolom: `pv_id`, `line_no`, `po_line_id` (pengikat fisik), `c_po_id`/`c_po_doc_no`/`c_po_line_no` (snapshot PO asal), `item_id`, `c_item_code`, `i_description`, `is_stock`, `i_supplier_do_no` (SJ khusus baris ini), `i_qty_purchase`/`i_uom_purchase` (satuan beli), `i_qty_stock`/`i_uom_stock` (satuan stok aktual), `c_conv_used`, `is_conv_manual`, `qc_qty_status` (enum PENDING/DONE), `qc_quality_status` (enum PENDING/PASS/PARTIAL/FAIL), `c_qty_accepted`, `c_qty_rejected`, `qc_note`, `qc_by`, `qc_at`, `c_tol_pct`, `c_qty_variance_pct`, `is_tol_exceeded`, `tol_override_by/at/reason`, `c_unit_price`, `c_line_value`, `i_lot_no`, `i_location_code` (teks bebas, master lokasi gudang belum ada), `i_co_line_id` (snapshot dari po_line), `c_qty_invoiced`, `c_qty_returned`, audit kolom
- FK internal: `po_line_id -> po_line` (RESTRICT), `pv_id -> pv_receipt` (CASCADE)
- Kemungkinan FK logis lintas-DB: -

### `prt_return`
Header retur barang ke supplier (dari QC reject atau dari stok).
- PK: `prt_id`
- Kolom: `doc_no` (NRB/YY/#####), `i_doc_date`, `i_supplier_id`, `pv_id` (PV asal, NULL bila retur dari stok), `po_id`, `i_source` (QC_REJECT|FROM_STOCK), `i_disposition` (REPLACEMENT|CREDIT_NOTE), `i_reason`, `c_credit_value`, `replacement_pv_id` (PV pengganti header-level), `rm_stock_doc_id` (LOGICAL -> tm2inv.rm_stock OUT doc_cat=RETURN), `qc_inspector`, `comm_method`, `supplier_pic`, `physical_return_date`, `expedition`, `cn_supplier_no`, `cn_received_date`, `replacement_due_date`, `status` (DRAFT|SUBMITTED|CONFIRMED|COMPLETED|CANCELLED), `cancel_reason`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: `i_supplier_id` -> tm2mst.sup_supplier, `rm_stock_doc_id` -> tm2inv.rm_stock

### `prt_line`
Baris retur.
- PK: `prt_line_id`
- Kolom: `prt_id`, `pv_line_id`, `line_no`, `item_id`, `c_item_code`, `i_description`, `i_qty` (satuan stok), `i_uom`, `c_unit_price`, `c_line_value`, `is_stock_moved` (1 bila qty ini pernah masuk stok -> perlu Stock Out RETURN), `i_lot_no`, `i_notes`, `replacement_pv_id`, `replacement_qty`, `replacement_status` (PENDING|RECEIVED), audit kolom
- FK internal: `prt_id -> prt_return` (CASCADE)
- Kemungkinan FK logis lintas-DB: -

## Catatan
- Semua tabel sudah pakai konvensi `stat_cancel`/`cancel_reason` (bukan `is_cancelled`) sesuai DEC-FIELDNAME-STATUS.
- Modul PQ (Purchase Quotation) tabelnya sudah ada & FK internal tegak, tapi datanya kosong di dump (belum dipakai nyata; `po_order.i_pq_id` "selalu NULL fase ini").
- `i_warehouse_code` di `pv_receipt` masih teks bebas -- gudang master (tm2mst wh_*) belum diikat FK/validasi ke sini (dicatat eksplisit di komentar DDL sebagai gap: PROC-WAREHOUSE-MASTER).
- Banyak kolom `_id` "LOGICAL" merujuk tm2mst/tm2acc/tm2sdi/tm2inv -- sesuai aturan CLAUDE.md §2, ini validasi Laravel-level (`exists:conn.table,col`), bukan FK fisik.
- Data dummy/test banyak dipakai user `seed_dummy`, `functional_test`, `test_*` -- konsisten dgn kebijakan "keep dummy data, never delete" di memory user.
