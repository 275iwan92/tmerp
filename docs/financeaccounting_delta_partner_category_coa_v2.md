# financeaccounting_delta_partner_category_coa_v2.md
**Modul 06 Finance & Accounting — delta lintas `tm2mst` + `tm2acc`.**
Status: **KEPUTUSAN TERKUNCI** — spec self-contained untuk Claude Code.

> **INI MENGGANTIKAN `financeaccounting_delta_partner_category_coa.md` (v1) SEPENUHNYA.**
> v1 ditulis berdasarkan asumsi skema yang TERNYATA salah di banyak titik (PK, nama kolom,
> dan — penting — sebagian besar §3 v1 ternyata **sudah dibangun** dengan struktur lebih baik
> dari yang saya usulkan). Spec ini sudah diverifikasi terhadap schema aktual yang diupload
> user (`sup_category.sql`, `tm2mst_20260827so.sql`, 2026-08-27). Buang v1, pakai file ini.

> **Konteks scope:** Finance & HR sudah dibuka penuh sejak DEC-SCOPE-OPEN (2026-08-26).
> `JournalPostingService` (2026-08-22) sudah live untuk auto-jurnal PV & Stock Out RM/SFG.

---

## 0. Ralat penting dari v1 (baca dulu supaya tidak bingung kenapa banyak berubah)

| Asumsi v1 (SALAH) | Kenyataan (verified dari upload) |
|---|---|
| `cust_customer.customer_id` | PK sebenarnya **`id_customer`** |
| `cust_customer.trade_type ENUM('LOCAL','IMPORT')` | Kolom sebenarnya **`customer_type VARCHAR(10) DEFAULT 'local'`**, nilai huruf kecil `local`/`import` (bukan ENUM, bukan uppercase) |
| `cust_customer.ar_account_code` (perlu dideprecate) | Kolom ini **tidak ada sama sekali** di skema aktual — tidak ada yang perlu dideprecate |
| `sup_supplier.supplier_id` | PK sebenarnya **`id_supplier`** |
| `sup_supplier` punya `trade_type` setara customer | **Tidak ada sama sekali.** Yang ada `category` (FK ke `sup_category` — kategori procurement riil: ATK/BAHAN BAKU/JASA/KERTAS/dst, BUKAN local/import). `i_po_type` LOCAL/IMPORT ada di level **transaksi PO**, bukan master supplier. |
| `prod_category` perlu dibuat baru | **Sudah ada** (`id_category`, `category_name`, `description`) — tinggal ALTER tambah 2 kolom akun |
| `prod_product` perlu tambah `category_id` + kolom akun | **Sudah ada SEMUA**: `category_id`, `stock_asset_account_id`, `cogs_account_id`, `cost_center_id`, `profit_center_id` — tidak ada yang perlu ditambah |
| `prod_part` perlu ditambah kolom akun | **Sudah ada SEMUA** (sama seperti di atas) — tidak ada yang perlu ditambah |

**Kesimpulan:** struktur akun Produk (§2 di file ini) sudah **3 lapis** (part → product → category)
dan 2 dari 3 lapis sudah selesai dibangun. Yang tersisa cuma kolom default di `prod_category`
+ logic resolve 3-lapis. Struktur Partner Account (§3) hampir semua **belum ada**, termasuk
kolom `trade_type` baru yang harus ditambahkan ke `sup_supplier` (keputusan eksplisit user,
menerima redundansi vs `po_order.i_po_type`).

---

## 1. KEPUTUSAN TERKUNCI

1. **Segmentasi akun AR/AP = `trade_type` (local/import, huruf kecil, konsisten dgn
   `cust_customer.customer_type`)** — untuk Customer sudah ada (`customer_type`), untuk
   Supplier **ditambahkan baru** (`sup_supplier.trade_type`, kolom terpisah dari `category`
   procurement yang sudah ada — dua hal beda, jangan digabung).
2. **4 kombinasi default**: CUSTOMER-local, CUSTOMER-import, SUPPLIER-local, SUPPLIER-import
   → masing-masing menunjuk 1 akun COA di `fin_account`.
3. **Akun baru di seed COA**: `122` Piutang Usaha - Ekspor (pasangan `121`), `212` Hutang
   Dagang - Impor (pasangan `211`). Verifikasi dulu account_code `121`/`211` benar-benar ada
   (§4) sebelum insert.
4. **Sumber kebenaran akun AR/AP = `fin_partner_account`** (tabel di `tm2acc`, domain
   berbeda dari `tm2mst` — **statusnya BELUM diverifikasi**, lihat §3.0). Tidak ada kolom
   `ar_account_code` semacamnya di `cust_customer`/`sup_supplier` yang perlu dideprecate.
