# DELTA — `m_raw_material` (tm2mst) · Field Fisik & Linkage Akuntansi
**Sumber pemicu:** review mockup Item Master (`gemini-code-*.html`) · Keputusan Jo 2026-08-20

| | |
|---|---|
| **Sifat** | Delta tambahan atas `procurement_tier3.md` §7.1 (bukan pengganti — akumulatif) |
| **Menyentuh** | `tm2mst.m_raw_material`, `tm2acc` (tabel baru `fin_profit_center`) |

---

## 1. KOREKSI ATAS DESAIN SEBELUMNYA

`procurement_tier3.md` menambahkan `costing_method ENUM('AVG','FIFO','LIFO')`. Mockup menambahkan `price_control ENUM('V','S')` (Moving Average vs Standard Price ala SAP). **Dua konsep ini tumpang tindih** — Standard Price tak kompatibel dengan pelacakan lot FIFO/LIFO. Digabung jadi satu kolom, **bukan** dua:

```sql
ALTER TABLE m_raw_material
  MODIFY COLUMN costing_method ENUM('AVG','FIFO','LIFO','STANDARD') NOT NULL DEFAULT 'AVG'
    COMMENT 'i_ = metode harga perolehan PER ITEM. STANDARD = harga tetap (jarang dipakai, biasanya utk barang non-fluktuatif)';
```
> Jika `procurement_tier3.md` belum dieksekusi ke DB, cukup pakai definisi ini langsung saat `ADD COLUMN` (ganti `MODIFY` jadi `ADD`).

**Linkage akuntansi disederhanakan** — bukan lapisan `valuation_class` → tabel penentu akun (OBYC-style), tapi **FK langsung** ke `fin_account`, konsisten dengan pola "logical FK langsung" yang dipakai di seluruh TMERP (tak ada tabel indirection tambahan).

---

## 2. DELTA DDL — FIELD FISIK

```sql
ALTER TABLE m_raw_material
  -- Klasifikasi kepabeanan & legacy
  ADD COLUMN hs_code            VARCHAR(20)  NULL COMMENT 'i_ = kode HS/tarif bea cukai, mis. 4810.13.00',
  ADD COLUMN old_material_code  VARCHAR(60)  NULL COMMENT 'i_ = kode legacy, bantu migrasi data',
  ADD COLUMN old_material_name  VARCHAR(150) NULL COMMENT 'i_ = nama legacy',

  -- Spesifikasi fisik (khusus kertas/board, NULL utk kategori lain)
  ADD COLUMN gram_weight   DECIMAL(8,2)  NULL COMMENT 'i_ = gramatur (gsm)',
  ADD COLUMN cm_length     DECIMAL(10,4) NULL COMMENT 'i_ = panjang plano (cm)',
  ADD COLUMN cm_width      DECIMAL(10,4) NULL COMMENT 'i_ = lebar plano (cm)',
  ADD COLUMN mm_thickness  DECIMAL(10,4) NULL COMMENT 'i_ = ketebalan (mm)',
  ADD COLUMN brand         VARCHAR(60)   NULL COMMENT 'i_ = merek pabrik',
  ADD COLUMN color         VARCHAR(40)   NULL,

  -- QC & masa simpan
  ADD COLUMN qc_required     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'i_ = wajib inspeksi QC saat penerimaan (nyambung ke qc_quality_status di pv_line)',
  ADD COLUMN shelf_life_days SMALLINT UNSIGNED NULL COMMENT 'i_ = masa simpan (hari); NULL = tak terbatas',
  ADD COLUMN moisture_pct    DECIMAL(5,2) NULL COMMENT 'i_ = batas kelembaban standar (%)';
```

> Field fisik ini **NULL untuk kategori non-kertas** (ATK, jasa, sparepart) — tidak semua item punya gramatur/dimensi. Validasi wajib-isi hanya berlaku bila `doc_rmprefix1.stock_class` kategori kertas/board.

---

## 3. DELTA DDL — LINKAGE AKUNTANSI (Odoo-style, langsung ke COA)

