---
name: screen_master_raw_material_index
description: Grid Master Item Bahan Baku (mat_raw) — pilot fasilitas grid custom view/sort/group-by
---

# Master Item Bahan Baku — Grid

**File**: `resources/views/pages/master/raw-material/index.blade.php`
**Route**: `master.raw-material.list` (`/master/raw-material`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
Grid daftar `mat_raw` — dipilih jadi **pilot fasilitas grid custom view/sort/group-by** (keputusan user 2026-08-27, trait `App\Livewire\Concerns\HasCustomizableGrid`) karena `mat_raw` punya 59 kolom total, representatif untuk kasus "15 kolom tersedia, 5 tampil default". Server-side pagination + WHERE (`grid_performance` di `globalrules.yaml`). Juga pilot chrome ala PowerGrid (2026-08-30, roll-out ke-2 setelah CO) dan hapus-massal untuk baris draft.

## Field & Kolom
Kolom fixed: checkbox seleksi (hanya untuk baris `!stat_confirm`), Aksi (dropdown Edit + toggle switch Aktif), Item (identity-cell kode+nama).

Kolom customizable (`gridColumnDefs()`, default tampil: `item_type`, `stat_confirm`, `category1`, `cur_stock`, `avg_unitprice`): `item_type`, `stat_confirm` (badge CONFIRMED/DRAFT), `category1` (gabungan category1»category2), `gram_weight`, `uom_stock`, `costing_method`, `cur_stock` (merah bold kalau ≤ `reorder_point`), `avg_unitprice`, `last_unitprice`, `min_stock`, `max_stock`, `reorder_point`, `brand`, `hs_code`, `lead_time_days`. Bisa sort per kolom (`toggleGridSort`), group-by, filter panel, kolom bisa dipilih via modal (ikon ⚙ toolbar). Search identitas: `material_code`/`material_name`.

Stat box (5): Total Master Item, Bahan Baku (Stok) [item_type=MATERIAL], Jasa/Sample [SERVICE+FREE_SAMPLE], Stok Kritis [cur_stock ≤ reorder_point], Draft (Belum Confirm) subtitle "Belum muncul di PR/PQ/PRT".

## Aksi / Method
- `gridKey()/gridColumnDefs()/gridDefaultColumns()/gridBaseQuery()/gridIdentitySearchColumns()` — kontrak trait `HasCustomizableGrid`
- `items()` — computed, `gridSortedQuery()` + default sort `material_name` + paginate
- `bulkDelete()` — hapus massal **hanya** baris `stat_confirm=false` (guard di level query, bukan cuma UI — komentar eksplisit: "jangan percaya $selectedRows mentah-mentah")
- `groupedItems()` — computed, group current page by `gridGroupBy`
- `stats()` — computed 5 KPI
- `toggleActive(materialId)` — toggle `activate`
- `columnValue($row, $key)` — formatter tampilan per jenis kolom (angka format ribuan, gramatur +" gsm", category1»category2, dst)
- `openItem(id)` — redirect ke `master.raw-material.edit`

## Tabel & Model yang Disentuh
- `App\Models\Master\MatRaw` → `tm2mst.mat_raw` (koneksi `mst`, PK `material_id`)

## Alur / Business Logic Penting
- **Draft vs Confirmed**: hanya item `stat_confirm=false` (DRAFT) yang bisa dicentang untuk hapus massal; item DRAFT juga belum muncul di picker PR/PQ/PRT (Procurement) — lihat detail alur confirm di `screen_master_raw_material_entry.md`.
- Server-side pagination + WHERE wajib (bukan ambil-semua-filter-di-PHP).
- Componentization 2026-08-27: buka detail disamakan ke pola `co/list` (`wire:dblclick`+redirect), bukan lagi `ondblclick="window.location=..."` inline JS.

## Catatan
- Tidak ada TODO eksplisit tersisa — pilot grid custom view dianggap berhasil dan jadi pola acuan `griduistandard.md` §10/§12.