5. **`is_overridden`** — auto-fill berhenti menimpa begitu user edit manual.
6. **Kategori Produk (`prod_category`) = fallback lapis TERAKHIR**, di bawah override
   `prod_product` dan `prod_part` yang sudah ada. Urutan resolve: `prod_part.*_account_id`
   → `prod_product.*_account_id` → `prod_category.default_*_account_id`.
7. **Visibilitas akun COA di Master Produk/Kategori = Sisi A** (Costing Officer + Direksi),
   konsisten model Costing Room.

---

## 2. KATEGORI PRODUK JADI → AKUN COA (delta MINIMAL — sebagian besar sudah ada)

### 2.1 Yang HARUS dikerjakan — hanya 2 hal
1. **ALTER `tm2mst.prod_category`** — tambah 2 kolom default akun (satu-satunya kolom yang belum ada).
2. **Resolve logic 3-lapis** di model + titik baca akun di `JournalPostingService`.

**TIDAK ADA ALTER lain.** `prod_product` dan `prod_part` sudah lengkap kolom akunnya.

### 2.2 ALTER — `tm2mst.prod_category`
```sql
ALTER TABLE prod_category
  ADD COLUMN default_stock_account_id BIGINT UNSIGNED NULL
    COMMENT 'LOGICAL -> tm2acc.fin_account -- Persediaan Barang Jadi, fallback terakhir' AFTER description,
  ADD COLUMN default_cogs_account_id BIGINT UNSIGNED NULL
    COMMENT 'LOGICAL -> tm2acc.fin_account -- akun HPP, fallback terakhir' AFTER default_stock_account_id;
```

### 2.3 Seed minimal
```sql
-- Ambil 1 kategori existing sbg default (cek dulu isinya -- prod_category AUTO_INCREMENT=6
-- artinya sudah ada s.d. 5 baris; pilih salah satu yang paling generik atau tambah baris baru
-- 'UMUM' bila belum ada kategori generik).
UPDATE prod_category
SET default_stock_account_id = (SELECT account_id FROM fin_account WHERE account_code='113500'),
    default_cogs_account_id  = (SELECT account_id FROM fin_account WHERE account_code LIKE '51%' LIMIT 1)
WHERE default_stock_account_id IS NULL;  -- isi semua kategori yang masih kosong, sama untuk semua sbg starting point
```
> Verifikasi 5 baris `prod_category` yang sudah ada isinya apa dulu (`SELECT * FROM prod_category`)
> sebelum menjalankan UPDATE massal ini — sesuaikan bila tiap kategori sebaiknya beda akun.

### 2.4 Resolve logic — 3 lapis
```php
// App\Models\Prod\ProdPart (existing model, tambah 2 method)
public function resolveStockAccountId(): ?int
{
    return $this->stock_asset_account_id
        ?? $this->product?->stock_asset_account_id
        ?? $this->product?->category?->default_stock_account_id;
}
public function resolveCogsAccountId(): ?int
{
    return $this->cogs_account_id
        ?? $this->product?->cogs_account_id
        ?? $this->product?->category?->default_cogs_account_id;
}
```
```php
// App\Models\Prod\ProdProduct (existing model, tambah 2 method -- dipakai saat DO/shipment,
// BUKAN saat SFG; prod_product = FG, prod_part = SFG/WIP)
public function resolveStockAccountId(): ?int
{
    return $this->stock_asset_account_id ?? $this->category?->default_stock_account_id;
}
public function resolveCogsAccountId(): ?int
{
    return $this->cogs_account_id ?? $this->category?->default_cogs_account_id;
}
```
```php
// Di JournalPostingService -- cari baris yang sekarang begini (auto-jurnal SFG, 2026-08-22):
//   if (!$part->cogs_account_id) { log warning; skip; continue; }
// GANTI menjadi:
$cogsAccountId = $part->resolveCogsAccountId();
if (!$cogsAccountId) { log warning; skip; continue; }
// (idem stock_asset_account_id -> resolveStockAccountId())
//
// Untuk FG (COGS saat DO/shipment, financeaccounting_tier2.md A5, BELUM diimplementasi
// menurut catatan "Delivery/Invoice auto-jurnal masih ditunda, butuh Costing V2 snapshot +
// DO") -- saat itu digarap, pakai ProdProduct::resolveCogsAccountId()/resolveStockAccountId()
// yang sama, bukan hardcode 113500/51x.
```
Efek: part/produk yang sudah diisi akun manual → **tidak berubah** (override menang). Yang
kosong tapi kategorinya sudah ada default → otomatis tertutup. Yang semuanya kosong → tetap
dilewati+peringatan (perilaku existing dipertahankan sebagai fallback terakhir).

