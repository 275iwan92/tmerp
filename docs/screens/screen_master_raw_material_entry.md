---
name: screen_master_raw_material_entry
description: Form Master Item Bahan Baku (mat_raw) — 5 tab, confirm workflow kunci kategori+kode+3 akun wajib
---

# Master Item Bahan Baku — Form (Entry)

**File**: `resources/views/pages/master/raw-material/entry.blade.php`
**Route**: `master.raw-material.create` (`/master/raw-material/create`) dan `master.raw-material.edit` (`/master/raw-material/{material}/edit`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
Form 5-tab untuk `mat_raw` (item bahan baku). Kode item **auto-generate** dari kombinasi prefix hirarki `mat_category` (5 level: Kategori→Jenis→Gramatur/Spec→Ukuran→Merek). Dilengkapi **confirm workflow** (2026-08-26): item DRAFT masih bebas diubah kategorinya, begitu di-Confirm kategori & kode terkunci permanen dan item baru muncul di picker PR/PQ/PRT.

## Field & Kolom
Header: `material_name` (required, max:200), `material_code` (readonly, auto dari kategori), `item_type` (MATERIAL/SERVICE/FREE_SAMPLE), `purchasable` (boolean).

**Tab 1 Kategori**: `pick1`..`pick5` (dropdown berjenjang dari `mat_category`, level 4 & 5 opsional, disabled kalau `isLocked`), `hs_code`, `old_item_code`, `old_item_name`. Kode item auto-regenerate tiap `pick` berubah (`regenerateCode()`, kecuali sudah locked) dari prefix gabungan + suffix angka kalau bentrok.

**Tab 2 Spesifikasi & QC**: `gram_weight`, `cm_length`/`cm_width` (plano), `mm_thickness`, `brand`, `color`, `qc_required` (boolean, "menyalakan gerbang QC kualitas di layar PV"), `shelf_life_days`, `moisture_pct`.

**Tab 3 UoM & Pengadaan**: `uom_stock`/`uom_purchase`/`uom_usage` (FK `uom_unit`), `conversion_to_stock` (catatan: "nilai AWAL saja — di PV selalu boleh ditimpa manual untuk barang curah"), `conversion_to_usage`, `lead_time_days`, `tol_purchase`, `purchase_tax_code` (PPN/NON_PPN), `suggest_supplier_id` (dropdown `sup_supplier` via query builder tm2mst — bukan Eloquent relasi), `costing_method` (AVG/FIFO/LIFO/STANDARD), `is_fx_sensitive` (boolean, ditandai di PR/PQ "harga mengikuti kurs").

**Tab 4 Stok & Lokasi**: `min_stock`, `safety_stock`, `reorder_point`, `max_stock`, `def_location_id` (teks bebas, **belum ditaut** ke `wh_warehouse` — catatan eksplisit di UI), `cur_stock`/`avg_unitprice` (readonly, dikelola sistem lewat posting Purchase Receive — tidak diedit manual).

**Tab 5 Akuntansi**: `valuation_class` (label laporan saja, bukan penentu akun), `stock_asset_account_id`, `cogs_account_id`, `gr_ir_account_id` (3 dropdown akun dari `tm2acc.fin_account` `is_postable=1`), `cost_center_id`, `profit_center_id` (dari `FinCostCenter`/`FinProfitCenter`). Placeholder dropdown "— default kategori —" (fallback ke `mat_category.default_*_account_id` kalau kosong — lihat `screen_master_mat_category.md`).

## Aksi / Method
- `mount(?material)` — load data existing, resolve `pick1..5` dari teks `category1..5` tersimpan (cari `id_category` matching)
- `isEdit()`, `isLocked()` (computed, = `stat_confirm`), `currentStock()` (computed readonly cur_stock/avg_unitprice)
- `cat1Options..cat5Options()` — computed dropdown berjenjang (level N tergantung `pick(N-1)`)
- `uomOptions()`, `suppliers()` (query builder `tm2mst.sup_supplier`), `coaAccounts()` (query builder `tm2acc.fin_account`, cross-DB), `costCenters()`, `profitCenters()` — computed dropdown sources
- `setPick(level, value)` — set 1 level kategori, reset semua level di bawahnya, regenerate kode (kalau belum locked)
- `regenerateCode()` (protected) — gabung `prefix_char` semua level terpilih + suffix angka kalau kode sudah dipakai
- `save()` — validasi (`unique:tm2mst.mat_raw,material_code`), simpan `category1..5` sebagai **teks snapshot nama kategori** (bukan FK id) ke `mat_raw`, redirect ke edit
- `confirm()` — syarat 3 akun (`stock_asset_account_id`, `cogs_account_id`, `gr_ir_account_id`) wajib terisi (guard di form, bukan DB constraint); begitu confirm set `stat_confirm=true`+`confirmed_at`+`confirmed_by`
- `delete()` — hapus item, redirect ke list

## Tabel & Model yang Disentuh
- `App\Models\Master\MatRaw` → `tm2mst.mat_raw` (koneksi `mst`, PK `material_id`)
- `App\Models\Master\MatCategory` → `tm2mst.mat_category` (dropdown hirarki, FK dalam-DB logis via nama snapshot)
- `App\Models\Master\UomUnit` → `tm2mst.uom_unit`
- `App\Models\Acc\FinCostCenter`, `App\Models\Acc\FinProfitCenter` → koneksi `acc` (tm2acc), lintas-DB (query Eloquent koneksi berbeda, bukan join langsung)
- Query builder langsung (bukan Eloquent) ke `tm2mst.sup_supplier` dan `tm2acc.fin_account` (pola 1 CLAUDE.md §2 — query terpisah per koneksi)

## Alur / Business Logic Penting
- **Kode item auto dari hirarki kategori** — bukan diketik manual, field readonly bertanda 🔒.
- **Confirm workflow** (mirip pola `product_is_confirmed` di Master Produk & `stat_confirm` global CLAUDE.md §3): DRAFT boleh ubah kategori bebas; setelah Confirm, kategori & kode **terkunci permanen** ("hubungi Admin bila perlu reklasifikasi") dan baru item ini muncul di pencarian PR/PQ/PRT. Syarat confirm: 3 akun akuntansi wajib terisi.
- `category1..5` di `mat_raw` disimpan sebagai **snapshot teks nama kategori** (bukan FK id ke `mat_category`) — konsisten dengan prinsip snapshot CLAUDE.md §4, tapi berarti perubahan nama kategori di master tidak otomatis merambat ke item yang sudah tersimpan.
- `def_location_id` sengaja teks bebas — **belum ditaut** ke `wh_warehouse`, dicatat eksplisit sebagai gap sementara.

## Catatan
- Gap eksplisit di kode: `def_location_id` belum ditaut Master Gudang.
- `conversion_to_stock` cuma nilai awal — boleh ditimpa manual di Purchase Receive (PV) untuk barang curah.
- Lihat memory `project_item_master_status.md` — status Grid+Form live, `fin_profit_center` sudah dikoreksi vs draft awal.
