# TIER 3 — DISPATCH · DDL, VIEW & SEED
**PT. Telasih Makmur** · DB `tm2sdi` · MySQL 8.0

| | |
|---|---|
| **Basis** | `dispatch_tier1.md`, `dispatch_tier2.md` |
| **Sifat** | Tabel BARU murni (`do_delivery`, `do_line`), **TIDAK ADA ALTER** terhadap `co_order`/`co_order_item`/`co_order_delivery` — semua kebutuhan "sisa qty" dihitung live via VIEW, bukan kolom fisik tambahan |
| **Cakupan** | §1 Prinsip · §2 `do_delivery` · §3 `do_line` · §4 VIEW · §5 Penomoran · §6 Seed data UAT |

> **Catatan MySQL 8:** sama seperti modul lain — `ADD COLUMN IF NOT EXISTS` tidak didukung, generated column tidak dipakai di sini (semua kalkulasi via VIEW, bukan generated column, karena butuh agregasi antar-tabel yang tidak bisa jadi generated column MySQL).

---

## 1. PRINSIP

1. FK ke `co_order`/`co_order_item`/`co_order_delivery` = **FISIK** (satu DB `tm2sdi`).
2. FK ke `cust_customer`/`cust_address` (`tm2mst`) = **LOGICAL** (lintas-DB, Eloquent saja).
3. Konvensi kolom mengikuti **tabel Sales-domain existing** (`co_order`, `co_order_item`) — **snake_case polos, TANPA prefix `i_`/`c_`** (beda dari konvensi `tm2pur`; Tier 3 Procurement pakai `i_`/`c_`, tapi `tm2sdi` sudah established tanpa itu — konsistensi per-DB menang di atas konsistensi global, sama alasan `INV-12`).
4. Uang `DECIMAL(18,4)` di baris, `DECIMAL(18,2)` di subtotal/total (meniru presisi `co_order_item.unit_price`/`subtotal`). Qty `DECIMAL(14,2)` (meniru `co_order_item.qty`).
5. Audit: `created_by/at`, `updated_by/at` di semua tabel transaksi.
6. Status transisi disimpan langsung sebagai kolom `status` (bukan `flow_log` — DO v1 tidak pakai flow engine approval, lihat Tier 1 §1.2 & §8).

---

## 2. `do_delivery` — HEADER

```sql
-- ══════════════════ tm2sdi.do_delivery ══════════════════
CREATE TABLE do_delivery (
  id_delivery         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  do_no               VARCHAR(20)  NULL COMMENT 'DO/YY/##### -- digenerate saat Confirm (Tier2 C.2), NULL selama DRAFT',
  do_date             DATE         NOT NULL,

  customer_id         BIGINT UNSIGNED NOT NULL COMMENT 'LOGICAL -> tm2mst.cust_customer.customer_id -- KUNCI DOKUMEN, terkunci begitu baris pertama ditarik (Tier2 D.1)',
  ship_address_id     BIGINT UNSIGNED NULL COMMENT 'LOGICAL -> tm2mst.cust_address.address_id, boleh NULL kalau alamat diketik manual',
  ship_address_label  VARCHAR(255) NULL COMMENT 'snapshot label alamat (mis. nama gudang customer)',
  ship_address_detail TEXT         NULL COMMENT 'snapshot alamat lengkap, mengikuti pola co_order.ship_address_detail',

  scheduled_ship_date DATE         NULL COMMENT 'rencana kirim, diisi user',
  actual_ship_date    DATETIME     NULL COMMENT 'diisi otomatis saat transisi ke SHIPPED (Tier2 C.3)',

  status               VARCHAR(20)  NOT NULL DEFAULT 'DRAFT'
                       COMMENT 'DRAFT|CONFIRMED|SHIPPED|CANCELLED (Tier2 C)',

  price_change_flag           TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '1 kalau minimal 1 do_line price_overridden=1 (Tier2 E)',
  price_change_approval_status VARCHAR(20) NULL COMMENT 'RESERVED Fase 2 -- TIDAK dipakai di v1, selalu NULL',

  notes                VARCHAR(255) NULL COMMENT 'wajib diisi kalau status=CANCELLED (Tier2 C.4)',

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by VARCHAR(100) NULL,
  updated_by VARCHAR(100) NULL,

  PRIMARY KEY (id_delivery),
  UNIQUE KEY uq_do_no (do_no),
  KEY idx_do_customer (customer_id),
  KEY idx_do_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Header Delivery Order -- juga sumber cetakan Surat Jalan (Tier1 §1.2)';
```

