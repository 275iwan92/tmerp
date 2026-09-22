# TIER 3 — INVENTORY / STOCK MOVEMENT · DDL, DELTA, VIEW & SEED

> Database: **`tm2inv`**. Delta lintas DB ke `tm2mst` dan `tm2sys` ditandai jelas.
> **Semua skrip di bawah idempoten-aman untuk dijalankan sekali**, sesuai urutan §10.
> Verifikasi struktur aktual sebelum `ALTER` — dump acuan `tminv_20260722_2018_sd.sql`
> berasal dari skema lama `tminv`, dan target adalah `tm2inv`.

---

## 1. PRINSIP

1. **Struktur legacy diadopsi apa adanya.** `rm_stock` / `rm_stock_d` / `rm_stockcard` dan padanan SFG/FG **tidak dirancang ulang** dan tidak diganti nama. Keputusan PROC-C tetap berlaku.
2. **Konvensi penamaan kolom mengikuti tabel induknya, bukan konvensi global.** Tabel legacy memakai `create_by` / `create_log`; kolom baru di tabel yang sama **ikut pola itu**. Mencampur `created_at` dan `create_log` dalam satu tabel jauh lebih berbahaya daripada menyimpang dari konvensi global. Tabel **baru** (`inv_period`, `inv_stock_layer`, `inv_layer_consumption`) memakai konvensi global penuh (`i_` / `c_` / `created_at`).
3. **FK lintas database bersifat logis** (Eloquent), bukan constraint MySQL.
4. **Trigger di-DROP.** Posting menjadi tanggung jawab `StockPostingService` (alasan lengkap: Tier 1 §4).
5. `rm_stock_d_bak`, `rm_stockmst`, `fg_stockmst` **tidak dimigrasi** — sisa eksperimen legacy.

---

## 2. DROP TRIGGER LEGACY

Wajib dijalankan **sebelum** aplikasi baru menyentuh tabel stok. Selama trigger masih hidup, setiap posting dari service akan menghasilkan baris kartu stok ganda.

```sql
USE tm2inv;
DROP TRIGGER IF EXISTS `trg_rm_stock_confirm`;
DROP TRIGGER IF EXISTS `trg_sfg_stock_confirm`;
DROP TRIGGER IF EXISTS `trg_fg_stock_confirm`;
```

Procedure legacy (`sp_rm_rebuild_stockcard`, `sp_rm_stock_card`, `sp_rm_master_stock`, dan padanan SFG/FG) **dipertahankan** — berguna untuk rebuild kartu stok setelah migrasi dan sebagai pembanding hasil service. Tidak dipanggil dari aplikasi.

---

## 3. DELTA HEADER — `rm_stock`

```sql
USE tm2inv;

ALTER TABLE `rm_stock`
  -- ── Referensi dokumen sumber (menggantikan pola 'Auto dari …' di notes) ──
  ADD COLUMN `ref_doc_type` VARCHAR(20) NULL DEFAULT 'MANUAL'
      COMMENT 'PV | WO | OUT | MANUAL — asal dokumen ini' AFTER `doc_cat`,
  ADD COLUMN `ref_doc_id`   BIGINT UNSIGNED NULL DEFAULT NULL
      COMMENT 'LOGICAL FK ke dokumen sumber (pv_receipt.pv_id / prd_wo.wo_id / rm_stock.doc_id)' AFTER `ref_doc_type`,
  ADD COLUMN `ref_doc_no`   VARCHAR(50) NULL DEFAULT NULL
      COMMENT 'Snapshot nomor dokumen sumber — tidak ikut berubah bila hulu dinomori ulang' AFTER `ref_doc_id`,

  -- ── Konteks operasional ──
  ADD COLUMN `issued_to_dept` VARCHAR(100) NULL DEFAULT NULL
      COMMENT 'Bagian penerima (OUT) / pengembali (RETURN)' AFTER `warehouse_id`,
  ADD COLUMN `process_code`   VARCHAR(20)  NULL DEFAULT NULL
      COMMENT 'BELAH|CETAK|COATING|LAMINASI|PLONG|LEM|KUPAS|PACKING — proses tujuan/asal' AFTER `issued_to_dept`,
  ADD COLUMN `reason_code`    VARCHAR(30)  NULL DEFAULT NULL
      COMMENT 'Alasan; wajib untuk doc_cat AD/OP/RETURN. Daftar nilai di sys_constant INV_REASON_*' AFTER `process_code`,
  ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL
      COMMENT 'Lampiran; wajib bila ref_doc_type=MANUAL atau nilai adjustment melampaui level 1' AFTER `reason_code`,
  ADD COLUMN `full_scope`     TINYINT UNSIGNED NOT NULL DEFAULT 0
      COMMENT 'Opname: 1 = seluruh item di cakupan wajib masuk daftar' AFTER `opn_officer`,
  ADD COLUMN `opn_witness`    VARCHAR(200) NULL DEFAULT NULL
      COMMENT 'Saksi/penyelia opname' AFTER `opn_officer`,

  -- ── Periode & status baru ──
  ADD COLUMN `period_id`   BIGINT UNSIGNED NULL DEFAULT NULL
      COMMENT 'FK inv_period — diisi sistem dari doc_date' AFTER `post_date`,
  ADD COLUMN `flow_status` VARCHAR(30) NOT NULL DEFAULT 'DRAFT'
      COMMENT 'DRAFT|SUBMITTED|APPROVED_L1..Ln|REVISION_NEEDED|POSTED|CANCELLED. Sumber kebenaran status; stat_confirm/stat_cancel dipertahankan sebagai cermin utk view legacy' AFTER `period_id`,
  ADD COLUMN `posted_by`   VARCHAR(100) NULL DEFAULT NULL AFTER `flow_status`,
  ADD COLUMN `posted_at`   DATETIME NULL DEFAULT NULL AFTER `posted_by`,

  -- ── Perbaikan cacat legacy ──
  MODIFY COLUMN `wo_id` BIGINT UNSIGNED NULL DEFAULT NULL
      COMMENT 'LOGICAL FK tm2prd.prd_wo. Legacy: INT NOT NULL tanpa default sehingga terisi 0 — diperbaiki jadi nullable',
  MODIFY COLUMN `doc_date` DATETIME NOT NULL
      COMMENT 'Legacy mengizinkan NULL; dokumen tanpa tanggal merusak urutan kartu stok',

  ADD INDEX `idx_rm_s_flow`   (`flow_status` ASC, `doc_cat` ASC) USING BTREE,
  ADD INDEX `idx_rm_s_ref`    (`ref_doc_type` ASC, `ref_doc_id` ASC) USING BTREE,
  ADD INDEX `idx_rm_s_period` (`period_id` ASC) USING BTREE,
  ADD UNIQUE INDEX `uq_rm_s_docno` (`doc_no` ASC) USING BTREE;
```