```sql
ALTER TABLE m_raw_material
  ADD COLUMN valuation_class   VARCHAR(30)  NULL COMMENT 'i_ = label klasifikasi laporan SAJA (bukan penentu akun) — mis. "Raw Material", "Auxiliary Chemical"',

  ADD COLUMN stock_asset_account_id BIGINT UNSIGNED NULL COMMENT 'i_ = LOGICAL -> tm2acc.fin_account (is_postable=1) — akun aset persediaan default item ini',
  ADD COLUMN cogs_account_id       BIGINT UNSIGNED NULL COMMENT 'i_ = LOGICAL -> tm2acc.fin_account — akun HPP/beban saat pemakaian',
  ADD COLUMN gr_ir_account_id      BIGINT UNSIGNED NULL COMMENT 'i_ = LOGICAL -> tm2acc.fin_account — akun kliring hutang-belum-ditagih (barang diterima, faktur belum masuk)',

  ADD COLUMN cost_center_id    BIGINT UNSIGNED NULL COMMENT 'i_ = LOGICAL -> tm2acc.fin_cost_center — default cost center pemakaian',
  ADD COLUMN profit_center_id  BIGINT UNSIGNED NULL COMMENT 'i_ = LOGICAL -> tm2acc.fin_profit_center (BARU, lihat §4)';
```

**Fungsi tiap akun (dipakai posting GL fase-2, sesuai `FIN_RULES.integrasi.keputusan_A`):**

| Akun | Terpakai saat |
|---|---|
| `stock_asset_account_id` | PV posting (barang masuk) → `Dr Persediaan` |
| `gr_ir_account_id` | PV posting, sebelum faktur masuk → `Cr GR/IR` (kliring sementara); saat PI `SUPPLIER_BILL` diverifikasi → `Dr GR/IR` dibalik, `Cr Hutang Usaha` |
| `cogs_account_id` | Stock issue ke produksi → `Dr HPP/Beban`, `Cr Persediaan` |
| `cost_center_id` / `profit_center_id` | Dimensi tambahan tiap baris jurnal (`fin_voucher_line`), bukan pengganti `co_id` |

> Ketiga akun **nullable** — bila kosong, sistem jatuh ke akun default per `doc_rmprefix1.default_coa_account_id` (sudah ada di `procurement_tier3.md` §7.1) atau default kategori umum. Field ini untuk **override per item**, bukan wajib diisi semua.

---

## 4. TABEL BARU — `tm2acc.fin_profit_center`

Belum ada padanannya di TMERP. Dibuat mirror minimal `fin_cost_center` (pola sama: kode terkontrol, bukan free-text):

```sql
CREATE TABLE fin_profit_center (
  profit_center_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(30)  NOT NULL COMMENT "mis. 'PC-PACKAGING', 'PC-COMMERCIAL'",
  name         VARCHAR(100) NOT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (profit_center_id),
  UNIQUE KEY uq_pc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Profit center — dimensi laporan business unit, dipakai di jurnal & item master';
```
> **Konfirmasi Jo diperlukan:** apakah `fin_cost_center` sesungguhnya sudah punya struktur serupa (kode+nama+aktif)? Kalau beda, sesuaikan `fin_profit_center` supaya sepasang. DDL asli `fin_cost_center` tak ada di project knowledge (hanya disebut naratif di `financeaccounting_tier1.md`) — verifikasi ke DB aktual sebelum eksekusi.

---

## 5. URUTAN EKSEKUSI

1. `CREATE TABLE fin_profit_center` (tm2acc) — **setelah** verifikasi pola `fin_cost_center` aktual.
2. `ALTER TABLE m_raw_material` §2 (field fisik) — aman, tanpa FK.
3. `ALTER TABLE m_raw_material` §3 (field akuntansi) — **setelah** langkah 1, karena `profit_center_id` menunjuk tabel baru.
4. Seed `fin_profit_center` minimal 2 baris (`PC-PACKAGING`, `PC-COMMERCIAL`) sesuai contoh mockup.

## 6. DAMPAK KE DOKUMEN LAIN

- `procurement_state.yaml` — tandai bahwa `costing_method` di §7.1 **diperluas** jadi 4 nilai (tambah `STANDARD`), bukan revisi baru.
- `financeaccounting_state.yaml` — catat penambahan `fin_profit_center` sebagai tabel baru modul Finance (dipicu kebutuhan Item Master, bukan inisiatif Finance sendiri).
- Item Master **belum punya Tier 1-4 resmi** — dokumen ini sifatnya delta/addendum. Layar Livewire-nya (§7 mockup) menyusul sebagai bagian modul **Lookup Data / Inventory**, bukan Procurement murni.