---

## 3. `do_line` — BARIS

```sql
-- ══════════════════ tm2sdi.do_line ══════════════════
CREATE TABLE do_line (
  id_line              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  delivery_id          BIGINT UNSIGNED NOT NULL,
  line_no              SMALLINT UNSIGNED NOT NULL,

  co_order_id          BIGINT UNSIGNED NOT NULL COMMENT 'FISIK -> co_order.id_order -- referensi CO asal PER BARIS (Tier1 §3.1)',
  co_order_item_id     BIGINT UNSIGNED NOT NULL COMMENT 'FISIK -> co_order_item.id_item -- KUNCI IDENTITAS baris (Tier2 §B), no-merge',
  co_delivery_id       BIGINT UNSIGNED NULL COMMENT 'FISIK -> co_order_delivery.id_delivery -- baris jadwal yang dipenuhi',

  item_description     VARCHAR(255) NOT NULL COMMENT 'SNAPSHOT co_order_item.description saat ditarik (Tier2 A.3)',
  qty_do               DECIMAL(14,2) NOT NULL COMMENT 'qty yang benar-benar dikirim di DO ini, <= qty_available saat ditarik (Tier2 A.3, D.2)',
  qty_unit             VARCHAR(20)  NOT NULL COMMENT 'SNAPSHOT co_order_item.qty_unit',
  unit_price           DECIMAL(18,4) NOT NULL COMMENT 'SNAPSHOT co_order_item.unit_price, ATAU harga override (Tier2 §E)',
  subtotal              DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'qty_do * unit_price, dihitung aplikasi saat simpan (bukan generated column)',

  price_overridden     TINYINT(1)   NOT NULL DEFAULT 0,
  original_unit_price  DECIMAL(18,4) NULL COMMENT 'diisi SEKALI saat override pertama kali (Tier2 §E), tidak ditimpa lagi',

  qty_invoiced         DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'RESERVED Fase 2 (Invoicing) -- selalu 0 di v1, tidak diupdate oleh modul ini',

  status                VARCHAR(20)  NOT NULL DEFAULT 'PLANNED'
                        COMMENT 'PLANNED|SHIPPED|CANCELLED (Tier2 C)',
  split_from_line_id   BIGINT UNSIGNED NULL COMMENT 'RESERVED -- self-FK untuk split baris, TIDAK dipakai di v1 (satu DO = satu shipping event, Tier1 §3.3)',

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by VARCHAR(100) NULL,
  updated_by VARCHAR(100) NULL,

  PRIMARY KEY (id_line),
  KEY idx_doline_delivery (delivery_id),
  KEY idx_doline_co_order (co_order_id),
  KEY idx_doline_co_item (co_order_item_id),
  KEY idx_doline_co_delivery (co_delivery_id),
  CONSTRAINT fk_doline_delivery  FOREIGN KEY (delivery_id)      REFERENCES do_delivery (id_delivery) ON DELETE CASCADE,
  CONSTRAINT fk_doline_co_order  FOREIGN KEY (co_order_id)      REFERENCES co_order (id_order)        ON DELETE RESTRICT,
  CONSTRAINT fk_doline_co_item   FOREIGN KEY (co_order_item_id) REFERENCES co_order_item (id_item)     ON DELETE RESTRICT,
  CONSTRAINT fk_doline_co_deliv  FOREIGN KEY (co_delivery_id)   REFERENCES co_order_delivery (id_delivery) ON DELETE RESTRICT,
  CONSTRAINT fk_doline_split     FOREIGN KEY (split_from_line_id) REFERENCES do_line (id_line)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Baris Delivery Order -- kunci identitas co_order_item_id, TIDAK PERNAH digabung lintas baris (Tier2 §B)';
```