> **`uq_rm_s_docno` akan gagal pada data legacy** — dump memuat `26BB00005` sebanyak 6 kali dan `26OU00014` sebanyak 5 kali (bug generator nomor lama). Bersihkan/renomori data lama **sebelum** menambahkan unique index, atau jalankan index ini hanya di `tm2inv` yang masih kosong.

**Kolom deprecated** (jangan ditulis lagi, dihapus pada fase 2 setelah data lama dimigrasi):
`stat_auth` · `auth_module` · `current_approval_level` · `current_approval_login` · `stat_confirm_plan` · `doc_status`
Semua fungsi ini pindah ke `tm2sys.flow_log` + `flow_status`.

---

## 4. DELTA DETAIL — `rm_stock_d`

```sql
ALTER TABLE `rm_stock_d`
  ADD COLUMN `seq_no` INT UNSIGNED NOT NULL DEFAULT 0
      COMMENT 'Urutan tampil baris' AFTER `doc_id`,

  -- ── Return from Production ──
  ADD COLUMN `return_condition` VARCHAR(10) NULL DEFAULT NULL
      COMMENT 'GOOD | SCRAP — hanya untuk doc_cat=RETURN. GOOD ke lokasi is_usable=1, SCRAP ke gudang WASTE' AFTER `action_type`,
  ADD COLUMN `source_detail_id` BIGINT UNSIGNED NULL DEFAULT NULL
      COMMENT 'FK self -> rm_stock_d.doc_detail_id baris OUT yang diretur; dasar batas qty & rekonsiliasi WO' AFTER `return_condition`,
  ADD COLUMN `qty_returned` DECIMAL(20,4) NOT NULL DEFAULT 0.0000
      COMMENT 'Akumulasi qty yang sudah diretur dari baris OUT ini; diperbarui saat RTP diposting' AFTER `qty`,

  -- ── Adjustment / Opname ──
  ADD COLUMN `qty_system`   DECIMAL(20,4) NULL DEFAULT NULL
      COMMENT 'Snapshot saldo kartu stok saat baris ditarik. DISIMPAN, tidak dibaca ulang saat posting' AFTER `qty_booked`,
  ADD COLUMN `qty_physical` DECIMAL(20,4) NULL DEFAULT NULL
      COMMENT 'Hasil hitung fisik yang diketik user. qty = qty_physical - qty_system (bertanda)' AFTER `qty_system`,

  -- ── Perbaikan cacat legacy ──
  MODIFY COLUMN `worm_id` BIGINT UNSIGNED NULL DEFAULT NULL
      COMMENT 'LOGICAL FK tm2prd.prd_wo_material. Legacy: INT NOT NULL tanpa default sehingga terisi 0',
  MODIFY COLUMN `location_id` BIGINT UNSIGNED NOT NULL
      COMMENT 'FK tm2mst.m_wh_loc — WAJIB. Legacy nullable/0 adalah penyebab saldo yatim di v_rm_onhand',

  ADD INDEX `idx_rm_d_source` (`source_detail_id` ASC) USING BTREE,
  ADD INDEX `idx_rm_d_action` (`action_type` ASC) USING BTREE;
```

> `MODIFY location_id ... NOT NULL` gagal bila ada baris legacy `NULL`. Jalankan dulu:
> `UPDATE rm_stock_d SET location_id = <lokasi_default_gudang> WHERE location_id IS NULL OR location_id = 0;`

---

## 5. DELTA KARTU STOK — `rm_stockcard`

