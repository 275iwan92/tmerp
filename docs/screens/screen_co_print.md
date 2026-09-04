---
name: screen_co_print
description: Cetak Nota Customer Order — konfirmasi pesanan resmi ke customer setelah CO Confirmed.
---

# Nota Customer Order

**File**: `resources/views/pages/co/print.blade.php`
**Route**: `co.print` — `GET /co/{order}/print`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery

## Tujuan
Cetakan Nota Customer Order — order acknowledgment/konfirmasi resmi pesanan ke customer setelah CO di-Confirm. Gate akses: hanya `doc_status==='CONFIRMED'` (CO cuma 2 status: DRAFT/CONFIRMED); kalau belum, redirect ke `co.entry` + flash error. Pola sama persis dengan `quotation/print.blade.php` (komponen `resources/views/components/print/*`), palet warna hijau `#1f6f4a` (beda dari navy Quotation). Aktor: Sales (cetak untuk dikirim), Customer (penerima).

## Field & Kolom
Read-only. Menampilkan:
- Kop surat (`OrgCompany::where('activate', true)->first()` — **catatan**: berbeda dari `quotation/print.blade.php`/`dispatch/do/print.blade.php` yang pakai `where('company_code', 'TM')`), No. CO, tanggal, No. PO Customer (kalau ada), Ref. Quotation.
- Party "Kepada Yth." (customer nama+kode) dan "Alamat Pengiriman" (`ship_address_label`/`ship_address_detail`/`ship_postal_code`).
- Tabel Rincian Pesanan: No. urut (`line_seq`), deskripsi + `spec_note`, qty+unit, harga satuan (+diskon%), subtotal.
- Blok totals (`$order->totals()`): Subtotal Item, Diskon (jika >0), Biaya Eksternal (jika >0), DPP, PPN, PPh 23/WHT23 (jika >0), TOTAL TAGIHAN (`net_receivable`).
- Catatan (`order->notes` atau pesan default: "Pesanan ini mengikat setelah CO dikonfirmasi...").
- Blok tanda tangan (Sales/`updated_by`, Customer) + footer cetak.

## Aksi / Method
- `mount(int $order)` — guard `doc_status==='CONFIRMED'`, else redirect ke `co.entry` + flash error.
- `order()` [Computed] — `CoOrder::with(['items', 'quotation.customer', 'externalCosts'])`.
- `totals()` [Computed] — `$order->totals()`.
- `company()` [Computed] — `OrgCompany::where('activate', true)->first()`.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\CoOrder` → `tm2sdi.co_order`, relasi `items`, `quotation.customer` (lintas-DB ke `tm2mst.cust_customer`), `externalCosts`.
- `App\Models\Master\OrgCompany` → tm2mst (koneksi `mst`) — lintas-DB, query terpisah.

## Alur / Business Logic Penting
- Tahap dalam alur besar: Quotation PUBLISHED → CO dibuat (snapshot) → **CO CONFIRMED → cetak Nota CO (layar ini)** → CPDEL → Delivery Order.
- Totals mengikuti `CoOrder::totals()` (sama logic dengan yang dipakai di `co/entry.blade.php`) — termasuk komponen PPh 23 (WHT, 2% dari subtotal SERVICE) yang tidak ada di cetakan Quotation.

## Catatan
Referensi komentar kode menyebut `docs/BACKLOG.md §12.7 "Nota Quotation & Nota CO print"` sebagai sumber requirement — Nota CO ini melengkapi Nota Quotation yang sudah ada lebih dulu.