**Guard aplikasi wajib** (tidak semuanya bisa jadi CONSTRAINT SQL, harus divalidasi di service layer):
- `UNIQUE(delivery_id, co_delivery_id)` **sengaja TIDAK dipasang sebagai constraint DB** — kolom `co_delivery_id` boleh `NULL` (kasus jarang: baris ditarik tanpa jadwal terdaftar) dan MySQL memperbolehkan banyak `NULL` di unique key, tapi guard "tidak boleh menarik `co_delivery_id` yang sama dua kali ke DO yang sama" (Tier2 A.4) tetap wajib dicek di `DispatchService::pullLines()` sebelum insert.
- `qty_do > 0` — CHECK constraint MySQL 8 bisa dipasang: `CONSTRAINT chk_doline_qty CHECK (qty_do > 0)`.

---

## 4. VIEW

### 4.1 `v_co_delivery_outstanding` — sumber picker (Tier2 §A.2)
```sql
CREATE OR REPLACE VIEW v_co_delivery_outstanding AS
SELECT
  cod.id_delivery                                  AS co_delivery_id,
  co.id_order                                       AS co_order_id,
  co.co_no                                          AS co_no,
  q.customer_id                                     AS customer_id,
  coi.id_item                                       AS co_order_item_id,
  coi.description                                   AS item_description,
  coi.qty_unit                                      AS qty_unit,
  coi.unit_price                                    AS unit_price,
  cod.ship_qty                                      AS ship_qty,
  cod.ship_date                                     AS ship_date,
  co.doc_status                                     AS co_status,
  cod.ship_qty - COALESCE(dl_sum.qty_allocated, 0)  AS qty_available
FROM co_order_delivery cod
JOIN co_order_item coi ON coi.id_item  = cod.item_id
JOIN co_order       co  ON co.id_order  = cod.order_id
JOIN quo_quotation   q   ON q.id_quotation = co.quotation_id
LEFT JOIN (
  SELECT co_delivery_id, SUM(qty_do) AS qty_allocated
  FROM do_line
  WHERE status IN ('PLANNED','SHIPPED')
  GROUP BY co_delivery_id
) dl_sum ON dl_sum.co_delivery_id = cod.id_delivery
WHERE cod.status <> 'SHIPPED';
```
Baris dengan `qty_available <= 0` **tetap muncul di VIEW** (tidak difilter di sini) — filter `qty_available > 0` dilakukan di query picker (Tier2 §A.2), supaya VIEW ini tetap reusable untuk kebutuhan monitoring lain (mis. "lihat semua jadwal customer X apa adanya").

### 4.2 `v_do_list` — sumber grid DO List (Tier 4 §2)
```sql
CREATE OR REPLACE VIEW v_do_list AS
SELECT
  d.id_delivery,
  d.do_no,
  d.do_date,
  d.status,
  d.customer_id,
  c.customer_name,
  COUNT(dl.id_line)                              AS line_count,
  COUNT(DISTINCT dl.co_order_id)                 AS co_count,
  COALESCE(SUM(dl.qty_do * dl.unit_price), 0)    AS total_value
FROM do_delivery d
LEFT JOIN do_line dl ON dl.delivery_id = d.id_delivery AND dl.status <> 'CANCELLED'
LEFT JOIN tm2mst.cust_customer c ON c.customer_id = d.customer_id
GROUP BY d.id_delivery, d.do_no, d.do_date, d.status, d.customer_id, c.customer_name;
```
> Kalau cross-database VIEW (`tm2sdi` → `tm2mst`) tidak diizinkan di konfigurasi MySQL server (tergantung privilege user aplikasi), fallback: JOIN `cust_customer` dilakukan di level Eloquent/query builder Laravel, bukan di VIEW SQL — VIEW cukup sampai `d.customer_id` tanpa `customer_name`. Cek dulu sebelum implementasi (sama catatan yang berlaku di modul lain untuk FK logical lintas-DB).

