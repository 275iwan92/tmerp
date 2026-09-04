---
name: flow_sales_order_delivery
description: Alur end-to-end Sales Order & Delivery — dari Inquiry berharga sampai Surat Jalan diterima customer.
---

# Alur Sales Order & Delivery

**Modul**: Sales Order & Delivery (tm2sdi, koneksi `sdi`)
**Prinsip desain**: Snapshot/beku di tiap tahap transisi (Quotation membekukan Inquiry, CO membekukan Quotation) — CLAUDE.md §4.

## Ringkasan Alur

```
Inquiry (berharga, costing published_flag=true)
   -> SALES QUOTATION (quotation/entry.blade.php) - tarik Inquiry, edit, Publish
   -> Customer OK (PO fisik / approval eksternal, di luar sistem)
   -> CUSTOMER ORDER Mode #1 (auto dari Quotation, snapshot) - co/entry.blade.php
   -> confirmCo() - ENGINE Customer Plan Delivery (CPDEL) auto-generate baris co_order_delivery
   -> Customer Plan Delivery (co/delivery-plan.blade.php) - Split/Gabung/geser tanggal manual
   -> Delivery Order (dispatch/do/entry.blade.php) - tarik baris CPDEL outstanding, Confirm, Tandai Terkirim
   -> Cetak DO (internal, Sisi A) + Surat Jalan (eksternal, Sisi B, tanpa harga)
```

## Tabel: Tahap -> Layar -> Aktor

