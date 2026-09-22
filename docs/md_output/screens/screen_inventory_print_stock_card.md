---
name: screen_inventory_print_stock_card
description: Kartu Stok — ledger kronologis per item+lokasi (saldo awal, tiap transaksi masuk/keluar/penyesuaian, saldo berjalan) dalam rentang tanggal, per kelas stok RM/SFG/FG.
---

# Kartu Stok

**File**: `resources/views/pages/inventory/print/stock-card.blade.php`
**Route**: `inv.print.stock-card` — `/inventory/print/stock-card/{category}/{material?}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Kartu stok klasik: pilih 1 item, tampilkan seluruh baris mutasi kronologis (tanggal, no. dokumen, tipe transaksi, referensi, lot, masuk/keluar/penyesuaian, saldo berjalan) dalam rentang tanggal terpilih, plus saldo awal periode. Dipakai Gudang/PPIC untuk audit histori pergerakan 1 item secara detail (beda dari Laporan Master Stock yang ringkas per banyak item). `{category}` wajib di URL (RM/SFG/FG, sidebarmenustandard.md §5); `{material}` opsional — bila diisi langsung buka item tsb (deep-link).

## Field & Kolom
Filter (tidak ikut cetak):
- Item (wajib) — pencarian live-search (`materialSearch`, debounce 300ms, min 2 karakter) → `materialResults`, pilih via `pickMaterial()`
- `dateFrom`, `dateTo` (date-picker, default awal bulan berjalan s/d hari ini)
- Tombol Cetak (`window.print()` langsung, disabled bila belum ada item terpilih)

Kolom ledger (tidak sortable — urutan kronologis WAJIB, saldo berjalan bergantung urutan tanggal/doc_id):
Tanggal | No. Dokumen | Tipe (badge warna per `doc_cat`: hijau STOCK/BB, biru RETURN, kuning AD/OP/ADJUST/OPNAME, abu lainnya) | Referensi (`ref_doc_no`) | Lot | Masuk | Keluar | Penyesuaian | Saldo

Baris pertama tabel selalu "Saldo Awal per {tanggal}" (`openingBalance`). Statistik ringkas (4 stat box): Saldo Awal, Total Masuk, Total Keluar, Saldo Akhir.

## Aksi / Method
- `mount(string $category, ?int $material = null)` — validasi category, set default rentang tanggal, `materialId` dari param opsional
- `material()` [Computed] — item terpilih dinormalisasi jadi `['code','name','uom']`, sumber beda per category (`ProdPart`/`ProdProduct`/`MatRaw`)
- `materialResults()` [Computed] — live-search item per category, limit 15
- `pickMaterial(int $materialId)` — set item terpilih, reset search, `unset($this->rows, $this->material)`
- `locationName()` [Computed] — label lokasi terpilih dari `tm2mst.wh_loc` (raw query, bukan Eloquent) — TAPI `locationId` sendiri TIDAK PERNAH di-set dari UI manapun di file ini (tidak ada filter lokasi di layar), jadi computed ini selalu null di praktiknya kecuali di-set lewat cara lain
- `ledger()` [Computed] — baris `v_inv_movement` dalam rentang tanggal (filter `stock_class`, `item_id`, `location_id` opsional), plus saldo awal: kalau ada baris dalam rentang pakai `qty_bb` baris pertama, kalau tidak ada cari baris terakhir SEBELUM `dateFrom` dan pakai `qty_eb`-nya
- `docCatBadge(?string $docCat)` — warna badge per makna transaksi (bukan palet baru per status, `griduistandard.md` §2)
- `totals()` [Computed] — sum `qty_in`/`qty_out`/`qty_adj`, closing = `qty_eb` baris terakhir atau saldo awal bila tidak ada baris

## Tabel & Model yang Disentuh
- **View lintas-DB** `tm2inv.v_inv_movement` (`DB::connection('tm2inv')`) — sama seperti Master Stock, UNION view yang menormalkan RM (`rm_stockcard`+`mat_raw`)/SFG (`sfg_stockcard`+`prod_part`)/FG (`fg_stockcard`+`prod_product`) jadi kolom seragam
- `App\Models\Master\MatRaw`, `ProdPart`, `ProdProduct` (tm2mst) — resolusi item terpilih & live-search
- `tm2mst.wh_loc` (raw query via `DB::connection('tm2mst')`, bukan model Eloquent) — label lokasi

## Alur / Business Logic Penting
- Satu file Livewire dipakai bertiga (RM/SFG/FG) lewat 3 route param berbeda, bukan 3 file terpisah — reuse kode karena struktur `v_inv_movement` sudah seragam.
- Ledger SENGAJA tidak diberi kolom sortable — beda dari Master Stock yang tiap baris independen per item, di sini urutan kronologis bermakna (saldo berjalan).
- Keluarga "Laporan" (header navy + Back, landscape) — bukan "Nota" `<x-print.document>` (`printnotastandard.md` §1).
- SFG/FG masih 0 baris nyata (baru data uji) — Stock In/Out sungguhan untuk kedua kelas ini belum digarap penuh saat layar ditulis.
- COMPACT + MENARIK (2026-08-28): badge tipe transaksi berwarna, header tabel sticky, zebra+hover baris.

## Catatan
- `locationName`/`locationId` ada di kode (computed + query `wh_loc`) tapi tidak ada elemen filter lokasi apa pun di markup Blade layar ini — kemungkinan sisa dari desain yang belum lengkap atau properti yang di-set dari luar (deep-link lain). Perlu verifikasi lanjut bila ingin dipakai/dibersihkan.
- Tidak ada TODO eksplisit lain di komentar kode.