---

## 5. PENOMORAN

Pola global `PREFIX/YY/#####` (sama seperti `INV-09`), digenerate **saat Confirm**, bukan saat draft dibuat (mengikuti pola PR: `doc_no` NULL selama `DRAFT`). Nomor **manual MAX+1 per tahun** (bukan `sys_sequence`, sama pola yang dipakai `StockPostingService`/modul lain — cek dulu apakah `sys_sequence` sudah dipakai modul lebih baru sebelum Claude Code memutuskan; kalau sudah ada konvensi baru yang established, ikuti itu, jangan pola manual ini secara membabi buta).

```
do_no = 'DO/' . date('y') . '/' . str_pad(next_seq, 5, '0', STR_PAD_LEFT)
-- next_seq = MAX(SUBSTRING_INDEX(do_no,'/',-1)) + 1 WHERE do_no LIKE 'DO/{tahun_ini}/%'
```

---

## 6. SEED DATA UAT

Skenario identik dengan `mockup_delivery_order.html` (diperluas dengan 1 kasus tambahan untuk uji batas `qty_available`). Customer: **PT Sinar Abadi Kemasan**. Asumsi `customer_id = 42` sudah ada di `tm2mst.cust_customer` (sesuaikan ID riil saat seed dijalankan — ganti semua `@CUST_ID` di bawah).