| # | Tahap | Layar (route) | Aktor | Status/Trigger |
|---|---|---|---|---|
| 1 | Inquiry berharga & disetujui | `inquiry/form.blade.php` (di luar cakupan dok ini) | Sales, Costing | `iqp_costing.published_flag=true` |
| 2 | Susun Quotation | `quotation/entry.blade.php` (`quotation.entry`) | Sales | `doc_status=DRAFT` → tarik Inquiry via `QuotationBuilder`, edit header/item |
| 3 | Terbitkan Quotation | `quotation/entry.blade.php::publish()` | Sales | `DRAFT` → `PUBLISHED` (isi terkunci) |
| 4 | Cetak Surat Penawaran | `quotation/print.blade.php` (`quotation.print`) | Sales, Customer | Hanya bisa diakses saat `PUBLISHED` |
| 5 | Customer OK (PO fisik) | — (di luar sistem, manual/eksternal) | Customer | Trigger manusia, dicatat sebagai `customer_po_no` di CO |
| 6 | Buat/buka Customer Order (Mode #1) | `quotation/entry.blade.php::createOrOpenCo()` → `co/entry.blade.php` (`co.entry`) | Sales | `CoOrderBuilder::findOrCreateFromQuotation()` — snapshot item Quotation, set `iqp_inquiry.sub_state=CONVERTED` |
| 7 | Lengkapi CO (Due Date wajib, External Cost, jadwal manual opsional) | `co/entry.blade.php` | Sales | `doc_status=DRAFT` |
| 8 | Confirm CO — ENGINE CPDEL | `co/entry.blade.php::confirmCo()` | Sales | `DRAFT`→`CONFIRMED`; auto-buat `co_order_delivery` (full qty @ due_date) untuk item PRODUCT yang belum ada jadwal manual |
| 9 | Cetak Nota CO | `co/print.blade.php` (`co.print`) | Sales, Customer | Hanya bisa diakses saat `CONFIRMED` |
| 10 | Lihat/kelola CO (grid, Confirm alternatif) | `co/list.blade.php` (`co.list`) | Sales | Expand-row preview; tombol ✓ Confirm ada juga di sini (lihat Catatan/Gap) |
| 11 | Atur ulang jadwal kirim (Split/Gabung) | `co/delivery-plan.blade.php` (`co.delivery-plan`) | Sales, Customer Service | Hanya CO `CONFIRMED`; murni penjadwalan, tidak sentuh stok/COGS |
| 12 | Buat Delivery Order (pilih customer) | `dispatch/do/list.blade.php` (`dispatch.do.index`) | Dispatcher/Gudang | "+ New Record" → modal picker customer → `DoDelivery` baru `DRAFT` |
| 13 | Tarik baris CPDEL outstanding ke DO | `dispatch/do/entry.blade.php::pullSelected()` | Dispatcher | Sumber VIEW `v_co_delivery_outstanding`; 1 `co_delivery_id` = 1 `do_line` (no-merge) |
| 14 | Confirm DO | `dispatch/do/entry.blade.php::confirmDo()` / grid `confirmDelivery()` | Dispatcher | `DispatchService::confirm()` — terbitkan `do_no`, `DRAFT`→`CONFIRMED` |
| 15 | Cetak DO (internal) | `dispatch/do/print.blade.php` (`dispatch.do.print`) | Sales/Finance | Sisi A, harga tampil |
| 16 | Cetak Surat Jalan (eksternal) | `dispatch/do/print-surat-jalan.blade.php` (`dispatch.do.print-surat-jalan`) | Kurir, Customer | Sisi B, kolom harga dikeluarkan dari query |
| 17 | Tandai Terkirim | `dispatch/do/entry.blade.php::markShipped()` | Dispatcher | `CONFIRMED`→`SHIPPED`; menutup `co_order_delivery.status=SHIPPED` kalau qty shipped >= ship_qty |
| — | Laporan Win-Rate (paralel, read-only) | `sales/win-rate.blade.php` (`sales.win-rate`) | Sales Manager/Direksi | Agregasi `iqp_inquiry.sub_state` CONVERTED/LOST |

## Titik Snapshot (Prinsip Beku)

1. **Inquiry → Quotation**: item Quotation ditarik dari Inquiry costing yang `published_flag=true` (harga/spec dibekukan saat ditarik, via `QuotationBuilder`).
2. **Quotation → Customer Order**: `CoOrderBuilder::findOrCreateFromQuotation()` menyalin `quo_quotation_item` → `co_order_item` sebagai snapshot (bukan live-linked) — harga/qty CO boleh menyimpang dari Quotation sesudahnya kalau memang diperlukan. 1 CO = 1 Quotation (locked, FK `co_order.quotation_id` NOT NULL + UNIQUE).
3. **CO → CPDEL**: `co_order_delivery` dibuat dari `co_order_item` (qty penuh @ due_date), lalu diatur ulang manual (split/gabung) tanpa mengubah qty/harga sumber di CO.
4. **CPDEL → DO Line**: `do_line` disalin dari VIEW `v_co_delivery_outstanding` (yang dasarnya `co_order_delivery` + `co_order_item`) — harga bisa di-override manual per baris DO (`price_overridden`, `original_unit_price` disimpan sekali).

## Dua Sisi Dokumen (A/B) di Tahap Delivery

- **Sisi A (internal)**: `dispatch/do/print.blade.php` — harga tampil penuh, untuk verifikasi Sales/Finance.
- **Sisi B (eksternal)**: `dispatch/do/print-surat-jalan.blade.php` — kolom harga dikeluarkan dari `select()` query, bukan cuma disembunyikan CSS; dibawa kurir/diserahkan ke customer.

## Engine "Suggests, Officer Confirms" pada CPDEL

`confirmCo()` di `co/entry.blade.php` adalah ENGINE yang otomatis mengisi `co_order_delivery` (full qty @ due_date) HANYA untuk item PRODUCT yang belum punya jadwal manual — item yang Sales sudah atur manual (tab Delivery, saat CO masih Draft) tidak ditimpa. Sales lalu bisa menyesuaikan hasilnya (Split/Gabung) di `co/delivery-plan.blade.php` sesuai kondisi lapangan riil (truk penuh, prioritas geser, dll) — pola sama dengan engine lain di sistem ini (ProDev/ProCost): "engine suggests, officer confirms".

## Catatan/Gap Ditemukan Selama Dokumentasi

- **`co/list.blade.php::confirmOrder()`** men-set `doc_status='CONFIRMED'` langsung dari grid TANPA memanggil logic auto-generate `co_order_delivery` yang ada di `co/entry.blade.php::confirmCo()` — berpotensi menghasilkan CO CONFIRMED tanpa baris CPDEL untuk item yang belum ada jadwal manual, kalau CO di-Confirm dari grid (bukan dari layar Entry). Perlu verifikasi ke pemilik produk apakah ini disengaja atau gap nyata yang perlu perbaikan (idealnya kedua titik Confirm memanggil 1 method/service yang sama, mengikuti pola `DispatchService::confirm()` di modul Dispatch yang sudah dipakai konsisten dari 2 layar).
- Route layar `co/delivery-plan.blade.php` bernama `co.delivery-plan` (`GET /co/delivery-plan`) — dikonfirmasi dari `routes/web.php`, tidak mengikuti pola `co.{action}` yang sama dengan `co.entry`/`co.print` (tidak ada parameter `{order}` karena grid ini lintas-CO).
