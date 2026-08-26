# TIER 3 — PROCUREMENT · DDL, VIEW, DELTA & SEED
**PT. Telasih Makmur** · DB utama `tm2pur` · MySQL 8.0

| | |
|---|---|
| **Basis** | `procurement_tier1.md`, `procurement_tier2.md` |
| **Sifat** | **3B pragmatis** — mengadopsi struktur legacy `tminv` & `m_raw_material` apa adanya, tidak merancang ulang |
| **Cakupan** | §1 Prinsip · §2 PR · §3 PQ · §4 PO · §5 PV · §6 PRT · §7 Delta lintas-DB · §8 VIEW · §9 Seed |

> **Catatan MySQL 8** (dari pelajaran modul sebelumnya):
> `ADD COLUMN IF NOT EXISTS` **tidak didukung** (itu MariaDB) — gunakan ALTER polos, buat migration idempotent di level aplikasi.
> Generated column + `ON DELETE CASCADE` bentrok → pakai `RESTRICT`.

---

## 1. PRINSIP

1. **FK lintas-DB = LOGICAL** (Eloquent), bukan constraint MySQL. FK fisik hanya di dalam satu database.
2. **Konvensi kolom**: `i_` input (boleh DEFAULT) · `c_` computed (**tanpa** DEFAULT) · turunan di VIEW `v1_`.
3. **Uang** `DECIMAL(18,4)` · **qty** `DECIMAL(20,4)` (mengikuti legacy `rm_stock_d`) · **persen** `DECIMAL(5,2)`.
4. **Tabel legacy tidak diganti nama maupun strukturnya** — hanya ditambah kolom bila perlu.
5. Audit di semua tabel transaksi: `created_by/at`, `updated_by/at`.
6. Status transisi **tidak** disimpan sebagai log per modul — seluruhnya ke `flow_log` (`doc_state_log` dibatalkan, lihat `TIER_3_FLOW_ENGINE`).

---

## 2. PR — PURCHASE REQUISITION

```sql
-- ══════════════════ tm2pur.pr_requisition ══════════════════
CREATE TABLE pr_requisition (
  pr_id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_no           VARCHAR(30)  NULL COMMENT 'PR/YY/##### — digenerate saat SUBMIT via sys_sequence',
  i_doc_date       DATE         NOT NULL,
  i_source         ENUM('MANUAL','MRP') NOT NULL DEFAULT 'MANUAL',
  mrp_run_id       BIGINT UNSIGNED NULL COMMENT 'reserved — MRP belum dibangun',
  i_department_id  BIGINT UNSIGNED NOT NULL COMMENT 'LOGICAL -> tm2mst.org_department (default menetes ke baris)',
  i_need_date      DATE         NULL,
  i_notes          VARCHAR(255) NULL,

  c_est_total      DECIMAL(18,4) NULL COMMENT 'SUM(pr_line.c_est_subtotal) - amount_snapshot approval',
  status           VARCHAR(20)  NOT NULL DEFAULT 'DRAFT'
                   COMMENT 'DRAFT|SUBMITTED|APPROVED|REJECTED|REVISION_NEEDED|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED|CANCELLED',

  revision_no      INT UNSIGNED NOT NULL DEFAULT 0,
  parent_pr_id     BIGINT UNSIGNED NULL COMMENT 'REVISE = copy-record; menunjuk versi sebelumnya',
  is_cancelled     TINYINT(1)   NOT NULL DEFAULT 0,
  cancel_reason    VARCHAR(100) NULL COMMENT 'SUPERSEDED bila digantikan revisi',

  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pr_id),
  UNIQUE KEY uq_pr_doc_no (doc_no),
  KEY idx_pr_status (status),
  KEY idx_pr_date (i_doc_date),
  KEY idx_pr_dept (i_department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Header Permintaan Pembelian';

-- ══════════════════ tm2pur.pr_line ══════════════════
CREATE TABLE pr_line (
  pr_line_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pr_id            BIGINT UNSIGNED NOT NULL,
  line_no          SMALLINT UNSIGNED NOT NULL,

  i_item_source    ENUM('MASTER_ITEM','FREE_TEXT') NOT NULL DEFAULT 'MASTER_ITEM',
  item_id          BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2mst.m_raw_material.material_id (NULL bila FREE_TEXT)',
  c_item_code      VARCHAR(60)  NULL COMMENT 'snapshot material_code, mis. PYJ31',
  i_description    VARCHAR(255) NOT NULL COMMENT 'snapshot nama item, atau teks bebas bila FREE_TEXT',

  i_qty            DECIMAL(20,4) NOT NULL,
  i_uom            VARCHAR(50)  NOT NULL COMMENT 'satuan beli; dari m_raw_material.uom_purchase atau manual',
  i_est_price      DECIMAL(18,4) NULL COMMENT 'estimasi (opsional) - referensi last_unitprice',
  c_est_subtotal   DECIMAL(18,4) NULL COMMENT 'i_qty * i_est_price',

  i_department_id  BIGINT UNSIGNED NOT NULL COMMENT 'LOGICAL -> tm2mst.org_department (boleh beda per baris)',
  i_coa_account_id BIGINT UNSIGNED NOT NULL COMMENT 'LOGICAL -> tm2acc.fin_account (is_postable=1); beban/overhead diturunkan dari klasifikasi akun',
  i_co_line_id     BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2sdi CO line - penelusuran biaya per order',

  is_stock         TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 utk SERVICE/INDIRECT/FREE_TEXT - tak masuk rm_stock',
  i_need_date      DATE         NULL,
  i_notes          VARCHAR(255) NULL,

  c_qty_allocated  DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT 'SUM(pr_line_alloc.i_qty)',
  c_qty_ordered    DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT 'SUM alokasi yang sudah jadi po_line',
  c_line_status    VARCHAR(20)  NULL COMMENT 'OPEN|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED (turunan)',

  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pr_line_id),
  UNIQUE KEY uq_pr_line (pr_id, line_no),
  KEY idx_prl_item (item_id),
  KEY idx_prl_coline (i_co_line_id),
  CONSTRAINT fk_prl_pr FOREIGN KEY (pr_id) REFERENCES pr_requisition(pr_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Baris Permintaan Pembelian';

-- ══════════════════ tm2pur.pr_line_alloc ══════════════════
-- Pemecah M:N: 1 pr_line -> N alokasi; 1 alokasi -> TEPAT 1 po_line.
CREATE TABLE pr_line_alloc (
  alloc_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pr_line_id   BIGINT UNSIGNED NOT NULL,
  alloc_no     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  i_qty        DECIMAL(20,4) NOT NULL COMMENT 'SUM per pr_line TIDAK BOLEH > pr_line.i_qty (validasi aplikasi)',
  i_supplier_id BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2mst.sup_supplier (rencana; final saat PO)',
  i_notes      VARCHAR(255) NULL,
  c_is_converted TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 bila sudah menjadi po_line',
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (alloc_id),
  UNIQUE KEY uq_alloc (pr_line_id, alloc_no),
  KEY idx_alloc_conv (c_is_converted),
  CONSTRAINT fk_alloc_prl FOREIGN KEY (pr_line_id) REFERENCES pr_line(pr_line_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Alokasi/split baris PR — penghilang relasi many-to-many PR:PO';
```