### 2.5 UI delta
| Elemen | Field | Visibilitas |
|---|---|---|
| Layar Master Kategori Produk (baru, kecil) — tambah 2 field akun ke form yang mungkin sudah ada | `default_stock_account_id`, `default_cogs_account_id` | **Costing Officer + Direksi SAJA** |
| Dropdown Kategori di Master Produk (existing, `category_id` sudah ada) | — | Semua role (tidak berubah) |
| Panel akun per-product/per-part (existing) | — | Tetap Sisi A seperti sekarang, tidak berubah |

---

## 3. PARTNER ACCOUNT AUTO-FILL (Customer + Supplier) — ⛔ SEBAGIAN BESAR PENDING

> **§3.0 s.d. §3.2 BOLEH dikerjakan sekarang** (menambah kolom & tabel baru, aman/idempotent).
> **§3.3 (Observer auto-fill) JANGAN dieksekusi dulu** — tunggu user konfirmasi status
> `fin_partner_account` di `tm2acc` (belum diupload/diverifikasi saat spec ini ditulis).

### 3.0 Verifikasi wajib duluan
`fin_partner_account` ada di `tm2acc`, bukan `tm2mst` — tidak tercakup di upload
`tm2mst_20260827so.sql`. **Sebelum §3.3 dieksekusi**, cek dump/schema `tm2acc` aktual:
kolom apa saja yang sungguh ada, apakah sudah ada baris data, apakah sudah ada mekanisme
serupa dari sesi lain.

### 3.1 DDL baru — `tm2mst.sup_supplier` (kolom baru, keputusan eksplisit user)
```sql
ALTER TABLE sup_supplier
  ADD COLUMN trade_type VARCHAR(10) NULL DEFAULT 'local'
    COMMENT 'local | import -- setara cust_customer.customer_type; TERPISAH dari category (procurement) dan po_order.i_po_type (transaksi)'
    AFTER category;
```
> Nama & gaya kolom sengaja disamakan dengan `cust_customer.customer_type` (VARCHAR(10),
> huruf kecil) demi konsistensi lintas partner_type, BUKAN ENUM seperti draf v1 lama.

### 3.2 DDL baru — `tm2acc.fin_partner_segment_default`
```sql
CREATE TABLE fin_partner_segment_default (
  seg_id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_type          ENUM('CUSTOMER','SUPPLIER') NOT NULL,
  trade_type            VARCHAR(10) NOT NULL COMMENT 'local | import -- match gaya cust_customer/sup_supplier, bukan ENUM',
  account_id            INT UNSIGNED NOT NULL COMMENT 'FK -> fin_account; AR utk CUSTOMER, AP utk SUPPLIER',
  payment_term_default  VARCHAR(50) NOT NULL DEFAULT 'NET30',
  is_active             TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(100) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(100) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (seg_id),
  UNIQUE KEY uq_partner_segment (partner_type, trade_type),
  CONSTRAINT fk_seg_acc FOREIGN KEY (account_id) REFERENCES fin_account(account_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Default akun AR/AP per segmen trade_type -- lapis 1 (jarang disentuh)';
```

Seed (jalankan setelah verifikasi §4 akun `121`/`122`/`211`/`212` benar ada):
```sql
INSERT INTO fin_account (account_code, account_name, account_type, normal_balance, is_recon, is_postable, is_active)
VALUES
  ('122', 'Piutang Usaha - Ekspor', 'ASSET', 'DEBIT', 1, 1, 1),
  ('212', 'Hutang Dagang - Impor', 'LIABILITY', 'CREDIT', 1, 1, 1);

INSERT INTO fin_partner_segment_default (partner_type, trade_type, account_id, payment_term_default)
SELECT 'CUSTOMER', 'local',  account_id, 'NET30' FROM fin_account WHERE account_code='121'
UNION ALL
SELECT 'CUSTOMER', 'import', account_id, 'NET30' FROM fin_account WHERE account_code='122'
UNION ALL
SELECT 'SUPPLIER', 'local',  account_id, 'NET30' FROM fin_account WHERE account_code='211'
UNION ALL
SELECT 'SUPPLIER', 'import', account_id, 'NET45' FROM fin_account WHERE account_code='212';
```