```sql
SET @CUST_ID = 42;             -- ganti sesuai id riil cust_customer PT Sinar Abadi Kemasan
SET @QUOT_1  = (SELECT id_quotation FROM quo_quotation WHERE customer_id=@CUST_ID ORDER BY id_quotation LIMIT 1);
-- Kalau belum ada quotation utk customer ini, buat quotation dummy dulu sebelum lanjut (di luar scope seed ini).

-- ═══ CO2600011 ═══
INSERT INTO co_order (co_no, co_date, quotation_id, doc_status, created_by, updated_by)
VALUES ('CO2600011', '2026-08-20', @QUOT_1, 'CONFIRMED', 'uat', 'uat');
SET @CO1 = LAST_INSERT_ID();

INSERT INTO co_order_item (order_id, line_seq, line_kind, description, qty, qty_unit, unit_price, discount_pct, subtotal, created_by, updated_by)
VALUES
  (@CO1, 1, 'PRODUCT', 'Box Karton 5-Ply RSC 40x30x25', 500.00, 'PCS', 12000.0000, 0, 6000000.00, 'uat', 'uat'),
  (@CO1, 2, 'PRODUCT', 'Sleeve Karton Laminasi Doff',   1200.00, 'PCS', 3400.0000,  0, 4080000.00, 'uat', 'uat');
SET @CO1_ITEM1 = (SELECT id_item FROM co_order_item WHERE order_id=@CO1 AND line_seq=1);
SET @CO1_ITEM2 = (SELECT id_item FROM co_order_item WHERE order_id=@CO1 AND line_seq=2);

INSERT INTO co_order_delivery (order_id, item_id, shipment_type, ship_date, ship_qty, status, created_by, updated_by)
VALUES
  (@CO1, @CO1_ITEM1, 'FULL', '2026-08-28', 500.00, 'PLANNED', 'uat', 'uat'),
  (@CO1, @CO1_ITEM2, 'FULL', '2026-08-28', 1200.00,'PLANNED', 'uat', 'uat');

-- ═══ CO2600014 — item nama SAMA dgn CO2600011 tapi HARGA BEDA (inti kasus no-merge) ═══
INSERT INTO co_order (co_no, co_date, quotation_id, doc_status, created_by, updated_by)
VALUES ('CO2600014', '2026-08-21', @QUOT_1, 'CONFIRMED', 'uat', 'uat');
SET @CO2 = LAST_INSERT_ID();

INSERT INTO co_order_item (order_id, line_seq, line_kind, description, qty, qty_unit, unit_price, discount_pct, subtotal, created_by, updated_by)
VALUES
  (@CO2, 1, 'PRODUCT', 'Box Karton 5-Ply RSC 40x30x25', 300.00, 'PCS', 13500.0000, 0, 4050000.00, 'uat', 'uat'),
  (@CO2, 2, 'PRODUCT', 'Inner Tray Corrugated',          800.00, 'PCS', 2150.0000,  0, 1720000.00, 'uat', 'uat');
SET @CO2_ITEM1 = (SELECT id_item FROM co_order_item WHERE order_id=@CO2 AND line_seq=1);
SET @CO2_ITEM2 = (SELECT id_item FROM co_order_item WHERE order_id=@CO2 AND line_seq=2);

INSERT INTO co_order_delivery (order_id, item_id, shipment_type, ship_date, ship_qty, status, created_by, updated_by)
VALUES
  (@CO2, @CO2_ITEM1, 'FULL', '2026-08-29', 300.00, 'PLANNED', 'uat', 'uat'),
  (@CO2, @CO2_ITEM2, 'FULL', '2026-08-29', 800.00, 'PLANNED', 'uat', 'uat');

-- ═══ CO2600018 — kasus TAMBAHAN: qty kecil, untuk uji batas qty_available setelah partial pull ═══
INSERT INTO co_order (co_no, co_date, quotation_id, doc_status, created_by, updated_by)
VALUES ('CO2600018', '2026-08-22', @QUOT_1, 'CONFIRMED', 'uat', 'uat');
SET @CO3 = LAST_INSERT_ID();

INSERT INTO co_order_item (order_id, line_seq, line_kind, description, qty, qty_unit, unit_price, discount_pct, subtotal, created_by, updated_by)
VALUES (@CO3, 1, 'PRODUCT', 'Label Sticker Vinyl Glossy', 100.00, 'ROLL', 45000.0000, 0, 4500000.00, 'uat', 'uat');
SET @CO3_ITEM1 = (SELECT id_item FROM co_order_item WHERE order_id=@CO3 AND line_seq=1);

INSERT INTO co_order_delivery (order_id, item_id, shipment_type, ship_date, ship_qty, status, created_by, updated_by)
VALUES (@CO3, @CO3_ITEM1, 'PARTIAL', '2026-08-30', 100.00, 'PLANNED', 'uat', 'uat');
```

**Ringkasan data untuk QA (tabel bantu, bukan bagian SQL):**

| CO | Item | Qty Outstanding | Harga | Catatan UAT |
|---|---|---|---|---|
| CO2600011 | Box Karton 5-Ply RSC 40x30x25 | 500 PCS | Rp 12.000 | Dipakai bareng baris CO2600014 → uji no-merge |
| CO2600011 | Sleeve Karton Laminasi Doff | 1.200 PCS | Rp 3.400 | Item unik, uji pull normal |
| CO2600014 | Box Karton 5-Ply RSC 40x30x25 | 300 PCS | Rp 13.500 | Nama sama dgn baris pertama, **harga beda** — wajib jadi baris terpisah |
| CO2600014 | Inner Tray Corrugated | 800 PCS | Rp 2.150 | Item unik |
| CO2600018 | Label Sticker Vinyl Glossy | 100 ROLL | Rp 45.000 | Untuk uji partial pull (tarik 60, sisa 40 harus tetap muncul di picker) |

Lihat Tier 4 §8 untuk skenario UAT langkah-demi-langkah memakai data ini.