```sql
ALTER TABLE `rm_stockcard`
  -- ── Penjaga idempotensi: inti perbaikan bug double-posting ──
  ADD COLUMN `reference_name` VARCHAR(100) NULL DEFAULT NULL
      COMMENT "Tabel asal reference_detail_id: 'tm2inv.rm_stock_d' | 'tm2pur.pv_line'" AFTER `reference_detail_id`,

  -- ── Nilai (AVG) — melanjutkan PROC_TIER_3 §7.3 ──
  ADD COLUMN `value_bb`      DECIMAL(20,4) NULL DEFAULT NULL COMMENT 'Nilai saldo sebelum movement',
  ADD COLUMN `value_in`      DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_out`     DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_adj`     DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_eb`      DECIMAL(20,4) NULL DEFAULT NULL COMMENT 'Nilai saldo sesudah movement',
  ADD COLUMN `avg_unitprice` DECIMAL(30,5) NULL DEFAULT NULL COMMENT 'Harga rata-rata bergerak setelah movement',

  ADD COLUMN `period_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK inv_period',

  MODIFY COLUMN `doc_date`    DATETIME NOT NULL,
  MODIFY COLUMN `location_id` BIGINT UNSIGNED NOT NULL,

  ADD UNIQUE INDEX `uq_rm_sc_ref` (`reference_name` ASC, `reference_detail_id` ASC) USING BTREE,
  ADD INDEX `idx_rm_sc_saldo`  (`material_id` ASC, `location_id` ASC, `doc_date` ASC, `doc_id` ASC) USING BTREE,
  ADD INDEX `idx_rm_sc_period` (`period_id` ASC) USING BTREE;
```

**`uq_rm_sc_ref` adalah tulang punggung koreksi bug.** Dump legacy memuat pasangan duplikat (`reference_detail_id` 62/63/64 muncul dua kali, doc 39–41 dan 42–44). Bersihkan duplikat dan jalankan `sp_rm_rebuild_stockcard` sebelum menambahkan index:

```sql
-- Pratinjau duplikat sebelum dibersihkan
SELECT reference_detail_id, COUNT(*) c, GROUP_CONCAT(doc_id) ids
FROM rm_stockcard
WHERE reference_detail_id IS NOT NULL
GROUP BY reference_detail_id HAVING c > 1;
```

`idx_rm_sc_saldo` melayani query pencarian saldo terakhir di service posting — tanpa index ini, tiap baris posting melakukan full scan.

---

## 6. DELTA SFG & FG (identik, disesuaikan nama kolom item)

```sql
-- ============ sfg_stock / fg_stock ============
ALTER TABLE `sfg_stock`
  ADD COLUMN `ref_doc_type` VARCHAR(20) NULL DEFAULT 'MANUAL' AFTER `doc_cat`,
  ADD COLUMN `ref_doc_id`   BIGINT UNSIGNED NULL DEFAULT NULL AFTER `ref_doc_type`,
  ADD COLUMN `ref_doc_no`   VARCHAR(50) NULL DEFAULT NULL AFTER `ref_doc_id`,
  ADD COLUMN `issued_to_dept` VARCHAR(100) NULL DEFAULT NULL AFTER `warehouse_id`,
  ADD COLUMN `process_code`   VARCHAR(20)  NULL DEFAULT NULL AFTER `issued_to_dept`,
  ADD COLUMN `reason_code`    VARCHAR(30)  NULL DEFAULT NULL AFTER `process_code`,
  ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_code`,
  ADD COLUMN `opn_witness`  VARCHAR(200) NULL DEFAULT NULL AFTER `opn_officer`,
  ADD COLUMN `full_scope`   TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `opn_witness`,
  ADD COLUMN `period_id`    BIGINT UNSIGNED NULL DEFAULT NULL AFTER `post_date`,
  ADD COLUMN `flow_status`  VARCHAR(30) NOT NULL DEFAULT 'DRAFT' AFTER `period_id`,
  ADD COLUMN `posted_by`    VARCHAR(100) NULL DEFAULT NULL AFTER `flow_status`,
  ADD COLUMN `posted_at`    DATETIME NULL DEFAULT NULL AFTER `posted_by`,
  MODIFY COLUMN `doc_date` DATETIME NOT NULL,
  ADD INDEX `idx_sfg_s_flow` (`flow_status` ASC, `doc_cat` ASC) USING BTREE,
  ADD UNIQUE INDEX `uq_sfg_s_docno` (`doc_no` ASC) USING BTREE;

-- fg_stock: sama persis, ganti prefix index fg_
-- (fg_stock sudah punya product_id di header — tetap dipertahankan sebagai filter laporan)

