---
name: screen_co_entry
description: Entry/edit Customer Order — snapshot item dari Quotation, atur shipment/external cost, Confirm memicu engine Customer Plan Delivery.
---

# Customer Order — Entry

**File**: `resources/views/pages/co/entry.blade.php`
**Route**: `co.entry` — `GET /co/{order}/edit`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery

## Tujuan
Layar detail 1 Customer Order (CO). Item CO lahir sebagai **snapshot** dari item Quotation saat CO dibuat (via `CoOrderBuilder::findOrCreateFromQuotation()`, dipicu dari `quotation/entry.blade.php`) — bukan live-linked. Selama `doc_status='DRAFT'`, Sales bisa: edit header (tanggal, No. PO Customer, alamat kirim, diskon qty, catatan), tambah baris manual (Produk/Jasa) di luar snapshot, edit Due Date semua baris (termasuk baris snapshot), atur jadwal Delivery manual per item PRODUCT, kelola External Cost (trucking/ekspedisi/lainnya), dan meng-Confirm CO. Setelah `CONFIRMED`, seluruh isi terkunci dan tombol cetak Nota muncul. Aktor: Sales.

## Field & Kolom
**Header Dokumen** (editable saat DRAFT via `updateHeader()`, field allowed: `co_date`, `customer_po_no`, `ship_address_detail`, `discount_qty_pct`, `notes`):
- Readonly: `co_no`, Ref. Quotation (`quotation.quotation_no`), Order Type (`orderType` — Product Only/Service Only/Mixed), Mata Uang (`quotation.currency_code`), Customer (nama+kode), Kode Pos.
- Editable: `co_date`, `customer_po_no`, alamat kirim via `<livewire:address-picker>` (event `address-picked`, field-name `co-ship-address` → set `ship_area_id`/`ship_area_level`/`ship_address_label`/`ship_postal_code`), `ship_address_detail`.

**Tab Product** (`co_order_item`, guard DRAFT):
- Baris snapshot (`quotation_item_id !== null`): description/qty/unit_price/discount_pct/subtotal readonly (locked), hanya `due_date` yang editable (WAJIB terisi utk baris `line_kind='PRODUCT'` sebelum Confirm — border merah + label "wajib diisi" kalau kosong).
- Baris manual (`quotation_item_id === null`, ditambah via `addItem('PRODUCT'|'SERVICE')`): description/qty/unit_price editable penuh, bisa dihapus (`removeItem`).
- Kolom: Produk/Jasa, Type (badge P/S), Qty, Harga, Disc%, Subtotal, Due Date, aksi hapus.

**Tab Delivery** (per item PRODUCT, `co_order_delivery`, guard DRAFT): `shipment_type` (select FULL/PARTIAL), `ship_date`, `ship_qty` — tambah via "+ Jadwal Kirim" (`addDelivery`), hapus via `removeDelivery`.

**Tab External Cost** (`co_order_external_cost`, guard DRAFT): `category` (select Trucking/Expedition/Lainnya), `description`, `nominal`, `tax_treatment` (select TAXABLE/REIMBURSEMENT) — tambah via `addExternalCost`, hapus via `removeExternalCost`.

**Tab History Flow**: timeline read-only Inquiry → Quotation → Customer Order (dibangun dinamis dari `historyTimeline()`, bukan hardcode).

**Panel kanan**: Document Discount (`discount_doc_pct` readonly dari IQ, `discount_qty_pct` editable), Summary & Tax (item subtotal, discounts, external cost, DPP, PPN, WHT23, Net Receivable — dari `$order->totals()`), Order Notes (textarea).

## Aksi / Method
- `mount(int $order)`.
- `order()` [Computed] — `CoOrder::with(['quotation.customer', 'items' (+inquiry, deliveries), 'externalCosts'])`.
- `totals()` [Computed] — `$order->totals()`.
- `itemsMissingDueDate()` [Computed] — item `PRODUCT` dengan `due_date` null (guard Confirm).
- `orderType()` [Computed].
- `historyTimeline()` [Computed].
- `onAddressPicked(...)` [`#[On('address-picked')]`] — guard DRAFT & `fieldName==='co-ship-address'`.
- `updateHeader(string $field, $value)`.
- `addItem(string $kind)` / `removeItem(int $itemId)` (hanya baris `quotation_item_id===null` bisa dihapus) / `updateItem(int $itemId, string $field, $value)` (field `due_date` bisa diedit di baris manapun; field lain hanya baris manual).
- `addDelivery(int $itemId)` / `updateDelivery(int $deliveryId, string $field, $value)` / `removeDelivery(int $deliveryId)`.
- `addExternalCost()` / `updateExternalCost(int $costId, string $field, $value)` / `removeExternalCost(int $costId)`.
- `confirmCo()` — lihat Alur di bawah.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\CoOrder` → `tm2sdi.co_order` (PK `id_order`)
- `App\Models\Sdi\CoOrderItem` → `tm2sdi.co_order_item` (PK `id_item`)
- `App\Models\Sdi\CoOrderDelivery` → `tm2sdi.co_order_delivery` (PK `id_delivery`)
- `App\Models\Sdi\CoOrderExternalCost` → `tm2sdi.co_order_external_cost` (PK `id_cost`)
- `CoOrder belongsTo quotation` (`QuoQuotation`, `tm2sdi`), `quotation.customer` (lintas-DB ke `tm2mst.cust_customer`, Eloquent relationship query terpisah).

## Alur / Business Logic Penting
- **Snapshot dari Quotation**: item CO adalah salinan beku dari `quo_quotation_item` (dibuat `CoOrderBuilder`), bukan live-linked — harga/qty CO boleh menyimpang dari Quotation setelahnya kalau memang diperlukan.
- **Guard Confirm** (keputusan 2026-09-03): `due_date` WAJIB terisi di semua baris `PRODUCT` sebelum CO bisa Confirm (`itemsMissingDueDate`) — tombol Confirm CO disabled + warning kalau ada yang kosong. Tujuannya supaya ENGINE CPDEL selalu punya tanggal dasar jadwal.
- **`confirmCo()` — ENGINE Customer Plan Delivery (CPDEL)**: guard `doc_status==='DRAFT'`, items tidak kosong, `itemsMissingDueDate` kosong. Dalam transaction (`tm2sdi`): set `doc_status='CONFIRMED'`; untuk tiap item `PRODUCT` yang **belum punya baris `co_order_delivery` sama sekali** (`deliveries->isEmpty()`), buat 1 baris default (`shipment_type='FULL'`, `ship_date`=`due_date` item, `ship_qty`=qty penuh, `status='PLANNED'`). Item yang sudah diisi manual via tab Delivery (saat masih Draft) TIDAK disentuh — itu override Sales, engine hanya mengisi yang kosong. Hasilnya lanjut diatur (Split/Gabung) di `co/delivery-plan.blade.php`.
- Setelah Confirm: seluruh field jadi readonly, tombol "🖨 Cetak Nota" (→ `co.print`) muncul.

## Catatan
Tidak ditemukan gap/TODO eksplisit dalam komentar kode file ini.
