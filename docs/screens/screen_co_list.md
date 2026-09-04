---
name: screen_co_list
description: Grid worklist Customer Order — CO lahir dari Quotation PUBLISHED (bukan "+ New Record"), Confirm langsung dari grid, expand-row preview item+external cost.
---

# Daftar Customer Order

**File**: `resources/views/pages/co/list.blade.php`
**Route**: `co.list` — `GET /co`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery

## Tujuan
Grid worklist Customer Order mengikuti `griduistandard.md`. Tidak ada tombol "+ New Record" — CO lahir dari Quotation yang sudah `PUBLISHED` (tombol "Buat Customer Order" di layar Quotation Entry). Kolom Aksi non-master: ↗ Buka + ✓ Confirm kondisional (DRAFT→CONFIRMED langsung dari grid), bukan Edit+Toggle-aktif. Mendukung kustomisasi kolom/sort (`HasCustomizableGrid`), expand-row preview item CO + External Cost, dan hapus massal (hanya CO berstatus DRAFT). Aktor: Sales.

## Field & Kolom
**Grid** (`gridBaseQuery`: `CoOrder::with(['quotation.customer', 'items', 'externalCosts'])`):
- Kolom tetap (anchor finansial): identitas (Customer/No. CO), Status, Net Receivable (dari `$order->totals()`, dihitung per baris via `grandTotals()`).
- Kolom kustomisasi (`gridColumnDefs`): `quotation.quotation_no` (Ref. Quotation, TIDAK sortable — relasi, bukan kolom langsung), `co_date`, `customer_po_no`, `ship_address_label`, `discount_doc_pct`, `discount_qty_pct`, `notes`, `created_by`, `updated_by`. Default tampil: `quotation.quotation_no`, `co_date`, `customer_po_no`.
- Search identitas (`gridIdentitySearchColumns`): `co_no` (No. CO), `quotation.customer.customer_name` (Nama Customer) — resolver cross-DB manual 2 tahap (lihat Alur).
- Expand-row (klik chevron): tabel item (Produk/Jasa, Type, Qty, Harga, Disc%, Subtotal, Due Date) + tabel External Cost (Category, Description, Nominal, Tax Treatment) kalau ada.

**Stat box**: Total Customer Order, Draft, Confirmed.

## Aksi / Method
- `gridKey()` → `'sales.co-list'`; `gridColumnDefs()`, `gridDefaultColumns()`, `gridBaseQuery()`, `gridIdentitySearchColumns()` — kontrak `HasCustomizableGrid`.
- `gridCrossDbSearchResolvers()` — resolver khusus `quotation.customer.customer_name` (lihat Alur).
- `columnValue($row, string $key)` — formatter kolom (tanggal, persen).
- `expanded` (array) + `toggleExpand(int $id)` — expand-row master-detail (bisa multi-row terbuka).
- `orders()` [Computed] — `gridSortedQuery()->paginate($perPage)`, default order `id_order` desc kalau tidak ada sort.
- `bulkDelete()` — hanya CO `doc_status='DRAFT'` dalam `selectedRows` yang dihapus (beserta items+externalCosts).
- `grandTotals()` [Computed] — map `id_order` → `net_receivable`.
- `statTotal()`/`statDraft()`/`statConfirmed()` [Computed].
- `openOrder(int $id)` — redirect ke `co.entry`.
- `confirmOrder(int $id)` — guard `doc_status==='DRAFT'` & items tidak kosong; set `doc_status='CONFIRMED'`. **Catatan**: method ini TIDAK memicu engine auto-generate CPDEL (tidak ada create `CoOrderDelivery` di sini) — beda dari `confirmCo()` di `co/entry.blade.php` yang punya engine tersebut. Lihat Catatan di bawah.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\CoOrder`, `CoOrderItem`, `CoOrderExternalCost` → `tm2sdi`.
- Resolver cross-DB: `App\Models\Master\CustCustomer` (tm2mst) dan `App\Models\Sdi\QuoQuotation` (tm2sdi) di-query terpisah untuk mencari `id_customer` → `id_quotation` → `co_order.quotation_id`.

## Alur / Business Logic Penting
- **Kenapa tidak "+ New Record"**: CO harus berasal dari 1 Quotation PUBLISHED (locked, FK `co_order.quotation_id` NOT NULL + UNIQUE) — pembuatan hanya via `CoOrderBuilder::findOrCreateFromQuotation()` dari layar Quotation.
- **Cross-DB search** (`gridCrossDbSearchResolvers`): `quotation.customer.customer_name` melintasi `tm2sdi` (co_order/quo_quotation) → `tm2mst` (cust_customer). `whereHas()` bertingkat lintas-koneksi TIDAK BISA dipakai (Eloquent membangun 1 EXISTS subquery di koneksi yang sama, error "table doesn't exist"). Resolusi manual 2 tahap sesuai CLAUDE.md §2 pola 1: cari `id_customer` yang cocok nama (tm2mst) → `id_quotation` yang `customer_id`-nya masuk situ (tm2sdi) → filter `co_order.quotation_id`.
- **Bulk delete**: guard di level query — hanya CO `DRAFT` bisa dicentang & dihapus (checkbox tidak muncul untuk CO non-DRAFT).
- Expand-row menampilkan External Cost karena ikut masuk hitungan Net Receivable (`CoOrder::totals()`).

## Catatan
- **Potensi inkonsistensi**: `confirmOrder()` di grid ini (list.blade.php) langsung set `doc_status='CONFIRMED'` TANPA memanggil logic auto-generate `co_order_delivery` yang ada di `co/entry.blade.php::confirmCo()` (engine CPDEL). Kalau CO di-Confirm dari grid ini (bukan dari layar Entry), kemungkinan item PRODUCT tanpa jadwal manual tidak akan otomatis dapat baris `co_order_delivery` — perlu verifikasi apakah ini gap nyata atau memang disengaja (grid Confirm untuk kasus sudah ada jadwal manual saja). TBD - perlu verifikasi ke pemilik produk.
