---
name: screen_master_produk
description: Master Produk (prod_product) — grid+form header standar, BUKAN Master Card (PROD_CARD/dieline)
---

# Master Produk

**File**: `resources/views/pages/master/produk.blade.php`
**Route**: `master.produk` (`/master/produk`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
CRUD grid+form-modal standar untuk header `prod_product` (menu `MD_PROD`), paralel dengan Customer/Supplier/dst. **Bukan** layar "Master Card" (`PROD_CARD`, modul Production, F1-F11 di `masterproduct_tier1.md`: dieline/material layer/warna per pass/sign-off) — kolom `design_no`/`card_status` sudah ada di `prod_product` (delta `TIER_3A_MASTER_CARD.sql` sudah live) tapi **sengaja tidak dipakai** di layar ini supaya tidak setengah-jadi. `cur_stock` juga **tidak bisa diedit** di sini — ikut pola Warehouse: itu posisi stok transaksional dari ledger Inventory, bukan master data (hanya batas min/max yang diatur di sini).

## Field & Kolom
Form: `product_code` (required, max:100, unique, dikunci `disabled` kalau `$lockedSpec`), `product_name` (required, max:200), `category_id` (FK `prod_category`), `customer_id` (opsional — pola search-select live: ketik `custQuery` min 2 karakter → hasil `customerResults` (limit 8, filter `activate=true`) → klik pilih via `selectCustomer()`; kosongkan berarti "stock item umum"), `description`, `uom_stock`/`uom_sales` (FK `uom_unit.uom_code`), `conversion_to_stock` (numeric, default 1), `qty_limit_min`/`qty_limit_max`, `cm_length`/`cm_width`/`cm_height` (numeric, dikunci kalau `$lockedSpec`), `min_stock`/`max_stock`, `default_selling_price`/`minimum_selling_price`, `product_is_confirmed` (checkbox → `stat_confirm`), `product_activate` (checkbox → `activate`).

Grid: kolom Produk (identity-cell), Kategori, Customer, Dimensi (cm, format `P×L×T` trim nol), Status (badge TERKUNCI kalau `stat_confirm` + badge Aktif/Nonaktif), Aksi (Edit+Toggle). Search di `product_code`/`product_name`. Stat box: Total Produk, Aktif, Terkonfirmasi (subtitle "SPEC TERKUNCI"), Nonaktif.

## Aksi / Method
- `products()` — computed list + search, eager-load `category`+`customer`
- `categoryOptions()`, `uomOptions()` — computed dropdown (filter `activate=true`)
- `customerResults()` — computed search-select customer (live query min 2 char, limit 8), kosong kalau `customer_id` sudah terisi
- `selectCustomer(id)`, `clearCustomer()` — pilih/hapus customer dari search-select
- `statTotal/statActive/statConfirmed/statInactive()` — computed stat box
- `openModal(mode, id)` — buka form; kalau edit set `$lockedSpec = (bool) $p->stat_confirm`
- `showGrid()`, `save()` — navigasi & simpan (validasi lengkap + `unique:tm2mst.prod_product,product_code`)
- `toggleActive(id)` — toggle `activate`

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdProduct` → `tm2mst.prod_product` (koneksi `mst`, PK `id_product`)
- `App\Models\Master\ProdCategory` → `tm2mst.prod_category` (dropdown kategori)
- `App\Models\Master\UomUnit` → `tm2mst.uom_unit` (dropdown UoM stok/jual)
- `App\Models\Master\CustCustomer` → `tm2mst.cust_customer` (search-select customer, sama DB jadi FK dalam-DB logis)

## Alur / Business Logic Penting
- **Confirm workflow**: `product_is_confirmed` (kolom `stat_confirm`, sesuai konvensi CLAUDE.md §3 prefix `stat_`) mengunci `product_code` & dimensi (`cm_length/width/height`) — kalau produk sudah terkonfirmasi, banner "🔒 Produk ini sudah terkonfirmasi — kode & dimensi terkunci. Revisi spec inti lewat Inquiry REVISE, bukan edit langsung di sini" ditampilkan, dan field terkait `disabled` di form.
- `cur_stock` (posisi stok aktual) TIDAK ada field-nya di form ini sama sekali — murni dari ledger Inventory (transaksional), hanya `min_stock`/`max_stock` (ambang) yang dikelola di master.

## Catatan
- Layar ini eksplisit dibedakan dari Master Card (`PROD_CARD`) yang belum digarap — jangan bingung keduanya walau berbagi tabel `prod_product` (kolom `design_no`/`card_status` ada tapi tidak dipakai di sini).
- Lihat memory `project_master_produk_missing.md` — status READY_FOR_QC, `tm2mst` sudah punya skema Master Card penuh tapi belum dibangun UI-nya.