### 3.3 ⛔ PENDING — Observer auto-fill (jangan jalankan dulu)
Setelah §3.0 terverifikasi, mekanismenya:
```php
// App\Observers\CustCustomerObserver / SupSupplierObserver
public function created($partner): void
{
    app(PartnerAccountResolver::class)->resolve(
        $partner instanceof CustCustomer ? 'CUSTOMER' : 'SUPPLIER',
        $partner instanceof CustCustomer ? $partner->id_customer : $partner->id_supplier,
        $partner->trade_type ?? $partner->customer_type // nama kolom beda per tabel
    );
}

public function updated($partner): void
{
    $col = $partner instanceof CustCustomer ? 'customer_type' : 'trade_type';
    if (!$partner->wasChanged($col)) return;
    $existing = FinPartnerAccount::where('partner_type', ...)->where('partner_id', ...)->first();
    if ($existing && $existing->is_overridden) return; // jangan timpa override manual
    // ... re-resolve sama seperti created()
}
```
```php
// App\Services\Finance\PartnerAccountResolver
public function resolve(string $partnerType, int $partnerId, string $tradeType): void
{
    $default = FinPartnerSegmentDefault::where('partner_type', $partnerType)
        ->where('trade_type', $tradeType)->where('is_active', 1)->first();
    if (!$default) return;
    $field = $partnerType === 'CUSTOMER' ? 'ar_account_id' : 'ap_account_id';
    FinPartnerAccount::updateOrCreate(
        ['partner_type' => $partnerType, 'partner_id' => $partnerId],
        [$field => $default->account_id, 'payment_term' => $default->payment_term_default, 'is_overridden' => 0]
    );
}
```

### 3.4 UI delta (menyusul setelah §3.3 jalan)
| Layar | Perubahan |
|---|---|
| Master Customer (tab Akuntansi) | Field "Akun Piutang" dari `fin_partner_account.ar_account_id`, readonly + badge segmen; tombol Override (permission `FINACC_OVERRIDE`). |
| Master Supplier (tab Akuntansi, buat kalau belum ada) | Simetris, "Akun Hutang" dari `ap_account_id`. |

---

## 4. VERIFIKASI WAJIB SEBELUM §2.3/§3.2/§3.3 (jangan ALTER buta)

1. `SELECT account_id, account_code FROM fin_account WHERE account_code IN ('121','211','113500')`
   — pastikan ada sebelum insert `122`/`212` & sebelum seed §2.3/§3.2.
2. `SELECT * FROM prod_category` — lihat isi 5 baris existing sebelum UPDATE massal §2.3.
3. Cek schema `tm2acc.fin_partner_account` aktual (§3.0) sebelum §3.3.

---

## 5. TASK CLAUDE CODE (urut)

### Grup A — §2 Kategori Produk (BOLEH JALAN SEKARANG, delta kecil)
1. Verifikasi §4.1–4.2.
2. Migration `tm2mst`: ALTER `prod_category` (2 kolom, §2.2) + seed/update (§2.3).
3. Model: tambah 4 method resolve di `ProdPart` + `ProdProduct` (§2.4).
4. Ubah titik baca akun di `JournalPostingService` (auto-jurnal SFG existing) supaya pakai
   method resolve, bukan baca kolom langsung. **Jangan tulis ulang service.**
5. UI: tambah 2 field akun ke layar Master Kategori Produk (Sisi A saja).
6. Uji: part/produk dengan akun kosong tapi kategori terisi → tidak lagi skip+warning.

### Grup B — §3 Partner Account (§3.0–3.2 boleh jalan, §3.3 TUNGGU)
7. Verifikasi §4.3 (schema `fin_partner_account` aktual).
8. Migration `tm2mst`: ALTER `sup_supplier` tambah `trade_type` (§3.1).
9. Migration `tm2acc`: `fin_partner_segment_default` baru + seed (§3.2) — **hanya kalau**
   `fin_account.121/211` sudah dikonfirmasi ada.
10. **STOP** — §3.3 (Observer + auto-fill) menunggu konfirmasi user lebih lanjut soal status
    `fin_partner_account`.

---

## 6. REMINDER UPDATE STATE (untuk Anda, bukan Claude Code)

Setelah **Grup A** dieksekusi:
- Buat/update `masterproduct_state.yaml` — catat delta `prod_category` + resolver 3-lapis,
  dan bahwa `JournalPostingService` diubah titik bacanya (bukan ditulis ulang).

Setelah **Grup B §3.1–3.2** dieksekusi:
- `procurement_state.yaml` — catat `sup_supplier.trade_type` ditambahkan (redundan-disengaja
  vs `po_order.i_po_type`, alasan: segmentasi akun AP butuh atribut tetap di master, bukan
  per-transaksi).

Setelah **Grup B §3.3** dikonfirmasi & dieksekusi (menyusul, setelah upload `tm2acc`):
- `financeaccounting_state.yaml` — tambah ke `artefak_selesai` & `keputusan_terkunci`.