-- ============ sfg_stock_d / fg_stock_d ============
ALTER TABLE `sfg_stock_d`
  ADD COLUMN `seq_no` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `doc_id`,
  ADD COLUMN `action_type`      VARCHAR(20) NULL DEFAULT NULL AFTER `qty`,
  ADD COLUMN `return_condition` VARCHAR(10) NULL DEFAULT NULL AFTER `action_type`,
  ADD COLUMN `source_detail_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `return_condition`,
  ADD COLUMN `qty_returned` DECIMAL(20,4) NOT NULL DEFAULT 0.0000 AFTER `qty`,
  ADD COLUMN `qty_system`   DECIMAL(20,4) NULL DEFAULT NULL AFTER `qty_returned`,
  ADD COLUMN `qty_physical` DECIMAL(20,4) NULL DEFAULT NULL AFTER `qty_system`,
  MODIFY COLUMN `location_id` BIGINT UNSIGNED NOT NULL,
  ADD INDEX `idx_sfg_d_source` (`source_detail_id` ASC) USING BTREE;

-- fg_stock_d: sama persis.

-- ============ sfg_stockcard / fg_stockcard ============
ALTER TABLE `sfg_stockcard`
  ADD COLUMN `reference_name` VARCHAR(100) NULL DEFAULT NULL AFTER `reference_detail_id`,
  ADD COLUMN `value_bb`  DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_in`  DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_out` DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_adj` DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_eb`  DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `avg_unitprice` DECIMAL(30,5) NULL DEFAULT NULL,
  ADD COLUMN `period_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  MODIFY COLUMN `doc_date` DATETIME NOT NULL,
  MODIFY COLUMN `location_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE INDEX `uq_sfg_sc_ref` (`reference_name` ASC, `reference_detail_id` ASC) USING BTREE,
  ADD INDEX `idx_sfg_sc_saldo` (`parts_id` ASC, `location_id` ASC, `doc_date` ASC, `doc_id` ASC) USING BTREE;

-- fg_stockcard: kunci saldo memakai COALESCE(product_id,0) + COALESCE(parts_id,0) + location_id.
-- MySQL tidak mendukung index pada ekspresi COALESCE secara langsung; pakai kolom generated:
ALTER TABLE `fg_stockcard`
  ADD COLUMN `reference_name` VARCHAR(100) NULL DEFAULT NULL AFTER `reference_detail_id`,
  ADD COLUMN `value_bb`  DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_in`  DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_out` DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_adj` DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `value_eb`  DECIMAL(20,4) NULL DEFAULT NULL,
  ADD COLUMN `avg_unitprice` DECIMAL(30,5) NULL DEFAULT NULL,
  ADD COLUMN `period_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN `c_sku_key` VARCHAR(64)
      GENERATED ALWAYS AS (CONCAT(IFNULL(`product_id`,0),'-',IFNULL(`parts_id`,0))) STORED
      COMMENT 'Kunci saldo FG: produk + komponen; NULL dinormalkan ke 0',
  MODIFY COLUMN `doc_date` DATETIME NOT NULL,
  MODIFY COLUMN `location_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE INDEX `uq_fg_sc_ref` (`reference_name` ASC, `reference_detail_id` ASC) USING BTREE,
  ADD INDEX `idx_fg_sc_saldo` (`c_sku_key` ASC, `location_id` ASC, `doc_date` ASC, `doc_id` ASC) USING BTREE;