---

## 3. PQ — PURCHASE QUOTATION

```sql
CREATE TABLE pq_quotation (
  pq_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_no       VARCHAR(30)  NULL COMMENT 'PQ/YY/#####',
  i_doc_date   DATE         NOT NULL,
  i_supplier_id BIGINT UNSIGNED NOT NULL COMMENT 'LOGICAL -> tm2mst.sup_supplier (1 PQ = 1 supplier)',
  i_pr_id      BIGINT UNSIGNED NULL COMMENT 'PR acuan (opsional — PQ boleh berdiri sendiri)',

  i_quotation_valid_until DATE NOT NULL COMMENT 'MASA BERLAKU — di-snapshot ke PO & ditampilkan ke approver',
  i_currency_id BIGINT UNSIGNED NOT NULL COMMENT 'LOGICAL -> tm2mst.fin_currency (default IDR)',
  i_fx_rate     DECIMAL(18,6) NOT NULL DEFAULT 1.000000
                COMMENT 'auto-isi dari fin_currency_rate terakhir, BOLEH di-override officer',
  i_payment_term VARCHAR(50) NULL,
  i_notes      VARCHAR(255) NULL,

  c_total_idr  DECIMAL(18,4) NULL COMMENT 'SUM(pq_line.c_subtotal_idr) — pembanding antar supplier',
  status       VARCHAR(20)  NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CONFIRMED|EXPIRED|CANCELLED',

  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pq_id),
  UNIQUE KEY uq_pq_doc_no (doc_no),
  KEY idx_pq_supplier (i_supplier_id),
  KEY idx_pq_valid (i_quotation_valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Penawaran harga supplier (kondisional — boleh dilewati)';

CREATE TABLE pq_line (
  pq_line_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pq_id        BIGINT UNSIGNED NOT NULL,
  line_no      SMALLINT UNSIGNED NOT NULL,
  pr_line_id   BIGINT UNSIGNED NULL COMMENT 'baris PR yang ditawar (opsional)',
  item_id      BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> m_raw_material',
  i_description VARCHAR(255) NOT NULL,
  i_qty        DECIMAL(20,4) NOT NULL,
  i_uom        VARCHAR(50)  NOT NULL,
  i_price_fx   DECIMAL(18,4) NOT NULL COMMENT 'harga dalam mata uang penawaran',
  c_price_idr  DECIMAL(18,4) NULL COMMENT 'i_price_fx * pq_quotation.i_fx_rate',
  c_subtotal_idr DECIMAL(18,4) NULL COMMENT 'i_qty * c_price_idr',
  i_lead_time_day SMALLINT UNSIGNED NULL,
  i_notes      VARCHAR(255) NULL,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pq_line_id),
  UNIQUE KEY uq_pq_line (pq_id, line_no),
  CONSTRAINT fk_pql_pq FOREIGN KEY (pq_id) REFERENCES pq_quotation(pq_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. PO — PURCHASE ORDER

```sql
CREATE TABLE po_order (
  po_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_no       VARCHAR(30)  NULL COMMENT 'PO/YY/#####',
  i_doc_date   DATE         NOT NULL,
  i_supplier_id BIGINT UNSIGNED NOT NULL COMMENT 'LOGICAL -> tm2mst.sup_supplier',
  i_pq_id      BIGINT UNSIGNED NULL COMMENT 'penawaran acuan (NULL bila PQ dilewati)',

  i_po_type      ENUM('LOCAL','IMPORT') NOT NULL DEFAULT 'LOCAL',
  i_procure_type ENUM('REPEAT','ONE_TIME','CAPEX','CASH') NOT NULL DEFAULT 'REPEAT',

  i_currency_id  BIGINT UNSIGNED NOT NULL COMMENT 'snapshot dari PQ; INDEPENDEN dari i_po_type',
  i_fx_rate      DECIMAL(18,6) NOT NULL DEFAULT 1.000000 COMMENT 'DIBEKUKAN saat APPROVED',
  i_quotation_valid_until DATE NULL COMMENT 'snapshot masa berlaku penawaran — dipajang di layar approval',

  i_payment_term VARCHAR(50) NULL,
  i_delivery_date DATE NULL,
  i_ship_to     VARCHAR(255) NULL,
  i_notes       VARCHAR(500) NULL,

  c_dpp         DECIMAL(18,4) NULL COMMENT 'SUM(po_line.c_line_net)',
  c_ppn         DECIMAL(18,4) NULL COMMENT 'c_dpp * rate PPN (sys_constant)',
  c_pph         DECIMAL(18,4) NULL COMMENT 'SUM(c_line_net baris SERVICE) * rate PPh23',
  c_grand_total DECIMAL(18,4) NULL COMMENT 'c_dpp + c_ppn — nilai tagihan & amount_snapshot approval',
  c_net_payable DECIMAL(18,4) NULL COMMENT 'c_grand_total - c_pph — yang dibayar ke supplier',

  status        VARCHAR(20) NOT NULL DEFAULT 'DRAFT'
                COMMENT 'DRAFT|SUBMITTED|APPROVED|REJECTED|SENT|PARTIALLY_RECEIVED|RECEIVED|CLOSED|CANCELLED',
  sent_at       DATETIME NULL,

  is_auto_generated TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dibuat otomatis oleh Pembelian Kontan',
  source_note   VARCHAR(100) NULL COMMENT "mis. 'Kontan'",

  revision_no   INT UNSIGNED NOT NULL DEFAULT 0,
  parent_po_id  BIGINT UNSIGNED NULL,
  is_cancelled  TINYINT(1) NOT NULL DEFAULT 0,
  cancel_reason VARCHAR(100) NULL,

  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (po_id),
  UNIQUE KEY uq_po_doc_no (doc_no),
  KEY idx_po_status (status),
  KEY idx_po_supplier (i_supplier_id),
  KEY idx_po_date (i_doc_date),
  KEY idx_po_type (i_procure_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Header Pesanan Pembelian';

CREATE TABLE po_line (
  po_line_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_id        BIGINT UNSIGNED NOT NULL,
  line_no      SMALLINT UNSIGNED NOT NULL,

  alloc_id     BIGINT UNSIGNED NULL COMMENT 'FK 1:1 ke pr_line_alloc; NULL bila PO tanpa PR (kontan/darurat)',
  item_id      BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> m_raw_material',
  c_item_code  VARCHAR(60)  NULL,
  i_description VARCHAR(255) NOT NULL,
  i_line_class ENUM('RAW','INDIRECT','SERVICE') NOT NULL DEFAULT 'RAW'
               COMMENT 'diturunkan dari doc_rmprefix1.stock_class; SERVICE => kena PPh 23',

  i_qty        DECIMAL(20,4) NOT NULL,
  i_uom        VARCHAR(50)  NOT NULL COMMENT 'satuan beli',
  c_uom_stock  VARCHAR(50)  NULL COMMENT 'snapshot m_raw_material.uom_stock',
  c_conv_to_stock DECIMAL(20,6) NULL COMMENT 'snapshot konversi — NILAI AWAL saja, boleh ditimpa di PV',

  i_price      DECIMAL(18,4) NOT NULL COMMENT 'harga satuan dalam i_currency_id header',
  i_disc_pct   DECIMAL(5,2)  NULL,
  i_disc_amt   DECIMAL(18,4) NULL,
  c_line_net   DECIMAL(18,4) NULL COMMENT '(i_qty*i_price) - diskon',

  i_department_id  BIGINT UNSIGNED NOT NULL,
  i_coa_account_id BIGINT UNSIGNED NOT NULL,
  i_co_line_id     BIGINT UNSIGNED NULL COMMENT 'menetes dari pr_line — penelusuran biaya per CO',
  is_stock         TINYINT(1) NOT NULL DEFAULT 1,

  c_tol_pct    DECIMAL(5,2) NULL COMMENT 'snapshot m_raw_material.tol_purchase; fallback sys_constant',
  c_qty_received DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT 'akumulasi dari pv_line (satuan beli)',
  c_qty_outstanding DECIMAL(20,4) NULL COMMENT 'i_qty - c_qty_received',
  c_line_status VARCHAR(20) NULL COMMENT 'OPEN|PARTIALLY_RECEIVED|RECEIVED|CLOSED_BY_TOLERANCE',

  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (po_line_id),
  UNIQUE KEY uq_po_line (po_id, line_no),
  UNIQUE KEY uq_po_alloc (alloc_id) COMMENT 'menjamin 1 alokasi = 1 po_line (anti many-to-many)',
  KEY idx_pol_item (item_id),
  KEY idx_pol_coline (i_co_line_id),
  CONSTRAINT fk_pol_po FOREIGN KEY (po_id) REFERENCES po_order(po_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist dokumen impor — STRUKTUR SIAP, STATUS INACTIVE fase ini
CREATE TABLE po_import_doc (
  import_doc_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_id        BIGINT UNSIGNED NOT NULL,
  i_doc_code   VARCHAR(20)  NOT NULL COMMENT 'PIB|INV|PL|BL|COO|INS|NIB|HS|PI_LARTAS|LS|SNI|SPPB|DO_FF|PAY_BM|INCO',
  i_doc_name   VARCHAR(100) NOT NULL,
  is_mandatory TINYINT(1)   NOT NULL DEFAULT 0,
  is_received  TINYINT(1)   NOT NULL DEFAULT 0,
  i_doc_no     VARCHAR(100) NULL,
  i_doc_date   DATE         NULL,
  i_file_path  VARCHAR(255) NULL,
  i_notes      VARCHAR(255) NULL,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (import_doc_id),
  UNIQUE KEY uq_po_importdoc (po_id, i_doc_code),
  CONSTRAINT fk_poid_po FOREIGN KEY (po_id) REFERENCES po_order(po_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kelengkapan dokumen impor — INACTIVE fase ini (jarang impor, Bea Cukai belum dipakai)';
```

---

## 5. PV — PURCHASE RECEIVE

```sql
CREATE TABLE pv_receipt (
  pv_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_no       VARCHAR(30)  NULL COMMENT 'SPB/YY/##### (istilah legacy: Surat Penerimaan Barang)',
  i_doc_date   DATE         NOT NULL,
  po_id        BIGINT UNSIGNED NOT NULL COMMENT '1 PO boleh banyak PV (penerimaan bertahap)',
  i_supplier_id BIGINT UNSIGNED NOT NULL COMMENT 'snapshot dari PO',
  i_warehouse_id BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2mst.m_warehouse',
  i_supplier_do_no VARCHAR(50) NULL COMMENT 'no surat jalan supplier',
  i_vehicle_no  VARCHAR(30)  NULL,
  i_notes       VARCHAR(255) NULL,

  status        VARCHAR(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|QC_PENDING|POSTED|CANCELLED',
  posted_at     DATETIME NULL,
  posted_by     VARCHAR(100) NULL,
  rm_stock_doc_id BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2inv.rm_stock.doc_id hasil posting',

  is_auto_generated TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dari Pembelian Kontan',
  source_note   VARCHAR(100) NULL,

  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pv_id),
  UNIQUE KEY uq_pv_doc_no (doc_no),
  KEY idx_pv_po (po_id),
  KEY idx_pv_status (status),
  KEY idx_pv_date (i_doc_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Header Penerimaan Barang (SPB)';

CREATE TABLE pv_line (
  pv_line_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pv_id        BIGINT UNSIGNED NOT NULL,
  po_line_id   BIGINT UNSIGNED NOT NULL,
  line_no      SMALLINT UNSIGNED NOT NULL,

  item_id      BIGINT UNSIGNED NULL,
  c_item_code  VARCHAR(60)  NULL,
  i_description VARCHAR(255) NOT NULL,
  is_stock     TINYINT(1)   NOT NULL DEFAULT 1,

  -- ── DUAL-UOM: dua sisi INDEPENDEN, tidak selalu terkonversi ──
  i_qty_purchase DECIMAL(20,4) NOT NULL COMMENT 'satuan BELI — menutup po_line & dasar tagihan (mis. 3 TRUK)',
  i_uom_purchase VARCHAR(50)  NOT NULL,
  i_qty_stock    DECIMAL(20,4) NOT NULL COMMENT 'satuan STOK aktual — hasil hitung/timbang (mis. total KG)',
  i_uom_stock    VARCHAR(50)  NOT NULL,
  c_conv_used    DECIMAL(20,6) NULL COMMENT 'i_qty_stock / i_qty_purchase — jejak konversi nyata',
  is_conv_manual TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = officer menimpa konversi master (barang curah)',

  -- ── QC dua gerbang ──
  qc_qty_status     ENUM('PENDING','DONE') NOT NULL DEFAULT 'PENDING',
  qc_quality_status ENUM('PENDING','PASS','PARTIAL','FAIL') NOT NULL DEFAULT 'PENDING',
  c_qty_accepted    DECIMAL(20,4) NULL COMMENT 'satuan stok — HANYA INI yang masuk gudang',
  c_qty_rejected    DECIMAL(20,4) NULL COMMENT 'satuan stok — kandidat retur',
  qc_note           VARCHAR(255) NULL,
  qc_by             VARCHAR(100) NULL,
  qc_at             DATETIME NULL,

  -- ── Toleransi ──
  c_tol_pct         DECIMAL(5,2) NULL COMMENT 'snapshot batas item',
  c_qty_variance_pct DECIMAL(8,4) NULL,
  is_tol_exceeded   TINYINT(1) NOT NULL DEFAULT 0,
  tol_override_by   VARCHAR(100) NULL,
  tol_override_reason VARCHAR(255) NULL,

  -- ── Nilai (Sisi A) — tersimpan selalu, TIDAK ditampilkan di layar gudang ──
  c_unit_price  DECIMAL(18,4) NULL COMMENT 'snapshot po_line.i_price',
  c_line_value  DECIMAL(18,4) NULL COMMENT 'c_qty_accepted * c_unit_price',

  i_lot_no      VARCHAR(100) NULL,
  i_location_id BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2mst.m_wh_loc',
  i_co_line_id  BIGINT UNSIGNED NULL COMMENT 'menetes dari po_line',
  c_qty_invoiced DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT 'akumulasi tertagih (satuan beli)',
  c_qty_returned DECIMAL(20,4) NOT NULL DEFAULT 0,

  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pv_line_id),
  UNIQUE KEY uq_pv_line (pv_id, line_no),
  KEY idx_pvl_poline (po_line_id),
  KEY idx_pvl_item (item_id),
  KEY idx_pvl_qc (qc_quality_status),
  CONSTRAINT fk_pvl_pv FOREIGN KEY (pv_id) REFERENCES pv_receipt(pv_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.1 Kontrak POST → `tm2inv` (WAJIB, dalam satu transaksi DB)
```
UNTUK tiap pv_line dengan is_stock=1 DAN c_qty_accepted > 0:

1. INSERT tm2inv.rm_stock
     doc_no       = pv_receipt.doc_no
     doc_date     = pv_receipt.i_doc_date
     doc_type     = 1                       -- IN
     doc_cat      = 'STOCK'
     warehouse_id = pv_receipt.i_warehouse_id
     notes        = CONCAT('Auto dari PV: ', doc_no)   -- pola legacy dipertahankan
     stat_confirm = 1                       -- memicu trigger stockcard
     create_by    = <user>

2. INSERT tm2inv.rm_stock_d  (per baris)
     doc_id         = <hasil langkah 1>
     material_id    = pv_line.item_id
     qty            = pv_line.c_qty_accepted        -- SISI STOK, bukan sisi beli
     uom_code       = pv_line.i_uom_stock
     unit_price     = pv_line.c_unit_price
     qty_unitprice  = pv_line.c_line_value
     lot_no         = pv_line.i_lot_no
     reference_id   = pv_line.pv_line_id
     reference_name = 'tm2pur.pv_line'
     location_id    = pv_line.i_location_id

3. Engine harga perolehan menurut m_raw_material.costing_method:
     AVG       -> perbarui avg_unitprice (moving average) + last_unitprice
     FIFO/LIFO -> INSERT tm2inv.inv_stock_layer (qty_remaining, unit_cost, received_at)

4. UPDATE pv_receipt SET status='POSTED', posted_at=NOW(), rm_stock_doc_id=<langkah 1>
5. UPDATE po_line.c_qty_received (+= i_qty_purchase), hitung ulang c_line_status
6. UPDATE po_order.status: PARTIALLY_RECEIVED / RECEIVED
7. Bila seluruh PO turunan PR sudah RECEIVED -> step AUTO flow engine menutup PR (CLOSED)
```
**Gerbang:** POST ditolak bila masih ada baris `qc_quality_status='PENDING'`, atau `is_tol_exceeded=1` tanpa `tol_override_by`.

---

## 6. PRT — PURCHASE RETURN

```sql
CREATE TABLE prt_return (
  prt_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_no       VARCHAR(30)  NULL COMMENT 'NRB/YY/#####',
  i_doc_date   DATE         NOT NULL,
  i_supplier_id BIGINT UNSIGNED NOT NULL,
  pv_id        BIGINT UNSIGNED NULL COMMENT 'PV asal (NULL bila retur dari stok)',
  po_id        BIGINT UNSIGNED NULL,
  i_source     ENUM('QC_REJECT','FROM_STOCK') NOT NULL DEFAULT 'QC_REJECT',
  i_disposition ENUM('REPLACEMENT','CREDIT_NOTE') NOT NULL,
  i_reason     VARCHAR(255) NOT NULL,
  c_credit_value DECIMAL(18,4) NULL COMMENT 'nilai pengurang tagihan bila CREDIT_NOTE',
  replacement_pv_id BIGINT UNSIGNED NULL COMMENT 'PV barang pengganti bila REPLACEMENT',
  rm_stock_doc_id BIGINT UNSIGNED NULL COMMENT 'rm_stock OUT (doc_cat=RETURN) bila stok sudah ter-posting',
  status       VARCHAR(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CONFIRMED|COMPLETED|CANCELLED',
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (prt_id),
  UNIQUE KEY uq_prt_doc_no (doc_no),
  KEY idx_prt_pv (pv_id),
  KEY idx_prt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Retur Pembelian — tukar barang atau nota kredit';

CREATE TABLE prt_line (
  prt_line_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prt_id       BIGINT UNSIGNED NOT NULL,
  pv_line_id   BIGINT UNSIGNED NULL,
  line_no      SMALLINT UNSIGNED NOT NULL,
  item_id      BIGINT UNSIGNED NULL,
  c_item_code  VARCHAR(60) NULL,
  i_description VARCHAR(255) NOT NULL,
  i_qty        DECIMAL(20,4) NOT NULL COMMENT 'satuan stok',
  i_uom        VARCHAR(50)  NOT NULL,
  c_unit_price DECIMAL(18,4) NULL,
  c_line_value DECIMAL(18,4) NULL,
  is_stock_moved TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 bila qty ini pernah masuk stok -> perlu OUT',
  i_lot_no     VARCHAR(100) NULL,
  i_notes      VARCHAR(255) NULL,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (prt_line_id),
  UNIQUE KEY uq_prt_line (prt_id, line_no),
  CONSTRAINT fk_prtl_prt FOREIGN KEY (prt_id) REFERENCES prt_return(prt_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 7. DELTA LINTAS-DATABASE (WAJIB dijalankan sebelum implementasi)

### 7.1 `tm2mst` — master item & kategori
```sql
-- Kelas stok di level kategori induk -> menuntun klasifikasi & akun beban
ALTER TABLE doc_rmprefix1
  ADD COLUMN stock_class ENUM('RAW','INDIRECT','SERVICE') NOT NULL DEFAULT 'RAW'
      COMMENT 'i_ = penentu apakah kategori masuk stok / beban langsung / jasa',
  ADD COLUMN default_coa_account_id BIGINT UNSIGNED NULL
      COMMENT 'i_ = usulan akun COA (LOGICAL -> tm2acc.fin_account); boleh diubah officer';

-- Item master: metode harga perolehan & penanda sensitif kurs
ALTER TABLE m_raw_material
  ADD COLUMN costing_method ENUM('AVG','FIFO','LIFO') NOT NULL DEFAULT 'AVG'
      COMMENT 'i_ = metode harga perolehan PER ITEM (kertas vs sparepart boleh beda)',
  ADD COLUMN is_fx_sensitive TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'i_ = harga mengikuti kurs -> tampilkan peringatan di PR/PQ',
  ADD COLUMN prefix5_id INT UNSIGNED NULL
      COMMENT 'i_ = FK ke doc_rmprefix5.idprefix; material_code dirangkai dari 5 level';
```
> `tol_purchase` **sudah ada** di `m_raw_material` — dipakai apa adanya sebagai batas toleransi per item. Tidak menambah kolom baru.
> `category1..5` legacy (teks) dipertahankan untuk kompatibilitas; `prefix5_id` menjadi sumber kebenaran baru. Lihat issue `PROC-ITEMCODE-GEN`.

### 7.2 `tm2acc` — rincian baris faktur (delta lintas modul)
```sql
-- Kaitan sumber Procurement
ALTER TABLE fin_invoice
  ADD COLUMN source_po_id BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2pur.po_order (SUPPLIER_DP)',
  ADD COLUMN source_pv_id BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2pur.pv_receipt (SUPPLIER_BILL)',
  ADD INDEX idx_fin_inv_po (source_po_id),
  ADD INDEX idx_fin_inv_pv (source_pv_id);

-- BARU: rincian baris faktur (dibutuhkan Sales MAUPUN Purchase)
CREATE TABLE fin_invoice_line (
  invoice_line_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id      BIGINT UNSIGNED NOT NULL,
  line_no         SMALLINT UNSIGNED NOT NULL,
  source_type     ENUM('PRODUCT','MATERIAL','SERVICE','FREE_TEXT') NOT NULL,
  source_ref_id   BIGINT UNSIGNED NULL COMMENT 'prod_product.id (Sales) ATAU m_raw_material.material_id (Purchase)',
  item_code       VARCHAR(60)  NULL COMMENT 'SNAPSHOT — nota tak berubah bila master diedit',
  item_name       VARCHAR(255) NOT NULL COMMENT 'SNAPSHOT',
  qty             DECIMAL(20,4) NOT NULL,
  uom             VARCHAR(50)  NOT NULL,
  unit_price      DECIMAL(18,4) NOT NULL,
  disc_pct        DECIMAL(5,2)  NULL,
  disc_amt        DECIMAL(18,4) NULL,
  line_subtotal   DECIMAL(18,4) NOT NULL,
  co_line_id      BIGINT UNSIGNED NULL COMMENT 'Sales — penelusuran per CO',
  pv_line_id      BIGINT UNSIGNED NULL COMMENT 'Purchase — realisasi terima',
  department_id   BIGINT UNSIGNED NULL,
  coa_account_id  BIGINT UNSIGNED NULL,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (invoice_line_id),
  UNIQUE KEY uq_fin_inv_line (invoice_id, line_no),
  KEY idx_finl_pvline (pv_line_id),
  KEY idx_finl_coline (co_line_id),
  CONSTRAINT fk_finl_inv FOREIGN KEY (invoice_id) REFERENCES fin_invoice(invoice_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rincian baris faktur (AR & AP) — subtotal/tax header menjadi hasil hitung dari sini';
```
> **Perhatian:** `fin_invoice.invoice_type` sudah memuat `SUPPLIER_BILL` & `SUPPLIER_DP` (terpasang di MySQL sebelum sesi 2026-08-19 — lihat `financeaccounting_state.yaml`). **Tidak perlu ALTER enum lagi.** Verifikasi dulu sebelum eksekusi.
> Nama PK `fin_invoice.invoice_id` — konfirmasi terhadap DDL aktual sebelum menjalankan FK (`FIN_STATE` mencatat PK `fin_dp_allocation` aslinya `allocation_id`, berbeda dari draf dokumen).

### 7.3 `tm2inv` — nilai & lapisan harga
```sql
-- Kolom NILAI di kartu stok (untuk AVG) — struktur legacy dipertahankan
ALTER TABLE rm_stockcard
  ADD COLUMN value_bb DECIMAL(20,4) NULL COMMENT 'c_ = nilai saldo sebelum movement',
  ADD COLUMN value_in DECIMAL(20,4) NULL,
  ADD COLUMN value_out DECIMAL(20,4) NULL,
  ADD COLUMN value_eb DECIMAL(20,4) NULL COMMENT 'c_ = nilai saldo setelah movement',
  ADD COLUMN avg_unitprice DECIMAL(30,5) NULL COMMENT 'c_ = harga rata-rata bergerak setelah movement';

-- Lapisan lot untuk FIFO/LIFO (milik modul Inventory; PV hanya MENGISI saat IN)
CREATE TABLE inv_stock_layer (
  layer_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  material_id   BIGINT UNSIGNED NOT NULL,
  warehouse_id  BIGINT UNSIGNED NULL,
  lot_no        VARCHAR(100) NULL,
  qty_in        DECIMAL(20,4) NOT NULL,
  qty_remaining DECIMAL(20,4) NOT NULL COMMENT 'dikurangi saat OUT sesuai FIFO/LIFO',
  unit_cost     DECIMAL(30,5) NOT NULL,
  uom_code      VARCHAR(50)  NULL,
  received_at   DATETIME     NOT NULL COMMENT 'dasar urutan FIFO/LIFO',
  reference_id   BIGINT UNSIGNED NULL COMMENT 'pv_line_id',
  reference_name VARCHAR(100) NULL COMMENT "'tm2pur.pv_line'",
  is_exhausted  TINYINT(1) NOT NULL DEFAULT 0,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (layer_id),
  KEY idx_layer_mat (material_id, is_exhausted, received_at),
  KEY idx_layer_ref (reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lapisan harga perolehan per lot — penopang FIFO/LIFO; stockcard tetap ringkasan saldo';
```

### 7.4 `tm2sys` — konstanta & urutan nomor
```sql
INSERT INTO sys_constant (const_code, const_value, const_type, description, is_active) VALUES
  ('PPN',                     '11',  'PERCENT', 'Tarif PPN (%)',                            1),
  ('PPH23_JASA',              '2',   'PERCENT', 'Tarif PPh 23 atas jasa (%)',               1),
  ('PPH22_IMPOR_API',         '2.5', 'PERCENT', 'PPh 22 impor ber-API (%) — belum dipakai', 0),
  ('RECEIVE_TOLERANCE_PCT',   '5',   'PERCENT', 'Toleransi terima default bila tol_purchase item kosong', 1);
```
> Tarif pajak **di `sys_constant`**, bukan tabel `fin_tax_scheme` baru — keputusan Jo 2026-08-20.
> Sesuaikan nama kolom dengan struktur `sys_constant` aktual sebelum eksekusi (lihat issue `PROC-SYSCONST-COL`).

Urutan nomor via `sys_sequence`: `PR/YY/#####`, `PQ/YY/#####`, `PO/YY/#####`, `SPB/YY/#####`, `NRB/YY/#####`.

---

## 8. VIEW

```sql
-- Ringkasan konsolidasi per item untuk approver PO (baris tetap granular di DB)
CREATE OR REPLACE VIEW v1_po_item_consolidated AS
SELECT l.po_id,
       COALESCE(l.c_item_code, '(bebas)') AS item_code,
       l.i_description,
       l.i_uom,
       COUNT(*)              AS line_count,
       SUM(l.i_qty)          AS total_qty,
       SUM(l.c_line_net)     AS total_net,
       MIN(l.i_price)        AS min_price,
       MAX(l.i_price)        AS max_price
FROM po_line l
GROUP BY l.po_id, item_code, l.i_description, l.i_uom;

-- Sisa PR yang belum dialokasikan/di-PO-kan
CREATE OR REPLACE VIEW v1_pr_outstanding AS
SELECT h.pr_id, h.doc_no, h.i_doc_date, h.status, h.i_department_id,
       l.pr_line_id, l.line_no, l.c_item_code, l.i_description, l.i_uom,
       l.i_qty, l.c_qty_allocated, l.c_qty_ordered,
       (l.i_qty - l.c_qty_ordered) AS qty_open, l.i_co_line_id
FROM pr_requisition h
JOIN pr_line l ON l.pr_id = h.pr_id
WHERE h.status IN ('APPROVED','PARTIALLY_ORDERED')
  AND h.is_cancelled = 0
  AND l.i_qty > l.c_qty_ordered;

-- Sisa PO yang belum diterima
CREATE OR REPLACE VIEW v1_po_outstanding AS
SELECT h.po_id, h.doc_no, h.i_doc_date, h.i_supplier_id, h.status, h.i_delivery_date,
       l.po_line_id, l.line_no, l.c_item_code, l.i_description,
       l.i_qty, l.i_uom, l.c_qty_received,
       (l.i_qty - l.c_qty_received) AS qty_outstanding,
       l.c_tol_pct, l.i_co_line_id
FROM po_order h
JOIN po_line l ON l.po_id = h.po_id
WHERE h.status IN ('SENT','PARTIALLY_RECEIVED')
  AND h.is_cancelled = 0
  AND l.i_qty > l.c_qty_received;

-- Realisasi biaya material per Customer Order (rantai CO -> PR -> PO -> PV)
CREATE OR REPLACE VIEW v1_material_cost_per_co AS
SELECT v.i_co_line_id AS co_line_id,
       v.item_id, v.c_item_code, v.i_description,
       SUM(v.c_qty_accepted) AS qty_received,
       SUM(v.c_line_value)   AS value_received
FROM pv_line v
JOIN pv_receipt r ON r.pv_id = v.pv_id
WHERE r.status = 'POSTED' AND v.i_co_line_id IS NOT NULL
GROUP BY v.i_co_line_id, v.item_id, v.c_item_code, v.i_description;
```

**VIEW nota** (`vw_nota_pr`, `vw_nota_po`, `vw_nota_pv`, `vw_nota_prt`) menggabungkan header + baris + data master (nama supplier, departemen) dalam bentuk siap cetak. `vw_nota_pv` **tidak menyertakan kolom harga** (Sisi B). Definisi kolom mengikuti layout Tier 4 §7.

---

## 9. SEED FLOW ENGINE

```sql
-- Config per modul (division_id NULL = berlaku company-wide)
INSERT INTO flow_config (module_code, division_id, is_active, description) VALUES
  ('PR',  NULL, 1, 'Approval Permintaan Pembelian'),
  ('PO',  NULL, 1, 'Approval Pesanan Pembelian'),
  ('PV',  NULL, 1, 'Alur Penerimaan Barang (tanpa approval)'),
  ('PRT', NULL, 1, 'Konfirmasi Retur Pembelian');
```

**Rancangan step** (ganti `position_id` dengan id `org_position` riil sebelum eksekusi — lihat issue `PROC-FLOW-POSITION`):

| module | step | status_from → status_to | type | jabatan | min_amount | max_amount | status_to_reject |
|---|---|---|---|---|---|---|---|
| PR | 1 | `SUBMITTED` → `APPROVED` | APPROVAL | Kepala Departemen | 0 | NULL | `REVISION_NEEDED` |
| PR | 2 | `APPROVED` → `APPROVED` | APPROVAL | Direksi | 50.000.000 | NULL | `REVISION_NEEDED` |
| PR | 3 | `FULLY_ORDERED` → `CLOSED` | AUTO | — | 0 | NULL | — |
| PO | 1 | `SUBMITTED` → `APPROVED` | APPROVAL | Manajer Operasional | 0 | NULL | `DRAFT` |
| PO | 2 | `SUBMITTED` → `APPROVED` | APPROVAL | Direksi | 100.000.000 | NULL | `DRAFT` |
| PV | 1 | `QC_PENDING` → `POSTED` | AUTO | — | 0 | NULL | — |
| PRT | 1 | `DRAFT` → `CONFIRMED` | APPROVAL | Manajer Operasional | 0 | NULL | `DRAFT` |

Untuk PO `i_procure_type='CASH'` (kontan), step approval diganti `NOTIFY_ONLY` ke Accounting/Manajer Operasional — barang sudah dibeli, alur tidak boleh memblokir.

---

## 10. URUTAN EKSEKUSI

1. Delta `tm2mst` (§7.1) — kategori & item master.
2. Delta `tm2sys` (§7.4) — `sys_constant` + `sys_sequence`.
3. Tabel `tm2pur` (§2–§6) sesuai urutan (induk sebelum anak).
4. Delta `tm2inv` (§7.3) — nilai stockcard + `inv_stock_layer`.
5. Delta `tm2acc` (§7.2) — **verifikasi struktur aktual dulu**.
6. VIEW (§8).
7. Seed flow engine (§9) — **setelah `org_position` terisi**.
8. Registrasi menu `sys_app_menu` (status awal `NOT_BUILT`).
