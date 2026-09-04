---
name: screen_inventory_print_master_stock
description: Laporan Master Stok — ringkasan mutasi (saldo awal/masuk/keluar/penyesuaian/saldo akhir) per item dalam rentang tanggal, per kelas stok RM/SFG/FG.
---

# Laporan Master Stok

**File**: `resources/views/pages/inventory/print/master-stock.blade.php`
**Route**: `inv.print.master-stock` — `/inventory/print/master-stock/{category}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Laporan ringkas per item (bukan per baris transaksi) yang menampilkan saldo awal, total masuk, total keluar, total penyesuaian, dan saldo akhir dalam rentang tanggal tertentu, dikelompokkan per kategori/grup item. Dipakai Gudang/PPIC/Purchasing untuk melihat kondisi stok keseluruhan per kelas (RM/SFG/FG) sekaligus, termasuk indikator status "RENDAH" saat saldo akhir di bawah `min_stock`. `{category}` wajib di URL (bukan toggle dalam halaman), mengikuti pola 3-menu-terpisah per kelas stok (`sidebarmenustandard.md` §5).

## Field & Kolom
Filter (tidak ikut cetak):
- `dateFrom`, `dateTo` (date-picker, default awal bulan berjalan s/d hari ini)
- `warehouseId` (dropdown, opsional — semua gudang bila kosong)
- `quickSearch` (cari kode/nama item, debounce 300ms)
- `sortBy`/`sortDir` — kolom sortable 3-arah (klik header): item_code, item_name, qty_bb, qty_in, qty_out, qty_adj, qty_eb; klik ke-3 pada kolom non-default reset ke default (item_code/asc)

Kolom grid (per item, dikelompokkan per grup/kategori, grup bisa dilipat via `collapsedGroups`):
No. | Kode Item | Nama Item | Gudang | UOM | Saldo Awal | Masuk | Keluar | Penyesuaian | Saldo Akhir | Status (RENDAH/OK, dibanding `min_stock`)

Statistik ringkas (5 stat box): Total Item, Saldo Awal, Masuk, Keluar, Saldo Akhir.

## Aksi / Method
- `mount(string $category)` — validasi category (RM/SFG/FG), set default rentang tanggal (awal bulan s/d hari ini)
- `toggleGroup(string $groupName)` — lipat/buka 1 grup (state server-side, bukan Alpine)
- `printReport()` — buka semua grup dulu (guna cetak lengkap), lalu dispatch event `print-report` yang memicu `window.print()` di sisi klien SETELAH re-render selesai
- `sortToggle(string $field)` — sort 3-arah per kolom
- `sortIcon(string $field)` — string ikon panah sesuai state sort
- `warehouses()` [Computed] — daftar `WhWarehouse`
- `itemMeta(array $ids)` (protected) — peta item_id → [code, name, group, uom, min_stock], sumbernya beda per category: `ProdPart` (SFG), `ProdProduct` (FG, dengan `category`), `MatRaw` (RM, default)
- `report()` [Computed] — query utama ke `v_inv_movement`, filter `stock_class`/`warehouse_id`/rentang `doc_date`, group per item lalu per lokasi (untuk saldo awal/akhir per lokasi dijumlah), terapkan quickSearch & sort, lalu group per `group` (kategori)

## Tabel & Model yang Disentuh
- **View lintas-DB** `tm2inv.v_inv_movement` (query builder mentah via `DB::connection('tm2inv')`) — sumber utama, UNION view yang menormalkan RM/SFG/FG jadi kolom seragam (item_id/item_code/item_name/stock_class/qty_bb/qty_in/qty_out/qty_adj/qty_eb/doc_date/doc_id/warehouse_id/location_id)
- `App\Models\Master\MatRaw` (tm2mst) — meta RM
- `App\Models\Master\ProdPart` (tm2mst) — meta SFG
- `App\Models\Master\ProdProduct` (tm2mst, dengan relasi `category`) — meta FG
- `App\Models\Master\WhWarehouse` (tm2mst) — dropdown & label gudang
- Lintas-DB: query terpisah (Eloquent) dicocokkan di PHP terhadap hasil view — bukan JOIN Eloquent lintas koneksi.

## Alur / Business Logic Penting
- Sumbernya `v_inv_movement` (mutasi per rentang tanggal), BUKAN `v_rm_onhand`/`v_fg_onhand`/`v_sfg_onhand` yang snapshot SEKARANG saja — laporan ini butuh pecahan masuk/keluar/adjustment per periode.
- SFG/FG masih data uji (bukan produksi sungguhan) — Stock In/Out untuk SFG/FG belum digarap secara penuh saat laporan ini ditulis.
- Keluarga "Laporan" (header navy + tombol Back, landscape, data internal) — BUKAN keluarga "Nota" (`<x-print.document>`, A4 potrait, dokumen ke pihak luar). Kedua keluarga sengaja tidak dicampur (`printnotastandard.md` §1).
- Saat cetak, `collapsedGroups` direset ke kosong sebelum `window.print()` dipicu (bukan cuma disembunyikan via CSS) supaya hasil cetak selalu lengkap terlepas dari state lipat di layar.
- Kontrol interaktif (search/sort/chevron) disembunyikan total saat cetak via class `.no-print`.

## Catatan
Tidak ada TODO eksplisit di komentar kode selain catatan SFG/FG masih data uji.