```

---

## 7. TABEL BARU `tm2inv`

### 7.1 `inv_period` — kunci periode

```sql
CREATE TABLE `inv_period` (
  `period_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `i_period_code` VARCHAR(6)  NOT NULL COMMENT 'YYYYMM, mis. 202608',
  `i_date_start`  DATE NOT NULL,
  `i_date_end`    DATE NOT NULL,
  `i_status`      VARCHAR(10) NOT NULL DEFAULT 'OPEN'
      COMMENT 'OPEN = boleh posting | CLOSING = hanya adjustment hasil opname | CLOSED = terkunci total',
  `c_closed_by`   VARCHAR(100) NULL DEFAULT NULL,
  `c_closed_at`   DATETIME NULL DEFAULT NULL,
  `i_notes`       VARCHAR(200) NULL DEFAULT NULL,
  `created_by` VARCHAR(100) NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(100) NULL, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`period_id`),
  UNIQUE KEY `uq_inv_period_code` (`i_period_code`),
  KEY `idx_inv_period_range` (`i_date_start`, `i_date_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Periode stok — posting hanya ke periode OPEN; mencegah backdate merusak rantai qty_bb/qty_eb';
```

### 7.2 `inv_stock_layer` — lapisan harga FIFO/LIFO

Definisi dari `procurement_tier3.md` §7.3 diadopsi utuh, ditambah dua kolom yang dibutuhkan sisi OUT dan retur:

```sql
CREATE TABLE `inv_stock_layer` (
  `layer_id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `i_stock_class` VARCHAR(5)  NOT NULL COMMENT 'RM | SFG | FG',
  `i_item_id`     BIGINT UNSIGNED NOT NULL COMMENT 'material_id / parts_id / product_id sesuai kelas',
  `i_location_id` BIGINT UNSIGNED NOT NULL,
  `i_warehouse_id` BIGINT UNSIGNED NULL,
  `i_lot_no`      VARCHAR(100) NULL,
  `i_qty_in`      DECIMAL(20,4) NOT NULL,
  `c_qty_remaining` DECIMAL(20,4) NOT NULL COMMENT 'Dikurangi saat OUT sesuai FIFO/LIFO',
  `i_unit_cost`   DECIMAL(30,5) NOT NULL,
  `i_uom_code`    VARCHAR(50) NULL,
  `i_received_at` DATETIME NOT NULL COMMENT 'Dasar urutan FIFO/LIFO',
  `i_reference_id`   BIGINT UNSIGNED NULL COMMENT 'rm_stock_d.doc_detail_id / pv_line_id',
  `i_reference_name` VARCHAR(100) NULL,
  `c_is_exhausted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` VARCHAR(100) NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(100) NULL, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`layer_id`),
  KEY `idx_layer_pick` (`i_stock_class`,`i_item_id`,`i_location_id`,`c_is_exhausted`,`i_received_at`),
  KEY `idx_layer_ref` (`i_reference_name`,`i_reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lapisan harga perolehan per lot — penopang FIFO/LIFO; stockcard tetap ringkasan saldo';
```

### 7.3 `inv_layer_consumption` — jejak konsumsi layer

```sql
CREATE TABLE `inv_layer_consumption` (
  `consumption_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `i_layer_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK inv_stock_layer',
  `i_doc_detail_id` BIGINT UNSIGNED NOT NULL COMMENT 'Baris movement OUT yang memakan layer ini',
  `i_reference_name` VARCHAR(100) NOT NULL COMMENT "'tm2inv.rm_stock_d' dst",
  `i_qty`        DECIMAL(20,4) NOT NULL,
  `i_unit_cost`  DECIMAL(30,5) NOT NULL COMMENT 'Snapshot harga layer saat dikonsumsi',
  `c_value`      DECIMAL(20,4) NOT NULL COMMENT 'i_qty * i_unit_cost',
  `created_by` VARCHAR(100) NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`consumption_id`),
  KEY `idx_cons_layer` (`i_layer_id`),
  KEY `idx_cons_doc` (`i_reference_name`,`i_doc_detail_id`),
  CONSTRAINT `fk_cons_layer` FOREIGN KEY (`i_layer_id`) REFERENCES `inv_stock_layer`(`layer_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Jejak konsumsi layer saat OUT — dasar HPP per dokumen dan dasar harga saat retur';
```

Tanpa tabel ini, retur bahan tidak tahu harus dikembalikan dengan harga berapa, dan HPP per WO tidak bisa ditelusuri.

---

## 8. DELTA LINTAS DATABASE

### 8.1 `tm2mst` — lokasi tak terpakai (INV-02)

```sql
USE tm2mst;

ALTER TABLE `m_wh_loc`
  ADD COLUMN `is_usable` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT '1 = stok di lokasi ini boleh dipakai produksi/dijual | 0 = waste/karantina, masuk saldo tapi tidak pernah masuk qty_available'
      AFTER `location_type`;

-- warehouse_type SUDAH ADA di m_warehouse — cukup di-seed, tidak perlu ALTER.
-- Nilai baku: RM | SFG | FG | WASTE | TRANSIT

-- Seed gudang & lokasi waste (sesuaikan id riil)
INSERT INTO `m_warehouse` (`warehouse_code`,`warehouse_name`,`warehouse_type`,`is_active`,`description`,`create_by`)
VALUES ('GD-05','Gudang Afkir / Waste','WASTE',1,'Penampungan bahan tidak layak pakai','_system');
SET @wh_waste := LAST_INSERT_ID();

INSERT INTO `m_wh_loc` (`warehouse_id`,`location_code`,`location_name`,`location_type`,`is_usable`,`is_active`,`create_by`) VALUES
  (@wh_waste,'WS-01','Waste Kertas','WASTE',0,1,'_system'),
  (@wh_waste,'WS-02','Waste Kimia & Tinta','WASTE',0,1,'_system'),
  (@wh_waste,'WS-03','Waste Barang Jadi','WASTE',0,1,'_system');
```

**Aturan integritas (dijaga aplikasi, bukan constraint):** `is_usable` harus `0` untuk semua lokasi di gudang bertipe `WASTE`, dan `1` untuk gudang lain kecuali lokasi karantina yang di-set eksplisit.

### 8.2 `tm2sys` — konstanta & urutan nomor

```sql
USE tm2sys;

-- Urutan nomor dokumen. Sesuaikan nama kolom dengan struktur sys_sequence aktual.
INSERT INTO `sys_sequence` (`seq_code`,`seq_format`,`seq_current`,`seq_reset`,`description`,`is_active`) VALUES
  ('INV_IN',  'IN/{YY}/{#####}',  0, 'YEARLY', 'Stock In / Saldo Awal',        1),
  ('INV_OUT', 'OUT/{YY}/{#####}', 0, 'YEARLY', 'Stock Out ke Work Order',      1),
  ('INV_ADJ', 'ADJ/{YY}/{#####}', 0, 'YEARLY', 'Stock Adjustment',             1),
  ('INV_OPN', 'OPN/{YY}/{#####}', 0, 'YEARLY', 'Stock Opname',                 1),
  ('INV_RTP', 'RTP/{YY}/{#####}', 0, 'YEARLY', 'Return from Production',       1);

-- Konstanta modul. Sesuaikan nama kolom (lihat issue PROC-SYSCONST-COL).
INSERT INTO `sys_constant` (`const_code`,`const_value`,`const_type`,`description`,`is_active`) VALUES
  ('INV_ADJ_LIMIT_L1',      '5000000',  'AMOUNT', 'Batas nilai Adjustment Kepala Gudang [DELTA-CONFIRM]', 1),
  ('INV_ADJ_LIMIT_L2',      '25000000', 'AMOUNT', 'Batas nilai Adjustment Manajer Operasional [DELTA-CONFIRM]', 1),
  ('INV_ALLOW_NEGATIVE',    '0',        'FLAG',   '0 = posting OUT ditolak bila saldo jadi negatif', 1),
  ('INV_BACKDATE_DAYS',     '7',        'NUMBER', 'Maksimum hari mundur doc_date dalam periode OPEN [DELTA-CONFIRM]', 1),
  ('INV_DEFAULT_COSTING',   'AVG',      'STRING', 'Metode harga perolehan default bila item belum diatur', 1);

-- Daftar alasan (dipakai dropdown reason_code)
INSERT INTO `sys_constant` (`const_code`,`const_value`,`const_type`,`description`,`is_active`) VALUES
  ('INV_REASON_ADJ',   'OPNAME_SCHEDULED,DAMAGED,SHRINKAGE,WRONG_ENTRY,LOST,FOUND,MIGRATION', 'LIST', 'Alasan Adjustment/Opname', 1),
  ('INV_REASON_RTP',   'SURPLUS,WO_CANCELLED,WRONG_ITEM,DEFECT_FOUND,SPEC_CHANGED',           'LIST', 'Alasan Return from Production', 1),
  ('INV_PROCESS_CODE', 'BELAH,CETAK,COATING,LAMINASI,PLONG,LEM,KUPAS,PACKING',                 'LIST', 'Urutan proses produksi — COATING sebelum LAMINASI', 1);
```

### 8.3 `tm2mst` — metode harga perolehan per item

```sql
USE tm2mst;
ALTER TABLE `m_raw_material`
  ADD COLUMN `costing_method` VARCHAR(10) NOT NULL DEFAULT 'AVG'
      COMMENT 'AVG | FIFO | LIFO — metode harga perolehan per item' AFTER `last_unitprice`;
```
(Bila kolom ini sudah ditambahkan oleh delta Procurement, lewati.)

---

## 9. VIEW

### 9.1 `v_rm_onhand` — REPLACE (perbaikan legacy)

Versi legacy memakai `MAX(doc_id)` per (material, location) tanpa memperhatikan `doc_date`, sehingga posting mundur menghasilkan saldo yang salah. Versi baru mengurutkan berdasarkan pasangan (`doc_date`, `doc_id`) — sama dengan urutan yang dipakai service posting.

```sql
USE tm2inv;
CREATE OR REPLACE VIEW `v_rm_onhand` AS
SELECT sc.material_id, sc.location_id, sc.warehouse_id,
       w.warehouse_name, l.location_name, l.location_code, l.is_usable,
       sc.uom_code, sc.qty_eb AS qty_onhand, sc.value_eb AS value_onhand,
       sc.avg_unitprice, sc.doc_date AS last_movement
FROM rm_stockcard sc
JOIN (
  SELECT material_id, location_id, MAX(CONCAT(DATE_FORMAT(doc_date,'%Y%m%d%H%i%s'), LPAD(doc_id,12,'0'))) AS mx
  FROM rm_stockcard GROUP BY material_id, location_id
) x ON x.material_id = sc.material_id
   AND x.location_id = sc.location_id
   AND x.mx = CONCAT(DATE_FORMAT(sc.doc_date,'%Y%m%d%H%i%s'), LPAD(sc.doc_id,12,'0'))
LEFT JOIN tm2mst.m_wh_loc    l ON l.location_id  = sc.location_id
LEFT JOIN tm2mst.m_warehouse w ON w.warehouse_id = sc.warehouse_id;
```

### 9.2 `v_rm_available` — REPLACE (menghormati `is_usable`)

```sql
CREATE OR REPLACE VIEW `v_rm_available` AS
SELECT o.material_id, o.location_id, o.warehouse_id, o.uom_code,
       o.is_usable, o.qty_onhand,
       IFNULL(b.qty_booked_open, 0) AS qty_booked,
       CASE WHEN o.is_usable = 0 THEN 0
            ELSE o.qty_onhand - IFNULL(b.qty_booked_open, 0) END AS qty_available
FROM v_rm_onhand o
LEFT JOIN (
  SELECT d.material_id, d.location_id, SUM(d.qty_book - d.qty_issued) AS qty_booked_open
  FROM rm_booking_d d
  JOIN rm_booking   h ON h.booking_id = d.booking_id
  WHERE h.stat_confirm = 1 AND h.stat_cancel = 0 AND h.stat_closed = 0
    AND d.stat_cancel = 0 AND d.qty_book > d.qty_issued
  GROUP BY d.material_id, d.location_id
) b ON b.material_id = o.material_id AND b.location_id <=> o.location_id;
```

### 9.3 `v_rm_out_returnable` — baris OUT yang masih boleh diretur

```sql
CREATE OR REPLACE VIEW `v_rm_out_returnable` AS
SELECT h.doc_id, h.doc_no, h.doc_date, h.wo_id, h.ref_doc_no,
       d.doc_detail_id, d.material_id, m.material_code, m.material_name,
       d.lot_no, d.location_id, l.location_name, d.uom_code,
       d.action_type, d.qty AS qty_issued, d.qty_returned,
       (d.qty - d.qty_returned) AS qty_returnable
FROM rm_stock h
JOIN rm_stock_d d ON d.doc_id = h.doc_id
LEFT JOIN tm2mst.m_raw_material m ON m.material_id = d.material_id
LEFT JOIN tm2mst.m_wh_loc       l ON l.location_id = d.location_id
WHERE h.doc_type = -1 AND h.doc_cat = 'STOCK'
  AND h.flow_status = 'POSTED'
  AND d.action_type IN ('ISSUE','SUBSTITUTE')
  AND (d.qty - d.qty_returned) > 0;
```

### 9.4 `v_inv_movement` — riwayat mutasi lintas kelas (untuk grid Kartu Stok)

```sql
CREATE OR REPLACE VIEW `v_inv_movement` AS
SELECT 'RM' AS stock_class, sc.doc_id, sc.doc_date, sc.doc_type,
       sc.material_id AS item_id, m.material_code AS item_code, m.material_name AS item_name,
       NULL AS parts_id, sc.warehouse_id, w.warehouse_name,
       sc.location_id, l.location_name, l.is_usable, sc.lot_no, sc.uom_code,
       h.doc_no, h.doc_cat, h.ref_doc_no,
       sc.qty_bb, sc.qty_in, sc.qty_out, sc.qty_adj, sc.qty_eb,
       sc.value_eb, sc.avg_unitprice, sc.period_id
FROM rm_stockcard sc
LEFT JOIN rm_stock_d d ON d.doc_detail_id = sc.reference_detail_id
                      AND sc.reference_name = 'tm2inv.rm_stock_d'
LEFT JOIN rm_stock   h ON h.doc_id = d.doc_id
LEFT JOIN tm2mst.m_raw_material m ON m.material_id  = sc.material_id
LEFT JOIN tm2mst.m_wh_loc       l ON l.location_id  = sc.location_id
LEFT JOIN tm2mst.m_warehouse    w ON w.warehouse_id = sc.warehouse_id

UNION ALL

SELECT 'SFG', sc.doc_id, sc.doc_date, sc.doc_type,
       sc.parts_id, pp.parts_code, pp.parts_name,
       sc.parts_id, sc.warehouse_id, w.warehouse_name,
       sc.location_id, l.location_name, l.is_usable, sc.lot_no, sc.uom_code,
       h.doc_no, h.doc_cat, h.ref_doc_no,
       sc.qty_bb, sc.qty_in, sc.qty_out, sc.qty_adj, sc.qty_eb,
       sc.value_eb, sc.avg_unitprice, sc.period_id
FROM sfg_stockcard sc
LEFT JOIN sfg_stock_d d ON d.doc_detail_id = sc.reference_detail_id
                       AND sc.reference_name = 'tm2inv.sfg_stock_d'
LEFT JOIN sfg_stock   h ON h.doc_id = d.doc_id
LEFT JOIN tm2mst.m_product_parts pp ON pp.parts_id = sc.parts_id
LEFT JOIN tm2mst.m_wh_loc     l ON l.location_id  = sc.location_id
LEFT JOIN tm2mst.m_warehouse  w ON w.warehouse_id = sc.warehouse_id

UNION ALL

SELECT 'FG', sc.doc_id, sc.doc_date, sc.doc_type,
       sc.product_id, p.product_code, p.product_name,
       sc.parts_id, sc.warehouse_id, w.warehouse_name,
       sc.location_id, l.location_name, l.is_usable, sc.lot_no, sc.uom_code,
       h.doc_no, h.doc_cat, h.ref_doc_no,
       sc.qty_bb, sc.qty_in, sc.qty_out, sc.qty_adj, sc.qty_eb,
       sc.value_eb, sc.avg_unitprice, sc.period_id
FROM fg_stockcard sc
LEFT JOIN fg_stock_d d ON d.doc_detail_id = sc.reference_detail_id
                      AND sc.reference_name = 'tm2inv.fg_stock_d'
LEFT JOIN fg_stock   h ON h.doc_id = d.doc_id
LEFT JOIN tm2mst.m_product   p ON p.product_id   = sc.product_id
LEFT JOIN tm2mst.m_wh_loc    l ON l.location_id  = sc.location_id
LEFT JOIN tm2mst.m_warehouse w ON w.warehouse_id = sc.warehouse_id;
```

> View ini memuat kolom nilai. Layar Sisi B **tidak boleh** menyeleksi `value_eb` / `avg_unitprice` dari sini — repositori menyediakan dua metode terpisah (`forWarehouse()` tanpa kolom nilai, `forFinance()` lengkap).

### 9.5 `v_inv_wo_reconcile` — rekonsiliasi pemakaian per WO

```sql
CREATE OR REPLACE VIEW `v_inv_wo_reconcile` AS
SELECT h.wo_id, h.ref_doc_no AS wo_no, d.material_id,
       m.material_code, m.material_name, d.uom_code,
       SUM(CASE WHEN h.doc_cat = 'STOCK' AND h.doc_type = -1 THEN d.qty ELSE 0 END) AS qty_issued,
       SUM(CASE WHEN h.doc_cat = 'RETURN' AND d.return_condition = 'GOOD'  THEN d.qty ELSE 0 END) AS qty_return_good,
       SUM(CASE WHEN h.doc_cat = 'RETURN' AND d.return_condition = 'SCRAP' THEN d.qty ELSE 0 END) AS qty_return_scrap,
       SUM(CASE WHEN h.doc_cat = 'STOCK' AND h.doc_type = -1 THEN d.qty ELSE 0 END)
       - SUM(CASE WHEN h.doc_cat = 'RETURN' THEN d.qty ELSE 0 END) AS qty_net_used
FROM rm_stock h
JOIN rm_stock_d d ON d.doc_id = h.doc_id
LEFT JOIN tm2mst.m_raw_material m ON m.material_id = d.material_id
WHERE h.flow_status = 'POSTED'
  AND h.wo_id IS NOT NULL
  AND IFNULL(d.action_type,'ISSUE') <> 'PURCHASE'
GROUP BY h.wo_id, h.ref_doc_no, d.material_id, m.material_code, m.material_name, d.uom_code;
```

Perbandingan terhadap `qty_standar` dilakukan di aplikasi (join lintas DB ke `tm2prd.prd_wo_material`), bukan di view — menjaga view tetap bisa dieksekusi bila `tm2prd` berada di server lain.

### 9.6 View legacy yang dipensiunkan

`v1_m_print_mst_mat` menunjuk `rm_stockmst` yang tidak dipakai → `DROP VIEW`.
`v_fg_onhand`, `v_sfg_onhand` → ganti dengan pola yang sama seperti §9.1 (urut `doc_date`+`doc_id`, bukan `MAX(doc_id)`).

---

## 10. SEED FLOW ENGINE

```sql
USE tm2sys;

INSERT INTO `flow_config` (`module_code`,`division_id`,`total_step`,`notes`,`is_active`,`created_by`) VALUES
  ('IN',  NULL, 2, 'Stock In — otomatis (PV/WO) & manual berpersetujuan', 1, '_system'),
  ('OUT', NULL, 1, 'Stock Out ke Work Order — konfirmasi gudang',         1, '_system'),
  ('AD',  NULL, 3, 'Stock Adjustment — berjenjang menurut nilai',         1, '_system'),
  ('OP',  NULL, 3, 'Stock Opname — berjenjang menurut nilai',             1, '_system'),
  ('RTP', NULL, 1, 'Return from Production — konfirmasi gudang',          1, '_system');
```

**Rancangan step** — ganti `position_id` dengan id `org_position` riil sebelum eksekusi (lihat open item `INV-FLOW-POSITION`):

| module | step | status_from → status_to | type | jabatan | min_amount | max_amount | label | tolak → |
|---|---|---|---|---|---|---|---|---|
| IN | 1 | `DRAFT` → `POSTED` | AUTO | — | — | — | Membuat | — |
| IN | 2 | `SUBMITTED` → `POSTED` | APPROVAL | Kepala Gudang | 0 | NULL | Menyetujui | `REVISION_NEEDED` |
| OUT | 1 | `DRAFT` → `POSTED` | AUTO | — | — | — | Menyerahkan | — |
| RTP | 1 | `DRAFT` → `POSTED` | AUTO | — | — | — | Menerima | — |
| AD | 1 | `SUBMITTED` → `APPROVED_L1` | APPROVAL | Kepala Gudang | 0 | 5.000.000 | Memeriksa | `REVISION_NEEDED` |
| AD | 2 | `APPROVED_L1` → `APPROVED_L2` | APPROVAL | Manajer Operasional | 5.000.000 | 25.000.000 | Mengetahui | `REVISION_NEEDED` |
| AD | 3 | `APPROVED_L2` → `POSTED` | APPROVAL | Direksi | 25.000.000 | NULL | Menyetujui | `REVISION_NEEDED` |
| OP | 1–3 | sama dengan AD | | | | | | |

**Aturan pelewatan step.** Bila nilai dokumen di bawah `min_amount` sebuah step, step itu dilewati dan status langsung melompat ke `status_to` step berikutnya yang berlaku. Step terakhir yang berlaku selalu memetakan ke `POSTED` — engine menyesuaikan `status_to` secara dinamis, bukan lewat baris konfigurasi tambahan.

---

## 11. REGISTRASI MENU

```sql
USE tm2sys;
INSERT INTO `sys_app_menu` (`parent_id`,`menu_code`,`menu_name`,`menu_type`,`target_kind`,`target`,`sort_order`,`is_active`,`required_object`) VALUES
  (310,'INV_IN',   'Stock In',                'item','grid','inv.stock-in.index',    5,1,'INV_IN'),
  (310,'INV_OUT',  'Stock Out',               'item','grid','inv.stock-out.index',   6,1,'INV_OUT'),
  (310,'INV_RTP',  'Return from Production',  'item','grid','inv.stock-return.index',7,1,'INV_RTP'),
  (310,'INV_ADJ2', 'Stock Adjustment & Opname','item','grid','inv.stock-adj.index',  8,1,'INV_ADJ'),
  (310,'INV_APRV', 'Persetujuan Penyesuaian', 'item','grid','inv.stock-adj.approval',9,1,'INV_ADJ'),
  (380,'INV_PERIOD','Periode Stok',           'item','grid','inv.period.index',      50,1,'INV_PERIOD');
```

Menu lama `INV_MOVE` (`form_stock_move.php`) dan `INV_ADJ` (`form_stock_adj.php`) adalah target ScriptCase legacy → `is_active = 0`.
Status awal warna menu: `NOT_BUILT` (abu), mengikuti `module_status_system`.

---

## 12. URUTAN EKSEKUSI

1. **DROP trigger** (§2) — sebelum apa pun.
2. Delta `tm2mst` (§8.1, §8.3) — `is_usable`, gudang WASTE, `costing_method`.
3. Delta `tm2sys` (§8.2) — `sys_sequence` + `sys_constant`.
4. Bersihkan data legacy: `location_id` nol/NULL, `doc_no` duplikat, baris kartu stok duplikat (§5).
5. Delta tabel `tm2inv` (§3, §4, §5, §6) — header → detail → kartu stok, untuk RM lalu SFG lalu FG.
6. Tabel baru `tm2inv` (§7) — `inv_period`, `inv_stock_layer`, `inv_layer_consumption`.
7. Seed `inv_period` untuk periode berjalan dan seluruh periode historis yang punya data (status `CLOSED`).
8. VIEW (§9) — `v_rm_onhand` dulu, `v_rm_available` menyusul (bergantung padanya).
9. Seed flow engine (§10) — **setelah `org_position` terisi**.
10. Registrasi menu (§11).
11. Jalankan `sp_rm_rebuild_stockcard` untuk memverifikasi bahwa hasil service posting cocok dengan hitungan procedure legacy pada data uji.
