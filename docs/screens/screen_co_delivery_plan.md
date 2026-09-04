---
name: screen_co_delivery_plan
description: Customer Plan Delivery (CPDEL) — hub logistik lintas-CO untuk mengatur ulang jadwal kirim (split/gabung/geser tanggal) setelah CO Confirmed.
---

# Customer Plan Delivery (CPDEL)

**File**: `resources/views/pages/co/delivery-plan.blade.php`
**Route**: `co.delivery-plan` — `GET /co/delivery-plan`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery

## Tujuan
Grid worklist lintas-CO untuk mengelola jadwal kirim (`co_order_delivery`) SETELAH CO `CONFIRMED`. Murni penjadwalan (split/gabung/geser tanggal) — TIDAK menyentuh stok/COGS sama sekali (itu domain Delivery Order/Dispatch). Baris awal datang otomatis dari ENGINE (`co/entry.blade.php::confirmCo()`, full qty @ due_date per item PRODUCT) begitu CO di-Confirm, kecuali item yang sudah diisi manual lebih dulu via tab "Delivery" di CO Entry (saat CO masih Draft) — engine hanya mengisi item yang belum disentuh manual. Layar ini menangani kelanjutan sesudah CO fix (kondisi lapangan berubah: truk penuh, prioritas geser, dll) — Sales bisa SPLIT/GABUNG baris atau membiarkan hasil default engine. Tidak ada tombol Confirm/Create DO/Print (sesuai mockup) — baris berubah jadi `SHIPPED` otomatis nanti begitu DO terbit. Aktor: Sales / Customer Service.

## Field & Kolom
**Grid** (`deliveries` computed, `CoOrderDelivery::with(['order.quotation.customer', 'item'])`, hanya CO `doc_status='CONFIRMED'`, urut `ship_date`, limit 300):
- Kolom identitas: Customer / No. CO (`order.quotation.customer.customer_name` + `order.co_no`)
- Produk (`item.description`)
- Qty Kirim (`ship_qty`)
- Tgl Rencana (`ship_date`, dengan ikon ⚠ kalau `isLate()`)
- Status (badge: `SHIPPED` hijau / lainnya kuning-oranye — hanya 2 status ditemukan di kode: `PLANNED`/`SHIPPED`)
- Asal (`split_from_id` → "SPLIT dari #{id}" atau "Awal (dari CO Entry)")
- Aksi: ikon ✂ Split/Pindah Tanggal (hanya baris `status='PLANNED'`)

**Toolbar**: search (no. CO / nama customer / deskripsi produk), toggle "⇄ Gabung Baris" (mode merge), tombol batal.

**Modal Split** (`splitModalOpen`): `splitQty` (kosong = cuma pindah tanggal), `splitDate`.

## Aksi / Method
- `deliveries()` [Computed] — query utama, filter `search` (cocok `co_no`/`customer_name`/`item.description`).
- `isLate(CoOrderDelivery $d)` — true kalau `status==='PLANNED'` dan (`item.due_date` sudah lewat ATAU `ship_date` > `due_date`).
- `statTotal()`/`statLate()`/`statShipped()` [Computed] — kartu statistik (Rencana Aktif = count `PLANNED`; Terlambat = count `isLate()`; Terkirim Bulan Ini = `SHIPPED` dengan `ship_date` bulan berjalan).
- `openSplitModal(int $id)` / `closeSplitModal()`.
- `confirmSplit()` — guard `status==='PLANNED'`. Kalau `splitQty` kosong: cuma update `ship_date` baris yang sama. Kalau diisi: validasi `0 < qty < ship_qty` baris asal, buat baris `CoOrderDelivery` baru (`shipment_type='PARTIAL'`, `status='PLANNED'`, `split_from_id` = baris asal), kurangi `ship_qty` baris asal sebesar qty split (juga jadi `PARTIAL`). Qty selalu konservasi.
- `toggleMergeMode()` / `toggleMergeSelect(int $id)`.
- `confirmMerge()` — guard minimal 2 baris dipilih, semua dari `order_id`+`item_id` sama, semua `status='PLANNED'`. Baris menang = `ship_date` paling awal, `ship_qty` dijumlah dari semua baris terpilih; baris lain dihapus.
- `statusBadgeClass(string $status)` — helper CSS badge.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\CoOrderDelivery` → `tm2sdi.co_order_delivery` (PK `id_delivery`), relasi `order` (→ `CoOrder`), `item` (→ `CoOrderItem`), `splitFrom` (self-referencing via `split_from_id`).
- Relasi lintas-modul lewat CO: `order.quotation.customer` (lintas-DB ke `tm2mst.cust_customer`, Eloquent relationship query terpisah).

## Alur / Business Logic Penting
- Tahap dalam alur besar: **CO Confirmed → CPDEL (layar ini) → Delivery Order**. Baris di sini adalah sumber picker "Tarik Baris dari Customer Order" di `dispatch/do/entry.blade.php` (lewat VIEW `v_co_delivery_outstanding`).
- Split: split qty konservasi total (qty dipecah antar 2 baris, tidak ada assertBalance terpisah selama `splitQty < ship_qty` baris asal).
- Merge: hanya baris `PLANNED` dari CO+item yang sama boleh digabung; disatukan ke baris bertanggal paling awal, sisanya dihapus.
- Tidak ada aksi Confirm/Create DO/Print di layar ini — baris berubah `SHIPPED` otomatis oleh `DispatchService::markShipped()` (di `dispatch/do/entry.blade.php`) begitu DO terbit dan qty shipped mencapai `ship_qty`.

## Catatan
Tidak ditemukan gap/TODO eksplisit lain dalam komentar kode file ini.
