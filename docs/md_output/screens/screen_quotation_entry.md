---
name: screen_quotation_entry
description: Entry/edit Sales Quotation — konsolidasi Inquiry berharga jadi 1 dokumen penawaran, publish, lalu buat Customer Order.
---

# Quotation Entry

**File**: `resources/views/pages/quotation/entry.blade.php`
**Route**: `quotation.entry` — `GET /quotation/{quotation}/edit`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery

## Tujuan
Layar tempat Sales menyusun 1 dokumen Quotation untuk 1 customer, menarik baris dari Inquiry yang sudah berharga (costing `published_flag=true`), menambah baris bebas (jasa/ongkos), mengedit header & harga selama status masih `DRAFT`, lalu menerbitkan (`publish()`, status jadi `PUBLISHED` — setelah itu isi terkunci). Dari Quotation yang sudah `PUBLISHED`, Sales bisa langsung membuat/membuka Customer Order (Mode #1, auto dari Quotation) via tombol "Buat Customer Order". Aktor: Sales.

## Field & Kolom
**Header** (editable saat `DRAFT` via `updateHeader()`):
- `issued_date` (date-picker)
- `valid_until` (date-picker)
- `payment_term` (select, opsi dari `FinPaymentTerm::where('activate', true)`)
- `tax_scheme` (select: `EXCL` Exclusive PPN 11% / `INCL` Inclusive PPN 11%)
- `notes` (textarea, syarat & ketentuan)
- Readonly: `quotation_no`, `customer_name`/`customer_code`, `currency_code`

**Baris item** (`quo_quotation_item`, editable saat `DRAFT` via `updateItem()`, field allowed: `description`, `qty`, `qty_unit`, `unit_price`, `discount_pct`):
- `source_type`: `INQUIRY_TIER` (dari Inquiry) / `INQUIRY_TOOLING` (badge "TOOLING") / `FREEFORM` (badge "BEBAS", description bisa diedit bebas)
- `inquiry_id` (ref, tampil sebagai `inquiry_no`), `description`, `spec_note`, `qty`, `qty_unit`, `unit_price`, `discount_pct`, `subtotal`

**Picker "Tarik Inquiry Lain"** — computed `availableInquiries()`: Inquiry dengan `customer_id` sama dengan Quotation, `costing.published_flag = true`, dan belum ditarik (`whereNotIn` id_inquiry yang sudah source_type=INQUIRY_TIER di quotation ini); filter live via `addInquirySearch` (cocok `inquiry_no` atau `product_draft_name`), limit 20, urut `inquiry_date` desc.

## Aksi / Method
- `mount(int $quotation)` — set `quotationId`.
- `quotation()` [Computed] — load `QuoQuotation` + `customer`, `items` (urut `line_seq`).
- `paymentTerms()` [Computed] — `FinPaymentTerm` aktif, urut `sort_order`.
- `availableInquiries()` [Computed] — lihat di atas.
- `totals()` [Computed] — panggil `$quotation->totals()` (subtotal, dpp, ppn_pct, ppn, grand).
- `addInquiry(int $inquiryId)` — guard `doc_status===DRAFT`; panggil `QuotationBuilder::addInquiry()`.
- `addFreeform()` — tambah baris `FREEFORM` kosong (qty=1, unit_price=0).
- `removeItem(int $itemId)` — guard DRAFT; hapus baris apa pun (termasuk INQUIRY_TIER) via tombol ✕.
- `updateItem(int $itemId, string $field, $value)` — guard DRAFT; field allowed: description/qty/qty_unit/unit_price/discount_pct; panggil `$item->recalcSubtotal()`.
- `updateHeader(string $field, $value)` — guard DRAFT; field allowed: issued_date/valid_until/payment_term/tax_scheme/notes.
- `publish()` — guard DRAFT & items tidak kosong; set `doc_status='PUBLISHED'`.
- `createOrOpenCo()` — panggil `App\Services\Sales\CoOrderBuilder::findOrCreateFromQuotation($this->quotation)`, redirect ke `co.entry`.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\QuoQuotation` → `tm2sdi.quo_quotation` (PK `id_quotation`)
- `App\Models\Sdi\QuoQuotationItem` → `tm2sdi.quo_quotation_item` (PK `id_item`)
- `App\Models\Sdi\IqpInquiry` → `tm2sdi.iqp_inquiry` (untuk picker + relasi `costing`)
- `App\Models\Master\FinPaymentTerm` → tm2mst (koneksi `mst`) — lintas-DB, diquery terpisah (pola 1 CLAUDE.md §2)
- `QuoQuotation belongsTo customer` (CustCustomer, tm2mst) — lintas-DB, relasi Eloquent query terpisah.

## Alur / Business Logic Penting
- Publish mengunci seluruh isi (header + item) — semua form field jadi readonly begitu `doc_status !== 'DRAFT'`.
- Cetak (link ke `quotation.print`) hanya muncul setelah `PUBLISHED`.
- "Buat Customer Order" memanggil `CoOrderBuilder::findOrCreateFromQuotation()` — 1 Quotation hanya boleh punya 1 CO (locked, FK `co_order.quotation_id` NOT NULL + UNIQUE); kalau CO sudah ada untuk quotation ini, method itu membuka yang sudah ada, bukan membuat baru.
- Baris `INQUIRY_TIER` bisa dihapus dari sini (removeItem tidak membedakan source_type) selama masih DRAFT.

## Catatan
- Tidak ada guard mencegah publish tanpa `payment_term`/`valid_until` terisi di kode ini — TBD apakah validasi tambahan diperlukan.
- Tidak ditemukan logic diskon per-tier di layar ini (diskon per baris cuma `discount_pct` flat); jenjang qty/tier alternatif hanya tampil di `quotation/print.blade.php` sebagai info tambahan, bukan bagian entry.
