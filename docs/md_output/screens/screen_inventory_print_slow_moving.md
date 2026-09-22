---
name: screen_inventory_print_slow_moving
description: Laporan Slow Moving Item — item yang masih bersaldo (qty_onhand > 0) tapi mutasi terakhirnya sudah melewati ambang 3/6/9/12 bulan, per kelas stok RM/SFG/FG.
---

# Laporan Slow Moving Item

**File**: `resources/views/pages/inventory/print/slow-moving.blade.php`
**Route**: `inv.print.slow-moving` — `/inventory/print/slow-moving/{category}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Laporan per item (bukan per baris transaksi) yang menyoroti item yang STOK-nya masih ada (qty_onhand > 0) tapi sudah lama tidak bergerak (`last_movement` melewati ambang waktu terpilih). Dipakai Gudang/PPIC/Purchasing untuk identifikasi barang mati/lambat perputaran per kelas stok RM/SFG/FG. Item tanpa `last_movement` sama sekali (baris legacy, `doc_date` NULL) dianggap "belum pernah bergerak sejak tercatat" dan SELALU masuk laporan pada ambang berapa pun — dianggap paling genting, bukan dikecualikan. Item dengan `qty_onhand <= 0` tidak relevan di sini (itu "habis", bukan "slow moving"). `{category}` wajib di URL, mengikuti pola 3-menu-terpisah per kelas stok (`sidebarmenustandard.md` §5).

## Field & Kolom
Filter (tidak ikut cetak):
- Ambang Tak Bergerak (`thresholdMonths`) — tombol pilihan 3/6/9/12 bulan (`setThreshold()`)
- `warehouseId` (dropdown, opsional — semua gudang bila kosong)
- `quickSearch` (cari kode/nama item, debounce 300ms)
- `sortBy`/`sortDir` — sort 2-arah per kolom (`item_code`, `item_name`, `qty_onhand`, `value_onhand`, `idle_days`), default `idle_days`/`desc`, klik kolom lain reset paksa balik ke default kecuali arah `desc` (lihat `sortToggle()` — arah `asc` di kolom non-`idle_days` otomatis dikembalikan ke `idle_days`/`desc`)

Kolom grid (dikelompokkan per grup/kategori, grup bisa dilipat via `collapsedGroups`):
No. | Kode Item | Nama Item | Gudang (`warehouse_names`, gabungan semua lokasi terkait) | UOM | On Hand | Nilai | Mutasi Terakhir | Diam Sejak (badge "BELUM PERNAH BERGERAK" bila `last_movement` NULL, atau "N HARI" — merah bila >365 hari, kuning bila tidak)

Statistik ringkas (3 stat box): Item Slow Moving, Total Qty Tertahan, Nilai Tertahan.

## Aksi / Method
- `mount(string $category)` — validasi category (RM/SFG/FG)
- `setThreshold(int $months)` — ganti ambang, hanya menerima nilai dari `THRESHOLDS` (3/6/9/12)
- `sortToggle(string $field)` / `sortIcon(string $field)` — sort 2-arah (bukan 3-arah seperti Master Stock)
- `toggleGroup(string $groupName)` — lipat/buka 1 grup
- `printReport()` — buka semua grup, dispatch event `print-report` → `window.print()`
- `warehouses()` [Computed] — daftar `WhWarehouse`
- `itemMeta(array $ids)` (protected) — peta item_id → [code, name, group], sumber beda per category: `ProdPart` (SFG), `ProdProduct`+`category` (FG), `MatRaw` (RM, `category1`)
- `report()` [Computed] — agregat dari view `*_onhand` per kelas: SUM `qty_onhand`/`value_onhand`, MAX `last_movement`, `GROUP_CONCAT` nama gudang, filter `is_usable=true`, `qty_onhand > 0` (SQL `having`), lalu filter PHP `last_movement === null || < cutoff`, gabung meta master, quickSearch, sort, group per kategori

## Tabel & Model yang Disentuh
- **View lintas-DB** `tm2inv.v_rm_onhand` / `v_sfg_onhand` / `v_fg_onhand` (bukan `v_inv_movement`) — sumber utama, sudah punya kolom snapshot `qty_onhand`, `value_onhand`, `last_movement`, `avg_unitprice` per item+lokasi, jadi tidak perlu hitung ulang dari stockcard mentah. Dipilih via `VIEW_MAP`/`ITEM_KEY_MAP` const per category (`material_id`/`parts_id`/`product_id`).
- `App\Models\Master\MatRaw`, `ProdPart`, `ProdProduct` (tm2mst) — meta item per category
- `App\Models\Master\WhWarehouse` (tm2mst) — dropdown gudang
- Lintas-DB: query terpisah (`DB::connection('tm2inv')`) dicocokkan di PHP terhadap hasil Eloquent master — bukan JOIN lintas koneksi.

## Alur / Business Logic Penting
- Sumbernya view `*_onhand` (snapshot SEKARANG), BEDA dari `master-stock`/`stock-card` yang pakai `v_inv_movement` (mutasi per rentang tanggal) — laporan ini tidak butuh rentang tanggal, cuma status "kapan terakhir bergerak" dari kondisi saat ini.
- Keluarga "Laporan" (header navy + tombol Back, landscape) — bukan "Nota" (`printnotastandard.md` §1).
- SFG/FG masih data uji — Stock In/Out untuk SFG/FG belum digarap penuh saat layar ini ditulis.
- `collapsedGroups` direset ke kosong sebelum cetak, supaya hasil cetak selalu lengkap.

## Catatan
Tidak ada TODO eksplisit selain catatan status data uji SFG/FG di komentar kode.
