---
name: screen_master_warehouse_index
description: Grid Master Gudang (wh_warehouse) — struktur gudang, bukan stok on-hand
---

# Master Gudang — Grid

**File**: `resources/views/pages/master/warehouse/index.blade.php`
**Route**: `master.warehouse.list` (`/master/warehouse`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
Grid daftar `wh_warehouse` (struktur gudang, lintas modul — CLAUDE.md §3 prefix `wh_`). Server-side pagination (`HasGridPagination`) + search. Klik 2x baris redirect ke form edit (`master.warehouse.edit`). **Bukan** tampilan stok — banner eksplisit di UI: "Stok on-hand tidak ditampilkan di sini — itu posisi transaksional dari modul Inventory, bukan master data. Layar ini murni struktur gudang & lokasi/bin."

## Field & Kolom
Grid: kolom Gudang (identity-cell kode+nama), Tipe (badge warna per `warehouse_type`: RM=Bahan Baku, SFG=Semi Finished Goods, FG=Barang Jadi, MIX=Mix, WASTE=Afkir/Waste, TRANSIT=Transit), Jumlah Lokasi (`locations_count`, via `withCount('locations')`), Status (Aktif/Nonaktif), Aksi (Edit + Toggle-aktif). Search (`search`, live debounce 300ms) di `warehouse_code`/`warehouse_name`.

Stat box (KPI struktural saja, **bukan stok** — komentar eksplisit di kode: "KPI struktural saja (jumlah gudang/lokasi) — BUKAN stok, karena modul Inventory (sumber ledger stok asli) belum dibangun. Lihat catatan deviasi di migration 2026_08_21_000006"): Total Gudang, Gudang Bahan Baku (RM), Gudang Barang Jadi (FG), Total Lokasi/Bin.

## Aksi / Method
- `warehouses()` — computed, `withCount('locations')` + search + `paginate($perPage)` (trait `HasGridPagination`)
- `stats()` — computed array KPI struktural
- `toggleActive(warehouseId)` — toggle `activate`
- `openWarehouse(id)` — redirect ke `master.warehouse.edit`

## Tabel & Model yang Disentuh
- `App\Models\Master\WhWarehouse` → `tm2mst.wh_warehouse` (koneksi `mst`, PK `id_warehouse`)
- `App\Models\Master\WhLoc` → `tm2mst.wh_loc` (dihitung via relasi `locations`, count total di stat box)
- Trait `App\Livewire\Concerns\HasGridPagination` untuk server-side pagination (pola `grid_performance` di `globalrules.yaml`)

## Alur / Business Logic Penting
- Server-side pagination + WHERE (bukan ambil-semua-lalu-filter-di-PHP) — mengikuti aturan performa grid global.
- Toggle aktif/nonaktif (bukan hard delete).
- Componentization 2026-08-27: buka detail via `wire:dblclick` + `$this->redirect()` (dulu `ondblclick="window.location=..."` inline JS).

## Catatan
- Tidak ada TODO eksplisit tersisa selain catatan arsitektur di atas (stok Inventory belum ada, memang disengaja bukan gap layar ini).
